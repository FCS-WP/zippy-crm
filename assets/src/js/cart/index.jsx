import { createRoot } from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { PointsTenderWidget } from "./PointsTenderWidget.jsx";
import "../shared/styles.css";

/**
 * Mounts the "Use your points" widget into any `#zippy-crm-cart-points` div
 * on the page. Two callers:
 *
 *   1. Default WC cart (PHP) — the plugin prints the div via the
 *      `woocommerce_before_cart_totals` hook. It exists in the DOM when our
 *      bundle parses, so the initial query catches it.
 *
 *   2. The ai-zippy theme's React cart — `CartSidebar.jsx` renders the div
 *      *inside* its own component tree, which only exists after the theme's
 *      cart bundle hydrates. That happens after our bundle parses, so the
 *      MutationObserver below is what catches it.
 *
 * Idempotent: each mount is keyed by its element, so re-renders of the
 * theme's CartSidebar (a coupon-applied state change, for instance) don't
 * spawn duplicate roots — the MutationObserver only sees a new element if
 * the node identity is new.
 */
const queryClient = new QueryClient();
const mounted = new WeakSet();

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
document.querySelectorAll("#zippy-crm-cart-points, .zippy-crm-cart-points").forEach(tryMount);

// Watch for late-rendered mounts (theme React cart hydrates after we run).
const observer = new MutationObserver((mutations) => {
	for (const m of mutations) {
		for (const node of m.addedNodes) {
			if (node.nodeType !== 1) continue;
			if (node.id === "zippy-crm-cart-points" || node.classList?.contains("zippy-crm-cart-points")) {
				tryMount(node);
			}
			node.querySelectorAll?.("#zippy-crm-cart-points, .zippy-crm-cart-points").forEach(tryMount);
		}
	}
});
observer.observe(document.body, { childList: true, subtree: true });
