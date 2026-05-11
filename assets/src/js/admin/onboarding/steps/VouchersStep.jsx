import { StepShell } from "../StepShell.jsx";

/**
 * Step 4 — Voucher explainer. Three concepts on one page: distribution
 * mode (single vs multi-code), audience targeting (public / customer /
 * tier), then a CTA to create the first voucher.
 */
export function VouchersStep({ step, total, onBack, onNext, onSkip }) {
	return (
		<StepShell
			step={step}
			total={total}
			title="Vouchers"
			subtitle="Promotional discounts customers can claim on their My Account page and apply at checkout."
			onBack={onBack}
			onNext={onNext}
			onSkip={onSkip}
		>
			<div className="zc-space-y-6">
				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						Two distribution modes
					</h2>
					<div className="zc-mt-3 zc-grid zc-gap-3 sm:zc-grid-cols-2">
						<ModeCard
							pill={{ label: "Single code", classes: "zc-bg-zinc-100 zc-text-zinc-800" }}
							title="One shared code"
							body="Same code (e.g. SUMMER25) for everyone. Capped by Max uses. Best for broad promos."
						/>
						<ModeCard
							pill={{ label: "Multi-code", classes: "zc-bg-violet-100 zc-text-violet-800" }}
							title="Unique code per claimer"
							body="N unique codes minted up front. Each customer gets their own, single-use code. Best for limited giveaways or referral-style campaigns."
						/>
					</div>
				</section>

				<section>
					<h2 className="zc-text-sm zc-font-semibold zc-uppercase zc-tracking-wider zc-text-zinc-500">
						Three audience modes
					</h2>
					<ul className="zc-mt-3 zc-space-y-2 zc-text-sm zc-text-zinc-700">
						<AudienceLine label="Public" body="Anyone can claim. Most common." />
						<AudienceLine
							label="Specific customers"
							body="Restrict to a hand-picked list of emails or registered users."
							pillClasses="zc-bg-sky-100 zc-text-sky-800"
						/>
						<AudienceLine
							label="Membership tiers"
							body="Restrict to one or more tiers (e.g. Gold + VIP). Tier-restricted vouchers auto-revoke if a customer's tier drops below the requirement."
							pillClasses="zc-bg-amber-100 zc-text-amber-800"
						/>
					</ul>
				</section>

				<section className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-p-5">
					<h2 className="zc-text-base zc-font-semibold zc-text-zinc-900">Ready to publish your first voucher?</h2>
					<p className="zc-mt-1 zc-text-sm zc-text-zinc-600">
						Drafts can be edited freely. Once you publish, the matching WooCommerce coupon is created and customers can claim.
					</p>
					<a
						href="admin.php?page=zippy-crm-vouchers"
						className="zc-mt-3 zc-inline-flex zc-items-center zc-gap-1.5 zc-rounded-md zc-bg-zinc-900 zc-px-4 zc-py-2 zc-text-sm zc-font-medium zc-text-white hover:zc-bg-zinc-800"
					>
						Create a voucher →
					</a>
				</section>
			</div>
		</StepShell>
	);
}

function ModeCard({ pill, title, body }) {
	return (
		<div className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-p-4">
			<span className={`zc-inline-flex zc-rounded-full zc-px-2 zc-py-0.5 zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wide ${pill.classes}`}>
				{pill.label}
			</span>
			<h3 className="zc-mt-2 zc-text-sm zc-font-semibold zc-text-zinc-900">{title}</h3>
			<p className="zc-mt-1 zc-text-xs zc-text-zinc-600">{body}</p>
		</div>
	);
}

function AudienceLine({ label, body, pillClasses = "zc-bg-zinc-100 zc-text-zinc-700" }) {
	return (
		<li className="zc-flex zc-items-start zc-gap-3">
			<span className={`zc-mt-0.5 zc-inline-flex zc-shrink-0 zc-rounded zc-px-1.5 zc-py-0.5 zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wide ${pillClasses}`}>
				{label}
			</span>
			<span className="zc-text-zinc-700">{body}</span>
		</li>
	);
}
