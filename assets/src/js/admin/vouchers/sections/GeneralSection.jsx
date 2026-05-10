import { Input } from "@/js/shared/ui/input.jsx";
import { Switch } from "@/js/shared/ui/switch.jsx";
import { DateTimeField } from "../DateTimeField.jsx";
import { Field } from "./Field.jsx";

export function GeneralSection({ form, set, setForm, isEdit, generateCode }) {
	return (
		<div className="zc-space-y-5">
			<Field
				label="Code"
				required={!isEdit}
				hint={isEdit
					? "Locked once created — code is wired to the WC coupon."
					: "3-50 chars: A-Z 0-9 _ -"}
			>
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

			<Field label="Title" required>
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
				<Field label="Discount type" required>
					<select
						className="zc-h-10 zc-w-full zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-3 zc-text-sm focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
						value={form.discount_type}
						onChange={set("discount_type")}
					>
						<option value="percent">Percent off</option>
						<option value="fixed_cart">Fixed cart amount</option>
					</select>
				</Field>
				<Field label={form.discount_type === "percent" ? "Discount (%)" : "Discount ($)"} required>
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

			<Field label="Allow free shipping" hint="Customer is not charged for shipping when this voucher is applied (subject to your shipping zone settings).">
				<div className="zc-flex zc-items-center zc-gap-2">
					<Switch
						checked={!!form.free_shipping}
						onCheckedChange={(checked) => setForm((f) => ({ ...f, free_shipping: checked }))}
					/>
					<span className="zc-text-sm zc-text-zinc-700">{form.free_shipping ? "Free shipping included" : "Shipping unaffected"}</span>
				</div>
			</Field>
		</div>
	);
}
