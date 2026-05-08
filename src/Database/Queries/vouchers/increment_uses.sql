-- Atomically increments uses_count and, if it hits max_uses, flips status to
-- expired in the same statement. Run from order-completion hook.
-- The CASE-WHEN keeps the transition consistent even under concurrent updates
-- (two completing orders both calling this).
-- Args: voucher_id (%d).
UPDATE {prefix}crm_vouchers
SET    uses_count = uses_count + 1,
       status     = CASE
                      WHEN max_uses > 0 AND uses_count + 1 >= max_uses THEN 'expired'
                      ELSE status
                    END
WHERE  id = %d
