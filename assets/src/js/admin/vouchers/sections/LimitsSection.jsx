import { Input } from "@/js/shared/ui/input.jsx";
import { Field } from "./Field.jsx";

export function LimitsSection({ form, set }) {
	return (
		<div className="zc-space-y-5">
			<Field label="Total uses (across all customers)" hint="0 = unlimited">
				<Input type="number" step="1" min="0" value={form.max_uses} onChange={set("max_uses")} />
			</Field>

			<Field label="Uses per customer" hint="0 = unlimited per customer">
				<Input type="number" step="1" min="0" value={form.usage_limit_per_user} onChange={set("usage_limit_per_user")} />
			</Field>

			<Field
				label="Limit usage to N items"
				hint="Discount applies to at most N qualifying line-items in the cart. 0 = all qualifying items."
			>
				<Input type="number" step="1" min="0" value={form.limit_usage_to_x_items} onChange={set("limit_usage_to_x_items")} />
			</Field>
		</div>
	);
}
