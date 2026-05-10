import { Badge } from "@/js/shared/ui/badge.jsx";
import { categoryFor, labelFor } from "./events.js";

/**
 * Two-piece badge for an audit event:
 *   [ category ] [ event label ]
 *
 * Category drives the color (so a row's chip is recognizable from across
 * the table); event label sits next to it so the admin doesn't have to
 * decode `voucher.published` themselves.
 */
export function EventBadge({ event }) {
	const cat = categoryFor(event);
	return (
		<span className="zc-inline-flex zc-items-center zc-gap-1.5">
			<Badge variant={cat.tone}>{cat.label}</Badge>
			<span className="zc-text-xs zc-text-zinc-700">{labelFor(event)}</span>
		</span>
	);
}
