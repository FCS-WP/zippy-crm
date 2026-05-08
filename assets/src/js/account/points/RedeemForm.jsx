import { useState, useMemo } from "react";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/js/shared/ui/card.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { money, number } from "@/js/shared/utils/format.js";

/**
 * Redemption picker. Three input affordances stacked, each does the same job
 * but for a different intent:
 *   - Preset chips: "I just want a small discount"
 *   - Slider:      "Show me the trade-off"
 *   - Number entry: "I know exactly what I want"
 */
export function RedeemForm({ summary }) {
	const {
		available,
		reserved,
		redemption_rate: rate,
		min_redemption: min,
	} = summary;

	// Clamp against AVAILABLE (gross balance minus already-reserved). Users
	// can't lock the same points twice into different pending coupons.
	const max = Math.floor(available / rate) * rate;
	const canRedeem = max >= min;
	const remainder = available - max; // available pts left over after rounding to rate

	const presets = useMemo(() => buildPresets(min, max, rate), [min, max, rate]);

	const [points, setPoints] = useState(canRedeem ? presets[0] : 0);
	const [result, setResult] = useState(null);

	const mutation = useApiMutation("post", "/points/redeem", {
		invalidate: ["/points/me", "/points/ledger"],
		onSuccess: (data) => setResult({ kind: "ok", data }),
		onError:   (err)  => setResult({ kind: "err", message: err.message }),
	});

	const submit = (e) => {
		e.preventDefault();
		setResult(null);
		mutation.mutate({ points });
	};

	const previewDiscount = points / rate;

	return (
		<Card>
			<CardHeader>
				<CardTitle>Redeem points</CardTitle>
				<CardDescription>
					{canRedeem
						? <>Up to <span className="zc-font-medium zc-text-zinc-700">{money(max / rate)}</span> in discount available now.</>
						: reserved > 0
							? <>All your points are tied up in pending coupons. Use them or wait for them to expire.</>
							: <>Earn at least {min} points to redeem.</>}
				</CardDescription>
			</CardHeader>
			<CardContent className="zc-space-y-4">
				{!canRedeem ? (
					<EmptyRedeemHint available={available} reserved={reserved} min={min} />
				) : (
					<form onSubmit={submit} className="zc-space-y-5">
						<PresetChips presets={presets} max={max} value={points} onChange={setPoints} />

						<div>
							<input
								type="range"
								min={min}
								max={max}
								step={rate}
								value={points}
								onChange={(e) => setPoints(Number(e.target.value))}
								className="zc-w-full zc-cursor-pointer zc-accent-zinc-900"
								aria-label="Points to redeem"
							/>
							<div className="zc-mt-1 zc-flex zc-justify-between zc-text-xs zc-text-zinc-500">
								<span>{number(min)} pts</span>
								<span>{number(max)} pts</span>
							</div>
						</div>

						<DiscountPreview points={points} discount={previewDiscount} />

						<Button
							type="submit"
							disabled={mutation.isPending || points < min || points > max}
							loading={mutation.isPending}
							className="zc-w-full"
						>
							Get {money(previewDiscount)} coupon
						</Button>

						{remainder > 0 && (
							<p className="zc-text-xs zc-text-zinc-500">
								{number(remainder)} pts left over · earn {rate - remainder} more to round up.
							</p>
						)}
					</form>
				)}

				{result?.kind === "ok" && <RedeemSuccess data={result.data} />}
				{result?.kind === "err" && (
					<p className="zc-rounded-md zc-bg-rose-50 zc-px-3 zc-py-2 zc-text-sm zc-text-rose-700">
						{result.message}
					</p>
				)}
			</CardContent>
		</Card>
	);
}

function buildPresets(min, max, rate) {
	// Common dollar amounts → points. Skip any preset that exceeds max.
	const dollarPresets = [1, 2, 5, 10, 25];
	const list = dollarPresets
		.map((d) => d * rate)
		.filter((p) => p >= min && p <= max);
	if (max > 0 && !list.includes(max)) list.push(max); // always include "Max"
	return list.length ? list : [min];
}

function PresetChips({ presets, max, value, onChange }) {
	return (
		<div className="zc-flex zc-flex-wrap zc-gap-2">
			{presets.map((p) => {
				const active = p === value;
				const isMax = p === max;
				return (
					<button
						key={p}
						type="button"
						onClick={() => onChange(p)}
						className={[
							"zc-inline-flex zc-items-center zc-rounded-full zc-border zc-px-3 zc-py-1 zc-text-xs zc-font-medium zc-transition-colors",
							active
								? "zc-border-zinc-900 zc-bg-zinc-900 zc-text-white"
								: "zc-border-zinc-200 zc-bg-white zc-text-zinc-700 hover:zc-border-zinc-400",
						].join(" ")}
					>
						{isMax ? `Max · ${number(p)}` : `$${p / 20}`}
					</button>
				);
			})}
		</div>
	);
}

function DiscountPreview({ points, discount }) {
	return (
		<div className="zc-flex zc-items-baseline zc-justify-between zc-rounded-lg zc-bg-zinc-50 zc-px-4 zc-py-3">
			<div>
				<p className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-zinc-500">Redeem</p>
				<p className="zc-text-lg zc-font-semibold zc-text-zinc-900">{number(points)} pts</p>
			</div>
			<svg className="zc-size-4 zc-text-zinc-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
				<path d="M5 12h14M13 6l6 6-6 6" />
			</svg>
			<div className="zc-text-right">
				<p className="zc-text-xs zc-uppercase zc-tracking-wider zc-text-zinc-500">Get</p>
				<p className="zc-text-lg zc-font-semibold zc-text-emerald-700">{money(discount)} off</p>
			</div>
		</div>
	);
}

function EmptyRedeemHint({ available, reserved, min }) {
	const need = min - available;
	return (
		<div className="zc-rounded-lg zc-bg-zinc-50 zc-p-4 zc-text-sm zc-text-zinc-600">
			You have <span className="zc-font-semibold zc-text-zinc-900">{number(available)} pts</span> available
			{reserved > 0 && <> (<span className="zc-font-semibold zc-text-zinc-900">{number(reserved)}</span> reserved in pending coupons)</>}
			.
			{need > 0 && available < min && reserved === 0 && (
				<> Earn <span className="zc-font-semibold zc-text-zinc-900">{number(need)}</span> more to redeem your first reward.</>
			)}
		</div>
	);
}

function RedeemSuccess({ data }) {
	const [copied, setCopied] = useState(false);
	const copy = async () => {
		try {
			await navigator.clipboard?.writeText(data.coupon_code);
			setCopied(true);
			setTimeout(() => setCopied(false), 1500);
		} catch { /* clipboard blocked */ }
	};
	return (
		<div className="zc-rounded-lg zc-border zc-border-emerald-200 zc-bg-emerald-50 zc-p-4">
			<p className="zc-text-xs zc-font-medium zc-uppercase zc-tracking-wider zc-text-emerald-800">Coupon ready</p>
			<div className="zc-mt-2 zc-flex zc-items-center zc-justify-between zc-gap-3">
				<code className="zc-rounded zc-border zc-border-emerald-300 zc-bg-white zc-px-2.5 zc-py-1 zc-font-mono zc-text-sm zc-font-semibold zc-text-emerald-900">
					{data.coupon_code}
				</code>
				<Button size="sm" variant="outline" onClick={copy}>{copied ? "Copied" : "Copy"}</Button>
			</div>
			<p className="zc-mt-2 zc-text-sm zc-text-emerald-800">
				{money(data.discount)} off — apply at checkout. Expires in 24h.
			</p>
		</div>
	);
}
