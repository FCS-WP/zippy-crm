<?php
namespace ZippyCrm\Hooks;

use ZippyCrm\Models\NotifSub;
use ZippyCrm\Models\PointsSummary;
use ZippyCrm\Services\ClaimHandler;
use ZippyCrm\Services\MembershipService;
use ZippyCrm\Services\PointsEngine;
use ZippyCrm\Services\SubsManager;

defined( 'ABSPATH' ) || exit;

final class WooCommerce {

	public static function register(): void {
		// Membership
		add_action( 'woocommerce_created_customer',       [ self::class, 'on_customer_created' ], 10, 3 );
		add_action( 'woocommerce_register_form',          [ self::class, 'render_optin_field' ] );

		// Tier evaluation + points (Points engine TODO)
		add_action( 'woocommerce_order_status_completed', [ self::class, 'on_order_completed' ] );

		// Cart page: mount-point for the points-tender widget. Renders just
		// above the totals box so the user sees "Use points" before they see
		// what they owe. The empty <div> is hydrated by the cart bundle.
		add_action( 'woocommerce_before_cart_totals',     [ self::class, 'render_cart_points_mount' ] );

		// Cleanup on user delete
		add_action( 'delete_user',                        [ self::class, 'on_user_deleted' ] );
	}

	public static function render_cart_points_mount(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		echo '<div id="zippy-crm-cart-points" class="zippy-crm-mount"></div>';
	}

	public static function on_customer_created( int $user_id, array $data = [], bool $password_generated = false ): void {
		MembershipService::on_customer_created( $user_id );
		PointsSummary::set( $user_id, 0, 0, 0 );
		SubsManager::on_customer_created( $user_id );
	}

	public static function render_optin_field(): void {
		SubsManager::render_optin_field();
	}

	public static function on_order_completed( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = (int) $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return; // guest order — no membership to update
		}

		// Order matters:
		//   1. tier (so the multiplier reflects the new level on the very order
		//      that pushed them up to it)
		//   2. earn (uses subtotal-after-discounts, so any applied voucher coupon
		//      already reduces the earned amount appropriately; the points-tender
		//      fee is post-tax so it doesn't affect subtotal here)
		//   3. settle voucher claims (mark used + bump uses_count for CRM
		//      vouchers used on this order)
		//
		// Note: PointsTender::settle_for_order runs at priority 30 on this
		// same hook (registered separately in PointsTender::register), so the
		// points debit happens AFTER everything above. That's correct — the
		// debit shouldn't influence earn calculations.
		MembershipService::evaluate_tier_upgrade( $user_id );
		PointsEngine::award_for_order( $order_id );
		ClaimHandler::consume_for_order( $order_id );
	}

	public static function on_user_deleted( int $user_id ): void {
		\ZippyCrm\Models\Membership::delete_for_user( $user_id );
		PointsSummary::delete_for_user( $user_id );
		\ZippyCrm\Models\VoucherClaim::delete_for_user( $user_id );
		NotifSub::delete_for_user( $user_id );
		\ZippyCrm\Models\NotificationLog::delete_for_user( $user_id );
		// crm_points_ledger rows are kept for audit history.
		MembershipService::invalidate( $user_id );
		PointsEngine::invalidate( $user_id );
		ClaimHandler::invalidate_user_cache( $user_id );
		SubsManager::invalidate( $user_id );
	}
}
