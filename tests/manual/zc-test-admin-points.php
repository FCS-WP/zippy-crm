<?php
/**
 * Smoke test for admin Points REST endpoints.
 * Run inside the wordpress container:
 *   wp eval-file /tmp/zc-test-admin-points.php
 */

$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
$admin  = $admins[0] ?? null;
if ( ! $admin ) { echo "FAIL: no administrator user found\n"; exit( 1 ); }
wp_set_current_user( $admin->ID );
echo "Acting as user #{$admin->ID} ({$admin->user_login})\n\n";

function hit( string $method, string $path, array $body = null ): array {
	$r = new WP_REST_Request( $method, '/zippy-crm/v1' . $path );
	if ( $body !== null ) {
		foreach ( $body as $k => $v ) { $r->set_param( $k, $v ); }
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

echo "=== 1. System summary ===\n";
[ $s, $d ] = hit( 'GET', '/admin/points/summary' );
pp( 'GET /admin/points/summary', $s, $d );

global $wpdb;
$direct = $wpdb->get_row(
	"SELECT COALESCE(SUM(total_earned),0) AS issued, COALESCE(SUM(total_redeemed),0) AS redeemed,
	        COALESCE(SUM(balance),0) AS outstanding, COUNT(*) AS members
	   FROM {$wpdb->prefix}crm_points_summary",
	ARRAY_A
);
echo "Direct SUM: " . json_encode( $direct ) . "\n";
$match = ( (int)$direct['issued'] === (int)$d['issued']
	&& (int)$direct['redeemed'] === (int)$d['redeemed']
	&& (int)$direct['outstanding'] === (int)$d['outstanding']
	&& (int)$direct['members'] === (int)$d['members'] );
echo ( $match ? 'OK — REST matches direct SUM' : 'FAIL — REST drifted from direct SUM' ) . "\n\n";

echo "=== 2. Recent ledger (no filter, page 1) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/points/ledger', [ 'per_page' => 5 ] );
pp( 'GET /admin/points/ledger', $s, [
	'total'       => $d['total']       ?? null,
	'page'        => $d['page']        ?? null,
	'per_page'    => $d['per_page']    ?? null,
	'total_pages' => $d['total_pages'] ?? null,
	'first_types' => array_column( $d['items'] ?? [], 'type' ),
	'first_logins' => array_column( $d['items'] ?? [], 'user_login' ),
] );

echo "=== 3. Recent ledger (type=adjust) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/points/ledger', [ 'type' => 'adjust', 'per_page' => 5 ] );
pp( 'GET filtered adjust', $s, [
	'total' => $d['total'] ?? null,
	'count' => count( $d['items'] ?? [] ),
	'types' => array_column( $d['items'] ?? [], 'type' ),
] );

echo "=== 4. Recent ledger (bad type → 400) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/points/ledger', [ 'type' => 'mystery' ] );
pp( 'GET bad filter', $s, $d );

echo "=== 5. Pending_redeem rows are excluded ===\n";
[ $s, $d ] = hit( 'GET', '/admin/points/ledger', [ 'per_page' => 100 ] );
$has_pending = false;
foreach ( $d['items'] ?? [] as $row ) {
	if ( $row['type'] === 'pending_redeem' ) { $has_pending = true; break; }
}
echo ( $has_pending ? 'FAIL — pending_redeem leaked through' : 'OK — pending_redeem excluded' ) . "\n\n";

echo "=== 6. Recalculate-all on a clean dataset (no drift expected) ===\n";
[ $s, $d ] = hit( 'POST', '/admin/points/recalculate-all' );
pp( 'POST recalculate-all (clean)', $s, $d );

echo "=== 7. Inject synthetic drift, recalc again ===\n";
$victim_id = wp_create_user( 'zc-drift-' . wp_generate_password(4, false), 'pw-' . wp_generate_password(12, false), 'drift-' . wp_generate_password(4, false) . '@example.com' );
\ZippyCrm\Services\MembershipService::on_customer_created( $victim_id );
\ZippyCrm\Services\PointsAdmin::adjust( $victim_id, 100, 'seed for drift test', $admin->ID );

// Now corrupt the summary directly so balance != ledger sum.
$wpdb->update( "{$wpdb->prefix}crm_points_summary", [ 'balance' => 9999 ], [ 'user_id' => $victim_id ] );
$wp_object_cache_flush_check = function_exists('wp_cache_flush_group') ? 'group-flush available' : 'no flush method';
\ZippyCrm\Services\PointsEngine::invalidate( $victim_id );
echo "Corrupted balance for #{$victim_id} to 9999 (real should be 100). Cache mode: {$wp_object_cache_flush_check}\n";

[ $s, $d ] = hit( 'POST', '/admin/points/recalculate-all' );
pp( 'POST recalculate-all (with drift)', $s, $d );

$after = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$wpdb->prefix}crm_points_summary WHERE user_id=%d", $victim_id ) );
echo "Balance after recalc: {$after} (expected 100)\n";
echo ( (int)$after === 100 ? 'OK — drift corrected' : 'FAIL — drift not corrected' ) . "\n\n";

echo "=== 8. Cleanup ===\n";
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $victim_id );
echo "Deleted user #{$victim_id}\n\nDone.\n";
