---
project: root-keeper
checked_at: 2026-10-23T07:38:57Z
health_status: critical-issues
context_type: brownfield
language_family: multi
stack_assessment_available: true
checks_run:
  - lockfile
  - dependency_audit
  - outdated_deps
  - test_runner
  - ci_cd
  - configuration
audit_findings:
  critical: 2
  high: 1
  moderate: 5
  low: 0
test_runner_detected: true
ci_provider: GitHub Actions
recommended_fixes: 7
---

# Health check — root-keeper

> Project: Laravel 13 + Inertia 3 + React 19 (TypeScript 7), PHP 8.5, PostgreSQL 18, run locally with Sail. Checked before the brownfield change in `context/foundation/prd.md` (M-3: import from the research repo). Replaces the 2026-10-04 check (see git history, commit `685ffa2`) — all seven of its fixes are done: league/commonmark patched (2.10.3), guardrail E2E specs written and running in CI, Node 24 in CI, `pnpm-workspace.yaml` removed, vite-plus 1.0, `CLAUDE.md` rule blocks pasted.

## Dependency Health

### Lockfile

```
Status: present (composer.lock, package-lock.json)
Package manager: composer + npm
```

### Security Audit

```
Tool: vendor/bin/sail composer audit --format json; vendor/bin/sail npm audit --json
Summary: 2 CRITICAL, 1 HIGH, 5 MODERATE, 0 LOW
Direct vs transitive: composer — no advisories, nothing abandoned; npm — every finding reaches the tree through two direct packages: concurrently (CRITICAL chain) and vite-plus (HIGH + all MODERATE)
```

All eight findings sit in local development tooling (the `composer dev` process runner and the Vite/Vitest dev and test servers), not in code the production build ships. They are real for a developer machine running `vp dev` or `vp test --ui`, and they keep every audit red.

#### CRITICAL findings

- **shell-quote** 1.8.4–1.10.0 (transitive, via concurrently) — GHSA-pqg4-j6r4-53mv: `quote()` command injection via a line terminator in a token after a `{ comment }` token (CVSS 8.1; npm rates it critical). Fix: update concurrently.
- **concurrently** 10.0.5 (direct, in `dependencies`, range `^10.0.3`) — affected through shell-quote. Fix: update to 10.0.6.

#### HIGH findings

- **vite-plus** 1.0.0 (direct, pinned exactly `"1.0.0"` in `devDependencies`) — affected range `<=1.2.0`: bundled Vite (GHSA-9jrq-w75r-8gcw cross-origin script execution through custom middleware; GHSA-rq7h-c2jc-7f22 files outside `server.fs.allow`; GHSA-vfpm-58rq-9qcg `?vite-wasm-instance` leaks 4 bytes), bundled launch-editor (GHSA-r825-w8mm-5pxw, GHSA-698v-4r3x-3vcv, GHSA-587h-7wmj-h5pm), and the Vitest findings below. Fix: update to 1.3.0+ (1.3.0 bundles vite-plus-core 1.3.0 and Vitest 5.0.5; 1.4.0 was published today).

MODERATE (all transitive through vite-plus, all fixed in Vitest 5.0.5):
- **vitest** 5.0.1 — pinned by `overrides.vitest` in `package.json`; carries the advisories of its sub-packages.
- **@vitest/mocker** — GHSA-qxg6-89mg-rqv8 code injection via unauthenticated manual mock registration (4.7); GHSA-xp26-3454-mjgp Windows backslash traversal (5.9).
- **@vitest/ui** — GHSA-p458-7748-mcp3 `server.fs.deny` misapplied, serving `.env`, private keys, `.git` (6.5).
- **@vitest/browser** — same GHSA-p458-7748-mcp3.
- **@vitest/browser-preview** — via @vitest/browser.

Note: the `overrides.vitest: "5.0.1"` pin forces the vulnerable Vitest even after vite-plus is bumped — it has to move with it (see `CLAUDE.md` "Frontend toolchain").

### Outdated Dependencies

```
Packages with major version gaps: 1 (npm); composer: none
```

- **@types/node**: 24.19.1 → 26.6.5 (2 majors). Intentional — types track the Node 24 runtime (CI and Sail both run Node 24).

Minor/patch updates available (none urgent): laravel/framework 13.34.0 → 13.38.0, laravel/fortify 1.40.0 → 1.41.0, larastan/larastan 3.12.2 → 3.13.1, phpunit/phpunit 13.3.6 → 13.4.3, pestphp/pest 5.3.0 → 5.3.3, inertiajs/inertia-laravel 3.5.1 → 3.5.2, laravel/wayfinder 0.1.21 → 0.1.23, laravel/boost 2.10.1 → 2.10.3, laravel/sail 1.68.0 → 1.69.0; @inertiajs/react and @inertiajs/vite 3.8.0 → 3.9.1, @playwright/test 1.63.0 → 1.64.1, React 19.3.0 → 19.3.1, lucide-react 1.52.0 → 1.61.0, the Radix packages (minor bumps), @laravel/vite-plugin-wayfinder 0.1.10 → 0.1.11. Windows-only optional binaries show as missing — expected on Linux/WSL. `@rollup/rollup-*` 4.9.5 are exact pins in `optionalDependencies` (a starter leftover) and show 4.65.0 as latest; not used by the Rolldown-based build.

## Test Suite

```
Test runner: Pest 5.3 (PHPUnit 13.3) via `vendor/bin/sail artisan test`; Vitest 5.0 via `npm run test:js` (`vp test`); Playwright 1.63 (E2E)
Tests found: 1036 Pest tests; 408 Vitest tests; 44 Playwright tests in 29 files (incl. auth setup)
Test execution: failing locally — Pest 1033/1036 (2 failed, 1 error, 5908 assertions, ~287 s, PostgreSQL 18); Vitest 408/408 (~5.6 s); Playwright tree-fill-window.spec.ts 12/12 locally (--repeat-each=6)
```

```
Configuration: phpunit.xml; vite.config.ts (`vp test`); playwright.config.ts + scripts/e2e-server.sh / e2e-server-ci.sh (see context/foundation/test-stack.md)
Framework: Pest 5.3.0 + pest-plugin-laravel, PHPUnit 13.3.6; Vitest 5.0.1; @playwright/test 1.63.0
```

**The three local Pest failures are an environment leak, not a regression.** All three are in `tests/Backup/` (`BackupContentsTest` ×2: archive entries read as `null`; `RestoreCommandTest` "a fresh backup dump carries no ownership statements": dump is not iterable). The local `.env` sets a non-empty `BACKUP_ARCHIVE_PASSWORD`; `config/backup.php` turns on archive encryption when it is set, and `phpunit.xml` does not override it, so `BackupTestCase::readArchiveEntry()` reads an encrypted zip without a password. Re-running `tests/Backup` with `BACKUP_ARCHIVE_PASSWORD=` in the environment: 13/13 pass. CI has no `.env` password, which is why `tests` is green there. See Recommended Fixes #3.

Other local gates, all passing: `composer types:check` (Larastan level 7, 0 errors), `npm run types:check` (tsc), `npm run check` (Oxfmt: 261 files formatted; Oxlint: 274 files, no warnings), `composer lint:check` (Pint).

## CI/CD

```
Provider: GitHub Actions
Configuration: .github/workflows/lint.yml, static-analysis.yml, tests.yml, e2e.yml (+ .github/dependabot.yml, github-actions ecosystem only)
```

| Stage      | Status | Notes                                                                                       |
|------------|--------|---------------------------------------------------------------------------------------------|
| Lint       | ✓      | Pint (`composer lint:check`) + Vite+ (`npm run check`); Vitest (`npm run test:js`) runs here too |
| Test       | ✓      | Pest on `postgres:18-alpine` with `pg_dump` 18; Playwright E2E (`e2e.yml`, 2 workers, no retries) |
| Build      | ✓      | `npm run build` inside `composer setup` (tests, e2e)                                         |
| Type check | ✓      | Larastan level 7 + `tsc --noEmit`                                                            |
| Security   | ✗      | No `composer audit` / `npm audit` step; Dependabot watches GitHub Actions only               |

All four workflows use Node 24 and PHP 8.5. Recent runs: `develop` 1038658 and `drop-legacy-columns` (×2) — all four green. `main` 6d04d65 (the merge of #54, today 04:21 UTC) — lint, static-analysis, tests green; **e2e red**: 43 passed, 1 failed — `tree-fill-window.spec.ts:139` ("at 1920x1080 the pair card is the tallest…"), `expect(child).toBeVisible()` timed out after 5 s, the "Dziecko" card was not found. `main` 6d04d65 and `develop` 1038658 have identical trees and e2e passed on `develop` four minutes earlier, and the spec passed 12/12 locally — the test is flaky in CI. See Recommended Fixes #4.

The missing security stage has a concrete cost again: the concurrently and vite-plus advisories (fixes published within the last week) are visible only to a manual `npm audit`.

All CI jobs install Composer packages **with** dev dependencies; nothing checks that `app/` uses only `require` packages (stack-assessment gap 2). See Recommended Fixes #5.

## Configuration

### High severity

No high-severity gaps. `.gitignore` present (incl. `/storage/gen-tree-import`); `tsconfig.json` `strict: true`; lint/format config present (`vite.config.ts` `lint`/`fmt`, `pint.json`); `phpstan.neon` level 7 without baseline.

### Medium severity

- **`phpunit.xml` does not isolate the backup password** — a developer `.env` with `BACKUP_ARCHIVE_PASSWORD` turns three backup tests red. An agent running the suite after an M-3 change sees failures unrelated to its work, and may "fix" the backup code or the assertions. Fix: Recommended Fixes #3.

### Low severity

- **`.env.example` does not list `BACKUP_ARCHIVE_PASSWORD`** — the variable that switches archive encryption on is undocumented. Fix: Recommended Fixes #3.
- **`.ai/rules` referenced but absent** — the Boost block in `CLAUDE.md` points agents to `.ai/rules/index.md`; the instruction is conditional ("If `.ai/rules` does not exist, continue without it"), so it costs only a lookup. No fix needed until the first rule is recorded.

Present and fine: `.editorconfig`, `.env.example` (pgsql, Sail defaults), `.gitignore`, `pint.json`, `CLAUDE.md`.

**Backup scope for the import (stack-assessment hand-off).** `config/backup.php` includes the whole database and `storage_path('app')`; `bootstrap/app.php` schedules `backup:run` daily in production, plus `backup:clean` and `backup:monitor`. Scans the import copies into `storage/app` are therefore covered within 24 h. The pre-write snapshot the PRD requires before an import (`backup:run --only-db`) is not built yet — it belongs to the import slices (`CLAUDE.md` "Research data").

## Stack Assessment Cross-Reference

```
Stack assessment: context/foundation/stack-assessment.md
Agent readiness (from stack-assess): ready-with-compensation
```

| Quality Gate Gap / Hand-off                                   | Health-Check Finding                                                                                  | Status       |
|---------------------------------------------------------------|-------------------------------------------------------------------------------------------------------|--------------|
| 1. vite-plus not in training data                             | `CLAUDE.md` Vite+ block (1.0.0) present; `vp check` green. But 1.0.0 is pinned exactly and is now under a HIGH advisory; the bump procedure (alias + `vitest` override) is in `CLAUDE.md` | Reinforced — the compensation rule is about to be exercised (#2) |
| 2. YAML parser only in dev                                    | `composer why symfony/yaml`: only `laravel/sail`, `laravel/roster` (dev). Not used in `app/` yet. CI installs dev everywhere; no `--no-dev` check | Open — decide in `/10x-plan` (#5) |
| 3. Import writes outside HTTP                                 | "Research repo import" rule block present in `CLAUDE.md`                                               | Mitigated    |
| 4. Real research data + pre-import backup                     | "Research data" rule present; `.gitignore` has `/storage/gen-tree-import`; `tests/Fixtures/` holds only two images. Daily backup covers `storage/app`; pre-import snapshot not built yet | Mitigated (snapshot is slice work) |
| 5. Date qualifier is a data move                              | Line present in "Migrations that move existing data"                                                   | Mitigated    |
| Hand-off: `.ai/rules/index.md` referenced, `.ai/` absent      | Still absent; the reference is conditional                                                            | Accepted     |
| Hand-off: dev/prod drift check                                | No CI step; three local test failures already show that the test env picks up whatever the developer's `.env` holds | Reinforced (#3, #5) |

## Recommended Fixes

### Fix before agent work (Category A)

### 1. Update concurrently (2 CRITICAL)

**Impact**: Every `npm audit` stays red with "critical", which trains agents and humans to ignore it — and a future CI audit step cannot be turned on while it is red. Exposure is local (the `composer dev` process runner), but the fix is a patch release.
**Severity**: critical
**Effort**: quick (< 5 min)
**Fix**:

```bash
vendor/bin/sail npm update concurrently
vendor/bin/sail npm audit
vendor/bin/sail composer dev   # smoke: all processes start, then Ctrl+C
```

Expected: concurrently 10.0.6, shell-quote outside 1.8.4–1.10.0, no critical findings.

### 2. Bump vite-plus to 1.3+ together with its alias and the Vitest pin (1 HIGH, 5 MODERATE)

**Impact**: the dev and test servers every slice uses (`vp dev`, `vp test`) carry file-disclosure and code-injection advisories. Bumping only `vite-plus` is not enough: the `vite` alias and `overrides.vitest: "5.0.1"` would keep the old core and the vulnerable Vitest — exactly the trap `CLAUDE.md` describes.
**Severity**: high
**Effort**: moderate (15–30 min)
**Fix**: on its own branch, before the first M-3 slice:

```bash
# in package.json: "vite-plus": "1.3.0" (or 1.4.0 once it has settled — published 2026-10-23),
#                  "vite": "npm:@voidzero-dev/vite-plus-core@1.3.0",
#                  overrides.vitest: "5.0.5"
vendor/bin/sail npm install
vendor/bin/sail npx vp toolchain        # confirm bundled vite/vitest versions match the pins
vendor/bin/sail npm audit
vendor/bin/sail npm run check && vendor/bin/sail npm run types:check && vendor/bin/sail npm run test:js && vendor/bin/sail npm run build
```

Read `node_modules/vite-plus/AGENTS.md` and `docs/guide/` for 1.1–1.3 changes first; do not run `vp migrate`. Then update "vite-plus 1.0.0" in the `CLAUDE.md` Vite+ heading and the versions line.

### 3. Isolate the backup tests from the developer's `.env`

**Impact**: three Pest tests fail locally on a clean `develop` (1033/1036). An agent finishing an M-3 slice runs the suite, sees red tests it did not touch, and either stops or "fixes" backup code that is correct. Category A because the agent's main verification signal is unreliable on this machine.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**:

```xml
<!-- phpunit.xml, inside <php> -->
<env name="BACKUP_ARCHIVE_PASSWORD" value="" force="true"/>
```

```dotenv
# .env.example — document the switch; empty = unencrypted archives
BACKUP_ARCHIVE_PASSWORD=
```

Verify: `vendor/bin/sail artisan test --compact tests/Backup` → 13/13, then the full suite. The encrypted-archive test sets its own password, so it keeps covering encryption.

### 4. Find out why `tree-fill-window` at 1920x1080 is flaky in CI

**Impact**: `main` is red on e2e for a commit identical to a green `develop`. With `retries: 0` (deliberate), a flaky spec makes every red e2e run ambiguous — the agent cannot tell its regression from noise, and the M-3 slices that touch the timeline and forms will run this suite.
**Severity**: medium
**Effort**: moderate (15–30 min)
**Fix**: diagnose from the trace, do not loosen the assertion:

```bash
gh run download 39899868644 -n playwright-report -D /tmp/e2e-main
npx playwright show-trace /tmp/e2e-main/test-results/tree-fill-window-*-chromium/trace.zip
```

The failure is before the layout assertions: the "Dziecko" card never appeared within 5 s after `createFullFamily` (eleven API writes) and `gotoTree`. Check in the trace whether the child was created and whether the tree rendered before or after the last write. If it is a missing wait on the setup, fix the setup; if the app renders a stale tree, that is a real bug. Debugging failing tests is Lesson 5's topic — record the finding there.

### 5. Close the dev/prod dependency gap before the YAML parser lands

**Impact**: M-3's data blocks need a YAML parser; `symfony/yaml` is installed only through dev packages. Locally and in CI the import works; on production (`composer install --no-dev`) it fails with a missing class, and nothing in the pipeline catches it.
**Severity**: medium
**Effort**: moderate (15–30 min)
**Fix**: decide in `/10x-plan` for the first import slice: add `symfony/yaml` to `require` (`vendor/bin/sail composer require symfony/yaml` — a new direct dependency, needs your approval), and add a CI step that proves it, e.g. in `tests.yml` after the Pest run:

```bash
composer install --no-dev --no-interaction --prefer-dist
php -r "require 'vendor/autoload.php'; exit(class_exists(Symfony\Component\Yaml\Yaml::class) ? 0 : 1);"
```

(or, once the import command exists, run it against `tests/Fixtures/research-repo/` in dry-run mode on the `--no-dev` install).

### 6. Apply pending minor updates — outside feature work

**Impact**: keeps the `CLAUDE.md` versions block accurate and shrinks the next upgrade. Nothing here is a security fix.
**Severity**: low
**Effort**: moderate (15–30 min)
**Fix**: on a separate branch, after #1–#2:

```bash
vendor/bin/sail composer update -W
vendor/bin/sail npm update
vendor/bin/sail artisan test --compact && vendor/bin/sail composer types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run test:js
```

If @playwright/test moves to 1.64, the CI cache key follows `package-lock.json` automatically; update "Playwright 1.63" in `CLAUDE.md`.

### Addressed in upcoming lessons (Category B)

### CI security scanning

**Lesson**: [Sprint Zero z Agentem: infrastruktura, walking skeleton i pierwszy deploy (M1L5)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l5)
**What you'll do there**: harden the pipeline — add `composer audit` and `npm audit --audit-level=high` steps (they will pass once #1–#2 are done), and add `composer` and `npm` ecosystems to `.github/dependabot.yml` (it watches GitHub Actions only). This is the second check in a row where an advisory was found only by hand.

Agent instruction files are not a gap: `CLAUDE.md` carries the project rules, including every block the stack assessment recommended for M-3.

## Summary

Health status: critical-issues

The status comes from npm rating two advisories critical. Both are in the `concurrently` dev-process runner, exposure is local only, and a patch release fixes them in minutes. The rest is solid: Larastan level 7, tsc, Oxlint/Oxfmt and Pint are clean; 1036 Pest, 408 Vitest and 44 Playwright tests exist; four CI workflows cover lint, types, Pest and E2E; and every fix from the previous check is done. Before M-3 work, three things erode the agent's feedback loop. The dev toolchain sits under a HIGH advisory whose fix needs the alias and Vitest pin moved together. Three backup tests fail locally because the test env reads the developer's backup password. And one E2E spec is flaky in CI and has already turned `main` red.

Next step: update concurrently (#1), bump vite-plus with its pins (#2), isolate the backup password in `phpunit.xml` (#3), diagnose the flaky spec (#4) — then proceed to agent onboarding / `/10x-plan` for the first M-3 slice, deciding `symfony/yaml` there (#5).
