-- Returns: total row count for the same filter as recent_ledger.sql.
-- Used to compute pagination totals.
-- Args: type_sentinel (%s), type (%s).
SELECT COUNT(*) AS total
FROM   {prefix}crm_points_ledger
WHERE  type <> 'pending_redeem'
  AND  ( %s = '__all__' OR type = %s )
