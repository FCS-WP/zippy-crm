# What Zippy CRM does

Zippy CRM turns your WooCommerce store into a customer-loyalty platform: members earn points on every order, climb tiers as they spend more, and claim vouchers you publish. It's built specifically for WooCommerce — orders, customers, and coupons are the same WooCommerce objects you already manage, no parallel data world to learn.

## The four moving parts

**Membership tiers.** Every customer is on a tier (Free, Silver, Gold, VIP — or whatever you rename them to). Tiers determine the **points multiplier** — a Silver member earning 1.2× points means a $50 order awards 60 points instead of 50. Customers auto-promote based on lifetime spend or order count; you can also assign tiers manually for VIPs.

**Points.** Customers earn points on completed orders (the rate is configurable, default `1 point per $1`). They redeem points at checkout as a discount — slide a value, click "Use", the discount is applied as a WooCommerce fee. Every point change is recorded in an append-only ledger you can audit.

**Vouchers.** You create vouchers (single code, or multi-code campaigns where each customer gets a unique code), publish them, and customers claim them from their My Account → Vouchers tab. Each voucher is backed by a real WooCommerce coupon under the hood, so checkout works exactly like any WC discount you've used before.

**Notifications.** Customers opt in at registration. When you publish a voucher, opted-in members get an email — batched, retried on failure, idempotent (no duplicate sends).

## Where you'll spend your time

| Panel | Day-to-day use |
|---|---|
| **Members** | Look up a specific customer, change their tier, suspend an account, adjust their points |
| **Vouchers** | Create promos, publish or pause them, see who claimed |
| **Tiers** | One-time setup; revisit when adjusting thresholds or multipliers |
| **Points** | Audit overall earn/redeem activity; manual point adjustments |
| **Reports** | Trend charts: new members per day, points activity, voucher claims |
| **Audit log** | "Who changed what?" — every admin write is logged |
| **Settings** | Plugin-wide configuration (earn rate, redemption rate, etc.) |

## What it's NOT

- **Not a marketing automation suite.** No drip campaigns, no segmented broadcasts beyond voucher-publish notifications. If you need Mailchimp-level automation, run that alongside Zippy CRM.
- **Not a separate user database.** Membership records hang off WordPress users — there's no parallel "CRM contact" you have to sync. Delete a WP user, their CRM record cascades.
- **Not coupon replacement.** Vouchers create real WooCommerce coupons. Existing manual coupons keep working alongside.

## Recommended reading order

If this is your first time:

1. **First steps after install** — get the basics configured
2. **Membership tiers** — set up your tier ladder before everything else
3. **Points** — decide your earn rate and redemption rate
4. **Vouchers** — create your first promo
5. **Settings reference** — everything else is configurable
