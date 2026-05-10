-- Returns: this user's claimed vouchers, joined with voucher metadata so the
-- UI gets everything in one query.
--
-- The `code` column resolves per claim:
--   single-code claim   → c.code_id IS NULL → v.code (the master code)
--   multi-code claim    → c.code_id REFERENCES vc.id → vc.code (the unique code)
--
-- COALESCE picks whichever is non-null. The LEFT JOIN on vc keeps single-code
-- claims (where code_id is NULL) included with vc.code = NULL.
--
-- Uses: idx_user (user_id) on claims; PK on vouchers; idx PK on voucher_codes.
-- Args: user_id (%d).
SELECT c.id           AS claim_id,
       c.voucher_id,
       c.code_id,
       c.status       AS claim_status,
       c.claimed_at,
       c.used_at,
       c.order_id,
       COALESCE(vc.code, v.code) AS code,
       v.title, v.description, v.distribution_mode,
       v.discount_type, v.discount_value, v.expires_at
FROM   {prefix}crm_voucher_claims c
JOIN   {prefix}crm_vouchers      v  ON v.id  = c.voucher_id
LEFT   JOIN {prefix}crm_voucher_codes vc ON vc.id = c.code_id
WHERE  c.user_id = %d
ORDER  BY c.claimed_at DESC, c.id DESC
