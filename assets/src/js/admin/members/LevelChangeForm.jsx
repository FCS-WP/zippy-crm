import { useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { useTiers } from "@/js/shared/hooks/useTiers.js";
import { Button } from "@/js/shared/ui/button.jsx";

/**
 * Build a one-line note describing the tier's auto-eval rule, so the
 * admin sees the consequence of picking each option.
 */
function noteFor(tier) {
	if (tier.is_admin_only) {
		return `${tier.multiplier}× — admin-assigned, sticky (auto-evaluator never removes)`;
	}
	const rules = [];
	if (tier.threshold_orders) rules.push(`${tier.threshold_orders}+ orders`);
	if (tier.threshold_spend)  rules.push(`$${tier.threshold_spend}+ spend`);
	const auto = rules.length ? `auto-assigned at ${rules.join(" or ")}` : "default tier";
	return `${tier.multiplier}× — ${auto}`;
}

export function LevelChangeForm({ row, onClose }) {
	const { tiers, findTier } = useTiers();
	const [next, setNext] = useState(row.level);
	const [error, setError] = useState(null);

	const setLevel = useApiMutation("post", `/admin/members/${row.user_id}/level`, {
		invalidate: ["/admin/members"],
	});

	const currentTier = findTier(row.level);
	const nextTier    = findTier(next);
	// Warn whenever the admin-only flag flips between current and next.
	const isStickyChange =
		currentTier && nextTier &&
		Boolean(currentTier.is_admin_only) !== Boolean(nextTier.is_admin_only);

	const onSubmit = (e) => {
		e.preventDefault();
		setError(null);
		setLevel.mutate({ level: next }, {
			onSuccess: () => onClose(),
			onError: (err) => setError(err?.message || "Could not change level."),
		});
	};

	return (
		<form onSubmit={onSubmit} className="zc-space-y-4">
			{error ? (
				<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-800">
					{error}
				</div>
			) : null}

			<p className="zc-text-sm zc-text-zinc-600">
				Current level: <strong className="zc-text-zinc-900">{row.level_label || row.level}</strong>
			</p>

			<div className="zc-space-y-2">
				{tiers.map((tier) => {
					const checked = next === tier.slug;
					return (
						<label
							key={tier.slug}
							className={[
								"zc-flex zc-cursor-pointer zc-items-start zc-gap-3 zc-rounded-md zc-border zc-p-3 zc-transition-colors",
								checked
									? "zc-border-zinc-900 zc-bg-zinc-50"
									: "zc-border-zinc-200 hover:zc-border-zinc-300",
							].join(" ")}
						>
							<input
								type="radio"
								name="level"
								value={tier.slug}
								checked={checked}
								onChange={() => setNext(tier.slug)}
								className="zc-mt-0.5"
							/>
							<div className="zc-flex-1">
								<div className="zc-text-sm zc-font-medium zc-text-zinc-900">
									{tier.label}
									{tier.is_admin_only ? (
										<span className="zc-ml-2 zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-[10px] zc-uppercase zc-tracking-wide zc-text-zinc-500">
											admin only
										</span>
									) : null}
								</div>
								<div className="zc-text-xs zc-text-zinc-500">{noteFor(tier)}</div>
							</div>
						</label>
					);
				})}
			</div>

			{isStickyChange ? (
				<div className="zc-rounded-md zc-border zc-border-amber-200 zc-bg-amber-50 zc-px-3 zc-py-2 zc-text-sm zc-text-amber-900">
					{nextTier.is_admin_only
						? `${nextTier.label} is sticky — once set, the auto-evaluator will never downgrade them.`
						: `Removing ${currentTier.label} — the auto-evaluator will start managing this member's tier again on the next completed order.`}
				</div>
			) : null}

			<div className="zc-flex zc-justify-end zc-gap-2 zc-border-t zc-border-zinc-200 zc-pt-4">
				<Button type="button" variant="ghost" onClick={onClose} disabled={setLevel.isPending}>
					Cancel
				</Button>
				<Button type="submit" loading={setLevel.isPending} disabled={next === row.level}>
					Save level
				</Button>
			</div>
		</form>
	);
}
