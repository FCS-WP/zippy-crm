# Zippy CRM

A WooCommerce CRM plugin: membership tiers, points, vouchers, opt-in notifications. Customer-facing tabs in the WC My Account area; admin panel under WooCommerce → CRM.

> **AI assistants**: read [docs/HANDOFF.md](docs/HANDOFF.md) first, not this file. This README is for humans landing cold.

---

## Quickstart

```bash
# 1. Install JS deps + build the bundle
npm install
npm run build

# 2. Activate the plugin
wp plugin activate zippy-crm

# 3. Seed test data (creates ~20 members, 8 vouchers, 50 orders)
wp eval 'ZippyCrm\Support\Seeder::all();'

# 4. Browse to a customer account
open https://your-site.test/my-account/crm-membership/
```

WC's HPOS (High-Performance Order Storage) must be enabled — the plugin declares compatibility and uses HPOS-only APIs throughout. Toggle it under WooCommerce → Settings → Advanced → Features if your store still runs the legacy order tables.

---

## What it does

| Surface | URL | Behavior |
|---|---|---|
| Membership | `/my-account/crm-membership/` | Auto-assigned tier (`free` / `silver` / `gold` / `vip`) based on order history; points multiplier; progress to next tier |
| Points | `/my-account/crm-points/` | Earn on completed orders, redeem for a 24h fixed-cart coupon (reserve-on-click, debit-on-completion), paginated ledger |
| Vouchers | `/my-account/crm-vouchers/` | Browse admin-published vouchers, claim, copy code (auto-applies if cart is non-empty), my-claims sub-tab |
| Notifications | `/my-account/crm-notifications/` | Opt-in toggles for voucher + points emails; sticky checkbox on the WC register form |
| Admin | `wp-admin/admin.php?page=zippy-crm` | Members list, Vouchers CRUD, Points overview, Reports |
| Documentation | `wp-admin/admin.php?page=zippy-crm-docs` | In-product user guide for store admins (Markdown sources in [docs/guide/](docs/guide/)) |

---

## In-product documentation

The Documentation admin page renders Markdown from [docs/guide/](docs/guide/) — edit a `.md` file and refresh, no rebuild needed. Sidebar groups (Getting started / Features / Reference) are configured in [docs/guide/manifest.php](docs/guide/manifest.php).

**Hidden dev-only page**: there's an "Architecture & integrations" reference for developers ([80-dev-notes.md](docs/guide/80-dev-notes.md)) — covers data model, REST endpoints, hooks, filters, and diagnostic commands. It's flagged `hidden => true` in the manifest so it stays out of the customer-facing sidebar; reach it directly:

```
/wp-admin/admin.php?page=zippy-crm-docs&doc=dev-notes
```

Hand the link to anyone who needs the technical detail. To unhide and put it back in the sidebar, drop `'hidden' => true` from its manifest entry.

---

## Architecture in 60 seconds

```
zippy-crm/
├── zippy-crm.php           Entry — version, autoloader, HPOS declaration
├── src/
│   ├── Core/               Plugin boot, Assets, RouteRegistrar, routes.php (THE place to add REST routes)
│   ├── Models/             Pure data access — one per crm_* table
│   ├── Services/           Business logic, hooks, caching (PointsEngine is the best reference pattern)
│   ├── Hooks/              WC + Cron action wiring
│   ├── Controllers/
│   │   ├── Account/        WC My Account integration
│   │   ├── Admin/          wp-admin pages
│   │   └── Rest/           Pure handlers — wired via Core/routes.php
│   ├── Views/              account/ tab mount points + emails/ templates
│   ├── Database/
│   │   ├── Schema/         CREATE TABLE files with {prefix}/{charset_collate} placeholders
│   │   └── Queries/        Runtime SELECT/UPDATE files (loaded via QueryLoader)
│   └── Support/            Cache, RestResponse, DateTimeHelper, Seeder
├── assets/src/js/
│   ├── account/            Customer React tabs
│   ├── admin/              Admin React panels
│   └── shared/             api/cn/hooks/ui/components/utils
└── docs/
    ├── FEATURE_SPEC.md     Source of truth for behavior
    ├── HANDOFF.md          Onboarding doc for new contributors / AI sessions
    ├── TODO.md             Work log — flip [ ] → [x] as you ship
    └── DATABASE_SCHEMA.md
```

Five rules under [.claude/rules/](.claude/rules/) (file size, SQL files, shared components, performance, HPOS) apply to every change.

### REST surface

All under `/wp-json/zippy-crm/v1/`. See [src/Core/routes.php](src/Core/routes.php) for the full manifest.

```
GET    /membership/me
GET    /points/me              GET /points/ledger     POST /points/redeem
GET    /vouchers               GET /vouchers/claims    POST /vouchers/{id}/claim
GET    /notifications/preferences  PUT /notifications/preferences
GET    /admin/vouchers          POST /admin/vouchers
PUT    /admin/vouchers/{id}     DELETE /admin/vouchers/{id}
POST   /admin/vouchers/{id}/{publish|pause|resume|duplicate}
GET    /admin/vouchers/{id}/claims
```

Auth via WP cookie + `X-WP-Nonce` (set on `window.zippyCrm.nonce`).

---

## Dev commands

```bash
# Build / watch
npm install
npm run dev                     # Vite dev server with HMR
npm run build                   # Production bundle to assets/dist/

# Plugin lifecycle
wp plugin activate zippy-crm
wp eval 'ZippyCrm\Core\Installer::maybe_upgrade();'   # Forces dbDelta on schema changes

# Seeders (Support/Seeder.php)
wp eval 'ZippyCrm\Support\Seeder::all();'             # 20 members + 8 vouchers + 50 orders
wp eval 'ZippyCrm\Support\Seeder::seed_members(50);'  # Just members
wp eval 'ZippyCrm\Support\Seeder::seed_vouchers(10);' # Just vouchers
wp eval 'ZippyCrm\Support\Seeder::seed_orders(100);'  # Random orders against existing seeded users
wp eval 'ZippyCrm\Support\Seeder::reset();'           # Clear seeded users + vouchers (real data is untouched)

# Maintenance
wp eval 'ZippyCrm\Services\PointsEngine::recalculate_balance(USER_ID);'  # If summary drifts from ledger
```

---

## Adding a feature

The plugin follows a six-step playbook for every feature. See [docs/HANDOFF.md](docs/HANDOFF.md#the-playbook-use-it) for the full version. Short form:

1. **Schema** — add a `.sql` file under `src/Database/Schema/`, register in `Installer::SCHEMAS`, bump `ZIPPY_CRM_VERSION`
2. **SQL queries** — anything beyond a one-liner goes in `src/Database/Queries/{domain}/`
3. **Model** — pure data access in `src/Models/`
4. **Service** — business logic in `src/Services/` (caching, hooks, idempotency)
5. **REST** — pure handler in `src/Controllers/Rest/`, route declared in `src/Core/routes.php`
6. **React** — mount under `assets/src/js/{admin|account}/{feature}/`

---

## Testing changes manually

The plugin currently relies on hand-driven smoke tests via `wp eval-file` rather than PHPUnit. Pattern:

```bash
# Drop a test script in /tmp, copy into the container, run it.
docker compose cp /tmp/zc-test-something.php wordpress:/tmp/
docker compose exec --user www-data wordpress sh -c 'wp eval-file /tmp/zc-test-something.php'
```

Inside the script: `wp_set_current_user($id)`, hit endpoints with `WP_REST_Request` + `rest_do_request`, then verify side effects against the DB. **Always** test negative cases (duplicates, expired, race re-fires).

---

## Configuration constants

Override in `wp-config.php` if needed:

```php
define( 'ZIPPY_CRM_POINTS_RATE',          20 );    // 20 points = $1
define( 'ZIPPY_CRM_MIN_REDEMPTION',       20 );    // Minimum points to redeem
define( 'ZIPPY_CRM_EMAIL_BATCH_SIZE',     50 );    // Notification dispatch batch size
define( 'ZIPPY_CRM_EMAIL_BATCH_INTERVAL', 300 );   // Seconds between batches
```

Filters and actions are documented at the top of each service. Most useful:

```php
apply_filters( 'crm_points_earn_multiplier', $multiplier, $user_id, $order );
apply_filters( 'crm_points_redemption_rate', 20, $user_id );
apply_filters( 'crm_pre_claim_voucher', $errors, $voucher_id, $user_id );
apply_filters( 'crm_voucher_notification_subject', $subject, $voucher );
apply_filters( 'crm_voucher_notification_content', $html, $voucher, $user_id );
do_action( 'crm_points_awarded',           $user_id, $points, $order_id );
do_action( 'crm_points_reserved',          $user_id, $points, $coupon_code );
do_action( 'crm_points_redeemed',          $user_id, $points, $coupon_code, $order_id );
do_action( 'crm_voucher_published',        $voucher_id );
do_action( 'crm_voucher_claimed',          $voucher_id, $user_id );
do_action( 'crm_membership_level_changed', $user_id, $old_level, $new_level );
```

---

## Production checklist

Before going live, see [docs/TODO.md §7](docs/TODO.md). Highlights:

- [ ] WC HPOS enabled and the plugin's compat declaration is firing (no admin warning on the Plugins page)
- [ ] Real system crontab pinging `wp-cron.php` (default WP cron is unreliable for batch email)
- [ ] SMTP configured (e.g. WP Mail SMTP) — voucher notification emails go through `wp_mail`
- [ ] `assets/src/js/shared/mocks/index.js` → `USE_MOCKS = false` (set; verify `LIVE_ROUTES` covers everything you ship)
- [ ] Run `wp eval 'ZippyCrm\Support\Seeder::reset();'` to remove any test data

---

## License

GPL-2.0-or-later.
