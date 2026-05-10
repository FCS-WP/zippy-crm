<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Models\Tier;
use ZippyCrm\Services\TierRegistry;
use ZippyCrm\Support\DateTimeHelper;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Routes wired in src/Core/routes.php.
 *
 * GET /tiers          - public, returns the tier ladder (used by progress UI)
 * GET /admin/tiers    - admin-only, returns tiers + member counts
 * POST /admin/tiers
 * PUT /admin/tiers/{slug}
 * DELETE /admin/tiers/{slug}
 *
 * The public endpoint omits internal fields (sort_order, created_at) and
 * filters out admin-only tiers by default — customers don't need to know
 * VIP exists.
 */
final class TiersController {

	public static function list_public( \WP_REST_Request $request ) {
		$include_admin = (bool) $request->get_param( 'include_admin_only' );
		$tiers = TierRegistry::all();
		if ( ! $include_admin ) {
			$tiers = array_filter( $tiers, static fn( array $t ) => ! (int) $t['is_admin_only'] );
		}
		return RestResponse::ok( [
			'items' => array_values( array_map( [ self::class, 'shape_public' ], $tiers ) ),
		] );
	}

	public static function admin_list( \WP_REST_Request $request ) {
		$counts = Tier::member_counts();
		$items  = array_map( static function ( array $t ) use ( $counts ) {
			$shaped = self::shape_admin( $t );
			$shaped['member_count'] = (int) ( $counts[ $t['slug'] ] ?? 0 );
			return $shaped;
		}, TierRegistry::all() );

		return RestResponse::ok( [ 'items' => array_values( $items ) ] );
	}

	public static function admin_create( \WP_REST_Request $request ) {
		$payload = self::extract_payload( $request );
		$result  = TierRegistry::create( $payload );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return RestResponse::ok( self::shape_admin( $result ), 201 );
	}

	public static function admin_update( \WP_REST_Request $request ) {
		$slug    = (string) $request['slug'];
		$payload = self::extract_payload( $request );
		$result  = TierRegistry::update( $slug, $payload );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return RestResponse::ok( self::shape_admin( $result ) );
	}

	public static function admin_delete( \WP_REST_Request $request ) {
		$slug   = (string) $request['slug'];
		$result = TierRegistry::delete( $slug );
		if ( $result instanceof \WP_Error ) {
			return $result;
		}
		return RestResponse::ok( [ 'deleted' => true, 'slug' => $slug ] );
	}

	private static function extract_payload( \WP_REST_Request $request ): array {
		$keys = [ 'slug', 'label', 'multiplier', 'threshold_orders', 'threshold_spend', 'is_admin_only', 'sort_order' ];
		$out  = [];
		foreach ( $keys as $key ) {
			$v = $request->get_param( $key );
			if ( $v === null ) {
				continue;
			}
			$out[ $key ] = $v === '' ? null : $v;
		}
		return $out;
	}

	private static function shape_public( array $row ): array {
		return [
			'slug'             => (string) $row['slug'],
			'label'            => (string) $row['label'],
			'multiplier'       => (float) $row['multiplier'],
			'threshold_orders' => $row['threshold_orders'] !== null ? (int)   $row['threshold_orders'] : null,
			'threshold_spend'  => $row['threshold_spend']  !== null ? (float) $row['threshold_spend']  : null,
			'is_admin_only'    => (bool) $row['is_admin_only'],
		];
	}

	private static function shape_admin( array $row ): array {
		return [
			'slug'             => (string) $row['slug'],
			'label'            => (string) $row['label'],
			'multiplier'       => (float) $row['multiplier'],
			'threshold_orders' => $row['threshold_orders'] !== null ? (int)   $row['threshold_orders'] : null,
			'threshold_spend'  => $row['threshold_spend']  !== null ? (float) $row['threshold_spend']  : null,
			'is_admin_only'    => (bool) $row['is_admin_only'],
			'sort_order'       => (int) $row['sort_order'],
			'created_at'       => DateTimeHelper::mysql_to_iso( $row['created_at'] ?? null ),
		];
	}
}
