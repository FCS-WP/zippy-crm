-- Atomically grab one available code from a multi-code voucher and assign it
-- to a user. InnoDB row locks serialize concurrent runs of this UPDATE so
-- two customers clicking Claim at the same moment can't be assigned the same
-- code.
--
-- Strategy: tag the row with assigned_to_user + assigned_at in the same
-- statement that flips status. After the UPDATE, the caller SELECTs the
-- row by (voucher_id, assigned_to_user, assigned_at = the now we passed in)
-- to discover which row id they got.
--
-- Args (in order): user_id (%d), now (%s, mysql utc datetime),
--                  voucher_id (%d).
-- Returns: rows affected (1 if a code was picked, 0 if exhausted).
UPDATE {prefix}crm_voucher_codes
SET    status           = 'assigned',
       assigned_to_user = %d,
       assigned_at      = %s
WHERE  voucher_id       = %d
  AND  status           = 'available'
LIMIT  1
