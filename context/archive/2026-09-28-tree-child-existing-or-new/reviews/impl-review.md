<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Existing or New Child of the Shown Pair

- **Plan**: context/changes/tree-child-existing-or-new/plan.md
- **Scope**: Full plan (Phase 1 and 2 of 2)
- **Reviewed phases**: 1, 2
- **Date**: 2026-09-28
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated criteria re-run in review: 150 tests pass, Larastan 0 errors, `npm run check` clean, `tsc` clean, build OK, Pint clean. Manual rows 1.7-1.12 and 2.7-2.12 were confirmed by the user. The stale-reference grep for `parentCandidates` / `ListParentCandidates` / `ParentCandidate` could not run (Bash classifier outage); it is covered indirectly by tsc, Larastan and the passing tests, but is worth one manual `grep -rn` (Success Criteria warning). The `TreeTest.php` additions were not read line by line by the drift reviewer.

## Findings

### F1 — Rollback test does not exercise a rollback

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/ParentChildRelationsTest.php:592-606
- **Detail**: "a child with an outside parent is rejected for a pair and the first link is rolled back" gives the child two outside parents, so the first `CreateParentChildRelation` throws before any write. The assertions pass without any transaction. The plan asks for a person with one outside parent, where the focus link succeeds and the partner link fails. The store-side same-sex test does prove a real rollback.
- **Fix**: Give the child exactly one outside parent (drop the second `linkParent` at line 598), keeping the "dwoje rodziców" assertion.
- **Decision**: FIXED (Fix now) — child now has one outside parent, relation count asserted as 1; break-check (outer transaction removed) turned the test red, then restored.

### F2 — Lock order across the attach loop is not globally sorted

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Relations/AttachChildToParents.php:26-38
- **Detail**: Each `CreateParentChildRelation` call locks its own pair of people in id order and holds the locks until the outer transaction commits. Two concurrent attaches of the same child with parents [A,B] and [B,A] can lock in crossing order and deadlock (Postgres aborts one). The pre-filter runs before any lock, and the inner action re-validates every rule, so data integrity holds; the worst outcome is a 500 for one request in a rare race.
- **Fix**: Lock the child and all parents up front in ascending id order inside the transaction, then build the graph; the inner locks become re-entrant.
  - Strength: Removes the deadlock class; small change in one action.
  - Tradeoff: Slightly more code; single-user family trees make the race very unlikely.
  - Confidence: MED — reviewer read the code but did not reproduce the deadlock.
  - Blind spot: How `CreateParentChildRelation` acquires its locks was not re-verified.
- **Decision**: FIXED (Fix now) — `AttachChildToParents` locks child + parents in ascending id order up front; inner row locks are re-entrant in the same transaction. 150 tests, Larastan and Pint pass.

### F3 — Same-sex pair and same-sex single parent not reflected in `blocked_ids`

- **Severity**: 👁 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Trees/BuildFamilySheet.php (addChild)
- **Detail**: `blocked_ids` follows the plan (parent count, and the single-parent same-sex case only). If the pair itself is a same-sex couple, or the partner clashes with a candidate's existing parent, the dialog still offers the person and the server rejects with "Ta osoba ma już ojca/matkę", shown as the dialog error. The plan reasoned that pair-mode blocking is purely the parent count, so this is a documented UX gap, not drift.
- **Fix**: Leave as is, or add a note to the plan; not worth widening scope.
- **Decision**: FIXED (Fix now) — `BuildFamilySheet::addChild` now also blocks a candidate when a newly added parent shares a known sex with a parent the child keeps or with the other added parent (`addsSecondParentOfSameSex`); genders come from one query in every mode. Two new TreeTest tests; break-check went red. Remaining gap: a same-sex pair also blocks parentless people, but `blocked_ids` only covers people who have parents, so those still get the server error.

### F4 — Missing tests for request rules that exist

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/ParentChildRelationsTest.php
- **Detail**: No tests for `other_parent_id` equal to the route person, `child_id` equal to `other_parent_id`, `other_parent_id` equal to the person on the store route, or a same-sex pair on the attach route.
- **Fix**: Add one small validation test per rule if desired.
- **Decision**: FIXED (Fix now) — four tests added (route person as other parent on store and attach, child = other parent, same-sex pair on attach). 156 tests pass.

### F5 — Docblock wraps mid-sentence

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/tree/add-child-dialog.tsx:19-25
- **Detail**: The docblock breaks after "People" as if hand-edited. Cosmetic.
- **Fix**: Re-wrap the comment.
- **Decision**: FIXED (Fix now) — comment re-wrapped.
