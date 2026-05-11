# Vouchers

A voucher is a discount you publish for customers to claim. Under the hood each voucher is backed by a **real WooCommerce coupon** — so claiming + applying at checkout uses the WC discount engine you already trust.

## Two voucher styles

**Single-code** — every customer who claims gets the **same code** (e.g. `WELCOME10`). Best for promos with no per-customer cost (a 10% discount that anyone can use).

**Multi-code campaign** — each customer who claims gets a **unique code** (e.g. `XYZ123` for one user, `ABC789` for another). Best when:
- You need to limit to N total redemptions across all customers
- You want to tie a specific code to a specific person (e.g. a referral campaign)
- You're tracking attribution outside the plugin

You set the style when creating the voucher: a **slot count** of 1 → single-code; >1 → multi-code (one slot per pre-generated code).

## Lifecycle

A voucher moves through these states:

```
draft → active → (paused ↔ active) → expired
                      |
                      └→ deleted (only if no claims yet)
```

- **Draft** — visible only in admin. Not claimable. Use this while editing.
- **Active** — visible to customers in My Account → Vouchers → Available. Claimable.
- **Paused** — temporarily hidden from customers. Existing claims still work; no new claims.
- **Expired** — past `expires_at`. Hidden from Available. Existing unused claims also become unusable.
- **Deleted** — only if `uses_count === 0` and there are no claims. Otherwise refused — soft-archive via "paused" instead.

## Audience targeting

You can restrict who sees a voucher in their Available list:

- **Public** — visible to all logged-in customers (default)
- **Tier-restricted** — visible only to customers on selected tiers (e.g. Gold + VIP only)
- **Email-restricted** — visible only to specific customer emails (paste a list)

Restrictions apply to **visibility AND claim** — a customer who isn't in the audience can't see or claim the voucher, and the underlying WC coupon enforces the same restriction at checkout.

## All the WC coupon options

Every WooCommerce coupon field is supported on vouchers — they map 1:1:

| Field | What it does |
|---|---|
| Discount type | `percent`, `fixed_cart`, `fixed_product`, `percent_product` |
| Discount value | Amount or percentage |
| Min order amount | Coupon won't apply unless cart total ≥ this |
| Max order amount | Coupon won't apply if cart total > this |
| Free shipping | Adds free-shipping flag |
| Individual use | Can't combine with other coupons |
| Exclude sale items | Skip line items already on sale |
| Usage limit per coupon | Total redemptions cap (across all customers) |
| Usage limit per user | How many times one customer can use this code |
| Limit usage to X items | Discount applies to first N matching items only |
| Email restrictions | WC's own per-coupon email allowlist |
| Product IDs / Excluded | Restrict to specific products |
| Product categories / Excluded | Restrict to specific categories |
| Allowed hours | Time-of-day window (e.g. happy-hour 5pm–7pm) |

Every value gets pushed into the WC coupon object when you publish, so checkout behavior is identical to a manually-created coupon.

## Creating a voucher

**Zippy CRM → Vouchers → New voucher**.

1. **Required up front**: title, code (must be unique among WC coupons), discount type, discount value
2. Save as **Draft** while you tune it
3. Set audience + WC coupon restrictions
4. Hit **Publish** — this:
   - Creates the WC coupon (`wp_posts` entry of type `shop_coupon`)
   - Sets voucher status to `active`
   - Triggers notification dispatch to opted-in customers in the audience

## Claims

When a customer clicks **Claim**, the plugin checks that the voucher is still active, the customer is in the audience, they haven't already claimed it, and (for multi-code campaigns) that a code is still available. If all checks pass, they get a popup with their code.

Even if a customer double-clicks the Claim button, they can only ever get one code — the plugin guards against duplicate claims at the storage level.

Claims appear under **My Account → Vouchers → My Claims** until used, then move to **History**.

## When to delete vs pause

**Delete** only if you created a voucher by mistake and no one has claimed it. The plugin refuses to delete a voucher with claims — the audit trail would have orphan references.

**Pause** is the right answer when you want to take a voucher down. Existing claims keep working (so customers who already grabbed a code can still use it), no new claims happen.

## Settling at checkout

When a customer uses a claimed code at checkout and the order completes:

- The claim moves from "Active" to "Used" — visible to the customer under **My Account → Vouchers → History**
- The voucher's redemption count goes up by 1
- For multi-code campaigns: only that specific code is marked used; the other codes in the campaign stay available for other customers
- The audit log records the redemption with the customer and order
