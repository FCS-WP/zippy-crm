# First steps after install

A 10-minute walkthrough to get the plugin doing useful work. Do these in order — each step builds on the previous.

## 1. Verify prerequisites

Before anything else, confirm your store is ready:

- **WooCommerce active.** Zippy CRM has a hard dependency.
- **HPOS enabled** (recommended). WooCommerce → Settings → Advanced → Features → "High-Performance Order Storage". This plugin targets HPOS only.
- **Customer accounts allowed.** WooCommerce → Settings → Accounts & Privacy → "Allow customers to create an account during checkout" (or "on the My Account page"). Without accounts, there are no members.

If you skip the customer-account check, the plugin still installs but no one can earn points or claim vouchers — guests are excluded by design.

## 2. Configure tiers (or accept the defaults)

Go to **Zippy CRM → Tiers**. The plugin seeds four tiers on activation:

| Tier | How customers reach it | Points multiplier |
|---|---|---|
| Free | Default on registration | 1× |
| Silver | 5+ orders OR $500+ lifetime spend | 1.2× |
| Gold | 15+ orders OR $2,000+ lifetime spend | 1.5× |
| VIP | Admin-assigned only | 2× |

You can rename labels, change thresholds, change multipliers, or add new tiers (e.g. "Platinum"). Slugs are immutable once created — they're the foreign key on every membership record. To "rename" a tier, edit its label, not the slug.

**Tip:** match thresholds to your actual store. A store where the average order is $30 and customers buy quarterly should set the Silver threshold lower than a store selling $500 sofas.

## 3. Set the earn + redemption rate

Go to **Zippy CRM → Settings**.

- **Points per dollar** (default `1`). 1 point per $1 spent (before tier multiplier).
- **Redemption rate** (default `20`). 20 points = $1 of discount.
- **Minimum redemption** (default `20`). Customers must redeem at least this many points at once.

Round numbers are easier for customers to reason about. The default `1 pt = $0.05` is the same ratio Starbucks Rewards uses (loosely — they use stars, but the math is comparable).

## 4. Create your first voucher

Go to **Zippy CRM → Vouchers → New voucher**.

A good first voucher is a **welcome offer** — something every new customer can claim:

- **Title**: "Welcome 10% off"
- **Code**: `WELCOME10`
- **Discount type**: Percent
- **Discount value**: 10
- **Status**: Active
- **Expires**: leave blank (or set 6 months out)

Hit **Publish**. The plugin creates the matching WooCommerce coupon automatically. Customers see it in **My Account → Vouchers** and can claim it with one click.

## 5. Test the customer flow

Open a logged-in customer's My Account in a private window:

1. Visit **My Account → Vouchers** — confirm `WELCOME10` shows under "Available".
2. Click **Claim**. A modal pops up with the code; copy it.
3. Add something to cart, go to checkout.
4. Apply the coupon — confirm the 10% discount lands in the totals.

If steps 3-4 don't work, see **Troubleshooting** → "Voucher claimed but won't apply at checkout".

## 6. Turn on notifications (optional but recommended)

When you publish a voucher, customers who opted into voucher emails at registration get notified. Send yourself a test:

- Make sure WordPress email actually delivers (the **WP Mail SMTP** plugin is the standard fix if it doesn't).
- Visit **Zippy CRM → Settings**. There's a "Send a test email" button.

If the test arrives, real notifications will work. If it doesn't, fix WP email *first* — Zippy CRM can't deliver mail your WP install can't deliver.

## You're done

That's the minimum useful setup. Customers can sign up, earn points, and claim your welcome voucher. Everything else (tier upgrades, automatic notifications, audit trail) runs in the background.

Next reads:

- **Membership tiers** — deeper explanation of the auto-promote logic
- **Vouchers** — multi-code campaigns, audience targeting, all the WC-coupon options
- **Troubleshooting** — for when things look off
