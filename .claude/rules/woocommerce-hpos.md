# Rule: WooCommerce HPOS Only — No Legacy

WooCommerce 8.x ships **High-Performance Order Storage (HPOS / COT)**. Orders live in `wc_orders`, `wc_order_addresses`, `wc_order_operational_data`, `wc_orders_meta` — NOT in `wp_posts` / `wp_postmeta`.

This plugin targets HPOS only. Legacy `wp_posts`-based order code is forbidden.

## Hard rules

### 1. Declare HPOS compatibility in the plugin header

In `zippy-crm.php`, add the HPOS compat declaration during `before_woocommerce_init`:

```php
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
```

Without this, WC shows an "incompatible" warning on the admin Plugins page.

### 2. Always use the WC CRUD API for orders

```php
// ✅ CORRECT — works on HPOS and legacy
$order = wc_get_order( $order_id );
$total = $order->get_total();
$customer_id = $order->get_customer_id();
$order->update_meta_data( '_zc_points_awarded', $points );
$order->save();

// ❌ WRONG — legacy postmeta path, breaks under HPOS
$total = get_post_meta( $order_id, '_order_total', true );
update_post_meta( $order_id, '_zc_points_awarded', $points );
$post = get_post( $order_id );
```

### 3. Never query orders via `WP_Query` or `get_posts`

```php
// ✅ CORRECT
$orders = wc_get_orders( [
    'customer_id' => $user_id,
    'status'      => [ 'wc-completed' ],
    'limit'       => 50,
    'return'      => 'ids',
] );

// ❌ WRONG
$orders = get_posts( [ 'post_type' => 'shop_order', 'meta_key' => ... ] );
new WP_Query( [ 'post_type' => 'shop_order', ... ] );
```

### 4. Order meta uses CRUD methods

```php
$order->get_meta( '_zc_points_redeemed' );
$order->update_meta_data( '_zc_points_redeemed', 100 );
$order->save();
```

Never `get_post_meta($order_id, ...)` — under HPOS that table doesn't have the row.

### 5. Order status checks via the API

```php
// ✅
if ( $order->get_status() === 'completed' ) { … }
if ( $order->has_status( [ 'completed', 'processing' ] ) ) { … }

// ❌
if ( $post->post_status === 'wc-completed' ) { … }
```

### 6. Hooks: prefer the `woocommerce_*` hooks, not `transition_post_status`

```php
// ✅ Fires on HPOS and legacy
add_action( 'woocommerce_order_status_completed', $cb );
add_action( 'woocommerce_order_status_changed',   $cb, 10, 4 );
add_action( 'woocommerce_new_order',              $cb );

// ❌ Does NOT fire under HPOS
add_action( 'transition_post_status', $cb );
add_action( 'save_post_shop_order',   $cb );
```

### 7. Coupon API is unchanged but use the API

```php
$coupon = new \WC_Coupon();
$coupon->set_code( $code );
$coupon->set_discount_type( 'fixed_cart' );
$coupon->set_amount( $value );
$coupon->save();
```

Never write coupons via raw `$wpdb` inserts.

### 8. Custom queries that JOIN orders → join the HPOS tables

When a `.sql` file needs to JOIN orders, use HPOS tables — NOT `wp_posts`:

```sql
-- ✅ CORRECT
SELECT o.id, o.total_amount, o.status
FROM   {prefix}wc_orders o
JOIN   {prefix}crm_voucher_claims c ON c.order_id = o.id
WHERE  c.user_id = %d
  AND  o.status = 'wc-completed';

-- ❌ WRONG — these rows don't exist under HPOS
SELECT p.ID FROM {prefix}posts p WHERE p.post_type = 'shop_order' …
```

HPOS table reference:
- `{prefix}wc_orders` — id, status, type, customer_id, total_amount, currency, date_created_gmt, date_updated_gmt
- `{prefix}wc_order_addresses` — billing/shipping
- `{prefix}wc_order_operational_data` — internal flags
- `{prefix}wc_orders_meta` — order_id, meta_key, meta_value

### 9. Customer reads via `WC_Customer` or `wc_get_customer_*`

```php
$customer = new \WC_Customer( $user_id );
$total_spent  = $customer->get_total_spent();
$order_count  = $customer->get_order_count();
```

Used by the membership tier evaluator — these helpers respect HPOS.

## Required check before shipping order-touching code

- [ ] Plugin declares `custom_order_tables` compatibility
- [ ] Zero references to `get_post_meta` / `update_post_meta` for order data
- [ ] Zero references to `WP_Query`/`get_posts` with `post_type => 'shop_order'`
- [ ] Zero `transition_post_status` / `save_post_shop_order` listeners for orders
- [ ] All custom SQL JOINs use `wc_orders*` tables, not `posts`/`postmeta`
- [ ] Manually tested with HPOS enabled (WC → Settings → Advanced → Features → "High-Performance order storage")
