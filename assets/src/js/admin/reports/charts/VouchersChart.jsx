import {
	CartesianGrid,
	Legend,
	Line,
	LineChart,
	ResponsiveContainer,
	Tooltip,
	XAxis,
	YAxis,
} from "recharts";
import { number } from "@/js/shared/utils/format.js";
import { ChartCard, formatDayShort } from "./ChartCard.jsx";

export default function VouchersChart({ series }) {
	const totalClaimed = series.reduce((s, r) => s + r.claimed, 0);
	const totalUsed    = series.reduce((s, r) => s + r.used,    0);

	return (
		<ChartCard
			title="Voucher activity"
			subtitle="Claims vs uses per day"
			total={`${number(totalClaimed)} claimed · ${number(totalUsed)} used`}
		>
			<ResponsiveContainer width="100%" height="100%">
				<LineChart data={series} margin={{ top: 8, right: 8, bottom: 0, left: -16 }}>
					<CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
					<XAxis dataKey="day" tickFormatter={formatDayShort} stroke="#a1a1aa" tick={{ fontSize: 12 }} minTickGap={24} />
					<YAxis allowDecimals={false} stroke="#a1a1aa" tick={{ fontSize: 12 }} width={40} />
					<Tooltip
						labelFormatter={formatDayShort}
						contentStyle={{ borderRadius: 8, border: "1px solid #e4e4e7", fontSize: 12 }}
					/>
					<Legend wrapperStyle={{ fontSize: 12 }} />
					<Line type="monotone" dataKey="claimed" name="Claimed" stroke="#0ea5e9" strokeWidth={2} dot={false} />
					<Line type="monotone" dataKey="used"    name="Used"    stroke="#10b981" strokeWidth={2} dot={false} />
				</LineChart>
			</ResponsiveContainer>
		</ChartCard>
	);
}
