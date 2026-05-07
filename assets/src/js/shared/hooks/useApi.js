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
	return useMutation({
		mutationFn: (body) => api[method](path, body),
		onSuccess: (...args) => {
			if (options.invalidate) {
				options.invalidate.forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
			}
			options.onSuccess?.(...args);
		},
		...options,
	});
}
