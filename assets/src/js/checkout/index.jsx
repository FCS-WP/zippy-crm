import { createRoot } from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { PointsTenderWidget } from "./PointsTenderWidget.jsx";
import { VoucherTrayWidget } from "./VoucherTrayWidget.jsx";
import "../shared/styles.css";

/**
 * Mounts the "Use your points" widget into any `#zippy-crm-checkout-points`
 * div on the page. Two callers:
 *
 *   1. Default WC checkout (PHP) — the plugin prints the div via the
 *      `woocommerce_review_order_before_payment` hook. It exists in the DOM
 *      when our bundle parses, so the initial query catches it.
 *
 *   2. The ai-zippy theme's React checkout — its CheckoutApp renders the div
 *      *inside* its own component tree, which only exists after the theme's
 *      checkout bundle hydrates. That happens after our bundle parses, so
 *      the MutationObserver below is what catches it.
 *
 * v1.13.0: moved from cart page to checkout page so points-as-payment is
 * decided when the customer sees the final number (with shipping/tax). The
 * widget code itself is unchanged — it always was page-agnostic.
 *
 * Idempotent: each mount is keyed by its element, so re-renders of the host
 * (a payment-method change, for instance) don't spawn duplicate roots — the
 * MutationObserver only sees a new element if the node identity is new.
 */
const queryClient = new QueryClient();
const mounted = new WeakSet();

// Mount registry: which selector renders which component. Same lifecycle
// for both — DOM scan on bundle parse + MutationObserver for late mounts
// (the ai-zippy theme renders these inside its checkout React tree which
// hydrates after our bundle runs).
const MOUNTS = [
	{
		selector: "#zippy-crm-checkout-points, .zippy-crm-checkout-points",
		idMatch:  (node) => node.id === "zippy-crm-checkout-points" || node.classList?.contains("zippy-crm-checkout-points"),
		render:   () => <PointsTenderWidget />,
	},
	{
		selector: "#zippy-crm-checkout-vouchers, .zippy-crm-checkout-vouchers",
		idMatch:  (node) => node.id === "zippy-crm-checkout-vouchers" || node.classList?.contains("zippy-crm-checkout-vouchers"),
		render:   () => <VoucherTrayWidget />,
	},
];

function tryMount(el, render) {
	if ( ! el || mounted.has( el ) ) return;
	mounted.add( el );
	createRoot( el ).render(
		<QueryClientProvider client={queryClient}>
			{render()}
		</QueryClientProvider>,
	);
}

// Initial pass for divs already in the DOM.
MOUNTS.forEach(({ selector, render }) => {
	document.querySelectorAll(selector).forEach((el) => tryMount(el, render));
});

const observer = new MutationObserver((mutations) => {
	for (const m of mutations) {
		for (const node of m.addedNodes) {
			if (node.nodeType !== 1) continue;
			for (const mount of MOUNTS) {
				if (mount.idMatch(node)) tryMount(node, mount.render);
				node.querySelectorAll?.(mount.selector).forEach((el) => tryMount(el, mount.render));
			}
		}
	}
});
observer.observe(document.body, { childList: true, subtree: true });
