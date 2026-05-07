# CRM Membership & Voucher — Feature Specification

> Version: 1.0.0
> Platform: WordPress + WooCommerce

---

## Table of Contents

1. [Membership System](#1-membership-system)
2. [Points System](#2-points-system)
3. [Voucher System](#3-voucher-system)
4. [Notification & Opt-in System](#4-notification--opt-in-system)
5. [My Account Dashboard](#5-my-account-dashboard)
6. [Admin Panel](#6-admin-panel)
7. [Database Schema Reference](#7-database-schema-reference)
8. [API Reference](#8-api-reference)

---

## 1. Membership System

### 1.1 Overview

The membership system lets customers register an account and be assigned to a tier. A membership record is created automatically when a customer registers.

### 1.2 Membership Levels

| Level | Condition | Points Multiplier |
|-------|-----------|------------------|
| `free` | Default on registration | 1x |
| `silver` | 5+ completed orders OR $500+ lifetime spend | 1.2x |
| `gold` | 15+ completed orders OR $2,000+ lifetime spend | 1.5x |
| `vip` | Admin-assigned only | 2x |

Level upgrades are evaluated inside the `woocommerce_order_status_completed` hook.

### 1.3 Registration Flow

1. Customer fills in the WooCommerce registration form (includes an opt-in checkbox for notifications).
2. WooCommerce creates the `wp_users` record.
3. Hook `woocommerce_created_customer` fires:
   - Insert `crm_memberships` row: `level = free`, `status = active`
   - Insert `crm_notif_subs` row using preferences from the form
   - Insert `crm_points_summary` row with `balance = 0`
4. Redirect to My Account dashboard.

### 1.4 Membership Status

| Status | Meaning |
|--------|---------|
| `active` | Normal — all features available |
| `suspended` | Admin-suspended — cannot claim vouchers or redeem points |
| `expired` | Tier has passed its `expires_at` date |

### 1.5 My Account — Membership Tab

Displays:
- Membership level name and badge
- Join date
- Expiry date (if applicable)
- Progress bar toward the next tier (e.g. "Spend $300 more to reach Gold")
- Total orders placed and total lifetime spend

---

## 2. Points System

### 2.1 Overview

Customers earn points on every completed order and can redeem them for a cart discount at checkout.

### 2.2 Earn Logic

```
points_earned = floor(order_subtotal_after_discounts) x membership_multiplier
```

**Example:** A `silver` customer places a $75.50 order → `floor(75.50) x 1.2 = 90 points`

**Timing:** Points are awarded only when order status changes to `completed`. No points for `processing` or `on-hold`.

### 2.3 Redeem Logic

```
discount_value ($) = floor(points_to_redeem / 20)
minimum redemption  = 20 points = $1
```

**Example:** Customer has 150 points → max discount = `floor(150/20) x 1 = $7`

**Process:**
1. Customer selects how many points to redeem on My Account or Cart page.
2. System creates a WC coupon (`type = fixed_cart`, `usage_limit = 1`, expires in 24 h).
3. Coupon is auto-applied to the cart.
4. On order completion: deduct points via `crm_points_ledger` and update the summary cache.

### 2.4 Points Ledger Rules

Every change to a user's points balance must produce a row in `crm_points_ledger`. Rows are **append-only** — never UPDATE or DELETE existing rows.

| Type | When | Points value |
|------|------|-------------|
| `earn` | Order completed | Positive (+N) |
| `redeem` | Customer redeems points | Negative (−N) |
| `expire` | Points reach expiry (if enabled) | Negative (−N) |
| `adjust` | Admin manual adjustment | Positive or negative |

### 2.5 My Account — Points Tab

Displays:
- Current balance (sourced from `crm_points_summary`)
- Equivalent dollar value (`balance / 20 = $X`)
- Redeem form
- Transaction history (`crm_points_ledger`, paginated 10 rows per page)

---

## 3. Voucher System

### 3.1 Overview

Admins publish voucher codes that customers can claim and use at checkout. Opted-in customers are notified by email when a new voucher goes live.

### 3.2 Voucher Types

| Type | Description |
|------|-------------|
| `fixed_cart` | Fixed dollar discount, e.g. −$10 |
| `percent` | Percentage discount, e.g. −20% |

### 3.3 Voucher Status Lifecycle

```
draft → active ⇄ paused → expired
```

- `draft` — created, not yet visible to customers; no notification sent
- `active` — live; customers can claim it
- `paused` — temporarily hidden; existing claims remain valid
- `expired` — automatically set when `expires_at` is reached or `uses_count >= max_uses`

### 3.4 Admin — Create Voucher Fields

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Title | text | Yes | Display name for customers |
| Code | text | Yes | Unique, uppercase alphanumeric — used as the WC coupon code |
| Description | textarea | No | Short description shown on My Account |
| Discount type | select | Yes | `fixed_cart` or `percent` |
| Discount value | decimal | Yes | Dollar amount or percentage |
| Minimum order | decimal | No | Default 0 = no minimum |
| Max uses (total) | int | No | 0 = unlimited |
| Start date | datetime | No | If empty, effective immediately on publish |
| Expiry date | datetime | No | If empty, no expiry |

**On publish (status → active):**
1. Create the corresponding WC coupon if it does not already exist.
2. Fire `do_action( 'crm_voucher_published', $voucher_id )`.
3. Notification Engine queries users with `subscribed_vouchers = 1`.
4. Batch email jobs are queued via WP Cron.

### 3.5 Customer — Claim Flow

1. Customer sees the voucher (via email or My Account Vouchers tab).
2. Clicks **Claim** → `POST /wp-json/zippy-crm/v1/vouchers/{id}/claim`.
3. Server validates in this exact order:
   - Voucher exists and `status = active`
   - `expires_at` has not passed
   - `uses_count < max_uses` (if `max_uses > 0`)
   - User has not already claimed this voucher (`UNIQUE KEY` check)
   - User membership `status = active`
4. On success: INSERT into `crm_voucher_claims`, return the voucher code.
5. Customer copies the code and applies it at checkout.

### 3.6 My Account — Vouchers Tab

**"Available" tab:** Active vouchers the user has not yet claimed.

**"My Claims" tab:** Vouchers the user has claimed, sorted by status:
- `claimed` — not yet used, still valid
- `used` — applied to a completed order
- `expired` — past expiry or voucher was paused/expired

---

## 4. Notification & Opt-in System

### 4.1 Opt-in at Registration

Two checkboxes are added to the WooCommerce registration form. Both are **checked by default** (opt-in by default).

```html
<p class="form-row">
  <label>
    <input type="checkbox" name="crm_subscribe_vouchers" value="1" checked />
    Notify me about new vouchers and promotions
  </label>
</p>
<p class="form-row">
  <label>
    <input type="checkbox" name="crm_subscribe_points" value="1" checked />
    Notify me about points and rewards updates
  </label>
</p>
```

### 4.2 Managing Preferences

Customers can update preferences at any time from **My Account → Notification Settings**.

### 4.3 Voucher Published — Email Notification

**Trigger:** `do_action( 'crm_voucher_published', $voucher_id )`

**Dispatch process:**
1. Query all users with `subscribed_vouchers = 1`.
2. Exclude users who already have a record in `crm_notification_log` for this voucher.
3. Split into batches of `CRM_EMAIL_BATCH_SIZE` (default 50).
4. Schedule a WP Cron event per batch:
   ```php
   wp_schedule_single_event( time() + $offset, 'crm_dispatch_voucher_notifications', [ $voucher_id, $batch_offset ] );
   ```
5. Each batch: send email → INSERT into `crm_notification_log` with `status = sent` or `failed`.
6. A separate hourly cron job retries `status = failed` entries, up to 3 attempts.

**Email content:**
- **Subject:** `New Voucher: {title} — Save {value} on your next order`
- **Body:** Title, description, discount value, expiry date, CTA button "View & Claim Now"
- **Unsubscribe link:** Direct link to My Account → Notification Settings

### 4.4 Idempotency

Before queuing any email, always check for an existing log entry:

```sql
SELECT id FROM crm_notification_log
WHERE voucher_id = %d AND user_id = %d
```

If a row exists, skip — do not send again.

---

## 5. My Account Dashboard

### 5.1 Custom Endpoints

| Endpoint slug | URL | Tab label |
|---------------|-----|-----------|
| `crm-membership` | `/my-account/crm-membership/` | Membership |
| `crm-points` | `/my-account/crm-points/` | Points |
| `crm-vouchers` | `/my-account/crm-vouchers/` | Vouchers |
| `crm-notifications` | `/my-account/crm-notifications/` | Notifications |

### 5.2 Registering Endpoints

```php
add_action( 'init', function () {
    add_rewrite_endpoint( 'crm-membership',    EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'crm-points',        EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'crm-vouchers',      EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'crm-notifications', EP_ROOT | EP_PAGES );
} );
```

After adding any new endpoint, flush rewrite rules: **Settings → Permalinks → Save**.

---

## 6. Admin Panel

### 6.1 Menu Structure

```
WooCommerce
└── CRM
    ├── Members      — member list, filter by tier/status, manual adjustments
    ├── Points       — overview, manual adjust, export
    ├── Vouchers     — CRUD, publish, view claims
    └── Reports      — new members, points activity, voucher usage charts
```

### 6.2 Members Page

- Columns: Avatar, Name, Email, Level (badge), Status, Points Balance, Join Date, Last Order
- Filters: Level, Status, Date range
- Row actions: View detail, Change level, Suspend / Activate, Adjust points
- Bulk export: CSV

### 6.3 Vouchers Page

- Columns: Code, Title, Type, Value, Claims / Max, Status, Expiry, Created
- Filters: Status, Date range
- Row actions: Edit (draft only), Publish, Pause / Resume, Duplicate, Delete (draft only)
- Quick stats bar: Total claimed, Total used, Remaining quota

### 6.4 Points Page

- Summary cards: Total points issued, Total redeemed, Total outstanding balance
- Recent transactions table: User, Type, Points, Date, Order
- Manual adjust form: User search, type (`adjust`), amount, reason note
- Bulk recalculate balances action

---

## 7. Database Schema Reference

See `CLAUDE.md` for the full `CREATE TABLE` statements.

### 7.1 Table Summary

| Table | Scale estimate | Key constraint |
|-------|---------------|---------------|
| `crm_memberships` | 1 row per user | `UNIQUE (user_id)` |
| `crm_points_ledger` | ~10 rows per user / year | Append-only |
| `crm_points_summary` | 1 row per user | Cache; must stay in sync with ledger |
| `crm_vouchers` | ~100 / year | `UNIQUE (code)` |
| `crm_voucher_claims` | N users × M vouchers | `UNIQUE (voucher_id, user_id)` |
| `crm_notif_subs` | 1 row per user | `UNIQUE (user_id)` |
| `crm_notification_log` | N users × M vouchers | `UNIQUE (voucher_id, user_id)` |

### 7.2 Key Indexes

```sql
-- Points history queries
INDEX idx_user_created ON crm_points_ledger (user_id, created_at DESC);

-- Voucher status + expiry filter
INDEX idx_status_expiry ON crm_vouchers (status, expires_at);
```

---

## 8. API Reference

### 8.1 REST API

All routes are namespaced under `/wp-json/zippy-crm/v1/`.

**Authentication:** WordPress cookie auth + nonce. Every request must send the `X-WP-Nonce` header (value comes from `wpApiSettings.nonce`, or — for our custom mounts — from `window.zippyCrm.nonce`, generated by `wp_create_nonce('wp_rest')` and exposed via `\ZippyCrm\Core\Assets::rest_settings()`).

**Permission callbacks:**
- Customer routes — `is_user_logged_in()`
- Admin routes — `current_user_can('manage_woocommerce')`

**Error envelope (WP REST default):**
```json
{ "code": "already_claimed", "message": "You have already claimed this voucher", "data": { "status": 409 } }
```

#### Membership

| Method | Path                | Purpose                                        |
|--------|---------------------|------------------------------------------------|
| GET    | `/membership/me`    | Current user's membership + tier progress      |

#### Points

| Method | Path             | Purpose                                                   |
|--------|------------------|-----------------------------------------------------------|
| GET    | `/points/me`     | Summary: balance, total earned, total redeemed, $ value   |
| GET    | `/points/ledger` | Paginated transaction history (`page`, `per_page`)        |
| POST   | `/points/redeem` | Redeem N points → returns coupon code, discount, expiry   |

**`POST /points/redeem`** — request:
```json
{ "points": 100 }
```
Constraint: `points` must be a multiple of `ZIPPY_CRM_MIN_REDEMPTION` (20) and ≤ current balance.

Response:
```json
{
  "coupon_code": "CRM-RDM-A1B2C3",
  "discount":    5.00,
  "expires":     "2026-01-02 23:59:59"
}
```

#### Vouchers (customer)

| Method | Path                          | Purpose                                |
|--------|-------------------------------|----------------------------------------|
| GET    | `/vouchers`                   | Active vouchers the user hasn't claimed |
| GET    | `/vouchers/claims`            | The user's own claimed vouchers         |
| POST   | `/vouchers/{id}/claim`        | Claim a voucher                         |

**`POST /vouchers/{id}/claim`** — response:
```json
{ "code": "SUMMER25", "message": "Voucher claimed successfully!" }
```

Error codes (returned as the standard WP REST `code` field):
- `voucher_inactive`   — Voucher is no longer active
- `voucher_expired`    — Voucher has expired
- `quota_exceeded`     — No remaining uses
- `already_claimed`    — You have already claimed this voucher
- `account_suspended`  — Your account is currently suspended

#### Vouchers (admin)

| Method | Path                       | Purpose          |
|--------|----------------------------|------------------|
| GET    | `/admin/vouchers`          | List + filter    |
| POST   | `/admin/vouchers`          | Create (`draft`) |
| PUT    | `/admin/vouchers/{id}`     | Update / publish / pause / resume |
| DELETE | `/admin/vouchers/{id}`     | Delete (draft only) |

#### Notifications

| Method | Path                            | Purpose                            |
|--------|---------------------------------|------------------------------------|
| GET    | `/notifications/preferences`    | Read current opt-in state          |
| PUT    | `/notifications/preferences`    | Update opt-in state                |

**`PUT /notifications/preferences`** — request:
```json
{ "subscribe_vouchers": true, "subscribe_points": false }
```

### 8.1.1 Frontend usage

```js
// assets/src/js/shared/api.js
import { api } from "@/js/shared/api";

await api.get("/points/me");
await api.post("/points/redeem", { points: 100 });
await api.post(`/vouchers/${id}/claim`);
await api.put("/notifications/preferences", { subscribe_vouchers: true, subscribe_points: false });
```

The shared client reads `window.zippyCrm.{root,nonce}` (set by `Assets::rest_settings()` and printed inline before the bundle loads) and attaches `X-WP-Nonce` automatically.

### 8.2 WP Cron Events

| Event hook | Schedule | Handler |
|-----------|----------|---------|
| `crm_dispatch_voucher_notifications` | One-time per batch | `CRM_Notif_Engine::dispatch_batch()` |
| `crm_retry_failed_notifications` | Hourly | `CRM_Notif_Engine::retry_failed()` |
| `crm_check_membership_upgrades` | Daily | `CRM_Membership_Manager::check_upgrades()` |
| `crm_expire_old_voucher_claims` | Daily | `CRM_Claim_Handler::expire_old_claims()` |

### 8.3 Custom Actions & Filters

#### Actions

```php
do_action( 'crm_points_awarded',          $user_id, $points, $order_id );
do_action( 'crm_points_redeemed',         $user_id, $points, $coupon_code );
do_action( 'crm_voucher_published',       $voucher_id );
do_action( 'crm_voucher_claimed',         $voucher_id, $user_id );
do_action( 'crm_membership_level_changed', $user_id, $old_level, $new_level );
```

#### Filters

```php
// Override the points earn multiplier
$multiplier = apply_filters( 'crm_points_earn_multiplier', 1, $user_id, $order );

// Override the redemption rate (points per $1)
$rate = apply_filters( 'crm_points_redemption_rate', 20, $user_id );

// Add custom pre-claim validation errors
$errors = apply_filters( 'crm_pre_claim_voucher', [], $voucher_id, $user_id );

// Modify voucher notification email content
$content = apply_filters( 'crm_voucher_notification_content', $content, $voucher_id, $user_id );
```

---

## Appendix: Error Handling Checklist

For every feature, verify that the following edge cases are handled:

- [ ] User is not logged in (`is_user_logged_in()` check)
- [ ] Nonce is missing, invalid, or expired
- [ ] User has no `crm_memberships` row (created before plugin was installed)
- [ ] `crm_points_summary` is out of sync with the ledger → log a warning and trigger recalculate
- [ ] WC coupon code already exists when creating from a voucher
- [ ] Database INSERT fails due to a `UNIQUE KEY` race condition (double-click)
- [ ] WP Cron is not running (production must use a real crontab)
- [ ] Email delivery fails → set `crm_notification_log.status = 'failed'` for retry
