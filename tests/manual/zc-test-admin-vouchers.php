<?php
/**
 * Smoke test for admin Vouchers REST endpoints.
 * Run inside the wordpress container:
 *   wp eval-file /tmp/zc-test-admin-vouchers.php
 */

// Need an admin user with manage_woocommerce.
$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
	$admin  = $admins[0] ?? null;
}
if ( ! $admin ) {
	echo "FAIL: no administrator user found\n";
	exit( 1 );
}
wp_set_current_user( $admin->ID );
echo "Acting as user #{$admin->ID} ({$admin->user_login}) — caps: " . ( current_user_can( 'manage_woocommerce' ) ? 'OK' : 'NO manage_woocommerce' ) . "\n\n";

// Helper: hit a route and return [status, body].
function hit( string $method, string $path, array $body = null ): array {
	$r = new WP_REST_Request( $method, '/zippy-crm/v1' . $path );
	if ( $body !== null ) {
		foreach ( $body as $k => $v ) {
			$r->set_param( $k, $v );
		}
	}
	$resp = rest_do_request( $r );
	return [ $resp->get_status(), $resp->get_data() ];
}

function pp( $label, $status, $data ): void {
	$short = is_array( $data )
		? json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT )
		: (string) $data;
	if ( strlen( $short ) > 600 ) {
		$short = substr( $short, 0, 600 ) . "\n... (truncated)";
	}
	echo "[{$status}] {$label}\n{$short}\n\n";
}

$test_code = 'TEST-ADMIN-' . wp_generate_password( 6, false );

echo "=== 1. List (initial) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/vouchers' );
pp( 'GET /admin/vouchers', $s, [ 'total' => $d['total'] ?? null, 'counts' => $d['counts'] ?? null, 'first' => array_slice( $d['items'] ?? [], 0, 1 ) ] );

echo "=== 2. Create (valid) ===\n";
[ $s, $d ] = hit( 'POST', '/admin/vouchers', [
	'code'             => $test_code,
	'title'            => 'Test Admin Voucher',
	'description'      => 'Created by smoke test',
	'discount_type'    => 'percent',
	'discount_value'   => 15,
	'min_order_amount' => 25,
	'max_uses'         => 100,
] );
pp( 'POST /admin/vouchers (create draft)', $s, $d );
$created_id = $d['id'] ?? 0;
if ( ! $created_id ) {
	echo "FAIL: no id returned, aborting\n";
	exit( 1 );
}

echo "=== 3. Create (duplicate code → 409) ===\n";
[ $s, $d ] = hit( 'POST', '/admin/vouchers', [
	'code'           => $test_code,
	'title'          => 'Dup',
	'discount_type'  => 'percent',
	'discount_value' => 5,
] );
pp( 'POST /admin/vouchers (duplicate)', $s, $d );

echo "=== 4. Create (bad payload → 400) ===\n";
[ $s, $d ] = hit( 'POST', '/admin/vouchers', [
	'code'           => 'BAD',
	'title'          => 'No discount type',
	'discount_value' => 10,
] );
pp( 'POST /admin/vouchers (missing discount_type)', $s, $d );

echo "=== 5. Update ===\n";
[ $s, $d ] = hit( 'PUT', "/admin/vouchers/{$created_id}", [
	'title'          => 'Test Admin Voucher (updated)',
	'discount_value' => 20,
] );
pp( 'PUT update', $s, $d );

echo "=== 6. Publish ===\n";
[ $s, $d ] = hit( 'POST', "/admin/vouchers/{$created_id}/publish" );
pp( 'POST publish', $s, $d );
echo "WC coupon for {$test_code}: id=" . wc_get_coupon_id_by_code( $test_code ) . "\n\n";

echo "=== 7. Delete (refused — not draft, has WC coupon) ===\n";
[ $s, $d ] = hit( 'DELETE', "/admin/vouchers/{$created_id}" );
pp( 'DELETE active', $s, $d );

echo "=== 8. Pause ===\n";
[ $s, $d ] = hit( 'POST', "/admin/vouchers/{$created_id}/pause" );
pp( 'POST pause', $s, $d );

echo "=== 9. Resume ===\n";
[ $s, $d ] = hit( 'POST', "/admin/vouchers/{$created_id}/resume" );
pp( 'POST resume', $s, $d );

echo "=== 10. Duplicate ===\n";
[ $s, $d ] = hit( 'POST', "/admin/vouchers/{$created_id}/duplicate" );
pp( 'POST duplicate', $s, $d );
$dup_id = $d['id'] ?? 0;

echo "=== 11. Filter list (status=paused) ===\n";
[ $s, $d ] = hit( 'POST', "/admin/vouchers/{$created_id}/pause" );
[ $s, $d ] = hit( 'GET', '/admin/vouchers', [ 'status' => 'paused' ] );
pp( 'GET filtered paused', $s, [ 'total' => $d['total'] ?? null, 'count' => count( $d['items'] ?? [] ) ] );

echo "=== 12. Search list ===\n";
[ $s, $d ] = hit( 'GET', '/admin/vouchers', [ 'search' => $test_code ] );
pp( 'GET search', $s, [ 'total' => $d['total'] ?? null, 'codes' => array_column( $d['items'] ?? [], 'code' ) ] );

echo "=== 13. List claims (empty) ===\n";
[ $s, $d ] = hit( 'GET', "/admin/vouchers/{$created_id}/claims" );
pp( 'GET claims', $s, $d );

echo "=== 14. Cleanup: delete duplicate (draft, no claims) ===\n";
if ( $dup_id ) {
	[ $s, $d ] = hit( 'DELETE', "/admin/vouchers/{$dup_id}" );
	pp( 'DELETE draft duplicate', $s, $d );
}

echo "=== 15. Cleanup: delete original (force draft first) ===\n";
global $wpdb;
$wpdb->update( $wpdb->prefix . 'crm_vouchers', [ 'status' => 'draft' ], [ 'id' => $created_id ] );
[ $s, $d ] = hit( 'DELETE', "/admin/vouchers/{$created_id}" );
pp( 'DELETE original (forced draft)', $s, $d );

// Also clean up the WC coupon left behind by publish.
$cid = wc_get_coupon_id_by_code( $test_code );
if ( $cid ) {
	wp_delete_post( $cid, true );
	echo "Deleted WC coupon #{$cid} ({$test_code})\n";
}

echo "\nDone.\n";
