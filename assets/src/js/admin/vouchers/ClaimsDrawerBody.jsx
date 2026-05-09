import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { dateTime } from "@/js/shared/utils/format.js";
import { StatusBadge } from "./StatusBadge.jsx";

const STATUS_LABEL = {
	claimed: "Claimed",
	used:    "Used",
	expired: "Expired",
};

export function ClaimsDrawerBody({ voucher }) {
	const { data, isLoading, error } = useApiQuery(`/admin/vouchers/${voucher.id}/claims`);

	if (isLoading) {
		return (
			<div className="zc-space-y-3">
				{[0, 1, 2].map((i) => <Skeleton key={i} className="zc-h-10 zc-w-full" />)}
			</div>
		);
	}

	if (error) {
		return <p className="zc-text-sm zc-text-rose-700">{error.message || "Could not load claims."}</p>;
	}

	const items = data?.items ?? [];
	if (items.length === 0) {
		return <p className="zc-text-sm zc-text-zinc-500">Nobody has claimed this voucher yet.</p>;
	}

	return (
		<div className="zc-space-y-2">
			<p className="zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
				{items.length} {items.length === 1 ? "claim" : "claims"}
			</p>
			<ul className="zc-divide-y zc-divide-zinc-100 zc-overflow-hidden zc-rounded-lg zc-border zc-border-zinc-200">
				{items.map((claim) => (
					<li key={claim.id} className="zc-flex zc-items-center zc-justify-between zc-gap-3 zc-bg-white zc-px-3 zc-py-2.5 zc-text-sm">
						<div className="zc-min-w-0">
							<div className="zc-truncate zc-font-medium zc-text-zinc-900">
								{claim.display_name || claim.user_login || `User #${claim.user_id}`}
							</div>
							<div className="zc-truncate zc-text-xs zc-text-zinc-500">
								{claim.user_email || `user-${claim.user_id}`}
							</div>
						</div>
						<div className="zc-text-right zc-text-xs zc-text-zinc-500">
							<div>Claimed {dateTime(claim.claimed_at)}</div>
							{claim.used_at ? <div>Used {dateTime(claim.used_at)}</div> : null}
						</div>
						<StatusBadgeFor status={claim.status} />
					</li>
				))}
			</ul>
		</div>
	);
}

function StatusBadgeFor({ status }) {
	if (status === "used")    return <StatusBadge status="active" />;
	if (status === "expired") return <StatusBadge status="expired" />;
	return <span className="zc-rounded-full zc-bg-sky-100 zc-px-2.5 zc-py-0.5 zc-text-xs zc-font-medium zc-text-sky-800">{STATUS_LABEL[status] ?? status}</span>;
}
