import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { StepShell } from "../StepShell.jsx";

/**
 * Step 2 — Tier ladder explainer. Shows the seeded tiers as a table so
 * admins see what they're starting with, then directs them to the Tiers
 * admin page to customise.
 */
export function TiersStep({ step, total, onBack, onNext, onSkip }) {
	const tiers = useApiQuery("/admin/tiers");
	const rows  = tiers.data?.items ?? [];

	return (
		<StepShell
			step={step}
			total={total}
			title="Membership tiers"
			subtitle="A ladder of customer levels. Each tier earns at a different rate and can unlock targeted vouchers."
			onBack={onBack}
			onNext={onNext}
			onSkip={onSkip}
		>
			<div className="zc-space-y-6">
				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						How tiers work
					</h2>
					<ul className="zc-mt-3 zc-space-y-2 zc-text-sm zc-text-zinc-700">
						<li>• Customers start at the lowest tier and auto-upgrade as they spend or place orders</li>
						<li>• Each tier sets its own <strong>earn rate</strong> (e.g. Gold earns 1.5× points)</li>
						<li>• You can mark tiers <strong>admin-only</strong> (like VIP) so the auto-evaluator never assigns them — manual gift</li>
						<li>• Vouchers can target specific tiers (e.g. "30% off, Gold + VIP only")</li>
					</ul>
				</section>

				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						Your starting tiers
					</h2>
					<p className="zc-mt-2 zc-text-xs zc-text-zinc-500">
						These were seeded on activation. Rename, add, or remove any of them in the Tiers admin.
					</p>

					{tiers.isLoading ? (
						<div className="zc-mt-3 zc-space-y-2">
							<Skeleton className="zc-h-10 zc-w-full" />
							<Skeleton className="zc-h-10 zc-w-full" />
							<Skeleton className="zc-h-10 zc-w-full" />
							<Skeleton className="zc-h-10 zc-w-full" />
						</div>
					) : (
						<div className="zc-mt-3 zc-overflow-hidden zc-rounded-lg zc-border zc-border-zinc-200">
							<table className="zc-w-full zc-text-sm">
								<thead className="zc-bg-zinc-50 zc-text-xs zc-uppercase zc-tracking-wider zc-text-zinc-500">
									<tr>
										<th className="zc-px-3 zc-py-2 zc-text-left">Tier</th>
										<th className="zc-px-3 zc-py-2 zc-text-right">Earn rate</th>
										<th className="zc-px-3 zc-py-2 zc-text-right">Orders ≥</th>
										<th className="zc-px-3 zc-py-2 zc-text-right">Spend ≥</th>
										<th className="zc-px-3 zc-py-2 zc-text-left">Assignment</th>
									</tr>
								</thead>
								<tbody className="zc-divide-y zc-divide-zinc-100 zc-bg-white">
									{rows.map((t) => (
										<tr key={t.slug}>
											<td className="zc-px-3 zc-py-2 zc-font-medium zc-text-zinc-900">{t.label}</td>
											<td className="zc-px-3 zc-py-2 zc-text-right zc-tabular-nums">
												{t.multiplier > 0 ? `${Number(t.multiplier).toFixed(2)} pt/$` : <span className="zc-text-zinc-400">No earn</span>}
											</td>
											<td className="zc-px-3 zc-py-2 zc-text-right zc-tabular-nums zc-text-zinc-600">
												{t.threshold_orders ?? "—"}
											</td>
											<td className="zc-px-3 zc-py-2 zc-text-right zc-tabular-nums zc-text-zinc-600">
												{t.threshold_spend !== null && t.threshold_spend !== undefined ? `$${t.threshold_spend}` : "—"}
											</td>
											<td className="zc-px-3 zc-py-2 zc-text-xs zc-text-zinc-500">
												{t.is_admin_only ? "Admin only" : "Auto-evaluated"}
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</div>
					)}
				</section>

				<a
					href="admin.php?page=zippy-crm-tiers"
					className="zc-inline-flex zc-items-center zc-gap-1.5 zc-text-sm zc-font-medium zc-text-zinc-900 hover:zc-underline"
				>
					Open Tiers admin →
				</a>
			</div>
		</StepShell>
	);
}
