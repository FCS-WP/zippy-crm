import { useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { dateTime } from "@/js/shared/utils/format.js";

/**
 * Per-code admin view for a multi-code voucher campaign.
 *
 * Shows a header with status totals, a status filter chip row, and a list
 * of codes with assignment + usage info. Single-code vouchers should never
 * route here (RowActions hides the "Codes" item) but if one slips through
 * we render an empty-state.
 *
 * GET /admin/vouchers/{id}/codes already returns:
 *   counts: { available, assigned, used, expired }
 *   items:  rows with user join + assignment metadata
 */
export function CodesDrawerBody({ voucher }) {
	const [filter, setFilter] = useState("");

	const { data, isLoading, error } = useApiQuery(
		`/admin/vouchers/${voucher.id}/codes`,
		{ params: filter ? { status: filter, per_page: 200 } : { per_page: 200 } },
	);

	if (isLoading) {
		return (
			<div className="zc-space-y-3">
				{[0, 1, 2, 3].map((i) => <Skeleton key={i} className="zc-h-10 zc-w-full" />)}
			</div>
		);
	}
	if (error) {
		return <p className="zc-text-sm zc-text-rose-700">{error.message || "Could not load codes."}</p>;
	}

	const counts = data?.counts ?? { available: 0, assigned: 0, used: 0, expired: 0 };
	const items  = data?.items  ?? [];
	const total  = (counts.available || 0) + (counts.assigned || 0) + (counts.used || 0) + (counts.expired || 0);
	const isMulti = (data?.distribution_mode ?? voucher.distribution_mode) === "multi_code_public";

	if (!isMulti) {
		return (
			<p className="zc-text-sm zc-text-zinc-500">
				This is a single-code voucher — there's nothing to list here. Single-code vouchers
				use the master code shown in the table.
			</p>
		);
	}

	return (
		<div className="zc-space-y-4">
			<CountsHeader counts={counts} total={total} />
			<FilterChips counts={counts} filter={filter} onChange={setFilter} />

			{items.length === 0 ? (
				<p className="zc-text-sm zc-text-zinc-500">
					{filter ? `No ${filter} codes.` : "No codes found."}
				</p>
			) : (
				<ul className="zc-divide-y zc-divide-zinc-100 zc-overflow-hidden zc-rounded-lg zc-border zc-border-zinc-200">
					{items.map((code) => <CodeRow key={code.id} code={code} />)}
				</ul>
			)}
		</div>
	);
}

function CountsHeader({ counts, total }) {
	return (
		<div className="zc-grid zc-grid-cols-4 zc-gap-2">
			<CountTile label="Total" value={total} tone="zinc" />
			<CountTile label="Available" value={counts.available || 0} tone="emerald" />
			<CountTile label="Assigned" value={counts.assigned || 0} tone="sky" />
			<CountTile label="Used" value={counts.used || 0} tone="violet" />
		</div>
	);
}

function CountTile({ label, value, tone }) {
	const toneCls = {
		zinc:    "zc-bg-zinc-50 zc-text-zinc-700",
		emerald: "zc-bg-emerald-50 zc-text-emerald-700",
		sky:     "zc-bg-sky-50 zc-text-sky-700",
		violet:  "zc-bg-violet-50 zc-text-violet-700",
	}[tone];
	return (
		<div className={`zc-rounded-md zc-px-3 zc-py-2 ${toneCls}`}>
			<div className="zc-text-[10px] zc-uppercase zc-tracking-wider zc-opacity-75">{label}</div>
			<div className="zc-text-lg zc-font-semibold zc-tabular-nums">{value}</div>
		</div>
	);
}

function FilterChips({ counts, filter, onChange }) {
	const chips = [
		{ key: "",          label: "All" },
		{ key: "available", label: `Available (${counts.available || 0})` },
		{ key: "assigned",  label: `Assigned (${counts.assigned || 0})` },
		{ key: "used",      label: `Used (${counts.used || 0})` },
		{ key: "expired",   label: `Expired (${counts.expired || 0})` },
	];
	return (
		<div className="zc-flex zc-flex-wrap zc-gap-1.5">
			{chips.map((c) => (
				<button
					key={c.key || "all"}
					type="button"
					onClick={() => onChange(c.key)}
					className={[
						"zc-rounded-full zc-border zc-px-2.5 zc-py-0.5 zc-text-xs zc-font-medium zc-transition",
						filter === c.key
							? "zc-border-zinc-900 zc-bg-zinc-900 zc-text-white"
							: "zc-border-zinc-300 zc-bg-white zc-text-zinc-700 hover:zc-bg-zinc-50",
					].join(" ")}
				>
					{c.label}
				</button>
			))}
		</div>
	);
}

function CodeRow({ code }) {
	const isClaimed = code.status === "assigned" || code.status === "used";
	return (
		<li className="zc-flex zc-items-center zc-justify-between zc-gap-3 zc-bg-white zc-px-3 zc-py-2.5 zc-text-sm">
			<div className="zc-min-w-0 zc-flex-1">
				<div className="zc-flex zc-items-center zc-gap-2">
					<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-font-mono zc-text-xs zc-text-zinc-800">
						{code.code}
					</code>
					<CodeStatusPill status={code.status} />
				</div>
				{isClaimed ? (
					<div className="zc-mt-1 zc-truncate zc-text-xs zc-text-zinc-500">
						{code.display_name || code.user_login || `User #${code.assigned_to_user}`}
						{code.user_email ? <span className="zc-text-zinc-400"> · {code.user_email}</span> : null}
					</div>
				) : null}
			</div>
			<div className="zc-shrink-0 zc-text-right zc-text-xs zc-text-zinc-500">
				{code.used_at
					? <div>Used {dateTime(code.used_at)}{code.order_id ? ` · order #${code.order_id}` : ""}</div>
					: code.assigned_at
						? <div>Assigned {dateTime(code.assigned_at)}</div>
						: <div className="zc-text-zinc-400">—</div>}
			</div>
		</li>
	);
}

function CodeStatusPill({ status }) {
	const cls = {
		available: "zc-bg-emerald-100 zc-text-emerald-800",
		assigned:  "zc-bg-sky-100 zc-text-sky-800",
		used:      "zc-bg-violet-100 zc-text-violet-800",
		expired:   "zc-bg-zinc-100 zc-text-zinc-700",
	}[status] || "zc-bg-zinc-100 zc-text-zinc-700";
	return (
		<span className={`zc-rounded-full zc-px-2 zc-py-0.5 zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wide ${cls}`}>
			{status}
		</span>
	);
}
