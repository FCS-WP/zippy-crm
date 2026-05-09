import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { DayPicker } from "react-day-picker";
import "react-day-picker/style.css";
import "./day-picker-overrides.css";

const PRESETS = [
	{ key: "7",   label: "7 days"  },
	{ key: "30",  label: "30 days" },
	{ key: "90",  label: "90 days" },
];

/**
 * Date-range picker — three preset pills + a trigger button that opens a
 * calendar popover (react-day-picker, range mode). Emits `{ from, to }` as
 * YYYY-MM-DD strings (UTC) once the user selects both ends.
 *
 * The popover is portaled to document.body and positioned with fixed coords
 * (same trick as OverflowMenu) so it can't be clipped by any ancestor's
 * overflow:hidden / overflow:auto.
 */
export function DateRangePicker({ value, onChange }) {
	const [mode, setMode] = useState(() => detectMode(value));

	const applyPreset = (days) => {
		setMode(days);
		onChange(presetRange(parseInt(days, 10)));
	};

	const applyCustom = (range) => {
		setMode("custom");
		onChange(range);
	};

	return (
		<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-2">
			<div className="zc-inline-flex zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-zinc-50 zc-p-1">
				{PRESETS.map((p) => {
					const active = mode === p.key;
					return (
						<button
							key={p.key}
							type="button"
							onClick={() => applyPreset(p.key)}
							className={[
								"zc-rounded-md zc-px-3 zc-py-1.5 zc-text-sm zc-font-medium zc-transition-colors",
								active
									? "zc-bg-white zc-text-zinc-900 zc-shadow-sm"
									: "zc-text-zinc-600 hover:zc-text-zinc-900",
							].join(" ")}
						>
							{p.label}
						</button>
					);
				})}
			</div>

			<CalendarTrigger
				value={value}
				active={mode === "custom"}
				onApply={applyCustom}
			/>
		</div>
	);
}

function CalendarTrigger({ value, active, onApply }) {
	const [open, setOpen] = useState(false);
	const [pos, setPos]   = useState({ top: 0, left: 0 });
	const [draft, setDraft] = useState(() => toRange(value));
	const triggerRef = useRef(null);
	const popoverRef = useRef(null);

	useEffect(() => { setDraft(toRange(value)); }, [value.from, value.to]);

	useLayoutEffect(() => {
		if (!open || !triggerRef.current) return;
		const r = triggerRef.current.getBoundingClientRect();
		const popoverW = 580;
		const left = Math.max(8, Math.min(window.innerWidth - popoverW - 8, r.right - popoverW));
		const top  = r.bottom + 4;
		setPos({ top, left });
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

	const apply = () => {
		if (!draft?.from || !draft?.to) return;
		onApply({
			from: formatYmd(draft.from),
			to:   formatYmd(draft.to),
		});
		setOpen(false);
	};

	const label = active
		? `${formatShort(value.from)} → ${formatShort(value.to)}`
		: "Custom";

	return (
		<>
			<button
				ref={triggerRef}
				type="button"
				onClick={() => setOpen((v) => !v)}
				className={[
					"zc-inline-flex zc-h-10 zc-items-center zc-gap-2 zc-rounded-md zc-border zc-px-3 zc-text-sm zc-font-medium zc-transition-colors",
					active
						? "zc-border-zinc-900 zc-bg-zinc-900 zc-text-white"
						: "zc-border-zinc-300 zc-bg-white zc-text-zinc-700 hover:zc-bg-zinc-50",
				].join(" ")}
			>
				<svg viewBox="0 0 24 24" className="zc-size-4" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
					<rect x="3" y="5" width="18" height="16" rx="2" />
					<path strokeLinecap="round" d="M3 9h18M8 3v4M16 3v4" />
				</svg>
				<span>{label}</span>
			</button>

			{open
				? createPortal(
					<div
						ref={popoverRef}
						style={{ position: "fixed", top: pos.top, left: pos.left, width: 580 }}
						className="zc-z-[100001] zc-overflow-hidden zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-shadow-xl"
					>
						<div className="zc-p-3">
							<DayPicker
								mode="range"
								numberOfMonths={2}
								selected={draft}
								onSelect={setDraft}
								captionLayout="dropdown"
							/>
						</div>
						<div className="zc-flex zc-items-center zc-justify-between zc-gap-2 zc-border-t zc-border-zinc-200 zc-bg-zinc-50 zc-px-3 zc-py-2">
							<span className="zc-text-xs zc-text-zinc-500">
								{draft?.from && draft?.to
									? `${formatShort(formatYmd(draft.from))} → ${formatShort(formatYmd(draft.to))}`
									: "Pick a start and end date"}
							</span>
							<div className="zc-flex zc-gap-2">
								<button
									type="button"
									onClick={() => setOpen(false)}
									className="zc-h-8 zc-rounded-md zc-px-3 zc-text-xs zc-font-medium zc-text-zinc-600 hover:zc-bg-zinc-100"
								>
									Cancel
								</button>
								<button
									type="button"
									onClick={apply}
									disabled={!draft?.from || !draft?.to}
									className="zc-h-8 zc-rounded-md zc-bg-zinc-900 zc-px-3 zc-text-xs zc-font-medium zc-text-white hover:zc-bg-zinc-800 disabled:zc-cursor-not-allowed disabled:zc-bg-zinc-300"
								>
									Apply
								</button>
							</div>
						</div>
					</div>,
					document.body,
				)
				: null}
		</>
	);
}

/* ============================================================
 * Helpers
 * ============================================================ */

/** YYYY-MM-DD → `{ from: Date, to: Date }` for react-day-picker. */
function toRange(value) {
	if (!value?.from || !value?.to) return undefined;
	return {
		from: new Date(value.from + "T00:00:00"),
		to:   new Date(value.to   + "T00:00:00"),
	};
}

/** Date → YYYY-MM-DD (local). */
function formatYmd(d) {
	if (!d) return "";
	const y = d.getFullYear();
	const m = String(d.getMonth() + 1).padStart(2, "0");
	const dd = String(d.getDate()).padStart(2, "0");
	return `${y}-${m}-${dd}`;
}

/** YYYY-MM-DD → "May 9". */
function formatShort(ymd) {
	if (!ymd) return "";
	const d = new Date(ymd + "T00:00:00");
	return d.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}

function detectMode(value) {
	if (!value?.from || !value?.to) return "30";
	const from = new Date(value.from + "T00:00:00Z");
	const to   = new Date(value.to   + "T00:00:00Z");
	const days = Math.round((to - from) / 86_400_000) + 1;
	const presetKey = String(days);
	return PRESETS.find((p) => p.key === presetKey) ? presetKey : "custom";
}

/** UTC-anchored preset range, inclusive of today. */
export function presetRange(days) {
	const today = new Date();
	const to    = today.toISOString().slice(0, 10);
	const start = new Date(today.getTime() - (days - 1) * 86_400_000);
	const from  = start.toISOString().slice(0, 10);
	return { from, to };
}
