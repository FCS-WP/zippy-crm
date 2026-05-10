/**
 * Active-filter chip row. Renders one chip per filter that has a non-empty
 * value, with an "✕" to clear that single filter, plus a "Clear all" link
 * when more than one chip is shown.
 *
 * Caller is responsible for providing the filter list and a per-key clear
 * callback. We don't try to be cute and infer it — the panel knows which
 * setter resets which filter.
 *
 * filters: [{ key, label, value, valueLabel?, onClear }]
 *   - chip is hidden when `value` is empty/null/undefined
 *   - `valueLabel` is what we display (e.g. "Gold" instead of "gold");
 *     falls back to value
 *   - `label` is the filter name ("Level", "Status", "Type")
 */
export function FilterChips({ filters, onClearAll }) {
	const active = filters.filter((f) =>
		f.value !== undefined && f.value !== null && f.value !== "" &&
		!(Array.isArray(f.value) && f.value.length === 0),
	);
	if (active.length === 0) return null;

	return (
		<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-1.5">
			<span className="zc-text-xs zc-uppercase zc-tracking-wide zc-text-zinc-500">Filters:</span>
			{active.map((f) => (
				<Chip key={f.key} filter={f} />
			))}
			{(active.length > 1 || (active.length === 1 && onClearAll)) ? (
				<button
					type="button"
					onClick={onClearAll}
					className="zc-text-xs zc-text-zinc-500 hover:zc-text-zinc-900 hover:zc-underline zc-underline-offset-2"
				>
					Clear all
				</button>
			) : null}
		</div>
	);
}

function Chip({ filter }) {
	return (
		<span className="zc-inline-flex zc-items-center zc-gap-1.5 zc-rounded-full zc-border zc-border-zinc-200 zc-bg-white zc-py-0.5 zc-pl-2.5 zc-pr-1 zc-text-xs zc-font-medium zc-text-zinc-700">
			<span className="zc-text-zinc-500">{filter.label}:</span>
			<span>{filter.valueLabel ?? String(filter.value)}</span>
			<button
				type="button"
				onClick={filter.onClear}
				aria-label={`Clear ${filter.label} filter`}
				className="zc-rounded-full zc-p-0.5 zc-text-zinc-400 hover:zc-bg-zinc-100 hover:zc-text-zinc-900"
			>
				<svg viewBox="0 0 24 24" className="zc-size-3" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
					<path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
				</svg>
			</button>
		</span>
	);
}
