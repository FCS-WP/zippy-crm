import { Badge } from "@/js/shared/ui/badge.jsx";

const MAP = {
	draft:   { variant: "muted",   label: "Draft"   },
	active:  { variant: "success", label: "Active"  },
	paused:  { variant: "warning", label: "Paused"  },
	expired: { variant: "danger",  label: "Expired" },
};

export function StatusBadge({ status }) {
	const info = MAP[status] ?? { variant: "muted", label: status };
	return <Badge variant={info.variant}>{info.label}</Badge>;
}
