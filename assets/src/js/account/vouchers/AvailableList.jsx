import { VoucherCard } from "./VoucherCard.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";

export function AvailableList({ query }) {
	const { data, isError, error } = query;

	if (isError)
		return <p className="zc-text-rose-600">{error?.message ?? "Failed to load vouchers."}</p>;

	const items = data?.items ?? [];
	if (items.length === 0) {
		return (
			<EmptyState
				title="No vouchers available right now"
				description="Check back later — new offers drop regularly."
			/>
		);
	}

	return (
		<div className="zc-grid zc-gap-4 sm:zc-grid-cols-2">
			{items.map((v) => <VoucherCard key={v.id} voucher={v} />)}
		</div>
	);
}
