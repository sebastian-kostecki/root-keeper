import { existsSync } from 'node:fs';
import { defineConfig, devices } from '@playwright/test';

// Local secrets (E2E_USERNAME, E2E_PASSWORD) come from the gitignored .env.
// In CI the file is absent and the variables come from the job.
if (existsSync('.env')) process.loadEnvFile('.env');

// 8100 is the chosen E2E port (the dev app owns 8000 through Sail); E2E_PORT overrides it.
const PORT = Number(process.env.E2E_PORT ?? 8100);
// 127.0.0.1, not localhost: /etc/hosts maps localhost only to IPv4, so the
// Playwright client's IPv6 lookup of localhost goes to the WSL DNS and adds
// ~10 s to every request when that DNS is slow.
const baseURL = `http://127.0.0.1:${PORT}`;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 1 : undefined,
    reporter: 'html',
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /.*\.setup\.ts/ },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'playwright/.auth/user.json',
            },
            dependencies: ['setup'],
        },
    ],
    webServer: {
        // Production-like build + artisan serve through Sail, own port and database `e2e`.
        command: 'scripts/e2e-server.sh',
        env: { E2E_PORT: String(PORT) },
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        // Lets scripts/e2e-server.sh stop its container; the default is SIGKILL.
        gracefulShutdown: { signal: 'SIGTERM', timeout: 20_000 },
        timeout: 300_000,
    },
});
