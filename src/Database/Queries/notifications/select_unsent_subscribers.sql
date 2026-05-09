-- Returns: user_ids that should receive a voucher email but haven't already
-- been queued/sent for this voucher. Used by NotifEngine::queue_voucher_published
-- to build batches.
--
-- The NOT IN subquery filters anyone with an existing log row for this voucher
-- (queued, sent, or failed) — UNIQUE (voucher_id, user_id) is the same guarantee
-- enforced at INSERT time, but checking up front lets us skip already-batched
-- rows when the publish action is replayed.
--
-- Index used: idx_subscribed_vouchers on subs, uq_notif on log.
-- Args: voucher_id (%d), limit (%d), offset (%d).
SELECT s.user_id
FROM   {prefix}crm_notif_subs s
WHERE  s.subscribed_vouchers = 1
  AND  s.user_id NOT IN (
       SELECT user_id
       FROM   {prefix}crm_notification_log
       WHERE  voucher_id = %d
  )
ORDER  BY s.user_id ASC
LIMIT  %d OFFSET %d
