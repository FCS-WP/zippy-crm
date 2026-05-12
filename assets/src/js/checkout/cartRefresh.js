/**
 * Re-renders cart totals after an apply/clear from any checkout-side widget.
 * Shared between PointsTenderWidget and VoucherTrayWidget — same template
 * detection, same strategies, same race fixes.
 *
 * Different templates need different signals; we try each in order and stop
 * at the first that hits:
 *
 *   0. wp.data Store API — for the WC Checkout block (no jQuery events).
 *   A1. ai-zippy theme — repaints `#az-checkout-totals` via the theme's
 *      `az_get_checkout_totals` admin-ajax. Must short-circuit here: the
 *      widget mount points live inside `#order_review` on this template,
 *      and the classic strategies below would tear down the React roots.
 *   A. Default WC classic — paste the server-rendered fragment if one
 *      was provided with the response, avoiding the `update_checkout`
 *      AJAX race (two responses fighting to replace the same fragment).
 *   1+2. Fallback — fire `update_checkout` and, if WC's `updated_checkout`
 *      doesn't fire back within 700ms, AJAX `update_order_review` directly.
 *
 * The custom `zippy-crm:tender-changed` event is also dispatched for any
 * theme that wants to wire its own listener.
 *
 * @param {string} [orderReviewHtml] Optional pre-rendered fragment for
 *   Strategy A. Points widget passes this; voucher widget doesn't.
 */
export function triggerCartRefresh(orderReviewHtml) {
	window.dispatchEvent(new CustomEvent("zippy-crm:tender-changed"));

	const wpData = window.wp?.data;
	if (wpData?.dispatch) {
		const cartStore = wpData.dispatch("wc/store/cart");
		cartStore?.invalidateResolutionForStore?.();
	}

	const $ = window.jQuery;

	// ai-zippy template: repaint its dedicated totals block. We must NOT
	// fall through to the classic strategies — they'd tear down the
	// widget React roots living inside `#order_review` on this template.
	const ajaxUrl = window.zippyCrm?.ajaxUrl;
	if (typeof $ === "function" && $("#az-checkout-totals").length && ajaxUrl) {
		swapTotalsBlock($, ajaxUrl);
		return;
	}

	// Default classic WC: paste the pre-rendered fragment if provided.
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

/**
 * Swap `#az-checkout-totals` content with a brief fade transition.
 *
 * The theme renders this block by replacing innerHTML wholesale — when a
 * new row appears (e.g. coupon discount line), the height changes
 * instantly and the rest of the checkout below it jumps. We fade the
 * block out before swapping and fade it back in after, which makes the
 * height change feel like an intentional transition rather than a glitch.
 *
 * Lives in the plugin (not the theme) because all our apply/clear flows
 * already go through this function. The theme's own coupon-apply button
 * still produces the instant snap; if that becomes a problem the same
 * pattern can be lifted into the theme's `refreshTotals()`.
 */
function swapTotalsBlock($, ajaxUrl) {
	const $el = $("#az-checkout-totals");
	// Inline-style the transition so we don't depend on Tailwind classes
	// existing in the page's CSS. 120ms each way matches the widget's
	// own collapse animation timing.
	$el.css({ transition: "opacity 120ms ease-out", opacity: "0.4" });

	$.post(ajaxUrl, { action: "az_get_checkout_totals" }, (res) => {
		const html = res && res.success && res.data && res.data.html;
		// Whether the request succeeded or not, restore opacity — leaving
		// the block at 0.4 forever would be worse than no animation.
		if (html) {
			$el.html(html);
		}
		// Next frame so the browser registers the new content before we
		// transition opacity back; without rAF the swap and fade-in
		// collapse into one paint and the animation gets lost.
		requestAnimationFrame(() => {
			$el.css({ opacity: "1" });
		});
	}).fail(() => {
		$el.css({ opacity: "1" });
	});
}
