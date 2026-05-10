-- Returns: the row that pick_available_for_user just flipped to 'assigned'.
-- Looked up by the (user_id, assigned_at) tuple — assigned_at is the same
-- timestamp value we passed to the UPDATE, so this discovers exactly which
-- row the UPDATE picked.
--
-- Args: voucher_id (%d), user_id (%d), assigned_at (%s, same as passed to
--       pick_available_for_user.sql).
SELECT id, code, status, assigned_to_user, assigned_at, used_at, order_id
FROM   {prefix}crm_voucher_codes
WHERE  voucher_id       = %d
  AND  assigned_to_user = %d
  AND  assigned_at      = %s
LIMIT  1
