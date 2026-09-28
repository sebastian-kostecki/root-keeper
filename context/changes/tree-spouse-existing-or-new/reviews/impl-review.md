<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Existing or New Spouse from the Tree

- **Plan**: context/changes/tree-spouse-existing-or-new/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-09-28
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated criteria (tests 127 passed, Larastan, check, types:check, build, Pint) were green at the Phase 3 gate; all manual rows confirmed by the user.

## Findings

### F1 — Ancestor/descendant/same-sex exclusions are client-only

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/PersonSpouseController.php, app/Http/Requests/PersonSpouseAttachRequest.php
- **Detail**: The server rejects only self, another tree and a duplicate pair. A crafted POST to `persons.spouses.attach` (or `marriages.store`) can marry a person to their parent/child. This is by design (plan: "server keeps allowing any pair", PRD v4 rules unchanged), but no test documents it and any comment implying the server is the authority for these exclusions would be misleading.
- **Fix**: Keep the plan decision; ensure the dialog docblock only claims the client hides them. Revisit server rules only if the PRD changes.
- **Decision**: PENDING

### F2 — Lone union chip is a no-op

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/tree/union-switch.tsx:15
- **Detail**: Planned behaviour (switcher shows for a single union to host "Dodaj małżonka"), but clicking the already-selected single chip does nothing.
- **Fix**: Accept as is.
- **Decision**: PENDING

### F3 — Rollback branch of CreateSpouseForPerson untested

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Relations/CreateSpouseForPerson.php:31
- **Detail**: With a freshly created person the marriage cannot fail the duplicate check, so the rollback is effectively unreachable; the transaction is required by the plan and harmless.
- **Fix**: Accept as is.
- **Decision**: PENDING

### F4 — First raw DB::table join in BuildFamilySheet

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Trees/BuildFamilySheet.php:181-225
- **Detail**: Raw query chosen deliberately: the Eloquent eager-load version broke the constant query-count test (10 vs 9). Tree-scoped and typed.
- **Fix**: Accept as is; Eloquent version only if it can keep the query count constant.
- **Decision**: PENDING
