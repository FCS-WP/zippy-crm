import { useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { HeroBalance } from "./HeroBalance.jsx";
import { InsightsStrip } from "./InsightsStrip.jsx";
import { RedeemCTA } from "./RedeemCTA.jsx";
import { LedgerTable } from "./LedgerTable.jsx";
import { PointsSkeleton } from "./PointsSkeleton.jsx";

export default function PointsTab() {
	const summary    = useApiQuery("/points/me");
	const membership = useApiQuery("/membership/me"); // dedup'd if Membership tab pre-cached
	const [page, setPage] = useState(1);
	const ledger     = useApiQuery("/points/ledger", { params: { page, per_page: 10 } });

	if (summary.isLoading) return <PointsSkeleton />;
	if (summary.isError)
		return <p className="zc-text-rose-600">{summary.error?.message ?? "Failed to load points."}</p>;

	return (
		<div className="zc-space-y-5">
			<HeroBalance summary={summary.data} membership={membership.data} />

			<InsightsStrip summary={summary.data} ledger={ledger.data} />

			<div className="zc-grid zc-gap-5 lg:zc-grid-cols-3">
				<div className="lg:zc-col-span-2">
					<LedgerTable
						query={ledger}
						page={page}
						onPageChange={setPage}
						redemptionRate={summary.data.redemption_rate}
					/>
				</div>
				<RedeemCTA summary={summary.data} />
			</div>
		</div>
	);
}
