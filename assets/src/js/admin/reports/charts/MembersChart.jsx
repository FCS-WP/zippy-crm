import {
	Area,
	AreaChart,
	CartesianGrid,
	ResponsiveContainer,
	Tooltip,
	XAxis,
	YAxis,
} from "recharts";
import { number } from "@/js/shared/utils/format.js";
import { ChartCard, formatDayShort } from "./ChartCard.jsx";

export default function MembersChart({ series }) {
	const total = series.reduce((sum, row) => sum + row.total, 0);

	return (
		<ChartCard
			title="New members"
			subtitle="Memberships created per day"
			total={number(total)}
		>
			<ResponsiveContainer width="100%" height="100%">
				<AreaChart data={series} margin={{ top: 8, right: 8, bottom: 0, left: -16 }}>
					<defs>
						<linearGradient id="zc-members-fill" x1="0" y1="0" x2="0" y2="1">
							<stop offset="0%"  stopColor="#10b981" stopOpacity={0.4} />
							<stop offset="100%" stopColor="#10b981" stopOpacity={0} />
						</linearGradient>
					</defs>
					<CartesianGrid strokeDasharray="3 3" stroke="#e4e4e7" />
					<XAxis dataKey="day" tickFormatter={formatDayShort} stroke="#a1a1aa" tick={{ fontSize: 12 }} minTickGap={24} />
					<YAxis allowDecimals={false} stroke="#a1a1aa" tick={{ fontSize: 12 }} width={40} />
					<Tooltip
						labelFormatter={formatDayShort}
						contentStyle={{ borderRadius: 8, border: "1px solid #e4e4e7", fontSize: 12 }}
					/>
					<Area type="monotone" dataKey="total" name="New members" stroke="#10b981" fill="url(#zc-members-fill)" strokeWidth={2} />
				</AreaChart>
			</ResponsiveContainer>
		</ChartCard>
	);
}
