<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Dodawanie osób na drzewie w panelu bocznym

- **Plan**: context/changes/tree-add-person-panel/plan.md
- **Mode**: Deep
- **Date**: 2026-10-11
- **Verdict**: REVISE → SOUND (po triage)
- **Findings**: 0 critical · 3 warnings · 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
42/42 paths ✓, 6/6 symbols ✓, brief↔plan ✓, Progress↔Faza ✓

Potwierdzone w kodzie: `personCandidates` jest `Inertia::optional` także w `TreeController@index` (lista probanta ma źródło); specy slotów przetrwają fazę 2 bez zmian (tytuł z półpauzą, sloty jako `<button>`); jedyni wywołujący endpointy dodawania są na drzewie; POST przechodzi przez `router.on('before')` strażnika.

## Findings

### F1 — Odsłanianie slotu w ZoomCanvas nie przetrwa obecnego schedule()

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 4 §2, Critical Implementation Details
- **Detail**: `schedule()` (`zoom-canvas.tsx:118-126`) ma jeden `frame` + `cancelAnimationFrame` i `fit()` tylko gdy widok nieruszony; `ResizeObserver` po zwężeniu wrappera anuluje wymuszony przebieg odsłaniania z tej samej klatki. Deps efektu `[anchorRef, floor, max]` (`:163`) — inline tablica refów zmienia tożsamość co render. Klucz canvasu remontuje go po zapisie małżonka.
- **Fix**: Flaga `pendingRevealRef` konsumowana w rAF niezależnie od `movedRef`; osobny efekt na `revealKey`; stabilne refy kotwic.
  - Strength: Usuwa wyścig z `ResizeObserverem` (flaky 4.5/4.7).
  - Tradeoff: Więcej stanu w ref-ach współdzielonego komponentu.
  - Confidence: HIGH — kod `schedule()` sprawdzony.
  - Blind spot: `setTransform` w trakcie animacji `fit()` niesprawdzony.
- **Decision**: FIXED

### F2 — Strażnik działa inną drogą, niż opisuje plan

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 2 §5–6, Faza 5 §2
- **Detail**: Przy `?add=` zewnętrzny `record` = null (`app-layout.tsx:19-25`), więc `requestExit` layoutu nie chroni formularza dodawania — robi to zagnieżdżony `router.on('before')`. `close()` to `router.replace` bez `before`. Brak odniesienia do `resolver`/`settle` (`person-create-dialog.tsx:69-74,122-127`). Spec strażnika nie testuje kliknięcia innego slotu.
- **Fix**: Opisano oba mechanizmy (layout → panel osoby, zagnieżdżony `before` → panel dodawania), X/Escape przez zagnieżdżony `requestExit`, dopisano `resolver`/`settle`, trzeci przebieg specu strażnika (inny slot).
- **Decision**: FIXED (skorygowane: `requestExit` layoutu przy slotach zostaje — chroni brudny panel osoby)

### F3 — Fallback redirectu bez `person=` łamie helpery E2E z fazy 5

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §1, Faza 5 §1
- **Detail**: `page.request` w Playwright nie wysyła Referera; `createParent`/`createChild` mają czytać id z `?person=` w `Location` (8 speców używa helperów).
- **Fix**: Fallback też dostaje `person=<id>`; testy Pest bez Referera i z obcym hostem.
- **Decision**: FIXED

### F4 — Czwarty parser Referera zamiast reużycia

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 1 §1
- **Detail**: `MarriageController::urlAfterDelete` (`:68-86`) już parsuje Referer ze strażnikiem hosta i `//`.
- **Fix**: `PersonPanelRedirect` wzorowany na `urlAfterDelete`.
- **Decision**: FIXED

### F5 — „Bez przeładowania listy przy zmianie slotu” nie ma kroku

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Performance Considerations vs Faza 2 §5
- **Detail**: Obecny wzorzec woła `load()` przy każdym otwarciu (`pick-or-create-person-dialog.tsx:104-108`).
- **Fix**: `load()` raz przy montowaniu panelu.
- **Decision**: FIXED

### F6 — `after()` zamiast konwencji `withValidator`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 1 §3
- **Detail**: Siostrzane requesty używają `withValidator()` + `parent::withValidator()` (`PersonParentStoreRequest.php:39-54`).
- **Fix**: Reguła probanta w `withValidator()` z wywołaniem rodzica.
- **Decision**: FIXED
