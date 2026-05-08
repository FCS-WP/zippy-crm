// Mock layer. Toggle off (USE_MOCKS = false) once REST handlers are live.
// Each mock fn matches the eventual REST response shape exactly — components
// never have to know which source they're talking to.

// Master switch — set false to disable mocks globally.
// Per-route: add the route to LIVE_ROUTES below to send it to the real REST API
// even while other routes still mock. Lets us flip features one at a time.
export const USE_MOCKS = true;

// Routes that should hit the real backend, even when USE_MOCKS is true.
// Match by exact "METHOD /path" for fixed paths, or prefix when the path has params.
const LIVE_ROUTES = new Set([
	"GET /membership/me",
	"GET /points/me",
	"GET /points/ledger",
	"POST /points/redeem",
	"GET /vouchers",
	"GET /vouchers/claims",
	"GET /notifications/preferences",
	"PUT /notifications/preferences",
]);

// Path prefixes that should hit the real backend (for routes with `{id}` etc).
const LIVE_PREFIXES = [
	"POST /vouchers/", // /vouchers/{id}/claim
];

export function shouldMock(method, path) {
	if (!USE_MOCKS) return false;
	const key = `${method} ${path.split("?")[0]}`;
	if (LIVE_ROUTES.has(key)) return false;
	if (LIVE_PREFIXES.some((p) => key.startsWith(p))) return false;
	return true;
}

const delay = (ms = 250) => new Promise((r) => setTimeout(r, ms));

import { membershipMe } from "./membership.js";
import { pointsMe, pointsLedger } from "./points.js";
import { vouchersAvailable, vouchersClaims } from "./vouchers.js";
import { notifPrefs } from "./notifications.js";

// Routes a path → mock response. Must mirror the REST surface in FEATURE_SPEC §8.
const ROUTES = {
	"GET /membership/me":              () => membershipMe(),
	"GET /points/me":                  () => pointsMe(),
	"GET /points/ledger":              (q) => pointsLedger(q),
	"GET /vouchers":                   () => vouchersAvailable(),
	"GET /vouchers/claims":            () => vouchersClaims(),
	"GET /notifications/preferences":  () => notifPrefs(),
};

export async function mockRequest(method, path, _body) {
	await delay();
	const key = `${method} ${path.split("?")[0]}`;
	const handler = ROUTES[key];
	if (!handler) {
		throw Object.assign(new Error(`No mock for ${key}`), { code: "no_mock", status: 404 });
	}
	const query = Object.fromEntries(new URL("http://x/" + path).searchParams);
	return handler(query);
}
