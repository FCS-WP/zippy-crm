-- Returns: paginated voucher list for the admin Vouchers panel.
-- Filter sentinels keep the same prepared shape regardless of filter state:
--   pass '__all__' to skip a filter, or the real value to apply it.
-- The search pattern is passed twice (code OR title) — caller is responsible
-- for wrapping the term with % wildcards (esc_like + sprintf).
-- Uses: idx_status_expiry when status is set; full scan otherwise (small table).
-- Args:  status_sentinel (%s),  status (%s),
--        search_sentinel (%s),  pattern_code (%s),  pattern_title (%s),
--        per_page (%d),         offset (%d).
SELECT id, code, title, description,
       discount_type, discount_value, min_order_amount,
       max_uses, uses_count, status,
       starts_at, expires_at, created_by, created_at
FROM   {prefix}crm_vouchers
WHERE  ( %s = '__all__' OR status = %s )
  AND  ( %s = '__all__' OR ( code LIKE %s OR title LIKE %s ) )
ORDER  BY id DESC
LIMIT  %d OFFSET %d
