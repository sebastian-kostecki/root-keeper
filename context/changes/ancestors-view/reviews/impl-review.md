<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Widok przodków (S-15 `ancestors-view`)

- **Plan**: context/changes/ancestors-view/plan.md
- **Scope**: Full plan (5 of 5 phases)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-10-11
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 7 observations
- **Triage**: complete — F1, F2, F4, F5, F6, F7, F8 fixed; F3 left as is (Fix A)

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Verification run (this review)

- Pest: `AncestorsTest`, `OwnershipBoundariesTest`, `PersonsTest`, `TreeTest` — 242 passed, 1419 assertions
- `composer types:check` (Larastan) — 0 errors
- `pint --test` — passed
- `npm run types:check` — passed
- `npm run check` (format + lint) — 231 files formatted, no warnings
- `npm run test:js` (Vitest) — 19 files, 265 passed
- Not re-run: Playwright `ancestors-view.spec.ts`, `npm run build`, seeder (marked `[x]` in Progress with commits; spec read, not executed)
- Manual items in Progress are all `[x]` with commit SHAs; no evidence contradicting them in the diff

## Plan adherence summary

All 16 planned change blocks: MATCH. No DRIFT, no MISSING, no violations of "What We're NOT Doing".
Small EXTRAs, all harmless: public `PersonOrdering::parentRank()` (reused by `BuildFamilySheet` focus_side), second `PersonsTest` case (deleting an ancestor keeps the view), seeder also calls `CreateMarriage` and `SetTreeRootPerson`, breadcrumb "Przodkowie" carries `?generations=`.

## Findings

### F1 — `zoomControls` in the shared ZoomCanvas has no automated coverage

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/zoom-canvas.tsx:165-184, 311-357
- **Detail**: `markMoved`, `zoomByButton`, `fitAgain`, `onWheelStart`/`onPinchStart` implement the plan's trickiest invariant (manual zoom sets `movedRef` so resize no longer refits; "Dopasuj" resets it and refits). Nothing in `tests/` references "Powiększ", "Dopasuj" or `zoomControls`; `ancestors-view.spec.ts` never touches the buttons. The component is shared with `family-sheet.tsx`, so a regression here is easy to miss. Phase 3 manual checks were the only verification.
- **Fix**: Add one E2E scenario to `tests/e2e/ancestors-view.spec.ts` (or a sibling spec): click "Powiększ" and assert the canvas transform scale increases; open/close a panel (canvas resize) and assert the scale is not reset; click "Dopasuj" and assert the fit is restored.
  - Strength: Pins the exact behaviour the plan calls out in "Critical Implementation Details"; E2E infrastructure and seed helpers already exist.
  - Tradeoff: Asserting scale needs a stable observable (e.g. transform style on the content wrapper); keep it to relative assertions (greater than / equal), not pixel values.
  - Confidence: MED — depends on whether the transform is observable without CSS selectors.
  - Blind spot: Have not checked whether `getByTestId` already exists on the canvas content.
- **Decision**: FIXED — added E2E `ancestors-view: a manual zoom survives a canvas resize until "Dopasuj"` (card width via boundingBox, no CSS). Verified: removing `markMoved()` from `zoomByButton` fails the test; restored.

### F2 — `ZOOM_NO_PAN_CLASS` on the zoom buttons is dead; no disabled state at limits

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/zoom-canvas.tsx:317, 326, 335
- **Detail**: The button bar is rendered as a sibling before `TransformWrapper`, outside the library's pan area, so `panning.excluded` never applies to it and the class is a no-op (the plan's "must not start panning" requirement is met structurally, not by the class). Buttons are also never disabled at floor/max scale.
- **Fix**: Remove `className={ZOOM_NO_PAN_CLASS}` from the three buttons (keep the export, it is used by `union-node.tsx`).
- **Decision**: FIXED — removed from the three buttons; check + types:check pass.

### F3 — Parent-slot rule duplicated between `BuildAncestorChart` and `BuildFamilySheet`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: app/Actions/Trees/BuildAncestorChart.php:85-111, app/Actions/Trees/BuildFamilySheet.php:393-421
- **Detail**: Both build "two slots from known parents, replace missing by Nieznany ojciec / Nieznana matka / Nieznany rodzic by the gender of the known parent". Ordering was extracted to `PersonOrdering::parents`, but the placeholder-label rule and the 0/1/2-parent branching exist twice, so a label or rule change must be made in two places. The plan explicitly chose "same rule as the sheet", not a shared builder.
- **Fix**: Later, extract the label/slot decision (e.g. `PersonOrdering::parentSlots()` returning known-or-placeholder-label) and use it in both; not needed now.
- **Decision**: Fix A — left as is; extract a shared slot helper when the rule next changes.

### F4 — E2E does not assert the open panel after entry and after the generations switch

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/e2e/ancestors-view.spec.ts:44-55, 83-86
- **Detail**: After step 2 (entry) the child's panel staying open (`?person=`) is not asserted; after step 4 (generations = 2) the URL predicate checks only `generations`, not the pathname or that the panel is kept, although `changeGenerations` copies the query. The plan promises panel preservation on recentre only, so this is a coverage gap, not a deviation.
- **Fix**: Assert the child's dialog is visible after entry and after the switch, and add the pathname to the step-4 URL predicate.
- **Decision**: FIXED — assertions added; spec passes (3 passed).

### F5 — Untested branches: ungendered single parent, guest redirect

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: app/Actions/Trees/BuildAncestorChart.php:109; tests/Feature/TreeTest.php:104
- **Detail**: Single known parent without gender → "Nieznany rodzic" placeholder is only exercised visually by the seeder. The guest-redirect dataset in `TreeTest` was not extended with `tree.ancestors` (route is in the `auth`/`verified` group, risk low).
- **Fix**: Add one Pest case for the ungendered parent and add `tree.ancestors` to the guest-redirect list.
- **Decision**: FIXED — dataset case `rodzic bez płci` in AncestorsTest, `tree.ancestors` in the TreeTest guest test; 53 passed, Pint clean.

### F6 — Two weak tests

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/AncestorsTest.php:201-212, 232; resources/js/lib/ancestor-layout.test.ts:105-116
- **Detail**: The cycle test's `depth <= 4` is guaranteed by the generations bound alone (proves "does not hang", nothing about shape). The other-user test (line 232) claims "the chart is not built" but asserts only `assertNotFound()` and duplicates the `OwnershipBoundariesTest` case. The layout `links` test checks only `from <= trunk <= to`; uneven spans (e.g. 3 columns, trunk 1.5) are not pinned.
- **Fix**: Assert the repeated-flag/shape in the cycle test, rename or drop the duplicate 404 test, and add one exact-value link case (3-column layout).
- **Decision**: FIXED — cycle test asserts the exact node sequence and `repeated`; 404 test renamed (kept, tests are not deleted without approval); exact-value 3-column link test added. Pest 16 passed, Vitest 266 passed.

### F7 — Inaccurate arithmetic in `ANCESTORS_FIT_FLOOR` comment

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/fit-view.ts:8-9
- **Detail**: Comment says 32 cards ≈ 5120 px (10 rem each) and ignores gaps; real width is 32 × 10.75 rem − 0.75 rem ≈ 5490 px at 16 px/rem. Floor 0.1 still fits 1000 px? 549 px — yes, conclusion unaffected.
- **Fix**: Correct the number in the comment (include the 0.75 rem gap).
- **Decision**: FIXED — comment now states 5490 px, scale 0.18, floor holds down to ~550 px screens.

### F8 — `PersonPanel` detects the ancestors page by the generic prop name `chart`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/person-panel.tsx:35-37
- **Detail**: `'chart' in props` mirrors the existing `'sheet' in props` and is what the plan prescribed, but a future page passing an unrelated `chart` prop would switch the panel into ancestors mode (hidden "Pokaż przodków" / wrong `generations`).
- **Fix**: Leave as is, or rename the prop to `ancestorChart` when a second chart-like page appears.
- **Decision**: FIXED — Inertia prop renamed `chart` → `ancestorChart` (controller, page, `PersonPanel`, `AncestorsTest`). plan.md still says `chart`; the rename supersedes it. Pest 243, Vitest 266, E2E 4, check/types/Pint clean.

## Checked and clean

- Authorization: `Gate::authorize('view')` → `denyAsNotFound` 404; `?person=` ownership checked in `HandleInertiaRequests::previewPerson`; route in `auth, verified`; `OwnershipBoundariesTest` covers URL and `?person=`.
- Recursion/performance: depth bounded by `generations` ≤ 6 (≤ 63 person nodes), BFS with visited set, cycle-safe, constant query count (one graph + one people query).
- Input: `generations` whitelisted to 2–6, falls back to 4; no SQL concatenation; React escapes output.
- `ZoomCanvas` with `zoomControls=false`: DOM and behaviour identical; effect cleanup complete; `activationKeys`/`onWheelStart`/`onPinchStart` valid in react-zoom-pan-pinch 4.2.0.
- Seeder: not in `DatabaseSeeder`, delete scoped to the demo user inside a transaction, same pattern as `TreeViewDemoSeeder` (factory password, no production guard — consistent with the existing seeder).
- Wayfinder routes, no hardcoded URLs, no `eslint-disable`/`ts-ignore`; E2E uses `getByRole`/`getByText`, unique suffix, no `waitForTimeout`.
