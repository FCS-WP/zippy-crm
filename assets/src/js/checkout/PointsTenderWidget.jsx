import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { useApiQuery, useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/js/shared/ui/card.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { money, number } from "@/js/shared/utils/format.js";
import { triggerCartRefresh } from "./cartRefresh.js";
import { CollapsePanel } from "./CollapsePanel.jsx";

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

	// Applied-state collapse: when points are applied, the widget becomes
	// a status row (mirrors the voucher tray's collapsed shape). The slider
	// is irrelevant in this state — the only meaningful action is Remove,
	// and that lives inline in the green strip. Click the chevron to peek
	// at the full balance/cap context.
	if (applied > 0) {
		return (
			<AppliedCollapsibleCard
				applied={applied}
				appliedDollars={d.applied_dollars}
				balance={balance}
				redemptionRate={redemption_rate}
				maxApplicable={max_applicable}
				cartCapBinding={cartCapBinding}
				autoClearedNotice={autoClearedNotice}
				onDismissNotice={() => setAutoClearedNotice(false)}
				onClear={() => clear.mutate({})}
				clearing={clear.isPending}
			/>
		);
	}

	return (
		<ApplyCollapsibleCard
			balance={balance}
			redemptionRate={redemption_rate}
			maxApplicable={max_applicable}
			minRedemption={min_redemption}
			cartCapBinding={cartCapBinding}
			autoClearedNotice={autoClearedNotice}
			onDismissNotice={() => setAutoClearedNotice(false)}
			onApply={(points) => apply.mutate({ points })}
			applying={apply.isPending}
			applyError={apply.error?.message}
		/>
	);
}

function ApplyCollapsibleCard({
	balance,
	redemptionRate,
	maxApplicable,
	minRedemption,
	cartCapBinding,
	autoClearedNotice,
	onDismissNotice,
	onApply,
	applying,
	applyError,
}) {
	const [expanded, setExpanded] = useState(false);
	return (
		<Card className="zc-mb-4 zc-mt-6 zc-overflow-hidden">
			<button
				type="button"
				onClick={() => setExpanded((v) => !v)}
				aria-expanded={expanded}
				className="zc-w-full zc-flex zc-items-center zc-justify-between zc-gap-3 zc-px-6 zc-py-4 zc-text-left hover:zc-bg-zinc-50"
			>
				<div className="zc-flex zc-items-center zc-gap-3 zc-min-w-0">
					<PointsIcon />
					<div className="zc-min-w-0">
						<p className="zc-text-sm zc-font-semibold zc-text-zinc-900">Use your points</p>
						<p className="zc-mt-0.5 zc-text-xs zc-text-zinc-500">
							{number(balance)} pts available
							{cartCapBinding ? ` · up to ${money(maxApplicable / redemptionRate)} off` : ` · ${money(balance / redemptionRate)} total`}
						</p>
					</div>
				</div>
				<ChevronIcon expanded={expanded} />
			</button>

			<CollapsePanel open={expanded}>
				<CardContent className="zc-border-t zc-border-zinc-100 !zc-pt-4">
					{autoClearedNotice ? (
						<AutoClearedNotice onDismiss={onDismissNotice} />
					) : null}
					{cartCapBinding ? (
						<p className="zc-mb-3 zc-text-xs zc-text-zinc-500">
							Up to <strong className="zc-text-zinc-700">{number(maxApplicable)} pts ({money(maxApplicable / redemptionRate)})</strong> can be applied to this order.
						</p>
					) : null}
					<ApplyForm
						max={maxApplicable}
						min={minRedemption}
						rate={redemptionRate}
						onApply={onApply}
						applying={applying}
						error={applyError}
						cartCapBinding={cartCapBinding}
					/>
				</CardContent>
			</CollapsePanel>
		</Card>
	);
}

function AppliedCollapsibleCard({
	applied,
	appliedDollars,
	balance,
	redemptionRate,
	maxApplicable,
	cartCapBinding,
	autoClearedNotice,
	onDismissNotice,
	onClear,
	clearing,
}) {
	const [expanded, setExpanded] = useState(false);
	return (
		<Card className="zc-mb-4 zc-mt-6 zc-overflow-hidden">
			<button
				type="button"
				onClick={() => setExpanded((v) => !v)}
				aria-expanded={expanded}
				className="zc-w-full zc-flex zc-items-center zc-justify-between zc-gap-3 zc-px-6 zc-py-4 zc-text-left hover:zc-bg-zinc-50"
			>
				<div className="zc-flex zc-items-center zc-gap-3 zc-min-w-0">
					<PointsIcon />
					<div className="zc-min-w-0">
						<p className="zc-text-sm zc-font-semibold zc-text-zinc-900">Use your points</p>
						<p className="zc-mt-0.5 zc-text-xs zc-text-emerald-700">
							Applied · {number(applied)} pts · {money(appliedDollars)} off
						</p>
					</div>
				</div>
				<div className="zc-flex zc-items-center zc-gap-2">
					<Button
						variant="outline"
						size="sm"
						onClick={(e) => { e.stopPropagation(); onClear(); }}
						loading={clearing}
					>
						Remove
					</Button>
					<ChevronIcon expanded={expanded} />
				</div>
			</button>

			<CollapsePanel open={expanded}>
				<CardContent className="zc-border-t zc-border-zinc-100 !zc-pt-4">
					{autoClearedNotice ? (
						<AutoClearedNotice onDismiss={onDismissNotice} />
					) : null}
					<p className="zc-text-sm zc-text-zinc-700">
						You have <span className="zc-font-medium">{number(balance)} pts</span> ({money(balance / redemptionRate)} total)
					</p>
					{cartCapBinding ? (
						<p className="zc-mt-1 zc-text-xs zc-text-zinc-500">
							Up to <strong className="zc-text-zinc-700">{number(maxApplicable)} pts ({money(maxApplicable / redemptionRate)})</strong> can be applied to this order.
						</p>
					) : null}
				</CardContent>
			</CollapsePanel>
		</Card>
	);
}

function PointsIcon() {
	// Coin / star icon — telegraphs "loyalty currency" at a glance.
	return (
		<span className="zc-flex zc-size-9 zc-shrink-0 zc-items-center zc-justify-center zc-rounded-full zc-bg-amber-100 zc-text-amber-700" aria-hidden>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="zc-size-5">
				<circle cx="12" cy="12" r="9" />
				<path d="M12 7v10M9.5 9.5h3.5a2 2 0 0 1 0 4H9.5h4a2 2 0 0 1 0 4H9.5" />
			</svg>
		</span>
	);
}

function ChevronIcon({ expanded }) {
	return (
		<svg
			viewBox="0 0 20 20"
			fill="currentColor"
			className={`zc-size-4 zc-shrink-0 zc-text-zinc-500 zc-transition-transform ${expanded ? "zc-rotate-180" : ""}`}
			aria-hidden
		>
			<path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.06l3.71-3.83a.75.75 0 1 1 1.08 1.04l-4.25 4.39a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06z" clipRule="evenodd" />
		</svg>
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
