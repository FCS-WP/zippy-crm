/**
 * Underlined tab strip used by VoucherForm. Pure layout — caller owns
 * which-tab-is-active state and renders its own panel below.
 *
 * Tabs: [{ key: "general", label: "General", count?: number, tone?: "muted"|"danger" }]
 *
 * Tone:
 *   - "muted"  (default): neutral grey count chip (e.g. "3 selected items")
 *   - "danger": rose-tinted chip for missing-required indicators
 */
const COUNT_TONES = {
	muted:  "zc-bg-zinc-100 zc-text-zinc-700",
	danger: "zc-bg-rose-100 zc-text-rose-700",
};

export function Tabs({ tabs, value, onChange }) {
	return (
		<div role="tablist" className="zc-flex zc-flex-wrap zc-border-b zc-border-zinc-200">
			{tabs.map((t) => {
				const active = value === t.key;
				const tone   = COUNT_TONES[t.tone] ?? COUNT_TONES.muted;
				return (
					<button
						key={t.key}
						type="button"
						role="tab"
						aria-selected={active}
						onClick={() => onChange(t.key)}
						className={[
							"zc-relative -zc-mb-px zc-flex zc-items-center zc-gap-1.5 zc-px-3 zc-py-2.5 zc-text-sm zc-font-medium zc-transition-colors",
							active
								? "zc-border-b-2 zc-border-zinc-900 zc-text-zinc-900"
								: "zc-border-b-2 zc-border-transparent zc-text-zinc-500 hover:zc-text-zinc-900",
						].join(" ")}
					>
						<span>{t.label}</span>
						{Number.isInteger(t.count) && t.count > 0 ? (
							<span className={`zc-rounded-full zc-px-1.5 zc-py-0.5 zc-text-[10px] zc-font-semibold ${tone}`}>
								{t.count}
							</span>
						) : null}
					</button>
				);
			})}
		</div>
	);
}
