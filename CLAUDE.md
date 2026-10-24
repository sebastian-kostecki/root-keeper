## Research data

- Real research data (`/mnt/d/my-projects/genealogy-project`, `storage/gen-tree-import/`) never enters the repo, fixtures, factories, seeders or CI. Import tests use a small invented repo under `tests/Fixtures/research-repo/` written for the test. Keep `/storage/gen-tree-import` in `.gitignore`.
- The research repo is read-only for the app: the import never writes, moves or deletes files there; scans are copied into the app's storage.
- On production the import takes a DB backup (`backup:run --only-db`) after the user approves changes and before the first write; if the backup fails, nothing is written.

## Backend conventions for tree-owned data

- Every domain record belongs to a tree (`tree_id`). Scope reads through the user's tree (`$request->user()->tree->sources()`), never through a global query.
- Authorization: one Policy per model in `app/Policies/`; ownership check is `$model->tree_id === $user->tree->id`, denial is `Response::denyAsNotFound()` (another user's record is a 404, not a 403).
- Write path: controller → Form Request (`app/Http/Requests/<Model><Action>Request.php`) → Action class (`app/Actions/<Domain>/<Verb><Model>.php` with a `handle()` method) → redirect with `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`. UI strings are Polish.
- Read path: pass data to Inertia pages through API Resources (`app/Http/Resources/`) with `->resolve()`; pages live in `resources/js/pages/<domain>/{index,create,show,edit}.tsx`, domain components in `resources/js/components/<domain>/`.
- Deleting a record that others reference shows the affected items first and requires explicit confirmation (pattern: `resources/js/components/sources/delete-source-dialog.tsx`).
- Every new endpoint gets a Pest feature test in `tests/Feature/<Domain>Test.php` covering the happy path and the other-user 404.
- Places, parishes / offices and cemeteries follow the same tree scoping and Policy as every other record. Uniqueness is per tree: a cemetery by locality + name (the name may be empty), a parish / office by seat + kind + religion + dedication (the seat is required). Invariant: a burial's place is the cemetery (`events.cemetery_id`) and `events.locality_id` must always equal that cemetery's locality.
- Merging a duplicate (place, parish or cemetery) moves every reference to the target inside one DB transaction, then deletes the duplicate; both records must belong to the same tree. A new table that references a place, parish or cemetery must be added to the merge Action and to that record type's merge test (it covers every reference type and the other-user 404). Merging a locality first merges the duplicate's cemeteries that collide by name, and its parishes / offices that collide by kind, religion and dedication, with the target's.
- Deleting a place, parish or cemetery is allowed only when nothing references it.
- Miejscowości, parafie i urzędy oraz cmentarze to jedna strona `/places` z trzema zakładkami („Miejscowości”, „Parafie i urzędy”, „Cmentarze”; przełącznik `?kind=`) i jednym wpisem „Miejsca” w menu; nie dodawaj osobnej listy ani wpisu. Stare `/localities` i `/parishes` tylko przekierowują (cmentarze nigdy nie miały osobnego adresu), a segmenty z licznikiem 0 nie są wyłączane (w odróżnieniu od `/sources`), bo pusta zakładka dodaje pierwszy rekord.

## Production dependencies

- Production builds with `composer install --no-dev` (`docker/8.5/Dockerfile.prod`); CI and Sail install dev packages. A class used from `app/` must come from a package in `require`, not only from the lockfile.
- Before using a package's class in `app/`, run `vendor/bin/sail composer why <vendor/package>`: if only `require-dev` packages pull it in (e.g. `symfony/yaml` through `laravel/sail`), it is missing on production. Adding it to `require` is a new dependency; every new dependency (Composer or `package.json`) needs the user's approval.

## Migrations that move existing data

- A migration that rewrites existing rows (free text → reference, field → other field) gets its own Pest test in `tests/Feature/<Name>MigrationTest.php`, following `tests/Feature/SourceMigrationTest.php`: insert rows in the old shape, run the migration, assert the new shape and that no value was lost.
- Copy first, drop later: never drop the source column in the same migration that copies from it. Values the user must transfer by hand (e.g. the old source "Numer wpisu") stay readable until the user removes them.
- Migrations must be reversible (`down()`) or state in a comment why they cannot be.
- Production runs `backup:run --only-db` before `migrate` (`scripts/deploy.sh`); do not remove that step.
- `events.date_is_approximate` is a legacy mirror of `events.date_qualifier` (copied by migration `2026_10_23_123108`) and is derived on model save only (`Event::booted()`): a query-builder bulk update of the qualifier leaves it stale, so change the qualifier through the model. Dropping the boolean is its own later change with a `MigrationTest`.

## Frontend toolchain — Vite+ (vite-plus 1.3.0)

- The frontend toolchain is Vite+ (`vp` CLI), not plain Vite. Lint = Oxlint, format = Oxfmt, both configured in the `lint` and `fmt` blocks of `vite.config.ts`. There is no ESLint or Prettier in this repo — never add `.eslintrc*`, `eslint.config.*`, `.prettierrc*` or install those packages.
- `vite.config.ts` imports `defineConfig` and `lazyPlugins` from `'vite-plus'`, not from `'vite'`. Add new plugins inside the `lazyPlugins(() => [...])` array.
- The package manager is npm (`package-lock.json`). In `package.json`, `vite` is an alias (`npm:@voidzero-dev/vite-plus-core@<version>`) and `overrides` pin `vite` (`$vite`) and `vitest` to the versions Vite+ bundles. Never replace the alias with plain `vite`, never add `pnpm-workspace.yaml` or `pnpm-lock.yaml`. When bumping `vite-plus`, bump the alias and the `vitest` pin with it (`vendor/bin/sail npx vp toolchain` shows the bundled versions); do not run `vp migrate` — it detects the wrong package manager here.
- Commands (run through Sail): `vendor/bin/sail npm run check` (format + lint, must pass in CI), `vendor/bin/sail npm run check:fix` (auto-fix), `vendor/bin/sail npm run types:check` (tsc), `vendor/bin/sail npm run test:js` (Vitest via `vp test`), `vendor/bin/sail npm run build`.
- Lint runs with `denyWarnings: true` and `typeAware: true`: a warning fails CI. Fix the code; do not add ignore patterns except for generated dirs (`resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`) and shadcn `resources/js/components/ui/*`.
- Before changing toolchain config, read the version-matched docs in `node_modules/vite-plus/docs/guide/` (`check.md`, `lint.md`, `fmt.md`, `build.md`) and `node_modules/vite-plus/AGENTS.md` — your training data likely predates this tool.
- UI rules (design tokens, shared components, tree cards, side panels vs modals) live in `resources/js/CLAUDE.md`; it loads automatically when you work under `resources/js/`.

## Installed versions (check before relying on an API)

PHP 8.5 · Laravel 13 · Inertia v3 (inertia-laravel 3.5, @inertiajs/react 3.8) · React 19 (React Compiler on) · TypeScript 7 · Tailwind v4 · Pest 5 / PHPUnit 13 · Larastan 3 (level 7) · Fortify 1.40 · Wayfinder 0.1 (pre-1.0) · PostgreSQL 18 · Vite 8 via vite-plus 1.3 · Vitest 5 (run as `vp test`) · Playwright 1.63 · react-zoom-pan-pinch 4.

- These are newer than most training data. Use Boost `search-docs` for Laravel/Inertia/Pest APIs instead of recalling them.
- Frontend routes come from Wayfinder: import from `@/actions/...` or `@/routes/...`; never hardcode URLs. After adding or renaming a route, regenerate with `vendor/bin/sail artisan wayfinder:generate` (the Vite plugin also does it in dev).
- Before adding a frontend dependency, check `package.json`: zoom/pan already uses `react-zoom-pan-pinch` (`resources/js/components/sources/source-viewer.tsx`); UI primitives come from `resources/js/components/ui/`.
- Laravel Prompts 0.3 (interactive Artisan prompts) and league/commonmark 2.10 come through `laravel/framework`; `symfony/yaml` is in `composer.lock` only through dev packages (see "Production dependencies").

## Research repo import (console write path)

- The import is an Artisan command in `app/Console/Commands/` (attributes `#[Signature]` / `#[Description]`, as in `RestoreBackup`). The command only resolves arguments, prints and asks; parsing, matching and writing live in `app/Actions/Import/*` with `handle()`.
- Resolve the tree once from the account e-mail (`User::where('email', …)->firstOrFail()->tree`) and pass it down. Every read and match goes through that tree (`$tree->persons()`, `$tree->localities()`), never `Person::query()` or another global query. `RestoreBackup` works on the whole instance and is not a template for this.
- No Policy runs here, so validation is explicit: each data block is validated (`Validator::make` with rules) before it reaches an Action; an invalid entry is skipped and reported with its file and reason, the rest continues.
- Split decide from apply: one Action builds a list of proposed changes (new, changed, conflict, match to confirm, removed in repo) without writing; the command reviews it with Laravel Prompts; another Action writes the approved changes in one DB transaction. Test the planning Action directly, and the command through `$this->artisan(...)` with `expectsQuestion` / `expectsChoice` / `expectsConfirmation` (check current API with `search-docs`).
- The person block format is defined in `context/foundation/research-block-contract.md`; changing the meaning or name of a field needs a bumped `wersja`. `symfony/yaml` (the block parser) is in `require`.
- Every import feature test has a second user with overlapping data (same names, same places) and asserts nothing of theirs is matched or written.
- Records carry the stable repo ID from the block, never the file name; records without a repo ID (added only in the app) are never updated or deleted by import.

<!-- BEGIN @przeprogramowani/10x-cli -->

## Zestaw narzędzi AI 10xDevs — Moduł 3, Lekcja 4 (testy E2E)

**W przypadku testów E2E używaj dwóch umiejętności M3L4 w tej kolejności:**

1. **`/10x-e2e-setup`** — jednorazowa konfiguracja: konfiguracja Playwright (`webServer`,
   projekt uwierzytelniania `setup`, `storageState`), zielony test seed oraz `context/foundation/test-stack.md`.
2. **`/10x-e2e`** — pętla dla każdego ryzyka: ryzyko → eksploracja uruchomionej aplikacji za pomocą
   `playwright-cli` → generowanie → przegląd względem pięciu antywzorców → ponowne zapytanie po nazwie → weryfikacja poprzez celowe wprowadzenie błędu.

Katalogi `references/` umiejętności zawierają pełne reguły, antywzorce, wzorzec seed oraz
szablon promptu.

Kilka twardych zasad obowiązujących jeszcze przed wywołaniem umiejętności:

- **Lokatory:** najpierw `getByRole` / `getByLabel` / `getByText`; `getByTestId`
  tylko wtedy, gdy atrybuty dostępności są niejednoznaczne. Nigdy selektory CSS, XPath
  ani struktura DOM.
- **Nigdy `page.waitForTimeout()`.** Czekaj na stan: `toBeVisible()`,
  `waitForURL()`, `waitForResponse()`.
- **Niezależność testów + czyszczenie.** Każdy test uruchamia się samodzielnie — własna konfiguracja,
  akcja, asercja i czyszczenie; unikalne identyfikatory (sufiks timestamp), aby równoległe uruchomienia
  i ponowne uruchomienia nie kolidowały.

Dwie granice, które należy jasno rozróżniać:

- **DOM (snapshot) jest domyślny.** Vision (`--caps=vision`) stanowi uzupełnienie dla
  ryzyk wyłącznie wizualnych (układ, z-index, animacja); do regresji pikselowych preferuj
  deterministyczne narzędzia (`toHaveScreenshot`, Argos, Lost Pixel). Wybór/koszt modelu VLM
  to temat debugowania (Lekcja 5), a nie testowania.
- **Czerwony test jest sygnałem, a nie obowiązkiem.** Zmieniony selektor → zaktualizuj
  lokator w sprawdzonym diffie. Zmienione zachowanie biznesowe → test wykrył
  błąd; nigdy nie edytuj asercji, aby je dopasować. Naprawianie nieudanych testów to Lekcja 5.

<!-- END @przeprogramowani/10x-cli -->

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- Backend code follows the write and read paths in "Backend conventions for tree-owned data" (Form Request → Action with `handle()` → `Inertia::flash`; API Resource with `->resolve()`). When creating a file, copy the structure and naming of its sibling files.
- Before finishing a change that touches PHP, run `vendor/bin/sail bin pint --dirty --format agent` and `vendor/bin/sail composer types:check` (Larastan level 7) with zero new errors. For frontend files run `vendor/bin/sail npm run check` and `vendor/bin/sail npm run types:check`.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval. New dependencies follow "Production dependencies".

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Pint (preset `laravel`, `pint.json`) fixes formatting and braces and Larastan (level 7, `phpstan.neon`) reports missing type hints: run them (see Conventions) instead of restating style rules.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Enum keys are TitleCase and string-backed, as in `app/Enums/LocalityKind.php` (`case City = 'city'`).
- Prefer PHPDoc blocks (with array shape types) over inline comments. An inline comment is allowed in two cases only: a Larastan annotation (`@var`, `@phpstan-ignore-next-line` with the reason) or a migration stating why `down()` cannot restore the data.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail: prefix every `php`, `artisan`, `composer`, `npm` and `npx` call with `vendor/bin/sail`.
- If the containers are down, start them with `vendor/bin/sail up -d`.

=== tests rules ===

# Test Enforcement

- A test is required when a change touches: a new endpoint (happy path + the other user's 404, see "Backend conventions for tree-owned data"), a migration that moves data (`tests/Feature/<Name>MigrationTest.php`), the research import (a second user with overlapping data), or a view file scanned by `resources/js/lib/view-tokens.test.ts`.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- Run the affected tests after each change and make sure they pass. Test the changed behavior and its failure modes, nothing beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Create files with `vendor/bin/sail artisan make:...` and pass `--no-interaction` to every Artisan command; `make:test` always gets `--pest`. Check other flags with `vendor/bin/sail artisan <command> --help` before using them.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

</laravel-boost-guidelines>
