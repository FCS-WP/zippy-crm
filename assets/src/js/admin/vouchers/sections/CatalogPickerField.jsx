import { useEffect, useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { Input } from "@/js/shared/ui/input.jsx";
import { Drawer } from "../Drawer.jsx";

/**
 * Multi-select picker for product IDs *or* product category IDs. The two
 * flavours share enough behavior (search-debounce, chips, modal layout)
 * that splitting them was duplication; kind="products"|"categories" picks
 * the endpoint + row shape.
 *
 * Trigger row: chips of currently-selected items + "Add" button. Clicking
 * Add opens a Drawer with a search field and result list. Click a row to
 * toggle membership; close the drawer to commit.
 *
 * Empty selection = "all products / all categories" — no restriction.
 */
export function CatalogPickerField({ kind, value, onChange, label, placeholder }) {
	const ids = Array.isArray(value) ? value : [];
	const [open, setOpen] = useState(false);

	// Resolve current IDs back to full row data so chips show names not numbers.
	// Skipped when there are no selections (saves an empty-array fetch round trip).
	const idsCsv = ids.join(",");
	const resolvedQ = useApiQuery(
		kind === "products" ? "/admin/catalog/products" : "/admin/catalog/categories",
		{ params: { ids: idsCsv }, enabled: ids.length > 0 },
	);
	const resolved = resolvedQ.data?.items ?? [];

	// Map id -> display row so the chip-row stays in caller order
	const byId = Object.fromEntries(resolved.map((r) => [r.id, r]));

	const remove = (id) => onChange(ids.filter((x) => x !== id));
	const toggle = (id) => onChange(ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]);

	return (
		<div className="zc-space-y-2">
			<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-1.5 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-p-2">
				{ids.length === 0 ? (
					<span className="zc-px-1 zc-text-xs zc-text-zinc-400">{placeholder}</span>
				) : (
					ids.map((id) => {
						const row = byId[id];
						const labelText = row
							? (kind === "products" ? `${row.name}${row.sku ? ` · ${row.sku}` : ""}` : row.name)
							: `#${id}`;
						return (
							<span
								key={id}
								className="zc-inline-flex zc-items-center zc-gap-1.5 zc-rounded-full zc-bg-zinc-100 zc-py-0.5 zc-pl-2.5 zc-pr-1 zc-text-xs zc-font-medium zc-text-zinc-800"
							>
								<span className="zc-truncate zc-max-w-[16rem]">{labelText}</span>
								<button
									type="button"
									onClick={() => remove(id)}
									aria-label="Remove"
									className="zc-rounded-full zc-p-0.5 zc-text-zinc-500 hover:zc-bg-zinc-200 hover:zc-text-zinc-900"
								>
									<svg viewBox="0 0 24 24" className="zc-size-3" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
										<path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
									</svg>
								</button>
							</span>
						);
					})
				)}
				<button
					type="button"
					onClick={() => setOpen(true)}
					className="zc-ml-auto zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-2 zc-py-1 zc-text-xs zc-font-medium zc-text-zinc-700 hover:zc-bg-zinc-50"
				>
					+ Add
				</button>
			</div>

			<Drawer
				open={open}
				onClose={() => setOpen(false)}
				title={label}
				width="zc-max-w-md"
			>
				<PickerBody
					kind={kind}
					selectedIds={ids}
					onToggle={toggle}
					onClose={() => setOpen(false)}
				/>
			</Drawer>
		</div>
	);
}

function PickerBody({ kind, selectedIds, onToggle, onClose }) {
	const [search, setSearch] = useState("");
	const debounced = useDebounced(search, 250);

	const path = kind === "products" ? "/admin/catalog/products" : "/admin/catalog/categories";
	const list = useApiQuery(path, {
		params: { search: debounced, per_page: 30 },
		enabled: debounced.length > 0,
	});

	const items = list.data?.items ?? [];

	return (
		<div className="zc-space-y-3">
			<Input
				type="text"
				autoFocus
				placeholder={`Search ${kind}…`}
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
					{items.map((row) => {
						const checked = selectedIds.includes(row.id);
						return (
							<li key={row.id}>
								<label className="zc-flex zc-cursor-pointer zc-items-center zc-gap-3 zc-bg-white zc-px-3 zc-py-2 zc-text-sm hover:zc-bg-zinc-50">
									<input
										type="checkbox"
										checked={checked}
										onChange={() => onToggle(row.id)}
										className="zc-size-4"
									/>
									{kind === "products" ? <ProductRow row={row} /> : <CategoryRow row={row} />}
								</label>
							</li>
						);
					})}
				</ul>
			)}

			<div className="zc-flex zc-items-center zc-justify-between zc-border-t zc-border-zinc-200 zc-pt-3">
				<span className="zc-text-xs zc-text-zinc-500">{selectedIds.length} selected</span>
				<Button size="sm" onClick={onClose}>Done</Button>
			</div>
		</div>
	);
}

function ProductRow({ row }) {
	return (
		<>
			{row.thumbnail ? (
				<img src={row.thumbnail} alt="" className="zc-size-9 zc-rounded zc-object-cover" />
			) : (
				<div className="zc-size-9 zc-rounded zc-bg-zinc-100" />
			)}
			<div className="zc-min-w-0 zc-flex-1">
				<div className="zc-truncate zc-font-medium zc-text-zinc-900">{row.name}</div>
				<div className="zc-truncate zc-text-xs zc-text-zinc-500">
					{row.sku ? <code className="zc-mr-1.5">{row.sku}</code> : null}
					{row.price !== null ? <>${row.price}</> : null}
					{row.status && row.status !== "publish" ? (
						<span className="zc-ml-2 zc-rounded zc-bg-amber-100 zc-px-1 zc-text-[10px] zc-uppercase zc-tracking-wide zc-text-amber-800">
							{row.status}
						</span>
					) : null}
				</div>
			</div>
		</>
	);
}

function CategoryRow({ row }) {
	return (
		<>
			<div className="zc-size-9 zc-rounded zc-bg-zinc-100" />
			<div className="zc-min-w-0 zc-flex-1">
				<div className="zc-truncate zc-font-medium zc-text-zinc-900">{row.name}</div>
				<div className="zc-text-xs zc-text-zinc-500">{row.count} products</div>
			</div>
		</>
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
