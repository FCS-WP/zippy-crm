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
| **Multi-code campaign — 20% off (3 slots)** | Percent | 20% | — | One voucher, three unique codes — `QAMC-AAA111`, `QAMC-BBB222`, `QAMC-CCC333`. The customer never sees those codes in the **Available** list — they see the campaign card. On claim, each of the first three claimers is assigned **one** unique code at random. The 4th claimer is rejected (no slots left). |
| `QA-GOLDVIP-30` | Percent | 30% | — | **Tier-restricted to Gold + VIP only.** Free and Silver members must NOT see it in their Available list. Gold and VIP members can claim normally. If a claimed-by member is later downgraded out of Gold/VIP, their claim is auto-revoked (status flips to `expired`). |

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

### TC-PTS-07: Tier with earn rate 0 awards no points

> **What this tests:** A tier whose earn rate is set to 0 must award no points on order completion, regardless of the order subtotal. This is the default for new tiers in v1.12.0.
>
> Requires admin access.

**Account:** `qa-vip-1` (VIP tier — admin-only so the auto-evaluator won't promote them)

**Steps — admin sets VIP rate to 0**
1. Log in to `/wp-admin/` as administrator. Go to **Zippy CRM → Tiers**.
2. Find the **VIP** tier and click to edit.
3. Set **Earn rate (points per $1)** to `0`.
4. Save.

**Expected — Tiers list**
- [ ] The VIP row's Earn rate column now shows **"No earn"** (not "0.00 pt/$")

**Steps — customer places order**
5. Log out, log in as `qa-vip-1`.
6. Note the current points balance (visible on the Points tab).
7. Add any product to your cart, complete checkout, and pay.
8. Wait for the order status to flip to Completed (admin may need to mark it so).

**Expected**
- [ ] After completion, qa-vip-1's points balance is **unchanged** (no earn row added)
- [ ] In the Points → Activity list, no new "Order #N" earn entry appears for this order

**Cleanup** — restore VIP earn rate to 2.0 (or whatever your seed default is) so other tests aren't affected.

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-PTS-08: Excluded products + categories don't earn points

> **What this tests:** The new Settings panel lets admins blacklist specific products or categories. Line items matching the blacklist must contribute zero to the points earn calculation, while unblacklisted items in the same order still earn normally.
>
> Requires admin access. Test products are easier to verify if you have at least 2 distinct products in 2 distinct categories.

**Account:** `qa-vip-1` (admin-only tier so auto-promotion doesn't confuse the test)

**Pre-condition:** VIP earn rate is set to `1` (1 pt per $1). If you ran TC-PTS-07, restore it to 1 first.

**Steps — admin configures blacklist**
1. Log in to `/wp-admin/`. Go to **Zippy CRM → Settings**.
2. Verify the page loads with three sections:
   - "Earn rate" (info card linking to Tiers)
   - "Excluded products"
   - "Excluded categories"
3. In **Excluded products**, click "Add" and pick **one specific product** (e.g. "Test Product A").
4. Save.

**Expected — Settings page**
- [ ] The chosen product appears as a chip in the Excluded products field
- [ ] A green "Saved [time]" indicator appears next to the Save button after saving
- [ ] Reload the page — the product is still listed (persisted)

**Steps — customer order with mixed cart**
5. Log out, log in as `qa-vip-1`. Note the current points balance.
6. Add to cart: **1× the excluded product** (e.g. $10) AND **1× any other (non-excluded) product** (e.g. $15).
7. Complete checkout, pay. Mark the order completed if needed.

**Expected**
- [ ] Points awarded ≈ floor of the **non-excluded** subtotal only (e.g. ~15 pts in this example)
- [ ] The excluded product's $10 is **not** counted toward earned points
- [ ] The Activity list shows one "Order #N" entry with the partial amount

**Steps — exclude an entire category**
8. Back in admin Settings, **clear the product blacklist** and add the category that contained the previous excluded product to **Excluded categories**.
9. Save.
10. Repeat the order from step 6 (any product in that category + any product not in it).

**Expected**
- [ ] Now ALL products in the excluded category contribute 0 points (not just the one previously excluded by ID)
- [ ] The other product still earns normally

**Cleanup** — clear both blacklist sections so other tests aren't affected.

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

### TC-VCH-07: Multi-code campaign — first claim assigns a unique code

> **What this tests:** A "multi-code" voucher is one campaign with multiple unique codes inside it. Each customer who claims gets *their own* unique code (not the same one shared by everyone). This case verifies the first claim works and the right code shows up.

**Account:** `qa-silver-1`

**Pre-condition:** Ask the dev to **reset fixtures** so all three slots are still available:
```bash
wp eval 'ZippyCrm\Support\Seeder::reset(); ZippyCrm\Support\Seeder::seed_qc_fixtures();'
```

> **Optional admin sanity check first:** if you have admin access, log in to `/wp-admin/` and go to **Zippy CRM → Vouchers**. The multi-code campaign should appear in the list with a violet **"Multi-code"** badge in the Code column (instead of a real code). Click the row to open the edit drawer — the form should show "Multi-code (public)" selected and a note that codes were minted at create time. **Do not edit the multi-code voucher** — re-saving it has no effect on the codes (they're immutable).

**Steps**
1. Log in as `qa-silver-1`.
2. Go to **My Account → Vouchers → Available**.
3. Find the card titled **"QA — multi-code 20% off (3 slots)"**.

**Expected — before claim**
- [ ] Card shows the title and description ("Multi-code campaign — each user gets their own unique code; only 3 customers total can claim.")
- [ ] Card shows **20%** discount and the expiry date
- [ ] Card does **NOT** display any code yet — customers shouldn't see real codes until they claim
- [ ] A **Claim voucher** button is visible

**Steps (continued)**
4. Click **Claim voucher**.

**Expected — after claim**
- [ ] No page reload happened — the change is instant
- [ ] Card immediately switches to a green "claimed" state with a code visible
- [ ] The visible code starts with `QAMC-` and matches one of: `QAMC-AAA111`, `QAMC-BBB222`, or `QAMC-CCC333`
- [ ] **Write down which code you got** — you'll need it for the next test case
- [ ] **My Claims** sub-tab counter increased by 1
- [ ] Switch to **My Claims** — the new claim card shows the same code you just got

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Code assigned to this tester:** ____________________
**Notes:**

---

### TC-VCH-08: Multi-code campaign — different customers get different codes

> **What this tests:** Two more customers claim the same campaign — each must get a **different** code than the first tester (and from each other). This is the core promise of multi-code: one campaign, N unique codes, no overlap.

**Pre-condition:** Run TC-VCH-07 first (don't reset fixtures between TC-VCH-07 and this case).

#### Part A — second customer

**Account:** `qa-gold-1`

**Steps**
1. Log out of `qa-silver-1`. Log in as `qa-gold-1`.
2. Go to **Vouchers → Available**, find the multi-code campaign card, and click **Claim voucher**.

**Expected**
- [ ] Claim succeeds; a code is shown
- [ ] The code is **different** from the one `qa-silver-1` got in TC-VCH-07
- [ ] The code is one of the remaining two: `QAMC-AAA111` / `QAMC-BBB222` / `QAMC-CCC333` (whichever wasn't picked in TC-VCH-07)
- [ ] **Write down this second code**

**Code assigned to qa-gold-1:** ____________________

#### Part B — third customer

**Account:** `qa-vip-1`

**Steps**
1. Log out. Log in as `qa-vip-1`.
2. Claim the same multi-code campaign.

**Expected**
- [ ] Claim succeeds; a code is shown
- [ ] The code is **different** from both codes already taken
- [ ] After this claim, all three codes (`QAMC-AAA111`, `QAMC-BBB222`, `QAMC-CCC333`) have been distributed exactly once across the three testers — no duplicates

**Code assigned to qa-vip-1:** ____________________

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-09: Multi-code campaign — 4th claim is rejected (quota exhausted)

> **What this tests:** A 3-slot campaign has a hard cap. Once 3 customers have claimed, the 4th must be turned away — the card should disappear from **Available** OR show a "no slots left" / quota-exhausted state.

**Pre-condition:** Run TC-VCH-07 and TC-VCH-08 first. All three slots are now taken.

**Account:** `qa-free-1` (the only remaining seeded account that hasn't claimed)

**Steps**
1. Log out. Log in as `qa-free-1`.
2. Go to **Vouchers → Available**.

**Expected**
- [ ] The "QA — multi-code 20% off (3 slots)" card is **NOT** in the Available list (it has been removed because no slots remain)
- [ ] **OR** the card appears but with a "Fully claimed" / "No slots remaining" indicator and the **Claim voucher** button is disabled or absent
- [ ] If the tester does see a Claim button and clicks it, the system shows a clear error message (e.g. "No more vouchers available" / "Quota exceeded") — not a silent failure or page crash

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-10: Customer A's code can't be used by customer B at checkout

> **What this tests:** Each multi-code is tied to one customer. Even if customer B somehow learns customer A's code, applying it at checkout must fail — codes are single-use AND user-bound after claim.

**Pre-condition:** TC-VCH-07 must have been run so `qa-silver-1` has been assigned a code. You'll use the code value `qa-silver-1` got (write it down).

**Account:** `qa-gold-1`

**Steps**
1. Log in as `qa-gold-1`.
2. Add any product to your cart.
3. Go to `/cart/`.
4. In the **"Add a coupon"** field, paste the code that `qa-silver-1` was assigned (NOT the code `qa-gold-1` got).
5. Click Apply.

**Expected**
- [ ] WooCommerce rejects the coupon — error message appears (e.g. "Coupon usage limit has been reached", "This coupon does not exist", or similar)
- [ ] No discount is applied to the cart
- [ ] Cart total is unchanged

**Then** apply `qa-gold-1`'s OWN code (the one assigned during TC-VCH-08 Part A):
- [ ] That coupon applies cleanly and a 20% discount appears

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-11: Multi-code — same customer can't claim twice

> **What this tests:** A customer who has already claimed from a multi-code campaign can't claim a second time (they shouldn't get two codes from the same campaign).

**Account:** `qa-silver-1` (already claimed in TC-VCH-07)

**Steps**
1. Log in as `qa-silver-1`.
2. Go to **Vouchers → Available**.

**Expected**
- [ ] The "QA — multi-code 20% off (3 slots)" card is **NOT** in the Available list (qa-silver-1 already claimed it; the system filters out already-claimed campaigns)
- [ ] Switch to **My Claims** — qa-silver-1's claim is there with the original code, exactly once (no duplicate claim row)

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-12: Tier-restricted voucher — only qualifying members see it

> **What this tests:** A voucher restricted to Gold + VIP must be invisible to Free and Silver members on the Available list. This is the visibility filter — non-qualifying customers shouldn't see the offer at all (no "tease").

**Pre-condition:** Ask the dev to **reset fixtures**:
```bash
wp eval 'ZippyCrm\Support\Seeder::reset(); ZippyCrm\Support\Seeder::seed_qc_fixtures();'
```

**Steps**
1. Log in as `qa-free-1`. Go to **My Account → Vouchers → Available**.
2. Look for a card titled **"QA — Gold/VIP exclusive 30%"**.

**Expected — Free member**
- [ ] The Gold/VIP card is **NOT** present in the Available list

**Steps (continued)**
3. Log out. Log in as `qa-silver-1`. Go to **Vouchers → Available**.

**Expected — Silver member**
- [ ] The Gold/VIP card is **NOT** present in the Available list (Silver doesn't qualify)

**Steps (continued)**
4. Log out. Log in as `qa-gold-1`. Go to **Vouchers → Available**.

**Expected — Gold member**
- [ ] The Gold/VIP card **IS** present, with discount "30%"
- [ ] A Claim button is visible

**Steps (continued)**
5. Click **Claim voucher** on the Gold/VIP card.

**Expected**
- [ ] Claim succeeds — the card flips to a green "claimed" state with the code visible
- [ ] **My Claims** counter increased by 1

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-13: Tier downgrade auto-revokes the claim

> **What this tests:** If a Gold member claims a Gold/VIP voucher and then gets downgraded to Silver (e.g. by an admin or by inactivity), the claim must be auto-revoked. The customer's Ready-to-use code goes away — they can no longer use it at checkout.
>
> Requires admin access (only an admin can change someone's tier).

**Pre-condition:** TC-VCH-12 must have been run first — `qa-gold-1` has claimed the Gold/VIP voucher.

**Steps**
1. Log in to `/wp-admin/` as an administrator.
2. Go to **Zippy CRM → Members**.
3. Find `qa-gold-1` in the member list and click to open their record.
4. Change their tier from **Gold** to **Silver** and save.
5. Open a private/incognito window. Log in to `/my-account/` as `qa-gold-1`.
6. Go to **Vouchers → My Claims**.

**Expected**
- [ ] The "QA — Gold/VIP exclusive 30%" claim is shown with status **Expired** (or no longer appears as a usable / Ready-to-use claim)
- [ ] The Available list does NOT show the Gold/VIP voucher again — Silver doesn't qualify, so it stays hidden

**Steps (continued — recovery)**
7. As admin, change `qa-gold-1`'s tier back to **Gold**.
8. Reload the customer's My Account → Vouchers page.

**Expected**
- [ ] The voucher does NOT auto-restore to a usable state — once expired, it stays expired (claim history is preserved; the customer would need a new claim opportunity)
- [ ] The Available list now shows the Gold/VIP voucher again as fresh (it can be re-claimed because the prior claim is `expired`, not `claimed`)

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-14: Tier voucher rejects an unauthorized claim attempt

> **What this tests:** Even if a non-qualifying user somehow tries to claim (via stale URL or direct API call), the system must refuse. Belt-and-suspenders for the visibility filter.
>
> This is mostly a backend test; QA can verify by attempting via WP-CLI or just confirming the visibility filter in TC-VCH-12 matches the screenshot below.

**Account:** `qa-silver-1` (does not qualify for Gold/VIP voucher)

**Steps (manual UI path)**
1. Log in as `qa-silver-1`. Go to **Vouchers → Available**.
2. Confirm the Gold/VIP card is hidden (already covered in TC-VCH-12).

**Optional dev check** (skip if you don't have terminal access):
```bash
wp eval '$id = ZippyCrm\Models\Voucher::find_by_code("QA-GOLDVIP-30")["id"]; $silver = get_user_by("login", "qa-silver-1")->ID; var_dump(ZippyCrm\Services\ClaimHandler::claim($id, $silver));'
```
Expected output: `["valid" => false, "code" => "voucher_not_for_user", ...]`

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-15: Admin Codes drawer for multi-code campaigns

> **What this tests:** Admin can inspect per-code state for a multi-code voucher — see who claimed which code, who used theirs, and what's still available. This requires admin access.

**Pre-condition:** Run TC-VCH-07 / TC-VCH-08 first so at least one code is `assigned`. Bonus if you also walked through a checkout in TC-VCH-04 with the assigned code so one is `used`.

**Steps**
1. Log in to `/wp-admin/` as administrator.
2. Go to **Zippy CRM → Vouchers**.
3. Find the row "QA — multi-code 20% off (3 slots)". The Code column shows a violet **Multi-code** badge instead of a real code.
4. Open the row's **⋯ (overflow menu)**.

**Expected — menu items**
- [ ] An entry labeled **Codes** is present (this entry should NOT appear on single-code voucher rows like `QA-PERCENT-25`)

**Steps (continued)**
5. Click **Codes**.

**Expected — drawer opens**
- [ ] A drawer slides in from the right titled "Codes — QA — multi-code 20% off (3 slots)"
- [ ] Top of drawer shows **4 colored count tiles**: Total, Available, Assigned, Used (the numbers depend on what claims you've made; each tile sums correctly)
- [ ] Below the tiles is a row of filter chips: **All / Available (n) / Assigned (n) / Used (n) / Expired (n)** — counts match the tiles
- [ ] Below filters is a list of code rows. Each row shows:
  - The code (e.g. `QAMC-AAA111`) as a monospaced chip
  - A status pill (color-coded: green=available, blue=assigned, violet=used)
  - For assigned/used rows: the assignee's name and email below the code
  - For used rows: a "Used [date] · order #[n]" line on the right
  - For assigned rows: an "Assigned [date]" line on the right
  - For available rows: just a "—" on the right

**Steps (continued)**
6. Click the **Assigned** filter chip.

**Expected**
- [ ] List narrows to only assigned codes; count tile values stay the same (counts always reflect the full set, not the filter)

7. Click **Used** filter (if any used).

**Expected**
- [ ] List narrows to used codes only

8. Click **All** to clear the filter.

**Expected**
- [ ] All codes visible again

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-VCH-16: Tier-restricted multi-code campaign (combined feature)

> **What this tests:** Combining tier targeting with multi-code distribution — a single campaign of N unique codes available only to a specific tier. This is the most powerful voucher shape in the system.
>
> Requires admin access and at least one Gold + one VIP test account.

**Pre-condition:** `wp eval 'ZippyCrm\Support\Seeder::reset(); ZippyCrm\Support\Seeder::seed_qc_fixtures();'`

**Steps — admin creates the voucher**
1. Log in to `/wp-admin/` as administrator. Go to **Zippy CRM → Vouchers → New voucher**.
2. On the **General** tab:
   - Distribution mode: **Multi-code (public)**
   - Slots: `2`
   - Codes: leave empty (auto-generate)
   - Title: `Combined test — VIP/Gold multi`
   - Discount type: `Percent off (cart or per-item if restricted)`
   - Discount: `40`
3. On the **Restrictions** tab:
   - Audience: **Membership tiers**
   - Tick **Gold** and **VIP**
4. Save → Publish.

**Expected — on the Vouchers list**
- [ ] The new row shows the violet **Multi-code** badge in the Code column
- [ ] The Title column shows the title with an amber **Tier** badge next to it
- [ ] Discount column shows `40%`

**Steps — customer flow**
5. Open an incognito window, log in as `qa-free-1`. Visit My Account → Vouchers.

**Expected**
- [ ] The "Combined test — VIP/Gold multi" card is **NOT** visible

6. Log out, log in as `qa-silver-1`.

**Expected**
- [ ] Still **NOT** visible (Silver doesn't qualify)

7. Log out, log in as `qa-gold-1`. Click Claim.

**Expected**
- [ ] Card visible, claim succeeds, a unique code is shown

8. Log out, log in as `qa-vip-1`. Click Claim.

**Expected**
- [ ] Card visible, claim succeeds, a **different** unique code is shown

9. Open the admin Codes drawer for this voucher (per TC-VCH-15).

**Expected**
- [ ] Counts: Available 0, Assigned 2, Used 0
- [ ] Two rows show the gold + VIP customers as assignees

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

### TC-NOT-06: Tier-restricted vouchers only email qualifying members

> **What this tests:** When an admin publishes a Gold/VIP-only voucher, the email goes ONLY to Gold and VIP members. Free and Silver members must NOT receive a "new voucher available" email — that would tease them with offers they can't claim.
>
> Requires admin access and a working SMTP/test mailer (or the dev needs to inspect the queue table).

**Pre-condition:** `wp eval 'ZippyCrm\Support\Seeder::reset(); ZippyCrm\Support\Seeder::seed_qc_fixtures();'`

**Steps — admin publishes**
1. Log in to `/wp-admin/`. Go to **Zippy CRM → Vouchers → New voucher**.
2. Fill: Title `Notif tier test 25%`, Discount type `Percent off`, Discount `25`.
3. Restrictions tab → Audience: **Membership tiers** → tick **Gold** and **VIP**.
4. Save → Publish.

**Dev-side verification** (skip if you don't have terminal access):
```bash
wp eval '$id = ZippyCrm\Models\Voucher::find_by_code(strtoupper("notif-test"))["id"] ?? null; if ($id) { global $wpdb; var_dump($wpdb->get_results($wpdb->prepare("SELECT u.user_login FROM {$wpdb->prefix}crm_notification_log nl JOIN {$wpdb->prefix}users u ON u.ID = nl.user_id WHERE voucher_id = %d", $id))); }'
```
Expected output: only `qa-gold-1` and `qa-vip-1` (and any other Gold/VIP members) appear in the queue.

**Customer-side verification** (if you have inboxes for the test accounts):
- [ ] `qa-gold-1` receives the voucher email
- [ ] `qa-vip-1` receives the voucher email
- [ ] `qa-free-1` does **NOT** receive an email
- [ ] `qa-silver-1` does **NOT** receive an email

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

## Admin onboarding (TC-ONB)

> **What's covered**: the first-run setup guide that appears the first time an admin activates the plugin. These are admin-side cases — log in as a WordPress admin (not a customer test account).
>
> **Setup the dev needs to run before TC-ONB-01**:
> ```bash
> wp eval 'delete_option("zippy_crm_ever_activated"); delete_option("zippy_crm_show_onboarding"); delete_user_meta(get_current_user_id(), "_zc_onboarding_step"); delete_user_meta(get_current_user_id(), "_zc_onboarding_dismissed");'
> ```
> That resets every flag so the next plugin activation triggers the auto-redirect cleanly.

### TC-ONB-01: Fresh activation triggers the auto-redirect

> **What this tests**: on a brand-new activation, the admin lands on the setup guide automatically — no menu hunt required.

**Steps**
1. Have the dev run the reset command above.
2. Go to `/wp-admin/plugins.php`.
3. Deactivate Zippy CRM, then activate it again.
4. WordPress redirects to the post-activation screen (usually the plugins list).
5. Click any admin menu link (e.g. Dashboard).

**Expected**
- [ ] You land on the Zippy CRM setup guide page — NOT the dashboard
- [ ] Page header reads "Welcome to Zippy CRM" with a 7-step indicator at the top
- [ ] Step 1 of 7 is highlighted (filled dark); steps 2-7 are hollow
- [ ] System check shows three rows (WooCommerce active, HPOS enabled, Customer accounts allowed) — green checks if your env is set up, amber/red warnings otherwise
- [ ] **Next button is disabled** if WooCommerce isn't active (hard gate); enabled otherwise

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-ONB-02: Second admin page load doesn't redirect again

> **What this tests**: the auto-redirect is one-shot. After it fires once, going to another admin page should land you there normally, not bounce back to the guide.

**Pre-condition:** TC-ONB-01 was run (auto-redirect already fired and consumed the flag).

**Steps**
1. From the setup guide, navigate to `/wp-admin/` (dashboard) directly via URL or the WP logo.
2. Click any other top-level menu link (e.g. Posts, Pages, WooCommerce).

**Expected**
- [ ] You stay on the page you clicked — no bounce back to the setup guide
- [ ] The Zippy CRM menu sidebar shows the normal items (Members, Tiers, Vouchers, etc.) — **no "Setup Guide" entry**; it's hidden by design

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-ONB-03: Step navigation + "Skip for now"

**Steps**
1. Go back to the setup guide: `/wp-admin/admin.php?page=zippy-crm-onboarding`.
2. From step 1, click **Next**.
3. Step 2 (Membership tiers) loads with a table of seeded tiers.
4. Click **Next** again → step 3 (Points).
5. Click **← Back** → returns to step 2.
6. Click **Skip for now** at the bottom.

**Expected**
- [ ] Step indicator at the top updates as you navigate (completed steps show a green ✓)
- [ ] Back button hidden on step 1; visible on steps 2-7
- [ ] "Skip for now" link is visible at the bottom-right on every step except the last
- [ ] After clicking Skip, you're redirected to **Zippy CRM → Members** (not the guide, not the dashboard)
- [ ] If you navigate back to the guide URL, you see a "Setup complete" card with two buttons: "Revisit step N" and "Go to Members"

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-ONB-04: Test email button (Notifications step)

> **What this tests**: the "Send test email" button on step 5 sends a sample voucher email to the current admin's address and surfaces success/failure clearly. Also tests the rate-limit.

**Pre-condition:** Your WP install has working SMTP (WP Mail SMTP plugin or equivalent). On a dev environment without SMTP, the button will return an error — that's also a valid result, just verifies the error path.

**Steps**
1. Visit the setup guide. If you're on the "Setup complete" card, click **Revisit step 1**, then click Next four times to reach step 5 (Notifications).
2. Click **Send test email**.
3. While the spinner is going, click **Send test email** again immediately (before the first response lands).
4. Wait for the result. Check your admin user's inbox.

**Expected — happy path** (working SMTP)
- [ ] Button shows a loading spinner; first click returns success: green text "✓ Sent to your.email@example.com. Check your inbox."
- [ ] Email arrives at the admin's address (may take a minute; check spam folder)
- [ ] Email subject contains the site name and "Test email from Zippy CRM"
- [ ] Email body renders the sample voucher card with a 20% discount

**Expected — rate limit**
- [ ] Clicking again within 60 seconds returns a rose error: "Please wait a moment before sending another test email."
- [ ] After waiting 60+ seconds, a fresh click succeeds again

**Expected — broken SMTP** (alternative)
- [ ] Button click returns a rose error: "wp_mail returned false. Check your SMTP configuration."
- [ ] The footnote below the button mentions checking spam folder + SMTP config

**Result:** [ ] Pass [ ] Fail [ ] Blocked
**Tester / date:**
**Notes:**

---

### TC-ONB-05: "View setup guide" re-access from Settings

> **What this tests**: after dismissing, an admin can intentionally re-enter the guide via the Settings panel link. This does NOT re-arm the auto-redirect — it's an explicit one-time visit.

**Pre-condition:** Setup guide was previously completed or dismissed (TC-ONB-03 leaves you in this state).

**Steps**
1. Go to **Zippy CRM → Settings**.
2. Look at the top-right of the page header.
3. Click the **View setup guide** button.

**Expected**
- [ ] Top-right of Settings shows a "View setup guide" button next to the page title
- [ ] Clicking it lands on the onboarding page **at step 1** (not the "Setup complete" card)
- [ ] The URL bar shows `admin.php?page=zippy-crm-onboarding` — the `?revisit=1` param was stripped after consumption (so reloading the page doesn't double-reset)
- [ ] Navigating away and coming back manually to `admin.php?page=zippy-crm-onboarding` shows the "Setup complete" card again — the revisit was one-shot
- [ ] Deactivating and re-activating the plugin (dev would need to manually clear `zippy_crm_ever_activated` first to simulate fresh install) auto-redirects again only on a truly fresh install — re-activations of an already-once-activated plugin don't trigger it

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
