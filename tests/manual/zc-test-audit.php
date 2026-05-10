<?php
global $wpdb;

// Clean state
$wpdb->query("DELETE FROM {$wpdb->prefix}crm_audit_log");

// Set current user to admin (id=1) so logger sees admin context.
wp_set_current_user(1);

// Manually set the REST_REQUEST flag so is_admin_context() passes —
// we're simulating admin REST calls.
if (!defined('REST_REQUEST')) define('REST_REQUEST', true);

// 1. Re-fire the listener registration (Plugin::boot already did this once,
//    but if we're in a fresh CLI invocation the listener might not be wired
//    — explicit register() is safe to call twice because add_action is dedup'd
//    on identical callbacks).
ZippyCrm\Services\AuditLogger::register();

// 2. Pick a victim user.
$ids = ZippyCrm\Support\Seeder::seed_qc_fixtures();
$victim_id = (int) get_user_by('login', 'qa-silver-1')->ID;
echo "victim_id = $victim_id\n";

// 3. Admin promotes them to gold.
$res = ZippyCrm\Services\MembershipService::set_level_admin($victim_id, 'gold');
echo "level change: ", var_export($res, true), "\n";

// 4. Admin suspends them.
$res = ZippyCrm\Services\MembershipService::set_status_admin($victim_id, 'suspended');
echo "status change: ", var_export($res, true), "\n";

// 5. Admin publishes a voucher.
$voucher = ZippyCrm\Models\Voucher::find_by_code('QA-FIXED-10');
ZippyCrm\Services\VoucherService::publish((int) $voucher['id']);

// 6. Explicit recorder — points adjusted.
ZippyCrm\Services\AuditLogger::record_points_adjusted($victim_id, 50, 'apology credit');

// 7. Read the audit log via the model.
$result = ZippyCrm\Models\AuditLog::get_paginated('', null, null, 1, 50);
echo "\n--- audit rows: $result[total] ---\n";
foreach ($result['items'] as $row) {
    $meta = $row['meta_json'] ? json_decode($row['meta_json'], true) : [];
    printf("  #%d %-32s actor=%d target=%-3s meta=%s\n",
        $row['id'], $row['event'], $row['actor_id'],
        $row['target_id'] ?? '—', json_encode($meta));
}

// 8. Verify the REST endpoint shape.
echo "\n--- REST GET /admin/audit ---\n";
$req = new WP_REST_Request('GET', '/zippy-crm/v1/admin/audit');
$res = rest_do_request($req);
echo "status: ", $res->get_status(), "\n";
$data = $res->get_data();
echo "total: $data[total]\n";
foreach ($data['items'] as $item) {
    printf("  %-32s by %s → %s · %s\n",
        $item['event'],
        $item['actor']['display_name'] ?: '(actor #' . $item['actor']['id'] . ')',
        $item['target'] ? $item['target']['display_name'] : '—',
        json_encode($item['meta']));
}

// 9. Filter test: only points adjustments.
echo "\n--- filter event=points.adjusted ---\n";
$req = new WP_REST_Request('GET', '/zippy-crm/v1/admin/audit');
$req->set_query_params(['event' => 'points.adjusted']);
$res = rest_do_request($req);
$data = $res->get_data();
echo "filtered total: $data[total]\n";

// 10. Filter test: by target user.
echo "\n--- filter target_id=$victim_id ---\n";
$req = new WP_REST_Request('GET', '/zippy-crm/v1/admin/audit');
$req->set_query_params(['target_id' => $victim_id]);
$res = rest_do_request($req);
$data = $res->get_data();
echo "filtered total: $data[total] (should be 3 — level, status, points adjust)\n";

// 11. Negative test: simulate a customer-side automated tier eval.
//     The listener should NOT log it because is_admin_context() returns false
//     for a non-admin user.
echo "\n--- automated tier eval (should NOT log) ---\n";
$customer_id = (int) get_user_by('login', 'qa-free-1')->ID;
wp_set_current_user($customer_id);
do_action('crm_membership_level_changed', $customer_id, 'free', 'silver');
$total_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}crm_audit_log");
echo "audit rows after customer-side action: $total_after (should still be 4)\n";
