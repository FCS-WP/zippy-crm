import MembershipTab from "./membership/MembershipTab.jsx";
import PointsTab from "./points/PointsTab.jsx";

const TABS = {
	membership:    MembershipTab,
	points:        PointsTab,
	vouchers:      Placeholder("Vouchers"),
	notifications: Placeholder("Notifications"),
};

export default function App({ tab }) {
	const Component = TABS[tab];
	if (!Component) return null;
	return (
		<div className="zc-text-zinc-900">
			<Component />
		</div>
	);
}

function Placeholder(label) {
	return function PlaceholderTab() {
		return (
			<div className="zc-rounded-xl zc-border zc-border-dashed zc-border-zinc-300 zc-p-10 zc-text-center zc-text-sm zc-text-zinc-500">
				{label} tab — coming next.
			</div>
		);
	};
}
