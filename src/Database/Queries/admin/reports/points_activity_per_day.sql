-- Returns: per-day points activity broken out by movement type.
--   earned   = SUM(points) where type='earn'
--   redeemed = -SUM(points) where type='redeem'   (stored negative; flip sign for chart)
--   adjusted = SUM(points) where type='adjust'    (signed; net effect)
-- Excludes type='pending_redeem' (zero-impact; only impacts balance on consume).
-- Excludes type='expire' (currently not emitted; reserved for future cron).
-- idx_user_created covers the date filter via composite scan.
-- Args: from (%s), to (%s).
SELECT DATE(created_at) AS day,
       COALESCE(SUM(CASE WHEN type = 'earn'   THEN points       END), 0) AS earned,
       COALESCE(SUM(CASE WHEN type = 'redeem' THEN -points       END), 0) AS redeemed,
       COALESCE(SUM(CASE WHEN type = 'adjust' THEN points       END), 0) AS adjusted
FROM   {prefix}crm_points_ledger
WHERE  type IN ('earn', 'redeem', 'adjust')
  AND  created_at BETWEEN %s AND %s
GROUP  BY DATE(created_at)
ORDER  BY day
