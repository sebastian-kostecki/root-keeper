<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Existing or New Child of the Shown Pair

- **Plan**: context/changes/tree-child-existing-or-new/plan.md
- **Mode**: Deep
- **Date**: 2026-09-28
- **Verdict**: SOUND
- **Findings**: 0 critical, 2 warnings, 0 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | PASS |
| Plan Completeness | WARNING |

## Grounding

- **Paths:** 14/14 existing paths present; 4/4 new files not yet present.
- **Symbols:** 7/7 checked (rename blast radius fully covered by the plan; no whole-sheet test assertions; nested-transaction rollback proven in `ParentChildRelationsTest.php:496-522`; `pluck('gender','id')` returns enums; `UnionSwitch` hidden for ≤1 union).
- **Brief↔plan:** consistent except F2.

## Findings

### F1 — The graph can't list people who have parents

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1, §1 "Add-child metadata on the sheet"
- **Detail**: `blocked_ids` requires going through everyone who has parents, but `ParentChildGraph` keeps `$parentsByChild` private (`app/Support/ParentChildGraph.php:19`) and exposes only per-person methods.
- **Fix**: Add a public `childIds(): list<int>` to `ParentChildGraph` and compute `blocked_ids` from that list.
- **Decision**: FIXED — §1 now names `childIds()` and lists `ParentChildGraph.php` among the files.

### F2 — Manual criterion 1.10 expects a switcher that won't be shown

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 Manual 1.10 (and Progress 1.10); Desired End State; Manual Testing Steps 3; brief Open Risks
- **Detail**: After attaching Anna's son to Jan, who had no union, Jan has exactly one union. `UnionSwitch` renders nothing when there is ≤1 union (`resources/js/components/tree/union-switch.tsx:13-15`), and `selectUnion` falls back to the first union (`BuildFamilySheet.php:196`). So the tree shows Anna's union directly, and "the switcher then shows the union" would fail as written.
- **Fix**: Criterion → "the tree now shows Jan's union with Anna, with the son among the children"; narrow the brief's risk to the „Nieznany partner" case.
- **Decision**: FIXED — plan (1.10 in the phase and in Progress, Desired End State, Manual Testing Steps 3) and brief risk updated.
