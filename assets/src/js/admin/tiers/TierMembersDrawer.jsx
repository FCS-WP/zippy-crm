import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { DataTable } from "@/js/shared/components/DataTable.jsx";
import { Pagination } from "@/js/shared/components/Pagination.jsx";
import { date, number } from "@/js/shared/utils/format.js";
import { LevelBadge, StatusBadge } from "../members/LevelBadge.jsx";

/**
 * Drawer body listing every member assigned to a tier. Reuses the existing
 * `GET /admin/members?level={slug}` endpoint — no new backend route. The
 * drawer is a slim view of the same data the Members panel shows when
 * filtered to one tier; admins who want full row actions can click "Open
 * in Members" to jump there.
 */
export function TierMembersDrawer({ tier }) {
	const [page, setPage]       = useState(1);
	const [perPage, setPerPage] = useState(10);

	const params = useMemo(
		() => ({ level: tier.slug, page, per_page: perPage }),
		[tier.slug, page, perPage],
	);
	const list = useApiQuery("/admin/members", { params });

	const items = list.data?.items ?? [];
	const total = list.data?.total ?? tier.member_count ?? 0;

	const columns = [
		{
			key: "user",
			label: "Member",
			render: (row) => (
				<>
					<div className="zc-font-medium zc-text-zinc-900">
						{row.display_name || row.user_login}
					</div>
					<div className="zc-text-xs zc-text-zinc-500">{row.user_email}</div>
				</>
			),
		},
		{
			key: "status",
			label: "Status",
			render: (row) => <StatusBadge status={row.status} />,
		},
		{
			key: "points",
			label: "Points",
			align: "right",
			cellClassName: "zc-tabular-nums zc-font-medium zc-text-zinc-900",
			render: (row) => number(row.points_balance),
		},
		{
			key: "joined_at",
			label: "Joined",
			cellClassName: "zc-whitespace-nowrap zc-text-xs zc-text-zinc-500",
			render: (row) => date(row.joined_at),
		},
	];

	// Builds the "Open in Members" link with the level filter pre-applied.
	// Uses URL search params so the Members panel picks up the filter on
	// mount (the panel reads URL on first render to seed state).
	const openInMembersHref = `${window.location.pathname}?page=zippy-crm&level=${encodeURIComponent(tier.slug)}`;

	return (
		<div className="zc-space-y-4">
			<div className="zc-flex zc-items-center zc-justify-between zc-gap-3">
				<div className="zc-flex zc-items-center zc-gap-2">
					<LevelBadge level={tier.slug} />
					<span className="zc-text-sm zc-text-zinc-600">
						<strong className="zc-text-zinc-900">{number(total)}</strong>{" "}
						member{total === 1 ? "" : "s"}
					</span>
				</div>
				<a
					href={openInMembersHref}
					className="zc-text-xs zc-text-zinc-500 hover:zc-text-zinc-900 hover:zc-underline zc-underline-offset-2"
				>
					Open in Members →
				</a>
			</div>

			{list.error ? (
				<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-800">
					{list.error.message || "Could not load members."}
				</div>
			) : (
				<>
					<DataTable
						columns={columns}
						rows={items}
						rowKey={(r) => r.user_id}
						loading={list.isLoading}
						empty="No members on this tier yet."
						density="compact"
					/>
					<Pagination
						page={page}
						perPage={perPage}
						total={total}
						onPage={setPage}
						onPerPage={(n) => { setPerPage(n); setPage(1); }}
						sizes={[ 10, 25, 50 ]}
					/>
				</>
			)}
		</div>
	);
}
