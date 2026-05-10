<?php
namespace ZippyCrm\Models;

use ZippyCrm\Database\QueryLoader;
use ZippyCrm\Support\DateTimeHelper;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for crm_vouchers. Status transitions belong in VoucherService
 * (which knows how to keep the WC coupon in sync); this model only handles
 * read/write.
 */
final class Voucher {

	public const TABLE = 'crm_vouchers';

	public const STATUSES       = [ 'draft', 'active', 'paused', 'expired' ];

	/**
	 * Discount types — these match WooCommerce's native coupon types (WC 10.x
	 * registers `percent`, `fixed_cart`, and `fixed_product`) so
	 * VoucherService::sync_wc_coupon can pass the value straight through to
	 * $coupon->set_discount_type().
	 *
	 *   percent       — N% off. Cart-wide if no product/category restriction;
	 *                   WC auto-scopes it per-line when restrictions are set.
	 *   fixed_cart    — $N off the entire cart total (cart-level only)
	 *   fixed_product — $N off each matching line item (qty multiplied).
	 *                   Requires a product or category restriction.
	 *
	 * Note: WC removed `percent_product` years ago — `percent` covers both
	 * cart-wide and item-restricted percent discounts depending on whether
	 * an allow-list is set.
	 */
	public const DISCOUNT_TYPES         = [ 'fixed_cart', 'percent', 'fixed_product' ];
	public const ITEM_LEVEL_TYPES       = [ 'fixed_product' ];
	public const PERCENT_DISCOUNT_TYPES = [ 'percent' ];

	/**
	 * Audience targeting (v1.11.0). Mutually exclusive — a voucher can be
	 * public, scoped to specific customer emails, OR scoped to membership
	 * tiers, never two at once. The form layer enforces this; the validator
	 * mirrors the same rule. WC has no concept of our tiers, so tier-scoped
	 * vouchers rely on our claim filter rather than WC's coupon engine.
	 */
	public const AUDIENCE_MODES = [ 'public', 'email', 'tier' ];

	/** Sentinel value for the prepared "skip this filter" branch in admin list/count SQL. */
	private const FILTER_ALL = '__all__';

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/** @return array<string,mixed>|null */
	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);
		return $row ?: null;
	}

	/** @return array<string,mixed>|null */
	public static function find_by_code( string $code ): ?array {
		global $wpdb;
		$sql = QueryLoader::query( 'vouchers/find_by_code.sql' );
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $code ), ARRAY_A );
		return $row ?: null;
	}

	/**
	 * Vouchers visible to a customer right now (active, unexpired, has quota,
	 * not already claimed by this user).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_available_for_user( int $user_id ): array {
		global $wpdb;
		$now  = DateTimeHelper::now_mysql();

		// Tier slug: SQL filters audience_mode='tier' rows by JSON_CONTAINS
		// against this slug. Email is filtered post-fetch in PHP because
		// email_restrictions stores rich {email,first_name,last_name} objects.
		$tier_slug = (string) ( \ZippyCrm\Models\Membership::find_by_user( $user_id )['membership_level']
			?? \ZippyCrm\Services\TierRegistry::default_slug() );

		$sql  = QueryLoader::query( 'vouchers/list_available_unclaimed.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $now, $now, $tier_slug ), ARRAY_A );
		if ( ! $rows ) {
			return [];
		}

		// Post-filter email-restricted rows. Skip cleanly if WP_User isn't
		// available (cron, etc.) — email-mode rows just won't match anything.
		$user        = get_user_by( 'id', $user_id );
		$user_email  = $user ? strtolower( (string) $user->user_email ) : '';

		$out = [];
		foreach ( $rows as $row ) {
			if ( ( $row['audience_mode'] ?? 'public' ) === 'email' ) {
				if ( $user_email === '' ) {
					continue;
				}
				$decoded = json_decode( (string) ( $row['email_restrictions'] ?? '' ), true );
				if ( ! is_array( $decoded ) || ! self::email_in_list( $user_email, $decoded ) ) {
					continue;
				}
			}
			// Strip the raw JSON-list columns from the response shape — the
			// customer-facing card doesn't need to know about restrictions.
			unset( $row['email_restrictions'], $row['allowed_tiers'] );
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Match a normalized email against a stored email_restrictions list.
	 * The list may contain plain strings (legacy) or {email,...} objects.
	 */
	private static function email_in_list( string $email, array $entries ): bool {
		foreach ( $entries as $entry ) {
			$candidate = is_array( $entry ) ? (string) ( $entry['email'] ?? '' ) : (string) $entry;
			if ( strtolower( trim( $candidate ) ) === $email ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns id of the new row, or 0 on failure.
	 *
	 * Vouchers are ALWAYS created in 'draft' status — the only way to flip to
	 * 'active' is through VoucherService::publish(), which is the only code
	 * path that creates the matching WC coupon. Allowing the caller to pass
	 * arbitrary statuses here would create active CRM vouchers with no WC
	 * coupon, breaking customer checkout silently.
	 */
	/**
	 * Columns whose stored value is a JSON-encoded array (or null when no
	 * restriction applies). Used by both create() and update() so the rules
	 * for shape and serialization live in one place.
	 *
	 *   email_restrictions          → array<int,string>      (allowed emails)
	 *   product_ids                 → array<int,int>         (allow-list)
	 *   excluded_product_ids        → array<int,int>         (block-list)
	 *   product_categories          → array<int,int>         (allow-list)
	 *   excluded_product_categories → array<int,int>         (block-list)
	 *   allowed_hours               → { days, from_minute, to_minute }
	 *   allowed_tiers               → array<int,string>      (tier slugs)
	 */
	public const JSON_FIELDS = [
		'email_restrictions',
		'product_ids',
		'excluded_product_ids',
		'product_categories',
		'excluded_product_categories',
		'allowed_hours',
		'allowed_tiers',
	];

	public static function create( array $data, int $created_by ): int {
		global $wpdb;
		$ok = $wpdb->insert(
			self::table(),
			[
				'code'             => strtoupper( (string) $data['code'] ),
				'title'            => (string) $data['title'],
				'description'      => $data['description'] ?? null,
				'discount_type'    => (string) $data['discount_type'],
				'discount_value'   => (float) $data['discount_value'],
				'min_order_amount' => (float) ( $data['min_order_amount'] ?? 0 ),
				'max_order_amount' => (float) ( $data['max_order_amount'] ?? 0 ),
				'max_uses'         => (int) ( $data['max_uses'] ?? 0 ),
				'usage_limit_per_user'   => (int) ( $data['usage_limit_per_user']   ?? 0 ),
				'limit_usage_to_x_items' => (int) ( $data['limit_usage_to_x_items'] ?? 0 ),
				'individual_use'     => isset( $data['individual_use'] )     ? (int) (bool) $data['individual_use']     : 1,
				'exclude_sale_items' => isset( $data['exclude_sale_items'] ) ? (int) (bool) $data['exclude_sale_items'] : 0,
				'free_shipping'      => isset( $data['free_shipping'] )      ? (int) (bool) $data['free_shipping']      : 0,
				'email_restrictions'          => self::encode_json( $data['email_restrictions']          ?? null ),
				'product_ids'                 => self::encode_json( $data['product_ids']                 ?? null ),
				'excluded_product_ids'        => self::encode_json( $data['excluded_product_ids']        ?? null ),
				'product_categories'          => self::encode_json( $data['product_categories']          ?? null ),
				'excluded_product_categories' => self::encode_json( $data['excluded_product_categories'] ?? null ),
				'allowed_hours'               => self::encode_json( $data['allowed_hours']               ?? null ),
				'allowed_tiers'               => self::encode_json( $data['allowed_tiers']               ?? null ),
				'distribution_mode' => isset( $data['distribution_mode'] ) ? (string) $data['distribution_mode'] : 'single_code',
				'audience_mode'     => isset( $data['audience_mode'] )     ? (string) $data['audience_mode']     : 'public',
				'status'           => 'draft',
				'starts_at'        => $data['starts_at'] ?? null,
				'expires_at'       => $data['expires_at'] ?? null,
				'created_by'       => $created_by,
				'created_at'       => DateTimeHelper::now_mysql(),
			],
			// Order matches the array above. JSON fields stored as strings (%s).
			[
				'%s', '%s', '%s', '%s', '%f',           // code, title, description, discount_type, discount_value
				'%f', '%f',                              // min/max order
				'%d', '%d', '%d',                        // max_uses, usage_limit_per_user, limit_usage_to_x_items
				'%d', '%d', '%d',                        // individual_use, exclude_sale_items, free_shipping
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', // 7 JSON fields (added allowed_tiers)
				'%s', '%s',                              // distribution_mode, audience_mode
				'%s', '%s', '%s', '%d', '%s',            // status, starts_at, expires_at, created_by, created_at
			]
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Encode an array (or null) to a JSON string for storage. Returns null
	 * for null / empty array so the column reads as "no restriction" rather
	 * than the literal string "[]".
	 */
	private static function encode_json( $value ): ?string {
		if ( $value === null || $value === '' ) {
			return null;
		}
		// Form might submit JSON strings already (e.g. from the React side).
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : null;
		}
		if ( ! is_array( $value ) || empty( $value ) ) {
			return null;
		}
		return wp_json_encode( $value );
	}

	/**
	 * Decode a stored row's JSON columns into PHP arrays so callers (REST
	 * controller, sync_wc_coupon) don't have to remember which fields are
	 * serialized. Mutates a copy and returns it.
	 *
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	public static function decode_json_fields( array $row ): array {
		foreach ( self::JSON_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $row ) ) {
				continue;
			}
			$raw = $row[ $field ];
			if ( $raw === null || $raw === '' ) {
				$row[ $field ] = null;
				continue;
			}
			$decoded = json_decode( (string) $raw, true );
			$row[ $field ] = is_array( $decoded ) ? $decoded : null;
		}
		return $row;
	}

	public static function update_status( int $id, string $status ): bool {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		global $wpdb;
		$updated = $wpdb->update(
			self::table(),
			[ 'status' => $status ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);
		return $updated !== false;
	}

	/**
	 * Atomically bumps uses_count and auto-flips to 'expired' when quota hits.
	 */
	public static function increment_uses( int $id ): bool {
		global $wpdb;
		$sql = QueryLoader::query( 'vouchers/increment_uses.sql' );
		return $wpdb->query( $wpdb->prepare( $sql, $id ) ) !== false;
	}

	/* ============================================================
	 * Admin
	 * ============================================================ */

	/**
	 * Whitelisted sortable columns → SQL fragment. Anything not in this map
	 * silently falls back to the default ('id DESC'). Extending: add the new
	 * column key + its bare SQL expression. Don't accept arbitrary strings —
	 * `{order_by}` is substituted directly into the prepared SQL.
	 */
	private const SORTABLE = [
		'id'             => 'id',
		'code'           => 'code',
		'title'          => 'title',
		'discount_value' => 'discount_value',
		'min_order_amount' => 'min_order_amount',
		'max_uses'       => 'max_uses',
		'uses_count'     => 'uses_count',
		'status'         => 'status',
		'starts_at'      => 'starts_at',
		'expires_at'     => 'expires_at',
		'created_at'     => 'created_at',
	];

	/**
	 * Paginated admin list. Status / search filters use the FILTER_ALL sentinel
	 * to keep one prepared shape for every combination.
	 *
	 * @param string $status    One of self::STATUSES, or '' to skip the status filter.
	 * @param string $search    Free-text search against `code` or `title`. '' to skip.
	 * @param string $sort      Column key (whitelisted in SORTABLE), or '' for default.
	 * @param string $direction 'asc' | 'desc' (default desc).
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_admin( string $status, string $search, int $page, int $per_page, string $sort = '', string $direction = 'desc' ): array {
		global $wpdb;

		$status_active  = $status !== '' && in_array( $status, self::STATUSES, true );
		$status_token   = $status_active ? $status : self::FILTER_ALL;
		$status_sent    = $status_active ? $status : self::FILTER_ALL;

		$search_active  = $search !== '';
		$search_sent    = $search_active ? $search : self::FILTER_ALL;
		$pattern        = '%' . $wpdb->esc_like( $search ) . '%';

		$page     = max( 1, $page );
		$per_page = max( 1, min( 100, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$order_by = self::resolve_order_by( $sort, $direction );

		// {order_by} is substituted from a strict whitelist BEFORE prepare();
		// safe because the value never comes from request input directly.
		$sql = str_replace(
			'{order_by}',
			$order_by,
			QueryLoader::query( 'vouchers/admin/list_paginated.sql' )
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$status_sent, $status_token,
				$search_sent, $pattern, $pattern,
				$per_page, $offset
			),
			ARRAY_A
		);
		return $rows ?: [];
	}

	/**
	 * Map a (sort_key, direction) pair to a safe SQL fragment for the
	 * `{order_by}` placeholder. Falls back to 'id DESC' for unknown keys
	 * or directions.
	 */
	private static function resolve_order_by( string $sort, string $direction ): string {
		$col = self::SORTABLE[ $sort ] ?? null;
		if ( ! $col ) {
			return 'id DESC';
		}
		$dir = strtolower( $direction ) === 'asc' ? 'ASC' : 'DESC';
		// Always tie-break on id for stable ordering across pages.
		return $col . ' ' . $dir . ', id DESC';
	}

	/**
	 * Total row count for the same filters as list_for_admin.
	 */
	public static function count_for_admin( string $status, string $search ): int {
		global $wpdb;

		$status_active = $status !== '' && in_array( $status, self::STATUSES, true );
		$status_token  = $status_active ? $status : self::FILTER_ALL;
		$status_sent   = $status_active ? $status : self::FILTER_ALL;

		$search_active = $search !== '';
		$search_sent   = $search_active ? $search : self::FILTER_ALL;
		$pattern       = '%' . $wpdb->esc_like( $search ) . '%';

		$sql   = QueryLoader::query( 'vouchers/admin/count.sql' );
		$total = $wpdb->get_var(
			$wpdb->prepare(
				$sql,
				$status_sent, $status_token,
				$search_sent, $pattern, $pattern
			)
		);
		return (int) $total;
	}

	/**
	 * Status → count for the Quick Stats bar. Always returns every status,
	 * filling missing keys with 0 so the UI doesn't have to.
	 *
	 * @return array<string,int>
	 */
	public static function count_by_status(): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'vouchers/admin/count_by_status.sql' );
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$out = array_fill_keys( self::STATUSES, 0 );
		foreach ( (array) $rows as $row ) {
			$status = (string) ( $row['status'] ?? '' );
			if ( isset( $out[ $status ] ) ) {
				$out[ $status ] = (int) $row['total'];
			}
		}
		return $out;
	}

	/**
	 * Partial update. Only writes the keys present in $data; ignores unknown
	 * keys to keep the API surface tight. Returns true if anything changed.
	 *
	 * Intentionally NOT updateable here:
	 *   - `code` / `created_by`         immutable post-creation
	 *   - `status`                       use update_status() instead so the
	 *                                    enum gets validated; arbitrary status
	 *                                    writes here would let an admin flip a
	 *                                    voucher to 'active' without ever
	 *                                    going through VoucherService::publish()
	 *                                    (which is what creates the WC coupon).
	 *   - `uses_count`                   only increment_uses() may write this
	 */
	public static function update( int $id, array $data ): bool {
		// scalar columns + their wpdb format specifier
		$scalars = [
			'title'                   => '%s',
			'description'             => '%s',
			'discount_type'           => '%s',
			'discount_value'          => '%f',
			'min_order_amount'        => '%f',
			'max_order_amount'        => '%f',
			'max_uses'                => '%d',
			'usage_limit_per_user'    => '%d',
			'limit_usage_to_x_items'  => '%d',
			'individual_use'          => '%d',
			'exclude_sale_items'      => '%d',
			'free_shipping'           => '%d',
			'starts_at'               => '%s',
			'expires_at'              => '%s',
			'audience_mode'           => '%s',
		];
		$booleans = [ 'individual_use', 'exclude_sale_items', 'free_shipping' ];

		$values  = [];
		$formats = [];
		foreach ( $scalars as $key => $fmt ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$values[ $key ] = in_array( $key, $booleans, true ) ? (int) (bool) $data[ $key ] : $data[ $key ];
			$formats[]      = $fmt;
		}

		// JSON-encoded array columns. Always normalised through encode_json so
		// "[]" / "null" / "" all collapse to NULL ("no restriction").
		foreach ( self::JSON_FIELDS as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			$values[ $key ] = self::encode_json( $data[ $key ] );
			$formats[]      = '%s';
		}

		if ( ! $values ) {
			return false;
		}

		global $wpdb;
		$updated = $wpdb->update( self::table(), $values, [ 'id' => $id ], $formats, [ '%d' ] );
		return $updated !== false;
	}

	/**
	 * Hard delete. Service layer is responsible for refusing to call this
	 * when claims exist or when the voucher isn't a draft.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
	}
}
