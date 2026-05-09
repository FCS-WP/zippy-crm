<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * crm_voucher_claims. The UNIQUE (voucher_id, user_id) constraint is the
 * authoritative double-claim guard — claim() relies on $wpdb->last_error to
 * detect the collision rather than a pre-flight SELECT (which has a TOCTOU
 * window under double-click).
 */
final class VoucherClaim {

	public const TABLE = 'crm_voucher_claims';

	public const STATUSES = [ 'claimed', 'used', 'expired' ];

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Insert a claim. Returns the new id, 0 on UNIQUE collision (already
	 * claimed), -1 on any other failure.
	 */
	public static function claim( int $voucher_id, int $user_id ): int {
		global $wpdb;
		$wpdb->suppress_errors( true );
		$ok = $wpdb->insert(
			self::table(),
			[
				'voucher_id' => $voucher_id,
				'user_id'    => $user_id,
				'status'     => 'claimed',
				'claimed_at' => DateTimeHelper::now_mysql(),
			],
			[ '%d', '%d', '%s', '%s' ]
		);
		$err = $wpdb->last_error;
		$wpdb->suppress_errors( false );

		if ( $ok ) {
			return (int) $wpdb->insert_id;
		}
		// MySQL "Duplicate entry … for key 'uq_claim'" — treat as already claimed.
		if ( $err !== '' && stripos( $err, 'duplicate' ) !== false ) {
			return 0;
		}
		return -1;
	}

	/** @return array<int,array<string,mixed>> */
	public static function list_for_user( int $user_id ): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'vouchers/list_my_claims.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ), ARRAY_A );
		return $rows ?: [];
	}

	/** @return array<string,mixed>|null */
	public static function find_for_user( int $voucher_id, int $user_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, voucher_id, user_id, status, claimed_at, used_at, order_id
				 FROM ' . self::table() . '
				 WHERE voucher_id = %d AND user_id = %d',
				$voucher_id,
				$user_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Conditional UPDATE — only flips claimed → used. Returns true on success.
	 */
	public static function mark_used( int $voucher_id, int $user_id, int $order_id ): bool {
		global $wpdb;
		$sql = QueryLoader::query( 'vouchers/mark_claim_used.sql' );
		$affected = $wpdb->query( $wpdb->prepare(
			$sql,
			$order_id,
			DateTimeHelper::now_mysql(),
			$voucher_id,
			$user_id
		) );
		return (int) $affected === 1;
	}

	public static function delete_for_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'user_id' => $user_id ], [ '%d' ] );
	}

	/* ============================================================
	 * Admin
	 * ============================================================ */

	/**
	 * Number of claims attached to a voucher. Used by VoucherService::delete()
	 * as a refusal guard (vouchers with claims must not be hard-deleted).
	 */
	public static function count_for_voucher( int $voucher_id ): int {
		global $wpdb;
		$sql = QueryLoader::query( 'vouchers/admin/claim_count_for_voucher.sql' );
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $voucher_id ) );
	}

	/**
	 * Claims for a single voucher with user data joined, for the admin drawer.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_voucher( int $voucher_id ): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'vouchers/admin/list_claims_for_voucher.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $voucher_id ), ARRAY_A );
		return $rows ?: [];
	}
}
