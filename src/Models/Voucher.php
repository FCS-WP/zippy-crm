<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for crm_vouchers. Status transitions belong in VoucherService
 * (which knows how to keep the WC coupon in sync); this model only handles
 * read/write.
 */
final class Voucher {

	public const TABLE = 'crm_vouchers';

	public const STATUSES       = [ 'draft', 'active', 'paused', 'expired' ];
	public const DISCOUNT_TYPES = [ 'fixed_cart', 'percent' ];

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** @return array<string,mixed>|null */
	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/** @return array<string,mixed>|null */
	public static function find_by_code( string $code ): ?array {
		global $wpdb;
		$sql = QueryLoader::query( 'vouchers/find_by_code.sql' );
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $code ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Vouchers visible to a customer right now (active, unexpired, has quota,
	 * not already claimed by this user).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_available_for_user( int $user_id ): array {
		global $wpdb;
		$now  = DateTimeHelper::now_mysql();
		$sql  = QueryLoader::query( 'vouchers/list_available_unclaimed.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $now, $now ), ARRAY_A );
		return $rows ?: [];
	}

	/** Returns id of the new row, or 0 on failure. */
	public static function create( array $data, int $created_by ): int {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			[
				'code'             => strtoupper( (string) $data['code'] ),
				'title'            => (string) $data['title'],
				'description'      => $data['description'] ?? null,
				'discount_type'    => (string) $data['discount_type'],
				'discount_value'   => (float) $data['discount_value'],
				'min_order_amount' => (float) ( $data['min_order_amount'] ?? 0 ),
				'max_uses'         => (int) ( $data['max_uses'] ?? 0 ),
				'status'           => $data['status'] ?? 'draft',
				'starts_at'        => $data['starts_at'] ?? null,
				'expires_at'       => $data['expires_at'] ?? null,
				'created_by'       => $created_by,
				'created_at'       => DateTimeHelper::now_mysql(),
			],
			[ '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%s', '%d', '%s' ]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function update_status( int $id, string $status ): bool {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		global $wpdb;
		$updated = $wpdb->update(
			self::table(),
			[ 'status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
		return $updated !== false;
	}

	/**
	 * Atomically bumps uses_count and auto-flips to 'expired' when quota hits.
	 */
	public static function increment_uses( int $id ): bool {
		global $wpdb;
		$sql = QueryLoader::query( 'vouchers/increment_uses.sql' );
		return $wpdb->query( $wpdb->prepare( $sql, $id ) ) !== false;
	}
}
