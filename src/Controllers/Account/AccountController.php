<?php
namespace ZippyCrm\Controllers\Account;

defined( 'ABSPATH' ) || exit;

final class AccountController {

	public static function register(): void {
		add_filter( 'woocommerce_account_menu_items',                [ self::class, 'menu_items' ] );
		add_action( 'woocommerce_account_crm-membership_endpoint',    [ self::class, 'render_membership' ] );
		add_action( 'woocommerce_account_crm-points_endpoint',        [ self::class, 'render_points' ] );
		add_action( 'woocommerce_account_crm-vouchers_endpoint',      [ self::class, 'render_vouchers' ] );
		add_action( 'woocommerce_account_crm-notifications_endpoint', [ self::class, 'render_notifications' ] );
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
