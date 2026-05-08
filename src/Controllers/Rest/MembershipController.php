<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\Membership;
use ZippyCrm\Services\MembershipService;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 */
final class MembershipController {

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
			'level_label' => Membership::LABELS[ $level ] ?? ucfirst( $level ),
			'multiplier'  => Membership::MULTIPLIERS[ $level ] ?? 1.0,
			'status'      => $row['status'] ?? 'active',
			'joined_at'   => DateTimeHelper::mysql_to_iso( $row['joined_at']  ?? null ),
			'expires_at'  => DateTimeHelper::mysql_to_iso( $row['expires_at'] ?? null ),
			'stats'       => $stats,
			'next_tier'   => MembershipService::next_tier_progress( $user_id, $stats ),
		] );
	}
}
