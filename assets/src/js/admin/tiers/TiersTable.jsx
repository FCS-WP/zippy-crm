import { Badge } from "@/js/shared/ui/badge.jsx";
import { DataTable } from "@/js/shared/components/DataTable.jsx";
import { money, number } from "@/js/shared/utils/format.js";
import { tierColor } from "@/js/shared/utils/tierColor.js";
import { RowActions } from "./RowActions.jsx";

/**
 * Tiers list. The dataset is small (typically ≤ 6 rows) so we don't bother
 * with server-side sort — the entire list ships in one request and the
 * admin already sees the canonical order via `sort_order` column.
 *
 * Whole row is clickable to open the members drawer; the action menu
 * stops propagation so clicking ⋯ → Edit doesn't also open the drawer.
 */
export function TiersTable({ rows, loading, error, onEdit, onOpenMembers }) {
	const columns = [
		{
			key: "label",
			label: "Tier",
			render: (row) => {
				const color = tierColor(row.sort_order);
				return (
					<div className="zc-flex zc-items-center zc-gap-2">
						<Badge variant={color.badge}>{row.label}</Badge>
						{row.is_admin_only ? (
							<Badge variant="muted" title="Auto-evaluator never sets this tier">
								Admin only
							</Badge>
						) : null}
					</div>
				);
			},
		},
		{
			key: "slug",
			label: "Slug",
			render: (row) => (
				<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-xs zc-font-mono zc-text-zinc-700">
					{row.slug}
				</code>
			),
		},
		{
			key: "multiplier",
			label: "Earn rate",
			align: "right",
			cellClassName: "zc-font-medium zc-text-zinc-900 zc-tabular-nums",
			render: (row) => {
				const v = Number(row.multiplier);
				if (v === 0) return <span className="zc-text-zinc-400">No earn</span>;
				return `${v.toFixed(2)} pt/$`;
			},
		},
		{
			key: "threshold_orders",
			label: "Orders ≥",
			align: "right",
			cellClassName: "zc-tabular-nums",
			render: (row) => row.threshold_orders !== null && row.threshold_orders !== undefined
				? number(row.threshold_orders)
				: "—",
		},
		{
			key: "threshold_spend",
			label: "Spend ≥",
			align: "right",
			cellClassName: "zc-tabular-nums",
			render: (row) => row.threshold_spend !== null && row.threshold_spend !== undefined
				? money(row.threshold_spend)
				: "—",
		},
		{
			key: "member_count",
			label: "Members",
			align: "right",
			cellClassName: "zc-tabular-nums",
			render: (row) => number(row.member_count),
		},
		{
			key: "sort_order",
			label: "Sort",
			align: "center",
			cellClassName: "zc-text-zinc-500 zc-tabular-nums",
		},
		{
			key: "_actions",
			label: "Actions",
			align: "right",
			stopPropagation: true,
			render: (row) => (
				<div className="zc-flex zc-justify-end">
					<RowActions row={row} onEdit={onEdit} />
				</div>
			),
		},
	];

	return (
		<DataTable
			columns={columns}
			rows={rows}
			rowKey={(r) => r.slug}
			loading={loading}
			error={error}
			empty="No tiers configured."
			onRowClick={onOpenMembers}
		/>
	);
}
