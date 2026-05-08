<?php
namespace ZippyCrm\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Versioned wrapper around the WP object cache.
 * Bump VERSION to flush every Zippy CRM cache key in one go.
 */
final class Cache {

	public const GROUP   = 'zippy_crm';
	public const VERSION = 'v1';
	public const TTL     = HOUR_IN_SECONDS;

	public static function key( string $name ): string {
		return self::VERSION . ':' . $name;
	}

	public static function get( string $name ) {
		$found = false;
		$value = wp_cache_get( self::key( $name ), self::GROUP, false, $found );
		return $found ? $value : null;
	}

	public static function set( string $name, $value, int $ttl = self::TTL ): void {
		wp_cache_set( self::key( $name ), $value, self::GROUP, $ttl );
	}

	public static function delete( string $name ): void {
		wp_cache_delete( self::key( $name ), self::GROUP );
	}

	/**
	 * Read-through: returns cached value, or computes + caches it.
	 */
	public static function remember( string $name, callable $compute, int $ttl = self::TTL ) {
		$cached = self::get( $name );
		if ( $cached !== null ) {
			return $cached;
		}
		$value = $compute();
		if ( $value !== null ) {
			self::set( $name, $value, $ttl );
		}
		return $value;
	}
}
