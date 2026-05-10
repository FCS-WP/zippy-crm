-- Sum of points earned by a user since a given UTC datetime.
-- Used by the Membership page to show "earned this month".
-- Args: user_id (%d), since_mysql (%s).
-- Index: idx_user_created on (user_id, created_at).
SELECT COALESCE(SUM(points), 0) AS earned
FROM   {prefix}crm_points_ledger
WHERE  user_id    = %d
  AND  type       = 'earn'
  AND  created_at >= %s
