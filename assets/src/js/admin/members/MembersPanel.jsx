import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";
import { Drawer } from "../vouchers/Drawer.jsx";
import { FilterBar } from "./FilterBar.jsx";
import { LevelChangeForm } from "./LevelChangeForm.jsx";
import { MemberDetailDrawerBody } from "./MemberDetailDrawer.jsx";
import { MembersSkeleton } from "./MembersSkeleton.jsx";
import { MembersTable } from "./MembersTable.jsx";
import { PointsAdjustForm } from "./PointsAdjustForm.jsx";
import { StatsBar } from "./StatsBar.jsx";

const PER_PAGE = 20;

export default function MembersPanel() {
	const [level, setLevel]   = useState("");
	const [status, setStatus] = useState("");
	const [search, setSearch] = useState("");
	const [page, setPage]     = useState(1);

	const [detailUser, setDetailUser] = useState(null); // user_id
	const [levelRow, setLevelRow]     = useState(null);
	const [pointsRow, setPointsRow]   = useState(null);

	const params = useMemo(
		() => ({ level, status, search, page, per_page: PER_PAGE }),
		[level, status, search, page],
	);
	const list = useApiQuery("/admin/members", { params });

	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;
	const counts = list.data?.counts;
	const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

	return (
		<div className="zc-space-y-5 zc-p-6">
			<header>
				<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Members</h1>
				<p className="zc-text-sm zc-text-zinc-500">
					Filter by level or status, search by login/email, and adjust points or level per row.
				</p>
			</header>

			<StatsBar counts={counts} />

			<FilterBar
				level={level}
				onLevel={(l) => { setLevel(l); setPage(1); }}
				status={status}
				onStatus={(s) => { setStatus(s); setPage(1); }}
				search={search}
				onSearch={(q) => { setSearch(q); setPage(1); }}
			/>

			{list.isLoading ? (
				<MembersSkeleton />
			) : list.error ? (
				<div className="zc-rounded-lg zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-4 zc-text-sm zc-text-rose-800">
					{list.error.message || "Could not load members."}
				</div>
			) : items.length === 0 ? (
				<EmptyState
					title={search || level || status ? "No members match your filters." : "No members yet."}
					description={search || level || status ? "Clear filters to see everyone." : "Members appear here as customers register."}
				/>
			) : (
				<>
					<MembersTable
						rows={items}
						onView={(row) => setDetailUser(row.user_id)}
						onChangeLevel={setLevelRow}
						onAdjustPoints={setPointsRow}
					/>
					<Pagination page={page} totalPages={totalPages} total={total} onPage={setPage} />
				</>
			)}

			<Drawer
				open={detailUser !== null}
				onClose={() => setDetailUser(null)}
				title="Member detail"
				width="zc-max-w-md"
			>
				{detailUser !== null ? <MemberDetailDrawerBody userId={detailUser} /> : null}
			</Drawer>

			<Drawer
				open={Boolean(levelRow)}
				onClose={() => setLevelRow(null)}
				title={levelRow ? `Change level — ${levelRow.display_name || levelRow.user_login}` : "Change level"}
				width="zc-max-w-md"
			>
				{levelRow ? <LevelChangeForm row={levelRow} onClose={() => setLevelRow(null)} /> : null}
			</Drawer>

			<Drawer
				open={Boolean(pointsRow)}
				onClose={() => setPointsRow(null)}
				title={pointsRow ? `Adjust points — ${pointsRow.display_name || pointsRow.user_login}` : "Adjust points"}
				width="zc-max-w-md"
			>
				{pointsRow ? <PointsAdjustForm row={pointsRow} onClose={() => setPointsRow(null)} /> : null}
			</Drawer>
		</div>
	);
}

function Pagination({ page, totalPages, total, onPage }) {
	if (totalPages <= 1) return null;
	return (
		<div className="zc-flex zc-items-center zc-justify-between zc-text-sm zc-text-zinc-600">
			<span>{total} total · page {page} of {totalPages}</span>
			<div className="zc-flex zc-gap-2">
				<Button size="sm" variant="outline" disabled={page <= 1} onClick={() => onPage(page - 1)}>
					Previous
				</Button>
				<Button size="sm" variant="outline" disabled={page >= totalPages} onClick={() => onPage(page + 1)}>
					Next
				</Button>
			</div>
		</div>
	);
}
