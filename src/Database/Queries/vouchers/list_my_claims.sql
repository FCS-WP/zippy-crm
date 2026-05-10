-- Returns: this user's claimed vouchers (still usable), joined with voucher
-- metadata so the UI gets everything in one query.
--
-- Hides claims when:
--   - the claim has been used (claim_status != 'claimed') — value already
--     consumed; chip becomes visual noise
--   - the parent voucher isn't currently active (paused / expired / draft)
--   - the parent voucher's expires_at is in the past
--   - the WC coupon backing the code has been deleted, trashed, or
--     renamed (defensive belt-and-suspenders for direct $wpdb deletes
--     that bypass our cascade hook). Without this guard, a customer
--     could see "Ready to use" + a Copy chip for a code that fatals at
--     checkout (WC throws "Invalid coupon" on dead post IDs).
--
-- The `code` column resolves per claim:
--   single-code claim   → c.code_id IS NULL → v.code (the master code)
--   multi-code claim    → c.code_id REFERENCES vc.id → vc.code (the unique code)
--
-- COALESCE picks whichever is non-null. The LEFT JOIN on vc keeps single-code
-- claims (where code_id is NULL) included with vc.code = NULL.
--
-- WC coupon liveness check: INNER JOIN onto wp_posts requiring
-- post_status='publish' AND post_type='shop_coupon'. The post_title IS the
-- coupon code (WC stores it that way, lowercased). Trashed/draft/private/
-- deleted coupons all fail this join and the claim is hidden.
-- Case-insensitive match works under the default utf8mb4_*_ci collation —
-- WC stores coupon titles lowercased while we store our codes uppercase.
--
-- Uses: idx_user (user_id) on claims; PK on vouchers; idx PK on voucher_codes;
--   posts has indexes on post_type + post_status that help.
-- Args: user_id (%d), now_mysql (%s).
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
JOIN   {prefix}posts p
       ON p.post_title  = COALESCE(vc.code, v.code)
      AND p.post_type   = 'shop_coupon'
      AND p.post_status = 'publish'
WHERE  c.user_id = %d
  AND  c.status = 'claimed'
  AND  v.status = 'active'
  AND  ( v.expires_at IS NULL OR v.expires_at > %s )
ORDER  BY c.claimed_at DESC, c.id DESC
