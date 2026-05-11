import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
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
/**
 * `onClaimed` is called with the claim payload + the voucher metadata when
 * the claim succeeds. The parent (AvailableList) holds the modal state so
 * it survives this card unmounting after the available-list refetch removes
 * the claimed voucher.
 */
export function VoucherCard({ voucher, onClaimed }) {
	const [error, setError] = useState(null);

	const mutation = useApiMutation("post", `/vouchers/${voucher.id}/claim`, {
		invalidate: ["/vouchers", "/vouchers/claims"],
		onSuccess: (data) => { setError(null); onClaimed?.({ ...data, voucher }); },
		onError:   (err)  => setError(err.message ?? "Could not claim voucher."),
	});

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
					<Button
						size="sm"
						onClick={() => mutation.mutate({})}
						disabled={mutation.isPending}
						loading={mutation.isPending}
					>
						Claim voucher
					</Button>
					{error ? (
						<p className="zc-mt-2 zc-text-xs zc-text-rose-700">{error}</p>
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

/**
 * Modal that shows the freshly claimed code. Mounted by AvailableList so it
 * survives the just-claimed VoucherCard unmounting (cache invalidation
 * removes it from the available list one tick after the claim succeeds, and
 * a portal alone doesn't help because React still owns the modal through
 * the unmounted parent — state has to live higher up the tree). Auto-copies
 * the code on open so the "click then it disappears" race is impossible —
 * the code is already in the paste buffer before the user even reads the
 * dialog.
 */
export function ClaimedCodeDialog({ claim, onClose }) {
	const [copied, setCopied] = useState(false);

	const copy = async () => {
		try {
			await navigator.clipboard?.writeText(claim.code);
			setCopied(true);
			setTimeout(() => setCopied(false), 1500);
		} catch { /* clipboard blocked — user can still copy manually */ }
	};

	// Best-effort auto-copy on open. Browsers usually allow this since the
	// modal is opened from a click handler. If it fails (Firefox in some
	// contexts, iframes), the visible Copy button is the fallback.
	useEffect(() => {
		copy();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	useEffect(() => {
		const onKey = (e) => { if (e.key === "Escape") onClose(); };
		window.addEventListener("keydown", onKey);
		return () => window.removeEventListener("keydown", onKey);
	}, [onClose]);

	return createPortal(
		<div className="zc-fixed zc-inset-0 zc-z-[100002] zc-flex zc-items-center zc-justify-center zc-p-4">
			<div onClick={onClose} className="zc-absolute zc-inset-0 zc-bg-zinc-900/40 zc-backdrop-blur-sm" aria-hidden />
			<div
				role="dialog"
				aria-modal="true"
				aria-labelledby="zc-claimed-title"
				className="zc-relative zc-w-full zc-max-w-md zc-overflow-hidden zc-rounded-xl zc-border zc-border-zinc-200 zc-bg-white zc-shadow-2xl"
			>
				<div className="zc-h-1 zc-w-full zc-bg-gradient-to-r zc-from-emerald-500 zc-via-emerald-400 zc-to-emerald-500" />
				<div className="zc-p-6">
					<div className="zc-flex zc-items-start zc-gap-3">
						<CheckIcon />
						<div className="zc-min-w-0 zc-flex-1">
							<h3 id="zc-claimed-title" className="zc-text-base zc-font-semibold zc-text-zinc-900">
								{claim.applied_to_cart ? "Applied to your cart" : "Voucher claimed"}
							</h3>
							<p className="zc-mt-0.5 zc-text-sm zc-text-zinc-600">
								{claim.applied_to_cart
									? "The discount is now active in your cart."
									: "Use this code at checkout to redeem your discount."}
							</p>
						</div>
					</div>

					<div className="zc-mt-5 zc-rounded-lg zc-border zc-border-dashed zc-border-emerald-300 zc-bg-emerald-50 zc-px-4 zc-py-4 zc-text-center">
						<p className="zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-[0.15em] zc-text-emerald-800">
							{claim.voucher.title}
						</p>
						<code className="zc-mt-2 zc-block zc-select-all zc-break-all zc-font-mono zc-text-2xl zc-font-bold zc-tracking-wider zc-text-emerald-900">
							{claim.code}
						</code>
					</div>

					<div className="zc-mt-5 zc-flex zc-items-center zc-justify-end zc-gap-2">
						<Button type="button" variant="ghost" onClick={onClose}>Close</Button>
						<Button type="button" onClick={copy}>
							{copied ? "Copied!" : "Copy code"}
						</Button>
					</div>
				</div>
			</div>
		</div>,
		document.body,
	);
}

function CheckIcon() {
	return (
		<span className="zc-flex zc-h-9 zc-w-9 zc-shrink-0 zc-items-center zc-justify-center zc-rounded-full zc-bg-emerald-100 zc-text-emerald-700">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
				<polyline points="20 6 9 17 4 12" />
			</svg>
		</span>
	);
}
