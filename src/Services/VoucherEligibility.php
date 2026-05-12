<?php
namespace ZippyCrm\Services;

use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates whether a voucher can be applied to the customer's current cart,
 * and (if not) produces a customer-friendly reason string for the checkout
 * tray widget.
 *
 * Two layers feed into "eligible":
 *   1. Claim eligibility — handled by ClaimHandler::validate (status, expiry,
 *      audience, already-claimed). Used for "can the customer even get this?"
 *   2. Cart eligibility — handled here. Given the voucher's WC coupon, would
 *      WC_Discounts accept it against the current cart? (min spend, product
 *      restrictions, etc.)
 *
 * The widget separates the two: claimed-and-cart-eligible gets an Apply CTA,
 * unclaimed-and-cart-eligible gets a Claim & apply CTA, everything else is
 * shown locked with a reason.
 */
final class VoucherEligibility {

	/**
	 * @return array{eligible:bool, reason?:string}
	 */
	public static function evaluate_for_cart( array $voucher ): array {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return [ 'eligible' => false, 'reason' => __( 'Checkout is not ready yet.', 'zippy-crm' ) ];
		}

		// Time-based checks first — cheap, and the most common reason a
		// claimed voucher won't apply ("you saved it but waited too long").
		$now = DateTimeHelper::now_mysql();
		if ( ! empty( $voucher['expires_at'] ) && $voucher['expires_at'] <= $now ) {
			return [ 'eligible' => false, 'reason' => __( 'Expired', 'zippy-crm' ) ];
		}
		if ( ! empty( $voucher['starts_at'] ) && $voucher['starts_at'] > $now ) {
			return [
				'eligible' => false,
				'reason'   => sprintf(
					/* translators: %s: date the voucher becomes active */
					__( 'Available from %s', 'zippy-crm' ),
					self::format_date( $voucher['starts_at'] )
				),
			];
		}

		// Min spend — fast cart-level check. We compute against the post-coupon
		// total so a customer with another coupon already on the cart doesn't
		// see "Spend $20 more" when their pre-discount subtotal would qualify.
		$min = (float) ( $voucher['min_order_amount'] ?? 0 );
		if ( $min > 0 ) {
			$cart_total = self::cart_subtotal_post_coupons();
			if ( $cart_total < $min ) {
				$delta = $min - $cart_total;
				return [
					'eligible' => false,
					'reason'   => sprintf(
						/* translators: %s: amount short of min spend, e.g. $5.00 */
						__( 'Add %s to unlock', 'zippy-crm' ),
						self::price_plain( $delta )
					),
				];
			}
		}

		// Hand off to WC for the remaining checks (product/category includes
		// and excludes, individual-use stacking, usage-per-customer limits).
		// Multi-code vouchers have a synthetic master code (`ZC_MULTI_*`) that
		// doesn't correspond to a real WC_Coupon — per-customer codes are
		// minted on claim. We skip the WC checks for unclaimed multi-code
		// rows here; cart fit will be re-evaluated against the real assigned
		// code at the claim-and-apply step.
		$mode = (string) ( $voucher['distribution_mode'] ?? 'single_code' );
		if ( $mode === 'multi_code_public' ) {
			return [ 'eligible' => true, 'reason' => null ];
		}

		$code   = (string) $voucher['code'];
		$coupon = new \WC_Coupon( $code );
		if ( $coupon->get_id() <= 0 ) {
			return [ 'eligible' => false, 'reason' => __( 'Not yet ready — please refresh.', 'zippy-crm' ) ];
		}

		// Already in the cart? Then it's already "applied" — eligible, no
		// action needed by the caller.
		if ( WC()->cart->has_discount( $code ) ) {
			return [ 'eligible' => true, 'reason' => null, 'already_applied' => true ];
		}

		$discounts = new \WC_Discounts( WC()->cart );
		$result    = $discounts->is_coupon_valid( $coupon );
		if ( is_wp_error( $result ) ) {
			return [ 'eligible' => false, 'reason' => self::map_wc_error( $result, $coupon ) ];
		}

		return [ 'eligible' => true, 'reason' => null ];
	}

	/**
	 * Maps a WC_Discounts validation error to a customer-friendly one-liner.
	 * WC's error codes are integers exposed via $error->get_error_code() —
	 * see WC_Coupon::COUPON_REMOVED_INVALID_REMOVED and the E_WC_COUPON_*
	 * constants in WC core. We cover the cases customers will actually see;
	 * a default fallback handles anything we missed.
	 */
	private static function map_wc_error( \WP_Error $error, \WC_Coupon $coupon ): string {
		$code = $error->get_error_code();

		// WC's coupon-validation error constants are class constants on
		// WC_Coupon. We use the numeric values directly so this file doesn't
		// have to import WC's class — keeps the lookup simple and explicit.
		switch ( (int) $code ) {
			case 108: // E_WC_COUPON_NOT_APPLICABLE — product/category mismatch
				return __( 'Not eligible for items in your cart', 'zippy-crm' );

			case 109: // E_WC_COUPON_MIN_SPEND_LIMIT_NOT_MET
				$min = (float) $coupon->get_minimum_amount();
				return sprintf( /* translators: %s: min spend */ __( 'Spend at least %s to unlock', 'zippy-crm' ), self::price_plain( $min ) );

			case 110: // E_WC_COUPON_NOT_VALID_SALE_ITEMS — coupon excludes sale items, but cart has only sale items
				return __( 'Not eligible — items are already on sale', 'zippy-crm' );

			case 111: // E_WC_COUPON_MAX_SPEND_LIMIT_MET
				$max = (float) $coupon->get_maximum_amount();
				return sprintf( /* translators: %s: max spend */ __( 'Order must be under %s', 'zippy-crm' ), self::price_plain( $max ) );

			case 112: // E_WC_COUPON_EXCLUDED_PRODUCTS
			case 113: // E_WC_COUPON_EXCLUDED_CATEGORIES
				return __( 'Some items in your cart aren\'t eligible for this voucher', 'zippy-crm' );

			case 114: // E_WC_COUPON_USAGE_LIMIT_REACHED — global cap exhausted
				return __( 'Fully redeemed', 'zippy-crm' );

			case 115: // E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK — already in cart (handled above; defensive)
				return __( 'Already applied', 'zippy-crm' );

			case 105: // E_WC_COUPON_EXPIRED
				return __( 'Expired', 'zippy-crm' );

			case 106: // E_WC_COUPON_INVALID_REMOVED — coupon was removed since the page loaded
				return __( 'No longer available', 'zippy-crm' );

			case 117: // E_WC_COUPON_NOT_YOURS_REMOVED — email restriction mismatch
				return __( 'Not for your account', 'zippy-crm' );

			default:
				// Surface WC's message rather than a vague "Not eligible" —
				// most uncovered codes have decent default copy in WC core.
				$msg = (string) $error->get_error_message();
				return $msg !== '' ? $msg : __( 'Not eligible for this order', 'zippy-crm' );
		}
	}

	/**
	 * Subtotal after coupon discounts but before fees (we don't include our
	 * own points-redemption fee — circular). Mirrors what
	 * PointsController::tender_payload computes.
	 */
	private static function cart_subtotal_post_coupons(): float {
		$cart = WC()->cart;
		if ( ! empty( $cart->get_cart() ) ) {
			$cart->calculate_totals();
		}
		$subtotal     = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
		$coupon_total = 0.0;
		foreach ( $cart->get_applied_coupons() as $applied ) {
			$coupon_total += (float) $cart->get_coupon_discount_amount( $applied, $cart->display_cart_ex_tax );
			$coupon_total += (float) $cart->get_coupon_discount_tax_amount( $applied );
		}
		return max( 0.0, $subtotal - $coupon_total );
	}

	/**
	 * Plain-text money string for reason copy. `wc_price()` returns HTML
	 * (`<span class="...">$5.00</span>`), which leaks markup into React
	 * when the widget renders the reason as text. We strip the tags but
	 * keep the symbol + formatted number WC produces, so locale-specific
	 * formatting (currency position, decimals, thousands separator) is
	 * preserved.
	 */
	private static function price_plain( float $amount ): string {
		return html_entity_decode( wp_strip_all_tags( (string) wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	private static function format_date( string $mysql ): string {
		$ts = strtotime( $mysql );
		if ( $ts === false ) {
			return $mysql;
		}
		return date_i18n( get_option( 'date_format', 'Y-m-d' ), $ts );
	}
}
