import { useQuery, useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "../api.js";

// Stable key generator — same path + same params = same cache entry.
const key = (path, params) => (params ? [path, params] : [path]);

export function useApiQuery(path, { params, ...options } = {}) {
	const url = params
		? `${path}?${new URLSearchParams(params).toString()}`
		: path;
	return useQuery({
		queryKey: key(path, params),
		queryFn:  () => api.get(url),
		staleTime: 30_000,
		...options,
	});
}

export function useApiMutation(method, path, options = {}) {
	const qc = useQueryClient();
	const { invalidate, onSuccess: userOnSuccess, ...rest } = options;

	// Pull invalidate + onSuccess out of options BEFORE the spread, then put
	// our wrapper last so it isn't clobbered by the user's onSuccess. The
	// user's onSuccess still fires — we call it after invalidation runs.
	return useMutation({
		mutationFn: (body) => api[method](path, body),
		...rest,
		onSuccess: (...args) => {
			if (invalidate) {
				invalidate.forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
			}
			userOnSuccess?.(...args);
		},
	});
}
