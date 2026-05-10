import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { FilterChips } from "@/js/shared/components/FilterChips.jsx";
import { Pagination } from "@/js/shared/components/Pagination.jsx";
import { Drawer } from "./Drawer.jsx";
import { FilterBar } from "./FilterBar.jsx";
import { StatsBar } from "./StatsBar.jsx";
import { VoucherDetailDrawer } from "./VoucherDetailDrawer.jsx";
import { VoucherForm } from "./VoucherForm.jsx";
import { VouchersTable } from "./VouchersTable.jsx";

const STATUS_LABELS = {
	draft:   "Draft",
	active:  "Active",
	paused:  "Paused",
	expired: "Expired",
};

export default function VouchersPanel() {
	const [status, setStatus]   = useState("");
	const [search, setSearch]   = useState("");
	const [page, setPage]       = useState(1);
	const [perPage, setPerPage] = useState(20);
	const [sort, setSort]       = useState({ key: "id", dir: "desc" });

	const [formRow, setFormRow]     = useState(null);
	const [formOpen, setFormOpen]   = useState(false);
	const [detailRow, setDetailRow] = useState(null);

	const params = useMemo(
		() => ({
			status, search,
			page, per_page: perPage,
			sort: sort.key, direction: sort.dir,
		}),
		[status, search, page, perPage, sort.key, sort.dir],
	);
	const list = useApiQuery("/admin/vouchers", { params });

	const openCreate = () => { setFormRow(null);  setFormOpen(true); };
	const openEdit   = (row) => {
		// "Edit" comes from two places: the row's ⋯ menu, and the Edit button
		// inside the Detail drawer's Overview tab. In both cases we want the
		// edit form, and we want the detail drawer to close so admins don't
		// see a stale read-only view layered behind the editable one.
		setDetailRow(null);
		setFormRow(row);
		setFormOpen(true);
	};
	const closeForm   = () => setFormOpen(false);
	const closeDetail = () => setDetailRow(null);

	const items  = list.data?.items  ?? [];
	const total  = list.data?.total  ?? 0;
	const counts = list.data?.counts;

	const onSort = (key) => {
		setSort((s) => s.key === key
			? { key, dir: s.dir === "asc" ? "desc" : "asc" }
			: { key, dir: "desc" }
		);
		setPage(1);
	};

	const clearAllFilters = () => {
		setStatus(""); setSearch(""); setPage(1);
	};

	return (
		<div className="zc-space-y-4 zc-p-6">
			<header className="zc-flex zc-flex-wrap zc-items-center zc-justify-between zc-gap-3">
				<div>
					<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Vouchers</h1>
					<p className="zc-text-sm zc-text-zinc-500">
						Create, publish, pause and review vouchers your customers can claim.
					</p>
				</div>
				<Button onClick={openCreate}>+ New voucher</Button>
			</header>

			<StatsBar counts={counts} />

			<FilterBar
				status={status}
				onStatus={(s) => { setStatus(s); setPage(1); }}
				search={search}
				onSearch={(q) => { setSearch(q); setPage(1); }}
			/>

			<FilterChips
				filters={[
					{ key: "status", label: "Status", value: status, valueLabel: STATUS_LABELS[status] ?? status, onClear: () => { setStatus(""); setPage(1); } },
					{ key: "search", label: "Search", value: search, valueLabel: `"${search}"`, onClear: () => { setSearch(""); setPage(1); } },
				]}
				onClearAll={clearAllFilters}
			/>

			{list.error ? (
				<div className="zc-rounded-lg zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-4 zc-text-sm zc-text-rose-800">
					{list.error.message || "Could not load vouchers."}
				</div>
			) : (
				<>
					<VouchersTable
						rows={items}
						sort={sort}
						onSort={onSort}
						loading={list.isLoading}
						onEdit={openEdit}
						onDetail={setDetailRow}
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
				open={formOpen}
				onClose={closeForm}
				title={formRow ? `Edit voucher — ${formRow.code}` : "New voucher"}
			>
				<VoucherForm row={formRow} onClose={closeForm} />
			</Drawer>

			<Drawer
				open={Boolean(detailRow)}
				onClose={closeDetail}
				title={detailRow ? detailRow.title : "Voucher details"}
				width="zc-max-w-2xl"
			>
				{detailRow ? <VoucherDetailDrawer voucher={detailRow} onEdit={openEdit} /> : null}
			</Drawer>
		</div>
	);
}
