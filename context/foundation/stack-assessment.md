---
project: root-keeper
assessed_at: 2026-10-23T07:12:12Z
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
  language: PHP 8.5 + TypeScript 7
  framework: Laravel 13 + Inertia v3 + React 19
  build_tool: Vite 8 via vite-plus 1.0.0
  test_runner: Pest 5 (+ Vitest 5 via vp test, Playwright 1.63 for E2E)
  package_manager: composer + npm
  ci_provider: github-actions
  deployment_target: self-host (Docker Compose on VPS behind Traefik, manual scripts/deploy.sh)
gates_passed: 13
gates_failed: 1
---

Assessed against `context/foundation/prd.md` (M-3: import from the research repo — Artisan command reading data blocks from markdown files, per-change review in the terminal, stable repo IDs, confidence A–D, fuzzy dates, surname variants; delivery up to 5 weeks after hours). Detection confirmed by the user. Replaces the 2026-10-04 assessment made for `archive/prd-v5.md` (see git history, commit `685ffa2`).

## Stack Components

**Language — PHP 8.5 + TypeScript 7.** `composer.json` requires `php: ^8.5`. Backend typing is enforced by Larastan 3.12.2 at level 7 (`phpstan.neon`, no baseline, no `ignoreErrors`) over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`. `declare(strict_types=1)` appears in 0 of 164 files in `app/` (not a convention here). Frontend is TypeScript 7.0.2 with `strict: true` and `noImplicitAny: true` in `tsconfig.json`.

**Framework — Laravel 13.34 + Inertia v3 + React 19.3.** Built from `laravel/react-starter-kit`. inertia-laravel 3.5.1, @inertiajs/react 3.8.0, Fortify 1.40.0, Wayfinder 0.1.21 (pre-1.0), spatie/laravel-backup 10.3.3, shadcn/ui on Radix, Tailwind 4.3.3, react-zoom-pan-pinch 4.2.0. Database: PostgreSQL 18. Write path: controller → Form Request → Action (`app/Actions/<Domain>/*::handle()`) → redirect; read path: API Resource → Inertia page; ownership via Policies on `tree_id` with `denyAsNotFound()`. Console: one class-based command, `app/Console/Commands/RestoreBackup.php` (PHP attributes `#[Signature]` / `#[Description]`, `ConfirmableTrait`), tested in `tests/Backup/RestoreCommandTest.php`. Laravel Prompts 0.3.24 and league/commonmark 2.10.3 come in through `laravel/framework`.

**Build tool — Vite 8 via vite-plus 1.0.0.** `package.json` scripts call `vp` (`vp build`, `vp dev`, `vp check`, `vp test`); `vite` is aliased to `@voidzero-dev/vite-plus-core@1.0.0`, `vitest` pinned to 5.0.1 in `overrides`. `vite.config.ts` holds `lint` (Oxlint, type-aware, `denyWarnings`) and `fmt` (Oxfmt). No ESLint/Prettier config.

**Test runners.** Pest 5.3.0 / PHPUnit 13.3.6 — 53 test files, including data-migration tests (`tests/Feature/SourceMigrationTest.php`). Vitest 5.0.1 via `npm run test:js` — 26 test files in `resources/js/`, incl. `view-tokens.test.ts`. Playwright 1.63 — 28 specs in `tests/e2e/` covering tree slots, side panels, sources, places, ancestors view; runs in CI (`.github/workflows/e2e.yml`); details in `context/foundation/test-stack.md`.

**Package manager — composer + npm.** `composer.lock`, `package-lock.json`. The starter's `pnpm-workspace.yaml` is gone.

**CI/CD — GitHub Actions.** `lint.yml` (Pint, `npm run check`, `npm run test:js`), `static-analysis.yml` (Larastan, `tsc --noEmit`), `tests.yml` (Pest on `postgres:18-alpine`), `e2e.yml` (Playwright on `postgres:18-alpine`). CI installs Composer packages **with** dev dependencies. Deploy is manual: `scripts/deploy.sh` → `backup:run --only-db` → `migrate --force`; the production image (`docker/8.5/Dockerfile.prod`) runs `composer install --no-dev`.

**Instruction files.** `CLAUDE.md`: 10xDevs E2E block, Vite+ toolchain, installed versions, UI tokens, side panels, data-moving migrations, tree-owned backend conventions, places, Laravel Boost guidelines. Project skills in `.claude/skills/`. Boost MCP provides `search-docs`. The Boost block tells agents to read `.ai/rules/index.md`; `.ai/` does not exist (the same text says to continue without it).

## Quality Gate Assessment

| Component                                     | Typed | Convention | Training Data | Documented | Verdict |
|-----------------------------------------------|-------|------------|---------------|------------|---------|
| Language: PHP 8.5 (Larastan level 7)          | ✓     | —          | —             | —          | pass    |
| Language: TypeScript 7 (strict)               | ✓     | —          | —             | —          | pass    |
| Framework: Laravel 13 + Inertia v3 + React 19 | —     | ✓          | ✓             | ✓          | pass    |
| Build tool: vite-plus 1.0.0 (Vite 8)          | —     | ✓          | ✗             | ✓          | fail    |
| Test runner: Pest 5                           | —     | —          | ✓             | ✓          | pass    |
| Test runner: Vitest 5 (via `vp test`)         | —     | —          | ✓             | ✓          | pass    |
| E2E runner: Playwright 1.63                   | —     | —          | ✓             | ✓          | pass    |

Legend: ✓ = pass, ✗ = fail, ~ = partial, — = not applicable

### Gate Details

**Typed**
- PHP ✓ — `phpstan.neon` includes the Larastan extension at `level: 7`, no baseline, no `ignoreErrors`; CI runs it in `static-analysis.yml`. Form Requests cover web write endpoints; a console command has no Form Request, so validation of parsed data blocks must be explicit (see gap 3).
- TypeScript ✓ — `strict: true`, `noImplicitAny: true`; `tsc --noEmit` in CI; Wayfinder generates typed route functions; Oxlint runs type-aware.

**Convention-based**
- Laravel ✓ — the strongest-convention PHP framework; Artisan commands have a fixed home (`app/Console/Commands/`, auto-discovered) and a test API (`$this->artisan(...)`). The repo's Action/Policy/Resource layer is documented in `CLAUDE.md`; the console write path is not (gap 3).
- vite-plus ✓ — one `vite.config.ts` holds build, lint and format; commands in `package.json`.

**Popular in training data** (within each language family)
- Laravel ✓ — mainstream PHP framework; Inertia + React is the official React starter. Caveat: installed majors (Laravel 13, Inertia v3, Pest 5, TypeScript 7, Vitest 5) are recent — mitigated by `CLAUDE.md` "Installed versions" and Boost `search-docs`. Laravel Prompts and Artisan console testing are well represented.
- Pest, Vitest, Playwright ✓ — default/mainstream runners in their families.
- vite-plus ✗ — 1.0.0 is the first stable release of a new tool; little training-data presence even within JS. Agents still default to `npx eslint`, `.prettierrc`, `import { defineConfig } from 'vite'`.

**Well-documented**
- Laravel / Inertia / Pest ✓ — versioned official docs, reachable via Boost `search-docs`.
- Vitest / Playwright ✓ — versioned official docs.
- vite-plus ✓ — https://viteplus.dev/guide/ plus version-matched local docs in `node_modules/vite-plus/docs/` and `node_modules/vite-plus/AGENTS.md`.

## Gaps & Compensation

### 1. vite-plus is not in the agent's training data (build tool, training data ✗) — compensated

**Why it matters for this change:** M-3 is mostly backend (command, parser, matching), but the frontend still changes: confidence A–D on facts, new date kinds in the timeline and forms, search by surname variant. Each slice ends with `npm run check`.

**Compensation:** already in `CLAUDE.md` ("Frontend toolchain — Vite+"), updated for 1.0.0. Keep as is.

### 2. The YAML parser exists only in dev (framework conventions — hardening for this change)

**Why it matters:** the data block in research files is the contract (FR-001, FR-002); the natural parsers are `symfony/yaml` or league/commonmark's front-matter extension, which itself needs `symfony/yaml`. `composer.lock` has `symfony/yaml` 8.1.8 only because `laravel/sail` and `laravel/roster` (dev) require it. Locally and in CI (`composer install` with dev) the import works and tests pass; the production image runs `composer install --no-dev`, so the command fails on the server with a missing class. Nothing in the pipeline catches it.

**Compensation:** a dependency rule (entry below). Adding `symfony/yaml` to `require` is a new direct dependency and needs the user's approval — decide in `/10x-plan`.

### 3. The import command writes outside the HTTP path (framework conventions — hardening for this change)

**Why it matters:** the PRD's guardrail is that import touches only the named account's tree, and it explicitly bypasses Policies (FR-016). The documented write path (Form Request → Action → Policy) does not apply to a command, and the repo has one command (`RestoreBackup`) that works on the whole instance — the wrong template for a tree-scoped import. An agent copying it, or reaching for `Person::query()` to match by name, would match against every tree. Interactive review (FR-005, FR-006, FR-007) mixed with domain logic is also hard to test without a TTY.

**Compensation:** a console-import rule (entry below): thin command, logic in Actions, tree resolved once from the e-mail, every query through it, a pure "proposed changes" object reviewed by the command and applied by an Action, and a two-user test.

### 4. Real research data and the pre-import backup (framework conventions — hardening for this change)

**Why it matters:** the PRD forbids real research data in the repo, test data and CI, and requires a DB copy taken right before an import writes on production. `storage/gen-tree-import` is gitignored today, and the live repo sits at `/mnt/d/my-projects/genealogy-project`, one copy away from a fixture. `scripts/deploy.sh` backs up before `migrate`, but an import runs outside deploy, so that backup does not cover it.

**Compensation:** a research-data rule (entry below).

### 5. New fields on existing rows (framework conventions — hardening, mostly already covered)

**Why it matters:** new nullable fields (repo ID on person/source/event, confidence A–D, surname variants) are additive. The date kinds are not: `events.date_is_approximate` is a boolean today, and range/before/after will likely replace it with a qualifier column — a migration that rewrites existing rows.

**Compensation:** the existing "Migrations that move existing data" rule covers it; one line added below to say so explicitly.

### Recommended Instruction File Additions

Paste into `CLAUDE.md` above the `<laravel-boost-guidelines>` block (Boost regenerates that block).

**Installed versions — add to the existing block (gap 1–2)**

```markdown
- Laravel Prompts 0.3 (interactive Artisan prompts) and league/commonmark 2.10 come through `laravel/framework`; `symfony/yaml` is in `composer.lock` only through dev packages (see "Production dependencies").
```

**Production dependencies — gap 2**

```markdown
## Production dependencies

- Production builds with `composer install --no-dev` (`docker/8.5/Dockerfile.prod`); CI and Sail install dev packages. A class used from `app/` must come from a package in `require`, not only from the lockfile.
- Before using a package's class in `app/`, run `vendor/bin/sail composer why <vendor/package>`: if only `require-dev` packages pull it in (e.g. `symfony/yaml` through `laravel/sail`), it is missing on production. Adding it to `require` is a new dependency and needs the user's approval.
```

**Research repo import — gap 3**

```markdown
## Research repo import (console write path)

- The import is an Artisan command in `app/Console/Commands/` (attributes `#[Signature]` / `#[Description]`, as in `RestoreBackup`). The command only resolves arguments, prints and asks; parsing, matching and writing live in `app/Actions/Import/*` with `handle()`.
- Resolve the tree once from the account e-mail (`User::where('email', …)->firstOrFail()->tree`) and pass it down. Every read and match goes through that tree (`$tree->persons()`, `$tree->localities()`), never `Person::query()` or another global query. `RestoreBackup` works on the whole instance and is not a template for this.
- No Policy runs here, so validation is explicit: each data block is validated (`Validator::make` with rules) before it reaches an Action; an invalid entry is skipped and reported with its file and reason, the rest continues.
- Split decide from apply: one Action builds a list of proposed changes (new, changed, conflict, match to confirm, removed in repo) without writing; the command reviews it with Laravel Prompts; another Action writes the approved changes in one DB transaction. Test the planning Action directly, and the command through `$this->artisan(...)` with `expectsQuestion` / `expectsChoice` / `expectsConfirmation` (check current API with `search-docs`).
- Every import feature test has a second user with overlapping data (same names, same places) and asserts nothing of theirs is matched or written.
- Records carry the stable repo ID from the block, never the file name; records without a repo ID (added only in the app) are never updated or deleted by import.
```

**Research data and backup — gap 4**

```markdown
## Research data

- Real research data (`/mnt/d/my-projects/genealogy-project`, `storage/gen-tree-import/`) never enters the repo, fixtures, factories, seeders or CI. Import tests use a small invented repo under `tests/Fixtures/research-repo/` written for the test. Keep `/storage/gen-tree-import` in `.gitignore`.
- The research repo is read-only for the app: the import never writes, moves or deletes files there; scans are copied into the app's storage.
- On production the import takes a DB backup (`backup:run --only-db`) after the user approves changes and before the first write; if the backup fails, nothing is written.
```

**Data moves — gap 5 (append to "Migrations that move existing data")**

```markdown
- Replacing `events.date_is_approximate` with a date qualifier (exact, approximate, range, before, after) is a data-moving migration: add the new column, copy (`true` → approximate, `false` → exact), test it, and drop the boolean only in a later change.
```

## Summary

**Overall agent-readiness: ready with compensation.** 13 of 14 applicable checks pass, same as on 2026-10-04.

**Strengths:** mainstream, convention-heavy PHP framework on its official React starter; type discipline on both sides (Larastan level 7 without baseline, strict TypeScript, typed routes); lint, static analysis, Pest, Vitest and now 28 E2E specs gate CI; the repo's layers and recurring traps (side panels, data moves, places) are written in `CLAUDE.md`; Boost gives version-scoped docs; Artisan + Laravel Prompts give the import command a well-known home and test API.

**Gaps:** the only failed check is still vite-plus (now 1.0, still new to agents), already compensated. M-3 adds a write path outside HTTP, so the hardening moves to the backend: a YAML parser that silently disappears on production, tree scoping and validation without Policies and Form Requests, real data kept out of fixtures, and a backup before an import writes.

**Hand-off to the health check:**
- Dev/prod dependency drift: nothing checks that `app/` uses only `require` packages; CI installs dev. Consider a CI step or a `--no-dev` smoke test of the import command.
- `.ai/rules/index.md` is referenced by the Boost block but `.ai/` does not exist.
- Backup coverage: `deploy.sh` backs up with `--only-db`; the import copies scans into storage — confirm how `storage/app` is backed up before the first production import.

**Next step:** `/10x-health-check`.
