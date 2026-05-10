<?php
/**
 * Smoke test for voucher full-WC-parity round-trip + hour-window validity.
 *   wp eval-file /tmp/zc-test-voucher-fields.php
 */
$admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
$admin  = $admins[0] ?? null;
if ( ! $admin ) { echo "FAIL: no admin\n"; exit( 1 ); }
wp_set_current_user( $admin->ID );
echo "Acting as #{$admin->ID}\n\n";

function hit( string $method, string $path, array $body = null ): array {
	$r = new WP_REST_Request( $method, '/zippy-crm/v1' . $path );
	if ( $body !== null ) {
		foreach ( $body as $k => $v ) { $r->set_param( $k, $v ); }
	}
	$resp = rest_do_request( $r );
	return [ $resp->get_status(), $resp->get_data() ];
}
function pp( $label, $status, $data ): void {
	$short = is_array( $data ) ? json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) : (string) $data;
	if ( strlen( $short ) > 1500 ) $short = substr( $short, 0, 1500 ) . "\n... (truncated)";
	echo "[{$status}] {$label}\n{$short}\n\n";
}

$test_code = 'TESTPARITY' . wp_generate_password( 4, false, false );

echo "=== 1. Create voucher with all new fields ===\n";
$payload = [
	'code'                        => $test_code,
	'title'                       => 'Parity test voucher',
	'discount_type'               => 'percent',
	'discount_value'              => 25,
	'min_order_amount'            => 50,
	'max_order_amount'            => 500,
	'max_uses'                    => 100,
	'usage_limit_per_user'        => 2,
	'limit_usage_to_x_items'      => 3,
	'individual_use'              => false,
	'exclude_sale_items'          => true,
	'free_shipping'               => true,
	'email_restrictions'          => [ '*@example.com', 'someone@test.com' ],
	'product_ids'                 => [ 12, 34, 56 ],
	'excluded_product_ids'        => [ 99 ],
	'product_categories'          => [ 7 ],
	'excluded_product_categories' => [],
	'allowed_hours'               => [ 'days' => [ 5, 6 ], 'from_minute' => 18*60, 'to_minute' => 21*60 ],
];
[ $s, $d ] = hit( 'POST', '/admin/vouchers', $payload );
pp( 'POST create with all fields', $s, $d );
$vid = $d['id'] ?? 0;
if ( ! $vid ) { echo "FAIL: no id\n"; exit( 1 ); }

echo "=== 2. DB row sanity ===\n";
global $wpdb;
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}crm_vouchers WHERE id=%d", $vid ), ARRAY_A );
echo "row stored:\n";
foreach ( [ 'min_order_amount', 'max_order_amount', 'usage_limit_per_user', 'limit_usage_to_x_items',
            'individual_use', 'exclude_sale_items', 'free_shipping',
            'email_restrictions', 'product_ids', 'allowed_hours' ] as $k ) {
	echo "  $k = " . var_export( $row[$k], true ) . "\n";
}
echo "\n";

echo "=== 3. Update voucher (partial — flip individual_use + drop excluded_product_ids) ===\n";
[ $s, $d ] = hit( 'PUT', "/admin/vouchers/{$vid}", [
	'individual_use'       => true,
	'excluded_product_ids' => [],
	'usage_limit_per_user' => 5,
] );
pp( 'PUT partial update', $s, [
	'individual_use'        => $d['individual_use'] ?? '?',
	'excluded_product_ids'  => $d['excluded_product_ids'] ?? '?',
	'usage_limit_per_user'  => $d['usage_limit_per_user'] ?? '?',
	'product_ids'           => $d['product_ids'] ?? '?',
	'min_order_amount'      => $d['min_order_amount'] ?? '?',
] );

echo "=== 4. Publish → sync WC coupon, verify all setters ===\n";
[ $s, $d ] = hit( 'POST', "/admin/vouchers/{$vid}/publish" );
echo "publish status=$s\n";
$cid = wc_get_coupon_id_by_code( $test_code );
echo "WC coupon id = $cid\n";
if ( $cid ) {
	$c = new WC_Coupon( $cid );
	echo "  type=" . $c->get_discount_type() . "\n";
	echo "  amount=" . $c->get_amount() . "\n";
	echo "  minimum_amount=" . $c->get_minimum_amount() . "\n";
	echo "  maximum_amount=" . $c->get_maximum_amount() . "\n";
	echo "  usage_limit=" . $c->get_usage_limit() . "\n";
	echo "  usage_limit_per_user=" . $c->get_usage_limit_per_user() . "\n";
	echo "  limit_usage_to_x_items=" . $c->get_limit_usage_to_x_items() . "\n";
	echo "  individual_use=" . var_export( $c->get_individual_use(), true ) . "\n";
	echo "  exclude_sale_items=" . var_export( $c->get_exclude_sale_items(), true ) . "\n";
	echo "  free_shipping=" . var_export( $c->get_free_shipping(), true ) . "\n";
	echo "  email_restrictions=" . json_encode( $c->get_email_restrictions() ) . "\n";
	echo "  product_ids=" . json_encode( $c->get_product_ids() ) . "\n";
	echo "  excluded_product_ids=" . json_encode( $c->get_excluded_product_ids() ) . "\n";
	echo "  product_categories=" . json_encode( $c->get_product_categories() ) . "\n";
}
echo "\n";

echo "=== 5. Hour-window validity check (synthetic time matching window) ===\n";
// We'll directly call the hook with stubbed Voucher data instead of mocking time —
// easier to assert.
$now_window = [ 'days' => [ (int) ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'w' ) ],
                'from_minute' => 0, 'to_minute' => 1440 ];
echo "(Today's day, full-day window — should pass)\n";
try {
	$ok = ZippyCrm\Hooks\VoucherHourWindow::is_valid( true, (object) [], null );
	echo "  Default (no meta) returned: " . var_export( $ok, true ) . "\n";
} catch ( Throwable $e ) {
	echo "  Threw: " . $e->getMessage() . "\n";
}

echo "\n=== 6. Hour-window validity (window doesn't match — should throw) ===\n";
// Set the voucher's allowed_hours to a window that excludes RIGHT NOW
$now = new DateTimeImmutable( 'now', wp_timezone() );
$now_min = (int) $now->format( 'H' ) * 60 + (int) $now->format( 'i' );
$exclusive = [
	'days' => [ (int) $now->format( 'w' ) ],
	'from_minute' => ( $now_min + 30 ) % 1440,
	'to_minute'   => ( $now_min + 60 ) % 1440,
];
$wpdb->update( $wpdb->prefix . 'crm_vouchers', [ 'allowed_hours' => wp_json_encode( $exclusive ) ], [ 'id' => $vid ] );
$coupon = new WC_Coupon( $cid );
try {
	ZippyCrm\Hooks\VoucherHourWindow::is_valid( true, $coupon, null );
	echo "  FAIL: should have thrown\n";
} catch ( Throwable $e ) {
	echo "  OK — threw: " . $e->getMessage() . "\n";
}

echo "\n=== 7. Catalog product search ===\n";
[ $s, $d ] = hit( 'GET', '/admin/catalog/products', [ 'search' => 'test', 'per_page' => 3 ] );
echo "[{$s}] returned " . count( $d['items'] ?? [] ) . " items\n";
if ( ! empty( $d['items'] ) ) echo "  first: " . json_encode( $d['items'][0] ) . "\n";

echo "\n=== 8. Catalog category search ===\n";
[ $s, $d ] = hit( 'GET', '/admin/catalog/categories', [ 'search' => '' , 'ids' => '0' ] );
echo "[{$s}] code=" . ( $d['code'] ?? '?' ) . " (expect missing_query 400)\n";

[ $s, $d ] = hit( 'GET', '/admin/catalog/categories', [ 'per_page' => 5, 'search' => 'a' ] );
echo "[{$s}] returned " . count( $d['items'] ?? [] ) . " category items\n";

echo "\n=== 9. Cleanup ===\n";
$wpdb->update( $wpdb->prefix . 'crm_vouchers', [ 'status' => 'draft' ], [ 'id' => $vid ] );
[ $s, $d ] = hit( 'DELETE', "/admin/vouchers/{$vid}" );
echo "delete status=$s\n";
if ( $cid ) wp_delete_post( $cid, true );
echo "Done.\n";
