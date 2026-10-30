<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kolejny import z podglądem zmian i konfliktów

- **Plan**: context/changes/reimport-changes-and-conflicts/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-10-30
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated checks run during review: 541 Pest tests passed (all import, persons, relations and unit tests from the plan), Larastan 0 errors, Pint passed, migration `repo_snapshots` is applied. Manual items 5.6-5.8 are ticked in Progress with the phase 5 commit.

## Findings

### F1 — Removal impact is not re-checked at apply time

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyImport.php:450-461 (`guardRemoval`), :551-583 (`remove`)
- **Detail**: The author approves "Usuń z aplikacji" after seeing a table of effects (photo, burial, residences, marriages, relations, proband). At apply time `guardRemoval` only checks that the record exists with the same `repo_id`. If a residence, photo, event or marriage is added after the plan (prompts can take minutes), `DeletePerson` removes it without asking. Same for a death removal that deletes the burial.
- **Fix**: Keep the impact numbers in `PlannedRemoval` and compare them with freshly counted values in `verify()`. On any difference throw the usual "zmieniła się po planowaniu; nic nie zostało zapisane."
  - Strength: Same pattern as the existing value guards; closes the gap for the only destructive path.
  - Tradeoff: Needs impact counting to be callable from `ApplyImport` (reuse the `ClassifyRepoRecords` code).
  - Confidence: HIGH — the numbers are already in the plan.
  - Blind spot: Single-user tool, so the race is rare.
- **Decision**: FIXED via Fix now. `ApplyImport::guardRemoval` now recounts the impact (`ClassifyRepoRecords::impactOf`, made public) for `delete` decisions and refuses on any difference; test added in `ImportApplyTest.php`.

### F2 — An empty or unmounted repo reads as "everything removed"

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ReadResearchRepo.php (`handle`, `personFiles`), app/Actions/Import/ClassifyRepoRecords.php (`handle`)
- **Detail**: An empty `drzewo/` (for example an unmounted `/mnt/d` path or an empty sync folder) gives zero persons, so every known person lands in `removedInRepo`. The default answer is "odłącz". Pressing Enter through the prompts clears all `repo_id` values and the whole import memory. Next import then treats everyone as new. Nothing is deleted, but the three-value history is lost. Found by reading the flow; not run.
- **Fix**: In the command, when the reading has zero persons and the tree has records with `repo_id`, stop with an error before any question.
  - Strength: One check, no change to the planner.
  - Tradeoff: A real "repo emptied on purpose" case needs a different path (not planned).
  - Confidence: MED — threshold choice (zero vs. a share of persons) is a judgment call.
  - Blind spot: Not reproduced.
- **Decision**: FIXED via Fix now. `ImportResearch` stops before classification when the reading has no persons and the tree has persons with `repo_id`; test added in `ImportResearchCommandTest.php`.

### F3 — Read errors give a stack trace, not a message

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ReadResearchRepo.php (`File::get`, `scandir`), app/Console/Commands/ImportResearch.php:78-83
- **Detail**: The command catches only `RuntimeException`. `FileNotFoundException` and `ErrorException` from an unreadable file or directory are not caught. Nothing is written, so it is only a robustness problem. One unreadable person file aborts the whole read.
- **Fix**: Turn a per-file read failure into an `ImportIssue` without id (it then holds back removals), and catch `ErrorException` around the directory scan.
- **Decision**: FIXED via Fix now. `ReadResearchRepo` skips an unreadable file as an `ImportIssue` without id (`is_readable` check) and turns a `scandir` failure into a `RuntimeException` the command already reports; test added in `ResearchRepoReadTest.php`.

### F4 — Dead constant `BLOCKED_REASON`

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: app/Actions/Import/PlanImport.php:51-54
- **Detail**: Plan says the blocked reason goes away. The constant is unused and its docblock is stale ("Until a later import phase lifts it").
- **Fix**: Delete the constant.
- **Decision**: FIXED via Fix now. Removed `PlanImport::BLOCKED_REASON`.

### F5 — Matched person's note is appended, not replaced

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyImport.php:776 (`writeMatch`)
- **Detail**: The block is appended with `"\n\n"`. If the author already pasted a block with the same header into a hand-entered person, a second block appears and `NoteBlock::find` reads only the first.
- **Fix**: Use `NoteBlock::replace($person->note, $repoId, $block)` in `writeMatch`.
- **Decision**: FIXED via Fix now. `writeMatch` uses `NoteBlock::replace`; test added in `ImportApplyTest.php`.

### F6 — Guards and locks are slightly weaker than the plan text

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/ApplyImport.php:391-443 (`verify`), :744-751 (`writeMatch`)
- **Detail**: The plan says all guards run under the lock before any write. Guards for matched persons run after removals (the transaction still rolls back, so the result is the same). Wedding events are read without `lockForUpdate`. Memory guard is stricter than planned (fingerprint, extra `seenSnapshots` field), which is an improvement.
- **Fix**: Move the matched-person guard into `verify()` and add `lockForUpdate()` to wedding reads. Or accept and note it in the plan.
- **Decision**: FIXED via Fix now. Matched-person guards moved from `writeMatch` into `verify()` (before the first write), and the wedding read in `verify()` now uses `lockForUpdate()`. Existing test at `ImportApplyTest.php:659` covers the matched-person refusal.

### F7 — Summary counts are by key, not by record

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Import/ApplyImport.php:269-270
- **Detail**: `keptDeleted` counts member keys (a person with a birth counts 2); `restored` counts planned restorations but not the members of a restored person. Only the printed summary is affected.
- **Fix**: Count records per decision, not snapshot keys.
- **Decision**: FIXED via Fix now. `keptDeleted` counts records (`ApplyImport::countKeptRecords`: a key that travels with a kept person is not counted again). `restored` already counted per record, so unchanged. Test added in `ImportApplyTest.php`.

### F8 — Repeated queries in apply

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyImport.php:189 (`CreateParentChildRelation::handle` per relation), `->load('events')` in `writeUpdate`, `verify`, `writeMatch`
- **Detail**: Each relation rebuilds the tree graph; events are loaded repeatedly. Fine for family-size trees; plan says not to optimize.
- **Fix**: Use `loadMissing` in the three places. Leave the graph as is.
- **Decision**: DISMISSED. The reload in `writeMatch` / `writeUpdate` is needed: removals and earlier writes change a person's events first, so `loadMissing` would use a stale list. In `verify` each person is loaded once, so there is nothing to save. Per-relation graph rebuild left as is (plan: no optimization).

### F9 — No other-account row diff after removal tests

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/ImportApplyTest.php:1033, :1060
- **Detail**: Plan asks every apply case to check `rowDiff` / `ownershipSnapshot` for the second user. Plain write (:175) and match (:510) do it; delete, detach and restore tests check the person and photo only, not the other account's events, relations and snapshots. The code is tree-scoped, so this is a proof gap, not a bug.
- **Fix**: Add the ownership snapshot check to the delete, detach and restore tests.
- **Decision**: FIXED via Fix now (smaller than the finding said). Delete, detach, death removal and restore tests already compared the other account. Added the check to the photo test and the new kept-record test.
