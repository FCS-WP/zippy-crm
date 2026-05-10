import { createRoot } from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import App from "./App.jsx";
import "../shared/styles.css";

const queryClient = new QueryClient();

const mounts = [
	{ id: "zippy-crm-admin-members",  panel: "members"  },
	{ id: "zippy-crm-admin-tiers",    panel: "tiers"    },
	{ id: "zippy-crm-admin-vouchers", panel: "vouchers" },
	{ id: "zippy-crm-admin-points",   panel: "points"   },
	{ id: "zippy-crm-admin-reports",  panel: "reports"  },
];

mounts.forEach(({ id, panel }) => {
	const el = document.getElementById(id);
	if (!el) return;
	createRoot(el).render(
		<QueryClientProvider client={queryClient}>
			<App panel={panel} />
		</QueryClientProvider>,
	);
});
