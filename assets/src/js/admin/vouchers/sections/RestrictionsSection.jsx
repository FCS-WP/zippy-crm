import { Input } from "@/js/shared/ui/input.jsx";
import { Switch } from "@/js/shared/ui/switch.jsx";
import { CatalogPickerField } from "./CatalogPickerField.jsx";
import { EmailRestrictionsField } from "./EmailRestrictionsField.jsx";
import { Field } from "./Field.jsx";
import { TierRestrictionField } from "./TierRestrictionField.jsx";

export function RestrictionsSection({ form, set, setForm }) {
	return (
		<div className="zc-space-y-5">
			<div className="zc-grid zc-grid-cols-2 zc-gap-4">
				<Field label="Min order ($)" hint="Cart subtotal must be ≥ this. 0 = no minimum.">
					<Input type="number" step="0.01" min="0" value={form.min_order_amount} onChange={set("min_order_amount")} />
				</Field>
				<Field label="Max order ($)" hint="Cart subtotal must be ≤ this. 0 = no maximum.">
					<Input type="number" step="0.01" min="0" value={form.max_order_amount} onChange={set("max_order_amount")} />
				</Field>
			</div>

			<Field
				label="Individual use only"
				hint="When on, this voucher can't be combined with any other coupon on the same order."
			>
				<div className="zc-flex zc-items-center zc-gap-2">
					<Switch
						checked={!!form.individual_use}
						onCheckedChange={(checked) => setForm((f) => ({ ...f, individual_use: checked }))}
					/>
					<span className="zc-text-sm zc-text-zinc-700">{form.individual_use ? "Cannot stack with other coupons" : "Can stack with other coupons"}</span>
				</div>
			</Field>

			<Field
				label="Exclude sale items"
				hint="When on, products already on sale don't count toward the discount."
			>
				<div className="zc-flex zc-items-center zc-gap-2">
					<Switch
						checked={!!form.exclude_sale_items}
						onCheckedChange={(checked) => setForm((f) => ({ ...f, exclude_sale_items: checked }))}
					/>
					<span className="zc-text-sm zc-text-zinc-700">{form.exclude_sale_items ? "Sale items skipped" : "Sale items included"}</span>
				</div>
			</Field>

			<Field label="Allowed products" hint="Only these products qualify. Empty = all products.">
				<CatalogPickerField
					kind="products"
					label="Allowed products"
					value={form.product_ids ?? []}
					onChange={(v) => setForm((f) => ({ ...f, product_ids: v }))}
					placeholder="Empty — all products qualify"
				/>
			</Field>

			<Field label="Excluded products" hint="These products are blocked from the discount.">
				<CatalogPickerField
					kind="products"
					label="Excluded products"
					value={form.excluded_product_ids ?? []}
					onChange={(v) => setForm((f) => ({ ...f, excluded_product_ids: v }))}
					placeholder="Empty — no products blocked"
				/>
			</Field>

			<Field label="Allowed product categories" hint="Only products in these categories qualify. Empty = all.">
				<CatalogPickerField
					kind="categories"
					label="Allowed categories"
					value={form.product_categories ?? []}
					onChange={(v) => setForm((f) => ({ ...f, product_categories: v }))}
					placeholder="Empty — all categories qualify"
				/>
			</Field>

			<Field label="Excluded product categories">
				<CatalogPickerField
					kind="categories"
					label="Excluded categories"
					value={form.excluded_product_categories ?? []}
					onChange={(v) => setForm((f) => ({ ...f, excluded_product_categories: v }))}
					placeholder="Empty — no categories blocked"
				/>
			</Field>

			<AudienceField form={form} setForm={setForm} />
		</div>
	);
}

/**
 * Audience selector — mutually exclusive between Public / Specific customers /
 * Membership tiers. Switching mode clears the other restriction column so the
 * stored shape always matches the chosen mode.
 */
function AudienceField({ form, setForm }) {
	const audience_mode = form.audience_mode ?? "public";

	const setMode = (mode) => {
		setForm((f) => ({
			...f,
			audience_mode: mode,
			// Clear the inactive list so we never persist a mixed shape.
			email_restrictions: mode === "email" ? (f.email_restrictions ?? []) : [],
			allowed_tiers:      mode === "tier"  ? (f.allowed_tiers      ?? []) : [],
		}));
	};

	return (
		<div className="zc-space-y-3 zc-rounded-md zc-border zc-border-zinc-200 zc-bg-zinc-50/60 zc-p-4">
			<div>
				<label className="zc-text-sm zc-font-medium zc-text-zinc-900">Audience</label>
				<p className="zc-mt-1 zc-text-xs zc-text-zinc-500">
					Who can see and claim this voucher. Pick one — these are mutually exclusive.
				</p>
			</div>

			<div className="zc-grid zc-grid-cols-1 zc-gap-2 sm:zc-grid-cols-3">
				<AudienceRadio
					checked={audience_mode === "public"}
					onChange={() => setMode("public")}
					label="Public"
					hint="Any customer can claim."
				/>
				<AudienceRadio
					checked={audience_mode === "email"}
					onChange={() => setMode("email")}
					label="Specific customers"
					hint="Pick individual customers or guest emails."
				/>
				<AudienceRadio
					checked={audience_mode === "tier"}
					onChange={() => setMode("tier")}
					label="Membership tiers"
					hint="Restrict to one or more tiers (e.g. Gold + VIP)."
				/>
			</div>

			{audience_mode === "email" ? (
				<div className="zc-pt-1">
					<EmailRestrictionsField
						value={form.email_restrictions ?? []}
						onChange={(v) => setForm((f) => ({ ...f, email_restrictions: v }))}
					/>
				</div>
			) : null}

			{audience_mode === "tier" ? (
				<div className="zc-pt-1">
					<TierRestrictionField
						value={form.allowed_tiers ?? []}
						onChange={(v) => setForm((f) => ({ ...f, allowed_tiers: v }))}
					/>
				</div>
			) : null}
		</div>
	);
}

function AudienceRadio({ checked, onChange, label, hint }) {
	return (
		<label
			className={[
				"zc-flex zc-cursor-pointer zc-flex-col zc-rounded-md zc-border zc-px-3 zc-py-2 zc-text-sm zc-transition",
				checked
					? "zc-border-zinc-900 zc-bg-white zc-shadow-sm"
					: "zc-border-zinc-300 zc-bg-white hover:zc-border-zinc-400",
			].join(" ")}
		>
			<div className="zc-flex zc-items-center zc-gap-2">
				<input type="radio" checked={checked} onChange={onChange} className="zc-shrink-0" />
				<span className="zc-font-medium zc-text-zinc-900">{label}</span>
			</div>
			<span className="zc-mt-1 zc-text-xs zc-text-zinc-500">{hint}</span>
		</label>
	);
}
