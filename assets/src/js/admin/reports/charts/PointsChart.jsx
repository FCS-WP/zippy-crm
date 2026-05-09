import {
	Bar,
	BarChart,
	CartesianGrid,
	Legend,
	ResponsiveContainer,
	Tooltip,
	XAxis,
	YAxis,
} from "recharts";
import { number } from "@/js/shared/utils/format.js";
import { ChartCard, formatDayShort } from "./ChartCard.jsx";

export default function PointsChart({ series }) {
	const totals = series.reduce(
		(acc, row) => ({
			earned:   acc.earned   + row.earned,
			redeemed: acc.redeemed + row.redeemed,
			adjusted: acc.adjusted + row.adjusted,
		}),
		{ earned: 0, redeemed: 0, adjusted: 0 },
	);
	const totalLine = `+${number(totals.earned)} / -${number(totals.redeemed)}`;

	return (
		<ChartCard
			title="Points activity"
			subtitle="Earned, redeemed, and admin adjustments per day"
			total={totalLine}
		>
			<ResponsiveContainer width="100%" height="100%">
				<BarChart data={series} margin={{ top: 8, right: 8, bottom: 0, left: -16 }}>
					<CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
					<XAxis dataKey="day" tickFormatter={formatDayShort} stroke="#a1a1aa" tick={{ fontSize: 12 }} minTickGap={24} />
					<YAxis allowDecimals={false} stroke="#a1a1aa" tick={{ fontSize: 12 }} width={40} />
					<Tooltip
						labelFormatter={formatDayShort}
						contentStyle={{ borderRadius: 8, border: "1px solid #e4e4e7", fontSize: 12 }}
					/>
					<Legend wrapperStyle={{ fontSize: 12 }} />
					<Bar dataKey="earned"   name="Earned"   stackId="a" fill="#10b981" />
					<Bar dataKey="redeemed" name="Redeemed" stackId="a" fill="#f43f5e" />
					<Bar dataKey="adjusted" name="Adjusted" stackId="a" fill="#f59e0b" />
				</BarChart>
			</ResponsiveContainer>
		</ChartCard>
	);
}
