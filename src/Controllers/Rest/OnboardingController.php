<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Services\NotifEngine;
use ZippyCrm\Services\OnboardingState;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Per-admin onboarding state. Each admin has their own progress; state
 * lives in user_meta via Services\OnboardingState.
 *
 * Routes wired in src/Core/routes.php:
 *   GET  /admin/onboarding/state  — read current admin's step + dismissed
 *   PUT  /admin/onboarding/state  — partial update (step and/or dismissed)
 *
 * Auth: manage_woocommerce (matches the rest of the admin REST surface).
 */
final class OnboardingController {

	public static function get_state( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return RestResponse::error( 'unauthorized', __( 'You must be logged in.', 'zippy-crm' ), 401 );
		}
		return RestResponse::ok( OnboardingState::get_for_user( $user_id ) );
	}

	public static function update_state( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return RestResponse::error( 'unauthorized', __( 'You must be logged in.', 'zippy-crm' ), 401 );
		}

		$step      = $request->get_param( 'step' );
		$dismissed = $request->get_param( 'dismissed' );

		// Partial update — only touch what was sent.
		if ( $step !== null ) {
			OnboardingState::set_step( $user_id, (int) $step );
		}
		if ( $dismissed === true || $dismissed === 1 || $dismissed === '1' ) {
			OnboardingState::dismiss( $user_id );
		}

		// Return the post-write state so the client has a single source of truth.
		return RestResponse::ok( OnboardingState::get_for_user( $user_id ) );
	}

	/**
	 * Send a sample voucher-notification email to the current admin's
	 * registered address. Used by the Notifications step in onboarding so
	 * admins can verify mail config (SPF/DKIM/SMTP) before publishing a
	 * real voucher and discovering deliverability bugs the customer way.
	 *
	 * Rate-limit: 1 send per minute per user via a transient. Admins
	 * clicking the button repeatedly while waiting for inbox delivery
	 * shouldn't spam.
	 */
	public static function send_test_email( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return RestResponse::error( 'unauthorized', __( 'You must be logged in.', 'zippy-crm' ), 401 );
		}

		$throttle_key = 'zippy_crm_onb_testmail_' . $user_id;
		if ( get_transient( $throttle_key ) ) {
			return RestResponse::error(
				'rate_limited',
				__( 'Please wait a moment before sending another test email.', 'zippy-crm' ),
				429
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->user_email ) ) {
			return RestResponse::error( 'no_admin_email', __( 'Your admin account has no email address.', 'zippy-crm' ), 400 );
		}

		// Claim the throttle slot BEFORE sending. If wp_mail fails the admin
		// still has to wait 60s to retry — but that's a feature, not a bug:
		// repeated wp_mail failures are usually SMTP misconfig and pounding
		// the mailer doesn't help. Admin sees a friendly retry-after message.
		set_transient( $throttle_key, 1, 60 );

		$result = NotifEngine::send_test_email( $user_id, (string) $user->user_email );
		if ( $result instanceof \WP_Error ) {
			return RestResponse::error( $result->get_error_code(), $result->get_error_message(), 500 );
		}

		return RestResponse::ok( [
			'sent_to' => (string) $user->user_email,
		] );
	}

	/**
	 * System readiness check for the onboarding Welcome step. Reports the
	 * three things that genuinely block the plugin from being useful:
	 *
	 *   wc_active           — WooCommerce plugin must be loaded
	 *   hpos_enabled        — orders go through HPOS, not legacy postmeta
	 *   customer_accounts   — WC allows customer account creation
	 *
	 * Anything else (mail config, cron, etc.) is downstream — the plugin
	 * still works; specific features may degrade. We surface those as
	 * warnings inside their relevant steps, not as a hard gate here.
	 */
	public static function check_prereqs( \WP_REST_Request $request ) {
		$wc_active = class_exists( '\WooCommerce' );

		$hpos_enabled = false;
		if ( $wc_active && class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			$hpos_enabled = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}

		// `woocommerce_enable_signup_and_login_from_checkout` plus the
		// "Allow customers to create an account during checkout" option are
		// the two practical gates. We read the explicit option here.
		$customer_accounts = $wc_active && get_option( 'woocommerce_enable_signup_and_login_from_checkout' ) === 'yes';

		return RestResponse::ok( [
			'wc_active'         => $wc_active,
			'hpos_enabled'      => $hpos_enabled,
			'customer_accounts' => $customer_accounts,
		] );
	}
}
