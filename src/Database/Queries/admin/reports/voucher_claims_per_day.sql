-- Returns: voucher claim count per day, plus how many of those got used.
-- Used count = claims that flipped to status='used' (consumed at order completion).
-- Args: from (%s), to (%s).
SELECT DATE(claimed_at) AS day,
       COUNT(*)         AS claimed,
       SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) AS used
FROM   {prefix}crm_voucher_claims
WHERE  claimed_at BETWEEN %s AND %s
GROUP  BY DATE(claimed_at)
ORDER  BY day
