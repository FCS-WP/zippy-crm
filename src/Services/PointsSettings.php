<?php
namespace ZippyCrm\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Site-wide points-earning configuration. Backed by `wp_options` rather than a
 * custom table because the data is small (a few hundred IDs at most), site-wide
 * (not per-user / per-tier), and changes infrequently.
 *
 *   zippy_crm_excluded_product_ids   → array<int,int>  WC product IDs that
 *                                                       earn no points
 *   zippy_crm_excluded_category_ids  → array<int,int>  WC product_cat term IDs
 *                                                       that earn no points
 *
 * The earn rate per tier still lives on crm_tiers.multiplier — it's per-tier,
 * so the Tiers admin owns it. This service handles only the cross-cutting
 * "what doesn't earn points" knobs.
 *
 * Blacklist semantics:
 *   - A line item earns 0 points if its product OR any of its categories is
 *     in the blacklist.
 *   - Empty list = no exclusions (default for fresh installs).
 *   - Stored as JSON arrays of integers; `get_*` always returns array<int,int>
 *     even if the stored value is malformed (cleans + casts on read).
 */
final class PointsSettings {

	public const OPT_EXCLUDED_PRODUCT_IDS  = 'zippy_crm_excluded_product_ids';
	public const OPT_EXCLUDED_CATEGORY_IDS = 'zippy_crm_excluded_category_ids';

	/** @return array<int,int> */
	public static function excluded_product_ids(): array {
		return self::read_int_list( self::OPT_EXCLUDED_PRODUCT_IDS );
	}

	/** @return array<int,int> */
	public static function excluded_category_ids(): array {
		return self::read_int_list( self::OPT_EXCLUDED_CATEGORY_IDS );
	}

	/** @param array<int,int|string> $ids */
	public static function set_excluded_product_ids( array $ids ): void {
		self::write_int_list( self::OPT_EXCLUDED_PRODUCT_IDS, $ids );
	}

	/** @param array<int,int|string> $ids */
	public static function set_excluded_category_ids( array $ids ): void {
		self::write_int_list( self::OPT_EXCLUDED_CATEGORY_IDS, $ids );
	}

	/**
	 * True if the given line-item product earns no points (its product is
	 * blacklisted, or any of its categories is). Used by PointsEngine when
	 * computing the earn subtotal.
	 */
	public static function is_product_excluded( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}
		if ( in_array( $product_id, self::excluded_product_ids(), true ) ) {
			return true;
		}

		$category_ids = self::excluded_category_ids();
		if ( empty( $category_ids ) ) {
			return false;
		}

		// `wc_get_product_term_ids` covers variations + parents transparently.
		$product_terms = wc_get_product_term_ids( $product_id, 'product_cat' );
		foreach ( (array) $product_terms as $term_id ) {
			if ( in_array( (int) $term_id, $category_ids, true ) ) {
				return true;
			}
		}
		return false;
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	/** @return array<int,int> */
	private static function read_int_list( string $option ): array {
		$raw = get_option( $option, [] );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : [];
		}
		if ( ! is_array( $raw ) ) {
			return [];
		}
		$out = [];
		foreach ( $raw as $v ) {
			$id = (int) $v;
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}
		// Dedupe — reading shouldn't be expensive but it's cheap to clean.
		return array_values( array_unique( $out ) );
	}

	/** @param array<int,int|string> $ids */
	private static function write_int_list( string $option, array $ids ): void {
		$clean = [];
		foreach ( $ids as $v ) {
			$id = (int) $v;
			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}
		$clean = array_values( array_unique( $clean ) );
		// `false` for autoload — these are admin-only reads that never run on
		// the customer-facing critical path before WP has loaded options anyway.
		// But the points-award hook runs on order completion, which is also
		// post-options-load, so autoloading isn't beneficial.
		update_option( $option, $clean, false );
	}
}
