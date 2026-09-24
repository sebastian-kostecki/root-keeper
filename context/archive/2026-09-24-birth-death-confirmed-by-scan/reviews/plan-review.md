<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Birth and Death Confirmed by Scan

- **Plan**: context/changes/birth-death-confirmed-by-scan/plan.md
- **Mode**: Deep
- **Date**: 2026-09-24
- **Verdict**: REVISE → SOUND (all findings fixed)
- **Findings**: 0 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
16/16 paths ✓, 7/7 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — "Person delete cascades pivot rows" is false

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §7 Feature tests; Testing Strategy
- **Detail**: Deleting a person cascades only `event_participants`; the tree-scoped `events` row, and with it the `event_scan` rows, survives.
- **Fix**: Test scan/event delete cascade instead. Add a What We're NOT Doing note that orphaned events after person deletion are S-05's concern.
- **Decision**: FIXED

### F2 — "Potwierdza" above the viewer pushes it below the fold

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 2 §7 Scan preview "Potwierdza" section
- **Detail**: `ScanViewer` has a fixed `h-[calc(100vh-12rem)]` (scan-viewer.tsx:46). Content inserted above it pushes the viewer past the fold.
- **Fix A ⭐ Recommended**: Render "Potwierdza" below the viewer.
- **Fix B**: A compact row above the viewer, with a smaller viewer calc.
- **Decision**: FIXED (Fix A)

### F3 — Validation runs before the event policy (422 vs 404 oracle)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §4 Link / unlink endpoints
- **Detail**: FormRequest validation ran before the controller's `Gate::authorize`, so another tree's event with a bad `scan_id` returned 422 instead of 404.
- **Fix**: Authorize in `EventScanStoreRequest::authorize()` via `Gate::inspect`, and add a test for a foreign event with an invalid scan_id.
- **Decision**: FIXED

### F4 — Picker has no failure path

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 §4 Scan picker dialog
- **Detail**: The dialog closes on success only, and no error display was specified.
- **Fix**: Keep the dialog open on error and show `errors.scan_id` with `InputError`.
- **Decision**: FIXED
