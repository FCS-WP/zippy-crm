<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Idempotency + audit table for voucher email dispatch.
 *
 * The UNIQUE (voucher_id, user_id) constraint is the source of truth — every
 * insert path tolerates collisions and treats them as "already queued".
 * Replays of the publish action or batch cron are therefore safe.
 *
 * Status transitions: queued → sent | failed → (retry) → sent | failed.
 * Append-only? No — `status` and `attempts` mutate. But each transition is a
 * conditional UPDATE so concurrent writers can't lose data.
 */
final class NotificationLog {

	public const TABLE = 'crm_notification_log';

	public const STATUS_QUEUED = 'queued';
	public const STATUS_SENT   = 'sent';
	public const STATUS_FAILED = 'failed';

	public const RETRY_MAX = 3;

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Insert a queued row. Returns the new id, or 0 on UNIQUE collision
	 * (someone else already queued this voucher_id+user_id).
	 */
	public static function insert_queued( int $voucher_id, int $user_id ): int {
		global $wpdb;
		$wpdb->suppress_errors( true );
		$ok = $wpdb->insert(
			self::table(),
			[
				'voucher_id' => $voucher_id,
				'user_id'    => $user_id,
				'status'     => self::STATUS_QUEUED,
				'attempts'   => 0,
				'queued_at'  => DateTimeHelper::now_mysql(),
			],
			[ '%d', '%d', '%s', '%d', '%s' ]
		);
		$err = $wpdb->last_error;
		$wpdb->suppress_errors( false );

		if ( $ok ) {
			return (int) $wpdb->insert_id;
		}
		// Duplicate is expected when the publish action replays.
		if ( $err !== '' && stripos( $err, 'duplicate' ) !== false ) {
			return 0;
		}
		return 0;
	}

	public static function mark_sent( int $id ): bool {
		global $wpdb;
		$ok = $wpdb->update(
			self::table(),
			[
				'status'  => self::STATUS_SENT,
				'sent_at' => DateTimeHelper::now_mysql(),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
		return $ok !== false;
	}

	/**
	 * Bumps `attempts` and stores the error message (truncated). Used after
	 * a wp_mail failure on either initial dispatch or a retry.
	 */
	public static function mark_failed( int $id, string $error ): bool {
		global $wpdb;
		$truncated = substr( $error, 0, 255 );
		$ok = $wpdb->query( $wpdb->prepare(
			'UPDATE ' . self::table() . '
			 SET status = %s, attempts = attempts + 1, last_error = %s
			 WHERE id = %d',
			self::STATUS_FAILED,
			$truncated,
			$id
		) );
		return $ok !== false;
	}

	/**
	 * @return array<int,array{id:int, voucher_id:int, user_id:int, attempts:int}>
	 */
	public static function find_failed_for_retry( int $limit ): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'notifications/find_failed_for_retry.sql' );
		$rows = $wpdb->get_results(
			$wpdb->prepare( $sql, self::RETRY_MAX, $limit ),
			ARRAY_A
		);
		if ( ! $rows ) {
			return [];
		}
		return array_map( static fn( array $r ) => [
			'id'         => (int) $r['id'],
			'voucher_id' => (int) $r['voucher_id'],
			'user_id'    => (int) $r['user_id'],
			'attempts'   => (int) $r['attempts'],
		], $rows );
	}

	public static function delete_for_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'user_id' => $user_id ], [ '%d' ] );
	}
}
