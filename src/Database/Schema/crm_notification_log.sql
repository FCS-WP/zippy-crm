-- crm_notification_log: idempotency + audit for voucher email dispatch.
-- One row per (voucher, user). UNIQUE on that pair is the authoritative
-- guard against double-sends — both the queue-subscribers query and the
-- per-row INSERT rely on it.
--
-- Lifecycle: 'queued' on insert → 'sent' or 'failed' after wp_mail.
-- 'failed' rows are retried by the hourly cron up to RETRY_MAX times;
-- once exhausted, they stay 'failed' and are visible in admin Reports.
CREATE TABLE {prefix}crm_notification_log (
	id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	voucher_id    BIGINT UNSIGNED NOT NULL,
	user_id       BIGINT UNSIGNED NOT NULL,
	status        VARCHAR(16)     NOT NULL DEFAULT 'queued',
	attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
	last_error    VARCHAR(255)    NULL,
	queued_at     DATETIME        NOT NULL,
	sent_at       DATETIME        NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_notif (voucher_id, user_id),
	KEY idx_status_attempts (status, attempts)
) {charset_collate};
