import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { useApiQuery, useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/js/shared/ui/card.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { money, number } from "@/js/shared/utils/format.js";

/**
 * "Use your points" widget. Reads cart context from /points/applicable and
 * commits via /points/apply | /points/clear.
 *
 * Server is authoritative — we never optimistically render a value that may
 * not survive the cart's own clamping. Apply/Remove are explicit clicks so
 * we don't spam /apply on every slider tick.
 */
export function PointsTenderWidget() {
	const qc = useQueryClient();
	const summary = useApiQuery("/points/applicable");

	const apply = useApiMutation("post", "/points/apply", {
		invalidate: ["/points/applicable", "/points/me"],
		onSuccess: (data) => triggerCartRefresh(data?.order_review_html),
	});

	const clear = useApiMutation("delete", "/points/apply", {
		invalidate: ["/points/applicable", "/points/me"],
		onSuccess: (data) => triggerCartRefresh(data?.order_review_html),
	});

	// Refetch on cart-fragment changes. We listen to both events because
	// templates differ: the ai-zippy theme fires `update_checkout` from its
	// coupon flow, classic WC fires `updated_checkout` after its AJAX.
	useEffect(() => {
		const $ = window.jQuery;
		if (typeof $ !== "function") return;
		const onChanged = () => {
			qc.invalidateQueries({ queryKey: ["/points/applicable"] });
		};
		$(document.body).on("update_checkout updated_checkout", onChanged);
		return () => { $(document.body).off("update_checkout updated_checkout", onChanged); };
	}, [qc]);

	// One-shot toast captured into local state so dismiss sticks for the
	// session (server deletes the flag after one read).
	const [autoClearedNotice, setAutoClearedNotice] = useState(false);
	useEffect(() => {
		if (summary.data?.auto_cleared) setAutoClearedNotice(true);
	}, [summary.data?.auto_cleared]);

	// Wipe stale apply errors when the cart context changes — otherwise a
	// rejected apply ("coupon covers cart") stays visible after the customer
	// removes the coupon, even though the apply would now succeed.
	const cartTotal = summary.data?.cart_total;
	const maxApplicable = summary.data?.max_applicable;
	useEffect(() => {
		if (apply.isError) apply.reset();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [cartTotal, maxApplicable]);

	if (summary.isLoading) {
		return (
			<Card className="zc-mb-4 zc-mt-6">
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

	if (max_applicable < min_redemption && applied === 0) {
		return balance > 0 ? (
			<Card className="zc-mb-4 zc-mt-6">
				<CardHeader>
					<CardTitle className="zc-text-base">Use your points</CardTitle>
					<CardDescription>
						You have <span className="zc-font-medium">{number(balance)} pts</span> ({money(balance / redemption_rate)}). Earn at least {min_redemption} more to apply them at checkout.
					</CardDescription>
				</CardHeader>
			</Card>
		) : null;
	}

	// Only surface "max for this order" framing when the cart cap is the
	// binding one; otherwise it's redundant with the balance line.
	const balanceInPoints = Math.floor(balance / redemption_rate) * redemption_rate;
	const cartCapBinding  = max_applicable < balanceInPoints;

	return (
		<Card className="zc-mb-4 zc-mt-6">
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
				{autoClearedNotice ? (
					<AutoClearedNotice onDismiss={() => setAutoClearedNotice(false)} />
				) : null}
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

function AutoClearedNotice({ onDismiss }) {
	return (
		<div className="zc-mb-3 zc-flex zc-items-start zc-justify-between zc-gap-3 zc-rounded-lg zc-border zc-border-amber-200 zc-bg-amber-50 zc-p-3">
			<p className="zc-text-xs zc-text-amber-900">
				Your applied points were removed — the coupon covered the full total. Your balance was not debited.
			</p>
			<button
				type="button"
				onClick={onDismiss}
				className="zc-shrink-0 zc-text-amber-900 hover:zc-text-amber-700"
				aria-label="Dismiss"
			>
				×
			</button>
		</div>
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
	const initial = Math.max(min, Math.min(max, Math.floor(max / rate) * rate));
	const [points, setPoints] = useState(initial);

	// Re-clamp the slider when max changes underneath us.
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
 * Re-renders cart totals after our apply/clear. Different templates need
 * different signals; we try each in order and stop at the first that hits:
 *
 *   0. wp.data Store API — needed by the WC Checkout block (it doesn't
 *      listen to jQuery events at all).
 *   A1. ai-zippy theme — repaints `#az-checkout-totals` via the theme's
 *      `az_get_checkout_totals` admin-ajax. Must short-circuit here: the
 *      widget mount point lives inside `#order_review` on this template,
 *      and the classic strategies below would tear down our React root.
 *   A. Default WC classic — paste the server-rendered fragment we already
 *      received with the apply response, avoiding the `update_checkout`
 *      AJAX race (two responses fighting to replace the same fragment).
 *   1+2. Fallback for unknown templates — fire `update_checkout` and, if
 *      WC's `updated_checkout` doesn't fire back within 700ms, AJAX the
 *      `update_order_review` endpoint directly.
 *
 * The custom `zippy-crm:tender-changed` event is also dispatched for any
 * theme that wants to wire its own listener.
 */
function triggerCartRefresh(orderReviewHtml) {
	window.dispatchEvent(new CustomEvent("zippy-crm:tender-changed"));

	const wpData = window.wp?.data;
	if (wpData?.dispatch) {
		const cartStore = wpData.dispatch("wc/store/cart");
		cartStore?.invalidateResolutionForStore?.();
	}

	const $ = window.jQuery;

	// ai-zippy template: repaint its dedicated totals block. We must NOT also
	// fall through to the classic strategies — they'd tear down the widget's
	// React root which lives inside `#order_review` on this template.
	const ajaxUrl = window.zippyCrm?.ajaxUrl;
	if (typeof $ === "function" && $("#az-checkout-totals").length && ajaxUrl) {
		$.post(ajaxUrl, { action: "az_get_checkout_totals" }, (res) => {
			if (res && res.success && res.data && res.data.html) {
				$("#az-checkout-totals").html(res.data.html);
			}
		});
		return;
	}

	// Default classic WC: paste the pre-rendered fragment.
	if (orderReviewHtml && typeof $ === "function") {
		const $target = $(".woocommerce-checkout-review-order-table");
		if ($target.length) {
			$target.replaceWith(orderReviewHtml);
			$(document.body).trigger("updated_checkout");
			return;
		}
	}

	if (typeof $ !== "function") return;

	// Fallback: trigger WC's update_checkout flow, then direct-AJAX after
	// 700ms if WC didn't ack. Covers themes that rebind form.checkout in
	// ways that swallow the jQuery trigger.
	const fireTrigger = () => $(document.body).trigger("update_checkout");
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
				$.each(html.fragments, (selector, content) => {
					$(selector).replaceWith(content);
				});
				$(document.body).trigger("updated_checkout", [html]);
			}
		});
	};

	// rAF so React commits before WC reads form values; direct AJAX as
	// fallback if `updated_checkout` doesn't fire back in 700ms.
	let answered = false;
	$(document.body).one("updated_checkout", () => { answered = true; });
	requestAnimationFrame(fireTrigger);
	setTimeout(() => { if (!answered) fireDirect(); }, 700);
}
