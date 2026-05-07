<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

final class Installer {

	public static function run(): void {
		// TODO: dbDelta() the seven crm_* tables defined in CLAUDE.md.
		// Tables:
		//   crm_memberships, crm_points_ledger, crm_points_summary,
		//   crm_vouchers, crm_voucher_claims, crm_notif_subs, crm_notification_log.
		update_option( 'zippy_crm_db_version', ZIPPY_CRM_VERSION );
	}
}
