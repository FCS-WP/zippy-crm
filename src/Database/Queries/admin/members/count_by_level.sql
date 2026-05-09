-- Returns: one row per level with a count, used by the admin Members Quick Stats bar.
-- Uses: full scan of crm_memberships (small table, 1 row per user — no index needed).
-- Args: none.
SELECT membership_level AS level, COUNT(*) AS total
FROM   {prefix}crm_memberships
GROUP  BY membership_level
