<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Source Description in Modal

- **Plan**: context/changes/source-description-in-modal/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-09-30
- **Verdict**: APPROVED
- **Findings**: 0 critical 1 warnings 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated re-run: 173 Pest tests (Sources, SourceMigration, EventSources, Persons) pass; 85 JS tests pass; `types:check`, `npm run check` (format + lint) and PHPStan pass; `sources.update` exists, `sources.create` does not.

## Findings

### F1 — Screenshot gate leaves a known layout defect and unseen states

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/source-description-in-modal/screenshots/verdict.md; resources/js/pages/sources/index.tsx
- **Detail**: verdict.md records a defect (at 1280 px the fully described card with three meta lines sticks out ~18 px below its row neighbours) and leaves it unfixed. It also marks the display-label charge "closed for the shots covered": picker, unlink and delete dialogs, the „Potwierdza" card and the Link error were not seen. Plan 4.6 says every charge closed and no state "not inspected".
- **Fix**: Equalise card height in the grid (`h-full` on the card) and shoot the picker/unlink/delete dialogs, or record the gaps as accepted.
- **Decision**: FIXED via Fix now (h-full on the card, block link; the unseen dialog states remain unshot — accepted)

### F2 — Erase-rule closure also fires on an invalid kind

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/SourceUpdateRequest.php:38
- **Detail**: For a stored akt with typ/numer and a garbage `kind`, the user gets the enum error plus a misleading "Zmiana rodzaju usunie…" error.
- **Fix**: Add `bail` before the closure or guard it with `SourceKind::tryFrom($value) !== null`.
- **Decision**: FIXED via Fix now (tryFrom guard)

### F3 — Confirmation flag could outlive a closed modal

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/sources/source-edit-dialog.tsx:96-103
- **Detail**: `confirmAndSubmit` sets the hidden `discard_record_fields` to '1'; only `onFinish` resets it. If the modal is dismissed between confirm and finish the flag could stay '1'. Reading of Inertia semantics: `onBefore` cancel does not set the flag, so the normal paths are safe; the server still requires an actual kind change, and the erase is user-intended.
- **Fix**: Also reset the hidden input in `reset()`.
- **Decision**: FIXED via Fix now (reset() clears the flag)

### F4 — Create modal silently drops typed akt fields on kind switch

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/sources/source-create-dialog.tsx
- **Detail**: Matches the plan ("No confirmation when the kind changes inside Dodaj źródło"); listed only for awareness.
- **Fix**: None needed.
- **Decision**: SKIPPED (accepted per plan)

### F5 — Phase 2 grep criterion is stale after Phase 3

- **Severity**: ℹ️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/sources/source-details.tsx:81
- **Detail**: The „Plik" group renders `original_name` as „Nazwa pliku", as Phase 3 requires, but Phase 2's grep allows only the placeholder. Also, `erasedRecordFields` gained an optional 4th `recordTypes` argument (harmless).
- **Fix**: Note in the plan as an addendum; no code change.
- **Decision**: SKIPPED
