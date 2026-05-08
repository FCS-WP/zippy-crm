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

		// Cleanup on user delete
		add_action( 'delete_user',                        [ self::class, 'on_user_deleted' ] );
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
		//   2. earn (uses subtotal-after-discounts, so any applied coupon already
		//      reduces the earned amount appropriately)
		//   3. consume reservations (debit points locked in CRM-RDM coupons used here)
		//   4. consume voucher claims (mark used + bump uses_count for CRM vouchers used here)
		MembershipService::evaluate_tier_upgrade( $user_id );
		PointsEngine::award_for_order( $order_id );
		PointsEngine::consume_redemptions_for_order( $order_id );
		ClaimHandler::consume_for_order( $order_id );
	}

	public static function on_user_deleted( int $user_id ): void {
		\ZippyCrm\Models\Membership::delete_for_user( $user_id );
		PointsSummary::delete_for_user( $user_id );
		\ZippyCrm\Models\VoucherClaim::delete_for_user( $user_id );
		NotifSub::delete_for_user( $user_id );
		// crm_points_ledger rows are kept for audit history.
		MembershipService::invalidate( $user_id );
		PointsEngine::invalidate( $user_id );
		ClaimHandler::invalidate_user_cache( $user_id );
		SubsManager::invalidate( $user_id );
	}
}
