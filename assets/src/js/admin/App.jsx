export default function App({ panel }) {
	return (
		<div className="zc-p-6">
			<h1 className="zc-text-2xl zc-font-semibold">Zippy CRM — {panel}</h1>
			{/* TODO: route to MembersPanel / VouchersPanel / PointsPanel / ReportsPanel */}
		</div>
	);
}
