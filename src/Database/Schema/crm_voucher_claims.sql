-- crm_voucher_claims: which user claimed which voucher.
-- The UNIQUE (voucher_id, user_id) constraint is the SQL-level guard against
-- double-claim (e.g. button double-click, race between two tabs).
-- idx_user covers "what have I claimed" (My Claims tab).
CREATE TABLE {prefix}crm_voucher_claims (
	id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	voucher_id BIGINT UNSIGNED NOT NULL,
	user_id    BIGINT UNSIGNED NOT NULL,
	status     VARCHAR(20)     NOT NULL DEFAULT 'claimed',
	claimed_at DATETIME        NOT NULL,
	used_at    DATETIME        NULL,
	order_id   BIGINT UNSIGNED NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_claim (voucher_id, user_id),
	KEY idx_user (user_id),
	KEY idx_voucher (voucher_id)
) {charset_collate};
