import { useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { AvailableList } from "./AvailableList.jsx";
import { ClaimsList } from "./ClaimsList.jsx";
import { VouchersSkeleton } from "./VouchersSkeleton.jsx";

const SUBTABS = [
	{ key: "available", label: "Available" },
	{ key: "claims",    label: "My Claims" },
];

export default function VouchersTab() {
	const [tab, setTab] = useState("available");

	const available = useApiQuery("/vouchers");
	const claims    = useApiQuery("/vouchers/claims");

	if (available.isLoading || claims.isLoading) return <VouchersSkeleton />;

	const counts = {
		available: available.data?.items?.length ?? 0,
		claims:    claims.data?.items?.length    ?? 0,
	};

	return (
		<div className="zc-space-y-5">
			<SubTabs value={tab} onChange={setTab} counts={counts} />

			{tab === "available"
				? <AvailableList query={available} />
				: <ClaimsList    query={claims} />}
		</div>
	);
}

function SubTabs({ value, onChange, counts }) {
	return (
		<div className="zc-inline-flex zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-1">
			{SUBTABS.map((t) => {
				const active = value === t.key;
				return (
					<button
						key={t.key}
						type="button"
						onClick={() => onChange(t.key)}
						className={[
							"zc-flex zc-items-center zc-gap-2 zc-rounded-md zc-px-4 zc-py-1.5 zc-text-sm zc-font-medium zc-transition-colors",
							active
								? "zc-bg-white zc-text-zinc-900 zc-shadow-sm"
								: "zc-text-zinc-600 hover:zc-text-zinc-900",
						].join(" ")}
					>
						<span>{t.label}</span>
						<span className={[
							"zc-rounded-full zc-px-1.5 zc-py-0.5 zc-text-xs zc-font-semibold",
							active ? "zc-bg-zinc-900 zc-text-white" : "zc-bg-zinc-200 zc-text-zinc-600",
						].join(" ")}>
							{counts[t.key]}
						</span>
					</button>
				);
			})}
		</div>
	);
}
