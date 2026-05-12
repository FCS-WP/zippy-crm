/**
 * Animated collapse using the CSS `grid-template-rows: 0fr → 1fr` trick.
 * Modern browsers interpolate between the two values smoothly; older
 * browsers snap without animating (graceful degradation, content still
 * shown/hidden correctly).
 *
 * Why this and not Framer Motion / similar: we want zero bundle weight
 * and no extra runtime measuring. The grid trick is one CSS transition.
 */
export function CollapsePanel({ open, children }) {
	return (
		<div
			className={`zc-grid zc-transition-[grid-template-rows] zc-duration-200 zc-ease-out ${
				open ? "zc-grid-rows-[1fr]" : "zc-grid-rows-[0fr]"
			}`}
			aria-hidden={!open}
		>
			<div className="zc-overflow-hidden">{children}</div>
		</div>
	);
}
