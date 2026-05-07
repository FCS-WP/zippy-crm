# CLAUDE.md — WooCommerce CRM Plugin

> Read this file in full before working on any task in this project.
>
> **Project rules** (read these too — they apply to every change):
> - [.claude/rules/file-size.md](./.claude/rules/file-size.md) — 500-line cap, where to split
> - [.claude/rules/sql-files.md](./.claude/rules/sql-files.md) — SQL goes in `.sql` files, not inline
> - [.claude/rules/shared-components.md](./.claude/rules/shared-components.md) — reusable React + PHP layers
> - [.claude/rules/performance.md](./.claude/rules/performance.md) — where perf actually matters
> - [.claude/rules/woocommerce-hpos.md](./.claude/rules/woocommerce-hpos.md) — HPOS only, no legacy postmeta

---

## Project Overview

Custom WordPress/WooCommerce plugin providing CRM functionality with three core features:

- **Membership System** — registration, login, member tier management
- **Points System** — earn points on purchase, redeem points for discount
- **Voucher System** — Admin creates & publishes vouchers, customers claim them, opt-in email notifications

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| CMS | WordPress 6.x |
| E-commerce | WooCommerce 8.x+ |
| Backend | PHP 8.1+ |
| Database | MySQL 8.0+ |
| Frontend | Vanilla JS + jQuery (WP bundled) |
| Styles | CSS (WP Admin + custom) |
| Tooling | WP-CLI, Composer |

---

## Plugin Structure

```
wp-content/plugins/crm-membership/
├── crm-membership.php                    # Entry point — defines constants, loads plugin
├── composer.json
├── includes/
│   ├── class-crm-core.php                # Bootstrap: loads modules, registers hooks
│   ├── class-crm-installer.php           # Creates / migrates database tables
│   ├── class-crm-auth.php                # Auth Handler: login, register, session
│   ├── class-crm-membership-manager.php  # Membership: levels, status, expiry
│   ├── class-crm-points-engine.php       # Points: earn, redeem, ledger
│   ├── class-crm-order-history.php       # Queries and displays order history
│   ├── class-crm-voucher-manager.php     # Voucher CRUD, publish, status
│   ├── class-crm-claim-handler.php       # Validates and records voucher claims
│   ├── class-crm-notif-engine.php        # Queues and dispatches email notifications
│   └── class-crm-subs-manager.php        # Manages notification opt-in preferences
├── admin/
│   ├── class-crm-admin.php               # Admin panel bootstrap and menu
│   ├── views/
│   │   ├── page-members.php              # Member list and detail view
│   │   ├── page-vouchers.php             # Voucher list, create, edit
│   │   ├── page-points.php               # Points overview and manual adjust
│   │   └── page-reports.php              # Reports and analytics
│   ├── js/crm-admin.js
│   └── css/crm-admin.css
├── public/
│   ├── class-crm-public.php              # Frontend hooks and shortcodes
│   ├── my-account/
│   │   ├── tab-membership.php            # My Account: membership info
│   │   ├── tab-points.php                # My Account: points balance and history
│   │   └── tab-vouchers.php              # My Account: available and claimed vouchers
│   ├── js/crm-public.js
│   └── css/crm-public.css
├── templates/
│   └── emails/
│       ├── voucher-notification.php      # Email template: voucher published
│       └── points-earned.php             # Email template: points awarded
├── languages/
│   └── crm-membership.pot
└── docs/
    ├── FEATURE_SPEC.md
    └── DATABASE_SCHEMA.md
```

---

## Database Tables

> All custom tables use the `crm_` prefix. Do not store business data in `wp_usermeta`.

### Membership

```sql
CREATE TABLE crm_memberships (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    membership_level ENUM('free','silver','gold','vip') DEFAULT 'free',
    status           ENUM('active','suspended','expired') DEFAULT 'active',
    joined_at        DATETIME NOT NULL,
    expires_at       DATETIME NULL,
    UNIQUE KEY uq_user (user_id),
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE
);
```

### Points

```sql
CREATE TABLE crm_points_ledger (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    order_id    BIGINT UNSIGNED NULL,
    type        ENUM('earn','redeem','expire','adjust') NOT NULL,
    points      INT NOT NULL,            -- positive = credit, negative = debit
    description VARCHAR(255),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_order (order_id)
);

CREATE TABLE crm_points_summary (
    user_id        BIGINT UNSIGNED PRIMARY KEY,
    total_earned   INT UNSIGNED DEFAULT 0,
    total_redeemed INT UNSIGNED DEFAULT 0,
    balance        INT UNSIGNED DEFAULT 0,
    updated_at     DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE
);
```

### Vouchers

```sql
CREATE TABLE crm_vouchers (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code             VARCHAR(50) NOT NULL UNIQUE,   -- mirrors the WC coupon code
    title            VARCHAR(255) NOT NULL,
    description      TEXT,
    discount_type    ENUM('fixed_cart','percent') NOT NULL,
    discount_value   DECIMAL(10,2) NOT NULL,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    max_uses         INT UNSIGNED DEFAULT 0,         -- 0 = unlimited
    uses_count       INT UNSIGNED DEFAULT 0,
    status           ENUM('draft','active','paused','expired') DEFAULT 'draft',
    starts_at        DATETIME NULL,
    expires_at       DATETIME NULL,
    created_by       BIGINT UNSIGNED NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_code (code)
);

CREATE TABLE crm_voucher_claims (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voucher_id BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    status     ENUM('claimed','used','expired') DEFAULT 'claimed',
    claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at    DATETIME NULL,
    order_id   BIGINT UNSIGNED NULL,
    UNIQUE KEY uq_claim (voucher_id, user_id),       -- prevents duplicate claims
    INDEX idx_user (user_id)
);
```

### Notifications

```sql
CREATE TABLE crm_notif_subs (
    user_id             BIGINT UNSIGNED PRIMARY KEY,
    subscribed_vouchers TINYINT(1) DEFAULT 1,
    subscribed_points   TINYINT(1) DEFAULT 1,
    updated_at          DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE
);

CREATE TABLE crm_notification_log (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    voucher_id BIGINT UNSIGNED NOT NULL,
    user_id    BIGINT UNSIGNED NOT NULL,
    status     ENUM('queued','sent','failed') DEFAULT 'queued',
    sent_at    DATETIME NULL,
    UNIQUE KEY uq_notif (voucher_id, user_id),       -- prevents duplicate sends
    INDEX idx_status (status)
);
```

---

## Business Logic Rules

### Points System

- **Earn:** `floor(order_subtotal_after_discounts) x membership_multiplier = N points`
- **Membership multipliers:** free = 1x, silver = 1.2x, gold = 1.5x, vip = 2x
- **Redeem:** 20 points = $1 discount — minimum redemption is 20 points
- **Hook trigger:** `woocommerce_order_status_completed` — only award on completed orders
- **Ledger is append-only:** never UPDATE or DELETE rows in `crm_points_ledger`
- **Balance cache:** always update `crm_points_summary` after every transaction

### Voucher & Claim

- Each user may claim a voucher only once — enforced by `UNIQUE KEY (voucher_id, user_id)`
- Claim validation order: (1) voucher active? → (2) quota available? → (3) not already claimed?
- The WC coupon is created from `crm_vouchers.code` and synced when the voucher is published
- `uses_count` increments when the linked order completes, **not** when claimed

### Email Notification

- Only send to users where `subscribed_vouchers = 1`
- Before queuing, check `crm_notification_log` to prevent duplicate sends
- Batch size: `CRM_EMAIL_BATCH_SIZE` (default 50) — dispatched via WP Cron
- Retry failed sends: a separate cron job retries `status = 'failed'` records every hour

---

## WooCommerce Hooks

```php
// Points: award when order completes
add_action( 'woocommerce_order_status_completed', [ $points_engine, 'award_points' ] );

// Membership: create record on customer registration
add_action( 'woocommerce_created_customer', [ $membership_manager, 'create_membership' ] );

// Opt-in: render checkbox on the registration form
add_action( 'woocommerce_register_form', [ $subs_manager, 'render_optin_field' ] );
add_action( 'woocommerce_created_customer', [ $subs_manager, 'save_optin_preference' ] );

// My Account: register custom tabs
add_filter( 'woocommerce_account_menu_items', [ $crm_public, 'add_account_tabs' ] );
add_action( 'woocommerce_account_crm-points_endpoint',     [ $crm_public, 'render_points_tab' ] );
add_action( 'woocommerce_account_crm-vouchers_endpoint',   [ $crm_public, 'render_vouchers_tab' ] );
add_action( 'woocommerce_account_crm-membership_endpoint', [ $crm_public, 'render_membership_tab' ] );

// Voucher: sync WC coupon when published
add_action( 'crm_voucher_published', [ $voucher_manager, 'sync_wc_coupon' ] );

// Cron: batch email dispatch
add_action( 'crm_dispatch_voucher_notifications', [ $notif_engine, 'dispatch_batch' ] );
```

---

## PHP Constants

Define in `wp-config.php` or at the top of `crm-membership.php`:

```php
define( 'CRM_VERSION',                '1.0.0' );
define( 'CRM_PLUGIN_DIR',             plugin_dir_path( __FILE__ ) );
define( 'CRM_PLUGIN_URL',             plugin_dir_url( __FILE__ ) );
define( 'CRM_POINTS_PER_DOLLAR',      1 );
define( 'CRM_POINTS_REDEMPTION_RATE', 20 );  // 20 pts = $1
define( 'CRM_EMAIL_BATCH_SIZE',       50 );
define( 'CRM_EMAIL_BATCH_INTERVAL',   300 ); // seconds between batches
define( 'CRM_MIN_REDEMPTION_POINTS',  20 );
```

---

## Coding Conventions

- Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Class names: `CRM_{Module}` — e.g. `CRM_Points_Engine`, `CRM_Voucher_Manager`
- Method names: `snake_case` — e.g. `award_points()`, `validate_claim()`
- Hook names: `crm_{action}` — e.g. `crm_points_awarded`, `crm_voucher_published`
- WP option names: `crm_{setting}` — e.g. `crm_points_per_dollar`
- Every file must begin with `defined( 'ABSPATH' ) || exit;`
- Always verify nonce on POST requests: `check_ajax_referer( 'crm_nonce' )`
- Always use `$wpdb->prepare()` for SQL — never interpolate variables directly
- Always escape output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- Always sanitize input: `sanitize_text_field()`, `intval()`, `absint()`
- Use the singleton pattern for all main classes: `CRM_Points_Engine::instance()`

---

## Common Code Patterns

### Singleton bootstrap

```php
class CRM_Points_Engine {
    private static $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }
}
```

### Award points

```php
CRM_Points_Engine::instance()->award(
    user_id:     $user_id,
    points:      $points,
    type:        'earn',
    order_id:    $order_id,
    description: 'Order #' . $order_id
);
```

### Validate a voucher claim

```php
$result = CRM_Claim_Handler::instance()->validate( $voucher_id, $user_id );
// returns: [ 'valid' => bool, 'code' => string, 'message' => string ]
if ( ! $result['valid'] ) {
    wp_send_json_error( $result['message'] );
}
```

### Query subscribed users (batch-safe)

```php
$users = $wpdb->get_col( $wpdb->prepare(
    "SELECT ns.user_id
     FROM   crm_notif_subs ns
     WHERE  ns.subscribed_vouchers = 1
       AND  ns.user_id NOT IN (
                SELECT user_id FROM crm_notification_log WHERE voucher_id = %d
            )
     LIMIT  %d",
    $voucher_id,
    CRM_EMAIL_BATCH_SIZE
) );
```

---

## Dev Commands

```bash
# Activate plugin
wp plugin activate crm-membership

# Run database migrations
wp eval 'CRM_Installer::instance()->run_migrations();'

# Seed test data
wp eval 'CRM_Seeder::seed_members(10); CRM_Seeder::seed_vouchers(5);'

# Manually trigger a notification batch
wp eval 'CRM_Notif_Engine::instance()->dispatch_batch();'

# Check PHP coding standards
./vendor/bin/phpcs --standard=WordPress includes/

# Auto-fix coding standards
./vendor/bin/phpcbf --standard=WordPress includes/

# Run unit tests
./vendor/bin/phpunit --configuration phpunit.xml
```

---

## Gotchas & Known Issues

- `crm_points_summary.balance` must always equal `SUM(points) FROM crm_points_ledger WHERE user_id = X`. If they diverge, call `CRM_Points_Engine::recalculate_balance( $user_id )`.
- WP Cron does not fire without incoming traffic. In production, disable the default `wp-cron.php` and schedule it via a real system crontab.
- The `UNIQUE KEY (voucher_id, user_id)` on `crm_voucher_claims` can silently fail if a user double-clicks the claim button. Always check `$wpdb->last_error` after the INSERT.
- Before creating a WC coupon from `crm_vouchers.code`, verify the coupon code does not already exist in WooCommerce.

---

## Keeping This File Up to Date

Update `CLAUDE.md` whenever you:

- Add a new database table
- Register a new hook
- Change a business logic rule
- Add a new module to the plugin
