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

use ZippyCrm\Controllers\Rest\AuditController;
use ZippyCrm\Controllers\Rest\CatalogController;
use ZippyCrm\Controllers\Rest\MembershipController;
use ZippyCrm\Controllers\Rest\NotificationsController;
use ZippyCrm\Controllers\Rest\OnboardingController;
use ZippyCrm\Controllers\Rest\PointsController;
use ZippyCrm\Controllers\Rest\ReportsController;
use ZippyCrm\Controllers\Rest\SettingsController;
use ZippyCrm\Controllers\Rest\TiersController;
use ZippyCrm\Controllers\Rest\UsersController;
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
	// New v1.8.0 cart-tender flow. The customer applies points to their cart;
	// the actual debit happens when the order completes (PointsTender::settle_for_order).
	[
		'method'  => 'GET',
		'path'    => '/points/applicable',
		'handler' => [ PointsController::class, 'applicable' ],
		'auth'    => 'user',
	],
	[
		'method'  => 'POST',
		'path'    => '/points/apply',
		'handler' => [ PointsController::class, 'apply' ],
		'auth'    => 'user',
		'args'    => [
			'points' => [ 'type' => 'integer', 'required' => true, 'minimum' => 0 ],
		],
	],
	[
		'method'  => 'DELETE',
		'path'    => '/points/apply',
		'handler' => [ PointsController::class, 'clear_apply' ],
		'auth'    => 'user',
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
		'method'  => 'GET',
		'path'    => '/vouchers/claims/history',
		'handler' => [ VouchersController::class, 'list_my_claims_history' ],
		'auth'    => 'user',
	],
	[
		'method'  => 'POST',
		'path'    => '/vouchers/(?P<id>\d+)/claim',
		'handler' => [ VouchersController::class, 'claim' ],
		'auth'    => 'user',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],

	// -------- Voucher checkout tray (v1.14.0) --------
	[
		'method'  => 'GET',
		'path'    => '/vouchers/checkout-tray',
		'handler' => [ VouchersController::class, 'checkout_tray' ],
		'auth'    => 'user',
	],
	[
		'method'  => 'POST',
		'path'    => '/vouchers/(?P<id>\d+)/apply',
		'handler' => [ VouchersController::class, 'apply_to_cart' ],
		'auth'    => 'user',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'POST',
		'path'    => '/vouchers/(?P<id>\d+)/claim-and-apply',
		'handler' => [ VouchersController::class, 'claim_and_apply' ],
		'auth'    => 'user',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'DELETE',
		'path'    => '/vouchers/(?P<id>\d+)/apply',
		'handler' => [ VouchersController::class, 'remove_from_cart' ],
		'auth'    => 'user',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],

	// -------- Vouchers (admin) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/vouchers',
		'handler' => [ VouchersController::class, 'admin_list' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'status'   => [ 'type' => 'string',  'default' => '' ],
			'search'   => [ 'type' => 'string',  'default' => '' ],
			'page'     => [ 'type' => 'integer', 'default' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 20 ],
		],
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
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'DELETE',
		'path'    => '/admin/vouchers/(?P<id>\d+)',
		'handler' => [ VouchersController::class, 'admin_delete' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/vouchers/(?P<id>\d+)/publish',
		'handler' => [ VouchersController::class, 'admin_publish' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/vouchers/(?P<id>\d+)/pause',
		'handler' => [ VouchersController::class, 'admin_pause' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/vouchers/(?P<id>\d+)/resume',
		'handler' => [ VouchersController::class, 'admin_resume' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/vouchers/(?P<id>\d+)/duplicate',
		'handler' => [ VouchersController::class, 'admin_duplicate' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/vouchers/(?P<id>\d+)/claims',
		'handler' => [ VouchersController::class, 'admin_list_claims' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/vouchers/(?P<id>\d+)/codes',
		'handler' => [ VouchersController::class, 'admin_list_codes' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'id'       => [ 'type' => 'integer', 'required' => true ],
			'status'   => [ 'type' => 'string',  'default'  => '' ],
			'page'     => [ 'type' => 'integer', 'default'  => 1 ],
			'per_page' => [ 'type' => 'integer', 'default'  => 50 ],
		],
	],

	// -------- Reports (admin) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/reports/members-per-day',
		'handler' => [ ReportsController::class, 'members_per_day' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'from' => [ 'type' => 'string', 'default' => '' ],
			'to'   => [ 'type' => 'string', 'default' => '' ],
		],
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/reports/points-activity',
		'handler' => [ ReportsController::class, 'points_activity_per_day' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'from' => [ 'type' => 'string', 'default' => '' ],
			'to'   => [ 'type' => 'string', 'default' => '' ],
		],
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/reports/voucher-claims',
		'handler' => [ ReportsController::class, 'voucher_claims_per_day' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'from' => [ 'type' => 'string', 'default' => '' ],
			'to'   => [ 'type' => 'string', 'default' => '' ],
		],
	],

	// -------- Points (admin) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/points/summary',
		'handler' => [ PointsController::class, 'admin_summary' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/points/ledger',
		'handler' => [ PointsController::class, 'admin_ledger' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'type'     => [ 'type' => 'string',  'default' => '' ],
			'page'     => [ 'type' => 'integer', 'default' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 20 ],
		],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/points/recalculate-all',
		'handler' => [ PointsController::class, 'admin_recalculate_all' ],
		'auth'    => 'manage_woocommerce',
	],

	// -------- Users (admin) — every non-admin WP user, with CRM coverage --------
	[
		'method'  => 'GET',
		'path'    => '/admin/users',
		'handler' => [ UsersController::class, 'admin_list' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'search'         => [ 'type' => 'string',  'default' => '' ],
			'has_membership' => [ 'type' => 'string',  'default' => '' ],
			'page'           => [ 'type' => 'integer', 'default' => 1 ],
			'per_page'       => [ 'type' => 'integer', 'default' => 25 ],
		],
	],

	// -------- Members (admin) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/members',
		'handler' => [ MembershipController::class, 'admin_list' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'level'    => [ 'type' => 'string',  'default' => '' ],
			'status'   => [ 'type' => 'string',  'default' => '' ],
			'search'   => [ 'type' => 'string',  'default' => '' ],
			'page'     => [ 'type' => 'integer', 'default' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 20 ],
		],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/members/enroll',
		'handler' => [ MembershipController::class, 'admin_enroll' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'user_id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/members/(?P<user_id>\d+)',
		'handler' => [ MembershipController::class, 'admin_get' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'user_id' => [ 'type' => 'integer', 'required' => true ] ],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/members/(?P<user_id>\d+)/level',
		'handler' => [ MembershipController::class, 'admin_set_level' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'user_id' => [ 'type' => 'integer', 'required' => true ],
			'level'   => [ 'type' => 'string',  'required' => true ],
		],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/members/(?P<user_id>\d+)/status',
		'handler' => [ MembershipController::class, 'admin_set_status' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'user_id' => [ 'type' => 'integer', 'required' => true ],
			'status'  => [ 'type' => 'string',  'required' => true ],
		],
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/members/(?P<user_id>\d+)/points',
		'handler' => [ PointsController::class, 'admin_adjust' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'user_id' => [ 'type' => 'integer', 'required' => true ],
			'delta'   => [ 'type' => 'integer', 'required' => true ],
			'reason'  => [ 'type' => 'string',  'required' => true ],
		],
	],

	// -------- Tiers (public read) --------
	[
		'method'  => 'GET',
		'path'    => '/tiers',
		'handler' => [ TiersController::class, 'list_public' ],
		'auth'    => 'user',
		'args'    => [
			'include_admin_only' => [ 'type' => 'boolean', 'default' => false ],
		],
	],

	// -------- Tiers (admin CRUD) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/tiers',
		'handler' => [ TiersController::class, 'admin_list' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/tiers',
		'handler' => [ TiersController::class, 'admin_create' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'PUT',
		'path'    => '/admin/tiers/(?P<slug>[a-z0-9_-]+)',
		'handler' => [ TiersController::class, 'admin_update' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'slug' => [ 'type' => 'string', 'required' => true ] ],
	],
	[
		'method'  => 'DELETE',
		'path'    => '/admin/tiers/(?P<slug>[a-z0-9_-]+)',
		'handler' => [ TiersController::class, 'admin_delete' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [ 'slug' => [ 'type' => 'string', 'required' => true ] ],
	],

	// -------- Site-wide Settings (admin) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/settings/points',
		'handler' => [ SettingsController::class, 'get_points' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'PUT',
		'path'    => '/admin/settings/points',
		'handler' => [ SettingsController::class, 'update_points' ],
		'auth'    => 'manage_woocommerce',
	],

	// -------- Onboarding state (per-admin first-run guide) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/onboarding/state',
		'handler' => [ OnboardingController::class, 'get_state' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'PUT',
		'path'    => '/admin/onboarding/state',
		'handler' => [ OnboardingController::class, 'update_state' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/onboarding/prereqs',
		'handler' => [ OnboardingController::class, 'check_prereqs' ],
		'auth'    => 'manage_woocommerce',
	],
	[
		'method'  => 'POST',
		'path'    => '/admin/onboarding/test-email',
		'handler' => [ OnboardingController::class, 'send_test_email' ],
		'auth'    => 'manage_woocommerce',
	],

	// -------- Catalog lookup (admin, for voucher product/category picker) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/catalog/products',
		'handler' => [ CatalogController::class, 'search_products' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'search'   => [ 'type' => 'string',  'default' => '' ],
			'ids'      => [ 'type' => 'string',  'default' => '' ],
			'per_page' => [ 'type' => 'integer', 'default' => 20 ],
		],
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/catalog/categories',
		'handler' => [ CatalogController::class, 'search_categories' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'search'   => [ 'type' => 'string',  'default' => '' ],
			'ids'      => [ 'type' => 'string',  'default' => '' ],
			'per_page' => [ 'type' => 'integer', 'default' => 20 ],
		],
	],
	[
		'method'  => 'GET',
		'path'    => '/admin/catalog/customers',
		'handler' => [ CatalogController::class, 'search_customers' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'search'   => [ 'type' => 'string',  'default' => '' ],
			'ids'      => [ 'type' => 'string',  'default' => '' ],
			'per_page' => [ 'type' => 'integer', 'default' => 20 ],
		],
	],

	// -------- Audit log (admin) --------
	[
		'method'  => 'GET',
		'path'    => '/admin/audit',
		'handler' => [ AuditController::class, 'list' ],
		'auth'    => 'manage_woocommerce',
		'args'    => [
			'event'     => [ 'type' => 'string',  'default' => '' ],
			'actor_id'  => [ 'type' => 'integer', 'default' => 0 ],
			'target_id' => [ 'type' => 'integer', 'default' => 0 ],
			'from'      => [ 'type' => 'string',  'default' => '' ],
			'to'        => [ 'type' => 'string',  'default' => '' ],
			'page'      => [ 'type' => 'integer', 'default' => 1 ],
			'per_page'  => [ 'type' => 'integer', 'default' => 25 ],
		],
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
