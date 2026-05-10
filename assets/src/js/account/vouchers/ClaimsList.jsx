import { useState } from "react";
import { Card } from "@/js/shared/ui/card.jsx";
import { Badge } from "@/js/shared/ui/badge.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";
import { date, isItemLevelType, isPercentType, money } from "@/js/shared/utils/format.js";

/**
 * Mirrors the new ticket-stub VoucherCard so the Available + My Claims
 * grids feel like one family. Status pill replaces the Claim button;
 * a copy-code chip replaces the value-meta row when the claim is still usable.
 */

const STATUS_VARIANT = {
	claimed: "info",
	used:    "success",
	expired: "muted",
};

const STATUS_LABEL = {
	claimed: "Ready to use",
	used:    "Used",
	expired: "Expired",
};

const STATUS_ORDER = { claimed: 0, used: 1, expired: 2 };

export function ClaimsList({ query }) {
	const { data, isError, error } = query;

	if (isError)
		return <p className="zc-text-rose-600">{error?.message ?? "Failed to load claims."}</p>;

	const items = data?.items ?? [];
	if (items.length === 0) {
		return (
			<EmptyState
				title="No claims yet"
				description="Vouchers you claim will appear here."
			/>
		);
	}

	const sorted = [...items].sort(
		(a, b) => (STATUS_ORDER[a.status] ?? 3) - (STATUS_ORDER[b.status] ?? 3),
	);

	return (
		<div className="zc-grid zc-gap-4 sm:zc-grid-cols-2">
			{sorted.map((claim) => <ClaimCard key={claim.id} claim={claim} />)}
		</div>
	);
}

function ClaimCard({ claim }) {
	const isUsable = claim.status === "claimed";
	const dim      = !isUsable;

	return (
		<Card className={`zc-flex zc-overflow-hidden ${dim ? "zc-opacity-75" : ""}`}>
			<DiscountStub voucher={claim} dim={dim} />

			<div className="zc-flex zc-flex-1 zc-flex-col zc-gap-2 zc-p-4">
				<div className="zc-flex zc-items-start zc-justify-between zc-gap-3">
					<h3 className="zc-text-sm zc-font-semibold zc-leading-tight zc-text-zinc-900">
						{claim.title}
					</h3>
					<Badge variant={STATUS_VARIANT[claim.status] ?? "muted"} className="zc-shrink-0 zc-text-[10px]">
						{STATUS_LABEL[claim.status] ?? claim.status}
					</Badge>
				</div>

				{claim.description ? (
					<p className="zc-line-clamp-2 zc-text-xs zc-text-zinc-500">
						{claim.description}
					</p>
				) : null}

				<MetaRow claim={claim} />

				<div className="zc-mt-auto zc-pt-2">
					{isUsable
						? <CodeChip code={claim.code} />
						: <UsedFootnote claim={claim} />}
				</div>
			</div>
		</Card>
	);
}

function DiscountStub({ voucher, dim }) {
	const isPercent   = isPercentType(voucher.discount_type);
	const isItemLevel = isItemLevelType(voucher.discount_type);
	const headline    = isPercent
		? `${Math.round(voucher.discount_value)}%`
		: money(voucher.discount_value);
	const suffix = isItemLevel ? "off item" : (isPercent ? "off" : "off cart");

	return (
		<div
			className={[
				"zc-flex zc-w-24 zc-shrink-0 zc-flex-col zc-items-center zc-justify-center zc-gap-1 zc-px-3 zc-py-4 zc-text-white",
				"zc-bg-gradient-to-br",
				dim
					? "zc-from-zinc-500 zc-via-zinc-500 zc-to-zinc-600"
					: isPercent
						? "zc-from-fuchsia-700 zc-via-fuchsia-600 zc-to-rose-600"
						: "zc-from-zinc-900 zc-via-zinc-800 zc-to-zinc-700",
			].join(" ")}
		>
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
		claim.used_at  ? [ "Used",     date(claim.used_at)    ] : null,
		claim.order_id ? [ "Order",    `#${claim.order_id}`   ] : null,
		claim.expires_at && claim.status === "claimed"
			? [ "Expires", date(claim.expires_at) ]
			: null,
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

function CodeChip({ code }) {
	const [copied, setCopied] = useState(false);
	const copy = async () => {
		try {
			await navigator.clipboard?.writeText(code);
			setCopied(true);
			setTimeout(() => setCopied(false), 1500);
		} catch { /* clipboard blocked */ }
	};

	return (
		<div className="zc-flex zc-items-center zc-justify-between zc-gap-2 zc-rounded-md zc-border zc-border-dashed zc-border-zinc-300 zc-bg-zinc-50 zc-px-2 zc-py-1.5">
			<code className="zc-font-mono zc-text-xs zc-font-semibold zc-text-zinc-900">{code}</code>
			<Button size="sm" variant="outline" onClick={copy}>
				{copied ? "Copied" : "Copy"}
			</Button>
		</div>
	);
}

function UsedFootnote({ claim }) {
	if (claim.status === "used")
		return (
			<p className="zc-text-[11px] zc-text-zinc-500">
				Used on order #{claim.order_id ?? "—"}
				{claim.used_at && <> · {date(claim.used_at)}</>}
			</p>
		);
	return <p className="zc-text-[11px] zc-text-zinc-500">No longer redeemable.</p>;
}
