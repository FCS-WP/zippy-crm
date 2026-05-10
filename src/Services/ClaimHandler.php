<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Models\VoucherCode;
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

		// Quota check is mode-dependent:
		//   single-code → uses_count vs max_uses
		//   multi-code  → at least one row in crm_voucher_codes is 'available'
		$mode = (string) ( $voucher['distribution_mode'] ?? VoucherService::MODE_SINGLE );
		if ( $mode === VoucherService::MODE_MULTI_PUBLIC ) {
			if ( VoucherCode::available_count_for_voucher( (int) $voucher['id'] ) <= 0 ) {
				return self::error( 'quota_exceeded', 'No remaining codes.' );
			}
		} else {
			if ( (int) $voucher['max_uses'] > 0 && (int) $voucher['uses_count'] >= (int) $voucher['max_uses'] ) {
				return self::error( 'quota_exceeded', 'No remaining uses.' );
			}
		}

		if ( VoucherClaim::find_for_user( $voucher_id, $user_id ) !== null ) {
			return self::error( 'already_claimed', 'You have already claimed this voucher.' );
		}

		// Suspended members can't claim.
		$membership = MembershipService::get_for_user( $user_id );
		if ( ( $membership['status'] ?? 'active' ) !== 'active' ) {
			return self::error( 'account_suspended', 'Your account is currently suspended.' );
		}

		// Audience targeting (v1.11.0). The visibility query already excludes
		// vouchers the user can't see, but a malicious or stale claim attempt
		// (e.g. tier downgrade between page-render and click) must still fail.
		if ( ! self::user_in_audience( $voucher, $user_id, $membership ) ) {
			return self::error( 'voucher_not_for_user', 'This voucher is not available for your account.' );
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
		$voucher = $result['voucher'];
		$mode    = (string) ( $voucher['distribution_mode'] ?? VoucherService::MODE_SINGLE );

		// Multi-code: atomically pick a code BEFORE inserting the claim row.
		// If we did it in the other order, a UNIQUE collision on (voucher,user)
		// would leave us with an unused-but-assigned code dangling.
		$code_id   = null;
		$code_text = null;
		if ( $mode === VoucherService::MODE_MULTI_PUBLIC ) {
			$picked = VoucherCode::pick_available_for_user( $voucher_id, $user_id );
			if ( $picked === null ) {
				return self::error( 'quota_exceeded', 'No remaining codes.' );
			}
			$code_id   = (int) $picked['id'];
			$code_text = (string) $picked['code'];
		}

		$claim_id = VoucherClaim::claim( $voucher_id, $user_id, $code_id );

		// 0 = UNIQUE collision (won the validate() race but lost the insert race).
		// Surface as already_claimed so the customer gets a sensible message.
		if ( $claim_id === 0 ) {
			// Roll back the code assignment — the claim never landed, so we
			// must release the code back to 'available' for someone else.
			if ( $code_id !== null ) {
				self::release_code( $code_id );
			}
			return self::error( 'already_claimed', 'You have already claimed this voucher.' );
		}
		if ( $claim_id < 0 ) {
			if ( $code_id !== null ) {
				self::release_code( $code_id );
			}
			return self::error( 'claim_failed', 'Could not record claim. Please try again.' );
		}

		self::invalidate_user_cache( $user_id );

		do_action( 'crm_voucher_claimed', $voucher_id, $user_id );

		return [
			'valid'         => true,
			'claim_id'      => $claim_id,
			'voucher'       => $voucher,
			'assigned_code' => $code_text, // null for single-code; the unique code for multi-code
		];
	}

	/**
	 * Releases a previously-assigned code back to 'available'. Used by the
	 * claim path when the claim insert fails after the code pick succeeded —
	 * leaving the code 'assigned' would make it unredeemable forever.
	 */
	private static function release_code( int $code_id ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . VoucherCode::TABLE,
			[ 'status' => 'available', 'assigned_to_user' => null, 'assigned_at' => null ],
			[ 'id' => $code_id, 'status' => 'assigned' ],
			[ '%s', '%d', '%s' ],
			[ '%d', '%s' ]
		);
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

			// First try a multi-code lookup: each row in crm_voucher_codes is
			// a real WC coupon. If found, the voucher_id on the row tells us
			// which campaign it belongs to.
			$code_row = VoucherCode::find_by_code( $code );
			if ( $code_row ) {
				$voucher_id = (int) $code_row['voucher_id'];
				$ok = VoucherClaim::mark_used( $voucher_id, $user_id, $order_id );
				if ( ! $ok ) {
					continue;
				}
				VoucherCode::mark_used_by_order( $code, $order_id );
				Voucher::increment_uses( $voucher_id );
				$consumed++;
				continue;
			}

			// Otherwise, single-code path — code matches the voucher's master code.
			$voucher = Voucher::find_by_code( $code );
			if ( ! $voucher ) {
				continue; // not a CRM voucher
			}

			$ok = VoucherClaim::mark_used( (int) $voucher['id'], $user_id, $order_id );
			if ( ! $ok ) {
				continue;
			}

			Voucher::increment_uses( (int) $voucher['id'] );
			$consumed++;
		}

		if ( $consumed > 0 ) {
			self::invalidate_user_cache( $user_id );
		}

		return $consumed;
	}

	/**
	 * Called from WooCommerce::on_order_cancelled. The order had a CRM
	 * voucher coupon applied but the customer never paid through.
	 *
	 * Per product decision (v1.13.0): the customer KEEPS their claim. Their
	 * code still works — they can apply it on a new order. Reverting the
	 * claim here would punish hesitant buyers and is the wrong tradeoff for
	 * a loyalty program.
	 *
	 * What this function actually does is defensive cleanup for a case that
	 * shouldn't happen in current code: if `consume_for_order` had already
	 * run on this order (status went completed → cancelled in some weird
	 * flow), we'd want to roll back the `used` markers. The caller
	 * (WooCommerce::on_order_cancelled) bails when META_SETTLED='1', so this
	 * is currently a no-op. Kept as a stub so future settled-then-cancelled
	 * paths have a hook to plug into.
	 *
	 * @return int number of claim rows reverted (always 0 today)
	 */
	public static function release_for_order( int $order_id ): int {
		// Nothing to do. See docblock — claims are intentionally NOT reverted
		// on cancel; consume_for_order hasn't run; there's no DB drift.
		unset( $order_id );
		return 0;
	}

	public static function invalidate_user_cache( int $user_id ): void {
		Cache::delete( sprintf( self::CACHE_KEY_AVAILABLE, $user_id ) );
	}

	private static function error( string $code, string $message ): array {
		return [ 'valid' => false, 'code' => $code, 'message' => $message ];
	}

	/**
	 * Mirrors the SQL audience filter in list_available_unclaimed.sql so that
	 * a direct claim() call without going through the visible list still
	 * enforces the same gating. The membership row is already loaded by the
	 * caller; passing it through saves a duplicate lookup.
	 *
	 * @param array<string,mixed>      $voucher
	 * @param array<string,mixed>|null $membership
	 */
	private static function user_in_audience( array $voucher, int $user_id, ?array $membership ): bool {
		$mode = (string) ( $voucher['audience_mode'] ?? 'public' );
		if ( $mode === 'public' ) {
			return true;
		}

		if ( $mode === 'tier' ) {
			$decoded = is_string( $voucher['allowed_tiers'] ?? null )
				? json_decode( (string) $voucher['allowed_tiers'], true )
				: ( $voucher['allowed_tiers'] ?? null );
			if ( ! is_array( $decoded ) || empty( $decoded ) ) {
				return false; // mode='tier' with empty list = nobody qualifies
			}
			$user_tier = (string) ( $membership['membership_level'] ?? TierRegistry::default_slug() );
			return in_array( $user_tier, array_map( 'strval', $decoded ), true );
		}

		if ( $mode === 'email' ) {
			$user = get_user_by( 'id', $user_id );
			if ( ! $user ) {
				return false;
			}
			$user_email = strtolower( (string) $user->user_email );
			$decoded    = is_string( $voucher['email_restrictions'] ?? null )
				? json_decode( (string) $voucher['email_restrictions'], true )
				: ( $voucher['email_restrictions'] ?? null );
			if ( ! is_array( $decoded ) ) {
				return false;
			}
			foreach ( $decoded as $entry ) {
				$email = is_array( $entry ) ? (string) ( $entry['email'] ?? '' ) : (string) $entry;
				if ( strtolower( trim( $email ) ) === $user_email ) {
					return true;
				}
			}
			return false;
		}

		// Unknown mode → fail closed.
		return false;
	}
}
