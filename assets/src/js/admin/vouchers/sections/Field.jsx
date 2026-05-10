/**
 * Form field shell — label + input slot + optional hint.
 *
 * Pass `required` to render a red asterisk next to the label so admins can
 * tell which fields the server will reject if blank. (Server enforces:
 * code, title, discount_type, discount_value.)
 */
export function Field({ label, hint, required = false, children }) {
	return (
		<label className="zc-block zc-space-y-1">
			<span className="zc-flex zc-items-center zc-gap-1 zc-text-sm zc-font-medium zc-text-zinc-800">
				{label}
				{required ? (
					<span aria-label="required" title="Required" className="zc-text-rose-600">*</span>
				) : null}
			</span>
			{children}
			{hint ? <span className="zc-block zc-text-xs zc-text-zinc-500">{hint}</span> : null}
		</label>
	);
}
