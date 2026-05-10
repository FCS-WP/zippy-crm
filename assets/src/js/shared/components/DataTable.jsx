import { Skeleton } from "@/js/shared/ui/skeleton.jsx";
import { cn } from "@/js/shared/cn.js";

/**
 * Generic admin data table. One canonical implementation so every panel
 * (Members, Vouchers, Tiers, Points-Ledger) gets the same look + the same
 * loading / empty / error states.
 *
 * Props:
 *   columns       — [{ key, label, align?, width?, sortable?, render(row) }]
 *   rows          — array of records
 *   rowKey        — fn(row) => string (defaults to row.id)
 *   sort          — { key, dir: 'asc'|'desc' } | null
 *   onSort        — (key) => void   (click a sortable header — toggles dir)
 *   loading       — boolean (renders skeleton rows)
 *   error         — string | null
 *   empty         — node (rendered when !loading && !error && rows.length===0)
 *   density       — 'compact' | 'cozy' (defaults 'cozy')
 *   onRowClick    — fn(row) => void   (whole-row click; cursor-pointer + hover)
 *   stickyHeader  — boolean (default true)
 *
 * Column.render gets the row and should return the cell node. Skipping render
 * uses `row[key]` verbatim. Cell padding is owned by the table — render fns
 * shouldn't add their own.
 */
export function DataTable({
	columns,
	rows,
	rowKey = (r) => r.id,
	sort = null,
	onSort,
	loading = false,
	error = null,
	empty = null,
	density = "cozy",
	onRowClick,
	stickyHeader = true,
}) {
	const cellPad = density === "compact" ? "zc-px-3 zc-py-1.5" : "zc-px-4 zc-py-2.5";
	const fontSize = density === "compact" ? "zc-text-xs" : "zc-text-sm";

	if (error) {
		return (
			<div className="zc-rounded-lg zc-border zc-border-rose-200 zc-bg-rose-50 zc-p-4 zc-text-sm zc-text-rose-800">
				{error}
			</div>
		);
	}

	return (
		<div className="zc-overflow-hidden zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white">
			<div className="zc-overflow-x-auto">
				<table className={cn("zc-w-full zc-text-left", fontSize)}>
					<thead
						className={cn(
							"zc-border-b zc-border-zinc-200 zc-bg-zinc-50 zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500",
							stickyHeader && "zc-sticky zc-top-0 zc-z-10",
						)}
					>
						<tr>
							{columns.map((col) => (
								<HeaderCell
									key={col.key}
									col={col}
									sort={sort}
									onSort={onSort}
									cellPad={cellPad}
								/>
							))}
						</tr>
					</thead>
					<tbody>
						{loading ? (
							<SkeletonRows columns={columns} cellPad={cellPad} />
						) : rows.length === 0 ? (
							<tr>
								<td colSpan={columns.length} className="zc-p-8 zc-text-center zc-text-sm zc-text-zinc-500">
									{empty ?? "No results."}
								</td>
							</tr>
						) : (
							rows.map((row) => (
								<BodyRow
									key={rowKey(row)}
									row={row}
									columns={columns}
									cellPad={cellPad}
									onRowClick={onRowClick}
								/>
							))
						)}
					</tbody>
				</table>
			</div>
		</div>
	);
}

function HeaderCell({ col, sort, onSort, cellPad }) {
	const align = col.align === "right" ? "zc-text-right" : col.align === "center" ? "zc-text-center" : "";
	const isActive = sort && sort.key === col.key;
	const dir      = isActive ? sort.dir : null;

	if (!col.sortable || !onSort) {
		return (
			<th
				scope="col"
				style={col.width ? { width: col.width } : undefined}
				className={cn("zc-font-semibold", cellPad, align)}
			>
				{col.label}
			</th>
		);
	}

	return (
		<th
			scope="col"
			aria-sort={dir === "asc" ? "ascending" : dir === "desc" ? "descending" : "none"}
			style={col.width ? { width: col.width } : undefined}
			className={cn("zc-font-semibold", cellPad, align)}
		>
			<button
				type="button"
				onClick={() => onSort(col.key)}
				className={cn(
					"zc-inline-flex zc-items-center zc-gap-1 zc-uppercase zc-tracking-wide hover:zc-text-zinc-900",
					isActive && "zc-text-zinc-900",
					col.align === "right" && "zc-flex-row-reverse",
				)}
			>
				<span>{col.label}</span>
				<SortIcon dir={dir} />
			</button>
		</th>
	);
}

function SortIcon({ dir }) {
	if (dir === "asc") {
		return (
			<svg viewBox="0 0 24 24" className="zc-size-3" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
				<path strokeLinecap="round" strokeLinejoin="round" d="M6 14l6-6 6 6" />
			</svg>
		);
	}
	if (dir === "desc") {
		return (
			<svg viewBox="0 0 24 24" className="zc-size-3" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
				<path strokeLinecap="round" strokeLinejoin="round" d="M6 10l6 6 6-6" />
			</svg>
		);
	}
	// inactive — both arrows muted
	return (
		<svg viewBox="0 0 24 24" className="zc-size-3 zc-text-zinc-400" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
			<path strokeLinecap="round" strokeLinejoin="round" d="M8 9l4-4 4 4M8 15l4 4 4-4" />
		</svg>
	);
}

function BodyRow({ row, columns, cellPad, onRowClick }) {
	const clickable = typeof onRowClick === "function";
	return (
		<tr
			onClick={clickable ? () => onRowClick(row) : undefined}
			className={cn(
				"zc-border-b zc-border-zinc-100 last:zc-border-b-0",
				clickable && "zc-cursor-pointer hover:zc-bg-zinc-50",
				!clickable && "hover:zc-bg-zinc-50/60",
			)}
		>
			{columns.map((col) => {
				const align = col.align === "right" ? "zc-text-right" : col.align === "center" ? "zc-text-center" : "";
				const value = col.render ? col.render(row) : row[col.key];
				return (
					<td
						key={col.key}
						className={cn("zc-align-middle zc-text-zinc-700", cellPad, align, col.cellClassName)}
						onClick={col.stopPropagation ? (e) => e.stopPropagation() : undefined}
					>
						{value}
					</td>
				);
			})}
		</tr>
	);
}

function SkeletonRows({ columns, cellPad }) {
	return Array.from({ length: 5 }).map((_, i) => (
		<tr key={i} className="zc-border-b zc-border-zinc-100 last:zc-border-b-0">
			{columns.map((col) => (
				<td key={col.key} className={cn("zc-align-middle", cellPad)}>
					<Skeleton className="zc-h-3.5 zc-w-3/4" />
				</td>
			))}
		</tr>
	));
}
