export const pointsMe = () => ({
	balance: 730,
	total_earned: 1180,
	total_redeemed: 450,
	dollar_value: 36.50,        // balance / 20
	redemption_rate: 20,         // points per $1
	min_redemption: 20,
});

const ALL_LEDGER = [
	{ id: 21, type: "earn",   points:  90, description: "Order #1042", order_id: 1042, created_at: "2026-04-22T08:11:00Z" },
	{ id: 20, type: "redeem", points: -100, description: "Coupon CRM-RDM-A1B2C3", order_id: 1041, created_at: "2026-04-15T14:02:00Z" },
	{ id: 19, type: "earn",   points:  72, description: "Order #1038", order_id: 1038, created_at: "2026-04-08T10:20:00Z" },
	{ id: 18, type: "earn",   points: 144, description: "Order #1031", order_id: 1031, created_at: "2026-03-28T17:55:00Z" },
	{ id: 17, type: "adjust", points:  50, description: "Birthday bonus", order_id: null, created_at: "2026-03-14T00:00:00Z" },
	{ id: 16, type: "earn",   points:  60, description: "Order #1024", order_id: 1024, created_at: "2026-03-02T11:34:00Z" },
	{ id: 15, type: "redeem", points: -200, description: "Coupon CRM-RDM-Z9Y8X7", order_id: 1019, created_at: "2026-02-21T19:08:00Z" },
	{ id: 14, type: "earn",   points: 108, description: "Order #1017", order_id: 1017, created_at: "2026-02-12T09:42:00Z" },
	{ id: 13, type: "earn",   points:  84, description: "Order #1011", order_id: 1011, created_at: "2026-02-01T13:21:00Z" },
	{ id: 12, type: "earn",   points:  66, description: "Order #1008", order_id: 1008, created_at: "2026-01-22T08:00:00Z" },
	{ id: 11, type: "earn",   points: 132, description: "Order #1004", order_id: 1004, created_at: "2026-01-09T16:18:00Z" },
];

export const pointsLedger = ({ page = 1, per_page = 10 } = {}) => {
	const p = Number(page) || 1;
	const pp = Number(per_page) || 10;
	const start = (p - 1) * pp;
	return {
		items: ALL_LEDGER.slice(start, start + pp),
		page: p,
		per_page: pp,
		total: ALL_LEDGER.length,
		total_pages: Math.ceil(ALL_LEDGER.length / pp),
	};
};
