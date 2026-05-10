<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;

defined( 'ABSPATH' ) || exit;

/**
 * Voucher lifecycle: create, publish, pause/resume.
 *
 * `publish()` syncs the WC coupon (creates if missing) and fires
 * `crm_voucher_published` for the Notifications feature to pick up later.
 */
final class VoucherService {

	public const COUPON_META_VOUCHER_ID = '_zc_voucher_id';

	public static function publish( int $voucher_id ): bool {
		$voucher = Voucher::find( $voucher_id );
		if ( ! $voucher ) {
			return false;
		}

		self::sync_wc_coupon( $voucher );

		Voucher::update_status( $voucher_id, 'active' );

		do_action( 'crm_voucher_published', $voucher_id );

		return true;
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
		$coupon_id = wc_get_coupon_id_by_code( $code );

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
		$coupon->set_email_restrictions(          self::email_restrictions_for_wc( $voucher['email_restrictions'] ?? [] ) );
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
		$err = self::validate_payload( $data, true );
		if ( $err instanceof \WP_Error ) {
			return $err;
		}

		$code = strtoupper( (string) $data['code'] );
		if ( Voucher::find_by_code( $code ) ) {
			return new \WP_Error(
				'voucher_code_taken',
				__( 'A voucher with this code already exists.', 'zippy-crm' ),
				[ 'status' => 409 ]
			);
		}
		if ( function_exists( 'wc_get_coupon_id_by_code' ) && wc_get_coupon_id_by_code( $code ) ) {
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

		return Voucher::find( $id );
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
			&& $data['discount_type'] === 'percent'
			&& (float) $data['discount_value'] > 100
		) {
			return new \WP_Error( 'voucher_bad_percent', __( 'Percent discount cannot exceed 100.', 'zippy-crm' ), [ 'status' => 400 ] );
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
