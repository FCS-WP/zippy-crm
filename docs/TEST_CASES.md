# Manual Test Cases — Customer Flows

End-to-end test cases for the four customer-facing tabs. Each case has Setup → Steps → Expected; tick the boxes as you go.

> **Convention:** `TC-{FEATURE}-{NN}` — stable IDs so they survive edits and are easy to reference in PRs and bug reports.
>
> **How to run a setup block:** copy into a `.php` file, then:
> ```bash
> docker compose cp /tmp/setup.php wordpress:/tmp/
> docker compose exec --user www-data wordpress sh -c 'wp eval-file /tmp/setup.php'
> ```
> Or paste short snippets directly into `wp eval '...'`.
>
> **Reset between features:** `wp eval 'ZippyCrm\Support\Seeder::reset();'` clears all seeded users + vouchers (real data is untouched).

---

## Table of contents

- [Membership](#membership-tc-mbr)
- [Points](#points-tc-pts)
- [Vouchers](#vouchers-tc-vch)
- [Notifications](#notifications-tc-not)
- [End-to-end flows](#end-to-end-flows-tc-e2e)

---

## Membership (TC-MBR)

### TC-MBR-01: First-time user lands on `free` tier

**Setup**
```bash
wp eval '$ids = ZippyCrm\Support\Seeder::seed_members(1); echo $ids[0];'
# Note the user ID. Force their tier to `free` for a clean baseline:
wp eval 'global $wpdb; $wpdb->update($wpdb->prefix . "crm_memberships",
    ["membership_level" => "free"], ["user_id" => USER_ID]);
    ZippyCrm\Services\MembershipService::invalidate(USER_ID);'
```

**Steps**
1. Log in as the seeded user.
2. Browse to `/my-account/crm-membership/`.

**Expected**
- [ ] Hero card shows the seeded display name + email
- [ ] Level badge says `Free`
- [ ] Status badge says `active`
- [ ] Points multiplier shows `1×`
- [ ] Member-since date is today
- [ ] "Progress to Silver" card is visible with current = 0/$0 and target = 5/$500
- [ ] Stats column shows total orders + lifetime spend (likely 0 + $0.00 for a brand-new seeded user)

---

### TC-MBR-02: Order completion auto-upgrades tier

**Setup**
```bash
wp eval 'ZippyCrm\Support\Seeder::reset();
    $ids = ZippyCrm\Support\Seeder::seed_members(1);
    echo "user_id=", $ids[0], PHP_EOL;'
```

**Steps**
1. Log in as the seeded user.
2. Place an order whose subtotal is **≥ $500** and complete payment.
3. As admin, mark the order **completed** (or use `wp eval 'ZippyCrm\Support\Seeder::seed_orders(1, USER_ID);'` to fast-forward).
4. As the customer, reload `/my-account/crm-membership/`.

**Expected**
- [ ] Level badge changes from `Free` → `Silver`
- [ ] Multiplier updates from `1×` → `1.2×`
- [ ] Progress card now shows progress toward `Gold` (target $2,000 / 15 orders)
- [ ] Lifetime spend matches the order subtotal (within rounding)

---

### TC-MBR-03: Suspended status disables redeem

**Setup**
```bash
wp eval 'global $wpdb; $wpdb->update($wpdb->prefix . "crm_memberships",
    ["status" => "suspended"], ["user_id" => USER_ID]);
    ZippyCrm\Services\MembershipService::invalidate(USER_ID);'
```

**Steps**
1. Log in as the suspended user (must have ≥ 20 points to even attempt redeem).
2. Browse to `/my-account/crm-points/`.
3. Try to redeem points.

**Expected**
- [ ] Membership tab shows status badge `suspended`
- [ ] On `POST /points/redeem`, response is `403 account_suspended`
- [ ] Toast / inline error displays: "Your account is currently suspended."
- [ ] No coupon is created
- [ ] Reset: `wp eval '$wpdb->update(...status="active"...)'`

---

### TC-MBR-04: VIP tier does not auto-downgrade

**Setup**
```bash
wp eval 'global $wpdb; $wpdb->update($wpdb->prefix . "crm_memberships",
    ["membership_level" => "vip"], ["user_id" => USER_ID]);
    ZippyCrm\Services\MembershipService::invalidate(USER_ID);'
```
The user has very few orders / spend (well below `gold` threshold).

**Steps**
1. Place + complete an order to trigger tier evaluation.
2. Reload `/my-account/crm-membership/`.

**Expected**
- [ ] Level remains `VIP` (the spec sticks VIP — only admins can remove it)
- [ ] Multiplier remains `2×`
- [ ] No progress card shown ("you've reached the top tier")

---

## Points (TC-PTS)

### TC-PTS-01: Earn on order completion (silver multiplier)

**Setup**
```bash
wp eval 'ZippyCrm\Support\Seeder::reset();
    $ids = ZippyCrm\Support\Seeder::seed_members(1);
    $uid = $ids[0];
    global $wpdb;
    $wpdb->update($wpdb->prefix . "crm_memberships",
        ["membership_level" => "silver"], ["user_id" => $uid]);
    ZippyCrm\Services\MembershipService::invalidate($uid);
    echo "user_id=$uid", PHP_EOL;'
```

**Steps**
1. As the seeded silver user, place + complete a $50 order.
2. Browse to `/my-account/crm-points/`.

**Expected**
- [ ] Balance shows **60 pts** (`floor(50) × 1.2 = 60`)
- [ ] Hero "Earned" mini-stat shows 60
- [ ] Activity row: type badge `Earned`, description `Order #N`, amount `+60`, timestamp = now
- [ ] Insights strip "Earned · 30d" shows `+60`

---

### TC-PTS-02: Click Redeem → reserves but doesn't debit balance

**Setup** — user from TC-PTS-01 (or any user with balance ≥ 100 pts and no active reservations)

**Steps**
1. On `/my-account/crm-points/`, set the redeem slider to 100 pts.
2. Click **Get $5 coupon**.

**Expected**
- [ ] Success card appears with a coupon code matching `CRM-RDM-[A-Z0-9]{12}`
- [ ] Hero balance is **unchanged** (still 60 — see note below if it dropped)
- [ ] Hero subtitle shows "Total balance · X reserved in pending coupons"
- [ ] Activity has a new amber **Reserved** row with the coupon code as a chip
- [ ] Sidebar redeem max drops by 100 pts (now clamps against `available`, not `balance`)
- [ ] Copy button copies the code to clipboard

**Note:** If the balance dropped, the spec-violating "debit-on-click" regression has returned. See [PointsEngine::redeem](../src/Services/PointsEngine.php) — should call `PointsLedger::insert(..., 'pending_redeem', 0, ...)`, NOT `self::debit(...)`.

---

### TC-PTS-03: Coupon used at checkout → debits balance

**Setup** — continue from TC-PTS-02 with the redeemed coupon code in hand. User needs ≥ $5 of products in cart.

**Steps**
1. Add a product worth ≥ $5 to the cart.
2. Apply the `CRM-RDM-…` coupon at checkout.
3. Place the order, complete payment, mark it `completed`.
4. Reload `/my-account/crm-points/`.

**Expected**
- [ ] Hero balance dropped by exactly 100 (the reserved amount)
- [ ] Hero "Reserved" sub-line is gone (or shows 0)
- [ ] Activity has a new **Redeemed** row, `−100`, sky-blue badge, with `$5.00 off` sub-text and Order # link
- [ ] The earlier **Reserved** row is no longer visible in the active list (its `pending_status` is now `consumed`)
- [ ] Hero "Total redeemed" mini-stat increased by 100

---

### TC-PTS-04: Coupon expires unused → balance returns to available

**Setup**
```bash
wp eval '
$user_id = USER_ID;
$points  = 100;
$code    = "CRM-RDM-EXPIRETEST" . wp_generate_password(4, false, false);
$coupon  = new WC_Coupon();
$coupon->set_code($code);
$coupon->set_discount_type("fixed_cart");
$coupon->set_amount("5");
$coupon->set_individual_use(true);
$coupon->set_usage_limit(1);
// Expire 25 hours ago — past our 24h window.
$coupon->set_date_expires(time() - 25 * HOUR_IN_SECONDS);
$coupon->update_meta_data("_zc_redemption", 1);
$coupon->update_meta_data("_zc_points_reserved", $points);
$coupon->update_meta_data("_zc_user_id", $user_id);
$coupon->save();
ZippyCrm\Models\PointsLedger::insert($user_id, "pending_redeem", 0, $code, null, $points,
    ZippyCrm\Models\PointsLedger::PENDING_ACTIVE);
// Backdate the ledger row so it falls outside the 24h available-balance window.
global $wpdb;
$wpdb->query("UPDATE {$wpdb->prefix}crm_points_ledger SET created_at = (UTC_TIMESTAMP() - INTERVAL 25 HOUR)
    WHERE user_id = $user_id AND description = \"$code\"");
ZippyCrm\Services\PointsEngine::invalidate($user_id);'
```

**Steps**
1. Reload `/my-account/crm-points/`.

**Expected**
- [ ] Hero `available` equals `balance` again (the expired pending no longer counts)
- [ ] Redeem max returns to its full value
- [ ] No errors, no negative balance

---

### TC-PTS-05: Insufficient available — error includes breakdown

**Setup**
```bash
# Create a user with balance=50, reserved=40 → available=10
wp eval '
$ids = ZippyCrm\Support\Seeder::seed_members(1);
$uid = $ids[0];
ZippyCrm\Models\PointsLedger::insert($uid, "adjust", 50, "Seed", null);
ZippyCrm\Models\PointsSummary::apply_delta($uid, 50);
ZippyCrm\Models\PointsLedger::insert($uid, "pending_redeem", 0, "CRM-RDM-FAKE12CHARSXX", null, 40,
    ZippyCrm\Models\PointsLedger::PENDING_ACTIVE);
ZippyCrm\Services\PointsEngine::invalidate($uid);
echo "user_id=$uid", PHP_EOL;'
```

**Steps**
1. Log in as the user.
2. Try to redeem 40 pts (which exceeds `available = 10`).

**Expected**
- [ ] HTTP 400, code `insufficient_available`
- [ ] Error message includes "You have 50 pts (10 available, 40 reserved in pending coupons)…"
- [ ] No new coupon was created
- [ ] No new ledger row was inserted

---

### TC-PTS-06: Replay of order_status_completed is idempotent

**Setup** — completed order from TC-PTS-01 (60 pts already awarded)

**Steps**
1. Run: `wp eval 'do_action("woocommerce_order_status_completed", ORDER_ID);'`
2. Reload `/my-account/crm-points/`.

**Expected**
- [ ] Balance is still 60 (NOT 120)
- [ ] No duplicate "Earned · Order #N" ledger row
- [ ] Order meta `_zc_points_awarded` is set (admin can verify in HPOS order edit screen)

---

## Vouchers (TC-VCH)

### TC-VCH-01: Browse available vouchers

**Setup**
```bash
wp eval 'ZippyCrm\Support\Seeder::reset();
    ZippyCrm\Support\Seeder::seed_members(1);
    ZippyCrm\Support\Seeder::seed_vouchers(5);'
# Note: seeder publishes ~75% — re-run if zero published this batch.
```

**Steps**
1. Log in as the seeded user.
2. Browse to `/my-account/crm-vouchers/`.
3. Stay on the **Available** sub-tab.

**Expected**
- [ ] Cards display in a 2-column grid
- [ ] Each card shows discount stripe (gradient header) with value (`%` or `$`)
- [ ] Title, description, expiry pill, "Claim voucher" button are visible
- [ ] Sub-tab counter `Available · N` matches the number of cards
- [ ] No drafts or expired vouchers appear

---

### TC-VCH-02: Claim voucher → moves to "My Claims"

**Setup** — continuing from TC-VCH-01

**Steps**
1. Click **Claim voucher** on the first available card.

**Expected**
- [ ] Card swaps to a green "Code claimed" state with a copyable code chip
- [ ] **Available** sub-tab counter decrements by 1
- [ ] **My Claims** sub-tab counter increments by 1 (without page reload — react-query invalidation worked)
- [ ] Switch to **My Claims**: the voucher appears with a `Ready to use` badge and discount stripe matching the original

---

### TC-VCH-03: Claim auto-applies to non-empty cart

**Setup** — user from TC-VCH-01, with at least one product in cart already

**Steps**
1. Add a product to the cart that meets the voucher's `min_order_amount`.
2. Browse to `/my-account/crm-vouchers/` (don't empty the cart).
3. Click **Claim** on a voucher.

**Expected**
- [ ] Success card says **"Applied to your cart"** (not just "Code claimed")
- [ ] Browse to `/cart/` — the coupon is already applied
- [ ] Order total reflects the discount

---

### TC-VCH-04: Double-claim returns 409

**Setup** — user has already claimed `SUMMER25` (or any voucher) per TC-VCH-02

**Steps**
1. Manually fire: `curl -X POST -H "X-WP-Nonce: <nonce>" -H "Cookie: <session>" \
   https://site.test/wp-json/zippy-crm/v1/vouchers/{voucher_id}/claim`
2. Or replay the claim mutation in the dev console.

**Expected**
- [ ] HTTP 409, code `already_claimed`
- [ ] Message: "You have already claimed this voucher."
- [ ] No duplicate row in `crm_voucher_claims` (verify: `wp eval 'echo count(ZippyCrm\Models\VoucherClaim::list_for_user(USER_ID));'` is unchanged)

---

### TC-VCH-05: Expired voucher returns 410

**Setup**
```bash
wp eval '
$id = ZippyCrm\Models\Voucher::create([
    "code" => "ZCSEED-EXPIRED" . wp_generate_password(4, false, false),
    "title" => "Already expired",
    "discount_type" => "fixed_cart",
    "discount_value" => 5,
    "expires_at" => "2024-01-01 00:00:00",
], 1);
// Bypass model whitelist and force active for the test
global $wpdb;
$wpdb->update($wpdb->prefix . "crm_vouchers", ["status" => "active"], ["id" => $id]);
echo "voucher_id=$id", PHP_EOL;'
```

**Steps**
1. POST to `/zippy-crm/v1/vouchers/{voucher_id}/claim`.

**Expected**
- [ ] HTTP 410, code `voucher_expired`
- [ ] Message: "Voucher has expired."
- [ ] Voucher does NOT appear in the customer's available list (the SQL filter excludes it)

---

### TC-VCH-06: Use claimed voucher on order → status flips to `used`

**Setup** — claim from TC-VCH-02 still in `claimed` state

**Steps**
1. Apply the voucher code at checkout.
2. Place order, complete payment, mark order `completed`.
3. Reload `/my-account/crm-vouchers/` → **My Claims** tab.

**Expected**
- [ ] Status badge flipped from `Ready to use` → `Used`
- [ ] Card is dimmed to 75% opacity
- [ ] "Used MMM D · Order #N" sub-line appears
- [ ] No copy/code chip (used vouchers don't show the code anymore)
- [ ] `crm_vouchers.uses_count` for that voucher incremented by 1 (verify: `wp eval 'echo ZippyCrm\Models\Voucher::find(VOUCHER_ID)["uses_count"];'`)

---

## Notifications (TC-NOT)

### TC-NOT-01: Default state — both channels on

**Setup**
```bash
wp eval 'ZippyCrm\Support\Seeder::reset();
    $ids = ZippyCrm\Support\Seeder::seed_members(1);
    // Wipe the seeded prefs so we test the no-row-yet path
    global $wpdb;
    $wpdb->delete($wpdb->prefix . "crm_notif_subs", ["user_id" => $ids[0]]);
    ZippyCrm\Services\SubsManager::invalidate($ids[0]);
    echo "user_id=", $ids[0];'
```

**Steps**
1. Log in.
2. Browse to `/my-account/crm-notifications/`.

**Expected**
- [ ] Both toggles render in the **on** position (dark pill, ball on right)
- [ ] No "Unsaved changes" hint
- [ ] No "All channels off" amber notice
- [ ] **Save preferences** button is disabled (nothing to save — defaults match server state)

---

### TC-NOT-02: Toggle off → save → updated_at timestamps

**Steps**
1. Toggle **New vouchers and promotions** off.
2. Click **Save preferences**.

**Expected**
- [ ] Button shows loading spinner briefly
- [ ] After save, "Last saved just now" appears
- [ ] Reload the page — the toggle stays off (server-side persisted)
- [ ] Verify in DB: `wp eval 'print_r(ZippyCrm\Models\NotifSub::get_for_user(USER_ID));'` shows `subscribed_vouchers: false` and a recent `updated_at`

---

### TC-NOT-03: All channels off → amber notice appears

**Steps**
1. Continuing from TC-NOT-02 (vouchers off), toggle **Points and rewards updates** off too.
2. Click **Save preferences**.

**Expected**
- [ ] Amber notice appears below the form: "You've turned off all CRM notifications."
- [ ] Subtext mentions transactional emails are still received
- [ ] Both toggles persist across reload

---

### TC-NOT-04: Registration form respects opt-in checkboxes

**Setup**
```bash
wp eval 'ZippyCrm\Support\Seeder::reset();'
# Make sure WC registration is enabled: WC → Settings → Accounts & Privacy → "Allow customers to create an account on the My account page"
```

**Steps**
1. Log out. Browse to `/my-account/`.
2. The two opt-in checkboxes ("Notify me about new vouchers…", "Notify me about points…") should appear, **both checked by default**.
3. **Uncheck** the vouchers one. Submit registration.
4. After register, log in and visit `/my-account/crm-notifications/`.

**Expected**
- [ ] Vouchers toggle is **off**, points toggle is **on**
- [ ] DB row exists: `wp eval 'print_r(ZippyCrm\Models\NotifSub::get_for_user(NEW_USER_ID));'`
- [ ] If registration form shows an error and re-renders, the checkbox state is sticky (vouchers stays unchecked)

---

## End-to-end flows (TC-E2E)

### TC-E2E-01: Full purchase flow exercises every feature

**Setup**
```bash
wp eval 'ZippyCrm\Support\Seeder::reset();
    $ids = ZippyCrm\Support\Seeder::seed_members(1);
    ZippyCrm\Support\Seeder::seed_vouchers(3);
    echo "user_id=", $ids[0];'
```

**Steps**
1. Log in as the seeded user (default `free` tier).
2. Visit `/my-account/crm-membership/` → confirm `Free`, multiplier `1×`.
3. Visit `/my-account/crm-vouchers/` → claim one voucher.
4. Add ~$600 of products to cart, apply the claimed voucher code at checkout.
5. Complete payment, mark order `completed` (admin or `wp eval 'wc_get_order(ORDER_ID)->update_status("completed");'`).
6. Reload all four CRM tabs.

**Expected**
- [ ] **Membership** tab shows `Silver` (lifetime spend ≥ $500 → tier upgraded)
- [ ] **Points** tab shows balance = `floor(subtotal_after_discount) × 1.2` (multiplier already silver because tier eval runs *before* points award)
- [ ] **Vouchers → My Claims** shows the claim flipped to `Used`
- [ ] **Notifications** tab is unchanged (TC-NOT covers it)
- [ ] No PHP errors in `wp-content/debug.log`

---

### TC-E2E-02: Reserve, abandon, expire — points return cleanly

**Setup**
```bash
wp eval '
$ids = ZippyCrm\Support\Seeder::seed_members(1);
$uid = $ids[0];
ZippyCrm\Models\PointsLedger::insert($uid, "adjust", 200, "Seed", null);
ZippyCrm\Models\PointsSummary::apply_delta($uid, 200);
ZippyCrm\Services\PointsEngine::invalidate($uid);
echo "user_id=$uid", PHP_EOL;'
```

**Steps**
1. Log in. Visit `/my-account/crm-points/`.
2. Redeem 100 pts → get coupon.
3. Don't use the coupon. Backdate the ledger row 25h:
   ```bash
   wp eval 'global $wpdb; $wpdb->query("UPDATE {$wpdb->prefix}crm_points_ledger
       SET created_at = (UTC_TIMESTAMP() - INTERVAL 25 HOUR)
       WHERE user_id = USER_ID AND type = \"pending_redeem\"");
       ZippyCrm\Services\PointsEngine::invalidate(USER_ID);'
   ```
4. Reload `/my-account/crm-points/`.

**Expected**
- [ ] Hero `available` is back to 200 (was 100 right after redeem)
- [ ] Hero `reserved` is 0 (or sub-line hidden entirely)
- [ ] Total balance is still 200 (no debit ever happened — points were never spent)
- [ ] Activity log still shows the `Reserved` row, but it's no longer counting against available

---

### TC-E2E-03: Voucher publish → email queue (with mocked wp_mail)

**Setup**
```bash
wp eval '
ZippyCrm\Support\Seeder::reset();
ZippyCrm\Support\Seeder::seed_members(5);
// Subscribe everyone to vouchers
foreach (get_users(["search" => "zcseed_*", "search_columns" => ["user_login"]]) as $u) {
    ZippyCrm\Models\NotifSub::upsert($u->ID, true, true);
}
$id = ZippyCrm\Models\Voucher::create([
    "code" => "E2ETEST" . wp_generate_password(4, false, false),
    "title" => "E2E test 15% off",
    "discount_type" => "percent",
    "discount_value" => 15,
    "expires_at" => "2026-12-31 23:59:59",
], 1);
echo "voucher_id=$id", PHP_EOL;'
```

**Steps**
1. Capture mail without actually sending:
   ```bash
   wp eval '
   $captured = [];
   add_filter("pre_wp_mail", function($_, $atts) use (&$captured) { $captured[] = $atts; return true; }, 10, 2);
   ZippyCrm\Services\VoucherService::publish(VOUCHER_ID);
   ZippyCrm\Services\NotifEngine::dispatch_batch(VOUCHER_ID, 0);
   echo "captured=", count($captured), PHP_EOL;
   global $wpdb;
   foreach ($wpdb->get_results("SELECT user_id, status FROM {$wpdb->prefix}crm_notification_log
       WHERE voucher_id = VOUCHER_ID") as $r) echo "  user $r->user_id → $r->status\n";'
   ```

**Expected**
- [ ] `captured = 5` (one email per opted-in seeded user)
- [ ] All 5 log rows have `status = sent`
- [ ] Replaying `dispatch_batch` again does NOT re-send (status already `sent` → skipped)
- [ ] One subscriber's email subject contains "New Voucher: E2E test 15% off — Save 15% on your next order"

---

## Cleanup after running the suite

```bash
wp eval 'ZippyCrm\Support\Seeder::reset();'
# Plus: clear any test orders manually if you want a totally clean slate.
```

---

## When a test fails

1. **Read the diff in expected vs actual carefully** — many "failures" are state pollution from a previous test (e.g. forgot to reset between TC-PTS cases).
2. **Check `wp-content/debug.log`** — most plugin issues surface there.
3. **Check `crm_notification_log` and `crm_points_ledger` directly** — they're append-only audit trails that show what the system thought happened.
4. **Re-run the setup block in isolation** — confirms whether the bug is in setup or in the steps.
5. **File the failure with the TC ID** (e.g. "TC-PTS-03 fails: balance dropped on reserve") — the ID is greppable in the codebase.
