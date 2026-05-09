import { useEffect } from "react";

/**
 * Bare-bones slide-in drawer. No portal — admin pages don't have z-index
 * conflicts to worry about. Closes on Escape and on backdrop click.
 *
 * `open` toggles visibility; the component stays mounted between renders so
 * forms can keep their internal state if you reopen the same drawer.
 */
export function Drawer({ open, onClose, title, children, width = "zc-max-w-xl" }) {
	useEffect(() => {
		if (!open) return undefined;
		const onKey = (e) => { if (e.key === "Escape") onClose(); };
		window.addEventListener("keydown", onKey);
		return () => window.removeEventListener("keydown", onKey);
	}, [open, onClose]);

	if (!open) return null;

	return (
		<div className="zc-fixed zc-inset-0 zc-z-[100000] zc-flex zc-justify-end">
			<div
				className="zc-absolute zc-inset-0 zc-bg-zinc-900/30 zc-backdrop-blur-sm"
				onClick={onClose}
				aria-hidden
			/>
			<aside
				className={`zc-relative zc-flex zc-h-full zc-w-full zc-flex-col zc-bg-white zc-shadow-xl ${width}`}
				role="dialog"
				aria-modal="true"
			>
				<header className="zc-flex zc-items-center zc-justify-between zc-border-b zc-border-zinc-200 zc-px-6 zc-py-4">
					<h2 className="zc-text-lg zc-font-semibold zc-text-zinc-900">{title}</h2>
					<button
						type="button"
						onClick={onClose}
						className="zc-rounded-md zc-p-1 zc-text-zinc-500 hover:zc-bg-zinc-100 hover:zc-text-zinc-900"
						aria-label="Close"
					>
						<svg viewBox="0 0 24 24" className="zc-size-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
							<path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6L6 18" />
						</svg>
					</button>
				</header>
				<div className="zc-flex-1 zc-overflow-y-auto zc-px-6 zc-py-5">
					{children}
				</div>
			</aside>
		</div>
	);
}
