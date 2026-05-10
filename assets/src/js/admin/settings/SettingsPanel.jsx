import { useEffect, useState } from "react";
import { useApiQuery, useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { CatalogPickerField } from "../vouchers/sections/CatalogPickerField.jsx";

/**
 * Site-wide settings panel. Currently scoped to the points-earning blacklist
 * (excluded products + excluded categories). The earn rate per tier still
 * lives on the Tiers panel — this page links there.
 *
 * Follows the existing admin pattern: load via useApiQuery, mutate via
 * useApiMutation, optimistic invalidation. Save button is sticky-disabled
 * until the form differs from the loaded state.
 */
export default function SettingsPanel() {
	const { data, isLoading, error } = useApiQuery("/admin/settings/points");
	const save = useApiMutation("put", "/admin/settings/points", { invalidate: ["/admin/settings/points"] });

	const [productIds, setProductIds]   = useState([]);
	const [categoryIds, setCategoryIds] = useState([]);
	const [savedAt, setSavedAt] = useState(null);

	// Hydrate local state once the GET resolves. Subsequent saves invalidate
	// the query, refetch, and re-hydrate via this same effect.
	useEffect(() => {
		if (!data) return;
		setProductIds((data.excluded_products ?? []).map((p) => p.id));
		setCategoryIds((data.excluded_categories ?? []).map((c) => c.id));
	}, [data]);

	const dirty =
		!arraysEqual(productIds, (data?.excluded_products ?? []).map((p) => p.id)) ||
		!arraysEqual(categoryIds, (data?.excluded_categories ?? []).map((c) => c.id));

	const onSave = () => {
		save.mutate(
			{ excluded_product_ids: productIds, excluded_category_ids: categoryIds },
			{ onSuccess: () => setSavedAt(new Date()) },
		);
	};

	if (isLoading) {
		return <div className="zc-p-6 zc-text-sm zc-text-zinc-500">Loading settings…</div>;
	}
	if (error) {
		return (
			<div className="zc-p-6">
				<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-3 zc-text-sm zc-text-rose-700">
					{error.message || "Could not load settings."}
				</div>
			</div>
		);
	}

	return (
		<div className="zc-space-y-6 zc-p-6">
			<header>
				<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Settings</h1>
				<p className="zc-text-sm zc-text-zinc-500">
					Site-wide configuration for how points are earned.
				</p>
			</header>

			<EarnRateCard />

			<Card
				title="Excluded products"
				description="Products on this list earn no points. Useful for gift cards, deposits, services, or items you don't want to reward."
			>
				<CatalogPickerField
					kind="products"
					label="Excluded products"
					value={productIds}
					onChange={setProductIds}
					placeholder="Empty — every product earns points (subject to category exclusions below)"
				/>
			</Card>

			<Card
				title="Excluded categories"
				description="Every product in any of these categories earns no points. Picking a category here is faster than listing each product if your store organizes excluded items by category."
			>
				<CatalogPickerField
					kind="categories"
					label="Excluded categories"
					value={categoryIds}
					onChange={setCategoryIds}
					placeholder="Empty — every category earns points"
				/>
			</Card>

			<div className="zc-sticky zc-bottom-0 zc-flex zc-items-center zc-justify-end zc-gap-3 zc-border-t zc-border-zinc-200 zc-bg-white zc-p-4 zc-shadow-sm">
				{savedAt && !dirty ? (
					<span className="zc-text-xs zc-text-emerald-700">
						Saved {timeAgo(savedAt)}
					</span>
				) : null}
				<Button onClick={onSave} disabled={!dirty || save.isPending} loading={save.isPending}>
					Save changes
				</Button>
			</div>

			{save.isError ? (
				<div className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-3 zc-text-sm zc-text-rose-700">
					{save.error?.message || "Could not save."}
				</div>
			) : null}
		</div>
	);
}

function Card({ title, description, children }) {
	return (
		<section className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-p-5 zc-shadow-sm">
			<header className="zc-mb-3">
				<h2 className="zc-text-base zc-font-semibold zc-text-zinc-900">{title}</h2>
				{description ? <p className="zc-mt-1 zc-text-sm zc-text-zinc-500">{description}</p> : null}
			</header>
			{children}
		</section>
	);
}

function EarnRateCard() {
	const adminUrl = (window.zippyCrmAdmin && window.zippyCrmAdmin.tiersUrl) || "admin.php?page=zippy-crm-tiers";
	return (
		<section className="zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-5">
			<h2 className="zc-text-base zc-font-semibold zc-text-zinc-900">Earn rate</h2>
			<p className="zc-mt-1 zc-text-sm zc-text-zinc-500">
				The earn rate (points per $1) is set per membership tier. New tiers default to <strong>0</strong>{" "}
				(no earning) — admins must explicitly opt in to awarding points.
			</p>
			<a
				href={adminUrl}
				className="zc-mt-3 zc-inline-flex zc-items-center zc-gap-1.5 zc-text-sm zc-font-medium zc-text-zinc-900 hover:zc-underline"
			>
				Configure tier rates →
			</a>
		</section>
	);
}

function arraysEqual(a, b) {
	if (a.length !== b.length) return false;
	const sa = [...a].sort();
	const sb = [...b].sort();
	for (let i = 0; i < sa.length; i++) if (sa[i] !== sb[i]) return false;
	return true;
}

function timeAgo(date) {
	const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
	if (seconds < 5) return "just now";
	if (seconds < 60) return `${seconds}s ago`;
	const minutes = Math.floor(seconds / 60);
	return `${minutes}m ago`;
}
