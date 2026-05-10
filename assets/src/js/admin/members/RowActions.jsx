import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { useConfirm } from "@/js/shared/components/ConfirmDialog.jsx";
import { OverflowMenu } from "../vouchers/OverflowMenu.jsx";

/**
 * Per-row action menu. Suspend/Activate is in the menu (not as a primary
 * inline button) because most rows are already active — the inline space is
 * better spent on whatever the next per-row primary becomes (TBD).
 *
 * Suspend prompts via ConfirmDialog (it blocks the user from redeeming
 * vouchers + adjusting points); Activate fires immediately (it's just
 * lifting a previously-applied restriction).
 */
export function RowActions({ row, onView, onChangeLevel, onAdjustPoints }) {
	const list      = "/admin/members";
	const confirm   = useConfirm();
	const setStatus = useApiMutation(
		"post",
		`/admin/members/${row.user_id}/status`,
		{ invalidate: [list] },
	);

	const isActive = row.status === "active";
	const who = row.display_name || row.user_login || `user #${row.user_id}`;

	const onSuspend = async () => {
		await confirm({
			title:        `Suspend ${who}?`,
			message:      "They'll be blocked from redeeming vouchers and from points adjustments until you reactivate.",
			confirmLabel: "Suspend",
			tone:         "danger",
			onConfirm:    () => new Promise((resolve, reject) => {
				setStatus.mutate({ status: "suspended" }, {
					onSuccess: resolve,
					onError:   (err) => reject(new Error(err?.message || "Could not suspend.")),
				});
			}),
		});
	};

	const items = [
		{ label: "View detail",   onSelect: () => onView(row) },
		{ label: "Change level",  onSelect: () => onChangeLevel(row) },
		{ label: "Adjust points", onSelect: () => onAdjustPoints(row) },
		isActive
			? { label: "Suspend",  onSelect: onSuspend, variant: "danger" }
			: { label: "Activate", onSelect: () => setStatus.mutate({ status: "active" }) },
	];

	return <OverflowMenu items={items} />;
}
