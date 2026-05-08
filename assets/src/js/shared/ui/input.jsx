import { cn } from "../cn.js";

export function Input({ className, ...props }) {
	return (
		<input
			className={cn(
				"zc-h-10 zc-w-full zc-rounded-md zc-border zc-border-zinc-300 zc-bg-white zc-px-3 zc-text-sm zc-text-zinc-900 zc-outline-none zc-transition-colors placeholder:zc-text-zinc-400 focus:zc-border-zinc-500 focus:zc-ring-2 focus:zc-ring-zinc-200 disabled:zc-bg-zinc-100",
				className,
			)}
			{...props}
		/>
	);
}
