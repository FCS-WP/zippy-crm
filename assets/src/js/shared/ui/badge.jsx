import { cn } from "../cn.js";

const variants = {
	default:  "zc-bg-zinc-900 zc-text-white",
	muted:    "zc-bg-zinc-100 zc-text-zinc-700",
	success:  "zc-bg-emerald-100 zc-text-emerald-800",
	warning:  "zc-bg-amber-100 zc-text-amber-800",
	danger:   "zc-bg-rose-100 zc-text-rose-800",
	info:     "zc-bg-sky-100 zc-text-sky-800",
	gold:     "zc-bg-yellow-100 zc-text-yellow-800",
	silver:   "zc-bg-zinc-200 zc-text-zinc-800",
	vip:      "zc-bg-fuchsia-100 zc-text-fuchsia-800",
};

export function Badge({ variant = "default", className, ...props }) {
	return (
		<span
			className={cn(
				"zc-inline-flex zc-items-center zc-rounded-full zc-px-2.5 zc-py-0.5 zc-text-xs zc-font-medium",
				variants[variant] ?? variants.default,
				className,
			)}
			{...props}
		/>
	);
}
