import { useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { Input } from "@/js/shared/ui/input.jsx";
import { number } from "@/js/shared/utils/format.js";

export function PointsAdjustForm({ row, onClose }) {
	const [type, setType]     = useState("credit");
	const [amount, setAmount] = useState("");
	const [reason, setReason] = useState("");
	const [error, setError]   = useState(null);

	const adjust = useApiMutation("post", `/admin/members/${row.user_id}/points`, {
		invalidate: ["/admin/members"],
	});

	const onSubmit = (e) => {
		e.preventDefault();
		setError(null);

		const n = Math.abs(parseInt(amount, 10) || 0);
		if (n <= 0) { setError("Enter a positive amount."); return; }
		if (!reason.trim()) { setError("Reason is required."); return; }

		const delta = type === "credit" ? n : -n;
		adjust.mutate(
			{ delta, reason: reason.trim() },
			{
				onSuccess: () => onClose(),
				onError:   (err) => setError(err?.message || "Could not apply adjustment."),
			},
		);
	};

	const preview = (() => {
		const n = Math.abs(parseInt(amount, 10) || 0);
		if (n <= 0) return null;
		const delta = type === "credit" ? n : -n;
		const newBal = row.points_balance + delta;
		return { delta, newBal };
	})();

	return (
		<form onSubmit={onSubmit} className="zc-space-y-4">
			{error ? (
				<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-800">
					{error}
				</div>
			) : null}

			<div className="zc-rounded-md zc-bg-zinc-50 zc-px-3 zc-py-2 zc-text-sm">
				<div className="zc-text-zinc-500">Current balance</div>
				<div className="zc-text-lg zc-font-semibold zc-text-zinc-900">{number(row.points_balance)} pts</div>
			</div>

			<div className="zc-grid zc-grid-cols-2 zc-gap-2">
				<TypeButton current={type} value="credit" label="Credit (+)" onSelect={setType} />
				<TypeButton current={type} value="debit"  label="Debit (-)"  onSelect={setType} />
			</div>

			<label className="zc-block zc-space-y-1">
				<span className="zc-text-sm zc-font-medium zc-text-zinc-800">Amount</span>
				<Input
					type="number"
					step="1"
					min="1"
					placeholder="100"
					value={amount}
					onChange={(e) => setAmount(e.target.value)}
					required
				/>
			</label>

			<label className="zc-block zc-space-y-1">
				<span className="zc-text-sm zc-font-medium zc-text-zinc-800">Reason</span>
				<textarea
					rows={2}
					value={reason}
					onChange={(e) => setReason(e.target.value)}
					required
					className="zc-w-full zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-3 zc-py-2 zc-text-sm zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-2 zc-ring-zinc-200"
					placeholder="Goodwill credit, refund correction, etc."
				/>
				<span className="zc-block zc-text-xs zc-text-zinc-500">
					Logged as <code className="zc-text-zinc-700">Admin #ID: &lt;reason&gt;</code> in the ledger.
				</span>
			</label>

			{preview ? (
				<div className="zc-rounded-md zc-border zc-border-zinc-200 zc-bg-white zc-px-3 zc-py-2 zc-text-sm">
					<span className="zc-text-zinc-500">After:</span>{" "}
					<strong className={preview.delta > 0 ? "zc-text-emerald-700" : "zc-text-rose-700"}>
						{preview.delta > 0 ? "+" : ""}{preview.delta}
					</strong>{" "}
					<span className="zc-text-zinc-500">→</span>{" "}
					<strong className="zc-text-zinc-900">{number(preview.newBal)} pts</strong>
				</div>
			) : null}

			<div className="zc-flex zc-justify-end zc-gap-2 zc-border-t zc-border-zinc-200 zc-pt-4">
				<Button type="button" variant="ghost" onClick={onClose} disabled={adjust.isPending}>
					Cancel
				</Button>
				<Button type="submit" loading={adjust.isPending}>
					Apply adjustment
				</Button>
			</div>
		</form>
	);
}

function TypeButton({ current, value, label, onSelect }) {
	const active = current === value;
	return (
		<button
			type="button"
			onClick={() => onSelect(value)}
			className={[
				"zc-rounded-md zc-border zc-px-3 zc-py-2 zc-text-sm zc-font-medium zc-transition-colors",
				active
					? value === "credit"
						? "zc-border-emerald-500 zc-bg-emerald-50 zc-text-emerald-800"
						: "zc-border-rose-500 zc-bg-rose-50 zc-text-rose-800"
					: "zc-border-zinc-200 zc-bg-white zc-text-zinc-700 hover:zc-bg-zinc-50",
			].join(" ")}
		>
			{label}
		</button>
	);
}
