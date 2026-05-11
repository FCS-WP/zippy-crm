import { useState } from "react";
import { ClaimedCodeDialog, VoucherCard } from "./VoucherCard.jsx";
import { EmptyState } from "@/js/shared/components/EmptyState.jsx";

/**
 * Holds the post-claim modal state. The state has to live here (not on
 * VoucherCard) because the just-claimed card unmounts when /vouchers
 * refetches and drops the claimed row — taking any state on the card with
 * it. AvailableList stays mounted regardless, so the modal survives.
 */
export function AvailableList({ query }) {
	const { data, isError, error } = query;
	const [claim, setClaim] = useState(null);

	if (isError)
		return <p className="zc-text-rose-600">{error?.message ?? "Failed to load vouchers."}</p>;

	const items = data?.items ?? [];
	if (items.length === 0 && !claim) {
		return (
			<EmptyState
				title="No vouchers available right now"
				description="Check back later — new offers drop regularly."
			/>
		);
	}

	return (
		<>
			<div className="zc-grid zc-gap-4 sm:zc-grid-cols-2">
				{items.map((v) => (
					<VoucherCard key={v.id} voucher={v} onClaimed={setClaim} />
				))}
			</div>
			{claim ? <ClaimedCodeDialog claim={claim} onClose={() => setClaim(null)} /> : null}
		</>
	);
}
