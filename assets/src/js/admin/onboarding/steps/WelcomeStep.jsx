import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { StepShell } from "../StepShell.jsx";

/**
 * Step 1 — Welcome screen + prerequisite check.
 *
 * Hard-gates on WC being active (Next button disabled if it's not).
 * The other two prereqs (HPOS, customer accounts) are surfaced as
 * warnings — the plugin still works without them, just degrades.
 */
export function WelcomeStep({ step, total, onNext, onSkip }) {
	const prereqs = useApiQuery("/admin/onboarding/prereqs");

	const data = prereqs.data ?? { wc_active: true, hpos_enabled: true, customer_accounts: true };
	const blocking = !data.wc_active;

	return (
		<StepShell
			step={step}
			total={total}
			title="Welcome to Zippy CRM"
			subtitle="Membership tiers, points, vouchers, and customer notifications — built on top of WooCommerce."
			onBack={null}
			onNext={blocking ? null : onNext}
			onSkip={onSkip}
			nextDisabled={blocking}
		>
			<div className="zc-space-y-6">
				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						What you'll set up
					</h2>
					<ul className="zc-mt-3 zc-space-y-2 zc-text-sm zc-text-zinc-700">
						<FeatureLine emoji="🪜">
							<strong>Tiers</strong> — membership ladder (Free → Silver → Gold → VIP) with custom earn rates per tier
						</FeatureLine>
						<FeatureLine emoji="✨">
							<strong>Points</strong> — earned on purchases, applied as cash tender at checkout
						</FeatureLine>
						<FeatureLine emoji="🎟️">
							<strong>Vouchers</strong> — single-code or multi-code campaigns, targeted by customer or tier
						</FeatureLine>
						<FeatureLine emoji="📬">
							<strong>Notifications</strong> — opt-in voucher emails when new offers go live
						</FeatureLine>
					</ul>
				</section>

				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						System check
					</h2>
					<div className="zc-mt-3 zc-space-y-2">
						{prereqs.isLoading ? (
							<>
								<Skeleton className="zc-h-8 zc-w-full" />
								<Skeleton className="zc-h-8 zc-w-full" />
								<Skeleton className="zc-h-8 zc-w-full" />
							</>
						) : (
							<>
								<PrereqRow
									ok={data.wc_active}
									label="WooCommerce is active"
									failNote="Activate the WooCommerce plugin before continuing — Zippy CRM is built on top of it."
									blocking
								/>
								<PrereqRow
									ok={data.hpos_enabled}
									label="High-Performance Order Storage (HPOS) is enabled"
									failNote="Enable in WooCommerce → Settings → Advanced → Features. Without HPOS, order-related features may not work."
								/>
								<PrereqRow
									ok={data.customer_accounts}
									label="Customer accounts allowed at checkout"
									failNote="Enable in WooCommerce → Settings → Accounts & Privacy. Loyalty needs identifiable customers."
								/>
							</>
						)}
					</div>
				</section>
			</div>
		</StepShell>
	);
}

function FeatureLine({ emoji, children }) {
	return (
		<li className="zc-flex zc-items-start zc-gap-3">
			<span className="zc-text-base" aria-hidden>{emoji}</span>
			<span>{children}</span>
		</li>
	);
}

function PrereqRow({ ok, label, failNote, blocking }) {
	return (
		<div
			className={[
				"zc-flex zc-items-start zc-gap-3 zc-rounded-md zc-border zc-px-3 zc-py-2 zc-text-sm",
				ok        ? "zc-border-emerald-200 zc-bg-emerald-50" :
				blocking  ? "zc-border-rose-200 zc-bg-rose-50" :
				            "zc-border-amber-200 zc-bg-amber-50",
			].join(" ")}
		>
			<span
				className={[
					"zc-mt-0.5 zc-flex zc-h-5 zc-w-5 zc-shrink-0 zc-items-center zc-justify-center zc-rounded-full zc-text-xs zc-font-bold",
					ok        ? "zc-bg-emerald-500 zc-text-white" :
					blocking  ? "zc-bg-rose-500 zc-text-white"    :
					            "zc-bg-amber-500 zc-text-white",
				].join(" ")}
				aria-hidden
			>
				{ok ? "✓" : "!"}
			</span>
			<div>
				<p className={[
					"zc-font-medium",
					ok ? "zc-text-emerald-900" : blocking ? "zc-text-rose-900" : "zc-text-amber-900",
				].join(" ")}>
					{label}
				</p>
				{!ok ? (
					<p className={[
						"zc-mt-0.5 zc-text-xs",
						blocking ? "zc-text-rose-800" : "zc-text-amber-800",
					].join(" ")}>
						{failNote}
					</p>
				) : null}
			</div>
		</div>
	);
}
