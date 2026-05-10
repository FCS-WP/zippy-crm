import { Badge } from "@/js/shared/ui/badge.jsx";
import { useTiers } from "@/js/shared/hooks/useTiers.js";
import { tierColor } from "@/js/shared/utils/tierColor.js";

/**
 * Tier badge — color comes from sort_order via tierColor(), label comes from
 * the tier definition. Falls back gracefully when tiers haven't loaded yet
 * or when a row references an unknown slug (e.g. a deleted tier left
 * orphaned membership rows).
 */
export function LevelBadge({ level }) {
	const { findTier, labelFor } = useTiers();
	const tier = findTier(level);
	const color = tierColor(tier?.sort_order);
	return <Badge variant={color.badge}>{labelFor(level)}</Badge>;
}

const STATUS_MAP = {
	active:    { variant: "success", label: "Active"    },
	suspended: { variant: "danger",  label: "Suspended" },
	expired:   { variant: "muted",   label: "Expired"   },
};

export function StatusBadge({ status }) {
	const info = STATUS_MAP[status] ?? { variant: "muted", label: status };
	return <Badge variant={info.variant}>{info.label}</Badge>;
}
