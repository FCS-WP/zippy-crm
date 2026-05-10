-- crm_voucher_claims: which user claimed which voucher.
-- The UNIQUE (voucher_id, user_id) constraint is the SQL-level guard against
-- double-claim (e.g. button double-click, race between two tabs).
-- idx_user covers "what have I claimed" (My Claims tab).
--
-- v1.10.0 added code_id for multi-code campaigns:
--   single-code voucher → code_id IS NULL; the customer's code is voucher.code
--   multi-code voucher  → code_id REFERENCES crm_voucher_codes.id; that row
--                         has the customer's unique-to-them code
--
-- v1.13.0 added revocation_reason. Status 'expired' is a multi-cause state:
-- voucher hit its expiry date, admin deleted the WC coupon (cascade), or the
-- customer's tier was downgraded out of an audience-restricted voucher. The
-- reason column lets the customer-facing History tab render a meaningful
-- label ("Voucher removed by admin" vs. "Tier no longer eligible" vs.
-- "Expired"). NULL when the row is still 'claimed' or 'used' — those don't
-- need a reason.
--
-- Vocabulary:
--   'expired_by_date'   → voucher.expires_at passed
--   'cascade_coupon'    → WC coupon deleted/trashed by admin (cascade hook)
--   'tier_downgrade'    → MembershipTierRevoker fired
--   'admin_revoke'      → reserved for future admin-initiated per-claim revoke
CREATE TABLE {prefix}crm_voucher_claims (
	id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	voucher_id        BIGINT UNSIGNED NOT NULL,
	user_id           BIGINT UNSIGNED NOT NULL,
	code_id           BIGINT UNSIGNED NULL,
	status            VARCHAR(20)     NOT NULL DEFAULT 'claimed',
	revocation_reason VARCHAR(32)     NULL,
	claimed_at        DATETIME        NOT NULL,
	used_at           DATETIME        NULL,
	order_id          BIGINT UNSIGNED NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_claim (voucher_id, user_id),
	KEY idx_user (user_id),
	KEY idx_voucher (voucher_id),
	KEY idx_code (code_id)
) {charset_collate};
