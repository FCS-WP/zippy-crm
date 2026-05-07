import { cn } from "../cn.js";

export function EmptyState({ icon: Icon, title, description, action, className }) {
	return (
		<div
			className={cn(
				"zc-flex zc-flex-col zc-items-center zc-justify-center zc-gap-2 zc-rounded-xl zc-border zc-border-dashed zc-border-zinc-200 zc-p-10 zc-text-center",
				className,
			)}
		>
			{Icon ? <Icon className="zc-size-8 zc-text-zinc-400" aria-hidden /> : null}
			<p className="zc-text-base zc-font-medium zc-text-zinc-900">{title}</p>
			{description ? (
				<p className="zc-max-w-sm zc-text-sm zc-text-zinc-500">{description}</p>
			) : null}
			{action ? <div className="zc-mt-2">{action}</div> : null}
		</div>
	);
}
