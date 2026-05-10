-- Returns: user_ids that should receive a voucher email but haven't already
-- been queued/sent for this voucher. Used by NotifEngine::queue_voucher_published
-- to build batches.
--
-- Audience targeting (v1.11.0+) — only queue customers who can actually claim
-- the voucher. The publish action will otherwise tease non-qualifying members
-- with offers they can't redeem. The voucher row's audience_mode + restriction
-- columns are joined in:
--   audience_mode = 'public' → all subscribed users qualify (no extra filter)
--   audience_mode = 'tier'   → user's membership_level must be in v.allowed_tiers
--   audience_mode = 'email'  → handled in PHP post-fetch (the email_restrictions
--                              column stores rich {email,name} objects that
--                              don't lend themselves to JSON_CONTAINS)
-- Email-mode rows are passed through here without filtering; NotifEngine
-- post-filters them in PHP using the same logic as Voucher::list_available_for_user.
--
-- The NOT IN subquery filters anyone with an existing log row for this voucher
-- (queued, sent, or failed) — UNIQUE (voucher_id, user_id) is the same guarantee
-- enforced at INSERT time, but checking up front lets us skip already-batched
-- rows when the publish action is replayed.
--
-- Index used: idx_subscribed_vouchers on subs, uq_notif on log.
-- Args: voucher_id (%d), voucher_id (%d), limit (%d), offset (%d).
SELECT s.user_id
FROM   {prefix}crm_notif_subs s
JOIN   {prefix}crm_vouchers v ON v.id = %d
LEFT   JOIN {prefix}crm_memberships m ON m.user_id = s.user_id
WHERE  s.subscribed_vouchers = 1
  AND  s.user_id NOT IN (
       SELECT user_id
       FROM   {prefix}crm_notification_log
       WHERE  voucher_id = %d
  )
  AND  (
         v.audience_mode = 'public'
         OR v.audience_mode = 'email'  -- post-filtered in PHP
         OR ( v.audience_mode = 'tier'
              AND v.allowed_tiers IS NOT NULL
              AND m.membership_level IS NOT NULL
              AND JSON_CONTAINS( v.allowed_tiers, JSON_QUOTE( m.membership_level ) ) )
       )
ORDER  BY s.user_id ASC
LIMIT  %d OFFSET %d
