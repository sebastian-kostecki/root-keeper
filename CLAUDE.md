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

## Frontend toolchain — Vite+ (vite-plus 1.0.0)

- The frontend toolchain is Vite+ (`vp` CLI), not plain Vite. Lint = Oxlint, format = Oxfmt, both configured in the `lint` and `fmt` blocks of `vite.config.ts`. There is no ESLint or Prettier in this repo — never add `.eslintrc*`, `eslint.config.*`, `.prettierrc*` or install those packages.
- `vite.config.ts` imports `defineConfig` and `lazyPlugins` from `'vite-plus'`, not from `'vite'`. Add new plugins inside the `lazyPlugins(() => [...])` array.
- The package manager is npm (`package-lock.json`). In `package.json`, `vite` is an alias (`npm:@voidzero-dev/vite-plus-core@<version>`) and `overrides` pin `vite` (`$vite`) and `vitest` to the versions Vite+ bundles. Never replace the alias with plain `vite`, never add `pnpm-workspace.yaml` or `pnpm-lock.yaml`. When bumping `vite-plus`, bump the alias and the `vitest` pin with it (`vendor/bin/sail npx vp toolchain` shows the bundled versions); do not run `vp migrate` — it detects the wrong package manager here.
- Commands (run through Sail): `vendor/bin/sail npm run check` (format + lint, must pass in CI), `vendor/bin/sail npm run check:fix` (auto-fix), `vendor/bin/sail npm run types:check` (tsc), `vendor/bin/sail npm run test:js` (Vitest via `vp test`), `vendor/bin/sail npm run build`.
- Lint runs with `denyWarnings: true` and `typeAware: true`: a warning fails CI. Fix the code; do not add ignore patterns except for generated dirs (`resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`) and shadcn `resources/js/components/ui/*`.
- Before changing toolchain config, read the version-matched docs in `node_modules/vite-plus/docs/guide/` (`check.md`, `lint.md`, `fmt.md`, `build.md`) and `node_modules/vite-plus/AGENTS.md` — your training data likely predates this tool.

## Installed versions (check before relying on an API)

PHP 8.5 · Laravel 13 · Inertia v3 (inertia-laravel 3.5, @inertiajs/react 3.8) · React 19 (React Compiler on) · TypeScript 7 · Tailwind v4 · Pest 5 / PHPUnit 13 · Larastan 3 (level 7) · Fortify 1.40 · Wayfinder 0.1 (pre-1.0) · PostgreSQL 18 · Vite 8 via vite-plus 1.0 · Vitest 5 (run as `vp test`) · Playwright 1.63 · react-zoom-pan-pinch 4.

- These are newer than most training data. Use Boost `search-docs` for Laravel/Inertia/Pest APIs instead of recalling them.
- Frontend routes come from Wayfinder: import from `@/actions/...` or `@/routes/...`; never hardcode URLs. After adding or renaming a route, regenerate with `vendor/bin/sail artisan wayfinder:generate` (the Vite plugin also does it in dev).
- Before adding a frontend dependency, check `package.json`: zoom/pan already uses `react-zoom-pan-pinch` (`resources/js/components/sources/source-viewer.tsx`); UI primitives come from `resources/js/components/ui/`. New dependencies need the user's approval.

## UI: tokens and shared components

- Tokens live in `resources/css/app.css` (`:root` / `.dark` mapped into `@theme`). Use role classes (`text-destructive-foreground`, `bg-accent`, `text-muted-foreground`). In views: no Tailwind palette classes (`text-red-600`), no hex, `rgb()` or `oklch()`, no arbitrary pixel values (`ring-[3px]`) and no inline `style=`.
- Before creating a component, check `resources/js/components/ui/` and the shared `components/{heading,search-field,segmented-toggle,empty-state,side-panel,table-parts}.tsx`. A missing shadcn component comes from `npx shadcn add <name>` (new dependencies need approval).
- Focus ring for elements outside `components/ui/`: `focusRing` from `resources/js/lib/focus-ring.ts`. List pages: `Heading variant="page"` and the search row with `role="search"`.
- Controls: `resources/js/lib/persons-view-tokens.test.ts` scans the `/persons` view files (runs in `npm run test:js`). Visual gate: screenshots in `context/changes/<id>/screenshots/`.

## Side panels vs modals

- Person, source, marriage and locality open in a right-hand side panel, not a modal. Tree add/choose person (slots, spouse menu, empty tree) happens in the add panel `?add=` (reference: `resources/js/components/tree/add-person-panel.tsx`). Creating a person from `/persons` is the same add panel as `?add=person` (a bare form), not a modal. Modals remain for delete confirmations, `MarryCoupleDialog` and small pickers without a form (parish inline, buried pick-person-dialog) (`resources/js/components/ui/dialog.tsx`).
- An open panel must not cover the tree: the tree area narrows beside it. Do not use an overlay for these panels — `resources/js/components/ui/sheet.tsx` is an overlay (Radix Dialog) and does not meet this as-is.
- Build one shared panel shell and reuse it for all four; the source panel is wider (scan beside the form).
- Closing a panel returns the user to the same tree position; moving to a relative replaces the panel content.

## Migrations that move existing data

- A migration that rewrites existing rows (free text → reference, field → other field) gets its own Pest test in `tests/Feature/<Name>MigrationTest.php`, following `tests/Feature/SourceMigrationTest.php`: insert rows in the old shape, run the migration, assert the new shape and that no value was lost.
- Copy first, drop later: never drop the source column in the same migration that copies from it. Values the user must transfer by hand (e.g. the old source "Numer wpisu") stay readable until the user removes them.
- Migrations must be reversible (`down()`) or state in a comment why they cannot be.
- Production runs `backup:run --only-db` before `migrate` (`scripts/deploy.sh`); do not remove that step.

## Backend conventions for tree-owned data

- Every domain record belongs to a tree (`tree_id`). Scope reads through the user's tree (`$request->user()->tree->sources()`), never through a global query.
- Authorization: one Policy per model in `app/Policies/`; ownership check is `$model->tree_id === $user->tree->id`, denial is `Response::denyAsNotFound()` (another user's record is a 404, not a 403).
- Write path: controller → Form Request (`app/Http/Requests/<Model><Action>Request.php`) → Action class (`app/Actions/<Domain>/<Verb><Model>.php` with a `handle()` method) → redirect with `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`. UI strings are Polish.
- Read path: pass data to Inertia pages through API Resources (`app/Http/Resources/`) with `->resolve()`; pages live in `resources/js/pages/<domain>/{index,create,show,edit}.tsx`, domain components in `resources/js/components/<domain>/`.
- Deleting a record that others reference shows the affected items first and requires explicit confirmation (pattern: `resources/js/components/sources/delete-source-dialog.tsx`).
- Every new endpoint gets a Pest feature test in `tests/Feature/<Domain>Test.php` covering the happy path and the other-user 404.
- Places and parishes are tree-owned records like every other: unique per tree, scoped through `$request->user()->tree`, Policy with `denyAsNotFound()`.
- Merging a duplicate (place or parish) moves every reference — parishes, gravestones, event places, burial places, residences — to the target inside one DB transaction, then deletes the duplicate. Both records must belong to the same tree; the merge test covers every reference type and the other-user 404.
- Deleting a place or parish is allowed only when nothing references it.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

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

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

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

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `vendor/bin/sail artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `vendor/bin/sail artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/sail bin pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `vendor/bin/sail artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
