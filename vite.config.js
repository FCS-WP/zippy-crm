import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "node:path";

export default defineConfig({
	plugins: [react()],
	// `base: "./"` makes Vite emit relative URLs in the dynamic-import deps
	// table (`__vite__mapDeps`). Default `"/"` would try to fetch lazy chunks
	// from the site root (`/js/foo.js`), which is wrong because our bundle
	// lives under /wp-content/plugins/zippy-crm/assets/dist/js/. Without this,
	// any lazy import (e.g. ReportsPanel + Recharts) silently breaks because
	// the modulepreload links and import() URLs both 404.
	base: "./",
	resolve: {
		alias: {
			"@": path.resolve(__dirname, "assets/src"),
		},
	},
	build: {
		manifest: true,
		outDir: "assets/dist",
		emptyOutDir: true,
		rollupOptions: {
			input: {
				admin:    path.resolve(__dirname, "assets/src/js/admin/index.jsx"),
				account:  path.resolve(__dirname, "assets/src/js/account/index.jsx"),
				checkout: path.resolve(__dirname, "assets/src/js/checkout/index.jsx"),
			},
			output: {
				entryFileNames: "js/[name].js",
				chunkFileNames: "js/[name]-[hash].js",
				assetFileNames: ({ name }) =>
					name && name.endsWith(".css") ? "css/[name][extname]" : "assets/[name]-[hash][extname]",
			},
		},
	},
});
