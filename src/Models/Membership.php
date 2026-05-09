<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for crm_memberships. Reads/writes only — business logic
 * (tier evaluation, multipliers) lives in MembershipService.
 *
 * Hot path: find_by_user is called on most authenticated WC requests.
 * Cache it at the service layer, not here.
 */
final class Membership {

	public const TABLE = 'crm_memberships';

	public const LEVELS = [ 'free', 'silver', 'gold', 'vip' ];
	public const STATUSES = [ 'active', 'suspended', 'expired' ];

	public const MULTIPLIERS = [
		'free'   => 1.0,
		'silver' => 1.2,
		'gold'   => 1.5,
		'vip'    => 2.0,
	];

	public const LABELS = [
		'free'   => 'Free',
		'silver' => 'Silver',
		'gold'   => 'Gold',
		'vip'    => 'VIP',
	];

	/** Sentinel value for the prepared "skip this filter" branch in admin SQL. */
	private const FILTER_ALL = '__all__';

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** @return array<string,mixed>|null */
	public static function find_by_user( int $user_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, user_id, membership_level, status, joined_at, expires_at
				 FROM ' . self::table() . ' WHERE user_id = %d',
				$user_id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	public static function create( int $user_id, string $level = 'free' ): bool {
		global $wpdb;
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			$level = 'free';
		}
		$inserted = $wpdb->insert(
			self::table(),
			[
				'user_id'          => $user_id,
				'membership_level' => $level,
				'status'           => 'active',
				'joined_at'        => DateTimeHelper::now_mysql(),
			],
			[ '%d', '%s', '%s', '%s' ]
		);
		return $inserted !== false;
	}

	public static function update_level( int $user_id, string $level ): bool {
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			return false;
		}
		global $wpdb;
		$updated = $wpdb->update(
			self::table(),
			[ 'membership_level' => $level ],
			[ 'user_id' => $user_id ],
			[ '%s' ],
			[ '%d' ]
		);
		return $updated !== false;
	}

	public static function update_status( int $user_id, string $status ): bool {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		global $wpdb;
		$updated = $wpdb->update(
			self::table(),
			[ 'status' => $status ],
			[ 'user_id' => $user_id ],
			[ '%s' ],
			[ '%d' ]
		);
		return $updated !== false;
	}

	public static function delete_for_user( int $user_id ): void {
		global $wpdb;
		$wpdb->delete( self::table(), [ 'user_id' => $user_id ], [ '%d' ] );
	}

	/* ============================================================
	 * Admin
	 * ============================================================ */

	/**
	 * Paginated member list for the admin Members panel. Joins wp_users +
	 * crm_points_summary so the table renders in one query (no N+1 on the
	 * row-count + balance per user).
	 *
	 * @param string $level   One of self::LEVELS, or '' to skip the filter.
	 * @param string $status  One of self::STATUSES, or '' to skip the filter.
	 * @param string $search  Free-text against login/email/display_name. '' to skip.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_admin( string $level, string $status, string $search, int $page, int $per_page ): array {
		global $wpdb;

		$level_active = $level !== '' && in_array( $level, self::LEVELS, true );
		$level_token  = $level_active ? $level : self::FILTER_ALL;

		$status_active = $status !== '' && in_array( $status, self::STATUSES, true );
		$status_token  = $status_active ? $status : self::FILTER_ALL;

		$search_active = $search !== '';
		$search_token  = $search_active ? $search : self::FILTER_ALL;
		$pattern       = '%' . $wpdb->esc_like( $search ) . '%';

		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$sql  = QueryLoader::query( 'admin/members/list_paginated.sql' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$level_token,  $level_token,
				$status_token, $status_token,
				$search_token, $pattern, $pattern, $pattern,
				$per_page, $offset
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Total row count for the same filters as list_for_admin.
	 */
	public static function count_for_admin( string $level, string $status, string $search ): int {
		global $wpdb;

		$level_active  = $level !== ''  && in_array( $level,  self::LEVELS,   true );
		$level_token   = $level_active  ? $level  : self::FILTER_ALL;

		$status_active = $status !== '' && in_array( $status, self::STATUSES, true );
		$status_token  = $status_active ? $status : self::FILTER_ALL;

		$search_active = $search !== '';
		$search_token  = $search_active ? $search : self::FILTER_ALL;
		$pattern       = '%' . $wpdb->esc_like( $search ) . '%';

		$sql   = QueryLoader::query( 'admin/members/count.sql' );
		$total = $wpdb->get_var(
			$wpdb->prepare(
				$sql,
				$level_token,  $level_token,
				$status_token, $status_token,
				$search_token, $pattern, $pattern, $pattern
			)
		);
		return (int) $total;
	}

	/**
	 * Level → count for the Quick Stats bar. Always returns every level,
	 * filling missing keys with 0.
	 *
	 * @return array<string,int>
	 */
	public static function count_by_level(): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'admin/members/count_by_level.sql' );
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$out = array_fill_keys( self::LEVELS, 0 );
		foreach ( (array) $rows as $row ) {
			$level = (string) ( $row['level'] ?? '' );
			if ( isset( $out[ $level ] ) ) {
				$out[ $level ] = (int) $row['total'];
			}
		}
		return $out;
	}
}
