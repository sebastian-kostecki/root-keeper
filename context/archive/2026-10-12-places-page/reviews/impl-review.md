<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Strona „Miejsca” (/places)

- **Plan**: context/changes/places-page/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical 2 warnings 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | FAIL |

## Automated verification (run during review)

- Pest (Places/Localities/Parishes/OwnershipBoundaries/AppShell): 163 passed
- `npm run test:js`: pass · `npm run types:check`: pass · `npm run check`: pass
- Larastan: 0 errors · Pint: pass · `grep ring-[3px]` in view files: empty
- Playwright (`places-page.spec.ts`, `places-merge-and-delete.spec.ts`): NOT run — E2E server on :8100 is down

## Findings

### F1 — Progress ticks 4.2 / 4.5 / 4.6 (and manual 1.6–3.7) have no evidence

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/places-page/plan.md:480-488, context/changes/places-page/screenshots/verdict.md
- **Detail**: 4.5 says the "after" screenshots and verdict confirm C1–C5, but `screenshots/after/` does not exist and every row of `verdict.md` says "nie zweryfikowano". 4.2 (new e2e spec passes) is contradicted by `verdict.md` ("nie został uruchomiony"), and the spec still has not been run. 4.6 (deliberate `ring-[3px]` turns test:js red) leaves no trace. `verdict.md` also says the dev DB had no user and the :8100 server was down, so the manual browser checks 1.6–3.7 (incl. 375 px sticky column, 2.3) are doubtful. `change.md` status `implemented` is therefore not supported.
- **Fix**: Untick 4.2, 4.5, 4.6 (and the manual items you did not personally check), set `change.md` back to `implementing`, then seed the demo tree on the E2E server, run both specs and `shots.mjs … after`, and fill `verdict.md`.
  - Strength: Restores Progress as a true source of truth for `/10x-status` and `/10x-archive`.
  - Tradeoff: Needs a running seeded E2E server (extra setup).
  - Confidence: HIGH — contradiction is in the repo's own files.
  - Blind spot: Manual checks may have been done off-record by the user.
- **Decision**: FIXED via Fix now — odznaczono 4.2, 4.5, 4.6 w plan.md, change.md → implementing; uruchomienie specy i zrzutów „po” nadal do zrobienia

### F2 — Hand-typed URL with both `?locality=` and `?parish=` opens two panels

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/places/index.tsx:100-103,409-420; app/Http/Controllers/PlaceController.php:39-68
- **Detail**: The server resolves both panel keys independently and the page renders both panels, while `openKey` (guard) only sees the locality. Plan assumes "najwyżej jeden panel" (only guaranteed by `withPanel` on client navigation). Old pages had a single panel each, so this is new.
- **Fix**: Render `ParishPanel` only when `localityPanel === null` (matches `openKey` precedence); add a one-line test or comment.
- **Decision**: FIXED via Fix now — ParishPanel renderowany tylko gdy localityPanel === null (places/index.tsx:418)

### F3 — Missing parish resolver cases in PlacesTest

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/PlacesTest.php
- **Detail**: Plan lists resolver cases (null, `new`, own id, missing → 404) for both panels; for parishes only no-key and foreign-id 404 are covered (missing id 404 and non-numeric key → null are not).
- **Fix**: Add the two parish cases mirroring the locality ones.
- **Decision**: FIXED via Fix now — dodano test parafii (nienumeryczny klucz → null, brak id → 404) w PlacesTest

### F4 — `clearSearch` pushes history instead of replacing

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/places/index.tsx:311-314
- **Detail**: Plan: search = `replace`, tab switch = `push`. `clearSearch` calls `visit('')` with the default (push), so "Back" after clearing returns to the filtered state.
- **Fix**: Make clearing a replace visit, like the debounced search.
- **Decision**: FIXED via Fix now — clearSearch woła visit('', true) (replace)

### F5 — Locality usage counts computed on the Parishes tab

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/PlaceController.php:23-29
- **Detail**: Both lists load on every full GET (segment counts need both), so the 4 `withCount` subqueries run even when the usage column is hidden. No N+1, no pagination (same as before).
- **Fix**: Accept; revisit only if lists grow.
- **Decision**: SKIPPED
