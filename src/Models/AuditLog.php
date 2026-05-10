<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Append-only audit table for admin write actions.
 *
 * Insert-only — the audit trail is the product. Reads are paginated and
 * filtered through indexes (`idx_target_created` / `idx_actor_created` /
 * `idx_event_created`) so the eventual admin Reports panel can drill in
 * without N+1.
 */
final class AuditLog {

	public const TABLE = 'crm_audit_log';

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Insert a single audit row. Returns the new id, or 0 on failure.
	 *
	 * `$meta` is a freeform array — wp_json_encode'd before storage. Caller is
	 * responsible for keeping the shape consistent within an event type so the
	 * eventual admin UI can render it predictably.
	 */
	public static function insert(
		string $event,
		int $actor_id,
		?int $target_id = null,
		array $meta = []
	): int {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			[
				'event'      => $event,
				'actor_id'   => $actor_id,
				'target_id'  => $target_id,
				'meta_json'  => empty( $meta ) ? null : wp_json_encode( $meta ),
				'created_at' => DateTimeHelper::now_mysql(),
			],
			[ '%s', '%d', '%d', '%s', '%s' ]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Paginated read with optional filters. Empty filters skip via the same
	 * `__all__` sentinel pattern used elsewhere — single prepared SQL shape
	 * regardless of which filters are active.
	 *
	 * @return array{items: array<int,array<string,mixed>>, total: int, page: int, per_page: int, total_pages: int}
	 */
	public static function get_paginated(
		string $event,
		?int $actor_id,
		?int $target_id,
		int $page = 1,
		int $per_page = 25,
		?string $from = null,
		?string $to = null
	): array {
		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Sentinels:
		//   '__all__' for VARCHAR (event)
		//   -1        for INT (actor_id, target_id)
		//   bracket DATETIMEs for created_at — see list_paginated.sql for why
		//   '__all__' wouldn't work on a DATETIME column.
		$event_token  = $event !== '' ? $event : '__all__';
		$actor_token  = $actor_id !== null  ? $actor_id  : -1;
		$target_token = $target_id !== null ? $target_id : -1;
		$from_token   = $from !== null && $from !== '' ? $from : '1970-01-01 00:00:00';
		$to_token     = $to   !== null && $to   !== '' ? $to   : '9999-12-31 23:59:59';

		global $wpdb;

		$items_sql = QueryLoader::query( 'audit/list_paginated.sql' );
		$items     = $wpdb->get_results(
			$wpdb->prepare(
				$items_sql,
				$event_token,  $event_token,
				$actor_token,  $actor_token,
				$target_token, $target_token,
				$from_token,
				$to_token,
				$per_page, $offset
			),
			ARRAY_A
		) ?: [];

		$count_sql = QueryLoader::query( 'audit/count.sql' );
		$total     = (int) $wpdb->get_var(
			$wpdb->prepare(
				$count_sql,
				$event_token,  $event_token,
				$actor_token,  $actor_token,
				$target_token, $target_token,
				$from_token,
				$to_token
			)
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
