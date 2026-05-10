<?php
namespace ZippyCrm\Hooks;

use ZippyCrm\Models\Voucher;
use ZippyCrm\Models\VoucherClaim;
use ZippyCrm\Models\VoucherCode;
use ZippyCrm\Services\ClaimHandler;

defined( 'ABSPATH' ) || exit;

/**
 * Cascade-revoke handler for WC coupons that mirror CRM vouchers.
 *
 * Why this exists: an admin can delete a WC coupon directly from the
 * WooCommerce → Coupons screen. Without this hook, customers who already
 * claimed the voucher continue to see it as "Ready to use" on My Claims —
 * but the code itself fails at checkout (worse: WC throws an uncaught
 * `Invalid coupon` exception, breaking the page).
 *
 * Behaviour by voucher type:
 *
 *   Single-code voucher
 *     — one WC coupon ↔ one CRM voucher row
 *     — deletion expires the voucher AND every 'claimed' claim against it.
 *       Customer's My Claims now shows "Expired" instead of a working code.
 *
 *   Multi-code voucher (one campaign, N codes)
 *     — each code is its own WC coupon
 *     — deletion of one code only expires THAT code row + its claim. The
 *       parent voucher and other codes are unaffected.
 *
 * We listen to `before_delete_post` (force-delete) AND `wp_trash_post`
 * (soft-trash). A trashed coupon also stops working at checkout, so it
 * needs the same cascade. Re-publishing a trashed coupon (untrashed_post)
 * is rare enough that we don't auto-restore claims — admin would re-publish
 * via the CRM voucher panel instead.
 */
final class WcCouponDelete {

	public static function register(): void {
		add_action( 'before_delete_post', [ self::class, 'on_coupon_deleted' ], 10, 2 );
		add_action( 'wp_trash_post',      [ self::class, 'on_coupon_trashed' ] );
	}

	/**
	 * Hook target: before_delete_post (fires before any post is deleted —
	 * we filter to shop_coupon ourselves).
	 *
	 * @param int      $post_id
	 * @param \WP_Post|null $post
	 */
	public static function on_coupon_deleted( int $post_id, $post = null ): void {
		if ( ! $post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post || $post->post_type !== 'shop_coupon' ) {
			return;
		}
		self::cascade_for_coupon_code( (string) $post->post_title );
	}

	/**
	 * Hook target: wp_trash_post (any post moved to trash).
	 */
	public static function on_coupon_trashed( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'shop_coupon' ) {
			return;
		}
		self::cascade_for_coupon_code( (string) $post->post_title );
	}

	/**
	 * Resolve the coupon code to one of:
	 *   1. a multi-code child row in crm_voucher_codes (matched by code)
	 *   2. a single-code parent row in crm_vouchers (matched by code)
	 *   3. nothing — non-CRM coupon, no-op
	 *
	 * The WC coupon's `post_title` IS the code; that's how WC stores it.
	 * We could also read the `_zc_voucher_id` meta but that's set per-coupon
	 * and the code lookup is more robust against mis-synced rows.
	 */
	private static function cascade_for_coupon_code( string $code ): void {
		$code = strtoupper( trim( (string) $code ) );
		if ( $code === '' ) {
			return;
		}

		// Multi-code: this WC coupon is a child code in a campaign. Expire
		// only the code row + its claim; the parent voucher stays alive.
		$code_row = VoucherCode::find_by_code( $code );
		if ( $code_row ) {
			self::expire_multi_code( (int) $code_row['id'], (int) $code_row['voucher_id'] );
			return;
		}

		// Single-code: WC coupon mirrors a CRM voucher 1:1.
		$voucher = Voucher::find_by_code( $code );
		if ( $voucher ) {
			self::expire_single_voucher( (int) $voucher['id'] );
			return;
		}

		// Not a CRM-managed coupon — leave alone. Removes the meta-only
		// false positive where some other plugin happened to set
		// `_zc_voucher_id` on its own coupon.
	}

	/**
	 * Expire a single-code voucher and every still-claimed claim against it.
	 * Idempotent — re-running on an already-expired voucher is a no-op.
	 */
	private static function expire_single_voucher( int $voucher_id ): void {
		global $wpdb;

		Voucher::update_status( $voucher_id, 'expired' );

		// Walk affected claims to invalidate per-user caches; the bulk update
		// itself is one query.
		$user_ids = $wpdb->get_col( $wpdb->prepare(
			'SELECT user_id FROM ' . $wpdb->prefix . VoucherClaim::TABLE
			. ' WHERE voucher_id = %d AND status = %s',
			$voucher_id,
			'claimed'
		) );

		$wpdb->update(
			$wpdb->prefix . VoucherClaim::TABLE,
			[ 'status' => 'expired', 'revocation_reason' => 'cascade_coupon' ],
			[ 'voucher_id' => $voucher_id, 'status' => 'claimed' ],
			[ '%s', '%s' ],
			[ '%d', '%s' ]
		);

		foreach ( (array) $user_ids as $uid ) {
			ClaimHandler::invalidate_user_cache( (int) $uid );
		}
	}

	/**
	 * Expire one code row inside a multi-code voucher + its claim.
	 *
	 * The code row's status flips `available|assigned → expired`. If a user
	 * had claimed this specific code, their claim row also flips to
	 * `expired` so My Claims renders the right pill. The parent voucher's
	 * `uses_count` is left alone — that tracks completed redemptions, not
	 * cancellations.
	 */
	private static function expire_multi_code( int $code_id, int $voucher_id ): void {
		global $wpdb;

		// Find the user (if any) whose claim is linked to this code row,
		// so we can flip the claim + invalidate their cache.
		$user_id = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT user_id FROM ' . $wpdb->prefix . VoucherClaim::TABLE
			. ' WHERE voucher_id = %d AND code_id = %d AND status = %s LIMIT 1',
			$voucher_id,
			$code_id,
			'claimed'
		) );

		// Flip the code row.
		$wpdb->update(
			$wpdb->prefix . VoucherCode::TABLE,
			[ 'status' => 'expired' ],
			[ 'id' => $code_id ],
			[ '%s' ],
			[ '%d' ]
		);

		// Flip the claim, if any.
		if ( $user_id > 0 ) {
			$wpdb->update(
				$wpdb->prefix . VoucherClaim::TABLE,
				[ 'status' => 'expired', 'revocation_reason' => 'cascade_coupon' ],
				[ 'voucher_id' => $voucher_id, 'code_id' => $code_id, 'status' => 'claimed' ],
				[ '%s', '%s' ],
				[ '%d', '%d', '%s' ]
			);
			ClaimHandler::invalidate_user_cache( $user_id );
		}
	}
}
