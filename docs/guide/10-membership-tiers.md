# Membership tiers

Tiers are the loyalty ladder — Free → Silver → Gold → VIP (or whatever you rename them to). Each tier has a **points multiplier** that boosts how fast a member earns. Customers move up automatically based on their lifetime stats.

## How auto-promotion works

Every time a customer's order goes to **processing** or **completed**, the plugin re-evaluates their tier:

1. Read lifetime stats from WooCommerce: total order count, total spend.
2. Walk the configured tiers from highest threshold to lowest.
3. The customer lands on the highest tier whose threshold they meet (orders **OR** spend, whichever qualifies them).

The default seeds:

| Tier | Threshold |
|---|---|
| Silver | 5 orders **OR** $500 spend |
| Gold | 15 orders **OR** $2,000 spend |

So a customer with 6 orders totaling $400 reaches Silver (orders qualifies); a customer with 2 orders totaling $600 also reaches Silver (spend qualifies).

**Customers don't auto-demote.** Once you reach Silver, you stay Silver even if your spend drops below the threshold (e.g. via refunds). This is a deliberate retention choice — punishing returning customers feels worse than the cost of leaving them on the higher tier.

## Admin-only tiers (sticky)

A tier marked **"admin-only"** is never set by auto-evaluation — only an admin can assign it. The default `VIP` tier is admin-only. Useful for:

- High-value relationships you've decided are worth the multiplier regardless of spend
- Beta testers / staff
- Offline customers who placed big orders before they signed up online

Once a customer is on an admin-only tier, **auto-evaluation will not touch them** — even if their stats drop below their tier's notional threshold. Take them off manually if you need to.

## Managing tiers

**Zippy CRM → Tiers** is where you configure the ladder.

### Editing a tier
- **Label**: human-readable name. Safe to rename anytime — the underlying slug doesn't change.
- **Multiplier**: 0–10. `0` means "this tier earns no points", `1` means standard, `1.5` means 50% more.
- **Order threshold / Spend threshold**: minimum order count / lifetime spend to qualify. Either field can be blank.
- **Sort order**: position in the ladder. Lower = lower tier.
- **Admin-only**: check to make this tier sticky (never auto-assigned).

### Deleting a tier
Refused if any member is on the tier. Reassign those members first (Members panel → bulk update level).

Also refused if it's the only non-admin-only tier — customers always need at least one tier to land on.

### Slugs are immutable
The slug is the foreign key on every `crm_memberships.membership_level` row. Renaming would orphan thousands of records. To rename a tier, edit its **label** instead.

## Manually changing a member's tier

Go to **Zippy CRM → Members**, find the customer, click the level dropdown. The change:
- Writes the new tier immediately
- Logs to the audit log (who, when, before → after)
- Does **not** retroactively recalculate past points (history is sacred)

If you assign an admin-only tier (e.g. VIP), auto-evaluation will leave the customer alone forever after — only manual reassignment can move them.

## When tiers re-evaluate

| Event | Re-evaluates? |
|---|---|
| Customer places an order, status transitions to processing | Yes |
| Customer places an order, status transitions to completed | Yes (idempotent — same result if already evaluated) |
| Order refunded | No (no auto-demote) |
| Order cancelled | No |
| Membership page loaded | Yes — self-heals customers who were on the wrong tier (e.g. orders predate plugin install) |
| Manual admin change | N/A (you're overriding) |

The on-page-load self-heal exists so installing the plugin on a store with existing customers Just Works — anyone who already qualified for Silver/Gold is upgraded the next time they visit My Account.

## What customers see

Their **My Account → Membership** page shows:
- Current tier badge + multiplier ("Silver — Earn 20% more points on every order")
- Progress to next tier (orders or spend remaining)
- Lifetime stats (orders, spend, average order value)
- Quick-glance points balance and voucher counts
- The whole tier ladder, with their current rung highlighted
