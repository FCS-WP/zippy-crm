-- Returns: this user's claimed vouchers, joined with voucher metadata so the
-- UI gets everything in one query.
-- Uses: idx_user (user_id) on claims, then PK lookup on vouchers.
-- Args: user_id (%d).
SELECT c.id           AS claim_id,
       c.voucher_id,
       c.status       AS claim_status,
       c.claimed_at,
       c.used_at,
       c.order_id,
       v.code, v.title, v.description,
       v.discount_type, v.discount_value, v.expires_at
FROM   {prefix}crm_voucher_claims c
JOIN   {prefix}crm_vouchers v ON v.id = c.voucher_id
WHERE  c.user_id = %d
ORDER  BY c.claimed_at DESC, c.id DESC
