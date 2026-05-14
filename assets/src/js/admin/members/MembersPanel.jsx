import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { useTiers } from "@/js/shared/hooks/useTiers.js";
import { FilterChips } from "@/js/shared/components/FilterChips.jsx";
import { Pagination } from "@/js/shared/components/Pagination.jsx";
import { Drawer } from "../vouchers/Drawer.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { AddMemberDialog } from "./AddMemberDialog.jsx";
import { FilterBar } from "./FilterBar.jsx";
import { LevelChangeForm } from "./LevelChangeForm.jsx";
import { MemberDetailDrawerBody } from "./MemberDetailDrawer.jsx";
import { MembersTable } from "./MembersTable.jsx";
import { PointsAdjustForm } from "./PointsAdjustForm.jsx";
import { StatsBar } from "./StatsBar.jsx";

const STATUS_LABELS = {
	active:    "Active",
	suspended: "Suspended",
	expired:   "Expired",
};

export default function MembersPanel() {
	const [level, setLevel]     = useState("");
	const [status, setStatus]   = useState("");
	const [search, setSearch]   = useState("");
	const [page, setPage]       = useState(1);
	const [perPage, setPerPage] = useState(20);
	const [sort, setSort]       = useState({ key: "joined_at", dir: "desc" });

	const [detailUser, setDetailUser] = useState(null);
	const [levelRow, setLevelRow]     = useState(null);
	const [pointsRow, setPointsRow]   = useState(null);
	const [addOpen, setAddOpen]       = useState(false);

	const { labelFor } = useTiers();

	const params = useMemo(
		() => ({
			level, status, search,
			page, per_page: perPage,
			sort: sort.key, direction: sort.dir,
		}),
		[level, status, search, page, perPage, sort.key, sort.dir],
	);
	const list = useApiQuery("/admin/members", { params });

	const items   = list.data?.items  ?? [];
	const total   = list.data?.total  ?? 0;
	const counts  = list.data?.counts;

	// Click a sortable header → toggle dir if same key, else switch key + reset to desc.
	const onSort = (key) => {
		setSort((s) => s.key === key
			? { key, dir: s.dir === "asc" ? "desc" : "asc" }
			: { key, dir: "desc" }
		);
		setPage(1);
	};

	const clearAllFilters = () => {
		setLevel(""); setStatus(""); setSearch(""); setPage(1);
	};

	return (
		<div className="zc-space-y-4 zc-p-6">
			<header className="zc-flex zc-items-start zc-justify-between zc-gap-4">
				<div>
					<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Members</h1>
					<p className="zc-text-sm zc-text-zinc-500">
						Filter by level or status, search by login/email, and adjust points or level per row.
					</p>
				</div>
				<Button onClick={() => setAddOpen(true)}>Add member</Button>
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

			<FilterChips
				filters={[
					{ key: "level",  label: "Level",  value: level,  valueLabel: level  ? labelFor(level) : "",  onClear: () => { setLevel("");  setPage(1); } },
					{ key: "status", label: "Status", value: status, valueLabel: status ? STATUS_LABELS[status] ?? status : "", onClear: () => { setStatus(""); setPage(1); } },
					{ key: "search", label: "Search", value: search, valueLabel: `"${search}"`, onClear: () => { setSearch(""); setPage(1); } },
				]}
				onClearAll={clearAllFilters}
			/>

			{list.error ? (
				<div className="zc-rounded-lg zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-4 zc-text-sm zc-text-rose-800">
					{list.error.message || "Could not load members."}
				</div>
			) : (
				<>
					<MembersTable
						rows={items}
						sort={sort}
						onSort={onSort}
						loading={list.isLoading}
						onView={(row) => setDetailUser(row.user_id)}
						onChangeLevel={setLevelRow}
						onAdjustPoints={setPointsRow}
					/>
					<Pagination
						page={page}
						perPage={perPage}
						total={total}
						onPage={setPage}
						onPerPage={(n) => { setPerPage(n); setPage(1); }}
					/>
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

			<AddMemberDialog
				open={addOpen}
				onClose={() => setAddOpen(false)}
				onEnrolled={(userId) => {
					// Close the add dialog and immediately open the new member's
					// detail drawer so the admin can set tier / adjust points
					// without an extra navigation.
					setAddOpen(false);
					setDetailUser(userId);
				}}
			/>
		</div>
	);
}
