import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Card } from "@/js/shared/ui/card.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { date, money, number } from "@/js/shared/utils/format.js";
import { LevelBadge, StatusBadge } from "./LevelBadge.jsx";

export function MemberDetailDrawerBody({ userId }) {
	const { data, isLoading, error } = useApiQuery(`/admin/members/${userId}`);

	if (isLoading) {
		return (
			<div className="zc-space-y-3">
				<Skeleton className="zc-h-20 zc-w-full" />
				<Skeleton className="zc-h-32 zc-w-full" />
			</div>
		);
	}
	if (error) {
		return <p className="zc-text-sm zc-text-rose-700">{error.message || "Could not load member."}</p>;
	}
	if (!data) return null;

	return (
		<div className="zc-space-y-4">
			<Card className="zc-p-4">
				<div className="zc-flex zc-items-start zc-justify-between zc-gap-3">
					<div>
						<h3 className="zc-text-base zc-font-semibold zc-text-zinc-900">
							{data.user.display_name || data.user.login}
						</h3>
						<p className="zc-text-sm zc-text-zinc-500">{data.user.email}</p>
						<p className="zc-mt-1 zc-text-xs zc-text-zinc-400">
							Registered {date(data.user.registered)}
						</p>
					</div>
					<div className="zc-flex zc-flex-col zc-items-end zc-gap-1">
						<LevelBadge level={data.level} />
						<StatusBadge status={data.status} />
					</div>
				</div>
			</Card>

			<Card className="zc-p-4">
				<p className="zc-mb-3 zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
					Lifetime stats
				</p>
				<dl className="zc-grid zc-grid-cols-3 zc-gap-3 zc-text-center">
					<Stat label="Orders"   value={number(data.stats.total_orders)} />
					<Stat label="Spend"    value={money(data.stats.lifetime_spend, data.stats.currency)} />
					<Stat label="Multiplier" value={`${data.multiplier}×`} />
				</dl>
			</Card>

			<Card className="zc-p-4">
				<p className="zc-mb-2 zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
					Membership
				</p>
				<dl className="zc-grid zc-grid-cols-2 zc-gap-y-1.5 zc-text-sm">
					<dt className="zc-text-zinc-500">Joined</dt>
					<dd className="zc-text-right zc-text-zinc-900">{date(data.joined_at)}</dd>
					<dt className="zc-text-zinc-500">Expires</dt>
					<dd className="zc-text-right zc-text-zinc-900">{data.expires_at ? date(data.expires_at) : "—"}</dd>
				</dl>
			</Card>
		</div>
	);
}

function Stat({ label, value }) {
	return (
		<div>
			<dt className="zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">{label}</dt>
			<dd className="zc-mt-1 zc-text-lg zc-font-semibold zc-text-zinc-900">{value}</dd>
		</div>
	);
}
