<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Models\VoucherCode;
use ZippyCrm\Services\TierRegistry;

defined( 'ABSPATH' ) || exit;

/**
 * Voucher lifecycle: create, publish, pause/resume.
 *
 * `publish()` syncs the WC coupon (creates if missing) and fires
 * `crm_voucher_published` for the Notifications feature to pick up later.
 */
final class VoucherService {

	public const COUPON_META_VOUCHER_ID = '_zc_voucher_id';

	/** Distribution modes — see crm_vouchers.sql for semantics. */
	public const MODE_SINGLE       = 'single_code';
	public const MODE_MULTI_PUBLIC = 'multi_code_public';

	public const MODES = [ self::MODE_SINGLE, self::MODE_MULTI_PUBLIC ];

	/** Synthetic placeholder code used in crm_vouchers.code for multi-code rows. */
	private const MULTI_CODE_PLACEHOLDER_PREFIX = 'ZC_MULTI_';

	/** Code generation: 6-char alphanumeric suffix for auto-generated codes. */
	private const AUTO_CODE_SUFFIX_LENGTH = 6;

	public static function publish( int $voucher_id ): bool {
		$voucher = Voucher::find( $voucher_id );
		if ( ! $voucher ) {
			return false;
		}

		$mode = (string) ( $voucher['distribution_mode'] ?? self::MODE_SINGLE );

		if ( $mode === self::MODE_MULTI_PUBLIC ) {
			// Mint one WC_Coupon per pre-generated code. Each is single-use
			// (max_uses=1) so the same code can never be redeemed twice.
			self::sync_multi_code_coupons( $voucher );
		} else {
			self::sync_wc_coupon( $voucher );
		}

		Voucher::update_status( $voucher_id, 'active' );

		do_action( 'crm_voucher_published', $voucher_id );

		return true;
	}

	/**
	 * For each crm_voucher_codes row, ensure a single-use WC_Coupon exists.
	 * Idempotent — safe to re-run on re-publish.
	 *
	 * Each minted coupon copies the voucher's discount/eligibility settings
	 * but forces `usage_limit=1` and `usage_limit_per_user=1` regardless of
	 * the parent's settings. The whole point of multi-code is that each
	 * code is single-use.
	 */
	private static function sync_multi_code_coupons( array $voucher ): void {
		global $wpdb;
		$decoded = Voucher::decode_json_fields( $voucher );
		$rows    = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT code FROM ' . $wpdb->prefix . VoucherCode::TABLE
				. ' WHERE voucher_id = %d',
				(int) $voucher['id']
			),
			ARRAY_A
		);
		if ( ! $rows ) {
			return;
		}
		foreach ( $rows as $r ) {
			$code      = (string) $r['code'];
			$coupon_id = self::resolve_existing_wc_coupon_id( $code );
			$coupon    = $coupon_id ? new \WC_Coupon( $coupon_id ) : new \WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( (string) $decoded['discount_type'] );
			$coupon->set_amount( (string) $decoded['discount_value'] );
			$coupon->set_description( (string) ( $decoded['description'] ?? $decoded['title'] ) );
			$coupon->set_minimum_amount( (string) ( $decoded['min_order_amount'] ?? 0 ) );
			$coupon->set_maximum_amount( (string) ( $decoded['max_order_amount'] ?? 0 ) );
			$coupon->set_individual_use(     (bool) ( $decoded['individual_use']     ?? true ) );
			$coupon->set_exclude_sale_items( (bool) ( $decoded['exclude_sale_items'] ?? false ) );
			$coupon->set_free_shipping(      (bool) ( $decoded['free_shipping']      ?? false ) );
			// Force single-use — defining characteristic of multi-code.
			$coupon->set_usage_limit( 1 );
			$coupon->set_usage_limit_per_user( 1 );
			$coupon->set_limit_usage_to_x_items( (int) ( $decoded['limit_usage_to_x_items'] ?? 0 ) );
			$coupon->set_product_ids(                 (array) ( $decoded['product_ids']                 ?? [] ) );
			$coupon->set_excluded_product_ids(        (array) ( $decoded['excluded_product_ids']        ?? [] ) );
			$coupon->set_product_categories(          (array) ( $decoded['product_categories']          ?? [] ) );
			$coupon->set_excluded_product_categories( (array) ( $decoded['excluded_product_categories'] ?? [] ) );
			if ( ! empty( $decoded['expires_at'] ) ) {
				$coupon->set_date_expires( strtotime( $decoded['expires_at'] . ' UTC' ) );
			} else {
				$coupon->set_date_expires( null );
			}
			$coupon->update_meta_data( self::COUPON_META_VOUCHER_ID, (int) $voucher['id'] );
			$coupon->save();
		}
	}

	public static function pause( int $voucher_id ): bool {
		return Voucher::update_status( $voucher_id, 'paused' );
	}

	public static function resume( int $voucher_id ): bool {
		return Voucher::update_status( $voucher_id, 'active' );
	}

	/**
	 * Creates the WC coupon if it doesn't exist; updates every field that we
	 * mirror onto WC's coupon model on each call. Idempotent — safe to call
	 * on every publish or update.
	 *
	 * Fields NOT covered by WC native: `allowed_hours` (day-of-week + hour
	 * window). That restriction is enforced by Hooks\VoucherHourWindow, which
	 * walks the voucher row at validation time using the `_zc_voucher_id`
	 * meta we write here.
	 */
	public static function sync_wc_coupon( array $voucher ): int {
		$voucher = Voucher::decode_json_fields( $voucher );

		$code      = (string) $voucher['code'];
		$coupon_id = self::resolve_existing_wc_coupon_id( $code );

		$coupon = $coupon_id ? new \WC_Coupon( $coupon_id ) : new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( (string) $voucher['discount_type'] );
		$coupon->set_amount( (string) $voucher['discount_value'] );
		$coupon->set_description( (string) ( $voucher['description'] ?? $voucher['title'] ) );

		// Spend bounds
		$coupon->set_minimum_amount( (string) ( $voucher['min_order_amount'] ?? 0 ) );
		$coupon->set_maximum_amount( (string) ( $voucher['max_order_amount'] ?? 0 ) );

		// Behavior toggles
		$coupon->set_individual_use(     (bool) ( $voucher['individual_use']     ?? true ) );
		$coupon->set_exclude_sale_items( (bool) ( $voucher['exclude_sale_items'] ?? false ) );
		$coupon->set_free_shipping(      (bool) ( $voucher['free_shipping']      ?? false ) );

		// Usage limits — `set_*` accept 0 / null for "unlimited" too
		$coupon->set_usage_limit(           (int) ( $voucher['max_uses']               ?? 0 ) );
		$coupon->set_usage_limit_per_user(  (int) ( $voucher['usage_limit_per_user']   ?? 0 ) );
		$coupon->set_limit_usage_to_x_items((int) ( $voucher['limit_usage_to_x_items'] ?? 0 ) );

		// Allow / block lists. WC expects arrays; nulls become empty arrays.
		// Email restrictions: our column may store rich objects ({email, first_name,
		// last_name}) so admins can see chips with names. WC only cares about
		// emails — flatten before handing it over.
		//
		// Audience-mode rule (v1.11.0): only push email_restrictions into the
		// WC coupon when audience_mode='email'. For 'public' or 'tier' modes,
		// WC's email gate must stay open — tier targeting is enforced by our
		// claim filter, not WC.
		$audience_mode = (string) ( $voucher['audience_mode'] ?? 'public' );
		$wc_emails     = $audience_mode === 'email'
			? self::email_restrictions_for_wc( $voucher['email_restrictions'] ?? [] )
			: [];
		$coupon->set_email_restrictions(          $wc_emails );
		$coupon->set_product_ids(                 (array) ( $voucher['product_ids']                 ?? [] ) );
		$coupon->set_excluded_product_ids(        (array) ( $voucher['excluded_product_ids']        ?? [] ) );
		$coupon->set_product_categories(          (array) ( $voucher['product_categories']          ?? [] ) );
		$coupon->set_excluded_product_categories( (array) ( $voucher['excluded_product_categories'] ?? [] ) );

		if ( ! empty( $voucher['expires_at'] ) ) {
			$coupon->set_date_expires( strtotime( $voucher['expires_at'] . ' UTC' ) );
		} else {
			$coupon->set_date_expires( null );
		}

		$coupon->update_meta_data( self::COUPON_META_VOUCHER_ID, (int) $voucher['id'] );

		return (int) $coupon->save();
	}

	/* ============================================================
	 * Admin write paths
	 *
	 * These return the new/updated voucher row on success and a \WP_Error on
	 * validation failure (matching the PointsEngine pattern). Controllers map
	 * the WP_Error straight to a RestResponse::error envelope.
	 * ============================================================ */

	/**
	 * Create a new voucher in 'draft' status.
	 *
	 * Refuses if the code already exists (in our table OR as a WC coupon —
	 * publishing later would clobber the unrelated coupon, so block it now).
	 *
	 * @return array<string,mixed>|\WP_Error  The new row on success.
	 */
	public static function create_draft( array $data, int $user_id ) {
		$mode = self::resolve_mode( $data );
		$data['distribution_mode'] = $mode;

		// For multi-code, the 'code' field is unused — but the schema still
		// requires it (NOT NULL + UNIQUE). Generate a synthetic placeholder
		// up-front; the per-code rows go in crm_voucher_codes.
		if ( $mode === self::MODE_MULTI_PUBLIC ) {
			$data['code'] = self::generate_multi_placeholder();
			// `max_uses` is meaningless on the parent voucher; the slot count
			// IS the number of codes. Validate the codes list now.
			$codes_err = self::validate_codes_list( $data, true );
			if ( $codes_err instanceof \WP_Error ) {
				return $codes_err;
			}
		}

		$err = self::validate_payload( $data, true );
		if ( $err instanceof \WP_Error ) {
			return $err;
		}

		$code = strtoupper( (string) $data['code'] );

		// Single-code: enforce uniqueness against our table + WC coupons.
		// Multi-code: synthetic placeholder, only need uniqueness against our
		// own table (it's never a real WC coupon code).
		if ( Voucher::find_by_code( $code ) ) {
			return new \WP_Error(
				'voucher_code_taken',
				__( 'A voucher with this code already exists.', 'zippy-crm' ),
				[ 'status' => 409 ]
			);
		}
		if ( $mode === self::MODE_SINGLE
			&& function_exists( 'wc_get_coupon_id_by_code' )
			&& wc_get_coupon_id_by_code( $code )
		) {
			return new \WP_Error(
				'wc_coupon_exists',
				__( 'A WooCommerce coupon with this code already exists. Pick a different code.', 'zippy-crm' ),
				[ 'status' => 409 ]
			);
		}

		$data['code']   = $code;
		$data['status'] = 'draft';
		$id = Voucher::create( $data, $user_id );

		if ( ! $id ) {
			return new \WP_Error( 'voucher_create_failed', __( 'Could not create voucher.', 'zippy-crm' ), [ 'status' => 500 ] );
		}

		// Multi-code: mint the per-code rows now, while still in draft. The
		// coupons themselves don't get created until publish() — admins can
		// edit the code list while drafting without polluting WC.
		if ( $mode === self::MODE_MULTI_PUBLIC ) {
			$codes = self::resolve_codes_list( $data, $id );
			$inserted = VoucherCode::bulk_insert( $id, $codes );
			if ( $inserted !== count( $codes ) ) {
				// Partial insert means a UNIQUE collision — clean up and refuse.
				VoucherCode::delete_for_voucher( $id );
				Voucher::delete( $id );
				return new \WP_Error(
					'voucher_code_collision',
					__( 'One or more generated codes collided with existing codes. Try again or use different prefixes.', 'zippy-crm' ),
					[ 'status' => 409 ]
				);
			}
		}

		return Voucher::find( $id );
	}

	/* ============================================================
	 * Multi-code helpers
	 * ============================================================ */

	private static function resolve_mode( array $data ): string {
		$mode = (string) ( $data['distribution_mode'] ?? self::MODE_SINGLE );
		return in_array( $mode, self::MODES, true ) ? $mode : self::MODE_SINGLE;
	}

	private static function generate_multi_placeholder(): string {
		// Synthetic; only used to satisfy crm_vouchers.code NOT NULL + UNIQUE.
		return self::MULTI_CODE_PLACEHOLDER_PREFIX . strtoupper( wp_generate_password( 10, false, false ) );
	}

	/**
	 * Validates a multi-code voucher's codes list.
	 *
	 *   - `codes` is optional in the payload; when missing, we auto-generate
	 *     `slots` codes from the prefix at resolve time.
	 *   - `slots` (count) is required; must be 1-1000.
	 *   - If admin provided codes, count must match `slots` and entries must
	 *     match the same /^[A-Z0-9_-]{3,50}$/ shape as a single-code voucher.
	 *   - We DON'T check uniqueness against WC here — bulk_insert's UNIQUE
	 *     constraint is the authoritative guard. Pre-checking would race with
	 *     a parallel admin save anyway.
	 */
	private static function validate_codes_list( array $data, bool $is_create ): ?\WP_Error {
		$slots = (int) ( $data['slots'] ?? 0 );
		if ( $is_create && ( $slots < 1 || $slots > 1000 ) ) {
			return new \WP_Error(
				'voucher_bad_slots',
				__( 'Multi-code vouchers need between 1 and 1000 slots.', 'zippy-crm' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! empty( $data['codes'] ) ) {
			$codes = (array) $data['codes'];
			if ( count( $codes ) !== $slots ) {
				return new \WP_Error(
					'voucher_codes_count_mismatch',
					/* translators: 1: slots, 2: provided code count */
					sprintf( __( 'Slots (%1$d) doesn\'t match number of codes provided (%2$d).', 'zippy-crm' ), $slots, count( $codes ) ),
					[ 'status' => 400 ]
				);
			}
			foreach ( $codes as $i => $code ) {
				$normalized = strtoupper( (string) $code );
				if ( ! preg_match( '/^[A-Z0-9_\-]{3,50}$/', $normalized ) ) {
					return new \WP_Error(
						'voucher_bad_code_in_list',
						/* translators: %d: 1-based row number */
						sprintf( __( 'Code at row %d is invalid (3-50 chars, A-Z 0-9 _ -).', 'zippy-crm' ), $i + 1 ),
						[ 'status' => 400 ]
					);
				}
			}
			$unique = array_unique( array_map( 'strtoupper', $codes ) );
			if ( count( $unique ) !== count( $codes ) ) {
				return new \WP_Error(
					'voucher_codes_duplicate',
					__( 'The codes list contains duplicates.', 'zippy-crm' ),
					[ 'status' => 400 ]
				);
			}
		}
		return null;
	}

	/**
	 * Builds the final list of N codes to mint:
	 *   - If admin provided `codes`, normalize (uppercase + dedupe).
	 *   - Otherwise, auto-generate `slots` codes prefixed with `prefix` (or
	 *     a derivation of the voucher title) + a random 6-char suffix.
	 *
	 * @return array<int,string>
	 */
	private static function resolve_codes_list( array $data, int $voucher_id ): array {
		if ( ! empty( $data['codes'] ) ) {
			return array_values( array_unique( array_map(
				static fn( $c ) => strtoupper( (string) $c ),
				(array) $data['codes']
			) ) );
		}

		$slots  = (int) ( $data['slots'] ?? 0 );
		$prefix = isset( $data['code_prefix'] ) && $data['code_prefix'] !== ''
			? strtoupper( (string) $data['code_prefix'] )
			: self::derive_prefix( $data['title'] ?? '' );

		$out = [];
		$attempt = 0;
		while ( count( $out ) < $slots ) {
			$candidate = $prefix . '-' . strtoupper( wp_generate_password( self::AUTO_CODE_SUFFIX_LENGTH, false, false ) );
			$out[ $candidate ] = true; // dedupe via keys
			if ( ++$attempt > $slots * 4 ) {
				break; // pathological — caller handles short list as a failure
			}
		}
		return array_keys( $out );
	}

	private static function derive_prefix( string $title ): string {
		$slug = preg_replace( '/[^A-Za-z0-9]/', '', $title );
		$slug = strtoupper( substr( $slug ?: 'CRM', 0, 8 ) );
		return $slug !== '' ? $slug : 'CRM';
	}

	/**
	 * Update voucher fields. `code` is immutable post-creation (the WC coupon
	 * is keyed off it). Status changes go through publish/pause/resume; this
	 * accepts a status only when the value is one of the editable transitions.
	 *
	 * If the voucher is currently active, re-syncs the WC coupon so amount /
	 * limit / expiry edits propagate immediately.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function update( int $id, array $data ) {
		$existing = Voucher::find( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'voucher_not_found', __( 'Voucher not found.', 'zippy-crm' ), [ 'status' => 404 ] );
		}

		// Code is immutable; silently drop if present.
		unset( $data['code'], $data['created_by'], $data['created_at'], $data['uses_count'] );

		// Status edits via this path only allowed in draft mode (admin form save).
		if ( isset( $data['status'] ) && $data['status'] !== 'draft' ) {
			unset( $data['status'] );
		}

		$err = self::validate_payload( $data, false );
		if ( $err instanceof \WP_Error ) {
			return $err;
		}

		Voucher::update( $id, $data );

		$updated = Voucher::find( $id );
		if ( $updated && (string) $updated['status'] === 'active' ) {
			self::sync_wc_coupon( $updated );
		}
		return $updated;
	}

	/**
	 * Hard delete. Refuses if any claims exist (data we can't restore) or if
	 * the voucher is anything other than draft (active vouchers have a live
	 * WC coupon and customer-facing visibility).
	 *
	 * @return true|\WP_Error
	 */
	public static function delete( int $id ) {
		$voucher = Voucher::find( $id );
		if ( ! $voucher ) {
			return new \WP_Error( 'voucher_not_found', __( 'Voucher not found.', 'zippy-crm' ), [ 'status' => 404 ] );
		}
		if ( (string) $voucher['status'] !== 'draft' ) {
			return new \WP_Error(
				'voucher_not_draft',
				__( 'Only draft vouchers can be deleted. Pause and try again, or just keep it paused.', 'zippy-crm' ),
				[ 'status' => 409 ]
			);
		}
		if ( VoucherClaim::count_for_voucher( $id ) > 0 ) {
			return new \WP_Error(
				'voucher_has_claims',
				__( 'This voucher has been claimed and cannot be deleted.', 'zippy-crm' ),
				[ 'status' => 409 ]
			);
		}

		// Cascade: drop pre-generated multi-code rows. Single-code vouchers
		// have zero rows here so this is a cheap no-op.
		VoucherCode::delete_for_voucher( $id );

		$ok = Voucher::delete( $id );
		if ( ! $ok ) {
			return new \WP_Error( 'voucher_delete_failed', __( 'Could not delete voucher.', 'zippy-crm' ), [ 'status' => 500 ] );
		}
		return true;
	}

	/**
	 * Duplicate a voucher as a draft. Code gets a `-COPY[-N]` suffix that
	 * is unique against both our table and existing WC coupons.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function duplicate( int $id, int $user_id ) {
		$source = Voucher::find( $id );
		if ( ! $source ) {
			return new \WP_Error( 'voucher_not_found', __( 'Voucher not found.', 'zippy-crm' ), [ 'status' => 404 ] );
		}

		$base = strtoupper( (string) $source['code'] ) . '-COPY';
		$candidate = $base;
		$suffix = 1;
		while ( Voucher::find_by_code( $candidate )
			|| ( function_exists( 'wc_get_coupon_id_by_code' ) && wc_get_coupon_id_by_code( $candidate ) )
		) {
			$suffix++;
			$candidate = $base . '-' . $suffix;
			if ( $suffix > 50 ) {
				return new \WP_Error( 'duplicate_code_exhausted', __( 'Could not find a free code suffix.', 'zippy-crm' ), [ 'status' => 500 ] );
			}
		}

		$payload = [
			'code'             => $candidate,
			'title'            => (string) $source['title'] . ' (Copy)',
			'description'      => $source['description'] ?? null,
			'discount_type'    => (string) $source['discount_type'],
			'discount_value'   => (float) $source['discount_value'],
			'min_order_amount' => (float) $source['min_order_amount'],
			'max_uses'         => (int) $source['max_uses'],
			'starts_at'        => $source['starts_at'] ?? null,
			'expires_at'       => $source['expires_at'] ?? null,
		];

		return self::create_draft( $payload, $user_id );
	}

	/* ============================================================
	 * Validation
	 * ============================================================ */

	/**
	 * Lightweight payload checks shared by create + update. On create we require
	 * code/title/discount_type/discount_value; on update we only validate the
	 * keys that are present (partial update).
	 *
	 * Inline rather than going through Support/Validator — first use; per the
	 * shared-components 3rd-use rule, we wait for two more callers before
	 * promoting.
	 */
	private static function validate_payload( array $data, bool $is_create ): ?\WP_Error {
		if ( $is_create ) {
			foreach ( [ 'code', 'title', 'discount_type', 'discount_value' ] as $required ) {
				if ( ! isset( $data[ $required ] ) || $data[ $required ] === '' ) {
					return new \WP_Error(
						'voucher_missing_field',
						/* translators: %s: field name */
						sprintf( __( 'Missing required field: %s.', 'zippy-crm' ), $required ),
						[ 'status' => 400 ]
					);
				}
			}
		}

		if ( isset( $data['code'] ) ) {
			$code = strtoupper( (string) $data['code'] );
			if ( ! preg_match( '/^[A-Z0-9_\-]{3,50}$/', $code ) ) {
				return new \WP_Error(
					'voucher_bad_code',
					__( 'Voucher code must be 3-50 chars, A-Z 0-9 _ -', 'zippy-crm' ),
					[ 'status' => 400 ]
				);
			}
		}

		if ( isset( $data['discount_type'] ) && ! in_array( (string) $data['discount_type'], Voucher::DISCOUNT_TYPES, true ) ) {
			return new \WP_Error( 'voucher_bad_discount_type', __( 'Invalid discount type.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		if ( isset( $data['discount_value'] ) && (float) $data['discount_value'] <= 0 ) {
			return new \WP_Error( 'voucher_bad_discount_value', __( 'Discount value must be greater than zero.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		if ( isset( $data['discount_type'], $data['discount_value'] )
			&& in_array( (string) $data['discount_type'], Voucher::PERCENT_DISCOUNT_TYPES, true )
			&& (float) $data['discount_value'] > 100
		) {
			return new \WP_Error( 'voucher_bad_percent', __( 'Percent discount cannot exceed 100.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		// Item-level discount types (`fixed_product`, `percent_product`) only
		// make sense scoped to specific products or categories. Without a
		// restriction WC treats them as cart-wide and the result is confusing
		// (e.g. "$5 off each item" applied to every line). Force the admin to
		// be explicit.
		//
		// On update we don't always receive the restriction fields in the
		// payload (partial update) — only enforce when the discount_type is
		// being set in this call. The restriction may already be saved.
		if ( isset( $data['discount_type'] )
			&& in_array( (string) $data['discount_type'], Voucher::ITEM_LEVEL_TYPES, true )
		) {
			$has_products   = ! empty( $data['product_ids'] );
			$has_categories = ! empty( $data['product_categories'] );
			if ( $is_create && ! $has_products && ! $has_categories ) {
				return new \WP_Error(
					'voucher_item_level_needs_restriction',
					__( 'Item-level discounts must restrict to specific products or categories.', 'zippy-crm' ),
					[ 'status' => 400 ]
				);
			}
		}

		// Audience targeting (v1.11.0). Mutually exclusive — voucher is either
		// public, restricted to specific emails, OR restricted to membership
		// tiers. The form sends one mode; we reject any payload that mixes
		// non-empty email_restrictions with non-empty allowed_tiers, and we
		// validate the audience_mode value itself.
		if ( isset( $data['audience_mode'] ) ) {
			$mode = (string) $data['audience_mode'];
			if ( ! in_array( $mode, Voucher::AUDIENCE_MODES, true ) ) {
				return new \WP_Error( 'voucher_bad_audience_mode', __( 'Invalid audience mode.', 'zippy-crm' ), [ 'status' => 400 ] );
			}

			$has_emails = ! empty( $data['email_restrictions'] );
			$has_tiers  = ! empty( $data['allowed_tiers'] );

			if ( $mode === 'tier' && ! $has_tiers ) {
				return new \WP_Error(
					'voucher_tier_audience_needs_tiers',
					__( 'Tier-restricted vouchers must include at least one tier.', 'zippy-crm' ),
					[ 'status' => 400 ]
				);
			}
			if ( $mode === 'email' && ! $has_emails ) {
				return new \WP_Error(
					'voucher_email_audience_needs_emails',
					__( 'Customer-restricted vouchers must include at least one customer or email.', 'zippy-crm' ),
					[ 'status' => 400 ]
				);
			}
			if ( $has_emails && $has_tiers ) {
				return new \WP_Error(
					'voucher_audience_conflict',
					__( 'A voucher can be restricted by customers OR membership tiers, not both.', 'zippy-crm' ),
					[ 'status' => 400 ]
				);
			}

			// Validate tier slugs against the live registry (admin can rename
			// or remove tiers; we don't want to persist stale slugs).
			if ( $mode === 'tier' && $has_tiers ) {
				$valid_slugs = TierRegistry::slugs();
				foreach ( (array) $data['allowed_tiers'] as $slug ) {
					if ( ! in_array( (string) $slug, $valid_slugs, true ) ) {
						return new \WP_Error(
							'voucher_unknown_tier',
							/* translators: %s: tier slug */
							sprintf( __( 'Unknown membership tier: %s', 'zippy-crm' ), (string) $slug ),
							[ 'status' => 400 ]
						);
					}
				}
			}
		}

		if ( isset( $data['min_order_amount'] ) && (float) $data['min_order_amount'] < 0 ) {
			return new \WP_Error( 'voucher_bad_min_order', __( 'Minimum order amount cannot be negative.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		if ( isset( $data['max_uses'] ) && (int) $data['max_uses'] < 0 ) {
			return new \WP_Error( 'voucher_bad_max_uses', __( 'Max uses cannot be negative.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		if ( isset( $data['starts_at'], $data['expires_at'] )
			&& $data['starts_at'] && $data['expires_at']
			&& strtotime( (string) $data['starts_at'] ) >= strtotime( (string) $data['expires_at'] )
		) {
			return new \WP_Error( 'voucher_bad_dates', __( 'Expiry must be after start date.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		return null;
	}

	/**
	 * Flatten our `email_restrictions` column into the plain email string
	 * array WC's `set_email_restrictions()` expects.
	 *
	 * Accepts both the legacy shape (array of strings) and the v1.10 shape
	 * (array of objects: { email, first_name?, last_name? }) so vouchers
	 * created before the customer-picker UI keep syncing correctly.
	 *
	 * Filters out empty / non-email entries; lower-cases everything (WC
	 * compares case-insensitively but admins might enter mixed case).
	 *
	 * @param mixed $entries
	 * @return array<int,string>
	 */
	/**
	 * Resolve an existing WC_Coupon post ID for `$code`, working around WC's
	 * stale code→ID cache. The cache (group 'coupons', key
	 * 'coupon_id_from_code_<md5>') is set when wc_get_coupon_id_by_code looks
	 * a code up, but is NOT invalidated by wp_delete_post / direct $wpdb
	 * deletes. Re-publishing a voucher whose WC coupon was deleted out-of-band
	 * (CRM admin → WC coupon screen → delete; or our own seeder reset) will
	 * otherwise hit a ghost ID and `new WC_Coupon($staleId)->save()` produces
	 * a coupon with default fields (amount=0, type=fixed_cart) instead of the
	 * voucher's actual values.
	 *
	 * Returns 0 if no live post exists — caller then constructs a fresh
	 * WC_Coupon. Returns the real ID otherwise.
	 */
	private static function resolve_existing_wc_coupon_id( string $code ): int {
		$id = (int) wc_get_coupon_id_by_code( $code );
		if ( $id <= 0 ) {
			return 0;
		}
		$post = get_post( $id );
		if ( $post && $post->post_type === 'shop_coupon' ) {
			return $id;
		}
		// Stale cache — flush and re-query so a freshly-recreated coupon
		// post (rare) is found, otherwise return 0.
		if ( class_exists( '\WC_Cache_Helper' ) ) {
			\WC_Cache_Helper::invalidate_cache_group( 'coupons' );
		}
		$id = (int) wc_get_coupon_id_by_code( $code );
		if ( $id <= 0 ) {
			return 0;
		}
		$post = get_post( $id );
		return ( $post && $post->post_type === 'shop_coupon' ) ? $id : 0;
	}

	private static function email_restrictions_for_wc( $entries ): array {
		if ( ! is_array( $entries ) ) {
			return [];
		}
		$out = [];
		foreach ( $entries as $entry ) {
			$email = is_array( $entry ) ? (string) ( $entry['email'] ?? '' ) : (string) $entry;
			$email = strtolower( trim( $email ) );
			if ( $email !== '' && strpos( $email, '@' ) !== false ) {
				$out[] = $email;
			}
		}
		return array_values( array_unique( $out ) );
	}
}
