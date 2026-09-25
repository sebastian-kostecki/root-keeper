<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Marriage with Shared Source

- **Plan**: context/changes/marriage-with-shared-source/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-09-25
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated checks were re-run on 2026-09-25. The 120 feature tests (848 assertions) in MarriagesTest, PersonsTest, EventScansTest, ScansTest and ParentChildRelationsTest passed. Pint, tsc, `npm run check` and `npm run build` were all clean.

Tree ownership is enforced on every marriage endpoint. `CreateMarriage` locks both people in id order and checks the pair in either order. `DeletePerson` never deletes scans. Nothing from "What We're NOT Doing" was built.

## Findings

### F1 — Spouse birth/death dates lazy-loaded per marriage (N+1)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/PersonController.php:97 (read at app/Http/Resources/MarriageResource.php:54-55)
- **Detail**: `show` loads `marriages.participants`, but `MarriageResource` calls `$spouse->birth()` and `$spouse->death()`, which read `$this->events` (app/Models/Person.php:89-97). That is one lazy query per marriage. The parents and children use the eager-loaded `parents.events` and `children.events` on the same line. The plan's load list had the same gap. A secondary point: marriage events and their scans are loaded twice, once through `events.scans` and again through `marriages.scans`. That is harmless at personal scale.
- **Fix**: Add `'marriages.participants.events'` to the `$person->load([...])` list in `PersonController::show`.
- **Decision**: FIXED

### F2 — Marriage form jumps to top of page after submit

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/marriage-form-dialog.tsx:126
- **Detail**: The `<Form>` has no `preserveScroll`, and Inertia's `<Form>` defaults to `false`. Every other change made on the preview sets `preserveScroll: true`: relative-picker-dialog.tsx:70, remove-relation-dialog.tsx:35 and remove-marriage-dialog.tsx:62. Adding or editing a marriage, or getting a validation error, therefore scrolls the preview to the top.
- **Fix**: Add `preserveScroll` to the marriage `<Form>`.
- **Decision**: FIXED (via `options={{ preserveScroll: true }}` — Inertia v3 `<Form>` takes visit options through `options`, as in two-factor-recovery-codes.tsx:85)

### F3 — Update checks the event type after validation

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/MarriageUpdateRequest.php:19 (type check at app/Http/Controllers/MarriageController.php:39)
- **Detail**: The plan says that a non-marriage event on a marriage endpoint responds 404. `authorize()` checks only tree ownership, so validation runs before the controller's `abort_unless(type === Marriage, 404)`. A PUT to `/marriages/{ownBirthEventId}` with an invalid payload returns 302 with validation errors, not 404. No data changes and nothing leaks across trees, but the boundary is inconsistent with `destroy`. The existing test (MarriagesTest.php:245) sends only a valid payload.
- **Fix**: In `MarriageUpdateRequest::authorize()`, return `Response::denyAsNotFound()` when the route event is not `EventType::Marriage`, then `Gate::inspect`. Remove the controller `abort_unless` and add an invalid-payload case to the 404 test.
- **Decision**: FIXED (new assertion verified to fail with 302 before the fix)

### F4 — Candidate "load on open" logic duplicated instead of moved into PersonCandidateList

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/persons/marriage-form-dialog.tsx:35-73 (and relative-picker-dialog.tsx:29-57)
- **Detail**: The plan moved the whole candidate list, including "load on open", into `PersonCandidateList`. The component got the skeleton, the empty state, the filter and the disabled rows. The `router.reload({ only: ['relationCandidates'] })` call and its loading/loaded state still live in each dialog and are now copied between them.
- **Fix**: Move the reload and loading state into `PersonCandidateList`, or a `useRelationCandidates()` hook next to it, and have both dialogs consume it.
  - Strength: This completes the extraction the plan described, and a third picker (for example S-09) would get it for free.
  - Tradeoff: It touches the S-06 relative picker, which currently works and has no browser tests. The parents/children picker would need a manual re-check.
  - Confidence: MED — two call sites with near-identical code, but the reload is tied to each dialog's `open` state.
  - Blind spot: I haven't checked whether the two dialogs differ in when they reset `loaded` on close.
- **Decision**: FIXED (new `resources/js/hooks/use-relation-candidates.ts` used by both dialogs; parents/children picker needs a manual re-check)

### F5 — `pluralPhrase` copied into a second dialog

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: resources/js/components/persons/remove-marriage-dialog.tsx:23-45
- **Detail**: `pluralPhrase` is duplicated verbatim from delete-person-dialog.tsx:21-43. This is accidental architecture: the Polish plural rules now live in two places.
- **Fix**: Move `pluralPhrase` to `resources/js/lib/` and import it in both dialogs.
- **Decision**: FIXED (moved to `resources/js/lib/plural.ts`)

### F6 — `spouse` made nullable, which the plan did not specify

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Http/Resources/MarriageResource.php:50 (resources/js/types/person.ts:31-36)
- **Detail**: The plan types `spouse` as non-null. The implementation returns `null` when there is no second participant, and the UI falls back to "Nieznany małżonek" (marriages-section.tsx, marriage-form-dialog.tsx). This is defensive only, but it is an undocumented deviation.
- **Fix**: Note it in the plan as an addendum. It is a defensive fallback for a state `DeletePerson` prevents.
- **Decision**: FIXED (addendum added to plan.md `## Addenda`)

### F7 — Thin assertions on rejection and cascade tests

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/MarriagesTest.php:114-141, 312-326
- **Detail**: The self-marriage and duplicate-pair tests check only the `spouse_id` error key. They don't check the messages "Osoba nie może poślubić samej siebie." and "Ta para ma już małżeństwo.". The spouse-deletion test doesn't check that the remaining spouse's preview no longer shows the marriage. That was a plan Testing Strategy bullet ("the other spouse and the scan remain").
- **Fix**: Assert the exact messages and the remaining spouse's `marriages` prop being empty.
- **Decision**: FIXED

### F8 — `foreach` by reference without `unset`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Requests/PersonStoreRequest.php:25-27
- **Detail**: `foreach ($deathRules as &$rule)` leaves `$rule` as a live reference to the last element. It is harmless today but a known PHP trap if the code is edited later.
- **Fix**: Replace the loop with `array_map` (or `unset($rule)` after the loop).
- **Decision**: FIXED (array_map)
