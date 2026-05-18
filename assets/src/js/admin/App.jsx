import { Suspense, lazy } from "react";
import MembersPanel  from "./members/MembersPanel.jsx";
import PointsPanel   from "./points/PointsPanel.jsx";
import TiersPanel    from "./tiers/TiersPanel.jsx";
import VouchersPanel from "./vouchers/VouchersPanel.jsx";
// Users panel — eager. We tried lazy + Suspense; React 18 raced the
// fallback-to-content commit against the panel's first /admin/users
// useApiQuery render, leaving the fallback div detached when React's
// commit phase tried to remove it (DOMException: removeChild — "node
// is not a child of this node"). Users has no heavy deps so eager
// imports cost ~nothing in admin.js size.
import UsersPanel from "./users/UsersPanel.jsx";

// Reports lazy-loads Recharts (~95KB gzipped) — keep it out of the base
// admin bundle. Per perf rule: chart lib only loads when this panel mounts.
const ReportsPanel = lazy(() => import("./reports/ReportsPanel.jsx"));

// Audit imports the Reports DateRangePicker (which pulls react-day-picker).
// Lazy-load so a tab the admin rarely opens doesn't cost everyone the
// calendar bundle.
const AuditPanel = lazy(() => import("./audit/AuditPanel.jsx"));

// Settings panel — small, infrequent visits; lazy-load consistent with the others.
const SettingsPanel = lazy(() => import("./settings/SettingsPanel.jsx"));

// Onboarding panel — only shown on first activation + via the Settings
// re-access link. Lazy-load so it's not in the everyday bundle.
const OnboardingPanel = lazy(() => import("./onboarding/OnboardingPanel.jsx"));

export default function App({ panel }) {
	if (panel === "members")  return <MembersPanel />;
	if (panel === "tiers")    return <TiersPanel />;
	if (panel === "points")   return <PointsPanel />;
	if (panel === "vouchers") return <VouchersPanel />;
	if (panel === "users")    return <UsersPanel />;
	if (panel === "reports")  return (
		<Suspense fallback={<div className="zc-p-6 zc-text-sm zc-text-zinc-500">Loading reports…</div>}>
			<ReportsPanel />
		</Suspense>
	);
	if (panel === "audit")    return (
		<Suspense fallback={<div className="zc-p-6 zc-text-sm zc-text-zinc-500">Loading audit log…</div>}>
			<AuditPanel />
		</Suspense>
	);
	if (panel === "settings") return (
		<Suspense fallback={<div className="zc-p-6 zc-text-sm zc-text-zinc-500">Loading settings…</div>}>
			<SettingsPanel />
		</Suspense>
	);
	if (panel === "onboarding") return (
		<Suspense fallback={<div className="zc-p-6 zc-text-sm zc-text-zinc-500">Loading setup guide…</div>}>
			<OnboardingPanel />
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
