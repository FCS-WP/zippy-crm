-- Returns: total count for the same filters as list_paginated.sql.
-- Used to compute pagination totals.
-- Args: status_sentinel (%s), status (%s),
--       search_sentinel (%s), pattern_code (%s), pattern_title (%s).
SELECT COUNT(*) AS total
FROM   {prefix}crm_vouchers
WHERE  ( %s = '__all__' OR status = %s )
  AND  ( %s = '__all__' OR ( code LIKE %s OR title LIKE %s ) )
