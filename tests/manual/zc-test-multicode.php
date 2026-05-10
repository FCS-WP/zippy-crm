<?php
/**
 * Smoke test for v1.10.0 multi-code voucher campaign:
 *
 *   1. Create a 3-slot multi-code voucher (admin types codes)
 *   2. Publish → 3 WC coupons exist, each max_uses=1
 *   3. Three different users claim — each gets a unique code
 *   4. 4th user claim → quota_exceeded
 *   5. User A applies their code at checkout → order completes →
 *      that code is marked 'used', voucher uses_count=1
 *   6. User A tries to claim again → already_claimed
 *   7. Single-code voucher claim flow still works (regression check)
 */

define( 'REST_REQUEST', true );

global $wpdb;

// Clean slate
$wpdb->query( "DELETE FROM {$wpdb->prefix}crm_voucher_codes WHERE voucher_id IN (SELECT id FROM {$wpdb->prefix}crm_vouchers WHERE code LIKE 'ZC_TEST_%' OR code LIKE 'TESTSINGLE')" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}crm_voucher_claims WHERE voucher_id IN (SELECT id FROM {$wpdb->prefix}crm_vouchers WHERE code LIKE 'ZC_TEST_%' OR code LIKE 'TESTSINGLE')" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}crm_vouchers WHERE code LIKE 'ZC_TEST_%' OR code LIKE 'TESTSINGLE'" );

// Create three test users (use existing if present)
$users = [];
foreach ( [ 'mc_alpha', 'mc_bravo', 'mc_charlie', 'mc_delta' ] as $login ) {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		$id = wp_create_user( $login, 'Test1234!', "$login@multicode.test" );
		$u  = get_user_by( 'id', $id );
	}
	$users[ $login ] = $u->ID;
}
echo "users: " . json_encode( $users ) . "\n\n";

// 1. Create 3-slot multi-code voucher
wp_set_current_user( 1 );
$result = ZippyCrm\Services\VoucherService::create_draft( [
	'title'             => 'MC test 25%',
	'description'       => 'Multi-code smoke test',
	'discount_type'     => 'percent',
	'discount_value'    => 25,
	'distribution_mode' => 'multi_code_public',
	'slots'             => 3,
	'codes'             => [ 'TESTMC-AAA111', 'TESTMC-BBB222', 'TESTMC-CCC333' ],
], 1 );

if ( $result instanceof WP_Error ) {
	echo "create error: " . $result->get_error_code() . " — " . $result->get_error_message() . "\n";
	exit;
}
$voucher_id = (int) $result['id'];
echo "1. created voucher #$voucher_id (placeholder code: {$result['code']})\n";

// Verify 3 rows in crm_voucher_codes
$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT code, status FROM {$wpdb->prefix}crm_voucher_codes WHERE voucher_id = %d ORDER BY id",
	$voucher_id
) );
echo "   codes minted:\n";
foreach ( $rows as $r ) echo "     - $r->code [$r->status]\n";

// 2. Publish
$ok = ZippyCrm\Services\VoucherService::publish( $voucher_id );
echo "2. publish: " . ( $ok ? "ok" : "FAIL" ) . "\n";
foreach ( $rows as $r ) {
	$cid = wc_get_coupon_id_by_code( $r->code );
	echo "     - $r->code → WC coupon id=" . ( $cid ?: 'MISSING' ) . "\n";
}

// 3. Three users claim
echo "\n3. claim flow:\n";
foreach ( [ 'mc_alpha', 'mc_bravo', 'mc_charlie' ] as $login ) {
	$uid = $users[ $login ];
	$cl  = ZippyCrm\Services\ClaimHandler::claim( $voucher_id, $uid );
	if ( ! $cl['valid'] ) {
		echo "   $login: FAIL — {$cl['code']}\n";
	} else {
		echo "   $login: got code " . $cl['assigned_code'] . "\n";
	}
}

// 4. 4th user → quota_exceeded
$cl = ZippyCrm\Services\ClaimHandler::claim( $voucher_id, $users['mc_delta'] );
echo "4. mc_delta claim (should fail): " . ( $cl['valid'] ? "UNEXPECTED OK" : $cl['code'] ) . "\n";

// 5. Simulate user A using their code on an order
$alpha_claim_code = $wpdb->get_var( $wpdb->prepare(
	"SELECT vc.code FROM {$wpdb->prefix}crm_voucher_codes vc
	 JOIN {$wpdb->prefix}crm_voucher_claims c ON c.code_id = vc.id
	 WHERE c.user_id = %d AND vc.voucher_id = %d",
	$users['mc_alpha'], $voucher_id
) );
echo "\n5. alpha's code: $alpha_claim_code\n";

$products = wc_get_products( [ 'limit' => 1, 'status' => 'publish' ] );
if ( $products ) {
	$order = wc_create_order( [ 'customer_id' => $users['mc_alpha'] ] );
	$order->add_product( $products[0], 1 );
	$order->apply_coupon( $alpha_claim_code );
	$order->calculate_totals();
	$order->save();
	$order->update_status( 'completed' );

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT status, used_at, order_id FROM {$wpdb->prefix}crm_voucher_codes WHERE code = %s",
		$alpha_claim_code
	) );
	echo "   after order completion: code status=$row->status, order_id=$row->order_id\n";

	$voucher_now = ZippyCrm\Models\Voucher::find( $voucher_id );
	echo "   voucher uses_count: {$voucher_now['uses_count']}\n";
}

// 6. Replay: alpha tries to claim again
$cl = ZippyCrm\Services\ClaimHandler::claim( $voucher_id, $users['mc_alpha'] );
echo "\n6. alpha claim again: " . ( $cl['valid'] ? "UNEXPECTED" : $cl['code'] ) . "\n";

// 7. Single-code regression check
echo "\n7. single-code voucher regression:\n";
$single = ZippyCrm\Services\VoucherService::create_draft( [
	'code'           => 'TESTSINGLE',
	'title'          => 'Single-code regression',
	'discount_type'  => 'fixed_cart',
	'discount_value' => 5,
], 1 );
if ( $single instanceof WP_Error ) {
	echo "   create error: " . $single->get_error_message() . "\n";
} else {
	ZippyCrm\Services\VoucherService::publish( (int) $single['id'] );
	$cl = ZippyCrm\Services\ClaimHandler::claim( (int) $single['id'], $users['mc_delta'] );
	$assigned = $cl['assigned_code'] ?? 'NULL (correct for single-code)';
	echo "   delta claim: code='$cl[valid]' assigned_code=$assigned\n";
	echo "   master code in voucher row: $single[code]\n";
}

// Cleanup
$wpdb->query( "DELETE FROM {$wpdb->prefix}crm_voucher_codes WHERE voucher_id = $voucher_id" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}crm_voucher_claims WHERE voucher_id = $voucher_id" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}crm_vouchers WHERE id = $voucher_id OR code = 'TESTSINGLE'" );
echo "\ndone, cleaned up.\n";
