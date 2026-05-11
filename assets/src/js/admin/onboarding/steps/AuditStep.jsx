import { StepShell } from "../StepShell.jsx";

/**
 * Step 6 — Audit log mention. Quick read-only page so admins know the
 * audit log exists for compliance / debugging. No CTA pressure — most
 * admins won't open it until something goes wrong.
 */
export function AuditStep({ step, total, onBack, onNext, onSkip }) {
	return (
		<StepShell
			step={step}
			total={total}
			title="Audit log"
			subtitle="Every admin write action is logged. Useful when things go wrong, or for compliance."
			onBack={onBack}
			onNext={onNext}
			onSkip={onSkip}
		>
			<div className="zc-space-y-6">
				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						What's logged
					</h2>
					<ul className="zc-mt-3 zc-space-y-2 zc-text-sm zc-text-zinc-700">
						<li>• Voucher create / update / publish / pause / delete / duplicate</li>
						<li>• Member tier and status changes (incl. manual admin assignments)</li>
						<li>• Manual points adjustments (credit, debit, reason text)</li>
						<li>• Each row stores: who, what, when, before → after diff</li>
					</ul>
				</section>

				<section className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-4 zc-text-xs zc-text-zinc-600">
					<p>
						You probably won't visit this page often. Bookmark it: <strong>Zippy CRM → Audit log</strong>.
						Filter by user, action type, or date range to find what happened.
					</p>
				</section>

				<a
					href="admin.php?page=zippy-crm-audit"
					className="zc-inline-flex zc-items-center zc-gap-1.5 zc-text-sm zc-font-medium zc-text-zinc-900 hover:zc-underline"
				>
					Open Audit log →
				</a>
			</div>
		</StepShell>
	);
}
