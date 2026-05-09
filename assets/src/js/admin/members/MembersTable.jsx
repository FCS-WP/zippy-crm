import { Card } from "@/js/shared/ui/card.jsx";
import { date, number } from "@/js/shared/utils/format.js";
import { LevelBadge, StatusBadge } from "./LevelBadge.jsx";
import { RowActions } from "./RowActions.jsx";

export function MembersTable({ rows, onView, onChangeLevel, onAdjustPoints }) {
	return (
		<Card className="zc-overflow-hidden">
			<div className="zc-overflow-x-auto">
				<table className="zc-w-full zc-text-left zc-text-sm">
					<thead className="zc-border-b zc-border-zinc-200 zc-bg-zinc-50 zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
						<tr>
							<Th>User</Th>
							<Th>Level</Th>
							<Th>Status</Th>
							<Th align="right">Points</Th>
							<Th align="right">Earned</Th>
							<Th align="right">Redeemed</Th>
							<Th>Joined</Th>
							<Th align="right">Actions</Th>
						</tr>
					</thead>
					<tbody>
						{rows.map((row) => (
							<tr key={row.user_id} className="zc-border-b zc-border-zinc-100 last:zc-border-b-0 hover:zc-bg-zinc-50/50">
								<Td>
									<div className="zc-font-medium zc-text-zinc-900">
										{row.display_name || row.user_login}
									</div>
									<div className="zc-text-xs zc-text-zinc-500">{row.user_email}</div>
								</Td>
								<Td><LevelBadge level={row.level} /></Td>
								<Td><StatusBadge status={row.status} /></Td>
								<Td align="right" className="zc-font-medium zc-text-zinc-900">{number(row.points_balance)}</Td>
								<Td align="right" className="zc-text-zinc-600">{number(row.points_earned)}</Td>
								<Td align="right" className="zc-text-zinc-600">{number(row.points_redeemed)}</Td>
								<Td>{date(row.joined_at)}</Td>
								<Td align="right">
									<div className="zc-flex zc-justify-end">
										<RowActions
											row={row}
											onView={onView}
											onChangeLevel={onChangeLevel}
											onAdjustPoints={onAdjustPoints}
										/>
									</div>
								</Td>
							</tr>
						))}
					</tbody>
				</table>
			</div>
		</Card>
	);
}

function Th({ children, align = "left" }) {
	return (
		<th
			className={`zc-px-4 zc-py-3 zc-font-semibold ${align === "right" ? "zc-text-right" : ""}`}
			scope="col"
		>
			{children}
		</th>
	);
}

function Td({ children, align = "left", className = "" }) {
	return (
		<td className={`zc-px-4 zc-py-3 zc-align-middle ${align === "right" ? "zc-text-right" : ""} ${className}`}>
			{children}
		</td>
	);
}
