import { existsSync } from 'node:fs';
import { defineConfig, devices } from '@playwright/test';

// Local secrets (E2E_USERNAME, E2E_PASSWORD) come from the gitignored .env.
// In CI the variables come from the job; the .env copied from .env.example is
// not read, its empty E2E_* values must not shadow them.
if (!process.env.CI && existsSync('.env')) process.loadEnvFile('.env');

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
    // No retries, in CI too: a flaky test must show up red, not pass on a rerun.
    retries: 0,
    workers: process.env.CI ? 2 : undefined,
    // In CI: annotations on the PR plus an HTML report uploaded as a job artifact.
    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never' }]]
        : 'html',
    use: {
        baseURL,
        // In CI the trace of a failed test goes into the artifact. Locally it stays off:
        // recording adds ~15% to every test, and some are close to the 30 s limit.
        trace: process.env.CI ? 'retain-on-failure' : 'off',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /.*\.setup\.ts/ },
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                // Lower bound of the NFR (1366-2560 px); the tree must work at the smallest screen.
                viewport: { width: 1366, height: 768 },
                storageState: 'playwright/.auth/user.json',
            },
            dependencies: ['setup'],
        },
    ],
    webServer: {
        // Locally: production-like build + php -S through Sail, own port and database `e2e`.
        // In CI: native PHP against the job's PostgreSQL service (build done by the workflow).
        command: process.env.CI
            ? 'scripts/e2e-server-ci.sh'
            : 'scripts/e2e-server.sh',
        env: { E2E_PORT: String(PORT) },
        url: baseURL,
        reuseExistingServer: !process.env.CI,
        // Lets scripts/e2e-server.sh stop its container; the default is SIGKILL.
        gracefulShutdown: { signal: 'SIGTERM', timeout: 20_000 },
        timeout: 300_000,
    },
});
