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
	}
}
