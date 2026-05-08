<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\NotifSub;
use ZippyCrm\Support\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the registration-form opt-in fields and the preferences read/write
 * surface used by the My Account tab + REST.
 *
 * Cache is per-user; the only writes are user-driven (registration + tab save),
 * so simple delete-on-write invalidation is enough.
 */
final class SubsManager {

	private const CACHE_KEY = 'notif_subs:%d';

	private const FIELD_VOUCHERS = 'crm_subscribe_vouchers';
	private const FIELD_POINTS   = 'crm_subscribe_points';

	/**
	 * @return array{subscribed_vouchers:bool, subscribed_points:bool, updated_at:?string}
	 */
	public static function get_for_user( int $user_id ): array {
		$key = sprintf( self::CACHE_KEY, $user_id );
		return Cache::remember( $key, fn() => NotifSub::get_for_user( $user_id ) );
	}

	public static function update_preferences( int $user_id, bool $vouchers, bool $points ): array {
		NotifSub::upsert( $user_id, $vouchers, $points );
		Cache::delete( sprintf( self::CACHE_KEY, $user_id ) );

		do_action( 'crm_notif_preferences_updated', $user_id, $vouchers, $points );

		return self::get_for_user( $user_id );
	}

	public static function invalidate( int $user_id ): void {
		Cache::delete( sprintf( self::CACHE_KEY, $user_id ) );
	}

	/* ============================================================
	 * Registration form
	 * ============================================================ */

	/**
	 * Hook target: woocommerce_register_form. Renders two opt-in checkboxes,
	 * both checked by default per FEATURE_SPEC §4.1.
	 */
	public static function render_optin_field(): void {
		$vouchers_checked = self::checkbox_default( self::FIELD_VOUCHERS );
		$points_checked   = self::checkbox_default( self::FIELD_POINTS );
		?>
		<p class="form-row form-row-wide zc-register-optin">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::FIELD_VOUCHERS ); ?>" value="1" <?php checked( $vouchers_checked ); ?> />
				<?php esc_html_e( 'Notify me about new vouchers and promotions', 'zippy-crm' ); ?>
			</label>
		</p>
		<p class="form-row form-row-wide zc-register-optin">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::FIELD_POINTS ); ?>" value="1" <?php checked( $points_checked ); ?> />
				<?php esc_html_e( 'Notify me about points and rewards updates', 'zippy-crm' ); ?>
			</label>
		</p>
		<?php
	}

	/**
	 * Hook target: woocommerce_created_customer. Reads the form values and
	 * persists them. If a user submits without the checkboxes (e.g. an
	 * older form), defaults to opted-in per spec.
	 */
	public static function on_customer_created( int $user_id ): void {
		$vouchers = self::read_checkbox( self::FIELD_VOUCHERS );
		$points   = self::read_checkbox( self::FIELD_POINTS );
		NotifSub::upsert( $user_id, $vouchers, $points );
		Cache::delete( sprintf( self::CACHE_KEY, $user_id ) );
	}

	/**
	 * Sticky behavior across reloads: if the user just submitted with the box
	 * unchecked, render it unchecked on the redisplay too.
	 */
	private static function checkbox_default( string $name ): bool {
		// On a fresh form, $_POST is empty → spec default = checked.
		// On a redisplay after error, $_POST exists → preserve user choice.
		if ( ! isset( $_POST['register'] ) && ! isset( $_POST[ $name ] ) ) {
			return true;
		}
		return self::read_checkbox( $name );
	}

	private static function read_checkbox( string $name ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST[ $name ] ) && (string) $_POST[ $name ] === '1';
	}
}
