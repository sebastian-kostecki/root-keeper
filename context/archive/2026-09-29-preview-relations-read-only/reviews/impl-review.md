<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Preview without relation adding (S-29)

- **Plan**: context/changes/preview-relations-read-only/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-09-29
- **Verdict**: APPROVED
- **Findings**: 0 critical, 0 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Evidence

- Phase 1 diff matches the plan file by file (props, empty-state copy, edit-only marriage dialog, „Pokaż na drzewie”, dead code removed). Kept: RemoveRelationDialog, RemoveMarriageDialog, marriage edit, `useOptionalList`, `person-candidate-list.tsx`.
- Phase 2 diff: `relationCandidates` prop, `ListRelationCandidates` and the four candidate-only tests removed; `availableScans` test kept. `ParentChildGraph` methods are all still used (BuildFamilySheet, CreateParentChildRelation), so no dead code left behind.
- Phase 3: roadmap S-28 removed and noted as „Porzucone”, S-29 narrowed, PRD FR-007/FR-008 amended, stub folder deleted.
- Re-run on review: 147 Pest tests pass (ParentChildRelationsTest, MarriagesTest, PersonsTest); pint, phpstan, `npm run check`, `types:check`, `build` passed at phase gates; grep for removed symbols in resources/js, app, routes, tests is empty.
- Manual items 1.5–1.9, 2.5, 3.6 were confirmed by the user.

## Findings

### F1 — Hook file name no longer matches its content

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/hooks/use-relation-candidates.ts
- **Detail**: The file now exports only the generic `useOptionalList`. The plan explicitly put the rename out of scope.
- **Fix**: Rename to `use-optional-list.ts` and update the tree imports in a follow-up.
- **Decision**: PENDING
