import { Card, CardContent, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { Badge } from "@/js/shared/ui/badge.jsx";
import { money, number } from "@/js/shared/utils/format.js";
import { tierColor } from "@/js/shared/utils/tierColor.js";

/**
 * Visual ladder of every tier the customer can reach. Admin-only tiers are
 * still shown (so VIP isn't invisible) but flagged "by invitation".
 *
 * Render order is the API's natural sort_order. Current tier is highlighted;
 * tiers above the current one are dimmed (locked).
 */
export function TierLadder({ membership }) {
	const tiers = (membership.tiers ?? []).slice().sort((a, b) => a.sort_order - b.sort_order);
	const currentSort = tiers.find((t) => t.slug === membership.level)?.sort_order ?? 0;
	const currency    = membership.stats.currency;

	return (
		<Card>
			<CardHeader>
				<CardTitle>Membership tiers</CardTitle>
			</CardHeader>
			<CardContent className="zc-space-y-2">
				{tiers.map((tier) => {
					const isCurrent = tier.slug === membership.level;
					const isLocked  = tier.sort_order > currentSort;
					const colors    = tierColor(tier.sort_order);

					return (
						<TierRow
							key={tier.slug}
							tier={tier}
							colors={colors}
							currency={currency}
							isCurrent={isCurrent}
							isLocked={isLocked}
						/>
					);
				})}
			</CardContent>
		</Card>
	);
}

function TierRow({ tier, colors, currency, isCurrent, isLocked }) {
	const requirement = formatRequirement(tier, currency);

	return (
		<div
			className={[
				"zc-flex zc-flex-wrap zc-items-center zc-gap-3 zc-rounded-lg zc-border zc-px-4 zc-py-3",
				isCurrent
					? "zc-border-zinc-900 zc-bg-zinc-50"
					: "zc-border-zinc-200 zc-bg-white",
				isLocked && !tier.is_admin_only ? "zc-opacity-60" : "",
			].join(" ")}
		>
			<div className="zc-flex zc-min-w-0 zc-flex-1 zc-items-center zc-gap-3">
				<TierDot variant={colors.badge} />
				<div className="zc-min-w-0">
					<div className="zc-flex zc-items-center zc-gap-2">
						<span className="zc-font-semibold zc-text-zinc-900">{tier.label}</span>
						{isCurrent ? (
							<Badge variant="success" className="zc-text-[10px]">Current</Badge>
						) : null}
						{tier.is_admin_only ? (
							<Badge variant="muted" className="zc-text-[10px]">Invitation only</Badge>
						) : null}
					</div>
					<p className="zc-mt-0.5 zc-text-xs zc-text-zinc-500">{requirement}</p>
				</div>
			</div>
			<div className={`zc-text-sm zc-font-semibold zc-tabular-nums ${colors.accent}`}>
				{tier.multiplier}× points
			</div>
		</div>
	);
}

function TierDot({ variant }) {
	const cls = {
		muted:   "zc-bg-zinc-300",
		silver:  "zc-bg-zinc-400",
		gold:    "zc-bg-yellow-500",
		vip:     "zc-bg-fuchsia-600",
		success: "zc-bg-emerald-500",
		info:    "zc-bg-sky-500",
		warning: "zc-bg-amber-500",
		danger:  "zc-bg-rose-500",
	}[variant] ?? "zc-bg-zinc-400";
	return <span className={`zc-h-2.5 zc-w-2.5 zc-shrink-0 zc-rounded-full ${cls}`} />;
}

function formatRequirement(tier, currency) {
	if (tier.is_admin_only)               return "Assigned by our team";
	const o = tier.threshold_orders;
	const s = tier.threshold_spend;
	if (!o && !s)                         return "Default starter tier";
	if (o && s)                           return `${number(o)}+ orders or ${money(s, currency)} lifetime spend`;
	if (o)                                return `${number(o)}+ orders`;
	return `${money(s, currency)} lifetime spend`;
}
