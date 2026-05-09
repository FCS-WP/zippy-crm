-- Returns: every user_id with a points summary row. Source list for
-- recalculate_all() — small (one row per active member), no pagination needed.
-- Args: none.
SELECT user_id
FROM   {prefix}crm_points_summary
ORDER  BY user_id
