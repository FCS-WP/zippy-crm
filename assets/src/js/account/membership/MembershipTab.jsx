import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { LevelCard } from "./LevelCard.jsx";
import { StatsGrid } from "./StatsGrid.jsx";
import { TierProgress } from "./TierProgress.jsx";
import { MembershipSkeleton } from "./MembershipSkeleton.jsx";

export default function MembershipTab() {
	const { data, isLoading, isError, error } = useApiQuery("/membership/me");

	if (isLoading) return <MembershipSkeleton />;
	if (isError)   return <p className="zc-text-rose-600">{error?.message ?? "Failed to load membership."}</p>;
	if (!data)     return null;

	return (
		<div className="zc-grid zc-gap-4 lg:zc-grid-cols-3">
			<div className="lg:zc-col-span-2 zc-space-y-4">
				<LevelCard membership={data} />
				<TierProgress membership={data} />
			</div>
			<StatsGrid stats={data.stats} />
		</div>
	);
}
