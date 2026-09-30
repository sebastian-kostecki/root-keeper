<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Sources list — Gallery and Table views

- **Plan**: context/changes/sources-list-views/plan.md
- **Scope**: Full plan (4 of 4 phases)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-09-30
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 3 observations

Automated checks re-run: Pest (SourcesTest, PersonsTest, EventSourcesTest) 201 passed; phpstan 0 errors; tsc clean; `npm run check` clean (150 files); Vitest 120 passed; build completes.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Stale `q` after a fast clear of the search box

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/sources/index.tsx:71-96
- **Detail**: Type "a", wait for the 300 ms visit (`lastVisitedQuery` = "a", `filters.q` still ""). Clear the input before the response lands: the debounce effect sees `trimmed === filters.q` and returns without visiting. The response then arrives with `filters.q = 'a'`; `lastVisitedQuery` already equals "a", so the input-sync effect does not fire. Result: input empty, list unfiltered, but URL and `filters.q` say `q=a`; a refresh or shared link shows the filtered list. `pages/persons/index.tsx` guards this with `latestSearchVisit` / `onCancel` / `onFinish`; the sources page copied only part of it.
- **Fix**: Compare the debounced value against the last *requested* query only (drop the `trimmed === filters.q` short-circuit, keep `lastVisitedQuery`), so clearing schedules a corrective visit; add a Vitest/manual check for type-then-clear.
  - Strength: Small change in one effect; `only: ['filters']` visits are cheap.
  - Tradeoff: One extra request in the rare race; must not loop on history navigation (covered by the sync effect).
  - Confidence: MED — race reasoned from code, not reproduced in a browser.
  - Blind spot: Behaviour of Inertia cancelling an in-flight visit when a new one starts.
- **Decision**: FIXED (Fix now — dropped the `trimmed === filters.q` short-circuit in the debounce effect; lint, types and Vitest pass after the fixes)

### F2 — Step 4.3 ticked without the canvas-board comparison

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/sources-list-views/screenshots/verdict.md:3,5,20; plan.md Progress 4.3
- **Detail**: Manual criterion 4.3 requires every state to match its canvas board. verdict.md itself says the boards were NOT compared ("the human still owes the board comparison"), yet 4.3 is `[x]` — possible rubber-stamp. Two lines are also wrong: line 3 says seeded sources have no files except a few placeholders (Phase 1 gives eight real images, ten fallbacks), and line 20 says the disabled „Inne" item is "still clickable" (code disables a 0-counter kind unless chosen: `pages/sources/index.tsx:175-181`).
- **Fix**: Do the board comparison (or untick 4.3 until done) and correct the two verdict.md lines.
- **Decision**: FIXED (Fix now — 4.3 unticked in plan.md; verdict.md lines 3 and 20 corrected; board comparison still owed)

### F3 — Live region announces on every keystroke

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/sources/index.tsx:109-114
- **Detail**: `role="status" aria-live="polite"` text derives from `visible.length`, which follows the un-debounced `search`; a screen reader announces "Znaleziono: N źródeł" per character. The plan specifies a polite live region but not its timing.
- **Fix**: Derive the status text from the debounced/committed query (`filters.q`) plus `filters.kind`, or debounce the text.
- **Decision**: FIXED (Fix now — status text computed from `filters.q`/`filters.kind`; lint/types re-run pending, Bash unavailable)

### F4 — Edit dialog is mounted/unmounted, not opened/closed

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/sources/sources-table.tsx:278-291
- **Detail**: Plan: one controlled dialog "opened by state that holds the chosen source". Implementation renders `{editing !== null && <SourceEditDialog key={editing.id} open …>}` with `open` hard-wired true. Works and saves, but loses the Radix close animation and focus may land on `body` instead of the row's „Akcje" button after close (unverified by keyboard).
- **Fix**: Keep the dialog mounted with `open={editing !== null}` and keep the last source in state for the exit animation; check focus return by keyboard.
- **Decision**: FIXED (Fix now — dialog stays mounted with `open={editOpen}`, remounted per opening via a session key; focus return by keyboard still to check)

### F5 — Unplanned `max-sm:px-1.5` on counted toggle items

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: resources/js/components/segmented-toggle.tsx (count items)
- **Detail**: Added during the gate to stop the kind filter overflowing at 390 px; recorded in verdict.md, not in the plan. No token added; `/persons` unaffected (no counts).
- **Fix**: Note it as an addendum in plan.md; no code change.
- **Decision**: FIXED (Fix now — addendum added to plan.md)

### F6 — Minor a11y/robustness leftovers

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/table-parts.tsx:46-58; resources/js/pages/sources/index.tsx:137; database/seeders/TreeViewDemoSeeder.php:69-71
- **Detail**: (a) Active sort state announced twice (sr-only suffix and `aria-sort`), carried over from the old persons table, now shared. (b) Toolbar has no `role="search"` landmark. (c) Seeder deletes demo files before its transaction; a failed transaction leaves rows without files until re-run (idempotent, demo only). Also `rodzaj` descending reverses the whole composite key, so an akt without a type comes first in `-rodzaj`; the plan's "reverses the primary key only" is ambiguous and tests cover ascending only.
- **Fix**: Optional; none blocks merge.
- **Decision**: FIXED in part (b) only — `role="search"` added to the toolbar; (a), (c) and the `-rodzaj` ambiguity left as is
