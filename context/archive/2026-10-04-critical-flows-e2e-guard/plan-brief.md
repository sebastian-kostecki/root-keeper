# F-01 critical-flows-e2e-guard — Plan Brief

> Full plan: `context/changes/critical-flows-e2e-guard/plan.md`
> Research: `context/changes/critical-flows-e2e-guard/research.md`

## What & Why

PRD wymaga, żeby ścieżki krytyczne z guardrails (fakt–źródło oraz relacje ze slotów drzewa) były chronione testami E2E przed przebudową paneli i drzewa (`prd.md:209`). F-01 musi wyprzedzić S-04, S-05 i S-06, które dotykają dokładnie tych ścieżek. Dziś chroni je tylko Pest na poziomie żądań. Pest nie zobaczy, że przebudowany UI przestał pozwalać przejść ścieżkę albo trafia w inną osobę lub inne źródło.

## Starting Point

Playwright jest skonfigurowany: osobny kontener Sail, baza `e2e`, port 8100, projekt `setup` z sesją. Są jednak tylko testy logowania i seed. Wszystkie specyfikacje dzielą jednego użytkownika i jedno drzewo w trybie `fullyParallel`. Pliki źródeł z E2E lądują w tym samym katalogu co deweloperskie drzewo 1.

## Desired End State

Cztery testy w `tests/e2e/` przechodzą równolegle i wielokrotnie. Każdy zrobił się czerwony przy celowym uszkodzeniu, które potem cofnięto. Po przebiegu nie zostają dane testowe. Przejście modala na panel (S-06) i przebudowa siatki (S-05) zmienią jeden moduł `tests/e2e/support/`, a nie asercje. `test-plan.md` zna wynik: fazę 4 zamkniętą, bramkę lokalną, wzorzec §6.6 i werdykt okna `choose()`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| CI | Lokalnie przed S-04 – S-06, job CI później | Job wymaga gałęzi `e2e-server` bez Sail, to osobna praca | Research |
| Izolacja danych | Wspólny użytkownik E2E, unikalny sufiks, `/tree/{własneId}`, bez probanta | Zgodne z obecnym `storageState`, bez nowego wzorca logowania | Research |
| Przygotowanie danych | `page.request` do istniejących tras z `X-XSRF-TOKEN`, ścieżka chroniona w UI | Test skupia się na ryzyku, a granice (auth, routing, DB) są prawdziwe | Research |
| Budżet | 4 testy: rodzic, dziecko, małżonek (każdy istniejący + nowy), fakt → źródło | Po jednym na rodzaj relacji, bo dialog i lista kandydatów są wspólne | Research |
| Dziecko | W drugim związku, czeka na nowy `sheet`, asercja właściwego partnera | Chroni wybór pary po przełączeniu związku | Research |
| Małżonek | Pierwszy z pustego slotu (nowy), drugi z menu związku (istniejący) | Pokrywa oba wejścia, które S-05 może przestawić | Research |
| Wyrocznia skanu | sha256 `GET /sources/{id}/file` = sha256 fikstury | Oryginał jest serwowany bajt w bajt, a test przetrwa `/sources/{id}` → `?source=` | Research |
| Okno `choose()` | Jednorazowa sonda w `playwright-cli` z opóźnioną wizytą `?spouse=` | Rozstrzyga pytanie otwarte od fazy 2 bez przekraczania budżetu; błąd → osobna zmiana | Plan |
| Pliki E2E | Nazwany wolumin Dockera na `storage/app/private/sources` w kontenerze E2E | Ten sam model co osobna baza `e2e`, brak sierot w katalogu dev | Plan |
| Odczyt id | `Location` po `store`, propsy z osadzonego obiektu strony w HTML | Bez dostępu do bazy i bez zależności od wersji zasobów Inertia | Plan |

## Scope

**In scope:**
- wolumin plików E2E w `scripts/e2e-server.sh`;
- moduł `tests/e2e/support/` (API, sprzątanie po sufiksie, nawigacja);
- 4 specyfikacje Playwright z celowymi uszkodzeniami;
- sonda okna `choose()`;
- aktualizacja `test-plan.md` (§3, §4, §5, §6.6, werdykt, §8).

**Out of scope:**
- job CI;
- stały test wyścigu `choose()` i naprawa ewentualnego błędu;
- jakikolwiek kod produkcyjny;
- FR-027 i FR-029 (pokrywa je Pest);
- asercje na wyglądzie;
- użytkownik na workera;
- zmiany w `test-stack.md`;
- viewport mobilny.

## Architecture / Approach

Każdy test składa się z czterech kroków:

1. Przygotowanie przez API z sufiksem.
2. Chroniona ścieżka w UI: lokatory rolami i etykietami, zawężone do `getByRole('dialog', { name: display_name })`, wejście przez `?person={id}`.
3. Asercja na danych: relacja z obu stron w zakładce `Rodzina` albo sha256 skanu.
4. Sprzątanie po sufiksie w `finally`: najpierw źródła, potem osoby, każde żądanie z `toBeOK`.

Wszystko, co S-02, S-05 i S-06 zmienią (wejście w drzewo, podgląd osoby, sekcje rodziny), siedzi w `tests/e2e/support/navigation.ts`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Izolacja plików źródeł E2E (`/10x-implement`) | Wolumin na pliki źródeł w kontenerze E2E | Prawa zapisu: wolumin należy do roota, a PHP działa jako `sail` |
| 2. Moduł pomocniczy + test fakt → źródło → skan (`/10x-e2e`) | `tests/e2e/support/` i test FR-025 | Odczyt id zdarzenia z osadzonego obiektu strony Inertia v3 |
| 3. Sloty drzewa + sonda `choose()` (`/10x-e2e`) | 3 testy FR-026 i werdykt okna | Stabilność przy równoległych workerach na wspólnym drzewie |
| 4. Domknięcie w `test-plan.md` (`/10x-implement`) | Faza 4 `complete`, §5, §6.6, werdykt | Spójność dokumentu z gotowymi testami |

**Prerequisites:** działający Sail i Docker, `E2E_USERNAME` i `E2E_PASSWORD` w `.env`, wolny port 8100.
**Estimated effort:** ~3–4 sesje. Fazy 1 i 4 są krótkie, fazy 2 i 3 to pętle `/10x-e2e`.

## Open Risks & Assumptions

- Sprzątanie przez `page.request.delete` z `X-XSRF-TOKEN` wynika z analizy kodu i nie było jeszcze uruchamiane. Potwierdza je dopiero faza 2.
- Sonda może potwierdzić błąd okna `choose()`. Wtedy powstaje osobna zmiana. Test dziecka czeka na nowy `sheet`, więc zostaje zielony.
- Lokator dialogu przetrwa S-06 tylko wtedy, gdy panel osoby dostanie `title={display_name}` (intencja z `side-panel-shell/plan.md:58`).

## Success Criteria (Summary)

- Przed S-04 – S-06 jedno polecenie (`npx playwright test`) mówi, czy fakt nadal prowadzi do tego samego skanu i czy sloty nadal dodają właściwych krewnych.
- Każdy test udowodnił, że łapie swoją regresję: czerwony przy celowym uszkodzeniu.
- Testy nie zostawiają danych w bazie `e2e` ani plików w katalogu deweloperskim.
