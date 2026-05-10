import { SearchInput } from "@/js/shared/components/SearchInput.jsx";

const STATUSES = [
	{ key: "",        label: "All"     },
	{ key: "draft",   label: "Draft"   },
	{ key: "active",  label: "Active"  },
	{ key: "paused",  label: "Paused"  },
	{ key: "expired", label: "Expired" },
];

/**
 * Vouchers filter row. Status stays as pill-tabs (small enum, looks tidy);
 * search is the shared debounced SearchInput.
 */
export function FilterBar({ status, onStatus, search, onSearch }) {
	return (
		<div className="zc-flex zc-flex-col zc-gap-3 md:zc-flex-row md:zc-items-center md:zc-justify-between">
			<div className="zc-inline-flex zc-flex-wrap zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-1">
				{STATUSES.map((s) => {
					const active = status === s.key;
					return (
						<button
							key={s.key || "all"}
							type="button"
							onClick={() => onStatus(s.key)}
							className={[
								"zc-rounded-md zc-px-3 zc-py-1.5 zc-text-sm zc-font-medium zc-transition-colors",
								active
									? "zc-bg-white zc-text-zinc-900 zc-shadow-sm"
									: "zc-text-zinc-600 hover:zc-text-zinc-900",
							].join(" ")}
						>
							{s.label}
						</button>
					);
				})}
			</div>
			<SearchInput
				className="zc-w-full md:zc-w-72"
				placeholder="Search code or title…"
				value={search}
				onChange={onSearch}
			/>
		</div>
	);
}
