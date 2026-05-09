-- Returns: one-row system-wide totals from crm_points_summary.
--   issued      = SUM(total_earned)   — every point ever credited
--   redeemed    = SUM(total_redeemed) — every point ever debited (excludes pending)
--   outstanding = SUM(balance)        — active points still on accounts
--   members     = COUNT(*)            — distinct users with a points row
-- Reading the cached summary table avoids scanning crm_points_ledger.
-- Uses: PRIMARY (user_id) — full table scan but it's a small 1-row-per-user table.
-- Args: none.
SELECT COALESCE(SUM(total_earned),   0) AS issued,
       COALESCE(SUM(total_redeemed), 0) AS redeemed,
       COALESCE(SUM(balance),        0) AS outstanding,
       COUNT(*)                         AS members
FROM   {prefix}crm_points_summary
