<?php
namespace ZippyCrm\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Loads .sql files from src/Database/{Schema,Queries}/ and substitutes the
 * `{prefix}` placeholder with $wpdb->prefix. Caches per-request.
 *
 * Returned SQL still uses %d / %s placeholders — callers MUST pass it to
 * $wpdb->prepare(). This loader does NOT escape user input.
 *
 * See .claude/rules/sql-files.md.
 */
final class QueryLoader {

	/** @var array<string,string> */
	private static array $cache = [];

	public static function query( string $relative ): string {
		// Both dbDelta() and $wpdb->prepare() choke on leading `-- ...` SQL
		// comments — dbDelta feeds them back to MySQL, prepare() returns ''.
		// Strip them everywhere so .sql files can carry documentation safely.
		return self::strip_comments( self::load( 'Queries/' . ltrim( $relative, '/' ) ) );
	}

	public static function schema( string $relative ): string {
		return self::strip_comments( self::load( 'Schema/' . ltrim( $relative, '/' ) ) );
	}

	private static function strip_comments( string $sql ): string {
		return (string) preg_replace( '/^\s*--[^\n]*\n/m', '', $sql );
	}

	private static function load( string $relative ): string {
		if ( isset( self::$cache[ $relative ] ) ) {
			return self::$cache[ $relative ];
		}

		$path = ZIPPY_CRM_DIR . 'src/Database/' . $relative;
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( "SQL file not found: {$relative}" );
		}

		global $wpdb;
		$sql = (string) file_get_contents( $path );
		$sql = str_replace(
			[ '{prefix}', '{charset_collate}' ],
			[ $wpdb->prefix, $wpdb->get_charset_collate() ],
			$sql
		);

		return self::$cache[ $relative ] = $sql;
	}
}
