---
project: root-keeper
checked_at: 2026-09-18T07:27:23Z
health_status: healthy
context_type: brownfield
language_family: multi
stack_assessment_available: false
checks_run:
  - lockfile
  - dependency_audit
  - outdated_deps
  - test_runner
  - ci_cd
  - configuration
audit_findings:
  critical: 0
  high: 0
  moderate: 0
  low: 1
test_runner_detected: true
ci_provider: GitHub Actions
recommended_fixes: 10
---

# Health check — root-keeper

> Project: Laravel 13 + Inertia 3 + React 19 (TypeScript) starter kit, run locally with Sail (PHP 8.5, PostgreSQL 18).
> The PRD is marked `context_type: greenfield` — the project was scaffolded by `/10x-bootstrapper` two days ago. This check treats the scaffold as an existing codebase to confirm its baseline before agent-assisted work starts.

## Dependency Health

### Lockfile

```
Status: present (composer.lock, package-lock.json)
Package manager: composer + npm
```

Both ecosystems are pinned. Minor ambiguity: `pnpm-workspace.yaml` (shipped by the starter) sits next to `package-lock.json`, which suggests pnpm to tools and agents while the project uses npm.

### Security Audit

```
Tool: composer audit --format json; npm audit --json
Summary: 0 CRITICAL, 0 HIGH, 0 MODERATE, 1 LOW
Direct vs transitive: composer — no advisories, no abandoned packages; npm — 0 direct, 1 transitive
```

LOW findings:

- **@babel/core** 8.0.0-rc.4 (transitive, via `@rolldown/plugin-babel` / `babel-plugin-react-compiler`) — Arbitrary File Read via sourceMappingURL comment. A fix is available (move to a patched 8.x release); build-time tooling only.

### Outdated Dependencies

```
Packages with major version gaps: 4 (plus 1 pre-1.0 minor gap)
```

- **typescript**: 5.9.3 → 7.0.2 (2 major versions behind). TS 7 is the native compiler port — upgrade deliberately, not as a drive-by.
- **@types/node**: 22.20.3 → 26.6.1 (4 major versions behind). Should track the Node runtime (CI uses Node 22, local shell uses Node 24) rather than "latest".
- **lucide-react**: 0.475.0 → 1.47.0 (1 major). Icon renames likely.
- **phpunit/phpunit**: 12.5.35 → 13.3.4 (1 major).
- **@laravel/passkeys**: 0.2.0 → 0.4.0 (pre-1.0, minor bumps may break).

The Windows-only optional binaries (`@rollup/rollup-win32-x64-msvc` etc.) show as not installed — expected on Linux/WSL.

## Test Suite

```
Test runner: PHPUnit 12.5 (via `php artisan test` / `composer test`)
Tests found: 39 tests (Unit + Feature: auth, dashboard, settings)
Test execution: passing (39/39, 136 assertions, ~3 s) against in-memory SQLite
```

```
Configuration: phpunit.xml
Framework: PHPUnit 12.5.35, Laravel testing helpers, Mockery
```

Additional local gates, all passing: `phpstan analyse` (Larastan, level 7) — 0 errors; `pint --test` — clean; `tsc --noEmit` — clean.

Caveats:

- **Plain `php artisan test` hangs on the host.** `.env` points at `DB_HOST=pgsql`, which only resolves inside the Sail network. With Sail stopped, the run hung until killed after 100 s instead of failing fast. Tests must run through `./vendor/bin/sail test` (containers up) or with an SQLite override.
- **No frontend tests.** The React/TSX side has no Vitest or Playwright — only type-checking and lint.

## CI/CD

```
Provider: GitHub Actions
Configuration: .github/workflows/tests.yml (+ .github/dependabot.yml)
```

| Stage      | Status | Notes                                                                 |
|------------|--------|-----------------------------------------------------------------------|
| Lint       | ✓      | `vp check` (oxlint + formatter) and `pint --test`, via `composer ci:check` |
| Test       | ✓      | PHPUnit via `composer test`                                            |
| Build      | ✓      | `npm run build` inside `composer setup`                                |
| Type check | ✓      | `tsc --noEmit` + PHPStan level 7                                       |
| Security   | ✗      | Dependabot covers GitHub Actions only; no `composer audit` / `npm audit` step, no npm/composer ecosystems in Dependabot |

Issues found:

- **CI would fail right now.** `vp check` reports formatting issues in 6 files: `.10x-cli.json`, `skills-lock.json` and four files under `context/`. These are course/tooling files the formatter was never meant to cover.
- **Push trigger targets `main`, but the only branch is `master`** (and no remote is configured yet). Pushes to `master` won't run CI; only pull requests will.
- **CI tests run on SQLite, dev runs on PostgreSQL.** `composer setup` copies `.env.example` (`DB_CONNECTION=sqlite`), so CI never exercises the Postgres driver. Postgres-specific behavior (JSON columns, case-sensitive `LIKE`, constraints) can pass in CI and fail in dev/prod.
- CI pins PHP 8.3; Sail runs PHP 8.5. Fine given `php: ^8.3`, but worth knowing.

## Configuration

### High severity

No high-severity gaps. `.gitignore` is present and `tsconfig.json` has `strict: true`.

### Medium severity

- **`vite.config.ts` → `fmt.ignorePatterns`** — the formatter checks `context/**`, `.10x-cli.json` and `skills-lock.json`, which fails `vp check` and therefore CI. Fix: add those paths to `fmt.ignorePatterns` (and `lint.ignorePatterns` if needed).
- **`.env.example`** — documents `DB_CONNECTION=sqlite` with MySQL-style commented defaults, while the real `.env` uses `pgsql` via Sail. New clones (and agents reading `.env.example`) get the wrong database picture. Fix: mirror the Sail Postgres block (`DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_PORT=5432`, `DB_DATABASE=root_keeper`, `DB_USERNAME=sail`, `DB_PASSWORD=password`).

### Low severity

- **`pnpm-workspace.yaml`** — suggests pnpm while the project uses npm (`package-lock.json`). Fix: delete it, or commit to pnpm and drop `package-lock.json`.
- **`.gitignore` ignores `/CLAUDE.md`, `/AGENTS.md`, `/.claude`** — the starter's defaults. `CLAUDE.md` is already tracked so it still commits, but a future `AGENTS.md` or shared `.claude/settings.json` would be silently ignored. Revisit during agent onboarding.

Present and fine: `.editorconfig`, `.env.example`, `pint.json`, `phpstan.neon`, formatter + linter via vite-plus (`vite.config.ts` `lint`/`fmt`), `tsconfig.json` strict.

## Stack Assessment Cross-Reference

```
No stack-assessment.md found. Run /10x-stack-assess for quality-gate analysis.
```

(Expected for this project: it came through the greenfield chain, and `context/foundation/tech-stack.md` records `self_check_answers` all true, with `quality_override: true`.)

## Recommended Fixes

### Fix before agent work (Category A)

### 1. Make the test command work without guessing the environment

**Impact**: The agent's main feedback loop is "change → run tests". Plain `php artisan test` hangs on the host, so an agent waits, times out, and may conclude tests are broken.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**:

```bash
./vendor/bin/sail up -d      # start PHP + PostgreSQL containers
./vendor/bin/sail test       # tests against the real Postgres 'testing' DB
```

Then document this as *the* test command in the agent instruction file during onboarding. Optional fast path with no containers: `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test`.

### 2. Unblock CI: exclude course/tooling files from the formatter

**Impact**: `composer ci:check` fails on formatting of files that aren't app code, so every agent PR goes red for unrelated reasons, and agents may "fix" it by reformatting `context/` docs.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**: in `vite.config.ts`, extend `fmt.ignorePatterns`:

```ts
ignorePatterns: [
    '.github/**',
    'composer.json',
    'resources/js/components/ui/*',
    'resources/views/mail/*',
    'context/**',
    '.10x-cli.json',
    'skills-lock.json',
],
```

Verify with `npx vp check`.

### 3. Align `.env.example` with the Sail/PostgreSQL setup

**Impact**: Agents and fresh clones read `.env.example` as the source of truth; it currently says SQLite.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**: replace the `DB_*` block in `.env.example` with the Postgres values from your `.env` (no secrets beyond Sail defaults) — see the Configuration section.

### 4. Upgrade the transitive `@babel/core` release candidate

**Impact**: Low-severity, build-time only; clearing it keeps `npm audit` quiet so real alerts stand out.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**:

```bash
npm audit fix && npm audit
```

### 5. Pin `@types/node` to the Node runtime you target

**Impact**: 22.x types vs Node 24 locally (22 in CI) — mismatched types let agents use or reject APIs incorrectly.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**: pick one Node version (e.g. 24 LTS), set it in CI (`node-version: '24'`) and `npm i -D @types/node@24`.

### 6. Plan major upgrades deliberately (TypeScript 7, PHPUnit 13, lucide-react 1.x)

**Impact**: Not urgent; doing them mid-feature makes agent diffs noisy and breakages hard to attribute.
**Severity**: low
**Effort**: moderate (15–30 min each)
**Fix**: one dedicated change per upgrade, e.g. `npm i lucide-react@latest && npm run types:check && npm run build`; `composer require --dev phpunit/phpunit:^13 -W && ./vendor/bin/sail test`. Hold TypeScript 7 until vite-plus/typescript-eslint tooling officially supports it.

### 7. Remove the package-manager ambiguity

**Impact**: Agents may run `pnpm install` because they see `pnpm-workspace.yaml`, producing a second lockfile.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**: `git rm pnpm-workspace.yaml` (you use npm), or switch fully to pnpm and remove `package-lock.json`.

### 8. Add frontend tests when the first real UI feature lands

**Impact**: React components are only type-checked; agents can't verify UI behavior.
**Severity**: low
**Effort**: moderate (15–30 min)
**Fix**: `npm i -D vitest @testing-library/react jsdom`, add `"test": "vitest run"` to `package.json`, and wire it into `composer ci:check`. Browser-level flows can use Laravel's feature tests or Playwright later.

### Addressed in upcoming lessons (Category B)

### Project-specific agent instructions (CLAUDE.md / AGENTS.md)

**Lesson**: [Agent Onboarding: Agents.md, AI Rules i feedback loops (M1L4)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l4)
**What you'll do there**: Write instruction files with the project's commands and conventions. Today `CLAUDE.md` holds course-lesson notes, not project rules, and `.gitignore` excludes `/AGENTS.md` and `/.claude` — onboarding is the right moment to fix both. Include the test command from fix #1.

### CI hardening: branch trigger, security scanning, DB parity, deploy

**Lesson**: [Sprint Zero z Agentem: infrastruktura, walking skeleton i pierwszy deploy (M1L5)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l5)
**What you'll do there**: Set up the remote and the real pipeline — point the push trigger at your actual default branch (`master` vs `main`), add `composer audit` / `npm audit` steps and npm/composer ecosystems to Dependabot, consider a Postgres service in CI for parity with dev, and add the self-host auto-deploy-on-merge flow recorded in `tech-stack.md`.

## Summary

Health status: healthy

root-keeper starts from a strong baseline: both lockfiles are present, `composer audit` is clean and `npm audit` shows a single LOW transitive advisory, all 39 PHPUnit tests pass, and PHPStan level 7, Pint and strict TypeScript all come back clean. The CI workflow already covers lint, types, tests and build. The gaps are about friction, not risk: the test command hangs unless Sail is running, the formatter check currently fails on course files (which would turn CI red), and `.env.example` and CI use SQLite while development uses PostgreSQL.

Next step: apply quick fixes 1–3 (about 10 minutes total), then proceed to agent onboarding.
