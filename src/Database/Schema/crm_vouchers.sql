-- crm_vouchers: admin-published voucher codes customers can claim.
-- Hot reads:
--   1. List active+unexpired (My Account Vouchers tab)
--   2. Find by code (WC coupon sync, claim validation)
-- idx_status_expiry covers the listing query (status='active' AND expires_at>now).
-- code is UNIQUE because it mirrors the WC coupon code (1:1).
CREATE TABLE {prefix}crm_vouchers (
	id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
	code             VARCHAR(50)      NOT NULL,
	title            VARCHAR(255)     NOT NULL,
	description      TEXT             NULL,
	discount_type    VARCHAR(20)      NOT NULL,
	discount_value   DECIMAL(10,2)    NOT NULL,
	min_order_amount DECIMAL(10,2)    NOT NULL DEFAULT 0,
	max_uses         INT UNSIGNED     NOT NULL DEFAULT 0,
	uses_count       INT UNSIGNED     NOT NULL DEFAULT 0,
	status           VARCHAR(20)      NOT NULL DEFAULT 'draft',
	starts_at        DATETIME         NULL,
	expires_at       DATETIME         NULL,
	created_by       BIGINT UNSIGNED  NOT NULL,
	created_at       DATETIME         NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_code (code),
	KEY idx_status_expiry (status, expires_at)
) {charset_collate};
