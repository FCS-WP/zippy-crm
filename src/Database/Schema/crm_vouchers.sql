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
	status           VARCHAR(20)      NOT NULL DEFAULT 'draft',
	starts_at        DATETIME         NULL,
	expires_at       DATETIME         NULL,
	created_by       BIGINT UNSIGNED  NOT NULL,
	created_at       DATETIME         NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_code (code),
	KEY idx_status_expiry (status, expires_at)
) {charset_collate};
