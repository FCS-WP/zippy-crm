import { useEffect, useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { Input } from "@/js/shared/ui/input.jsx";
import { DateTimeField } from "./DateTimeField.jsx";

const EMPTY = {
	code: "",
	title: "",
	description: "",
	discount_type: "percent",
	discount_value: "",
	min_order_amount: "",
	max_uses: "",
	starts_at: null,
	expires_at: null,
};

/**
 * Random voucher code: 4-letter readable prefix + 4 alphanumerics. Matches
 * the server-side regex (^[A-Z0-9_-]{3,50}$). 36^4 ≈ 1.7M suffix combos,
 * so collisions are rare; the server still rejects duplicates with 409 if
 * one slips through.
 *
 * Skips visually-confusing chars (I, O, 0, 1) so codes are easier to read
 * out loud or copy by hand.
 */
function generateCode() {
	const ALPHA = "ABCDEFGHJKLMNPQRSTUVWXYZ";
	const ALNUM = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
	const pick = (chars) => chars[Math.floor(Math.random() * chars.length)];
	let prefix = "";
	for (let i = 0; i < 4; i++) prefix += pick(ALPHA);
	let suffix = "";
	for (let i = 0; i < 4; i++) suffix += pick(ALNUM);
	return prefix + suffix;
}

/** API serves ISO ("...Z"); DateTimeField + backend both prefer MySQL. */
function isoToMysql(iso) {
	if (!iso) return null;
	const d = new Date(iso);
	if (Number.isNaN(d.getTime())) return null;
	const pad = (n) => String(n).padStart(2, "0");
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
}

function fromRow(row) {
	if (!row) return EMPTY;
	return {
		code: row.code ?? "",
		title: row.title ?? "",
		description: row.description ?? "",
		discount_type: row.discount_type ?? "percent",
		discount_value: row.discount_value ?? "",
		min_order_amount: row.min_order_amount ?? "",
		max_uses: row.max_uses ?? "",
		starts_at: isoToMysql(row.starts_at),
		expires_at: isoToMysql(row.expires_at),
	};
}

export function VoucherForm({ row, onClose }) {
	const isEdit = Boolean(row?.id);
	const [form, setForm] = useState(fromRow(row));
	const [error, setError] = useState(null);

	useEffect(() => { setForm(fromRow(row)); setError(null); }, [row]);

	const create = useApiMutation("post", "/admin/vouchers", { invalidate: ["/admin/vouchers"] });
	const update = useApiMutation("put",  `/admin/vouchers/${row?.id}`, { invalidate: ["/admin/vouchers"] });
	const mutation = isEdit ? update : create;

	const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

	const onSubmit = (e) => {
		e.preventDefault();
		setError(null);

		const payload = {
			title: form.title.trim(),
			description: form.description.trim(),
			discount_type: form.discount_type,
			discount_value: Number(form.discount_value) || 0,
			min_order_amount: Number(form.min_order_amount) || 0,
			max_uses: Number(form.max_uses) || 0,
			starts_at: form.starts_at,
			expires_at: form.expires_at,
		};
		if (!isEdit) {
			payload.code = form.code.trim().toUpperCase();
		}

		mutation.mutate(payload, {
			onSuccess: () => onClose(),
			onError: (err) => setError(err?.message || "Could not save."),
		});
	};

	return (
		<form onSubmit={onSubmit} className="zc-space-y-5">
			{error ? (
				<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-800">
					{error}
				</div>
			) : null}

			<Field label="Code" hint={isEdit ? "Locked once created — code is wired to the WC coupon." : "3-50 chars: A-Z 0-9 _ -"}>
				<div className="zc-flex zc-gap-2">
					<Input
						value={form.code}
						onChange={set("code")}
						placeholder="SUMMER25"
						required
						disabled={isEdit}
					/>
					{!isEdit ? (
						<button
							type="button"
							onClick={() => setForm((f) => ({ ...f, code: generateCode() }))}
							className="zc-inline-flex zc-h-10 zc-shrink-0 zc-items-center zc-gap-1.5 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-3 zc-text-sm zc-font-medium zc-text-zinc-700 hover:zc-bg-zinc-50"
							title="Generate random code"
						>
							<svg viewBox="0 0 24 24" className="zc-size-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
								<path strokeLinecap="round" strokeLinejoin="round" d="M21 12a9 9 0 1 1-3-6.7" />
								<path strokeLinecap="round" strokeLinejoin="round" d="M21 4v5h-5" />
							</svg>
							Generate
						</button>
					) : null}
				</div>
			</Field>

			<Field label="Title">
				<Input value={form.title} onChange={set("title")} placeholder="Summer Sale 25% Off" required />
			</Field>

			<Field label="Description">
				<textarea
					className="zc-w-full zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-3 zc-py-2 zc-text-sm zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
					rows={2}
					value={form.description}
					onChange={set("description")}
					placeholder="Optional — shown on the voucher card."
				/>
			</Field>

			<div className="zc-grid zc-grid-cols-2 zc-gap-4">
				<Field label="Discount type">
					<select
						className="zc-h-10 zc-w-full zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-3 zc-text-sm focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
						value={form.discount_type}
						onChange={set("discount_type")}
					>
						<option value="percent">Percent off</option>
						<option value="fixed_cart">Fixed cart amount</option>
					</select>
				</Field>
				<Field label={form.discount_type === "percent" ? "Discount (%)" : "Discount ($)"}>
					<Input
						type="number"
						step="0.01"
						min="0"
						value={form.discount_value}
						onChange={set("discount_value")}
						required
					/>
				</Field>
			</div>

			<div className="zc-grid zc-grid-cols-2 zc-gap-4">
				<Field label="Min order ($)" hint="0 = no minimum">
					<Input type="number" step="0.01" min="0" value={form.min_order_amount} onChange={set("min_order_amount")} />
				</Field>
				<Field label="Max uses" hint="0 = unlimited">
					<Input type="number" step="1" min="0" value={form.max_uses} onChange={set("max_uses")} />
				</Field>
			</div>

			<div className="zc-grid zc-grid-cols-2 zc-gap-4">
				<Field label="Starts at">
					<DateTimeField
						value={form.starts_at}
						onChange={(v) => setForm((f) => ({ ...f, starts_at: v }))}
						placeholder="Optional — start date"
					/>
				</Field>
				<Field label="Expires at">
					<DateTimeField
						value={form.expires_at}
						onChange={(v) => setForm((f) => ({ ...f, expires_at: v }))}
						placeholder="Optional — expiry date"
					/>
				</Field>
			</div>

			<div className="zc-flex zc-justify-end zc-gap-2 zc-border-t zc-border-zinc-200 zc-pt-4">
				<Button type="button" variant="ghost" onClick={onClose} disabled={mutation.isPending}>
					Cancel
				</Button>
				<Button type="submit" loading={mutation.isPending}>
					{isEdit ? "Save changes" : "Create draft"}
				</Button>
			</div>
		</form>
	);
}

function Field({ label, hint, children }) {
	return (
		<label className="zc-block zc-space-y-1">
			<span className="zc-text-sm zc-font-medium zc-text-zinc-800">{label}</span>
			{children}
			{hint ? <span className="zc-block zc-text-xs zc-text-zinc-500">{hint}</span> : null}
		</label>
	);
}
