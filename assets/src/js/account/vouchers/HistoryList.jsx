import { Card } from "@/js/shared/ui/card.jsx";
import { Badge } from "@/js/shared/ui/badge.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";
import { date, isItemLevelType, isPercentType, money } from "@/js/shared/utils/format.js";

/**
 * Customer-facing voucher history. Shows claims that are no longer usable:
 *   - used        → redeemed on an order (shows order #)
 *   - expired     → voucher's expiry date passed before customer used it
 *   - revoked     → admin removed the WC coupon, OR tier downgrade, OR future
 *                   admin-initiated revoke. The PHP shape function returns a
 *                   `reason_label` string we render verbatim — keeps the
 *                   labelling logic single-source.
 *
 * Always dimmed; no Copy chip (codes are dead). The bottom-right footnote
 * tells the customer why this row is here.
 */

const DISPLAY_VARIANT = {
	used:    "success",
	expired: "muted",
	revoked: "muted",
};

const DISPLAY_LABEL = {
	used:    "Used",
	expired: "Expired",
	revoked: "Revoked",
};

export function HistoryList({ query, onLoadMore, hasMore, loadingMore }) {
	const { data, isError, error } = query;

	if (isError) {
		return <p className="zc-text-rose-600">{error?.message ?? "Failed to load history."}</p>;
	}

	const items = data?.items ?? [];
	if (items.length === 0) {
		return (
			<EmptyState
				title="No history yet"
				description="Vouchers you've used or that have expired will appear here."
			/>
		);
	}

	return (
		<div className="zc-space-y-4">
			<div className="zc-grid zc-gap-4 sm:zc-grid-cols-2">
				{items.map((claim) => <HistoryCard key={claim.id} claim={claim} />)}
			</div>

			{hasMore ? (
				<div className="zc-flex zc-justify-center">
					<Button variant="outline" onClick={onLoadMore} loading={loadingMore} disabled={loadingMore}>
						Load more
					</Button>
				</div>
			) : null}
		</div>
	);
}

function HistoryCard({ claim }) {
	const status = claim.display_status ?? "expired";

	return (
		<Card className="zc-flex zc-overflow-hidden zc-opacity-75">
			<DiscountStub voucher={claim} />

			<div className="zc-flex zc-flex-1 zc-flex-col zc-gap-2 zc-p-4">
				<div className="zc-flex zc-items-start zc-justify-between zc-gap-3">
					<h3 className="zc-text-sm zc-font-semibold zc-leading-tight zc-text-zinc-900">
						{claim.title}
					</h3>
					<Badge variant={DISPLAY_VARIANT[status] ?? "muted"} className="zc-shrink-0 zc-text-[10px]">
						{DISPLAY_LABEL[status] ?? status}
					</Badge>
				</div>

				{claim.description ? (
					<p className="zc-line-clamp-2 zc-text-xs zc-text-zinc-500">
						{claim.description}
					</p>
				) : null}

				<MetaRow claim={claim} />

				<div className="zc-mt-auto zc-pt-2">
					<ReasonFootnote claim={claim} />
				</div>
			</div>
		</Card>
	);
}

function DiscountStub({ voucher }) {
	const isPercent   = isPercentType(voucher.discount_type);
	const isItemLevel = isItemLevelType(voucher.discount_type);
	const headline    = isPercent
		? `${Math.round(voucher.discount_value)}%`
		: money(voucher.discount_value);
	const suffix = isItemLevel ? "off item" : (isPercent ? "off" : "off cart");

	return (
		<div className="zc-flex zc-w-24 zc-shrink-0 zc-flex-col zc-items-center zc-justify-center zc-gap-1 zc-bg-gradient-to-br zc-from-zinc-500 zc-via-zinc-500 zc-to-zinc-600 zc-px-3 zc-py-4 zc-text-white">
			<span className="zc-text-2xl zc-font-bold zc-leading-none">{headline}</span>
			<span className="zc-text-[10px] zc-uppercase zc-tracking-wider zc-text-white/80">
				{suffix}
			</span>
		</div>
	);
}

function MetaRow({ claim }) {
	const items = [
		[ "Claimed", date(claim.claimed_at) ],
		claim.used_at  ? [ "Used",  date(claim.used_at)  ] : null,
		claim.order_id ? [ "Order", `#${claim.order_id}` ] : null,
	].filter(Boolean);
	if (items.length === 0) return null;
	return (
		<div className="zc-flex zc-flex-wrap zc-gap-x-3 zc-gap-y-0.5 zc-text-[11px] zc-text-zinc-500">
			{items.map(([ label, value ]) => (
				<span key={label}>
					{label} <strong className="zc-text-zinc-700">{value}</strong>
				</span>
			))}
		</div>
	);
}

function ReasonFootnote({ claim }) {
	if (claim.display_status === "used") {
		return (
			<p className="zc-text-[11px] zc-text-zinc-500">
				Used on order #{claim.order_id ?? "—"}
				{claim.used_at && <> · {date(claim.used_at)}</>}
			</p>
		);
	}
	// reason_label is server-derived for non-used rows; fall back gracefully.
	return (
		<p className="zc-text-[11px] zc-text-zinc-500">
			{claim.reason_label ?? "No longer available"}
		</p>
	);
}
