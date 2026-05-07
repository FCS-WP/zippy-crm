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
		return self::load( 'Queries/' . ltrim( $relative, '/' ) );
	}

	public static function schema( string $relative ): string {
		return self::load( 'Schema/' . ltrim( $relative, '/' ) );
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
		$sql = str_replace( '{prefix}', $wpdb->prefix, $sql );

		return self::$cache[ $relative ] = $sql;
	}
}
