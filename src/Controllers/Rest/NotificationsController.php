<?php
namespace ZippyCrm\Controllers\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 */
final class NotificationsController {

	public static function get_prefs( \WP_REST_Request $r )    { /* TODO */ return rest_ensure_response( [] ); }
	public static function update_prefs( \WP_REST_Request $r ) { /* TODO */ return rest_ensure_response( [] ); }
}
