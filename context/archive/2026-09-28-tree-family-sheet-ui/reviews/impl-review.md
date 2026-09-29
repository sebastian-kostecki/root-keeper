<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Family Sheet View (`tree/show`) — Visual Pass

- **Plan**: context/changes/tree-family-sheet-ui/plan.md
- **Scope**: Full plan (5 of 5)
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-09-28
- **Verdict**: APPROVED
- **Findings**: 0 critical 0 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Evidence: `npm run check`, `types:check` pass; `tests/Feature/TreeTest.php` 37/37 pass; every changed file outside the change folder is named in the plan; nothing under `components/ui/*` edited; `comm` of before/after shots is empty.

## Findings

### F1 — Centre-in-scroll effect does not attach when the container mounts late

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: resources/js/components/tree/union-switch.tsx:17-20, resources/js/hooks/use-centre-in-scroll.ts:29
- **Detail**: `UnionSwitch` returns `null` while `unions.length === 0`, so the row ref is null when the effect first runs. If unions appear without `selected` changing, the effect does not re-run and no listeners attach. In practice `selected` changes in that case.
- **Fix**: Leave as is, or include `unions.length` in the hook key at the call site.
- **Decision**: PENDING

### F2 — Hover shot selector is fragile

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/changes/tree-family-sheet-ui/screenshots/shots.mjs
- **Detail**: `a[href*="/tree/1"]` picks the first matching card by substring. It was kept so the after-shot matches the before-shot. Out-of-repo tooling, no runtime impact.
- **Fix**: Leave; revisit only if the seeder order changes.
- **Decision**: PENDING
