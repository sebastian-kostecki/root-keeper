<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Jedno nazwisko rodowe

- **Plan**: context/changes/single-birth-surname/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-10-22
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 1 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Verification run (2026-10-22)

- Pest (Persons, Tree, Ancestors, WriteTargets, OwnershipBoundaries, SingleSurnameMigration): 320 passed, 1931 assertions
- Pint: passed; Larastan (`composer types:check`): 0 errors
- `npm run test:js`: 403 passed; `types:check`: clean; `check`: 262 files formatted, no lint warnings
- `grep maiden_name`: only the model `@property`, the migration test, `person-payload.test.ts` negative assertions, `PersonsTest` (ignored-key / legacy-column tests) and `use-initials` (plan: unchanged)
- Not re-run: E2E specs (2.5) and `npm run build` (2.4) — need a browser / build; taken from the Progress record.

## Findings

### F1 — Step 2.10 checked, screenshots missing

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/single-birth-surname/ (no `screenshots/` directory)
- **Detail**: Progress 2.10 requires screenshots of the form and "Dane osobowe" view in `context/changes/single-birth-surname/screenshots/` and is marked `[x] — 8a1a358`, but no such directory exists in the tree or in git history.
- **Fix**: Capture the two screenshots into `screenshots/` (or uncheck 2.10 if the gate is waived).
- **Decision**: FIXED — screenshots/add-person-form.png and screenshots/personal-data-view.png captured via Playwright

### F2 — Leftover "maiden" naming in tree-card-name spec

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/tree-card-name.spec.ts:96, 138
- **Detail**: Test title still says "a woman card shows her maiden surname" and the variable is `maidenName`, while the model now has a single birth surname. Cosmetic.
- **Fix**: Rename title/variable to "birth surname".
- **Decision**: FIXED — renamed title and variable to birth surname
