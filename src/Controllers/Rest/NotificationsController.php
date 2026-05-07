<?php
namespace ZippyCrm\Controllers\Rest;

defined( 'ABSPATH' ) || exit;

final class NotificationsController {

	public static function register_routes(): void {
		$ns = ZIPPY_CRM_REST_NAMESPACE;

		register_rest_route( $ns, '/notifications/preferences', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'get_prefs' ],
				'permission_callback' => [ self::class, 'auth_user' ],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ self::class, 'update_prefs' ],
				'permission_callback' => [ self::class, 'auth_user' ],
				'args'                => [
					'subscribe_vouchers' => [ 'type' => 'boolean', 'required' => true ],
					'subscribe_points'   => [ 'type' => 'boolean', 'required' => true ],
				],
			],
		] );
	}

	public static function auth_user(): bool {
		return is_user_logged_in();
	}

	public static function get_prefs( \WP_REST_Request $r )    { /* TODO */ return rest_ensure_response( [] ); }
	public static function update_prefs( \WP_REST_Request $r ) { /* TODO */ return rest_ensure_response( [] ); }
}
