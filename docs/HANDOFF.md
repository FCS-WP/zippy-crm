# Handoff — Zippy CRM Plugin

> Read this **first** if you're picking up this plugin in a new session.
> Then read [CLAUDE.md](../CLAUDE.md), [TODO.md](./TODO.md), and the rules under [.claude/rules/](../.claude/rules/).

---

## Where we are

Four customer-facing tabs are end-to-end live, hitting real REST endpoints backed by real DB tables:

| Tab | URL | Status |
|---|---|---|
| Membership | `/my-account/crm-membership/` | ✅ tier auto-eval, multiplier, progress to next tier |
| Points | `/my-account/crm-points/` | ✅ earn on order, reserve-on-redeem (debit-on-completion), ledger pagination |
| Vouchers | `/my-account/crm-vouchers/` | ✅ browse, claim, copy code, auto-apply to cart, my-claims sub-tab |
| Notifications | `/my-account/crm-notifications/` | ✅ preferences GET/PUT, register-form opt-in, sticky checkbox |

What's left is in [TODO.md](./TODO.md). The biggest remaining slice is the **Admin Panel** (§5).

---

## What you should build

If you've been told "build the Admin Panel," it's TODO §5:

- **Members** — list/filter/search users, change level, suspend/activate, adjust points
- **Vouchers** — CRUD on `crm_vouchers` (REST routes are already stubbed)
- **Points** — overview cards (issued/redeemed/outstanding), recent transactions, manual adjust
- **Reports** — new members chart, points activity, voucher usage

Pick whichever sub-panel makes sense to land first. **Vouchers is probably the easiest** because the REST routes are already wired in [routes.php](../src/Core/routes.php) and the model/service/customer-claim flow is fully operational — you only need to fill in the four admin handler bodies in [VouchersController](../src/Controllers/Rest/VouchersController.php) and build a React panel.

If you've been told something else, follow that.

---

## The playbook (use it)

Every customer feature was built with the same six-step sequence. Use it for admin panels too.

### 1. Schema first (if new tables needed)

- File goes in [src/Database/Schema/{table}.sql](../src/Database/Schema/) with `{prefix}` and `{charset_collate}` placeholders
- Add to `Installer::SCHEMAS` array in [Installer.php](../src/Core/Installer.php)
- **Bump `ZIPPY_CRM_VERSION`** in [zippy-crm.php](../zippy-crm.php) so `maybe_upgrade()` re-runs `dbDelta()`
- Run `wp eval "ZippyCrm\\Core\\Installer::maybe_upgrade(); echo get_option(\"zippy_crm_db_version\");"` in the container

### 2. SQL queries

- Anything beyond a single-line `WHERE id = %d` goes in [src/Database/Queries/{domain}/{verb_noun}.sql](../src/Database/Queries/) with `{prefix}` placeholders and a top-of-file comment explaining what it returns and which index it uses
- Loaded via [`QueryLoader::query('domain/file.sql')`](../src/Database/QueryLoader.php) — comments are auto-stripped (`$wpdb->prepare()` chokes on them)

### 3. Model

- Pure data access in [src/Models/](../src/Models/), namespace `ZippyCrm\Models`
- Static methods. No business logic. Returns `array<string,mixed>|null` or `array<int,array>` for lists
- Use `{prefix}` table reference via private static `table()` helper

### 4. Service

- Business logic in [src/Services/](../src/Services/), namespace `ZippyCrm\Services`
- Owns: caching, hook handlers, idempotency, action/filter dispatching
- Cache via [`Support/Cache::remember()`](../src/Support/Cache.php) — `delete()` on every write
- Reference patterns: [PointsEngine](../src/Services/PointsEngine.php) (best example), [ClaimHandler](../src/Services/ClaimHandler.php), [MembershipService](../src/Services/MembershipService.php)

### 5. REST

- Handler in [src/Controllers/Rest/{Feature}Controller.php](../src/Controllers/Rest/) — pure handler, no `register_rest_route`
- Add the route to [src/Core/routes.php](../src/Core/routes.php) — declarative manifest
- Use `'auth' => 'manage_woocommerce'` for admin routes (the registrar expands the keyword)
- Return shapes via [`RestResponse::ok()`](../src/Support/RestResponse.php) / `RestResponse::error()` — never `wp_send_json_*`

### 6. React (admin)

- Entry point in [assets/src/js/admin/index.jsx](../assets/src/js/admin/index.jsx) — already routes to mount points by element ID
- Each panel: `assets/src/js/admin/{feature}/{Feature}Panel.jsx` + sub-components
- Use shared primitives from [`assets/src/js/shared/ui/`](../assets/src/js/shared/ui/) — Button, Card, Badge, Input, Switch, Skeleton, Progress
- API client: [`useApiQuery` / `useApiMutation`](../assets/src/js/shared/hooks/useApi.js) — already handles cache invalidation correctly
- The admin enqueue is wired but **untested by a real panel** — your first admin React component is its smoke test

---

## Rules to follow (non-negotiable)

These are linked from [CLAUDE.md](../CLAUDE.md) and they're not optional:

| Rule | What it means in practice |
|---|---|
| [file-size.md](../.claude/rules/file-size.md) | 500 line cap. Split along responsibility seams. |
| [sql-files.md](../.claude/rules/sql-files.md) | SQL goes in `.sql` files. Inline only for one-liner `WHERE id = %d`. Always `$wpdb->prepare()` with the loaded SQL. |
| [shared-components.md](../.claude/rules/shared-components.md) | `shared/ui/` for primitives, `shared/components/` for composed widgets. Promote on the **third** use, not the first. |
| [performance.md](../.claude/rules/performance.md) | No DB queries in loops. Index every `WHERE`/`ORDER BY`. Cache hot reads + invalidate on write. Conditional enqueue. |
| [woocommerce-hpos.md](../.claude/rules/woocommerce-hpos.md) | HPOS only — `wc_get_order()`, `WC_Customer`, `WC_Coupon`. Never `get_post_meta` for orders, never `WP_Query` on `shop_order`. |

---

## Cross-cutting patterns we settled on

A few non-obvious decisions worth knowing:

### 1. **Points = cash tender at checkout. Vouchers = promotional coupons. Don't conflate them.**
- **Points** apply via [`PointsTender`](../src/Services/PointsTender.php): customer slides "use N pts" on the cart page → REST writes to `WC()->session`. A `cart_calculate_fees` hook reads the session and adds a negative fee. On `order_status_completed`, the debit settles (idempotent via `_zc_points_settled`). On refund, points credit back proportionally.
- **Vouchers** are real `WC_Coupon` objects with full eligibility rules (min order, percent vs fixed, individual_use, etc.). Promotional discounts that the merchant gave away.
- The two should never be implemented through the same primitive again — they had different semantics, refund behavior, tax treatment, and stacking rules.
- v1.7 had a "reserve-on-click, debit-on-completion" coupon-based flow for points. Retired in v1.8 because mixing stored value with coupon mechanics led to bugs around stacking, tax, and refunds. Legacy `pending_redeem` ledger rows + `idx_pending` + `reserved_points` column remain for backward compat / audit but are no longer written.

### 2. **Routes are declarative, controllers are pure**
- All routes live in [src/Core/routes.php](../src/Core/routes.php) as an array
- [RouteRegistrar](../src/Core/RouteRegistrar.php) walks it on `rest_api_init`
- Controllers don't call `register_rest_route` themselves — they're just handler classes
- Admin routes use `'auth' => 'manage_woocommerce'`

### 3. **Mock layer for staged rollout**
- [shared/mocks/index.js](../assets/src/js/shared/mocks/index.js) has `LIVE_ROUTES` + `LIVE_PREFIXES` sets
- A route in those sets hits the real REST API; everything else uses fixtures
- Lets you build UI before backend, or backend before UI, without breakage
- When admin endpoints land, add them to `LIVE_ROUTES`

### 4. **Cache key versioning**
- All cache keys go through [`Support/Cache`](../src/Support/Cache.php)
- Key prefix is `zc:v1:` — bump `Cache::VERSION` to global-flush
- Pattern: `Cache::remember("feature:scope:%d", fn() => …, $user_id)`

### 5. **Validation order (per FEATURE_SPEC §3.5)**
- Voucher claim: active → expiry → quota → not-claimed → not-suspended
- The same shape applies to admin actions where ordering matters: cheap-checks first, DB-touching checks last, conditional `UPDATE` as the authoritative race-winner

### 6. **The `--` SQL comment fix**
- Both `dbDelta()` and `$wpdb->prepare()` choke on leading `--` comments
- [`QueryLoader`](../src/Database/QueryLoader.php) strips them globally — you can document `.sql` files freely without breaking anything

### 7. **Audit log for admin write actions**
- Every admin-initiated mutation (level change, status change, points adjust, voucher CRUD) lands in `crm_audit_log` via [`AuditLogger`](../src/Services/AuditLogger.php).
- Two recording paths:
  - **Action-listener** (preferred): if the service method already does `do_action('crm_*_changed', ...)`, the listener captures it automatically — gated on `is_admin_context()` so customer/auto fires don't pollute the table. `MembershipService::set_level_admin` and `set_status_admin` are already covered this way.
  - **Explicit recorder**: for mutations that don't fire an action (or where the action carries insufficient context). Examples: `AuditLogger::record_voucher_created($id, $payload)`, `record_voucher_paused($id)`, `record_points_adjusted($user_id, $delta, $reason)`. **Call these from your admin write methods** — they're a one-liner each.
- Event names are `AuditLogger::EVENT_*` constants. Add new ones there, not as ad-hoc strings.
- Read via `GET /admin/audit?event=&actor_id=&target_id=&page=&per_page=`. Empty/0 filters are skipped.
- The audit table is append-only — no `record_*_undid()` calls. Corrections are a *new* row.

### 8. **Tier definitions are data, not constants**
- The four tiers (free/silver/gold/vip) **used to be** hardcoded as constants on `Membership`. They're now rows in `crm_tiers`, served by [`TierRegistry`](../src/Services/TierRegistry.php).
- **Never** read `Membership::LEVELS`, `MULTIPLIERS`, or `LABELS` — those constants are gone. Use `TierRegistry::slugs()`, `TierRegistry::multiplier_for($slug)`, `TierRegistry::labels()` instead. Or call `Membership::valid_slugs()` / `Membership::labels()` / `Membership::multipliers()` if you specifically want the model's facade.
- Adding a new tier: `TierRegistry::create([slug, label, multiplier, threshold_orders, threshold_spend, is_admin_only, sort_order])`. Slugs are immutable once created.
- `is_admin_only=1` tiers (e.g. vip) are skipped by `evaluate_tier_upgrade` — same sticky-VIP semantics as the original spec, just generalized.
- `next_tier_progress` walks `sort_order` ascending, `compute_for_stats` walks `threshold_spend` descending.

### 9. **HPOS-only — no `get_post_meta` for orders**
- All order code uses `wc_get_order($id)`, `$order->get_meta()`, `$order->update_meta_data()`, `wc_get_orders([...])`
- See [woocommerce-hpos.md](../.claude/rules/woocommerce-hpos.md) for the full list

---

## Verification workflow (use it after every feature)

We've built up a workflow that catches regressions early. Use it:

### Manual QA scripts live in `tests/manual/`

**Before writing a new `wp eval-file` script, check [tests/manual/](../tests/manual/) — there's likely already one for the feature you're touching.** The folder's [README](../tests/manual/README.md) lists every script with a one-line purpose; if one almost-fits, edit it rather than reinventing.

Run pattern (copy in, eval-file out):

```bash
docker compose cp src/wp-content/plugins/zippy-crm/tests/manual/zc-test-{name}.php \
  wordpress:/tmp/zc-test-{name}.php
docker compose exec -T --user www-data wordpress sh -lc \
  'wp eval-file /tmp/zc-test-{name}.php' 2>&1 | grep -v 'PHP Warning'
```

Inside a test script:
1. Pick an admin user via `get_users(['role'=>'administrator','number'=>1])` + `wp_set_current_user` — don't hardcode user IDs
2. Hit endpoints via `WP_REST_Request` + `rest_do_request` (exercises the real route registrar + permission callbacks)
3. Print one numbered case header per assertion — easy to scan a failed run
4. **Always test the negative cases** — duplicates, expired, race re-fires, idempotency
5. **Clean up at the end** so re-runs don't accumulate orphaned data

When you write a new script, **commit it back to `tests/manual/`** so the next session benefits — don't leave it in `/tmp`.

### Diagnostic noise to ignore

The IntelliPHP analyzer doesn't have WordPress stubs loaded. You'll see harmless warnings constantly:

- `PHP0417 Call to unknown function: 'add_action'` / `wc_get_order` / `dbDelta` / `__` etc. — all real at runtime
- `PHP0413 Use of unknown class: 'WP_REST_Request'` — same
- `PHP0415 Use of undefined constant: 'ARRAY_A'` / `'ABSPATH'` — same
- `PHP0421 Symbol '$r' is declared but not used` on stub handlers — required by WP REST signature
- `PHP6616` namespace-resolution micro-optimization hints — stylistic
- `P1003` unused-symbol hints on stale-cached files — re-parse after edit clears them

**Don't try to fix them.** They'll noisy your output but they don't affect the runtime.

---

## File map (where things live)

```
zippy-crm/
├── zippy-crm.php                      # entry, version constant, autoloader
├── docs/
│   ├── FEATURE_SPEC.md                # source of truth for behavior
│   ├── TODO.md                        # what's done, what's next
│   ├── HANDOFF.md                     # this file
│   └── DATABASE_SCHEMA.md
├── .claude/rules/                     # five rule files (auto-loaded)
└── src/
    ├── Core/
    │   ├── Plugin.php                 # boot orchestration
    │   ├── Autoloader.php             # PSR-4 hand-rolled
    │   ├── Installer.php              # dbDelta + maybe_upgrade
    │   ├── Assets.php                 # Vite manifest enqueue
    │   ├── Endpoints.php              # WC My Account endpoints
    │   ├── RouteRegistrar.php         # walks routes.php
    │   └── routes.php                 # REST manifest (THE place to add routes)
    ├── Models/                        # data access only
    │   ├── Membership.php
    │   ├── PointsLedger.php
    │   ├── PointsSummary.php
    │   ├── Voucher.php
    │   ├── VoucherClaim.php
    │   └── NotifSub.php
    ├── Services/                      # business logic, hooks, caching
    │   ├── MembershipService.php
    │   ├── PointsEngine.php           # ★ best reference pattern
    │   ├── VoucherService.php
    │   ├── ClaimHandler.php
    │   └── SubsManager.php
    ├── Hooks/
    │   ├── WooCommerce.php            # all WC hooks
    │   └── Cron.php                   # scheduled events
    ├── Controllers/
    │   ├── Account/                   # WC My Account integration
    │   ├── Admin/                     # admin menu + mount points (mostly stubs)
    │   └── Rest/                      # REST handlers (no route registration)
    ├── Views/
    │   ├── account/                   # tab mount points
    │   └── emails/                    # email templates (deferred)
    ├── Database/
    │   ├── QueryLoader.php
    │   ├── Schema/                    # CREATE TABLE files
    │   └── Queries/                   # runtime query files
    └── Support/
        ├── RestResponse.php           # ok() / error() envelope helpers
        ├── Cache.php                  # versioned wp_cache_* wrapper
        └── DateTimeHelper.php

assets/src/js/
├── admin/                             # YOUR WORK GOES HERE
│   ├── index.jsx                      # routes to mount points by ID
│   └── App.jsx                        # current placeholder
├── account/                           # 4 customer tabs (reference patterns)
│   ├── membership/
│   ├── points/                        # ★ best reference pattern
│   ├── vouchers/
│   └── notifications/
└── shared/
    ├── api.js                         # REST client w/ X-WP-Nonce
    ├── cn.js
    ├── mocks/                         # USE_MOCKS + LIVE_ROUTES toggle
    ├── hooks/useApi.js                # React Query wrappers
    ├── ui/                            # shadcn-style primitives
    ├── components/                    # composed widgets (just EmptyState so far)
    └── utils/format.js                # money/date/number/percent
```

---

## When in doubt

- **Before writing code, find the closest existing pattern.** Points was the most thorough end-to-end build — copy its structure.
- **Ask for clarification rather than guessing.** Especially on UX trade-offs (e.g., "auto-apply vs hand back code") and idempotency strategy.
- **Verify with `wp eval-file` before claiming a feature is done.** UI looking right ≠ feature working.
- **Update [TODO.md](./TODO.md) as you go** — flip `[ ]` to `[x]` when shipped, leave `[~]` for in-progress.
- **Don't skip the rules** under [.claude/rules/](../.claude/rules/). They're there because we hit problems without them.

---

## What I'd recommend tackling first

If I were you in the new session:

1. Read `CLAUDE.md` + `TODO.md` + this file (~10 min).
2. Skim [PointsEngine.php](../src/Services/PointsEngine.php), [VouchersController.php](../src/Controllers/Rest/VouchersController.php), and [account/points/PointsTab.jsx](../assets/src/js/account/points/PointsTab.jsx) to internalize the patterns.
3. Confirm scope with the user — is it Admin Vouchers first, or Members? They go in different orders depending on what's most useful.
4. Build one sub-panel end-to-end (REST handlers + admin enqueue test + React panel) before starting the next. Don't try to scaffold all four at once.
5. Update TODO.md as you ship.

Good luck.
