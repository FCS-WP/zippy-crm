<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

final class Assets {

	private const HANDLE_ACCOUNT  = 'zippy-crm-account';
	private const HANDLE_ADMIN    = 'zippy-crm-admin';
	private const HANDLE_CHECKOUT = 'zippy-crm-checkout';

	private const ENTRY_ACCOUNT  = 'assets/src/js/account/index.jsx';
	private const ENTRY_ADMIN    = 'assets/src/js/admin/index.jsx';
	private const ENTRY_CHECKOUT = 'assets/src/js/checkout/index.jsx';

	public static function register(): void {
		add_action( 'wp_enqueue_scripts',    [ self::class, 'enqueue_account' ] );
		add_action( 'wp_enqueue_scripts',    [ self::class, 'enqueue_checkout' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin' ] );
		add_filter( 'script_loader_tag',     [ self::class, 'as_module' ], 10, 3 );
	}

	public static function enqueue_account(): void {
		if ( ! self::is_account_endpoint() ) {
			return;
		}
		self::enqueue_entry( self::HANDLE_ACCOUNT, self::ENTRY_ACCOUNT );
	}

	/**
	 * Checkout-page-only enqueue for the points-tender widget. Conditional on
	 * `is_checkout()` so the bundle never loads on shop / product / cart pages —
	 * keeps the perf cost bounded to the one place the widget renders.
	 *
	 * v1.13.0: moved off the cart page. Customers decide redemption against
	 * the final number (with shipping/tax) rather than the cart subtotal.
	 */
	public static function enqueue_checkout(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return; // guests have no points balance to spend
		}
		self::enqueue_entry( self::HANDLE_CHECKOUT, self::ENTRY_CHECKOUT );
	}

	public static function enqueue_admin( string $hook ): void {
		if ( strpos( $hook, 'zippy-crm' ) === false ) {
			return;
		}
		self::enqueue_entry( self::HANDLE_ADMIN, self::ENTRY_ADMIN );
	}

	/**
	 * Walks the Vite manifest: enqueues the entry JS, every imported chunk,
	 * and any CSS those chunks reference. One handle per file, deps wired so
	 * shared chunks load before the entry.
	 */
	private static function enqueue_entry( string $handle, string $entry_key ): void {
		$manifest = self::manifest();
		if ( ! isset( $manifest[ $entry_key ] ) ) {
			return;
		}

		$entry   = $manifest[ $entry_key ];
		$base    = ZIPPY_CRM_URL . 'assets/dist/';
		$js_deps = [];

		// Imported shared chunks (e.g. the React + RQ chunk Vite splits out).
		foreach ( (array) ( $entry['imports'] ?? [] ) as $import_key ) {
			if ( empty( $manifest[ $import_key ]['file'] ) ) {
				continue;
			}
			$file = $manifest[ $import_key ]['file'];
			$import_handle = $handle . '-' . md5( $import_key );
			wp_enqueue_script( $import_handle, $base . $file, [], self::file_ver( $file ), true );
			$js_deps[] = $import_handle;

			foreach ( (array) ( $manifest[ $import_key ]['css'] ?? [] ) as $css_path ) {
				wp_enqueue_style( $import_handle . '-css-' . md5( $css_path ), $base . $css_path, [], self::file_ver( $css_path ) );
			}
		}

		// Entry's own CSS (rare with our setup, but supported).
		foreach ( (array) ( $entry['css'] ?? [] ) as $css_path ) {
			wp_enqueue_style( $handle . '-css-' . md5( $css_path ), $base . $css_path, [], self::file_ver( $css_path ) );
		}

		wp_enqueue_script( $handle, $base . $entry['file'], $js_deps, self::file_ver( $entry['file'] ), true );

		// Inline config — read by shared/api.js.
		wp_add_inline_script(
			$handle,
			'window.zippyCrm = ' . wp_json_encode( self::rest_settings() ) . ';',
			'before'
		);
	}

	/**
	 * Vite outputs ES modules — every script we enqueue from dist/ must carry
	 * type="module" or the browser will throw on `import` statements.
	 */
	public static function as_module( string $tag, string $handle, string $src ): string {
		if ( strpos( $handle, 'zippy-crm-' ) !== 0 ) {
			return $tag;
		}
		if ( strpos( $tag, ' type=' ) !== false ) {
			return $tag;
		}
		return str_replace( '<script ', '<script type="module" ', $tag );
	}

	private static function is_account_endpoint(): bool {
		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return false;
		}
		global $wp;
		$query_vars = (array) ( $wp->query_vars ?? [] );
		foreach ( Endpoints::SLUGS as $slug ) {
			if ( array_key_exists( $slug, $query_vars ) ) {
				return true;
			}
		}
		return false;
	}

	public static function manifest(): array {
		static $cache = null;
		if ( $cache !== null ) {
			return $cache;
		}
		$file  = ZIPPY_CRM_DIR . 'assets/dist/.vite/manifest.json';
		$cache = is_readable( $file ) ? (array) json_decode( file_get_contents( $file ), true ) : [];
		return $cache;
	}

	public static function rest_settings(): array {
		return [
			'root'        => esc_url_raw( rest_url( ZIPPY_CRM_REST_NAMESPACE . '/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'currentUser' => get_current_user_id(),
			// Account-side CTAs link to the cart for the points-tender flow.
			'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? esc_url_raw( wc_get_cart_url() ) : '/cart/',
			// `window.ajaxurl` is admin-only — expose it for theme-specific
			// frontend refreshers (e.g. ai-zippy's az_get_checkout_totals).
			'ajaxUrl'     => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
		];
	}

	/**
	 * Cache-busting version for a built asset under assets/dist/. Uses
	 * filemtime so any rebuild auto-busts without bumping ZIPPY_CRM_VERSION.
	 *
	 * Vite's hashed chunk filenames already self-bust on content change; this
	 * matters most for the unhashed entries (admin.js, account.js, styles.css).
	 *
	 * Falls back to the plugin version if the file is missing — defensive,
	 * shouldn't happen in production.
	 */
	private static function file_ver( string $relative ): string {
		static $cache = [];
		if ( isset( $cache[ $relative ] ) ) {
			return $cache[ $relative ];
		}
		$path = ZIPPY_CRM_DIR . 'assets/dist/' . ltrim( $relative, '/' );
		$mtime = is_readable( $path ) ? filemtime( $path ) : false;
		return $cache[ $relative ] = $mtime ? (string) $mtime : ZIPPY_CRM_VERSION;
	}
}
