import { Card, CardContent } from "@/js/shared/ui/card.jsx";
import { money, number } from "@/js/shared/utils/format.js";

/**
 * Lightweight insights computed on the client from the ledger we already have.
 * No extra REST round-trip. If we later add /points/insights we can swap the
 * source here without touching the rest of the tab.
 */
export function InsightsStrip({ summary, ledger }) {
	const items = ledger?.items ?? [];
	const now = Date.now();
	const cutoff30d = now - 30 * 24 * 60 * 60 * 1000;

	let earned30 = 0;
	let redeemed30 = 0;
	let lastEarnAt = null;

	for (const row of items) {
		const ts = row.created_at ? new Date(row.created_at).getTime() : 0;
		if (ts < cutoff30d) continue;
		if (row.points > 0) {
			earned30 += row.points;
			if (!lastEarnAt || ts > lastEarnAt) lastEarnAt = ts;
		} else {
			redeemed30 += -row.points;
		}
	}

	const lifetimeWorth = summary.total_earned / summary.redemption_rate;

	return (
		<div className="zc-grid zc-gap-3 sm:zc-grid-cols-3">
			<Insight
				label="Earned · 30d"
				value={`+${number(earned30)}`}
				tone={earned30 > 0 ? "positive" : "muted"}
				hint={lastEarnAt ? `Last earned ${formatRelative(lastEarnAt)}` : "No recent activity"}
			/>
			<Insight
				label="Redeemed · 30d"
				value={`−${number(redeemed30)}`}
				tone={redeemed30 > 0 ? "negative" : "muted"}
				hint={redeemed30 > 0 ? `${money(redeemed30 / summary.redemption_rate)} of savings` : "Nothing yet"}
			/>
			<Insight
				label="Lifetime worth"
				value={money(lifetimeWorth)}
				tone="muted"
				hint={`From ${number(summary.total_earned)} pts earned`}
			/>
		</div>
	);
}

function Insight({ label, value, tone, hint }) {
	const valueColor = {
		positive: "zc-text-emerald-700",
		negative: "zc-text-rose-700",
		muted:    "zc-text-zinc-900",
	}[tone];
	return (
		<Card>
			<CardContent className="zc-p-4">
				<p className="zc-text-xs zc-font-medium zc-uppercase zc-tracking-wider zc-text-zinc-500">{label}</p>
				<p className={`zc-mt-1 zc-text-2xl zc-font-semibold ${valueColor}`}>{value}</p>
				<p className="zc-mt-0.5 zc-text-xs zc-text-zinc-500">{hint}</p>
			</CardContent>
		</Card>
	);
}

function formatRelative(ts) {
	const diff = Date.now() - ts;
	const day = 24 * 60 * 60 * 1000;
	if (diff < day)        return "today";
	if (diff < 2 * day)    return "yesterday";
	if (diff < 7 * day)    return `${Math.floor(diff / day)} days ago`;
	if (diff < 30 * day)   return `${Math.floor(diff / (7 * day))}w ago`;
	return `${Math.floor(diff / (30 * day))}mo ago`;
}
