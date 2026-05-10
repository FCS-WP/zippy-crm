import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

/**
 * Styled time input — trigger button shows current value, opens a portaled
 * popover with two scrollable columns (hour + minute). Emits "HH:MM" 24h
 * strings, same shape as the native `<input type="time">` value, so it
 * drops in as a replacement.
 *
 * Why not native: it's browser-themed and clashes with the rest of the
 * admin (Reports calendar, voucher date pickers all use custom popovers).
 * WP admin's `forms.css` also restyles native time inputs in ways we can't
 * fully override without specificity wars.
 *
 * Minute column step is configurable; default 15 (matches WC's coupon
 * usage patterns where exact minutes rarely matter).
 */
export function TimeField({ value, onChange, step = 15, disabled = false }) {
	const [open, setOpen] = useState(false);
	const [pos, setPos]   = useState({ top: 0, left: 0, width: 0 });
	const triggerRef = useRef(null);
	const popoverRef = useRef(null);

	const display = formatLabel(value);

	useLayoutEffect(() => {
		if (!open || !triggerRef.current) return;
		const r = triggerRef.current.getBoundingClientRect();
		// Popover at least as wide as the trigger so the columns line up.
		const w    = Math.max(r.width, 180);
		const left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left));
		const top  = r.bottom + 4;
		setPos({ top, left, width: w });
	}, [open]);

	useEffect(() => {
		if (!open) return undefined;
		const close = () => setOpen(false);
		const onKey = (e) => { if (e.key === "Escape") close(); };
		const onClick = (e) => {
			const inTrigger = triggerRef.current?.contains(e.target);
			const inPopover = popoverRef.current?.contains(e.target);
			if (!inTrigger && !inPopover) close();
		};
		window.addEventListener("keydown", onKey);
		window.addEventListener("mousedown", onClick);
		window.addEventListener("resize", close);
		return () => {
			window.removeEventListener("keydown", onKey);
			window.removeEventListener("mousedown", onClick);
			window.removeEventListener("resize", close);
		};
	}, [open]);

	const [h, m] = parseHHMM(value);

	const setHour = (newH) => {
		onChange(formatHHMM(newH, m));
	};
	const setMinute = (newM) => {
		onChange(formatHHMM(h, newM));
		setOpen(false); // commit-and-close once both are picked
	};

	return (
		<>
			<button
				ref={triggerRef}
				type="button"
				disabled={disabled}
				onClick={() => setOpen((v) => !v)}
				className={[
					"zc-flex zc-h-9 zc-w-full zc-items-center zc-justify-between zc-gap-2 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-2.5 zc-text-sm zc-text-zinc-900 zc-transition-colors hover:zc-bg-zinc-50",
					disabled && "zc-cursor-not-allowed zc-opacity-50",
				].filter(Boolean).join(" ")}
			>
				<span className="zc-flex zc-items-center zc-gap-2">
					<svg viewBox="0 0 24 24" className="zc-size-4 zc-text-zinc-500" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
						<circle cx="12" cy="12" r="9" />
						<path strokeLinecap="round" d="M12 7v5l3 2" />
					</svg>
					<span className={value ? "" : "zc-text-zinc-400"}>{display}</span>
				</span>
			</button>

			{open
				? createPortal(
					<div
						ref={popoverRef}
						style={{ position: "fixed", top: pos.top, left: pos.left, width: pos.width }}
						className="zc-z-[100001] zc-overflow-hidden zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-shadow-xl"
					>
						<div className="zc-grid zc-grid-cols-2 zc-divide-x zc-divide-zinc-100">
							<Column
								label="Hr"
								items={HOURS}
								active={h}
								format={(n) => String(n).padStart(2, "0")}
								onSelect={setHour}
							/>
							<Column
								label="Min"
								items={minuteOptions(step)}
								active={nearestStep(m, step)}
								format={(n) => String(n).padStart(2, "0")}
								onSelect={setMinute}
							/>
						</div>
					</div>,
					document.body,
				)
				: null}
		</>
	);
}

function Column({ label, items, active, format, onSelect }) {
	const listRef = useRef(null);

	// Scroll the active row into view when the popover opens.
	useEffect(() => {
		if (!listRef.current) return;
		const node = listRef.current.querySelector('[data-active="true"]');
		if (node) node.scrollIntoView({ block: "center" });
	}, []);

	return (
		<div className="zc-flex zc-flex-col">
			<div className="zc-border-b zc-border-zinc-100 zc-px-2 zc-py-1 zc-text-center zc-text-[10px] zc-font-medium zc-uppercase zc-tracking-wide zc-text-zinc-500">
				{label}
			</div>
			<ul ref={listRef} className="zc-h-44 zc-overflow-y-auto zc-py-1">
				{items.map((n) => {
					const selected = n === active;
					return (
						<li key={n}>
							<button
								type="button"
								data-active={selected ? "true" : "false"}
								onClick={() => onSelect(n)}
								className={[
									"zc-block zc-w-full zc-px-3 zc-py-1.5 zc-text-center zc-text-sm zc-tabular-nums zc-transition-colors",
									selected
										? "zc-bg-zinc-900 zc-text-white"
										: "zc-text-zinc-700 hover:zc-bg-zinc-100",
								].join(" ")}
							>
								{format(n)}
							</button>
						</li>
					);
				})}
			</ul>
		</div>
	);
}

const HOURS = Array.from({ length: 24 }, (_, i) => i);

function minuteOptions(step) {
	const out = [];
	for (let m = 0; m < 60; m += step) out.push(m);
	return out;
}

function nearestStep(m, step) {
	if (!Number.isFinite(m)) return 0;
	return Math.round(m / step) * step % 60;
}

function parseHHMM(value) {
	if (!value || typeof value !== "string") return [0, 0];
	const [h, m] = value.split(":").map((n) => parseInt(n, 10) || 0);
	return [Math.max(0, Math.min(23, h)), Math.max(0, Math.min(59, m))];
}

function formatHHMM(h, m) {
	return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
}

function formatLabel(value) {
	const [h, m] = parseHHMM(value);
	if (!value) return "Pick time";
	return formatHHMM(h, m);
}
