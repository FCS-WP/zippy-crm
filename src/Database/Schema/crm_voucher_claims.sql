-- crm_voucher_claims: which user claimed which voucher.
-- The UNIQUE (voucher_id, user_id) constraint is the SQL-level guard against
-- double-claim (e.g. button double-click, race between two tabs).
-- idx_user covers "what have I claimed" (My Claims tab).
--
-- v1.10.0 added code_id for multi-code campaigns:
--   single-code voucher → code_id IS NULL; the customer's code is voucher.code
--   multi-code voucher  → code_id REFERENCES crm_voucher_codes.id; that row
--                         has the customer's unique-to-them code
CREATE TABLE {prefix}crm_voucher_claims (
	id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	voucher_id BIGINT UNSIGNED NOT NULL,
	user_id    BIGINT UNSIGNED NOT NULL,
	code_id    BIGINT UNSIGNED NULL,
	status     VARCHAR(20)     NOT NULL DEFAULT 'claimed',
	claimed_at DATETIME        NOT NULL,
	used_at    DATETIME        NULL,
	order_id   BIGINT UNSIGNED NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_claim (voucher_id, user_id),
	KEY idx_user (user_id),
	KEY idx_voucher (voucher_id),
	KEY idx_code (code_id)
) {charset_collate};
