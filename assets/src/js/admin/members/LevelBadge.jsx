import { Badge } from "@/js/shared/ui/badge.jsx";

const MAP = {
	free:   { variant: "muted",  label: "Free"   },
	silver: { variant: "silver", label: "Silver" },
	gold:   { variant: "gold",   label: "Gold"   },
	vip:    { variant: "vip",    label: "VIP"    },
};

export function LevelBadge({ level }) {
	const info = MAP[level] ?? { variant: "muted", label: level };
	return <Badge variant={info.variant}>{info.label}</Badge>;
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
