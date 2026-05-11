# Troubleshooting & FAQ

Common problems and how to fix them. If your issue isn't here, check the **Audit log** first — the answer is often in there.

> Need to dig deeper than these recipes? Ask your developer — the plugin ships with a separate technical reference (database tables, REST endpoints, command-line tools) they can access directly.

## Points

### Customer's points didn't update after their order

**Most likely**: the order is still in *Pending* or *On hold*. Points award when an order moves to *Processing* or *Completed* — not before.

Check the order status in WooCommerce admin. If it's stuck on *Pending*:

- For manual orders: change to *Processing* once payment is verified
- For failed payment: the customer needs to retry — points award on the *retry's* completion, not the original

If the order **is** in *Processing* or *Completed* and there's still no points entry:

1. Open **Members → that customer → Points history**. Look for an entry tied to their order ID.
2. If absent, the customer may not have been logged in at checkout — guest orders don't earn points.
3. If you suspect the running balance is out of sync, run **Zippy CRM → Points → Recalculate all** (safe to run anytime).

### Customer balance doesn't match their points history

The running balance can drift in rare cases. Fix it from **Zippy CRM → Points → Recalculate all** — it rebuilds every customer's balance from their full history. Safe, idempotent, no downtime.

### "Negative balance" error when adjusting

The plugin refuses to debit a customer below 0. To zero out a customer with a small lingering balance, you'd need to credit them slightly first, then debit cleanly. Or just credit the difference and accept they end at 0 instead of going negative.

## Vouchers

### Customer claimed a voucher but can't apply it at checkout

The most common cause: the underlying WooCommerce coupon got deleted or never synced. Both the voucher record and the WooCommerce coupon need to exist for the code to work at checkout.

Check:

1. **Zippy CRM → Vouchers** — is the voucher status *Active*?
2. **WooCommerce → Marketing → Coupons** — does a coupon with that exact code exist?

If the WooCommerce coupon is missing, **pause and resume** the voucher — that re-syncs the coupon without losing existing claims.

### Voucher claimed but doesn't show in customer's My Claims

Most likely the voucher expired. The "My Claims" tab only shows codes the customer can still use right now. Used codes and expired claims appear under the **History** sub-tab instead.

### Multi-code voucher: customer got "no codes available"

The campaign ran out of slots. Either:

- Open the voucher in admin and increase the slot count, OR
- Accept that the campaign is fully claimed and pause it

### "Coupon does not exist" when applying a claimed voucher

Same as "claimed but can't apply at checkout" above — the WooCommerce coupon was deleted. Pause and resume the voucher to restore it.

When the underlying coupon is deleted, affected claims show under **History** with the reason **"Coupon removed"** so the customer understands why their code stopped working.

## Membership tiers

### Customer is on the wrong tier

Two possible causes:

1. **They were manually assigned an admin-only tier (e.g. VIP).** Once a customer is on an admin-only tier, the auto-promotion logic leaves them alone forever. Check the audit log for tier changes on this customer; if there's a manual assignment, take them off it manually first.

2. **Their lifetime stats are out of date.** Have them visit their **My Account → Membership** page (or open the Members panel and click their row). The page-load triggers a re-check that re-reads their lifetime stats and corrects the tier.

### Tier got "stuck" — won't auto-upgrade despite hitting the threshold

Confirm the customer's *current* tier isn't admin-only. Open them in **Members** — if their tier is flagged as admin-assigned, manual reassignment is the only way to change it.

## Checkout

### Points-redemption row appears but cart total doesn't change

Hard-refresh once (Ctrl+Shift+R). If it persists:

- Your checkout page is likely using the **WooCommerce Checkout block** rather than the classic checkout. The block uses a different refresh mechanism. Switch the page to the classic `[woocommerce_checkout]` shortcode, or ask your developer to upgrade Zippy CRM to a version that supports the block flow.
- A custom theme might have overridden the checkout template in a way that breaks the totals refresh. Test with a stock theme to confirm.

### "Use your points" widget doesn't appear at checkout

Three reasons it intentionally hides:

1. The customer isn't logged in (guests can't redeem)
2. The customer's account is suspended
3. The customer's balance is below the minimum redemption amount

If none of those apply but the widget is still missing, ask your developer — usually a stale build or a JavaScript error.

### "Insufficient balance" error when applying

The customer's balance dropped between when the page loaded and when they clicked Use (often because another order completed in another tab). Refresh the checkout and try again with the current balance.

## Notifications

### Test email arrives but real notifications don't

Your email setup works, but the scheduler isn't running batches. Most common cause: WordPress's built-in scheduler only fires on visitor traffic. On low-traffic stores, ask your hosting provider to set up a real system scheduler — see **Notifications → A scheduling caveat to know about**.

### Notifications going to spam

Your sender domain isn't authenticated. Ask your developer or hosting provider to set up **SPF, DKIM, and DMARC** for the From address your store uses. Transactional-email plugins like WP Mail SMTP usually handle this when wired to a real provider (SendGrid, Postmark, Mailgun, Amazon SES).

### Customer says they didn't get the voucher email but they're opted in

Walk through this in order:

1. **Confirm they really are opted in** — open them in **Members** and check their notification preferences. If "Vouchers" is unchecked, no email was ever queued for them.
2. **Confirm they were in the voucher's audience** — tier-restricted vouchers only email allowed tiers; email-restricted vouchers only email the listed addresses. If their tier or email isn't allowed, they were skipped on purpose.
3. **Confirm your email setup works** — run a test from **Zippy CRM → Settings**. If the test arrives, the customer's spam folder is the next place to check.

## General

### Plugin won't activate

Two common reasons:

1. **WooCommerce isn't active.** Activate WooCommerce first.
2. **A conflicting CRM plugin is active** (the plugin refuses to coexist with EPOS CRM by default to avoid double-counting orders). Deactivate the other plugin.

### Tables didn't get created on activation

Ask your developer to run the installer manually — it's a single command and safe to run anytime.

### Schema upgrade didn't run after a plugin update

Database migrations only run when the plugin's version number changes. If you updated the plugin files but the database wasn't migrated, ask your developer — there's a one-line command to force the migration.

## Still stuck?

- **Check the Audit log** — most "weird state" questions have an answer there
- **Open a support ticket** with: your WooCommerce version, WordPress version, Zippy CRM version, the audit log entries around the time the issue happened, and a description of what the customer was trying to do
