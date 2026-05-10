import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { Card, CardContent, CardHeader } from "@/js/shared/ui/card.jsx";

export function MembershipSkeleton() {
	return (
		<div className="zc-space-y-4">
			{/* Hero */}
			<Card>
				<CardContent className="zc-p-6 zc-space-y-4">
					<div className="zc-flex zc-items-start zc-justify-between zc-gap-4">
						<div className="zc-space-y-2">
							<Skeleton className="zc-h-3 zc-w-24" />
							<Skeleton className="zc-h-6 zc-w-48" />
							<Skeleton className="zc-h-3 zc-w-32" />
						</div>
						<Skeleton className="zc-h-6 zc-w-24" />
					</div>
					<Skeleton className="zc-h-10 zc-w-full" />
					<div className="zc-grid zc-grid-cols-3 zc-gap-4">
						<Skeleton className="zc-h-10" />
						<Skeleton className="zc-h-10" />
						<Skeleton className="zc-h-10" />
					</div>
				</CardContent>
			</Card>

			{/* Stats */}
			<div className="zc-grid zc-grid-cols-2 zc-gap-3 sm:zc-grid-cols-3">
				<Skeleton className="zc-h-16 zc-rounded-xl" />
				<Skeleton className="zc-h-16 zc-rounded-xl" />
				<Skeleton className="zc-h-16 zc-rounded-xl" />
			</div>

			{/* Two-column body */}
			<div className="zc-grid zc-gap-4 lg:zc-grid-cols-3">
				<div className="lg:zc-col-span-2 zc-space-y-4">
					<Card>
						<CardHeader><Skeleton className="zc-h-5 zc-w-40" /></CardHeader>
						<CardContent className="zc-space-y-3">
							<Skeleton className="zc-h-4 zc-w-full" />
							<Skeleton className="zc-h-2 zc-w-full" />
						</CardContent>
					</Card>
					<Card>
						<CardHeader><Skeleton className="zc-h-5 zc-w-40" /></CardHeader>
						<CardContent className="zc-space-y-2">
							<Skeleton className="zc-h-12 zc-w-full" />
							<Skeleton className="zc-h-12 zc-w-full" />
							<Skeleton className="zc-h-12 zc-w-full" />
							<Skeleton className="zc-h-12 zc-w-full" />
						</CardContent>
					</Card>
				</div>
				<div className="zc-space-y-4">
					<Skeleton className="zc-h-36 zc-rounded-xl" />
					<Skeleton className="zc-h-28 zc-rounded-xl" />
				</div>
			</div>
		</div>
	);
}
