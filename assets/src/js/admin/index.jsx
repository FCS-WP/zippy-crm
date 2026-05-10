import { createRoot } from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import App from "./App.jsx";
import { ConfirmProvider } from "../shared/components/ConfirmDialog.jsx";

// Inter font — admin only. Latin subset, four weights covering the design
// system's needs (regular text, medium for buttons/labels, semibold for
// headings, bold for emphasis). Total: ~50KB woff2 across the four files.
// Imported here (not in account/cart entries) so the font ships with the
// admin bundle and never loads on customer pages.
import "@fontsource/inter/latin-400.css";
import "@fontsource/inter/latin-500.css";
import "@fontsource/inter/latin-600.css";
import "@fontsource/inter/latin-700.css";

import "../shared/styles.css";

const queryClient = new QueryClient();

const mounts = [
	{ id: "zippy-crm-admin-members",  panel: "members"  },
	{ id: "zippy-crm-admin-users",    panel: "users"    },
	{ id: "zippy-crm-admin-tiers",    panel: "tiers"    },
	{ id: "zippy-crm-admin-vouchers", panel: "vouchers" },
	{ id: "zippy-crm-admin-points",   panel: "points"   },
	{ id: "zippy-crm-admin-reports",  panel: "reports"  },
	{ id: "zippy-crm-admin-audit",    panel: "audit"    },
	{ id: "zippy-crm-admin-settings", panel: "settings" },
];

mounts.forEach(({ id, panel }) => {
	const el = document.getElementById(id);
	if (!el) return;
	createRoot(el).render(
		<QueryClientProvider client={queryClient}>
			<ConfirmProvider>
				<App panel={panel} />
			</ConfirmProvider>
		</QueryClientProvider>,
	);
});
