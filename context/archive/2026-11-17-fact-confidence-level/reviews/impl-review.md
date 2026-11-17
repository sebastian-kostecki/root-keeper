<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Poziom pewności A–D przy fakcie

- **Plan**: context/changes/fact-confidence-level/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-17
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Notes:
- Range reviewed: `a1e7556..HEAD` (9f8bff1, 0f38961, c2bcf13, 06ab7b1, aa7bb21).
- Unplanned but justified changes:
  - `CreateMarriage` phpdoc and import, because ApplyImport passes `confidence`.
  - `persons-index.test.ts` fixtures, needed by the new required types.
  - `Arr::only` in `ApplyImport::placeAttributes()`, so `burialPlaceFrom()`'s `confidence => null` cannot null the level. It is tested.
- No item on the "What We're NOT Doing" list was violated.

Automated checks re-run on 2026-11-17 (one-off Sail container mounting this worktree):
- Pest, all Phase 1 + 3 suites: 694 passed (4609 assertions).
- Migration round-trip on the `testing` DB (up → rollback --step=1 → up): OK.
- Pint `--test`: passed.
- Larastan: no errors.
- `npm run check`: OK.
- `types:check`: OK.
- Vitest: 451 passed.
- `npm run build`: OK.
- Playwright `fact-confidence-level.spec.ts`: passed.
- Deliberate break of `eventToPayload` gives a red spec (line 51, "Poziom B" not found). After restoring the line the build is green again.

## Findings

### F1 — Poziom pochowania bez cmentarza ginie, a podsumowanie go liczy

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/PlanImport.php:1297, app/Support/Import/RepoValues.php:195, app/Actions/Import/PlanImport.php:382
- **Detail**:
  - A burial from the repo is not created when its cemetery is not recognized or the person has no death (`PlanImport.php:1297`). Its place and note go into the person's note.
  - Before this change its level went there too ("poziom C"). `eventDetails()` no longer emits it, so the level now lands neither in a column nor in the note.
  - `tallyNoteValues` still counts it into "Poziomy pewności przy faktach".
  - The test fixture shows it: `nowak_jan_1801.md` has `pochowek: miejsce: Zalesie, poziom: C`, but `ImportApplyTest.php:2518` asserts only 2 events with a level, and the command test expects 3 in the summary.
  - The repo and the snapshot (`poziom`) keep the value, so it comes back if the burial is created later.
- **Fix A ⭐ Recommended**: Keep "poziom X" in the note for an event the import does not create (a burial without a key), and count in "przy faktach" only the levels actually written.
  - Strength: no value from the repo silently disappears. This matches the rule that everything not mapped to a column goes to the note.
  - Tradeoff: `eventDetails()` needs to know whether the event lands in the app. That means a small change in the signature, plus a test for the no-cemetery burial.
  - Confidence: HIGH — the branch is a single spot (`PlanImport.php:1297`), and the snapshot already handles it.
  - Blind spot: whether anything other than a burial (e.g. a wedding without a spouse in the tree) has the same path.
- **Fix B**: Fix only the counter (do not count unwritten levels) and accept that the level stays only in the repo.
  - Strength: smaller change, and the summary becomes truthful.
  - Tradeoff: the author does not see in the app that the burial had a level.
  - Confidence: MED — it depends on whether the author wants the level visible in the note.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)
  - `PlanImport` collects `$outside`: the repo burials the tree does not hold after the import (the person has no burial, and the import neither creates nor restores one).
  - It passes them to `RepoValues::note()` and `PlannedNote`. Such a burial keeps "poziom X" on its note line.
  - `tallyNoteValues` no longer counts it toward "Poziomy pewności przy faktach".
  - Tests:
    - ImportApplyTest, first import: the note and the global count.
    - New test: "drops the level of a burial from the note once the import creates the burial".
    - ImportPlanTest: the note and counter assertions.

### F2 — Ręczne sprawdzenia i zrzuty ekranu nie zrobione

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/fact-confidence-level/plan.md:420-438
- **Detail**:
  - Progress rows 2.6–2.11 and 3.4–3.5 are `[ ]`.
  - `context/changes/fact-confidence-level/screenshots/` does not exist, although the plan and `resources/js/CLAUDE.md` require it as the visual gate (light and dark theme, a card with a level and one without).
  - The E2E spec partly covers 2.7 (the level survives saving "Dane osobowe").
- **Fix**: Run the manual checks 2.6–2.11 and 3.4–3.5, save the screenshots in `screenshots/`, and tick Progress before archiving.
- **Decision**: PENDING — the manual checks and screenshots are left to the author.

### F3 — Testy importu: brak drugiego konta i luki w guardach

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/ImportApplyTest.php:2674, tests/Feature/ImportApplyTest.php:2650
- **Detail**:
  - The test "writes the level of a new wedding and of a wedding of a matched pair" (:2674) has no second user with overlapping data, which CLAUDE.md requires for every import feature test. Despite its title it covers only the matched-pair path (`CreateMarriage`), not a new wedding of new people.
  - The guard test (:2650) has one dataset case (birth). The `death_confidence` / `burial_confidence` branches of `guardPerson` and the fill / conflict guards are untested; for the wedding only the change guard is.
- **Fix**: In :2674, add a second account with the same names, assert that its events are unchanged, and add the new-wedding path (or rename the test). Extend the :2650 dataset with a death level and a burial level.
- **Decision**: FIXED
  - The wedding test covers both a new pair and a matched pair (a dataset), with a second account checked through `reimportTreeState($other)`.
  - The guard test covers the birth, death and burial levels (a dataset built on `applyPlaceTwins`).

### F4 — Spec E2E nie sprawdza znacznika na karcie Przeglądu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/e2e/fact-confidence-level.spec.ts:46-57
- **Detail**:
  - The plan says that after reopening, the "Poziom B" badge and the birth level field are visible.
  - The spec checks the badge in the Źródła tab and the select value in edit mode. It does not check the badge on the Urodzenie card in Przegląd or the "Poziom pewności: B — …" field in the view.
  - The risk (losing the level on a full save) is covered: the deliberate break gives a red test.
- **Fix**: Add `expect` for the badge on the Urodzenie card and for the "Poziom pewności" field in Przegląd after reopening.
- **Decision**: FIXED — after reopening, the spec also checks "Poziom B" and the "B — indeks / źródło wtórne" field (`exact`) on the Urodzenie card. The spec is green.

### F5 — Nieuzupełnione phpdoc przy małżeństwie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Relations/UpdateMarriage.php:18, app/Http/Resources/MarriageResource.php:28
- **Detail**:
  - The `@param` of `UpdateMarriage::handle` has no `confidence?`, though its sibling `CreateMarriage` does.
  - The `@return` of `MarriageResource` does not list `confidence` / `confidence_label` from the `EventResource` spread.
  - Larastan does not catch it, because the controller passes `validated()`.
- **Fix**: Add `confidence?: string|null` to `UpdateMarriage`, and `confidence` / `confidence_label` to `MarriageResource`'s `@return`.
- **Decision**: FIXED
  - `UpdateMarriage` has `confidence?` in its `@param`.
  - Both marriage actions mention the level in their descriptions.
  - `MarriageResource`'s `@return` lists `confidence` and `confidence_label`.

### F6 — Komenda importu pokazuje same litery poziomu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Support/Import/PlannedFill.php:29, app/Actions/Import/PlanImport.php:1236
- **Detail**:
  - In the tables of fills, changes and conflicts, the level is a bare letter ("A → C").
  - The neighbouring enums (`Gender`, `SourceKind`) are shown through `label()`, and the UI shows "A — akt pierwotny".
  - When settling a conflict in the terminal, the author does not see what the letter means.
- **Fix**: Show the level as "A — akt pierwotny" (from `ConfidenceLevel::label()`) in `valueLabel()` and in the labels in `confidenceItem()`, and update the `expectsTable` assertions.
- **Decision**: FIXED (display only)
  - `ImportResearch::shown()` and the "poprzednio w repo" line show `*_confidence` fields as "A — akt pierwotny" (`levelShown()`). `PlannedFill::valueLabel()` does the same.
  - The labels in `confidenceItem()` stay letters, because the guards compare them.
  - The `expectsTable` and `valueLabel` assertions are updated.

### F7 — Brak testu serwera: poziom pochowania bez cmentarza przepada

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Persons/MapsEventInput.php:56-70
- **Detail**:
  - The plan ("Pochowanie bez cmentarza nie istnieje") says the burial level is saved only together with a cemetery.
  - The code does this (`burialPlaceFrom` returns null), but only Vitest covers the client side. No Pest test sends `burial.confidence` without `cemetery_id`, or clears the cemetery and checks that the level is gone.
- **Fix**: Add a case to PersonsTest: an update with `burial.confidence = 'C'` and no `cemetery_id` creates no burial, and clearing the cemetery removes the burial along with its level.
- **Decision**: FIXED — new PersonsTest test: "a burial confidence without a cemetery creates no burial and clearing the cemetery drops the level with the burial".

## Triage summary (2026-11-17)

- Fixed: F1 (Fix A), F3, F4, F5, F6, F7 (6)
- Pending: F2, the author's manual checks and screenshots (1)
- Checks after the fixes:
  - Pest, Phase 1 + 3: 699 passed (4636 assertions).
  - Pint: passed.
  - Larastan: no errors.
  - `npm run check`: OK.
  - Playwright `fact-confidence-level.spec.ts`: passed.
