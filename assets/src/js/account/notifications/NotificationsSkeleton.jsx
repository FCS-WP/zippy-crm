import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { Card, CardContent, CardHeader } from "@/js/shared/ui/card.jsx";

export function NotificationsSkeleton() {
	return (
		<div className="zc-max-w-2xl">
			<Card>
				<CardHeader>
					<Skeleton className="zc-h-5 zc-w-48" />
					<Skeleton className="zc-h-4 zc-w-72" />
				</CardHeader>
				<CardContent className="zc-space-y-4">
					<div className="zc-flex zc-items-start zc-justify-between zc-gap-4 zc-py-2">
						<div className="zc-flex-1 zc-space-y-2">
							<Skeleton className="zc-h-4 zc-w-1/2" />
							<Skeleton className="zc-h-3 zc-w-3/4" />
						</div>
						<Skeleton className="zc-h-6 zc-w-11 zc-rounded-full" />
					</div>
					<div className="zc-flex zc-items-start zc-justify-between zc-gap-4 zc-py-2">
						<div className="zc-flex-1 zc-space-y-2">
							<Skeleton className="zc-h-4 zc-w-1/2" />
							<Skeleton className="zc-h-3 zc-w-3/4" />
						</div>
						<Skeleton className="zc-h-6 zc-w-11 zc-rounded-full" />
					</div>
				</CardContent>
			</Card>
		</div>
	);
}
