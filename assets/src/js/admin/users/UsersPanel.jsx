import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { FilterChips } from "@/js/shared/components/FilterChips.jsx";
import { Pagination } from "@/js/shared/components/Pagination.jsx";
import { CoverageBar } from "./CoverageBar.jsx";
import { FilterBar } from "./FilterBar.jsx";
import { UsersTable } from "./UsersTable.jsx";

const HAS_LABELS = {
	yes: "Members only",
	no:  "Non-members only",
};

export default function UsersPanel() {
	const [search, setSearch]   = useState("");
	const [has, setHas]         = useState("");
	const [page, setPage]       = useState(1);
	const [perPage, setPerPage] = useState(25);

	const params = useMemo(
		() => ({ search, has_membership: has, page, per_page: perPage }),
		[search, has, page, perPage],
	);
	const list = useApiQuery("/admin/users", { params });

	const items  = list.data?.items  ?? [];
	const total  = list.data?.total  ?? 0;
	const totals = list.data?.totals;

	const clearAll = () => { setSearch(""); setHas(""); setPage(1); };

	return (
		<div className="zc-space-y-4 zc-p-6">
			<header>
				<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Users</h1>
				<p className="zc-text-sm zc-text-zinc-500">
					Every non-admin WP user, with their CRM coverage. The Members panel is
					the funnel-narrowed view of users who already have a membership row.
				</p>
			</header>

			<CoverageBar totals={totals} />

			<FilterBar
				search={search} onSearch={(q) => { setSearch(q); setPage(1); }}
				has={has}       onHas={(v)    => { setHas(v);    setPage(1); }}
			/>

			<FilterChips
				filters={[
					{ key: "search", label: "Search", value: search, valueLabel: `"${search}"`, onClear: () => { setSearch(""); setPage(1); } },
					{ key: "has",    label: "Filter", value: has,    valueLabel: HAS_LABELS[has] ?? "", onClear: () => { setHas(""); setPage(1); } },
				]}
				onClearAll={clearAll}
			/>

			<UsersTable
				rows={items}
				loading={list.isLoading}
				error={list.error?.message}
			/>
			<Pagination
				page={page}
				perPage={perPage}
				total={total}
				onPage={setPage}
				onPerPage={(n) => { setPerPage(n); setPage(1); }}
			/>
		</div>
	);
}
