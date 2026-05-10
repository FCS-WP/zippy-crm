<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\Membership;
use ZippyCrm\Services\MembershipService;
use ZippyCrm\Services\TierRegistry;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 */
final class MembershipController {

	/** Status enum lives on the model — tier slugs come from TierRegistry. */
	private const ALLOWED_STATUS_FILTERS = [ 'active', 'suspended', 'expired' ];
	private const DEFAULT_PER_PAGE       = 20;
	private const MAX_PER_PAGE           = 100;

	public static function get_me( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$stats = MembershipService::get_customer_stats( $user_id );

		// Self-heal pre-existing users: if WC stats already qualify them for a
		// higher tier (e.g. orders predate plugin install), upgrade now.
		MembershipService::evaluate_tier_upgrade( $user_id );

		$row   = MembershipService::get_for_user( $user_id );
		$user  = get_userdata( $user_id );
		$level = $row['membership_level'] ?? 'free';

		return RestResponse::ok( [
			'user' => [
				'id'           => $user_id,
				'display_name' => $user ? $user->display_name : '',
				'email'        => $user ? $user->user_email : '',
			],
			'level'       => $level,
			'level_label' => TierRegistry::labels()[ $level ] ?? ucfirst( $level ),
			'multiplier'  => TierRegistry::multiplier_for( $level ),
			'status'      => $row['status'] ?? 'active',
			'joined_at'   => DateTimeHelper::mysql_to_iso( $row['joined_at']  ?? null ),
			'expires_at'  => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'stats'       => $stats,
			'next_tier'   => MembershipService::next_tier_progress( $user_id, $stats ),
		] );
	}

	/* ============================================================
	 * Admin
	 *
	 * Auth: routes use 'manage_woocommerce' (see Core/routes.php).
	 * ============================================================ */

	public static function admin_list( \WP_REST_Request $request ) {
		$level    = (string) $request->get_param( 'level' );
		$status   = (string) $request->get_param( 'status' );
		$search   = trim( (string) $request->get_param( 'search' ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = (int) $request->get_param( 'per_page' ) ?: self::DEFAULT_PER_PAGE;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		if ( $level !== '' && ! TierRegistry::exists( $level ) ) {
			return RestResponse::error( 'bad_level_filter', __( 'Unknown level filter.', 'zippy-crm' ), 400 );
		}
		if ( $status !== '' && ! in_array( $status, self::ALLOWED_STATUS_FILTERS, true ) ) {
			return RestResponse::error( 'bad_status_filter', __( 'Unknown status filter.', 'zippy-crm' ), 400 );
		}

		$rows  = Membership::list_for_admin( $level, $status, $search, $page, $per_page );
		$total = Membership::count_for_admin( $level, $status, $search );

		return RestResponse::ok( [
			'items'    => array_map( [ self::class, 'shape_member_row' ], $rows ),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'counts'   => Membership::count_by_level(),
		] );
	}

	public static function admin_get( \WP_REST_Request $request ) {
		$user_id = (int) $request['user_id'];
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return RestResponse::error( 'user_not_found', __( 'User not found.', 'zippy-crm' ), 404 );
		}

		$row     = MembershipService::get_for_user( $user_id );
		$stats   = MembershipService::get_customer_stats( $user_id );
		$level   = $row['membership_level'] ?? 'free';

		return RestResponse::ok( [
			'user' => [
				'id'           => $user_id,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'registered'   => $user->user_registered,
			],
			'level'       => $level,
			'level_label' => TierRegistry::labels()[ $level ] ?? ucfirst( $level ),
			'multiplier'  => TierRegistry::multiplier_for( $level ),
			'status'      => $row['status'] ?? 'active',
			'joined_at'   => DateTimeHelper::mysql_to_iso( $row['joined_at']  ?? null ),
			'expires_at'  => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'stats'       => $stats,
		] );
	}

	public static function admin_set_level( \WP_REST_Request $request ) {
		$user_id = (int) $request['user_id'];
		if ( ! get_userdata( $user_id ) ) {
			return RestResponse::error( 'user_not_found', __( 'User not found.', 'zippy-crm' ), 404 );
		}
		$level = (string) $request->get_param( 'level' );

		$result = MembershipService::set_level_admin( $user_id, $level );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return self::admin_get( $request );
	}

	public static function admin_set_status( \WP_REST_Request $request ) {
		$user_id = (int) $request['user_id'];
		if ( ! get_userdata( $user_id ) ) {
			return RestResponse::error( 'user_not_found', __( 'User not found.', 'zippy-crm' ), 404 );
		}
		$status = (string) $request->get_param( 'status' );

		$result = MembershipService::set_status_admin( $user_id, $status );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return self::admin_get( $request );
	}

	private static function shape_member_row( array $row ): array {
		$level = (string) $row['membership_level'];
		return [
			'user_id'        => (int) $row['user_id'],
			'user_login'     => (string) $row['user_login'],
			'user_email'     => (string) $row['user_email'],
			'display_name'   => (string) $row['display_name'],
			'registered_at'  => DateTimeHelper::mysql_to_iso( $row['user_registered'] ?? null ),
			'level'          => $level,
			'level_label'    => TierRegistry::labels()[ $level ] ?? ucfirst( $level ),
			'status'         => (string) $row['membership_status'],
			'joined_at'      => DateTimeHelper::mysql_to_iso( $row['joined_at']  ?? null ),
			'expires_at'     => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'points_balance' => (int) $row['points_balance'],
			'points_earned'  => (int) $row['points_earned'],
			'points_redeemed' => (int) $row['points_redeemed'],
		];
	}
}
