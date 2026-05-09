-- Returns: total row count for the same filters as list_paginated.sql.
-- Used to compute pagination totals.
-- Args: level_sentinel  (%s), level  (%s),
--       status_sentinel (%s), status (%s),
--       search_sentinel (%s),
--           pat_login (%s), pat_email (%s), pat_name (%s).
SELECT COUNT(*) AS total
FROM   {prefix}crm_memberships m
JOIN   {prefix}users u ON u.ID = m.user_id
WHERE  ( %s = '__all__' OR m.membership_level = %s )
  AND  ( %s = '__all__' OR m.status = %s )
  AND  ( %s = '__all__' OR ( u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s ) )
