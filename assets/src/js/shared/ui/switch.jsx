import { cn } from "../cn.js";

/**
 * Toggle switch — uses a hidden checkbox so native form semantics + keyboard
 * focus + screen readers work without any extra ARIA work.
 */
export function Switch({ checked, onCheckedChange, disabled = false, id, className, ...props }) {
	return (
		<label
			className={cn(
				"zc-relative zc-inline-flex zc-h-6 zc-w-11 zc-shrink-0 zc-cursor-pointer zc-items-center zc-rounded-full zc-transition-colors",
				checked ? "zc-bg-zinc-900" : "zc-bg-zinc-300",
				disabled && "zc-cursor-not-allowed zc-opacity-50",
				className,
			)}
			htmlFor={id}
		>
			<input
				id={id}
				type="checkbox"
				role="switch"
				className="zc-sr-only"
				checked={checked}
				disabled={disabled}
				onChange={(e) => onCheckedChange?.(e.target.checked)}
				{...props}
			/>
			<span
				aria-hidden
				className={cn(
					"zc-pointer-events-none zc-inline-block zc-size-5 zc-transform zc-rounded-full zc-bg-white zc-shadow-md zc-ring-0 zc-transition-transform",
					checked ? "zc-translate-x-5" : "zc-translate-x-0.5",
				)}
			/>
		</label>
	);
}
