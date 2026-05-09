-- Returns: one row per status with a count, used by the admin Quick Stats bar.
-- Uses: idx_status_expiry (covering for status grouping).
-- Args: none.
SELECT status, COUNT(*) AS total
FROM   {prefix}crm_vouchers
GROUP  BY status
