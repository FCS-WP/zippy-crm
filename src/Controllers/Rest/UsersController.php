<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\AdminUsersQuery;
use ZippyCrm\Services\TierRegistry;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Users panel — every non-admin WP user, with their CRM coverage.
 *
 * Companion to the Members panel (which only lists users with a
 * crm_memberships row). Users panel is the "everyone" view; Members
 * is the funnel-narrowed view.
 *
 * Routes wired in src/Core/routes.php.
 */
final class UsersController {

	private const DEFAULT_PER_PAGE = 25;
	private const MAX_PER_PAGE     = 100;

	public static function admin_list( \WP_REST_Request $request ) {
		$search   = trim( (string) $request->get_param( 'search' ) );
		$has      = (string) $request->get_param( 'has_membership' );
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = (int) $request->get_param( 'per_page' ) ?: self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		if ( $has !== '' && ! in_array( $has, [ 'yes', 'no' ], true ) ) {
			return RestResponse::error( 'bad_has_filter', __( 'has_membership must be "yes" or "no".', 'zippy-crm' ), 400 );
		}

		$rows   = AdminUsersQuery::list_for_admin( $search, $has, $page, $per_page );
		$total  = AdminUsersQuery::count_for_admin( $search, $has );
		$totals = AdminUsersQuery::totals();

		return RestResponse::ok( [
			'items'    => array_map( [ self::class, 'shape_user_row' ], $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'totals'   => $totals,
		] );
	}

	private static function shape_user_row( array $row ): array {
		$level = isset( $row['membership_level'] ) ? (string) $row['membership_level'] : '';
		return [
			'user_id'         => (int) $row['user_id'],
			'user_login'      => (string) $row['user_login'],
			'user_email'      => (string) $row['user_email'],
			'display_name'    => (string) $row['display_name'],
			'registered_at'   => DateTimeHelper::mysql_to_iso( $row['user_registered'] ?? null ),
			'has_membership'  => (bool) $row['has_membership'],
			'level'           => $level,
			'level_label'     => $level !== '' ? ( TierRegistry::labels()[ $level ] ?? ucfirst( $level ) ) : '',
			'status'          => isset( $row['membership_status'] ) ? (string) $row['membership_status'] : '',
			'joined_at'       => DateTimeHelper::mysql_to_iso( $row['joined_at'] ?? null ),
			'points_balance'  => (int) ( $row['points_balance']  ?? 0 ),
			'points_earned'   => (int) ( $row['points_earned']   ?? 0 ),
			'points_redeemed' => (int) ( $row['points_redeemed'] ?? 0 ),
		];
	}
}
