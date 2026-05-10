import { Input } from "@/js/shared/ui/input.jsx";
import { Switch } from "@/js/shared/ui/switch.jsx";
import { CatalogPickerField } from "./CatalogPickerField.jsx";
import { EmailRestrictionsField } from "./EmailRestrictionsField.jsx";
import { Field } from "./Field.jsx";

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

			<Field label="Allowed customer emails">
				<EmailRestrictionsField
					value={form.email_restrictions ?? []}
					onChange={(v) => setForm((f) => ({ ...f, email_restrictions: v }))}
				/>
			</Field>
		</div>
	);
}
