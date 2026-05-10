-- Returns: paginated WP user list, excluding administrators, joined with
--   our membership row (auto-LEFT) + points summary (auto-LEFT) so the
--   admin sees both "registered users" and their CRM state in one query.
--
-- Filter sentinels keep one prepared shape regardless of which filters are
-- active:
--   '__all__' = "skip this filter"
--   has_membership: '__all__' / 'yes' / 'no'
--
-- Search matches user_login OR user_email OR display_name (caller wraps with %).
--
-- Admin exclusion: wp_capabilities is a serialized PHP array and we can't
-- reliably parse it in SQL. The role name appears verbatim inside the
-- serialized blob, so a LIKE '%"administrator"%' is good enough — no false
-- positives because the role name is always quoted in the serialised form.
--
-- Args: search_sentinel (%s), pat_login (%s), pat_email (%s), pat_name (%s),
--       has_sentinel (%s), has_sentinel (%s), has_sentinel (%s),
--       per_page (%d), offset (%d).
SELECT u.ID            AS user_id,
       u.user_login,
       u.user_email,
       u.display_name,
       u.user_registered,
       m.membership_level,
       m.status        AS membership_status,
       m.joined_at,
       COALESCE(s.balance, 0)        AS points_balance,
       COALESCE(s.total_earned, 0)   AS points_earned,
       COALESCE(s.total_redeemed, 0) AS points_redeemed,
       (m.id IS NOT NULL)            AS has_membership
FROM   {prefix}users u
LEFT   JOIN {prefix}crm_memberships    m ON m.user_id = u.ID
LEFT   JOIN {prefix}crm_points_summary s ON s.user_id = u.ID
WHERE  NOT EXISTS (
         SELECT 1 FROM {prefix}usermeta um
         WHERE  um.user_id = u.ID
           AND  um.meta_key = 'wp_capabilities'
           AND  um.meta_value LIKE '%"administrator"%'
       )
  AND  ( %s = '__all__' OR ( u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s ) )
  AND  ( %s = '__all__'
         OR ( %s = 'yes' AND m.id IS NOT NULL )
         OR ( %s = 'no'  AND m.id IS NULL )
       )
ORDER  BY u.user_registered DESC, u.ID DESC
LIMIT  %d OFFSET %d
