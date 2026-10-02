<!-- BEGIN @przeprogramowani/10x-cli -->

## Zestaw narzędzi AI 10xDevs — Moduł 3, Lekcja 2

Lekcja 2 dotyczy **pisania testów, które faktycznie chronią kod** — a nie tylko maksymalizowania pokrycia. Problem wyroczni oraz antywzorce vibe-testingu wyjaśniają, dlaczego testy generowane przez LLM zawodzą na rzeczywistym kodzie; rozwiązaniem jest kontrakt jakości oparty na ryzyku z Lekcji 1.

```
context/foundation/test-plan.md (§3 Phased Rollout)
        │
        ▼  (one rollout phase at a time)
   /10x-research  ──►  research.md  (oracle source: what code should do, not what it does)
        │
        ▼
   /10x-plan  ──►  plan.md  (cost × signal, two-layer strategy, ordered phases)
        │
        ▼
   /10x-implement  or  /10x-tdd   ──►  working tests + §6 cookbook update
```

`/10x-tdd` jest **opcjonalnym trybem test-first**, a nie zamiennikiem dla łańcucha. Odczytuje ten sam `plan.md`, zapisuje do tej samej sekcji `## Progress` i obejmuje te same fazy co `/10x-implement`. Używaj go tylko wtedy, gdy potrafisz nazwać pierwszą nieudaną asercję przed napisaniem jakiegokolwiek kodu.

### Router zadań — od czego zacząć

| Umiejętność / Prompt | Użyj, gdy |
| --- | --- |
| `/10x-research` | Przed napisaniem jakiegokolwiek testu dla ryzyka. Research tworzy wyrocznię — jakie zachowanie test musi udowodnić — na podstawie źródeł (PRD, tech-stack, dokumentacja), a nie kształtu implementacji. Ujawnia również, czy ryzyko jest już pokryte albo ma dwa odrębne oblicza (jedno bezpieczne, drugie rzeczywiste). |
| `/10x-plan` | Research jest ukończony. Plan rozkłada ryzyko na uporządkowane fazy: najpierw konfiguracja środowiska, następnie reguły od niej zależne, potem hermetyczne stuby dla błędów, których rzeczywista infrastruktura nie potrafi wywołać, a na końcu aktualizacja cookbooka. Każda faza nazywa zachowanie, które potwierdza, oraz regresję, którą wykrywa. |
| `/10x-implement` | Domyślny wykonawca faz planu. Używaj do konfiguracji środowiska, istniejącego kodu, scaffolding oraz każdej fazy, w której nie możesz zdefiniować czerwonego testu przed napisaniem kodu. |
| `/10x-tdd` | Opcjonalne. Użyj zamiast `/10x-implement` dla fazy, w której potrafisz nazwać pierwszy czerwony test jednym zdaniem. Agent najpierw pisze nieudany test, potem minimalny kod, aby go zazielenić, a następnie refaktoryzuje. Zatrzymuje się na asercji przed dotknięciem implementacji — ta pauza jest sednem. |
| Prompt `m3l2-ad-hoc-testing` | Masz pojedynczy plik i chcesz testów teraz, bez pełnego cyklu research→plan→implement. Prompt wymusza wyrocznię-ze-źródeł (odczytuje PRD + TECH_STACK przed asercjami), asercje behawioralne, przypadki brzegowe wynikające z ryzyka oraz tabelę regresji. Używaj go ze świadomością, że wymieniasz głębię na szybkość. |

### Kiedy używać `/10x-tdd` vs `/10x-implement`

Decydujące pytanie: *Czy potrafisz nazwać pierwszy czerwony test jednym zdaniem?*

Dobre warunki dla `/10x-tdd`:
- "promuje wyłącznie drafty w stanie `accepted`, a `pending`/`rejected` nigdy nie trafiają do talii"
- "zwraca `ok: true` i loguje `orphan_review_state`, gdy upsert stanu powtórek padnie w trakcie zapisu"
- "zwraca 401, gdy użytkownik nie ma dostępu do kursu"
- "resetuje interwał powtórki do jednego dnia, gdy ocena wynosi 0"

Każdy z nich nazywa obserwowalny wynik, a nie wewnętrzny szczegół. Jeśli nie potrafisz stworzyć zdania takiego jak to, pozostań przy `/10x-implement` albo wróć do `/10x-research`.

`/10x-tdd` **nie nadaje się** do: konfiguracji środowiska, konfiguracji CI/CD, dokumentacji, prostego łączenia elementów, gdzie test jedynie przepisywałby implementację, ani spike'a, podczas którego nadal odkrywasz kontrakt.

Możesz łączyć oba tryby w jednym planie:

```
/10x-implement <change-id> phase 1   # environment
/10x-tdd       <change-id> phase 2   # contract (new code)
/10x-tdd       <change-id> phase 3   # contract (API endpoint)
/10x-implement <change-id> phase 4   # cookbook + plan sync
```

Oba zapisują postęp do tej samej sekcji `## Progress` w `plan.md`.

### Dwuwarstwowa strategia testów (koszt × sygnał)

Dla każdego ryzyka wybierz **najtańszy test, który daje rzeczywisty sygnał**. Nie wybieraj domyślnie e2e „bo jest najbezpieczniej” i nie ścigaj się za procentem pokrycia.

| Warstwa | Kiedy używać | Kiedy NIE używać |
| --- | --- | --- |
| Integracja (prawdziwa DB / prawdziwa infrastruktura) | Reguła obejmuje ograniczenia DB, kaskady, rzeczywisty SQL lub ograniczenia unikalności, co do których mock by kłamał. | Przepływy auth kontrolowane przez RLS, które należą do odrębnej fazy; wszystko, gdzie koszt konfiguracji przekracza wartość sygnału. |
| Hermetyczna (stub klienta) | Częściowe błędy, których rzeczywista infrastruktura nie potrafi łatwo wywołać (np. druga operacja w sekwencji kończy się błędem). | Reguły zależne od faktycznego stanu DB — stub będzie kłamał w kwestii naruszeń ograniczeń i kaskad. |

Nieatomowa sekwencja zapisu (wiele niezależnych operacji bez transakcji) oznacza: pisz hermetyczne testy dla gałęzi częściowych błędów, a nie testy integracyjne wymuszające błąd w środku sekwencji.

### Reguły wyroczni

- Wyrocznia — co kod *powinien* robić — musi pochodzić ze źródeł: PRD, dokumentacji, ograniczeń tech-stacku, wiedzy domenowej. **Nie może** pochodzić z odczytywania implementacji.
- Jeśli implementacja zawiera błąd, skopiowanie jej wyniku jako oczekiwanej wartości tworzy test lustrzany, który przechodzi mimo błędu.
- Gdy źródła nie rozstrzygają oczekiwanego zachowania jednoznacznie, **zatrzymaj się i zapytaj**, zamiast zgadywać.
- Zadaniem researchu jest ujawnienie wyroczni, zanim zostanie napisany jakikolwiek test.

### Antywzorce vibe-testingu, których należy unikać

| Antywzorzec | Jak wygląda | Co zrobić zamiast tego |
| --- | --- | --- |
| Implementacja lustrzana | Asercja oblicza oczekiwaną wartość tą samą logiką co testowany kod. | Asercja wobec wartości wyprowadzonej z wyroczni (PRD / reguła domenowa), a nie z implementacji. |
| Tylko happy paths | Testy przepuszczają tylko prawidłowe dane wejściowe; brak przypadków brzegowych. | Dodaj co najmniej jeden przypadek brzegowy dla każdego ryzyka: `null`, pusty, błąd zależności, nieprawidłowe dane wejściowe. |
| Nadmiarowe kopie | Sześć niemal identycznych testów sprawdzających ten sam brak sentinela. | Jeden sparametryzowany test (`it.each`) na właściwość; każdy test wykrywa inną regresję. |

### Testowanie mutacyjne (Stryker) — selektywna bramka jakości

Pokrycie mówi „ta linia została wykonana”. Wynik mutacji mówi „czy test by nie przeszedł, gdybym zepsuł tę linię?”. Używaj Stryker jako **selektywnej bramki** po fazie ryzyka, a nie jako bramki CI przy każdym commicie.

Przebieg pracy:
1. Testy przechodzą dla fazy ryzyka.
2. Uruchom `npx stryker run --mutate "path/to/file.ts"` (zawęź zakres do zmienionego modułu).
3. Otwórz raport HTML; znajdź mutacje, które przetrwały.
4. Dla każdej przetrwałej mutacji zapytaj: „Czy ta zmiana zaszkodziłaby użytkownikowi lub firmie?”
   - Tak → dodaj asercję, która zabija mutację.
   - Nie (równoważna mutacja lub kosmetyczna zmiana) → świadomie ją zignoruj.
5. Nie ścigaj 100% wyniku mutacji. Test, który przytwierdza szczegóły implementacji, aby zabić kosmetyczną mutację, sam jest testem vibe.

Bramka integracyjna może pozostać **ad hoc** (nie przy każdym commicie), gdy uruchamianie lokalnej infrastruktury jest kosztowne. Oznacz to odpowiednio w `test-plan.md §4`.

### Granice lekcji

- Nie konfiguruj hooków, cyklu życia hooków ani debugowania hooków. To Lekcja 3.
- Nie konfiguruj serwerów MCP, Playwright API, kodu e2e ani kodu scenariuszy multimodalnych. To Lekcja 4.
- Nie uruchamiaj workflow bug-to-fix-to-regression-test. To Lekcja 5.
- Nie twórz od zera pipeline'ów CI/CD. To Moduł 1 Lekcja 5 / Moduł 2 Lekcja 5.
- Nie uruchamiaj `/10x-test-plan`, aby zmienić strategię ryzyka. To Lekcja 1. Użyj `/10x-test-plan --status`, aby odczytać bieżący stan.
- Nie pisz testów bez kroku researchu, chyba że używasz promptu ad-hoc z pełną świadomością jego kompromisów.

### Ścieżki używane w tej lekcji

- `context/foundation/test-plan.md` — stan wdrożenia §3; cookbook §6 (uzupełniany w miarę dostarczania faz)
- `context/changes/<change-id>/research.md` — źródło wyroczni dla każdej fazy wdrożenia
- `context/changes/<change-id>/plan.md` — uporządkowane fazy z `## Progress` jako stanem wykonania
- `.claude/prompts/m3l2-ad-hoc-testing.md` — prompt do testowania ad-hoc na poziomie pliku

<!-- END @przeprogramowani/10x-cli -->

## Frontend toolchain — Vite+ (vite-plus 0.3.0)

- The frontend toolchain is Vite+ (`vp` CLI), not plain Vite. Lint = Oxlint, format = Oxfmt, both configured in the `lint` and `fmt` blocks of `vite.config.ts`. There is no ESLint or Prettier in this repo — never add `.eslintrc*`, `eslint.config.*`, `.prettierrc*` or install those packages.
- `vite.config.ts` imports `defineConfig` and `lazyPlugins` from `'vite-plus'`, not from `'vite'`. Add new plugins inside the `lazyPlugins(() => [...])` array.
- Commands (run through Sail): `vendor/bin/sail npm run check` (format + lint, must pass in CI), `vendor/bin/sail npm run check:fix` (auto-fix), `vendor/bin/sail npm run types:check` (tsc), `vendor/bin/sail npm run build`.
- Lint runs with `denyWarnings: true` and `typeAware: true`: a warning fails CI. Fix the code; do not add ignore patterns except for generated dirs (`resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`) and shadcn `resources/js/components/ui/*`.
- Before changing toolchain config, read the version-matched docs in `node_modules/vite-plus/docs/guide/` (`check.md`, `lint.md`, `fmt.md`, `build.md`) and `node_modules/vite-plus/AGENTS.md` — your training data likely predates this tool.

## Installed versions (check before relying on an API)

PHP 8.5 · Laravel 13 · Inertia v3 (inertia-laravel 3.3, @inertiajs/react 3) · React 19 (React Compiler on) · TypeScript 7 · Tailwind v4 · Pest 5 / PHPUnit 13 · Larastan 3 (level 7) · Fortify 1.39 · Wayfinder 0.1 (pre-1.0) · PostgreSQL 18 · Vite 8 via vite-plus 0.3.

- These are newer than most training data. Use Boost `search-docs` for Laravel/Inertia/Pest APIs instead of recalling them.
- Frontend routes come from Wayfinder: import from `@/actions/...` or `@/routes/...`; never hardcode URLs. After adding or renaming a route, regenerate with `vendor/bin/sail artisan wayfinder:generate` (the Vite plugin also does it in dev).

## Backend conventions for tree-owned data

- Every domain record belongs to a tree (`tree_id`). Scope reads through the user's tree (`$request->user()->tree->scans()`), never through a global query.
- Authorization: one Policy per model in `app/Policies/`; ownership check is `$model->tree_id === $user->tree->id`, denial is `Response::denyAsNotFound()` (another user's record is a 404, not a 403).
- Write path: controller → Form Request (`app/Http/Requests/<Model><Action>Request.php`) → Action class (`app/Actions/<Domain>/<Verb><Model>.php` with a `handle()` method) → redirect with `Inertia::flash('toast', ['type' => 'success', 'message' => __('...')])`. UI strings are Polish.
- Read path: pass data to Inertia pages through API Resources (`app/Http/Resources/`) with `->resolve()`; pages live in `resources/js/pages/<domain>/{index,create,show,edit}.tsx`, domain components in `resources/js/components/<domain>/`.
- Deleting a record that others reference shows the affected items first and requires explicit confirmation (pattern: `resources/js/components/scans/delete-scan-dialog.tsx`).
- Every new endpoint gets a Pest feature test in `tests/Feature/<Domain>Test.php` covering the happy path and the other-user 404.

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
