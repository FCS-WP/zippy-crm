import { DateTimeField } from "../DateTimeField.jsx";
import { Field } from "./Field.jsx";
import { HourWindowField } from "./HourWindowField.jsx";

export function TimeSection({ form, setForm }) {
	return (
		<div className="zc-space-y-5">
			<div className="zc-grid zc-grid-cols-2 zc-gap-4">
				<Field label="Starts at" hint="Voucher is invalid before this date.">
					<DateTimeField
						value={form.starts_at}
						onChange={(v) => setForm((f) => ({ ...f, starts_at: v }))}
						placeholder="Optional — start date"
					/>
				</Field>
				<Field label="Expires at" hint="Voucher is invalid after this date.">
					<DateTimeField
						value={form.expires_at}
						onChange={(v) => setForm((f) => ({ ...f, expires_at: v }))}
						placeholder="Optional — expiry date"
					/>
				</Field>
			</div>

			<Field
				label="Day-of-week / hour-of-day window"
				hint="Voucher is only valid during the selected days and hour range. Useful for happy hours or weekend-only promos. Site timezone applies."
			>
				<HourWindowField
					value={form.allowed_hours}
					onChange={(v) => setForm((f) => ({ ...f, allowed_hours: v }))}
				/>
			</Field>
		</div>
	);
}
