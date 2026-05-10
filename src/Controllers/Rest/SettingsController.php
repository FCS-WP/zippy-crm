<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Services\PointsSettings;
use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Admin REST routes for site-wide CRM settings. Currently scoped to the
 * points-earning blacklist (excluded products + excluded categories). The
 * earn rate per tier still lives on /admin/tiers.
 *
 * Routes wired in src/Core/routes.php.
 *
 *   GET    /admin/settings/points     — read excluded product + category IDs
 *   PUT    /admin/settings/points     — update both lists in one request
 *
 * Returns lightweight rows with id + label so the React picker can render
 * chips without re-querying WC for names.
 */
final class SettingsController {

	public static function get_points( \WP_REST_Request $request ) {
		return RestResponse::ok( [
			'excluded_products'   => self::shape_products( PointsSettings::excluded_product_ids() ),
			'excluded_categories' => self::shape_categories( PointsSettings::excluded_category_ids() ),
		] );
	}

	public static function update_points( \WP_REST_Request $request ) {
		$products   = $request->get_param( 'excluded_product_ids' );
		$categories = $request->get_param( 'excluded_category_ids' );

		// Both fields are optional — only update what's provided so a partial
		// PUT (e.g. only update products) doesn't accidentally wipe categories.
		if ( is_array( $products ) ) {
			PointsSettings::set_excluded_product_ids( $products );
		}
		if ( is_array( $categories ) ) {
			PointsSettings::set_excluded_category_ids( $categories );
		}

		// Return the post-write state so the client's optimistic-update path
		// has a single source of truth.
		return self::get_points( $request );
	}

	/**
	 * @param array<int,int> $ids
	 * @return array<int,array{id:int,label:string,sku:string}>
	 */
	private static function shape_products( array $ids ): array {
		$out = [];
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				// Stored ID pointing at a deleted product — keep the id so the
				// admin can see it's stale and remove it; UI shows "(deleted)".
				$out[] = [ 'id' => $id, 'label' => sprintf( '(deleted product #%d)', $id ), 'sku' => '' ];
				continue;
			}
			$out[] = [
				'id'    => $id,
				'label' => (string) $product->get_name(),
				'sku'   => (string) $product->get_sku(),
			];
		}
		return $out;
	}

	/**
	 * @param array<int,int> $ids
	 * @return array<int,array{id:int,label:string,slug:string}>
	 */
	private static function shape_categories( array $ids ): array {
		$out = [];
		foreach ( $ids as $id ) {
			$term = get_term( $id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				$out[] = [ 'id' => $id, 'label' => sprintf( '(deleted category #%d)', $id ), 'slug' => '' ];
				continue;
			}
			$out[] = [
				'id'    => $id,
				'label' => (string) $term->name,
				'slug'  => (string) $term->slug,
			];
		}
		return $out;
	}
}
