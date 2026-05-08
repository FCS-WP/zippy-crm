<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Reads src/Core/routes.php and registers every route with WP REST in one pass.
 *
 * Manifest row shape:
 *   [
 *     'method'     => 'GET' | 'POST' | 'PUT' | 'DELETE' | array of those,
 *     'path'       => '/membership/me',
 *     'handler'    => [ Controller::class, 'method' ],
 *     'auth'       => 'public' | 'user' | 'manage_woocommerce' | 'manage_options' | callable,
 *     'args'       => [ ... ]   // optional, WP REST args schema
 *   ]
 *
 * Keeping the auth field as a short string keeps the manifest scannable; the
 * registrar expands it into the right permission callback.
 */
final class RouteRegistrar {

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'wire' ] );
	}

	public static function wire(): void {
		$routes = require ZIPPY_CRM_DIR . 'src/Core/routes.php';
		foreach ( (array) $routes as $route ) {
			self::register_one( $route );
		}
	}

	private static function register_one( array $route ): void {
		register_rest_route(
			ZIPPY_CRM_REST_NAMESPACE,
			$route['path'],
			[
				'methods'             => $route['method'],
				'callback'            => $route['handler'],
				'permission_callback' => self::resolve_auth( $route['auth'] ?? 'user' ),
				'args'                => $route['args'] ?? [],
			]
		);
	}

	/**
	 * Expand a short auth keyword into a permission_callback closure.
	 * Custom callables pass through untouched.
	 */
	private static function resolve_auth( $auth ): callable {
		if ( is_callable( $auth ) ) {
			return $auth;
		}
		return match ( $auth ) {
			'public'             => '__return_true',
			'user'               => static fn() => is_user_logged_in(),
			'manage_woocommerce' => static fn() => current_user_can( 'manage_woocommerce' ),
			'manage_options'     => static fn() => current_user_can( 'manage_options' ),
			default              => '__return_false',
		};
	}
}
