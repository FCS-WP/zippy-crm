-- Count of claims the user has redeemed at checkout.
-- Args: user_id (%d).
SELECT COUNT(*) AS total
FROM   {prefix}crm_voucher_claims
WHERE  user_id = %d
  AND  status  = 'used'
