<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Panel osoby obok drzewa (S-06)

- **Plan**: context/changes/person-side-panel/plan.md
- **Mode**: Deep
- **Date**: 2026-10-05
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | PASS |
| Plan Completeness | PASS |

## Grounding
25/25 paths ✓, 6/6 symbols ✓ (`withPanel`, `panelVisitOptions`, `useSidePanelNavigation`, `mergeQuery`, `slotSpouse`, `personPreviewUrl`), brief↔plan ✓, Progress↔Phase ✓

Verified claims: no E2E spec clicks a tree card or "Podgląd" (Phase 2 card change breaks none). Only `PersonSpouseController` drops `?person` after a write; the other controllers use `back()`. `withPanel` prevents two panels in one slot (the slot itself does not enforce it). Nested Radix modals do not need a parent `Dialog`. `mergeQuery` reads `window.location` at click time in `union-node.tsx` `choose()`.

## Findings

### F1 — Rejestr centreHints w providerze jest zbędny i kruchy

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Faza 2 §2, Critical Implementation Details
- **Detail**: `FamilySheet` miał rejestrować podpowiedzi w stanie `PersonPreviewProvider`, który tworzy wartość kontekstu inline. Rejestracja zmienia stan, a konsument renderuje się ponownie z nowym obiektem hints. Brak pętli zależał od niezapisanej memoizacji przez React Compiler. Layout i tak widzi `usePage().props.sheet` (tylko `tree/show`), a częściowa wizyta z `only` zachowuje pozostałe propsy.
- **Fix A ⭐ Recommended**: `PersonPanel` liczy `centreHints(usePage().props.sheet)` ze strażnikiem typu.
  - Strength: Bez stanu, rejestru i pułapki „remount drzewa”.
  - Tradeoff: Komponent layoutu czyta prop konkretnej strony (strażnik typu).
  - Confidence: HIGH — partial reload zachowuje niewymienione propsy.
  - Blind spot: None significant (tylko `tree/show` ma `sheet`).
- **Fix B**: Zostawić rejestr, zapisać w kontrakcie stabilną rejestrację (useMemo po `sheet`).
  - Strength: Strona jawnie publikuje kontekst.
  - Tradeoff: Dyscyplina implementacji, dwa rendery na przecentrowanie.
  - Confidence: MED.
  - Blind spot: StrictMode (podwójne register/unregister).
- **Decision**: FIXED (Fix A) — plan: Implementation Approach, Critical Implementation Details, Faza 1 §3, Faza 2 §2–3, Performance; brief: Key Decisions, Architecture

### F2 — PersonCreateDialog ma własny SectionEditorProvider

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §4
- **Detail**: `person-create-dialog.tsx:42` ma `<SectionEditorProvider personId={0}>` i `UnsavedChangesBar`. Plan go nie wymieniał, a usunięcie go skleiłoby stan brudny dialogu tworzenia z panelem osoby.
- **Fix**: Dopisać w Fazie 1 §4, że `PersonCreateDialog` zachowuje własny provider.
- **Decision**: FIXED

### F3 — guardClick dubluje istniejące guard/useGuardedClick

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Faza 1 §5
- **Detail**: `useUnsavedChangesGuard` ma już `guard(action)`, a `section-editor.tsx` ma `isPlainClick` i `useGuardedClick` (w kodzie `guardClick`). Nowa metoda hooka o tej samej nazwie byłaby trzecim wariantem.
- **Fix**: Strona źródeł składa `onPersonLinkClick` z `isPlainClick` + `guard`, bez nowego API hooka.
- **Decision**: FIXED
