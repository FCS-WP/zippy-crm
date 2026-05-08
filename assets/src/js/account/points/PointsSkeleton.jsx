import { Skeleton } from "@/js/shared/ui/skeleton.jsx";

export function PointsSkeleton() {
	return (
		<div className="zc-space-y-5">
			<Skeleton className="zc-h-44 zc-rounded-2xl" />

			<div className="zc-grid zc-gap-3 sm:zc-grid-cols-3">
				<Skeleton className="zc-h-24 zc-rounded-xl" />
				<Skeleton className="zc-h-24 zc-rounded-xl" />
				<Skeleton className="zc-h-24 zc-rounded-xl" />
			</div>

			<div className="zc-grid zc-gap-5 lg:zc-grid-cols-3">
				<Skeleton className="zc-h-80 zc-rounded-xl lg:zc-col-span-2" />
				<Skeleton className="zc-h-80 zc-rounded-xl" />
			</div>
		</div>
	);
}
