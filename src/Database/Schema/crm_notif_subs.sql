-- crm_notif_subs: 1 row per user, opt-in preferences for two channels.
-- Read on every batch dispatch ("who should we email about this voucher?")
-- and on every load of the My Account → Notifications tab.
-- PK is user_id — no surrogate id needed (single-row-per-user table).
CREATE TABLE {prefix}crm_notif_subs (
	user_id             BIGINT UNSIGNED NOT NULL,
	subscribed_vouchers TINYINT(1)      NOT NULL DEFAULT 1,
	subscribed_points   TINYINT(1)      NOT NULL DEFAULT 1,
	updated_at          DATETIME        NOT NULL,
	PRIMARY KEY (user_id),
	KEY idx_subscribed_vouchers (subscribed_vouchers),
	KEY idx_subscribed_points (subscribed_points)
) {charset_collate};
