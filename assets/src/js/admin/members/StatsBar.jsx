import { Card } from "@/js/shared/ui/card.jsx";
import { useTiers } from "@/js/shared/hooks/useTiers.js";
import { tierColor } from "@/js/shared/utils/tierColor.js";

/**
 * One stat card per tier. Grid columns adapt to tier count: 4 → 4 cols,
 * 5-8 → up to 4 cols wrapping. Color picked from sort_order.
 */
export function StatsBar({ counts }) {
	const { tiers } = useTiers();
	if (!tiers.length) return null;

	return (
		<div className="zc-grid zc-grid-cols-2 zc-gap-3 md:zc-grid-cols-4">
			{tiers.map((tier) => {
				const color = tierColor(tier.sort_order);
				return (
					<Card key={tier.slug} className="zc-p-4">
						<p className="zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
							{tier.label}
						</p>
						<p className={`zc-mt-1 zc-text-2xl zc-font-semibold ${color.accent}`}>
							{counts?.[tier.slug] ?? 0}
						</p>
					</Card>
				);
			})}
		</div>
	);
}
