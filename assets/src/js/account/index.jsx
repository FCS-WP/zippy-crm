import { createRoot } from "react-dom/client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import App from "./App.jsx";
import "../shared/styles.css";

const queryClient = new QueryClient();

document.querySelectorAll("[id^='zippy-crm-account-']").forEach((el) => {
	const tab = el.dataset.tab;
	if (!tab) return;
	createRoot(el).render(
		<QueryClientProvider client={queryClient}>
			<App tab={tab} />
		</QueryClientProvider>,
	);
});
