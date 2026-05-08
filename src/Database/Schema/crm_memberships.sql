-- crm_memberships: 1 row per user.
-- Read-heavy: every authenticated WC page render hits this for points multiplier.
-- Indexes: PRIMARY (id) + uq_user (user_id) is enough; status filter is rare enough
-- to skip a separate index on a 1-row-per-user table.
CREATE TABLE {prefix}crm_memberships (
	id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id          BIGINT UNSIGNED NOT NULL,
	membership_level VARCHAR(20)     NOT NULL DEFAULT 'free',
	status           VARCHAR(20)     NOT NULL DEFAULT 'active',
	joined_at        DATETIME        NOT NULL,
	expires_at       DATETIME        NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_user (user_id)
) {charset_collate};
