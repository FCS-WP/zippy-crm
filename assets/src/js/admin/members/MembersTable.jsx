import { DataTable } from "@/js/shared/components/DataTable.jsx";
import { date, number } from "@/js/shared/utils/format.js";
import { LevelBadge, StatusBadge } from "./LevelBadge.jsx";
import { RowActions } from "./RowActions.jsx";

/**
 * Thin column-config wrapper around the shared DataTable. Sortable headers
 * are wired by passing `sort`/`onSort` through to the shared component.
 */
export function MembersTable({ rows, sort, onSort, loading, error, onView, onChangeLevel, onAdjustPoints }) {
	const columns = [
		{
			key: "display_name",
			label: "User",
			sortable: true,
			render: (row) => (
				<>
					<div className="zc-font-medium zc-text-zinc-900">
						{row.display_name || row.user_login}
					</div>
					<div className="zc-text-xs zc-text-zinc-500">{row.user_email}</div>
				</>
			),
		},
		{
			key: "membership_level",
			label: "Level",
			sortable: true,
			render: (row) => <LevelBadge level={row.level} />,
		},
		{
			key: "membership_status",
			label: "Status",
			sortable: true,
			render: (row) => <StatusBadge status={row.status} />,
		},
		{
			key: "points_balance",
			label: "Points",
			align: "right",
			sortable: true,
			cellClassName: "zc-font-medium zc-text-zinc-900 zc-tabular-nums",
			render: (row) => number(row.points_balance),
		},
		{
			key: "points_earned",
			label: "Earned",
			align: "right",
			sortable: true,
			cellClassName: "zc-tabular-nums",
			render: (row) => number(row.points_earned),
		},
		{
			key: "points_redeemed",
			label: "Redeemed",
			align: "right",
			cellClassName: "zc-tabular-nums",
			render: (row) => number(row.points_redeemed),
		},
		{
			key: "joined_at",
			label: "Joined",
			sortable: true,
			render: (row) => date(row.joined_at),
		},
		{
			key: "_actions",
			label: "Actions",
			align: "right",
			stopPropagation: true, // don't trigger row click when ⋯ is clicked
			render: (row) => (
				<div className="zc-flex zc-justify-end">
					<RowActions
						row={row}
						onView={onView}
						onChangeLevel={onChangeLevel}
						onAdjustPoints={onAdjustPoints}
					/>
				</div>
			),
		},
	];

	return (
		<DataTable
			columns={columns}
			rows={rows}
			rowKey={(r) => r.user_id}
			sort={sort}
			onSort={onSort}
			loading={loading}
			error={error}
			empty="No members match your filters."
			onRowClick={onView}
		/>
	);
}
