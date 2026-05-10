import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { money, number } from "@/js/shared/utils/format.js";

/**
 * Replaces the v1.7 RedeemForm. Customers no longer redeem points to a coupon
 * in advance — they apply points to their cart at checkout. This card just
 * tells them what they have and points them to the cart.
 *
 * Renders nothing for users below the minimum redemption — same restraint as
 * the cart-page widget.
 */
export function RedeemCTA({ summary }) {
	const { balance, redemption_rate, min_redemption } = summary;
	const dollars = balance / redemption_rate;
	const canRedeem = balance >= min_redemption;

	const cartUrl =
		(typeof window !== "undefined" && window.zippyCrm?.cartUrl) || "/cart/";

	return (
		<Card>
			<CardHeader>
				<CardTitle>Use your points</CardTitle>
				<CardDescription>
					{canRedeem
						? <>Apply your balance at checkout for an instant discount.</>
						: <>Earn at least {min_redemption} points to start redeeming.</>}
				</CardDescription>
			</CardHeader>
			<CardContent className="zc-space-y-4">
				<div className="zc-flex zc-items-baseline zc-justify-between zc-rounded-lg zc-bg-zinc-50 zc-px-4 zc-py-3">
					<div>
						<p className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-zinc-500">Balance</p>
						<p className="zc-text-lg zc-font-semibold zc-text-zinc-900">{number(balance)} pts</p>
					</div>
					<div className="zc-text-right">
						<p className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-zinc-500">Worth</p>
						<p className="zc-text-lg zc-font-semibold zc-text-emerald-700">{money(dollars)}</p>
					</div>
				</div>

				<a
					href={cartUrl}
					className={[
						"zc-inline-flex zc-w-full zc-items-center zc-justify-center zc-gap-2 zc-rounded-md zc-px-4 zc-py-2.5 zc-text-sm zc-font-medium zc-transition-colors",
						canRedeem
							? "zc-bg-zinc-900 zc-text-white hover:zc-bg-zinc-800"
							: "zc-pointer-events-none zc-bg-zinc-200 zc-text-zinc-500",
					].join(" ")}
					aria-disabled={!canRedeem}
				>
					Redeem at checkout
					<svg className="zc-size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
						<path d="M5 12h14M13 6l6 6-6 6" />
					</svg>
				</a>

				<p className="zc-text-xs zc-text-zinc-500">
					Add items to your cart, then choose how many points to use. Points are deducted only when the order completes — abandoned carts don't lose points.
				</p>
			</CardContent>
		</Card>
	);
}
