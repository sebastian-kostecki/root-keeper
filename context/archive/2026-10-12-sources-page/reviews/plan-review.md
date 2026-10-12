<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Widok /sources zgodny z systemem projektowym

- **Plan**: context/changes/sources-page/plan.md
- **Mode**: Deep
- **Date**: 2026-10-12
- **Verdict**: REVISE → SOUND po triage
- **Findings**: 0 critical · 2 warnings · 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS (1 observation) |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | PASS (1 observation) |

## Grounding
15/15 paths ✓ (4 nowe pliki nie istnieją — zgodnie z planem), 8/8 symbols ✓, Progress↔Fazy ✓, brief↔plan ✓. Zweryfikowane w kodzie: Radix `forceMount` (`isOpen = context.open || isPresent`, `data-state` z `context.open`), Tailwind 4.3.3 z wariantem `not-*`, `stickyCell` używany tylko w `sources-table.tsx` i `persons-table.tsx`, `failureHandlers` tylko w `pages/persons/index.tsx`, skan tokenów na plikach `/sources` łapie dziś tylko dwa `ring-[3px]` (usuwane w Fazie 1).

## Findings

### F1 — Wspólne opakowanie lat z komunikatem psuje desktopowy pasek

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Faza 3, #2 „Komunikat przy polach roku”
- **Detail**: Opakowanie `order-5` (lata + `InputError`) przeczy „Bez zmian w pasku na desktopie” i Fazie 2 („pola zachowują order-4…order-7”). Wiersz `role="search"` ma `items-center` (index.tsx:361), więc komunikat przesuwa resztę paska w pionie. Komunikat łamie się w ok. 236 px. Na telefonie `flex-col` ustawiłby lata jedno pod drugim.
- **Fix**: Lata zostają osobnymi polami. `InputError` to osobny element `basis-full order-6 sm:order-9` zaraz po „Rok do”. `CollapsibleContent` na telefonie ma `flex flex-wrap gap-3`.
  - Strength: Pasek od sm wygląda jak dziś, dopóki nie ma błędu. Nic nie skacze w pionie.
  - Tradeoff: Na desktopie komunikat stoi pod całym paskiem (powiązanie przez `aria-describedby`).
  - Confidence: HIGH — wynika z klas paska i kolejności order-*.
  - Blind spot: Wygląd przy 640–1024 px sprawdzi zrzut 07.
- **Decision**: FIXED (Fix in plan — Faza 3 #2 i Faza 2 #2)

### F2 — Cykl życia pokazywanego błędu roku nieokreślony poza pisaniem

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details „Moment komunikatu roku”; Faza 3, #2
- **Detail**: Tekst lat zmienia też „Wyczyść filtry” (index.tsx:290-302) i historia (index.tsx:173-185). Błąd ustawiany z `onChange` zostałby nieaktualny. Plan nie mówił, czy poprawienie usuwa komunikat od razu.
- **Fix**: Jeden efekt na `[yearFrom, yearTo]`: `null` → komunikat znika od razu, błąd → `setTimeout(300)` z cleanupem.
- **Decision**: FIXED (Fix in plan — Critical Implementation Details)

### F3 — `hasOtherFilters` dubluje `hiddenFilterCount`

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Faza 3, #5 (vs Faza 2, #1)
- **Detail**: Ten sam predykat zdefiniowany dwa razy.
- **Fix**: `hasOtherFilters = hiddenFilterCount(filters) > 0`.
- **Decision**: FIXED (Fix in plan — Faza 3 #5)

### F4 — Scenariusze E2E 2 i 3 nie mówią, jakie dane tworzą

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 4, #1
- **Detail**: Pasek renderuje się tylko przy `sources.length > 0` (index.tsx:358). Segment z licznikiem 0 jest wyłączony (index.tsx:387-405).
- **Fix**: `createSource` z `uniqueSuffix` w scenariuszach 2 i 3 (scen. 2: akt → „Akty” → `kind=akty`).
- **Decision**: FIXED (Fix in plan — Faza 4 #1)
