-- Total history rows for one customer. Same predicate as
-- list_my_claims_history.sql but returns COUNT(*). Used by the My Account
-- History sub-tab to decide whether to show "Load more".
-- Args: user_id (%d), now_mysql (%s).
SELECT COUNT(*) AS total
FROM   {prefix}crm_voucher_claims c
JOIN   {prefix}crm_vouchers      v  ON v.id  = c.voucher_id
WHERE  c.user_id = %d
  AND  (
         c.status = 'used'
         OR c.status = 'expired'
         OR ( c.status = 'claimed' AND v.expires_at IS NOT NULL AND v.expires_at <= %s )
       )
