<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Existing or New Spouse from the Tree

- **Plan**: context/changes/tree-spouse-existing-or-new/plan.md
- **Mode**: Deep
- **Date**: 2026-09-28
- **Verdict**: SOUND
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | PASS |

## Grounding

13/13 paths ✓, 6/6 symbols ✓, brief↔plan ✓. The deep check confirmed the riskiest claims:
- `PersonStoreRequest::validated()` fits `CreatePerson`.
- `lockedGender` still posts `gender` through a hidden input.
- No `TreeTest` asserts the whole sheet.
- The two `AddSpouseDialog` variants never mount together.
- Undated marriages render in the preview and as „ślub ?" on the tree.

## Findings

### F1 — Proband redirect branch makes the URL jump

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Critical Implementation Details; Phase 1 §2, §7; manual 1.9
- **Detail**: The plan redirected the proband to `tree.index?spouse=S` and everyone else to `tree.show`. But `/tree/{probandId}` is an ordinary URL that every sheet card (`sheet-person-card.tsx:66-69`) and the breadcrumb (`show.tsx:41`) link to. A user there would jump to `/tree`, and manual 1.9 held only for one entry path.
- **Fix**: Always redirect to `tree.show(person, {spouse})`; drop the proband branch and its test; reword 1.9.
- **Decision**: FIXED

### F2 — „Połącz" under the couple sits inside an aria-hidden gap

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §4
- **Detail**: The gap between „Związek" and „Dzieci" is `aria-hidden="true"` (`family-sheet.tsx:234`). A button placed there would take keyboard focus but be invisible to screen readers. The plan handled this only for `PairConnector`.
- **Fix**: The gap keeps `aria-hidden` only on the vertical line.
- **Decision**: FIXED

### F3 — PersonSpouseStoreRequest is an empty subclass

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Lean Execution
- **Location**: Phase 2 §2
- **Detail**: The request extended `PersonStoreRequest` without adding a field. `PersonController@store` already passes `PersonStoreRequest::validated()` to `CreatePerson` (`PersonController.php:74`).
- **Fix**: `PersonSpouseController@store` type-hints `PersonStoreRequest`; no new request class.
- **Decision**: FIXED
