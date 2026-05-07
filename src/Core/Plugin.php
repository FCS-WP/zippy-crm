<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		// Core
		Assets::register();
		Endpoints::register();

		// Hooks
		\ZippyCrm\Hooks\WooCommerce::register();
		\ZippyCrm\Hooks\Cron::register();

		// Account (frontend My Account tabs)
		\ZippyCrm\Controllers\Account\AccountController::register();

		// Admin
		if ( is_admin() ) {
			\ZippyCrm\Controllers\Admin\AdminMenu::register();
		}

		// REST API
		add_action( 'rest_api_init', [ self::class, 'register_rest_routes' ] );
	}

	public static function register_rest_routes(): void {
		\ZippyCrm\Controllers\Rest\MembershipController::register_routes();
		\ZippyCrm\Controllers\Rest\PointsController::register_routes();
		\ZippyCrm\Controllers\Rest\VouchersController::register_routes();
		\ZippyCrm\Controllers\Rest\NotificationsController::register_routes();
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
