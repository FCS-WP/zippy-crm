<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\PointsLedger;
use ZippyCrm\Models\PointsSummary;
use ZippyCrm\Support\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Owns every points mutation. Three rules:
 *   1. Every change is a ledger row first, summary update second.
 *   2. Cache invalidation is always paired with the summary write.
 *   3. Reads always come from the summary (cached), never SUM(ledger).
 *
 * Earn formula:   points_earned = floor(order_subtotal_after_discounts) * multiplier
 * Redeem formula: dollars       = floor(points / ZIPPY_CRM_POINTS_RATE)
 */
final class PointsEngine {

	private const CACHE_KEY_SUMMARY = 'points:summary:%d';

	/** Order meta — set when we award, prevents double-credit on status flips. */
	private const META_AWARDED = '_zc_points_awarded';

	/** Order meta — JSON list of CRM coupon codes already debited for this order. */
	private const META_REDEEMED_CODES = '_zc_points_redeemed_codes';

	/** Coupon meta — written at reserve time, read at consumption time. */
	private const COUPON_META_REDEMPTION = '_zc_redemption';
	private const COUPON_META_RESERVED   = '_zc_points_reserved';
	private const COUPON_META_USER_ID    = '_zc_user_id';

	/** Generated coupon code prefix. Cheap pre-filter on order completion. */
	private const COUPON_PREFIX = 'CRM-RDM-';

	/* ============================================================
	 * Reads
	 * ============================================================ */

	/**
	 * Cached summary read. Auto-seeds the row for users created before the plugin.
	 *
	 * @return array{user_id:int, total_earned:int, total_redeemed:int, balance:int}
	 */
	public static function get_summary( int $user_id ): array {
		$key = sprintf( self::CACHE_KEY_SUMMARY, $user_id );

		return Cache::remember( $key, function () use ( $user_id ) {
			$row = PointsSummary::find( $user_id );
			if ( $row === null ) {
				PointsSummary::set( $user_id, 0, 0, 0 );
				$row = PointsSummary::find( $user_id );
			}
			return [
				'user_id'        => (int) $row['user_id'],
				'total_earned'   => (int) $row['total_earned'],
				'total_redeemed' => (int) $row['total_redeemed'],
				'balance'        => (int) $row['balance'],
			];
		} );
	}

	public static function get_balance( int $user_id ): int {
		return self::get_summary( $user_id )['balance'];
	}

	/**
	 * Spendable balance: gross minus points locked in active pending coupons.
	 * Reservation totals are NOT cached — they change the moment a user clicks
	 * Redeem, and the read is one indexed query against `idx_pending`.
	 */
	public static function get_available_balance( int $user_id ): int {
		$summary  = self::get_summary( $user_id );
		$reserved = PointsLedger::get_reserved_total( $user_id );
		return max( 0, $summary['balance'] - $reserved );
	}

	/**
	 * Composite read for the REST `GET /points/me` endpoint.
	 *
	 * @return array<string,int|float>
	 */
	public static function get_full_summary( int $user_id ): array {
		$summary  = self::get_summary( $user_id );
		$reserved = PointsLedger::get_reserved_total( $user_id );
		$available = max( 0, $summary['balance'] - $reserved );
		return [
			'balance'        => $summary['balance'],
			'reserved'       => $reserved,
			'available'      => $available,
			'total_earned'   => $summary['total_earned'],
			'total_redeemed' => $summary['total_redeemed'],
		];
	}

	/* ============================================================
	 * Writes
	 * ============================================================ */

	/**
	 * Hook target: woocommerce_order_status_completed.
	 * Idempotent — order meta `_zc_points_awarded` blocks double-credit.
	 */
	public static function award_for_order( int $order_id ): int {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return 0;
		}
		$user_id = (int) $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return 0;
		}
		if ( $order->get_meta( self::META_AWARDED ) !== '' ) {
			return 0;
		}

		// Subtotal AFTER discounts. WC's `get_total()` includes shipping/tax —
		// so we use subtotal - discount instead.
		$subtotal_after = (float) $order->get_subtotal() - (float) $order->get_total_discount();
		if ( $subtotal_after <= 0 ) {
			return 0;
		}

		$multiplier = MembershipService::get_multiplier( $user_id );
		$multiplier = (float) apply_filters( 'crm_points_earn_multiplier', $multiplier, $user_id, $order );

		$points = (int) floor( floor( $subtotal_after ) * $multiplier );
		if ( $points <= 0 ) {
			return 0;
		}

		self::credit(
			$user_id,
			$points,
			'earn',
			sprintf( /* translators: %d: order id */ __( 'Order #%d', 'zippy-crm' ), $order_id ),
			$order_id
		);

		$order->update_meta_data( self::META_AWARDED, $points );
		$order->save();

		do_action( 'crm_points_awarded', $user_id, $points, $order_id );
		return $points;
	}

	/**
	 * Reserve N points → 24h WC coupon. Points stay on the user's balance —
	 * they're locked into the coupon and only debited when the coupon is
	 * actually used on a completed order. Unused coupons that expire return
	 * the points to availability automatically (see get_reserved_total.sql).
	 *
	 * @return array{coupon_code:string, discount:float, expires:string, available_balance:int}|\WP_Error
	 */
	public static function redeem( int $user_id, int $points ) {
		$rate = (int) apply_filters( 'crm_points_redemption_rate', ZIPPY_CRM_POINTS_RATE, $user_id );

		if ( $points < ZIPPY_CRM_MIN_REDEMPTION ) {
			return new \WP_Error(
				'redeem_too_small',
				sprintf( /* translators: %d: minimum points */ __( 'Minimum redemption is %d points.', 'zippy-crm' ), ZIPPY_CRM_MIN_REDEMPTION ),
				[ 'status' => 400 ]
			);
		}
		if ( $points % $rate !== 0 ) {
			return new \WP_Error(
				'redeem_not_multiple',
				sprintf( /* translators: %d: points per dollar */ __( 'Points must be a multiple of %d.', 'zippy-crm' ), $rate ),
				[ 'status' => 400 ]
			);
		}

		// Validate against AVAILABLE balance (gross minus already-reserved).
		$summary   = self::get_summary( $user_id );
		$reserved  = PointsLedger::get_reserved_total( $user_id );
		$available = max( 0, $summary['balance'] - $reserved );
		if ( $points > $available ) {
			return new \WP_Error(
				'insufficient_available',
				sprintf(
					/* translators: 1: gross balance, 2: available, 3: reserved, 4: requested */
					__( 'You have %1$d pts (%2$d available, %3$d reserved in pending coupons). Cannot redeem %4$d — try %2$d or wait for pending coupons to expire.', 'zippy-crm' ),
					$summary['balance'],
					$available,
					$reserved,
					$points
				),
				[
					'status'    => 400,
					'balance'   => $summary['balance'],
					'available' => $available,
					'reserved'  => $reserved,
				]
			);
		}

		// Suspended members can't redeem.
		$membership = MembershipService::get_for_user( $user_id );
		if ( ( $membership['status'] ?? 'active' ) !== 'active' ) {
			return new \WP_Error( 'account_suspended', __( 'Your account is currently suspended.', 'zippy-crm' ), [ 'status' => 403 ] );
		}

		$discount = floor( $points / $rate );
		$code     = self::generate_coupon_code( $user_id );
		$expires  = time() + DAY_IN_SECONDS;

		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( (string) $discount );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_date_expires( $expires );
		$coupon->set_description( sprintf( /* translators: 1: points, 2: user id */ __( 'Redeemed %1$d points (user %2$d)', 'zippy-crm' ), $points, $user_id ) );

		// Mark this coupon as ours + remember reservation amount + owner.
		// Read at order completion to verify and debit.
		$coupon->update_meta_data( self::COUPON_META_REDEMPTION, 1 );
		$coupon->update_meta_data( self::COUPON_META_RESERVED, $points );
		$coupon->update_meta_data( self::COUPON_META_USER_ID, $user_id );

		$coupon_id = $coupon->save();

		if ( ! $coupon_id ) {
			return new \WP_Error( 'coupon_failed', __( 'Could not create discount coupon.', 'zippy-crm' ), [ 'status' => 500 ] );
		}

		// Pending row: points=0 (no balance impact yet), reserved_points=N for
		// the available-balance subquery, description = exact coupon code so
		// `find_pending_by_code` is an O(1) lookup at consumption time.
		PointsLedger::insert(
			$user_id,
			'pending_redeem',
			0,
			$code,
			null,
			$points,
			PointsLedger::PENDING_ACTIVE
		);

		do_action( 'crm_points_reserved', $user_id, $points, $code );

		return [
			'coupon_code'       => $code,
			'discount'          => (float) $discount,
			'expires'           => gmdate( 'Y-m-d\TH:i:s\Z', $expires ),
			'available_balance' => $available - $points,
		];
	}

	/**
	 * Hook target: woocommerce_order_status_completed (after award_for_order).
	 *
	 * Walks each applied coupon, debits the matching pending reservation.
	 * Three independent idempotency guards:
	 *   1. Order meta `_zc_points_redeemed_codes` — fast skip on re-fire
	 *   2. Coupon prefix + `_zc_redemption` meta — protects against admin-created lookalikes
	 *   3. Conditional UPDATE on pending_status='active' — wins exactly one race
	 */
	public static function consume_redemptions_for_order( int $order_id ): int {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return 0;
		}

		$already_raw = $order->get_meta( self::META_REDEEMED_CODES );
		$already     = is_string( $already_raw ) && $already_raw !== ''
			? (array) json_decode( $already_raw, true )
			: [];

		$codes = $order->get_coupon_codes();
		if ( empty( $codes ) ) {
			return 0;
		}

		$total_debited = 0;
		$consumed_now  = [];

		foreach ( $codes as $raw_code ) {
			$code = strtoupper( $raw_code );

			// Guard 1: prefix + already-debited check (cheap, no DB).
			if ( strpos( $code, self::COUPON_PREFIX ) !== 0 ) {
				continue;
			}
			if ( in_array( $code, $already, true ) ) {
				continue;
			}

			// Guard 2: verify it's actually a CRM redemption coupon.
			$coupon_id = wc_get_coupon_id_by_code( $code );
			if ( ! $coupon_id ) {
				continue;
			}
			$coupon = new \WC_Coupon( $coupon_id );
			if ( (string) $coupon->get_meta( self::COUPON_META_REDEMPTION ) !== '1' ) {
				continue;
			}

			// Find the matching pending row.
			$pending = PointsLedger::find_pending_by_code( $code );
			if ( $pending === null ) {
				continue;
			}

			// Guard 3: conditional UPDATE — only the winner of the race proceeds.
			$won = PointsLedger::mark_pending_consumed( $pending['id'], $order_id );
			if ( ! $won ) {
				continue;
			}

			$user_id = $pending['user_id'];
			$points  = $pending['reserved_points'];

			// Real debit now: ledger row + summary delta + cache invalidation.
			self::debit(
				$user_id,
				$points,
				'redeem',
				sprintf( /* translators: %s: coupon code */ __( 'Coupon %s', 'zippy-crm' ), $code ),
				$order_id
			);

			do_action( 'crm_points_redeemed', $user_id, $points, $code, $order_id );

			$total_debited += $points;
			$consumed_now[] = $code;
		}

		if ( $consumed_now ) {
			$order->update_meta_data(
				self::META_REDEEMED_CODES,
				wp_json_encode( array_values( array_unique( array_merge( $already, $consumed_now ) ) ) )
			);
			$order->save();
		}

		return $total_debited;
	}

	/**
	 * Reconcile summary against ledger. Cheap, but only run when drift is suspected.
	 */
	public static function recalculate_balance( int $user_id ): array {
		$totals = PointsLedger::recalculate( $user_id );
		PointsSummary::set( $user_id, $totals['total_earned'], $totals['total_redeemed'], $totals['balance'] );
		self::invalidate( $user_id );
		return $totals;
	}

	public static function invalidate( int $user_id ): void {
		Cache::delete( sprintf( self::CACHE_KEY_SUMMARY, $user_id ) );
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	private static function credit( int $user_id, int $points, string $type, string $description, ?int $order_id ): void {
		PointsLedger::insert( $user_id, $type, $points, $description, $order_id );
		PointsSummary::apply_delta( $user_id, $points );
		self::invalidate( $user_id );
	}

	private static function debit( int $user_id, int $points, string $type, string $description, ?int $order_id ): void {
		PointsLedger::insert( $user_id, $type, -$points, $description, $order_id );
		PointsSummary::apply_delta( $user_id, -$points );
		self::invalidate( $user_id );
	}

	private static function generate_coupon_code( int $user_id ): string {
		return strtoupper( sprintf( 'CRM-RDM-%s', wp_generate_password( 6, false, false ) ) );
	}
}
