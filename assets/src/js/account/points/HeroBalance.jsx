import { money, number } from "@/js/shared/utils/format.js";

/**
 * Loyalty-card hero. Owns the visual identity of the Points tab — the rest of
 * the page is supporting cast. Pulls multiplier from the membership query so
 * earn power is visible alongside the balance.
 */
export function HeroBalance({ summary, membership }) {
	const level = membership?.level ?? "free";
	const levelLabel = membership?.level_label ?? "Free";
	const multiplier = membership?.multiplier ?? 1;

	return (
		<section
			className={[
				"zc-relative zc-overflow-hidden zc-rounded-2xl zc-text-white",
				"zc-bg-gradient-to-br zc-from-zinc-900 zc-via-zinc-800 zc-to-zinc-900",
				"zc-shadow-[0_10px_40px_-12px_rgba(0,0,0,0.45)]",
			].join(" ")}
		>
			{/* decorative spotlight */}
			<div
				aria-hidden
				className="zc-pointer-events-none zc-absolute -zc-right-24 -zc-top-24 zc-size-72 zc-rounded-full zc-bg-amber-300/15 zc-blur-3xl"
			/>
			<div
				aria-hidden
				className="zc-pointer-events-none zc-absolute -zc-left-16 -zc-bottom-16 zc-size-56 zc-rounded-full zc-bg-sky-400/10 zc-blur-3xl"
			/>

			<div className="zc-relative zc-flex zc-flex-col zc-gap-6 zc-p-7 sm:zc-flex-row sm:zc-items-end sm:zc-justify-between">
				<div className="zc-space-y-3">
					<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-2">
						<TierBadge level={level} label={levelLabel} />
						<span className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-white/60">
							{multiplier}× earn rate
						</span>
					</div>
					<div>
						<p className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-white/60">Available to redeem</p>
						<p className="zc-mt-1 zc-text-5xl zc-font-bold zc-leading-none zc-tracking-tight">
							{number(summary.available)}
							<span className="zc-ml-2 zc-text-base zc-font-medium zc-text-white/70">pts</span>
						</p>
						<p className="zc-mt-2 zc-text-sm zc-text-white/80">
							Worth <span className="zc-font-semibold zc-text-white">{money(summary.available_dollar_value)}</span>
							{" "}at checkout · {summary.redemption_rate} pts = $1
						</p>
						{summary.reserved > 0 && (
							<p className="zc-mt-1 zc-text-xs zc-text-white/60">
								{number(summary.balance)} total · {number(summary.reserved)} reserved in pending coupons
							</p>
						)}
					</div>
				</div>

				<dl className="zc-grid zc-grid-cols-2 zc-gap-3 zc-text-sm sm:zc-w-auto">
					<MiniStat label="Earned"   value={number(summary.total_earned)} />
					<MiniStat label="Redeemed" value={number(summary.total_redeemed)} />
				</dl>
			</div>
		</section>
	);
}

function TierBadge({ level, label }) {
	// Each tier reads as its own little "card" inside the hero.
	const palette = {
		free:   "zc-bg-white/10 zc-text-white/90",
		silver: "zc-bg-zinc-200/90 zc-text-zinc-900",
		gold:   "zc-bg-amber-300/95 zc-text-amber-950",
		vip:    "zc-bg-fuchsia-200/95 zc-text-fuchsia-950",
	};
	return (
		<span
			className={`zc-inline-flex zc-items-center zc-gap-1.5 zc-rounded-full zc-px-2.5 zc-py-1 zc-text-xs zc-font-semibold zc-uppercase zc-tracking-wider ${palette[level] ?? palette.free}`}
		>
			<span className="zc-size-1.5 zc-rounded-full zc-bg-current zc-opacity-70" />
			{label}
		</span>
	);
}

function MiniStat({ label, value }) {
	return (
		<div className="zc-rounded-lg zc-bg-white/5 zc-px-3 zc-py-2 zc-backdrop-blur-sm zc-ring-1 zc-ring-inset zc-ring-white/10">
			<dt className="zc-text-[11px] zc-uppercase zc-tracking-wider zc-text-white/60">{label}</dt>
			<dd className="zc-mt-0.5 zc-text-base zc-font-semibold zc-text-white">{value}</dd>
		</div>
	);
}
