import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { useConfirm } from "@/js/shared/components/ConfirmDialog.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { OverflowMenu } from "./OverflowMenu.jsx";

/**
 * Per-row action group: status-driven primary button on the left, ⋯ overflow
 * menu on the right. Overflow items are filtered by status (Edit/Delete only
 * on draft).
 *
 * Publish / Pause / Delete prompt via the shared ConfirmDialog so the admin
 * sees a themed modal with inline error reporting + spinner. Resume fires
 * immediately (it's just undoing pause, no customer-facing surprise).
 */
export function RowActions({ row, onEdit }) {
	const { id, status } = row;
	const list    = "/admin/vouchers";
	const confirm = useConfirm();

	const publish   = useApiMutation("post", `/admin/vouchers/${id}/publish`,   { invalidate: [list] });
	const pause     = useApiMutation("post", `/admin/vouchers/${id}/pause`,     { invalidate: [list] });
	const resume    = useApiMutation("post", `/admin/vouchers/${id}/resume`,    { invalidate: [list] });
	const duplicate = useApiMutation("post", `/admin/vouchers/${id}/duplicate`, { invalidate: [list] });
	const remove    = useApiMutation("del",  `/admin/vouchers/${id}`,           { invalidate: [list] });

	const busy = publish.isPending || pause.isPending || resume.isPending || duplicate.isPending || remove.isPending;

	/**
	 * Wrap a mutation in a confirm prompt. The dialog stays open while the
	 * request is in flight (spinner on the confirm button) and surfaces
	 * server errors inline so the admin can retry without losing context.
	 */
	const runWithConfirm = async (mutation, opts) => {
		await confirm({
			...opts,
			onConfirm: () => new Promise((resolve, reject) => {
				mutation.mutate(undefined, {
					onSuccess: resolve,
					onError:   (err) => reject(new Error(err?.message || "Could not complete the action.")),
				});
			}),
		});
	};

	const onPublish = () => runWithConfirm(publish, {
		title:        `Publish voucher "${row.code}"?`,
		message:      "Customers will see it immediately and a WooCommerce coupon will be created.",
		confirmLabel: "Publish",
	});

	const onPause = () => runWithConfirm(pause, {
		title:        `Pause voucher "${row.code}"?`,
		message:      "Customers won't be able to claim it until you resume.",
		confirmLabel: "Pause",
		tone:         "danger",
	});

	const onDelete = () => runWithConfirm(remove, {
		title:        `Delete voucher "${row.code}"?`,
		message:      "This cannot be undone.",
		confirmLabel: "Delete",
		tone:         "danger",
	});

	// Primary inline action — exactly one button driven by status.
	const primary = (() => {
		if (status === "draft")  return { label: "Publish", variant: "primary", run: publish, onClick: onPublish };
		if (status === "active") return { label: "Pause",   variant: "outline", run: pause,   onClick: onPause   };
		if (status === "paused") return { label: "Resume",  variant: "outline", run: resume,  onClick: () => resume.mutate() };
		return null;  // expired → no inline primary; everything's in the menu.
	})();

	// Verbs only — the read-only views (Claims, Codes, summary) live in the
	// Detail drawer that opens when the admin clicks the row itself.
	const items = [
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
					onClick={primary.onClick}
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
