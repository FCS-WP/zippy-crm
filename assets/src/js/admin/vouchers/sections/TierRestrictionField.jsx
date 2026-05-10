import { useApiQuery } from "@/js/shared/hooks/useApi.js";

/**
 * Allowed-tier picker. Multi-select chips for membership tier slugs.
 *
 * Pulls the live tier list from /admin/tiers — admins can rename/add/remove
 * tiers in the Tiers panel and we want this picker to reflect that without
 * a hardcoded list.
 *
 * Storage shape (in voucher.allowed_tiers JSON column):
 *   array of slug strings, e.g. ["gold", "vip"]
 *
 * Empty list = the validator rejects the create — see VoucherService::validate_payload
 * (mode='tier' requires at least one slug).
 */
export function TierRestrictionField({ value, onChange }) {
	const { data, isLoading, error } = useApiQuery("/admin/tiers");
	const items = data?.items ?? [];
	const selected = new Set(Array.isArray(value) ? value : []);

	const toggle = (slug) => {
		const next = new Set(selected);
		if (next.has(slug)) {
			next.delete(slug);
		} else {
			next.add(slug);
		}
		onChange(Array.from(next));
	};

	if (isLoading) {
		return (
			<div className="zc-rounded-md zc-border zc-border-zinc-300 zc-bg-zinc-50 zc-px-3 zc-py-2 zc-text-sm zc-text-zinc-500">
				Loading tiers…
			</div>
		);
	}
	if (error) {
		return (
			<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-700">
				Could not load tiers: {error.message}
			</div>
		);
	}
	if (items.length === 0) {
		return (
			<div className="zc-rounded-md zc-border zc-border-zinc-300 zc-bg-zinc-50 zc-px-3 zc-py-2 zc-text-sm zc-text-zinc-500">
				No tiers defined yet — create some in the Tiers panel first.
			</div>
		);
	}

	return (
		<div className="zc-space-y-2">
			<div className="zc-flex zc-flex-wrap zc-gap-2">
				{items.map((tier) => {
					const isSelected = selected.has(tier.slug);
					return (
						<button
							key={tier.slug}
							type="button"
							onClick={() => toggle(tier.slug)}
							className={[
								"zc-inline-flex zc-items-center zc-gap-1.5 zc-rounded-full zc-border zc-px-3 zc-py-1 zc-text-xs zc-font-medium zc-transition",
								isSelected
									? "zc-border-zinc-900 zc-bg-zinc-900 zc-text-white"
									: "zc-border-zinc-300 zc-bg-white zc-text-zinc-700 hover:zc-bg-zinc-50",
							].join(" ")}
						>
							{isSelected ? <CheckIcon /> : null}
							<span>{tier.label}</span>
							<span className={`zc-text-[10px] ${isSelected ? "zc-text-zinc-300" : "zc-text-zinc-400"}`}>
								{tier.member_count} member{tier.member_count === 1 ? "" : "s"}
							</span>
						</button>
					);
				})}
			</div>
			<p className="zc-text-xs zc-text-zinc-500">
				{selected.size === 0
					? "Pick at least one tier — only members in these tiers will see and be able to claim this voucher."
					: `${selected.size} tier${selected.size === 1 ? "" : "s"} selected — anyone in these tiers can claim.`}
			</p>
		</div>
	);
}

function CheckIcon() {
	return (
		<svg viewBox="0 0 16 16" className="zc-size-3" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
			<path strokeLinecap="round" strokeLinejoin="round" d="M3.5 8.5l3 3 6-6" />
		</svg>
	);
}
