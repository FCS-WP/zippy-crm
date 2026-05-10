import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { Badge } from "@/js/shared/ui/badge.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";
import { date, isItemLevelType, isPercentType, money } from "@/js/shared/utils/format.js";

/**
 * Mirrors VoucherCard's visual weight — same gradient stripe, same card
 * footprint — so the Available and My Claims grids feel like one family.
 * The status pill replaces the Claim button; a copy code chip replaces the
 * value descriptors when the claim is still usable.
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
	const isUsable  = claim.status === "claimed";
	const dim       = !isUsable;

	return (
		<Card className={`zc-flex zc-flex-col zc-overflow-hidden ${dim ? "zc-opacity-75" : ""}`}>
			<DiscountStripe voucher={claim} dim={dim} />

			<CardHeader>
				<div className="zc-flex zc-items-start zc-justify-between zc-gap-3">
					<CardTitle className="zc-text-base">{claim.title}</CardTitle>
					<Badge variant={STATUS_VARIANT[claim.status] ?? "muted"} className="zc-shrink-0">
						{STATUS_LABEL[claim.status] ?? claim.status}
					</Badge>
				</div>
			</CardHeader>

			<CardContent className="zc-flex zc-flex-1 zc-flex-col zc-gap-3">
				{claim.description && (
					<p className="zc-text-sm zc-text-zinc-600">{claim.description}</p>
				)}

				<MetaList claim={claim} />

				<div className="zc-mt-auto zc-pt-2">
					{isUsable
						? <CodeChip code={claim.code} />
						: <UsedFootnote claim={claim} />}
				</div>
			</CardContent>
		</Card>
	);
}

function DiscountStripe({ voucher, dim }) {
	const isPercent = isPercentType(voucher.discount_type);
	const isItemLevel = isItemLevelType(voucher.discount_type);
	const headline = isPercent
		? `${Math.round(voucher.discount_value)}%`
		: money(voucher.discount_value);
	const suffix = isItemLevel ? "off each item" : (isPercent ? "off" : "off cart");

	return (
		<div
			className={[
				"zc-flex zc-items-baseline zc-justify-between zc-gap-2 zc-px-6 zc-py-4 zc-text-white",
				"zc-bg-gradient-to-br",
				dim
					? "zc-from-zinc-500 zc-via-zinc-500 zc-to-zinc-600"
					: isPercent
						? "zc-from-fuchsia-700 zc-via-fuchsia-600 zc-to-rose-600"
						: "zc-from-zinc-900 zc-via-zinc-800 zc-to-zinc-700",
			].join(" ")}
		>
			<span className="zc-text-3xl zc-font-bold zc-leading-none">{headline}</span>
			<span className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-white/80">
				{suffix}
			</span>
		</div>
	);
}

function MetaList({ claim }) {
	const items = [
		[ "Claimed", date(claim.claimed_at) ],
		claim.used_at    ? [ "Used",     date(claim.used_at)    ] : null,
		claim.order_id   ? [ "Order",    `#${claim.order_id}`   ] : null,
		claim.expires_at && claim.status === "claimed"
			? [ "Expires", date(claim.expires_at) ]
			: null,
	].filter(Boolean);

	return (
		<dl className="zc-grid zc-grid-cols-2 zc-gap-3 zc-text-xs">
			{items.map(([label, value]) => (
				<div key={label}>
					<dt className="zc-text-zinc-500">{label}</dt>
					<dd className="zc-mt-0.5 zc-font-medium zc-text-zinc-900">{value}</dd>
				</div>
			))}
		</dl>
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
		<div className="zc-flex zc-items-center zc-justify-between zc-gap-2 zc-rounded-lg zc-border zc-border-dashed zc-border-zinc-300 zc-bg-zinc-50 zc-px-3 zc-py-2">
			<code className="zc-font-mono zc-text-sm zc-font-semibold zc-text-zinc-900">{code}</code>
			<Button size="sm" variant="outline" onClick={copy}>
				{copied ? "Copied" : "Copy code"}
			</Button>
		</div>
	);
}

function UsedFootnote({ claim }) {
	if (claim.status === "used")
		return (
			<p className="zc-text-xs zc-text-zinc-500">
				Used on order #{claim.order_id ?? "—"}
				{claim.used_at && <> · {date(claim.used_at)}</>}
			</p>
		);
	return <p className="zc-text-xs zc-text-zinc-500">No longer redeemable.</p>;
}
