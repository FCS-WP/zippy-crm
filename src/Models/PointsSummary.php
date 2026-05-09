<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Cached point totals — 1 row per user. The hot read path: balance is checked
 * on cart, checkout, and the My Account points tab. Always update on every
 * ledger write so the cache never lags.
 *
 * Invariant: balance = total_earned - total_redeemed = SUM(ledger.points).
 */
final class PointsSummary {

	public const TABLE = 'crm_points_summary';

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** @return array<string,mixed>|null */
	public static function find( int $user_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT user_id, total_earned, total_redeemed, balance, updated_at
				 FROM ' . self::table() . ' WHERE user_id = %d',
				$user_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Apply a delta atomically. Used after every ledger insert.
	 * Inserts the row if missing — handles users created before plugin install.
	 */
	public static function apply_delta( int $user_id, int $points ): void {
		global $wpdb;
		$now = DateTimeHelper::now_mysql();

		$earned_delta   = $points > 0 ? $points  : 0;
		$redeemed_delta = $points < 0 ? -$points : 0;

		// Single-statement upsert — atomic against concurrent writes.
		$sql = $wpdb->prepare(
			'INSERT INTO ' . self::table() . '
				(user_id, total_earned, total_redeemed, balance, updated_at)
			 VALUES (%d, %d, %d, %d, %s)
			 ON DUPLICATE KEY UPDATE
				total_earned   = total_earned   + VALUES(total_earned),
				total_redeemed = total_redeemed + VALUES(total_redeemed),
				balance        = balance        + %d,
				updated_at     = VALUES(updated_at)',
			$user_id,
			$earned_delta,
			$redeemed_delta,
			$points, // initial balance for INSERT
			$now,
			$points  // delta for UPDATE
		);
		$wpdb->query( $sql );
	}

	/** Replace the row with authoritative values. Used by recalculate. */
	public static function set( int $user_id, int $earned, int $redeemed, int $balance ): void {
		global $wpdb;
		$wpdb->replace(
			self::table(),
			[
				'user_id'        => $user_id,
				'total_earned'   => max( 0, $earned ),
				'total_redeemed' => max( 0, $redeemed ),
				'balance'        => $balance,
				'updated_at'     => DateTimeHelper::now_mysql(),
			],
			[ '%d', '%d', '%d', '%d', '%s' ]
		);
	}

	public static function delete_for_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'user_id' => $user_id ], [ '%d' ] );
	}

	/* ============================================================
	 * Admin
	 * ============================================================ */

	/**
	 * System-wide totals used by the admin Points panel.
	 *
	 * @return array{issued:int, redeemed:int, outstanding:int, members:int}
	 */
	public static function system_totals(): array {
		global $wpdb;
		$sql = QueryLoader::query( 'admin/points/system_summary.sql' );
		$row = $wpdb->get_row( $sql, ARRAY_A );
		return [
			'issued'      => (int) ( $row['issued']      ?? 0 ),
			'redeemed'    => (int) ( $row['redeemed']    ?? 0 ),
			'outstanding' => (int) ( $row['outstanding'] ?? 0 ),
			'members'     => (int) ( $row['members']     ?? 0 ),
		];
	}

	/**
	 * Every user_id with a points summary row. Source list for
	 * PointsAdmin::recalculate_all().
	 *
	 * @return array<int,int>
	 */
	public static function all_user_ids(): array {
		global $wpdb;
		$sql = QueryLoader::query( 'admin/points/list_user_ids.sql' );
		$ids = $wpdb->get_col( $sql );
		return array_map( 'intval', $ids ?: [] );
	}
}
