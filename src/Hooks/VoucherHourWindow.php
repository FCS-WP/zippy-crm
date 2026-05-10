<?php
namespace ZippyCrm\Hooks;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Services\VoucherService;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the per-voucher day-of-week + hour-of-day window. WC has no native
 * concept of this — `WC_Coupon` only knows about the date-range expiry.
 *
 * We hook `woocommerce_coupon_is_valid` (called both at apply time and on
 * every cart/checkout recalc). Returning false marks the coupon invalid for
 * this request; WC then surfaces the standard "coupon does not exist or is
 * not valid" error. We override the message via
 * `woocommerce_coupon_error` so the customer sees a useful explanation.
 *
 * The check is short-circuited when:
 *   - coupon has no `_zc_voucher_id` meta (not one of ours)
 *   - voucher row is gone (deleted CRM voucher; treat as invalid)
 *   - allowed_hours is null (no window restriction)
 *
 * Time comparisons use the SITE timezone (wp_timezone()) so admins specifying
 * "6pm-9pm Friday" mean local time, not UTC.
 */
final class VoucherHourWindow {

	public const ERROR_CODE = 998; // WC reserves <= 999 for built-ins; we use a high one.

	public static function register(): void {
		add_filter( 'woocommerce_coupon_is_valid', [ self::class, 'is_valid' ], 10, 3 );
		add_filter( 'woocommerce_coupon_error',    [ self::class, 'error_message' ], 10, 3 );
	}

	/**
	 * @param bool       $valid
	 * @param \WC_Coupon $coupon
	 * @param mixed      $discounts
	 */
	public static function is_valid( $valid, $coupon, $discounts = null ) {
		// Already invalid for some other reason — let it through unchanged.
		if ( ! $valid ) {
			return $valid;
		}

		$voucher_id = (int) $coupon->get_meta( VoucherService::COUPON_META_VOUCHER_ID );
		if ( $voucher_id <= 0 ) {
			return $valid; // not a CRM voucher
		}

		$voucher = Voucher::find( $voucher_id );
		if ( ! $voucher ) {
			// Orphaned coupon — voucher row was deleted. Refuse so the
			// admin's intent (delete) is honored at the customer-facing edge.
			throw new \Exception( __( 'This voucher is no longer available.', 'zippy-crm' ), self::ERROR_CODE );
		}

		$voucher = Voucher::decode_json_fields( $voucher );
		$window  = $voucher['allowed_hours'] ?? null;

		if ( ! self::has_window( $window ) ) {
			return true;
		}

		if ( ! self::matches_now( $window ) ) {
			throw new \Exception( self::format_window_error( $window ), self::ERROR_CODE );
		}

		return true;
	}

	/**
	 * Replace WC's generic message for our error code with the one we threw.
	 * WC built-in errors (1-15) keep their standard text.
	 *
	 * @param string $msg
	 * @param int    $err_code
	 * @param mixed  $coupon
	 */
	public static function error_message( $msg, $err_code, $coupon = null ) {
		// `is_valid` throws an Exception with our message + ERROR_CODE; WC
		// catches it and routes to this filter. No need to munge here, the
		// thrown message becomes the wc_add_notice() text. We're only kept
		// in the filter for symmetry / extensibility.
		return $msg;
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	/**
	 * A window is considered "set" when it picks at least one day AND has
	 * a non-empty hour range. Anything else means "no restriction".
	 *
	 * @param mixed $window
	 */
	private static function has_window( $window ): bool {
		if ( ! is_array( $window ) ) return false;
		$days = (array) ( $window['days'] ?? [] );
		$from = isset( $window['from_minute'] ) ? (int) $window['from_minute'] : null;
		$to   = isset( $window['to_minute'] )   ? (int) $window['to_minute']   : null;
		if ( empty( $days ) ) return false;
		if ( $from === null || $to === null ) return false;
		if ( $from === $to ) return false; // zero-width window — treat as "not configured"
		return true;
	}

	/**
	 * Returns true when the current site-local time falls inside the window.
	 * Wrapped windows (`from > to`, e.g. 22:00 → 02:00) cross midnight; in
	 * that case we accept times BEFORE `to` on the day after a matched day,
	 * which means matching either:
	 *   - today is in days AND now >= from
	 *   - yesterday was in days AND now < to
	 */
	private static function matches_now( array $window ): bool {
		$tz   = wp_timezone();
		$now  = new \DateTimeImmutable( 'now', $tz );
		$dow  = (int) $now->format( 'w' ); // 0=Sunday
		$mins = ( (int) $now->format( 'H' ) ) * 60 + (int) $now->format( 'i' );

		$days = array_map( 'intval', (array) $window['days'] );
		$from = (int) $window['from_minute'];
		$to   = (int) $window['to_minute'];

		if ( $from < $to ) {
			// Same-day window
			return in_array( $dow, $days, true ) && $mins >= $from && $mins < $to;
		}

		// Wrapped window: matches today >= from, OR (yesterday in days AND now < to)
		$prev_dow = ( $dow + 6 ) % 7;
		$today_match     = in_array( $dow, $days, true )      && $mins >= $from;
		$yesterday_match = in_array( $prev_dow, $days, true ) && $mins <  $to;
		return $today_match || $yesterday_match;
	}

	private static function format_window_error( array $window ): string {
		$days_label  = self::days_label( (array) $window['days'] );
		$hours_label = sprintf(
			'%s - %s',
			self::minute_label( (int) $window['from_minute'] ),
			self::minute_label( (int) $window['to_minute']   )
		);
		return sprintf(
			/* translators: 1: days like "Fri, Sat", 2: hours like "18:00 - 21:00" */
			__( 'This voucher is only valid %1$s %2$s.', 'zippy-crm' ),
			$days_label,
			$hours_label
		);
	}

	private static function days_label( array $days ): string {
		static $names = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
		$days = array_unique( array_map( 'intval', $days ) );
		sort( $days );
		if ( count( $days ) === 7 ) return __( 'every day', 'zippy-crm' );
		return implode( ', ', array_map( static fn( $d ) => $names[ $d ] ?? '?', $days ) );
	}

	private static function minute_label( int $m ): string {
		$m = max( 0, min( 1440, $m ) );
		$h  = (int) floor( $m / 60 );
		$mi = $m % 60;
		// 24h format — admin-facing; shorter than 12h with AM/PM.
		return sprintf( '%02d:%02d', $h, $mi );
	}
}
