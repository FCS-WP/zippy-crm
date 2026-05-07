import { cn } from "../cn.js";

export function Skeleton({ className, ...props }) {
	return (
		<div
			className={cn("zc-animate-pulse zc-rounded-md zc-bg-zinc-200/70", className)}
			{...props}
		/>
	);
}
