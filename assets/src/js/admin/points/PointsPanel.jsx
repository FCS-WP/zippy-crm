import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";
import { LedgerTable } from "./LedgerTable.jsx";
import { PointsSkeleton } from "./PointsSkeleton.jsx";
import { RecalculateButton } from "./RecalculateButton.jsx";
import { SystemSummary } from "./SystemSummary.jsx";

const PER_PAGE = 20;

const TYPES = [
	{ key: "",       label: "All types"   },
	{ key: "earn",   label: "Earn"        },
	{ key: "redeem", label: "Redeem"      },
	{ key: "expire", label: "Expire"      },
	{ key: "adjust", label: "Adjust"      },
];

export default function PointsPanel() {
	const [type, setType] = useState("");
	const [page, setPage] = useState(1);

	const summary = useApiQuery("/admin/points/summary");
	const params  = useMemo(() => ({ type, page, per_page: PER_PAGE }), [type, page]);
	const ledger  = useApiQuery("/admin/points/ledger", { params });

	const items   = ledger.data?.items ?? [];
	const total   = ledger.data?.total ?? 0;
	const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

	const isLoading = summary.isLoading || ledger.isLoading;

	return (
		<div className="zc-space-y-5 zc-p-6">
			<header className="zc-flex zc-flex-wrap zc-items-start zc-justify-between zc-gap-3">
				<div>
					<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Points</h1>
					<p className="zc-text-sm zc-text-zinc-500">
						System totals, recent ledger activity, and bulk reconciliation.
					</p>
				</div>
				<RecalculateButton memberCount={summary.data?.members ?? 0} />
			</header>

			{isLoading ? (
				<PointsSkeleton />
			) : (
				<>
					<SystemSummary data={summary.data} />

					<div className="zc-flex zc-flex-wrap zc-items-center zc-justify-between zc-gap-3">
						<TypeSelect value={type} onChange={(v) => { setType(v); setPage(1); }} />
						<p className="zc-text-xs zc-text-zinc-500">
							Showing {items.length} of {total} ledger row{total === 1 ? "" : "s"}
						</p>
					</div>

					{ledger.error ? (
						<div className="zc-rounded-lg zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-4 zc-text-sm zc-text-rose-800">
							{ledger.error.message || "Could not load ledger."}
						</div>
					) : items.length === 0 ? (
						<EmptyState
							title={type ? "No ledger rows match this type." : "No ledger activity yet."}
							description={type ? "Switch to All types to see everything." : "Activity will appear here as customers earn, redeem, or get manual adjustments."}
						/>
					) : (
						<>
							<LedgerTable rows={items} />
							<Pagination page={page} totalPages={totalPages} total={total} onPage={setPage} />
						</>
					)}
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
				className="zc-h-10 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-pl-3 zc-pr-9 zc-text-sm zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
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
