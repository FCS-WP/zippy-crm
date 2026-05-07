<?php
namespace ZippyCrm\Hooks;

defined( 'ABSPATH' ) || exit;

final class WooCommerce {

	public static function register(): void {
		// Membership
		add_action( 'woocommerce_created_customer',       [ self::class, 'on_customer_created' ], 10, 3 );
		add_action( 'woocommerce_register_form',          [ self::class, 'render_optin_field' ] );

		// Points
		add_action( 'woocommerce_order_status_completed', [ self::class, 'on_order_completed' ] );
	}

	public static function on_customer_created( int $user_id, array $data, bool $password_generated ): void {
		// TODO: MembershipService::create_on_register, SubsManager::save_optin_preference, seed PointsSummary.
	}

	public static function render_optin_field(): void {
		// TODO: SubsManager::render_optin_field().
	}

	public static function on_order_completed( int $order_id ): void {
		// TODO: PointsEngine::award_for_order, MembershipService::evaluate_tier_upgrade,
		//       ClaimHandler::mark_used (if a claim coupon was applied).
	}
}
