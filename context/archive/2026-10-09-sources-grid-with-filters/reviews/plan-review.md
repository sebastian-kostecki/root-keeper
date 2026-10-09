<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Siatka źródeł z filtrami (S-10)

- **Plan**: context/changes/sources-grid-with-filters/plan.md
- **Mode**: Deep (weryfikacja kodu inline, bez sub-agenta)
- **Date**: 2026-10-09
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical, 5 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
11/11 ścieżek ✓, 9/9 symboli ✓, numery linii testów ✓, brief↔plan ✓, Progress↔Fazy ✓

## Findings

### F1 — Faza 2 nie przejdzie własnej bramki typów
- **Severity**: ⚠️ WARNING · **Impact**: 🏃 LOW · **Dimension**: Plan Completeness · **Location**: Faza 2
- **Detail**: zmiana sygnatury `filterSources` łamie wywołania w `pages/sources/index.tsx:165`, `:177`, a plik był dopiero w Fazie 3.
- **Fix**: dodano do Fazy 2 punkt 3 „Przepięcie wywołań na stronie listy”.
- **Decision**: FIXED (Fix in plan)

### F2 — Zamiana odwróconego zakresu lat koliduje z wpisywaniem
- **Severity**: ⚠️ WARNING · **Impact**: 🔎 MEDIUM · **Dimension**: Blind Spots · **Location**: Faza 1 (normalizacja), Critical Implementation Details
- **Detail**: `year_to=1` przy `year_from=1890` → serwer zamienia, echo nadpisuje pola w trakcie pisania.
- **Fix A ⭐**: bez zamiany, odwrócony zakres = pusty wynik.
- **Decision**: FIXED via Fix A (zmiany: Overview/End State, Critical Details, Faza 1 #1 i #5, Faza 2 reguła roku i Vitest, Testing Strategy, krok ręczny 2)

### F3 — „Wartość spoza opcji = nieustawiona” bez właściciela
- **Severity**: ⚠️ WARNING · **Impact**: 🔎 MEDIUM · **Dimension**: Blind Spots · **Location**: Critical Implementation Details, Faza 2/3
- **Detail**: `visit()` rozsmarowuje `...filters`, więc ślepa wartość przeżywa; `isFiltering`/status liczą z surowych filtrów; zdarza się po usunięciu ostatniego źródła z miejscem/archiwum lub zmianie archiwum w panelu.
- **Fix**: `resolveFilters(filters, placeOptions, archiveOptions)` w Fazie 2, używana przez pola, `visit()`, `filterSources`, `isFiltering`, status.
- **Decision**: FIXED (Fix in plan)

### F4 — „Bez miejsca” dla filtra parafii sprzeczne
- **Severity**: ⚠️ WARNING · **Impact**: 🏃 LOW · **Dimension**: Blind Spots · **Location**: Faza 2 (`hidden`)
- **Detail**: nagrobki mają `parish_id = null` (UpdateSource), parafia ma nullable `locality_id`.
- **Fix**: brak miejsca = `parish_id === null` i `cemetery_locality_id === null`, jednakowo dla obu filtrów; przypadki w Vitest.
- **Decision**: FIXED (Fix in plan)

### F5 — Czekające lata vs inne kontrolki i „Wyczyść filtry”
- **Severity**: ⚠️ WARNING · **Impact**: 🏃 LOW · **Dimension**: Blind Spots · **Location**: Faza 3 (pasek filtrów)
- **Detail**: debounce lat przywróciłby lata po wyczyszczeniu; niejasne źródło lat dla listy w trakcie pisania.
- **Fix**: „Wyczyść filtry” zeruje lokalne `q` i lata oraz timery; `visit()` bierze lata z lokalnego stanu; lista i status po zatwierdzonych latach.
- **Decision**: FIXED (Fix in plan)

### F6 — „Wszystkie” w Radix Select
- **Severity**: 💡 OBSERVATION · **Impact**: 🏃 LOW · **Dimension**: Plan Completeness · **Location**: Faza 3
- **Detail**: Radix odrzuca `value=""`; archiwum to wolny tekst; E2E musi klikać combobox + `role=option`.
- **Fix**: wartownik (np. `__all__`) mapowany na `''`, `Label htmlFor`/`id` pod `getByLabel`.
- **Decision**: FIXED (Fix in plan)
