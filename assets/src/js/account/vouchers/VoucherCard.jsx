import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { Badge } from "@/js/shared/ui/badge.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { date, money } from "@/js/shared/utils/format.js";

/**
 * One voucher in the Available grid. Claim mutation invalidates both
 * /vouchers and /vouchers/claims so the cards move from one list to the other
 * without a manual refresh.
 */
export function VoucherCard({ voucher }) {
	const [result, setResult] = useState(null);

	const mutation = useApiMutation("post", `/vouchers/${voucher.id}/claim`, {
		invalidate: ["/vouchers", "/vouchers/claims"],
		onSuccess: (data) => setResult({ kind: "ok", data }),
		onError:   (err)  => setResult({ kind: "err", code: err.code, message: err.message }),
	});

	const claimed = result?.kind === "ok";

	return (
		<Card className="zc-flex zc-flex-col zc-overflow-hidden">
			<DiscountStripe voucher={voucher} />

			<CardHeader>
				<div className="zc-flex zc-items-start zc-justify-between zc-gap-3">
					<CardTitle className="zc-text-base">{voucher.title}</CardTitle>
					{voucher.expires_at && (
						<Badge variant="muted" className="zc-shrink-0">
							Until {date(voucher.expires_at)}
						</Badge>
					)}
				</div>
			</CardHeader>

			<CardContent className="zc-flex zc-flex-1 zc-flex-col zc-gap-3">
				{voucher.description && (
					<p className="zc-text-sm zc-text-zinc-600">{voucher.description}</p>
				)}

				<dl className="zc-grid zc-grid-cols-2 zc-gap-3 zc-text-xs">
					{voucher.min_order_amount > 0 && (
						<Row label="Minimum order" value={money(voucher.min_order_amount)} />
					)}
					{voucher.remaining_uses !== null && (
						<Row label="Remaining" value={`${voucher.remaining_uses} uses`} />
					)}
				</dl>

				<div className="zc-mt-auto zc-pt-2">
					{!claimed ? (
						<Button
							onClick={() => mutation.mutate({})}
							disabled={mutation.isPending}
							loading={mutation.isPending}
							className="zc-w-full"
						>
							Claim voucher
						</Button>
					) : (
						<ClaimedNotice data={result.data} />
					)}
					{result?.kind === "err" && (
						<p className="zc-mt-2 zc-text-sm zc-text-rose-700">{result.message}</p>
					)}
				</div>
			</CardContent>
		</Card>
	);
}

function DiscountStripe({ voucher }) {
	const isPercent = voucher.discount_type === "percent";
	const headline = isPercent
		? `${Math.round(voucher.discount_value)}%`
		: money(voucher.discount_value);

	return (
		<div className={[
			"zc-flex zc-items-baseline zc-justify-between zc-gap-2 zc-px-6 zc-py-4 zc-text-white",
			"zc-bg-gradient-to-br",
			isPercent
				? "zc-from-fuchsia-700 zc-via-fuchsia-600 zc-to-rose-600"
				: "zc-from-zinc-900 zc-via-zinc-800 zc-to-zinc-700",
		].join(" ")}>
			<span className="zc-text-3xl zc-font-bold zc-leading-none">{headline}</span>
			<span className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-white/80">
				{isPercent ? "off" : "off cart"}
			</span>
		</div>
	);
}

function Row({ label, value }) {
	return (
		<div>
			<dt className="zc-text-zinc-500">{label}</dt>
			<dd className="zc-mt-0.5 zc-font-medium zc-text-zinc-900">{value}</dd>
		</div>
	);
}

function ClaimedNotice({ data }) {
	const [copied, setCopied] = useState(false);
	const copy = async () => {
		try {
			await navigator.clipboard?.writeText(data.code);
			setCopied(true);
			setTimeout(() => setCopied(false), 1500);
		} catch { /* clipboard blocked */ }
	};

	return (
		<div className="zc-rounded-lg zc-border zc-border-emerald-200 zc-bg-emerald-50 zc-p-3">
			<p className="zc-text-xs zc-font-medium zc-uppercase zc-tracking-wider zc-text-emerald-800">
				{data.applied_to_cart ? "Applied to your cart" : "Code claimed"}
			</p>
			<div className="zc-mt-2 zc-flex zc-items-center zc-justify-between zc-gap-2">
				<code className="zc-rounded zc-border zc-border-emerald-300 zc-bg-white zc-px-2 zc-py-1 zc-font-mono zc-text-sm zc-font-semibold zc-text-emerald-900">
					{data.code}
				</code>
				<Button size="sm" variant="outline" onClick={copy}>
					{copied ? "Copied" : "Copy"}
				</Button>
			</div>
			{!data.applied_to_cart && (
				<p className="zc-mt-2 zc-text-xs zc-text-emerald-800">
					Use it at checkout to apply the discount.
				</p>
			)}
		</div>
	);
}
