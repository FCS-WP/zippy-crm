-- Total row count for the same filter shape as list_paginated.sql.
-- See list_paginated.sql for sentinel-pattern rationale.
-- Args: event×2, actor_id×2, target_id×2, from, to.
SELECT COUNT(*)
FROM   {prefix}crm_audit_log
WHERE  ( %s = '__all__' OR event     = %s )
  AND  ( %d = -1        OR actor_id  = %d )
  AND  ( %d = -1        OR target_id = %d )
  AND  created_at >= %s
  AND  created_at <= %s
