import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { cn } from "@/js/shared/cn.js";

/**
 * Overflow menu — trigger button + popover. Closes on Escape, click outside,
 * scroll, resize, or item activation.
 *
 * The popover is rendered into document.body via a portal and positioned with
 * fixed coords, so it escapes any `overflow:hidden` / `overflow:auto`
 * ancestor (e.g. table cells inside a Card with overflow-hidden). Without
 * this, table-row menus get clipped by the table's scroll container.
 *
 * Items: [{ label, onSelect, disabled?, variant?: "default"|"danger" }]
 */
export function OverflowMenu({ items, label = "More actions" }) {
	const [open, setOpen] = useState(false);
	const [pos, setPos]   = useState({ top: 0, left: 0 });
	const triggerRef = useRef(null);
	const menuRef    = useRef(null);

	useLayoutEffect(() => {
		if (!open || !triggerRef.current) return;
		const r = triggerRef.current.getBoundingClientRect();
		const menuW = 176; // ≈ min-w-[10rem] + paddings; small fixed estimate is fine
		const left  = Math.max(8, r.right - menuW);   // right-align under the trigger
		const top   = r.bottom + 4;
		setPos({ top, left });
	}, [open]);

	useEffect(() => {
		if (!open) return undefined;
		const close = () => setOpen(false);
		const onKey = (e) => { if (e.key === "Escape") close(); };
		const onClick = (e) => {
			const inTrigger = triggerRef.current && triggerRef.current.contains(e.target);
			const inMenu    = menuRef.current    && menuRef.current.contains(e.target);
			if (!inTrigger && !inMenu) close();
		};
		window.addEventListener("keydown",   onKey);
		window.addEventListener("mousedown", onClick);
		// A scroll inside the table or the page invalidates our fixed coords —
		// just close rather than reposition. Same for resize.
		window.addEventListener("scroll",  close, true);
		window.addEventListener("resize",  close);
		return () => {
			window.removeEventListener("keydown",   onKey);
			window.removeEventListener("mousedown", onClick);
			window.removeEventListener("scroll",  close, true);
			window.removeEventListener("resize",  close);
		};
	}, [open]);

	const visible = items.filter(Boolean);
	if (visible.length === 0) return null;

	return (
		<>
			<button
				ref={triggerRef}
				type="button"
				aria-label={label}
				aria-haspopup="menu"
				aria-expanded={open}
				onClick={() => setOpen((v) => !v)}
				className="zc-inline-flex zc-h-8 zc-w-8 zc-items-center zc-justify-center zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-text-zinc-600 hover:zc-bg-zinc-50 hover:zc-text-zinc-900 focus-visible:zc-ring-2 focus-visible:zc-ring-zinc-400"
			>
				<svg viewBox="0 0 24 24" className="zc-size-4" fill="currentColor" aria-hidden>
					<circle cx="5"  cy="12" r="1.75" />
					<circle cx="12" cy="12" r="1.75" />
					<circle cx="19" cy="12" r="1.75" />
				</svg>
			</button>

			{open
				? createPortal(
					<div
						ref={menuRef}
						role="menu"
						style={{ position: "fixed", top: pos.top, left: pos.left }}
						className="zc-z-[100001] zc-min-w-[10rem] zc-overflow-hidden zc-rounded-md zc-border zc-border-zinc-200 zc-bg-white zc-py-1 zc-shadow-lg"
					>
						{visible.map((item, i) => (
							<button
								key={i}
								type="button"
								role="menuitem"
								disabled={item.disabled}
								onClick={() => { setOpen(false); item.onSelect?.(); }}
								className={cn(
									"zc-flex zc-w-full zc-items-center zc-gap-2 zc-px-3 zc-py-1.5 zc-text-left zc-text-sm",
									item.disabled
										? "zc-cursor-not-allowed zc-text-zinc-400"
										: item.variant === "danger"
											? "zc-text-rose-700 hover:zc-bg-rose-50"
											: "zc-text-zinc-700 hover:zc-bg-zinc-100",
								)}
							>
								{item.label}
							</button>
						))}
					</div>,
					document.body,
				)
				: null}
		</>
	);
}
