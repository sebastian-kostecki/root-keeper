<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Scalanie i usuwanie miejscowości i parafii (S-11)

- **Plan**: context/changes/places-merge-and-delete/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-08
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated checks re-run during review: Pest (LocalitiesTest, ParishesTest, OwnershipBoundariesTest, DeleteImpactTest) 139 passed; Larastan 0 errors; Pint clean; Vitest 240 passed; `npm run check` clean; `types:check` clean; `npm run build` OK. E2E (Playwright) not re-run. Manual items 3.5–3.9 and 4.4 are ticked in Progress; not independently verifiable from the diff.

## Findings

### F1 — Residence dedupe also deletes pre-existing duplicates at the target

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Localities/MergeLocality.php:43-49
- **Detail**: The `DELETE ... USING residences keep` covers every residence of the target locality, not only rows just moved from the duplicate. Residences have no uniqueness constraint, so two identical rows (same person, years) that already existed at the target are silently collapsed by a merge that did not create them. The plan's Contract wording ("wśród wierszy residences z locality_id = właściwy") and "What We're NOT Doing" ("redukowane są tylko identyczne") allow this literally, but the intent is to remove duplicates created by the merge. No test pins either behaviour.
- **Fix**: Capture ids of the duplicate's residences before the UPDATE and restrict the DELETE to `r.id IN (moved ids)` (keeping `keep` over all target rows); add a test with a pre-existing identical pair at the target.
  - Strength: Merge touches only data it moved; matches the DeleteImpactTest principle of exact row sets.
  - Tradeoff: Slightly more code (extra pluck/ids array); departs from the literal plan SQL.
  - Confidence: HIGH — behaviour verified by reading the SQL.
  - Blind spot: Whether the author considers pre-existing identical residences junk worth cleaning.
- **Decision**: FIXED — DELETE ograniczony do zamieszkań przeniesionych z duplikatu (id zebrane przed UPDATE; przy identycznych przeżywa najniższe id, wcześniejsze duplikaty w docelowej nietknięte); 2 nowe testy w LocalitiesTest.

### F2 — Merge dialog text misdescribes the description merge

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/localities/merge-locality-dialog.tsx:76
- **Detail**: Dialog says "Puste pola wybranej uzupełnią się danymi …", but when both descriptions are non-empty and differ, the target's description is extended with the duplicate's text (MergeLocality::mergedDescription). The user is told the target is only filled, while a non-empty field is changed. (The wording comes from the plan itself.)
- **Fix**: Change wording to e.g. "Puste pola wybranej uzupełnią się danymi „X”, a różne opisy zostaną połączone."
- **Decision**: FIXED — tekst modalu: „…a różne opisy zostaną połączone.”

### F3 — Merged description can exceed the 5000-char validation limit

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Localities/MergeLocality.php:51-57, app/Concerns/LocalityValidationRules.php:36
- **Detail**: Two descriptions up to 5000 chars each can concatenate beyond `max:5000`; next save of the panel (even a rename) then fails validation until the user trims. This is a documented, deliberate trade-off in the plan (Critical Implementation Details) — noted only; no test pins it.
- **Fix**: Accept as-is (plan-acknowledged); optionally add a test documenting the behaviour.
- **Decision**: SKIPPED — świadoma decyzja z planu.

### F4 — Uncaught unique violation in MergeParish on concurrent insert

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Parishes/MergeParish.php:55-57
- **Detail**: Only the two merged rows are locked. A concurrent insert/rename of a third parish to (name, religion) between `nameAndReligionTaken()` and the update would raise `UniqueConstraintViolationException` → 500. `StoreParish`/`UpdateParish` catch this exception. Very narrow race for a single-author app.
- **Fix**: Catch `UniqueConstraintViolationException` around the religion update and leave religion empty (or map to a validation error), as the sibling actions do.
- **Decision**: FIXED — aktualizacja pól docelowej w zagnieżdżonej transakcji (savepoint, bo w PostgreSQL złapany błąd inaczej unieważniłby całą transakcję scalenia); przy `UniqueConstraintViolationException` wyznanie zostaje puste. Bez testu (wyścigu nie da się wywołać w Pest, jak w planie).

### F5 — Delete dialogs fail open when usage counts are absent

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/localities/delete-locality-dialog.tsx:~29, resources/js/components/parishes/delete-parish-dialog.tsx:~38
- **Detail**: `locality.usage ?? noLocalityUsage` / `sources_count ?? 0` show "Usunąć…?" as if the entry were unused when counts are missing. The server still refuses deleting a used entry, so no data risk; both list and panel always supply counts today.
- **Fix**: Skip (server guard suffices) or make the types non-optional where counts are always loaded.
- **Decision**: SKIPPED — serwer pilnuje reguły, liczniki zawsze są dostarczane.

### F6 — Disabled "Scal z…" / "Usuń" give no reason when form is dirty

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/localities/locality-panel.tsx:99,108, resources/js/components/parishes/parish-panel.tsx:164,173
- **Detail**: Buttons are `disabled={isDirty}` without a visible hint or `aria-describedby`; the user may not understand why they are inactive. Behaviour matches the plan.
- **Fix**: Add a short hint ("Zapisz zmiany, aby scalić lub usunąć") or `title`/`aria-describedby` when dirty.
- **Decision**: FIXED — przy `isDirty` pod przyciskami pojawia się „Zapisz zmiany, aby scalić lub usunąć.” (`aria-describedby`) w obu panelach.
