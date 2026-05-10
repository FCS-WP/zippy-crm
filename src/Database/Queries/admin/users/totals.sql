-- Returns: 2 columns — total non-admin users + how many have a CRM
-- membership row. Used by the Users panel callout: "X of Y users are members".
-- Args: none.
SELECT
  COUNT(*) AS total_users,
  SUM(CASE WHEN m.id IS NOT NULL THEN 1 ELSE 0 END) AS member_count
FROM   {prefix}users u
LEFT   JOIN {prefix}crm_memberships m ON m.user_id = u.ID
WHERE  NOT EXISTS (
         SELECT 1 FROM {prefix}usermeta um
         WHERE  um.user_id = u.ID
           AND  um.meta_key = 'wp_capabilities'
           AND  um.meta_value LIKE '%"administrator"%'
       )
