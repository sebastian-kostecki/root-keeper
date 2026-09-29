<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Person modal (variant B)

- **Plan**: context/changes/person-modal/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5, 6
- **Date**: 2026-09-29
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Success criteria re-run at review time: 199 feature tests, Larastan, `npm run check`, `types:check`, 52 parser tests and `build` all pass. Manual rows are all `[x]`; open items in `screenshots/verdict.md` (canvas comparison, 390px touch check, real-browser click-away exits, `12.3.48` and Zgon-in-Źródła shots) remain pending.

Accepted drifts (not findings): hand-written `ui/tabs.tsx`; `useSetRootPerson` hook; „Osoba nie żyje” in the Zgon card body; Anuluj discards without the bar; create wraps `SectionEditorProvider personId={0}`; Powiąż in Źródła not guarded; mobile editor overlays instead of hiding tabs.

## Findings

### F1 — Failed save never resolves the save promise

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/overview-tab.tsx:187-203, resources/js/components/persons/section-editor.tsx:157-167, resources/js/components/persons/section-card.tsx:120-137
- **Detail**: The save promise resolves only in `onSuccess` (true) and `onError` (false). A 403/404/419/500 or network failure calls neither, so `saveAndExit` leaves `isSaving` true and never clears `pending`: Porzuć and Zapisz on the guard bar stay disabled and the user must reload, losing edits. `submit()` in section-card has the same hang.
- **Fix**: Resolve `false` in `onFinish` (a second resolve is a no-op) and wrap the `saveAndExit` chain in try/finally so `isSaving` always resets.
  - Strength: Small, local change; covers every failure path.
  - Tradeoff: None significant.
  - Confidence: HIGH — Inertia calls `onFinish` on every outcome.
  - Blind spot: Create dialog's own promise wiring should get the same treatment.
- **Decision**: FIXED — `onFinish` resolves false; `saveAndExit` catches rejection and always resets `isSaving`/`pending`

### F2 — Failed guarded save silently drops the blocked exit

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/section-editor.tsx:157-171
- **Detail**: After a failed Zapisz on the guard bar, `setPending(null)` closes the bar and the requested exit (tab, navigation, other card) is dropped without explanation. The user stays in the dirty editor and sees the server error in the card. Arguably intended.
- **Fix**: Keep as is and record it as accepted, or leave the bar open showing „Nie udało się zapisać” with Porzuć still available.
- **Decision**: ACCEPTED — intended: the server error stays visible in the card

### F3 — Parser accepts impossible calendar dates and year 0000

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/partial-date.ts:150-190
- **Detail**: `31.02.1900` and `31.04.1948` show a valid hint; the server's `checkdate` rejects them after submit (plan says the server stays the authority). Year `0000` is passed on; the server range rule (1000..current year) rejects it.
- **Fix**: Accept the server round trip (plan-conformant), or add a client day-in-month check.
- **Decision**: ACCEPTED — server is the authority (plan); round trip is fine

### F4 — Cancel and back are enabled while a save is in flight

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/section-card.tsx:194-198, 271-279
- **Detail**: Anuluj, mobile „Wróć” and Esc are not disabled during `processing`/`isSaving`; cancelling mid-save unmounts the editor and `onSuccess` calls `cancel()` on it later. Benign but inconsistent.
- **Fix**: Disable Anuluj/Wróć while `processing`.
- **Decision**: FIXED — Anuluj and mobile Wróć disabled while processing

### F5 — No test for the death-with-scans uncheck guard

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: tests/Feature/PersonsTest.php
- **Detail**: Unchecking `is_deceased` deletes the death event; the seeder and Phase 4 manual check rely on the server refusing this when a scan is linked. The reviewer did not verify existing coverage of that rule in this diff (it predates the change).
- **Fix**: Confirm an existing test covers it; add one only if not.
- **Decision**: DISMISSED — already covered by `PersonsTest.php:675` (asserts the „Najpierw odepnij skany od zgonu…” error).
