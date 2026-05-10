<?php
namespace ZippyCrm\Controllers\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {

	public const PARENT_SLUG = 'zippy-crm';

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
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
		add_submenu_page( self::PARENT_SLUG, __( 'Tiers',    'zippy-crm' ), __( 'Tiers',    'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-tiers',    [ TiersController::class,    'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Vouchers', 'zippy-crm' ), __( 'Vouchers', 'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-vouchers', [ VouchersController::class, 'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Points',   'zippy-crm' ), __( 'Points',   'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-points',   [ PointsController::class,   'render' ] );
		add_submenu_page( self::PARENT_SLUG, __( 'Reports',  'zippy-crm' ), __( 'Reports',  'zippy-crm' ),
			'manage_woocommerce', self::PARENT_SLUG . '-reports',  [ ReportsController::class,  'render' ] );
	}
}
