import { number } from "@/js/shared/utils/format.js";

/**
 * Per-event meta renderer. Each event has its own shape — keep the rendering
 * explicit so the admin sees something useful instead of raw JSON.
 *
 * Unknown / missing meta falls back to a muted hyphen.
 *
 * The shapes here mirror what AuditLogger writes on the PHP side. If you add
 * a new event:
 *   1. Extend events.js with the slug + label
 *   2. Add a case in the switch below
 *   3. Smoke-check by triggering the action in the admin
 */
export function MetaCell({ event, meta }) {
	const m = meta ?? {};

	switch (event) {
		case "membership.level_changed":
			return <Pair from={m.from} to={m.to} suffix="tier" />;

		case "membership.status_changed":
			return <Pair from={m.from} to={m.to} suffix="status" />;

		case "points.adjusted": {
			const delta  = Number(m.delta) || 0;
			const reason = m.reason ?? "";
			return (
				<span className="zc-text-xs">
					<DeltaPill delta={delta} />
					{reason ? <span className="zc-ml-2 zc-text-zinc-600 zc-italic">"{reason}"</span> : null}
				</span>
			);
		}

		case "points.recalculated": {
			const processed = m.processed ?? 0;
			const drift     = m.drift_corrected ?? 0;
			const errors    = m.errors ?? 0;
			return (
				<span className="zc-text-xs zc-text-zinc-600">
					processed <strong className="zc-text-zinc-900">{number(processed)}</strong>
					{drift > 0  ? <> · drift <strong className="zc-text-sky-700">{number(drift)}</strong></>     : null}
					{errors > 0 ? <> · errors <strong className="zc-text-rose-700">{number(errors)}</strong></>  : null}
				</span>
			);
		}

		case "voucher.created":
		case "voucher.updated":
		case "voucher.published":
		case "voucher.paused":
		case "voucher.resumed":
		case "voucher.deleted":
		case "voucher.duplicated":
			return (
				<span className="zc-text-xs">
					{m.code ? <Code>{m.code}</Code> : null}
					{m.fields ? (
						<span className="zc-ml-2 zc-text-zinc-600">
							changed: {(Array.isArray(m.fields) ? m.fields : []).join(", ") || "—"}
						</span>
					) : null}
					{m.from_id ? (
						<span className="zc-ml-2 zc-text-zinc-500">from #{m.from_id}</span>
					) : null}
				</span>
			);

		case "tier.created":
		case "tier.updated":
		case "tier.deleted":
			return (
				<span className="zc-text-xs">
					<Code>{m.slug ?? "?"}</Code>
					{m.fields ? (
						<span className="zc-ml-2 zc-text-zinc-600">
							changed: {(Array.isArray(m.fields) ? m.fields : []).join(", ") || "—"}
						</span>
					) : null}
				</span>
			);

		default:
			// Unknown event — fall back to compact JSON so we still show
			// something useful while the admin / dev tracks down the missing
			// renderer.
			if (Object.keys(m).length === 0) return <span className="zc-text-zinc-400">—</span>;
			return (
				<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-[10px] zc-font-mono zc-text-zinc-700">
					{JSON.stringify(m)}
				</code>
			);
	}
}

function Pair({ from, to, suffix = "" }) {
	if (!from && !to) return <span className="zc-text-zinc-400">—</span>;
	return (
		<span className="zc-inline-flex zc-items-center zc-gap-1 zc-text-xs">
			<Code>{from ?? "—"}</Code>
			<svg viewBox="0 0 24 24" className="zc-size-3 zc-text-zinc-400" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
				<path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14M13 6l6 6-6 6" />
			</svg>
			<Code>{to ?? "—"}</Code>
			{suffix ? <span className="zc-ml-1 zc-text-zinc-500">{suffix}</span> : null}
		</span>
	);
}

function DeltaPill({ delta }) {
	const sign = delta > 0 ? "+" : "";
	const tone = delta > 0
		? "zc-bg-emerald-100 zc-text-emerald-800"
		: delta < 0
			? "zc-bg-rose-100 zc-text-rose-800"
			: "zc-bg-zinc-100 zc-text-zinc-700";
	return (
		<span className={`zc-rounded zc-px-1.5 zc-py-0.5 zc-text-[11px] zc-font-semibold zc-tabular-nums ${tone}`}>
			{sign}{number(delta)} pts
		</span>
	);
}

function Code({ children }) {
	return (
		<code className="zc-rounded zc-bg-zinc-100 zc-px-1.5 zc-py-0.5 zc-text-[11px] zc-font-mono zc-text-zinc-700">
			{children}
		</code>
	);
}
