<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		// Core
		Installer::maybe_upgrade();
		Assets::register();
		Endpoints::register();
		RouteRegistrar::register();

		// Hooks
		\ZippyCrm\Hooks\WooCommerce::register();
		\ZippyCrm\Hooks\Cron::register();

		// Account (frontend My Account tabs)
		\ZippyCrm\Controllers\Account\AccountController::register();

		// Admin
		if ( is_admin() ) {
			\ZippyCrm\Controllers\Admin\AdminMenu::register();
		}
	}

	public static function on_activate(): void {
		Installer::run();
		Endpoints::register();
		flush_rewrite_rules();
	}

	public static function on_deactivate(): void {
		flush_rewrite_rules();
	}
}
