-- Returns: paginated ledger rows for one user, newest first.
-- Index used: idx_user_created (user_id, created_at).
-- Args (in order): user_id (%d), per_page (%d), offset (%d).
SELECT id, type, points, reserved_points, pending_status, description, order_id, created_at
FROM   {prefix}crm_points_ledger
WHERE  user_id = %d
ORDER  BY created_at DESC, id DESC
LIMIT  %d OFFSET %d
