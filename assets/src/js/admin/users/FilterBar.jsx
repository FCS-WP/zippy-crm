import { SearchInput } from "@/js/shared/components/SearchInput.jsx";

const HAS_OPTIONS = [
	{ key: "",    label: "All users"          },
	{ key: "yes", label: "Members only"       },
	{ key: "no",  label: "Non-members only"   },
];

/**
 * Filter row for the Users panel. Search is debounced via SearchInput;
 * has_membership is a 3-way pill toggle since the value space is small +
 * mutually exclusive.
 */
export function FilterBar({ search, onSearch, has, onHas }) {
	return (
		<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-2">
			<SearchInput
				className="zc-flex-1 zc-min-w-[14rem] zc-max-w-md"
				placeholder="Search login, email or name…"
				value={search}
				onChange={onSearch}
			/>
			<div className="zc-inline-flex zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-1">
				{HAS_OPTIONS.map((o) => {
					const active = has === o.key;
					return (
						<button
							key={o.key || "all"}
							type="button"
							onClick={() => onHas(o.key)}
							className={[
								"zc-rounded-md zc-px-3 zc-py-1.5 zc-text-sm zc-font-medium zc-transition-colors",
								active
									? "zc-bg-white zc-text-zinc-900 zc-shadow-sm"
									: "zc-text-zinc-600 hover:zc-text-zinc-900",
							].join(" ")}
						>
							{o.label}
						</button>
					);
				})}
			</div>
		</div>
	);
}
