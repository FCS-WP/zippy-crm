-- Returns: vouchers visible to this customer right now —
--   active status, not expired, not started later, has remaining quota,
--   and the user has not already claimed.
-- Uses: idx_status_expiry (status,expires_at). The LEFT JOIN against claims is
-- by (voucher_id,user_id) which is UNIQUE on the claims table — single seek.
-- Args: user_id (%d), now (%s, UTC mysql datetime), now (%s, again).
SELECT v.id, v.code, v.title, v.description, v.discount_type, v.discount_value,
       v.min_order_amount, v.max_uses, v.uses_count,
       v.starts_at, v.expires_at
FROM   {prefix}crm_vouchers v
LEFT   JOIN {prefix}crm_voucher_claims c
       ON c.voucher_id = v.id AND c.user_id = %d
WHERE  v.status     = 'active'
  AND  ( v.expires_at IS NULL OR v.expires_at > %s )
  AND  ( v.starts_at  IS NULL OR v.starts_at  <= %s )
  AND  ( v.max_uses   = 0     OR v.uses_count < v.max_uses )
  AND  c.id IS NULL
ORDER  BY v.id DESC
