-- Returns: how many codes per status for one voucher. Used by the public
-- /vouchers list to show "remaining_slots" and by the admin Codes drawer.
-- Index used: idx_voucher_status (voucher_id, status).
-- Args: voucher_id (%d).
SELECT status, COUNT(*) AS total
FROM   {prefix}crm_voucher_codes
WHERE  voucher_id = %d
GROUP  BY status
