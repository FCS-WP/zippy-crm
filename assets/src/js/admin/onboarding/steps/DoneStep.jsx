import { StepShell } from "../StepShell.jsx";

/**
 * Step 7 — Wrap-up. The "Next" button is replaced with a Finish CTA that
 * marks the guide dismissed and lands the admin on the Members panel.
 * Skip is hidden — there's nothing left to skip at this point.
 *
 * `onFinish` is the orchestrator's dismiss-and-redirect callback (PUT
 * dismissed=true → window.location to Members).
 */
export function DoneStep({ step, total, onBack, onFinish }) {
	return (
		<StepShell
			step={step}
			total={total}
			title="You're ready"
			subtitle="Zippy CRM is live. Here's where you might want to go next."
			onBack={onBack}
			onNext={onFinish}
			onSkip={null}
			nextLabel="Finish & go to Members"
		>
			<div className="zc-space-y-6">
				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						What's working now
					</h2>
					<ul className="zc-mt-3 zc-space-y-2 zc-text-sm zc-text-zinc-700">
						<li>✓ Tier ladder seeded — customers auto-promote as they spend</li>
						<li>✓ Points engine listening on order completion (and processing)</li>
						<li>✓ Voucher claim flow live at <strong>My Account → Vouchers</strong></li>
						<li>✓ Notification opt-in on registration; emails fire on voucher publish</li>
						<li>✓ Audit log capturing every admin write</li>
					</ul>
				</section>

				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						Common next moves
					</h2>
					<ul className="zc-mt-3 zc-space-y-2 zc-text-sm zc-text-zinc-700">
						<li>
							<a href="admin.php?page=zippy-crm-vouchers" className="zc-text-zinc-900 hover:zc-underline">
								Create your first voucher →
							</a> {" "}
							<span className="zc-text-zinc-500">— a welcome offer is a good first promo</span>
						</li>
						<li>
							<a href="admin.php?page=zippy-crm-tiers" className="zc-text-zinc-900 hover:zc-underline">
								Tweak tier thresholds →
							</a> {" "}
							<span className="zc-text-zinc-500">— match your store's typical order patterns</span>
						</li>
						<li>
							<a href="admin.php?page=zippy-crm-reports" className="zc-text-zinc-900 hover:zc-underline">
								Reports →
							</a> {" "}
							<span className="zc-text-zinc-500">— check back in a week to see early signal</span>
						</li>
					</ul>
				</section>

				<section className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-4 zc-text-xs zc-text-zinc-600">
					<p>
						Need to revisit this guide? <strong>Zippy CRM → Settings</strong> has a "View setup guide"
						link at the top. You won't see it auto-pop-up again — that only fires once on first activation.
					</p>
				</section>
			</div>
		</StepShell>
	);
}
