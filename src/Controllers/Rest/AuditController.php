<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\AuditLog;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 *
 * Read-only endpoint for the audit log. The eventual admin Reports panel
 * (or a Members-row drilldown) will GET against this. Admin-only.
 *
 * Filters supported:
 *   - event: exact match against AuditLogger::EVENT_* constants, e.g. "membership.level_changed"
 *   - actor_id: filter by who did the action
 *   - target_id: filter by who the action was done to
 *   - page / per_page: standard pagination
 */
final class AuditController {

	public static function list( \WP_REST_Request $request ) {
		$event     = (string) $request->get_param( 'event' );
		$actor_id  = (int) $request->get_param( 'actor_id' );
		$target_id = (int) $request->get_param( 'target_id' );
		$page      = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$per_page  = (int) ( $request->get_param( 'per_page' ) ?: 25 );
		$from_raw  = trim( (string) $request->get_param( 'from' ) );
		$to_raw    = trim( (string) $request->get_param( 'to' ) );

		// Accept YYYY-MM-DD; widen to start/end of day so the comparison covers
		// the whole calendar day. Anything malformed → no filter (silently
		// skipped) so a bad query string can't hide rows.
		$from = self::widen_date( $from_raw, 'start' );
		$to   = self::widen_date( $to_raw,   'end' );

		// Route default for actor_id / target_id is 0 — treat that as "no filter".
		// Real user IDs are always positive, so 0 never collides with a real filter.
		$result = AuditLog::get_paginated(
			$event,
			$actor_id  > 0 ? $actor_id  : null,
			$target_id > 0 ? $target_id : null,
			$page,
			$per_page,
			$from,
			$to
		);

		// Normalize: cast types, decode meta_json, expand actor display name.
		$result['items'] = array_map( static function ( array $row ) {
			$actor      = (int) $row['actor_id'] > 0 ? get_userdata( (int) $row['actor_id'] ) : null;
			$target     = (int) ( $row['target_id'] ?? 0 ) > 0 ? get_userdata( (int) $row['target_id'] ) : null;
			$meta_json  = $row['meta_json'] ?? null;
			$meta       = is_string( $meta_json ) && $meta_json !== '' ? json_decode( $meta_json, true ) : [];

			return [
				'id'         => (int) $row['id'],
				'event'      => (string) $row['event'],
				'actor'      => [
					'id'           => (int) $row['actor_id'],
					'login'        => $actor ? $actor->user_login : '',
					'display_name' => $actor ? $actor->display_name : '',
				],
				'target'     => $row['target_id'] !== null ? [
					'id'           => (int) $row['target_id'],
					'login'        => $target ? $target->user_login : '',
					'display_name' => $target ? $target->display_name : '',
				] : null,
				'meta'       => is_array( $meta ) ? $meta : [],
				'created_at' => DateTimeHelper::mysql_to_iso( $row['created_at'] ),
			];
		}, $result['items'] );

		return RestResponse::ok( $result );
	}

	/**
	 * Turn a YYYY-MM-DD string into a MySQL DATETIME at start- or end-of-day.
	 * Returns null for empty / malformed input so the caller treats it as
	 * "no filter".
	 *
	 * Audit timestamps are stored in UTC (DateTimeHelper::now_mysql) — the
	 * picker's date is interpreted as UTC too, matching how the Reports
	 * date-range picker works.
	 */
	private static function widen_date( string $raw, string $edge ): ?string {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return null;
		}
		return $edge === 'end' ? "{$raw} 23:59:59" : "{$raw} 00:00:00";
	}
}
