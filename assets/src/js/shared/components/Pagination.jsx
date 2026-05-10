import { Button } from "@/js/shared/ui/button.jsx";
import { number } from "@/js/shared/utils/format.js";

/**
 * Page-aware pagination control with size picker + range indicator.
 *
 * Caller owns page + perPage state and the request shape; this component
 * is presentation only.
 *
 * Props:
 *   page          — current 1-based page index
 *   perPage       — current page size
 *   total         — total row count (across all pages)
 *   onPage        — fn(newPage)
 *   onPerPage     — fn(newPerPage)   omit to hide the size picker
 *   sizes         — [10, 25, 50, 100]
 *
 * Rendered as: "Showing 21–40 of 134" on the left, Prev / page X of Y /
 * Next on the right, page-size dropdown trailing.
 */
const DEFAULT_SIZES = [ 10, 25, 50, 100 ];

export function Pagination({ page, perPage, total, onPage, onPerPage, sizes = DEFAULT_SIZES }) {
	if (total <= 0) {
		// Still render the size picker so the admin can pre-set it for next time.
		if (!onPerPage) return null;
		return (
			<div className="zc-flex zc-justify-end zc-text-sm zc-text-zinc-500">
				<SizePicker perPage={perPage} sizes={sizes} onPerPage={onPerPage} />
			</div>
		);
	}

	const totalPages = Math.max(1, Math.ceil(total / perPage));
	const safePage   = Math.min(Math.max(1, page), totalPages);
	const start = (safePage - 1) * perPage + 1;
	const end   = Math.min(safePage * perPage, total);

	return (
		<div className="zc-flex zc-flex-wrap zc-items-center zc-justify-between zc-gap-3 zc-text-sm zc-text-zinc-600">
			<span>
				Showing <strong className="zc-text-zinc-900">{number(start)}–{number(end)}</strong> of{" "}
				<strong className="zc-text-zinc-900">{number(total)}</strong>
			</span>

			<div className="zc-flex zc-items-center zc-gap-3">
				{onPerPage ? (
					<SizePicker perPage={perPage} sizes={sizes} onPerPage={onPerPage} />
				) : null}

				<div className="zc-flex zc-items-center zc-gap-1">
					<IconButton
						label="First page"
						disabled={safePage <= 1}
						onClick={() => onPage(1)}
					>
						<svg viewBox="0 0 24 24" className="zc-size-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
							<path strokeLinecap="round" strokeLinejoin="round" d="M19 5L11 12L19 19M5 5V19" />
						</svg>
					</IconButton>
					<IconButton
						label="Previous page"
						disabled={safePage <= 1}
						onClick={() => onPage(safePage - 1)}
					>
						<svg viewBox="0 0 24 24" className="zc-size-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
							<path strokeLinecap="round" strokeLinejoin="round" d="M15 18l-6-6 6-6" />
						</svg>
					</IconButton>

					<span className="zc-px-2 zc-text-xs zc-tabular-nums">
						Page {safePage} / {totalPages}
					</span>

					<IconButton
						label="Next page"
						disabled={safePage >= totalPages}
						onClick={() => onPage(safePage + 1)}
					>
						<svg viewBox="0 0 24 24" className="zc-size-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
							<path strokeLinecap="round" strokeLinejoin="round" d="M9 18l6-6-6-6" />
						</svg>
					</IconButton>
					<IconButton
						label="Last page"
						disabled={safePage >= totalPages}
						onClick={() => onPage(totalPages)}
					>
						<svg viewBox="0 0 24 24" className="zc-size-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
							<path strokeLinecap="round" strokeLinejoin="round" d="M5 5l8 7-8 7M19 5v14" />
						</svg>
					</IconButton>
				</div>
			</div>
		</div>
	);
}

function SizePicker({ perPage, sizes, onPerPage }) {
	return (
		<label className="zc-flex zc-items-center zc-gap-1.5 zc-text-xs zc-text-zinc-500">
			<span>Rows per page</span>
			<div className="zc-relative">
				<select
					value={perPage}
					onChange={(e) => onPerPage(Number(e.target.value))}
					style={{ WebkitAppearance: "none", MozAppearance: "none", appearance: "none", backgroundImage: "none" }}
					className="zc-h-7 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-pl-2 zc-pr-6 zc-text-xs zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-1 focus:zc-ring-zinc-200"
				>
					{sizes.map((s) => (
						<option key={s} value={s}>{s}</option>
					))}
				</select>
				<svg
					viewBox="0 0 24 24"
					className="zc-pointer-events-none zc-absolute zc-right-2 zc-top-1/2 zc-size-3 -zc-translate-y-1/2 zc-text-zinc-500"
					fill="none" stroke="currentColor" strokeWidth="2" aria-hidden
				>
					<path strokeLinecap="round" strokeLinejoin="round" d="M6 9l6 6 6-6" />
				</svg>
			</div>
		</label>
	);
}

function IconButton({ label, disabled, onClick, children }) {
	return (
		<button
			type="button"
			aria-label={label}
			disabled={disabled}
			onClick={onClick}
			className="zc-inline-flex zc-h-7 zc-w-7 zc-items-center zc-justify-center zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-text-zinc-600 hover:zc-bg-zinc-50 hover:zc-text-zinc-900 disabled:zc-cursor-not-allowed disabled:zc-bg-zinc-50 disabled:zc-text-zinc-300"
		>
			{children}
		</button>
	);
}
