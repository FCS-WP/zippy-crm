<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Support\Cache;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Validates and records voucher claims. Validation order is the spec
 * (FEATURE_SPEC §3.5): active → expiry → quota → not-claimed → not-suspended.
 *
 * The actual UNIQUE-key collision in claim() is the authoritative double-claim
 * guard — the pre-flight `find_for_user` check is a fast UX path so users
 * see a clean error in the common case.
 */
final class ClaimHandler {

	private const CACHE_KEY_AVAILABLE = 'vouchers:available:%d';

	/**
	 * @return array{valid:bool, code?:string, message?:string, voucher?:array}
	 */
	public static function validate( int $voucher_id, int $user_id ): array {
		$voucher = Voucher::find( $voucher_id );
		if ( ! $voucher ) {
			return self::error( 'voucher_inactive', 'Voucher is no longer available.' );
		}
		if ( $voucher['status'] !== 'active' ) {
			return self::error( 'voucher_inactive', 'Voucher is no longer active.' );
		}

		$now = DateTimeHelper::now_mysql();
		if ( ! empty( $voucher['expires_at'] ) && $voucher['expires_at'] <= $now ) {
			return self::error( 'voucher_expired', 'Voucher has expired.' );
		}
		if ( ! empty( $voucher['starts_at'] ) && $voucher['starts_at'] > $now ) {
			return self::error( 'voucher_inactive', 'Voucher is not active yet.' );
		}

		if ( (int) $voucher['max_uses'] > 0 && (int) $voucher['uses_count'] >= (int) $voucher['max_uses'] ) {
			return self::error( 'quota_exceeded', 'No remaining uses.' );
		}

		if ( VoucherClaim::find_for_user( $voucher_id, $user_id ) !== null ) {
			return self::error( 'already_claimed', 'You have already claimed this voucher.' );
		}

		// Suspended members can't claim.
		$membership = MembershipService::get_for_user( $user_id );
		if ( ( $membership['status'] ?? 'active' ) !== 'active' ) {
			return self::error( 'account_suspended', 'Your account is currently suspended.' );
		}

		// Filter hook — lets the membership level (or future Notifications)
		// veto a claim for reasons we don't bake into the engine.
		$errors = (array) apply_filters( 'crm_pre_claim_voucher', [], $voucher_id, $user_id );
		if ( ! empty( $errors ) ) {
			$first = reset( $errors );
			$code  = is_array( $first ) ? ( $first['code'] ?? 'pre_claim_failed' ) : 'pre_claim_failed';
			$msg   = is_array( $first ) ? ( $first['message'] ?? 'Voucher cannot be claimed right now.' ) : (string) $first;
			return self::error( $code, $msg );
		}

		return [ 'valid' => true, 'voucher' => $voucher ];
	}

	/**
	 * Validates + records the claim. Returns the same shape as validate(), but
	 * a successful return includes `claim_id` and the voucher row.
	 *
	 * @return array<string,mixed>
	 */
	public static function claim( int $voucher_id, int $user_id ): array {
		$result = self::validate( $voucher_id, $user_id );
		if ( ! $result['valid'] ) {
			return $result;
		}

		$claim_id = VoucherClaim::claim( $voucher_id, $user_id );

		// 0 = UNIQUE collision (won the validate() race but lost the insert race).
		// Surface as already_claimed so the customer gets a sensible message.
		if ( $claim_id === 0 ) {
			return self::error( 'already_claimed', 'You have already claimed this voucher.' );
		}
		if ( $claim_id < 0 ) {
			return self::error( 'claim_failed', 'Could not record claim. Please try again.' );
		}

		self::invalidate_user_cache( $user_id );

		do_action( 'crm_voucher_claimed', $voucher_id, $user_id );

		return [
			'valid'    => true,
			'claim_id' => $claim_id,
			'voucher'  => $result['voucher'],
		];
	}

	/**
	 * Hook target: woocommerce_order_status_completed.
	 * Walks the order's applied coupon codes; for each that maps to a CRM
	 * voucher this user has claimed, marks the claim used and bumps uses_count.
	 */
	public static function consume_for_order( int $order_id ): int {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return 0;
		}
		$user_id = (int) $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return 0;
		}

		$consumed = 0;
		foreach ( $order->get_coupon_codes() as $raw_code ) {
			$code = strtoupper( $raw_code );

			$voucher = Voucher::find_by_code( $code );
			if ( ! $voucher ) {
				continue; // not a CRM voucher
			}

			$ok = VoucherClaim::mark_used( (int) $voucher['id'], $user_id, $order_id );
			if ( ! $ok ) {
				continue; // already used, not claimed by this user, or race lost
			}

			Voucher::increment_uses( (int) $voucher['id'] );
			$consumed++;
		}

		if ( $consumed > 0 ) {
			self::invalidate_user_cache( $user_id );
		}

		return $consumed;
	}

	public static function invalidate_user_cache( int $user_id ): void {
		Cache::delete( sprintf( self::CACHE_KEY_AVAILABLE, $user_id ) );
	}

	private static function error( string $code, string $message ): array {
		return [ 'valid' => false, 'code' => $code, 'message' => $message ];
	}
}
