import { StepShell } from "../StepShell.jsx";

/**
 * Step 3 — Points explainer. Two halves: earn rate (per-tier, configured in
 * Tiers admin) and redemption (cash tender at checkout, configured in
 * Settings). Light copy + two CTAs to the actual screens.
 */
export function PointsStep({ step, total, onBack, onNext, onSkip }) {
	return (
		<StepShell
			step={step}
			total={total}
			title="Points"
			subtitle="Customers earn points on completed orders and apply them as cash at checkout."
			onBack={onBack}
			onNext={onNext}
			onSkip={onSkip}
		>
			<div className="zc-space-y-6">
				<HalfCard
					emoji="📈"
					heading="Earning"
					body="Each tier sets its own earn rate (points per $1 of order subtotal). VIPs typically earn more than Free members. New tiers default to 0 — you must opt them in to awarding points."
					cta={{ label: "Configure earn rates →", href: "admin.php?page=zippy-crm-tiers" }}
				/>

				<HalfCard
					emoji="💸"
					heading="Redeeming"
					body="At checkout, logged-in customers see a 'Use your points' widget that applies points as cash against the order total. The default rate is 20 points = $1 (configurable). Points are debited when the order completes."
					cta={{ label: "Configure exclusions →", href: "admin.php?page=zippy-crm-settings" }}
				/>

				<section className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-4 zc-text-xs zc-text-zinc-600">
					<p className="zc-font-medium zc-text-zinc-700">Heads up — exclusions</p>
					<p className="zc-mt-1">
						You can blacklist specific products or categories from earning points (gift cards, deposits, etc.).
						Configure these on the Settings page.
					</p>
				</section>
			</div>
		</StepShell>
	);
}

function HalfCard({ emoji, heading, body, cta }) {
	return (
		<section className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-p-5">
			<div className="zc-flex zc-items-start zc-gap-4">
				<span className="zc-text-2xl" aria-hidden>{emoji}</span>
				<div className="zc-flex-1">
					<h2 className="zc-text-base zc-font-semibold zc-text-zinc-900">{heading}</h2>
					<p className="zc-mt-1 zc-text-sm zc-text-zinc-600">{body}</p>
					{cta ? (
						<a
							href={cta.href}
							className="zc-mt-3 zc-inline-flex zc-items-center zc-gap-1.5 zc-text-sm zc-font-medium zc-text-zinc-900 hover:zc-underline"
						>
							{cta.label}
						</a>
					) : null}
				</div>
			</div>
		</section>
	);
}
