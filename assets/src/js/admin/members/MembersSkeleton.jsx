import { Card } from "@/js/shared/ui/card.jsx";
import { Skeleton } from "@/js/shared/ui/skeleton.jsx";

export function MembersSkeleton() {
	return (
		<div className="zc-space-y-5">
			<div className="zc-grid zc-grid-cols-2 zc-gap-3 md:zc-grid-cols-4">
				{[0, 1, 2, 3].map((i) => (
					<Card key={i} className="zc-p-4">
						<Skeleton className="zc-h-3 zc-w-16" />
						<Skeleton className="zc-mt-3 zc-h-7 zc-w-12" />
					</Card>
				))}
			</div>
			<Card className="zc-overflow-hidden">
				{[0, 1, 2, 3, 4].map((i) => (
					<div key={i} className="zc-flex zc-items-center zc-gap-4 zc-border-b zc-border-zinc-100 zc-p-4 last:zc-border-b-0">
						<Skeleton className="zc-h-9 zc-w-9 zc-rounded-full" />
						<div className="zc-flex-1 zc-space-y-1">
							<Skeleton className="zc-h-3 zc-w-32" />
							<Skeleton className="zc-h-3 zc-w-48" />
						</div>
						<Skeleton className="zc-h-4 zc-w-16" />
						<Skeleton className="zc-h-4 zc-w-12" />
						<Skeleton className="zc-h-8 zc-w-8" />
					</div>
				))}
			</Card>
		</div>
	);
}
