// Money — uses the page's WC currency settings if exposed, falls back to USD/en-US.
export function money(value, currency = "USD") {
	const n = Number(value) || 0;
	return new Intl.NumberFormat(undefined, {
		style: "currency",
		currency,
		minimumFractionDigits: 2,
	}).format(n);
}

export function number(value) {
	return new Intl.NumberFormat().format(Number(value) || 0);
}

export function date(iso, opts = { dateStyle: "medium" }) {
	if (!iso) return "—";
	return new Intl.DateTimeFormat(undefined, opts).format(new Date(iso));
}

export function dateTime(iso) {
	return date(iso, { dateStyle: "medium", timeStyle: "short" });
}

export function percent(value, fractionDigits = 0) {
	return `${(Number(value) || 0).toFixed(fractionDigits)}%`;
}

/**
 * True for any discount type that should be rendered with a `%` suffix
 * (cart-level or item-level percent). Mirrors `Voucher::PERCENT_DISCOUNT_TYPES`
 * on the PHP side. Use this instead of `t === "percent"` so item-level percent
 * vouchers render correctly.
 */
export function isPercentType(t) {
	return t === "percent" || t === "percent_product";
}

/** True for any item-level (per-line) discount type. */
export function isItemLevelType(t) {
	return t === "fixed_product" || t === "percent_product";
}
