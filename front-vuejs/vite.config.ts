import { fileURLToPath, URL } from "node:url";

import { defineConfig } from "vite";
import vue2 from "@vitejs/plugin-vue2";

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue2()],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ["vue", "vue-router", "bootstrap-vue", "axios"],
        },
      },
    },
  },
  server: {
    host: true,
    port: 5173,
    strictPort: true,
  },
  preview: {
    host: true,
    port: 4173,
  },
});
