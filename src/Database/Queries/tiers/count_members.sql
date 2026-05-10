-- How many members are currently on each tier slug.
-- Used as a deletion guard: a tier with > 0 members can't be hard-deleted.
-- Returns one row per slug with at least one member.
-- Index used: PK on crm_memberships.user_id covers the GROUP BY.
SELECT membership_level AS slug, COUNT(*) AS members
FROM   {prefix}crm_memberships
GROUP  BY membership_level
