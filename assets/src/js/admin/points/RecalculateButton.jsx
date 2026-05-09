import { useState } from "react";
import { useApiMutation } from "@/js/shared/hooks/useApi.js";
import { Button } from "@/js/shared/ui/button.jsx";
import { number } from "@/js/shared/utils/format.js";

/**
 * Bulk reconciliation trigger. Confirms first because it walks every member —
 * cheap on small sites, but worth a confirm so an accidental click doesn't
 * spike DB load on a large one.
 */
export function RecalculateButton({ memberCount }) {
	const [result, setResult] = useState(null);
	const recalc = useApiMutation("post", "/admin/points/recalculate-all", {
		invalidate: ["/admin/points/summary", "/admin/points/ledger", "/admin/members"],
	});

	const onClick = () => {
		const ok = window.confirm(
			`Recalculate balances for ${number(memberCount)} member${memberCount === 1 ? "" : "s"}? This walks every account and may take a moment.`,
		);
		if (!ok) return;
		recalc.mutate(undefined, {
			onSuccess: (d) => setResult(d),
			onError:   (err) => setResult({ error: err?.message || "Could not recalculate." }),
		});
	};

	return (
		<div className="zc-flex zc-flex-wrap zc-items-center zc-gap-3">
			<Button variant="outline" onClick={onClick} loading={recalc.isPending}>
				Recalculate all balances
			</Button>
			{result ? <ResultPill result={result} /> : null}
		</div>
	);
}

function ResultPill({ result }) {
	if (result.error) {
		return (
			<span className="zc-rounded-md zc-border zc-border-rose-200 zc-bg-rose-50 zc-px-2.5 zc-py-1 zc-text-xs zc-text-rose-800">
				{result.error}
			</span>
		);
	}
	const { processed, drift_corrected, errors } = result;
	const tone = errors > 0
		? "zc-border-amber-200 zc-bg-amber-50 zc-text-amber-800"
		: drift_corrected > 0
			? "zc-border-sky-200 zc-bg-sky-50 zc-text-sky-800"
			: "zc-border-emerald-200 zc-bg-emerald-50 zc-text-emerald-800";

	return (
		<span className={`zc-rounded-md zc-border zc-px-2.5 zc-py-1 zc-text-xs ${tone}`}>
			Processed {number(processed)} · drift corrected {number(drift_corrected)}{errors > 0 ? ` · errors ${number(errors)}` : ""}
		</span>
	);
}
