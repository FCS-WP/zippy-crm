<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Per-code rows for multi-code voucher campaigns. See
 * src/Database/Schema/crm_voucher_codes.sql for the lifecycle.
 *
 * Status transitions go through `pick_available_for_user` (atomic claim),
 * `mark_used_by_order` (consumed), and `Voucher::increment_uses` (no-op
 * here — the voucher's uses_count tracks the *campaign* total).
 */
final class VoucherCode {

	public const TABLE = 'crm_voucher_codes';

	public const STATUS_AVAILABLE = 'available';
	public const STATUS_ASSIGNED  = 'assigned';
	public const STATUS_USED      = 'used';
	public const STATUS_EXPIRED   = 'expired';

	public const STATUSES = [
		self::STATUS_AVAILABLE,
		self::STATUS_ASSIGNED,
		self::STATUS_USED,
		self::STATUS_EXPIRED,
	];

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/* ============================================================
	 * Writes
	 * ============================================================ */

	/**
	 * Bulk-insert the codes minted at voucher publish time. Uses a single
	 * extended INSERT so creating 100 codes is one round-trip, not 100.
	 *
	 * Returns the number of rows inserted (0 on collision/error).
	 */
	public static function bulk_insert( int $voucher_id, array $codes ): int {
		if ( empty( $codes ) ) {
			return 0;
		}
		global $wpdb;
		$table = self::table();
		$now   = DateTimeHelper::now_mysql();

		$placeholders = [];
		$values       = [];
		foreach ( $codes as $code ) {
			$placeholders[] = '(%d, %s, %s, %s)';
			$values[]       = $voucher_id;
			$values[]       = (string) $code;
			$values[]       = self::STATUS_AVAILABLE;
			$values[]       = $now;
		}

		$sql = "INSERT INTO {$table} (voucher_id, code, status, created_at) VALUES "
			. implode( ', ', $placeholders );

		// Suppress duplicate-code errors so a single dup doesn't kill the
		// whole batch — caller is responsible for pre-deduping.
		$wpdb->suppress_errors( true );
		$ok = $wpdb->query( $wpdb->prepare( $sql, $values ) );
		$wpdb->suppress_errors( false );

		return $ok === false ? 0 : (int) $ok;
	}

	/**
	 * Atomic claim: assigns one available code to the user, returns the row
	 * or null if no codes are available.
	 *
	 * The race-safety guarantee: InnoDB row locks serialize concurrent
	 * UPDATEs that hit the same row. Two parallel claims either pick
	 * different rows (both succeed) or one picks the row first (the other
	 * sees status='assigned' on its second pass and skips). No customer can
	 * be assigned the same code as another.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function pick_available_for_user( int $voucher_id, int $user_id ): ?array {
		global $wpdb;
		$now    = DateTimeHelper::now_mysql();
		$update = QueryLoader::query( 'voucher_codes/pick_available_for_user.sql' );

		$affected = $wpdb->query( $wpdb->prepare( $update, $user_id, $now, $voucher_id ) );
		if ( (int) $affected !== 1 ) {
			return null; // exhausted
		}

		// Discover which row the UPDATE just picked. We can't use
		// $wpdb->insert_id (this is an UPDATE), so we look up by the
		// (user_id, assigned_at) tuple — only one row can match because
		// $now is unique-per-millisecond and we only ran one UPDATE.
		$select = QueryLoader::query( 'voucher_codes/find_assigned_for_user.sql' );
		$row    = $wpdb->get_row( $wpdb->prepare( $select, $voucher_id, $user_id, $now ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Conditional UPDATE — flips an assigned code to 'used'. Returns true
	 * only if the row was in 'assigned' state at the time (idempotent).
	 */
	public static function mark_used_by_order( string $code, int $order_id ): bool {
		global $wpdb;
		$sql = QueryLoader::query( 'voucher_codes/mark_used_by_order.sql' );
		$ok  = $wpdb->query( $wpdb->prepare(
			$sql,
			$order_id,
			DateTimeHelper::now_mysql(),
			$code
		) );
		return (int) $ok === 1;
	}

	/* ============================================================
	 * Reads
	 * ============================================================ */

	/**
	 * @return array<string,int>  status => count
	 */
	public static function counts_for_voucher( int $voucher_id ): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'voucher_codes/count_by_status_for_voucher.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $voucher_id ), ARRAY_A );
		$out  = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $rows as $r ) {
			$status = (string) ( $r['status'] ?? '' );
			if ( isset( $out[ $status ] ) ) {
				$out[ $status ] = (int) $r['total'];
			}
		}
		return $out;
	}

	public static function available_count_for_voucher( int $voucher_id ): int {
		return self::counts_for_voucher( $voucher_id )[ self::STATUS_AVAILABLE ] ?? 0;
	}

	/** @return array<string,mixed>|null */
	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, voucher_id, code, status, assigned_to_user, assigned_to_email,
				        assigned_at, used_at, order_id, created_at
				 FROM ' . self::table() . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/** @return array<string,mixed>|null */
	public static function find_by_code( string $code ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, voucher_id, code, status, assigned_to_user, assigned_to_email,
				        assigned_at, used_at, order_id, created_at
				 FROM ' . self::table() . ' WHERE code = %s',
				$code
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Admin list with optional status filter.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_voucher_admin( int $voucher_id, string $status_filter, int $page, int $per_page ): array {
		global $wpdb;
		$status_token = $status_filter !== '' && in_array( $status_filter, self::STATUSES, true )
			? $status_filter
			: '__all__';
		$page     = max( 1, $page );
		$per_page = max( 1, min( 200, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$sql  = QueryLoader::query( 'voucher_codes/list_for_voucher_admin.sql' );
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, $voucher_id, $status_token, $status_token, $per_page, $offset ),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/** Wipe a voucher's codes (used by admin delete + targeted-mode revoke). */
	public static function delete_for_voucher( int $voucher_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'voucher_id' => $voucher_id ], [ '%d' ] );
	}
}
