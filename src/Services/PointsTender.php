<?php
namespace ZippyCrm\Services;

use ZippyCrm\Models\PointsLedger;
use ZippyCrm\Models\PointsSummary;

defined( 'ABSPATH' ) || exit;

/**
 * Apply points as cash tender at checkout — replaces the old coupon-based
 * redemption flow (which is now legacy and disabled in PointsEngine).
 *
 * Model:
 *   - Customer slides "use N points" on the cart page → REST writes N to the
 *     WC session under SESSION_KEY.
 *   - On every cart total recalc, `add_fee()` reads the session and attaches a
 *     negative WC_Order_Item_Fee. `tax_status='none'` so it reduces the
 *     post-tax total (treat-as-cash semantics; gift-card-equivalent).
 *   - On checkout, the session value is copied to order meta `_zc_points_applied`.
 *   - On order completion, `settle_for_order()` debits the ledger + summary
 *     once (gated by `_zc_points_settled` for idempotency under hook re-fire).
 *   - On refund, `credit_back_on_refund()` returns points proportionally to
 *     the refunded fraction.
 *
 * Why session, not DB:
 *   No reservation, no 24h coupon, no pending state. The session expires
 *   naturally (~48h default), so abandoned carts return points implicitly.
 *   Two tabs racing to use the same points are arbitrated at order completion
 *   — whichever order completes first gets the debit; the second order's
 *   apply-step value is just a hint that gets validated against current
 *   balance at settle time.
 */
final class PointsTender {

	/**
	 * WC session key used by the cart fee + checkout copy.
	 *
	 * Note: we ALSO mirror the value into user meta `_zc_applied_points` for
	 * logged-in users. WC's session class doesn't bind the customer ID until
	 * `init` priority 1, but REST authentication runs later, so a REST call
	 * from a logged-in user lands on a fresh guest session every time. The
	 * user-meta fallback survives that.
	 */
	public const SESSION_KEY = 'zc_applied_points';

	/** User meta — primary source of truth for logged-in customers. */
	public const USER_META_APPLIED = '_zc_applied_points';

	/** Order meta key set during checkout, read at order_status_completed. */
	public const META_APPLIED  = '_zc_points_applied';

	/** Order meta marker — set after settle to make it idempotent. */
	public const META_SETTLED  = '_zc_points_settled';

	/** Order meta — points already credited back via refund (cumulative). */
	public const META_REFUNDED = '_zc_points_refunded';

	/** Order meta — set by revert_for_order so cancel hook is idempotent. */
	public const META_REVERTED = '_zc_points_reverted';

	/* ============================================================
	 * Hook registration (called from Plugin::boot via Hooks/WooCommerce)
	 * ============================================================ */

	public static function register(): void {
		add_action( 'woocommerce_cart_calculate_fees',     [ self::class, 'add_fee' ] );
		add_action( 'woocommerce_checkout_create_order',   [ self::class, 'persist_to_order' ], 10, 2 );
		// Settle on `processing` (most stores) AND `completed` (fallback for
		// flows that skip processing). Idempotent via META_SETTLED.
		// v1.13.0: was completed-only; now fires earlier so the ledger reflects
		// the redemption when the customer actually pays through.
		add_action( 'woocommerce_order_status_processing', [ self::class, 'settle_for_order' ], 30 );
		add_action( 'woocommerce_order_status_completed',  [ self::class, 'settle_for_order' ], 30 );
		add_action( 'woocommerce_order_refunded',          [ self::class, 'credit_back_on_refund' ], 10, 2 );

		// If the cart empties or order completes, drop the session value so a
		// new shopping session starts clean.
		add_action( 'woocommerce_cart_emptied',          [ self::class, 'clear_session' ] );
		add_action( 'woocommerce_thankyou',              [ self::class, 'clear_session' ] );
	}

	/* ============================================================
	 * Apply / clear (REST callers)
	 * ============================================================ */

	/**
	 * Validates and stores the requested apply amount on the WC session.
	 * Caller (REST controller) decides what to do with errors.
	 *
	 * Contract:
	 *   - $points must be a non-negative multiple of the redemption rate
	 *   - $points must be ≤ user balance
	 *   - $points-as-dollars must be ≤ current cart total (no negative orders)
	 *
	 * Returns the resolved amount on success (may differ from requested if
	 * we clamped to cart_total) or \WP_Error on failure.
	 *
	 * @return int|\WP_Error
	 */
	public static function apply( int $user_id, int $points ) {
		$rate = (int) apply_filters( 'crm_points_redemption_rate', ZIPPY_CRM_POINTS_RATE, $user_id );

		if ( $points < 0 ) {
			return new \WP_Error( 'apply_negative', __( 'Cannot apply a negative number of points.', 'zippy-crm' ), [ 'status' => 400 ] );
		}
		if ( $points === 0 ) {
			self::clear_session();
			return 0;
		}
		if ( $points % $rate !== 0 ) {
			return new \WP_Error(
				'apply_not_multiple',
				sprintf( /* translators: %d: rate */ __( 'Points must be a multiple of %d.', 'zippy-crm' ), $rate ),
				[ 'status' => 400 ]
			);
		}

		// Suspended members can't tender.
		$membership = MembershipService::get_for_user( $user_id );
		if ( ( $membership['status'] ?? 'active' ) !== 'active' ) {
			return new \WP_Error( 'account_suspended', __( 'Your account is currently suspended.', 'zippy-crm' ), [ 'status' => 403 ] );
		}

		$balance = (int) PointsSummary::find( $user_id )['balance'] ?? 0;
		if ( $points > $balance ) {
			return new \WP_Error(
				'insufficient_balance',
				sprintf(
					/* translators: 1: balance, 2: requested */
					__( 'You only have %1$d points. Cannot apply %2$d.', 'zippy-crm' ),
					$balance,
					$points
				),
				[ 'status' => 400, 'balance' => $balance ]
			);
		}

		// Clamp to cart total — points can never make the order go below zero.
		// Only clamp when we can read a non-zero cart. In REST context, WC's
		// session-bound cart often loads empty (cart_total == 0) even though
		// the customer has items — see ensure_cart_loaded notes. In that case
		// trust the user's request and let the fee hook re-clamp at render
		// time, when WC()->cart is the live cart with items.
		$cart_total = self::cart_total_for_clamp();
		if ( $cart_total !== null && $cart_total > 0 ) {
			$dollars = $points / $rate;
			if ( $dollars > $cart_total ) {
				$dollars = floor( $cart_total );
				$points  = (int) ( $dollars * $rate );
			}
		}

		self::write_state( $user_id, $points );

		// Force WC to recalc immediately so the fee shows up without a page reload.
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->calculate_totals();
		}

		return $points;
	}

	/**
	 * Returns currently-applied points for the user, or 0.
	 *
	 * **User meta is the sole source of truth.** Earlier versions consulted
	 * the WC session first (with user_meta as a fallback) — but WC sessions
	 * are keyed by session_key, and a logged-in user can have multiple
	 * WC session rows in `{prefix}woocommerce_sessions` (REST calls + the
	 * classic checkout page can land on different session_keys). When that
	 * happens, the DELETE clears one session row while the checkout page
	 * keeps reading the OTHER, stale row → the "Points redemption" row
	 * lingers in the order table even after Remove.
	 *
	 * user_meta is keyed by user_id, so there's exactly one truth per user
	 * regardless of how many WC sessions exist. WC's `add_fee` callback runs
	 * in the page-render context where `get_current_user_id()` resolves
	 * correctly, so we don't lose anything by dropping the session read.
	 */
	public static function get_applied( ?int $user_id = null ): int {
		$user_id = $user_id ?? get_current_user_id();
		if ( $user_id <= 0 ) {
			return 0;
		}
		return (int) get_user_meta( $user_id, self::USER_META_APPLIED, true );
	}

	/**
	 * Clear the applied amount for a user.
	 *
	 * Wipes user_meta (the source of truth) plus best-effort cleans WC's
	 * session for this user — any session row WE can find via the customer's
	 * stored session_key gets the SESSION_KEY entry wiped. The session
	 * cleanup isn't load-bearing for correctness (add_fee reads user_meta),
	 * but keeping the session tidy avoids confusion if someone debugs by
	 * inspecting `woocommerce_sessions` rows.
	 */
	public static function clear_session( ?int $user_id = null ): void {
		$user_id = $user_id ?? get_current_user_id();
		if ( $user_id > 0 ) {
			delete_user_meta( $user_id, self::USER_META_APPLIED );
		}
		// Best-effort session cleanup.
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( self::SESSION_KEY );
			if ( method_exists( WC()->session, 'save_data' ) ) {
				WC()->session->save_data();
			}
		}
		error_log( sprintf( '[zc-tender] clear_session user=%d meta=%d', $user_id ?? 0, $user_id > 0 ? (int) get_user_meta( $user_id, self::USER_META_APPLIED, true ) : -1 ) );
	}

	/**
	 * Persist the applied amount in both stores so reads from any context
	 * find it. Session is the per-request hint; user meta is the durable
	 * source for cross-REST reads.
	 */
	private static function write_state( int $user_id, int $points ): void {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $points );
			// Same shutdown-race fix as clear_session — force the session to
			// hit the DB now so the immediate follow-up wc-ajax request sees
			// the new value, not the previous one.
			if ( method_exists( WC()->session, 'save_data' ) ) {
				WC()->session->save_data();
			}
		}
		if ( $user_id > 0 ) {
			if ( $points > 0 ) {
				update_user_meta( $user_id, self::USER_META_APPLIED, $points );
			} else {
				delete_user_meta( $user_id, self::USER_META_APPLIED );
			}
		}
	}

	/* ============================================================
	 * Cart fee
	 * ============================================================ */

	/**
	 * Hook target: woocommerce_cart_calculate_fees.
	 *
	 * Reads the session value and adds it as a negative fee. tax_status='none'
	 * makes this a post-tax reduction (gift-card semantics). Stores that need
	 * to treat points as a discount can filter `crm_points_fee_taxable`.
	 *
	 * Scope: redemption is a checkout-only concept (the widget lives there;
	 * customers commit to the final number when shipping/tax are known). So we
	 * SKIP attaching the fee when the request is rendering the cart page —
	 * otherwise customers see a "Points redemption" line on /cart that they
	 * have no UI to control (the widget isn't rendered there). We DO still
	 * attach on:
	 *   - checkout page renders                  (widget present)
	 *   - update_order_review AJAX               (widget triggered it)
	 *   - REST /apply, /clear_apply, /applicable (widget initiated)
	 *   - woocommerce_checkout_create_order      (order finalization)
	 * The `is_cart()` gate is the only context we deliberately exclude.
	 */
	public static function add_fee( $cart ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		// Skip cart-page renders. is_cart() is reliable here — it returns true
		// only during front-end rendering of the cart page (it's a query-flag
		// check, doesn't depend on session/ajax state). It correctly returns
		// false during /checkout, update_order_review AJAX, REST calls, and
		// the checkout-create-order hook chain.
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return;
		}

		$points = self::get_applied( $user_id );
		// Diagnostic — remove once tender flow is stable. Tells us whether
		// the fee hook is bailing correctly after a clear, or whether stale
		// state is still leaking through.
		$session_val = ( function_exists( 'WC' ) && WC()->session ) ? (int) WC()->session->get( self::SESSION_KEY, 0 ) : -1;
		$meta_val    = (int) get_user_meta( $user_id, self::USER_META_APPLIED, true );
		error_log( sprintf( '[zc-tender] add_fee user=%d session=%d meta=%d resolved=%d action=%s', $user_id, $session_val, $meta_val, $points, $points > 0 ? 'ATTACH' : 'BAIL' ) );
		if ( $points <= 0 ) {
			return;
		}

		$rate    = (int) apply_filters( 'crm_points_redemption_rate', ZIPPY_CRM_POINTS_RATE, $user_id );
		$dollars = $points / $rate;
		if ( $dollars <= 0 ) {
			return;
		}

		// Re-clamp at recalc time too: the cart may have shrunk since `apply()`.
		$cart_total = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
		if ( $dollars > $cart_total ) {
			$dollars = floor( $cart_total );
		}

		$taxable = (bool) apply_filters( 'crm_points_fee_taxable', false, $points, $user_id );

		$cart->add_fee(
			__( 'Points redemption', 'zippy-crm' ),
			-1 * $dollars,
			$taxable
		);
	}

	/* ============================================================
	 * Persist to order at checkout
	 * ============================================================ */

	/**
	 * Hook target: woocommerce_checkout_create_order.
	 *
	 * Copies the session value onto the order so settle_for_order can find it
	 * even if the session has expired by the time the hook runs (e.g. delayed
	 * payment gateway).
	 */
	public static function persist_to_order( \WC_Order $order, $data ): void {
		$user_id = (int) $order->get_customer_id();
		$points  = self::get_applied( $user_id );
		if ( $points <= 0 ) {
			return;
		}
		$order->update_meta_data( self::META_APPLIED, $points );
		// Don't save() here — WC saves the order itself after this hook.

		// The order has captured the value; clear the live state so the
		// customer's next cart starts fresh and a stale meta value can't
		// re-attach to a future order.
		self::clear_session( $user_id );
	}

	/* ============================================================
	 * Settle on completion
	 * ============================================================ */

	/**
	 * Hook target: woocommerce_order_status_completed.
	 *
	 * Idempotent via META_SETTLED. Re-firings of the hook (admin flips status,
	 * payment retries) won't double-debit.
	 *
	 * Runs at priority 30 — after award_for_order (default 10) and tier eval,
	 * so the multiplier on this very order isn't influenced by the points
	 * coming off (correct: customers earn on subtotal-after-discounts, and the
	 * points fee is post-tax so it doesn't affect subtotal at all).
	 */
	public static function settle_for_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = (int) $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$applied = (int) $order->get_meta( self::META_APPLIED );
		if ( $applied <= 0 ) {
			return; // no points were tendered on this order
		}
		if ( $order->get_meta( self::META_SETTLED ) === '1' ) {
			return; // already debited; replay is a no-op
		}

		// Re-validate against current balance — a race between "apply 200 in
		// cart A" and "apply 200 in cart B" is arbitrated here. Whichever
		// completes first gets the full debit; the second one gets clamped
		// to whatever balance remains (or zero).
		$balance = (int) ( PointsSummary::find( $user_id )['balance'] ?? 0 );
		$debit   = min( $applied, $balance );

		if ( $debit <= 0 ) {
			// No balance left — leave applied=N on the order for audit but
			// don't debit. Mark settled so we don't keep retrying.
			$order->update_meta_data( self::META_SETTLED, '1' );
			$order->save();
			return;
		}

		PointsLedger::insert(
			$user_id,
			'redeem',
			-1 * $debit,
			sprintf( /* translators: %d: order id */ __( 'Order #%d (points tender)', 'zippy-crm' ), $order_id ),
			$order_id
		);
		PointsSummary::apply_delta( $user_id, -1 * $debit );
		PointsEngine::invalidate( $user_id );

		$order->update_meta_data( self::META_SETTLED, '1' );
		$order->save();

		do_action( 'crm_points_redeemed', $user_id, $debit, null, $order_id );
	}

	/* ============================================================
	 * Refund crediting
	 * ============================================================ */

	/**
	 * Called from WooCommerce::on_order_cancelled (order_status_cancelled or
	 * _failed). The order was created and the customer applied points to it,
	 * but they never paid through. Two things to clean up:
	 *
	 *   1. **Order meta** — the `_zc_points_applied` value is left in place
	 *      for audit. It's marked "reverted" so a subsequent cancel hook
	 *      (re-fire on edge cases) is a no-op.
	 *
	 *   2. **Customer's pending tender state** — only if the user_meta /
	 *      session still match THIS order's value (defensive: if they've
	 *      already moved on to a new order with different applied points,
	 *      we don't want to wipe that). The normal flow cleared this in
	 *      persist_to_order(); this function is the belt to that suspenders.
	 *
	 * No ledger row is needed — the points were never debited (settle only
	 * fires on completed orders, gated by META_SETTLED). If the order DID
	 * settle (cancelled-after-completion is rare but possible), the caller
	 * already bailed in WooCommerce::on_order_cancelled.
	 *
	 * Idempotent — META_REVERTED guards against double-fire.
	 */
	public static function revert_for_order( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( $order->get_meta( self::META_REVERTED ) === '1' ) {
			return;
		}

		$applied = (int) $order->get_meta( self::META_APPLIED );
		if ( $applied <= 0 ) {
			return; // no points were applied to this order
		}

		$user_id = (int) $order->get_customer_id();
		if ( $user_id > 0 ) {
			// Defensive: only clear if the user's pending state still matches
			// this order's value. If they've already started a new checkout
			// with a different amount, leave that alone.
			$current = (int) get_user_meta( $user_id, self::USER_META_APPLIED, true );
			if ( $current === $applied ) {
				delete_user_meta( $user_id, self::USER_META_APPLIED );
			}
			if ( WC()->session && (int) WC()->session->get( self::SESSION_KEY, 0 ) === $applied ) {
				WC()->session->__unset( self::SESSION_KEY );
			}
		}

		$order->update_meta_data( self::META_REVERTED, '1' );
		$order->save();
	}

	/**
	 * Hook target: woocommerce_order_refunded.
	 *
	 * Returns points proportional to the refunded fraction. Tracks cumulative
	 * refunded amount via META_REFUNDED so partial-refund-then-partial-refund
	 * doesn't over-credit.
	 */
	public static function credit_back_on_refund( int $order_id, int $refund_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$user_id = (int) $order->get_customer_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$applied = (int) $order->get_meta( self::META_APPLIED );
		if ( $applied <= 0 ) {
			return;
		}
		if ( $order->get_meta( self::META_SETTLED ) !== '1' ) {
			return; // never debited, nothing to refund
		}

		$order_total       = (float) $order->get_total();
		$total_refunded    = (float) $order->get_total_refunded(); // cumulative across all refunds
		if ( $order_total <= 0 ) {
			return;
		}

		$refund_fraction = min( 1.0, $total_refunded / $order_total );
		$points_to_have_back = (int) floor( $applied * $refund_fraction );

		$already_refunded = (int) $order->get_meta( self::META_REFUNDED );
		$delta            = $points_to_have_back - $already_refunded;
		if ( $delta <= 0 ) {
			return;
		}

		PointsLedger::insert(
			$user_id,
			'adjust',
			$delta,
			sprintf( /* translators: %d: order id */ __( 'Refund credit on order #%d', 'zippy-crm' ), $order_id ),
			$order_id
		);
		PointsSummary::apply_delta( $user_id, $delta );
		PointsEngine::invalidate( $user_id );

		$order->update_meta_data( self::META_REFUNDED, (string) ( $already_refunded + $delta ) );
		$order->save();

		do_action( 'crm_points_refund_credited', $user_id, $delta, $order_id, $refund_id );
	}

	/* ============================================================
	 * Internal
	 * ============================================================ */

	/**
	 * Cart total for the clamp on apply(). Returns null if no cart context
	 * (e.g. apply called outside of a request that has a cart yet).
	 *
	 * WC's session-stored cart doesn't auto-bootstrap during a REST request —
	 * the cart is loaded by `wp` action on page renders, not by REST routing.
	 * We call `wc_load_cart()` explicitly so a `GET /points/applicable` from
	 * the cart page hydrates the same session cart the customer is looking at.
	 *
	 * Subtotal (line items pre-discount) + subtotal-tax — covers the cart's
	 * value before our fee subtracts. We deliberately don't call
	 * `$cart->get_total()` here because that would be circular (it includes
	 * our fee). Subtotal+tax is a safe upper bound.
	 */
	private static function cart_total_for_clamp(): ?float {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}
		self::ensure_cart_loaded();
		if ( ! WC()->cart ) {
			return null;
		}
		return (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
	}

	/**
	 * Bootstraps `WC()->cart` if it isn't already loaded for this request.
	 * Required when called from a REST handler — REST doesn't fire the `wp`
	 * action that normally hydrates the cart. Idempotent: re-runs are no-ops.
	 *
	 * Public so the controller can call it before reading the cart for the
	 * tender_payload response.
	 */
	public static function ensure_cart_loaded(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		// If a cart instance already exists, the session is already hydrated.
		if ( WC()->cart instanceof \WC_Cart ) {
			return;
		}
		// `wc_load_cart` registers the cart and session classes for the
		// current request and reads the persistent cart for the logged-in
		// user. Available in WC 3.6+.
		if ( function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
	}
}
