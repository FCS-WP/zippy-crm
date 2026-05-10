import { useEffect, useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { Input } from "@/js/shared/ui/input.jsx";
import { Switch } from "@/js/shared/ui/switch.jsx";

const EMPTY = {
	slug: "",
	label: "",
	multiplier: "1.00",
	threshold_orders: "",
	threshold_spend: "",
	is_admin_only: false,
	sort_order: 0,
};

function fromRow(row) {
	if (!row) return EMPTY;
	return {
		slug: row.slug ?? "",
		label: row.label ?? "",
		multiplier: row.multiplier !== undefined ? String(row.multiplier) : "1.00",
		threshold_orders: row.threshold_orders ?? "",
		threshold_spend: row.threshold_spend ?? "",
		is_admin_only: Boolean(row.is_admin_only),
		sort_order: row.sort_order ?? 0,
	};
}

export function TierForm({ row, onClose }) {
	const isEdit = Boolean(row?.slug);
	const [form, setForm] = useState(fromRow(row));
	const [error, setError] = useState(null);

	useEffect(() => { setForm(fromRow(row)); setError(null); }, [row]);

	const create = useApiMutation("post", "/admin/tiers",                  { invalidate: ["/admin/tiers", "/tiers"] });
	const update = useApiMutation("put",  `/admin/tiers/${row?.slug}`,     { invalidate: ["/admin/tiers", "/tiers"] });
	const mutation = isEdit ? update : create;

	const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

	const onSubmit = (e) => {
		e.preventDefault();
		setError(null);

		const payload = {
			label: form.label.trim(),
			multiplier: Number(form.multiplier) || 0,
			// Empty string → null (= no threshold). Server accepts both.
			threshold_orders: form.threshold_orders === "" ? null : Number(form.threshold_orders),
			threshold_spend:  form.threshold_spend  === "" ? null : Number(form.threshold_spend),
			is_admin_only: form.is_admin_only ? 1 : 0,
			sort_order: Number(form.sort_order) || 0,
		};
		if (!isEdit) {
			payload.slug = form.slug.trim().toLowerCase();
		}

		mutation.mutate(payload, {
			onSuccess: () => onClose(),
			onError:   (err) => setError(err?.message || "Could not save."),
		});
	};

	return (
		<form onSubmit={onSubmit} className="zc-space-y-5">
			{error ? (
				<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-800">
					{error}
				</div>
			) : null}

			<Field label="Slug" hint={isEdit
				? "Locked once created — slug is referenced by every member's membership_level."
				: "2-40 chars: lowercase a-z, 0-9, _, -. Used as the stable internal identifier."}>
				<Input
					value={form.slug}
					onChange={set("slug")}
					placeholder="platinum"
					required
					disabled={isEdit}
				/>
			</Field>

			<Field label="Label" hint="Display name customers see (My Account, emails).">
				<Input value={form.label} onChange={set("label")} placeholder="Platinum" required />
			</Field>

			<div className="zc-grid zc-grid-cols-2 zc-gap-4">
				<Field label="Multiplier (×)" hint="Earn-rate multiplier. e.g. 2.00 = 2× points per dollar.">
					<Input type="number" step="0.05" min="0.05" max="10" value={form.multiplier} onChange={set("multiplier")} required />
				</Field>
				<Field label="Sort order" hint="Lower = earlier in the ladder. Used for display and tie-break.">
					<Input type="number" step="1" value={form.sort_order} onChange={set("sort_order")} />
				</Field>
			</div>

			<div className="zc-grid zc-grid-cols-2 zc-gap-4">
				<Field label="Orders threshold" hint="Auto-assign when lifetime orders ≥ this. Blank = no order rule.">
					<Input type="number" step="1" min="0" value={form.threshold_orders} onChange={set("threshold_orders")} placeholder="—" />
				</Field>
				<Field label="Spend threshold ($)" hint="Auto-assign when lifetime spend ≥ this. Blank = no spend rule.">
					<Input type="number" step="0.01" min="0" value={form.threshold_spend} onChange={set("threshold_spend")} placeholder="—" />
				</Field>
			</div>

			<Field
				label="Admin only"
				hint="When on, the auto-evaluator never sets or removes this tier — admins assign it manually. (e.g. VIP)"
			>
				<div className="zc-flex zc-items-center zc-gap-2">
					<Switch
						checked={form.is_admin_only}
						onCheckedChange={(checked) => setForm((f) => ({ ...f, is_admin_only: checked }))}
					/>
					<span className="zc-text-sm zc-text-zinc-700">
						{form.is_admin_only ? "Sticky / admin-assigned only" : "Auto-evaluated by orders + spend"}
					</span>
				</div>
			</Field>

			<div className="zc-flex zc-justify-end zc-gap-2 zc-border-t zc-border-zinc-200 zc-pt-4">
				<Button type="button" variant="ghost" onClick={onClose} disabled={mutation.isPending}>
					Cancel
				</Button>
				<Button type="submit" loading={mutation.isPending}>
					{isEdit ? "Save changes" : "Create tier"}
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
