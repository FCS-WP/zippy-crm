import { Card, CardContent, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { Progress } from "@/js/shared/ui/progress.jsx";
import { money, number, percent } from "@/js/shared/utils/format.js";

export function TierProgress({ membership }) {
	const next = membership.next_tier;

	if (!next) {
		return (
			<Card>
				<CardHeader>
					<CardTitle>You've reached the top tier</CardTitle>
				</CardHeader>
				<CardContent>
					<p className="zc-text-sm zc-text-zinc-500">
						Enjoy the maximum benefits available — points multiplier {membership.multiplier}×.
					</p>
				</CardContent>
			</Card>
		);
	}

	const isSpend = next.metric === "spend";
	const remaining = isSpend
		? money(next.remaining, membership.stats.currency)
		: `${number(next.remaining)} more ${next.remaining === 1 ? "order" : "orders"}`;

	return (
		<Card>
			<CardHeader>
				<CardTitle>Progress to {next.level_label}</CardTitle>
			</CardHeader>
			<CardContent className="zc-space-y-3">
				<div className="zc-flex zc-items-baseline zc-justify-between zc-text-sm">
					<span className="zc-text-zinc-500">
						{isSpend
							? `${money(next.current, membership.stats.currency)} of ${money(next.target, membership.stats.currency)}`
							: `${number(next.current)} of ${number(next.target)} orders`}
					</span>
					<span className="zc-font-medium zc-text-zinc-900">{percent(next.percent, 1)}</span>
				</div>
				<Progress value={next.percent} />
				<p className="zc-text-sm zc-text-zinc-600">
					Spend <span className="zc-font-semibold zc-text-zinc-900">{remaining}</span> to reach{" "}
					<span className="zc-font-semibold zc-text-zinc-900">{next.level_label}</span>.
				</p>
			</CardContent>
		</Card>
	);
}
