import { useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";

const LEVELS = [
	{ key: "free",   label: "Free",   note: "1× points multiplier" },
	{ key: "silver", label: "Silver", note: "1.2× — auto-assigned at 5+ orders or $500+ spend" },
	{ key: "gold",   label: "Gold",   note: "1.5× — auto-assigned at 15+ orders or $2000+ spend" },
	{ key: "vip",    label: "VIP",    note: "2× — admin-assigned, sticky (auto-evaluator never removes)" },
];

export function LevelChangeForm({ row, onClose }) {
	const [next, setNext] = useState(row.level);
	const [error, setError] = useState(null);

	const setLevel = useApiMutation("post", `/admin/members/${row.user_id}/level`, {
		invalidate: ["/admin/members"],
	});

	const isVipChange = (row.level === "vip" && next !== "vip") || (row.level !== "vip" && next === "vip");

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
				{LEVELS.map((l) => {
					const checked = next === l.key;
					return (
						<label
							key={l.key}
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
								value={l.key}
								checked={checked}
								onChange={() => setNext(l.key)}
								className="zc-mt-0.5"
							/>
							<div className="zc-flex-1">
								<div className="zc-text-sm zc-font-medium zc-text-zinc-900">{l.label}</div>
								<div className="zc-text-xs zc-text-zinc-500">{l.note}</div>
							</div>
						</label>
					);
				})}
			</div>

			{isVipChange ? (
				<div className="zc-rounded-md zc-border zc-border-amber-200 zc-bg-amber-50 zc-px-3 zc-py-2 zc-text-sm zc-text-amber-900">
					{next === "vip"
						? "VIP is sticky — once set, the auto-evaluator will never downgrade them."
						: "Removing VIP — the auto-evaluator will start managing this member's tier again on the next completed order."}
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
