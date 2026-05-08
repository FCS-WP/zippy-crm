<?php
namespace ZippyCrm\Support;

defined( 'ABSPATH' ) || exit;

final class DateTimeHelper {

	/** Current MySQL DATETIME in UTC — for storage. */
	public static function now_mysql(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/** Convert a MySQL DATETIME (UTC, from our tables) to ISO-8601 with `Z`. */
	public static function mysql_to_iso( ?string $mysql ): ?string {
		if ( ! $mysql ) {
			return null;
		}
		return ( new \DateTimeImmutable( $mysql, new \DateTimeZone( 'UTC' ) ) )
			->format( 'Y-m-d\TH:i:s\Z' );
	}
}
