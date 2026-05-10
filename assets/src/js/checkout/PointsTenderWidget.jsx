import { useEffect, useState } from "react";
import { useApiQuery, useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/js/shared/ui/card.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { money, number } from "@/js/shared/utils/format.js";

/**
 * "Use your points" widget that renders on the WC cart page. Reads the user's
 * balance + cart context from `GET /points/applicable`, lets them slide a
 * value, posts to `POST /points/apply`. The server-side fee hook handles the
 * cart total; we just reflect the stored amount and refresh on success so WC
 * re-renders the totals.
 *
 * Design notes:
 *   - We DO NOT optimistically update the slider. The server clamps to the
 *     current cart total and returns the resolved amount; we display that
 *     as authoritative so the UI never shows a value the cart can't honor.
 *   - "Use" / "Remove" are explicit clicks, not auto-apply on slide. Avoids
 *     spamming /apply with every slider tick.
 */
export function PointsTenderWidget() {
	const summary = useApiQuery("/points/applicable");

	const apply = useApiMutation("post", "/points/apply", {
		invalidate: ["/points/applicable", "/points/me"],
		onSuccess: () => triggerCartRefresh(),
	});

	const clear = useApiMutation("delete", "/points/apply", {
		invalidate: ["/points/applicable", "/points/me"],
		onSuccess: () => triggerCartRefresh(),
	});

	if (summary.isLoading) {
		return (
			<Card className="zc-mb-4">
				<CardHeader>
					<Skeleton className="zc-h-5 zc-w-40" />
					<Skeleton className="zc-h-4 zc-w-64" />
				</CardHeader>
				<CardContent>
					<Skeleton className="zc-h-2 zc-w-full" />
				</CardContent>
			</Card>
		);
	}

	if (summary.isError || !summary.data) return null;

	const d = summary.data;
	const { balance, applied, max_applicable, redemption_rate, min_redemption } = d;

	// Don't render the widget at all when the user has no usable balance —
	// it'd be visual clutter on the cart for a customer with nothing to spend.
	if (max_applicable < min_redemption && applied === 0) {
		return balance > 0 ? (
			<Card className="zc-mb-4">
				<CardHeader>
					<CardTitle className="zc-text-base">Use your points</CardTitle>
					<CardDescription>
						You have <span className="zc-font-medium">{number(balance)} pts</span> ({money(balance / redemption_rate)}). Earn at least {min_redemption} more to apply them at checkout.
					</CardDescription>
				</CardHeader>
			</Card>
		) : null;
	}

	return (
		<Card className="zc-mb-4">
			<CardHeader>
				<CardTitle className="zc-text-base">Use your points</CardTitle>
				<CardDescription>
					Balance: <span className="zc-font-medium">{number(balance)} pts</span> ({money(balance / redemption_rate)})
				</CardDescription>
			</CardHeader>
			<CardContent>
				{applied > 0 ? (
					<AppliedState
						applied={applied}
						appliedDollars={d.applied_dollars}
						onClear={() => clear.mutate({})}
						clearing={clear.isPending}
					/>
				) : (
					<ApplyForm
						max={max_applicable}
						min={min_redemption}
						rate={redemption_rate}
						onApply={(points) => apply.mutate({ points })}
						applying={apply.isPending}
						error={apply.error?.message}
					/>
				)}
			</CardContent>
		</Card>
	);
}

function AppliedState({ applied, appliedDollars, onClear, clearing }) {
	return (
		<div className="zc-flex zc-items-center zc-justify-between zc-gap-3 zc-rounded-lg zc-border zc-border-emerald-200 zc-bg-emerald-50 zc-p-3">
			<div>
				<p className="zc-text-sm zc-font-medium zc-text-emerald-900">
					{number(applied)} pts applied · {money(appliedDollars)} off
				</p>
				<p className="zc-mt-0.5 zc-text-xs zc-text-emerald-800">
					Will be deducted when this order completes.
				</p>
			</div>
			<Button variant="outline" size="sm" onClick={onClear} loading={clearing}>
				Remove
			</Button>
		</div>
	);
}

function ApplyForm({ max, min, rate, onApply, applying, error }) {
	// Default the slider to the next multiple of `rate` ≥ min, capped at max.
	const initial = Math.max(min, Math.min(max, Math.floor(max / rate) * rate));
	const [points, setPoints] = useState(initial);

	// If max changes (cart total shrank, balance dropped), keep the slider valid.
	useEffect(() => {
		if (points > max) setPoints(Math.floor(max / rate) * rate);
	}, [max, rate, points]);

	const dollars = points / rate;

	return (
		<div className="zc-space-y-3">
			<div>
				<input
					type="range"
					min={min}
					max={max}
					step={rate}
					value={points}
					onChange={(e) => setPoints(Number(e.target.value))}
					className="zc-w-full zc-cursor-pointer zc-accent-zinc-900"
					aria-label="Points to apply"
				/>
				<div className="zc-mt-1 zc-flex zc-justify-between zc-text-xs zc-text-zinc-500">
					<span>{number(min)} pts</span>
					<span>{number(max)} pts</span>
				</div>
			</div>

			<div className="zc-flex zc-items-baseline zc-justify-between zc-rounded-lg zc-bg-zinc-50 zc-px-3 zc-py-2">
				<span className="zc-text-sm zc-font-semibold zc-text-zinc-900">
					{number(points)} pts
				</span>
				<span className="zc-text-sm zc-font-semibold zc-text-emerald-700">
					{money(dollars)} off
				</span>
			</div>

			<Button
				onClick={() => onApply(points)}
				loading={applying}
				disabled={applying || points < min || points > max}
				className="zc-w-full"
			>
				Use {number(points)} pts
			</Button>

			{error && <p className="zc-text-sm zc-text-rose-700">{error}</p>}
		</div>
	);
}

/**
 * Asks every interested party to re-read the cart/checkout so the new fee
 * line shows up without a full page reload.
 *
 *   1. **ai-zippy theme React checkout** (primary) — listens to the custom
 *      DOM event `zippy-crm:tender-changed`. The theme's CheckoutApp re-fetches
 *      cart data via WC Store API on this event.
 *
 *   2. **WC blocks-based checkout** — handled implicitly: the Store API
 *      client revalidates cached cart data on most user interactions. We
 *      don't need to trigger anything for this one.
 *
 *   3. **Classic WC PHP checkout** — fires the `update_checkout` jQuery event
 *      so WC's own checkout fragments re-render with the new fee line. This
 *      is the standard WC convention; safe even when no listener is wired.
 *
 * Event name: `zippy-crm:tender-changed` (was `zippy-crm:cart-tender-changed`
 * before v1.13.0 — renamed when redemption moved off the cart page).
 */
function triggerCartRefresh() {
	window.dispatchEvent(new CustomEvent("zippy-crm:tender-changed"));

	// Classic WC checkout listens to this jQuery event to recompute totals.
	// jQuery is loaded on the WC checkout page; bail safely if absent.
	if (typeof window.jQuery === "function") {
		window.jQuery(document.body).trigger("update_checkout");
	}
}
