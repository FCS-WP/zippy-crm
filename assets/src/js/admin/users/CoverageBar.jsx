import { number } from "@/js/shared/utils/format.js";

/**
 * "X of Y users are members" callout. Two halves of a single-row card:
 *   - left: bold count + caption
 *   - right: visual progress bar showing coverage ratio
 *
 * Hidden when there are zero users (avoids "0 of 0" division noise on
 * brand-new sites).
 */
export function CoverageBar({ totals }) {
	const total   = totals?.total_users  ?? 0;
	const members = totals?.member_count ?? 0;
	if (total <= 0) return null;

	const pct = Math.round((members / total) * 100);
	const non = total - members;

	return (
		<div className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-p-4">
			<div className="zc-flex zc-items-end zc-justify-between zc-gap-3">
				<div>
					<p className="zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
						Membership coverage
					</p>
					<p className="zc-mt-1 zc-text-sm zc-text-zinc-700">
						<strong className="zc-text-2xl zc-font-semibold zc-text-zinc-900">{number(members)}</strong>
						<span className="zc-mx-1 zc-text-zinc-400">/</span>
						<span className="zc-text-zinc-600">{number(total)} users are members</span>
					</p>
				</div>
				<div className="zc-text-right">
					<p className="zc-text-2xl zc-font-semibold zc-text-zinc-900 zc-tabular-nums">{pct}%</p>
					<p className="zc-text-xs zc-text-zinc-500">{number(non)} not yet</p>
				</div>
			</div>
			<div className="zc-mt-3 zc-h-2 zc-w-full zc-overflow-hidden zc-rounded-full zc-bg-zinc-100">
				<div
					className="zc-h-full zc-rounded-full zc-bg-emerald-500 zc-transition-all"
					style={{ width: `${pct}%` }}
				/>
			</div>
		</div>
	);
}
