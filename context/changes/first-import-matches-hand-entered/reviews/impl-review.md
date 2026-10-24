<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Pierwszy import dopasowuje osoby wpisane ręcznie

- **Plan**: context/changes/first-import-matches-hand-entered/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-24
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 7 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Automated gates re-run at review time on a9a1b68:
- `vendor/bin/sail artisan test --compact`: 1270 passed
- `vendor/bin/sail composer types:check`: 0 errors
- Pint: clean

Tree boundary: every read and write goes through `$tree`. The transaction and locks, the production backup before the write, and the preservation of photo, residences, `event_source`, `locality_id`, `cemetery_id` and `marriage_form` were all verified as correct.

## Findings

### F1 — Uzupełnienia nadpisują wartości zmienione w aplikacji po planowaniu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyFirstImport.php:250-269 (pola osoby), `markEvent` (~:290, data urodzenia/zgonu), :150-158 (data istniejącego ślubu)
- **Detail**:
  - A `PlannedFill` is created because a field was empty during planning. Between planning and the write, the command asks all the conflict questions and the final confirmation, which can take minutes.
  - `ApplyFirstImport` locks the matched persons and re-checks only that they exist, their `repo_id`, and whether their birth/death events exist. It does not check that a filled field is still empty, or that the app value for a conflict resolved "from repo" is still the one shown to the author.
  - If the author enters a first name, surname, gender or date in the UI during that window, the import silently overwrites it. This breaks the docblock promise "nic z nich nie znika".
  - The plan (§Faza 3, step 1) did not require this re-check, so this is also a gap in the plan.
- **Fix A ⭐ Recommended**: In `writeMatch` / `markEvent` and for a marked wedding, compare the locked state with the plan: a fill needs the field still empty, and a "from repo" conflict needs the app value still equal to `appLabel`. On a mismatch, throw `RuntimeException` ("… zmieniła się po planowaniu; nic nie zostało zapisane.") and add an apply test.
  - Strength: The same pattern as the existing re-checks (deleted person, `repo_id` after planning). Atomicity and the "nothing from the app disappears" rule hold even under a race.
  - Tradeoff: More code in Apply, and the author repeats the run after an edit made in parallel.
  - Confidence: HIGH — the path was read end to end, and no test covers it.
  - Blind spot: None significant.
- **Fix B**: Accept the risk and document it in the plan (one user, a manual run, a small time window).
  - Strength: Zero code.
  - Tradeoff: A manual edit can be lost without any trace in the report.
  - Confidence: MED — depends on how the author works (whether the UI is open during the import).
  - Blind spot: Production behaviour after S-05.
- **Decision**: FIXED — Fix A (guardPerson/guardWedding pod blokadą, PlannedFill::appLabel, testy)

### F2 — Komunikat „w drzewie inny matka”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/PlanFirstImport.php:554
- **Detail**: `sprintf('w drzewie inny %s: %s', … 'ojciec' : 'matka', …)` produces the user-visible skip reason "w drzewie inny matka". Only the father variant is tested (`FirstImportPlanTest.php:444`).
- **Fix**: Choose the whole phrase by gender ("inny ojciec" / "inna matka") and add the mother case to the test.
- **Decision**: FIXED — „inny ojciec” / „inna matka” + test matki

### F3 — Rodzice bez imion liczą się jako zgodny element

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/MatchResearchPersons.php:202, :328 (`sameName`)
- **Detail**: `sameName` applies the candidate rule literally: "a missing first name matches only a missing first name". As a result, a repo parent and a tree parent both without a first name (and with the same surname, or both without one) count as an agreeing "Ojciec"/"Matka". Two such weak elements can push a candidate to `Sure`. This follows the plan's wording, but it is weak evidence for the identity rule from the PRD.
- **Fix**: Count a parent or spouse as agreeing only when the first name is known on both sides (an extra condition in `compareParent` / the spouse comparison). The candidate rule for the person itself stays the same. Add a test.
  - Strength: The identity rule rests on real evidence, and an empty parent pushes the case to "wątpliwe" (the author decides).
  - Tradeoff: Slightly more doubtful cases to resolve by hand.
  - Confidence: MED — not checked how often the repo has a parent without a first name.
  - Blind spot: Whether the block contract allows a parent block without `imiona`.
- **Decision**: FIXED — sameKnownName dla rodzica i małżonka + test

### F4 — Ręczny przebieg 4.6–4.7 nie przećwiczył dopasowania

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: N/A (plan.md Progress 4.6, 4.7)
- **Detail**:
  - Items 4.6–4.8 are marked done.
  - In the dev run on `test-pack`, all 7 people were imported as new (#111–#117), because the dev tree (#1–#110) has no one named Bednaruk or Prus. Not one of the following was visible in the terminal: the sure table, the doubtful comparisons, option label length, conflicts, and the preservation of photo, sources and place on a matched person.
  - These paths are covered only by automated tests.
- **Fix**: Before the first import on production (which S-05 still blocks anyway), repeat 4.6–4.7 on the dev tree with 2–3 namesakes from `test-pack` added by hand (one sure through parents, one doubtful, one with a date conflict).
  - Strength: It checks the real readability of tables and labels (`Str::limit(74)` is not tested).
  - Tradeoff: About 15 minutes of manual work.
  - Confidence: HIGH — confirmed in the dev database (7 people with `repo_id`, all created at 10:47:13).
  - Blind spot: None significant.
- **Decision**: QUEUED — follow-ups/review-fixes.md (ręczna kontrola dla autora)

### F5 — Luki w kształcie testów względem planu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/ResearchPersonMatchTest.php:353; tests/Feature/ImportResearchCommandTest.php:304-313
- **Detail**:
  - (a) The test "refuses a tree that already holds a person from an import" has no second user, although the plan requires one in every test in this file.
  - (b) The command helper has 2 people instead of 3: Jan is sure through birth year + father and also has the date conflict. "Sure through both parents by name" is proven only at the Action level.
  - (c) Several Plan/Apply/Command tests create the second user but assert nothing about them. The explicit checks are in `matchTemporaryRepo`, `FirstImportApplyTest:165/:497/:533` and `ImportResearchCommandTest:91/:358/:469`.
- **Fix**: Add the second user to `ResearchPersonMatchTest:353`, and add a person who is sure through both parents to the command helper.
- **Decision**: FIXED — (a) drugi użytkownik w teście blokady; (b) zamiast zmiany helpera nowy test komendy z repo tymczasowym (Jan pewny przez oboje rodziców)

### F6 — Wyjątek planera nie jest łapany przez komendę

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/ImportResearch.php:91
- **Detail**: If a candidate is deleted in the UI between matching and planning (the window covers all the identity questions), `PlanFirstImport` throws `InvalidArgumentException` ("Decyzje wskazują osobę spoza drzewa konta.") and the command ends with a stack trace. Nothing is written.
- **Fix**: Wrap the call the same way as `ReadResearchRepo` (:65-71): `error(message)` and `FAILURE`.
- **Decision**: FIXED — InvalidArgumentException → error + FAILURE + test

### F7 — Komunikat wycofania nie wskazuje osoby

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/ImportResearch.php:131
- **Detail**: A `ValidationException` from `guardParentSexChange` / `CreateParentChildRelation` during the write prints only `getMessage()` (e.g. "Ta osoba jest rodzicem dziecka, które ma już ojca."), without the repo id or the tree `#id`.
- **Fix**: In `ApplyFirstImport`, catch `ValidationException` around `writeMatch` and the relations loop, and rethrow it with the repo id and `#id` in the message.
- **Decision**: FIXED — RuntimeException z id repo i #id dla writeMatch i nowych relacji (CreateMarriage poza zakresem)

### F8 — Zapis nie sprawdza powtórzonej osoby drzewa w planie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyFirstImport.php:197-219
- **Detail**: The uniqueness of `PlannedMatch::personId` is enforced only by `PlanFirstImport::matchedPeople` and by `$taken` in the command. For a plan built by hand with a repeated id, the second `writeMatch` would silently overwrite the `repo_id` of the person and their events. This cannot happen through the command today.
- **Fix**: In `lockMatchedPeople`, throw `RuntimeException` when `count(array_unique($ids)) !== count($ids)`.
- **Decision**: FIXED — RuntimeException przy powtórzonej osobie drzewa + test

### F9 — Martwa metoda `MatchVerdict::label()`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Enums/MatchVerdict.php:11
- **Detail**: `label()` is never called. `ImportResearch::matchSummary` (:148-154) hardcodes "nowe / pewne / wątpliwe".
- **Fix**: Use `MatchVerdict::label()` in `matchSummary` (or remove the method).
- **Decision**: FIXED — matchSummary używa MatchVerdict::label() (New → „nowe”)
