import { useEffect, useState } from "react";
import { Button } from "@/js/shared/ui/button.jsx";
import { date, isPercentType, money, percent } from "@/js/shared/utils/format.js";
import { ClaimsDrawerBody } from "./ClaimsDrawerBody.jsx";
import { CodesDrawerBody } from "./CodesDrawerBody.jsx";
import { StatusBadge } from "./StatusBadge.jsx";
import { Tabs } from "./sections/Tabs.jsx";

/**
 * Read-only voucher detail drawer with tabs. Single source of "what is this
 * voucher" — opens on row click. Tabs:
 *
 *   Overview — voucher metadata + audience badges + quota; Edit button
 *              (draft only) jumps to the editable form.
 *   Claims   — who claimed this voucher (via existing ClaimsDrawerBody).
 *   Codes    — per-code state (only present when distribution_mode='multi_code_public').
 *
 * Verbs (Pause/Resume/Edit/Duplicate/Delete) live in the row's ⋯ menu — the
 * drawer is intentionally read-mostly so admins never accidentally publish
 * something while skimming.
 */
export function VoucherDetailDrawer({ voucher, onEdit }) {
	const isMulti = voucher.distribution_mode === "multi_code_public";
	const [tab, setTab] = useState("overview");

	// Reset to Overview whenever the voucher changes (drawer reopens for a
	// different row).
	useEffect(() => { setTab("overview"); }, [voucher?.id]);

	const tabs = [
		{ key: "overview", label: "Overview" },
		{ key: "claims",   label: "Claims" },
		isMulti && { key: "codes", label: "Codes" },
	].filter(Boolean);

	return (
		<div className="zc-space-y-4">
			<Tabs tabs={tabs} value={tab} onChange={setTab} />

			<div role="tabpanel">
				{tab === "overview" ? <OverviewPanel voucher={voucher} onEdit={onEdit} /> : null}
				{tab === "claims"   ? <ClaimsDrawerBody voucher={voucher} /> : null}
				{tab === "codes" && isMulti ? <CodesDrawerBody voucher={voucher} /> : null}
			</div>
		</div>
	);
}

function OverviewPanel({ voucher, onEdit }) {
	const isMulti = voucher.distribution_mode === "multi_code_public";
	const isPercent = isPercentType(voucher.discount_type);

	const discount = isPercent
		? percent(voucher.discount_value)
		: money(voucher.discount_value);

	const usedLabel = voucher.max_uses
		? `${voucher.uses_count} / ${voucher.max_uses}`
		: `${voucher.uses_count} / ∞`;

	return (
		<div className="zc-space-y-4">
			<header className="zc-space-y-1">
				<div className="zc-flex zc-items-center zc-gap-2">
					<h3 className="zc-text-lg zc-font-semibold zc-text-zinc-900">{voucher.title}</h3>
					<StatusBadge status={voucher.status} />
					<AudienceBadge mode={voucher.audience_mode} />
				</div>
				{voucher.description ? (
					<p className="zc-text-sm zc-text-zinc-600">{voucher.description}</p>
				) : null}
			</header>

			<dl className="zc-grid zc-grid-cols-2 zc-gap-3 zc-rounded-md zc-border zc-border-zinc-200 zc-bg-zinc-50/60 zc-p-4 zc-text-sm">
				<Field label="Code">
					{isMulti ? (
						<span className="zc-inline-flex zc-items-center zc-gap-1.5 zc-rounded zc-bg-violet-50 zc-px-2 zc-py-0.5 zc-text-xs zc-font-medium zc-text-violet-700">
							Multi-code campaign
						</span>
					) : (
						<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-font-mono zc-text-xs zc-text-zinc-800">
							{voucher.code}
						</code>
					)}
				</Field>
				<Field label="Discount">{discount}</Field>
				<Field label="Distribution">
					{isMulti ? "Multi-code (public)" : "Single code"}
				</Field>
				<Field label="Audience">{audienceLabel(voucher.audience_mode)}</Field>
				<Field label="Min order">
					{voucher.min_order_amount > 0 ? money(voucher.min_order_amount) : "—"}
				</Field>
				<Field label="Used / Max">{usedLabel}</Field>
				<Field label="Starts">{voucher.starts_at ? date(voucher.starts_at) : "—"}</Field>
				<Field label="Expires">{voucher.expires_at ? date(voucher.expires_at) : "—"}</Field>
			</dl>

			{voucher.status === "draft" && onEdit ? (
				<div className="zc-flex zc-justify-end">
					<Button variant="outline" onClick={() => onEdit(voucher)}>Edit voucher</Button>
				</div>
			) : voucher.status !== "draft" ? (
				<p className="zc-text-xs zc-text-zinc-500">
					This voucher is {voucher.status}. To change its settings, duplicate it as a draft from the row's ⋯ menu.
				</p>
			) : null}
		</div>
	);
}

function Field({ label, children }) {
	return (
		<div>
			<dt className="zc-text-[10px] zc-uppercase zc-tracking-wider zc-text-zinc-500">{label}</dt>
			<dd className="zc-mt-0.5 zc-text-zinc-900">{children}</dd>
		</div>
	);
}

function AudienceBadge({ mode }) {
	if (mode === "tier") {
		return (
			<span className="zc-inline-flex zc-items-center zc-rounded zc-bg-amber-50 zc-px-1.5 zc-py-0.5 zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wide zc-text-amber-700">
				Tier
			</span>
		);
	}
	if (mode === "email") {
		return (
			<span className="zc-inline-flex zc-items-center zc-rounded zc-bg-sky-50 zc-px-1.5 zc-py-0.5 zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wide zc-text-sky-700">
				Customer
			</span>
		);
	}
	return null;
}

function audienceLabel(mode) {
	if (mode === "tier")  return "Membership tiers";
	if (mode === "email") return "Specific customers";
	return "Public — anyone can claim";
}
