import { Skeleton } from "@/js/shared/ui/skeleton.jsx";

export function VouchersSkeleton() {
	return (
		<div className="zc-space-y-5">
			<Skeleton className="zc-h-10 zc-w-64 zc-rounded-lg" />

			<div className="zc-grid zc-gap-4 sm:zc-grid-cols-2">
				<Skeleton className="zc-h-56 zc-rounded-xl" />
				<Skeleton className="zc-h-56 zc-rounded-xl" />
			</div>
		</div>
	);
}
