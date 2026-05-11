# Notifications

When you publish a voucher, opted-in members get an email. The system is opt-in (never spam), batched (won't melt your SMTP), and idempotent (no duplicate sends).

## Customer opt-in

Customers see two checkboxes on the WooCommerce registration form:

- **Email me about new vouchers** (checked by default)
- **Email me about points activity** (checked by default)

They can change either choice anytime under **My Account → Notifications**.

You can change the default state (checked vs unchecked) per local privacy law, but the plugin defaults to opt-in — vouchers are the main "value moment" for customers, and silently disabling notifications hurts engagement.

## What triggers a send

Right now, **only one event sends emails**: voucher publish.

- Publishing a voucher (changing it from Draft to Active) queues a notification batch
- The audience filter is applied — tier-restricted vouchers only email customers on the allowed tiers; email-restricted vouchers only email the listed addresses
- Each customer is queued **at most once per voucher**, even if you publish/unpublish the same voucher repeatedly

## How dispatching works

Sending is asynchronous so the Publish action stays snappy — clicking Publish doesn't block while emails go out.

1. Publishing queues a notification batch
2. Emails go out in batches of 50 at a time
3. Each batch sends, then waits 5 minutes before the next one starts
4. If a send fails (SMTP timeout, bounce, etc.), the plugin retries it every hour until it succeeds

This means a 10,000-customer voucher takes about 17 hours to fully dispatch. For most stores that's fine — voucher promos run for days. If you need faster, ask your developer to lower the interval (it's a single setting).

## A scheduling caveat to know about

WordPress's built-in scheduler only runs when someone visits your site. A store with low traffic might see emails go out hours later than expected.

For production stores — especially low-traffic ones — ask your developer or hosting provider to set up a **real system cron job** that triggers the scheduler every few minutes regardless of visitor traffic. This is a one-time hosting setup and the most common deliverability fix for slow notification dispatch.

## Email deliverability

The plugin uses your WordPress install's standard email — whatever sends password resets and order confirmations is what sends notifications.

If those don't reliably reach customers (especially Gmail, Outlook, iCloud), notifications won't either. **Install a transactional-email plugin** like:

- **WP Mail SMTP** (free, easiest)
- **FluentSMTP** (free)
- **Post SMTP** (free)

Wire them to a real provider (SendGrid, Postmark, Mailgun, Amazon SES). Then use the "Send a test email" button in **Zippy CRM → Settings** — if the test arrives in the inbox (not spam), you're good.

## Confirming a customer was notified

If a customer says "I never got the email", check three things in order:

1. **Did they opt in?** Go to **Zippy CRM → Members**, open the customer, look at their notification preferences. If "Vouchers" is unchecked, no notification was ever queued for them.
2. **Were they in the voucher's audience?** Tier-restricted and email-restricted vouchers only notify the allowed group. If their tier or email isn't in the allow-list, they were skipped on purpose.
3. **Did your store actually send the email?** Use the "Send a test email" button in **Zippy CRM → Settings**. If the test reaches your inbox, your store can deliver mail; the customer's spam folder is the next place to check.

## What's NOT sent

- No "you earned points" email (would be too noisy)
- No "you're now a Silver member" email (planned, not built)
- No drip campaigns / sequences
- No abandoned-cart emails

Customers always see all of this on My Account. If you need broader email engagement, run a separate marketing tool (Mailchimp, Klaviyo) alongside.
