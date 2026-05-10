import { DataTable } from "@/js/shared/components/DataTable.jsx";
import { date, isPercentType, money, percent } from "@/js/shared/utils/format.js";
import { RowActions } from "./RowActions.jsx";
import { StatusBadge } from "./StatusBadge.jsx";

function discountLabel(row) {
	return isPercentType(row.discount_type)
		? percent(row.discount_value)
		: money(row.discount_value);
}

function quotaLabel(row) {
	if (!row.max_uses) return `${row.uses_count} / ∞`;
	return `${row.uses_count} / ${row.max_uses}`;
}

export function VouchersTable({ rows, sort, onSort, loading, error, onEdit, onDetail }) {
	const columns = [
		{
			key: "code",
			label: "Code",
			sortable: true,
			render: (row) => {
				const isMulti = row.distribution_mode === "multi_code_public";
				if (isMulti) {
					return (
						<span className="zc-inline-flex zc-items-center zc-gap-1.5 zc-rounded zc-bg-violet-50 zc-px-2 zc-py-0.5 zc-text-xs zc-font-medium zc-text-violet-700">
							<svg viewBox="0 0 24 24" className="zc-size-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
								<rect x="3" y="3" width="7" height="7" rx="1" />
								<rect x="14" y="3" width="7" height="7" rx="1" />
								<rect x="3" y="14" width="7" height="7" rx="1" />
								<rect x="14" y="14" width="7" height="7" rx="1" />
							</svg>
							Multi-code
						</span>
					);
				}
				return (
					<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-xs zc-font-mono zc-text-zinc-800">
						{row.code}
					</code>
				);
			},
		},
		{
			key: "title",
			label: "Title",
			sortable: true,
			cellClassName: "zc-max-w-xs",
			render: (row) => (
				<>
					<div className="zc-flex zc-items-center zc-gap-1.5">
						<span className="zc-font-medium zc-text-zinc-900">{row.title}</span>
						<AudienceBadge mode={row.audience_mode} />
					</div>
					{row.description ? (
						<div className="zc-truncate zc-text-xs zc-text-zinc-500">{row.description}</div>
					) : null}
				</>
			),
		},
		{
			key: "discount_value",
			label: "Discount",
			align: "right",
			sortable: true,
			cellClassName: "zc-tabular-nums",
			render: discountLabel,
		},
		{
			key: "min_order_amount",
			label: "Min order",
			align: "right",
			sortable: true,
			cellClassName: "zc-tabular-nums",
			render: (row) => (row.min_order_amount > 0 ? money(row.min_order_amount) : "—"),
		},
		{
			key: "uses_count",
			label: "Used",
			align: "right",
			sortable: true,
			cellClassName: "zc-tabular-nums",
			render: quotaLabel,
		},
		{
			key: "status",
			label: "Status",
			sortable: true,
			render: (row) => <StatusBadge status={row.status} />,
		},
		{
			key: "expires_at",
			label: "Expires",
			sortable: true,
			render: (row) => row.expires_at ? date(row.expires_at) : "—",
		},
		{
			key: "_actions",
			label: "Actions",
			align: "right",
			stopPropagation: true,
			render: (row) => <RowActions row={row} onEdit={onEdit} />,
		},
	];

	// Row click opens the unified Detail drawer (Overview/Claims/Codes tabs).
	// The ⋯ menu keeps the verbs (Edit/Duplicate/Delete) — the drawer is for
	// reading, the menu is for acting.
	return (
		<DataTable
			columns={columns}
			rows={rows}
			rowKey={(r) => r.id}
			sort={sort}
			onSort={onSort}
			loading={loading}
			error={error}
			empty="No vouchers match your filters."
			onRowClick={onDetail}
		/>
	);
}

function AudienceBadge({ mode }) {
	if (mode === "tier") {
		return (
			<span
				className="zc-inline-flex zc-items-center zc-rounded zc-bg-amber-50 zc-px-1.5 zc-py-0.5 zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wide zc-text-amber-700"
				title="Restricted to specific membership tiers"
			>
				Tier
			</span>
		);
	}
	if (mode === "email") {
		return (
			<span
				className="zc-inline-flex zc-items-center zc-rounded zc-bg-sky-50 zc-px-1.5 zc-py-0.5 zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wide zc-text-sky-700"
				title="Restricted to specific customers / emails"
			>
				Customer
			</span>
		);
	}
	return null;
}
