-- Returns: number of claims for a voucher. Used by VoucherService::delete()
-- as a refusal guard (vouchers with claims must not be hard-deleted).
-- Uses: idx_voucher.
-- Args: voucher_id (%d).
SELECT COUNT(*) AS total
FROM   {prefix}crm_voucher_claims
WHERE  voucher_id = %d
