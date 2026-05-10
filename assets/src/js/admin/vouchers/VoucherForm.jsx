import { useEffect, useMemo, useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { GeneralSection }      from "./sections/GeneralSection.jsx";
import { LimitsSection }       from "./sections/LimitsSection.jsx";
import { RestrictionsSection } from "./sections/RestrictionsSection.jsx";
import { Tabs }                from "./sections/Tabs.jsx";
import { TimeSection }         from "./sections/TimeSection.jsx";

const EMPTY = {
	// general
	code: "",
	title: "",
	description: "",
	discount_type: "percent",
	discount_value: "",
	free_shipping: false,
	// restrictions
	min_order_amount: "",
	max_order_amount: "",
	individual_use: true,
	exclude_sale_items: false,
	product_ids: [],
	excluded_product_ids: [],
	product_categories: [],
	excluded_product_categories: [],
	email_restrictions: [],
	// limits
	max_uses: "",
	usage_limit_per_user: "",
	limit_usage_to_x_items: "",
	// time
	starts_at: null,
	expires_at: null,
	allowed_hours: null,
};

/**
 * Random voucher code: 4-letter readable prefix + 4 alphanumerics. Matches
 * the server-side regex (^[A-Z0-9_-]{3,50}$). Skips visually-confusing chars
 * (I, O, 0, 1) so codes are easier to read or copy by hand.
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
		free_shipping: Boolean(row.free_shipping),

		min_order_amount: row.min_order_amount ?? "",
		max_order_amount: row.max_order_amount ?? "",
		individual_use: row.individual_use !== undefined ? Boolean(row.individual_use) : true,
		exclude_sale_items: Boolean(row.exclude_sale_items),
		product_ids:                 Array.isArray(row.product_ids)                 ? row.product_ids                 : [],
		excluded_product_ids:        Array.isArray(row.excluded_product_ids)        ? row.excluded_product_ids        : [],
		product_categories:          Array.isArray(row.product_categories)          ? row.product_categories          : [],
		excluded_product_categories: Array.isArray(row.excluded_product_categories) ? row.excluded_product_categories : [],
		email_restrictions:          Array.isArray(row.email_restrictions)          ? row.email_restrictions          : [],

		max_uses:               row.max_uses               ?? "",
		usage_limit_per_user:   row.usage_limit_per_user   ?? "",
		limit_usage_to_x_items: row.limit_usage_to_x_items ?? "",

		starts_at:  isoToMysql(row.starts_at),
		expires_at: isoToMysql(row.expires_at),
		allowed_hours: row.allowed_hours ?? null,
	};
}

const TABS = [
	{ key: "general",      label: "General"      },
	{ key: "restrictions", label: "Restrictions" },
	{ key: "limits",       label: "Limits"       },
	{ key: "time",         label: "Time window"  },
];

/**
 * Count required fields that are blank. Mirrors the server-side validator
 * (VoucherService::validate_payload). Used to badge the General tab so the
 * admin sees from any tab how many required fields still need filling.
 *
 * On edit, code is locked so it doesn't count toward the missing total.
 */
function countMissingRequired(form, isEdit) {
	let n = 0;
	if (!isEdit && !form.code?.trim())  n++;
	if (!form.title?.trim())            n++;
	if (!form.discount_type)            n++;
	const dv = form.discount_value;
	if (dv === "" || dv === null || dv === undefined) n++;
	return n;
}

export function VoucherForm({ row, onClose }) {
	const isEdit = Boolean(row?.id);
	const [form, setForm]   = useState(fromRow(row));
	const [error, setError] = useState(null);
	const [tab, setTab]     = useState("general");

	useEffect(() => { setForm(fromRow(row)); setError(null); setTab("general"); }, [row]);

	const create = useApiMutation("post", "/admin/vouchers",            { invalidate: ["/admin/vouchers"] });
	const update = useApiMutation("put",  `/admin/vouchers/${row?.id}`, { invalidate: ["/admin/vouchers"] });
	const mutation = isEdit ? update : create;

	const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));

	const missingRequired = useMemo(() => countMissingRequired(form, isEdit), [form, isEdit]);

	const onSubmit = (e) => {
		e.preventDefault();
		setError(null);

		const payload = {
			// general
			title:          form.title.trim(),
			description:    form.description.trim(),
			discount_type:  form.discount_type,
			discount_value: Number(form.discount_value) || 0,
			free_shipping:  !!form.free_shipping,
			// restrictions
			min_order_amount:   Number(form.min_order_amount) || 0,
			max_order_amount:   Number(form.max_order_amount) || 0,
			individual_use:     !!form.individual_use,
			exclude_sale_items: !!form.exclude_sale_items,
			product_ids:                 form.product_ids,
			excluded_product_ids:        form.excluded_product_ids,
			product_categories:          form.product_categories,
			excluded_product_categories: form.excluded_product_categories,
			email_restrictions:          form.email_restrictions,
			// limits
			max_uses:               Number(form.max_uses)               || 0,
			usage_limit_per_user:   Number(form.usage_limit_per_user)   || 0,
			limit_usage_to_x_items: Number(form.limit_usage_to_x_items) || 0,
			// time
			starts_at:     form.starts_at,
			expires_at:    form.expires_at,
			allowed_hours: form.allowed_hours,
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

			<Tabs
				tabs={TABS.map((t) =>
					t.key === "general" && missingRequired > 0
						? { ...t, count: missingRequired, tone: "danger" }
						: t,
				)}
				value={tab}
				onChange={setTab}
			/>

			<div role="tabpanel">
				{tab === "general"      ? <GeneralSection      form={form} set={set} setForm={setForm} isEdit={isEdit} generateCode={generateCode} /> : null}
				{tab === "restrictions" ? <RestrictionsSection form={form} set={set} setForm={setForm} /> : null}
				{tab === "limits"       ? <LimitsSection       form={form} set={set} /> : null}
				{tab === "time"         ? <TimeSection         form={form} setForm={setForm} /> : null}
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
