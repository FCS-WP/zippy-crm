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
		onSuccess: (data) => triggerCartRefresh(data?.order_review_html),
	});

	const clear = useApiMutation("delete", "/points/apply", {
		invalidate: ["/points/applicable", "/points/me"],
		onSuccess: (data) => triggerCartRefresh(data?.order_review_html),
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

	// Is the cart-side cap stricter than the balance-side cap? When the
	// customer's balance > what the cart can absorb, we want to surface the
	// "max for this order" framing so they understand why the slider stops
	// short of their full balance. (When the balance is the binding cap,
	// "max" and "balance" are effectively the same number — no need to
	// surface the distinction.)
	const balanceInPoints = Math.floor(balance / redemption_rate) * redemption_rate;
	const cartCapBinding  = max_applicable < balanceInPoints;

	return (
		<Card className="zc-mb-4">
			<CardHeader>
				<CardTitle className="zc-text-base">Use your points</CardTitle>
				<CardDescription>
					You have <span className="zc-font-medium">{number(balance)} pts</span> ({money(balance / redemption_rate)} total)
					{cartCapBinding ? (
						<>
							<br />
							<span className="zc-text-xs zc-text-zinc-500">
								Up to <strong className="zc-text-zinc-700">{number(max_applicable)} pts ({money(max_applicable / redemption_rate)})</strong> can be applied to this order.
							</span>
						</>
					) : null}
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
						cartCapBinding={cartCapBinding}
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

function ApplyForm({ max, min, rate, onApply, applying, error, cartCapBinding }) {
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
					<span className={cartCapBinding ? "zc-text-amber-700" : ""}>
						{number(max)} pts{cartCapBinding ? " (max for this order)" : ""}
					</span>
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
 *      so WC's own checkout fragments re-render with the new fee line.
 *
 * Quirks we work around for the classic checkout:
 *   - WC's wc-checkout.js binds its handler to `$('form.checkout')` AND to
 *     `$(document.body)`, depending on WC version. Trigger on both so we
 *     don't depend on the version.
 *   - WC has internal AJAX queueing — calling update_checkout while a prior
 *     request is in-flight gets coalesced. If our trigger fires inside the
 *     same microtask as React's state commit, the click handler hasn't yet
 *     yielded and WC's prior init AJAX may still be running. Defer to the
 *     next animation frame so React commits first, then schedule a second
 *     trigger 350ms later as belt-and-braces in case the first was queued
 *     and dropped.
 *   - Some themes ship a noConflict jQuery; trust window.jQuery (the WC
 *     handle) which is the one wc-checkout.js binds against.
 *
 * Event name: `zippy-crm:tender-changed` (was `zippy-crm:cart-tender-changed`
 * before v1.13.0 — renamed when redemption moved off the cart page).
 */
function triggerCartRefresh(orderReviewHtml) {
	window.dispatchEvent(new CustomEvent("zippy-crm:tender-changed"));

	// Strategy 0 (WC Checkout block / Store-API flow): invalidate the
	// wc/store/cart resolution so the block refetches its totals from
	// /wc/store/v1/cart. Required when the page is rendered by
	// `<!-- wp:woocommerce/checkout /-->` rather than the legacy
	// [woocommerce_checkout] shortcode — the block doesn't listen for the
	// `update_checkout` jQuery event at all. No-op on classic checkouts where
	// wp.data isn't loaded.
	const wpData = window.wp?.data;
	if (wpData?.dispatch) {
		const cartStore = wpData.dispatch("wc/store/cart");
		cartStore?.invalidateResolutionForStore?.();
	}

	const $ = window.jQuery;

	// Strategy A (classic checkout, preferred): paste the server-rendered
	// order-review fragment that came with the apply/clear response. This
	// avoids the jQuery `update_checkout` round-trip entirely and eliminates
	// the race where two parallel `update_order_review` AJAX calls fight to
	// replace the fragment (the slower one wins and can carry stale data,
	// leaving a "Points redemption" row visible even after the customer
	// clicked Remove). The HTML already reflects the post-clear cart state.
	if (orderReviewHtml && typeof $ === "function") {
		const $target = $(".woocommerce-checkout-review-order-table");
		if ($target.length) {
			$target.replaceWith(orderReviewHtml);
			// Fire WC's standard "I updated the order review" event so any
			// listeners (payment gateway scripts, etc.) re-bind to the new DOM.
			$(document.body).trigger("updated_checkout");
			return;
		}
		// If the selector isn't on the page (e.g. block-based checkout), fall
		// through to the jQuery-trigger path below.
	}

	if (typeof $ !== "function") return;

	// Strategy 1: the standard jQuery trigger WC checkout.js listens for.
	// WC's handler is delegated on `body` — a form-level trigger would just
	// bubble there, so triggering on body once is enough. Triggering on both
	// (as an earlier draft did) caused two AJAX round-trips per call.
	const fireTrigger = () => $(document.body).trigger("update_checkout");

	// Strategy 2: direct AJAX to WC's update_order_review endpoint. The trigger
	// approach can silently no-op when WC hasn't finished its init sequence or
	// when a theme rebinds form.checkout. Calling the endpoint directly with
	// the same payload WC's checkout.js sends always refreshes the totals
	// fragment.
	const fireDirect = () => {
		const params = window.wc_checkout_params;
		if (!params || !params.wc_ajax_url) return;
		const url = params.wc_ajax_url.toString().replace("%%endpoint%%", "update_order_review");
		const $form = $("form.checkout");
		const data = {
			security:        params.update_order_review_nonce,
			payment_method:  $form.find('input[name="payment_method"]:checked').val() || "",
			country:         $form.find('#billing_country').val() || "",
			state:           $form.find('#billing_state').val() || "",
			postcode:        $form.find(':input[name="billing_postcode"]').val() || "",
			city:            $form.find(':input[name="billing_city"]').val() || "",
			address:         $form.find(':input[name="billing_address_1"]').val() || "",
			address_2:       $form.find(':input[name="billing_address_2"]').val() || "",
			s_country:       $form.find('#shipping_country').val() || "",
			s_state:         $form.find('#shipping_state').val() || "",
			s_postcode:      $form.find(':input[name="shipping_postcode"]').val() || "",
			s_city:          $form.find(':input[name="shipping_city"]').val() || "",
			s_address:       $form.find(':input[name="shipping_address_1"]').val() || "",
			s_address_2:     $form.find(':input[name="shipping_address_2"]').val() || "",
			has_full_address: $form.find('input#billing_address_1').val() ? "true" : "false",
			post_data:       $form.serialize(),
		};
		$.post(url, data).done((html) => {
			if (typeof html === "object" && html.fragments) {
				// WC returns fragments keyed by selector. Replace each in the DOM.
				$.each(html.fragments, (selector, content) => {
					$(selector).replaceWith(content);
				});
				$(document.body).trigger("updated_checkout", [html]);
			}
		});
	};

	// Trigger after React commits so any state change in this tick is
	// reflected in the form values WC reads. If WC's `updated_checkout`
	// event fires within 700ms we know checkout.js handled the trigger and
	// the fallback isn't needed. Otherwise we step in with a direct AJAX.
	let answered = false;
	$(document.body).one("updated_checkout", () => { answered = true; });
	requestAnimationFrame(fireTrigger);
	setTimeout(() => { if (!answered) fireDirect(); }, 700);
}
