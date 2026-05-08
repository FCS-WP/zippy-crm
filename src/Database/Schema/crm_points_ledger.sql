-- crm_points_ledger: append-only history of every point change.
-- Hot read: paginated by user, newest first (My Account → Points tab).
-- idx_user_created covers the WHERE+ORDER BY in one index lookup.
CREATE TABLE {prefix}crm_points_ledger (
	id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	user_id         BIGINT UNSIGNED NOT NULL,
	order_id        BIGINT UNSIGNED NULL,
	type            VARCHAR(20)     NOT NULL,
	points          INT             NOT NULL,
	reserved_points INT             NULL,
	pending_status  VARCHAR(16)     NULL,
	description     VARCHAR(255)    NULL,
	created_at      DATETIME        NOT NULL,
	PRIMARY KEY (id),
	KEY idx_user_created (user_id, created_at),
	KEY idx_order (order_id),
	KEY idx_pending (pending_status, user_id)
) {charset_collate};
