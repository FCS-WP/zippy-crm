import { DataTable } from "@/js/shared/components/DataTable.jsx";
import { dateTime, number } from "@/js/shared/utils/format.js";
import { TypeBadge } from "./TypeBadge.jsx";

/**
 * Recent points-ledger table. Sort is server-side by created_at DESC and
 * isn't user-controllable for now (the admin endpoint doesn't expose a
 * sort param yet — feature is more useful when filterable).
 */
export function LedgerTable({ rows, loading, error }) {
	const columns = [
		{
			key: "created_at",
			label: "When",
			cellClassName: "zc-whitespace-nowrap zc-text-xs zc-text-zinc-500",
			render: (row) => dateTime(row.created_at),
		},
		{
			key: "user",
			label: "User",
			render: (row) => row.user_login || row.display_name ? (
				<>
					<div className="zc-font-medium zc-text-zinc-900">
						{row.display_name || row.user_login}
					</div>
					<div className="zc-text-xs zc-text-zinc-500">{row.user_email}</div>
				</>
			) : (
				<span className="zc-text-xs zc-italic zc-text-zinc-400">
					deleted user #{row.user_id}
				</span>
			),
		},
		{
			key: "type",
			label: "Type",
			render: (row) => <TypeBadge type={row.type} />,
		},
		{
			key: "points",
			label: "Points",
			align: "right",
			render: (row) => (
				<span className={[
					"zc-whitespace-nowrap zc-font-medium zc-tabular-nums",
					row.points > 0 ? "zc-text-emerald-700" : row.points < 0 ? "zc-text-rose-700" : "zc-text-zinc-700",
				].join(" ")}>
					{row.points > 0 ? "+" : ""}{number(row.points)}
				</span>
			),
		},
		{
			key: "description",
			label: "Description",
			cellClassName: "zc-max-w-md zc-truncate zc-text-zinc-600",
			render: (row) => row.description || "—",
		},
		{
			key: "order_id",
			label: "Order",
			render: (row) => row.order_id ? (
				<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-xs zc-text-zinc-700">
					#{row.order_id}
				</code>
			) : "—",
		},
	];

	return (
		<DataTable
			columns={columns}
			rows={rows}
			rowKey={(r) => r.id}
			loading={loading}
			error={error}
			empty="No ledger activity yet."
		/>
	);
}
