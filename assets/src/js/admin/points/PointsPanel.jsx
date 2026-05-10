import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { FilterChips } from "@/js/shared/components/FilterChips.jsx";
import { Pagination } from "@/js/shared/components/Pagination.jsx";
import { LedgerTable } from "./LedgerTable.jsx";
import { PointsSkeleton } from "./PointsSkeleton.jsx";
import { RecalculateButton } from "./RecalculateButton.jsx";
import { SystemSummary } from "./SystemSummary.jsx";

const TYPES = [
	{ key: "",       label: "All types" },
	{ key: "earn",   label: "Earn"      },
	{ key: "redeem", label: "Redeem"    },
	{ key: "expire", label: "Expire"    },
	{ key: "adjust", label: "Adjust"    },
];
const TYPE_LABELS = Object.fromEntries(TYPES.map((t) => [t.key, t.label]));

export default function PointsPanel() {
	const [type, setType]       = useState("");
	const [page, setPage]       = useState(1);
	const [perPage, setPerPage] = useState(20);

	const summary = useApiQuery("/admin/points/summary");
	const params  = useMemo(() => ({ type, page, per_page: perPage }), [type, page, perPage]);
	const ledger  = useApiQuery("/admin/points/ledger", { params });

	const items = ledger.data?.items ?? [];
	const total = ledger.data?.total ?? 0;

	return (
		<div className="zc-space-y-4 zc-p-6">
			<header className="zc-flex zc-flex-wrap zc-items-start zc-justify-between zc-gap-3">
				<div>
					<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Points</h1>
					<p className="zc-text-sm zc-text-zinc-500">
						System totals, recent ledger activity, and bulk reconciliation.
					</p>
				</div>
				<RecalculateButton memberCount={summary.data?.members ?? 0} />
			</header>

			{summary.isLoading ? (
				<PointsSkeleton />
			) : (
				<>
					<SystemSummary data={summary.data} />

					<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-2">
						<TypeSelect value={type} onChange={(v) => { setType(v); setPage(1); }} />
					</div>

					<FilterChips
						filters={[
							{ key: "type", label: "Type", value: type, valueLabel: TYPE_LABELS[type] ?? type, onClear: () => { setType(""); setPage(1); } },
						]}
						onClearAll={() => { setType(""); setPage(1); }}
					/>

					<LedgerTable
						rows={items}
						loading={ledger.isLoading}
						error={ledger.error?.message}
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
		</div>
	);
}

function TypeSelect({ value, onChange }) {
	return (
		<div className="zc-relative">
			<select
				aria-label="Filter ledger by type"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				style={{ WebkitAppearance: "none", MozAppearance: "none", appearance: "none", backgroundImage: "none" }}
				className="zc-h-9 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-pl-3 zc-pr-9 zc-text-sm zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
			>
				{TYPES.map((t) => (
					<option key={t.key || "all"} value={t.key}>{t.label}</option>
				))}
			</select>
			<svg
				viewBox="0 0 24 24"
				className="zc-pointer-events-none zc-absolute zc-right-3 zc-top-1/2 zc-size-4 -zc-translate-y-1/2 zc-text-zinc-500"
				fill="none" stroke="currentColor" strokeWidth="2" aria-hidden
			>
				<path strokeLinecap="round" strokeLinejoin="round" d="M6 9l6 6 6-6" />
			</svg>
		</div>
	);
}
