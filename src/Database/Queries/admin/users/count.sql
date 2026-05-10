-- Total row count for the same filters as list_paginated.sql.
-- Args: search_sentinel (%s), pat_login (%s), pat_email (%s), pat_name (%s),
--       has_sentinel (%s), has_sentinel (%s), has_sentinel (%s).
SELECT COUNT(*)
FROM   {prefix}users u
LEFT   JOIN {prefix}crm_memberships m ON m.user_id = u.ID
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
