<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Models\VoucherCode;
use ZippyCrm\Services\ClaimHandler;
use ZippyCrm\Services\VoucherEligibility;
use ZippyCrm\Services\VoucherService;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php. Customer + admin handlers live in
 * one class because the shaping helpers are shared.
 */
final class VouchersController {

	private const ALLOWED_STATUS_FILTERS = [ 'draft', 'active', 'paused', 'expired' ];
	private const DEFAULT_PER_PAGE       = 20;
	private const MAX_PER_PAGE           = 100;

	/* ============================================================
	 * Customer
	 * ============================================================ */

	public static function list_available( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$rows = Voucher::list_available_for_user( $user_id );
		return RestResponse::ok( [
			'items' => array_map( [ self::class, 'shape_voucher' ], $rows ),
		] );
	}

	public static function list_my_claims( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$rows = VoucherClaim::list_for_user( $user_id );
		return RestResponse::ok( [
			'items' => array_map( [ self::class, 'shape_claim' ], $rows ),
		] );
	}

	/**
	 * History view: used + expired + revoked claims, paginated. Customer-facing
	 * "My Claims → History" sub-tab. Default 50 per page; client can override.
	 */
	public static function list_my_claims_history( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$per_page = (int) ( $request->get_param( 'per_page' ) ?: 50 );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$rows  = VoucherClaim::list_history_for_user( $user_id, $per_page, $offset );
		$total = VoucherClaim::count_history_for_user( $user_id );

		return RestResponse::ok( [
			'items'    => array_map( [ self::class, 'shape_history_claim' ], $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		] );
	}

	public static function claim( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$voucher_id = (int) $request['id'];
		$result     = ClaimHandler::claim( $voucher_id, $user_id );

		if ( ! $result['valid'] ) {
			$status = self::status_for_code( $result['code'] );
			return RestResponse::error( $result['code'], $result['message'], $status );
		}

		$voucher        = $result['voucher'];
		// Multi-code vouchers return a unique assigned code; single-code
		// vouchers fall back to the parent voucher's master code.
		$customer_code  = ! empty( $result['assigned_code'] )
			? (string) $result['assigned_code']
			: (string) $voucher['code'];
		$applied_to_cart = self::maybe_apply_to_cart( $customer_code );

		return RestResponse::ok( [
			'message'         => __( 'Voucher claimed successfully!', 'zippy-crm' ),
			'code'            => $customer_code,
			'title'           => (string) $voucher['title'],
			'discount_type'   => (string) $voucher['discount_type'],
			'discount_value'  => (float) $voucher['discount_value'],
			'expires_at'      => DateTimeHelper::mysql_to_iso( $voucher['expires_at'] ?? null ),
			'applied_to_cart' => $applied_to_cart,
		] );
	}

	/* ============================================================
	 * Checkout tray (v1.14.0)
	 *
	 * Single read endpoint that powers the "Your vouchers" widget at
	 * checkout. Returns three buckets:
	 *
	 *   - eligible_claimed   : claimed vouchers that apply to this cart
	 *   - eligible_unclaimed : public vouchers the customer hasn't claimed
	 *                         but could use right now (one-click claim+apply)
	 *   - locked             : claimed OR unclaimed vouchers that exist but
	 *                         don't apply to this cart, each with a friendly
	 *                         `reason` ("Spend $5 more to use", etc.)
	 *
	 * Two writes accompany the read: `apply` for claimed vouchers,
	 * `claim_and_apply` for the unclaimed bucket. Both go through
	 * `maybe_apply_to_cart` so the same coupon-stacking behavior our
	 * points-tender flow handles applies symmetrically here.
	 * ============================================================ */

	public static function checkout_tray( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}
		self::ensure_cart_loaded();

		$applied_codes = self::current_cart_codes();

		// Bucket 1: claimed vouchers. Includes "already applied" flag so the
		// React side can render an Applied state with a Remove CTA.
		$claimed       = VoucherClaim::list_for_user( $user_id );
		$eligible_yes  = [];
		$locked        = [];
		$claimed_ids   = [];
		foreach ( $claimed as $row ) {
			if ( (string) ( $row['claim_status'] ?? '' ) !== 'claimed' ) {
				continue; // 'used' / 'expired' claims belong on the history tab.
			}
			$claimed_ids[] = (int) $row['voucher_id'];
			$voucher_row   = Voucher::find( (int) $row['voucher_id'] );
			if ( ! $voucher_row ) {
				continue;
			}
			$eval = VoucherEligibility::evaluate_for_cart( $voucher_row );
			$item = self::shape_tray_item( $row['code'], $voucher_row, true, $eval, $applied_codes );
			if ( $eval['eligible'] ) {
				$eligible_yes[] = $item;
			} else {
				$locked[] = $item;
			}
		}

		// Bucket 2: publishable + audience-eligible vouchers the customer
		// hasn't claimed yet. `list_available_for_user` already filters by
		// audience (public/email/tier) and excludes claimed-by-this-user.
		$publishable = Voucher::list_available_for_user( $user_id );
		foreach ( $publishable as $voucher_row ) {
			if ( in_array( (int) $voucher_row['id'], $claimed_ids, true ) ) {
				continue;
			}
			$mode = (string) ( $voucher_row['distribution_mode'] ?? VoucherService::MODE_SINGLE );
			// Multi-code distribution: until the customer claims, no real
			// code exists for them. We can still evaluate cart fit against
			// the master voucher row, but skip if no remaining codes.
			if ( $mode === VoucherService::MODE_MULTI_PUBLIC && (int) ( $voucher_row['remaining_slots'] ?? 0 ) <= 0 ) {
				continue;
			}
			$eval = VoucherEligibility::evaluate_for_cart( $voucher_row );
			$item = self::shape_tray_item( null, $voucher_row, false, $eval, $applied_codes );
			if ( $eval['eligible'] ) {
				$eligible_yes[] = $item; // will be split client-side by `claimed:false` flag
			} else {
				$locked[] = $item;
			}
		}

		return RestResponse::ok( [
			'eligible' => $eligible_yes,
			'locked'   => $locked,
			// Surface for the widget's "X more locked" counter without
			// requiring it to count the array.
			'locked_count' => count( $locked ),
		] );
	}

	/**
	 * Apply a claimed voucher to the current cart. The customer must already
	 * own a claim row for this voucher — unclaimed vouchers go through
	 * `claim_and_apply` instead.
	 */
	public static function apply_to_cart( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$voucher_id = (int) $request['id'];
		$claim      = VoucherClaim::find_for_user( $voucher_id, $user_id );
		if ( ! $claim || (string) ( $claim['status'] ?? '' ) !== 'claimed' ) {
			return RestResponse::error( 'not_claimed', __( 'You must claim this voucher first.', 'zippy-crm' ), 404 );
		}

		$code = self::resolve_claim_code( $claim, $voucher_id );
		if ( $code === '' ) {
			return RestResponse::error( 'voucher_inactive', __( 'This voucher is no longer available.', 'zippy-crm' ), 410 );
		}

		self::ensure_cart_loaded();
		if ( ! self::maybe_apply_to_cart( $code ) ) {
			return RestResponse::error( 'apply_failed', __( 'This voucher cannot be applied to your current cart.', 'zippy-crm' ), 400 );
		}

		return RestResponse::ok( [ 'code' => $code, 'applied' => true ] );
	}

	/**
	 * Resolve the actual coupon code for a claim row. `find_for_user`'s
	 * SELECT doesn't include the code (it's split across two tables for
	 * single- vs multi-code vouchers), so we look it up here using the
	 * same COALESCE logic as `list_my_claims.sql`.
	 *
	 * Returns '' if neither lookup succeeds — caller must treat that as
	 * "voucher unavailable", NOT as "apply with empty code" (WC stores
	 * empty entries in the cart and bombs on the next page load).
	 */
	private static function resolve_claim_code( array $claim, int $voucher_id ): string {
		$code_id = isset( $claim['code_id'] ) ? (int) $claim['code_id'] : 0;
		if ( $code_id > 0 ) {
			$row = VoucherCode::find( $code_id );
			if ( $row && ! empty( $row['code'] ) ) {
				return (string) $row['code'];
			}
		}
		$voucher = Voucher::find( $voucher_id );
		return (string) ( $voucher['code'] ?? '' );
	}

	/**
	 * Claim then apply in a single call. For unclaimed-but-eligible vouchers
	 * the tray surfaces. If the claim succeeds but the apply fails (e.g.,
	 * the cart shifted between page-render and click), we keep the claim —
	 * the customer can apply it later from My Account.
	 */
	public static function claim_and_apply( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$voucher_id = (int) $request['id'];
		$result     = ClaimHandler::claim( $voucher_id, $user_id );
		if ( ! $result['valid'] ) {
			$status = self::status_for_code( $result['code'] );
			return RestResponse::error( $result['code'], $result['message'], $status );
		}

		$voucher       = $result['voucher'];
		$customer_code = ! empty( $result['assigned_code'] )
			? (string) $result['assigned_code']
			: (string) $voucher['code'];

		self::ensure_cart_loaded();
		$applied = self::maybe_apply_to_cart( $customer_code );

		return RestResponse::ok( [
			'code'            => $customer_code,
			'voucher_id'      => (int) $voucher['id'],
			'applied_to_cart' => $applied,
			// Friendly message tells the widget which path to take. If apply
			// failed despite eligibility passing pre-flight (rare race), we
			// kept the claim so the customer doesn't lose it.
			'message'         => $applied
				? __( 'Voucher claimed and applied.', 'zippy-crm' )
				: __( 'Voucher claimed. We couldn\'t apply it to your cart — try again from your vouchers list.', 'zippy-crm' ),
		] );
	}

	/**
	 * Remove an applied voucher from the cart. Does NOT release the claim —
	 * the customer can re-apply on this or a later checkout.
	 */
	public static function remove_from_cart( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}
		self::ensure_cart_loaded();
		$voucher_id = (int) $request['id'];
		$voucher    = Voucher::find( $voucher_id );
		if ( ! $voucher ) {
			return RestResponse::error( 'voucher_inactive', __( 'Voucher is no longer available.', 'zippy-crm' ), 410 );
		}

		// Determine the actual code in the cart. Multi-code vouchers issue
		// per-customer codes via the claim's code_id → voucher_codes table;
		// single-code uses the master code on the voucher row.
		$claim = VoucherClaim::find_for_user( $voucher_id, $user_id );
		$code  = $claim ? self::resolve_claim_code( $claim, $voucher_id ) : (string) $voucher['code'];

		if ( function_exists( 'WC' ) && WC()->cart ) {
			// Force session hydration before remove. In REST context the
			// cart object starts with applied_coupons=[], and remove_coupon
			// would silently no-op against the empty list (then a follow-up
			// calculate_totals reloads the session and puts the coupon
			// back). Calling calculate_totals up front pulls applied_coupons
			// out of the session so remove_coupon sees the real state.
			if ( ! empty( WC()->cart->get_cart() ) ) {
				WC()->cart->calculate_totals();
			}
			WC()->cart->remove_coupon( $code );
			self::flush_cart_session();
		}
		return RestResponse::ok( [ 'code' => $code, 'removed' => true ] );
	}

	/* ============================================================
	 * Admin
	 *
	 * Auth: routes use 'manage_woocommerce' (see Core/routes.php).
	 * Validation lives in VoucherService — handlers are thin glue.
	 * ============================================================ */

	public static function admin_list( \WP_REST_Request $request ) {
		$status    = (string) $request->get_param( 'status' );
		$search    = trim( (string) $request->get_param( 'search' ) );
		$page      = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page  = (int) $request->get_param( 'per_page' ) ?: self::DEFAULT_PER_PAGE;
		$per_page  = max( 1, min( self::MAX_PER_PAGE, $per_page ) );
		$sort      = (string) $request->get_param( 'sort' );
		$direction = strtolower( (string) $request->get_param( 'direction' ) );
		if ( $direction !== 'asc' && $direction !== 'desc' ) $direction = 'desc';

		if ( $status !== '' && ! in_array( $status, self::ALLOWED_STATUS_FILTERS, true ) ) {
			return RestResponse::error( 'bad_status_filter', __( 'Unknown status filter.', 'zippy-crm' ), 400 );
		}

		$rows  = Voucher::list_for_admin( $status, $search, $page, $per_page, $sort, $direction );
		$total = Voucher::count_for_admin( $status, $search );

		return RestResponse::ok( [
			'items'     => array_map( [ self::class, 'shape_voucher_admin' ], $rows ),
			'total'     => $total,
			'page'      => $page,
			'per_page'  => $per_page,
			'sort'      => $sort,
			'direction' => $direction,
			'counts'   => Voucher::count_by_status(),
		] );
	}

	public static function admin_create( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$payload = self::extract_voucher_payload( $request );

		$result = VoucherService::create_draft( $payload, $user_id );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return RestResponse::ok( self::shape_voucher_admin( $result ), 201 );
	}

	public static function admin_update( \WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$payload = self::extract_voucher_payload( $request );

		$result = VoucherService::update( $id, $payload );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return RestResponse::ok( self::shape_voucher_admin( $result ) );
	}

	public static function admin_delete( \WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$result = VoucherService::delete( $id );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return RestResponse::ok( [ 'deleted' => true, 'id' => $id ] );
	}

	public static function admin_publish( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! VoucherService::publish( $id ) ) {
			return RestResponse::error( 'voucher_publish_failed', __( 'Could not publish voucher.', 'zippy-crm' ), 400 );
		}
		return RestResponse::ok( self::shape_voucher_admin( Voucher::find( $id ) ) );
	}

	public static function admin_pause( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! VoucherService::pause( $id ) ) {
			return RestResponse::error( 'voucher_pause_failed', __( 'Could not pause voucher.', 'zippy-crm' ), 400 );
		}
		return RestResponse::ok( self::shape_voucher_admin( Voucher::find( $id ) ) );
	}

	public static function admin_resume( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! VoucherService::resume( $id ) ) {
			return RestResponse::error( 'voucher_resume_failed', __( 'Could not resume voucher.', 'zippy-crm' ), 400 );
		}
		return RestResponse::ok( self::shape_voucher_admin( Voucher::find( $id ) ) );
	}

	public static function admin_duplicate( \WP_REST_Request $request ) {
		$id      = (int) $request['id'];
		$user_id = get_current_user_id();
		$result  = VoucherService::duplicate( $id, $user_id );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return RestResponse::ok( self::shape_voucher_admin( $result ), 201 );
	}

	public static function admin_list_claims( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! Voucher::find( $id ) ) {
			return RestResponse::error( 'voucher_not_found', __( 'Voucher not found.', 'zippy-crm' ), 404 );
		}
		$rows = VoucherClaim::list_for_voucher( $id );
		return RestResponse::ok( [
			'items' => array_map( [ self::class, 'shape_admin_claim' ], $rows ),
		] );
	}

	/**
	 * Lists per-code rows for a multi-code voucher. Single-code vouchers
	 * have no rows here — the response is just an empty list with summary
	 * counts of zero.
	 */
	public static function admin_list_codes( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		$voucher = Voucher::find( $id );
		if ( ! $voucher ) {
			return RestResponse::error( 'voucher_not_found', __( 'Voucher not found.', 'zippy-crm' ), 404 );
		}

		$status   = (string) $request->get_param( 'status' );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = (int) ( $request->get_param( 'per_page' ) ?: 50 );

		$rows   = VoucherCode::list_for_voucher_admin( $id, $status, $page, $per_page );
		$counts = VoucherCode::counts_for_voucher( $id );

		return RestResponse::ok( [
			'items'    => array_map( [ self::class, 'shape_voucher_code' ], $rows ),
			'counts'   => $counts,
			'page'     => $page,
			'per_page' => $per_page,
			'distribution_mode' => (string) ( $voucher['distribution_mode'] ?? VoucherService::MODE_SINGLE ),
		] );
	}

	private static function shape_voucher_code( array $row ): array {
		return [
			'id'                => (int) $row['id'],
			'code'              => (string) $row['code'],
			'status'            => (string) $row['status'],
			'assigned_to_user'  => $row['assigned_to_user'] !== null ? (int) $row['assigned_to_user'] : null,
			'assigned_to_email' => $row['assigned_to_email'] ?? null,
			'user_login'        => (string) ( $row['user_login']   ?? '' ),
			'user_email'        => (string) ( $row['user_email']   ?? '' ),
			'display_name'      => (string) ( $row['display_name'] ?? '' ),
			'assigned_at'       => DateTimeHelper::mysql_to_iso( $row['assigned_at'] ?? null ),
			'used_at'           => DateTimeHelper::mysql_to_iso( $row['used_at']     ?? null ),
			'order_id'          => $row['order_id'] !== null ? (int) $row['order_id'] : null,
			'created_at'        => DateTimeHelper::mysql_to_iso( $row['created_at'] ?? null ),
		];
	}

	/**
	 * Pulls the editable subset out of the request. Centralized so create and
	 * update agree on the shape; service layer does the validation.
	 */
	private static function extract_voucher_payload( \WP_REST_Request $request ): array {
		// Scalars (REST gives strings/ints for these — model casts further).
		$scalar_keys = [
			'code', 'title', 'description',
			'discount_type', 'discount_value',
			'min_order_amount', 'max_order_amount',
			'max_uses', 'usage_limit_per_user', 'limit_usage_to_x_items',
			'individual_use', 'exclude_sale_items', 'free_shipping',
			'starts_at', 'expires_at',
			// Multi-code fields (ignored unless distribution_mode='multi_code_public')
			'distribution_mode', 'slots', 'code_prefix',
			// Audience targeting (v1.11.0). 'public' | 'email' | 'tier'.
			'audience_mode',
		];

		// Array / object fields (JSON in the column). Values may arrive as
		// arrays (JSON-decoded by the WP REST machinery when sent as JSON) or
		// as JSON strings (form-encoded fallback). Voucher::encode_json
		// handles both.
		$json_keys = Voucher::JSON_FIELDS;

		$out = [];
		foreach ( array_merge( $scalar_keys, $json_keys ) as $key ) {
			$value = $request->get_param( $key );
			if ( $value === null ) {
				continue;
			}
			// Empty string → null only for scalars. JSON columns let
			// encode_json normalise empty arrays / "[]" / "" all to null.
			if ( in_array( $key, $json_keys, true ) ) {
				$out[ $key ] = $value;
			} else {
				$out[ $key ] = $value === '' ? null : $value;
			}
		}

		// Multi-code: optional pre-typed codes list. Not a DB column on
		// crm_vouchers — VoucherService::create_draft consumes it to bulk
		// insert into crm_voucher_codes. Pass through as-is.
		$codes = $request->get_param( 'codes' );
		if ( $codes !== null ) {
			$out['codes'] = $codes;
		}

		return $out;
	}

	private static function shape_voucher_admin( ?array $row ): array {
		if ( ! $row ) {
			return [];
		}
		$row  = Voucher::decode_json_fields( $row );
		$max  = (int) $row['max_uses'];
		$used = (int) $row['uses_count'];
		return [
			'id'                          => (int) $row['id'],
			'code'                        => (string) $row['code'],
			'title'                       => (string) $row['title'],
			'description'                 => $row['description'] ?? null,
			'discount_type'               => (string) $row['discount_type'],
			'discount_value'              => (float) $row['discount_value'],
			'min_order_amount'            => (float) ( $row['min_order_amount'] ?? 0 ),
			'max_order_amount'            => (float) ( $row['max_order_amount'] ?? 0 ),
			'max_uses'                    => $max,
			'uses_count'                  => $used,
			'remaining_uses'              => $max > 0 ? max( 0, $max - $used ) : null,
			'usage_limit_per_user'        => (int) ( $row['usage_limit_per_user']   ?? 0 ),
			'limit_usage_to_x_items'      => (int) ( $row['limit_usage_to_x_items'] ?? 0 ),
			'individual_use'              => (bool) ( $row['individual_use']     ?? true ),
			'exclude_sale_items'          => (bool) ( $row['exclude_sale_items'] ?? false ),
			'free_shipping'               => (bool) ( $row['free_shipping']      ?? false ),
			'email_restrictions'          => $row['email_restrictions']          ?? null,
			'product_ids'                 => $row['product_ids']                 ?? null,
			'excluded_product_ids'        => $row['excluded_product_ids']        ?? null,
			'product_categories'          => $row['product_categories']          ?? null,
			'excluded_product_categories' => $row['excluded_product_categories'] ?? null,
			'allowed_hours'               => $row['allowed_hours']               ?? null,
			'audience_mode'               => (string) ( $row['audience_mode'] ?? 'public' ),
			'allowed_tiers'               => $row['allowed_tiers']               ?? null,
			'distribution_mode'           => (string) ( $row['distribution_mode'] ?? VoucherService::MODE_SINGLE ),
			'status'                      => (string) $row['status'],
			'starts_at'                   => DateTimeHelper::mysql_to_iso( $row['starts_at'] ?? null ),
			'expires_at'                  => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'created_by'                  => (int) $row['created_by'],
			'created_at'                  => DateTimeHelper::mysql_to_iso( $row['created_at'] ?? null ),
		];
	}

	private static function shape_admin_claim( array $row ): array {
		return [
			'id'           => (int) $row['claim_id'],
			'user_id'      => (int) $row['user_id'],
			'user_login'   => (string) ( $row['user_login']   ?? '' ),
			'user_email'   => (string) ( $row['user_email']   ?? '' ),
			'display_name' => (string) ( $row['display_name'] ?? '' ),
			'status'       => (string) $row['claim_status'],
			'claimed_at'   => DateTimeHelper::mysql_to_iso( $row['claimed_at'] ?? null ),
			'used_at'      => DateTimeHelper::mysql_to_iso( $row['used_at']   ?? null ),
			'order_id'     => $row['order_id'] !== null ? (int) $row['order_id'] : null,
		];
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	private static function shape_voucher( array $row ): array {
		$max  = (int) $row['max_uses'];
		$used = (int) $row['uses_count'];
		$mode = (string) ( $row['distribution_mode'] ?? VoucherService::MODE_SINGLE );

		// Multi-code: don't expose the synthetic ZC_MULTI_* placeholder. The
		// customer learns their unique code only when they claim. Likewise
		// `remaining_slots` is the per-customer signal — it comes from the
		// list query's subquery, not from the voucher row itself.
		$is_multi  = $mode === VoucherService::MODE_MULTI_PUBLIC;
		$remaining = $is_multi
			? (int) ( $row['remaining_slots'] ?? 0 )
			: ( $max > 0 ? max( 0, $max - $used ) : null );

		return [
			'id'                => (int) $row['id'],
			'code'              => $is_multi ? null : (string) $row['code'],
			'title'             => (string) $row['title'],
			'description'       => $row['description'] ?? null,
			'discount_type'     => (string) $row['discount_type'],
			'discount_value'    => (float) $row['discount_value'],
			'min_order_amount'  => (float) $row['min_order_amount'],
			'expires_at'        => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'distribution_mode' => $mode,
			'remaining_uses'    => $is_multi ? null : ( $max > 0 ? max( 0, $max - $used ) : null ),
			'remaining_slots'   => $is_multi ? $remaining : null,
		];
	}

	private static function shape_claim( array $row ): array {
		return [
			'id'             => (int) $row['claim_id'],
			'voucher_id'     => (int) $row['voucher_id'],
			'code'           => (string) $row['code'],
			'title'          => (string) $row['title'],
			'description'    => $row['description'] ?? null,
			'status'         => (string) $row['claim_status'],
			'discount_type'  => (string) $row['discount_type'],
			'discount_value' => (float) $row['discount_value'],
			'claimed_at'     => DateTimeHelper::mysql_to_iso( $row['claimed_at'] ?? null ),
			'used_at'        => DateTimeHelper::mysql_to_iso( $row['used_at'] ?? null ),
			'expires_at'     => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'order_id'       => $row['order_id'] !== null ? (int) $row['order_id'] : null,
		];
	}

	/**
	 * History row shape. Adds a derived `display_status` ("used" | "expired" |
	 * "revoked") and `reason_label` so the React side can render without
	 * re-deriving logic. Status='claimed' rows here are necessarily
	 * expired-by-date (the SQL only returns those when expires_at <= now).
	 */
	private static function shape_history_claim( array $row ): array {
		$claim_status = (string) $row['claim_status'];
		$reason       = (string) ( $row['revocation_reason'] ?? '' );

		// display_status collapses the three SQL states into the three UX states.
		// status='claimed' here only appears for expired-by-date rows.
		$display_status = $claim_status === 'used'    ? 'used'
		                : ( $claim_status === 'expired' ? 'revoked' : 'expired' );

		// Human label per reason. Wrapped here so the customer-facing copy
		// lives next to its data shape and translators have one place to
		// localize. The empty case ("expired" with no reason) is the
		// passive "voucher's expiry date passed" path.
		$reason_label = match ( true ) {
			$display_status === 'used'                    => null,
			$reason === 'cascade_coupon'                  => __( 'Voucher removed by admin', 'zippy-crm' ),
			$reason === 'tier_downgrade'                  => __( 'Tier no longer eligible', 'zippy-crm' ),
			$reason === 'admin_revoke'                    => __( 'Revoked by admin', 'zippy-crm' ),
			$display_status === 'expired'                 => __( 'Expired', 'zippy-crm' ),
			default                                       => __( 'No longer available', 'zippy-crm' ),
		};

		return [
			'id'                => (int) $row['claim_id'],
			'voucher_id'        => (int) $row['voucher_id'],
			'code'              => (string) $row['code'],
			'title'             => (string) $row['title'],
			'description'       => $row['description'] ?? null,
			// Raw DB status (for debug / future use)
			'status'            => $claim_status,
			'revocation_reason' => $reason !== '' ? $reason : null,
			// Pre-derived for the UI
			'display_status'    => $display_status,
			'reason_label'      => $reason_label,
			'discount_type'     => (string) $row['discount_type'],
			'discount_value'    => (float) $row['discount_value'],
			'claimed_at'        => DateTimeHelper::mysql_to_iso( $row['claimed_at'] ?? null ),
			'used_at'           => DateTimeHelper::mysql_to_iso( $row['used_at'] ?? null ),
			'expires_at'        => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'order_id'          => $row['order_id'] !== null ? (int) $row['order_id'] : null,
		];
	}

	/**
	 * Shape a tray row. `voucher_code` is the customer's claim-specific code
	 * for already-claimed rows, null for unclaimed rows (no code exists yet).
	 */
	private static function shape_tray_item( ?string $voucher_code, array $voucher_row, bool $claimed, array $eval, array $applied_codes ): array {
		$mode  = (string) ( $voucher_row['distribution_mode'] ?? VoucherService::MODE_SINGLE );
		$max   = (int) ( $voucher_row['max_uses'] ?? 0 );
		$used  = (int) ( $voucher_row['uses_count'] ?? 0 );
		// Unclaimed multi-code rows: customer hasn't been assigned a code yet,
		// so we leave `code` null. The claim_and_apply call mints one.
		// Unclaimed single-code rows: master code is the future customer code.
		$is_multi = $mode === VoucherService::MODE_MULTI_PUBLIC;
		$code     = $voucher_code;
		if ( $code === null && ! $is_multi ) {
			$code = (string) $voucher_row['code'];
		}

		$already_applied = $code !== null && in_array( strtolower( $code ), $applied_codes, true );

		return [
			'id'              => (int) $voucher_row['id'],
			'code'            => $code,
			'title'           => (string) $voucher_row['title'],
			'description'     => $voucher_row['description'] ?? null,
			'discount_type'   => (string) $voucher_row['discount_type'],
			'discount_value'  => (float) $voucher_row['discount_value'],
			'min_order_amount'=> (float) ( $voucher_row['min_order_amount'] ?? 0 ),
			'free_shipping'   => (bool) ( $voucher_row['free_shipping'] ?? false ),
			'expires_at'      => DateTimeHelper::mysql_to_iso( $voucher_row['expires_at'] ?? null ),
			'distribution_mode' => $mode,
			'remaining_uses'  => $is_multi ? null : ( $max > 0 ? max( 0, $max - $used ) : null ),
			'claimed'         => $claimed,
			'eligible'        => (bool) $eval['eligible'],
			'reason'          => $eval['reason'] ?? null,
			'already_applied' => $already_applied || ( $eval['already_applied'] ?? false ),
		];
	}

	/**
	 * Cart hydrate for REST. WC's session-bound cart doesn't auto-load on
	 * REST routing (it binds on the `wp` action). Mirrors
	 * PointsTender::ensure_cart_loaded — used here for the tray's read +
	 * apply paths so we read/write against the customer's real cart.
	 */
	private static function ensure_cart_loaded(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		if ( WC()->cart instanceof \WC_Cart ) {
			return;
		}
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
	}

	/**
	 * Lowercased applied-coupon codes on the live cart. Used to flag tray
	 * rows whose coupon is already in the cart (so the widget can render
	 * "Applied · Remove" instead of "Apply").
	 *
	 * Forces `calculate_totals()` first — same trick as PointsController's
	 * tender_payload. In REST context the cart object exists but its
	 * applied-coupons list reads from session data that isn't fully
	 * hydrated until something triggers a recalc. Without this nudge,
	 * `get_applied_coupons()` came back empty even when the cart visibly
	 * had a coupon attached, leaving the tray with stale "Apply" buttons.
	 */
	private static function current_cart_codes(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return [];
		}
		$cart = WC()->cart;
		if ( ! empty( $cart->get_cart() ) ) {
			$cart->calculate_totals();
		}
		return array_map( 'strtolower', (array) $cart->get_applied_coupons() );
	}

	/**
	 * If the user has an active cart, apply the coupon server-side.
	 * Returns true on success; false if there's no cart or WC rejects it.
	 *
	 * Wrapped in try/catch — any WC failure (already applied, doesn't match,
	 * cart empty) is non-fatal; user just sees the code instead.
	 */
	private static function maybe_apply_to_cart( string $code ): bool {
		// Defensive: an empty code would make WC store a phantom entry that
		// poisons the cart and bombs on the next page load with "Coupon ''
		// cannot be applied". Hard-bail rather than letting it through.
		if ( trim( $code ) === '' ) {
			return false;
		}
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		$wc = WC();
		if ( ! $wc || ! $wc->cart || $wc->cart->is_empty() ) {
			return false;
		}
		if ( $wc->cart->has_discount( $code ) ) {
			self::flush_cart_session();
			return true;
		}
		try {
			$ok = (bool) $wc->cart->apply_coupon( $code );
		} catch ( \Throwable $e ) {
			return false;
		}
		if ( $ok ) {
			self::flush_cart_session();
		}
		return $ok;
	}

	/**
	 * Force the WC session to persist before the REST handler returns.
	 *
	 * Why: `apply_coupon` / `remove_coupon` update the cart in-memory and
	 * rely on WC's `shutdown` hook to write back to the session table.
	 * Our React widget fires a follow-up `/checkout-tray` read inside the
	 * same browser tick — that read lands in a separate request that
	 * hydrates the customer's session fresh from the DB. If the previous
	 * request hasn't finished writing yet, the read sees the OLD applied
	 * coupons list and the widget re-renders unchanged. Explicit flush
	 * here forecloses the race.
	 *
	 * `calculate_totals()` is the canonical "I'm done mutating the cart"
	 * signal; it triggers WC's own session persistence path.
	 */
	private static function flush_cart_session(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		WC()->cart->calculate_totals();
		// WC_Cart::set_session() also pushes cart_contents + applied_coupons
		// into the session store. calculate_totals alone doesn't always
		// persist the applied-coupons array after a remove (it does after
		// apply, via the internal apply_coupon path). Belt to the braces.
		if ( method_exists( WC()->cart, 'set_session' ) ) {
			WC()->cart->set_session();
		}
		if ( WC()->session && method_exists( WC()->session, 'save_data' ) ) {
			WC()->session->save_data();
		}
	}

	private static function status_for_code( string $code ): int {
		return match ( $code ) {
			'voucher_inactive', 'voucher_expired', 'quota_exceeded' => 410, // Gone
			'already_claimed'                                        => 409, // Conflict
			'account_suspended'                                      => 403,
			default                                                  => 400,
		};
	}
}
