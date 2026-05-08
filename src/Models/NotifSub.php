<?php
namespace ZippyCrm\Models;

use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * 1 row per user. Read-heavy on the registration form (default checked) and on
 * the My Account → Notifications tab; written rarely (registration + manual save).
 *
 * Default-checked-on-register is encoded in the column DEFAULT (1) so a missing
 * row is treated as opted-in everywhere.
 */
final class NotifSub {

	public const TABLE = 'crm_notif_subs';

	public const DEFAULTS = [
		'subscribed_vouchers' => true,
		'subscribed_points'   => true,
	];

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Returns the row, or DEFAULTS if missing. Callers never need to handle null.
	 *
	 * @return array{subscribed_vouchers:bool, subscribed_points:bool, updated_at:?string}
	 */
	public static function get_for_user( int $user_id ): array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT subscribed_vouchers, subscribed_points, updated_at
				 FROM ' . self::table() . ' WHERE user_id = %d',
				$user_id
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return [
				'subscribed_vouchers' => true,
				'subscribed_points'   => true,
				'updated_at'          => null,
			];
		}
		return [
			'subscribed_vouchers' => (bool) $row['subscribed_vouchers'],
			'subscribed_points'   => (bool) $row['subscribed_points'],
			'updated_at'          => $row['updated_at'],
		];
	}

	/**
	 * Insert-or-update preferences. Atomic upsert avoids the read-then-write
	 * race when two tabs save concurrently.
	 */
	public static function upsert( int $user_id, bool $vouchers, bool $points ): void {
		global $wpdb;
		$now = DateTimeHelper::now_mysql();
		$sql = $wpdb->prepare(
			'INSERT INTO ' . self::table() . '
				(user_id, subscribed_vouchers, subscribed_points, updated_at)
			 VALUES (%d, %d, %d, %s)
			 ON DUPLICATE KEY UPDATE
				subscribed_vouchers = VALUES(subscribed_vouchers),
				subscribed_points   = VALUES(subscribed_points),
				updated_at          = VALUES(updated_at)',
			$user_id,
			$vouchers ? 1 : 0,
			$points   ? 1 : 0,
			$now
		);
		$wpdb->query( $sql );
	}

	public static function delete_for_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'user_id' => $user_id ], [ '%d' ] );
	}
}
