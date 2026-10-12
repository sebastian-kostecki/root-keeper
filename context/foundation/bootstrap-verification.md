---
bootstrapped_at: 2026-09-16T16:43:32Z
starter_id: laravel
starter_name: Laravel
project_name: root-keeper
language_family: php
package_manager: composer
cwd_strategy: subdir-then-move
bootstrapper_confidence: verified
phase_3_status: ok
audit_command: "null"
---

# Bootstrap verification — root-keeper

## Hand-off

```yaml
starter_id: laravel
package_manager: composer
project_name: root-keeper
hints:
  language_family: php
  team_size: solo
  deployment_target: self-host
  ci_provider: github-actions
  ci_default_flow: auto-deploy-on-merge
  bootstrapper_confidence: verified
  path_taken: custom
  quality_override: true
  self_check_answers:
    typed: true
    from_official_starter: true
    conventions: true
    docs_current: true
    can_judge_agent: true
  has_auth: true
  has_payments: false
  has_realtime: false
  has_ai: false
  has_background_jobs: false
  frontend_framework: react-inertia-typescript
```

> **Why this stack**
>
> A solo developer building a genealogy web app in a 7-week after-hours window (hard deadline 2026-11-04) needs registration, login, per-user data isolation and private scan storage without spending the budget on foundations. The PRD requires that data and scans never reach third parties, and the owner self-hosts with Docker on a VPS behind Traefik, which ruled out managed-services stacks. Laravel won over Go: its filesystem layer keeps scans on a local volume served only through authorized routes, and Eloquent with migrations fits the person–marriage–parent-child model. The frontend is React with TypeScript via Inertia, from Laravel's official React starter kit: it ships registration and login, needs no separate API, and React's ecosystem best serves the graphical tree view and zoomable scan preview. Laravel fails the typed gate, accepted knowingly: compensation is strict_types, typed properties and return types, Larastan in CI, Form Requests, ownership policies, and TypeScript on the frontend. CI runs on GitHub Actions with auto-deploy on merge.

**Schema drift**: `hints.frontend_framework` is not part of the hand-off schema. Logged; not acted on as a key.

### Session overrides (hand-off file on disk unchanged)

At the confirm step the user asked to (1) use Laravel Sail for local development and (2) include the frontend part. Applied for this run only:

| Field / step              | Registry card value                                                   | Used in this run                                                                     |
| ------------------------- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Scaffold command          | `composer create-project laravel/laravel {name} --no-interaction --prefer-dist` | `composer create-project laravel/react-starter-kit {name} dev-main --no-interaction --prefer-dist` |
| Post-scaffold dev env     | —                                                                     | `php artisan sail:install --with=pgsql --no-interaction` (DB chosen by user: PostgreSQL) |

Rationale: the registry card scaffolds a Blade-only skeleton, while the hand-off rationale names Laravel's official React starter kit (React + TypeScript + Inertia + auth). `dev-main` is what the official Laravel installer uses; the only tagged release (v1.0.1, 2025-02-24) targets Laravel 12.

## Pre-scaffold verification

| Signal                       | Value                                                   | Severity | Notes                                                              |
| ---------------------------- | ------------------------------------------------------- | -------- | ------------------------------------------------------------------ |
| npm package                  | not run                                                 | —        | non-JS starter (composer)                                          |
| GitHub repo (registry card)  | not run                                                 | —        | card `docs_url` is https://laravel.com/docs (not GitHub); `gh` CLI not installed |
| Packagist `laravel/react-starter-kit` | dev-main @ 0c94c26, updated 2026-09-14         | fresh    | override starter; tagged v1.0.1 is 2025-02-24 (stale, not used)    |
| GitHub `laravel/react-starter-kit`    | last pushed 2026-09-14T15:48:13Z               | fresh    | via api.github.com (curl)                                          |
| Packagist `laravel/sail`     | v1.67.0 published 2026-08-12                            | fresh    | override dev env                                                   |
| GitHub `laravel/sail`        | last pushed 2026-09-13T16:31:00Z                        | fresh    | via api.github.com (curl)                                          |

Local toolchain observed: PHP 8.3.33 (XAMPP), Composer 2.8.11, Node v24.5.0, npm 11.5.1, Docker 28.3.2 (Docker Desktop, WSL 2).

## Scaffold log

**Resolved invocation**: `composer create-project laravel/react-starter-kit .bootstrap-scaffold dev-main --no-interaction --prefer-dist`
**Strategy**: subdir-then-move
**Exit code**: 0
**Files moved**: 288 files (excluding `vendor/` and `node_modules/`, moved as whole directories); 30 top-level entries
**Conflicts (.scaffold siblings)**: none
**.gitignore handling**: moved silently (absent in cwd)
**.bootstrap-scaffold cleanup**: deleted

Notes from CLI output:

- Installed Laravel Framework 13.32.0 plus Inertia (inertia-laravel ^3.0), Fortify, Wayfinder, Passkeys, Chisel, Sail.
- Post-create scripts ran: `install:features` (non-interactive → all auth features kept: registration, password reset, email verification, 2FA, passkeys, password confirmation), npm install, Wayfinder generation, asset build (`public/build`), `key:generate`, SQLite file creation, `migrate --graceful` (users, cache, jobs, passkeys, two-factor columns) against local SQLite.
- Composer's built-in install-time audit: "No security vulnerability advisories found."

Top-level entries moved: `.editorconfig`, `.env`, `.env.example`, `.gitattributes`, `.github/` (dependabot.yml, workflows/tests.yml), `.gitignore`, `.npmrc`, `app/`, `artisan`, `bootstrap/`, `components.json`, `composer.json`, `composer.lock`, `config/`, `database/`, `node_modules/`, `package-lock.json`, `package.json`, `phpstan.neon`, `phpunit.xml`, `pint.json`, `pnpm-workspace.yaml`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`, `tsconfig.json`, `vendor/`, `vite.config.ts`.

Preserved untouched: `context/`, `.claude/`, `.cursor/`, `.agents/`, `.git/`, `CLAUDE.md`, `.mcp.json`, `.10x-cli.json`, `skills-lock.json`.

**Heads-up — scaffold `.gitignore`**: it ignores `/.claude`, `/.cursor`, `/.agents`, `/.mcp.json`, `/CLAUDE.md`, `/AGENTS.md`. These are already tracked in this repo (tracked files stay tracked), but new files under those paths will not be picked up by `git add` until the entries are removed.

### Sail install (session override)

**Invocation**: `php artisan sail:install --with=pgsql --no-interaction`
**Exit code**: 0 (stderr printed a Windows "path not found" line; scaffolding reported success)
**Created**: `compose.yaml` — services `laravel.test` (Sail runtime PHP 8.5, ports 80 / 5173) and `pgsql` (`postgres:18-alpine`, port 5432, testing DB init script)
**`.env` changes**: `DB_CONNECTION=pgsql`, `DB_HOST=pgsql`, `DB_PORT=5432`, `DB_DATABASE=laravel`, `DB_USERNAME=sail`, `DB_PASSWORD` set, `PHP_CLI_SERVER_WORKERS=4` uncommented
**Not changed**: `.env.example` still has `DB_CONNECTION=sqlite`
**Not run**: `sail up`, `sail artisan migrate` (containers not started)

## Post-scaffold audit

**Tool**: skipped — no built-in audit tool for php
**Recommended external tool**: `composer audit` (PHP); `npm audit` for the frontend part

Supplementary run (outside the v1 lookup table, because the scaffold includes a JS frontend):

**`composer audit --format=json`**: exit 0 — 0 advisories, 0 abandoned packages.

**`npm audit --json`**: 0 CRITICAL, 0 HIGH, 0 MODERATE, 1 LOW (395 dependencies: 137 prod, 146 dev)
**Direct vs transitive**: 0/0/0/0 direct of total 0/0/0/1

#### CRITICAL findings

none

#### HIGH findings

none

#### MODERATE findings

none

#### LOW / INFO findings

- `@babel/core` (transitive), affected range `8.0.0-alpha.0 - 8.0.0-rc.4` — Arbitrary File Read via sourceMappingURL Comment — GHSA-4x5r-pxfx-6jf8 — fix available.

## Hints recorded but not acted on

| Hint                            | Value                      |
| ------------------------------- | -------------------------- |
| bootstrapper_confidence         | verified                   |
| quality_override                | true                       |
| path_taken                      | custom                     |
| self_check_answers.typed        | true                       |
| self_check_answers.from_official_starter | true              |
| self_check_answers.conventions  | true                       |
| self_check_answers.docs_current | true                       |
| self_check_answers.can_judge_agent | true                    |
| team_size                       | solo                       |
| deployment_target               | self-host                  |
| ci_provider                     | github-actions             |
| ci_default_flow                 | auto-deploy-on-merge       |
| has_auth                        | true                       |
| has_payments                    | false                      |
| has_realtime                    | false                      |
| has_ai                          | false                      |
| has_background_jobs             | false                      |
| frontend_framework (off-schema) | react-inertia-typescript (honoured via session override, not as a hint) |

`quality_override: true` — the typed gate was knowingly passed during stack selection. Scaffolding does not compensate (`strict_types`, Larastan in CI, etc. remain manual). Note: the starter ships `phpstan.neon`.

## Next steps

Next: a future skill will set up agent context (CLAUDE.md, AGENTS.md). For now, your project is scaffolded and verified — happy hacking.

Useful manual steps in the meantime:
- `git init` (if you have not already) to start your own repo history.
- Review any `.scaffold` siblings the conflict policy created and decide which version of each file to keep.
- Address audit findings per your project's risk tolerance — the full breakdown is in this log.
- Start the dev environment: `./vendor/bin/sail up -d`, then `./vendor/bin/sail artisan migrate` and `./vendor/bin/sail npm run dev`.
- Review the `.gitignore` entries for `/.claude`, `/CLAUDE.md`, `/.agents`, `/.cursor`, `/.mcp.json`.
- Trim unused auth features (e.g. passkeys, 2FA) if not needed for the MVP.
