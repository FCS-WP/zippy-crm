-- Conditional UPDATE — flips an assigned code to 'used' once a customer's
-- order completes. The status='assigned' guard makes this idempotent; a
-- repeated fire on the same order is a no-op.
--
-- Args: order_id (%d), used_at (%s utc), code (%s).
UPDATE {prefix}crm_voucher_codes
SET    status   = 'used',
       order_id = %d,
       used_at  = %s
WHERE  code     = %s
  AND  status   = 'assigned'
