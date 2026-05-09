import { Card } from "@/js/shared/ui/card.jsx";
import { dateTime, number } from "@/js/shared/utils/format.js";
import { TypeBadge } from "./TypeBadge.jsx";

export function LedgerTable({ rows }) {
	return (
		<Card className="zc-overflow-hidden">
			<div className="zc-overflow-x-auto">
				<table className="zc-w-full zc-text-left zc-text-sm">
					<thead className="zc-border-b zc-border-zinc-200 zc-bg-zinc-50 zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
						<tr>
							<Th>When</Th>
							<Th>User</Th>
							<Th>Type</Th>
							<Th align="right">Points</Th>
							<Th>Description</Th>
							<Th>Order</Th>
						</tr>
					</thead>
					<tbody>
						{rows.map((row) => (
							<tr key={row.id} className="zc-border-b zc-border-zinc-100 last:zc-border-b-0 hover:zc-bg-zinc-50/50">
								<Td className="zc-whitespace-nowrap zc-text-xs zc-text-zinc-500">{dateTime(row.created_at)}</Td>
								<Td>
									{row.user_login || row.display_name ? (
										<>
											<div className="zc-font-medium zc-text-zinc-900">
												{row.display_name || row.user_login}
											</div>
											<div className="zc-text-xs zc-text-zinc-500">{row.user_email}</div>
										</>
									) : (
										<span className="zc-text-xs zc-italic zc-text-zinc-400">deleted user #{row.user_id}</span>
									)}
								</Td>
								<Td><TypeBadge type={row.type} /></Td>
								<Td align="right" className={[
									"zc-whitespace-nowrap zc-font-medium",
									row.points > 0 ? "zc-text-emerald-700" : row.points < 0 ? "zc-text-rose-700" : "zc-text-zinc-700",
								].join(" ")}>
									{row.points > 0 ? "+" : ""}{number(row.points)}
								</Td>
								<Td className="zc-max-w-md zc-truncate zc-text-zinc-600">{row.description || "—"}</Td>
								<Td>{row.order_id ? <code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-xs zc-text-zinc-700">#{row.order_id}</code> : "—"}</Td>
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
