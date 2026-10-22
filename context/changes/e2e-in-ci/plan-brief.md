# E2E (Playwright) w CI — Plan Brief

> Full plan: `context/changes/e2e-in-ci/plan.md`

## What & Why

Specyfikacje Playwright chroniące ścieżki krytyczne (fakt → źródło → skan, dodawanie relacji ze slotów drzewa, panele) chodzą dziś tylko lokalnie, więc regresja UI może trafić na `develop`/`main`, jeśli nikt nie uruchomi ich ręcznie. Ta zmiana uruchamia je w GitHub Actions przy każdym PR (issue #36), zanim M-3 zacznie przebudowywać kolejne widoki.

## Starting Point

`playwright.config.ts` startuje `scripts/e2e-server.sh`, który działa wyłącznie przez Sail (osobny kontener, baza `e2e`, wolumen na skany). Testy same tworzą i sprzątają dane przez trasy aplikacji, więc na pustej bazie potrzebują tylko użytkownika E2E z drzewem. CI ma trzy workflowy (`lint`, `static-analysis`, `tests`); `tests` stawia natywnie PHP 8.5 i PostgreSQL 18.

## Desired End State

Job `e2e` uruchamia się na każdym PR i pushu do `main` obok pozostałych, jest zielony na obecnym kodzie, a czerwony test daje czerwony job z adnotacją przy linii i artefaktem (raport HTML, trace, zrzut). Lokalne uruchomienie przez Sail działa jak dotąd.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Jak startuje aplikacja w CI | Natywnie: `setup-php` + usługa PostgreSQL 18, nowy `scripts/e2e-server-ci.sh` | Ten sam wzorzec co `tests.yml`, bez budowania obrazu Sail przy każdym runie. |
| Ponowienia | `retries: 0`, trace `retain-on-failure` | Flaky ma być czerwony, nie maskowany (issue #36, CLAUDE.md). |
| Równoległość | 2 workery, bez shardów | Testy są izolowane sufiksem i lokalnie już chodzą równolegle; shardy dopiero po pomiarze. |
| Blokada merge'a | Widoczny check, bramka umowna | GitHub Free nie pozwala na ochronę gałęzi w prywatnym repo (API zwraca 403). |
| Dane logowania E2E | Stałe w `env` joba, nie sekrety | Baza CI jest efemeryczna, użytkownik powstaje w skrypcie startowym. |
| `.env` w Playwright | Nie czytany w CI | `.env` z `.env.example` ma puste `E2E_*`, które mogłyby przesłonić zmienne joba. |

## Scope

**In scope:**
- `scripts/e2e-server-ci.sh` (migrate, użytkownik E2E z drzewem, `php -S`)
- tryb CI w `playwright.config.ts`
- `.github/workflows/e2e.yml` z cache Chromium i artefaktem raportu
- aktualizacja `test-plan.md` §4/§5 i `test-stack.md`, zamknięcie #36

**Out of scope:**
- Sail w CI, shardowanie, ponowienia
- techniczna blokada merge'a (GitHub Pro)
- zmiany w specyfikacjach i w lokalnym `e2e-server.sh`
- inne przeglądarki niż Chromium
- naprawianie ewentualnych flaky (osobna zmiana)

## Architecture / Approach

`playwright.config.ts` wybiera serwer po `process.env.CI`. Workflow robi `composer setup` (zależności, `.env`, klucz, migracje, build), instaluje Chromium (z cache) i uruchamia `npx playwright test`. Playwright startuje `scripts/e2e-server-ci.sh`, który migruje bazę `e2e`, tworzy użytkownika i robi `exec php -S 127.0.0.1:8100`. Zmienne `DB_*`, `APP_URL`, `E2E_*` są w `env` joba i mają pierwszeństwo przed `.env`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Job E2E w CI | Skrypt CI, tryb CI w configu, workflow `e2e.yml`; zielony run i celowo czerwony run z artefaktem | Testy flaky ujawnione dopiero w CI (bez ponowień job będzie czerwony) |
| 2. Dokumentacja i domknięcie | `test-plan.md`, `test-stack.md` opisują E2E w CI; #36 zamknięte | — |

**Prerequisites:** F-01 `critical-flows-e2e-guard` (zrobione); dostęp `gh` do repo.
**Estimated effort:** ~1 sesja (głównie czekanie na runy CI).

## Open Risks & Assumptions

- Pierwszy run w CI może ujawnić flaky test albo różnicę środowiska (np. wolniejszy runner). Nie maskujemy tego ponowieniami: flaky zapisujemy jako osobną zmianę.
- Zakładamy, że 2 workery mieszczą się w budżecie 20 min. Jeśli czas przekroczy ~15 min, rozważamy shardy.
- Bramka jest umowna. Merge na czerwono jest technicznie możliwy, dopóki repo jest prywatne na planie Free.

## Success Criteria (Summary)

- Każdy PR pokazuje job `e2e`, a na obecnym kodzie jest on zielony.
- Zepsuta asercja daje czerwony job z trace i zrzutem w artefakcie.
- Lokalne `npx playwright test` działa jak dotąd.
