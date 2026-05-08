-- Returns: total ledger row count for one user (used for pagination total_pages).
-- Index used: idx_user_created.
-- Args: user_id (%d).
SELECT COUNT(*) FROM {prefix}crm_points_ledger WHERE user_id = %d
