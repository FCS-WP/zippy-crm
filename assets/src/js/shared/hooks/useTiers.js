import { useApiQuery } from "./useApi.js";

/**
 * Single source of truth for tier definitions on the React side.
 *
 * Hits `GET /tiers?include_admin_only=true` so admin-facing UIs (level
 * change, members filter, stats bar) see the full ladder including VIP.
 *
 * Returns the React Query result plus a few convenience derivatives so
 * components don't reimplement the same filter/lookup over and over:
 *   - `tiers`       — array of { slug, label, multiplier, threshold_orders, threshold_spend, is_admin_only }
 *   - `bySlug`      — { [slug]: tier }
 *   - `findTier`    — (slug) => tier | null
 *   - `labelFor`    — (slug) => string (label, falls back to titlecased slug)
 *   - `multiplierFor` — (slug) => number (1.0 fallback)
 *
 * The query is cached by React Query at the default 30s staleTime, which is
 * fine: tiers change only via admin CRUD, and any mutation on /admin/tiers
 * should `invalidate: ["/tiers"]` on success.
 */
export function useTiers({ includeAdminOnly = true } = {}) {
	const params = includeAdminOnly ? { include_admin_only: true } : undefined;
	const query = useApiQuery("/tiers", { params });

	const tiers = query.data?.items ?? [];
	const bySlug = Object.fromEntries(tiers.map((t) => [t.slug, t]));

	return {
		...query,
		tiers,
		bySlug,
		findTier:     (slug) => bySlug[slug] ?? null,
		labelFor:     (slug) => bySlug[slug]?.label ?? titlecase(slug),
		multiplierFor: (slug) => Number(bySlug[slug]?.multiplier ?? 1),
	};
}

function titlecase(s) {
	if (!s) return "";
	return s.charAt(0).toUpperCase() + s.slice(1);
}
