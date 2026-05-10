<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Models\VoucherCode;
use ZippyCrm\Services\ClaimHandler;
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
	 * If the user has an active cart, apply the coupon server-side.
	 * Returns true on success; false if there's no cart or WC rejects it.
	 *
	 * Wrapped in try/catch — any WC failure (already applied, doesn't match,
	 * cart empty) is non-fatal; user just sees the code instead.
	 */
	private static function maybe_apply_to_cart( string $code ): bool {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}
		$wc = WC();
		if ( ! $wc || ! $wc->cart || $wc->cart->is_empty() ) {
			return false;
		}
		if ( $wc->cart->has_discount( $code ) ) {
			return true;
		}
		try {
			return (bool) $wc->cart->apply_coupon( $code );
		} catch ( \Throwable $e ) {
			return false;
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
