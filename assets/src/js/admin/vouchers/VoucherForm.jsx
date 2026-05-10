import { useEffect, useMemo, useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { isItemLevelType } from "@/js/shared/utils/format.js";
import { GeneralSection }      from "./sections/GeneralSection.jsx";
import { LimitsSection }       from "./sections/LimitsSection.jsx";
import { RestrictionsSection } from "./sections/RestrictionsSection.jsx";
import { Tabs }                from "./sections/Tabs.jsx";
import { TimeSection }         from "./sections/TimeSection.jsx";

const EMPTY = {
	// general
	distribution_mode: "single_code",
	code: "",
	title: "",
	description: "",
	discount_type: "percent",
	discount_value: "",
	free_shipping: false,
	// multi-code only — UI-side state, transformed before submit
	slots: "",
	codes_text: "",
	code_prefix: "",
	// restrictions
	min_order_amount: "",
	max_order_amount: "",
	individual_use: true,
	exclude_sale_items: false,
	product_ids: [],
	excluded_product_ids: [],
	product_categories: [],
	excluded_product_categories: [],
	// Audience targeting (mutually exclusive). The RestrictionsSection
	// AudienceField clears the inactive list when mode changes.
	audience_mode: "public",
	email_restrictions: [],
	allowed_tiers: [],
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
		distribution_mode: row.distribution_mode ?? "single_code",
		// Multi-code rows store a synthetic ZC_MULTI_* placeholder in `code` —
		// blank it for the UI so admins don't see the internal placeholder.
		code: (row.distribution_mode === "multi_code_public") ? "" : (row.code ?? ""),
		// Multi-code create-only fields. Always empty when editing — the form
		// shows a read-only summary instead of input fields.
		slots: "",
		codes_text: "",
		code_prefix: "",
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
		audience_mode:               row.audience_mode ?? "public",
		email_restrictions:          Array.isArray(row.email_restrictions)          ? row.email_restrictions          : [],
		allowed_tiers:               Array.isArray(row.allowed_tiers)               ? row.allowed_tiers               : [],

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
	const isMulti = form.distribution_mode === "multi_code_public";
	if (!isEdit) {
		// Single-code requires the Code field; multi-code requires Slots.
		if (isMulti) {
			if (!form.slots || Number(form.slots) <= 0) n++;
		} else {
			if (!form.code?.trim()) n++;
		}
	}
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

		// Mirror VoucherService::validate_payload's item-level guard so the
		// admin sees the issue inline rather than a 400 from the server.
		if (
			!isEdit &&
			isItemLevelType(form.discount_type) &&
			(form.product_ids || []).length === 0 &&
			(form.product_categories || []).length === 0
		) {
			setTab("restrictions");
			setError("Item-level discounts must restrict to specific products or categories. Pick at least one on the Restrictions tab.");
			return;
		}

		// Audience-mode mirrors of the same guard.
		if (form.audience_mode === "tier" && (form.allowed_tiers || []).length === 0) {
			setTab("restrictions");
			setError("Pick at least one membership tier on the Restrictions tab, or switch the audience back to Public.");
			return;
		}
		if (form.audience_mode === "email" && (form.email_restrictions || []).length === 0) {
			setTab("restrictions");
			setError("Pick at least one customer or email on the Restrictions tab, or switch the audience back to Public.");
			return;
		}

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
			// Audience targeting — always send the mode + only the matching list.
			// The form's mode-switch handler already cleared the inactive list,
			// but be defensive here so a stale state can't accidentally save mixed.
			audience_mode:      form.audience_mode || "public",
			email_restrictions: form.audience_mode === "email" ? form.email_restrictions : [],
			allowed_tiers:      form.audience_mode === "tier"  ? form.allowed_tiers      : [],
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
			const isMulti = form.distribution_mode === "multi_code_public";
			payload.distribution_mode = form.distribution_mode || "single_code";
			if (isMulti) {
				payload.slots = Number(form.slots) || 0;
				const typedCodes = (form.codes_text || "")
					.split(/\r?\n/)
					.map((s) => s.trim().toUpperCase())
					.filter(Boolean);
				if (typedCodes.length > 0) {
					payload.codes = typedCodes;
				}
				const prefix = (form.code_prefix || "").trim().toUpperCase();
				if (prefix) {
					payload.code_prefix = prefix;
				}
				// Don't send `code` — server generates the ZC_MULTI_* placeholder.
			} else {
				payload.code = form.code.trim().toUpperCase();
			}
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
