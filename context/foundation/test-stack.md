# Test stack

## E2E

<!-- Written by /10x-e2e-setup. Re-run it to change this section; other skills only read it. -->

- runner: Playwright Test, @playwright/test 1.63.0
- config: playwright.config.ts
- single-spec command: npx playwright test tests/e2e/<name>.spec.ts
- full-suite command: npx playwright test
- base URL: http://127.0.0.1:8100 (not localhost: the IPv6 lookup of localhost goes to the WSL DNS and can add ~10 s per request)
- port: 8100 (chosen by the user: dev app owns 8000 through Sail; detected default 8000, override with E2E_PORT)
- web server command: scripts/e2e-server.sh (sail npm run build, then a separate Sail container with its own database `e2e` serving the app via php -S on $E2E_PORT); reuseExistingServer outside CI
- CI: `.github/workflows/e2e.yml` on every PR and push to `main`; `playwright.config.ts` picks scripts/e2e-server-ci.sh when `CI` is set (native PHP 8.5 + the job's PostgreSQL 18 service, database `e2e`, `composer setup` builds first); no retries, 2 workers (also locally), trace on failure in CI only; HTML report + test-results uploaded as the `playwright-report` artifact. A stale `public/hot` (dev server stopped) makes local E2E pages blank — delete it
- auth setup project: setup (tests/e2e/auth.setup.ts), credentials from E2E_USERNAME / E2E_PASSWORD in .env locally, from the job env in CI (fixed test values; .env is not read when `CI` is set); the user is created in the `e2e` database by the server script
- storageState: playwright/.auth/user.json (gitignored)
- seed: tests/e2e/seed.spec.ts — protects auth-gate-roundtrip: a signed-out visitor to /persons is redirected to /login, and signing in lands on /persons
- browser CLI: playwright-cli, command skill at .claude/skills/playwright-cli/SKILL.md
- updated: 2026-10-22
