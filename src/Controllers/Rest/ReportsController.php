<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Services\ReportsService;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php. All endpoints require
 * 'manage_woocommerce' (admin Reports panel only).
 *
 * Each handler accepts `from`/`to` (YYYY-MM-DD, UTC). Both are optional —
 * defaults to the last DEFAULT_DAYS ending today.
 */
final class ReportsController {

	public static function members_per_day( \WP_REST_Request $request ) {
		$range = ReportsService::parse_range( $request->get_param( 'from' ), $request->get_param( 'to' ) );
		if ( $range instanceof \WP_Error ) {
			return $range;
		}

		return RestResponse::ok( [
			'from'   => substr( $range['from'], 0, 10 ),
			'to'     => substr( $range['to'],   0, 10 ),
			'days'   => $range['days'],
			'series' => ReportsService::members_per_day( $range['from'], $range['to'] ),
		] );
	}

	public static function points_activity_per_day( \WP_REST_Request $request ) {
		$range = ReportsService::parse_range( $request->get_param( 'from' ), $request->get_param( 'to' ) );
		if ( $range instanceof \WP_Error ) {
			return $range;
		}

		return RestResponse::ok( [
			'from'   => substr( $range['from'], 0, 10 ),
			'to'     => substr( $range['to'],   0, 10 ),
			'days'   => $range['days'],
			'series' => ReportsService::points_activity_per_day( $range['from'], $range['to'] ),
		] );
	}

	public static function voucher_claims_per_day( \WP_REST_Request $request ) {
		$range = ReportsService::parse_range( $request->get_param( 'from' ), $request->get_param( 'to' ) );
		if ( $range instanceof \WP_Error ) {
			return $range;
		}

		return RestResponse::ok( [
			'from'   => substr( $range['from'], 0, 10 ),
			'to'     => substr( $range['to'],   0, 10 ),
			'days'   => $range['days'],
			'series' => ReportsService::voucher_claims_per_day( $range['from'], $range['to'] ),
		] );
	}
}
