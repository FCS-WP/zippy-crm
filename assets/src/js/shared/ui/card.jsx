import { cn } from "../cn.js";

export function Card({ className, ...props }) {
	return (
		<div
			className={cn(
				"zc-rounded-xl zc-border zc-border-zinc-200 zc-bg-white zc-shadow-sm",
				className,
			)}
			{...props}
		/>
	);
}

export function CardHeader({ className, ...props }) {
	return <div className={cn("zc-flex zc-flex-col zc-gap-1.5 zc-p-6", className)} {...props} />;
}

export function CardTitle({ className, ...props }) {
	return (
		<h3
			className={cn("zc-text-lg zc-font-semibold zc-leading-none zc-tracking-tight", className)}
			{...props}
		/>
	);
}

export function CardDescription({ className, ...props }) {
	return <p className={cn("zc-text-sm zc-text-zinc-500", className)} {...props} />;
}

export function CardContent({ className, ...props }) {
	return <div className={cn("zc-p-6 zc-pt-0", className)} {...props} />;
}

export function CardFooter({ className, ...props }) {
	return <div className={cn("zc-flex zc-items-center zc-p-6 zc-pt-0", className)} {...props} />;
}
