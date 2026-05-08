-- Returns: authoritative total_earned, total_redeemed, balance for one user
-- by re-aggregating the ledger. Used to repair drift in crm_points_summary.
-- Index used: idx_user_created (uses just the user_id prefix).
-- Args: user_id (%d).
SELECT
	COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0)        AS total_earned,
	COALESCE(SUM(CASE WHEN points < 0 THEN -points ELSE 0 END), 0)       AS total_redeemed,
	COALESCE(SUM(points), 0)                                             AS balance
FROM {prefix}crm_points_ledger
WHERE user_id = %d
