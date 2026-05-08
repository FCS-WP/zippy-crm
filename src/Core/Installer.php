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
	];

	public static function run(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( self::SCHEMAS as $file ) {
			dbDelta( QueryLoader::schema( $file ) );
		}

		update_option( self::OPTION_VERSION, ZIPPY_CRM_VERSION, false );
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
