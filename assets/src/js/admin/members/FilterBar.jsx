import { SearchInput } from "@/js/shared/components/SearchInput.jsx";
import { useTiers } from "@/js/shared/hooks/useTiers.js";

const STATUSES = [
	{ key: "",          label: "All statuses" },
	{ key: "active",    label: "Active"       },
	{ key: "suspended", label: "Suspended"    },
	{ key: "expired",   label: "Expired"      },
];

/**
 * Members filter row. Search is debounced by SearchInput; selects are
 * styled selects with a custom chevron (avoids WP-admin's `forms.css`
 * dropdown caret showing through).
 *
 * The "Clear all" affordance now lives on FilterChips above the table —
 * removed from here to avoid duplication.
 */
export function FilterBar({ level, onLevel, status, onStatus, search, onSearch }) {
	const { tiers } = useTiers();

	const levelOptions = [
		{ key: "", label: "All levels" },
		...tiers.map((t) => ({ key: t.slug, label: t.label })),
	];

	return (
		<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-2">
			<SearchInput
				className="zc-flex-1 zc-min-w-[14rem] zc-max-w-md"
				placeholder="Search login, email or name…"
				value={search}
				onChange={onSearch}
			/>
			<Select value={level}  onChange={onLevel}  options={levelOptions} ariaLabel="Filter by level" />
			<Select value={status} onChange={onStatus} options={STATUSES}     ariaLabel="Filter by status" />
		</div>
	);
}

function Select({ value, onChange, options, ariaLabel }) {
	return (
		<div className="zc-relative">
			<select
				aria-label={ariaLabel}
				value={value}
				onChange={(e) => onChange(e.target.value)}
				style={{ WebkitAppearance: "none", MozAppearance: "none", appearance: "none", backgroundImage: "none" }}
				className="zc-h-9 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-pl-3 zc-pr-9 zc-text-sm zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
			>
				{options.map((o) => (
					<option key={o.key || "all"} value={o.key}>{o.label}</option>
				))}
			</select>
			<svg
				viewBox="0 0 24 24"
				className="zc-pointer-events-none zc-absolute zc-right-3 zc-top-1/2 zc-size-4 -zc-translate-y-1/2 zc-text-zinc-500"
				fill="none" stroke="currentColor" strokeWidth="2" aria-hidden
			>
				<path strokeLinecap="round" strokeLinejoin="round" d="M6 9l6 6 6-6" />
			</svg>
		</div>
	);
}
