<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		// Refuse to run alongside conflicting CRM plugins. should_skip_boot()
		// also registers the admin notice as a side effect — see Compat.
		if ( Compat::should_skip_boot() ) {
			return;
		}

		// Core
		Installer::maybe_upgrade();
		Assets::register();
		Endpoints::register();
		RouteRegistrar::register();

		// Hooks
		\ZippyCrm\Hooks\WooCommerce::register();
		\ZippyCrm\Hooks\Cron::register();
		\ZippyCrm\Hooks\VoucherHourWindow::register();
		\ZippyCrm\Hooks\MembershipTierRevoker::register();
		\ZippyCrm\Services\AuditLogger::register();
		\ZippyCrm\Services\PointsTender::register();

		// Account (frontend My Account tabs)
		\ZippyCrm\Controllers\Account\AccountController::register();

		// Admin
		if ( is_admin() ) {
			\ZippyCrm\Controllers\Admin\AdminMenu::register();
		}
	}

	public static function on_activate(): void {
		// Hard-block activation if a conflicting CRM is already on. Calls
		// wp_die internally so we never return — admin sees a screen with
		// a "back" link.
		Compat::block_activation_if_conflict();

		Installer::run();
		Endpoints::register();
		flush_rewrite_rules();
	}

	public static function on_deactivate(): void {
		flush_rewrite_rules();
	}
}
