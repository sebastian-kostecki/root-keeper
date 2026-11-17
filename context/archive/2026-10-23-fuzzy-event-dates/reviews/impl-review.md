<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Daty nieostre: zakres, przed, po

- **Plan**: context/changes/fuzzy-event-dates/plan.md
- **Scope**: Full plan (completed phases)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-23
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 3 observations

Faza 4 nie jest w zakresie (4.3 „CI” niezaznaczone).

Automatyczne kontrole: Pest dla poleceń z planu 377/377 zielone, Larastan 0 błędów, Pint czysty, Vitest 437/437, `tsc` czysty. `npm run check` FAIL (patrz F1).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — `npm run check` czerwony (formatowanie fixtur)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Fixtures/research-repo/**
- **Detail**: `vp check` zgłasza formatowanie w 18 plikach, wszystkie w `tests/Fixtures/research-repo/` (wprowadzone w 8535a86, zmiana research-blocks-read-and-report). Żaden plik tej zmiany nie jest na liście. Punkt 3.3 jest zaznaczony, ale polecenie dziś nie przechodzi, a CLAUDE.md wymaga zielonego `check` w CI.
- **Fix**: Dodać `tests/Fixtures/**` do ignorowanych w bloku `fmt` w `vite.config.ts` (to dane wejściowe testu, nie powinny być formatowane) albo uruchomić `check:fix` osobnym commitem. Sprawdzić, że fixtury nie zmieniają znaczenia po formatowaniu.
- **Decision**: FIXED — wyłączono `tests/Fixtures/**` z `fmt` w vite.config.ts; `npm run check` zielony

### F2 — Zrzuty ekranu (3.11) zaznaczone, a katalogu brak

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/fuzzy-event-dates/screenshots/
- **Detail**: 3.11 jest `[x]` z sha 3fd3138, ale katalog `screenshots/` nie istnieje, nie ma go w diffie ani w historii gita. Plan wymagał czterech widoków w jasnym i ciemnym motywie (bramka wizualna z CLAUDE.md). Punkty 3.8 i 3.10 są `[x]` bez sha, więc nie ma śladu ich wykonania.
- **Fix**: Wykonać zrzuty (pole z podpowiedzią, oś czasu, karta drzewa, karta przodka × 2 motywy) albo odznaczyć 3.11 i dopisać w planie, dlaczego zrezygnowano.
- **Decision**: FIXED — odznaczono 3.11 w Progress planu (zrzutów brak; do wykonania przed zamknięciem zmiany)

### F3 — `FillBurialPlacesFromGravestone` tworzy zdarzenie bez `date_qualifier`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Sources/FillBurialPlacesFromGravestone.php:41
- **Detail**: Atrybut jest `DateQualifier` (nie-null), a utworzony model ma go null do odświeżenia (w bazie domyślnie `exact`). Odczyt `Event::date` na tej instancji dałby TypeError. Dziś nikt jej nie czyta.
- **Fix**: Dodać `'date_qualifier' => DateQualifier::Exact` w `create`.
- **Decision**: FIXED — dodano `date_qualifier => Exact` w create

### F4 — Qualifier z zakresem daje ogólny błąd parsera

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/partial-date.ts:116-152
- **Detail**: „ok. 1805-1811” i „przed 1805-1811” zwracają `invalid` z komunikatem „Wpisz np. 1948, 03.1948 lub 12.03.1948”, który nie wspomina zakresów. Odrzucenie jest zgodne z gramatyką planu.
- **Fix**: Rozszerzyć komunikat o zakres albo dodać osobny kod błędu.
- **Decision**: FIXED — komunikat błędu parsera wspomina zakres (partial-date-input.tsx:31)

### F5 — Flaga `date_is_approximate` zsynchronizowana tylko hookiem modelu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: app/Models/Event.php:42-46
- **Detail**: Hook działa tylko przy zapisie modelu. Jedyna masowa aktualizacja zdarzeń (`MergeLocality.php:55`) zmienia tylko `locality_id`, więc dziś brak rozjazdu. Przyszła aktualizacja `date_qualifier` przez query builder zostawiłaby flagę nieaktualną. Zgodne z planem (flaga znika w osobnej zmianie).
- **Fix**: Nie aktualizować `date_qualifier` zbiorczo do czasu usunięcia flagi; ewentualnie zanotować w planie usunięcia.
- **Decision**: FIXED — dodano PHPDoc przy hooku `saving` w Event ostrzegający przed zbiorczą aktualizacją
