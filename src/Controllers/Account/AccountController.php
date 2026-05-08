<?php
namespace ZippyCrm\Controllers\Account;

defined( 'ABSPATH' ) || exit;

final class AccountController {

	public static function register(): void {
		add_filter( 'woocommerce_account_menu_items',                [ self::class, 'menu_items' ] );
		add_filter( 'ai_zippy_account_nav_icons',                    [ self::class, 'nav_icons' ] );
		add_action( 'woocommerce_account_crm-membership_endpoint',    [ self::class, 'render_membership' ] );
		add_action( 'woocommerce_account_crm-points_endpoint',        [ self::class, 'render_points' ] );
		add_action( 'woocommerce_account_crm-vouchers_endpoint',      [ self::class, 'render_vouchers' ] );
		add_action( 'woocommerce_account_crm-notifications_endpoint', [ self::class, 'render_notifications' ] );
	}

	public static function nav_icons( array $icons ): array {
		$svg = static fn( string $body ): string =>
			'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';

		return $icons + [
			// Award/medal — membership tier
			'crm-membership'    => $svg( '<circle cx="12" cy="8" r="6"/><path d="M9 13.5 7.5 22 12 19l4.5 3-1.5-8.5"/>' ),
			// Coin/star — loyalty points
			'crm-points'        => $svg( '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>' ),
			// Ticket — vouchers
			'crm-vouchers'      => $svg( '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/><path d="M9 6v12"/>' ),
			// Bell — notifications
			'crm-notifications' => $svg( '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>' ),
		];
	}

	public static function menu_items( array $items ): array {
		$insert = [
			'crm-membership'    => __( 'Membership',    'zippy-crm' ),
			'crm-points'        => __( 'Points',         'zippy-crm' ),
			'crm-vouchers'      => __( 'Vouchers',       'zippy-crm' ),
			'crm-notifications' => __( 'Notifications', 'zippy-crm' ),
		];
		$pos = array_search( 'edit-account', array_keys( $items ), true );
		if ( $pos === false ) {
			return $items + $insert;
		}
		return array_slice( $items, 0, $pos, true ) + $insert + array_slice( $items, $pos, null, true );
	}

	public static function render_membership(): void    { self::render_view( 'membership' ); }
	public static function render_points(): void        { self::render_view( 'points' ); }
	public static function render_vouchers(): void      { self::render_view( 'vouchers' ); }
	public static function render_notifications(): void { self::render_view( 'notifications' ); }

	private static function render_view( string $tab ): void {
		include ZIPPY_CRM_DIR . 'src/Views/account/tab-' . $tab . '.php';
	}
}
