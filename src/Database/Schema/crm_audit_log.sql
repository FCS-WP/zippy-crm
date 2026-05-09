-- crm_audit_log: who did what to whom, for admin write actions.
--
-- Customer + system events have their own audit tables already
-- (crm_points_ledger, crm_voucher_claims, crm_notification_log) — this table
-- captures the gap: privileged admin actions that mutate user state.
--
-- One row per discrete admin event. `meta_json` stores event-specific payload
-- (from-level / to-level, points-delta, reason text) — kept as JSON because
-- shape varies by event type and the data is read more than queried.
--
-- Indexes:
--   idx_target_created — "show me everything that happened to user X" (admin
--                         drilldown from a Members panel row)
--   idx_actor_created  — "show me everything admin Y did" (audit reports)
--   idx_event_created  — "show me every points adjustment" (filter by event)
CREATE TABLE {prefix}crm_audit_log (
	id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	event       VARCHAR(64)     NOT NULL,
	actor_id    BIGINT UNSIGNED NOT NULL,
	target_id   BIGINT UNSIGNED NULL,
	meta_json   TEXT            NULL,
	created_at  DATETIME        NOT NULL,
	PRIMARY KEY (id),
	KEY idx_target_created (target_id, created_at),
	KEY idx_actor_created (actor_id, created_at),
	KEY idx_event_created (event, created_at)
) {charset_collate};
