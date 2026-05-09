-- Returns: claim list for a single voucher (admin Claims drawer).
--   Joins user display data so the table can render names/emails without N+1.
-- Uses: idx_voucher on claims; PK on users.
-- Args: voucher_id (%d).
SELECT c.id AS claim_id, c.user_id, c.status AS claim_status,
       c.claimed_at, c.used_at, c.order_id,
       u.user_login, u.user_email, u.display_name
FROM   {prefix}crm_voucher_claims c
LEFT   JOIN {prefix}users u ON u.ID = c.user_id
WHERE  c.voucher_id = %d
ORDER  BY c.claimed_at DESC
LIMIT  500
