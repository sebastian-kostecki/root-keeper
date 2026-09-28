<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Graphical Tree View (Family Sheet)

- **Plan**: context/changes/graphical-tree-view/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-09-28
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Automated verification (run during review)

| Command | Result |
|---|---|
| `sail artisan test --compact` TreeTest, PersonsTest, PartialDateTest, ParentChildRelationsTest, MarriagesTest, Settings/ProfileUpdateTest | PASS: 115 tests, 861 assertions |
| `sail npm run check` | PASS: 104 files formatted, 0 lint warnings |
| `sail npm run types:check` | PASS |
| `sail composer run types:check` (Larastan level 7) | PASS: 0 errors |
| `sail bin pint --test` | PASS |
| `sail npm run build` | PASS |
| `sail artisan migrate:status` | Both new migrations Ran. The rollback round-trip was not re-run, to avoid dropping gender and proband data on the dev DB. |

## Findings

### F1 — Visual gate not delivered: no screenshots, yet 5.4/5.5 ticked

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria / Plan Adherence
- **Location**: context/changes/graphical-tree-view/screenshots/ (missing); plan.md:734-736
- **Detail**: Phase 5 and the CLAUDE.md contract ("Visual gate: a kitchen sink rendering every state, screenshotted") both require one PNG per state:
  - empty state;
  - proband sheet;
  - each union;
  - placeholders;
  - no-union person;
  - dark mode;
  - 390px.

  The folder does not exist. Row 5.3 is honestly unchecked ("skipped by decision", commits dbe1b1e and 64fb36c). Rows 5.4 (dark mode legible) and 5.5 (390px alignment) are ticked with no artifact to back them.

  The AA contrast of the `--gender-*` tokens against `card` (a Phase 4 contract) is also unverified.

  The kitchen-sink seeder exists and matches every listed state, so the gate is cheap to finish.
- **Fix A ⭐ Recommended**: Seed `TreeViewDemoSeeder` and capture the 7+ screenshots as `<nn>-<state>.png`, then tick 5.3.
  - Strength: Completes the design-system contract the lesson is about. The seeder already renders every state.
  - Tradeoff: About 10 minutes of manual Chrome work (no browser tool is connected in this session).
  - Confidence: HIGH — the capture method is fully specified in plan.md:592-594.
  - Blind spot: None significant.
- **Fix B**: Record a waiver in the plan (why screenshots were skipped) and untick 5.4/5.5 until they have evidence.
  - Strength: Progress stays honest without extra work now.
  - Tradeoff: The slice closes without its visual gate, and future UI changes have no baseline to compare against.
  - Confidence: MED — acceptable only if the visual check truly happened off-record.
  - Blind spot: Whether the dark mode and 390px checks were actually performed.
- **Decision**: SKIPPED

### F2 — Query-count test cannot catch a missed focus/partner in the batch load

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/TreeTest.php:397-428
- **Detail**: The plan (lines 377-380) names the silent N+1 risk: the batched `with('events')` must include the focus and **all** union partners, and "only the query-count test catches" a miss.

  The test grows only children and partner-parents. The focus and the set of unions (1 marriage + 1 co-parent) stay fixed in both runs. If the focus or a non-selected partner fell out of the batch, the lazy load would add the same +N in both runs, and the equality check would still pass.

  The implementation is correct today (BuildFamilySheet.php:157-173); only the regression guard is weak.
- **Fix**: Make the union count grow with `$extraPeople` too, so a missed partner changes the count between runs. For example, add an extra co-parent or marriage partner per iteration. Optionally assert that no `events` query is issued after the batch.
- **Decision**: FIXED — each iteration now adds a co-parent + shared child so the union count grows; mutation check (co-parent events not eager-loaded) fails the test 32 vs 12

### F3 — Proband write/read not scoped to the user's tree (defense in depth)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Trees/SetTreeRootPerson.php:15; app/Http/Controllers/TreeController.php:21
- **Detail**: The action writes `root_person_id` without checking `$person->tree_id === $tree->id`. `index` then follows `$tree->rootPerson`, and nothing re-scopes it to the tree.

  This is not exploitable today: the only HTTP caller runs `Gate::authorize('update', $person)` first, and the other-user 404 is tested. A future caller (import, seeder, API) could still point one tree at another tree's person, and then `/tree` would render a foreign person's family.
- **Fix**: In the action, guard with `abort_unless($person->tree_id === $tree->id, 404)` or throw an exception.
- **Decision**: FIXED — abort_unless same-tree guard in SetTreeRootPerson + test calling the action with a foreign person

### F4 — Children mapper has no null handling (500 on inconsistent edges)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Trees/BuildFamilySheet.php:126
- **Detail**: `fn (int $id): Person => $this->people->get($id)` has no null handling. The partner and parent mappers do handle it: they use `?Person` plus `->filter()` (lines 81-82, 229-230).

  A child id missing from the batch throws a TypeError, and the tree page returns 500. Only inconsistent data can trigger this.
- **Fix**: Mirror the sibling mappers: `fn (int $id): ?Person => $this->people->get($id)`, then `->filter()`.
- **Decision**: FIXED — children mapper now ?Person + ->filter(), like the sibling mappers

### F5 — Parent-order test covers only one insertion order

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/TreeTest.php:255-268
- **Detail**: The plan's test contract (plan.md:435) is "male+female in either DB order puts the male in slot 0". The test only links the mother first.
- **Fix**: Turn it into a dataset over `[mother-first, father-first]` link order.
- **Decision**: FIXED — dataset over mother-first / father-first link order

### F6 — New file-local global helpers in TreeTest

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/TreeTest.php:93-129
- **Detail**: `linkParent`, `attachDeath` and `sheetFor` are top-level functions declared in a test file. This is the same "undefined function / redeclare" hazard that Phase 3 removed by moving `attachBirth`/`attachMarriage` into `tests/Pest.php` (plan.md:80-83). `linkParent` and `attachDeath` are general-purpose, and the next relations test will want them.
- **Fix**: Move `linkParent` and `attachDeath` into `tests/Pest.php`. Keep `sheetFor` local, or move it too.
- **Decision**: FIXED — linkParent + attachDeath moved verbatim to tests/Pest.php; sheetFor stays local

### F7 — „Ustaw jako probanta” duplicated in two pages without processing state

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/tree/show.tsx:84-96; resources/js/pages/persons/show.tsx:51-63
- **Detail**: The same `router.put(TreeRootPersonController.update(...))` button is inlined twice, and neither copy has a disabled/processing state. Sibling write components (`delete-person-dialog.tsx`, `remove-relation-dialog.tsx`) track `processing`. The PUT is idempotent, so double clicks are harmless. This is a "missing shared component" charge in the lesson's terms.
- **Fix**: Extract `SetRootPersonButton` into `resources/js/components/tree/` with a processing state, and use it on both pages.
- **Decision**: FIXED — extracted resources/js/components/tree/set-root-person-button.tsx (processing state, preserveScroll); used on tree/show and persons/show

### F8 — Unplanned behaviour: duplicate-marriage dedupe and toolbar „Podgląd”

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: app/Actions/Trees/BuildFamilySheet.php:57; resources/js/pages/tree/show.tsx:100-104
- **Detail**: Two marriages to the same partner collapse into one union (the earliest by sort key). This is a sensible guard, but the plan does not describe it and no test covers it. The toolbar also gained a „Podgląd” button for the focus person, which duplicates the focus card's own Podgląd icon. Both are benign.
- **Fix**: Add a short addendum to plan.md under Critical Implementation Details (the dedupe rule plus the toolbar button). Optionally add one test for the dedupe.
- **Decision**: FIXED — addendum in plan.md Critical Implementation Details + TreeTest case for repeat marriages

## Triage summary

| Decision | Findings |
|---|---|
| Fixed | F2, F3, F4, F5, F6, F7, F8 (7) |
| Skipped | F1 (1) |

Post-fix verification: 118 tests pass (TreeTest, PersonsTest, PartialDateTest, ParentChildRelationsTest, MarriagesTest, ProfileUpdateTest); npm check, types:check, Larastan, Pint and build pass.

## Checked and OK

- **Plan adherence**:
  - Every planned file for Phases 1-4 matches its intent.
  - Every union, `?spouse`, slot, `focus_side` and lifespan contract in `BuildFamilySheet` and `SheetPersonResource` was verified line by line.
  - `PersonUpdateRequest` inherits the gender rule from `PersonStoreRequest`.
- **Authorization**:
  - `tree.show` and `tree.root-person.update` go through `PersonPolicy`, which returns a 404 for another user's person, and this is tested.
  - `BuildFamilySheet` reads edges and people through the focus's tree.
  - `?spouse` is only matched against the union keys the builder computed itself.
- **Batch loading**:
  - The focus, all partners, both ancestor blocks and the children are loaded in one `with('events')` query.
  - Circular FK: `nullOnDelete` is in place, and deleting the proband and deleting the account are both tested on PostgreSQL.
- **Frontend**:
  - Podgląd is a sibling of the card link, not nested inside it.
  - All URLs come from Wayfinder, and all strings are in Polish.
  - Gender tokens are in `:root` and `.dark`, mapped in `@theme`, with a source comment.
  - No new npm dependency and no second design system.
- **Seeder**:
  - It is idempotent: it deletes only the two demo emails, inside a transaction.
  - It is not wired into `DatabaseSeeder`.
