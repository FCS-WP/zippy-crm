<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\PointsLedger;
use ZippyCrm\Services\PointsAdmin;
use ZippyCrm\Services\PointsEngine;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 */
final class PointsController {

	public static function get_summary( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$summary = PointsEngine::get_full_summary( $user_id );
		$rate    = (int) apply_filters( 'crm_points_redemption_rate', ZIPPY_CRM_POINTS_RATE, $user_id );

		return RestResponse::ok( [
			'balance'                 => $summary['balance'],
			'reserved'                => $summary['reserved'],
			'available'               => $summary['available'],
			'total_earned'            => $summary['total_earned'],
			'total_redeemed'          => $summary['total_redeemed'],
			'dollar_value'            => $rate > 0 ? round( $summary['balance']   / $rate, 2 ) : 0.0,
			'available_dollar_value'  => $rate > 0 ? round( $summary['available'] / $rate, 2 ) : 0.0,
			'redemption_rate'         => $rate,
			'min_redemption'          => ZIPPY_CRM_MIN_REDEMPTION,
		] );
	}

	public static function get_ledger( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$page     = (int) ( $request['page']     ?? 1 );
		$per_page = (int) ( $request['per_page'] ?? 10 );

		$result = PointsLedger::get_paginated( $user_id, $page, $per_page );

		// Normalize timestamps + cast types so the React side never has to.
		$result['items'] = array_map( static function ( array $row ) {
			return [
				'id'              => (int) $row['id'],
				'type'            => $row['type'],
				'points'          => (int) $row['points'],
				'reserved_points' => isset( $row['reserved_points'] ) ? (int) $row['reserved_points'] : null,
				'pending_status'  => $row['pending_status'] ?? null,
				'description'     => $row['description'],
				'order_id'        => $row['order_id'] !== null ? (int) $row['order_id'] : null,
				'created_at'      => DateTimeHelper::mysql_to_iso( $row['created_at'] ),
			];
		}, $result['items'] );

		return RestResponse::ok( $result );
	}

	public static function redeem( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return RestResponse::error( 'unauthorized', 'You must be logged in.', 401 );
		}

		$points = (int) $request['points'];
		$result = PointsEngine::redeem( $user_id, $points );

		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return RestResponse::ok( $result );
	}

	/* ============================================================
	 * Admin
	 * ============================================================ */

	public static function admin_adjust( \WP_REST_Request $request ) {
		$user_id  = (int) $request['user_id'];
		$delta    = (int) $request->get_param( 'delta' );
		$reason   = trim( (string) $request->get_param( 'reason' ) );
		$admin_id = get_current_user_id();

		if ( ! get_userdata( $user_id ) ) {
			return RestResponse::error( 'user_not_found', __( 'User not found.', 'zippy-crm' ), 404 );
		}

		$result = PointsAdmin::adjust( $user_id, $delta, $reason, $admin_id );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}

		return RestResponse::ok( [
			'user_id' => $user_id,
			'delta'   => $delta,
			'balance' => (int) $result,
			'reason'  => $reason,
		] );
	}

	public static function admin_summary( \WP_REST_Request $request ) {
		$rate    = (int) apply_filters( 'crm_points_redemption_rate', ZIPPY_CRM_POINTS_RATE, 0 );
		$summary = PointsAdmin::system_summary();

		return RestResponse::ok( [
			'issued'                    => $summary['issued'],
			'redeemed'                  => $summary['redeemed'],
			'outstanding'               => $summary['outstanding'],
			'members'                   => $summary['members'],
			'redemption_rate'           => $rate,
			'outstanding_dollar_value'  => $rate > 0 ? round( $summary['outstanding'] / $rate, 2 ) : 0.0,
		] );
	}

	public static function admin_ledger( \WP_REST_Request $request ) {
		$type     = (string) $request->get_param( 'type' );
		$page     = (int)    $request->get_param( 'page' )     ?: 1;
		$per_page = (int)    $request->get_param( 'per_page' ) ?: 20;

		if ( $type !== '' && ! in_array( $type, PointsLedger::ADMIN_FILTER_TYPES, true ) ) {
			return RestResponse::error( 'bad_type_filter', __( 'Unknown ledger type filter.', 'zippy-crm' ), 400 );
		}

		$result = PointsLedger::get_recent_for_admin( $type, $page, $per_page );

		$result['items'] = array_map( static function ( array $row ) {
			return [
				'id'           => (int) $row['id'],
				'user_id'      => (int) $row['user_id'],
				'user_login'   => (string) ( $row['user_login']   ?? '' ),
				'display_name' => (string) ( $row['display_name'] ?? '' ),
				'user_email'   => (string) ( $row['user_email']   ?? '' ),
				'order_id'     => $row['order_id'] !== null ? (int) $row['order_id'] : null,
				'type'         => (string) $row['type'],
				'points'       => (int) $row['points'],
				'description'  => $row['description'],
				'created_at'   => DateTimeHelper::mysql_to_iso( $row['created_at'] ),
			];
		}, $result['items'] );

		return RestResponse::ok( $result );
	}

	public static function admin_recalculate_all( \WP_REST_Request $request ) {
		$result = PointsAdmin::recalculate_all();

		do_action( 'crm_points_recalculated_all', $result, get_current_user_id() );

		return RestResponse::ok( $result );
	}
}
