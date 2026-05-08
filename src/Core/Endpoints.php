<?php
namespace ZippyCrm\Core;

defined( 'ABSPATH' ) || exit;

final class Endpoints {

	public const SLUGS = [
		'crm-membership',
		'crm-points',
		'crm-vouchers',
		'crm-notifications',
	];

	public static function register(): void {
		add_action( 'init', [ self::class, 'add_endpoints' ] );
	}

	public static function add_endpoints(): void {
		foreach ( self::SLUGS as $slug ) {
			add_rewrite_endpoint( $slug, EP_ROOT | EP_PAGES );
		}

		// Auto-flush when the endpoint list changes — saves a manual `wp rewrite flush`
		// after every deploy that adds or renames an endpoint.
		$current   = md5( implode( '|', self::SLUGS ) );
		$installed = get_option( 'zippy_crm_endpoints_hash' );
		if ( $current !== $installed ) {
			flush_rewrite_rules( false );
			update_option( 'zippy_crm_endpoints_hash', $current, false );
		}
	}
}
