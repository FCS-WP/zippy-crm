import { clsx } from "clsx";
import { extendTailwindMerge } from "tailwind-merge";

// Our Tailwind config uses the `zc-` prefix (see tailwind.config.js). Stock
// twMerge doesn't know about that prefix, so it leaves conflicting classes
// like `zc-p-6 zc-p-4` both in the output — the second one only wins by CSS
// source order, which fails for utilities like `zc-pt-0` overriding `zc-p-4`.
// extendTailwindMerge with `prefix` teaches it the same shorthand.
const twMerge = extendTailwindMerge({ prefix: "zc-" });

export function cn(...inputs) {
	return twMerge(clsx(inputs));
}
