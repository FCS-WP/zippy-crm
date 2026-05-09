<?php
namespace ZippyCrm\Services;

use ZippyCrm\Database\QueryLoader;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only aggregations for the admin Reports panel. Every series is a
 * dense array (one row per day in the requested range) — the SQL groups by
 * day, this layer fills missing days with zeros so charts render flat lines
 * instead of broken segments.
 *
 * Date semantics: from/to are interpreted as UTC (joined_at, created_at,
 * claimed_at all stored UTC). Bucketing is by `DATE(col)` server-side, which
 * MySQL evaluates against the server timezone — but since stored values are
 * UTC and we widen the range with a `Y-m-d 00:00:00` / `23:59:59` envelope,
 * boundary skew at most pulls one extra row at the edges. Acceptable for
 * an admin dashboard; revisit if reports go to customers.
 */
final class ReportsService {

	public const MAX_RANGE_DAYS = 365;
	public const DEFAULT_DAYS   = 30;

	/**
	 * Normalize + validate a date range.
	 *
	 * @return array{from:string,to:string,days:int}|\WP_Error
	 */
	public static function parse_range( ?string $from, ?string $to ) {
		// Default: last DEFAULT_DAYS ending today (UTC).
		$now    = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$end    = $to   ? self::parse_date( $to )   : $now;
		$start  = $from ? self::parse_date( $from ) : $end->modify( '-' . ( self::DEFAULT_DAYS - 1 ) . ' days' );

		if ( ! $start || ! $end ) {
			return new \WP_Error( 'reports_bad_date', __( 'Invalid date format. Use YYYY-MM-DD.', 'zippy-crm' ), [ 'status' => 400 ] );
		}
		if ( $start > $end ) {
			return new \WP_Error( 'reports_bad_range', __( '"from" must be on or before "to".', 'zippy-crm' ), [ 'status' => 400 ] );
		}

		$days = (int) $start->diff( $end )->format( '%a' ) + 1;
		if ( $days > self::MAX_RANGE_DAYS ) {
			return new \WP_Error(
				'reports_range_too_wide',
				/* translators: %d: max days */
				sprintf( __( 'Range too wide. Maximum is %d days.', 'zippy-crm' ), self::MAX_RANGE_DAYS ),
				[ 'status' => 400 ]
			);
		}

		return [
			'from' => $start->format( 'Y-m-d 00:00:00' ),
			'to'   => $end->format( 'Y-m-d 23:59:59' ),
			'days' => $days,
		];
	}

	/**
	 * @return array<int,array{day:string,total:int}>
	 */
	public static function members_per_day( string $from, string $to ): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'admin/reports/members_per_day.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $from, $to ), ARRAY_A );

		return self::zero_fill( $from, $to, $rows ?: [], [ 'total' => 0 ] );
	}

	/**
	 * @return array<int,array{day:string,earned:int,redeemed:int,adjusted:int}>
	 */
	public static function points_activity_per_day( string $from, string $to ): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'admin/reports/points_activity_per_day.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $from, $to ), ARRAY_A );

		return self::zero_fill( $from, $to, $rows ?: [], [
			'earned'   => 0,
			'redeemed' => 0,
			'adjusted' => 0,
		] );
	}

	/**
	 * @return array<int,array{day:string,claimed:int,used:int}>
	 */
	public static function voucher_claims_per_day( string $from, string $to ): array {
		global $wpdb;
		$sql  = QueryLoader::query( 'admin/reports/voucher_claims_per_day.sql' );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $from, $to ), ARRAY_A );

		return self::zero_fill( $from, $to, $rows ?: [], [
			'claimed' => 0,
			'used'    => 0,
		] );
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	private static function parse_date( string $raw ): ?\DateTimeImmutable {
		// Strict YYYY-MM-DD only — DateTimeImmutable's parser also accepts
		// relative phrases ("yesterday", "+1 week"), which we don't want from
		// REST input. Anchor with a regex first, then construct explicitly.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
			return null;
		}
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $raw, new \DateTimeZone( 'UTC' ) );
		if ( ! $d ) {
			return null;
		}
		// Reject obviously bogus calendar values that createFromFormat coerces
		// (e.g. 2026-13-01 → 2027-01-01) by round-tripping.
		if ( $d->format( 'Y-m-d' ) !== $raw ) {
			return null;
		}
		if ( $d->format( 'Y' ) < '2000' || $d->format( 'Y' ) > '2100' ) {
			return null;
		}
		return $d;
	}

	/**
	 * Pad missing days. Indexes the DB rows by day, then walks the range and
	 * fills gaps with the supplied zero shape. Casts numeric columns to int.
	 *
	 * @param array<int,array<string,mixed>> $rows  DB rows with a 'day' column.
	 * @param array<string,int>              $zero  Default values for columns other than 'day'.
	 * @return array<int,array<string,mixed>>
	 */
	private static function zero_fill( string $from, string $to, array $rows, array $zero ): array {
		$by_day = [];
		foreach ( $rows as $row ) {
			$day = (string) $row['day'];
			$out = [ 'day' => $day ];
			foreach ( $zero as $col => $_ ) {
				$out[ $col ] = (int) ( $row[ $col ] ?? 0 );
			}
			$by_day[ $day ] = $out;
		}

		$start  = new \DateTimeImmutable( substr( $from, 0, 10 ), new \DateTimeZone( 'UTC' ) );
		$end    = new \DateTimeImmutable( substr( $to,   0, 10 ), new \DateTimeZone( 'UTC' ) );
		$series = [];
		$cursor = $start;
		while ( $cursor <= $end ) {
			$day = $cursor->format( 'Y-m-d' );
			$series[] = $by_day[ $day ] ?? array_merge( [ 'day' => $day ], $zero );
			$cursor   = $cursor->modify( '+1 day' );
		}
		return $series;
	}
}
