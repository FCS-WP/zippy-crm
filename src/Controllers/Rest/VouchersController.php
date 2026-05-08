<?php
namespace ZippyCrm\Controllers\Rest;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 */
final class VouchersController {

	public static function list_available( \WP_REST_Request $r ) { /* TODO */ return rest_ensure_response( [] ); }
	public static function list_my_claims( \WP_REST_Request $r ) { /* TODO */ return rest_ensure_response( [] ); }
	public static function claim( \WP_REST_Request $r )          { /* TODO */ return rest_ensure_response( [] ); }
	public static function admin_list( \WP_REST_Request $r )     { /* TODO */ return rest_ensure_response( [] ); }
	public static function admin_create( \WP_REST_Request $r )   { /* TODO */ return rest_ensure_response( [] ); }
	public static function admin_update( \WP_REST_Request $r )   { /* TODO */ return rest_ensure_response( [] ); }
	public static function admin_delete( \WP_REST_Request $r )   { /* TODO */ return rest_ensure_response( [] ); }
}
