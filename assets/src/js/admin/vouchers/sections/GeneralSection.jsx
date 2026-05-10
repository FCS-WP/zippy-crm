import { Input } from "@/js/shared/ui/input.jsx";
import { Switch } from "@/js/shared/ui/switch.jsx";
import { isItemLevelType, isPercentType } from "@/js/shared/utils/format.js";
import { Field } from "./Field.jsx";

export function GeneralSection({ form, set, setForm, isEdit, generateCode }) {
	const isMulti = form.distribution_mode === "multi_code_public";

	return (
		<div className="zc-space-y-5">
			<Field
				label="Distribution mode"
				hint={isEdit
					? "Locked once created."
					: isMulti
						? "One campaign with N unique codes — each customer gets their own. Pick this for limited-quantity giveaways."
						: "One shared code reused by every customer who claims (capped by Max uses)."}
			>
				<div className="zc-flex zc-flex-col zc-gap-2 sm:zc-flex-row sm:zc-gap-4">
					<label className={`zc-flex zc-cursor-pointer zc-items-center zc-gap-2 zc-rounded-md zc-border zc-px-3 zc-py-2 zc-text-sm ${form.distribution_mode === "single_code" ? "zc-border-zinc-900 zc-bg-zinc-50" : "zc-border-zinc-300"} ${isEdit ? "zc-cursor-not-allowed zc-opacity-60" : ""}`}>
						<input
							type="radio"
							name="distribution_mode"
							value="single_code"
							checked={form.distribution_mode === "single_code"}
							onChange={() => setForm((f) => ({ ...f, distribution_mode: "single_code" }))}
							disabled={isEdit}
						/>
						<span className="zc-font-medium">Single code</span>
					</label>
					<label className={`zc-flex zc-cursor-pointer zc-items-center zc-gap-2 zc-rounded-md zc-border zc-px-3 zc-py-2 zc-text-sm ${isMulti ? "zc-border-zinc-900 zc-bg-zinc-50" : "zc-border-zinc-300"} ${isEdit ? "zc-cursor-not-allowed zc-opacity-60" : ""}`}>
						<input
							type="radio"
							name="distribution_mode"
							value="multi_code_public"
							checked={isMulti}
							onChange={() => setForm((f) => ({ ...f, distribution_mode: "multi_code_public" }))}
							disabled={isEdit}
						/>
						<span className="zc-font-medium">Multi-code (public)</span>
					</label>
				</div>
			</Field>

			{isMulti ? (
				<MultiCodeFields form={form} set={set} setForm={setForm} isEdit={isEdit} />
			) : (
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
			)}

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
				<Field
					label="Discount type"
					required
					hint={
						isItemLevelType(form.discount_type)
							? "Item-level: applies to each matching line in the cart. Restrict to specific products or categories on the Restrictions tab."
							: form.discount_type === "percent"
								? "Percent off. Cart-wide by default; if you set Products or Categories on the Restrictions tab"
								: "Cart-level: applies once to the whole cart."
					}
				>
					<select
						className="zc-h-10 zc-w-full zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-3 zc-text-sm focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
						value={form.discount_type}
						onChange={set("discount_type")}
					>
						<option value="percent">Percent off (cart or per-item if restricted)</option>
						<option value="fixed_cart">Fixed amount off cart</option>
						<option value="fixed_product">Fixed amount off each item</option>
					</select>
				</Field>
				<Field label={isPercentType(form.discount_type) ? "Discount (%)" : "Discount ($)"} required>
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

function MultiCodeFields({ form, set, setForm, isEdit }) {
	if (isEdit) {
		return (
			<div className="zc-rounded-md zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-3 zc-text-sm zc-text-zinc-600">
				This is a multi-code campaign. Codes were minted at create time and are immutable.
				Use the Codes view (admin → Vouchers row → View codes) to inspect per-code status.
			</div>
		);
	}

	const slots = Number(form.slots) || 0;
	const codesText = form.codes_text ?? "";
	const typedCodes = codesText.split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
	const slotsMismatch = slots > 0 && typedCodes.length > 0 && typedCodes.length !== slots;

	return (
		<div className="zc-space-y-4 zc-rounded-md zc-border zc-border-zinc-200 zc-bg-zinc-50/60 zc-p-4">
			<Field
				label="Slots"
				required
				hint="How many unique codes to mint. Each one is single-use; once all slots are claimed the campaign is exhausted."
			>
				<Input
					type="number"
					min="1"
					max="500"
					value={form.slots}
					onChange={set("slots")}
					placeholder="50"
					required
				/>
			</Field>

			<Field
				label="Codes (one per line)"
				hint="Optional. Leave blank to auto-generate. If you type codes, the count must match Slots."
			>
				<textarea
					className="zc-w-full zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-3 zc-py-2 zc-font-mono zc-text-sm zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
					rows={5}
					value={codesText}
					onChange={(e) => setForm((f) => ({ ...f, codes_text: e.target.value }))}
					placeholder={"SUMMER-AAA111\nSUMMER-BBB222\nSUMMER-CCC333"}
				/>
				<div className="zc-mt-1 zc-flex zc-justify-between zc-text-xs">
					<span className="zc-text-zinc-500">{typedCodes.length} typed</span>
					{slotsMismatch ? (
						<span className="zc-text-rose-600">⚠ Typed codes ({typedCodes.length}) ≠ slots ({slots})</span>
					) : null}
				</div>
			</Field>

			<Field
				label="Auto-generate prefix"
				hint="Optional. Used only when Codes is blank — auto-generated codes look like PREFIX-AB12CD."
			>
				<Input
					value={form.code_prefix}
					onChange={set("code_prefix")}
					placeholder="SUMMER"
				/>
			</Field>
		</div>
	);
}
