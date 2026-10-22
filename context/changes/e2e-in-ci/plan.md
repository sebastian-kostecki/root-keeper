# E2E (Playwright) w CI — Implementation Plan

## Overview

Specyfikacje Playwright z `tests/e2e/` (27 specyfikacji przepływów + `seed.spec.ts` + projekt `setup`) uruchamiają się w GitHub Actions przy każdym PR i pushu do `main`, tak jak trzy istniejące workflowy. Aplikacja startuje w CI natywnie (PHP 8.5 + usługa PostgreSQL 18, wzorzec `tests.yml`), testy idą bez ponowień na dwóch workerach, a przy błędzie trace, zrzuty i raport HTML trafiają do artefaktu joba. Zamyka issue #36 (foundation z przeglądu po M-2).

## Current State Analysis

- E2E chodzi tylko lokalnie: `playwright.config.ts:44-53` startuje `scripts/e2e-server.sh`, który jest w całości oparty na Sail (`vendor/bin/sail up -d pgsql`, `sail npm run build`, `docker compose run` z osobnym kontenerem `root-keeper-e2e`, bazą `e2e` i wolumenem na pliki źródeł). W GitHub Actions nie ma Sail ani obrazu.
- `playwright.config.ts:6` wczytuje `.env`, jeśli plik istnieje (komentarz: „In CI the file is absent”). W CI plik będzie jednak istniał, bo artisan go potrzebuje (`composer setup` kopiuje `.env.example`), a `.env.example:68-69` ma puste `E2E_USERNAME=` / `E2E_PASSWORD=`.
- `playwright.config.ts:17-24` ma dla CI `retries: 2`, `workers: 1`, `trace: 'on-first-retry'`, `reporter: 'html'`. Bez ponowień trace przy `on-first-retry` nigdy nie powstaje.
- Testy są samowystarczalne: dane tworzą przez trasy aplikacji (`tests/e2e/support/api.ts`), każdy rekord ma unikalny sufiks (`uniqueSuffix`, `api.ts:48`), a fixture sprząta po sufiksie także po czerwonym teście (`tests/e2e/support/fixtures.ts`). Na pustej bazie potrzebny jest tylko zweryfikowany użytkownik z drzewem — tak jak tworzy go `scripts/e2e-server.sh:27-30` przez tinker.
- Żadna specyfikacja nie wywołuje Sail, artisana ani Dockera; jedyna zależność od plików to `tests/Fixtures/portrait-small.jpg` (w repo).
- Upload zdjęć używa `gd` (`app/Actions/Sources/GenerateSourceThumbnail.php:84-86`) i `exif` (`app/Actions/Sources/ReadExifOrientation.php:13`); aplikacja nie ma zadań kolejkowanych (brak `ShouldQueue` / `dispatch` w `app/`), więc worker kolejki nie jest potrzebny.
- `tests.yml` jest gotowym wzorcem: usługa `postgres:18-alpine`, `setup-php` z `gd, exif, pdo_pgsql`, Node 24 z cache npm, `composer setup`, akcje przypięte do SHA.
- Repo jest prywatne na planie GitHub Free: API ochrony gałęzi i rulesetów zwraca 403 („Upgrade to GitHub Pro or make this repository public”). Wymaganych checków nie da się włączyć.

## Desired End State

- Każdy PR i push do `main` uruchamia workflow `e2e`; job jest zielony na obecnym `develop`.
- Celowo zepsuta asercja daje czerwony job, a artefakt joba zawiera raport HTML z trace i zrzutem ekranu nieudanego testu.
- Lokalnie `npx playwright test` działa bez zmian (Sail, `scripts/e2e-server.sh`, `reuseExistingServer`).
- `test-plan.md` §4/§5 i `test-stack.md` opisują E2E w CI i bramkę jako umowną (bez technicznej blokady merge'a).

### Key Discoveries:

- `playwright.config.ts:6` — wczytywanie `.env` musi być wyłączone w CI, inaczej puste wartości z `.env.example` mogą przesłonić zmienne joba (kolejność pierwszeństwa `process.loadEnvFile` nie jest czymś, na czym warto polegać).
- `scripts/e2e-server.sh:21-30` — gotowy przepis na przygotowanie bazy (migrate + `firstOrCreate` użytkownika + drzewo) do powtórzenia natywnie.
- `scripts/e2e-server.sh:7-8,51-53` — `php -S` z `server.php` frameworka i `PHP_CLI_SERVER_WORKERS=4`, nie `artisan serve` (ten gubi `DB_DATABASE` i czyta `.env` od nowa).
- `.github/workflows/tests.yml:15-80` — wzorzec joba z PostgreSQL 18 do skopiowania.

## What We're NOT Doing

- Uruchamiania E2E przez Sail w CI (decyzja: natywnie, jak `tests.yml`).
- Ponowień (`retries`) w CI — flaky ma być czerwony (CLAUDE.md, M3L4; issue #36).
- Shardowania joba — najpierw pomiar czasu przy 2 workerach.
- Technicznej blokady merge'a (wymaga GitHub Pro lub publicznego repo) — bramka jest umowna.
- Naprawiania testów, które okażą się flaky w CI, w ramach tego planu poza wąskim zakresem: czerwony test to sygnał; jeśli pojawi się flaky, zostaje zapisany jako osobna zmiana (Lekcja 5), a nie maskowany.
- Zmian w lokalnym `scripts/e2e-server.sh` i w samych specyfikacjach.
- Testów przeglądarek innych niż Chromium.

## Implementation Approach

`playwright.config.ts` rozróżnia dwa tryby po `process.env.CI`: lokalnie bez zmian, w CI osobny, natywny skrypt startowy `scripts/e2e-server-ci.sh`, który zakłada, że zależności i build są już przygotowane przez krok workflowu (`composer setup`), a sam robi tylko migracje, użytkownika E2E i serwer. Workflow `e2e.yml` kopiuje strukturę `tests.yml` (te same przypięte akcje, ta sama usługa PostgreSQL, baza `e2e`), dokłada instalację Chromium z cache i wgrywa raport jako artefakt. Dane logowania E2E to stałe w `env` joba — baza jest efemeryczna, więc sekrety są zbędne.

## Critical Implementation Details

**Kolejność env w CI.** Zmienne bazy (`DB_*`, `DB_DATABASE=e2e`), `APP_URL` i `E2E_*` ustawiamy na poziomie joba: Laravel daje pierwszeństwo zmiennym procesu nad `.env`, więc ten sam `.env` z `.env.example` obsłuży i build, i serwer. Playwright w CI nie czyta `.env` wcale (patrz Key Discoveries).

**Serwer w tle i sygnały.** `webServer` w CI uruchamia skrypt i czeka na `url`; skrypt musi kończyć się `exec php -S …`, żeby SIGTERM od Playwrighta trafił prosto w serwer (bez pułapek i kontenerów z wersji Sail). Host `127.0.0.1`, port z `E2E_PORT`.

## Phase 1: Job E2E w CI

### Overview

Natywny skrypt startowy dla CI, tryb CI w `playwright.config.ts` i workflow `e2e.yml`; weryfikacja na prawdziwym runie GitHub Actions, w tym celowo czerwonym.

### Changes Required:

#### 1. Natywny serwer E2E dla CI

**File**: `scripts/e2e-server-ci.sh` (nowy, wykonywalny)

**Intent**: Przygotować bazę i wystartować aplikację w CI bez Sail: migracje, zweryfikowany użytkownik E2E z drzewem, serwer PHP na `127.0.0.1:$E2E_PORT` w pierwszym planie. Zakłada gotowe `vendor/`, `.env` z kluczem i zbudowane assety.

**Contract**: Wejście ze zmiennych środowiska (`E2E_PORT` domyślnie 8100, `DB_*`, `E2E_USERNAME`, `E2E_PASSWORD`); `set -euo pipefail`; `php artisan migrate --force --no-interaction`; ten sam `firstOrCreate` + `email_verified_at` + `tree()->create()` co `scripts/e2e-server.sh:27-30`; zakończenie `cd public && PHP_CLI_SERVER_WORKERS=4 exec php -S 127.0.0.1:$PORT ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`. Nagłówkowy komentarz w stylu `e2e-server.sh` wyjaśnia, czym różni się od wersji lokalnej.

#### 2. Tryb CI w konfiguracji Playwright

**File**: `playwright.config.ts`

**Intent**: W CI: nie czytać `.env`, startować skrypt CI, bez ponowień, 2 workery, trace i zrzuty zachowane przy błędzie, reporter z adnotacjami GitHub i raportem HTML do artefaktu. Lokalnie zachowanie bez zmian.

**Contract**:
- `.env` wczytywany tylko gdy `!process.env.CI && existsSync('.env')`; komentarz zaktualizowany.
- `retries: 0` (zawsze), `workers: process.env.CI ? 2 : undefined`.
- `use.trace: 'retain-on-failure'`, `screenshot: 'only-on-failure'` (bez zmian).
- `reporter`: w CI `[['github'], ['html', { open: 'never' }]]`, lokalnie `'html'`.
- `webServer.command`: `process.env.CI ? 'scripts/e2e-server-ci.sh' : 'scripts/e2e-server.sh'`; `reuseExistingServer: !process.env.CI` i `gracefulShutdown` bez zmian; `timeout` może zostać.

#### 3. Workflow e2e

**File**: `.github/workflows/e2e.yml` (nowy)

**Intent**: Job `e2e` na tych samych wyzwalaczach co `tests.yml` (`push` do `main`, `pull_request`), z usługą PostgreSQL 18 i bazą `e2e`, natywnym PHP i Node, Chromium Playwrighta i artefaktem raportu.

**Contract**:
- `permissions: contents: read`; `timeout-minutes: 20`; `runs-on: ubuntu-latest`.
- `services.pgsql` jak w `tests.yml`, ale `POSTGRES_DB: e2e`.
- `env` joba: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=e2e`, `DB_USERNAME=sail`, `DB_PASSWORD=password`, `E2E_PORT=8100`, `APP_URL=http://127.0.0.1:8100`, `E2E_USERNAME=e2e@example.com`, `E2E_PASSWORD` stałe testowe (komentarz: baza efemeryczna, to nie sekret).
- Kroki: checkout (`persist-credentials: false`), `setup-php` 8.5 z `gd, exif, pdo_pgsql`, `setup-node` 24 z cache npm, `composer setup`, cache `~/.cache/ms-playwright` kluczowany wersją `@playwright/test` z `package-lock.json`, `npx playwright install --with-deps chromium` (przy trafieniu cache wystarczy `install-deps chromium`), `npx playwright test`, upload `playwright-report/` i `test-results/` z `if: ${{ !cancelled() }}` i rozsądną retencją.
- Akcje przypięte do SHA z komentarzem wersji, tak jak w istniejących workflowach (checkout, setup-php, setup-node te same SHA; `actions/cache` i `actions/upload-artifact` przypięte do aktualnych wydań).

### Success Criteria:

#### Automated Verification:

- Formatowanie i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Lokalny pełny przebieg nadal zielony przez Sail: `npx playwright test`
- Workflow `e2e` jest zielony na PR z tą zmianą (`gh pr checks`)

#### Manual Verification:

- Celowo zepsuta asercja w jednej specyfikacji (commit na gałęzi tymczasowej, potem cofnięty) daje czerwony job `e2e`, a artefakt zawiera raport HTML z trace i zrzutem tego testu
- Czas joba `e2e` jest akceptowalny (zapisany w notatkach zmiany; jeśli > 15 min, zapisane jako kandydat na shardowanie)

**Implementation Note**: Po zielonej automatyce zatrzymaj się na ręczne potwierdzenie runu czerwonego i artefaktu, zanim przejdziesz do fazy 2. Jeśli zielony run nie wyjdzie z powodu flaky testu (nie konfiguracji), nie dodawaj ponowień — zapisz go w notatkach zmiany i wróć do użytkownika.

---

## Phase 2: Dokumentacja i domknięcie

### Overview

Dokumenty fundamentów przestają mówić „lokalnie, poza CI” i opisują, jak E2E chodzi w CI oraz że bramka jest umowna.

### Changes Required:

#### 1. Plan testów

**File**: `context/foundation/test-plan.md`

**Intent**: §4 wiersz e2e: liczba specyfikacji i „lokalnie + CI (`e2e.yml`)”; §5 wiersz e2e: Where = local + CI (`e2e`), Required = required (umownie — repo prywatne na GitHub Free, brak ochrony gałęzi); §8 data przeglądu.

**Contract**: Tylko wiersze tabel §4 i §5 oraz Freshness Ledger; statusy §3 bez zmian.

#### 2. Stos testów

**File**: `context/foundation/test-stack.md`

**Intent**: Sekcja E2E mówi, czym CI różni się od lokalnego uruchomienia: serwer `scripts/e2e-server-ci.sh` (natywny PHP + PostgreSQL 18), dane logowania z `env` joba, `retries 0`, 2 workery, raport w artefakcie.

**Contract**: Linie `web server command`, `auth setup project` i `updated` sekcji `## E2E`; reszta bez zmian.

#### 3. Issue

**Intent**: Zamknąć issue #36 z linkiem do PR i zielonego runu.

**Contract**: `gh issue close 36 --comment …` po merge'u.

### Success Criteria:

#### Automated Verification:

- Żaden dokument fundamentów nie opisuje już E2E jako „poza CI”: `grep -rn 'poza CI' context/foundation/` zwraca pusto

#### Manual Verification:

- Issue #36 zamknięte z linkiem do zielonego runu

---

## Testing Strategy

### Integration Tests:

- Pełny przebieg Playwright w CI na pustej bazie `e2e` (zielony) oraz lokalnie przez Sail (zielony).

### Manual Testing Steps:

1. Otworzyć PR z gałęzi zmiany do `develop`; sprawdzić, że job `e2e` startuje obok `lint`, `static-analysis`, `tests` i kończy się na zielono.
2. Na gałęzi tymczasowej zepsuć jedną asercję (np. oczekiwany tekst w `seed.spec.ts`), wypchnąć, sprawdzić czerwony job i adnotację GitHub przy linii, pobrać artefakt i otworzyć trace.
3. Usunąć gałąź tymczasową.

## Performance Considerations

29 plików na 2 workerach z `PHP_CLI_SERVER_WORKERS=4`; budżet joba 20 min. Shardowanie dopiero, gdy zmierzony czas przekroczy ~15 min.

## References

- Issue: #36 „E2E (Playwright) w CI”
- `context/foundation/health-check.md` → „E2E in CI” (kategoria B)
- Wzorzec joba: `.github/workflows/tests.yml`
- Lokalny serwer: `scripts/e2e-server.sh`
- Konfiguracja: `playwright.config.ts`, `context/foundation/test-stack.md`
- Poprzednia zmiana E2E: `context/archive/2026-10-04-critical-flows-e2e-guard/`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Job E2E w CI

#### Automated

- [x] 1.1 Formatowanie i lint przechodzą: `vendor/bin/sail npm run check`
- [x] 1.2 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [ ] 1.3 Lokalny pełny przebieg nadal zielony przez Sail: `npx playwright test`
- [x] 1.4 Workflow `e2e` jest zielony na PR z tą zmianą (`gh pr checks`)

#### Manual

- [x] 1.5 Celowo zepsuta asercja daje czerwony job `e2e` z raportem HTML, trace i zrzutem w artefakcie
- [x] 1.6 Czas joba `e2e` jest akceptowalny i zapisany w notatkach zmiany

### Phase 2: Dokumentacja i domknięcie

#### Automated

- [ ] 2.1 Żaden dokument fundamentów nie opisuje już E2E jako „poza CI”

#### Manual

- [ ] 2.2 Issue #36 zamknięte z linkiem do zielonego runu
