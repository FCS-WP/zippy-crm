-- History view for the customer's "My Claims → History" sub-tab. Returns
-- claims that are NO LONGER usable, in three flavours:
--
--   1. status='used'                           → redeemed on an order
--   2. status='claimed' AND v.expires_at past  → never used, voucher expired
--   3. status='expired'                        → revoked (cascade or tier)
--
-- The active "still usable" claims are the inverse of this and live in
-- list_my_claims.sql. The two queries together cover every claim row.
--
-- Why we don't filter by WC coupon liveness here: history is read-only —
-- the customer can't "use" these claims anymore, so a missing WC coupon is
-- not dangerous. Surfacing them in History is the whole point (admin took
-- it back, customer should see why).
--
-- Pagination: caller passes %d limit + %d offset. Default UI surfaces 50
-- newest, with "Load more" pulling the next 50.
--
-- Args: user_id (%d), now_mysql (%s), limit (%d), offset (%d).
SELECT c.id           AS claim_id,
       c.voucher_id,
       c.code_id,
       c.status       AS claim_status,
       c.revocation_reason,
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
  AND  (
         c.status = 'used'
         OR c.status = 'expired'
         OR ( c.status = 'claimed' AND v.expires_at IS NOT NULL AND v.expires_at <= %s )
       )
ORDER  BY COALESCE(c.used_at, c.claimed_at) DESC, c.id DESC
LIMIT  %d OFFSET %d
