// Mock layer. Toggle off (USE_MOCKS = false) once REST handlers are live.
// Each mock fn matches the eventual REST response shape exactly — components
// never have to know which source they're talking to.

export const USE_MOCKS = true;

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
