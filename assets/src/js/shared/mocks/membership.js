// Shape mirrors GET /membership/me — when the REST handler ships, response must match.
export const membershipMe = () => ({
	user: {
		id: 42,
		display_name: "Tuan Huynh",
		email: "tuanhuynh@floatingcube.com",
	},
	level: "silver",                       // free | silver | gold | vip
	level_label: "Silver",
	multiplier: 1.2,
	status: "active",                      // active | suspended | expired
	joined_at: "2025-08-14T09:30:00Z",
	expires_at: null,
	stats: {
		total_orders: 7,
		lifetime_spend: 612.40,
		currency: "USD",
	},
	next_tier: {
		level: "gold",
		level_label: "Gold",
		// progress is whichever metric is closer
		metric: "spend",                   // "orders" | "spend"
		current: 612.40,
		target:  2000.00,
		remaining: 1387.60,
		percent: 30.62,
	},
});
