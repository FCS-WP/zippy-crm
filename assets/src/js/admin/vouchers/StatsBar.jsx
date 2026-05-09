import { Card } from "@/js/shared/ui/card.jsx";

const ITEMS = [
	{ key: "draft",   label: "Draft",   accent: "zc-text-zinc-700"     },
	{ key: "active",  label: "Active",  accent: "zc-text-emerald-700"  },
	{ key: "paused",  label: "Paused",  accent: "zc-text-amber-700"    },
	{ key: "expired", label: "Expired", accent: "zc-text-zinc-500"     },
];

export function StatsBar({ counts }) {
	return (
		<div className="zc-grid zc-grid-cols-2 zc-gap-3 md:zc-grid-cols-4">
			{ITEMS.map((item) => (
				<Card key={item.key} className="zc-p-4">
					<p className="zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
						{item.label}
					</p>
					<p className={`zc-mt-1 zc-text-2xl zc-font-semibold ${item.accent}`}>
						{counts?.[item.key] ?? 0}
					</p>
				</Card>
			))}
		</div>
	);
}
