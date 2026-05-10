<?php
/**
 * Smoke test for admin Reports REST endpoints.
 *   wp eval-file /tmp/zc-test-admin-reports.php
 */

$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
$admin  = $admins[0] ?? null;
if ( ! $admin ) { echo "FAIL: no admin user\n"; exit( 1 ); }
wp_set_current_user( $admin->ID );
echo "Acting as #{$admin->ID} ({$admin->user_login})\n\n";

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
	if ( strlen( $short ) > 800 ) {
		$short = substr( $short, 0, 800 ) . "\n... (truncated)";
	}
	echo "[{$status}] {$label}\n{$short}\n\n";
}

echo "=== 1. Members default range (last 30 days) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/reports/members-per-day' );
$series = $d['series'] ?? [];
echo "[{$s}] from={$d['from']} to={$d['to']} days={$d['days']} series_len=" . count( $series ) . "\n";
echo "First row: " . json_encode( $series[0] ?? null ) . "\n";
echo "Last row:  " . json_encode( $series[ count( $series ) - 1 ] ?? null ) . "\n";
echo ( count( $series ) === 30 ? 'OK — exactly 30 days' : 'FAIL — expected 30 rows, got ' . count( $series ) ) . "\n\n";

echo "=== 2. Points activity (custom 7d window) ===\n";
$to   = gmdate( 'Y-m-d' );
$from = gmdate( 'Y-m-d', strtotime( '-6 days' ) );
[ $s, $d ] = hit( 'GET', '/admin/reports/points-activity', [ 'from' => $from, 'to' => $to ] );
$series = $d['series'] ?? [];
echo "[{$s}] from={$d['from']} to={$d['to']} series_len=" . count( $series ) . "\n";
$total_earned = array_sum( array_column( $series, 'earned' ) );
$total_redeemed = array_sum( array_column( $series, 'redeemed' ) );
$total_adjusted = array_sum( array_column( $series, 'adjusted' ) );
echo "Totals — earned={$total_earned} redeemed={$total_redeemed} adjusted={$total_adjusted}\n";
echo ( count( $series ) === 7 ? 'OK — exactly 7 days' : 'FAIL' ) . "\n\n";

echo "=== 3. Voucher claims default range ===\n";
[ $s, $d ] = hit( 'GET', '/admin/reports/voucher-claims' );
$series = $d['series'] ?? [];
$total_claimed = array_sum( array_column( $series, 'claimed' ) );
echo "[{$s}] series_len=" . count( $series ) . " total_claimed={$total_claimed}\n";
echo ( count( $series ) === 30 ? 'OK — 30 days' : 'FAIL' ) . "\n\n";

echo "=== 4. Bad date format (400) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/reports/members-per-day', [ 'from' => 'yesterday' ] );
pp( 'GET bad from', $s, $d );

echo "=== 5. Inverted range (400) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/reports/members-per-day', [ 'from' => '2026-05-09', 'to' => '2026-05-01' ] );
pp( 'GET inverted', $s, $d );

echo "=== 6. Range too wide (400) ===\n";
[ $s, $d ] = hit( 'GET', '/admin/reports/members-per-day', [ 'from' => '2024-01-01', 'to' => '2026-12-31' ] );
pp( 'GET 1000+ day range', $s, $d );

echo "=== 7. Zero-fill verification (single empty day) ===\n";
$today = gmdate( 'Y-m-d' );
[ $s, $d ] = hit( 'GET', '/admin/reports/members-per-day', [ 'from' => $today, 'to' => $today ] );
$series = $d['series'] ?? [];
echo "[{$s}] series_len=" . count( $series ) . "\n";
echo "Row: " . json_encode( $series[0] ?? null ) . "\n";
echo ( count( $series ) === 1 && isset( $series[0]['day'], $series[0]['total'] ) ? 'OK — 1 row, has day+total' : 'FAIL' ) . "\n\n";

echo "=== 8. Permission check (anonymous → 401) ===\n";
wp_set_current_user( 0 );
[ $s, $d ] = hit( 'GET', '/admin/reports/members-per-day' );
echo "[{$s}] code=" . ( $d['code'] ?? '?' ) . " (expected 401)\n";
echo ( $s === 401 ? 'OK — blocked anonymous' : 'FAIL — got ' . $s ) . "\n\n";

echo "Done.\n";
