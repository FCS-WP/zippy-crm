// Lazy-loaded chart bundle. Importing this file pulls in Recharts and all
// three chart components — that's exactly what the lazy() in ReportsPanel
// wants. Don't import this from any non-lazy file or the code-split breaks.
import MembersChart  from "./MembersChart.jsx";
import PointsChart   from "./PointsChart.jsx";
import VouchersChart from "./VouchersChart.jsx";

export default function Charts({ membersSeries, pointsSeries, vouchersSeries }) {
	return (
		<div className="zc-space-y-4">
			<MembersChart  series={membersSeries}  />
			<PointsChart   series={pointsSeries}   />
			<VouchersChart series={vouchersSeries} />
		</div>
	);
}
