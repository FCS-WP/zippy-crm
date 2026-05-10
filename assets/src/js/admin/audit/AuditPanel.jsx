import { useMemo, useState } from "react";
import { useApiQuery } from "@/js/shared/hooks/useApi.js";
import { FilterChips } from "@/js/shared/components/FilterChips.jsx";
import { Pagination } from "@/js/shared/components/Pagination.jsx";
import { presetRange } from "../reports/DateRangePicker.jsx";
import { AuditTable } from "./AuditTable.jsx";
import { FilterBar } from "./FilterBar.jsx";
import { labelFor } from "./events.js";

export default function AuditPanel() {
	const [event, setEvent]     = useState("");
	const [actor, setActor]     = useState(0);
	const [target, setTarget]   = useState(0);
	const [range, setRange]     = useState(() => presetRange(30));
	const [page, setPage]       = useState(1);
	const [perPage, setPerPage] = useState(25);

	const params = useMemo(() => ({
		event,
		actor_id:  actor,
		target_id: target,
		from: range.from,
		to:   range.to,
		page,
		per_page: perPage,
	}), [event, actor, target, range.from, range.to, page, perPage]);

	const list = useApiQuery("/admin/audit", { params });

	const items = list.data?.items ?? [];
	const total = list.data?.total ?? 0;

	// Resolve actor/target chip labels from already-fetched rows when possible
	// (saves a round trip; falls back to "#42").
	const actorRow  = items.find((r) => r.actor?.id === actor);
	const targetRow = items.find((r) => r.target?.id === target);

	const clearAll = () => {
		setEvent(""); setActor(0); setTarget(0);
		setRange(presetRange(30));
		setPage(1);
	};

	return (
		<div className="zc-space-y-4 zc-p-6">
			<header>
				<h1 className="zc-text-2xl zc-font-semibold zc-text-zinc-900">Audit log</h1>
				<p className="zc-text-sm zc-text-zinc-500">
					Every admin write action — level changes, points adjustments, voucher
					lifecycle, tier edits. Append-only; corrections are new rows.
				</p>
			</header>

			<FilterBar
				event={event}     onEvent={(v)  => { setEvent(v);  setPage(1); }}
				actor={actor}     onActor={(v)  => { setActor(v);  setPage(1); }}
				target={target}   onTarget={(v) => { setTarget(v); setPage(1); }}
				range={range}     onRange={(v)  => { setRange(v);  setPage(1); }}
			/>

			<FilterChips
				filters={[
					{
						key:  "event",
						label: "Event",
						value: event,
						valueLabel: event ? labelFor(event) : "",
						onClear: () => { setEvent(""); setPage(1); },
					},
					{
						key:  "actor",
						label: "Admin",
						value: actor || "",
						valueLabel: actor
							? (actorRow?.actor?.display_name || actorRow?.actor?.login || `#${actor}`)
							: "",
						onClear: () => { setActor(0); setPage(1); },
					},
					{
						key:  "target",
						label: "Target",
						value: target || "",
						valueLabel: target
							? (targetRow?.target?.display_name || targetRow?.target?.login || `#${target}`)
							: "",
						onClear: () => { setTarget(0); setPage(1); },
					},
				]}
				onClearAll={clearAll}
			/>

			<AuditTable
				rows={items}
				loading={list.isLoading}
				error={list.error?.message}
			/>
			<Pagination
				page={page}
				perPage={perPage}
				total={total}
				onPage={setPage}
				onPerPage={(n) => { setPerPage(n); setPage(1); }}
			/>
		</div>
	);
}
