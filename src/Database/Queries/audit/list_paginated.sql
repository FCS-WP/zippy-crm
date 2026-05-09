-- Returns: paginated audit rows with optional filters.
-- Sentinel pattern: '__all__' for event, -1 for actor_id/target_id means
-- "skip this filter". Single prepared shape covers every combination so we
-- don't rebuild the query for each.
-- Index used: idx_target_created or idx_actor_created or idx_event_created
-- depending on which filter is active. The planner picks; in pure no-filter
-- listings it falls back to ORDER BY created_at on the PK + a filesort but
-- the table is small enough (admin-only writes).
-- Args: event×2, actor_id×2, target_id×2, limit, offset.
SELECT id, event, actor_id, target_id, meta_json, created_at
FROM   {prefix}crm_audit_log
WHERE  ( %s = '__all__' OR event     = %s )
  AND  ( %d = -1        OR actor_id  = %d )
  AND  ( %d = -1        OR target_id = %d )
ORDER  BY created_at DESC, id DESC
LIMIT  %d OFFSET %d
