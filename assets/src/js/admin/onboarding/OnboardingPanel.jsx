import { useState, useEffect } from "react";
import { useApiQuery, useApiMutation } from "@/js/shared/hooks/useApi.js";

import { WelcomeStep }       from "./steps/WelcomeStep.jsx";
import { TiersStep }         from "./steps/TiersStep.jsx";
import { PointsStep }        from "./steps/PointsStep.jsx";
import { VouchersStep }      from "./steps/VouchersStep.jsx";
import { NotificationsStep } from "./steps/NotificationsStep.jsx";
import { AuditStep }         from "./steps/AuditStep.jsx";
import { DoneStep }          from "./steps/DoneStep.jsx";

/**
 * First-run onboarding orchestrator. Owns:
 *   - Current step (mirrored to user_meta via PUT /admin/onboarding/state
 *     so the admin can leave and come back)
 *   - Navigation (next/back/skip)
 *   - Step → component routing
 *   - Dismiss-and-exit flow
 *
 * Each step is a self-contained component that takes `onNext` / `onSkip`
 * and renders its own body via StepShell.
 *
 * Why one orchestrator vs a state library: 7 steps, linear nav, single
 * piece of server state. Local React state is plenty.
 */

const TOTAL_STEPS = 7;

// Step number → component. Index here matches `step` value (1-based on the
// server; 0-based array index here for convenience).
const STEPS = [
	null,              // 0 unused — server uses 1-based steps
	WelcomeStep,       // 1
	TiersStep,         // 2
	PointsStep,        // 3
	VouchersStep,      // 4
	NotificationsStep, // 5
	AuditStep,         // 6
	DoneStep,          // 7
];

export default function OnboardingPanel() {
	const state = useApiQuery("/admin/onboarding/state");

	// Local mirror so step transitions feel instant; server is the persisted
	// source of truth but we don't wait for the round-trip to render.
	const [step, setStep] = useState(1);

	// One-shot "I just clicked View setup guide from Settings" flag. When set,
	// we render the flow even if dismissed=true (admin explicitly chose to
	// revisit). We do NOT clear the server-side dismissed flag — auto-redirect
	// protection stays armed; only this explicit click re-enters the tour.
	const [forceShow, setForceShow] = useState(false);

	useEffect(() => {
		if (state.data?.step) setStep(state.data.step);
	}, [state.data?.step]);

	const update = useApiMutation("put", "/admin/onboarding/state", {
		invalidate: ["/admin/onboarding/state"],
	});

	// Handle the ?revisit=1 URL flag (from the Settings panel's "View setup
	// guide" link). One-shot on mount: PUT step=1, drop the query param so
	// reload doesn't replay, set forceShow so the dismissed-notice branch
	// is bypassed for this session.
	useEffect(() => {
		if (typeof window === "undefined") return;
		const params = new URLSearchParams(window.location.search);
		if (params.get("revisit") !== "1") return;

		// Strip the param from the URL without triggering a navigation.
		params.delete("revisit");
		const cleaned = params.toString();
		const newUrl = window.location.pathname + (cleaned ? `?${cleaned}` : "") + window.location.hash;
		window.history.replaceState({}, "", newUrl);

		setStep(1);
		setForceShow(true);
		update.mutate({ step: 1 });
		// `update` is stable via useApiMutation; no exhaustive-deps churn.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	if (state.isLoading) {
		return <div className="zc-p-6 zc-text-sm zc-text-zinc-500">Loading…</div>;
	}
	if (state.isError) {
		return (
			<div className="zc-p-6">
				<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-3 zc-text-sm zc-text-rose-700">
					Could not load onboarding state: {state.error?.message}
				</div>
			</div>
		);
	}

	// Already dismissed: show a small "you've completed this" card instead
	// of restarting the flow. Admins reach this state via the Done step or
	// any "Skip for now" click. Re-entering still works (the page exists)
	// but it makes clear they don't have to redo it.
	//
	// Bypassed when forceShow is set — that's the ?revisit=1 path from the
	// Settings panel's "View setup guide" link. Admin made an explicit
	// choice to re-take the tour; respect it.
	if (state.data?.dismissed && ! forceShow) {
		return <DismissedNotice currentStep={state.data.step} onResume={(n) => { setStep(n); setForceShow(true); }} />;
	}

	const goTo = (next) => {
		const clamped = Math.max(1, Math.min(TOTAL_STEPS, next));
		setStep(clamped);
		update.mutate({ step: clamped });
	};

	const dismissAndExit = () => {
		update.mutate({ dismissed: true }, {
			onSuccess: () => {
				// Land on the Members panel — that's the most useful "what next"
				// after dismissing the guide.
				const url = (window.zippyCrm && window.zippyCrm.membersUrl) || "admin.php?page=zippy-crm";
				window.location.href = url;
			},
		});
	};

	const StepComponent = STEPS[step] ?? WelcomeStep;

	return (
		<StepComponent
			step={step}
			total={TOTAL_STEPS}
			onBack={step > 1 ? () => goTo(step - 1) : null}
			onNext={() => goTo(step + 1)}
			onSkip={dismissAndExit}
			onFinish={dismissAndExit}
		/>
	);
}

function DismissedNotice({ currentStep, onResume }) {
	return (
		<div className="zc-min-h-screen zc-bg-zinc-50">
			<div className="zc-mx-auto zc-max-w-2xl zc-px-6 zc-py-16 zc-text-center">
				<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">
					Setup complete
				</h1>
				<p className="zc-mt-2 zc-text-sm zc-text-zinc-500">
					You've already finished (or skipped) the setup guide. You can revisit
					any step below or head back to the dashboard.
				</p>
				<div className="zc-mt-6 zc-flex zc-justify-center zc-gap-3">
					<button
						type="button"
						onClick={() => onResume(currentStep)}
						className="zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-4 zc-py-2 zc-text-sm zc-font-medium zc-text-zinc-700 hover:zc-bg-zinc-50"
					>
						Revisit step {currentStep}
					</button>
					<a
						href="admin.php?page=zippy-crm"
						className="zc-rounded-md zc-bg-zinc-900 zc-px-4 zc-py-2 zc-text-sm zc-font-medium zc-text-white hover:zc-bg-zinc-800"
					>
						Go to Members
					</a>
				</div>
			</div>
		</div>
	);
}
