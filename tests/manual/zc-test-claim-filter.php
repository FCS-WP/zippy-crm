<?php
global $wpdb;

$rows = $wpdb->get_results(
	"SELECT c.user_id, c.id AS claim_id, c.status as cs, v.title, v.expires_at, v.status AS vs
	 FROM   {$wpdb->prefix}crm_voucher_claims c
	 JOIN   {$wpdb->prefix}crm_vouchers v ON v.id = c.voucher_id
	 ORDER  BY c.id DESC LIMIT 10"
);
foreach ( $rows as $r ) {
	echo "  claim #{$r->claim_id} user={$r->user_id} cs={$r->cs} voucher='{$r->title}' vs={$r->vs} expires=" . ( $r->expires_at ?? '∞' ) . "\n";
}

$pick = $rows[0] ?? null;
if ( ! $pick ) { echo "no claims yet\n"; exit; }
$uid = (int) $pick->user_id;
$raw = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}crm_voucher_claims WHERE user_id=%d", $uid ) );
$filt = ZippyCrm\Models\VoucherClaim::list_for_user( $uid );
echo "\nUser #{$uid}: raw=$raw filtered=" . count( $filt ) . "\n";

echo "\n=== Inject expired voucher + claim ===\n";
$wpdb->insert( $wpdb->prefix . 'crm_vouchers', [
	'code'           => 'TESTEXPIRED-' . time(),
	'title'          => 'Expired test',
	'discount_type'  => 'percent',
	'discount_value' => 10,
	'status'         => 'active',
	'expires_at'     => '2020-01-01 00:00:00',
	'created_by'     => 1,
	'created_at'     => gmdate( 'Y-m-d H:i:s' ),
] );
$vid = (int) $wpdb->insert_id;

$wpdb->insert( $wpdb->prefix . 'crm_voucher_claims', [
	'voucher_id' => $vid,
	'user_id'    => $uid,
	'status'     => 'claimed',
	'claimed_at' => gmdate( 'Y-m-d H:i:s' ),
] );
$cid = (int) $wpdb->insert_id;

$raw_after  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}crm_voucher_claims WHERE user_id=%d", $uid ) );
$filt_after = ZippyCrm\Models\VoucherClaim::list_for_user( $uid );
echo "raw_after=$raw_after  filtered_after=" . count( $filt_after ) . "  (filtered should equal pre-inject filtered = " . count( $filt ) . ")\n";

echo "\n=== Inject paused voucher + claim ===\n";
$wpdb->insert( $wpdb->prefix . 'crm_vouchers', [
	'code'           => 'TESTPAUSED-' . time(),
	'title'          => 'Paused test',
	'discount_type'  => 'percent',
	'discount_value' => 10,
	'status'         => 'paused',
	'expires_at'     => null,
	'created_by'     => 1,
	'created_at'     => gmdate( 'Y-m-d H:i:s' ),
] );
$vid2 = (int) $wpdb->insert_id;
$wpdb->insert( $wpdb->prefix . 'crm_voucher_claims', [
	'voucher_id' => $vid2,
	'user_id'    => $uid,
	'status'     => 'claimed',
	'claimed_at' => gmdate( 'Y-m-d H:i:s' ),
] );
$cid2 = (int) $wpdb->insert_id;

$filt_paused = ZippyCrm\Models\VoucherClaim::list_for_user( $uid );
echo "filtered_after_paused=" . count( $filt_paused ) . "  (should still be " . count( $filt ) . ")\n";

// Cleanup
$wpdb->delete( $wpdb->prefix . 'crm_voucher_claims', [ 'id' => $cid ] );
$wpdb->delete( $wpdb->prefix . 'crm_voucher_claims', [ 'id' => $cid2 ] );
$wpdb->delete( $wpdb->prefix . 'crm_vouchers', [ 'id' => $vid ] );
$wpdb->delete( $wpdb->prefix . 'crm_vouchers', [ 'id' => $vid2 ] );
echo "cleaned\n";
