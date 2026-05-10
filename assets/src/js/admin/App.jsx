import { Suspense, lazy } from "react";
import MembersPanel  from "./members/MembersPanel.jsx";
import PointsPanel   from "./points/PointsPanel.jsx";
import TiersPanel    from "./tiers/TiersPanel.jsx";
import VouchersPanel from "./vouchers/VouchersPanel.jsx";

// Reports lazy-loads Recharts (~95KB gzipped) — keep it out of the base
// admin bundle. Per perf rule: chart lib only loads when this panel mounts.
const ReportsPanel = lazy(() => import("./reports/ReportsPanel.jsx"));

export default function App({ panel }) {
	if (panel === "members")  return <MembersPanel />;
	if (panel === "tiers")    return <TiersPanel />;
	if (panel === "points")   return <PointsPanel />;
	if (panel === "vouchers") return <VouchersPanel />;
	if (panel === "reports")  return (
		<Suspense fallback={<div className="zc-p-6 zc-text-sm zc-text-zinc-500">Loading reports…</div>}>
			<ReportsPanel />
		</Suspense>
	);

	return (
		<div className="zc-p-6">
			<h1 className="zc-text-2xl zc-font-semibold">Zippy CRM — {panel}</h1>
			<p className="zc-mt-2 zc-text-sm zc-text-zinc-500">
				This panel hasn't been built yet. See <code>docs/TODO.md</code> §5.
			</p>
		</div>
	);
}
