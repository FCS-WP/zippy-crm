import { useState } from "react";
import { Card } from "@/js/shared/ui/card.jsx";
import { Badge } from "@/js/shared/ui/badge.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { date, isItemLevelType, isPercentType, money } from "@/js/shared/utils/format.js";

/**
 * Coupon-stub layout. Left vertical band shows the discount value + small
 * label; right side stacks title, optional description, optional meta row,
 * and the action button. Top-right shows the expiry chip so the customer
 * can scan all cards for "what expires soonest".
 *
 * Why ticket layout instead of the previous full-width gradient header:
 *   1. The header was eating ~25% of card height for redundant info
 *      (the discount is also implied by the title most of the time).
 *   2. Vertical band reads as a coupon stub which is the right metaphor.
 *   3. Frees the body for a primary CTA that doesn't span the entire card
 *      width — looks less like a divider, more like a button.
 */
export function VoucherCard({ voucher }) {
	const [result, setResult] = useState(null);

	const mutation = useApiMutation("post", `/vouchers/${voucher.id}/claim`, {
		invalidate: ["/vouchers", "/vouchers/claims"],
		onSuccess: (data) => setResult({ kind: "ok", data }),
		onError:   (err)  => setResult({ kind: "err", code: err.code, message: err.message }),
	});

	const claimed = result?.kind === "ok";

	return (
		<Card className="zc-flex zc-overflow-hidden">
			<DiscountStub voucher={voucher} />

			<div className="zc-flex zc-flex-1 zc-flex-col zc-gap-2 zc-p-4">
				<div className="zc-flex zc-items-start zc-justify-between zc-gap-3">
					<h3 className="zc-text-sm zc-font-semibold zc-leading-tight zc-text-zinc-900">
						{voucher.title}
					</h3>
					{voucher.expires_at ? (
						<Badge variant="muted" className="zc-shrink-0 zc-text-[10px]">
							Until {date(voucher.expires_at)}
						</Badge>
					) : null}
				</div>

				{voucher.description ? (
					<p className="zc-line-clamp-2 zc-text-xs zc-text-zinc-500">
						{voucher.description}
					</p>
				) : null}

				{(voucher.min_order_amount > 0 || voucher.remaining_uses !== null) ? (
					<div className="zc-flex zc-flex-wrap zc-gap-x-3 zc-gap-y-0.5 zc-text-[11px] zc-text-zinc-500">
						{voucher.min_order_amount > 0 ? (
							<span>Min order <strong className="zc-text-zinc-700">{money(voucher.min_order_amount)}</strong></span>
						) : null}
						{voucher.remaining_uses !== null ? (
							<span><strong className="zc-text-zinc-700">{voucher.remaining_uses}</strong> uses left</span>
						) : null}
					</div>
				) : null}

				<div className="zc-mt-auto zc-pt-2">
					{claimed ? (
						<ClaimedNotice data={result.data} />
					) : (
						<Button
							size="sm"
							onClick={() => mutation.mutate({})}
							disabled={mutation.isPending}
							loading={mutation.isPending}
						>
							Claim voucher
						</Button>
					)}
					{result?.kind === "err" ? (
						<p className="zc-mt-2 zc-text-xs zc-text-rose-700">{result.message}</p>
					) : null}
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
		<div
			className={[
				"zc-flex zc-w-24 zc-shrink-0 zc-flex-col zc-items-center zc-justify-center zc-gap-1 zc-px-3 zc-py-4 zc-text-white",
				"zc-bg-gradient-to-br",
				isPercent
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

function ClaimedNotice({ data }) {
	const [copied, setCopied] = useState(false);
	const copy = async () => {
		try {
			await navigator.clipboard?.writeText(data.code);
			setCopied(true);
			setTimeout(() => setCopied(false), 1500);
		} catch { /* clipboard blocked */ }
	};

	return (
		<div className="zc-rounded-md zc-border zc-border-emerald-200 zc-bg-emerald-50 zc-p-2">
			<p className="zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wider zc-text-emerald-800">
				{data.applied_to_cart ? "Applied to your cart" : "Code claimed"}
			</p>
			<div className="zc-mt-1.5 zc-flex zc-items-center zc-justify-between zc-gap-2">
				<code className="zc-rounded zc-border zc-border-emerald-300 zc-bg-white zc-px-1.5 zc-py-0.5 zc-font-mono zc-text-xs zc-font-semibold zc-text-emerald-900">
					{data.code}
				</code>
				<Button size="sm" variant="outline" onClick={copy}>
					{copied ? "Copied" : "Copy"}
				</Button>
			</div>
		</div>
	);
}
