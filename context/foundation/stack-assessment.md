---
project: root-keeper
assessed_at: 2026-09-28T04:53:57Z
agent_readiness: ready-with-compensation
context_type: brownfield
stack_components:
  language: PHP 8.5 + TypeScript 7
  framework: Laravel 13 + Inertia v3 + React 19
  build_tool: Vite 8 via vite-plus 0.3.0
  test_runner: Pest 5
  package_manager: composer + npm
  ci_provider: github-actions
  deployment_target: self-host (Docker on VPS behind Traefik, manual scripts/deploy.sh)
gates_passed: 9
gates_failed: 1
---

Assessed against `context/foundation/prd-v2.md` (scan descriptions + places gazetteer + residence history). Detection confirmed by the user.

## Stack Components

**Language — PHP 8.5 + TypeScript 7.** `composer.json` requires `php: ^8.5`. Backend typing is enforced by Larastan 3.12 at level 7 (`phpstan.neon`, no baseline) over `app/`, `config/`, `database/`, `routes/`; 151 of 152 functions in `app/` declare a return type. None of the 61 PHP files in `app/` declare `strict_types=1`. Frontend is TypeScript `^7.0.2` with `strict: true` and `noImplicitAny: true` in `tsconfig.json`.

**Framework — Laravel 13.32 + Inertia v3.3 + React 19.** Built from Laravel's official React starter kit (`composer.json` name `laravel/react-starter-kit`). Supporting packages: Fortify 1.39 (auth), Wayfinder 0.1.21 (typed route functions for the frontend), spatie/laravel-backup 10, shadcn/ui (`components.json`, `resources/js/components/ui/`), Tailwind v4. Database: PostgreSQL 18 (`compose.yaml`, `docker-compose.prod.yml`). Established code path: route → controller → Form Request → Action class (`app/Actions/<Domain>/*::handle()`) → API Resource → Inertia page in `resources/js/pages/<domain>/`; ownership via Policies comparing `tree_id` and returning `denyAsNotFound()`.

**Build tool — Vite 8 via vite-plus 0.3.0.** `package.json` scripts call the `vp` CLI (`vp build`, `vp dev`, `vp check`). `vite.config.ts` imports `defineConfig`/`lazyPlugins` from `vite-plus` and carries the `lint` (Oxlint, type-aware, `denyWarnings`) and `fmt` (Oxfmt) configuration — there is no ESLint or Prettier config in the repo. Plugins: laravel-vite-plugin, `@inertiajs/vite`, React + React Compiler, Tailwind, Wayfinder.

**Test runner — Pest 5.2 (PHPUnit 13).** `tests/Feature` covers auth, persons, marriages, parent–child relations, scans, event–scan links, thumbnails, backups; `tests/Unit` covers partial dates. No frontend tests exist, although Vite+ bundles Vitest (`vp test`).

**Package manager — composer + npm.** `composer.lock` and `package-lock.json`; CI uses `cache: npm`. A `pnpm-workspace.yaml` is also present (starter leftover, no `pnpm-lock.yaml`).

**CI/CD — GitHub Actions.** `.github/workflows/lint.yml` (Pint + `npm run check`), `static-analysis.yml` (Larastan + `tsc --noEmit`), `tests.yml` (Pest). Deploys are manual: `scripts/deploy.sh` on the VPS builds `docker-compose.prod.yml` after merge to `main`.

**Instruction files.** `CLAUDE.md` (10xDevs UI contract + Laravel Boost guidelines for PHP, Sail, Inertia v3, Wayfinder, Pint, Pest), Laravel Boost MCP (`search-docs`), project skills under `.claude/skills/`. `CLAUDE.md` asks agents to read `.ai/rules/index.md`, but `.ai/rules` does not exist.

## Quality Gate Assessment

| Component                          | Typed | Convention | Training Data | Documented | Verdict |
|------------------------------------|-------|------------|---------------|------------|---------|
| Language: PHP 8.5                  | ✓     | —          | —             | —          | pass    |
| Language: TypeScript 7             | ✓     | —          | —             | —          | pass    |
| Framework: Laravel 13 + Inertia v3 + React 19 | — | ✓   | ✓             | ✓          | pass    |
| Build tool: vite-plus 0.3.0 (Vite 8) | —   | ✓          | ✗             | ✓          | fail    |
| Test runner: Pest 5                | —     | —          | ✓             | ✓          | pass    |

Legend: ✓ = pass, ✗ = fail, ~ = partial, — = not applicable

### Gate Details

**Typed**
- PHP ✓ — `phpstan.neon` includes `vendor/larastan/larastan/extension.neon` at `level: 7`, no `ignoreErrors`, no baseline; run in CI by `static-analysis.yml` (`composer types:check`). 151/152 functions in `app/` have return types; Form Requests exist for every write endpoint (`app/Http/Requests/*`). Note: `declare(strict_types=1)` is in 0/61 files; `tech-stack.md` listed it as a typing compensation until 2026-09-28 — see Gaps.
- TypeScript ✓ — `tsconfig.json`: `"strict": true`, `"noImplicitAny": true`; `npm run types:check` (`tsc --noEmit`) in CI; Wayfinder generates typed route functions.

**Convention-based**
- Laravel ✓ — strongest-convention framework in PHP (fixed layout for routes, controllers, requests, policies, migrations, factories). The repo follows it and adds one consistent layer: `app/Actions/<Domain>/` classes with a `handle()` method.
- vite-plus ✓ — single `vite.config.ts` holds build, lint and format config; scripts in `package.json`.

**Popular in training data** (assessed within each language family)
- Laravel ✓ — the mainstream PHP web framework; Inertia + React is Laravel's official React starter path. Caveat: installed majors (Laravel 13, Inertia v3, Pest 5, TypeScript 7) are recent, so the agent's priors skew to older APIs (e.g. Inertia v2 `router.cancel()`, Axios). Already mitigated by `CLAUDE.md`'s "Inertia v3" section and the Boost `search-docs` rule.
- Pest ✓ — the default test framework of current Laravel starters.
- vite-plus ✗ — 0.3.0, pre-1.0 and recent; little training-data presence even within the JS family. Agents will default to ESLint/Prettier/`vite` CLI idioms that do not exist in this repo (`npx eslint`, `.prettierrc`, `import { defineConfig } from 'vite'`).

**Well-documented**
- Laravel / Inertia / Pest ✓ — versioned official docs, reachable via Boost `search-docs` scoped to installed versions.
- vite-plus ✓ — official docs at https://viteplus.dev/guide/ and, more usefully, version-matched local docs bundled in `node_modules/vite-plus/docs/` plus an agent guide at `node_modules/vite-plus/AGENTS.md`.

## Gaps & Compensation

### 1. vite-plus is not in the agent's training data (build tool, training data ✗)

**Why it matters for this change:** every slice of `prd-v2` adds frontend code (scan form fields, place picker with suggestions, residence chips, gazetteer page). The agent's verification loop ends with `npm run check` / `check:fix`; if it reaches for ESLint/Prettier or edits a nonexistent config, it will either fail the lint CI job or add a second toolchain.

**Compensation:** tell the agent the toolchain explicitly and point it at the bundled, version-matched docs (entry below).

### 2. Recent major versions (framework, not a gate failure — hardening)

**Why it matters:** the change adds new routes, controllers and forms. Wayfinder 0.1.x (pre-1.0) and Inertia v3 form helpers are where outdated priors are most likely to leak in.

**Compensation:** a pinned-versions block, so the agent does not have to rediscover them each session (entry below). The existing Boost `search-docs` rule stays the primary mechanism.

### 3. `strict_types` claimed but absent (typing — resolved)

`tech-stack.md` named strict_types as a typing compensation, but no file declares it and `pint.json` (`"preset": "laravel"`) does not enforce it. Larastan level 7 already carries the typed gate, so this was not a failure — but the documented contract and the code disagreed.

**Resolved 2026-09-28:** the claim was dropped from `tech-stack.md`; strict_types is not enforced. Enforcing it (Pint `declare_strict_types` + full test run) remains possible later.

### 4. Change-scope conventions (framework — hardening for prd-v2)

The change introduces a new tree-owned entity (places), a person↔place many-to-many (residences), place references on events, and new scan fields. The existing pattern (Form Request → Action → Resource, Policy on `tree_id` with `denyAsNotFound`) is visible in code but not written down; writing it down keeps the new gazetteer module from drifting (entry below).

### Recommended Instruction File Additions

Paste into `CLAUDE.md` (above the `<laravel-boost-guidelines>` block, which Boost regenerates).

**Frontend toolchain (Vite+) — compensates gap 1**

```markdown
## Frontend toolchain — Vite+ (vite-plus 0.3.0)

- The frontend toolchain is Vite+ (`vp` CLI), not plain Vite. Lint = Oxlint, format = Oxfmt, both configured in the `lint` and `fmt` blocks of `vite.config.ts`. There is no ESLint or Prettier in this repo — never add `.eslintrc*`, `eslint.config.*`, `.prettierrc*` or install those packages.
- `vite.config.ts` imports `defineConfig` and `lazyPlugins` from `'vite-plus'`, not from `'vite'`. Add new plugins inside the `lazyPlugins(() => [...])` array.
- Commands (run through Sail): `vendor/bin/sail npm run check` (format + lint, must pass in CI), `vendor/bin/sail npm run check:fix` (auto-fix), `vendor/bin/sail npm run types:check` (tsc), `vendor/bin/sail npm run build`.
- Lint runs with `denyWarnings: true` and `typeAware: true`: a warning fails CI. Fix the code; do not add ignore patterns except for generated dirs (`resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`) and shadcn `resources/js/components/ui/*`.
- Before changing toolchain config, read the version-matched docs in `node_modules/vite-plus/docs/guide/` (`check.md`, `lint.md`, `fmt.md`, `build.md`) and `node_modules/vite-plus/AGENTS.md` — your training data likely predates this tool.
```

**Pinned versions — compensates gap 2**

```markdown
## Installed versions (check before relying on an API)

PHP 8.5 · Laravel 13 · Inertia v3 (inertia-laravel 3.3, @inertiajs/react 3) · React 19 (React Compiler on) · TypeScript 7 · Tailwind v4 · Pest 5 / PHPUnit 13 · Larastan 3 (level 7) · Fortify 1.39 · Wayfinder 0.1 (pre-1.0) · PostgreSQL 18 · Vite 8 via vite-plus 0.3.

- These are newer than most training data. Use Boost `search-docs` for Laravel/Inertia/Pest APIs instead of recalling them.
- Frontend routes come from Wayfinder: import from `@/actions/...` or `@/routes/...`; never hardcode URLs. After adding or renaming a route, regenerate with `vendor/bin/sail artisan wayfinder:generate` (the Vite plugin also does it in dev).
```

**Domain conventions — compensates gap 4**

```markdown
## Backend conventions for tree-owned data

- Every domain record belongs to a tree (`tree_id`). Scope reads through the user's tree (`$request->user()->tree->scans()`), never through a global query.
- Authorization: one Policy per model in `app/Policies/`; ownership check is `$model->tree_id === $user->tree->id`, denial is `Response::denyAsNotFound()` (another user's record is a 404, not a 403).
- Write path: controller → Form Request (`app/Http/Requests/<Model><Action>Request.php`) → Action class (`app/Actions/<Domain>/<Verb><Model>.php` with a `handle()` method) → redirect with `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`. UI strings are Polish.
- Read path: pass data to Inertia pages through API Resources (`app/Http/Resources/`) with `->resolve()`; pages live in `resources/js/pages/<domain>/{index,create,show,edit}.tsx`, domain components in `resources/js/components/<domain>/`.
- Deleting a record that others reference shows the affected items first and requires explicit confirmation (pattern: `resources/js/components/scans/delete-scan-dialog.tsx`).
- Every new endpoint gets a Pest feature test in `tests/Feature/<Domain>Test.php` covering the happy path and the other-user 404.
```

All three blocks were added to `CLAUDE.md` on 2026-09-28, between the 10x-cli block and the Boost guidelines.

## Summary

**Overall agent-readiness: ready with compensation.** 9 of 10 applicable checks pass.

**Strengths:** a mainstream, convention-heavy PHP framework on its official React starter; real type discipline on both sides (Larastan level 7 without a baseline, strict TypeScript, typed routes via Wayfinder); lint, static analysis and tests all gate CI; a consistent Action/Policy/Resource layer; Laravel Boost gives the agent version-scoped docs.

**Gaps:** the one failed check is the build toolchain — vite-plus 0.3 is too new for the agent to know, which a short `CLAUDE.md` block plus the bundled local docs fixes. Hardening items: pin installed versions, write down the tree-ownership conventions before the gazetteer module lands (all done in `CLAUDE.md`); the `strict_types` mismatch with `tech-stack.md` was resolved by dropping the claim. Minor: `CLAUDE.md` points to a nonexistent `.ai/rules/`, and `pnpm-workspace.yaml` sits beside an npm lockfile.

**Hand-off to the health check:** no frontend tests (Vitest is available through `vp test`); the backup scope must cover the new data (PRD constraint); confirm whether the `.ai/rules` pointer and `pnpm-workspace.yaml` are intentional.

**Next step:** `/10x-health-check`.
