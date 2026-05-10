<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for crm_tiers. Replaces the hardcoded LEVELS/MULTIPLIERS/LABELS
 * constants on Membership.
 *
 * Slugs are immutable once created (they're the foreign-key value in
 * crm_memberships.membership_level). Renaming "the silver tier" means
 * editing the `label` field, not the slug.
 *
 * Caching is at the service layer (TierRegistry) — this model is a thin
 * data access layer on purpose.
 */
final class Tier {

	public const TABLE = 'crm_tiers';

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** @return array<int,array<string,mixed>> ordered by sort_order ASC */
	public static function all(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT slug, label, multiplier, threshold_orders, threshold_spend, is_admin_only, sort_order, created_at
			 FROM ' . self::table() . '
			 ORDER BY sort_order ASC, slug ASC',
			ARRAY_A
		);
		return $rows ?: [];
	}

	/** @return array<string,mixed>|null */
	public static function find( string $slug ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT slug, label, multiplier, threshold_orders, threshold_spend, is_admin_only, sort_order, created_at
				 FROM ' . self::table() . ' WHERE slug = %s',
				$slug
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Insert a new tier. Returns true on success, false on UNIQUE collision
	 * (slug already exists) or insert error. Caller is responsible for
	 * validating the slug shape + checking for collision before calling
	 * (TierRegistry::create does both).
	 */
	public static function insert( array $data ): bool {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			[
				'slug'             => (string) $data['slug'],
				'label'            => (string) $data['label'],
				'multiplier'       => (float) $data['multiplier'],
				'threshold_orders' => isset( $data['threshold_orders'] ) ? (int) $data['threshold_orders'] : null,
				'threshold_spend'  => isset( $data['threshold_spend']  ) ? (float) $data['threshold_spend']  : null,
				'is_admin_only'    => ! empty( $data['is_admin_only'] ) ? 1 : 0,
				'sort_order'       => (int) ( $data['sort_order'] ?? 0 ),
				'created_at'       => DateTimeHelper::now_mysql(),
			],
			[ '%s', '%s', '%f', '%d', '%f', '%d', '%d', '%s' ]
		);
		return $ok !== false;
	}

	/**
	 * Partial update. Slug is intentionally NOT in the allowed list — slugs
	 * are immutable once created. To rename a tier, edit `label` instead.
	 */
	public static function update( string $slug, array $data ): bool {
		$allowed = [
			'label'            => '%s',
			'multiplier'       => '%f',
			'threshold_orders' => '%d',
			'threshold_spend'  => '%f',
			'is_admin_only'    => '%d',
			'sort_order'       => '%d',
		];

		$values  = [];
		$formats = [];
		foreach ( $allowed as $key => $fmt ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			// Coerce nullable numeric to PHP null so wpdb writes SQL NULL.
			$v = $data[ $key ];
			if ( in_array( $key, [ 'threshold_orders', 'threshold_spend' ], true ) && ( $v === '' || $v === null ) ) {
				$values[ $key ] = null;
			} else {
				$values[ $key ] = $v;
			}
			$formats[] = $fmt;
		}
		if ( ! $values ) {
			return false;
		}

		global $wpdb;
		$updated = $wpdb->update( self::table(), $values, [ 'slug' => $slug ], $formats, [ '%s' ] );
		return $updated !== false;
	}

	public static function delete( string $slug ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), [ 'slug' => $slug ], [ '%s' ] );
	}

	/**
	 * Member counts per tier slug. Used as the deletion guard.
	 *
	 * @return array<string,int>  slug → count
	 */
	public static function member_counts(): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'tiers/count_members.sql' );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out  = [];
		foreach ( (array) $rows as $r ) {
			$out[ (string) $r['slug'] ] = (int) $r['members'];
		}
		return $out;
	}
}
