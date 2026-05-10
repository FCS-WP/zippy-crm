<?php
/**
 * Smoke test for the v1.8.0 tender flow.
 *
 *   1. Set up user 1 with a known balance
 *   2. Create a cart with one product
 *   3. POST /points/apply 100 → fee should appear, balance unchanged
 *   4. Complete the order → balance debits by 100, ledger gets `redeem` row
 *   5. Refund 50% → 50 pts credit back via `adjust` row
 *   6. Re-fire order_status_completed → no double-debit (idempotency)
 */

define( 'REST_REQUEST', true ); // for AuditLogger gate
wp_set_current_user( 1 );

// 1. Reset state for user 1
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->prefix}crm_points_ledger WHERE user_id = 1 AND created_at > '2026-04-01'" );
ZippyCrm\Models\PointsSummary::set( 1, 500, 0, 500 );
ZippyCrm\Models\PointsLedger::insert( 1, 'adjust', 500, 'tender test seed', null );
ZippyCrm\Services\PointsEngine::invalidate( 1 );
echo "seed: balance = " . ZippyCrm\Services\PointsEngine::get_balance( 1 ) . "\n";

// 2. Boot a cart context
WC()->cart->empty_cart();
$products = wc_get_products( [ 'limit' => 1, 'status' => 'publish' ] );
if ( ! $products ) {
	echo "no products — abort\n"; exit;
}
$product = $products[0];
WC()->cart->add_to_cart( $product->get_id(), 1 );
WC()->cart->calculate_totals();
$pre_tender_total = (float) WC()->cart->get_total( 'edit' );
echo "cart total before tender: $pre_tender_total\n";

// 3. Apply 100 pts
$req = new WP_REST_Request( 'POST', '/zippy-crm/v1/points/apply' );
$req->set_body_params( [ 'points' => 100 ] );
$res = rest_do_request( $req );
echo "apply 100: status=" . $res->get_status() . " applied=" . $res->get_data()['applied'] . " applied_dollars=" . $res->get_data()['applied_dollars'] . "\n";

// Force recalc to populate the fee
WC()->cart->calculate_totals();
$post_tender_total = (float) WC()->cart->get_total( 'edit' );
echo "cart total after  tender: $post_tender_total (should be \$5 less)\n";

$fees = WC()->cart->get_fees();
echo "fees on cart: " . count( $fees ) . "\n";
foreach ( $fees as $fee ) {
	echo "  - {$fee->name}: " . wc_price( $fee->amount ) . "\n";
}

// 4. Create + complete an order via WC the way checkout would
WC()->cart->calculate_totals();
$order = wc_create_order( [ 'customer_id' => 1 ] );
$order->add_product( $product, 1 );
foreach ( WC()->cart->get_fees() as $fee ) {
	$item = new \WC_Order_Item_Fee();
	$item->set_name( $fee->name );
	$item->set_amount( $fee->amount );
	$item->set_total( $fee->amount );
	$item->set_tax_status( $fee->taxable ? 'taxable' : 'none' );
	$order->add_item( $item );
}
$order->update_meta_data( ZippyCrm\Services\PointsTender::META_APPLIED, 100 );
$order->calculate_totals();
$order_id = $order->get_id();
echo "order #$order_id created, total = " . $order->get_total() . "\n";

// Trigger the full hook chain
$order->update_status( 'completed' );
$balance_after = ZippyCrm\Services\PointsEngine::get_balance( 1 );
echo "balance after completion: $balance_after (should be 400 — earned a few from the order, debited 100)\n";

// Verify ledger
$rows = $wpdb->get_results( "SELECT type, points, description FROM {$wpdb->prefix}crm_points_ledger WHERE user_id=1 AND order_id={$order_id} ORDER BY id" );
echo "ledger rows on order #$order_id:\n";
foreach ( $rows as $r ) {
	echo "  - {$r->type}: {$r->points} ({$r->description})\n";
}

// 5. Refund half the order
$refund = wc_create_refund( [
	'amount'   => round( $order->get_total() / 2, 2 ),
	'order_id' => $order_id,
] );
if ( is_wp_error( $refund ) ) {
	echo "refund error: " . $refund->get_error_message() . "\n";
} else {
	echo "refunded half (refund #" . $refund->get_id() . ")\n";
}

$balance_after_refund = ZippyCrm\Services\PointsEngine::get_balance( 1 );
echo "balance after 50% refund: $balance_after_refund (should be ~450 — got 50 pts back)\n";

// 6. Re-fire completed hook → no double-debit
do_action( 'woocommerce_order_status_completed', $order_id );
$balance_replay = ZippyCrm\Services\PointsEngine::get_balance( 1 );
echo "balance after replay: $balance_replay (should be UNCHANGED from previous)\n";

// Cleanup the test order so it doesn't pollute future runs
$order->delete( true );
echo "test order deleted\n";
