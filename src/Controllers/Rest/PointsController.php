<?php
namespace ZippyCrm\Controllers\Rest;

defined( 'ABSPATH' ) || exit;

final class PointsController {

	public static function register_routes(): void {
		$ns = ZIPPY_CRM_REST_NAMESPACE;

		register_rest_route( $ns, '/points/me', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ self::class, 'get_summary' ],
			'permission_callback' => [ self::class, 'auth_user' ],
		] );

		register_rest_route( $ns, '/points/ledger', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ self::class, 'get_ledger' ],
			'permission_callback' => [ self::class, 'auth_user' ],
			'args'                => [
				'page'     => [ 'type' => 'integer', 'default' => 1 ],
				'per_page' => [ 'type' => 'integer', 'default' => 10 ],
			],
		] );

		register_rest_route( $ns, '/points/redeem', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ self::class, 'redeem' ],
			'permission_callback' => [ self::class, 'auth_user' ],
			'args'                => [
				'points' => [ 'type' => 'integer', 'required' => true, 'minimum' => ZIPPY_CRM_MIN_REDEMPTION ],
			],
		] );
	}

	public static function auth_user(): bool {
		return is_user_logged_in();
	}

	public static function get_summary( \WP_REST_Request $r ) { /* TODO */ return rest_ensure_response( [] ); }
	public static function get_ledger( \WP_REST_Request $r )  { /* TODO */ return rest_ensure_response( [] ); }
	public static function redeem( \WP_REST_Request $r )      { /* TODO */ return rest_ensure_response( [] ); }
}
