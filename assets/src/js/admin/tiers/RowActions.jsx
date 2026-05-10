import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { useConfirm } from "@/js/shared/components/ConfirmDialog.jsx";
import { OverflowMenu } from "../vouchers/OverflowMenu.jsx";

/**
 * Per-row actions for the Tiers table.
 *
 * Delete is server-side guarded — refused if any members are on the tier
 * (returns 409 + a member count). The confirm dialog warns the admin
 * upfront when there's a non-zero member count, then surfaces the server's
 * refusal inline if it still fails.
 */
export function RowActions({ row, onEdit }) {
	const lists   = ["/admin/tiers", "/tiers"];
	const confirm = useConfirm();
	const remove  = useApiMutation("del", `/admin/tiers/${row.slug}`, { invalidate: lists });

	const onDelete = async () => {
		const hasMembers = row.member_count > 0;
		await confirm({
			title:        `Delete tier "${row.label}"?`,
			message: hasMembers
				? `This tier has ${row.member_count} member${row.member_count === 1 ? "" : "s"} assigned. Server will refuse — move them to another tier first.`
				: "This cannot be undone.",
			confirmLabel: "Delete",
			tone:         "danger",
			onConfirm:    () => new Promise((resolve, reject) => {
				remove.mutate(undefined, {
					onSuccess: resolve,
					onError:   (err) => reject(new Error(err?.message || "Could not delete.")),
				});
			}),
		});
	};

	const items = [
		{ label: "Edit",   onSelect: () => onEdit(row) },
		{ label: "Delete", onSelect: onDelete, variant: "danger", disabled: remove.isPending },
	];

	return <OverflowMenu items={items} />;
}
