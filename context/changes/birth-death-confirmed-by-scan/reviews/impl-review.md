<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Birth and Death Confirmed by Scan

- **Plan**: context/changes/birth-death-confirmed-by-scan/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-09-24
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Automated criteria, re-run on 2026-09-24:
- `artisan migrate`: nothing to migrate.
- Full Pest suite: 112 passed, 470 assertions.
- `composer types:check`: 0 errors.
- Pint: clean.
- `npm run types:check`: passes.
- `npm run check`: passes.
- `npm run build`: passes.
- `route:list --path=events`: exactly 2 routes, both behind `auth` and `verified`.

## Findings

### F1 — Phase 2 manual checks and screenshot gate ticked without evidence

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/birth-death-confirmed-by-scan/plan.md (Progress 2.5–2.11)
- **Detail**:
  - All seven Phase 2 manual items were ticked in the same commit as the UI code (c21551e).
  - No commit message, note or file shows that a manual pass took place.
  - Item 2.11 claims screenshots of every state in light and dark themes were captured. No screenshot files exist in the repo or anywhere on disk.
  - CLAUDE.md makes a screenshotted rendering of every state the visual gate for UI changes, so that gate has not been passed.
- **Fix**: Untick items 2.5–2.11. Then run the manual pass in the app and capture the planned screenshots:
  - person preview: confirmed, unconfirmed, two sources, open picker, unlink dialog
  - scan page: with and without confirmations
  - both light and dark themes

  Re-tick each item only once it has evidence.
  - Strength: Restores the visual gate that CLAUDE.md requires, and makes the Progress section trustworthy again.
  - Tradeoff: Needs a human pass with real data. Roughly 20–30 minutes.
  - Confidence: HIGH. There are no screenshot artifacts, and the items were checked in the same commit as the code.
  - Blind spot: The user may have run the checks in a browser without saving anything. Only 2.11 is provably unmet.
- **Decision**: FIXED — Progress 2.5–2.11 unticked (SHA removed); manual pass + light/dark screenshots still to be done before re-ticking.

### F2 — Scan picker gets stuck on the loading skeleton after a failed link or reload

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/scans/scan-picker-dialog.tsx:44-47, 61, 67
- **Detail**:
  - When linking fails validation (`onError`), the redirect back is a normal Inertia visit, not a partial one. It doesn't include the optional `availableScans` prop, so the prop becomes `undefined`.
  - `isLoading = loading || availableScans === undefined` then stays true forever. The `scan_id` error shows under a skeleton that never resolves, and the user has to close and reopen the dialog.
  - The same happens when `router.reload({ only: ['availableScans'] })` fails on open.
  - The plan's "Critical Implementation Details" handled this stale-prop problem only for the success path.
- **Fix**: Keep the last non-undefined `availableScans` in local state and render from that, so the list survives the non-partial redirect. Derive the skeleton from the `loading` flag only, and show a short message if the reload failed.
- **Decision**: FIXED — picker caches last loaded scans in state, skeleton only while reloading, error note when the reload fails.

### F3 — Scan thumbnail link and "Odepnij" buttons lack distinguishable accessible names

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/persons/event-card.tsx:58-64, 71-77
- **Detail**:
  - The source thumbnail `<Link>` gets its only name from `ScanThumbnail`.
  - When `has_thumbnail` is false, the link contains only the decorative lucide `ImageOff` icon, so it has no accessible name at all. When a thumbnail exists, the name is just the file name.
  - Every source has an identical "Odepnij" button, so a screen reader user can't tell which scan a button unlinks.
- **Fix**: Add `aria-label={\`Otwórz skan ${scan.original_name}\`}` to the Link and `aria-label={\`Odepnij skan ${scan.original_name}\`}` to the unlink trigger.
- **Decision**: FIXED — aria-labels with scan.original_name on the thumbnail Link and the Odepnij trigger.

### F4 — Concurrent duplicate link can 500 on the unique index

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/EventScanController.php:19
- **Detail**:
  - `syncWithoutDetaching` does a SELECT and then an INSERT.
  - Two simultaneous requests for the same event and scan (for example from two tabs) would both pass the SELECT. The second then hits `unique(event_id, scan_id)` and throws `UniqueConstraintViolationException`, which becomes a 500 on pgsql.
  - The UI disables all picker items while a request is in flight, so this is unlikely.
- **Fix**: Catch `UniqueConstraintViolationException` in `store` and treat it as success, since linking is meant to be idempotent.
- **Decision**: FIXED — store catches UniqueConstraintViolationException and proceeds to the success toast.

### F5 — Test gaps against the plan's Testing Strategy

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/EventScansTest.php:127-143, tests/Feature/PersonsTest.php, tests/Feature/ScansTest.php
- **Detail**:
  - **"One scan confirms both events"** only counts relation rows. It never asserts `birth.is_confirmed` / `death.is_confirmed` on the two person-show payloads, which is the rule the UI relies on.
  - **Link-order and newest-first tests** attach both rows in the same second. They only exercise the `id` tiebreak, and the link-order test would pass even with no `orderBy`.
  - **No test unlinks a foreign-tree scan from your own event** (the `view` check on the scan).
  - **The plan's ScansTest case "never exposing another tree's data"** has no dedicated test.
- **Fix**: Make the following changes:
  - Assert `is_confirmed` in the two-events test.
  - Use `$this->travel()` between attaches in the ordering tests.
  - Add a foreign-scan unlink 404 test.
- **Decision**: FIXED — two-events test asserts is_confirmed on both previews; link-order test links the newer scan first (travel 1 min) so scan-id order would fail; newest-first test gives the higher-id scan an older created_at; new test: unlinking a foreign scan from an own event is 404 and keeps the row.

### F6 — Small UI drifts: client re-sort and conditional birth card

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/scans/scan-picker-dialog.tsx:100-102, resources/js/pages/persons/show.tsx:57
- **Detail**:
  - **Client re-sort:** the picker re-sorts with `.sort((a, b) => b.id - a.id)` on top of the server's `latest()`-then-id-desc order. This duplicates the ordering rule, and the two orders can disagree when `created_at` and `id` order differ.
  - **Conditional birth card:** the plan says the birth card is "always present", but it now renders only when `person.birth` is truthy. `CreatePerson` always creates a birth event, and an event card can't link without an event id, so this is harmless.
- **Fix**: Drop the client-side sort and trust the server order. Leave the birth guard as it is.
- **Decision**: FIXED — client-side re-sort removed; picker renders the server order. Birth guard kept (harmless; card needs an event id).

### F7 — Loose typing in two spots

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Resources/EventResource.php:36, resources/js/types/scan.ts:14
- **Detail**:
  - **Untyped closure:** `fn ($scan): array` has no parameter type. The project rules require type hints on every parameter, and `ScanController::show` types its closures (`fn (Event $event)`).
  - **Loose TS type:** `ScanConfirmation.event_type` is `string` instead of `PersonEvent['type']`.
- **Fix**: Use `fn (Scan $scan): array` and `event_type: PersonEvent['type']`.
- **Decision**: FIXED — closure typed `fn (Scan $scan): array`; `ScanConfirmation.event_type` narrowed to `PersonEvent['type']`.

## Triage summary

- Fixed: F1, F2, F3, F4, F5, F6, F7 (7).
- F1 left Progress items 2.5–2.11 unticked. The manual pass and light/dark screenshots are still outstanding.
- Checks after the fixes (2026-09-24), all passing:
  - Pest: 113 passed, 486 assertions.
  - PHPStan: 0 errors.
  - Pint: clean.
  - tsc: passes.
  - `vp check`: passes.
  - `vite build`: passes.
