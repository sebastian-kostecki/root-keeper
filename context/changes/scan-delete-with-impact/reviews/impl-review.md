<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Scan delete with impact

- **Plan**: context/changes/scan-delete-with-impact/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-09-25
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

## Findings

### F1 — `router.delete` omits `preserveScroll: true`

- **Severity**: 🔵 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/scans/delete-scan-dialog.tsx (handleConfirm)
- **Detail**: `unlink-scan-dialog.tsx:40` passes `preserveScroll: true` to its `router.delete` call; `delete-scan-dialog.tsx`'s equivalent call omits it. Low impact in practice since scan destroy redirects to `scans.index` (a full navigation away from the page), so there's no scroll position to preserve — but the two sibling dialogs are now inconsistent in their call shape.
- **Fix**: Add `preserveScroll: true` to the `router.delete` options object in `delete-scan-dialog.tsx` for consistency with `unlink-scan-dialog.tsx`.
- **Decision**: FIXED

## Evidence summary

**Plan Drift Detection** (Agent 1): all 9 planned change items across both phases verified MATCH (ScanPolicy::delete, DeleteScan action, route+controller destroy, is_last_scan flag with no N+1, wayfinder destroy export, ScansTest coverage, ScanConfirmation type, DeleteScanDialog, show.tsx trigger). All 7 "What We're NOT Doing" scope boundaries confirmed respected. `git diff --name-only 1b49938..HEAD` file list maps 1:1 to planned files plus change-folder docs — no unplanned/extra source files.

**Safety, Quality & Pattern Compliance** (Agent 2): no CRITICAL or WARNING findings.
- Security: `Gate::authorize('delete', $scan)` runs before the action; `ScanPolicy::delete` mirrors `view`'s tree check + `denyAsNotFound()`; cross-tenant isolation confirmed by test.
- Performance: `withCount('scans')` + `with('participants')` in one eager-load — no N+1 from the `is_last_scan` addition.
- Reliability: `array_filter` before `Storage::disk('local')->delete()` tolerates null thumbnail and missing files without throwing, confirmed by test.
- Data safety: row deleted before files (plan's stated ordering); `event_scan` FK cascade confirmed at migration level and by test; events/persons never touched.
- Pattern compliance: `DeleteScan` vs `DeletePerson`, `ScanPolicy::delete` vs `view`, `ScanController::destroy` vs `PersonController::destroy`, `delete-scan-dialog.tsx` vs `unlink-scan-dialog.tsx`/`delete-person-dialog.tsx`, and the `show.tsx` heading row vs `persons/show.tsx` — all full matches except F1 above.

**Success criteria** (re-verified after epilogue commit): `ScansTest.php` + `EventScansTest.php` + `PersonsTest.php` → 87 passed, 593 assertions. `route:list --name=scans.destroy` → registered. Pint clean. Build succeeds. `types:check` fails with 15 pre-existing, unrelated `.form`-typing errors (confirmed via git-stash isolation to predate this change) — user explicitly approved proceeding past this known repo-wide gap during Phase 2.
