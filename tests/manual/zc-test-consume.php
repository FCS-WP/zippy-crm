<?php
wp_set_current_user(1);

// Find the latest active pending coupon for user 1.
global $wpdb;
$code = $wpdb->get_var( $wpdb->prepare(
	"SELECT description FROM {$wpdb->prefix}crm_points_ledger
	 WHERE type='pending_redeem' AND pending_status='active' AND user_id=%d
	 ORDER BY id DESC LIMIT 1", 1
) );
echo "Coupon to consume: $code\n";

// Make a fresh order and apply the coupon.
$order = wc_create_order( [ 'customer_id' => 1 ] );

$products = wc_get_products( [ 'limit' => 1 ] );
if ( $products ) {
	$order->add_product( $products[0], 1 );
}

$order->apply_coupon( $code );
$order->calculate_totals();
$order->save();

echo "Order before completion: status=" . $order->get_status() . " total=" . $order->get_total() . "\n";

$order->update_status( 'completed' );

// Check pending row state.
$pending = ZippyCrm\Models\PointsLedger::find_pending_by_code( $code );
echo "Pending lookup (null=consumed): " . var_export( $pending, true ) . "\n";

// Check final balance via REST.
$req = new WP_REST_Request( 'GET', '/zippy-crm/v1/points/me' );
$res = rest_do_request( $req );
echo "FINAL summary: " . json_encode( $res->get_data() ) . "\n";

// Check the meta on the order.
$meta = $order->get_meta( '_zc_points_redeemed_codes' );
echo "Order meta _zc_points_redeemed_codes: $meta\n";

// And re-fire the hook to confirm idempotency.
echo "\n-- Re-firing order_status_completed (should be no-op) --\n";
do_action( 'woocommerce_order_status_completed', $order->get_id() );
$req = new WP_REST_Request( 'GET', '/zippy-crm/v1/points/me' );
$res = rest_do_request( $req );
echo "After replay: " . json_encode( $res->get_data() ) . "\n";
