<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Person Form in Modal

- **Plan**: context/changes/person-form-in-modal/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-09-28
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated criteria re-run on HEAD: PersonsTest 51 passed, `npm run check`, `types:check`, Pint all pass. All manual rows confirmed by the user.

## Findings

### F1 — Error scroll depends on a utility class

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/person-form-dialog.tsx:121
- **Detail**: Scroll-to-first-error queries `[class*="text-red-600"]`, coupled to the class list inside `InputError` (input-error.tsx:12). Restyling `InputError` to a semantic token silently breaks the scroll, with no type or lint signal.
- **Fix**: Add `data-slot="input-error"` to `InputError` and query that attribute instead.
- **Decision**: FIXED (Fix now — `data-slot="input-error"` on InputError, dialog queries it)

### F2 — Store redirect target is implicit

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/PersonController.php:80
- **Detail**: `store` uses `back()` with a `persons.index` fallback, as the plan specifies (keeps the `?q=` filter). If a create-mode `PersonFormDialog` is later mounted elsewhere (S-21 – S-23), it will return to that page. `update` still hardcodes `persons.show`. Intentional today; worth remembering when the tree opens the modal.
- **Fix**: None now; revisit in S-21.
- **Decision**: SKIPPED (intentional; revisit in S-21)

### F3 — Small unplanned additions

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/components/persons/person-form-dialog.tsx
- **Detail**: `data-test="update-person-button"` and the edit description "Zmień dane osoby" are not in the plan. Harmless.
- **Fix**: Leave as is.
- **Decision**: SKIPPED (leave as is)
