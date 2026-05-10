<?php
wp_set_current_user(1);
global $wpdb;

// Reset
$wpdb->query("DELETE FROM {$wpdb->prefix}crm_voucher_claims WHERE user_id = 1");
$wpdb->query("DELETE FROM {$wpdb->prefix}crm_vouchers WHERE code IN ('SUMMER25','WELCOME10','EXPIRED5')");

// Seed: an active voucher, an active second voucher, an expired one for negative testing
$id1 = ZippyCrm\Models\Voucher::create([
    'code' => 'SUMMER25',
    'title' => 'Summer Sale 25% Off',
    'description' => '25% off your next order, minimum $50.',
    'discount_type' => 'percent',
    'discount_value' => 25,
    'min_order_amount' => 50,
    'max_uses' => 100,
    'status' => 'active',
    'expires_at' => '2026-12-31 23:59:59',
], 1);

$id2 = ZippyCrm\Models\Voucher::create([
    'code' => 'WELCOME10',
    'title' => 'Welcome $10 Off',
    'description' => '$10 off your first order.',
    'discount_type' => 'fixed_cart',
    'discount_value' => 10,
    'status' => 'active',
], 1);

$id3 = ZippyCrm\Models\Voucher::create([
    'code' => 'EXPIRED5',
    'title' => 'Expired test',
    'discount_type' => 'fixed_cart',
    'discount_value' => 5,
    'status' => 'active',
    'expires_at' => '2025-01-01 00:00:00',
], 1);

echo "seeded vouchers: $id1, $id2, $id3\n\n";

// 1. List available — should see id1 and id2 only
$req = new WP_REST_Request('GET', '/zippy-crm/v1/vouchers');
$res = rest_do_request($req);
$d = $res->get_data();
echo "AVAILABLE: " . count($d['items']) . " vouchers\n";
foreach ($d['items'] as $v) {
    echo "  - {$v['code']} ({$v['discount_type']} {$v['discount_value']})\n";
}

// 2. Claim id1
$req = new WP_REST_Request('POST', "/zippy-crm/v1/vouchers/$id1/claim");
$res = rest_do_request($req);
echo "\nCLAIM SUMMER25: status=" . $res->get_status() . "\n";
echo "  body: " . json_encode($res->get_data()) . "\n";

// 3. Try to claim it again — should be already_claimed (409)
$req = new WP_REST_Request('POST', "/zippy-crm/v1/vouchers/$id1/claim");
$res = rest_do_request($req);
echo "\nRECLAIM SUMMER25: status=" . $res->get_status() . "\n";
$d = $res->get_data();
echo "  code: " . ($d['code'] ?? '?') . " msg: " . ($d['message'] ?? '') . "\n";

// 4. Try to claim the expired one — should be voucher_expired (410)
$req = new WP_REST_Request('POST', "/zippy-crm/v1/vouchers/$id3/claim");
$res = rest_do_request($req);
echo "\nCLAIM EXPIRED5: status=" . $res->get_status() . "\n";
$d = $res->get_data();
echo "  code: " . ($d['code'] ?? '?') . " msg: " . ($d['message'] ?? '') . "\n";

// 5. List my claims — should be 1
$req = new WP_REST_Request('GET', '/zippy-crm/v1/vouchers/claims');
$res = rest_do_request($req);
$d = $res->get_data();
echo "\nMY CLAIMS: " . count($d['items']) . "\n";
foreach ($d['items'] as $c) {
    echo "  - {$c['code']} status={$c['status']} claimed_at={$c['claimed_at']}\n";
}

// 6. List available again — should be 1 (id2 only, SUMMER25 already claimed)
$req = new WP_REST_Request('GET', '/zippy-crm/v1/vouchers');
$res = rest_do_request($req);
$d = $res->get_data();
echo "\nAVAILABLE (after claim): " . count($d['items']) . "\n";
foreach ($d['items'] as $v) {
    echo "  - {$v['code']}\n";
}
