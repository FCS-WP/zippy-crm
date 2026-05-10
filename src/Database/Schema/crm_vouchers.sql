-- crm_vouchers: admin-published voucher codes customers can claim.
-- Hot reads:
--   1. List active+unexpired (My Account Vouchers tab)
--   2. Find by code (WC coupon sync, claim validation)
-- idx_status_expiry covers the listing query (status='active' AND expires_at>now).
-- code is UNIQUE because it mirrors the WC coupon code (1:1).
--
-- v1.9.0 added the WC-coupon-parity columns (max_order_amount … allowed_hours).
-- Array fields are JSON-encoded TEXT; loaded via json_decode at sync time.
-- allowed_hours shape (when not null):
--   {"days":[0..6],"from_minute":0..1439,"to_minute":1..1440}
-- Site timezone applies. NULL = always available.
--
-- v1.10.0 added distribution_mode for multi-code voucher campaigns:
--   'single_code'       — legacy. crm_vouchers.code is the master code.
--   'multi_code_public' — N rows in crm_voucher_codes; each customer gets a
--                         unique single-use code on claim. crm_vouchers.code
--                         holds a synthetic placeholder (`ZC_MULTI_<id>`) so
--                         the existing NOT NULL + UNIQUE on `code` keeps
--                         working — never used as a real WC coupon code.
--
-- v1.11.0 added audience targeting (mutually exclusive with email_restrictions):
--   audience_mode = 'public' — anyone may claim (default).
--   audience_mode = 'email'  — restricted to email_restrictions list.
--   audience_mode = 'tier'   — restricted to allowed_tiers list (JSON of tier slugs).
-- These three modes are mutually exclusive: VoucherService rejects any payload
-- that mixes email + tier restriction. The existing email_restrictions column
-- remains the source of truth for mode='email'; allowed_tiers is the new column
-- for mode='tier'. Public mode leaves both columns NULL/empty.
CREATE TABLE {prefix}crm_vouchers (
	id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
	code             VARCHAR(50)      NOT NULL,
	title            VARCHAR(255)     NOT NULL,
	description      TEXT             NULL,
	discount_type    VARCHAR(20)      NOT NULL,
	discount_value   DECIMAL(10,2)    NOT NULL,
	min_order_amount DECIMAL(10,2)    NOT NULL DEFAULT 0,
	max_order_amount DECIMAL(10,2)    NOT NULL DEFAULT 0,
	max_uses         INT UNSIGNED     NOT NULL DEFAULT 0,
	uses_count       INT UNSIGNED     NOT NULL DEFAULT 0,
	usage_limit_per_user   INT UNSIGNED NOT NULL DEFAULT 0,
	limit_usage_to_x_items INT UNSIGNED NOT NULL DEFAULT 0,
	individual_use     TINYINT(1)     NOT NULL DEFAULT 1,
	exclude_sale_items TINYINT(1)     NOT NULL DEFAULT 0,
	free_shipping      TINYINT(1)     NOT NULL DEFAULT 0,
	email_restrictions          TEXT  NULL,
	product_ids                 TEXT  NULL,
	excluded_product_ids        TEXT  NULL,
	product_categories          TEXT  NULL,
	excluded_product_categories TEXT  NULL,
	allowed_hours               TEXT  NULL,
	distribution_mode  VARCHAR(20)    NOT NULL DEFAULT 'single_code',
	audience_mode      VARCHAR(16)    NOT NULL DEFAULT 'public',
	allowed_tiers               TEXT  NULL,
	status           VARCHAR(20)      NOT NULL DEFAULT 'draft',
	starts_at        DATETIME         NULL,
	expires_at       DATETIME         NULL,
	created_by       BIGINT UNSIGNED  NOT NULL,
	created_at       DATETIME         NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_code (code),
	KEY idx_status_expiry (status, expires_at)
) {charset_collate};
