-- crm_tiers: configurable membership tier definitions.
--
-- Replaces the hardcoded LEVELS/MULTIPLIERS/THRESHOLDS constants. Admins can
-- add (e.g. 'platinum'), rename labels, edit multipliers + thresholds.
--
-- PK is slug because slug is the natural key — every other table that
-- references a tier (crm_memberships.membership_level, REST responses, the
-- React side) uses the slug, not an integer id.
--
-- is_admin_only:
--   1 = automated tier evaluation never sets this tier (admin-assigned only).
--   The original 'vip' tier had this implicitly. The flag generalizes it.
--
-- sort_order:
--   Used for display ordering and as a tiebreaker when two tiers have the
--   same spend threshold. Auto-evaluation walks tiers descending by spend.
--
-- The four canonical tiers (free/silver/gold/vip) are seeded by Installer
-- on first install so legacy crm_memberships rows keep referring to a
-- valid slug.
CREATE TABLE {prefix}crm_tiers (
	slug             VARCHAR(40)     NOT NULL,
	label            VARCHAR(100)    NOT NULL,
	multiplier       DECIMAL(4,2)    NOT NULL DEFAULT 1.00,
	threshold_orders INT UNSIGNED    NULL,
	threshold_spend  DECIMAL(12,2)   NULL,
	is_admin_only    TINYINT(1)      NOT NULL DEFAULT 0,
	sort_order       INT             NOT NULL DEFAULT 0,
	created_at       DATETIME        NOT NULL,
	PRIMARY KEY (slug),
	KEY idx_sort (sort_order)
) {charset_collate};
