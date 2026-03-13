import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    timeout: 120000,
    expect: { timeout: 30000 },
    fullyParallel: false,
    retries: 0,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: 'https://pl-opensocial.ddev.site:8493',
        ignoreHTTPSErrors: true,
        trace: 'on-first-retry',
        screenshot: 'on',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
