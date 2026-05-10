-- crm_voucher_codes: per-code rows for multi-code voucher campaigns.
--
-- Voucher distribution modes:
--   'single_code'        — legacy / default. crm_vouchers.code is the master
--                          code; this table has zero rows for the voucher.
--   'multi_code_public'  — admin pre-mints N unique codes (this table has N
--                          rows, status='available'). First N customers to
--                          claim each get one assigned. Each code is a
--                          separate WC_Coupon with max_uses=1.
--
-- Atomic-claim invariant: the path that picks a code MUST do a single
-- conditional UPDATE — `UPDATE … WHERE status='available' LIMIT 1`. InnoDB
-- row locks serialize concurrent claims so two customers can't be assigned
-- the same code.
--
-- Status lifecycle:
--   available → assigned (customer claimed; not yet used)
--   assigned  → used     (customer placed an order using their code)
--   any       → expired  (admin pause/expire; daily cron eventually)
CREATE TABLE {prefix}crm_voucher_codes (
	id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	voucher_id         BIGINT UNSIGNED NOT NULL,
	code               VARCHAR(50)     NOT NULL,
	status             VARCHAR(16)     NOT NULL DEFAULT 'available',
	assigned_to_user   BIGINT UNSIGNED NULL,
	assigned_to_email  VARCHAR(255)    NULL,
	assigned_at        DATETIME        NULL,
	used_at            DATETIME        NULL,
	order_id           BIGINT UNSIGNED NULL,
	created_at         DATETIME        NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_code (code),
	KEY idx_voucher_status (voucher_id, status),
	KEY idx_assigned_user (assigned_to_user)
) {charset_collate};
