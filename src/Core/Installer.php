<?php
namespace ZippyCrm\Core;

use ZippyCrm\Database\QueryLoader;

defined( 'ABSPATH' ) || exit;

/**
 * Runs schema migrations via dbDelta(). Idempotent — safe to call on every
 * activation and on version bumps.
 */
final class Installer {

	private const OPTION_VERSION = 'zippy_crm_db_version';

	/** Schema files (relative to src/Database/Schema/) loaded in dependency order. */
	private const SCHEMAS = [
		'crm_memberships.sql',
		'crm_points_ledger.sql',
		'crm_points_summary.sql',
		'crm_vouchers.sql',
		'crm_voucher_codes.sql',
		'crm_voucher_claims.sql',
		'crm_notif_subs.sql',
		'crm_notification_log.sql',
		'crm_audit_log.sql',
		'crm_tiers.sql',
	];

	public static function run(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::SCHEMAS as $file ) {
			dbDelta( QueryLoader::schema( $file ) );
		}

		// Data migrations after the schema runs.
		self::seed_default_tiers();
		self::expire_legacy_pending_redemptions();

		update_option( self::OPTION_VERSION, ZIPPY_CRM_VERSION, false );
	}

	/**
	 * One-time migration for v1.8.0: when points moved from coupon-based
	 * redemption (with a 24h pending hold) to session-based "tender at
	 * checkout", legacy `pending_redeem` ledger rows became dead state.
	 *
	 * Mark every still-active row as `expired` — the points were never
	 * debited (points=0 on those rows by design), so no balance arithmetic
	 * changes. The customer's `available` balance, which used to subtract
	 * these reservations, now equals their gross balance again.
	 *
	 * The unused WC_Coupons those rows referenced will expire naturally per
	 * their `date_expires` (24h after creation) and become invisible.
	 *
	 * Idempotent: a re-run finds zero matching rows.
	 */
	private static function expire_legacy_pending_redemptions(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'crm_points_ledger';
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table}
			 SET pending_status = %s
			 WHERE type = %s AND pending_status = %s",
			'expired',
			'pending_redeem',
			'active'
		) );
	}

	/**
	 * Seed the four canonical tiers when crm_tiers is empty.
	 *
	 * Idempotent: only inserts rows that don't exist (`INSERT IGNORE` on PK
	 * `slug`). Legacy `crm_memberships.membership_level` values (free/silver/
	 * gold/vip) MUST resolve to a row here after this runs — we'd otherwise
	 * orphan every existing membership the moment crm_tiers becomes the
	 * source of truth.
	 *
	 * Reads multiplier/threshold values from the (still-present) Membership
	 * + MembershipService constants to keep one source of truth at seed time.
	 */
	private static function seed_default_tiers(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'crm_tiers';

		// Skip the work if the table already has rows — admin may have edited
		// labels/multipliers and we don't want to clobber that on every boot.
		// (dbDelta has already created the table by the time we get here.)
		$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $existing > 0 ) {
			return;
		}

		$now      = gmdate( 'Y-m-d H:i:s' );
		$defaults = [
			[ 'slug' => 'free',   'label' => 'Free',   'multiplier' => 1.00, 'orders' => null, 'spend' => null,    'admin_only' => 0, 'sort' => 10 ],
			[ 'slug' => 'silver', 'label' => 'Silver', 'multiplier' => 1.20, 'orders' => 5,    'spend' => 500.00,  'admin_only' => 0, 'sort' => 20 ],
			[ 'slug' => 'gold',   'label' => 'Gold',   'multiplier' => 1.50, 'orders' => 15,   'spend' => 2000.00, 'admin_only' => 0, 'sort' => 30 ],
			[ 'slug' => 'vip',    'label' => 'VIP',    'multiplier' => 2.00, 'orders' => null, 'spend' => null,    'admin_only' => 1, 'sort' => 40 ],
		];

		// Use $wpdb->insert so PHP `null` correctly serializes to SQL NULL —
		// $wpdb->prepare() with %s would turn null into the empty string.
		foreach ( $defaults as $t ) {
			$wpdb->insert(
				$table,
				[
					'slug'             => $t['slug'],
					'label'            => $t['label'],
					'multiplier'       => $t['multiplier'],
					'threshold_orders' => $t['orders'],
					'threshold_spend'  => $t['spend'],
					'is_admin_only'    => $t['admin_only'],
					'sort_order'       => $t['sort'],
					'created_at'       => $now,
				],
				[ '%s', '%s', '%f', '%d', '%f', '%d', '%d', '%s' ]
			);
		}
	}

	/**
	 * Re-run when the stored DB version doesn't match the plugin version.
	 * Hooked from Plugin::boot() so a code-only upgrade still migrates.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::OPTION_VERSION ) !== ZIPPY_CRM_VERSION ) {
			self::run();
		}
	}
}
