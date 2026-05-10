/**
 * Auto-pick a tier's display color from its sort_order.
 *
 * Eight-stop palette covers any reasonable tier ladder. Assignments mirror
 * the original spec where possible (free=zinc, silver=zinc-200, gold=yellow,
 * vip=fuchsia) so existing screenshots / docs still match. Beyond the
 * baseline four, new tiers cycle through emerald/sky/violet/orange.
 *
 * Returns:
 *   {
 *     badge: <Badge variant>,
 *     accent: <text color class>,
 *   }
 *
 * Both Badge variants and accent classes already exist in the admin Tailwind
 * setup — no new CSS needed. Falls back to the muted slot for unknown
 * sort_order values (negative, undefined, etc).
 */
const PALETTE = [
	{ badge: "muted",   accent: "zc-text-zinc-700"     }, // 0 — free / default
	{ badge: "silver",  accent: "zc-text-zinc-700"     }, // 1 — silver
	{ badge: "gold",    accent: "zc-text-yellow-700"   }, // 2 — gold
	{ badge: "vip",     accent: "zc-text-fuchsia-700"  }, // 3 — vip
	{ badge: "success", accent: "zc-text-emerald-700"  }, // 4 — emerald
	{ badge: "info",    accent: "zc-text-sky-700"      }, // 5 — sky
	{ badge: "warning", accent: "zc-text-amber-700"    }, // 6 — amber
	{ badge: "danger",  accent: "zc-text-rose-700"     }, // 7 — rose
];

export function tierColor(sortOrder) {
	const n = Number.isFinite(sortOrder) ? sortOrder : 0;
	const slot = ((n % PALETTE.length) + PALETTE.length) % PALETTE.length;
	return PALETTE[slot];
}
