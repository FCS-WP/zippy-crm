import { useEffect, useState } from "react";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { api } from "@/js/shared/api.js";
import { Drawer } from "../vouchers/Drawer.jsx";
import { Button } from "@/js/shared/ui/button.jsx";
import { Input } from "@/js/shared/ui/input.jsx";

/**
 * Add an existing WP user to the CRM as a member.
 *
 * Server: GET /admin/users?has_membership=no powers the picker;
 * POST /admin/members/enroll seeds the rows. We don't create new WP
 * users here — the admin uses WP Admin → Users → Add New for that.
 *
 * Success path lifts the new user_id back to the parent so it can
 * open the member detail drawer next (per the agreed UX).
 */
export function AddMemberDialog({ open, onClose, onEnrolled }) {
	const qc = useQueryClient();
	const [search, setSearch] = useState("");
	const [debounced, setDebounced] = useState("");

	// Debounce the search input. 200ms is short enough to feel
	// responsive but cuts the rate enough to not hammer the API on
	// every keystroke.
	useEffect(() => {
		const t = setTimeout(() => setDebounced(search.trim()), 200);
		return () => clearTimeout(t);
	}, [search]);

	// Reset when the dialog reopens so a fresh session doesn't carry
	// the previous search.
	useEffect(() => {
		if (open) { setSearch(""); setDebounced(""); }
	}, [open]);

	// Only query once the dialog is open AND there's at least one
	// character to search. Empty searches would return the full
	// non-member list — fine technically but visually noisy.
	const results = useApiQuery("/admin/users", {
		params: { has_membership: "no", search: debounced, per_page: 10 },
		enabled: open && debounced.length > 0,
	});

	const enroll = useMutation({
		mutationFn: (userId) => api.post("/admin/members/enroll", { user_id: userId }),
		onSuccess: (data) => {
			// Refresh the Members table + per-user-cache so the new row
			// appears immediately when the drawer closes.
			qc.invalidateQueries({ queryKey: ["/admin/members"] });
			const newUserId = data?.user?.id;
			if (newUserId) onEnrolled?.(newUserId);
		},
	});

	const items = results.data?.items ?? [];

	return (
		<Drawer open={open} onClose={onClose} title="Add member" width="zc-max-w-md">
			<div className="zc-space-y-4">
				<p className="zc-text-sm zc-text-zinc-500">
					Search by name, email, or username. Only Users who aren't already members are shown.
				</p>

				<Input
					type="text"
					value={search}
					onChange={(e) => setSearch(e.target.value)}
					placeholder="Start typing to search…"
					autoFocus
				/>

				<UserList
					query={debounced}
					results={results}
					items={items}
					enrolling={enroll.variables}
					isEnrolling={enroll.isPending}
					onPick={(userId) => enroll.mutate(userId)}
				/>

				{enroll.isError ? (
					<p className="zc-text-sm zc-text-rose-700">
						{enroll.error?.message || "Could not enroll this user."}
					</p>
				) : null}

				<div className="zc-flex zc-justify-end">
					<Button variant="ghost" onClick={onClose}>Close</Button>
				</div>
			</div>
		</Drawer>
	);
}

function UserList({ query, results, items, enrolling, isEnrolling, onPick }) {
	if (query.length === 0) {
		return (
			<p className="zc-rounded-md zc-bg-zinc-50 zc-p-3 zc-text-xs zc-text-zinc-500">
				Type at least one character to start searching.
			</p>
		);
	}
	if (results.isLoading) {
		return (
			<p className="zc-rounded-md zc-bg-zinc-50 zc-p-3 zc-text-xs zc-text-zinc-500">
				Searching…
			</p>
		);
	}
	if (results.isError) {
		return (
			<p className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-3 zc-text-xs zc-text-rose-700">
				{results.error?.message || "Could not load users."}
			</p>
		);
	}
	if (items.length === 0) {
		return (
			<p className="zc-rounded-md zc-bg-zinc-50 zc-p-3 zc-text-xs zc-text-zinc-500">
				No matching users. (Users who are already members are hidden.)
			</p>
		);
	}
	return (
		<ul className="zc-divide-y zc-divide-zinc-100 zc-rounded-md zc-border zc-border-zinc-200">
			{items.map((u) => (
				<li key={u.user_id} className="zc-flex zc-items-center zc-justify-between zc-gap-3 zc-px-3 zc-py-2.5">
					<div className="zc-min-w-0">
						<p className="zc-truncate zc-text-sm zc-font-medium zc-text-zinc-900">
							{u.display_name || u.user_login}
						</p>
						<p className="zc-truncate zc-text-xs zc-text-zinc-500">
							{u.user_email}
						</p>
					</div>
					<Button
						size="sm"
						onClick={() => onPick(u.user_id)}
						loading={isEnrolling && enrolling === u.user_id}
					>
						Enroll
					</Button>
				</li>
			))}
		</ul>
	);
}
