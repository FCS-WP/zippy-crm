# Zippy CRM — Implementation TODO

> **Source of truth** for what's done and what's next.
> When you finish a task, change `[ ]` → `[x]` and add a one-line note if context is non-obvious (e.g. "needed dbDelta workaround for FK").
> When you start a task, leave a `[~]` (in progress) marker so we don't double-pick the same item.
>
> **Legend:** `[ ]` todo · `[~]` in progress · `[x]` done · `[!]` blocked
>
> See [FEATURE_SPEC.md](./FEATURE_SPEC.md) for behavior, [CLAUDE.md](../CLAUDE.md) for architecture, and [.claude/rules/](../.claude/rules/) for the rules every change must follow.

---

## 0. Foundation

- [x] Plugin scaffold — entry, autoloader, namespace `ZippyCrm\`
- [x] Domain-grouped MVC layout (`Core / Models / Services / Controllers / Hooks / Views`)
- [x] HPOS compatibility declared
- [x] Auto-flush rewrites when endpoint list hash changes
- [x] Vite + Tailwind (`zc-` prefix) + React Query, two entries (`admin`, `account`)
- [x] Manifest-driven enqueue (`Assets::enqueue_account/_admin`) — module type, conditional, inline `window.zippyCrm`
- [x] Mock layer (`shared/mocks/`) with `USE_MOCKS` switch in `shared/api.js`
- [x] Shared primitives so far: `card`, `badge`, `progress`, `skeleton`, `EmptyState`
- [x] Mount-point CSS reset (`.zippy-crm-mount`) — survives parent theme's `display:flex` content container
- [x] Custom My Account icons via parent's `ai_zippy_account_nav_icons` filter
- [ ] Composer-managed PSR-4 autoload (currently hand-rolled — fine for now)
- [x] `Support/` base classes: `RestResponse`, `Cache`, `DateTimeHelper` (Validator/Repository deferred until 3rd use per shared-components rule)
- [x] `Database/QueryLoader.php` + first real schema file (Membership)
- [ ] CI: phpcs + phpunit + ESLint config
- [ ] WC dependency check (deactivate plugin gracefully if WC missing)

---

## 1. Membership

### UI (customer)
- [x] Mock data shape (`shared/mocks/membership.js`)
- [x] `MembershipTab` + sub-components (`LevelCard`, `TierProgress`, `StatsGrid`, `MembershipSkeleton`)
- [x] Wire to real REST (per-route opt-in via `LIVE_ROUTES` in `mocks/index.js`)
- [ ] Empty/edge states: brand-new user (no orders), top-tier user (no `next_tier`), suspended

### Database
- [x] `Schema/crm_memberships.sql` (`{prefix}` + `{charset_collate}` placeholders, no inline FK / ENUM)
- [x] `Installer::run()` + `maybe_upgrade()` (auto-runs on version bump)

### Model + Service
- [x] `Models/Membership` — `find_by_user`, `create`, `update_level`, `update_status`, `delete_for_user`
- [x] `Services/MembershipService` — seeding, tier evaluator, multiplier, next-tier progress
- [x] Tier rules per FEATURE_SPEC §1.2 (vip is sticky — never auto-set or auto-removed)

### REST
- [x] `GET /membership/me` matches mock shape exactly
- [x] Permission callback: `is_user_logged_in()`
- [x] Auto-seeds missing rows; self-heals pre-existing users (opportunistic tier upgrade on read)

### Hooks
- [x] `woocommerce_created_customer` → seed membership row
- [x] `woocommerce_order_status_completed` → re-evaluate tier
- [x] `delete_user` → cleanup row + cache
- [ ] Daily cron `crm_check_membership_upgrades` for backfills (optional — read endpoint self-heals)

### Performance
- [x] Cache `membership/me` per-user via `Support/Cache`, key `zc:v1:membership:{user_id}`
- [x] Invalidate on tier change, status change, registration, user delete

---

## 2. Points

### UI (customer)
- [x] Mock data shape (`shared/mocks/points.js`)
- [x] `PointsTab` + `BalanceCard` + `RedeemForm` + `LedgerTable` + `PointsSkeleton`
- [x] Wire to real REST (`/points/me`, `/points/ledger`, `/points/redeem` in `LIVE_ROUTES`)
- [x] Loading + empty + error states
- [ ] Optimistic UI for redeem (current implementation invalidates + refetches; fine for now)

### Database
- [x] `Schema/crm_points_ledger.sql` — append-only, `idx_user_created`, `idx_order`
- [x] `Schema/crm_points_summary.sql` — `balance` is `INT` (signed, drift-detection)

### Model + Service
- [x] `Models/PointsLedger` — `insert`, `get_paginated`, `recalculate` (all via `.sql` files)
- [x] `Models/PointsSummary` — `find`, `apply_delta` (atomic upsert), `set`, `delete_for_user`
- [x] `Services/PointsEngine` — `get_summary`, `get_balance`, `award_for_order`, `redeem`, `recalculate_balance`
- [x] `award_for_order` is idempotent via `_zc_points_awarded` order meta

### REST
- [x] `GET /points/me`
- [x] `GET /points/ledger?page=&per_page=`
- [x] `POST /points/redeem` — validates min, multiple-of-rate, balance, suspended status
- [x] Custom error codes: `redeem_too_small`, `redeem_not_multiple`, `insufficient_balance`, `account_suspended`

### Hooks
- [x] `woocommerce_order_status_completed` → tier eval + `award_for_order`
- [x] `woocommerce_created_customer` → seed `crm_points_summary`
- [x] `delete_user` → drop summary, keep ledger for audit
- [x] Filters: `crm_points_earn_multiplier`, `crm_points_redemption_rate`
- [x] Actions: `crm_points_awarded`, `crm_points_redeemed`

### Performance
- [x] Cache `points:summary:{user_id}` via `Support/Cache`; invalidate on every credit/debit
- [x] Reads come from `crm_points_summary` (never `SUM(ledger)`)
- [x] `idx_user_created` covers paginated ledger query
- [x] Order subtotal computed via `WC_Order` API (HPOS-safe)

### Bonus (uncovered)
- [x] `QueryLoader` strips `--` SQL comments globally so `$wpdb->prepare()` works on commented `.sql` files

### Spec compliance fix — reserve-on-click, debit-on-completion
- [x] Schema: added `reserved_points` + `pending_status` cols + `idx_pending` index (DB v1.2.0)
- [x] New ledger type `pending_redeem` (points=0, holds reserved amount + status)
- [x] Coupon meta: `_zc_redemption`, `_zc_points_reserved`, `_zc_user_id`
- [x] `redeem()` now reserves only — no balance debit until coupon is used
- [x] `consume_redemptions_for_order()` debits on `woocommerce_order_status_completed`
- [x] Three-layer idempotency: order meta + coupon meta + conditional UPDATE
- [x] `available_balance` = balance − reserved (computed live, not cached)
- [x] New error code `insufficient_available` with breakdown message
- [x] React hero shows available as headline; reserved sub-line when non-zero
- [x] Ledger UI renders `pending_redeem` rows with coupon chip + amber styling + status label
- [ ] Daily cron `crm_expire_old_pendings` to flip expired pendings (deferred — SQL filter handles correctness)

---

## 3. Vouchers

### UI (customer)
- [x] Mock data shape (`shared/mocks/vouchers.js`) — available + claims
- [ ] `VouchersTab` with two sub-tabs: Available · My Claims
- [ ] `VoucherCard` (available) — title, value, expiry, Claim button
- [ ] `ClaimCard` (claimed) — code (copy button), status pill, used/expired/active
- [ ] Optimistic claim mutation + error toast (use error codes from spec §3.5)

### Database
- [ ] `Schema/crm_vouchers.sql` — `idx_status_expiry`
- [ ] `Schema/crm_voucher_claims.sql` — `UNIQUE (voucher_id, user_id)`

### Model + Service
- [ ] `Models/Voucher` — `find`, `find_by_code`, `list_active_unclaimed`, `increment_uses`
- [ ] `Models/VoucherClaim` — `claim` (handles UNIQUE race), `find_for_user`, `mark_used`
- [ ] `Services/VoucherService` — `create`, `publish` (creates WC coupon), `pause`, `resume`
- [ ] `Services/ClaimHandler::validate()` order: active → expiry → quota → not-claimed → not-suspended

### REST
- [ ] `GET /vouchers` (customer-facing, available unclaimed)
- [ ] `GET /vouchers/claims`
- [ ] `POST /vouchers/{id}/claim`
- [ ] Admin CRUD: `GET|POST /admin/vouchers`, `PUT|DELETE /admin/vouchers/{id}`

### Hooks
- [ ] On voucher publish → fire `crm_voucher_published` → kick off notification batches
- [ ] On order completed → if claimed-coupon was applied, mark claim `used` + bump `uses_count`
- [ ] Daily cron `crm_expire_old_voucher_claims`

### Performance
- [ ] List query uses `idx_status_expiry`
- [ ] `EXPLAIN` the available-unclaimed JOIN before merging
- [ ] Cache "available" list per-user; invalidate on publish/pause/claim

---

## 4. Notifications

### UI (customer)
- [x] Mock data shape (`shared/mocks/notifications.js`)
- [ ] `NotificationsTab` — two switches (vouchers, points), Save button
- [ ] Optimistic update + revert on error

### Registration form
- [ ] `woocommerce_register_form` action: render two checkboxes (vouchers, points)
- [ ] `woocommerce_created_customer`: read POST + insert `crm_notif_subs` row

### Database
- [ ] `Schema/crm_notif_subs.sql`
- [ ] `Schema/crm_notification_log.sql` — `UNIQUE (voucher_id, user_id)`

### Model + Service
- [ ] `Models/NotifSub` — `get_for_user`, `upsert`, `query_subscribed_user_ids` (via `.sql` file)
- [ ] `Models/NotificationLog` — `log_sent`, `log_failed`, `find_failed_for_retry`
- [ ] `Services/SubsManager` — render + save opt-in fields, update prefs
- [ ] `Services/NotifEngine`:
  - [ ] `queue_voucher_published($voucher_id)` — splits into `ZIPPY_CRM_EMAIL_BATCH_SIZE` chunks
  - [ ] `dispatch_batch` (cron handler) — sends + writes log idempotently
  - [ ] `retry_failed` (hourly cron) — up to 3 attempts

### REST
- [ ] `GET /notifications/preferences`
- [ ] `PUT /notifications/preferences`

### Email template
- [ ] `src/Views/emails/voucher-notification.php` — subject, body, CTA, unsubscribe link
- [ ] Self-contained (no shared header/footer), inline styles, table layout
- [ ] Unsubscribe link routes to `/my-account/crm-notifications/`

### Performance
- [ ] Subscriber query uses `NOT IN (notification_log)` to skip already-sent
- [ ] Batch via `wp_schedule_single_event`, never inline in the publish request
- [ ] `UNIQUE` constraint is the idempotency guard — never bypass it

---

## 5. Admin Panel

### Bootstrap
- [x] Menu registration (`Controllers/Admin/AdminMenu`) + 4 mount controllers (stubs)
- [ ] `enqueue_admin` already loads `dist/admin.js` on `zippy-crm*` pages — verify after first real panel

### Members
- [ ] `MembersPanel` React app — list + filter (level/status/date), row actions
- [ ] CSV export endpoint (`GET /admin/members/export`)
- [ ] Manual "adjust points" modal → `POST /admin/points/adjust`
- [ ] Manual "change level" modal → `POST /admin/membership/{user_id}/level`
- [ ] Suspend / activate toggle

### Vouchers
- [ ] `VouchersPanel` — list, status filters, Quick Stats bar
- [ ] Create/Edit form (drawer or new page)
- [ ] Publish / Pause / Resume / Duplicate / Delete (draft-only) actions
- [ ] Claims subview per voucher

### Points
- [ ] `PointsPanel` — summary cards (issued / redeemed / outstanding)
- [ ] Recent transactions table
- [ ] Manual adjust form (user search → type → amount → reason)
- [ ] Bulk recalculate balances action (admin-only, confirms count)

### Reports
- [ ] `ReportsPanel` — new members chart, points activity chart, voucher usage chart
- [ ] Date-range picker
- [ ] Lazy-load chart lib only when this panel mounts (perf rule)

---

## 6. Cross-cutting

### Security
- [ ] Every REST route has the right permission callback (user vs `manage_woocommerce`)
- [ ] All input goes through `Support/Validator` — no inline `is_int` chains
- [ ] No raw `$wpdb->query()` with concat — all SQL via `QueryLoader` + `prepare()`

### Performance check (every feature, before "done")
- [ ] No DB query inside a loop in any code path
- [ ] Every WHERE/ORDER BY column has an index
- [ ] Hot reads route through `Support/Cache`; writes invalidate
- [ ] Conditional enqueue verified
- [ ] Query Monitor pass on the customer-facing path

### Tests
- [ ] PHPUnit harness with WC + HPOS enabled
- [ ] Membership tier evaluator unit tests (boundary conditions: 4 vs 5 orders, $499 vs $500)
- [ ] Points award/redeem round-trip — ledger ↔ summary equality
- [ ] Claim race — two concurrent inserts must surface as `already_claimed`
- [ ] Notification batch idempotency — re-running same batch never double-sends

### DevX
- [ ] `wp eval` seeders: `seed_members(N)`, `seed_vouchers(N)`, `seed_orders(N)`
- [ ] README in plugin root with quickstart + endpoint URLs

---

## 7. Pre-launch

- [ ] Mock layer disabled (`USE_MOCKS = false`)
- [ ] Real WC test on staging with HPOS enabled
- [ ] Email deliverability test (SPF/DKIM via WP Mail SMTP or equivalent)
- [ ] Production cron runs via system crontab, not `wp-cron.php`
- [ ] Rollback plan: deactivate plugin must NOT delete tables (only `delete_plugin` should — and even then, opt-in)
