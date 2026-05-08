-- crm_points_summary: 1 row per user, balance cache.
-- MUST equal SUM(points) FROM crm_points_ledger WHERE user_id = X.
-- Read on every authenticated points UI; updated on every ledger write.
CREATE TABLE {prefix}crm_points_summary (
	user_id        BIGINT UNSIGNED NOT NULL,
	total_earned   INT UNSIGNED    NOT NULL DEFAULT 0,
	total_redeemed INT UNSIGNED    NOT NULL DEFAULT 0,
	balance        INT             NOT NULL DEFAULT 0,
	updated_at     DATETIME        NOT NULL,
	PRIMARY KEY (user_id)
) {charset_collate};
