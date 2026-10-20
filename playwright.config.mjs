// Browser tests for Sibol. They run against the app served by XAMPP (Apache + MySQL must be running)
// and reset the local demo database first (see tests/e2e/global-setup.mjs).
//
//   npx playwright test                 all tests, desktop and phone
//   npx playwright test --project=desktop
//
// SIBOL_URL     base URL (default http://localhost/BEST/public)
// SIBOL_BROWSER installed browser channel to use (default msedge; "chromium" uses Playwright's own build)
import { defineConfig, devices } from '@playwright/test';

const channel = process.env.SIBOL_BROWSER || 'msedge';
const browser = channel === 'chromium' ? {} : { channel };

export default defineConfig({
  testDir: 'tests/e2e',
  globalSetup: './tests/e2e/global-setup.mjs',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  outputDir: 'test-results',
  use: {
    baseURL: (process.env.SIBOL_URL || 'http://localhost/BEST/public') + '/',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    locale: 'en-PH',
    timezoneId: 'Asia/Manila',
  },
  projects: [
    { name: 'desktop', testIgnore: /responsive\.spec/, use: { ...devices['Desktop Chrome'], ...browser, viewport: { width: 1440, height: 900 } } },
    { name: 'phone', testMatch: /responsive\.spec/, use: { ...browser, viewport: { width: 390, height: 844 }, isMobile: false, hasTouch: true } },
  ],
});
