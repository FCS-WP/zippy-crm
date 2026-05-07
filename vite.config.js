import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "node:path";

export default defineConfig({
	plugins: [react()],
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
				admin:   path.resolve(__dirname, "assets/src/js/admin/index.jsx"),
				account: path.resolve(__dirname, "assets/src/js/account/index.jsx"),
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
