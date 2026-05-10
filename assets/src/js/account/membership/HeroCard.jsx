import { Card, CardContent } from "@/js/shared/ui/card.jsx";
import { Badge } from "@/js/shared/ui/badge.jsx";
import { date } from "@/js/shared/utils/format.js";
import { tierColor } from "@/js/shared/utils/tierColor.js";

const STATUS_VARIANT = { active: "success", suspended: "danger", expired: "muted" };

/**
 * Top-of-page hero. The user's tier is the dominant element — whoever
 * lands here should know "I'm a Silver member" before reading anything else.
 * Multiplier is rendered as a plain-English benefit, not a bare "1.2×".
 */
export function HeroCard({ membership }) {
	const { level_label, multiplier, status, joined_at, expires_at, user, tiers, level } = membership;
	const tier   = (tiers ?? []).find((t) => t.slug === level);
	const colors = tierColor(tier?.sort_order ?? 0);

	const earnLine = multiplier === 1
		? "Standard 1× points on every order"
		: multiplier > 1
			? `Earn ${Math.round((multiplier - 1) * 100)}% more points on every order`
			: `Earns ${Math.round(multiplier * 100)}% of standard points`;

	return (
		<Card className="zc-overflow-hidden">
			<div className="zc-h-1 zc-w-full zc-bg-gradient-to-r zc-from-zinc-900 zc-via-zinc-700 zc-to-zinc-900" />
			<CardContent className="zc-p-6 sm:zc-p-7">
				<div className="zc-flex zc-flex-wrap zc-items-start zc-justify-between zc-gap-4">
					<div className="zc-min-w-0">
						<p className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-zinc-500">
							Welcome back
						</p>
						<h2 className="zc-mt-1 zc-truncate zc-text-xl zc-font-semibold zc-text-zinc-900">
							{user.display_name}
						</h2>
						<p className="zc-mt-0.5 zc-truncate zc-text-sm zc-text-zinc-500">
							{user.email}
						</p>
					</div>
					<div className="zc-flex zc-flex-col zc-items-end zc-gap-1.5">
						<Badge variant={colors.badge} className="zc-text-xs">{level_label} member</Badge>
						<Badge variant={STATUS_VARIANT[status] ?? "muted"} className="zc-text-[10px]">
							{status}
						</Badge>
					</div>
				</div>

				<div className={`zc-mt-5 zc-rounded-lg zc-bg-zinc-50 zc-px-4 zc-py-3 zc-text-sm ${colors.accent}`}>
					<span className="zc-font-semibold">{multiplier}× points</span>
					<span className="zc-ml-1.5 zc-text-zinc-600">— {earnLine}.</span>
				</div>

				<dl className="zc-mt-4 zc-grid zc-grid-cols-2 zc-gap-4 zc-text-sm sm:zc-grid-cols-3">
					<Field label="Member since" value={date(joined_at)} />
					<Field label="Expires"      value={expires_at ? date(expires_at) : "Never"} />
					<Field label="Tier earn rate" value={`${multiplier}×`} />
				</dl>
			</CardContent>
		</Card>
	);
}

function Field({ label, value }) {
	return (
		<div>
			<dt className="zc-text-zinc-500">{label}</dt>
			<dd className="zc-mt-0.5 zc-font-medium zc-text-zinc-900">{value}</dd>
		</div>
	);
}
