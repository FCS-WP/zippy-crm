<?php
/**
 * REST route manifest. Loaded once by RouteRegistrar on `rest_api_init`.
 *
 * Each entry:
 *   method  — HTTP verb(s); single string or array
 *   path    — path under /wp-json/zippy-crm/v1/  (must start with `/`)
 *   handler — [ Controller::class, 'method' ]
 *   auth    — 'public' | 'user' | 'manage_woocommerce' | 'manage_options' | callable
 *   args    — optional WP REST args schema
 *
 * Routes are flat by design — easy to grep, easy to scan the whole API surface.
 *
 * @see ZippyCrm\Core\RouteRegistrar
 * @see docs/FEATURE_SPEC.md §8
 */

defined( 'ABSPATH' ) || exit;

use ZippyCrm\Controllers\Rest\MembershipController;
use ZippyCrm\Controllers\Rest\NotificationsController;
use ZippyCrm\Controllers\Rest\PointsController;
use ZippyCrm\Controllers\Rest\VouchersController;

return [

	// -------- Membership --------
	[
		'method'  => 'GET',
		'path'    => '/membership/me',
		'handler' => [ MembershipController::class, 'get_me' ],
		'auth'    => 'user',
	],

	// -------- Points --------
	[
		'method'  => 'GET',
		'path'    => '/points/me',
		'handler' => [ PointsController::class, 'get_summary' ],
		'auth'    => 'user',
	],
	[
		'method'  => 'GET',
		'path'    => '/points/ledger',
		'handler' => [ PointsController::class, 'get_ledger' ],
		'auth'    => 'user',
		'args'    => [
			'page'     => [ 'type' => 'integer', 'default' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 10 ],
		],
	],
	[
		'method'  => 'POST',
		'path'    => '/points/redeem',
		'handler' => [ PointsController::class, 'redeem' ],
		'auth'    => 'user',
		'args'    => [
			'points' => [ 'type' => 'integer', 'required' => true, 'minimum' => ZIPPY_CRM_MIN_REDEMPTION ],
		],
	],

	// -------- Vouchers (customer) --------
	[
		'method'  => 'GET',
		'path'    => '/vouchers',
		'handler' => [ VouchersController::class, 'list_available' ],
		'auth'    => 'user',
	],
	[
		'method'  => 'GET',
		'path'    => '/vouchers/claims',
		'handler' => [ VouchersController::class, 'list_my_claims' ],
		'auth'    => 'user',
	],
	[
		'method'  => 'POST',
		'path'    => '/vouchers/(?P<id>\d+)/claim',
		'handler' => [ VouchersController::class, 'claim' ],
		'auth'    => 'user',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],

	// -------- Vouchers (admin) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/vouchers',
		'handler' => [ VouchersController::class, 'admin_list' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/vouchers',
		'handler' => [ VouchersController::class, 'admin_create' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'PUT',
		'path'    => '/admin/vouchers/(?P<id>\d+)',
		'handler' => [ VouchersController::class, 'admin_update' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'DELETE',
		'path'    => '/admin/vouchers/(?P<id>\d+)',
		'handler' => [ VouchersController::class, 'admin_delete' ],
		'auth'    => 'manage_woocommerce',
	],

	// -------- Notifications --------
	[
		'method'  => 'GET',
		'path'    => '/notifications/preferences',
		'handler' => [ NotificationsController::class, 'get_prefs' ],
		'auth'    => 'user',
	],
	[
		'method'  => 'PUT',
		'path'    => '/notifications/preferences',
		'handler' => [ NotificationsController::class, 'update_prefs' ],
		'auth'    => 'user',
		'args'    => [
			'subscribe_vouchers' => [ 'type' => 'boolean', 'required' => true ],
			'subscribe_points'   => [ 'type' => 'boolean', 'required' => true ],
		],
	],
];
