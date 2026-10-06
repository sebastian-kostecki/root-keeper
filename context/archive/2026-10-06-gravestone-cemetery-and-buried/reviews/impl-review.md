<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Nagrobek z cmentarzem i osobami pochowanymi (S-09)

- **Plan**: context/changes/gravestone-cemetery-and-buried/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-10-06
- **Verdict**: APPROVED
- **Findings**: 0 critical 2 warnings 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated: Pest (Gravestone, Sources, WriteTargets, Persons, Backup) 291 passed; Larastan 0 errors; Pint clean; Vitest 216 passed; `npm run check` clean; `types:check` clean; build OK. E2E not re-run (checked off at 88346e2).

## Findings

### F1 — Race: duplicate Burial events from concurrent saves

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Sources/FillBurialPlacesFromGravestone.php:26-37
- **Detail**: `death()`/`burial()` are read from events loaded before the insert, with no lock. Two concurrent saves of the same gravestone (or two gravestones sharing a person) can both see `burial() === null` and create two Burial events for one person. Schema does not prevent it. Also ~3 queries per person.
- **Fix**: Inside the transaction lock the person rows (`lockForUpdate`) and re-check `burial()` after the lock.
  - Strength: Small change, closes the race inside the existing transaction.
  - Tradeoff: Slightly more queries; a partial unique constraint would be stronger but needs a schema change.
  - Confidence: MED — single-user app, so the race is unlikely in practice.
  - Blind spot: Whether the data model allows only one burial per person everywhere.
- **Decision**: FIXED via Fix (lockForUpdate on buriedPeople() in FillBurialPlacesFromGravestone)

### F2 — No test for account deletion with cemetery and buried people

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/SourcesTest.php:412-434
- **Detail**: The `cemetery_locality_id` FK is NO ACTION and the migration relies on end-of-statement checking during tree cascade. Not exercised by any test (the plan's backup seed does not delete accounts).
- **Fix**: Add a `profile.destroy` test with a gravestone that has a cemetery and buried people.
- **Decision**: FIXED (test 'deleting the account removes a gravestone with its cemetery and buried people' in GravestoneTest)

### F3 — "Escape closes dialog, not panel" not demonstrated

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/persons/pick-person-dialog.tsx
- **Detail**: Plan required Escape to close only the dialog. No explicit handling or E2E check; may be covered by Radix stacking. Manual item 3.5-3.8 do not cover it.
- **Fix**: Verify manually once; add handling only if the panel closes too.
- **Decision**: SKIPPED

### F4 — Fill recreates a deliberately cleared burial on the next gravestone save

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Sources/FillBurialPlacesFromGravestone.php:26
- **Detail**: Matches the plan's decision ("każdy zapis nagrobka, cała lista"). Noted as accepted behavior; no test pins it.
- **Fix**: Accept; optionally add a test documenting it.
- **Decision**: SKIPPED (accepted per plan)

### F5 — Future place delete/merge must cover `cemetery_locality_id`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Models/Locality.php:43
- **Detail**: No locality delete/merge exists yet (S-11). `cemeterySources()` is in place; the S-11 plan must include it per CLAUDE.md merge rules.
- **Fix**: Carry into the S-11 plan.
- **Decision**: PENDING
