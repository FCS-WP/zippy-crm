-- Returns: paginated audit rows with optional filters.
-- Sentinel pattern:
--   '__all__'              for event (VARCHAR column)
--   -1                     for actor_id / target_id (INT columns)
--   '1970-01-01 00:00:00'  for `from` (no-filter sentinel)
--   '9999-12-31 23:59:59'  for `to`   (no-filter sentinel)
-- The DATETIME sentinels are needed because MySQL strict mode validates
-- literal types in the prepared statement, even on branches that short-circuit
-- — '__all__' compared against created_at would error before the OR could
-- rescue it. Using bracketing DATETIMEs keeps the WHERE a no-op without
-- the type-cast warning.
-- Index used: idx_target_created or idx_actor_created or idx_event_created
-- depending on which filter is active.
-- Args: event×2, actor_id×2, target_id×2, from, to, limit, offset.
SELECT id, event, actor_id, target_id, meta_json, created_at
FROM   {prefix}crm_audit_log
WHERE  ( %s = '__all__' OR event     = %s )
  AND  ( %d = -1        OR actor_id  = %d )
  AND  ( %d = -1        OR target_id = %d )
  AND  created_at >= %s
  AND  created_at <= %s
ORDER  BY created_at DESC, id DESC
LIMIT  %d OFFSET %d
