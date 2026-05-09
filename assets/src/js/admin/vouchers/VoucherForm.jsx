import { useEffect, useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { Input } from "@/js/shared/ui/input.jsx";

const EMPTY = {
	code: "",
	title: "",
	description: "",
	discount_type: "percent",
	discount_value: "",
	min_order_amount: "",
	max_uses: "",
	starts_at: "",
	expires_at: "",
};

/**
 * Convert an ISO timestamp from the API to the format `<input type="datetime-local">`
 * expects (`YYYY-MM-DDTHH:MM`). Returns "" for null/undefined.
 */
function isoToLocal(iso) {
	if (!iso) return "";
	return iso.slice(0, 16);
}

/** Convert form-local datetime back to MySQL UTC datetime (server expects UTC). */
function localToMysql(local) {
	if (!local) return null;
	const date = new Date(local);
	if (Number.isNaN(date.getTime())) return null;
	return date.toISOString().slice(0, 19).replace("T", " ");
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
		starts_at: isoToLocal(row.starts_at),
		expires_at: isoToLocal(row.expires_at),
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
			starts_at: localToMysql(form.starts_at),
			expires_at: localToMysql(form.expires_at),
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
				<Input
					value={form.code}
					onChange={set("code")}
					placeholder="SUMMER25"
					required
					disabled={isEdit}
				/>
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
					<Input type="datetime-local" value={form.starts_at} onChange={set("starts_at")} />
				</Field>
				<Field label="Expires at">
					<Input type="datetime-local" value={form.expires_at} onChange={set("expires_at")} />
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
