import { Badge } from "@/js/shared/ui/badge.jsx";
import { Card } from "@/js/shared/ui/card.jsx";
import { money, number } from "@/js/shared/utils/format.js";
import { tierColor } from "@/js/shared/utils/tierColor.js";
import { RowActions } from "./RowActions.jsx";

export function TiersTable({ rows, onEdit }) {
	return (
		<Card className="zc-overflow-hidden">
			<div className="zc-overflow-x-auto">
				<table className="zc-w-full zc-text-left zc-text-sm">
					<thead className="zc-border-b zc-border-zinc-200 zc-bg-zinc-50 zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
						<tr>
							<Th>Tier</Th>
							<Th>Slug</Th>
							<Th align="right">Multiplier</Th>
							<Th align="right">Orders ≥</Th>
							<Th align="right">Spend ≥</Th>
							<Th align="right">Members</Th>
							<Th align="center">Sort</Th>
							<Th align="right">Actions</Th>
						</tr>
					</thead>
					<tbody>
						{rows.map((row) => {
							const color = tierColor(row.sort_order);
							return (
								<tr key={row.slug} className="zc-border-b zc-border-zinc-100 last:zc-border-b-0 hover:zc-bg-zinc-50/50">
									<Td>
										<div className="zc-flex zc-items-center zc-gap-2">
											<Badge variant={color.badge}>{row.label}</Badge>
											{row.is_admin_only ? (
												<Badge variant="muted" title="Auto-evaluator never sets this tier">
													Admin only
												</Badge>
											) : null}
										</div>
									</Td>
									<Td>
										<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-xs zc-font-mono zc-text-zinc-700">
											{row.slug}
										</code>
									</Td>
									<Td align="right" className="zc-font-medium zc-text-zinc-900">
										{Number(row.multiplier).toFixed(2)}×
									</Td>
									<Td align="right">
										{row.threshold_orders !== null && row.threshold_orders !== undefined
											? number(row.threshold_orders)
											: "—"}
									</Td>
									<Td align="right">
										{row.threshold_spend !== null && row.threshold_spend !== undefined
											? money(row.threshold_spend)
											: "—"}
									</Td>
									<Td align="right">{number(row.member_count)}</Td>
									<Td align="center" className="zc-text-zinc-500">{row.sort_order}</Td>
									<Td align="right">
										<div className="zc-flex zc-justify-end">
											<RowActions row={row} onEdit={onEdit} />
										</div>
									</Td>
								</tr>
							);
						})}
					</tbody>
				</table>
			</div>
		</Card>
	);
}

function Th({ children, align = "left" }) {
	return (
		<th
			className={`zc-px-4 zc-py-3 zc-font-semibold ${align === "right" ? "zc-text-right" : align === "center" ? "zc-text-center" : ""}`}
			scope="col"
		>
			{children}
		</th>
	);
}

function Td({ children, align = "left", className = "" }) {
	return (
		<td className={`zc-px-4 zc-py-3 zc-align-middle ${align === "right" ? "zc-text-right" : align === "center" ? "zc-text-center" : ""} ${className}`}>
			{children}
		</td>
	);
}
