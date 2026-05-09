import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { OverflowMenu } from "./OverflowMenu.jsx";

/**
 * Per-row action group: status-driven primary button on the left, ⋯ overflow
 * menu on the right. Overflow items are filtered by status (Edit/Delete only
 * on draft).
 *
 * All mutations invalidate `/admin/vouchers` so the list + counts refresh.
 * Delete uses window.confirm — adequate for a manage_woocommerce-only screen;
 * upgrade to ConfirmDialog if non-technical users start touching this.
 */
export function RowActions({ row, onEdit, onClaims }) {
	const { id, status } = row;
	const list = "/admin/vouchers";

	const publish   = useApiMutation("post", `/admin/vouchers/${id}/publish`,   { invalidate: [list] });
	const pause     = useApiMutation("post", `/admin/vouchers/${id}/pause`,     { invalidate: [list] });
	const resume    = useApiMutation("post", `/admin/vouchers/${id}/resume`,    { invalidate: [list] });
	const duplicate = useApiMutation("post", `/admin/vouchers/${id}/duplicate`, { invalidate: [list] });
	const remove    = useApiMutation("del",  `/admin/vouchers/${id}`,           { invalidate: [list] });

	const busy = publish.isPending || pause.isPending || resume.isPending || duplicate.isPending || remove.isPending;

	const onDelete = () => {
		if (!window.confirm(`Delete voucher "${row.code}"? This cannot be undone.`)) return;
		remove.mutate(undefined, {
			onError: (err) => window.alert(err?.message || "Could not delete."),
		});
	};

	// Primary inline action — exactly one button driven by status.
	const primary = (() => {
		if (status === "draft") return { label: "Publish", variant: "primary", run: publish };
		if (status === "active") return { label: "Pause",   variant: "outline", run: pause };
		if (status === "paused") return { label: "Resume",  variant: "outline", run: resume };
		return null;  // expired → no inline primary; everything's in the menu.
	})();

	// Overflow items. `false` slots are filtered out by OverflowMenu.
	const items = [
		{ label: "Claims",    onSelect: () => onClaims(row), disabled: busy },
		status === "draft" && { label: "Edit", onSelect: () => onEdit(row), disabled: busy },
		{ label: "Duplicate", onSelect: () => duplicate.mutate(), disabled: busy },
		status === "draft" && { label: "Delete", onSelect: onDelete, disabled: busy, variant: "danger" },
	];

	return (
		<div className="zc-flex zc-items-center zc-justify-end zc-gap-1.5">
			{primary ? (
				<Button
					size="sm"
					variant={primary.variant}
					onClick={() => primary.run.mutate()}
					loading={primary.run.isPending}
					disabled={busy && !primary.run.isPending}
				>
					{primary.label}
				</Button>
			) : null}
			<OverflowMenu items={items} />
		</div>
	);
}
