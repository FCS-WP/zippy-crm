import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { HeroCard } from "./HeroCard.jsx";
import { StatsGrid } from "./StatsGrid.jsx";
import { TierProgress } from "./TierProgress.jsx";
import { TierLadder } from "./TierLadder.jsx";
import { PointsWidget } from "./PointsWidget.jsx";
import { VouchersWidget } from "./VouchersWidget.jsx";
import { MembershipSkeleton } from "./MembershipSkeleton.jsx";

export default function MembershipTab() {
	const { data, isLoading, isError, error } = useApiQuery("/membership/me");

	if (isLoading) return <MembershipSkeleton />;
	if (isError)   return <p className="zc-text-rose-600">{error?.message ?? "Failed to load membership."}</p>;
	if (!data)     return null;

	return (
		<div className="zc-space-y-4">
			<HeroCard membership={data} />

			<StatsGrid stats={data.stats} />

			<div className="zc-grid zc-gap-4 lg:zc-grid-cols-3">
				<div className="lg:zc-col-span-2 zc-space-y-4">
					<TierProgress membership={data} />
					<TierLadder membership={data} />
				</div>
				<div className="zc-space-y-4">
					<PointsWidget   points={data.points}     link={data.links?.points} />
					<VouchersWidget vouchers={data.vouchers} link={data.links?.vouchers} />
				</div>
			</div>
		</div>
	);
}
