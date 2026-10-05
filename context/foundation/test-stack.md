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
- auth setup project: setup (tests/e2e/auth.setup.ts), credentials from E2E_USERNAME / E2E_PASSWORD in .env (the user is created in the `e2e` database by scripts/e2e-server.sh)
- storageState: playwright/.auth/user.json (gitignored)
- seed: tests/e2e/seed.spec.ts — protects auth-gate-roundtrip: a signed-out visitor to /persons is redirected to /login, and signing in lands on /persons
- browser CLI: playwright-cli, command skill at .claude/skills/playwright-cli/SKILL.md
- known cleanup exception: tests/e2e/source-panel-parish.spec.ts leaves a parish and a locality (suffix-unique) in the `e2e` database per run — there is no delete route for them until S-11 (places-merge-and-delete); the source it creates is removed by the suffix sweep
- updated: 2026-10-05
