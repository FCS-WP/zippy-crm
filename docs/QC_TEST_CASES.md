# QC Test Cases — Customer-Facing UI

End-to-end manual test pass for a non-technical QA tester. Pure click-through; no terminal, no database, no admin screens. Just log in, navigate the site, observe what happens.

> **Looking for the developer version?** See [docs/TEST_CASES.md](./TEST_CASES.md) — same flows but with `wp eval` setup blocks and DB verification commands.

---

## Before you start

### 1. The dev runs this once for you

Ask the developer to run **one** command before you begin:

```bash
wp eval 'ZippyCrm\Support\Seeder::seed_qc_fixtures();'
```

That creates the test accounts and vouchers listed below. It's idempotent — they can re-run it any time to reset everything to a clean state.

### 2. Test accounts

All accounts use the same password: **`TestQA1234!`**

| Login | Tier | Points | Status | Use for |
|---|---|---|---|---|
| `qa-free-1` | Free | 0 | Active | New customer experience, "earn points" first time |
| `qa-silver-1` | Silver | 250 | Active | **Default tester.** Most cases use this account. |
| `qa-gold-1` | Gold | 1,500 | Active | High-balance redemption tests |
| `qa-vip-1` | VIP | 800 | Active | VIP-tier behavior, never-auto-downgrade test |
| `qa-suspended-1` | Silver | 100 | **Suspended** | Negative tests — system must refuse claims/redeems |

### 3. Test vouchers (the dev pre-publishes these)

| Code | Type | Discount | Min order | Notes |
|---|---|---|---|---|
| `QA-PERCENT-25` | Percent | 25% | — | Already claimed by `qa-silver-1` so My Claims is non-empty |
| `QA-FIXED-10` | Fixed cart | $10 | — | For claim + apply-to-cart tests |
| `QA-MINORDER-15` | Percent | 15% | $50 | Tests the minimum-order threshold |
| `QA-EXPIRED` | Fixed cart | $5 | — | Already expired — must NOT appear in customer list |

### 4. How to log in as a test account

1. Browse to `/my-account/`
2. Enter the test login (e.g. `qa-silver-1`) and password (`TestQA1234!`)
3. Click **Log in**

If you stay logged in between cases, that's fine — most cases use `qa-silver-1`. Cases that need a different account will tell you to log out and log in as someone else.

### 5. How to record results

Each test case ends with a **Result block**. After running it:

- Tick the `[ ]` boxes for each assertion that's true
- Mark the overall result `Pass`, `Fail`, or `Blocked`
- Add your name and the date
- If anything's wrong, write it under **Notes** — be specific (what you saw, what you expected)

Submit failures back to the dev with the test case ID (e.g. "TC-PTS-02 failed"). The IDs are stable.

---

## Table of contents

- [Membership tab](#membership-tab-tc-mbr)
- [Points tab](#points-tab-tc-pts)
- [Vouchers tab](#vouchers-tab-tc-vch)
- [Notifications tab](#notifications-tab-tc-not)
- [End-to-end customer journey](#end-to-end-customer-journey-tc-e2e)

---

## Membership tab (TC-MBR)

### TC-MBR-01: Free tier user sees the right info

**Account:** `qa-free-1`

**Steps**
1. Log in.
2. Click **Membership** in the My Account sidebar.

**Expected**
- [ ] Top card shows the name "Quinn Free"
- [ ] A grey-ish badge says **Free**
- [ ] Another badge says **active**
- [ ] "Points multiplier" shows **1×**
- [ ] "Member since" shows today's date
- [ ] "Expires" shows **Never**
- [ ] A second card titled "Progress to Silver" is visible
- [ ] The progress bar is at 0% (or near it)
- [ ] Right side shows "Total orders: 0" and "Lifetime spend: $0.00"

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-MBR-02: Silver tier shows multiplier and progress to Gold

**Account:** `qa-silver-1`

**Steps**
1. Log in.
2. Click **Membership**.

**Expected**
- [ ] Name "Sam Silver"
- [ ] Badge says **Silver** (light grey, distinct from Free)
- [ ] Multiplier shows **1.2×**
- [ ] Progress card title says "Progress to **Gold**" (not Silver)
- [ ] Below the progress bar there's a sentence like "Spend $X to reach Gold" or "X more orders to reach Gold"

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-MBR-03: VIP tier shows no progress card

**Account:** `qa-vip-1`

**Steps**
1. Log in.
2. Click **Membership**.

**Expected**
- [ ] Badge says **VIP** (distinct color, not silver/gold)
- [ ] Multiplier shows **2×**
- [ ] There is **no** "Progress to next tier" card — instead a card saying something like "You've reached the top tier"

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-MBR-04: Suspended account shows the right status

**Account:** `qa-suspended-1`

**Steps**
1. Log in.
2. Click **Membership**.

**Expected**
- [ ] Badge says **suspended** (red or warning color)
- [ ] Account info still loads (name, joined date, multiplier)
- [ ] No error or blank page

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

## Points tab (TC-PTS)

### TC-PTS-01: Silver user sees their balance correctly

**Account:** `qa-silver-1`

**Steps**
1. Log in.
2. Click **Points** in the sidebar.

**Expected**
- [ ] Hero card shows the headline number **250 pts** (large)
- [ ] Subtitle below: "Worth $12.50 at checkout" (250 ÷ 20 = 12.5)
- [ ] Tier badge in the hero says **SILVER** with "1.2× earn rate"
- [ ] Three small "insights" cards: Earned 30d, Redeemed 30d, Lifetime worth
- [ ] On the right, a **Redeem points** card with chip buttons ($1, $2, etc.) and a slider
- [ ] An **Activity** card lists past transactions (likely just "QC fixture seed" + 250)

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-PTS-02: Redeem 100 points → coupon code appears

**Account:** `qa-silver-1` (must have ≥ 100 pts; reset via `seed_qc_fixtures` if balance is wrong)

**Steps**
1. Open the **Points** tab.
2. In the **Redeem points** card on the right, click the chip labelled **$5** (or drag the slider to 100).
3. Click the **Get $5 coupon** button.

**Expected**
- [ ] Button briefly shows a loading spinner
- [ ] A green box appears showing a coupon code starting with `CRM-RDM-`
- [ ] The code has 12 random characters after `CRM-RDM-` (so total length is 20)
- [ ] A **Copy** button is next to the code
- [ ] Clicking **Copy** shows "Copied" briefly
- [ ] **Important — the headline balance has NOT dropped.** It still shows **250 pts**.
- [ ] Below the headline number, a new line appears: "250 total · 100 reserved in pending coupons"
- [ ] The Redeem panel's max value drops by 100 (so the next slider max is 150 instead of 250)
- [ ] In the **Activity** list, a new amber row appears with type "Reserved" and the coupon code as a chip

**Why the balance shouldn't drop:** points are only deducted when the coupon is actually used at checkout. If you abandon the coupon, the points come back. This is the design.

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-PTS-03: Try to redeem more than available — clear error

**Account:** `qa-silver-1` (continuing from TC-PTS-02 — has 250 balance, 100 reserved, 150 available)

**Steps**
1. Drag the redeem slider all the way right (it should clamp at 150).
2. Confirm the chip buttons only go up to "Max · 150" (no $7+ chip available).
3. *(If you can override the slider value via dev tools or by typing a number, try entering 200 and submitting — but this isn't required.)*

**Expected**
- [ ] Slider max is 150, not 250
- [ ] No chip lets you select more than 150
- [ ] Hint text under the form mentions the reservation, e.g. "10 pts left over · earn 10 more to round up"

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-PTS-04: Activity list shows transaction history

**Account:** `qa-silver-1`

**Steps**
1. Open the **Points** tab.
2. Scroll to the **Activity** card.

**Expected**
- [ ] At least 2 rows visible (the seed adjust + the reservation from TC-PTS-02 if you ran it)
- [ ] Each row has a colored circle icon on the left (green up-arrow for earn, blue card for redeem, amber lock for reserved)
- [ ] Each row shows: description text · type label · date
- [ ] Earn rows show "+N" in green
- [ ] Reserved rows show "N" in amber with "reserved" sub-text
- [ ] If there are >10 rows, **Previous** / **Next** buttons appear at the bottom

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-PTS-05: New customer with 0 points sees a helpful empty state

**Account:** `qa-free-1`

**Steps**
1. Log out, log in as `qa-free-1`.
2. Open the **Points** tab.

**Expected**
- [ ] Hero shows **0 pts** as the big number
- [ ] No insights cards crash or show errors (might show all zeros — that's fine)
- [ ] Redeem panel is greyed out / disabled with a message like "Earn at least 20 points to redeem your first reward"
- [ ] Activity card shows "No activity yet" or similar empty state

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-PTS-06: Suspended account cannot redeem

**Account:** `qa-suspended-1` (Silver tier, 100 pts, suspended)

**Steps**
1. Log out, log in as `qa-suspended-1`.
2. Open the **Points** tab.
3. Try to redeem 20 pts (the minimum).

**Expected**
- [ ] Hero balance shows **100 pts**
- [ ] Redeem form is visible (the front-end can't tell us about suspension up front — the server returns the error after submit)
- [ ] After clicking the Get coupon button, an error appears mentioning the account is suspended
- [ ] No coupon was created (balance unchanged)

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

## Vouchers tab (TC-VCH)

### TC-VCH-01: Available vouchers list

**Account:** `qa-silver-1`

**Steps**
1. Log in.
2. Click **Vouchers** in the sidebar.
3. Make sure the **Available** sub-tab is selected (it's the default).

**Expected**
- [ ] Two sub-tab buttons at the top: **Available · 2** and **My Claims · 1** (counts may differ slightly if `QA-EXPIRED` ever leaks; if you see 3, that's a bug)
- [ ] Card grid shows **2** voucher cards (QA-FIXED-10 and QA-MINORDER-15 — `QA-PERCENT-25` is already claimed by this account, `QA-EXPIRED` is filtered out)
- [ ] Each card has a colored gradient header showing the discount amount (e.g. "$10 off cart" or "15% off")
- [ ] Each card has a description, expiry pill (top right), and a black **Claim voucher** button
- [ ] The card with "min $50" mentions the minimum order somewhere

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-02: Expired voucher does NOT appear

**Account:** `qa-silver-1`

**Steps**
1. On the **Available** tab, scan the cards.
2. Look for any voucher labelled `QA-EXPIRED`.

**Expected**
- [ ] **No `QA-EXPIRED` card appears anywhere** on the page
- [ ] The Available counter does NOT include it

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-03: Claim a voucher → moves to My Claims instantly

**Account:** `qa-silver-1`

**Steps**
1. On **Available**, click **Claim voucher** on the `QA-FIXED-10` card.

**Expected**
- [ ] The card immediately changes to a green "Code claimed" state with the code shown
- [ ] A **Copy** button appears
- [ ] **No page reload happened** — the change is instant
- [ ] **Available** counter dropped by 1 (now 1)
- [ ] **My Claims** counter increased by 1 (now 2)
- [ ] Click the **My Claims** sub-tab — you see two claim cards: `QA-PERCENT-25` (pre-seeded) and `QA-FIXED-10` (just claimed)

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-04: Claim with empty cart shows the code; with full cart auto-applies

**Account:** `qa-silver-1`

**Part A — empty cart**

**Steps**
1. Make sure your cart is empty (visit `/cart/` and remove anything if needed).
2. Reset the fixtures (ask dev to re-run seed, this puts QA-MINORDER-15 back as unclaimed).
3. On Vouchers → Available, click **Claim voucher** on `QA-MINORDER-15`.

**Expected**
- [ ] Success block appears with the code
- [ ] Sub-text says **"Use it at checkout to apply the discount"** (NOT "Applied to your cart")

**Part B — non-empty cart**

**Steps**
1. Add a product worth ≥ $50 to your cart (so the min-order threshold is met).
2. Reset fixtures again.
3. Browse to Vouchers, click **Claim voucher** on `QA-MINORDER-15`.

**Expected**
- [ ] Success block appears
- [ ] Sub-text says **"Applied to your cart"** (different from Part A)
- [ ] Visit `/cart/` — the discount is already applied, no need to paste the code

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-05: My Claims sub-tab shows status correctly

**Account:** `qa-silver-1`

**Steps**
1. Click the **My Claims** sub-tab.

**Expected**
- [ ] At least one claim is visible: `QA-PERCENT-25` (the pre-seeded one)
- [ ] Card looks similar to Available cards (gradient stripe with discount)
- [ ] A **Ready to use** badge in the top right (green/teal)
- [ ] A dashed footer with the code (e.g. `QA-PERCENT-25`) and a **Copy code** button
- [ ] Below the card title, meta info: "Claimed [date]" + "Expires [date]"

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-06: Empty Available state when nothing to claim

**Account:** `qa-silver-1` — first claim everything available so the Available list is empty

**Steps**
1. On **Available**, click Claim on every card until none are left.
2. The Available tab should now show counter `Available · 0`.

**Expected**
- [ ] Empty state card appears: "No vouchers available right now"
- [ ] Subtext: "Check back later — new offers drop regularly."
- [ ] No layout breakage

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

## Notifications tab (TC-NOT)

### TC-NOT-01: Default state — both channels on

**Account:** `qa-silver-1` (or any seeded account — they all default to opted in)

**Steps**
1. Click **Notifications** in the sidebar.

**Expected**
- [ ] Card titled "Notification preferences"
- [ ] Two rows, each with a label + description + toggle switch on the right
- [ ] Both switches are in the **on** position (dark pill, ball on the right)
- [ ] No "Unsaved changes" warning
- [ ] No amber notice
- [ ] **Save preferences** button appears greyed out (disabled — nothing has changed)

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-NOT-02: Turn one off, save, reload — it sticks

**Account:** `qa-silver-1`

**Steps**
1. Click the toggle next to "New vouchers and promotions" — it should slide off (light pill, ball on the left).
2. Notice an "Unsaved changes" hint appears.
3. Click **Save preferences**.
4. Wait for the spinner to finish.
5. Reload the page.

**Expected**
- [ ] After save, hint changes to "Last saved just now"
- [ ] After reload, the vouchers toggle is **still off**
- [ ] The points toggle is still on
- [ ] Save button is disabled again (nothing pending)

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-NOT-03: Turn everything off → amber warning appears

**Account:** `qa-silver-1` (continuing from TC-NOT-02 — vouchers already off)

**Steps**
1. Toggle "Points and rewards updates" off as well.
2. Click **Save preferences**.

**Expected**
- [ ] An amber/yellow notice appears below the form
- [ ] Notice says something like "You've turned off all CRM notifications"
- [ ] Subtext mentions transactional emails (orders) are unaffected
- [ ] Reload — both toggles are still off, notice is still visible

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-NOT-04: Turn one back on → amber warning disappears

**Account:** `qa-silver-1` (continuing from TC-NOT-03)

**Steps**
1. Toggle "New vouchers and promotions" back on.
2. Click **Save preferences**.

**Expected**
- [ ] Amber notice disappears immediately (or after save)
- [ ] Reload — vouchers on, points off, no notice

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-NOT-05: Registration form has opt-in checkboxes

**Account:** Logged out

**Steps**
1. Log out.
2. Browse to `/my-account/`.
3. Look at the registration form section.

**Expected**
- [ ] Two checkboxes appear (label like "Notify me about new vouchers and promotions" and "Notify me about points and rewards updates")
- [ ] **Both are checked by default**
- [ ] You can uncheck them
- [ ] *(If you actually register: the new account's Notifications tab reflects what you chose — but you don't have to test this if it's risky to create accounts in your test env)*

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

## End-to-end customer journey (TC-E2E)

These cases tie multiple features together. They're the most realistic — what a real customer actually does.

### TC-E2E-01: Browse → claim → use → tier auto-upgrade

**Account:** `qa-free-1` (zero history, free tier)

**Steps**
1. Log in.
2. Visit **Membership** — confirm Free, multiplier 1×.
3. Visit **Vouchers** → claim `QA-FIXED-10` (or any available).
4. Browse the shop, add ~$600 of products to your cart.
5. At checkout, paste the claimed coupon code (or it should already be applied if your cart was non-empty when claiming).
6. Complete payment. Ask the dev or admin to mark the order **completed** in WooCommerce.
7. Refresh **Membership**.

**Expected**
- [ ] Membership tab now shows **Silver** (lifetime spend ≥ $500 → auto-upgrade fired)
- [ ] Multiplier is now **1.2×**
- [ ] Progress card now targets **Gold**
- [ ] Visit **Points** — balance increased; the earn amount used the silver multiplier (because tier eval runs *before* the points award)
- [ ] Visit **Vouchers** → My Claims — the `QA-FIXED-10` claim is now marked **Used**, with a dimmed card style and "Used [date]" text

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-E2E-02: Reserve → abandon → balance returns

**Account:** `qa-gold-1` (1500 pts, gold tier)

**Steps**
1. Log in.
2. Visit **Points**. Note the available balance (1500).
3. Click the **$10** chip in the redeem panel and **Get $10 coupon**. Don't use the coupon.
4. After 24 hours OR ask the dev to age the reservation (`SET created_at = NOW() - INTERVAL 25 HOUR ...`), reload **Points**.

**Expected (immediately after redeem)**
- [ ] Total balance: 1500 (unchanged)
- [ ] Available: 1300 (1500 - 200)
- [ ] Sub-line: "1500 total · 200 reserved in pending coupons"
- [ ] An amber Reserved row in Activity

**Expected (after 24h has elapsed / dev aged the row)**
- [ ] Available is back to 1500
- [ ] No more "reserved" sub-line
- [ ] Total balance still 1500 (no debit ever happened — points were never spent)
- [ ] The Reserved row is still visible in Activity (audit trail) but greyed/dimmed

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-E2E-03: VIP tier never auto-downgrades

**Account:** `qa-vip-1`

**Steps**
1. Log in. Confirm Membership shows **VIP**, multiplier **2×**.
2. Place + complete a small order ($10).

**Expected**
- [ ] Tier is **still VIP** after the order completes (the system never downgrades VIP automatically)
- [ ] Points awarded use the **2× multiplier**
- [ ] No "Progress to next tier" card appears

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

## Final results summary

After completing the pass, fill this in:

| Section | Total | Pass | Fail | Blocked |
|---|---|---|---|---|
| Membership | 4 | | | |
| Points | 6 | | | |
| Vouchers | 6 | | | |
| Notifications | 5 | | | |
| End-to-end | 3 | | | |
| **Total** | **24** | | | |

**Tester:**
**Date pass started:**
**Date pass completed:**
**Build / version tested:**
**Browser / device:**

**High-priority bugs found** (paste TC IDs and one-liners):

```
e.g.
- TC-PTS-02 — clicking Get coupon dropped my balance from 250 to 150 (should stay 250)
- TC-VCH-04B — code applied but discount didn't show in cart total
```

**Other observations / suggestions:**

```


```

---

## When something fails

1. **Re-run that test case once more** — sometimes a transient cache or stale page is to blame
2. **Note exactly what you saw vs what was expected** — screenshots help
3. **Note which step failed** (step 2? step 4?) — narrows down where to look
4. **File the failure with the test case ID** (e.g. "TC-VCH-03") — devs can grep the codebase for the ID

If you're not sure whether something is a bug or expected behavior, mark it **Blocked** and write your question in the notes — the dev will clarify.
