import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { OverflowMenu } from "../vouchers/OverflowMenu.jsx";

/**
 * Per-row action menu. Suspend/Activate is in the menu (not as a primary
 * inline button) because most rows are already active — the inline space is
 * better spent on whatever the next per-row primary becomes (TBD).
 *
 * The menu's destructive actions invalidate `/admin/members` so the list +
 * counts refresh.
 */
export function RowActions({ row, onView, onChangeLevel, onAdjustPoints }) {
	const list = "/admin/members";
	const setStatus = useApiMutation(
		"post",
		`/admin/members/${row.user_id}/status`,
		{ invalidate: [list] },
	);

	const isActive = row.status === "active";

	const items = [
		{ label: "View detail",   onSelect: () => onView(row) },
		{ label: "Change level",  onSelect: () => onChangeLevel(row) },
		{ label: "Adjust points", onSelect: () => onAdjustPoints(row) },
		isActive
			? { label: "Suspend",  onSelect: () => setStatus.mutate({ status: "suspended" }), variant: "danger" }
			: { label: "Activate", onSelect: () => setStatus.mutate({ status: "active"    }) },
	];

	return <OverflowMenu items={items} />;
}
