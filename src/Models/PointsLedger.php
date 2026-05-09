<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Append-only ledger of every point change.
 *
 * NEVER UPDATE or DELETE rows here — corrections go in as a new `adjust` row.
 * The audit trail is the product. Read patterns are paginated, newest first.
 */
final class PointsLedger {

	public const TABLE = 'crm_points_ledger';

	public const TYPES = [ 'earn', 'redeem', 'expire', 'adjust', 'pending_redeem' ];

	public const PENDING_ACTIVE   = 'active';
	public const PENDING_CONSUMED = 'consumed';
	public const PENDING_EXPIRED  = 'expired';

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Insert a single ledger row. Returns the new id, or 0 on failure.
	 *
	 * For `pending_redeem` rows, pass `$reserved_points` (the points locked in
	 * the coupon) and `$pending_status = self::PENDING_ACTIVE`. The `points`
	 * column stays at 0 so balance is unaffected until consumption.
	 */
	public static function insert(
		int $user_id,
		string $type,
		int $points,
		?string $description = null,
		?int $order_id = null,
		?int $reserved_points = null,
		?string $pending_status = null
	): int {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return 0;
		}
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			[
				'user_id'         => $user_id,
				'order_id'        => $order_id,
				'type'            => $type,
				'points'          => $points,
				'reserved_points' => $reserved_points,
				'pending_status'  => $pending_status,
				'description'     => $description,
				'created_at'      => DateTimeHelper::now_mysql(),
			],
			[ '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s' ]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/* ============================================================
	 * Reservation helpers
	 * ============================================================ */

	public static function get_reserved_total( int $user_id ): int {
		global $wpdb;
		$sql = QueryLoader::query( 'points/get_reserved_total.sql' );
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $user_id ) );
	}

	/**
	 * @return array{id:int, user_id:int, reserved_points:int}|null
	 */
	public static function find_pending_by_code( string $coupon_code ): ?array {
		global $wpdb;
		$sql = QueryLoader::query( 'points/find_pending_by_code.sql' );
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $coupon_code ), ARRAY_A );
		if ( ! $row ) {
			return null;
		}
		return [
			'id'              => (int) $row['id'],
			'user_id'         => (int) $row['user_id'],
			'reserved_points' => (int) $row['reserved_points'],
		];
	}

	/**
	 * Conditional UPDATE — succeeds only if the row is still active.
	 * Returns true if this caller "won" the consumption race.
	 */
	public static function mark_pending_consumed( int $row_id, int $order_id ): bool {
		global $wpdb;
		$sql      = QueryLoader::query( 'points/mark_pending_consumed.sql' );
		$affected = $wpdb->query( $wpdb->prepare( $sql, $order_id, $row_id ) );
		return (int) $affected === 1;
	}

	/**
	 * @return array{items: array<int,array<string,mixed>>, total: int, page: int, per_page: int, total_pages: int}
	 */
	public static function get_paginated( int $user_id, int $page = 1, int $per_page = 10 ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		global $wpdb;

		$items_sql = QueryLoader::query( 'points/get_ledger_paginated.sql' );
		$items     = $wpdb->get_results(
			$wpdb->prepare( $items_sql, $user_id, $per_page, $offset ),
			ARRAY_A
		) ?: [];

		$count_sql = QueryLoader::query( 'points/count_ledger.sql' );
		$total     = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $user_id ) );

		return [
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
		];
	}

	/**
	 * Reaggregate from the ledger — source of truth when the summary cache drifts.
	 *
	 * @return array{total_earned:int, total_redeemed:int, balance:int}
	 */
	public static function recalculate( int $user_id ): array {
		global $wpdb;
		$sql = QueryLoader::query( 'points/recalculate_balance.sql' );
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $user_id ), ARRAY_A );
		return [
			'total_earned'   => (int) ( $row['total_earned']   ?? 0 ),
			'total_redeemed' => (int) ( $row['total_redeemed'] ?? 0 ),
			'balance'        => (int) ( $row['balance']        ?? 0 ),
		];
	}

	/* ============================================================
	 * Admin
	 * ============================================================ */

	private const FILTER_ALL = '__all__';

	/** Types the admin recent-ledger filter accepts. Excludes pending_redeem. */
	public const ADMIN_FILTER_TYPES = [ 'earn', 'redeem', 'expire', 'adjust' ];

	/**
	 * Recent global ledger rows for the admin Points panel. Joins user data
	 * inline so the table renders without N+1.
	 *
	 * @return array{items: array<int,array<string,mixed>>, total: int, page: int, per_page: int, total_pages: int}
	 */
	public static function get_recent_for_admin( string $type, int $page = 1, int $per_page = 20 ): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$type_active = $type !== '' && in_array( $type, self::ADMIN_FILTER_TYPES, true );
		$type_token  = $type_active ? $type : self::FILTER_ALL;

		global $wpdb;

		$items_sql = QueryLoader::query( 'admin/points/recent_ledger.sql' );
		$items     = $wpdb->get_results(
			$wpdb->prepare( $items_sql, $type_token, $type_token, $per_page, $offset ),
			ARRAY_A
		) ?: [];

		$count_sql = QueryLoader::query( 'admin/points/count_recent_ledger.sql' );
		$total     = (int) $wpdb->get_var(
			$wpdb->prepare( $count_sql, $type_token, $type_token )
		);

		return [
			'items'       => $items,
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
		];
	}
}
