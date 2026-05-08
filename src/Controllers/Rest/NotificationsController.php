<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Services\SubsManager;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 */
final class NotificationsController {

	public static function get_prefs( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}
		return RestResponse::ok( self::shape( SubsManager::get_for_user( $user_id ) ) );
	}

	public static function update_prefs( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$prefs = SubsManager::update_preferences(
			$user_id,
			(bool) $request['subscribe_vouchers'],
			(bool) $request['subscribe_points']
		);

		return RestResponse::ok( self::shape( $prefs ) );
	}

	private static function shape( array $prefs ): array {
		return [
			'subscribe_vouchers' => (bool) $prefs['subscribed_vouchers'],
			'subscribe_points'   => (bool) $prefs['subscribed_points'],
			'updated_at'         => DateTimeHelper::mysql_to_iso( $prefs['updated_at'] ?? null ),
		];
	}
}
