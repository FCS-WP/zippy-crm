<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin compatibility checks. Right now: refuse to coexist with EPOS CRM,
 * since both plugins claim the customer/membership domain and would
 * step on each other's data.
 *
 * Two enforcement points:
 *   - on_activate (in Plugin::on_activate)  → wp_die before any tables are
 *     created or rewrites flushed
 *   - boot       (in Plugin::boot)          → skip our hooks + show a red
 *     notice if the conflict appears later (EPOS activated after Zippy CRM
 *     was already running)
 *
 * Slug is filterable so the team can adjust if EPOS CRM ships under a
 * different folder/main file:
 *
 *   add_filter( 'zippy_crm_conflicting_plugin', function () {
 *     return 'epos-crm-pro/epos-crm-pro.php';
 *   } );
 */
final class Compat {

	/** Default plugin slug we look for. Filterable. */
	public const DEFAULT_CONFLICT_SLUG = 'epos-crm/epos-crm.php';

	/**
	 * @return string The plugin-basename slug we treat as conflicting.
	 */
	public static function conflict_slug(): string {
		return (string) apply_filters( 'zippy_crm_conflicting_plugin', self::DEFAULT_CONFLICT_SLUG );
	}

	/**
	 * True when the conflicting plugin is currently active. Loads
	 * `wp-admin/includes/plugin.php` if `is_plugin_active()` isn't already
	 * available — it isn't on the front-end by default, but we sometimes
	 * call this from `plugins_loaded` (boot) where it might not be.
	 */
	public static function has_conflict(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( self::conflict_slug() );
	}

	/**
	 * Activation-time gate. Called from register_activation_hook. If the
	 * conflicting plugin is already active, abort with a wp_die screen so
	 * the admin sees the reason and gets a "back to plugins" link.
	 *
	 * Important: don't deactivate ourselves here — register_activation_hook
	 * already short-circuits on wp_die. Trying to call deactivate_plugins()
	 * here can corrupt the active_plugins option in some WP versions.
	 */
	public static function block_activation_if_conflict(): void {
		if ( ! self::has_conflict() ) {
			return;
		}
		$conflict = self::conflict_label();
		wp_die(
			wp_kses_post( sprintf(
				/* translators: %s: name of the conflicting plugin */
				__( '<h1>Cannot activate Zippy CRM</h1><p><strong>%s</strong> is already active. The two plugins both manage customer membership and points data — running them together would corrupt one of them.</p><p>Please deactivate %1$s first, then re-activate Zippy CRM.</p>', 'zippy-crm' ),
				$conflict
			) ),
			__( 'Plugin conflict', 'zippy-crm' ),
			[
				'back_link' => true,
				'response'  => 409,
			]
		);
	}

	/**
	 * Runtime guard. Called at the top of Plugin::boot. Returns true when we
	 * should NOT proceed with normal startup — caller should bail. Side
	 * effect: registers an admin_notices listener so the admin sees what
	 * happened.
	 */
	public static function should_skip_boot(): bool {
		if ( ! self::has_conflict() ) {
			return false;
		}
		add_action( 'admin_notices', [ self::class, 'render_runtime_conflict_notice' ] );
		return true;
	}

	/**
	 * Red persistent admin-notice rendered while both plugins are active.
	 * Tells the admin which plugin to deactivate to restore Zippy CRM.
	 */
	public static function render_runtime_conflict_notice(): void {
		// Only show on plugin-management screens + the dashboard so we don't
		// nag inside the admin chrome of unrelated pages. The admin notice
		// system collapses well, so keep it short.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$slug   = $screen?->id ?? '';
		$show_on = [ 'dashboard', 'plugins', 'plugins-network' ];
		if ( $slug && ! in_array( $slug, $show_on, true ) && strpos( $slug, 'zippy-crm' ) === false ) {
			return;
		}

		$conflict = self::conflict_label();
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Zippy CRM is paused.', 'zippy-crm' ),
			wp_kses_post( sprintf(
				/* translators: %s: conflicting plugin name */
				__( 'It cannot run alongside <strong>%s</strong>. Deactivate one of the two plugins to restore service.', 'zippy-crm' ),
				$conflict
			) )
		);
	}

	/**
	 * Pretty name for the conflicting plugin (read from its header). Falls
	 * back to "EPOS CRM" if the file isn't readable for any reason.
	 */
	private static function conflict_label(): string {
		$slug = self::conflict_slug();
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$path = WP_PLUGIN_DIR . '/' . $slug;
		if ( is_readable( $path ) ) {
			$data = get_plugin_data( $path, false, false );
			if ( ! empty( $data['Name'] ) ) {
				return (string) $data['Name'];
			}
		}
		return 'EPOS CRM';
	}
}
