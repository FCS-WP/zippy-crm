-- Count of claims still usable by the user.
-- Mirrors the predicate in list_my_claims.sql so the count and the list agree.
-- Args: user_id (%d), now_mysql (%s).
SELECT COUNT(*) AS total
FROM   {prefix}crm_voucher_claims c
JOIN   {prefix}crm_vouchers       v ON v.id = c.voucher_id
WHERE  c.user_id = %d
  AND  c.status  = 'claimed'
  AND  v.status  = 'active'
  AND  ( v.expires_at IS NULL OR v.expires_at > %s )
