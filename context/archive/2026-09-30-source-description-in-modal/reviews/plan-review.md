<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Source Description in Modal Implementation Plan

- **Plan**: context/changes/source-description-in-modal/plan.md
- **Mode**: Deep
- **Date**: 2026-09-30
- **Verdict**: REVISE
- **Verdict after triage**: SOUND (2026-09-30 — 7 fixed, 0 skipped, 0 accepted, 0 dismissed)
- **Findings**: 0 critical, 4 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

Grounding: 23/23 paths ✓, 9/9 symbols ✓, brief↔plan ✓, Progress↔Phase ✓ (4 phases, 40 rows, no checkboxes in phase blocks). No `context/foundation/lessons.md`, no `docs/reference/contract-surfaces.md`, no `.ai/rules` — those checks were skipped.

Confirmed against code (no finding): the `<Form>` ref exposes `submit()` and `getData()` (`@inertiajs/react` 3.7.1, `dist/index.js:1080-1106`); a validation error keeps the file input and typed values (`resetOnError` defaults to false, `preserveState` true on non-GET); `update.form(id)` is a POST with `_method=PUT`; nothing filters linked sources by kind, so a linked akt changed to another kind stays linked and confirmed; `SourceMigrationTest` calls `up()`/`down()` directly, so a later nullable-only migration breaks none of its tests; the global `source` route pattern affects no existing test; every frontend reader of `original_name` and every reference to `sources.create` is in the plan's lists; Playwright path, port 8000 and the demo password are as the screenshot script expects.

## Findings

### F1 — Edit-modal confirm flow leaves interception, flag and value-reading undecided

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §2 (Edit modal), Critical Implementation Details („Confirm-then-submit ordering")
- **Detail**: The plan names the timing trap but not the mechanism, and the repo has no precedent to copy (no `onBefore`, no `<Form ref>`, no `flushSync` in `resources/js`). Three things are left to the implementer. (a) Where the intercept sits: `<Form>` overwrites a consumer `onSubmit` (`node_modules/@inertiajs/react/dist/index.js:1110-1117`), so if „Zapisz" stays `type="submit"` and the intercept is a click handler, Enter in any field submits past it; the server then rejects on `kind` and the user sees an error with no way to confirm. (b) How `discard_record_fields` reaches the DOM before `submit()`: the ref handle is rebuilt only on commit (`:1106`) and `submit()` reads `new FormData(form)` synchronously. (c) How the predicate learns the typed typ and numer wpisu: Phase 2 §5 makes the description inputs uncontrolled and gives the field group no callback, while Phase 3 §1 needs „the current form values" and Phase 3 does not list `source-description-fields.tsx` as changed.
- **Fix A ⭐ Recommended**: Intercept in `<Form onBefore>` and keep everything DOM-driven. `onBefore` returns false and opens the confirm dialog when the erase list built from `formRef.current.getData()` is non-empty and the flag is not set. The flag is an always-mounted `<input type="hidden" name="discard_record_fields" defaultValue="0">`; confirming writes `'1'` to it through a ref and calls `formRef.current.submit()`; `onFinish` resets it. The in-place notice recomputes from `getData()` in `SourceKindField.onValueChange`. The nested confirm `Dialog` renders as a sibling after `</Form>`.
  - Strength: One path covers the button and the Enter key; the Phase 2 field group stays as specified; no React-state timing at all.
  - Tradeoff: First use of `onBefore` and `<Form ref>` in the repo, plus one imperative DOM write.
  - Confidence: HIGH — `onBefore` returning false cancels the visit (`@inertiajs/core/dist/index.js:3204`); `submit`/`getData` are on the ref in the installed version.
  - Blind spot: Not exercised in a browser; whether `processing` flickers when `onBefore` cancels is unverified.
- **Fix B**: Make typ and numer wpisu controlled state owned by the edit dialog, switch „Zapisz" to `type="button"` while the erase list is non-empty, and confirm with `flushSync(() => setConfirmed(true))` followed by `formRef.current.submit()`.
  - Strength: The erase list is ordinary React state, so the notice re-renders without reading the DOM.
  - Tradeoff: Changes the Phase 2 field-group contract (two controlled fields); Enter-key safety rests on the HTML implicit-submission rule; `flushSync` is new to the repo.
  - Confidence: MEDIUM — follows from the commit-only ref refresh, but untested here with React Compiler on.
  - Blind spot: Mobile keyboards' „Go" key behaviour with no submit button is unverified.
- **Decision**: FIXED — Fixed via Fix A

### F2 — Production rollback step is a prohibited command

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Migration Notes („Rollback: run `migrate:rollback` while the new code is still deployed")
- **Detail**: `AppServiceProvider.php:39-41` calls `DB::prohibitDestructiveCommands(app()->isProduction())`, and in the installed Laravel 13.32 that list includes `RollbackCommand` (`vendor/laravel/framework/src/Illuminate/Support/Facades/DB.php:138-145`). In production `migrate:rollback` exits with „This command is prohibited from running in this environment", so the rollback as written cannot run. The archived S-16 plan carried the same step (`context/archive/2026-09-29-scans-become-sources/plan.md:501`), so this is a recurring assumption.
- **Fix**: Rewrite the rollback as code-only: revert the merge on `main` and run `scripts/deploy.sh`, leaving the seven nullable columns in place — the previous code never reads them, no description is lost, and a later redeploy finds the migration already recorded. Drop the `migrate:rollback` step and the „descriptions are lost" sentence (here and in the brief's Open Risks).
- **Decision**: FIXED — fix applied to plan

### F3 — An existing index test breaks in Phase 1 and is not listed

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §10 (Tests), Success Criteria 1.3
- **Detail**: `tests/Feature/SourcesTest.php:45-47` asserts `sources.0` inside a scoped closure with `hasAll([...10 keys])`. Scoped closures fail on any key not interacted with, so the nine keys Phase 1 §8 adds to `SourceResource` raise „Unexpected properties were found in scope [sources.0]". Phase 1 §10 lists only new tests, so the phase's own gate (1.3) goes red on a test the plan does not mention.
- **Fix**: Add to Phase 1 §10: „Modified — `sources index shows only the authenticated user own sources, newest first`: extend the `hasAll` list with `name`, `display_name`, `record_type`, `record_type_label`, `entry_number`, `year`, `provenance`, `url`, `note`" (extend rather than `->etc()`, since this test is the resource-shape contract).
- **Decision**: FIXED — fix applied to plan

### F4 — Preview panel: „Dodaj opis" wiring, the „undescribed" boundary and the repeated kind are undecided

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §3 (Description panel), §4 (Preview page layout)
- **Detail**: (a) `SourceDetails` „takes `source`" yet its empty state has a „Dodaj opis" button that opens the edit modal; `SourceEditDialog` is trigger-based and needs `kinds` and `recordTypes`, so the second trigger has no specified owner. (b) „With no description field filled" is undecided at the tie: Rodzaj is in the row list and is always filled, and `name` is a description field but not a panel row — a source with only a name is either „described" or not depending on what the implementer writes. (c) The header line now starts with rodzaj and the panel has a Rodzaj row, while the existing `Badge` at `pages/sources/show.tsx:46` is not mentioned, so the kind would show three times.
- **Fix**: State in Phase 3: (a) `SourceDetails` takes an `emptyAction` slot and `show.tsx` passes a second `SourceEditDialog` whose trigger is „Dodaj opis"; (b) „undescribed" means all seven description columns are null — export that predicate from `source-description.ts` with a „name only → described" test case; (c) the `Badge` at `show.tsx:46` is removed.
- **Decision**: FIXED — fix applied to plan

### F5 — A trait-level `attributes()` would be silently overridden

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 1 §4–5 („Trait with the rules and attributes", „Both use the trait")
- **Detail**: `SourceStoreRequest.php:31-37` already defines `attributes()`; a class method wins over a trait method without any error, which would drop the Polish names for the description fields. Existing traits expose helpers only (`PartialDateValidationRules::partialDateRules()`), and requests merge them by hand (`PersonStoreRequest.php:37-38`, `:47-67`).
- **Fix**: Specify the trait as two helpers, `sourceDescriptionRules()` and `sourceDescriptionAttributes()`, spread into each request's own `rules()` and `attributes()`.
- **Decision**: FIXED — fix applied to plan

### F6 — Upload progress bar sits below the fold on the fast path

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §6 (Upload modal, body order)
- **Detail**: The body order ends with the progress bar, after seven description fields. On the main path — a file and a kind, nothing else — the body is still scrolled to the top when „Zapisz" in the pinned footer is pressed, so at 390 px a 20 MB upload shows only two disabled buttons. Manual check 2.10 expects the bar to be visible.
- **Fix**: Render the progress bar directly under the file input (or in the footer) instead of at the end of the body.
- **Decision**: FIXED — fix applied to plan

### F7 — Four copy-from-precedent details are missing from the contracts

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §8, §9, §11; Phase 2 §6, §10; Phase 4 §1
- **Detail**: (1) `<Form>` wrapping body and footer needs `className="flex min-h-0 flex-1 flex-col"` or the body does not scroll and the footer is not pinned (`pick-or-create-person-dialog.tsx:201`, `person-create-dialog.tsx:128`); the contract omits it. (2) The archived `shots.mjs` `before` flavour is `/scans`-specific (`:19` root, `:107` „skan" regex, `:118` needle `akt-urodzenia-jan`) and fails if copied verbatim; `sourceId()` matches card text, so `after` needles for the described seeds must be names, and the three new seeds should get explicit `original_name` values (the factory default is a random slug). (3) `EventResource.php:30` has its own docblock shape that needs `display_name`; the plan names only `:55` and the `MarriageResource` docblock. (4) After `source-picker-dialog.tsx:105` points to `sourcesIndex()`, the `SourceController` import at `:4` is unused and lint runs with `denyWarnings`.
- **Fix**: Add one line per item to the named sections: the form class in Phase 2 §6 and Phase 3 §2; „rewrite the `before` flavour for `/sources`, needles by file name before and by name after" in Phase 1 §11 and explicit `original_name` in Phase 1 §9; `EventResource.php:30` in Phase 1 §8; „drop the unused import" in Phase 2 §10.
- **Decision**: FIXED — fix applied to plan
