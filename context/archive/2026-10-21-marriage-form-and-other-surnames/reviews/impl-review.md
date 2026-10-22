<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Forma ślubu

- **Plan**: context/changes/marriage-form-and-other-surnames/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-10-22
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 3 observations

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

- Pest (Marriages, WriteTargets, Persons, Tree): 256 passed, 2083 assertions; `tests/Backup`: 13 passed
- Pint: passed; Larastan (`composer types:check`): 0 errors
- `npm run test:js`: 403 passed; `types:check`: clean; `check`: 262 files formatted, no lint warnings; `build`: exit 0
- Not re-run: 1.1 migration round trip — `migrate:rollback --step=1` would now roll back the later `move_maiden_names_to_last_name` migration (single-birth-surname); `down()` drops the column (read). 1.4 seeder — rewrites the dev DB; taken from the Progress record.
- Plan vs code: every planned change MATCH, also at HEAD after single-birth-surname touched the same frontend files. `MarriageResource.php` (not in plan) only extends its PHPDoc shape with `marriage_form?` / `marriage_form_label?` for Larastan — justified, no behaviour change. Nothing from "What We're NOT Doing" was built.
- Full-replace safety: every client of `PUT marriages/{id}` goes through `marriageSaveRequest`, which always sends `form`; create paths (`MarryCoupleDialog`, attach, spouse store) leave it `null`; birth/death mapping (`eventAttributesFrom`) unchanged.

## Findings

### F1 — Step 2.11 checked, screenshots missing

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/marriage-form-and-other-surnames/ (no `screenshots/` directory)
- **Detail**: Progress 2.11 requires screenshots of the "Ślub" card (view and edit) and the Family row and is marked `[x] — ea3ff23`, but no `screenshots/` directory exists in the tree or in git history. The view grid in `marriage-panel.tsx:181-191` now holds three `Field`s in `sm:grid-cols-2`, so "Forma ślubu" sits alone on the second row — the screenshot is where that gets confirmed.
- **Fix**: Capture the three screenshots on demo data into `screenshots/` (or uncheck 2.11 if the gate is waived).
- **Decision**: FIXED — 12 screenshots (Ślub card view known/unknown, edit, Family row × desktop light/dark, phone light) + shots.mjs in screenshots/; no horizontal overflow, the lone "Forma ślubu" row reads fine

### F2 — Stale docblocks and `$date` parameter name in marriage actions

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Relations/UpdateMarriage.php:13, app/Actions/Relations/CreateMarriage.php, app/Http/Controllers/MarriageController.php (`update` docblock)
- **Detail**: Docblocks still say the action updates "date and place", and the parameter is still `$date` although it now carries `form`. The full-replace contract ("a missing `form` key resets it to unknown") — which the client must honour — is documented nowhere in PHP.
- **Fix**: Rename `$date` to `$marriage` in both actions and mention the form plus the reset-when-omitted rule in the docblocks.
- **Decision**: FIXED — `$date` → `$input` in both actions (`$marriage` is taken by the Event in UpdateMarriage); docblocks of both actions and `MarriageController::update` describe the form and the reset-when-omitted rule

### F3 — Store/attach test does not assert each request succeeded

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/MarriagesTest.php:303 ("store saves the wedding form while attach and spouse store leave it unknown")
- **Detail**: The `attach` and `spouses.store` calls have no `assertSessionHasNoErrors()`; the test selects the other marriages with `whereKeyNot(Event::where('marriage_form', 'religious')->value('id'))`. It still fails if a path breaks, but the failure message won't say which one.
- **Fix**: Assert each response succeeded and select each marriage by its spouses.
- **Decision**: FIXED — attach and spouse store assert `assertSessionHasNoErrors()`; each marriage selected by its other spouse

### F4 — Type-erasing fixture cast in payload test

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/lib/person-payload.test.ts:173-184
- **Detail**: The `marriageFieldDefaults` fixture is built with `as unknown as Parameters<...>[0]`, so a new required field on `MarriageEvent` would not break the test's type check.
- **Fix**: Have `marriageFieldDefaults` accept a `Pick<MarriageEvent, ...>` of the fields it reads and drop the cast.
- **Decision**: FIXED — `marriageFieldDefaults` takes `Pick<MarriageEvent, 'date_parts' | 'locality_id' | 'place' | 'marriage_form'>`; test fixture without the cast
