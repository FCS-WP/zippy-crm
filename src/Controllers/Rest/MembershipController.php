<?php
namespace ZippyCrm\Controllers\Rest;

defined( 'ABSPATH' ) || exit;

final class MembershipController {

	public static function register_routes(): void {
		register_rest_route( ZIPPY_CRM_REST_NAMESPACE, '/membership/me', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ self::class, 'get_me' ],
			'permission_callback' => [ self::class, 'auth_user' ],
		] );
	}

	public static function auth_user(): bool {
		return is_user_logged_in();
	}

	public static function get_me( \WP_REST_Request $request ) {
		// TODO: return current user's membership + tier progress.
		return rest_ensure_response( [] );
	}
}
