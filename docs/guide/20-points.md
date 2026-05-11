# Points (earn & redeem)

Points are the loyalty currency. Customers earn them on completed orders and redeem them at checkout for a cash discount.

## Earning points

**Formula:**

```
points_earned = floor(order_subtotal_after_discounts) × tier_multiplier
```

So a Silver customer (1.2×) ordering $50 of goods earns `floor(50 × 1.2) = 60` points. The points are awarded when the order transitions to **processing** (most stores) or **completed** (fallback).

### What counts as the subtotal

- Line items minus any discounts (coupons, voucher codes)
- **Excludes** shipping and tax
- **Excludes** the points-tender fee itself (you don't earn points on points you spent)

So if a customer redeems points to bring a $50 order down to $30, they still earn points on the full $50 base, not the $30 net.

### Each order awards points only once

The plugin remembers which orders have already awarded points, so an order can never be double-awarded — even if its status changes back and forth between processing and completed.

If you ever need to re-award an order (for example, the customer's tier was wrong when their order originally completed), use a **manual points adjustment** from the Members panel.

## Redeeming points

Customers redeem points as a **cash-equivalent discount** at checkout. Default: `20 points = $1 off`.

### The flow

1. Customer goes to checkout with items in cart.
2. The "Use your points" widget appears (shows balance + slider).
3. They slide a value (must be a multiple of the redemption rate, ≥ minimum).
4. Click **Use N pts**. The discount appears in the totals as a **"Points redemption"** fee.
5. Pay. On order completion, the points are **debited** from their ledger.

### Pending vs settled

When a customer clicks "Use", the points are *reserved* but not yet debited:
- The cart shows the discount
- The customer's balance still reflects the unspent total
- If they abandon the cart, the reservation expires (~48h) and nothing is debited

The actual ledger debit happens on **`woocommerce_order_status_processing`** (and is idempotent on completed). So a customer who applies 100 points but never pays keeps their 100 points.

### Refunds

A full refund credits the points back. A partial refund credits **proportionally** — refund 50% of the order, get 50% of the redeemed points back.

The credit-back appears as a separate ledger row (type `adjust`) so the audit trail shows exactly what happened.

## The points history

Every point movement — every earn, redeem, refund, or manual adjustment — is recorded in the customer's points history. History is **append-only**: existing entries are never edited or deleted, so the trail of what happened is always intact. Corrections are added as new entries.

You can browse a customer's history under **Members → click a customer → Points history**.

| Type | When it appears |
|---|---|
| **Earned** | Customer's order completed |
| **Redeemed** | Customer applied points at checkout and the order went through |
| **Adjusted** | An admin manually credited or debited the customer, or a refund returned points |

Each entry shows the order it relates to (if any), the number of points moved, the reason, and the date.

## Keeping the balance in sync

The plugin keeps a running balance for each customer so the My Account page loads instantly without summing every history entry. The balance updates after every change.

In the rare case that a customer's displayed balance disagrees with their history, go to **Zippy CRM → Points → Recalculate all**. It rebuilds every customer's balance from their history. The history is always the source of truth.

## Manual adjustments (admin)

Go to **Zippy CRM → Members** → click a customer. There's a "Adjust points" form.

- **Amount**: positive to credit, negative to debit
- **Reason** (required): free text — appears in the ledger description and the audit log
- Negative-balance refusal: you can't debit a customer below 0

Use this for:
- "Customer service comp — sorry about the late delivery, here's 200 pts"
- "Correcting a missed earn from order #1234"
- "Remove fraudulent points from a chargebacked order"

Every adjustment writes both a ledger row and an audit-log entry. There's no way to "delete" an adjustment — to reverse one, post the opposite adjustment with a reason like "Reversing adjustment from 2026-05-01".
