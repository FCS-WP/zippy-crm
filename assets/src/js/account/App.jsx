import MembershipTab from "./membership/MembershipTab.jsx";
import PointsTab from "./points/PointsTab.jsx";
import VouchersTab from "./vouchers/VouchersTab.jsx";
import NotificationsTab from "./notifications/NotificationsTab.jsx";

const TABS = {
	membership:    MembershipTab,
	points:        PointsTab,
	vouchers:      VouchersTab,
	notifications: NotificationsTab,
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
