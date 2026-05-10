<?php
namespace ZippyCrm\Controllers\Rest;

use ZippyCrm\Support\RestResponse;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight admin search/lookup over WC products + product categories,
 * tailored to the voucher form's product/category picker modal.
 *
 * Why not use `wc/store/v1/products`? It's customer-facing — caches
 * aggressively, returns published+visible only, and ships a heavy payload
 * (images, variations, attributes). For an admin picker we need:
 *   - lightweight (id + name + sku + price + thumbnail), no variations
 *   - includes draft / private / hidden products too (admin can pick any)
 *   - resolves a list of IDs back to display data (so existing voucher
 *     selections render as chips on edit, not as bare numbers)
 *
 * Search uses WC's HPOS-safe `wc_get_products()` and `get_terms()` — no
 * direct SQL. Backend never trusts the client; ID lists for `?ids=` are
 * cast to int + de-duped.
 */
final class CatalogController {

	private const MAX_PER_PAGE  = 50;
	private const DEFAULT_LIMIT = 20;

	/**
	 * GET /admin/catalog/products
	 *
	 * Query: search (string), ids (csv), per_page (int, default 20, max 50)
	 *
	 * Either `search` OR `ids` is required. With `ids`, ordering matches the
	 * caller-provided sequence so the React side can show chips in the same
	 * order the admin picked them.
	 */
	public static function search_products( \WP_REST_Request $request ) {
		$search   = trim( (string) $request->get_param( 'search' ) );
		$ids_raw  = (string) $request->get_param( 'ids' );
		$per_page = (int) $request->get_param( 'per_page' ) ?: self::DEFAULT_LIMIT;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$ids = self::parse_id_list( $ids_raw );

		if ( $search === '' && empty( $ids ) ) {
			return RestResponse::error(
				'missing_query',
				__( 'Provide either `search` or `ids`.', 'zippy-crm' ),
				400
			);
		}

		$args = [
			'limit'   => $per_page,
			'orderby' => 'title',
			'order'   => 'ASC',
			'status'  => [ 'publish', 'private', 'draft' ],
			'return'  => 'ids',
		];
		if ( ! empty( $ids ) ) {
			$args['include'] = $ids;
			$args['orderby'] = 'include'; // preserve caller order
			$args['limit']   = count( $ids );
		}
		if ( $search !== '' ) {
			// `s` matches title + content + sku in WC's product query layer.
			$args['s'] = $search;
		}

		$found = wc_get_products( $args );
		return RestResponse::ok( [
			'items' => array_map( [ self::class, 'shape_product' ], (array) $found ),
		] );
	}

	/**
	 * GET /admin/catalog/categories
	 *
	 * Query: search (string), ids (csv), per_page (int)
	 *
	 * Same pattern as products — search OR ids; ID order preserved.
	 */
	public static function search_categories( \WP_REST_Request $request ) {
		$search   = trim( (string) $request->get_param( 'search' ) );
		$ids_raw  = (string) $request->get_param( 'ids' );
		$per_page = (int) $request->get_param( 'per_page' ) ?: self::DEFAULT_LIMIT;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$ids = self::parse_id_list( $ids_raw );

		if ( $search === '' && empty( $ids ) ) {
			return RestResponse::error(
				'missing_query',
				__( 'Provide either `search` or `ids`.', 'zippy-crm' ),
				400
			);
		}

		$args = [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => $per_page,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];
		if ( ! empty( $ids ) ) {
			$args['include'] = $ids;
			$args['orderby'] = 'include';
			$args['number']  = count( $ids );
		}
		if ( $search !== '' ) {
			$args['name__like'] = $search;
		}

		$terms = get_terms( $args );
		if ( $terms instanceof \WP_Error ) {
			return RestResponse::error( 'category_query_failed', $terms->get_error_message(), 500 );
		}

		return RestResponse::ok( [
			'items' => array_map( [ self::class, 'shape_category' ], (array) $terms ),
		] );
	}

	/**
	 * GET /admin/catalog/customers
	 *
	 * Search WP users by login / email / display name OR resolve a list of
	 * IDs back to display rows. Same `search` OR `ids` contract as products
	 * + categories so the picker modal can reuse the same shape.
	 *
	 * Returns lightweight rows: { id, login, email, display_name, first_name,
	 * last_name }. The voucher form stores email + name from the chosen row;
	 * WC only sees emails at coupon-validation time.
	 */
	public static function search_customers( \WP_REST_Request $request ) {
		$search   = trim( (string) $request->get_param( 'search' ) );
		$ids_raw  = (string) $request->get_param( 'ids' );
		$per_page = (int) $request->get_param( 'per_page' ) ?: self::DEFAULT_LIMIT;
		$per_page = max( 1, min( self::MAX_PER_PAGE, $per_page ) );

		$ids = self::parse_id_list( $ids_raw );

		if ( $search === '' && empty( $ids ) ) {
			return RestResponse::error(
				'missing_query',
				__( 'Provide either `search` or `ids`.', 'zippy-crm' ),
				400
			);
		}

		$args = [
			'number'  => $per_page,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => [ 'ID', 'user_login', 'user_email', 'display_name' ],
		];

		if ( ! empty( $ids ) ) {
			$args['include'] = $ids;
			$args['orderby'] = 'include';
			$args['number']  = count( $ids );
		}
		if ( $search !== '' ) {
			// `search` matches login, email, nicename, display name out of the
			// box — close to what an admin expects from "find a customer".
			$args['search']         = '*' . esc_attr( $search ) . '*';
			$args['search_columns'] = [ 'user_login', 'user_email', 'user_nicename', 'display_name' ];
		}

		$users = get_users( $args );
		return RestResponse::ok( [
			'items' => array_map( [ self::class, 'shape_customer' ], (array) $users ),
		] );
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	/**
	 * Parse `?ids=12,45,78` into a clean int array. Drops 0 / negative /
	 * duplicates / non-numeric tokens.
	 *
	 * @return array<int,int>
	 */
	private static function parse_id_list( string $raw ): array {
		if ( $raw === '' ) return [];
		$parts = array_map( 'trim', explode( ',', $raw ) );
		$ints  = array_map( 'intval', $parts );
		$ints  = array_filter( $ints, static fn( $n ) => $n > 0 );
		return array_values( array_unique( $ints ) );
	}

	/**
	 * Lightweight product shape for the picker. Uses WC's accessors so HPOS
	 * + variations are handled correctly.
	 *
	 * @param int|\WC_Product $product_or_id
	 */
	private static function shape_product( $product_or_id ): array {
		$product = is_object( $product_or_id ) ? $product_or_id : wc_get_product( $product_or_id );
		if ( ! $product ) {
			return [
				'id'    => (int) $product_or_id,
				'name'  => '(deleted product)',
				'sku'   => '',
				'price' => null,
				'thumbnail' => null,
			];
		}

		$thumb_id = (int) $product->get_image_id();
		$thumb    = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : null;

		return [
			'id'        => (int) $product->get_id(),
			'name'      => (string) $product->get_name(),
			'sku'       => (string) $product->get_sku(),
			'price'     => $product->get_price() !== '' ? (float) $product->get_price() : null,
			'thumbnail' => $thumb ?: null,
			'status'    => (string) $product->get_status(),
		];
	}

	private static function shape_category( $term ): array {
		if ( ! is_object( $term ) || $term instanceof \WP_Error ) {
			return [ 'id' => 0, 'name' => '(unknown)', 'slug' => '', 'count' => 0 ];
		}
		return [
			'id'    => (int) $term->term_id,
			'name'  => (string) $term->name,
			'slug'  => (string) $term->slug,
			'count' => (int) $term->count,
		];
	}

	/**
	 * Shape a WP user row for the customer picker. Pulls first/last from
	 * usermeta because `get_users` doesn't return them by default — one
	 * meta read per row is fine for a 50-row picker page.
	 *
	 * @param object $user A row from get_users() (with `fields` array set).
	 */
	private static function shape_customer( $user ): array {
		if ( ! is_object( $user ) ) {
			return [ 'id' => 0, 'login' => '', 'email' => '', 'display_name' => '', 'first_name' => '', 'last_name' => '' ];
		}
		$id = (int) $user->ID;
		return [
			'id'           => $id,
			'login'        => (string) ( $user->user_login   ?? '' ),
			'email'        => (string) ( $user->user_email   ?? '' ),
			'display_name' => (string) ( $user->display_name ?? '' ),
			'first_name'   => (string) get_user_meta( $id, 'first_name', true ),
			'last_name'    => (string) get_user_meta( $id, 'last_name',  true ),
		];
	}
}
