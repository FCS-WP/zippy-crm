-- Returns: vouchers visible to this customer right now —
--   active status, not expired, not started later, has remaining quota,
--   the user has not already claimed, and audience targeting matches.
--
-- Quota check is mode-aware:
--   single_code      → uses_count < max_uses (legacy)
--   multi_code_public → at least one row in crm_voucher_codes is 'available'
--
-- Audience filter (v1.11.0) — a voucher is visible if:
--   audience_mode = 'public'
--   audience_mode = 'tier'  AND the user's tier slug is in allowed_tiers
--   audience_mode = 'email' (handled in PHP post-fetch — email_restrictions
--                            stores rich {email,first_name,last_name} objects
--                            that don't lend themselves to JSON_CONTAINS).
-- The email-mode rows are still included by this query so the PHP filter
-- can decide; that filter lives in Voucher::list_available_for_user.
--
-- Also surfaces `remaining_slots` so the client can show "X codes left" for
-- multi-code; NULL means "not applicable" (single-code voucher).
--
-- Uses: idx_status_expiry (status,expires_at). The LEFT JOIN against claims
-- is by (voucher_id,user_id) which is UNIQUE on the claims table — single
-- seek. The codes subquery uses idx_voucher_status.
--
-- Args: user_id (%d), now (%s, UTC mysql datetime), now (%s, again),
--       user_tier_slug (%s).
SELECT v.id, v.code, v.title, v.description, v.discount_type, v.discount_value,
       v.min_order_amount, v.max_uses, v.uses_count, v.distribution_mode,
       v.audience_mode, v.email_restrictions, v.allowed_tiers,
       v.starts_at, v.expires_at,
       (
         SELECT COUNT(*)
         FROM   {prefix}crm_voucher_codes vc
         WHERE  vc.voucher_id = v.id AND vc.status = 'available'
       ) AS remaining_slots
FROM   {prefix}crm_vouchers v
LEFT   JOIN {prefix}crm_voucher_claims c
       ON c.voucher_id = v.id AND c.user_id = %d
WHERE  v.status     = 'active'
  AND  ( v.expires_at IS NULL OR v.expires_at > %s )
  AND  ( v.starts_at  IS NULL OR v.starts_at  <= %s )
  AND  (
         ( v.distribution_mode = 'single_code'
           AND ( v.max_uses = 0 OR v.uses_count < v.max_uses ) )
       OR
         ( v.distribution_mode = 'multi_code_public'
           AND EXISTS (
                 SELECT 1
                 FROM   {prefix}crm_voucher_codes vc
                 WHERE  vc.voucher_id = v.id AND vc.status = 'available'
               ) )
       )
  AND  c.id IS NULL
  AND  (
         v.audience_mode = 'public'
         OR  v.audience_mode = 'email'  -- post-filtered in PHP
         OR ( v.audience_mode = 'tier'
              AND v.allowed_tiers IS NOT NULL
              AND JSON_CONTAINS( v.allowed_tiers, JSON_QUOTE(%s) ) )
       )
ORDER  BY v.id DESC
