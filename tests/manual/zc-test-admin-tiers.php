<?php
/**
 * Smoke test for admin Tiers REST endpoints AND the
 * MembershipController slug-filter regression (newly-added tiers must
 * be accepted by the Members admin level filter).
 *
 * Run inside the wordpress container:
 *   wp eval-file /tmp/zc-test-admin-tiers.php
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
	if ( strlen( $short ) > 700 ) {
		$short = substr( $short, 0, 700 ) . "\n... (truncated)";
	}
	echo "[{$status}] {$label}\n{$short}\n\n";
}

$test_slug = 'platinum-' . wp_generate_password( 4, false, false );

echo "=== 1. Public list (filtered to non-admin) ===\n";
[ $s, $d ] = hit( 'GET', '/tiers' );
pp( 'GET /tiers', $s, [
	'count' => count( $d['items'] ?? [] ),
	'slugs' => array_column( $d['items'] ?? [], 'slug' ),
] );

echo "=== 2. Public list (include admin-only) ===\n";
[ $s, $d ] = hit( 'GET', '/tiers', [ 'include_admin_only' => true ] );
pp( 'GET /tiers?include_admin_only=true', $s, [
	'count' => count( $d['items'] ?? [] ),
	'slugs' => array_column( $d['items'] ?? [], 'slug' ),
] );

echo "=== 3. Admin list ===\n";
[ $s, $d ] = hit( 'GET', '/admin/tiers' );
pp( 'GET /admin/tiers', $s, [
	'count' => count( $d['items'] ?? [] ),
	'first' => $d['items'][0] ?? null,
] );

echo "=== 4. Create tier ===\n";
[ $s, $d ] = hit( 'POST', '/admin/tiers', [
	'slug'             => $test_slug,
	'label'            => 'Platinum (test)',
	'multiplier'       => 2.5,
	'threshold_orders' => 30,
	'threshold_spend'  => 5000,
	'is_admin_only'    => 0,
	'sort_order'       => 5,
] );
pp( 'POST /admin/tiers (create)', $s, $d );

echo "=== 5. Bad slug (400) ===\n";
[ $s, $d ] = hit( 'POST', '/admin/tiers', [
	'slug' => 'BAD SLUG!',
	'label' => 'Bad',
	'multiplier' => 1,
] );
pp( 'POST bad slug', $s, $d );

echo "=== 6. Duplicate slug (409) ===\n";
[ $s, $d ] = hit( 'POST', '/admin/tiers', [
	'slug' => $test_slug,
	'label' => 'Dup',
	'multiplier' => 1,
] );
pp( 'POST duplicate', $s, $d );

echo "=== 7. Update tier ===\n";
[ $s, $d ] = hit( 'PUT', "/admin/tiers/{$test_slug}", [
	'label' => 'Platinum (updated)',
	'multiplier' => 3.0,
] );
pp( 'PUT update', $s, $d );

echo "=== 8. Members admin filter accepts new slug ===\n";
[ $s, $d ] = hit( 'GET', '/admin/members', [ 'level' => $test_slug ] );
pp( 'GET /admin/members?level=' . $test_slug, $s, [
	'total'  => $d['total']  ?? null,
	'counts' => $d['counts'] ?? null,
] );

echo "=== 9. Members admin filter rejects truly unknown slug ===\n";
[ $s, $d ] = hit( 'GET', '/admin/members', [ 'level' => 'definitely-not-a-tier' ] );
pp( 'GET /admin/members?level=junk', $s, $d );

echo "=== 10. Delete tier (no members → succeeds) ===\n";
[ $s, $d ] = hit( 'DELETE', "/admin/tiers/{$test_slug}" );
pp( 'DELETE empty tier', $s, $d );

echo "=== 11. Delete a tier that has members (refused 409) ===\n";
// Find a tier with members to verify the refusal path.
[ $s, $d ] = hit( 'GET', '/admin/tiers' );
$with_members = null;
foreach ( $d['items'] ?? [] as $row ) {
	if ( ( $row['member_count'] ?? 0 ) > 0 ) { $with_members = $row['slug']; break; }
}
if ( $with_members ) {
	[ $s, $d ] = hit( 'DELETE', "/admin/tiers/{$with_members}" );
	pp( "DELETE /admin/tiers/{$with_members}", $s, $d );
} else {
	echo "(skipped — no tier has members in this dataset)\n\n";
}

echo "=== 12. Delete the only non-admin tier (refused 409) ===\n";
// Hard to test without nuking real data; just confirm the path exists by
// reading code — skipping live delete.
echo "(skipped — verified by code read; refusal path returns 'tier_last_default')\n\n";

echo "Done.\n";
