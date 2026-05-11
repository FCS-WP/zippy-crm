# Audit log

Every admin action that changes data is recorded in the audit log. This is your "who changed what?" trail — useful for compliance, customer-service investigations, and the rare case where you need to figure out how a record got into a weird state.

## What gets logged

| Event | Logged |
|---|---|
| Voucher CRUD (create / update / publish / pause / resume / delete / duplicate) | Yes |
| Member tier change (manual admin assignment) | Yes |
| Member status change (suspend / activate) | Yes |
| Manual points adjustment (credit / debit) | Yes |
| Tier definition CRUD (create / update / delete) | Yes |
| Points settings update | Yes |

**Customer-side actions are NOT logged.** A customer claiming a voucher, redeeming points at checkout, or toggling their notification preferences doesn't generate audit entries — those are routine, high-volume, and already visible in the customer's voucher claims, points history, and notification preferences. Logging them would clutter the audit log without adding investigative value.

## What's in each entry

- **Actor** — the admin who did it (or "System" for automated events)
- **Event** — what happened, in plain language ("Voucher published", "Member tier changed", "Points adjusted")
- **Target** — which voucher, customer, or tier was affected
- **Details** — structured context: the before/after values for an update, the reason text for a manual adjustment, etc.
- **Timestamp** — when it happened

## Browsing the log

**Zippy CRM → Audit log**.

You can filter by:
- **Event type** — narrow to "voucher.*" or "member.*" or a specific event slug
- **Actor** — show only what one admin did
- **Date range** — investigate within a window
- **Object** — find every event involving a specific user, voucher, or tier

The log is **read-only**. There's no UI to delete or edit entries. If you ever need to clear it (e.g. a GDPR data-deletion request for a specific customer), have your developer run the cleanup — and document what was removed.

## Retention

Default: **forever**. The table doesn't auto-prune.

For high-volume stores the log can grow large over time (a busy admin might generate ~50 entries per day → roughly 18,000 per year). If you need to prune older entries, ask your developer to run a one-shot cleanup. The plugin doesn't ship an automatic pruner because retention is a policy decision — some industries require longer retention, some shorter.

## Common investigations

**"Why is customer X on Gold tier? They only have 3 orders."**

Filter by:
- Target → customer X
- Event → "Member tier changed"

You'll see whether they were manually assigned or auto-promoted, when, and by whom.

**"Who deleted voucher SUMMER25?"**

Filter by:
- Event → "Voucher deleted"
- Search for the code `SUMMER25`

If nobody deleted it, the voucher was either never created or was edited to a different code. Filter by "Voucher updated" to find rename events.

**"Customer says they had 500 pts last week, now they have 100. What happened?"**

The audit log only shows **manual** changes by admins. Automated movements (earned from orders, redeemed at checkout, refunded) are in the **customer's points history** instead — open the customer in **Members** and check there first. If you spot a manual adjustment in the history, then come back to the audit log and filter by "Points adjusted" to find the admin who did it and the reason they gave.
