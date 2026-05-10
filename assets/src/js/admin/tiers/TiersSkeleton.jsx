import { Card } from "@/js/shared/ui/card.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";

export function TiersSkeleton() {
	return (
		<Card className="zc-overflow-hidden">
			{[0, 1, 2, 3].map((i) => (
				<div key={i} className="zc-flex zc-items-center zc-gap-4 zc-border-b zc-border-zinc-100 zc-p-4 last:zc-border-b-0">
					<Skeleton className="zc-h-5 zc-w-20" />
					<Skeleton className="zc-h-5 zc-w-32" />
					<Skeleton className="zc-h-4 zc-w-12" />
					<Skeleton className="zc-h-4 zc-w-20" />
					<Skeleton className="zc-h-4 zc-w-20" />
					<Skeleton className="zc-h-4 zc-w-12" />
					<Skeleton className="zc-ml-auto zc-h-8 zc-w-8" />
				</div>
			))}
		</Card>
	);
}
