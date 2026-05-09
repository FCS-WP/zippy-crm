<?php
namespace ZippyCrm\Support;

use ZippyCrm\Models\Membership;
use ZippyCrm\Models\NotifSub;
use ZippyCrm\Models\PointsLedger;
use ZippyCrm\Models\PointsSummary;
use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Services\PointsEngine;
use ZippyCrm\Services\VoucherService;

defined( 'ABSPATH' ) || exit;

/**
 * Test data generator. Designed to be called from `wp eval`:
 *
 *   wp eval 'ZippyCrm\Support\Seeder::seed_members(20);'
 *   wp eval 'ZippyCrm\Support\Seeder::seed_vouchers(8);'
 *   wp eval 'ZippyCrm\Support\Seeder::seed_orders(50);'
 *   wp eval 'ZippyCrm\Support\Seeder::all(20, 8, 50);'
 *   wp eval 'ZippyCrm\Support\Seeder::reset();'
 *
 * Idempotent on re-run — `seed_members(20)` adds N more, doesn't reset.
 * Use `reset()` first if you want a clean slate.
 *
 * Goes in Support/ rather than a separate /scripts/ dir so it ships with the
 * plugin and benefits from the autoloader. NOT loaded in production code paths
 * — it's invoked manually via wp-cli.
 */
final class Seeder {

	private const SEED_PREFIX = 'zcseed';

	private const FIRST_NAMES = [ 'Alex', 'Sam', 'Jordan', 'Riley', 'Avery', 'Quinn', 'Reese', 'Hayden', 'Parker', 'Dakota', 'Casey', 'Morgan', 'Skyler', 'Rowan', 'Phoenix' ];
	private const LAST_NAMES  = [ 'Chen', 'Patel', 'Nguyen', 'Garcia', 'Kim', 'Singh', 'Tran', 'Lopez', 'Khan', 'Wong' ];

	private const VOUCHER_TITLES = [
		'Summer Sale',
		'Welcome Discount',
		'Loyalty Reward',
		'Flash Promo',
		'Seasonal Pick',
		'Birthday Treat',
		'Member Exclusive',
		'Restock Special',
	];

	/* ============================================================
	 * Public commands
	 * ============================================================ */

	/**
	 * Create N test customers. Each one gets a randomized membership level,
	 * a points balance via real ledger writes, and notification preferences.
	 *
	 * Returns the array of seeded user IDs.
	 *
	 * @return array<int,int>
	 */
	public static function seed_members( int $count = 10 ): array {
		$ids = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$first  = self::pick( self::FIRST_NAMES );
			$last   = self::pick( self::LAST_NAMES );
			$login  = strtolower( self::SEED_PREFIX . '_' . $first . substr( wp_generate_password( 4, false, false ), 0, 4 ) );
			$email  = $login . '@seed.test';

			$user_id = wp_create_user( $login, wp_generate_password(), $email );
			if ( is_wp_error( $user_id ) ) {
				continue;
			}

			wp_update_user( [
				'ID'           => $user_id,
				'first_name'   => $first,
				'last_name'    => $last,
				'display_name' => "$first $last",
				'role'         => 'customer',
			] );

			// Membership: roll a level. Bias toward 'free' so the distribution
			// reads like a real customer base.
			$level = self::weighted_pick( [
				'free'   => 60,
				'silver' => 25,
				'gold'   => 12,
				'vip'    => 3,
			] );
			Membership::create( $user_id, $level );

			// Points: random balance via a single 'adjust' ledger row so the
			// summary is consistent.
			$points = mt_rand( 0, 2500 );
			if ( $points > 0 ) {
				PointsLedger::insert( $user_id, 'adjust', $points, 'Seed top-up', null );
				PointsSummary::apply_delta( $user_id, $points );
			} else {
				PointsSummary::set( $user_id, 0, 0, 0 );
			}
			PointsEngine::invalidate( $user_id );

			// Notifications — most users opt in.
			NotifSub::upsert(
				$user_id,
				mt_rand( 0, 100 ) < 80, // 80% subscribed to vouchers
				mt_rand( 0, 100 ) < 60  // 60% subscribed to points
			);

			$ids[] = $user_id;
		}
		return $ids;
	}

	/**
	 * Create N draft vouchers and publish a fraction. The published ones get
	 * their WC coupons synced via VoucherService::publish (the real path).
	 *
	 * @return array<int,int>  Voucher IDs.
	 */
	public static function seed_vouchers( int $count = 5 ): array {
		$ids = [];
		$admin_id = self::admin_id();

		for ( $i = 0; $i < $count; $i++ ) {
			$is_percent = mt_rand( 0, 1 ) === 1;
			$value      = $is_percent ? mt_rand( 5, 30 ) : mt_rand( 5, 50 );
			$title      = self::pick( self::VOUCHER_TITLES );
			$code       = strtoupper( self::SEED_PREFIX . substr( wp_generate_password( 6, false, false ), 0, 6 ) );

			$voucher_id = Voucher::create( [
				'code'             => $code,
				'title'            => "$title $value" . ( $is_percent ? '% off' : ' off' ),
				'description'      => 'Seeded test voucher.',
				'discount_type'    => $is_percent ? 'percent' : 'fixed_cart',
				'discount_value'   => $value,
				'min_order_amount' => $is_percent ? 0 : mt_rand( 0, 1 ) * 25,
				'max_uses'         => mt_rand( 0, 1 ) === 0 ? 0 : mt_rand( 50, 500 ),
				'expires_at'       => gmdate( 'Y-m-d H:i:s', time() + mt_rand( 7, 90 ) * DAY_IN_SECONDS ),
			], $admin_id );

			if ( ! $voucher_id ) {
				continue;
			}

			// Publish ~75% of them via the real service, leave the rest as drafts.
			if ( mt_rand( 0, 99 ) < 75 ) {
				VoucherService::publish( $voucher_id );
			}

			$ids[] = $voucher_id;
		}
		return $ids;
	}

	/**
	 * Generate completed orders against seeded users so tier evaluation has
	 * something to work with. Each order has a random subtotal between $20-$200.
	 *
	 * Requires at least one purchasable WC product to attach line items;
	 * if none exist, falls back to a simple fee item.
	 */
	public static function seed_orders( int $count = 30, ?int $user_id = null ): int {
		if ( ! function_exists( 'wc_create_order' ) ) {
			return 0;
		}

		$users = $user_id !== null
			? [ get_user_by( 'id', $user_id ) ]
			: self::list_seeded_users();

		if ( empty( $users ) ) {
			return 0;
		}

		$products = wc_get_products( [ 'limit' => 5, 'status' => 'publish' ] );

		$created = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$user  = $users[ array_rand( $users ) ];
			if ( ! $user ) {
				continue;
			}
			$order = wc_create_order( [ 'customer_id' => $user->ID ] );

			if ( $products ) {
				$order->add_product( $products[ array_rand( $products ) ], mt_rand( 1, 3 ) );
			} else {
				$fee = new \WC_Order_Item_Fee();
				$fee->set_name( 'Seed line' );
				$fee->set_amount( (string) mt_rand( 20, 200 ) );
				$fee->set_total( (string) mt_rand( 20, 200 ) );
				$order->add_item( $fee );
			}
			$order->calculate_totals();
			// Triggers the full hook chain: tier eval + points award + claim consumption.
			$order->update_status( 'completed' );
			$created++;
		}
		return $created;
	}

	/**
	 * One-shot: members + vouchers + orders. Most useful for a fresh dev box.
	 *
	 * @return array{members:int, vouchers:int, orders:int}
	 */
	public static function all( int $members = 20, int $vouchers = 8, int $orders = 50 ): array {
		$m = count( self::seed_members( $members ) );
		$v = count( self::seed_vouchers( $vouchers ) );
		$o = self::seed_orders( $orders );
		return [ 'members' => $m, 'vouchers' => $v, 'orders' => $o ];
	}

	/**
	 * Predictable named accounts + vouchers for the QC test pass.
	 * See docs/QC_TEST_CASES.md.
	 *
	 * Idempotent — safe to re-run; updates existing fixtures rather than
	 * creating duplicates. Use `reset()` first only if a fixture got into a
	 * truly broken state.
	 *
	 * Returns the full credentials sheet so the dev can hand it to QA.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function seed_qc_fixtures(): array {
		$accounts = [
			'qa-free-1' => [
				'email'    => 'qa-free-1@qc.test',
				'first'    => 'Quinn',
				'last'     => 'Free',
				'level'    => 'free',
				'status'   => 'active',
				'points'   => 0,
				'vouchers' => true,
				'note'     => 'Brand-new customer, no history.',
			],
			'qa-silver-1' => [
				'email'    => 'qa-silver-1@qc.test',
				'first'    => 'Sam',
				'last'     => 'Silver',
				'level'    => 'silver',
				'status'   => 'active',
				'points'   => 250,
				'vouchers' => true,
				'note'     => 'Silver tier, 250 pts. Default tester.',
			],
			'qa-gold-1' => [
				'email'    => 'qa-gold-1@qc.test',
				'first'    => 'Gabe',
				'last'     => 'Gold',
				'level'    => 'gold',
				'status'   => 'active',
				'points'   => 1500,
				'vouchers' => true,
				'note'     => 'Gold tier, 1500 pts. For high-balance flows.',
			],
			'qa-vip-1' => [
				'email'    => 'qa-vip-1@qc.test',
				'first'    => 'Vee',
				'last'     => 'Vip',
				'level'    => 'vip',
				'status'   => 'active',
				'points'   => 800,
				'vouchers' => true,
				'note'     => 'VIP — must NOT auto-downgrade.',
			],
			'qa-suspended-1' => [
				'email'    => 'qa-suspended-1@qc.test',
				'first'    => 'Sue',
				'last'     => 'Suspended',
				'level'    => 'silver',
				'status'   => 'suspended',
				'points'   => 100,
				'vouchers' => true,
				'note'     => 'Suspended account — claim/redeem must refuse.',
			],
		];

		$out = [];
		foreach ( $accounts as $login => $cfg ) {
			$user = get_user_by( 'login', $login );
			if ( $user ) {
				$user_id = $user->ID;
				wp_set_password( 'TestQA1234!', $user_id ); // reset every run
			} else {
				$user_id = wp_create_user( $login, 'TestQA1234!', $cfg['email'] );
				if ( is_wp_error( $user_id ) ) {
					continue;
				}
			}

			wp_update_user( [
				'ID'           => $user_id,
				'first_name'   => $cfg['first'],
				'last_name'    => $cfg['last'],
				'display_name' => "{$cfg['first']} {$cfg['last']}",
				'role'         => 'customer',
			] );

			// Membership: upsert level + status.
			global $wpdb;
			$existing = Membership::find_by_user( $user_id );
			if ( ! $existing ) {
				Membership::create( $user_id, $cfg['level'] );
			} else {
				$wpdb->update(
					$wpdb->prefix . Membership::TABLE,
					[ 'membership_level' => $cfg['level'], 'status' => $cfg['status'] ],
					[ 'user_id' => $user_id ],
					[ '%s', '%s' ],
					[ '%d' ]
				);
			}
			Membership::update_status( $user_id, $cfg['status'] );
			\ZippyCrm\Services\MembershipService::invalidate( $user_id );

			// Points: hard-set summary, drop seed ledger row for traceability.
			$wpdb->delete(
				$wpdb->prefix . PointsLedger::TABLE,
				[ 'user_id' => $user_id, 'description' => 'QC fixture seed' ],
				[ '%d', '%s' ]
			);
			PointsSummary::set( $user_id, $cfg['points'], 0, $cfg['points'] );
			if ( $cfg['points'] > 0 ) {
				PointsLedger::insert( $user_id, 'adjust', $cfg['points'], 'QC fixture seed', null );
			}
			PointsEngine::invalidate( $user_id );

			// Notification prefs.
			NotifSub::upsert( $user_id, true, true );

			$out[ $login ] = [
				'login'    => $login,
				'password' => 'TestQA1234!',
				'email'    => $cfg['email'],
				'level'    => $cfg['level'],
				'status'   => $cfg['status'],
				'points'   => (string) $cfg['points'],
				'note'     => $cfg['note'],
			];
		}

		// One predictable voucher set: 1 percent, 1 fixed, 1 expired (for negative tests),
		// 1 with min order, 1 already-claimed by qa-silver-1 (for the My Claims tab).
		$vouchers = [
			[
				'code' => 'QA-PERCENT-25', 'title' => 'QA — 25% off everything',
				'description' => 'Used for the percent-discount tests.',
				'discount_type' => 'percent', 'discount_value' => 25,
				'min_order_amount' => 0, 'max_uses' => 0,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 60 * DAY_IN_SECONDS ),
				'publish' => true,
			],
			[
				'code' => 'QA-FIXED-10', 'title' => 'QA — $10 off cart',
				'description' => 'Used for the fixed-cart discount tests.',
				'discount_type' => 'fixed_cart', 'discount_value' => 10,
				'min_order_amount' => 0, 'max_uses' => 0,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 60 * DAY_IN_SECONDS ),
				'publish' => true,
			],
			[
				'code' => 'QA-MINORDER-15', 'title' => 'QA — 15% off, min $50',
				'description' => 'Tests the minimum-order threshold.',
				'discount_type' => 'percent', 'discount_value' => 15,
				'min_order_amount' => 50, 'max_uses' => 0,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + 60 * DAY_IN_SECONDS ),
				'publish' => true,
			],
			[
				'code' => 'QA-EXPIRED', 'title' => 'QA — already expired',
				'description' => 'Should NEVER appear on the customer Available list.',
				'discount_type' => 'fixed_cart', 'discount_value' => 5,
				'min_order_amount' => 0, 'max_uses' => 0,
				'expires_at' => '2024-01-01 00:00:00',
				'publish' => true, // we publish then it auto-filters as expired by SQL clock check
			],
		];

		$admin_id   = self::admin_id();
		$voucher_ids = [];
		foreach ( $vouchers as $v ) {
			global $wpdb;
			$existing = Voucher::find_by_code( $v['code'] );
			if ( $existing ) {
				$id = (int) $existing['id'];
				Voucher::update( $id, [
					'title'            => $v['title'],
					'description'      => $v['description'],
					'discount_type'    => $v['discount_type'],
					'discount_value'   => $v['discount_value'],
					'min_order_amount' => $v['min_order_amount'],
					'max_uses'         => $v['max_uses'],
					'expires_at'       => $v['expires_at'],
				] );
			} else {
				$id = Voucher::create( $v, $admin_id );
			}
			if ( ! $id ) {
				continue;
			}
			if ( $v['publish'] ) {
				VoucherService::publish( $id );
			}
			$voucher_ids[ $v['code'] ] = $id;
		}

		// Pre-claim QA-PERCENT-25 for qa-silver-1 so the "My Claims" tab is non-empty
		// in the very first test that lands there.
		if ( isset( $out['qa-silver-1'], $voucher_ids['QA-PERCENT-25'] ) ) {
			$silver_id = (int) get_user_by( 'login', 'qa-silver-1' )->ID;
			$existing  = VoucherClaim::find_for_user( $voucher_ids['QA-PERCENT-25'], $silver_id );
			if ( ! $existing ) {
				VoucherClaim::claim( $voucher_ids['QA-PERCENT-25'], $silver_id );
			}
		}

		return $out;
	}

	/**
	 * Drops every seeded record. Identifies seeded data by the `zcseed` prefix
	 * on user logins and voucher codes — never touches real users/vouchers.
	 *
	 * Doesn't delete completed test orders (we can't tell ours from real ones).
	 *
	 * @return array{users:int, vouchers:int}
	 */
	public static function reset(): array {
		global $wpdb;

		// Clear both the random `zcseed_*` accounts AND the predictable `qa-*`
		// QC fixtures. Real users (no matching prefix) are untouched.
		$user_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->users} WHERE user_login LIKE %s OR user_login LIKE %s",
			$wpdb->esc_like( self::SEED_PREFIX . '_' ) . '%',
			$wpdb->esc_like( 'qa-' ) . '%'
		) );

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$users_deleted = 0;
		foreach ( (array) $user_ids as $id ) {
			if ( wp_delete_user( (int) $id ) ) {
				$users_deleted++;
			}
		}

		$voucher_ids = $wpdb->get_col( $wpdb->prepare(
			'SELECT id FROM ' . $wpdb->prefix . Voucher::TABLE . ' WHERE code LIKE %s OR code LIKE %s',
			$wpdb->esc_like( strtoupper( self::SEED_PREFIX ) ) . '%',
			$wpdb->esc_like( 'QA-' ) . '%'
		) );

		// Clear claims rows for our seeded vouchers — direct delete avoids a
		// model method that only this seeder would call.
		$claims_table = $wpdb->prefix . VoucherClaim::TABLE;
		$vouchers_deleted = 0;
		foreach ( (array) $voucher_ids as $id ) {
			$id = (int) $id;
			$wpdb->delete( $claims_table, [ 'voucher_id' => $id ], [ '%d' ] );
			if ( Voucher::delete( $id ) ) {
				$vouchers_deleted++;
			}
		}

		return [ 'users' => $users_deleted, 'vouchers' => $vouchers_deleted ];
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	/**
	 * @return array<int,\WP_User>
	 */
	private static function list_seeded_users(): array {
		$users = get_users( [
			'search'         => self::SEED_PREFIX . '_*',
			'search_columns' => [ 'user_login' ],
			'number'         => -1,
		] );
		return $users ?: [];
	}

	private static function admin_id(): int {
		$admins = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
		return ! empty( $admins ) ? (int) $admins[0] : 0;
	}

	private static function pick( array $arr ): string {
		return (string) $arr[ array_rand( $arr ) ];
	}

	/**
	 * Pick a key from a weighted associative array. Sum of weights is the
	 * effective denominator.
	 *
	 * @param array<string,int> $weights
	 */
	private static function weighted_pick( array $weights ): string {
		$total = array_sum( $weights );
		$roll  = mt_rand( 1, max( 1, $total ) );
		$acc   = 0;
		foreach ( $weights as $key => $w ) {
			$acc += $w;
			if ( $roll <= $acc ) {
				return $key;
			}
		}
		return (string) array_key_first( $weights );
	}
}
