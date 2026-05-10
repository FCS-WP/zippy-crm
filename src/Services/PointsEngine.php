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
 * Earn formula:   points_earned = floor(SUM(non_blacklisted_line_totals)) * tier_rate
 *                  Lines whose product (or any of its categories) is in the
 *                  PointsSettings blacklist are excluded from the sum. The
 *                  per-tier rate (crm_tiers.multiplier) doubles as the points
 *                  per $1 — defaults to 0 for new tiers (no earn until admin
 *                  opts in).
 * Redeem formula: dollars       = floor(points / ZIPPY_CRM_POINTS_RATE)
 */
final class PointsEngine {

	private const CACHE_KEY_SUMMARY = 'points:summary:%d';

	/**
	 * Shared with PointsAdmin so per-user writes (here) and the system summary
	 * read (there) reference the same cache slot. Public for that reason —
	 * nothing else should touch this key.
	 */
	public const CACHE_KEY_SYSTEM = 'points:system';

	/** Order meta — set when we award, prevents double-credit on status flips. */
	private const META_AWARDED = '_zc_points_awarded';

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
	 * Spendable balance. As of v1.8.0 there's no reservation system —
	 * `available` is just `balance`. The method name and the legacy
	 * `available` REST field are kept so a stale browser tab carrying old
	 * React code keeps working (the values are equal, so no behavior change).
	 */
	public static function get_available_balance( int $user_id ): int {
		return self::get_summary( $user_id )['balance'];
	}

	/**
	 * Composite read for the REST `GET /points/me` endpoint.
	 *
	 * `reserved` is permanently 0 in v1.8.0+ (kept as a field for backward
	 * compatibility with cached React bundles); `available` always equals
	 * `balance`. Customers tender points at checkout — there's no advance-
	 * reservation step anymore.
	 *
	 * @return array<string,int>
	 */
	public static function get_full_summary( int $user_id ): array {
		$summary = self::get_summary( $user_id );
		return [
			'balance'        => $summary['balance'],
			'reserved'       => 0,
			'available'      => $summary['balance'],
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

		// Earn base = sum of line totals (post-discount, pre-tax/shipping) —
		// MINUS lines whose product is on the points blacklist (PointsSettings).
		// Equivalent to `get_subtotal() - get_total_discount()` when no
		// exclusions are configured, because WC discounts are allocated per
		// line. v1.12.0: blacklist support.
		$earn_base = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product_id = (int) $item->get_product_id();
			if ( PointsSettings::is_product_excluded( $product_id ) ) {
				continue;
			}
			$earn_base += (float) $item->get_total(); // post-discount line subtotal
		}
		if ( $earn_base <= 0 ) {
			return 0;
		}

		$multiplier = MembershipService::get_multiplier( $user_id );
		$multiplier = (float) apply_filters( 'crm_points_earn_multiplier', $multiplier, $user_id, $order );

		$points = (int) floor( floor( $earn_base ) * $multiplier );
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
	 * @deprecated since 1.8.0 — points are now tendered at checkout via
	 * `PointsTender::apply()`, not redeemed in advance for a coupon.
	 *
	 * Kept callable so legacy front-end clients (a stale browser tab with
	 * the old My Account redeem form) get a clean error rather than a fatal.
	 *
	 * @return \WP_Error always
	 */
	public static function redeem( int $user_id, int $points ) {
		return new \WP_Error(
			'redeem_deprecated',
			__( 'Redeeming points to a coupon is no longer supported. Apply your points at checkout instead.', 'zippy-crm' ),
			[ 'status' => 410 ]
		);
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
		// System-wide totals depend on every per-user balance — any per-user
		// write must also drop the system cache key.
		Cache::delete( self::CACHE_KEY_SYSTEM );
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	private static function credit( int $user_id, int $points, string $type, string $description, ?int $order_id ): void {
		PointsLedger::insert( $user_id, $type, $points, $description, $order_id );
		PointsSummary::apply_delta( $user_id, $points );
		self::invalidate( $user_id );
	}
}
