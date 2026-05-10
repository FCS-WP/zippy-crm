import { DataTable } from "@/js/shared/components/DataTable.jsx";
import { dateTime } from "@/js/shared/utils/format.js";
import { EventBadge } from "./EventBadge.jsx";
import { MetaCell } from "./MetaCell.jsx";

/**
 * Audit log table. Read-only — no row actions, since the table itself IS
 * the action history. Server-side sort is fixed (created_at DESC + id) so
 * we don't expose sortable headers; admin filters through the toolbar.
 */
export function AuditTable({ rows, loading, error }) {
	const columns = [
		{
			key: "created_at",
			label: "When",
			cellClassName: "zc-whitespace-nowrap zc-text-xs zc-text-zinc-500",
			render: (row) => dateTime(row.created_at),
		},
		{
			key: "event",
			label: "Event",
			render: (row) => <EventBadge event={row.event} />,
		},
		{
			key: "actor",
			label: "Admin",
			render: (row) => row.actor?.id ? (
				<>
					<div className="zc-font-medium zc-text-zinc-900">
						{row.actor.display_name || row.actor.login}
					</div>
					<div className="zc-text-xs zc-text-zinc-500">#{row.actor.id}</div>
				</>
			) : (
				<span className="zc-text-xs zc-italic zc-text-zinc-400">system</span>
			),
		},
		{
			key: "target",
			label: "Target",
			render: (row) => row.target?.id ? (
				<>
					<div className="zc-font-medium zc-text-zinc-900">
						{row.target.display_name || row.target.login}
					</div>
					<div className="zc-text-xs zc-text-zinc-500">#{row.target.id}</div>
				</>
			) : (
				<span className="zc-text-xs zc-italic zc-text-zinc-400">—</span>
			),
		},
		{
			key: "meta",
			label: "Detail",
			cellClassName: "zc-max-w-md",
			render: (row) => <MetaCell event={row.event} meta={row.meta} />,
		},
	];

	return (
		<DataTable
			columns={columns}
			rows={rows}
			rowKey={(r) => r.id}
			loading={loading}
			error={error}
			empty="No audit entries match your filters."
		/>
	);
}
