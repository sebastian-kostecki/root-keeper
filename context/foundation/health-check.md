---
project: root-keeper
checked_at: 2026-10-04T19:48:16Z
health_status: needs-attention
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
  critical: 0
  high: 1
  moderate: 1
  low: 0
test_runner_detected: true
ci_provider: GitHub Actions
recommended_fixes: 7
---

# Health check — root-keeper

> Project: Laravel 13 + Inertia 3 + React 19 (TypeScript), PHP 8.5, PostgreSQL 18, run locally with Sail. Checked before the brownfield change in `context/foundation/prd.md` (source description, places gazetteer, tree look + side panels, all-ancestors view; deadline 2026-11-04). Replaces the 2026-09-28 check (see git history) — all of its high and medium fixes (Larastan errors, PostgreSQL in CI, `.env.example`) are done.

## Dependency Health

### Lockfile

```
Status: present (composer.lock, package-lock.json)
Package manager: composer + npm
```

`pnpm-workspace.yaml` (starter leftover) still sits next to `package-lock.json`.

### Security Audit

```
Tool: vendor/bin/sail composer audit --format json; vendor/bin/sail npm audit --json
Summary: 0 CRITICAL, 1 HIGH, 1 MODERATE, 0 LOW
Direct vs transitive: composer — both advisories in one transitive package (league/commonmark, via laravel/framework and laravel-lang/publisher); npm — no advisories
```

#### HIGH findings

- **league/commonmark** 2.10.1 — PKSA-m4t9-vsgq-8khn (GHSA-3q6v-r5mr-hxv8): quadratic-time denial of service in the GitHub Flavored Markdown table extension. Fix: update to 2.10.2+ (2.10.3 available). Exposure is low: the app does not render Markdown (`Str::markdown`, CommonMark — no hits in `app/`, `routes/`, `resources/views/`, `config/`); the package comes in through the framework. Reported 2026-09-30.

MODERATE: **league/commonmark** 2.10.1 — PKSA-m2dq-1fhr-29b1 (GHSA-97jj-33gv-5xf9): `DisallowedRawHtml` bypass when a disallowed tag name ends the raw-HTML literal. Same fix.

### Outdated Dependencies

```
Packages with major version gaps: 2 (npm); composer: none
```

- **vite-plus**: 0.3.0 → 1.0.0 (pre-1.0 → stable 1.0). The 2026-09-28 check waited for 1.0 to leave RC; it now has. This is the toolchain the stack assessment flagged as least known to agents, and the `CLAUDE.md` Vite+ block pins 0.3.0.
- **@types/node**: 24.13.5 → 26.6.4 (2 majors). Intentional — types track the Node 24 runtime (wanted 24.19.1).

Minor/patch updates available (none urgent): inertiajs/inertia-laravel 3.3.4 → 3.5.1, laravel/framework 13.32.0 → 13.34.0, laravel/fortify 1.39.0 → 1.40.0, laravel/boost 2.9.1 → 2.10.1, larastan/larastan 3.12.1 → 3.12.2, laravel/sail 1.67.0 → 1.68.0, pestphp/pest 5.2.1 → 5.3.0, phpunit/phpunit 13.3.4 → 13.4.0; @inertiajs/react and @inertiajs/vite 3.7.1 → 3.8.0, vite 8.3.0 → 8.3.2, lucide-react 1.47.0 → 1.52.0. Windows-only optional binaries show as missing — expected on Linux/WSL.

## Test Suite

```
Test runner: Pest 5.2 (PHPUnit 13.3) via `vendor/bin/sail artisan test`; Vitest 4.1 via `npm run test:js` (`vp test`); Playwright 1.63 (E2E)
Tests found: 454 Pest tests; 124 Vitest tests in 7 files; 2 Playwright tests (setup + 1 seed spec)
Test execution: passing — Pest 454/454 (2944 assertions, ~76 s, PostgreSQL 18); Vitest 124/124 (~1.7 s); Playwright listed, not executed (needs the isolated E2E server)
```

```
Configuration: phpunit.xml; vite.config.ts (`vp test`); playwright.config.ts + scripts/e2e-server.sh (see context/foundation/test-stack.md)
Framework: Pest 5.2.1 + pest-plugin-laravel 5, PHPUnit 13.3.4; Vitest 4.1.11; @playwright/test 1.63.0
```

Other local gates, all passing: `composer types:check` (Larastan level 7, 0 errors), `npm run types:check` (tsc), `npm run check` (Oxfmt: 149 files formatted; Oxlint: 150 files, no warnings), `composer lint:check` (Pint).

**E2E coverage vs. PRD guardrails.** The only E2E spec protects the auth-gate round-trip. The two PRD guardrails (fact–source links; adding relations from tree slots) have no browser test. They are covered one layer down by Pest (`tests/Feature/WriteTargetsTest.php` pins "Inertia prop → request → database"), which is why `test-plan.md` §3 Phase 4 stayed `not started` on 2026-10-02 with the trigger: "run e2e when client-side id state appears". This change creates exactly that state — side panels whose content is swapped when moving to a relative (FR-019, FR-028), and a marriage panel editing a couple (FR-017). See Recommended Fixes #2.

## CI/CD

```
Provider: GitHub Actions
Configuration: .github/workflows/lint.yml, static-analysis.yml, tests.yml (+ .github/dependabot.yml, github-actions ecosystem only)
```

| Stage      | Status | Notes                                                                                 |
|------------|--------|---------------------------------------------------------------------------------------|
| Lint       | ✓      | Pint (`composer lint:check`) + Vite+ (`npm run check`); Vitest (`npm run test:js`) runs here too |
| Test       | ✓      | Pest on a `postgres:18-alpine` service with `pg_dump` 18 — green on `main`              |
| Build      | ✓      | `npm run build` inside `composer setup`                                               |
| Type check | ✓      | Larastan level 7 + `tsc --noEmit` — green on `main`                                   |
| Security   | ✗      | No `composer audit` / `npm audit` step; Dependabot watches GitHub Actions only        |

All three workflows are green on `main` for the last three pushes (`0be67d4`, `e816162`, `4b23032`). E2E (Playwright) does not run in CI. Workflows still use `node-version: '22'` while Sail runs Node 24.

The missing security stage has a concrete cost now: the league/commonmark HIGH advisory (reported 2026-09-30) went unnoticed for four days.

## Configuration

### High severity

No high-severity gaps. `.gitignore` present; `tsconfig.json` `strict: true`; lint/format config present (`vite.config.ts` `lint`/`fmt`, `pint.json`); `phpstan.neon` level 7 without baseline.

### Medium severity

- **Docs describe E2E coverage that does not exist** — `prd.md` Current System Overview says the critical paths are "chronione testami E2E (Playwright)", and `test-plan.md` §4 Stack still says "e2e: none yet … kandydat: pest-plugin-browser" although Playwright is set up (`test-stack.md`, 2026-10-02). Agents planning slices from these files will either assume a safety net that isn't there or propose a second E2E tool. Fix: Recommended Fixes #3.

### Low severity

- **CI Node version** — `node-version: '22'` in all three workflows; Sail runs Node 24 and `@types/node` is 24.x. Fix: Recommended Fixes #5.
- **`pnpm-workspace.yaml`** — suggests pnpm while the project uses npm. Fix: Recommended Fixes #6.
- **`.ai/rules` referenced but absent** — Boost's section of `CLAUDE.md` points agents to `.ai/rules/index.md`; the instruction is conditional ("If `.ai/rules` does not exist, continue without it"), so it only costs a lookup. No fix needed until the first rule is recorded.

Present and fine: `.editorconfig`, `.env.example` (now `DB_CONNECTION=pgsql`, Sail defaults), `.gitignore`, `pint.json`, `CLAUDE.md`.

**Backup scope (PRD NFR: new data incl. person photos, RPO 24 h).** `config/backup.php` includes the whole database and `storage_path('app')`; `bootstrap/app.php` schedules `backup:run` daily at 03:45 Europe/Warsaw in production (run by `schedule:work` in `docker/8.5/supervisord.prod.conf`). Photos stored under `storage/app` are therefore covered within 24 h. The pre-migration snapshot in `scripts/deploy.sh` is `--only-db`, which is enough — the planned data moves touch only the database. Store photos under `storage/app` (not outside it) to stay covered.

## Stack Assessment Cross-Reference

```
Stack assessment: context/foundation/stack-assessment.md
Agent readiness (from stack-assess): ready-with-compensation
```

| Quality Gate Gap / Assumption                               | Health-Check Finding                                                                  | Status      |
|-------------------------------------------------------------|---------------------------------------------------------------------------------------|-------------|
| Build tool (vite-plus 0.3) not in training data             | `CLAUDE.md` Vite+ block present; `vp check` green locally and in CI                   | Mitigated   |
| vite-plus pinned at pre-1.0                                 | vite-plus 1.0.0 stable is out; `CLAUDE.md` block and versions line describe 0.3.0    | Watch — upgrade outside feature work (#4) |
| Typed gate relies on Larastan level 7 in CI                 | Larastan 0 errors locally; `static-analysis` green on `main`                          | Mitigated (was Reinforced on 2026-09-28) |
| Recent major versions (Inertia v3, Pest 5, TS 7)            | All gates pass on these versions; versions block refresh pending (stack-assessment gap 2) | Mitigated |
| Side panels / data moves / gazetteer merge rules (gaps 3–5) | Proposed `CLAUDE.md` blocks not pasted yet (`CLAUDE.md` has no "Side panels" or "Migrations that move existing data" section) | Open — paste before the first slice (#7) |
| Hand-off: E2E protects critical paths                       | Only the auth-gate seed exists; no E2E in CI; test-plan Phase 4 trigger now met by side panels | Reinforced (#2, #3) |
| Hand-off: backups cover photos                              | Daily full backup covers `storage/app`; pre-migration DB-only snapshot suffices       | Resolved    |

## Recommended Fixes

### Fix before agent work (Category A)

### 1. Patch league/commonmark (1 HIGH, 1 MODERATE advisory)

**Impact**: Every `composer audit` (and any future CI audit step) stays red until patched, which trains agents and humans to ignore it. Real exposure is low — the app renders no Markdown — but the fix is a one-package update.
**Severity**: high
**Effort**: quick (< 5 min)
**Fix**:

```bash
vendor/bin/sail composer update league/commonmark
vendor/bin/sail composer audit
vendor/bin/sail artisan test --compact
```

Expected: league/commonmark 2.10.3, `composer audit` reports no advisories.

### 2. Cover the PRD guardrails in the browser before the panel slices

**Impact**: The change replaces modals with side panels in four places and swaps panel content when moving to a relative (FR-019, FR-028). That is the "client-side id state" `test-plan.md` named as the trigger for E2E. Without a browser test on the two guardrails (fact–source link, relation added from a tree slot), an agent can wire a panel action to the wrong person and every Pest test will still pass.
**Severity**: medium
**Effort**: significant (> 1 hour)
**Fix**: before the first panel slice, run `/10x-e2e` for two risks — (a) adding an existing parent from an empty tree slot lands on the right person; (b) linking a source to a fact keeps leading to the same source and scan. Assert on data, not layout (test-plan §6.6). Then flip `test-plan.md` §3 Phase 4 to in progress. Running E2E in CI is a separate step (see Category B).

### 3. Correct the docs that describe E2E coverage

**Impact**: `/10x-roadmap` and `/10x-plan` read `prd.md` and `test-plan.md`. Today they say critical paths are already E2E-protected, and that no E2E tool is chosen.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**:

- `context/foundation/prd.md`, Current System Overview, "Jakość dziś": replace the E2E clause with "ścieżki krytyczne (fakt–źródło, relacje ze slotów drzewa) chronione testami Pest (`WriteTargetsTest`); E2E (Playwright) skonfigurowane — na razie tylko test seed logowania, poza CI."
- `context/foundation/test-plan.md` §4 Stack, row `e2e`: Playwright 1.63 via `playwright.config.ts` (see `test-stack.md`), replacing the pest-plugin-browser candidate.

### 4. Upgrade vite-plus to 1.0 — outside feature work

**Impact**: 1.0 is stable. The change is frontend-heavy; upgrading mid-change mixes toolchain diffs with feature diffs, while staying on 0.3 keeps agents on a pre-1.0 tool whose `CLAUDE.md` block will age.
**Severity**: low
**Effort**: moderate (15–30 min)
**Fix**: on its own branch, either before the first slice or after 2026-11-04 — not in between:

```bash
vendor/bin/sail npm i -D vite-plus@^1
# read node_modules/vite-plus/docs/guide/upgrade.md (if present) and node_modules/vite-plus/AGENTS.md
vendor/bin/sail npm run check && vendor/bin/sail npm run types:check && vendor/bin/sail npm run test:js && vendor/bin/sail npm run build
```

Then update "vite-plus 0.3.0" in the `CLAUDE.md` Vite+ block heading and the versions line.

### 5. Match CI's Node version to the runtime

**Impact**: CI builds and type-checks on Node 22 while development runs Node 24 with `@types/node` 24 — an agent can use a Node 24 API that passes locally and breaks only in CI.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**: in `.github/workflows/{lint,static-analysis,tests}.yml`, change `node-version: '22'` to `node-version: '24'`.

### 6. Remove the package-manager ambiguity

**Impact**: agents that see `pnpm-workspace.yaml` may run `pnpm install` and create a second lockfile.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**: `git rm pnpm-workspace.yaml`, then verify with `vendor/bin/sail npm ci && vendor/bin/sail npm run build`.

### 7. Paste the change-specific rules into `CLAUDE.md` and apply pending minor updates

**Impact**: the stack assessment wrote rules for the riskiest parts of this change (side panels vs modals, migrations that move existing data, gazetteer merge) and a refreshed versions block. Until they are in `CLAUDE.md`, an agent will not see them. Minor updates keep that versions block accurate.
**Severity**: low
**Effort**: quick (< 5 min) for the paste; moderate for updates
**Fix**: copy the four blocks from `context/foundation/stack-assessment.md` → "Recommended Instruction File Additions" into `CLAUDE.md` above `<laravel-boost-guidelines>` (replace the existing "Installed versions" block; append the gazetteer bullets to "Backend conventions for tree-owned data"). On a separate branch: `vendor/bin/sail composer update -W && vendor/bin/sail npm update`, then `vendor/bin/sail artisan test --compact && vendor/bin/sail composer types:check && vendor/bin/sail npm run check`.

### Addressed in upcoming lessons (Category B)

### CI security scanning

**Lesson**: [Sprint Zero z Agentem: infrastruktura, walking skeleton i pierwszy deploy (M1L5)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l5)
**What you'll do there**: harden the pipeline — add `composer audit` and `npm audit --audit-level=high` steps, and add `composer` and `npm` ecosystems to `.github/dependabot.yml` (it watches GitHub Actions only). The league/commonmark advisory (#1) is what this would have caught.

### E2E in CI

**Lesson**: [Sprint Zero z Agentem: infrastruktura, walking skeleton i pierwszy deploy (M1L5)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l5)
**What you'll do there**: wire the pipeline stages. Here the E2E server is Sail-based (`scripts/e2e-server.sh`), so CI needs its own way to boot the app and the `e2e` database before `npx playwright test`; `test-plan.md` §5 already lists "e2e on critical tree/modal flows — CI on PR" as a gate after Phase 4.

Agent instruction files are not a gap: `CLAUDE.md` carries project rules (Vite+, versions, tree-owned data, E2E rules); the pending additions are in #7.

## Summary

Health status: needs-attention

The project is in good shape for agent work: CI is green on `main`, Larastan, tsc, Oxlint/Oxfmt and Pint are clean, and 454 Pest plus 124 Vitest tests pass on PostgreSQL. All fixes from the 2026-09-28 check are done, and backups already cover the photos the PRD adds. What keeps the status at needs-attention is one HIGH advisory in a transitive package the app does not use (a five-minute update), plus two gaps specific to this change. The PRD guardrails have no browser test while side panels introduce exactly the client-side state the test plan was waiting for. And the docs claim E2E coverage that does not exist.

Next step: patch league/commonmark (#1), correct the E2E wording in `prd.md` and `test-plan.md` (#3), paste the `CLAUDE.md` blocks (#7), then plan the milestone with `/10x-roadmap` — scheduling the two guardrail E2E tests (#2) before the first panel slice.
