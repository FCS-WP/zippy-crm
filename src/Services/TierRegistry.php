<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\Tier;
use ZippyCrm\Support\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for tier definitions.
 *
 * Replaces the hardcoded `Membership::LEVELS / MULTIPLIERS / LABELS` and
 * `MembershipService::TIER_THRESHOLDS` constants. Reads come through the
 * cache; writes invalidate.
 *
 * Hot path: every authenticated WC request that does anything with points
 * eventually reads `multiplier_for($user_id)`. Caching the whole tier set
 * keeps that to one cache hit instead of one query per request.
 */
final class TierRegistry {

	private const CACHE_KEY = 'tiers:all';

	/** Slug shape: lowercase letters/digits/hyphens, 2-40 chars. */
	private const SLUG_REGEX = '/^[a-z0-9][a-z0-9_-]{1,39}$/';

	/* ============================================================
	 * Reads
	 * ============================================================ */

	/**
	 * Every tier, ordered by sort_order. Cached for the request lifetime
	 * via the wp object cache.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		return Cache::remember( self::CACHE_KEY, fn() => Tier::all() ) ?: [];
	}

	/** Slugs only. */
	public static function slugs(): array {
		return array_map( static fn( array $t ) => (string) $t['slug'], self::all() );
	}

	/** slug → label map (for display). */
	public static function labels(): array {
		$out = [];
		foreach ( self::all() as $t ) {
			$out[ (string) $t['slug'] ] = (string) $t['label'];
		}
		return $out;
	}

	public static function exists( string $slug ): bool {
		return in_array( $slug, self::slugs(), true );
	}

	/** @return array<string,mixed>|null */
	public static function find( string $slug ): ?array {
		foreach ( self::all() as $t ) {
			if ( (string) $t['slug'] === $slug ) {
				return $t;
			}
		}
		return null;
	}

	/** Earn-rate multiplier for a slug. Falls back to 1.0 for unknown slugs. */
	public static function multiplier_for( string $slug ): float {
		$t = self::find( $slug );
		return $t ? (float) $t['multiplier'] : 1.0;
	}

	/** Slug of the lowest-sort_order non-admin-only tier. Falls back to 'free'. */
	public static function default_slug(): string {
		foreach ( self::all() as $t ) {
			if ( ! (int) $t['is_admin_only'] ) {
				return (string) $t['slug'];
			}
		}
		return 'free';
	}

	/**
	 * Highest tier the given stats qualify for, walking tiers descending by
	 * spend threshold. Tiers with `is_admin_only=1` are skipped — they're
	 * admin-assigned only.
	 *
	 * Falls back to `default_slug()` when no threshold matches.
	 */
	public static function compute_for_stats( array $stats ): string {
		$orders = (int) ( $stats['total_orders'] ?? 0 );
		$spend  = (float) ( $stats['lifetime_spend'] ?? 0 );

		// Walk descending by spend threshold (highest first). Tiers with no
		// threshold (free, vip) are filtered out.
		$candidates = array_filter( self::all(), static fn( array $t ) =>
			! (int) $t['is_admin_only']
			&& ( $t['threshold_orders'] !== null || $t['threshold_spend'] !== null )
		);
		usort( $candidates, static fn( array $a, array $b ) =>
			(float) ( $b['threshold_spend'] ?? 0 ) <=> (float) ( $a['threshold_spend'] ?? 0 )
		);

		foreach ( $candidates as $t ) {
			$o_ok = $t['threshold_orders'] !== null && $orders >= (int) $t['threshold_orders'];
			$s_ok = $t['threshold_spend']  !== null && $spend  >= (float) $t['threshold_spend'];
			if ( $o_ok || $s_ok ) {
				return (string) $t['slug'];
			}
		}

		return self::default_slug();
	}

	/**
	 * Next tier above $current, or null if $current is at the top of the
	 * non-admin ladder. Used by next-tier-progress.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function next_above( string $current ): ?array {
		$cur = self::find( $current );
		if ( ! $cur ) {
			return null;
		}
		$cur_sort = (int) $cur['sort_order'];
		$candidate = null;
		foreach ( self::all() as $t ) {
			if ( (int) $t['is_admin_only'] ) {
				continue;
			}
			if ( (int) $t['sort_order'] <= $cur_sort ) {
				continue;
			}
			if ( $candidate === null || (int) $t['sort_order'] < (int) $candidate['sort_order'] ) {
				$candidate = $t;
			}
		}
		return $candidate;
	}

	/* ============================================================
	 * Writes (admin)
	 * ============================================================ */

	/**
	 * @return array<string,mixed>|\WP_Error  the new row on success
	 */
	public static function create( array $data ) {
		$err = self::validate( $data, true );
		if ( $err instanceof \WP_Error ) {
			return $err;
		}
		$slug = strtolower( (string) $data['slug'] );

		if ( self::exists( $slug ) ) {
			return new \WP_Error( 'tier_slug_taken', __( 'A tier with this slug already exists.', 'zippy-crm' ), [ 'status' => 409 ] );
		}

		$data['slug'] = $slug;
		if ( ! Tier::insert( $data ) ) {
			return new \WP_Error( 'tier_create_failed', __( 'Could not create tier.', 'zippy-crm' ), [ 'status' => 500 ] );
		}

		self::invalidate();
		do_action( 'crm_tier_created', $slug );
		return Tier::find( $slug );
	}

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function update( string $slug, array $data ) {
		if ( ! self::exists( $slug ) ) {
			return new \WP_Error( 'tier_not_found', __( 'Tier not found.', 'zippy-crm' ), [ 'status' => 404 ] );
		}

		// Slug is immutable; silently drop if present.
		unset( $data['slug'], $data['created_at'] );

		$err = self::validate( $data, false );
		if ( $err instanceof \WP_Error ) {
			return $err;
		}

		Tier::update( $slug, $data );
		self::invalidate();
		do_action( 'crm_tier_updated', $slug, array_keys( $data ) );
		return Tier::find( $slug );
	}

	/**
	 * Refuses if the tier has any members on it (would orphan their
	 * `membership_level` value). Admin must re-assign those members first.
	 *
	 * @return true|\WP_Error
	 */
	public static function delete( string $slug ) {
		if ( ! self::exists( $slug ) ) {
			return new \WP_Error( 'tier_not_found', __( 'Tier not found.', 'zippy-crm' ), [ 'status' => 404 ] );
		}

		$counts = Tier::member_counts();
		$on_tier = (int) ( $counts[ $slug ] ?? 0 );
		if ( $on_tier > 0 ) {
			return new \WP_Error(
				'tier_has_members',
				sprintf(
					/* translators: 1: tier slug, 2: member count */
					__( 'Cannot delete tier "%1$s" — %2$d members are assigned to it. Move them to another tier first.', 'zippy-crm' ),
					$slug,
					$on_tier
				),
				[ 'status' => 409, 'members' => $on_tier ]
			);
		}

		// Don't let admin delete every non-admin tier. Customers always need
		// at least one tier to land on.
		$remaining_non_admin = array_filter( self::all(), static fn( array $t ) =>
			(string) $t['slug'] !== $slug && ! (int) $t['is_admin_only']
		);
		if ( empty( $remaining_non_admin ) ) {
			return new \WP_Error(
				'tier_last_default',
				__( 'Cannot delete the only non-admin tier — at least one must remain.', 'zippy-crm' ),
				[ 'status' => 409 ]
			);
		}

		if ( ! Tier::delete( $slug ) ) {
			return new \WP_Error( 'tier_delete_failed', __( 'Could not delete tier.', 'zippy-crm' ), [ 'status' => 500 ] );
		}

		self::invalidate();
		do_action( 'crm_tier_deleted', $slug );
		return true;
	}

	public static function invalidate(): void {
		Cache::delete( self::CACHE_KEY );
	}

	/* ============================================================
	 * Validation
	 * ============================================================ */

	private static function validate( array $data, bool $is_create ): ?\WP_Error {
		if ( $is_create ) {
			foreach ( [ 'slug', 'label', 'multiplier' ] as $required ) {
				if ( ! isset( $data[ $required ] ) || $data[ $required ] === '' ) {
					return new \WP_Error(
						'tier_missing_field',
						/* translators: %s: field name */
						sprintf( __( 'Missing required field: %s.', 'zippy-crm' ), $required ),
						[ 'status' => 400 ]
					);
				}
			}
		}

		if ( isset( $data['slug'] ) ) {
			$slug = strtolower( (string) $data['slug'] );
			if ( ! preg_match( self::SLUG_REGEX, $slug ) ) {
				return new \WP_Error(
					'tier_bad_slug',
					__( 'Slug must be 2-40 chars, lowercase a-z 0-9 - _ only, starting with a letter or digit.', 'zippy-crm' ),
					[ 'status' => 400 ]
				);
			}
		}

		if ( isset( $data['multiplier'] ) ) {
			$m = (float) $data['multiplier'];
			// 0 is valid — it means "this tier earns no points" and is the
			// default for new tiers (admin must opt in to earning).
			if ( $m < 0 || $m > 10 ) {
				return new \WP_Error(
					'tier_bad_multiplier',
					__( 'Earn rate must be ≥ 0 and ≤ 10.', 'zippy-crm' ),
					[ 'status' => 400 ]
				);
			}
		}

		if ( array_key_exists( 'threshold_orders', $data ) && $data['threshold_orders'] !== null && $data['threshold_orders'] !== '' ) {
			if ( (int) $data['threshold_orders'] < 0 ) {
				return new \WP_Error( 'tier_bad_threshold', __( 'Order threshold must be ≥ 0.', 'zippy-crm' ), [ 'status' => 400 ] );
			}
		}
		if ( array_key_exists( 'threshold_spend', $data ) && $data['threshold_spend'] !== null && $data['threshold_spend'] !== '' ) {
			if ( (float) $data['threshold_spend'] < 0 ) {
				return new \WP_Error( 'tier_bad_threshold', __( 'Spend threshold must be ≥ 0.', 'zippy-crm' ), [ 'status' => 400 ] );
			}
		}

		return null;
	}
}
