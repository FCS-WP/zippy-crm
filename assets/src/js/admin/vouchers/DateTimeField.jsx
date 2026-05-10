import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { DayPicker } from "react-day-picker";
import "react-day-picker/style.css";
import "../reports/day-picker-overrides.css";

/**
 * Single-date + time input. Trigger button shows the current value and opens
 * a portaled popover with a calendar + time field. Emits MySQL-style strings
 * `YYYY-MM-DD HH:MM:00` (or null when cleared) — matches what the voucher
 * REST endpoint expects.
 *
 * Why a custom widget instead of `<input type="datetime-local">`:
 *   1. Native picker UI is browser-default and clashes with the rest of the
 *      admin look.
 *   2. Native picker emits "YYYY-MM-DDTHH:MM" which we'd have to normalise
 *      anyway.
 *   3. WP admin's `forms.css` styles native pickers in ways we can't fully
 *      override without specificity wars.
 */
export function DateTimeField({ value, onChange, placeholder = "Pick a date" }) {
	const [open, setOpen]   = useState(false);
	const [pos, setPos]     = useState({ top: 0, left: 0 });
	const [draftDate, setDraftDate] = useState(() => parseDate(value));
	const [draftTime, setDraftTime] = useState(() => parseTime(value));

	const triggerRef = useRef(null);
	const popoverRef = useRef(null);

	useEffect(() => {
		setDraftDate(parseDate(value));
		setDraftTime(parseTime(value));
	}, [value]);

	const [width, setWidth] = useState(280);

	useLayoutEffect(() => {
		if (!open || !triggerRef.current) return;
		const r = triggerRef.current.getBoundingClientRect();
		// Popover is at least as wide as the trigger (so the calendar fills the
		// field on narrow drawers), with a 240px floor and 360px ceiling so it
		// doesn't get unusably small or absurdly wide.
		const w   = Math.max(240, Math.min(360, r.width));
		const left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left));
		const top  = r.bottom + 4;
		setWidth(w);
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
		if (!draftDate) {
			onChange(null);
		} else {
			onChange(toMysql(draftDate, draftTime || "00:00"));
		}
		setOpen(false);
	};

	const clear = () => {
		setDraftDate(undefined);
		setDraftTime("");
		onChange(null);
		setOpen(false);
	};

	const label = value ? formatLabel(value) : placeholder;
	const hasValue = Boolean(value);

	return (
		<>
			<button
				ref={triggerRef}
				type="button"
				onClick={() => setOpen((v) => !v)}
				className={[
					"zc-flex zc-h-10 zc-w-full zc-items-center zc-justify-between zc-gap-2 zc-rounded-md zc-border zc-px-3 zc-text-sm zc-transition-colors",
					hasValue
						? "zc-border-zinc-300 zc-bg-white zc-text-zinc-900"
						: "zc-border-zinc-300 zc-bg-white zc-text-zinc-400",
					"hover:zc-bg-zinc-50",
				].join(" ")}
			>
				<span className="zc-flex zc-items-center zc-gap-2">
					<svg viewBox="0 0 24 24" className="zc-size-4 zc-text-zinc-500" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
						<rect x="3" y="5" width="18" height="16" rx="2" />
						<path strokeLinecap="round" d="M3 9h18M8 3v4M16 3v4" />
					</svg>
					<span>{label}</span>
				</span>
				{hasValue ? (
					<span
						role="button"
						tabIndex={0}
						aria-label="Clear date"
						onClick={(e) => { e.stopPropagation(); clear(); }}
						onKeyDown={(e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); clear(); } }}
						className="zc-rounded zc-p-0.5 zc-text-zinc-400 hover:zc-bg-zinc-100 hover:zc-text-zinc-700"
					>
						<svg viewBox="0 0 24 24" className="zc-size-3.5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
							<path strokeLinecap="round" d="M6 6l12 12M18 6L6 18" />
						</svg>
					</span>
				) : null}
			</button>

			{open
				? createPortal(
					<div
						ref={popoverRef}
						style={{ position: "fixed", top: pos.top, left: pos.left, width }}
						className="zc-z-[100001] zc-overflow-hidden zc-rounded-lg zc-border zc-border-zinc-200 zc-bg-white zc-shadow-xl"
					>
						<div className="zc-px-3 zc-py-2">
							<DayPicker
								mode="single"
								selected={draftDate}
								onSelect={setDraftDate}
								captionLayout="dropdown"
								showOutsideDays={false}
								defaultMonth={draftDate ?? new Date()}
							/>
						</div>
						<div className="zc-flex zc-items-center zc-gap-2 zc-border-t zc-border-zinc-200 zc-px-3 zc-py-2">
							<label className="zc-text-[11px] zc-text-zinc-500">Time</label>
							<input
								type="time"
								value={draftTime}
								onChange={(e) => setDraftTime(e.target.value)}
								className="zc-h-7 zc-flex-1 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-2 zc-text-xs zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-1 focus:zc-ring-zinc-200"
							/>
						</div>
						<div className="zc-flex zc-items-center zc-justify-end zc-gap-1 zc-border-t zc-border-zinc-200 zc-bg-zinc-50 zc-px-3 zc-py-1.5">
							<button
								type="button"
								onClick={() => setOpen(false)}
								className="zc-h-7 zc-rounded-md zc-px-2.5 zc-text-xs zc-font-medium zc-text-zinc-600 hover:zc-bg-zinc-100"
							>
								Cancel
							</button>
							<button
								type="button"
								onClick={apply}
								className="zc-h-7 zc-rounded-md zc-bg-zinc-900 zc-px-2.5 zc-text-xs zc-font-medium zc-text-white hover:zc-bg-zinc-800"
							>
								Apply
							</button>
						</div>
					</div>,
					document.body,
				)
				: null}
		</>
	);
}

/* ============================================================
 * Helpers — convert between the field's internal Date+time and the API's
 * MySQL-style "YYYY-MM-DD HH:MM:SS" format used by VoucherService.
 * ============================================================ */

function parseDate(mysql) {
	if (!mysql) return undefined;
	// API gives back either "YYYY-MM-DD HH:MM:SS" (MySQL) or ISO with T.
	const date = new Date(mysql.includes("T") ? mysql : mysql.replace(" ", "T"));
	return Number.isNaN(date.getTime()) ? undefined : date;
}

function parseTime(mysql) {
	if (!mysql) return "";
	const d = parseDate(mysql);
	if (!d) return "";
	return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
}

function toMysql(date, hhmm) {
	const [hh, mm] = (hhmm || "00:00").split(":").map((n) => parseInt(n, 10));
	const d = new Date(date.getFullYear(), date.getMonth(), date.getDate(), hh || 0, mm || 0, 0);
	const y  = d.getFullYear();
	const mo = String(d.getMonth() + 1).padStart(2, "0");
	const dd = String(d.getDate()).padStart(2, "0");
	const h  = String(d.getHours()).padStart(2, "0");
	const mi = String(d.getMinutes()).padStart(2, "0");
	return `${y}-${mo}-${dd} ${h}:${mi}:00`;
}

function formatLabel(mysql) {
	const d = parseDate(mysql);
	if (!d) return "";
	const datePart = d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
	const timePart = `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
	return `${datePart}, ${timePart}`;
}
