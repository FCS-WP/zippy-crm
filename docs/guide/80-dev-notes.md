# Dev notes

> **Audience**: developers, integrators, and technical staff. Everything else in this guide is written for non-technical admins. This page intentionally surfaces the underlying tables, REST endpoints, hooks, and command-line commands that the user-facing pages keep hidden.

## Data model

### Membership

`crm_memberships` — one row per WP user.

| Column | Notes |
|---|---|
| `user_id` | UNIQUE FK → `wp_users.ID`, ON DELETE CASCADE |
| `membership_level` | FK-by-slug → `crm_tiers.slug` |
| `status` | enum: `active` / `suspended` / `expired` |
| `joined_at`, `expires_at` | UTC `DATETIME` |

`crm_tiers` — tier definitions. Slug is PK and immutable (it's the FK target on every membership row). Rename via `label`, never `slug`.

### Points

`crm_points_ledger` — append-only history. **Never UPDATE or DELETE rows.** Corrections are added as new `adjust` entries.

| Column | Notes |
|---|---|
| `type` | enum: `earn` / `redeem` / `adjust` / `expire` (reserved) / `pending_redeem` |
| `points` | signed INT — positive = credit, negative = debit |
| `reserved_points` | for `pending_redeem` rows only — locked points before settlement |
| `pending_status` | for `pending_redeem` rows: `active` / `consumed` / `expired` |
| `order_id` | nullable FK → `wp_wc_orders.id` (HPOS) |

`crm_points_summary` — derived per-user balance cache. The invariant:

```
crm_points_summary.balance == SUM(points) FROM crm_points_ledger WHERE user_id = X AND type IN ('earn','redeem','adjust')
```

If they diverge, `Zippy CRM → Points → Recalculate all` rebuilds the cache from the ledger. Ledger is authoritative.

### Vouchers

`crm_vouchers` — voucher definitions. Each one syncs to a real WooCommerce coupon (`shop_coupon` post) when published. The voucher's `code` mirrors the WC coupon code 1:1.

`crm_voucher_codes` (v1.10+) — per-slot rows for multi-code campaigns. Single-code vouchers don't use this table.

`crm_voucher_claims` — UNIQUE (`voucher_id`, `user_id`) is the authoritative double-claim guard. The plugin relies on `$wpdb->last_error` to detect duplicate claims rather than a pre-flight SELECT (avoids the TOCTOU window under double-click).

| Column | Notes |
|---|---|
| `status` | `claimed` / `used` / `expired` |
| `code_id` | FK → `crm_voucher_codes.id` for multi-code; NULL for single-code |
| `revocation_reason` | `cascade_coupon` / `tier_downgrade` / `admin_revoke` / NULL |

### Notifications

`crm_notif_subs` — per-user opt-in flags (vouchers / points). One row per user.

`crm_notification_log` — UNIQUE (`voucher_id`, `user_id`) prevents duplicate sends across batch retries. Statuses: `queued` / `sent` / `failed`.

### Audit

`crm_audit_log` — append-only. `meta_json` holds structured before/after diff, reason text, etc. No automatic pruning.

## Order meta keys

Order-level state (HPOS — accessed via `$order->get_meta()`, never `get_post_meta()`):

| Meta key | Set by | Purpose |
|---|---|---|
| `_zc_points_awarded` | `PointsEngine::award_for_order` | Idempotency guard — set when order has been awarded; second hook fire is a no-op |
| `_zc_points_applied` | `PointsTender::persist_to_order` | Points the customer applied at checkout (copied from session) |
| `_zc_points_settled` | `PointsTender::settle_for_order` | Idempotency marker after debit |
| `_zc_points_refunded` | `PointsTender::credit_back_on_refund` | Cumulative credit-back across partial refunds |
| `_zc_points_reverted` | `PointsTender::revert_for_order` | Idempotency marker for cancel/fail revert |

## REST endpoints

Base: `/wp-json/zippy-crm/v1`. Routes registered declaratively in [src/Core/routes.php](../src/Core/routes.php).

### Customer-facing (auth: `read`)

| Method | Path | Purpose |
|---|---|---|
| GET | `/membership/me` | Current user's membership + stats + tier ladder + points/voucher counts |
| GET | `/points/me` | Balance summary |
| GET | `/points/ledger` | Paginated history |
| GET | `/points/applicable` | Cart context for the tender widget |
| POST | `/points/apply` | Apply N points to current cart |
| DELETE | `/points/apply` | Clear applied points |
| GET | `/vouchers` | Available vouchers for the current user |
| GET | `/vouchers/claims` | Their active claims |
| GET | `/vouchers/claims/history` | Used / expired / revoked claims (paginated) |
| POST | `/vouchers/{id}/claim` | Claim a voucher |
| GET | `/notifications/preferences` | Their opt-in flags |
| PUT | `/notifications/preferences` | Update opt-in flags |

### Admin-facing (auth: `manage_woocommerce`)

| Method | Path | Purpose |
|---|---|---|
| GET / POST / PUT / DELETE | `/admin/vouchers[/{id}]` | Voucher CRUD |
| POST | `/admin/vouchers/{id}/{publish\|pause\|resume\|duplicate}` | State transitions |
| GET | `/admin/vouchers/{id}/claims`, `/codes` | Drawer data |
| GET / POST | `/admin/members[/{user_id}/{level\|status\|points}]` | Member management |
| GET / POST / PUT / DELETE | `/admin/tiers[/{slug}]` | Tier CRUD |
| GET / POST | `/admin/points/{summary\|ledger\|recalculate-all}` | Points admin |
| GET | `/admin/reports/{members-per-day\|points-activity\|voucher-claims}` | Charts |
| GET | `/admin/audit` | Audit log search |
| GET / PUT | `/admin/settings/points` | Points settings |
| GET / PUT | `/admin/onboarding/state` | Onboarding wizard state |

## Common diagnostic commands

Run inside the container as `www-data` (replace `USER_ID_HERE` with the actual user ID):

```bash
# Get a customer's points balance
wp eval 'echo \ZippyCrm\Services\PointsEngine::get_balance( USER_ID_HERE );'

# Force tier re-evaluation for one user
wp eval '\ZippyCrm\Services\MembershipService::evaluate_tier_upgrade( USER_ID_HERE );'

# Inspect the cached membership row for one user
wp eval 'print_r( \ZippyCrm\Services\MembershipService::get_for_user( USER_ID_HERE ) );'

# Inspect notification log for a specific (voucher, user)
wp db query "SELECT * FROM \$(wp db prefix)crm_notification_log WHERE voucher_id = X AND user_id = Y;"

# List scheduled cron events
wp cron event list | grep crm_

# Manual installer run — creates missing tables, adds missing columns. Idempotent.
wp eval '\ZippyCrm\Core\Installer::maybe_upgrade();'

# Compare PHP-side schema version vs DB-recorded version
wp eval 'echo "PHP: " . ZIPPY_CRM_VERSION . " | DB: " . get_option("zippy_crm_db_version");'

# Force schema migration to re-run (after the version was bumped but didn't auto-fire)
wp eval 'delete_option("zippy_crm_db_version"); \ZippyCrm\Core\Installer::maybe_upgrade();'
```

## WooCommerce hooks the plugin listens to

```php
woocommerce_created_customer       → MembershipService::on_customer_created + PointsSummary seed + opt-in save
woocommerce_order_status_processing → MembershipService::evaluate_tier_upgrade + PointsEngine::award_for_order + ClaimHandler::consume_for_order + PointsTender::settle_for_order
woocommerce_order_status_completed  → same chain (idempotent re-fire)
woocommerce_order_status_cancelled / failed → PointsTender::revert + voucher slot release
woocommerce_order_refunded         → PointsTender::credit_back_on_refund
woocommerce_cart_calculate_fees    → PointsTender::add_fee
woocommerce_checkout_create_order  → PointsTender::persist_to_order
delete_user                        → cascade cleanup
```

## Filters available

| Filter | Args | Purpose |
|---|---|---|
| `crm_points_redemption_rate` | `(int $rate, int $user_id)` | Override the points-per-dollar rate per user |
| `crm_points_fee_taxable` | `(bool $taxable, int $points, int $user_id)` | Make the points-redemption fee taxable |
| `zippy_crm_conflicting_plugin` | `(string $slug)` | Override the EPOS CRM coexistence-check slug |

## Cron events

| Hook | Purpose | Default schedule |
|---|---|---|
| `crm_dispatch_voucher_notifications` | Send next batch of voucher emails | One-shot, re-scheduled per batch (5 min interval) |
| `crm_retry_failed_notifications` | Retry rows in `failed` status | Hourly |

## Logging

Plugin emits `[zippy-crm]` prefixed lines via `error_log()` for diagnostic situations (race-condition guards, unexpected NULL returns from WC, etc.). Enable WP debug log in `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Then `tail -f wp-content/debug.log | grep zippy-crm`.

## Where the source lives

| Concern | Location |
|---|---|
| Schema | `src/Database/Schema/*.sql` |
| Reusable queries | `src/Database/Queries/{domain}/{verb_noun}.sql` |
| Models | `src/Models/` (data access only — no business logic) |
| Services | `src/Services/` (business logic, hook handlers) |
| REST controllers | `src/Controllers/Rest/` |
| Admin page renderers | `src/Controllers/Admin/` (mostly thin React mount points) |
| React admin | `assets/src/js/admin/` |
| React account | `assets/src/js/account/` |
| Manual QA scripts | `tests/manual/zc-test-*.php` (run via `wp eval-file`) |

## Architecture rules

See [`.claude/rules/`](../../.claude/rules/) in the repo for the binding conventions:

- **`woocommerce-hpos.md`** — HPOS-only; never `get_post_meta` for orders, never `WP_Query` for `shop_order`
- **`sql-files.md`** — anything beyond a one-line WHERE goes in a `.sql` file
- **`file-size.md`** — 500-line cap per file
- **`shared-components.md`** — promote to `shared/` on the third use, not the first
- **`performance.md`** — where perf actually matters (DB N+1, hot reads, cron idempotency)
- **`git-workflow.md`** — branch + commit + PR conventions for the team
