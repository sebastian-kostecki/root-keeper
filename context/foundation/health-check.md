---
project: root-keeper
checked_at: 2026-09-28T05:01:34Z
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
  high: 0
  moderate: 0
  low: 0
test_runner_detected: true
ci_provider: GitHub Actions
recommended_fixes: 9
---

# Health check — root-keeper

> Project: Laravel 13 + Inertia 3 + React 19 (TypeScript), PHP 8.5, PostgreSQL 18, run locally with Sail. Checked before the brownfield change in `context/foundation/prd-v2.md` (scan descriptions, places gazetteer, residence history). Replaces the 2026-09-18 baseline check (see git history).

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
Summary: 0 CRITICAL, 0 HIGH, 0 MODERATE, 0 LOW
Direct vs transitive: composer — no advisories, no abandoned packages; npm — no advisories across 418 packages
```

The LOW `@babel/core` advisory from the 2026-09-18 check is gone.

### Outdated Dependencies

```
Packages with major version gaps: 2 (npm); composer: none
```

- **vite-plus**: 0.3.0 → 1.0.0-rc.1 (pre-1.0 → 1.0 release candidate). The toolchain the stack assessment flagged as least known to agents is about to hit 1.0; upgrade once 1.0 is stable, not the RC.
- **@types/node**: 24.13.5 → 26.6.3 (2 majors). Intentional — types should track the Node runtime (24 locally and in Sail), not "latest". CI, however, runs Node 22 (see Configuration).

Minor/patch updates available (no action required mid-change): inertiajs/inertia-laravel 3.3.4 → 3.4.0, laravel/framework 13.32.0 → 13.33.0, laravel/fortify 1.39.0 → 1.40.0, laravel/boost 2.9.1 → 2.10.0, larastan/larastan 3.12.1 → 3.12.2, laravel/sail 1.67.0 → 1.68.0, phpunit/phpunit 13.3.4 → 13.3.5; vite 8.3.0 → 8.3.1, lucide-react 1.47.0 → 1.48.0. Windows-only optional binaries show as missing — expected on Linux/WSL.

## Test Suite

```
Test runner: Pest 5.2 (PHPUnit 13.3) via `vendor/bin/sail artisan test`
Tests found: 169 tests (Feature + Unit)
Test execution: passing locally (169/169, 1026 assertions, ~15 s) against PostgreSQL 18; failing in CI (1 failed, 168 passed) against SQLite
```

```
Configuration: phpunit.xml (DB_DATABASE=testing; driver comes from .env)
Framework: Pest 5.2.1, pest-plugin-laravel 5, PHPUnit 13.3.4, Mockery
```

Other local gates:

- `composer lint:check` (Pint) — pass.
- `npm run check` (Vite+: Oxfmt + Oxlint) — pass, 98 files formatted, 89 files lint-clean.
- `npm run types:check` (tsc) — pass.
- `composer types:check` (Larastan level 7) — **fail, 12 errors in 8 files** (details under Recommended Fixes #1).

The test command works as long as Sail is up (the hang noted on 2026-09-18 does not apply when run through Sail). No frontend tests exist; Vitest is available through `vp test`.

## CI/CD

```
Provider: GitHub Actions
Configuration: .github/workflows/lint.yml, static-analysis.yml, tests.yml (+ .github/dependabot.yml, github-actions ecosystem only)
```

| Stage      | Status | Notes                                                                                   |
|------------|--------|-----------------------------------------------------------------------------------------|
| Lint       | ✓      | Pint (`composer lint:check`) + Vite+ (`npm run check`) — green on `main`                 |
| Test       | ✓      | Pest (`php artisan test`) — **red on `main` since `a253498` (2026-09-25)**: 1 SQLite-only failure |
| Build      | ✓      | `npm run build` inside `composer setup`                                                  |
| Type check | ✓      | Larastan level 7 + `tsc --noEmit` — **red on `main` since `a253498`**: 12 Larastan errors |
| Security   | ✗      | No `composer audit` / `npm audit` step; Dependabot covers GitHub Actions only           |

Last fully green run on `main`: `1c07eb4` (2026-09-24). Both `a253498` and `8addb52` (current `origin/main`) fail `tests` and `static-analysis`.

**CI test failure:** `Tests\Feature\PersonsTest > persons index ignores case but not polish diacritics` — searching `ŁUKASZ żółk` returns 0 persons instead of 1. CI builds the app with `composer setup`, which copies `.env.example` (`DB_CONNECTION=sqlite`); SQLite's case-insensitive `LIKE` folds only ASCII, so `Ł`/`ł` and `Ż`/`ż` don't match. PostgreSQL (dev, prod) passes. The DB-parity risk flagged on 2026-09-18 has now produced a real false-red, and the planned change adds two more text searches over Polish names (scan name search, place suggestions).

## Configuration

### High severity

No high-severity gaps. `.gitignore` present; `tsconfig.json` has `strict: true`; lint/format config present (`vite.config.ts` `lint`/`fmt`, `pint.json`).

### Medium severity

- **`.env.example`** — still `DB_CONNECTION=sqlite` with MySQL-style comments, while dev/prod run PostgreSQL. Because CI copies this file, it decides which database CI tests on (root cause of the CI test failure). Fix: see Recommended Fixes #2.

### Low severity

- **CI Node version** — workflows use `node-version: '22'`; local and Sail run Node 24 and `@types/node` is 24.x. Fix: set `node-version: '24'` in all three workflows.
- **`pnpm-workspace.yaml`** — suggests pnpm while the project uses npm. Fix: `git rm pnpm-workspace.yaml`.
- **`.ai/rules` referenced but absent** — the Boost section of `CLAUDE.md` tells agents to read `.ai/rules/index.md`; the directory doesn't exist. The instruction is conditional ("If `.ai/rules` does not exist, continue without it"), so it only costs a lookup. No fix needed until the first rule is recorded.

Present and fine: `.editorconfig`, `.env.example`, `.gitignore`, `pint.json`, `phpstan.neon` (level 7, no baseline), `tsconfig.json` strict, `CLAUDE.md` (tracked; `.claude/` and `AGENTS.md` are gitignored — CLI-managed skills, intentional).

Backup scope (PRD constraint "backups must cover the new data"): `config/backup.php` backs up the whole database plus `storage/app`, so new scan fields, places and residences are covered without changes.

## Stack Assessment Cross-Reference

```
Stack assessment: context/foundation/stack-assessment.md
Agent readiness (from stack-assess): ready-with-compensation
```

| Quality Gate Gap / Assumption                         | Health-Check Finding                                                                 | Status      |
|-------------------------------------------------------|--------------------------------------------------------------------------------------|-------------|
| Build tool (vite-plus) not in training data           | `CLAUDE.md` Vite+ block present; `vp check` passes locally and in CI                 | Mitigated   |
| Build tool version pin (0.3)                          | vite-plus 1.0.0-rc.1 published — pin and `CLAUDE.md` block need a refresh at 1.0     | Watch       |
| Typed gate passes via Larastan level 7 in CI          | Larastan fails with 12 errors on `main`; CI static-analysis red since 2026-09-25    | Reinforced — the typing signal the gate relies on is currently off |
| Tree-owned data conventions (Resources, Requests, controllers) | `CLAUDE.md` block present; 10 of the 12 Larastan errors sit in exactly these layers | Reinforced — fix before the gazetteer copies the patterns |
| Recent major versions (Inertia v3, Pest 5, TS 7)      | Versions block in `CLAUDE.md`; all checks except Larastan pass on these versions    | Mitigated   |
| `strict_types` claim                                  | Dropped from `tech-stack.md` (2026-09-28)                                            | Resolved    |

## Recommended Fixes

### Fix before agent work (Category A)

### 1. Make Larastan green again (12 errors)

**Impact**: The stack's typed rating rests on Larastan in CI. While it's red, every agent PR fails static analysis for pre-existing reasons, new type errors hide among the old ones, and the gazetteer module would copy the failing patterns (Resources, Form Requests, controllers).
**Severity**: high
**Effort**: moderate (15–30 min)
**Fix**: run `vendor/bin/sail composer types:check` and fix the causes (no baseline, no `@phpstan-ignore`):

- `MarriageController.php:27`, `ParentChildRelationController.php:24` — the `Person` passed to the Action is typed `Person|Collection<Person>` (4 errors). Resolve a single model (e.g. `Person::query()->findOrFail($id)` with an `int` id, or a Form Request method returning `Person`).
- `ListRelationCandidates.php:50` — returns `array<int, …>` where `list<…>` is declared; wrap the result in `array_values()` or build it with `->values()->all()`.
- `MarriageResource.php:43` — `toArray()` return shape doesn't match its PHPDoc array shape; align the shape.
- `RelativeResource.php:30` — `$this->pivot` is undefined on the resource; annotate the underlying model (`@mixin Person` / `@property-read` pivot) or read the pivot from `$this->resource`.
- `PersonResource.php:85-87` — drop the unnecessary `?->` on `year`/`month`/`day` (3 errors).
- `MarriageUpdateRequest.php:25`, `PersonStoreRequest.php:24` — narrow the types in the rule closures/`array_map` callback.

Verify: `vendor/bin/sail composer types:check` → 0 errors, then push and confirm the `static-analysis` workflow is green.

### 2. Run CI tests on PostgreSQL (DB parity)

**Impact**: CI tests on SQLite, dev and prod run PostgreSQL. This already produced a false-red (Polish diacritics search), and `prd-v2` adds scan name search (FR-006) and place suggestions while typing (FR-008) over Polish names — exactly where SQLite and PostgreSQL disagree. An agent seeing a CI-only failure may "fix" correct code.
**Severity**: high
**Effort**: moderate (15–30 min)
**Fix**:

1. In `.github/workflows/tests.yml`, add a Postgres service and point the test step at it:

```yaml
jobs:
  tests:
    runs-on: ubuntu-latest
    services:
      pgsql:
        image: postgres:18-alpine
        env:
          POSTGRES_DB: testing
          POSTGRES_USER: sail
          POSTGRES_PASSWORD: password
        ports:
          - 5432:5432
        options: >-
          --health-cmd "pg_isready -U sail"
          --health-interval 5s --health-timeout 5s --health-retries 10
    env:
      DB_CONNECTION: pgsql
      DB_HOST: 127.0.0.1
      DB_PORT: 5432
      DB_DATABASE: testing
      DB_USERNAME: sail
      DB_PASSWORD: password
```

and add `pdo_pgsql` to the `extensions:` list of `shivammathur/setup-php` (`extensions: gd, exif, pdo_pgsql`). Keep `composer setup`'s `migrate --force` working by setting the same env at job level (as above).

2. Push and confirm `tests` is green, including `persons index ignores case but not polish diacritics`.

### 3. Align `.env.example` with the Sail/PostgreSQL setup

**Impact**: `.env.example` is what fresh clones, CI (`composer setup`) and agents read as the database truth; it still says SQLite.
**Severity**: medium
**Effort**: quick (< 5 min)
**Fix**: replace the `DB_*` block in `.env.example` with:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=root_keeper
DB_USERNAME=sail
DB_PASSWORD=password
```

(Sail defaults only, no secrets. CI overrides these via the job env from fix #2.)

### 4. Match CI's Node version to the runtime

**Impact**: CI builds and type-checks on Node 22 while development runs Node 24 with `@types/node` 24 — an agent can use a Node 24 API that passes locally and breaks only in CI.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**: in `.github/workflows/{lint,static-analysis,tests}.yml`, change `node-version: '22'` to `node-version: '24'`.

### 5. Remove the package-manager ambiguity

**Impact**: agents that see `pnpm-workspace.yaml` may run `pnpm install` and create a second lockfile.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**: `git rm pnpm-workspace.yaml` (the project uses npm; its `publicHoistPattern` setting has no effect under npm). Verify with `vendor/bin/sail npm ci && vendor/bin/sail npm run build`.

### 6. Apply pending minor/patch updates between changes

**Impact**: small, but keeps the "installed versions" block in `CLAUDE.md` accurate and avoids bundling version bumps into feature diffs.
**Severity**: low
**Effort**: quick (< 5 min)
**Fix**: on a separate branch, not mid-feature:

```bash
vendor/bin/sail composer update -W
vendor/bin/sail npm update
vendor/bin/sail artisan test --compact && vendor/bin/sail composer types:check && vendor/bin/sail npm run check
```

Update the versions line in `CLAUDE.md` if a listed version changes (e.g. inertia-laravel 3.4).

### 7. Move to vite-plus 1.0 when it is stable

**Impact**: 1.0 will likely change CLI or config details the `CLAUDE.md` Vite+ block describes; the local docs in `node_modules/vite-plus/docs/` will change with it.
**Severity**: low
**Effort**: quick (< 5 min) for the bump; re-read the upgrade guide first
**Fix**: after 1.0.0 (not the RC) is out: read `node_modules/vite-plus/docs/guide/upgrade.md`, run `vendor/bin/sail npm i -D vite-plus@^1`, then `vendor/bin/sail npm run check && vendor/bin/sail npm run build`, and update the version in the `CLAUDE.md` Vite+ block and versions line.

### 8. Add frontend tests when UI logic grows

**Impact**: React components are only type-checked and linted. The place picker (suggest existing, offer "add new" only when nothing matches) is the first UI with real client-side logic that a test could pin down.
**Severity**: low
**Effort**: moderate (15–30 min)
**Fix**: Vite+ already bundles Vitest — read `node_modules/vite-plus/docs/guide/test.md`, add `vendor/bin/sail npm i -D @testing-library/react jsdom`, a `"test": "vp test"` script, and a step `npm test` in `tests.yml`. Optional for `prd-v2`; backend rules stay covered by Pest feature tests.

### Addressed in upcoming lessons (Category B)

### CI security scanning

**Lesson**: [Sprint Zero z Agentem: infrastruktura, walking skeleton i pierwszy deploy (M1L5)](https://platforma.przeprogramowani.pl/external/10xdevs-3/m1-l5)
**What you'll do there**: harden the pipeline — add `composer audit` and `npm audit --audit-level=high` steps, and add `composer` and `npm` ecosystems to `.github/dependabot.yml` (today it watches GitHub Actions only). Both audits are clean today, so this is prevention, not a fix.

Agent instruction files are no longer a gap: `CLAUDE.md` now carries project rules (Vite+ toolchain, installed versions, tree-owned data conventions) added from the stack assessment on 2026-09-28.

## Summary

Health status: needs-attention

Dependencies are in good shape: both lockfiles are present, `composer audit` and `npm audit` are clean, and no Composer package is a major version behind. Locally, all 169 Pest tests pass on PostgreSQL, and Pint, Vite+ lint/format and `tsc` are clean. The problem is CI: `main` has been red since 2026-09-25 for two reasons. Larastan reports 12 type errors in controllers, Resources and Form Requests, the same layers the gazetteer module will copy. And CI tests on SQLite, which fails a Polish-diacritics search that PostgreSQL passes. The planned change adds more searches over Polish names, so that gap will keep producing false-reds.

Next step: fix #1 (Larastan) and #2–#3 (PostgreSQL in CI and `.env.example`) — about an hour together, best done as one small change — so CI is green before the `prd-v2` slices start. Then proceed to planning the roadmap (`/10x-roadmap`).
