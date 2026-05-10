<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only queries for the admin Users panel — every non-admin WP user,
 * LEFT-joined with our membership + points-summary tables so the admin
 * sees both the WP user base and CRM coverage in one view.
 *
 * Lives in Models/ rather than Membership.php because the entity here is
 * `wp_users`, not crm_memberships. The funnel is "everyone → those with a
 * crm row" — different starting point.
 */
final class AdminUsersQuery {

	private const FILTER_ALL = '__all__';

	/**
	 * Paginated user list with optional search + has_membership filter.
	 *
	 * @param string $search          Free-text against login/email/display_name. '' to skip.
	 * @param string $has_membership  '' / 'yes' / 'no' — '' means no filter.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_admin( string $search, string $has_membership, int $page, int $per_page ): array {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$search_active = $search !== '';
		$search_token  = $search_active ? $search : self::FILTER_ALL;
		$pattern       = '%' . $wpdb->esc_like( $search ) . '%';

		$has_token = in_array( $has_membership, [ 'yes', 'no' ], true ) ? $has_membership : self::FILTER_ALL;

		$sql  = QueryLoader::query( 'admin/users/list_paginated.sql' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$search_token, $pattern, $pattern, $pattern,
				$has_token, $has_token, $has_token,
				$per_page, $offset
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	public static function count_for_admin( string $search, string $has_membership ): int {
		global $wpdb;

		$search_active = $search !== '';
		$search_token  = $search_active ? $search : self::FILTER_ALL;
		$pattern       = '%' . $wpdb->esc_like( $search ) . '%';

		$has_token = in_array( $has_membership, [ 'yes', 'no' ], true ) ? $has_membership : self::FILTER_ALL;

		$sql   = QueryLoader::query( 'admin/users/count.sql' );
		$total = $wpdb->get_var(
			$wpdb->prepare(
				$sql,
				$search_token, $pattern, $pattern, $pattern,
				$has_token, $has_token, $has_token
			)
		);
		return (int) $total;
	}

	/**
	 * Single-row totals for the panel callout.
	 *
	 * @return array{total_users:int, member_count:int}
	 */
	public static function totals(): array {
		global $wpdb;
		$sql = QueryLoader::query( 'admin/users/totals.sql' );
		$row = $wpdb->get_row( $sql, ARRAY_A );
		return [
			'total_users'  => (int) ( $row['total_users']  ?? 0 ),
			'member_count' => (int) ( $row['member_count'] ?? 0 ),
		];
	}
}
