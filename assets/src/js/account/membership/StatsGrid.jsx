import { Card, CardContent } from "@/js/shared/ui/card.jsx";
import { money, number } from "@/js/shared/utils/format.js";

export function StatsGrid({ stats }) {
	const aov = stats.total_orders > 0 ? stats.lifetime_spend / stats.total_orders : 0;

	return (
		<div className="zc-grid zc-grid-cols-2 zc-gap-3 sm:zc-grid-cols-3">
			<Stat label="Total orders"       value={number(stats.total_orders)} />
			<Stat label="Lifetime spend"     value={money(stats.lifetime_spend, stats.currency)} />
			<Stat label="Average order"      value={stats.total_orders > 0 ? money(aov, stats.currency) : "—"} />
		</div>
	);
}

function Stat({ label, value }) {
	return (
		<Card>
			<CardContent className="zc-p-4">
				<p className="zc-text-xs zc-text-zinc-500">{label}</p>
				<p className="zc-mt-1 zc-text-lg zc-font-semibold zc-text-zinc-900 zc-tabular-nums">
					{value}
				</p>
			</CardContent>
		</Card>
	);
}
