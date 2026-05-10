import { Badge } from "@/js/shared/ui/badge.jsx";
import { DataTable } from "@/js/shared/components/DataTable.jsx";
import { date, number } from "@/js/shared/utils/format.js";
import { LevelBadge, StatusBadge } from "../members/LevelBadge.jsx";

/**
 * Users table — every non-admin WP user with their CRM coverage. Clean
 * "Not a member" pill instead of a level badge for users who have never
 * triggered membership creation (auto-seeded on woocommerce_created_customer
 * + on first My Account visit, so this set is mostly subscribers / contributors
 * / users created via wp_create_user).
 */
export function UsersTable({ rows, loading, error }) {
	const columns = [
		{
			key: "user",
			label: "User",
			render: (row) => (
				<>
					<div className="zc-font-medium zc-text-zinc-900">
						{row.display_name || row.user_login}
					</div>
					<div className="zc-text-xs zc-text-zinc-500">
						{row.user_email}
						<span className="zc-ml-1.5 zc-text-zinc-400">@{row.user_login}</span>
					</div>
				</>
			),
		},
		{
			key: "membership",
			label: "Membership",
			render: (row) => row.has_membership ? (
				<div className="zc-flex zc-items-center zc-gap-2">
					<LevelBadge level={row.level} />
					<StatusBadge status={row.status} />
				</div>
			) : (
				<Badge variant="muted">Not a member</Badge>
			),
		},
		{
			key: "points_balance",
			label: "Points",
			align: "right",
			cellClassName: "zc-tabular-nums",
			render: (row) => row.has_membership ? (
				<span className="zc-font-medium zc-text-zinc-900">{number(row.points_balance)}</span>
			) : (
				<span className="zc-text-zinc-400">—</span>
			),
		},
		{
			key: "registered_at",
			label: "Registered",
			cellClassName: "zc-whitespace-nowrap zc-text-xs zc-text-zinc-500",
			render: (row) => date(row.registered_at),
		},
	];

	return (
		<DataTable
			columns={columns}
			rows={rows}
			rowKey={(r) => r.user_id}
			loading={loading}
			error={error}
			empty="No users match your filters."
		/>
	);
}
