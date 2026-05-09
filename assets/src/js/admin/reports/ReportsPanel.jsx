import { Suspense, lazy, useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { DateRangePicker, presetRange } from "./DateRangePicker.jsx";
import { ReportsSkeleton } from "./ReportsSkeleton.jsx";

// One lazy chunk for all three charts so the Recharts runtime is fetched
// once. Per perf rule: chart lib only loads when this panel mounts.
const Charts = lazy(() => import("./charts/index.jsx"));

export default function ReportsPanel() {
	const [range, setRange] = useState(() => presetRange(30));

	const params = useMemo(() => ({ from: range.from, to: range.to }), [range.from, range.to]);

	const members  = useApiQuery("/admin/reports/members-per-day",  { params });
	const points   = useApiQuery("/admin/reports/points-activity",  { params });
	const vouchers = useApiQuery("/admin/reports/voucher-claims",   { params });

	// `isFetching` (true on background refetch) AND `isLoading` (true on first
	// load only) — the charts must not render with `series=[]` and then re-mount
	// with real data, because Recharts' ResponsiveContainer can throw a DOM
	// reconciliation error when re-mounted mid-resize.
	const isLoading = members.isLoading || points.isLoading || vouchers.isLoading;
	const error     = members.error     || points.error     || vouchers.error;
	const ready     = !isLoading && !error
		&& members.data?.series  && points.data?.series  && vouchers.data?.series;

	return (
		<div className="zc-space-y-5 zc-p-6">
			<header className="zc-flex zc-flex-wrap zc-items-start zc-justify-between zc-gap-3">
				<div>
					<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Reports</h1>
					<p className="zc-text-sm zc-text-zinc-500">
						New members, points activity, and voucher claims over time.
					</p>
				</div>
				<DateRangePicker value={range} onChange={setRange} />
			</header>

			{error ? (
				<div className="zc-rounded-lg zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-4 zc-text-sm zc-text-rose-800">
					{error.message || "Could not load reports."}
				</div>
			) : !ready ? (
				<ReportsSkeleton />
			) : (
				<Suspense fallback={<ReportsSkeleton />}>
					<Charts
						membersSeries={members.data.series}
						pointsSeries={points.data.series}
						vouchersSeries={vouchers.data.series}
					/>
				</Suspense>
			)}
		</div>
	);
}
