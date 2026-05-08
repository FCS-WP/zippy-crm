<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Services\ClaimHandler;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 *
 * Customer routes are live; admin CRUD routes are registered but stubbed —
 * they'll be implemented in the Admin slice.
 */
final class VouchersController {

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

		$voucher = $result['voucher'];
		$applied_to_cart = self::maybe_apply_to_cart( (string) $voucher['code'] );

		return RestResponse::ok( [
			'message'         => __( 'Voucher claimed successfully!', 'zippy-crm' ),
			'code'            => (string) $voucher['code'],
			'title'           => (string) $voucher['title'],
			'discount_type'   => (string) $voucher['discount_type'],
			'discount_value'  => (float) $voucher['discount_value'],
			'expires_at'      => DateTimeHelper::mysql_to_iso( $voucher['expires_at'] ?? null ),
			'applied_to_cart' => $applied_to_cart,
		] );
	}

	/* ============================================================
	 * Admin (stubbed — implemented in Admin slice)
	 * ============================================================ */

	public static function admin_list( \WP_REST_Request $r )   { return rest_ensure_response( [ 'items' => [] ] ); }
	public static function admin_create( \WP_REST_Request $r ) { return rest_ensure_response( [] ); }
	public static function admin_update( \WP_REST_Request $r ) { return rest_ensure_response( [] ); }
	public static function admin_delete( \WP_REST_Request $r ) { return rest_ensure_response( [] ); }

	/* ============================================================
	 * Internal
	 * ============================================================ */

	private static function shape_voucher( array $row ): array {
		$max  = (int) $row['max_uses'];
		$used = (int) $row['uses_count'];
		return [
			'id'               => (int) $row['id'],
			'code'             => (string) $row['code'],
			'title'            => (string) $row['title'],
			'description'      => $row['description'] ?? null,
			'discount_type'    => (string) $row['discount_type'],
			'discount_value'   => (float) $row['discount_value'],
			'min_order_amount' => (float) $row['min_order_amount'],
			'expires_at'       => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'remaining_uses'   => $max > 0 ? max( 0, $max - $used ) : null,
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
