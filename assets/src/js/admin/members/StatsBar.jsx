import { Card } from "@/js/shared/ui/card.jsx";

const ITEMS = [
	{ key: "free",   label: "Free",   accent: "zc-text-zinc-700"     },
	{ key: "silver", label: "Silver", accent: "zc-text-zinc-700"     },
	{ key: "gold",   label: "Gold",   accent: "zc-text-yellow-700"   },
	{ key: "vip",    label: "VIP",    accent: "zc-text-fuchsia-700"  },
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
