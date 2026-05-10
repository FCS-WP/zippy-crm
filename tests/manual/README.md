# Manual QA Scripts

Throwaway-feeling but reusable: PHP files that run inside the wordpress
container via `wp eval-file` to verify a feature end-to-end. They hit the
real REST endpoints / models, write to / read from the live database, and
print pass/fail lines.

Use these to **verify backend changes after editing a model, service,
or controller** — much faster than refreshing the React UI and clicking
through, and they exercise the actual SQL + WC integration without mocks.

> **Future me / agents:** check this folder before writing a new
> `wp eval-file` script. There's a good chance one already exists for the
> feature you're touching. If you change a script while debugging, commit
> the improved version back here so the next person benefits.

---

## How to run

Each script is self-contained. Two-step pattern:

```bash
# Copy the script into the container (the source path is the host repo;
# the destination path is what wp eval-file will read inside the container)
docker compose -f /home/shin/Documents/Workspace/Projects/ai_zippy/docker-compose.yml \
  cp src/wp-content/plugins/zippy-crm/tests/manual/zc-test-{name}.php \
  wordpress:/tmp/zc-test-{name}.php

# Run it
docker compose -f /home/shin/Documents/Workspace/Projects/ai_zippy/docker-compose.yml \
  exec -T --user www-data wordpress sh -lc \
  'wp eval-file /tmp/zc-test-{name}.php' 2>&1 | grep -v 'PHP Warning'
```

The `grep -v 'PHP Warning'` filters out the IntelliPHP / WordPress-stub
noise documented in [HANDOFF.md → Diagnostic noise to ignore](../../docs/HANDOFF.md).
Real failures still show up.

If a script needs an admin user, it picks the first administrator
(`get_users(['role' => 'administrator', 'number' => 1])`) and calls
`wp_set_current_user`, so you don't need to set one up.

---

## What each script tests

### Customer flows

| Script | Tests |
|---|---|
| `zc-test-consume.php` | Reserve-on-click → consume-on-completion path for the **legacy** points-redemption coupon (pre-v1.8 flow). Looks up a user's latest active pending_redeem ledger row and walks it through `consume_redemptions_for_order`. |
| `zc-test-tender.php` | v1.8 cart-tender flow end-to-end: set balance → add to cart → `POST /points/apply` → complete order → balance debits + ledger `redeem` row → refund → 50% credit back via `adjust` → idempotency check on order_status_completed re-fire. |
| `zc-test-vouchers.php` | Customer-side voucher claim path: seeds 3 vouchers (active, second active, expired), then runs through claim / dup / expired-rejection. |
| `zc-test-claim-filter.php` | `list_my_claims.sql` correctly drops expired + paused-voucher claims from the customer's My Claims view. Injects synthetic expired/paused vouchers, asserts they don't appear. |

### Admin endpoints

| Script | Tests |
|---|---|
| `zc-test-admin-vouchers.php` | 15-case voucher CRUD: list / create / dup-collision (409) / bad-payload (400) / update / publish (WC coupon synced) / draft-only delete refusal / pause / resume / duplicate / filter / search / claims drawer / cleanup. |
| `zc-test-voucher-fields.php` | Full WC-coupon-parity round-trip: 12 new columns (max_amount, individual_use, exclude_sale_items, free_shipping, usage_limit_per_user, limit_usage_to_x_items, email_restrictions, product_ids, excluded_product_ids, product_categories, excluded_product_categories, allowed_hours), DB JSON encoding, partial update, publish→WC coupon sync of all 11 setters, hour-window validity check. |
| `zc-test-multicode.php` | v1.10 multi-code voucher campaigns: 3-slot voucher → publish creates 3 WC coupons → 3 different users claim → 4th claim hits `quota_exceeded` → checkout consumes one code → that code marked used, voucher uses_count=1. |
| `zc-test-admin-members.php` | 16-case Members admin: list / single get / level filter / search / level changes (incl. vip) / suspend & activate / points credit (+500) + debit (-200) / negative-balance refusal / reason-required / ledger↔summary equality after adjustments. |
| `zc-test-admin-points.php` | 8-case Points admin: REST summary == direct SUM, paginated ledger w/ type filter, bad-filter 400, `pending_redeem` excluded, recalculate-all on clean data (zero drift), recalculate-all on synthetic-drift data (corrects). |
| `zc-test-admin-tiers.php` | 12-case Tiers admin CRUD: public list (filtered + incl-admin-only), admin list w/ member counts, create with full payload, bad-slug 400, dup 409, update, delete-with-members 409 (real refusal), Members filter accepts new slug, junk-slug filter 400. |
| `zc-test-admin-reports.php` | 8-case Reports endpoints: default 30-day range, custom 7-day, voucher claims, bad-date 400, inverted range 400, range-too-wide 400, single-day zero-fill, anonymous 401. |
| `zc-test-audit.php` | Wires admin context, performs N admin actions (level change, points adjust, voucher CRUD, tier ops), then checks rows landed in `crm_audit_log` with the right event slug + meta_json shape. Customer-side `crm_*_changed` actions correctly skipped. |
| `zc-test-notif.php` | Notifications subscription engine: opt-in/out preferences, `select_unsent_subscribers.sql` filter, batch dispatch, retry-failed cron. |

---

## Conventions

When you write a new script, please follow these for consistency:

1. **Pick an admin user via `get_users` + `wp_set_current_user`**, so the
   script works on any dev box without hardcoded IDs.
2. **Use `WP_REST_Request` + `rest_do_request`** to hit endpoints — exercises
   the real route registrar + permission callbacks. Don't call controller
   methods directly unless you specifically want to bypass auth.
3. **Print one numbered case header per assertion** (`=== 5. Bad date format (400) ===`)
   so a failed run is easy to scan.
4. **Clean up at the end.** Delete created users, vouchers, etc. so re-runs
   don't accumulate orphaned data.
5. **Keep them under 200 lines.** If a script grows beyond that, split it
   along feature boundaries rather than letting one script test five things.
6. **Rerun the script after you make backend changes** — it's the cheapest
   way to catch a regression before it hits the React side.

If a script hardcodes IDs (some of the older ones do), feel free to
generalize when you touch them — but don't refactor in bulk for the sake
of it.
