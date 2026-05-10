<?php
/**
 * Smoke test for admin Members REST endpoints.
 * Run inside the wordpress container:
 *   wp eval-file /tmp/zc-test-admin-members.php
 */

$admin = get_user_by( 'login', 'admin' );
if ( ! $admin ) {
	$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
	$admin  = $admins[0] ?? null;
}
if ( ! $admin ) { echo "FAIL: no administrator user found\n"; exit( 1 ); }
wp_set_current_user( $admin->ID );
echo "Acting as user #{$admin->ID} ({$admin->user_login}) — caps: "
	. ( current_user_can( 'manage_woocommerce' ) ? 'OK' : 'NO manage_woocommerce' ) . "\n\n";

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
	if ( strlen( $short ) > 700 ) {
		$short = substr( $short, 0, 700 ) . "\n... (truncated)";
	}
	echo "[{$status}] {$label}\n{$short}\n\n";
}

// Make a victim user we can poke at.
$victim_login = 'zc-test-' . wp_generate_password( 4, false );
$victim_id    = wp_create_user( $victim_login, 'pw-' . wp_generate_password( 12, false ), $victim_login . '@example.com' );
if ( is_wp_error( $victim_id ) ) {
	echo "FAIL: cannot create victim: " . $victim_id->get_error_message() . "\n"; exit( 1 );
}
echo "Victim user #{$victim_id} ({$victim_login})\n";

// Trigger the membership seed (the customer-side flow runs on woocommerce_created_customer,
// but wp_create_user doesn't fire that — call the service directly to seed).
\ZippyCrm\Services\MembershipService::on_customer_created( $victim_id );
echo "Seeded membership for #{$victim_id}\n\n";

echo "=== 1. List (initial) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/members' );
pp( 'GET /admin/members', $s, [
	'total'  => $d['total']  ?? null,
	'counts' => $d['counts'] ?? null,
	'first_codes' => array_slice( array_column( $d['items'] ?? [], 'user_login' ), 0, 5 ),
] );

echo "=== 2. Get single (victim) ===\n";
[ $s, $d ] = hit( 'GET', "/admin/members/{$victim_id}" );
pp( 'GET single', $s, $d );

echo "=== 3. Filter list (level=free) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/members', [ 'level' => 'free' ] );
pp( 'GET filtered', $s, [ 'total' => $d['total'] ?? null, 'count' => count( $d['items'] ?? [] ) ] );

echo "=== 4. Search (matches victim) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/members', [ 'search' => $victim_login ] );
pp( 'GET search', $s, [ 'total' => $d['total'] ?? null, 'logins' => array_column( $d['items'] ?? [], 'user_login' ) ] );

echo "=== 5. Bad level filter (400) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/members', [ 'level' => 'platinum' ] );
pp( 'GET bad filter', $s, $d );

echo "=== 6. Set level → silver ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/level", [ 'level' => 'silver' ] );
pp( 'POST set level=silver', $s, [ 'level' => $d['level'] ?? null, 'multiplier' => $d['multiplier'] ?? null ] );

echo "=== 7. Set level → vip (admin can do this) ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/level", [ 'level' => 'vip' ] );
pp( 'POST set level=vip', $s, [ 'level' => $d['level'] ?? null, 'multiplier' => $d['multiplier'] ?? null ] );

echo "=== 8. Bad level (400) ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/level", [ 'level' => 'platinum' ] );
pp( 'POST bad level', $s, $d );

echo "=== 9. Suspend ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/status", [ 'status' => 'suspended' ] );
pp( 'POST status=suspended', $s, [ 'status' => $d['status'] ?? null ] );

echo "=== 10. Activate ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/status", [ 'status' => 'active' ] );
pp( 'POST status=active', $s, [ 'status' => $d['status'] ?? null ] );

echo "=== 11. Adjust points +500 ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/points", [
	'delta' => 500, 'reason' => 'Goodwill credit',
] );
pp( 'POST adjust +500', $s, $d );

echo "=== 12. Adjust points -200 ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/points", [
	'delta' => -200, 'reason' => 'Refund correction',
] );
pp( 'POST adjust -200', $s, $d );

echo "=== 13. Adjust would-go-negative (refused) ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/points", [
	'delta' => -10000, 'reason' => 'Drain',
] );
pp( 'POST adjust huge debit', $s, $d );

echo "=== 14. Adjust missing reason (refused) ===\n";
[ $s, $d ] = hit( 'POST', "/admin/members/{$victim_id}/points", [
	'delta' => 100, 'reason' => '',
] );
pp( 'POST adjust no reason', $s, $d );

echo "=== 15. Verify ledger ↔ summary equality ===\n";
\ZippyCrm\Services\PointsEngine::recalculate_balance( $victim_id );
$summary_after = \ZippyCrm\Services\PointsEngine::get_summary( $victim_id );
echo "Recalc result: " . json_encode( $summary_after ) . "\n";
echo ( $summary_after['balance'] === 300 ? 'OK — balance = 300 (500-200)' : 'FAIL — expected 300, got ' . $summary_after['balance'] ) . "\n\n";

echo "=== 16. Cleanup ===\n";
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $victim_id );
echo "Deleted user #{$victim_id}\n\nDone.\n";
