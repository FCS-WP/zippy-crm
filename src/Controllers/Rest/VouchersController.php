<?php
namespace ZippyCrm\Controllers\Rest;

defined( 'ABSPATH' ) || exit;

final class VouchersController {

	public static function register_routes(): void {
		$ns = ZIPPY_CRM_REST_NAMESPACE;

		register_rest_route( $ns, '/vouchers', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ self::class, 'list_available' ],
			'permission_callback' => [ self::class, 'auth_user' ],
		] );

		register_rest_route( $ns, '/vouchers/claims', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ self::class, 'list_my_claims' ],
			'permission_callback' => [ self::class, 'auth_user' ],
		] );

		register_rest_route( $ns, '/vouchers/(?P<id>\d+)/claim', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ self::class, 'claim' ],
			'permission_callback' => [ self::class, 'auth_user' ],
			'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
		] );

		// Admin CRUD
		register_rest_route( $ns, '/admin/vouchers', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'admin_list' ],
				'permission_callback' => [ self::class, 'auth_admin' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ self::class, 'admin_create' ],
				'permission_callback' => [ self::class, 'auth_admin' ],
			],
		] );

		register_rest_route( $ns, '/admin/vouchers/(?P<id>\d+)', [
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ self::class, 'admin_update' ],
				'permission_callback' => [ self::class, 'auth_admin' ],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ self::class, 'admin_delete' ],
				'permission_callback' => [ self::class, 'auth_admin' ],
			],
		] );
	}

	public static function auth_user():  bool { return is_user_logged_in(); }
	public static function auth_admin(): bool { return current_user_can( 'manage_woocommerce' ); }

	public static function list_available( \WP_REST_Request $r ) { /* TODO */ return rest_ensure_response( [] ); }
	public static function list_my_claims( \WP_REST_Request $r ) { /* TODO */ return rest_ensure_response( [] ); }
	public static function claim( \WP_REST_Request $r )          { /* TODO */ return rest_ensure_response( [] ); }
	public static function admin_list( \WP_REST_Request $r )     { /* TODO */ return rest_ensure_response( [] ); }
	public static function admin_create( \WP_REST_Request $r )   { /* TODO */ return rest_ensure_response( [] ); }
	public static function admin_update( \WP_REST_Request $r )   { /* TODO */ return rest_ensure_response( [] ); }
	public static function admin_delete( \WP_REST_Request $r )   { /* TODO */ return rest_ensure_response( [] ); }
}
