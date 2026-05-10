import { useEffect, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Input } from "@/js/shared/ui/input.jsx";
import { Drawer } from "../vouchers/Drawer.jsx";

/**
 * Single-user picker for the audit panel filters. Returns a user ID (number)
 * or 0 for "no selection". The trigger pill shows the picked user's name;
 * a small 'x' clears it.
 *
 * Why not reuse EmailRestrictionsField's user picker?
 *   - EmailRestrictions stores by email + supports guest emails too
 *   - here we need a numeric user_id (audit endpoint filters by id)
 *   - EmailRestrictions is multi-select; audit filter is single-select
 * The shared bit is the search-as-you-type pattern; the rest diverges
 * enough that keeping this local beats a complex polymorphic picker.
 */
export function UserPickerField({ value, onChange, placeholder = "Pick a user" }) {
	const [open, setOpen] = useState(false);

	// Resolve current ID back to display info so the chip shows a name.
	const resolved = useApiQuery("/admin/catalog/customers", {
		params: value > 0 ? { ids: String(value) } : undefined,
		enabled: value > 0,
	});
	const picked = resolved.data?.items?.[0] ?? null;

	const display = picked
		? (picked.display_name || picked.login || `#${value}`)
		: placeholder;
	const hasValue = value > 0;

	return (
		<>
			<button
				type="button"
				onClick={() => setOpen(true)}
				className={[
					"zc-inline-flex zc-h-9 zc-items-center zc-gap-2 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-pl-2.5 zc-pr-1 zc-text-sm zc-text-zinc-900 zc-transition-colors hover:zc-bg-zinc-50",
					!hasValue && "zc-text-zinc-400",
				].filter(Boolean).join(" ")}
			>
				<svg viewBox="0 0 24 24" className="zc-size-4 zc-text-zinc-500" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
					<path strokeLinecap="round" strokeLinejoin="round" d="M16 11a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM3 21a9 9 0 0 1 18 0" />
				</svg>
				<span className="zc-truncate zc-max-w-[10rem]">{display}</span>
				{hasValue ? (
					<span
						role="button"
						tabIndex={0}
						aria-label="Clear filter"
						onClick={(e) => { e.stopPropagation(); onChange(0); }}
						onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); onChange(0); } }}
						className="zc-rounded zc-p-0.5 zc-text-zinc-400 hover:zc-bg-zinc-100 hover:zc-text-zinc-700"
					>
						<svg viewBox="0 0 24 24" className="zc-size-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
							<path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
						</svg>
					</span>
				) : null}
			</button>

			<Drawer
				open={open}
				onClose={() => setOpen(false)}
				title="Pick a user"
				width="zc-max-w-md"
			>
				<PickerBody
					selectedId={value}
					onPick={(id) => { onChange(id); setOpen(false); }}
					onClose={() => setOpen(false)}
				/>
			</Drawer>
		</>
	);
}

function PickerBody({ selectedId, onPick, onClose }) {
	const [search, setSearch] = useState("");
	const debounced = useDebounced(search, 250);

	const list = useApiQuery("/admin/catalog/customers", {
		params: { search: debounced, per_page: 30 },
		enabled: debounced.length > 0,
	});
	const items = list.data?.items ?? [];

	return (
		<div className="zc-space-y-3">
			<Input
				type="text"
				autoFocus
				placeholder="Search users by name, login, or email…"
				value={search}
				onChange={(e) => setSearch(e.target.value)}
			/>

			{debounced === "" ? (
				<p className="zc-rounded-md zc-border zc-border-dashed zc-border-zinc-200 zc-p-4 zc-text-center zc-text-xs zc-text-zinc-500">
					Start typing to search.
				</p>
			) : list.isLoading ? (
				<p className="zc-text-sm zc-text-zinc-500">Searching…</p>
			) : list.error ? (
				<p className="zc-text-sm zc-text-rose-700">{list.error.message || "Search failed."}</p>
			) : items.length === 0 ? (
				<p className="zc-text-sm zc-text-zinc-500">No matches.</p>
			) : (
				<ul className="zc-divide-y zc-divide-zinc-100 zc-overflow-hidden zc-rounded-md zc-border zc-border-zinc-200">
					{items.map((user) => {
						const fullName = [ user.first_name, user.last_name ].filter(Boolean).join(" ");
						const heading  = fullName || user.display_name || user.login || `(no name)`;
						const isPicked = selectedId === user.id;
						return (
							<li key={user.id}>
								<button
									type="button"
									onClick={() => onPick(user.id)}
									className={[
										"zc-flex zc-w-full zc-items-center zc-gap-3 zc-px-3 zc-py-2 zc-text-left zc-text-sm zc-transition-colors",
										isPicked ? "zc-bg-zinc-100" : "zc-bg-white hover:zc-bg-zinc-50",
									].join(" ")}
								>
									<div className="zc-min-w-0 zc-flex-1">
										<div className="zc-truncate zc-font-medium zc-text-zinc-900">{heading}</div>
										<div className="zc-truncate zc-text-xs zc-text-zinc-500">
											{user.email}
											{user.login && user.login !== heading ? (
												<span className="zc-ml-1.5 zc-text-zinc-400">@{user.login}</span>
											) : null}
										</div>
									</div>
									<span className="zc-text-[10px] zc-text-zinc-400">#{user.id}</span>
								</button>
							</li>
						);
					})}
				</ul>
			)}

			<div className="zc-flex zc-justify-end zc-pt-2">
				<button
					type="button"
					onClick={onClose}
					className="zc-text-xs zc-text-zinc-500 hover:zc-text-zinc-900"
				>
					Cancel
				</button>
			</div>
		</div>
	);
}

function useDebounced(value, ms) {
	const [v, setV] = useState(value);
	useEffect(() => {
		const t = setTimeout(() => setV(value), ms);
		return () => clearTimeout(t);
	}, [value, ms]);
	return v;
}
