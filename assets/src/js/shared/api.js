// Thin REST client. Reads root + nonce from window.zippyCrm (set by Assets::enqueue_*).
// While the backend is being built, USE_MOCKS routes calls to the in-memory fixtures.
import { USE_MOCKS, mockRequest } from "./mocks/index.js";

const cfg = () => window.zippyCrm ?? { root: "/wp-json/zippy-crm/v1/", nonce: "" };

async function request(method, path, body) {
	if (USE_MOCKS) {
		return mockRequest(method, path, body);
	}

	const { root, nonce } = cfg();
	const res = await fetch(root + path.replace(/^\//, ""), {
		method,
		credentials: "same-origin",
		headers: {
			"Content-Type": "application/json",
			"X-WP-Nonce": nonce,
		},
		body: body ? JSON.stringify(body) : undefined,
	});
	const data = await res.json().catch(() => ({}));
	if (!res.ok) {
		const err = new Error(data?.message || res.statusText);
		err.code = data?.code;
		err.status = res.status;
		throw err;
	}
	return data;
}

export const api = {
	get:    (path)        => request("GET",    path),
	post:   (path, body)  => request("POST",   path, body),
	put:    (path, body)  => request("PUT",    path, body),
	del:    (path)        => request("DELETE", path),
};
