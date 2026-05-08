-- Returns: id + reserved_points for the active pending row matching a coupon code.
-- Used by the order-completion handler to find what to debit.
-- Index used: idx_pending (covers pending_status), then description filter.
-- Args: coupon_code (%s).
SELECT id, user_id, reserved_points
FROM   {prefix}crm_points_ledger
WHERE  type           = 'pending_redeem'
  AND  pending_status = 'active'
  AND  description    = %s
LIMIT  1
