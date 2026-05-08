-- Marks a pending_redeem row as consumed by an order. Conditional UPDATE on
-- pending_status='active' acts as the SQL-level idempotency guard — if a
-- parallel handler already consumed this row, the UPDATE returns 0 rows and
-- the caller must skip its debit.
-- Args: order_id (%d), id (%d).
UPDATE {prefix}crm_points_ledger
SET    pending_status = 'consumed',
       order_id       = %d
WHERE  id             = %d
  AND  pending_status = 'active'
