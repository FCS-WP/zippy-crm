import { cn } from "../cn.js";

const variants = {
	primary: "zc-bg-zinc-900 zc-text-white hover:zc-bg-zinc-800 disabled:zc-bg-zinc-300 disabled:zc-text-zinc-500",
	outline: "zc-border zc-border-zinc-300 zc-bg-white zc-text-zinc-900 hover:zc-bg-zinc-50 disabled:zc-text-zinc-400",
	ghost:   "zc-text-zinc-700 hover:zc-bg-zinc-100",
	danger:  "zc-bg-rose-600 zc-text-white hover:zc-bg-rose-700",
};

const sizes = {
	sm: "zc-h-8  zc-px-3 zc-text-xs",
	md: "zc-h-10 zc-px-4 zc-text-sm",
	lg: "zc-h-11 zc-px-6 zc-text-base",
};

export function Button({
	variant = "primary",
	size = "md",
	className,
	type = "button",
	disabled = false,
	loading = false,
	children,
	...props
}) {
	return (
		<button
			type={type}
			disabled={disabled || loading}
			className={cn(
				"zc-inline-flex zc-items-center zc-justify-center zc-gap-2 zc-rounded-md zc-font-medium zc-transition-colors zc-outline-none focus-visible:zc-ring-2 focus-visible:zc-ring-zinc-400 disabled:zc-cursor-not-allowed",
				variants[variant] ?? variants.primary,
				sizes[size]    ?? sizes.md,
				className,
			)}
			{...props}
		>
			{loading ? <Spinner /> : null}
			{children}
		</button>
	);
}

function Spinner() {
	return (
		<svg className="zc-size-4 zc-animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden>
			<circle cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="3" opacity="0.25" />
			<path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
		</svg>
	);
}
