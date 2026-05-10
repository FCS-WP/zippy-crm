import { TimeField } from "./TimeField.jsx";

/**
 * Day-of-week chips + hour range picker. Emits null when "no restriction"
 * (master switch off). When on, emits `{ days, from_minute, to_minute }`
 * — same shape Hooks/VoucherHourWindow expects.
 *
 * `from_minute` / `to_minute` are minutes-since-midnight (0..1440), matching
 * the storage format. UI uses our styled TimeField (HH:MM 24h string), the
 * conversion happens at the boundary.
 */
const DAYS = [
	{ key: 0, short: "Sun" },
	{ key: 1, short: "Mon" },
	{ key: 2, short: "Tue" },
	{ key: 3, short: "Wed" },
	{ key: 4, short: "Thu" },
	{ key: 5, short: "Fri" },
	{ key: 6, short: "Sat" },
];

const DEFAULT_WINDOW = { days: [1, 2, 3, 4, 5], from_minute: 9 * 60, to_minute: 17 * 60 };

export function HourWindowField({ value, onChange }) {
	const enabled = !!value;
	const window  = value ?? DEFAULT_WINDOW;

	const toggleEnabled = (on) => onChange(on ? DEFAULT_WINDOW : null);

	const toggleDay = (day) => {
		const has  = window.days.includes(day);
		const next = has ? window.days.filter((d) => d !== day) : [...window.days, day].sort();
		onChange({ ...window, days: next });
	};

	const setHHMM = (key) => (hhmm) => {
		onChange({ ...window, [key]: hhmmToMinutes(hhmm) });
	};

	const wrapped = enabled && window.from_minute > window.to_minute;

	return (
		<div className="zc-space-y-3">
			<label className="zc-flex zc-items-center zc-gap-2">
				<input
					type="checkbox"
					checked={enabled}
					onChange={(e) => toggleEnabled(e.target.checked)}
					className="zc-size-4"
				/>
				<span className="zc-text-sm zc-font-medium zc-text-zinc-800">
					Restrict to specific days &amp; hours
				</span>
			</label>

			{enabled ? (
				<div className="zc-rounded-md zc-border zc-border-zinc-200 zc-bg-zinc-50/50 zc-p-3 zc-space-y-3">
					<div>
						<p className="zc-mb-1.5 zc-text-xs zc-font-medium zc-text-zinc-700">Days</p>
						<div className="zc-flex zc-flex-wrap zc-gap-1.5">
							{DAYS.map((d) => {
								const active = window.days.includes(d.key);
								return (
									<button
										key={d.key}
										type="button"
										onClick={() => toggleDay(d.key)}
										className={[
											"zc-rounded-md zc-border zc-px-2.5 zc-py-1 zc-text-xs zc-font-medium zc-transition-colors",
											active
												? "zc-border-zinc-900 zc-bg-zinc-900 zc-text-white"
												: "zc-border-zinc-300 zc-bg-white zc-text-zinc-700 hover:zc-bg-zinc-100",
										].join(" ")}
									>
										{d.short}
									</button>
								);
							})}
						</div>
					</div>

					<div className="zc-grid zc-grid-cols-2 zc-gap-3">
						<div className="zc-space-y-1">
							<span className="zc-text-xs zc-font-medium zc-text-zinc-700">From</span>
							<TimeField
								value={minutesToHHMM(window.from_minute)}
								onChange={setHHMM("from_minute")}
							/>
						</div>
						<div className="zc-space-y-1">
							<span className="zc-text-xs zc-font-medium zc-text-zinc-700">To</span>
							<TimeField
								value={minutesToHHMM(window.to_minute)}
								onChange={setHHMM("to_minute")}
							/>
						</div>
					</div>

					{wrapped ? (
						<p className="zc-rounded zc-border zc-border-amber-200 zc-bg-amber-50 zc-px-2 zc-py-1.5 zc-text-xs zc-text-amber-900">
							Window crosses midnight. Voucher will be valid from {minutesToHHMM(window.from_minute)} on selected days
							until {minutesToHHMM(window.to_minute)} the next morning.
						</p>
					) : null}

					{window.days.length === 0 ? (
						<p className="zc-rounded zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-2 zc-py-1.5 zc-text-xs zc-text-rose-800">
							No days selected — voucher will never be redeemable.
						</p>
					) : null}
				</div>
			) : null}
		</div>
	);
}

function hhmmToMinutes(hhmm) {
	if (!hhmm || typeof hhmm !== "string") return 0;
	const [h, m] = hhmm.split(":").map((n) => parseInt(n, 10) || 0);
	return Math.max(0, Math.min(1440, h * 60 + m));
}

function minutesToHHMM(min) {
	const m = Math.max(0, Math.min(1440, min | 0));
	const h = Math.floor(m / 60);
	const r = m % 60;
	return `${String(h).padStart(2, "0")}:${String(r).padStart(2, "0")}`;
}
