-- Conditional UPDATE: marks a claim as used by an order, but only if the
-- claim is still in 'claimed' state. The status='claimed' clause is the
-- SQL-level idempotency guard — replays return 0 affected rows.
-- Args: order_id (%d), used_at (%s, mysql utc datetime), voucher_id (%d), user_id (%d).
UPDATE {prefix}crm_voucher_claims
SET    status   = 'used',
       order_id = %d,
       used_at  = %s
WHERE  voucher_id = %d
  AND  user_id    = %d
  AND  status     = 'claimed'
