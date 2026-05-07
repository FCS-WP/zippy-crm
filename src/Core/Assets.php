<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts',    [ self::class, 'enqueue_account' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin' ] );
	}

	public static function enqueue_account(): void {
		// TODO: enqueue dist/account.js + account.css on My Account CRM endpoints only.
	}

	public static function enqueue_admin( string $hook ): void {
		// TODO: enqueue dist/admin.js + admin.css on Zippy CRM admin pages only.
	}

	public static function manifest(): array {
		static $cache = null;
		if ( $cache !== null ) {
			return $cache;
		}
		$file = ZIPPY_CRM_DIR . 'assets/dist/.vite/manifest.json';
		$cache = is_readable( $file ) ? (array) json_decode( file_get_contents( $file ), true ) : [];
		return $cache;
	}

	public static function rest_settings(): array {
		return [
			'root'      => esc_url_raw( rest_url( ZIPPY_CRM_REST_NAMESPACE . '/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'currentUser' => get_current_user_id(),
		];
	}
}
