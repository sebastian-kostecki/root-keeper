---
project: root-keeper
assessed_at: 2026-10-04T19:41:05Z
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
  language: PHP 8.5 + TypeScript 7
  framework: Laravel 13 + Inertia v3 + React 19
  build_tool: Vite 8 via vite-plus 0.3.0
  test_runner: Pest 5 (+ Vitest 4 via vp test, Playwright 1.63 for E2E)
  package_manager: composer + npm
  ci_provider: github-actions
  deployment_target: self-host (Docker Compose on VPS behind Traefik, manual scripts/deploy.sh)
gates_passed: 13
gates_failed: 1
---

Assessed against `context/foundation/prd.md` (source description, places gazetteer, tree look + side panels, all-ancestors view; delivery 4 weeks, deadline 2026-11-04). Detection confirmed by the user. Replaces the 2026-09-28 assessment made for `archive/prd-v2.md` (see git history).

## Stack Components

**Language — PHP 8.5 + TypeScript 7.** `composer.json` requires `php: ^8.5`. Backend typing is enforced by Larastan 3.12.1 at level 7 (`phpstan.neon`, no baseline, no `ignoreErrors`) over `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`. `declare(strict_types=1)` appears in 0 of 87 files in `app/` (dropped as a claim on 2026-09-28; not enforced). Frontend is TypeScript 7.0.2 with `strict: true` and `noImplicitAny: true` in `tsconfig.json` (`noUncheckedIndexedAccess` off).

**Framework — Laravel 13.32 + Inertia v3 + React 19.3.** Built from `laravel/react-starter-kit`. inertia-laravel 3.3.4, @inertiajs/react 3.7.1, Fortify 1.39, Wayfinder 0.1.21 (pre-1.0, typed route functions), spatie/laravel-backup 10.3, shadcn/ui on Radix (`resources/js/components/ui/`, including `sheet.tsx`, `dialog.tsx`, `tabs.tsx`), Tailwind 4.3, react-zoom-pan-pinch 4 (used today in `resources/js/components/sources/source-viewer.tsx`). Database: PostgreSQL 18. Established code path: route → controller → Form Request → Action (`app/Actions/<Domain>/*::handle()`) → API Resource → Inertia page; ownership via Policies on `tree_id` with `denyAsNotFound()`.

**Build tool — Vite 8.3 via vite-plus 0.3.0.** `package.json` scripts call `vp` (`vp build`, `vp dev`, `vp check`, `vp test`). `vite.config.ts` imports from `vite-plus` and holds `lint` (Oxlint, type-aware, `denyWarnings`) and `fmt` (Oxfmt). No ESLint/Prettier config.

**Test runners.** Pest 5.2.1 / PHPUnit 13.3 — 29 test files in `tests/`, including data-migration tests (`tests/Feature/SourceMigrationTest.php`, `tests/Feature/TreeBackfillTest.php`). Vitest 4.1 via `npm run test:js` (`vp test`) — 7 unit tests in `resources/js/lib/` and `resources/js/hooks/`, run in CI. Playwright 1.63 — `playwright.config.ts`, `tests/e2e/auth.setup.ts` and one seed spec (`tests/e2e/seed.spec.ts`, auth gate round-trip) against an isolated Sail server (`scripts/e2e-server.sh`, port 8100); details in `context/foundation/test-stack.md`. E2E does not run in CI.

**Package manager — composer + npm.** `composer.lock`, `package-lock.json`. `pnpm-workspace.yaml` still present without `pnpm-lock.yaml` (starter leftover).

**CI/CD — GitHub Actions.** `lint.yml` (Pint `lint:check`, `npm run check`, `npm run test:js`), `static-analysis.yml` (Larastan, `tsc --noEmit`), `tests.yml` (Pest on a `postgres:18-alpine` service). Deploy is manual: `scripts/deploy.sh` → `production_backup_before_migrate` (`backup:run --only-db`) → `migrate --force` on the VPS (`docker-compose.prod.yml`, Traefik; see `infrastructure.md`).

**Instruction files.** `CLAUDE.md`: 10xDevs E2E block (M3L4), Vite+ toolchain, installed versions, backend conventions for tree-owned data, Laravel Boost guidelines. Project skills in `.claude/skills/`. Boost MCP provides `search-docs`. `CLAUDE.md` tells agents to read `.ai/rules/index.md`, which does not exist (the same text says to continue without it).

## Quality Gate Assessment

| Component                                     | Typed | Convention | Training Data | Documented | Verdict |
|-----------------------------------------------|-------|------------|---------------|------------|---------|
| Language: PHP 8.5 (Larastan level 7)          | ✓     | —          | —             | —          | pass    |
| Language: TypeScript 7 (strict)               | ✓     | —          | —             | —          | pass    |
| Framework: Laravel 13 + Inertia v3 + React 19 | —     | ✓          | ✓             | ✓          | pass    |
| Build tool: vite-plus 0.3.0 (Vite 8)          | —     | ✓          | ✗             | ✓          | fail    |
| Test runner: Pest 5                           | —     | —          | ✓             | ✓          | pass    |
| Test runner: Vitest 4 (via `vp test`)         | —     | —          | ✓             | ✓          | pass    |
| E2E runner: Playwright 1.63                   | —     | —          | ✓             | ✓          | pass    |

Legend: ✓ = pass, ✗ = fail, ~ = partial, — = not applicable

### Gate Details

**Typed**
- PHP ✓ — `phpstan.neon` includes the Larastan extension at `level: 7`, no baseline file, no `ignoreErrors`; run in CI by `static-analysis.yml` (`composer types:check` → `phpstan analyse`). Form Requests cover write endpoints.
- TypeScript ✓ — `tsconfig.json` `strict: true`, `noImplicitAny: true`; `tsc --noEmit` in CI; Wayfinder generates typed route functions; Oxlint runs type-aware.

**Convention-based**
- Laravel ✓ — the strongest-convention framework in PHP; the repo adds one consistent layer (Actions with `handle()`, Policies with `denyAsNotFound()`, Resources with `->resolve()`), documented in `CLAUDE.md`.
- vite-plus ✓ — one `vite.config.ts` holds build, lint and format; commands in `package.json`.

**Popular in training data** (within each language family)
- Laravel ✓ — mainstream PHP framework; Inertia + React is Laravel's official React starter path. Caveat: installed majors (Laravel 13, Inertia v3, Pest 5, TypeScript 7) are recent; agent priors skew to older APIs — mitigated by `CLAUDE.md` "Installed versions" and Boost `search-docs`.
- Pest ✓ — default test framework of current Laravel starters.
- Vitest ✓ — mainstream JS unit-test runner; here invoked through `vp test`, which the toolchain block already explains.
- Playwright ✓ — mainstream JS E2E runner.
- vite-plus ✗ — 0.3.0, pre-1.0, recent; little training-data presence even within JS. Agents default to `npx eslint`, `.prettierrc`, `import { defineConfig } from 'vite'`.

**Well-documented**
- Laravel / Inertia / Pest ✓ — versioned official docs, reachable via Boost `search-docs` scoped to installed versions.
- Vitest / Playwright ✓ — versioned official docs (vitest.dev, playwright.dev).
- vite-plus ✓ — https://viteplus.dev/guide/ plus version-matched local docs in `node_modules/vite-plus/docs/` and `node_modules/vite-plus/AGENTS.md`.

## Gaps & Compensation

### 1. vite-plus is not in the agent's training data (build tool, training data ✗) — compensated

**Why it matters for this change:** most of the PRD is frontend work (side panels for person, source, marriage, locality; tree redesign with zoom; ancestors view; filters on the source grid; autocomplete pickers). Every slice ends with `npm run check`; an agent reaching for ESLint/Prettier fails CI or adds a second toolchain.

**Compensation:** already in `CLAUDE.md` ("Frontend toolchain — Vite+") since 2026-09-28. Keep as is.

### 2. Recent major versions (framework — hardening, not a gate failure)

**Why it matters:** new routes, forms and pages; Wayfinder 0.1 and Inertia v3 form helpers are where outdated priors leak in. Since the last assessment Playwright 1.63 and Vitest (via `vp test`) joined the toolchain, and the change will lean on react-zoom-pan-pinch for tree and ancestors-view zoom (FR-013, FR-018).

**Compensation:** refresh the "Installed versions" block (replacement below).

### 3. Side panels replace modals in four places (framework conventions — hardening for this change)

**Why it matters:** FR-006, FR-016, FR-017, FR-019 move person, source, marriage and locality into a right side panel, while delete confirmations and small pickers stay modal (shape-notes decision "side panel scope"). FR-019 requires the tree to narrow beside the open panel, not be covered by it. The shadcn `sheet.tsx` in the repo is an overlay built on Radix Dialog, so an agent picking it "because it is the side panel component" would ship an overlay that fails FR-019. Without a written rule, each of four slices will invent its own panel.

**Compensation:** a panel rule (entry below). The concrete component choice belongs to `/10x-plan`; the rule fixes the constraints it must satisfy.

### 4. Data moves with real data on production (framework conventions — hardening for this change)

**Why it matters:** four data moves (free-text places → gazetteer, single residence → residence list, "Pochodzenie" → archive, "Numer wpisu" kept visible for manual transfer). The author accepts manual fixes, but a lost value is unrecoverable without a backup. The repo already has the right pattern — data migrations with their own Pest tests (`SourceMigrationTest.php`, `TreeBackfillTest.php`) and a DB backup before `migrate` in `scripts/deploy.sh` — but it is not written down.

**Compensation:** a data-move rule (entry below).

### 5. Gazetteer is a new tree-owned module with merge (framework conventions — hardening for this change)

**Why it matters:** places and parishes are referenced from parishes, gravestones, facts and residences; merging a duplicate must move every reference (Business Logic Changes). Missing one reference type leaves orphans; a merge across trees would leak data.

**Compensation:** extend the tree-owned conventions (entry below).

### Recommended Instruction File Additions

Paste into `CLAUDE.md` above the `<laravel-boost-guidelines>` block (Boost regenerates that block).

**Installed versions — replaces the existing block (gap 2)**

```markdown
## Installed versions (check before relying on an API)

PHP 8.5 · Laravel 13 · Inertia v3 (inertia-laravel 3.3, @inertiajs/react 3.7) · React 19 (React Compiler on) · TypeScript 7 · Tailwind v4 · Pest 5 / PHPUnit 13 · Larastan 3 (level 7) · Fortify 1.39 · Wayfinder 0.1 (pre-1.0) · PostgreSQL 18 · Vite 8 via vite-plus 0.3 · Vitest 4 (run as `vp test`) · Playwright 1.63 · react-zoom-pan-pinch 4.

- These are newer than most training data. Use Boost `search-docs` for Laravel/Inertia/Pest APIs instead of recalling them.
- Frontend routes come from Wayfinder: import from `@/actions/...` or `@/routes/...`; never hardcode URLs. After adding or renaming a route, regenerate with `vendor/bin/sail artisan wayfinder:generate` (the Vite plugin also does it in dev).
- Before adding a frontend dependency, check `package.json`: zoom/pan already uses `react-zoom-pan-pinch` (`resources/js/components/sources/source-viewer.tsx`); UI primitives come from `resources/js/components/ui/`. New dependencies need the user's approval.
```

**Side panels — gap 3**

```markdown
## Side panels vs modals

- Person, source, marriage and locality open in a right-hand side panel, not a modal. Delete confirmations and small pickers (choose a person, add a parish inline) stay modal (`resources/js/components/ui/dialog.tsx`).
- An open panel must not cover the tree: the tree area narrows beside it. Do not use an overlay for these panels — `resources/js/components/ui/sheet.tsx` is an overlay (Radix Dialog) and does not meet this as-is.
- Build one shared panel shell and reuse it for all four; the source panel is wider (scan beside the form).
- Closing a panel returns the user to the same tree position; moving to a relative replaces the panel content.
```

**Data moves — gap 4**

```markdown
## Migrations that move existing data

- A migration that rewrites existing rows (free text → reference, field → other field) gets its own Pest test in `tests/Feature/<Name>MigrationTest.php`, following `tests/Feature/SourceMigrationTest.php`: insert rows in the old shape, run the migration, assert the new shape and that no value was lost.
- Copy first, drop later: never drop the source column in the same migration that copies from it. Values the user must transfer by hand (e.g. the old source "Numer wpisu") stay readable until the user removes them.
- Migrations must be reversible (`down()`) or state in a comment why they cannot be.
- Production runs `backup:run --only-db` before `migrate` (`scripts/deploy.sh`); do not remove that step.
```

**Tree-owned gazetteer — gap 5 (append to "Backend conventions for tree-owned data")**

```markdown
- Places and parishes are tree-owned records like every other: unique per tree, scoped through `$request->user()->tree`, Policy with `denyAsNotFound()`.
- Merging a duplicate (place or parish) moves every reference — parishes, gravestones, event places, burial places, residences — to the target inside one DB transaction, then deletes the duplicate. Both records must belong to the same tree; the merge test covers every reference type and the other-user 404.
- Deleting a place or parish is allowed only when nothing references it.
```

## Summary

**Overall agent-readiness: ready with compensation.** 13 of 14 applicable checks pass.

**Strengths:** mainstream, convention-heavy PHP framework on its official React starter; real type discipline on both sides (Larastan level 7 without baseline, strict TypeScript, typed routes); lint, static analysis, PHP and JS unit tests all gate CI; consistent Action/Policy/Resource layer documented in `CLAUDE.md`; Boost gives version-scoped docs; data migrations already have a tested pattern.

**Gaps:** the only failed check is still vite-plus (too new for the agent), already compensated in `CLAUDE.md`. For this change, three new rules harden the riskiest areas — side panels in four places, four data moves on real data, and the gazetteer merge — plus a refreshed versions block.

**Hand-off to the health check:**
- The PRD's Current System Overview says critical paths (fact–source, relations from tree slots) are protected by E2E tests, but `tests/e2e/` holds only the auth-gate seed and E2E does not run in CI. The PRD guardrails depend on that coverage.
- The pre-migration backup is `--only-db`; person photos (FR-015) are files, and the PRD requires backups to cover them (RPO 24 h). Confirm how `storage/app` is backed up.
- Minor: `.ai/rules` pointer in `CLAUDE.md` without the directory; `pnpm-workspace.yaml` beside an npm lockfile.

**Next step:** `/10x-health-check`.
