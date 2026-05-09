<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\Membership;
use ZippyCrm\Support\Cache;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Business rules for membership tiers. Reads/writes go through Membership model;
 * this layer owns: seeding on registration, tier upgrades on order completion,
 * multiplier lookup with cache.
 *
 * Tier rules (FEATURE_SPEC §1.2):
 *   silver — 5+ completed orders OR $500+ lifetime spend
 *   gold   — 15+ completed orders OR $2,000+ lifetime spend
 *   vip    — admin-assigned only (never auto-upgraded)
 *
 * vip is sticky — once an admin sets it, automated evaluation never downgrades.
 */
final class MembershipService {

	private const CACHE_KEY = 'membership:%d';

	public const TIER_THRESHOLDS = [
		'silver' => [ 'orders' => 5,  'spend' => 500.0 ],
		'gold'   => [ 'orders' => 15, 'spend' => 2000.0 ],
	];

	/**
	 * Read-through cached fetch. Returns the membership row, seeding one
	 * if missing (handles users who existed before the plugin was installed).
	 *
	 * @return array<string,mixed>
	 */
	public static function get_for_user( int $user_id ): array {
		$cache_key = sprintf( self::CACHE_KEY, $user_id );

		return Cache::remember( $cache_key, function () use ( $user_id ) {
			$row = Membership::find_by_user( $user_id );
			if ( $row === null ) {
				Membership::create( $user_id );
				$row = Membership::find_by_user( $user_id );
			}
			return $row;
		} );
	}

	public static function get_multiplier( int $user_id ): float {
		$row = self::get_for_user( $user_id );
		$level = $row['membership_level'] ?? 'free';
		return Membership::MULTIPLIERS[ $level ] ?? 1.0;
	}

	/**
	 * Hook target: woocommerce_created_customer.
	 * Seeds the membership row + invalidates any stale cache from a prior soft-delete.
	 */
	public static function on_customer_created( int $user_id ): void {
		Membership::create( $user_id );
		Cache::delete( sprintf( self::CACHE_KEY, $user_id ) );
	}

	/**
	 * Re-evaluate tier based on lifetime stats. VIP is never auto-set or auto-removed.
	 * Returns the new level (changed or unchanged).
	 */
	public static function evaluate_tier_upgrade( int $user_id ): string {
		$row = self::get_for_user( $user_id );
		$current = $row['membership_level'] ?? 'free';

		if ( $current === 'vip' ) {
			return $current;
		}

		$stats = self::get_customer_stats( $user_id );
		$next  = self::compute_tier( $stats );

		if ( $next !== $current ) {
			Membership::update_level( $user_id, $next );
			self::invalidate( $user_id );
			do_action( 'crm_membership_level_changed', $user_id, $current, $next );
		}

		return $next;
	}

	/**
	 * Highest tier the user qualifies for, given (orders, spend).
	 */
	public static function compute_tier( array $stats ): string {
		$orders = (int) ( $stats['total_orders'] ?? 0 );
		$spend  = (float) ( $stats['lifetime_spend'] ?? 0 );

		foreach ( [ 'gold', 'silver' ] as $tier ) {
			$t = self::TIER_THRESHOLDS[ $tier ];
			if ( $orders >= $t['orders'] || $spend >= $t['spend'] ) {
				return $tier;
			}
		}
		return 'free';
	}

	/**
	 * Lifetime stats from WC. HPOS-safe via WC_Customer.
	 *
	 * @return array{total_orders:int, lifetime_spend:float, currency:string}
	 */
	public static function get_customer_stats( int $user_id ): array {
		if ( ! function_exists( 'wc_get_customer_total_spent' ) ) {
			return [ 'total_orders' => 0, 'lifetime_spend' => 0.0, 'currency' => 'USD' ];
		}

		$customer = new \WC_Customer( $user_id );
		return [
			'total_orders'   => (int) $customer->get_order_count(),
			'lifetime_spend' => (float) $customer->get_total_spent(),
			'currency'       => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
		];
	}

	/**
	 * Progress info for the next tier the user could reach. Null if at the top.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function next_tier_progress( int $user_id, array $stats ): ?array {
		$current = self::get_for_user( $user_id )['membership_level'] ?? 'free';
		if ( in_array( $current, [ 'gold', 'vip' ], true ) ) {
			return null;
		}

		$next = $current === 'free' ? 'silver' : 'gold';
		$t    = self::TIER_THRESHOLDS[ $next ];

		// Pick whichever metric (orders vs spend) is closer.
		$orders_pct = $t['orders'] > 0 ? min( 100, ( $stats['total_orders']   / $t['orders'] ) * 100 ) : 0;
		$spend_pct  = $t['spend']  > 0 ? min( 100, ( $stats['lifetime_spend'] / $t['spend']  ) * 100 ) : 0;

		$by_spend = $spend_pct >= $orders_pct;

		return [
			'level'       => $next,
			'level_label' => Membership::LABELS[ $next ],
			'metric'      => $by_spend ? 'spend' : 'orders',
			'current'     => $by_spend ? round( (float) $stats['lifetime_spend'], 2 ) : (int) $stats['total_orders'],
			'target'      => $by_spend ? (float) $t['spend'] : (int) $t['orders'],
			'remaining'   => $by_spend
				? max( 0, round( $t['spend'] - $stats['lifetime_spend'], 2 ) )
				: max( 0, $t['orders'] - $stats['total_orders'] ),
			'percent'     => round( $by_spend ? $spend_pct : $orders_pct, 2 ),
		];
	}

	public static function invalidate( int $user_id ): void {
		Cache::delete( sprintf( self::CACHE_KEY, $user_id ) );
	}

	/* ============================================================
	 * Admin write paths
	 *
	 * These bypass the auto-evaluator: an admin can assign vip (which
	 * evaluate_tier_upgrade refuses to touch) or downgrade a user to free
	 * even if their lifetime stats would put them higher. Auto-evaluation
	 * still runs on the next order — but with vip it's sticky, and with
	 * lower tiers the admin's choice will get overwritten on the next
	 * qualifying purchase. That's intentional: admin override = manual fix,
	 * not a permanent ceiling.
	 *
	 * Returns true on success and \WP_Error on validation failure.
	 * ============================================================ */

	public static function set_level_admin( int $user_id, string $level ) {
		if ( ! in_array( $level, Membership::LEVELS, true ) ) {
			return new \WP_Error( 'bad_level', __( 'Unknown membership level.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		// Auto-seed if missing (handles users created before plugin install).
		self::get_for_user( $user_id );

		$current = Membership::find_by_user( $user_id )['membership_level'] ?? 'free';
		if ( $current === $level ) {
			return true;
		}

		$ok = Membership::update_level( $user_id, $level );
		if ( ! $ok ) {
			return new \WP_Error( 'update_failed', __( 'Could not update membership level.', 'zippy-crm' ), [ 'status' => 500 ] );
		}

		self::invalidate( $user_id );
		do_action( 'crm_membership_level_changed', $user_id, $current, $level );
		return true;
	}

	public static function set_status_admin( int $user_id, string $status ) {
		if ( ! in_array( $status, Membership::STATUSES, true ) ) {
			return new \WP_Error( 'bad_status', __( 'Unknown membership status.', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		self::get_for_user( $user_id );

		$current = Membership::find_by_user( $user_id )['status'] ?? 'active';
		if ( $current === $status ) {
			return true;
		}

		$ok = Membership::update_status( $user_id, $status );
		if ( ! $ok ) {
			return new \WP_Error( 'update_failed', __( 'Could not update membership status.', 'zippy-crm' ), [ 'status' => 500 ] );
		}

		self::invalidate( $user_id );
		do_action( 'crm_membership_status_changed', $user_id, $current, $status );
		return true;
	}
}
