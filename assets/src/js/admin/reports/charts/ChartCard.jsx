import { Card } from "@/js/shared/ui/card.jsx";

/**
 * Header + body + optional total. Body is fixed-height so Recharts'
 * ResponsiveContainer has something to measure.
 */
export function ChartCard({ title, subtitle, total, children, height = 280 }) {
	return (
		<Card className="zc-p-5">
			<div className="zc-mb-4 zc-flex zc-items-end zc-justify-between zc-gap-3">
				<div>
					<h3 className="zc-text-base zc-font-semibold zc-text-zinc-900">{title}</h3>
					{subtitle ? <p className="zc-text-xs zc-text-zinc-500">{subtitle}</p> : null}
				</div>
				{total ? (
					<div className="zc-text-right">
						<p className="zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">Total</p>
						<p className="zc-text-xl zc-font-semibold zc-text-zinc-900">{total}</p>
					</div>
				) : null}
			</div>
			<div style={{ height }}>{children}</div>
		</Card>
	);
}

/** Format a YYYY-MM-DD day label as "May 9". */
export function formatDayShort(day) {
	if (!day) return "";
	const d = new Date(day + "T00:00:00Z");
	return d.toLocaleDateString(undefined, { month: "short", day: "numeric", timeZone: "UTC" });
}
