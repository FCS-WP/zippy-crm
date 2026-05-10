import { useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { AvailableList } from "./AvailableList.jsx";
import { ClaimsList } from "./ClaimsList.jsx";
import { HistoryList } from "./HistoryList.jsx";
import { VouchersSkeleton } from "./VouchersSkeleton.jsx";

const SUBTABS = [
	{ key: "available", label: "Available" },
	{ key: "claims",    label: "My Claims" },
	{ key: "history",   label: "History"   },
];

const HISTORY_PAGE_SIZE = 50;

export default function VouchersTab() {
	const [tab, setTab] = useState("available");
	const [historyPage, setHistoryPage] = useState(1);

	const available = useApiQuery("/vouchers");
	const claims    = useApiQuery("/vouchers/claims");

	// History is paginated and lazily fetched only when the customer opens
	// the tab — no point loading it eagerly for the 90% of customers who
	// won't browse their archive on a given visit.
	const history = useApiQuery("/vouchers/claims/history", {
		params: { page: historyPage, per_page: HISTORY_PAGE_SIZE },
		enabled: tab === "history",
	});

	if (available.isLoading || claims.isLoading) return <VouchersSkeleton />;

	const counts = {
		available: available.data?.items?.length ?? 0,
		claims:    claims.data?.items?.length    ?? 0,
		// History total comes from the server (the SQL COUNT(*)) — using
		// items.length here would only show the loaded page count, not the
		// real archive size.
		history:   history.data?.total            ?? null,
	};

	const historyLoaded = (history.data?.items?.length ?? 0);
	const historyTotal  = history.data?.total ?? 0;
	const hasMoreHistory = historyLoaded < historyTotal;

	return (
		<div className="zc-space-y-5">
			<SubTabs value={tab} onChange={setTab} counts={counts} />

			{tab === "available" ? <AvailableList query={available} /> : null}
			{tab === "claims"    ? <ClaimsList    query={claims} />    : null}
			{tab === "history"   ? (
				<HistoryList
					query={history}
					onLoadMore={() => setHistoryPage((p) => p + 1)}
					hasMore={hasMoreHistory}
					loadingMore={history.isFetching}
				/>
			) : null}
		</div>
	);
}

function SubTabs({ value, onChange, counts }) {
	return (
		<div className="zc-inline-flex zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-1">
			{SUBTABS.map((t) => {
				const active = value === t.key;
				return (
					<button
						key={t.key}
						type="button"
						onClick={() => onChange(t.key)}
						className={[
							"zc-flex zc-items-center zc-gap-2 zc-rounded-md zc-px-4 zc-py-1.5 zc-text-sm zc-font-medium zc-transition-colors",
							active
								? "zc-bg-white zc-text-zinc-900 zc-shadow-sm"
								: "zc-text-zinc-600 hover:zc-text-zinc-900",
						].join(" ")}
					>
						<span>{t.label}</span>
						{counts[t.key] !== null && counts[t.key] !== undefined ? (
							<span className={[
								"zc-rounded-full zc-px-1.5 zc-py-0.5 zc-text-xs zc-font-semibold",
								active ? "zc-bg-zinc-900 zc-text-white" : "zc-bg-zinc-200 zc-text-zinc-600",
							].join(" ")}>
								{counts[t.key]}
							</span>
						) : null}
					</button>
				);
			})}
		</div>
	);
}
