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
	public const DISCOUNT_TYPES = [ 'fixed_cart', 'percent' ];

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
		$sql  = QueryLoader::query( 'vouchers/list_available_unclaimed.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $now, $now ), ARRAY_A );
		return $rows ?: [];
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
	 */
	public const JSON_FIELDS = [
		'email_restrictions',
		'product_ids',
		'excluded_product_ids',
		'product_categories',
		'excluded_product_categories',
		'allowed_hours',
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
				'%s', '%s', '%s', '%s', '%s', '%s',      // 6 JSON fields
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
	 * Paginated admin list. Status / search filters use the FILTER_ALL sentinel
	 * to keep one prepared shape for every combination.
	 *
	 * @param string $status One of self::STATUSES, or '' to skip the status filter.
	 * @param string $search Free-text search against `code` or `title`. '' to skip.
	 * @return array<int,array<string,mixed>>
	 */
	public static function list_for_admin( string $status, string $search, int $page, int $per_page ): array {
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

		$sql  = QueryLoader::query( 'vouchers/admin/list_paginated.sql' );
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
