-- Returns: full voucher row by code. Used at order completion to mark claims
-- as used and increment uses_count.
-- Uses: uq_code (UNIQUE INDEX on code).
-- Args: code (%s).
SELECT id, code, title, status, discount_type, discount_value,
       min_order_amount, max_uses, uses_count,
       starts_at, expires_at
FROM   {prefix}crm_vouchers
WHERE  code = %s
LIMIT  1
