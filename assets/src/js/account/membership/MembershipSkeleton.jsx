import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { Card, CardContent, CardHeader } from "@/js/shared/ui/card.jsx";

export function MembershipSkeleton() {
	return (
		<div className="zc-grid zc-gap-4 lg:zc-grid-cols-3">
			<div className="lg:zc-col-span-2 zc-space-y-4">
				<Card>
					<CardHeader>
						<Skeleton className="zc-h-4 zc-w-24" />
						<Skeleton className="zc-h-6 zc-w-48" />
					</CardHeader>
					<CardContent className="zc-grid zc-grid-cols-3 zc-gap-4">
						<Skeleton className="zc-h-10" />
						<Skeleton className="zc-h-10" />
						<Skeleton className="zc-h-10" />
					</CardContent>
				</Card>
				<Card>
					<CardHeader>
						<Skeleton className="zc-h-5 zc-w-40" />
					</CardHeader>
					<CardContent className="zc-space-y-3">
						<Skeleton className="zc-h-4 zc-w-full" />
						<Skeleton className="zc-h-2 zc-w-full" />
						<Skeleton className="zc-h-4 zc-w-2/3" />
					</CardContent>
				</Card>
			</div>
			<div className="zc-space-y-4">
				<Skeleton className="zc-h-24 zc-rounded-xl" />
				<Skeleton className="zc-h-24 zc-rounded-xl" />
			</div>
		</div>
	);
}
