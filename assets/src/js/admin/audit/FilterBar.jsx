import { useState } from "react";
import { DateRangePicker } from "../reports/DateRangePicker.jsx";
import { EVENTS, EVENT_CATEGORIES } from "./events.js";
import { UserPickerField } from "./UserPickerField.jsx";

/**
 * Audit panel filter row. Four controls:
 *   - Event type (grouped optgroup select; "All events" up top)
 *   - Actor (admin who did it)  — single user picker
 *   - Target (user it was done to) — single user picker
 *   - Date range (presets + custom calendar — reused from Reports)
 */
export function FilterBar({ event, onEvent, actor, onActor, target, onTarget, range, onRange }) {
	return (
		<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-2">
			<EventSelect value={event} onChange={onEvent} />

			<div className="zc-flex zc-items-center zc-gap-1.5">
				<span className="zc-text-xs zc-text-zinc-500">Admin:</span>
				<UserPickerField value={actor} onChange={onActor} placeholder="Any admin" />
			</div>

			<div className="zc-flex zc-items-center zc-gap-1.5">
				<span className="zc-text-xs zc-text-zinc-500">Target:</span>
				<UserPickerField value={target} onChange={onTarget} placeholder="Any target" />
			</div>

			<DateRangePicker value={range} onChange={onRange} />
		</div>
	);
}

function EventSelect({ value, onChange }) {
	return (
		<div className="zc-relative">
			<select
				aria-label="Filter by event type"
				value={value}
				onChange={(e) => onChange(e.target.value)}
				style={{ WebkitAppearance: "none", MozAppearance: "none", appearance: "none", backgroundImage: "none" }}
				className="zc-h-9 zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-pl-3 zc-pr-9 zc-text-sm zc-text-zinc-900 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200"
			>
				<option value="">All events</option>
				{EVENT_CATEGORIES.map((cat) => (
					<optgroup key={cat.key} label={cat.label}>
						{EVENTS.filter((e) => e.category === cat.key).map((e) => (
							<option key={e.key} value={e.key}>{e.label}</option>
						))}
					</optgroup>
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
