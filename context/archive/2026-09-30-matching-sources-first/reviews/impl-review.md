<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Matching Sources First

- **Plan**: context/changes/matching-sources-first/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-09-30
- **Verdict**: APPROVED
- **Findings**: 0 critical 1 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated checks re-run: `test:js` 93 passed; `types:check` clean; `check` (format + lint) clean; Pest 167 passed. Diff matches plan files exactly (no server, schema or Pest change).

## Findings

### F1 — Follow-up issue for the shared group label not linked

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/changes/matching-sources-first/screenshots/verdict.md (Charges table, "Missing shared component" row)
- **Detail**: Plan Phase 3 requires the open charge to link a follow-up GitHub issue naming its three copies. verdict.md still reads "link pending — to be created after the user's go-ahead".
- **Fix**: Create the GitHub issue (overview-tab.tsx:69, family-tab.tsx:149, picker `groupLabelClass`) and replace the placeholder with its URL.
- **Decision**: FIXED via Fix now (issue #28 created, linked in verdict.md)

### F2 — Tile misalignment when a linked tile shares a row

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/components/sources/source-picker-dialog.tsx:96-99
- **Detail**: Already recorded in verdict.md: the tile button centres its content vertically, so a shorter tile sits ~10 px lower beside a linked tile with an extra "Już powiązane" line. Cosmetic, now more visible in the matching section.
- **Fix**: Align tile content to the top (e.g. `content-start`) on the tile button.
- **Decision**: FIXED via Fix now (`self-start` on the tile button)

### F3 — Screenshot inspection was partial

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/matching-sources-first/screenshots/verdict.md
- **Detail**: verdict.md admits only light 1280 (all four states) and dark 390 (birth) were opened; the other variants were not. The "Seen" column covers a subset of the 16 shots.
- **Fix**: Open the remaining after/ variants before merge, or accept as-is.
- **Decision**: FIXED via Fix now (dark 1280, light/dark 390 death/marriage shots opened; verdict.md updated)
