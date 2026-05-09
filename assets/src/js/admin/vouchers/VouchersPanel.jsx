import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";
import { ClaimsDrawerBody } from "./ClaimsDrawerBody.jsx";
import { Drawer } from "./Drawer.jsx";
import { FilterBar } from "./FilterBar.jsx";
import { StatsBar } from "./StatsBar.jsx";
import { VoucherForm } from "./VoucherForm.jsx";
import { VouchersSkeleton } from "./VouchersSkeleton.jsx";
import { VouchersTable } from "./VouchersTable.jsx";

const PER_PAGE = 20;

export default function VouchersPanel() {
	const [status, setStatus] = useState("");
	const [search, setSearch] = useState("");
	const [page, setPage]     = useState(1);

	const [formRow, setFormRow]     = useState(null);
	const [formOpen, setFormOpen]   = useState(false);
	const [claimsRow, setClaimsRow] = useState(null);

	const params = useMemo(
		() => ({ status, search, page, per_page: PER_PAGE }),
		[status, search, page],
	);
	const list = useApiQuery("/admin/vouchers", { params });

	const openCreate = () => { setFormRow(null);  setFormOpen(true); };
	const openEdit   = (row) => { setFormRow(row); setFormOpen(true); };

	const closeForm   = () => setFormOpen(false);
	const closeClaims = () => setClaimsRow(null);

	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;
	const counts = list.data?.counts;
	const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

	return (
		<div className="zc-space-y-5 zc-p-6">
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

			{list.isLoading ? (
				<VouchersSkeleton />
			) : list.error ? (
				<div className="zc-rounded-lg zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-4 zc-text-sm zc-text-rose-800">
					{list.error.message || "Could not load vouchers."}
				</div>
			) : items.length === 0 ? (
				<EmptyState
					title={search || status ? "No vouchers match your filters." : "No vouchers yet."}
					description={search || status ? "Clear the filter to see everything." : "Create your first voucher to start letting customers claim discounts."}
					action={!search && !status ? <Button onClick={openCreate}>Create voucher</Button> : null}
				/>
			) : (
				<>
					<VouchersTable rows={items} onEdit={openEdit} onClaims={setClaimsRow} />
					<Pagination page={page} totalPages={totalPages} total={total} onPage={setPage} />
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
				open={Boolean(claimsRow)}
				onClose={closeClaims}
				title={claimsRow ? `Claims — ${claimsRow.code}` : "Claims"}
				width="zc-max-w-lg"
			>
				{claimsRow ? <ClaimsDrawerBody voucher={claimsRow} /> : null}
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
