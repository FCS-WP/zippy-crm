import { Card } from "@/js/shared/ui/card.jsx";
import { date, money, percent } from "@/js/shared/utils/format.js";
import { RowActions } from "./RowActions.jsx";
import { StatusBadge } from "./StatusBadge.jsx";

function discountLabel(row) {
	return row.discount_type === "percent"
		? percent(row.discount_value)
		: money(row.discount_value);
}

function quotaLabel(row) {
	if (!row.max_uses) return `${row.uses_count} / ∞`;
	return `${row.uses_count} / ${row.max_uses}`;
}

export function VouchersTable({ rows, onEdit, onClaims }) {
	return (
		<Card className="zc-overflow-hidden">
			<div className="zc-overflow-x-auto">
				<table className="zc-w-full zc-text-left zc-text-sm">
					<thead className="zc-border-b zc-border-zinc-200 zc-bg-zinc-50 zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">
						<tr>
							<Th>Code</Th>
							<Th>Title</Th>
							<Th>Discount</Th>
							<Th>Min order</Th>
							<Th>Used</Th>
							<Th>Status</Th>
							<Th>Expires</Th>
							<Th align="right">Actions</Th>
						</tr>
					</thead>
					<tbody>
						{rows.map((row) => (
							<tr key={row.id} className="zc-border-b zc-border-zinc-100 last:zc-border-b-0 hover:zc-bg-zinc-50/50">
								<Td>
									<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-xs zc-font-mono zc-text-zinc-800">
										{row.code}
									</code>
								</Td>
								<Td className="zc-max-w-xs">
									<div className="zc-font-medium zc-text-zinc-900">{row.title}</div>
									{row.description ? (
										<div className="zc-truncate zc-text-xs zc-text-zinc-500">{row.description}</div>
									) : null}
								</Td>
								<Td>{discountLabel(row)}</Td>
								<Td>{row.min_order_amount > 0 ? money(row.min_order_amount) : "—"}</Td>
								<Td>{quotaLabel(row)}</Td>
								<Td><StatusBadge status={row.status} /></Td>
								<Td>{row.expires_at ? date(row.expires_at) : "—"}</Td>
								<Td align="right">
									<RowActions row={row} onEdit={onEdit} onClaims={onClaims} />
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
