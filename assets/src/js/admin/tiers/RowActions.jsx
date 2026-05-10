import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { OverflowMenu } from "../vouchers/OverflowMenu.jsx";

/**
 * Per-row actions for the Tiers table.
 *
 * Delete is server-side guarded — refused if any members are on the tier
 * (returns 409 + a member count). We surface that as an alert; the Members
 * panel is where the admin reassigns those people.
 */
export function RowActions({ row, onEdit }) {
	const lists = ["/admin/tiers", "/tiers"];
	const remove = useApiMutation("del", `/admin/tiers/${row.slug}`, { invalidate: lists });

	const onDelete = () => {
		const msg = row.member_count > 0
			? `Tier "${row.label}" has ${row.member_count} member${row.member_count === 1 ? "" : "s"} assigned. Server will refuse — move them first.`
			: `Delete tier "${row.label}"? This cannot be undone.`;
		if (!window.confirm(msg)) return;
		remove.mutate(undefined, {
			onError: (err) => window.alert(err?.message || "Could not delete."),
		});
	};

	const items = [
		{ label: "Edit",   onSelect: () => onEdit(row) },
		{ label: "Delete", onSelect: onDelete, variant: "danger", disabled: remove.isPending },
	];

	return <OverflowMenu items={items} />;
}
