export const vouchersAvailable = () => ({
	items: [
		{
			id: 8, code: "SUMMER25", title: "Summer Sale 25% Off",
			description: "25% off your next order, minimum $50.",
			discount_type: "percent", discount_value: 25.0, min_order_amount: 50,
			expires_at: "2026-06-30T23:59:59Z", remaining_uses: 142,
		},
		{
			id: 7, code: "WELCOME10", title: "Welcome $10 Off",
			description: "$10 off your first order.",
			discount_type: "fixed_cart", discount_value: 10.0, min_order_amount: 0,
			expires_at: null, remaining_uses: null,
		},
	],
});

export const vouchersClaims = () => ({
	items: [
		{
			id: 31, voucher_id: 6, code: "SPRING15",
			title: "Spring 15% Off", status: "claimed",
			claimed_at: "2026-04-20T10:00:00Z", used_at: null,
			expires_at: "2026-05-31T23:59:59Z",
			discount_type: "percent", discount_value: 15.0,
		},
		{
			id: 30, voucher_id: 5, code: "EASTER5",
			title: "Easter $5 Off", status: "used",
			claimed_at: "2026-04-01T08:00:00Z", used_at: "2026-04-03T12:14:00Z",
			expires_at: "2026-04-30T23:59:59Z",
			discount_type: "fixed_cart", discount_value: 5.0,
		},
		{
			id: 29, voucher_id: 4, code: "MARCH20",
			title: "March Promo 20% Off", status: "expired",
			claimed_at: "2026-03-05T08:00:00Z", used_at: null,
			expires_at: "2026-03-31T23:59:59Z",
			discount_type: "percent", discount_value: 20.0,
		},
	],
});
