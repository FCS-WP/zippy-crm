<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {

	public const PARENT_SLUG     = 'zippy-crm';
	public const ONBOARDING_SLUG = 'zippy-crm-onboarding';

	/** wp_options flag set on activation; consumed once by maybe_redirect_on_activation. */
	public const ACTIVATION_FLAG_OPTION = 'zippy_crm_show_onboarding';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		// Runs on every admin page load. The handler bails fast if the flag
		// isn't set; only the very first post-activation page load triggers
		// the redirect.
		add_action( 'admin_init', [ self::class, 'maybe_redirect_on_activation' ] );
	}

	public static function add_menu(): void {
		add_menu_page(
			__( 'Zippy CRM', 'zippy-crm' ),
			__( 'Zippy CRM', 'zippy-crm' ),
			'manage_woocommerce',
			self::PARENT_SLUG,
			[ MembersController::class, 'render' ],
			'dashicons-groups',
			56
		);

		add_submenu_page( self::PARENT_SLUG, __( 'Members',  'zippy-crm' ), __( 'Members',  'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG,             [ MembersController::class,  'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Users',    'zippy-crm' ), __( 'Users',    'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-users',    [ UsersController::class,    'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Tiers',    'zippy-crm' ), __( 'Tiers',    'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-tiers',    [ TiersController::class,    'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Vouchers', 'zippy-crm' ), __( 'Vouchers', 'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-vouchers', [ VouchersController::class, 'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Points',   'zippy-crm' ), __( 'Points',   'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-points',   [ PointsController::class,   'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Reports',  'zippy-crm' ), __( 'Reports',  'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-reports',  [ ReportsController::class,  'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Settings', 'zippy-crm' ), __( 'Settings', 'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-settings', [ SettingsController::class, 'render' ] );
		// Documentation — in-product user guide. Read-only static content
		// rendered from docs/guide/*.md so the team can PR doc improvements
		// like code. Sits next to Audit log because both are reference, not
		// day-to-day management screens.
		add_submenu_page( self::PARENT_SLUG, __( 'Documentation', 'zippy-crm' ), __( 'Documentation', 'zippy-crm' ),
			'manage_woocommerce', DocsController::MENU_SLUG,       [ DocsController::class,     'render' ] );
		// Audit log goes last — it's a read-only log, less day-to-day than the
		// management screens above. Keeps the everyday workflow at the top.
		add_submenu_page( self::PARENT_SLUG, __( 'Audit log', 'zippy-crm' ), __( 'Audit log', 'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-audit',    [ AuditController::class,    'render' ] );

		// Hidden first-run guide. Null parent_slug = page exists but no menu
		// entry. Admins land here via the activation auto-redirect (handled
		// in maybe_redirect_on_activation) or the "View setup guide" link
		// in the Settings panel header (Phase 3).
		add_submenu_page(
			'',
			__( 'Zippy CRM — Setup Guide', 'zippy-crm' ),
			__( 'Zippy CRM — Setup Guide', 'zippy-crm' ),
			'manage_woocommerce',
			self::ONBOARDING_SLUG,
			[ OnboardingController::class, 'render' ]
		);
	}

	/**
	 * One-shot redirect to the onboarding page on the first admin page load
	 * after activation. Bails fast on any path where redirecting would be
	 * wrong:
	 *
	 *   - flag not set (the common path; near-zero cost)
	 *   - already on the onboarding page (don't redirect-loop)
	 *   - any AJAX / REST / cron / WP-CLI request — never a UI session
	 *   - user lacks `manage_woocommerce` — different admin caps shouldn't
	 *     hijack a non-CRM admin's first admin page load
	 *   - network-admin context on multisite — site-level flag, not network
	 *
	 * Clears the flag *before* redirecting so a redirect failure (filter,
	 * etc.) doesn't trap the user in an infinite loop.
	 */
	public static function maybe_redirect_on_activation(): void {
		if ( ! get_option( self::ACTIVATION_FLAG_OPTION ) ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		if ( is_network_admin() ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// Already on the onboarding page — don't loop.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page === self::ONBOARDING_SLUG ) {
			delete_option( self::ACTIVATION_FLAG_OPTION );
			return;
		}

		// Clear FIRST, then redirect — keeps recovery clean if redirect fails.
		delete_option( self::ACTIVATION_FLAG_OPTION );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::ONBOARDING_SLUG ) );
		exit;
	}
}
