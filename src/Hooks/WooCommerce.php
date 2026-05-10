<?php
namespace ZippyCrm\Hooks;

use ZippyCrm\Models\NotifSub;
use ZippyCrm\Models\PointsSummary;
use ZippyCrm\Services\ClaimHandler;
use ZippyCrm\Services\MembershipService;
use ZippyCrm\Services\PointsEngine;
use ZippyCrm\Services\PointsTender;
use ZippyCrm\Services\SubsManager;

defined( 'ABSPATH' ) || exit;

final class WooCommerce {

	public static function register(): void {
		// Membership
		add_action( 'woocommerce_created_customer',       [ self::class, 'on_customer_created' ], 10, 3 );
		add_action( 'woocommerce_register_form',          [ self::class, 'render_optin_field' ] );

		// Tier evaluation + points (Points engine TODO)
		add_action( 'woocommerce_order_status_completed', [ self::class, 'on_order_completed' ] );

		// Order didn't pay through. WC's "hold stock" feature auto-cancels
		// pending orders after the configured timeout (Settings → Products →
		// Inventory). Admins can also cancel manually. In both cases we want
		// to release whatever the customer reserved against this order:
		//   - applied points (user_meta + session) so they get a clean slate
		//   - multi-code voucher slots (assigned → available) so other
		//     qualifying customers can still claim them
		// Failed orders fire a parallel cleanup — same logic, different status.
		add_action( 'woocommerce_order_status_cancelled', [ self::class, 'on_order_cancelled' ] );
		add_action( 'woocommerce_order_status_failed',    [ self::class, 'on_order_cancelled' ] );

		// Checkout page: mount-point for the points-tender widget. Renders
		// just above the payment methods so the user decides redemption
		// against the final number (with shipping/tax). The empty <div> is
		// hydrated by the checkout bundle.
		// v1.13.0: moved from `woocommerce_before_cart_totals`.
		add_action( 'woocommerce_review_order_before_payment', [ self::class, 'render_checkout_points_mount' ] );

		// Cleanup on user delete
		add_action( 'delete_user',                        [ self::class, 'on_user_deleted' ] );
	}

	public static function render_checkout_points_mount(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}
		echo '<div id="zippy-crm-checkout-points" class="zippy-crm-mount"></div>';
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

	/**
	 * Hook target: woocommerce_order_status_cancelled, _failed.
	 *
	 * The customer never paid through. Two things to revert:
	 *   1. Applied points stored against the order (`_zc_points_applied` meta) —
	 *      these were never debited from the balance, but the user_meta /
	 *      session still holds the "I plan to apply N points" intent. Clear
	 *      it so the next checkout starts clean.
	 *   2. Multi-code voucher slots — the customer claimed a code, applied
	 *      it to the order, then the order died. Release the code back to
	 *      `available` so other qualifying customers can claim it.
	 *
	 * Idempotent: status transitions can fire more than once on edge cases
	 * (e.g. cancelled then re-cancelled via admin tools); both reverts use
	 * conditional UPDATEs so a second call is a no-op.
	 */
	public static function on_order_cancelled( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Skip orders that already settled (i.e. went completed → refunded →
		// cancelled). The credit-back path on refund handled those points;
		// running the revert here would double-credit. The order's
		// _zc_points_settled meta is the canonical "we awarded points
		// already" marker — bail if it's set.
		if ( $order->get_meta( PointsTender::META_SETTLED ) !== '' ) {
			return;
		}

		PointsTender::revert_for_order( $order_id );
		ClaimHandler::release_for_order( $order_id );
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
