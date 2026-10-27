/// <reference types="vitest" />
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
  css: {
    preprocessorOptions: {
      scss: {
        // Bootstrap 4 (required with BootstrapVue 2) relies on @import and old
        // Sass built-ins, and Vite 3 uses the legacy JS API. None of these
        // warnings is actionable without leaving Bootstrap 4, so keep builds quiet.
        quietDeps: true,
        silenceDeprecations: [
          "import",
          "legacy-js-api",
          "global-builtin",
          "color-functions",
          "if-function",
          "abs-percent",
        ],
      },
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
  test: {
    coverage: {
      provider: "istanbul",
      reporter: ["text", "html", "lcov"],
      reportsDirectory: "coverage",
      // Every source file counts, not only the ones a test happens to import.
      all: true,
      include: ["src/**/*.{ts,vue}"],
      exclude: [
        "src/**/__tests__/**",
        "src/test/**",
        "src/main.ts",
        "src/router/**",
        "src/**/*.d.ts",
      ],
      // Set slightly below the measured values: fails on regressions.
      // Measured (Sprint 5): statements 87.3, branches 81.6, functions 88.3, lines 87.4.
      statements: 86,
      branches: 80,
      functions: 87,
      lines: 86,
    },
  },
});
