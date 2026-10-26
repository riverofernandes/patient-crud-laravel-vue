import { defineConfig, devices } from "@playwright/test";
import { BASE_URL } from "./support/env";

const isCI = !!process.env.CI;


export default defineConfig({
  testDir: "./tests",
  timeout: 30_000,
  expect: { timeout: 7_000 },
  fullyParallel: true,
  forbidOnly: isCI,
  retries: isCI ? 2 : 0,
  // The API rate limit is shared by the browser and the API helper. A full run sends ~425 requests
  // in under a minute, above the default 300/min: run the stack with API_RATE_LIMIT=1000 (as CI
  // does) and keep new tests lean (create only the patients they need).
  workers: 2,
  reporter: isCI
    ? [["list"], ["html", { open: "never" }], ["github"]]
    : [["list"], ["html", { open: "never" }]],
  use: {
    baseURL: BASE_URL,
    locale: "pt-BR",
    // Negative UTC offset: catches "off-by-one day" bugs from new Date("YYYY-MM-DD").
    timezoneId: "America/Sao_Paulo",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
    video: "off",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
