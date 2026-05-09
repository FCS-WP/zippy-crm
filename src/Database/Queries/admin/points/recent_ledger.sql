-- Returns: recent ledger rows globally (newest first), joined with wp_users
--   for display. `pending_redeem` rows (zero impact, status-tracking only)
--   are excluded — they clutter the admin view and have a separate lifecycle.
-- Filter sentinel '__all__' = no type filter; otherwise must be one of the
-- ledger TYPES (validated at controller).
-- Uses: idx_user_created (covers ORDER BY created_at via composite); PK on users.
-- Args: type_sentinel (%s), type (%s), per_page (%d), offset (%d).
SELECT l.id, l.user_id, l.order_id, l.type,
       l.points, l.reserved_points, l.pending_status,
       l.description, l.created_at,
       u.user_login, u.display_name, u.user_email
FROM   {prefix}crm_points_ledger l
LEFT   JOIN {prefix}users u ON u.ID = l.user_id
WHERE  l.type <> 'pending_redeem'
  AND  ( %s = '__all__' OR l.type = %s )
ORDER  BY l.created_at DESC, l.id DESC
LIMIT  %d OFFSET %d
