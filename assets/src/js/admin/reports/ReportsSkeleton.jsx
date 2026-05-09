import { Card } from "@/js/shared/ui/card.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";

export function ReportsSkeleton({ rows = 3 }) {
	return (
		<div className="zc-space-y-4">
			{Array.from({ length: rows }).map((_, i) => (
				<Card key={i} className="zc-p-5">
					<div className="zc-mb-4 zc-flex zc-items-end zc-justify-between">
						<div className="zc-space-y-2">
							<Skeleton className="zc-h-4 zc-w-32" />
							<Skeleton className="zc-h-3 zc-w-48" />
						</div>
						<Skeleton className="zc-h-7 zc-w-20" />
					</div>
					<Skeleton className="zc-h-[260px] zc-w-full" />
				</Card>
			))}
		</div>
	);
}
