<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Szczegóły miejscowości

- **Plan**: context/changes/locality-details/plan.md
- **Mode**: Deep
- **Date**: 2026-10-20
- **Verdict**: REVISE → SOUND (po poprawkach)
- **Findings**: 0 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Grounding: 12/12 paths ✓, 6/6 symbols ✓, brief↔plan ✓, Progress↔Phases ✓ (1.1–1.6, 2.1–2.9)

## Findings

### F1 — Zmiana kontraktu psuje więcej asercji, niż plan wymienia

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §5, §9; Key Discoveries
- **Detail**: Poza `PersonsTest.php:1696` pękną: `SourcesTest.php:1474` (dokładne `sourcePanel.localities`), `LocalitiesTest.php:300,306,325` (`assertExactJson` inline), `LocalitiesTest.php:117-118` (stara sygnatura `handle()`), `PlacesTest.php:63` (`hasAll` bez `etc()`).
- **Fix**: Wypisać je w §9 jako aktualizacje kontraktu w przeglądanym diffie.
- **Decision**: FIXED

### F2 — Scalanie po cichu gubi różną przynależność historyczną

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 §7; brief „nic nie ginie”
- **Detail**: `mergedDescription()` łączy różne opisy, a plan dla `historical_affiliation` brał duplikat tylko przy pustym celu — różny tekst duplikatu przepadał.
- **Fix A ⭐ Recommended**: Przynależność łączona jak opis (ten sam helper); rodzaj, współrzędne, opis zaniku — wygrywa cel.
  - Strength: Spójne z istniejącym łączeniem opisów; helper już jest.
  - Tradeoff: Suma może przekroczyć `max:1000` (jak opis `max:5000`).
  - Confidence: HIGH — helper z testem istnieje.
  - Blind spot: Limit po łączeniu tylko udokumentowany.
- **Fix B**: Zostawić „cel wygrywa”, poprawić brief i dialog.
  - Strength: Prosta reguła.
  - Tradeoff: Utrata danych, tylko ujawniona.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — §7, §9, Desired End State, tekst `MergeLocalityDialog` (Faza 2 §3), brief.

### F3 — Dopisek w <li role="option"> zmienia nazwę dostępną opcji

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §3
- **Detail**: `<li>` bez `aria-label` (`suggest-field.tsx:284-308`); span bez spacji daje „Lipnicanie istnieje”; lokatory E2E z `exact: true` muszą znać nową nazwę.
- **Fix**: Jawna spacja `{' '}` przed spanem i nota o nazwie „<nazwa> nie istnieje”.
- **Decision**: FIXED

### F4 — Po edycji pole współrzędnych pokazuje surowy wklejony tekst

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §4
- **Detail**: `<Form key={locality?.id ?? 'new'} setDefaultsOnSuccess>` + `update` → `back()`: brak remountu, `defaultValue` ignorowane po zapisie.
- **Fix**: Klucz formularza `${id}-${formatCoordinates(lat, lng)}`.
- **Decision**: FIXED

### F5 — Warunkowe „jeśli test ich nie obejmuje” — nie obejmuje

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §6; Current State
- **Detail**: `view-tokens.test.ts:20-58` nie skanuje `suggest-field.tsx` ani `locality-picker.tsx`; Current State pomijał `LocalityResource` jako źródło opcji (siedziba parafii, scalanie).
- **Fix**: Konkretne „dopisać do `VIEW_FILES`”; `LocalityResource` i `placeOptions()` opisane w Current State.
- **Decision**: FIXED
