/**
 * Single source of truth for audit event metadata on the React side.
 *
 * Mirrors the AuditLogger::EVENT_* constants on the PHP side. The category
 * drives chip colour + the grouped event-filter dropdown; the label is the
 * human-readable rendering of the event name.
 *
 * If the backend gains a new event, add it here too. Unknown events fall
 * back to a muted chip with the raw slug — they still render, just without
 * polish.
 */

export const EVENT_CATEGORIES = [
	{ key: "membership", label: "Membership", tone: "info"    },
	{ key: "points",     label: "Points",     tone: "success" },
	{ key: "voucher",    label: "Voucher",    tone: "warning" },
	{ key: "tier",       label: "Tier",       tone: "vip"     },
];

export const EVENTS = [
	{ key: "membership.level_changed",  category: "membership", label: "Level changed"      },
	{ key: "membership.status_changed", category: "membership", label: "Status changed"     },

	{ key: "points.adjusted",           category: "points",     label: "Points adjusted"    },
	{ key: "points.recalculated",       category: "points",     label: "Recalculated all"   },

	{ key: "voucher.created",           category: "voucher",    label: "Voucher created"    },
	{ key: "voucher.updated",           category: "voucher",    label: "Voucher updated"    },
	{ key: "voucher.published",         category: "voucher",    label: "Published"          },
	{ key: "voucher.paused",            category: "voucher",    label: "Paused"             },
	{ key: "voucher.resumed",           category: "voucher",    label: "Resumed"            },
	{ key: "voucher.deleted",           category: "voucher",    label: "Deleted"            },
	{ key: "voucher.duplicated",        category: "voucher",    label: "Duplicated"         },

	{ key: "tier.created",              category: "tier",       label: "Tier created"       },
	{ key: "tier.updated",              category: "tier",       label: "Tier updated"       },
	{ key: "tier.deleted",              category: "tier",       label: "Tier deleted"       },
];

const EVENTS_BY_KEY = Object.fromEntries(EVENTS.map((e) => [e.key, e]));
const CATEGORIES_BY_KEY = Object.fromEntries(EVENT_CATEGORIES.map((c) => [c.key, c]));

export function findEvent(key) {
	return EVENTS_BY_KEY[key] ?? null;
}

export function categoryFor(eventKey) {
	const e = EVENTS_BY_KEY[eventKey];
	if (!e) return { key: "other", label: "Other", tone: "muted" };
	return CATEGORIES_BY_KEY[e.category] ?? { key: e.category, label: e.category, tone: "muted" };
}

export function labelFor(eventKey) {
	return EVENTS_BY_KEY[eventKey]?.label ?? eventKey;
}
