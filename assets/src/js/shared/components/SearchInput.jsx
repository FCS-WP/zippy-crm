import { useEffect, useRef, useState } from "react";

/**
 * Debounced search box. Caller supplies the committed `value` and an
 * `onChange` that fires after `debounceMs` of idle typing — perfect for
 * REST list filters (no spam).
 *
 * Optional `loading` prop renders a spinner overlay so the admin sees the
 * search is in flight even when the value hasn't changed yet.
 *
 * Local state holds the in-progress text; we sync back from `value` if the
 * caller resets it (e.g. Clear button on a filter chip).
 */
export function SearchInput({
	value,
	onChange,
	placeholder = "Search…",
	debounceMs = 300,
	loading = false,
	className = "",
}) {
	const [draft, setDraft] = useState(value ?? "");
	const lastCommitted = useRef(value ?? "");

	// Re-sync when the parent value changes (e.g. Clear All).
	useEffect(() => {
		if (value !== lastCommitted.current) {
			setDraft(value ?? "");
			lastCommitted.current = value ?? "";
		}
	}, [value]);

	useEffect(() => {
		if (draft === lastCommitted.current) return;
		const t = setTimeout(() => {
			lastCommitted.current = draft;
			onChange(draft);
		}, debounceMs);
		return () => clearTimeout(t);
	}, [draft, debounceMs, onChange]);

	const clear = () => {
		setDraft("");
		lastCommitted.current = "";
		onChange("");
	};

	return (
		<div className={`zc-relative ${className}`}>
			<svg
				viewBox="0 0 24 24"
				className="zc-pointer-events-none zc-absolute zc-left-3 zc-top-1/2 zc-size-4 -zc-translate-y-1/2 zc-text-zinc-400"
				fill="none" stroke="currentColor" strokeWidth="2" aria-hidden
			>
				<circle cx="11" cy="11" r="7" />
				<path strokeLinecap="round" d="M21 21l-4.3-4.3" />
			</svg>

			<input
				type="text"
				value={draft}
				onChange={(e) => setDraft(e.target.value)}
				placeholder={placeholder}
				// `paddingLeft/Right` inline because WP admin's `forms.css`
				// targets `input[type="text"]` with attribute-selector
				// specificity that beats Tailwind's class-based padding.
				style={{ paddingLeft: "2.25rem", paddingRight: "2.25rem" }}
				className="zc-h-9 zc-w-full zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-text-sm zc-text-zinc-900 zc-outline-none placeholder:zc-text-zinc-400 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
			/>

			{loading ? (
				<span className="zc-pointer-events-none zc-absolute zc-right-3 zc-top-1/2 -zc-translate-y-1/2">
					<svg viewBox="0 0 24 24" className="zc-size-4 zc-animate-spin zc-text-zinc-400" fill="none" aria-hidden>
						<circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" opacity="0.25" />
						<path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
					</svg>
				</span>
			) : draft ? (
				<button
					type="button"
					onClick={clear}
					aria-label="Clear search"
					className="zc-absolute zc-right-2 zc-top-1/2 -zc-translate-y-1/2 zc-rounded zc-p-1 zc-text-zinc-400 hover:zc-bg-zinc-100 hover:zc-text-zinc-700"
				>
					<svg viewBox="0 0 24 24" className="zc-size-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
						<path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
					</svg>
				</button>
			) : null}
		</div>
	);
}
