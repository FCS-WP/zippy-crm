import { useEffect, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { api } from "@/js/shared/api.js";
import { Card, CardContent } from "@/js/shared/ui/card.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { money } from "@/js/shared/utils/format.js";
import { triggerCartRefresh } from "./cartRefresh.js";
import { CollapsePanel } from "./CollapsePanel.jsx";

const LOCKED_PREVIEW_LIMIT = 3;

/**
 * Voucher tray on /checkout. Shows the customer's claimed-and-eligible
 * vouchers + public-and-eligible-and-unclaimed vouchers as one-click
 * apply rows, plus locked ones with a friendly "why not" reason. Server
 * is authoritative for eligibility — the widget renders what it's told.
 */
export function VoucherTrayWidget() {
	const qc = useQueryClient();
	const tray = useApiQuery("/vouchers/checkout-tray");

	// Per-row paths vary, so we drop one level down from useApiMutation
	// to useMutation and dispatch by intent. After any write, invalidate
	// the tray AND trigger the cart-refresh path the points widget uses
	// so the totals block repaints.
	const refreshAfter = () => {
		qc.invalidateQueries({ queryKey: ["/vouchers/checkout-tray"] });
		qc.invalidateQueries({ queryKey: ["/vouchers"] });
		triggerCartRefresh();
	};
	const apply = useMutation({
		mutationFn: (id) => api.post(`/vouchers/${id}/apply`),
		onSuccess: refreshAfter,
	});
	const claimAndApply = useMutation({
		mutationFn: (id) => api.post(`/vouchers/${id}/claim-and-apply`),
		onSuccess: refreshAfter,
	});
	const remove = useMutation({
		mutationFn: (id) => api.delete(`/vouchers/${id}/apply`),
		onSuccess: refreshAfter,
	});

	// Refetch on cart-fragment changes. Same dual listener as
	// PointsTenderWidget — different templates fire different events.
	useEffect(() => {
		const $ = window.jQuery;
		if (typeof $ !== "function") return;
		const onChanged = () => {
			qc.invalidateQueries({ queryKey: ["/vouchers/checkout-tray"] });
		};
		$(document.body).on("update_checkout updated_checkout", onChanged);
		return () => { $(document.body).off("update_checkout updated_checkout", onChanged); };
	}, [qc]);

	const [showAllLocked, setShowAllLocked] = useState(false);
	const [expanded, setExpanded] = useState(false);

	if (tray.isLoading) {
		return (
			<Card className="zc-mb-4 zc-mt-6">
				<div className="zc-px-4 zc-py-3">
					<Skeleton className="zc-h-5 zc-w-40" />
				</div>
			</Card>
		);
	}
	if (tray.isError || !tray.data) return null;

	const { eligible = [], locked = [] } = tray.data;

	// Hide the widget when there's literally nothing to surface — both
	// buckets empty means the customer has no claimed vouchers AND no
	// public ones are visible to them. A useless empty card on checkout
	// is worse than no card.
	if (eligible.length === 0 && locked.length === 0) return null;

	const callApply = (item) => {
		if (item.claimed) apply.mutate(item.id);
		else claimAndApply.mutate(item.id);
	};
	const callRemove = (item) => {
		remove.mutate(item.id);
	};

	const visibleLocked = showAllLocked ? locked : locked.slice(0, LOCKED_PREVIEW_LIMIT);
	const hiddenLockedCount = locked.length - visibleLocked.length;

	// Per-row spinner state: a row is busy only when ITS voucher id is the
	// one currently in flight, so clicking Apply on row A doesn't put rows
	// B and C into a loading state. React Query exposes the original
	// `variables` we passed to mutate() while the mutation is pending.
	const busyId = (apply.isPending && apply.variables) ?? (claimAndApply.isPending && claimAndApply.variables) ?? (remove.isPending && remove.variables) ?? null;

	// Collapsed-state summary. Prefer the applied voucher if any (so the
	// customer always sees their discount even when the tray is closed),
	// else hint at the best eligible offer to encourage the open.
	const appliedRow = eligible.find((v) => v.already_applied);
	const summary = collapsedSummary(eligible, locked, appliedRow);

	return (
		<Card className="zc-mb-4 zc-mt-6 zc-overflow-hidden">
			<button
				type="button"
				onClick={() => setExpanded((v) => !v)}
				aria-expanded={expanded}
				className="zc-w-full zc-flex zc-items-center zc-justify-between zc-gap-3 zc-px-6 zc-py-4 zc-text-left hover:zc-bg-zinc-50"
			>
				<div className="zc-flex zc-items-center zc-gap-3 zc-min-w-0">
					<VoucherIcon highlight={!!appliedRow} />
					<div className="zc-min-w-0">
						<p className="zc-text-sm zc-font-semibold zc-text-zinc-900">Your vouchers</p>
						<p className={`zc-mt-0.5 zc-text-xs ${appliedRow ? "zc-text-emerald-700" : "zc-text-zinc-500"}`}>
							{summary}
						</p>
					</div>
				</div>
				<Chevron expanded={expanded} />
			</button>

			<CollapsePanel open={expanded}>
				<CardContent className="zc-space-y-3 zc-border-t zc-border-zinc-100 !zc-pt-4">
					{eligible.length > 0 ? (
						eligible.map((item) => (
							<EligibleRow
								key={`${item.claimed ? "c" : "p"}-${item.id}`}
								item={item}
								onApply={() => callApply(item)}
								onRemove={() => callRemove(item)}
								busy={busyId === item.id}
							/>
						))
					) : (
						<p className="zc-text-xs zc-text-zinc-500">
							No vouchers available for this order right now.
						</p>
					)}

					{locked.length > 0 ? (
						<div className="zc-space-y-2 zc-pt-2 zc-border-t zc-border-zinc-100">
							<p className="zc-text-xs zc-font-medium zc-text-zinc-500">Not available right now</p>
							{visibleLocked.map((item) => (
								<LockedRow key={`l-${item.claimed ? "c" : "p"}-${item.id}`} item={item} />
							))}
							{hiddenLockedCount > 0 ? (
								<button
									type="button"
									onClick={() => setShowAllLocked(true)}
									className="zc-text-xs zc-text-zinc-600 hover:zc-text-zinc-900 zc-underline"
								>
									Show {hiddenLockedCount} more locked voucher{hiddenLockedCount === 1 ? "" : "s"}
								</button>
							) : null}
						</div>
					) : null}
				</CardContent>
			</CollapsePanel>
		</Card>
	);
}

function EligibleRow({ item, onApply, onRemove, busy }) {
	const discount = formatDiscount(item);
	return (
		<div className="zc-rounded-lg zc-border zc-border-emerald-200 zc-bg-emerald-50 zc-p-3">
			<div className="zc-flex zc-items-start zc-justify-between zc-gap-3">
				<div className="zc-min-w-0">
					<p className="zc-text-sm zc-font-medium zc-text-emerald-900">
						{item.title}
					</p>
					<p className="zc-mt-0.5 zc-text-xs zc-text-emerald-800">
						{discount}{item.code ? ` · code ${item.code}` : ""}
					</p>
				</div>
				{item.already_applied ? (
					<Button variant="outline" size="sm" onClick={onRemove} loading={busy}>
						Remove
					</Button>
				) : (
					<Button size="sm" onClick={onApply} loading={busy}>
						Use
					</Button>
				)}
			</div>
		</div>
	);
}

function LockedRow({ item }) {
	const discount = formatDiscount(item);
	return (
		<div className="zc-flex zc-items-start zc-gap-2.5 zc-rounded-md zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-2.5 hover:zc-bg-zinc-100 zc-transition-colors">
			<LockIcon />
			<div className="zc-min-w-0">
				<p className="zc-text-sm zc-text-zinc-700">
					<span className="zc-font-medium">{item.title}</span>
					<span className="zc-text-zinc-500"> · {discount}</span>
				</p>
				{item.reason ? (
					<p className="zc-mt-0.5 zc-text-xs zc-text-amber-700">{item.reason}</p>
				) : null}
			</div>
		</div>
	);
}

function LockIcon() {
	return (
		<span className="zc-flex zc-size-6 zc-shrink-0 zc-items-center zc-justify-center zc-rounded-full zc-bg-zinc-200 zc-text-zinc-500" aria-hidden>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="zc-size-3.5">
				<rect x="4" y="11" width="16" height="9" rx="2" />
				<path d="M8 11V8a4 4 0 0 1 8 0v3" />
			</svg>
		</span>
	);
}

function formatDiscount(item) {
	if (item.free_shipping) return "Free shipping";
	if (item.discount_type === "percent") return `${Math.round(item.discount_value)}% off`;
	return `${money(item.discount_value)} off`;
}

/**
 * One-line summary for the collapsed header. Applied voucher takes
 * priority so the customer always sees their active discount even when
 * the tray is closed. Otherwise we hint at the best eligible offer to
 * encourage the open.
 */
function collapsedSummary(eligible, locked, appliedRow) {
	if (appliedRow) {
		return `Applied · ${appliedRow.code || appliedRow.title} · ${formatDiscount(appliedRow)}`;
	}
	const total = eligible.length + locked.length;
	if (total === 0) return "No vouchers";
	const available = eligible.length;
	if (available === 0) {
		return `${locked.length} voucher${locked.length === 1 ? "" : "s"} (not available right now)`;
	}
	const best = bestEligible(eligible);
	const lead = `${available} voucher${available === 1 ? "" : "s"} available`;
	return best ? `${lead} · best: ${formatDiscount(best)}` : lead;
}

function bestEligible(eligible) {
	// Crude ranking: free shipping > biggest fixed amount > biggest percent.
	// Good enough as a teaser; the customer sees the full list on expand.
	return eligible.slice().sort((a, b) => {
		if (a.free_shipping !== b.free_shipping) return a.free_shipping ? -1 : 1;
		const aFixed = a.discount_type !== "percent";
		const bFixed = b.discount_type !== "percent";
		if (aFixed !== bFixed) return aFixed ? -1 : 1;
		return (b.discount_value || 0) - (a.discount_value || 0);
	})[0];
}

function VoucherIcon({ highlight }) {
	// Ticket / coupon icon. Switches to emerald when a voucher is applied,
	// echoing the green collapsed-summary text.
	const tone = highlight
		? "zc-bg-emerald-100 zc-text-emerald-700"
		: "zc-bg-zinc-100 zc-text-zinc-600";
	return (
		<span className={`zc-flex zc-size-9 zc-shrink-0 zc-items-center zc-justify-center zc-rounded-full ${tone}`} aria-hidden>
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="zc-size-5">
				<path d="M2 9a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z" />
				<path d="M9 7v10" strokeDasharray="2 2" />
			</svg>
		</span>
	);
}

function Chevron({ expanded }) {
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
