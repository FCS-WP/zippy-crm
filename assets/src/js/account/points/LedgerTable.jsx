import { Card, CardContent, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { date, money, number } from "@/js/shared/utils/format.js";

const TYPE_META = {
	earn: {
		label: "Earned",
		ring: "zc-ring-emerald-200 zc-bg-emerald-50 zc-text-emerald-700",
		icon: (
			<path d="M12 5v14M5 12l7-7 7 7" />
		),
	},
	redeem: {
		label: "Redeemed",
		ring: "zc-ring-sky-200 zc-bg-sky-50 zc-text-sky-700",
		icon: (
			<>
				<rect x="3" y="6" width="18" height="12" rx="2" />
				<path d="M3 12h18" />
			</>
		),
	},
	pending_redeem: {
		label: "Reserved",
		ring: "zc-ring-amber-200 zc-bg-amber-50 zc-text-amber-700",
		icon: (
			<>
				<rect x="5" y="11" width="14" height="9" rx="2" />
				<path d="M8 11V7a4 4 0 0 1 8 0v4" />
			</>
		),
	},
	expire: {
		label: "Expired",
		ring: "zc-ring-zinc-200 zc-bg-zinc-50 zc-text-zinc-600",
		icon: (
			<>
				<circle cx="12" cy="12" r="9" />
				<path d="M12 7v5l3 2" />
			</>
		),
	},
	adjust: {
		label: "Adjusted",
		ring: "zc-ring-amber-200 zc-bg-amber-50 zc-text-amber-700",
		icon: (
			<>
				<circle cx="12" cy="12" r="9" />
				<path d="M12 8v4M12 16h.01" />
			</>
		),
	},
};

export function LedgerTable({ query, page, onPageChange, redemptionRate = 20 }) {
	const { data, isLoading, isFetching, isError, error } = query;

	return (
		<Card>
			<CardHeader>
				<div className="zc-flex zc-items-center zc-justify-between zc-gap-3">
					<CardTitle>Activity</CardTitle>
					{data && data.total > 0 && (
						<span className="zc-text-sm zc-text-zinc-500">
							{data.total} {data.total === 1 ? "entry" : "entries"}
						</span>
					)}
				</div>
			</CardHeader>
			<CardContent>
				{isLoading ? (
					<LoadingRows />
				) : isError ? (
					<p className="zc-text-sm zc-text-rose-600">{error?.message ?? "Failed to load activity."}</p>
				) : !data || data.total === 0 ? (
					<EmptyState />
				) : (
					<>
						<ul className="zc-divide-y zc-divide-zinc-100">
							{data.items.map((row) => (
								<LedgerRow key={row.id} row={row} redemptionRate={redemptionRate} />
							))}
						</ul>
						{data.total_pages > 1 && (
							<Pagination
								data={data}
								page={page}
								onPageChange={onPageChange}
								busy={isFetching}
							/>
						)}
					</>
				)}
			</CardContent>
		</Card>
	);
}

function LedgerRow({ row, redemptionRate }) {
	const meta = TYPE_META[row.type] ?? TYPE_META.adjust;
	const isPending = row.type === "pending_redeem";
	const positive = row.points > 0;

	// Pending rows: description is the coupon code itself (rendered as a chip),
	// the points cell shows the reserved amount in amber, status sub-line says
	// what state the reservation is in.
	const primary = isPending
		? <CouponChip code={row.description ?? ""} />
		: (row.description ?? meta.label);

	const secondaryParts = [
		meta.label,
		date(row.created_at),
		row.order_id ? `Order #${row.order_id}` : null,
		isPending ? pendingStatusLabel(row.pending_status) : null,
	].filter(Boolean);

	return (
		<li className="zc-flex zc-items-center zc-gap-4 zc-py-3">
			<TypeIcon meta={meta} />

			<div className="zc-min-w-0 zc-flex-1">
				<div className="zc-truncate zc-text-sm zc-font-medium zc-text-zinc-900">
					{primary}
				</div>
				<p className="zc-mt-0.5 zc-flex zc-flex-wrap zc-items-center zc-gap-x-2 zc-text-xs zc-text-zinc-500">
					{secondaryParts.map((part, i) => (
						<span key={i} className="zc-flex zc-items-center zc-gap-x-2">
							{i > 0 && <span aria-hidden>·</span>}
							<span>{part}</span>
						</span>
					))}
				</p>
			</div>

			<div className="zc-text-right">
				{isPending ? (
					<>
						<p className="zc-text-base zc-font-semibold zc-text-amber-700">
							{number(row.reserved_points ?? 0)}
						</p>
						<p className="zc-mt-0.5 zc-text-xs zc-text-zinc-500">reserved</p>
					</>
				) : (
					<>
						<p className={`zc-text-base zc-font-semibold ${positive ? "zc-text-emerald-700" : "zc-text-rose-700"}`}>
							{positive ? "+" : ""}{number(row.points)}
						</p>
						{row.type === "redeem" && (
							<p className="zc-mt-0.5 zc-text-xs zc-text-zinc-500">
								{money(Math.abs(row.points) / redemptionRate)} off
							</p>
						)}
					</>
				)}
			</div>
		</li>
	);
}

function CouponChip({ code }) {
	return (
		<code className="zc-rounded zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-px-1.5 zc-py-0.5 zc-font-mono zc-text-xs zc-text-zinc-700">
			{code}
		</code>
	);
}

function pendingStatusLabel(status) {
	if (status === "active")   return "Pending — use within 24h";
	if (status === "consumed") return "Used";
	if (status === "expired")  return "Expired unused";
	return null;
}

function TypeIcon({ meta }) {
	return (
		<span
			className={`zc-flex zc-size-9 zc-shrink-0 zc-items-center zc-justify-center zc-rounded-full zc-ring-1 zc-ring-inset ${meta.ring}`}
			aria-hidden
		>
			<svg className="zc-size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
				{meta.icon}
			</svg>
		</span>
	);
}

function Pagination({ data, page, onPageChange, busy }) {
	return (
		<div className="zc-mt-4 zc-flex zc-items-center zc-justify-between">
			<span className="zc-text-sm zc-text-zinc-500">
				Page {data.page} of {data.total_pages}
			</span>
			<div className="zc-flex zc-gap-2">
				<Button variant="outline" size="sm" disabled={page <= 1 || busy} onClick={() => onPageChange(page - 1)}>
					Previous
				</Button>
				<Button variant="outline" size="sm" disabled={page >= data.total_pages || busy} onClick={() => onPageChange(page + 1)}>
					Next
				</Button>
			</div>
		</div>
	);
}

function LoadingRows() {
	return (
		<ul className="zc-divide-y zc-divide-zinc-100">
			{[0, 1, 2].map((i) => (
				<li key={i} className="zc-flex zc-items-center zc-gap-4 zc-py-3">
					<Skeleton className="zc-size-9 zc-rounded-full" />
					<div className="zc-flex-1 zc-space-y-2">
						<Skeleton className="zc-h-4 zc-w-1/2" />
						<Skeleton className="zc-h-3 zc-w-1/3" />
					</div>
					<Skeleton className="zc-h-5 zc-w-12" />
				</li>
			))}
		</ul>
	);
}

function EmptyState() {
	return (
		<div className="zc-rounded-lg zc-border zc-border-dashed zc-border-zinc-200 zc-px-6 zc-py-10 zc-text-center">
			<p className="zc-text-sm zc-font-medium zc-text-zinc-900">No activity yet</p>
			<p className="zc-mt-1 zc-text-sm zc-text-zinc-500">
				Place an order to start earning points.
			</p>
		</div>
	);
}
