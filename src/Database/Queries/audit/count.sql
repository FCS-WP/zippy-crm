-- Total row count for the same filter shape as list_paginated.sql.
-- Args: event×2, actor_id×2, target_id×2.
SELECT COUNT(*)
FROM   {prefix}crm_audit_log
WHERE  ( %s = '__all__' OR event     = %s )
  AND  ( %d = -1        OR actor_id  = %d )
  AND  ( %d = -1        OR target_id = %d )
