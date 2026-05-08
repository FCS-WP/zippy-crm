-- Returns: total points currently reserved in active, unexpired pending coupons.
-- Subtract from summary.balance to get spendable balance.
-- Index used: idx_pending (pending_status, user_id).
-- The 24h window matches the WC coupon's `date_expires` so an expired pending
-- stops counting against `available` even before the cron sweeper marks it.
-- Args: user_id (%d).
SELECT COALESCE(SUM(reserved_points), 0)
FROM   {prefix}crm_points_ledger
WHERE  user_id        = %d
  AND  type           = 'pending_redeem'
  AND  pending_status = 'active'
  AND  created_at     > (UTC_TIMESTAMP() - INTERVAL 24 HOUR)
