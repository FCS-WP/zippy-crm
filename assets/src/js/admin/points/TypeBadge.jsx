import { Badge } from "@/js/shared/ui/badge.jsx";

const MAP = {
	earn:    { variant: "success", label: "Earn"    },
	redeem:  { variant: "info",    label: "Redeem"  },
	expire:  { variant: "muted",   label: "Expire"  },
	adjust:  { variant: "warning", label: "Adjust"  },
};

export function TypeBadge({ type }) {
	const info = MAP[type] ?? { variant: "muted", label: type };
	return <Badge variant={info.variant}>{info.label}</Badge>;
}
