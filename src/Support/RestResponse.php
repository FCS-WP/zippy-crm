<?php
namespace ZippyCrm\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Standard envelope helpers for REST controllers.
 * Keeps every endpoint's success/error shape identical.
 */
final class RestResponse {

	public static function ok( $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( $data, $status );
	}

	public static function error( string $code, string $message, int $status = 400, array $data = [] ): \WP_Error {
		return new \WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
	}
}
