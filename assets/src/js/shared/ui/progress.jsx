import { cn } from "../cn.js";

export function Progress({ value = 0, className, indicatorClassName }) {
	const pct = Math.max(0, Math.min(100, Number(value) || 0));
	return (
		<div
			role="progressbar"
			aria-valuenow={pct}
			aria-valuemin={0}
			aria-valuemax={100}
			className={cn(
				"zc-relative zc-h-2 zc-w-full zc-overflow-hidden zc-rounded-full zc-bg-zinc-200",
				className,
			)}
		>
			<div
				className={cn("zc-h-full zc-bg-zinc-900 zc-transition-all", indicatorClassName)}
				style={{ width: `${pct}%` }}
			/>
		</div>
	);
}
