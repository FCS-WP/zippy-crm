<?php
namespace ZippyCrm\Controllers\Admin;

use ZippyCrm\Support\Markdown;

defined( 'ABSPATH' ) || exit;

/**
 * In-product Documentation page (Zippy CRM → Documentation).
 *
 * Renders one of the markdown docs in docs/guide/ as HTML inside wp-admin.
 * Pure server-side: no React bundle, no REST routes, no DB. The markdown
 * source files live in the repo so the team can PR doc improvements like
 * code, and a clean `git log docs/guide/` traces every wording change.
 *
 * The selected doc is chosen by the `doc` query arg (sanitized against the
 * manifest — anything not whitelisted falls back to the first entry, so an
 * attacker can't read arbitrary files via path traversal).
 */
final class DocsController {

	public const MENU_SLUG = 'zippy-crm-docs';

	/** Cached manifest (loaded lazily by manifest()). */
	private static ?array $manifest = null;

	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'zippy-crm' ) );
		}

		$manifest = self::manifest();
		$active   = self::resolve_active( $manifest );
		$html     = self::render_doc( $active['file'] );

		include ZIPPY_CRM_DIR . 'src/Views/admin/docs/page.php';
	}

	/**
	 * Whitelist-resolve the active doc from `?doc=...`. Falls back to the
	 * first entry if the param is missing or unknown.
	 *
	 * @return array{slug:string,title:string,file:string,group:string}
	 */
	private static function resolve_active( array $manifest ): array {
		$requested = isset( $_GET['doc'] ) ? sanitize_key( wp_unslash( $_GET['doc'] ) ) : '';
		if ( $requested !== '' ) {
			foreach ( $manifest as $entry ) {
				if ( $entry['slug'] === $requested ) {
					return $entry;
				}
			}
		}
		return $manifest[0];
	}

	/**
	 * Read a doc file from docs/guide/ and render it. Filename is taken
	 * straight from the manifest, never from user input — but we guard with
	 * a basename() pass anyway in case a future contributor edits the
	 * manifest carelessly.
	 */
	private static function render_doc( string $file ): string {
		$safe = basename( $file );
		$path = ZIPPY_CRM_DIR . 'docs/guide/' . $safe;
		if ( ! is_readable( $path ) ) {
			return '<p class="zc-doc-missing">' . esc_html(
				/* translators: %s: doc filename */
				sprintf( __( 'Documentation page "%s" is missing.', 'zippy-crm' ), $safe )
			) . '</p>';
		}
		$md   = (string) file_get_contents( $path );
		$html = Markdown::render( $md );
		// Final defensive pass — strips anything wp_kses_post considers unsafe.
		return wp_kses_post( $html );
	}

	/**
	 * @return array<int,array{slug:string,title:string,file:string,group:string}>
	 */
	public static function manifest(): array {
		if ( self::$manifest !== null ) {
			return self::$manifest;
		}
		$path = ZIPPY_CRM_DIR . 'docs/guide/manifest.php';
		self::$manifest = is_readable( $path ) ? (array) require $path : [];
		return self::$manifest;
	}

	/**
	 * Sidebar nav helper — groups manifest entries by `group` field while
	 * preserving manifest order within each group. Entries flagged
	 * `hidden => true` are filtered out (still resolvable by direct URL via
	 * resolve_active(); just don't appear in the visible nav).
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function grouped(): array {
		$grouped = [];
		foreach ( self::manifest() as $entry ) {
			if ( ! empty( $entry['hidden'] ) ) {
				continue;
			}
			$grouped[ $entry['group'] ][] = $entry;
		}
		return $grouped;
	}
}
