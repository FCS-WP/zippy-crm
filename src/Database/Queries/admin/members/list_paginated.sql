-- Returns: paginated member list for the admin Members panel.
--   Joins wp_users (auth identity) + crm_memberships (level/status/joined_at)
--   + crm_points_summary (balance) so the table renders without N+1.
--   crm_memberships is INNER JOINed because a member-row is required (we
--   auto-seed on registration), but crm_points_summary is LEFT-joined since
--   the user may not have any points history yet.
-- Filters use the FILTER_ALL sentinel to keep the same prepared shape.
-- Search matches user_login OR user_email OR display_name (caller wraps with %).
-- Uses: uq_user on memberships; PRIMARY on summary; PRIMARY on users.
-- Args: level_sentinel  (%s), level  (%s),
--       status_sentinel (%s), status (%s),
--       search_sentinel (%s),
--           pat_login (%s), pat_email (%s), pat_name (%s),
--       per_page (%d), offset (%d).
SELECT u.ID            AS user_id,
       u.user_login,
       u.user_email,
       u.display_name,
       u.user_registered,
       m.id            AS membership_id,
       m.membership_level,
       m.status        AS membership_status,
       m.joined_at,
       m.expires_at,
       COALESCE(s.balance, 0)        AS points_balance,
       COALESCE(s.total_earned, 0)   AS points_earned,
       COALESCE(s.total_redeemed, 0) AS points_redeemed
FROM   {prefix}crm_memberships m
JOIN   {prefix}users u ON u.ID = m.user_id
LEFT   JOIN {prefix}crm_points_summary s ON s.user_id = m.user_id
WHERE  ( %s = '__all__' OR m.membership_level = %s )
  AND  ( %s = '__all__' OR m.status = %s )
  AND  ( %s = '__all__' OR ( u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s ) )
ORDER  BY m.id DESC
LIMIT  %d OFFSET %d
