import { createRoot } from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { PointsTenderWidget } from "./PointsTenderWidget.jsx";
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

const SELECTOR = "#zippy-crm-checkout-points, .zippy-crm-checkout-points";

function tryMount(el) {
	if ( ! el || mounted.has( el ) ) return;
	mounted.add( el );
	createRoot( el ).render(
		<QueryClientProvider client={queryClient}>
			<PointsTenderWidget />
		</QueryClientProvider>,
	);
}

// Initial pass for divs already in the DOM.
document.querySelectorAll(SELECTOR).forEach(tryMount);

// Watch for late-rendered mounts (theme React checkout hydrates after we run).
const observer = new MutationObserver((mutations) => {
	for (const m of mutations) {
		for (const node of m.addedNodes) {
			if (node.nodeType !== 1) continue;
			if (node.id === "zippy-crm-checkout-points" || node.classList?.contains("zippy-crm-checkout-points")) {
				tryMount(node);
			}
			node.querySelectorAll?.(SELECTOR).forEach(tryMount);
		}
	}
});
observer.observe(document.body, { childList: true, subtree: true });
