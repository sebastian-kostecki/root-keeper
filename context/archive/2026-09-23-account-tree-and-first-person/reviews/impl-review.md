<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Konto z gotowym drzewem i pierwsza osoba

- **Plan**: context/changes/account-tree-and-first-person/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-09-23
- **Verdict**: REJECTED
- **Findings**: 1 critical, 3 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | FAIL |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Automated checks, all re-run during this review and all passing:
- `artisan test`: 56 tests, 208 assertions
- `composer types:check`: 0 errors
- Pint
- `npm run types:check`
- `npm run check`
- `npm run build`
- the grep for leftover password-reset references

## Findings

### F1 — Person with only a first name or only a surname cannot be saved from the form

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/PersonStoreRequest.php:24-25
- **Detail**:
  - `first_name` and `last_name` are `['required_without:…', 'string', 'max:255']` with no `nullable`.
  - The form always sends both inputs, and `ConvertEmptyStringsToNull` turns the empty one into `null`, which the `string` rule rejects.
  - Reproduced in tinker: `first_name=Maria, last_name=null` fails with "Pole nazwisko musi być ciągiem znaków."
  - This breaks the plan's "imię lub nazwisko" intent. The `NN` display path and manual step 3 ("Maria" with no surname) cannot be reached from the UI.
  - The tests miss it because the `store` tests leave the other key out instead of sending `''` like the form does.
- **Fix**: Add `nullable` before `string` on both fields. Add a PersonsTest case that posts `last_name => ''` (and one with `first_name => ''`) and expects a saved person whose display name contains `NN`.
- **Decision**: FIXED — added `nullable` to both name rules; new dataset test in PersonsTest (empty string for the other name)

### F2 — `UserFactory::withoutTree()` uses a leaking static flag

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: database/factories/UserFactory.php:28-54, database/factories/TreeFactory.php:22
- **Detail**:
  - `withoutTree()` sets a process-wide `static::$skipsTreeCreation`, which only the next `afterCreating` resets.
  - `TreeFactory::definition()` calls `User::factory()->withoutTree()` eagerly. So `Tree::factory()->for($user)->create()`, or any `make()`, sets the flag and never consumes it.
  - The next unrelated `User::factory()->create()`, possibly in another test, then silently gets no tree.
  - `withoutTree()->count(3)` skips the tree only for the first user.
  - Tests are green today because no test triggers this path yet. It will surface as flaky null errors in later slices.
- **Fix**: Remove the static flag.
  - `withoutTree()` returns `$this->afterCreating(fn (User $user) => $user->tree()->delete())`. It runs after the tree-creating callback, so it undoes it for every model.
  - In `TreeFactory`, make the user lazy: `'user_id' => fn () => User::factory()->withoutTree()`.
  - Strength: No shared state. Works with `count()`, `make()` and overrides.
  - Tradeoff: One extra insert and delete per `withoutTree` user, which is negligible.
  - Confidence: HIGH. `afterCreating` callbacks run in registration order.
  - Blind spot: Not yet run against TreeBackfillTest.
- **Decision**: FIXED — static flag removed; `withoutTree()` deletes the tree in its own afterCreating callback; TreeFactory user is lazy. Full suite 58/58 (incl. TreeBackfillTest).

### F3 — `is_deceased` handled inconsistently between the rules and `CreatePerson`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/PersonStoreRequest.php:28,36-40; app/Actions/Persons/CreatePerson.php:77-80
- **Detail**:
  - A JSON `true` passes `boolean`, but it fails `prohibited_unless:is_deceased,1`, which compares strictly for bools. Every `death.*` field is then refused.
  - An integer `1` passes validation, but `isChecked()` accepts only `true` or `'1'`, so no death event is created. `*.is_approximate` has the same gap.
  - The React form sends `"1"`, so the UI works today. Only non-form clients are affected.
- **Fix**: In `CreatePerson::isChecked()`, use `filter_var($value, FILTER_VALIDATE_BOOL)`. In the rules, use `prohibited_unless:is_deceased,1,true`.
- **Decision**: FIXED — `prohibited_unless:is_deceased,1,true` on all death.* rules; `isChecked()` uses `FILTER_VALIDATE_BOOL`; new PersonsTest case posting JSON `true`.

### F4 — Header nav still highlights with exact URL matching

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/app-header.tsx:70,170
- **Detail**: Plan §8 says the active item is chosen with `isCurrentOrParentUrl`. Only `nav-main.tsx` (the sidebar) was switched. In the header layout, "Osoby" is not highlighted on `/persons/create` or `/persons/{id}`.
- **Fix**: Replace `isCurrentUrl(item.href)` with `isCurrentOrParentUrl(item.href)` in app-header.tsx.
- **Decision**: FIXED — app-header uses `isCurrentOrParentUrl` for both the active style and the underline (replacing `whenCurrentUrl`/`isCurrentUrl`).

### F5 — Unused `PersonPolicy` scaffold methods that all return false

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: app/Policies/PersonPolicy.php:14-17,32-67
- **Detail**: The plan asked only for `view()`. The `make:policy` stubs `viewAny`, `create`, `update`, `delete`, `restore` and `forceDelete` all `return false`. They are dead code today, and they will silently deny once S-05 calls `Gate::authorize('update', …)`.
- **Fix**: Delete the unused stubs and keep only `view()`.
- **Decision**: FIXED — removed the six unused scaffold stubs; only `view()` remains.

### F6 — Religion select has no way to clear a choice

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/persons/create.tsx:84-111
- **Detail**: The plan asked for a select "z pustą opcją". There is only a placeholder "Brak danych". Once the user picks a religion, they cannot go back to none.
- **Fix**: Add a "Brak danych" item with a sentinel value (Radix does not allow `""`), and map it to `''` in the hidden input.
- **Decision**: FIXED — added a "Brak danych" item with a `none` sentinel mapped back to `''` in the hidden input. Needs a manual click-through in the browser.

### F7 — Impossible-date check runs after earlier rules already failed

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/PersonStoreRequest.php:84-96
- **Detail**: The `after()` hook casts the raw input with `(int)`. When the input is non-numeric or out of range (e.g. `month=13`), it adds a second, misleading "Podana data jest nieprawidłowa." error on `.day`.
- **Fix**: Return early from `validateFullDate()` when `$validator->errors()->hasAny([...year, ...month, ...day])`.
- **Decision**: FIXED — early return in `validateFullDate()` when a date part already has an error; new PersonsTest case (month=13 → only `birth.month` error), verified failing before the fix.

### F8 — Polish strings hardcoded instead of going through `__()`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/PersonController.php:53, app/Http/Requests/PersonStoreRequest.php:95
- **Detail**: The sibling `Settings/ProfileController.php:41` uses `__('Profile updated.')`, and this branch adds `lang/pl.json`. The strings `'Dodano osobę'` and `'Podana data jest nieprawidłowa.'` are literals. This is harmless because the UI is Polish-only.
- **Fix**: Wrap both in `__()`, or accept literals as the convention for domain strings.
- **Decision**: FIXED — both strings wrapped in `__()` using the Polish text as key (no lang/pl.json entries needed).

### F9 — User without a tree causes a 500 error

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/PersonController.php:23,51; app/Policies/PersonPolicy.php:24
- **Detail**: `$user->tree->…` is called on null when a user has no tree. The registration flow and the backfill migration both guarantee the invariant, so this is reachable only through tinker, seeders, or the F2 factory leak.
- **Fix**: Accept the risk. Fixing F2 closes the realistic path.
- **Decision**: ACCEPTED — the one-tree-per-user invariant is guaranteed by `CreateNewUser` (transaction) and the backfill migration; the F2 fix closed the realistic test-side path.

### F10 — Test gaps: first-name tie-break and the single-name path

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/PersonsTest.php:18-36
- **Detail**:
  - The sort test uses only distinct surnames, so the `first_name` tie-break the plan specifies is never exercised.
  - Manual items 2.9 and 2.10 are ticked. Manual step 3 ("Maria" with no surname) cannot pass through the real form (see F1), so that step was not actually run as written.
- **Fix**: Add two people who share a surname to the sort test. Re-run manual step 3 after F1 is fixed.
- **Decision**: FIXED (test) — sort test now has two "Adamski" people created in reverse first-name order. Manual step 3 still to be re-run by the owner in the browser.

## Triage summary

- Fixed: F1, F2, F3, F4, F5, F6, F7, F8, F10 (9)
- Accepted: F9 (1)
- After fixes: full suite 60/60 (219 assertions), Larastan, Pint, tsc, `vp check`, build all pass.
- Open manual follow-up: re-run plan manual step 3 ("Maria" with no surname, "Zmarły/a" checked) and click the new "Brak danych" religion option in the browser.
