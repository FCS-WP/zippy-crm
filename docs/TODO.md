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

### Spec compliance fix — reserve-on-click, debit-on-completion (v1.2.0)
- [x] Schema: added `reserved_points` + `pending_status` cols + `idx_pending` index
- [x] New ledger type `pending_redeem` (points=0, holds reserved amount + status)
- [x] Coupon meta: `_zc_redemption`, `_zc_points_reserved`, `_zc_user_id`
- [x] `redeem()` now reserves only — no balance debit until coupon is used
- [x] `consume_redemptions_for_order()` debits on `woocommerce_order_status_completed`
- [x] Three-layer idempotency: order meta + coupon meta + conditional UPDATE
- [x] `available_balance` = balance − reserved (computed live, not cached)
- [x] New error code `insufficient_available` with breakdown message
- [x] React hero shows available as headline; reserved sub-line when non-zero
- [x] Ledger UI renders `pending_redeem` rows with coupon chip + amber styling + status label
- [ ] Daily cron `crm_expire_old_pendings` (irrelevant after v1.8.0 — flow retired)

### Conceptual cleanup — points as cash tender, not coupon (v1.8.0)
- [x] Migrated points from coupon-based redemption to cart-tender model. Vouchers stay coupons; points become a negative fee on the order.
- [x] `Services/PointsTender` — owns the WC session apply/clear, the `cart_calculate_fees` hook, the `checkout_create_order` persist, and the `order_status_completed` settle (priority 30, after award)
- [x] REST: `GET /points/applicable`, `POST /points/apply`, `DELETE /points/apply`
- [x] Cart-page React widget at `assets/src/js/cart/PointsTenderWidget.jsx` (separate Vite entry, conditionally enqueued only on `is_cart()` for logged-in users)
- [x] `woocommerce_before_cart_totals` prints the mount div
- [x] **Refund handling**: `woocommerce_order_refunded` listener credits points back proportionally to the refunded fraction (cumulative-aware via `_zc_points_refunded` meta)
- [x] Tax behavior: post-tax (gift-card semantics) by default; `crm_points_fee_taxable` filter for jurisdiction overrides
- [x] Idempotent settle via `_zc_points_settled` order meta — replay-safe
- [x] One-time data migration: legacy `pending_redeem` rows flipped to `expired`; legacy unused coupons left to expire naturally
- [x] `PointsEngine::redeem()` returns 410 `redeem_deprecated` so stale browser tabs get a clean error
- [x] Legacy code removed: `consume_redemptions_for_order`, `generate_coupon_code`, `debit()`, the four `COUPON_META_*` + `META_REDEEMED_CODES` + `COUPON_PREFIX` constants
- [x] `get_full_summary` collapsed: `available == balance`, `reserved == 0` (kept as fields for one-deploy backward compat with old React bundles)
- [x] Customer Points tab: `RedeemForm` retired; replaced with `RedeemCTA` linking to the cart; hero hero shows just `balance`
- [x] Verified end-to-end: apply → fee on cart, complete → ledger debits, refund 50% → 50 pts credit back, replay completed hook → no double-debit

---

## 3. Vouchers

### UI (customer)
- [x] Mock data shape (`shared/mocks/vouchers.js`)
- [x] `VouchersTab` with two sub-tabs (Available / My Claims) and pill-toggle
- [x] `VoucherCard` (available) — gradient discount stripe, claim button, copy-code success
- [x] `ClaimsList` + `ClaimRow` — status badge, copy button when usable, sorted by status
- [x] Wire to real REST (`/vouchers`, `/vouchers/claims`, `/vouchers/{id}/claim` via `LIVE_PREFIXES`)
- [x] Empty + error states for both sub-tabs

### Database
- [x] `Schema/crm_vouchers.sql` — `uq_code`, `idx_status_expiry`
- [x] `Schema/crm_voucher_claims.sql` — `UNIQUE (voucher_id, user_id)` + `idx_user`/`idx_voucher`

### Model + Service
- [x] `Models/Voucher` — `find`, `find_by_code`, `list_available_for_user`, `create`, `update_status`, `increment_uses`
- [x] `Models/VoucherClaim` — `claim` (UNIQUE-collision aware), `list_for_user`, `find_for_user`, `mark_used`
- [x] `Services/VoucherService` — `publish`, `pause`, `resume`, `sync_wc_coupon` (idempotent)
- [x] `Services/ClaimHandler` — validate (per spec §3.5 order), `claim`, `consume_for_order`
- [x] `crm_pre_claim_voucher` filter wired for extension

### REST
- [x] `GET /vouchers` — available-unclaimed list
- [x] `GET /vouchers/claims` — user's claims with voucher metadata joined
- [x] `POST /vouchers/{id}/claim` — auto-applies to cart if one exists, else returns code
- [x] Error codes per spec: `voucher_inactive`, `voucher_expired`, `quota_exceeded`, `already_claimed`, `account_suspended`
- [x] HTTP status mapping: 400/403/409/410 by code
- [x] Admin CRUD — `list/create/update/delete` + `publish/pause/resume/duplicate` + `claims` (see §5 Vouchers)

### Hooks
- [x] `woocommerce_order_status_completed` → `ClaimHandler::consume_for_order` (mark used + bump uses_count)
- [x] `delete_user` → cleanup user's claims + cache
- [x] `crm_voucher_published` action fires on publish — Notifications listener TBD
- [ ] Daily cron `crm_expire_old_voucher_claims` (deferred — claims expire via voucher's `expires_at` filter in SQL)

### Performance
- [x] List query joins claims via `LEFT JOIN ... uq_claim` — single seek per row
- [x] All filters use `idx_status_expiry` + `uq_code` + `idx_user`
- [x] No N+1 on My Claims (single JOIN'd query)
- [ ] Cache "available" list per-user (deferred — list is small, 1-3 indexed seeks; revisit if it shows up in Query Monitor)

---

## 4. Notifications

### UI (customer)
- [x] Mock data shape (`shared/mocks/notifications.js`)
- [x] `NotificationsTab` — two channel toggles, Save button, dirty/saved hint
- [x] `ChannelToggle` (uses new `shared/ui/switch.jsx` primitive)
- [x] `NotificationsSkeleton` for loading state
- [x] "All channels off" amber notice when both switches are off
- [x] Wire to real REST (`GET|PUT /notifications/preferences` in `LIVE_ROUTES`)

### Registration form
- [x] `woocommerce_register_form` → `SubsManager::render_optin_field()` (both checked by default per spec §4.1)
- [x] `woocommerce_created_customer` → `SubsManager::on_customer_created()` reads POST + upserts `crm_notif_subs`
- [x] Sticky checkbox state on form redisplay after error

### Database
- [x] `Schema/crm_notif_subs.sql` — PK = user_id, idx_subscribed_vouchers, idx_subscribed_points
- [x] `Schema/crm_notification_log.sql` — UNIQUE (voucher_id, user_id), idx_status_attempts, attempts + last_error columns

### Model + Service
- [x] `Models/NotifSub` — `get_for_user` (returns DEFAULTS if missing), `upsert` (atomic UPSERT), `delete_for_user`
- [x] `Services/SubsManager` — `get_for_user` (cached), `update_preferences`, `render_optin_field`, `on_customer_created`
- [x] `Models/NotificationLog` — `insert_queued` (UNIQUE-collision-aware), `mark_sent`, `mark_failed`, `find_failed_for_retry`, `delete_for_user`
- [x] `Services/NotifEngine` — `on_voucher_published`, `dispatch_batch`, `retry_failed`

### REST
- [x] `GET /notifications/preferences` — returns defaults when no row exists
- [x] `PUT /notifications/preferences` — atomic upsert via SubsManager

### Email template + dispatch
- [x] `src/Views/emails/voucher-notification.php` — self-contained, table-based, inline CSS, no images
- [x] `NotifEngine::dispatch_batch` + `retry_failed` — three idempotency layers
- [x] `crm_voucher_published` listener wired in `Hooks/Cron::register`
- [x] Subscriber query with `NOT IN (notification_log)` filter (`select_unsent_subscribers.sql`)
- [x] Batches staggered by `ZIPPY_CRM_EMAIL_BATCH_INTERVAL` (5 min default)
- [x] Failure path bumps `attempts` + stores `last_error`; hourly retry cron picks them up
- [x] Filter hooks: `crm_voucher_notification_subject`, `crm_voucher_notification_content`
- [x] Action hooks: `crm_voucher_notifications_queued`, `crm_voucher_notifications_dispatched`

### Performance
- [x] Cache `notif_subs:{user_id}` via `Support/Cache`; invalidate on every save + on registration
- [x] PK = user_id (no surrogate id needed for 1-row-per-user table)
- [x] `idx_subscribed_*` indexes ready for the future "who do I email?" batch query
- [x] `idx_status_attempts` covers the retry query
- [x] `wp_mail` runs in cron, never inline in the publish request

---

## 5. Admin Panel

### Bootstrap
- [x] Menu registration (`Controllers/Admin/AdminMenu`) + 4 mount controllers (stubs)
- [x] `enqueue_admin` loads `dist/admin.js` on `zippy-crm*` pages — verified by Vouchers panel

### Members
- [x] `MembersPanel` React app — list + filter (level/status), search by login/email/name, row actions
- [ ] CSV export endpoint (`GET /admin/members/export`) — deferred
- [x] Manual "adjust points" form → `POST /admin/members/{user_id}/points` (credit/debit + reason; refuses negative balance)
- [x] Manual "change level" form → `POST /admin/members/{user_id}/level` (admin can set vip; vip stays sticky)
- [x] Suspend / activate toggle → `POST /admin/members/{user_id}/status`
- [x] Backend: 3 admin SQL files; extended `Membership` + `MembershipService` + `PointsEngine`; 5 REST routes
- [x] Mock layer: admin members + points routes added to `LIVE_ROUTES` / `LIVE_PREFIXES`
- [x] Smoke-tested via `wp eval-file` (16 cases — list/filter/search/level changes incl. vip/suspend/activate/points credit+debit/negative-balance refusal/reason-required/ledger↔summary equality)
- [x] Member detail drawer (joins WC stats: orders, spend, multiplier)

### Vouchers
- [x] `VouchersPanel` — list, status filters, Quick Stats bar
- [x] Create/Edit form (slide-in drawer; `code` locked once created)
- [x] Publish / Pause / Resume / Duplicate / Delete (draft + zero-claims only) actions
- [x] Claims drawer per voucher (joins user data, no N+1)
- [x] Backend: model + service + REST + 9 SQL files; HPOS-safe; first invariant locked into `Voucher::create`
- [x] Mock layer: admin voucher routes added to `LIVE_ROUTES` / `LIVE_PREFIXES`
- [x] Smoke-tested via `wp eval-file` (15 cases, all pass — list/create/dup/bad-payload/update/publish/draft-delete-refusal/pause/resume/duplicate/filter/search/claims/cleanup)

### Vouchers — full WC-coupon parity (v1.9.0)
- [x] **Schema migration**: 12 new columns on `crm_vouchers` (`max_order_amount`, `usage_limit_per_user`, `limit_usage_to_x_items`, `individual_use`, `exclude_sale_items`, `free_shipping`, `email_restrictions`, `product_ids`, `excluded_product_ids`, `product_categories`, `excluded_product_categories`, `allowed_hours`). Array fields stored as JSON-encoded TEXT.
- [x] **Voucher model**: `JSON_FIELDS` const, `encode_json()` (normalises null/empty/[]→null), `decode_json_fields()` for read-time deserialization. `create()` and `update()` whitelists extended; partial update preserves untouched JSON fields.
- [x] **VoucherService::sync_wc_coupon**: pushes every new field onto `WC_Coupon` via the appropriate setter (`set_maximum_amount` / `set_usage_limit_per_user` / `set_limit_usage_to_x_items` / `set_individual_use` / `set_exclude_sale_items` / `set_free_shipping` / `set_email_restrictions` / `set_product_ids` / `set_excluded_product_ids` / `set_product_categories` / `set_excluded_product_categories`); handles null expiry by clearing the WC date.
- [x] **Hour-window enforcement**: new `Hooks/VoucherHourWindow` listens on `woocommerce_coupon_is_valid`, reads voucher `allowed_hours`, throws with a human-readable message ("This voucher is only valid Fri, Sat 18:00 - 21:00.") when out of window. Wrapped windows (across midnight) supported. Site timezone (`wp_timezone()`) honored.
- [x] **REST shape**: `extract_voucher_payload` and `shape_voucher_admin` carry all 12 new fields (JSON columns deserialize to PHP arrays for the React side, encode back on submit).
- [x] **Catalog lookup**: new `Controllers/Rest/CatalogController` + 2 admin-only routes — `GET /admin/catalog/products` and `GET /admin/catalog/categories`. Both accept `search` (string) OR `ids` (csv); ID order preserved so chip-row order matches admin's pick order.
- [x] **VoucherForm refactor**: 4 tabs — General / Restrictions / Limits / Time window — backed by `sections/{Field, Tabs, GeneralSection, RestrictionsSection, LimitsSection, TimeSection, EmailRestrictionsField, HourWindowField, CatalogPickerField}`. Old single-page form retired.
- [x] **CatalogPickerField**: one component, two flavours (`kind="products" | "categories"`). Trigger row shows chips + "Add"; Add opens a Drawer with a debounced search-as-you-type field; checkbox toggles selection. Empty selection = "no restriction".
- [x] **HourWindowField**: master switch + 7-day chips + from/to hour inputs. Detects wrapped windows (00:00 crossing) and zero-day selections, surfaces both as inline notices.
- [x] **EmailRestrictionsField**: chip input with Enter/comma to commit, Backspace-on-empty to remove last; supports literal addresses + WC wildcard syntax (`*@bigco.com`).
- [x] Mock layer: 2 admin catalog routes added to `LIVE_ROUTES`
- [x] Smoke-tested via `wp eval-file` (9 cases — create-with-all-fields, DB-row JSON encoding, partial update preserves siblings, publish round-trip pushes 11 setters to WC coupon, hour-window real coupon throws with correct message, catalog product/category search and bad-query 400, cleanup)

### Points
- [x] `PointsPanel` — summary cards (issued / redeemed / outstanding / members) + outstanding-liability subtitle
- [x] Recent transactions table (joined with users, type filter, pagination, excludes `pending_redeem`)
- [x] Manual adjust form — already shipped on the Members panel (`PointsAdjustForm`); reused there to keep one canonical entry point
- [x] Bulk recalculate balances action — `POST /admin/points/recalculate-all` with confirm + result pill (`processed / drift_corrected / errors`)
- [x] Backend: 4 admin SQL files; new `Services/PointsAdmin` (split from PointsEngine at the admin/customer responsibility seam); 3 REST routes
- [x] Cache: `points:system` key shared between PointsEngine (invalidates on every per-user write) and PointsAdmin (read-through cache)
- [x] Mock layer: admin points routes added to `LIVE_ROUTES`
- [x] Smoke-tested via `wp eval-file` (8 cases — system_summary equals direct SUM; ledger pagination + type filter + bad-filter 400; pending_redeem excluded; recalculate-all on clean dataset reports zero drift; recalculate-all on synthetic-drift dataset corrects the corrupted balance)

### Tiers
- [x] `TiersPanel` — table of tiers (slug, label, multiplier, thresholds, admin-only flag, member count, sort_order) + create/edit drawer + delete
- [x] `TierForm` — slug locked once created; multiplier / thresholds / admin-only switch / sort_order; serves both create and edit
- [x] Auto-color palette (`shared/utils/tierColor.js`) — sort_order → palette slot (zinc/silver/gold/fuchsia/emerald/sky/amber/rose) so new tiers get a sensible default colour with no extra config
- [x] Shared `useTiers()` hook (`shared/hooks/useTiers.js`) — single cached query feeding `TiersPanel` + Members `FilterBar` / `StatsBar` / `LevelChangeForm` / `LevelBadge` (third use → promoted to `shared/`)
- [x] Members admin UI is now fully tier-data-driven — adding "platinum" via the Tiers panel grows StatsBar to 5 cards, adds the option to FilterBar, and shows up in LevelChangeForm with auto-generated note ("2.5× — auto-assigned at 30+ orders or $5000+ spend"). No frontend redeploy needed.
- [x] Top-level submenu — Zippy CRM → Members / Tiers / Vouchers / Points / Reports
- [x] **Backend regression fix**: `MembershipController::admin_list` no longer filters slugs against a hardcoded `['free','silver','gold','vip']` constant — uses `TierRegistry::exists()` so newly-created tiers are accepted by the Members filter immediately.
- [x] Mock layer: `GET /tiers`, `GET|POST /admin/tiers`, `PUT|DELETE /admin/tiers/{slug}` added to `LIVE_ROUTES` / `LIVE_PREFIXES`
- [x] Smoke-tested via `wp eval-file` (12 cases — public list filtered, public list incl. admin-only, admin list w/ member counts, create with full payload, bad slug 400, duplicate slug 409, update, delete-with-members 409, junk-slug filter rejection 400; lowercase round-trip verified separately)

### Reports
- [x] `ReportsPanel` — new members (area), points activity (stacked bars: earned/redeemed/adjusted), voucher claims (line: claimed vs used)
- [x] Date-range picker — 7/30/90-day presets + custom range with date inputs and Apply button
- [x] Lazy-load chart lib only when this panel mounts — `Charts` chunk (Recharts + 3 chart components) split out via `import()`; admin.js stayed at ~46 KB, lazy chunk ~395 KB / 115 KB gzipped, only fetched on Reports tab click
- [x] Backend: 3 admin SQL files; new `Services/ReportsService` with strict YYYY-MM-DD parsing + zero-fill helper; new `Controllers/Rest/ReportsController`; 3 REST routes
- [x] Mock layer: 3 admin reports routes added to `LIVE_ROUTES`
- [x] Smoke-tested via `wp eval-file` (8 cases — default 30-day range, custom 7-day range, voucher claims, bad date format 400, inverted range 400, range-too-wide 400, single-day zero-fill, anonymous 401)

---

## 6. Cross-cutting

### Tier configuration (data-driven tier ladder)
- [x] `Schema/crm_tiers.sql` — slug PK, label, multiplier, threshold_orders, threshold_spend, is_admin_only, sort_order (DB v1.7.0)
- [x] Installer seeds the four canonical tiers (free/silver/gold/vip) on first install — legacy `crm_memberships.membership_level` rows keep working
- [x] `Models/Tier` — `all`, `find`, `insert`, `update` (slug immutable), `delete`, `member_counts`
- [x] `Services/TierRegistry` — single source of truth: `slugs()`, `labels()`, `multiplier_for()`, `compute_for_stats()`, `next_above()`, plus admin CRUD with validation + deletion guards (refuse if members on tier; refuse if last non-admin tier)
- [x] `MembershipService` refactored: removed `TIER_THRESHOLDS` constant, removed hardcoded `'free'/'silver'/'gold'/'vip'` ladder, uses `TierRegistry` everywhere
- [x] `Membership` model: removed `LEVELS / MULTIPLIERS / LABELS` constants, replaced with static methods that delegate to `TierRegistry`
- [x] REST: `GET /tiers` (public, filtered to non-admin tiers), `GET|POST /admin/tiers`, `PUT|DELETE /admin/tiers/{slug}`
- [x] `AuditLogger` listens to `crm_tier_created/updated/deleted` and records when admin-context
- [x] Verified end-to-end: REST CRUD round-trip works, admin events captured in audit log, existing customer endpoints unchanged

### Audit log (admin write actions)
- [x] `Schema/crm_audit_log.sql` — `idx_target_created`, `idx_actor_created`, `idx_event_created` (DB v1.6.0)
- [x] `Models/AuditLog` — `insert`, `get_paginated` with sentinel-pattern filters via `audit/list_paginated.sql` + `audit/count.sql`
- [x] `Services/AuditLogger` — `EVENT_*` constants, listens to existing `crm_*` actions gated on `is_admin_context()`, plus explicit recorder helpers (`record_voucher_*`, `record_points_adjusted`, `record_points_recalculated`)
- [x] REST `GET /admin/audit?event=&actor_id=&target_id=&page=&per_page=` — admin-only, sentinel filtering when 0/empty
- [x] Verified end-to-end: 8 events captured, customer-side `crm_*_changed` actions correctly skipped via context gate
- [ ] Admin session: wire `AuditLogger::record_voucher_*` calls into `VoucherService::create_draft / update / pause / resume / delete / duplicate` and `record_points_adjusted` into the admin points-adjust handler

### Security
- [x] Audit pass on every REST handler (Membership, Points, Vouchers, Notifications) — see findings below
- [x] **H1 (high)**: `Voucher::create()` no longer accepts user-supplied `status` — always inserts as 'draft'. Prevents admin-supplied `status=active` from creating CRM vouchers without a matching WC coupon.
- [x] **H2 (high)**: `Voucher::update()` whitelist no longer includes `status`. Status changes now require `update_status()` (validated enum) or the service-layer publish/pause/resume methods.
- [x] **L2 (low)**: `RouteRegistrar` now `_doing_it_wrong()`s on unknown auth keyword instead of silently denying — typos in `routes.php` surface in the debug log.
- [x] **L4 (medium-low)**: Redeem coupon codes bumped 6→12 random chars (36^6 ≈ 2B → 36^12 ≈ 4.7e18). Length is the primary defense because WC doesn't enforce our `_zc_user_id` meta on apply — a guessed code WOULD work at checkout.
- [x] All REST routes confirmed using correct auth keyword (`user` for customer, `manage_woocommerce` for admin) per `routes.php`
- [x] All SQL goes through `QueryLoader` + `$wpdb->prepare()` — no concat, no raw `$wpdb->query()` with user input
- [ ] `Support/Validator` lifted (deferred — only 1 caller (`VoucherService::validate_payload`); promote on third use per shared-components rule)
- [ ] Rate limiting on `POST /points/redeem` and `POST /vouchers/{id}/claim` (deferred — capability + UNIQUE constraints already prevent abuse beyond DB load)

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
- [x] `wp eval` seeders: `seed_members(N)`, `seed_vouchers(N)`, `seed_orders(N)`, `all()`, `reset()` (Support/Seeder.php)
- [x] `Seeder::seed_qc_fixtures()` — predictable QA accounts (qa-free-1, qa-silver-1, qa-gold-1, qa-vip-1, qa-suspended-1) + 4 named vouchers, idempotent on re-run
- [x] README at plugin root: quickstart, architecture map, dev commands, REST surface, filters/actions, production checklist
- [x] `docs/TEST_CASES.md` — dev-facing manual cases (wp eval setup + DB checks)
- [x] `docs/QC_TEST_CASES.md` — non-technical QA cases (UI-only, pre-seeded accounts, Pass/Fail/Blocked blocks)

---

## 7. Pre-launch

- [ ] Mock layer disabled (`USE_MOCKS = false`)
- [ ] Real WC test on staging with HPOS enabled
- [ ] Email deliverability test (SPF/DKIM via WP Mail SMTP or equivalent)
- [ ] Production cron runs via system crontab, not `wp-cron.php`
- [ ] Rollback plan: deactivate plugin must NOT delete tables (only `delete_plugin` should — and even then, opt-in)
