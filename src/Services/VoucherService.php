<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\Voucher;

defined( 'ABSPATH' ) || exit;

/**
 * Voucher lifecycle: create, publish, pause/resume.
 *
 * `publish()` syncs the WC coupon (creates if missing) and fires
 * `crm_voucher_published` for the Notifications feature to pick up later.
 */
final class VoucherService {

	public const COUPON_META_VOUCHER_ID = '_zc_voucher_id';

	public static function publish( int $voucher_id ): bool {
		$voucher = Voucher::find( $voucher_id );
		if ( ! $voucher ) {
			return false;
		}

		self::sync_wc_coupon( $voucher );

		Voucher::update_status( $voucher_id, 'active' );

		do_action( 'crm_voucher_published', $voucher_id );

		return true;
	}

	public static function pause( int $voucher_id ): bool {
		return Voucher::update_status( $voucher_id, 'paused' );
	}

	public static function resume( int $voucher_id ): bool {
		return Voucher::update_status( $voucher_id, 'active' );
	}

	/**
	 * Creates the WC coupon if it doesn't exist; updates amount/limits if it
	 * does. Idempotent — safe to call on every publish.
	 */
	public static function sync_wc_coupon( array $voucher ): int {
		$code      = (string) $voucher['code'];
		$coupon_id = wc_get_coupon_id_by_code( $code );

		$coupon = $coupon_id ? new \WC_Coupon( $coupon_id ) : new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( (string) $voucher['discount_type'] );
		$coupon->set_amount( (string) $voucher['discount_value'] );
		$coupon->set_minimum_amount( (string) $voucher['min_order_amount'] );
		$coupon->set_individual_use( true );

		if ( (int) $voucher['max_uses'] > 0 ) {
			$coupon->set_usage_limit( (int) $voucher['max_uses'] );
		}

		if ( ! empty( $voucher['expires_at'] ) ) {
			$coupon->set_date_expires( strtotime( $voucher['expires_at'] . ' UTC' ) );
		}

		$coupon->set_description( (string) ( $voucher['description'] ?? $voucher['title'] ) );
		$coupon->update_meta_data( self::COUPON_META_VOUCHER_ID, (int) $voucher['id'] );

		return (int) $coupon->save();
	}
}
