import { Switch } from "@/js/shared/ui/switch.jsx";

/**
 * One row of the preferences form. The whole row is clickable via the label
 * association — switch + label + description all activate together.
 */
export function ChannelToggle({ id, title, description, checked, onChange }) {
	return (
		<div className="zc-flex zc-items-start zc-justify-between zc-gap-4 zc-border-b zc-border-zinc-100 zc-py-4 last:zc-border-b-0">
			<label htmlFor={id} className="zc-min-w-0 zc-flex-1 zc-cursor-pointer">
				<span className="zc-block zc-text-sm zc-font-medium zc-text-zinc-900">
					{title}
				</span>
				<span className="zc-mt-0.5 zc-block zc-text-sm zc-text-zinc-500">
					{description}
				</span>
			</label>
			<Switch
				id={id}
				checked={checked}
				onCheckedChange={onChange}
			/>
		</div>
	);
}
