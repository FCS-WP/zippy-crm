import { Card, CardContent } from "@/js/shared/ui/card.jsx";
import { money, number } from "@/js/shared/utils/format.js";

export function StatsGrid({ stats }) {
	return (
		<div className="zc-space-y-4">
			<Stat label="Total orders"   value={number(stats.total_orders)} />
			<Stat label="Lifetime spend" value={money(stats.lifetime_spend, stats.currency)} />
		</div>
	);
}

function Stat({ label, value }) {
	return (
		<Card>
			<CardContent className="zc-p-5">
				<p className="zc-text-sm zc-text-zinc-500">{label}</p>
				<p className="zc-mt-1 zc-text-2xl zc-font-semibold zc-text-zinc-900">{value}</p>
			</CardContent>
		</Card>
	);
}
