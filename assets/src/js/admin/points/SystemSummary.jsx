import { Card } from "@/js/shared/ui/card.jsx";
import { money, number } from "@/js/shared/utils/format.js";

export function SystemSummary({ data }) {
	const cards = [
		{ key: "issued",      label: "Issued",      accent: "zc-text-emerald-700", value: number(data?.issued      ?? 0) + " pts" },
		{ key: "redeemed",    label: "Redeemed",    accent: "zc-text-rose-700",    value: number(data?.redeemed    ?? 0) + " pts" },
		{ key: "outstanding", label: "Outstanding", accent: "zc-text-zinc-900",    value: number(data?.outstanding ?? 0) + " pts" },
		{ key: "members",     label: "Members",     accent: "zc-text-zinc-700",    value: number(data?.members     ?? 0) },
	];

	const liability = data?.outstanding_dollar_value ?? 0;

	return (
		<div className="zc-space-y-3">
			<div className="zc-grid zc-grid-cols-2 zc-gap-3 md:zc-grid-cols-4">
				{cards.map((c) => (
					<Card key={c.key} className="zc-p-4">
						<p className="zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">{c.label}</p>
						<p className={`zc-mt-1 zc-text-2xl zc-font-semibold ${c.accent}`}>{c.value}</p>
					</Card>
				))}
			</div>
			{data ? (
				<p className="zc-text-xs zc-text-zinc-500">
					Outstanding liability ≈ <strong className="zc-text-zinc-700">{money(liability)}</strong>{" "}
					at {data.redemption_rate} pts = $1.
				</p>
			) : null}
		</div>
	);
}
