import { Card, CardContent } from "@/js/shared/ui/card.jsx";
import { number } from "@/js/shared/utils/format.js";

export function PointsWidget({ points, link }) {
	return (
		<Card>
			<CardContent className="zc-p-5">
				<div className="zc-flex zc-items-baseline zc-justify-between">
					<p className="zc-text-sm zc-text-zinc-500">Points balance</p>
					{link ? (
						<a href={link} className="zc-text-xs zc-font-medium zc-text-zinc-700 hover:zc-text-zinc-900 hover:zc-underline">
							Manage →
						</a>
					) : null}
				</div>
				<p className="zc-mt-1 zc-text-3xl zc-font-semibold zc-text-zinc-900 zc-tabular-nums">
					{number(points.balance)}
				</p>
				<dl className="zc-mt-4 zc-grid zc-grid-cols-2 zc-gap-3 zc-border-t zc-border-zinc-100 zc-pt-3 zc-text-xs">
					<MiniStat label="Earned this month" value={`+${number(points.earned_month)}`} />
					<MiniStat label="Lifetime earned"   value={number(points.total_earned)} />
				</dl>
			</CardContent>
		</Card>
	);
}

function MiniStat({ label, value }) {
	return (
		<div>
			<dt className="zc-text-zinc-500">{label}</dt>
			<dd className="zc-mt-0.5 zc-font-semibold zc-text-zinc-900 zc-tabular-nums">{value}</dd>
		</div>
	);
}
