import { Input } from "@/js/shared/ui/input.jsx";
import { useTiers } from "@/js/shared/hooks/useTiers.js";

const STATUSES = [
	{ key: "",          label: "All statuses" },
	{ key: "active",    label: "Active"       },
	{ key: "suspended", label: "Suspended"    },
	{ key: "expired",   label: "Expired"      },
];

/**
 * Filter row: search left, two selects + reset right. Selects scale better
 * than pill rows when more filters are added (e.g. date range, role).
 */
export function FilterBar({ level, onLevel, status, onStatus, search, onSearch }) {
	const { tiers } = useTiers();
	const hasFilters = Boolean(level || status || search);

	// "All levels" + one option per tier (admin-only included so admins can
	// filter to e.g. VIP).
	const levelOptions = [
		{ key: "", label: "All levels" },
		...tiers.map((t) => ({ key: t.slug, label: t.label })),
	];

	const reset = () => { onLevel(""); onStatus(""); onSearch(""); };

	return (
		<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-2">
			<div className="zc-relative zc-flex-1 zc-min-w-[14rem] zc-max-w-md">
				<SearchIcon />
				<Input
					placeholder="Search login, email or name…"
					value={search}
					onChange={(e) => onSearch(e.target.value)}
					className="zc-pl-9"
				/>
			</div>

			<Select value={level}  onChange={onLevel}  options={levelOptions} ariaLabel="Filter by level" />
			<Select value={status} onChange={onStatus} options={STATUSES}     ariaLabel="Filter by status" />

			{hasFilters ? (
				<button
					type="button"
					onClick={reset}
					className="zc-text-sm zc-text-zinc-500 zc-underline-offset-2 hover:zc-text-zinc-900 hover:zc-underline"
				>
					Clear
				</button>
			) : null}
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
				className="zc-h-10 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-pl-3 zc-pr-9 zc-text-sm zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
			>
				{options.map((o) => (
					<option key={o.key || "all"} value={o.key}>{o.label}</option>
				))}
			</select>
			<ChevronIcon />
		</div>
	);
}

function SearchIcon() {
	return (
		<svg
			viewBox="0 0 24 24"
			className="zc-pointer-events-none zc-absolute zc-left-3 zc-top-1/2 zc-size-4 -zc-translate-y-1/2 zc-text-zinc-400"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			aria-hidden
		>
			<circle cx="11" cy="11" r="7" />
			<path strokeLinecap="round" d="M21 21l-4.3-4.3" />
		</svg>
	);
}

function ChevronIcon() {
	return (
		<svg
			viewBox="0 0 24 24"
			className="zc-pointer-events-none zc-absolute zc-right-3 zc-top-1/2 zc-size-4 -zc-translate-y-1/2 zc-text-zinc-500"
			fill="none"
			stroke="currentColor"
			strokeWidth="2"
			aria-hidden
		>
			<path strokeLinecap="round" strokeLinejoin="round" d="M6 9l6 6 6-6" />
		</svg>
	);
}
