# Scan delete with impact — Plan Brief

> Full plan: `context/changes/scan-delete-with-impact/plan.md`

## What & Why

Let the user delete a scan (akt metrykalny) after an explicit confirmation that first shows every fact losing this scan as its source (roadmap S-08, PRD FR-015). Without it, scans can only accumulate; and deleting one silently would break the Business Logic rule that a fact is confirmed only while it has a scan.

## Starting Point

Scans can be uploaded, previewed and linked to birth/death events; the preview page already lists what the scan confirms ("Potwierdza"). There is no delete route or policy ability, "confirmed" is derived from linked scans, and the `event_scan` links cascade when a scan row is deleted.

## Desired End State

On a scan's preview page, "Usuń" opens a dialog listing each fact (`Urodzenie — Jan Kowalski`) with a marker: "stanie się niepotwierdzone" if this is its last scan, otherwise it stays confirmed by another scan. Confirming permanently deletes the scan row, its links and both files, and returns to the scan list with the toast "Usunięto skan"; affected facts show as unconfirmed on the person page.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Impact list content | Every fact linked to the scan, each flagged whether it becomes unconfirmed | FR-015 lists facts losing this source; flagging the last-scan ones avoids the "silent unconfirm" risk the roadmap names. |
| Files on disk | Delete original + thumbnail right after the row is deleted; missing files tolerated | PRD rules out trash/undo; no orphan files; recovery only via the nightly F-01 backup. |
| Placement | Scan preview page only, then redirect to scan list with toast | Preview already loads the impact data (`confirms`); list cards are whole links with no event data. |
| Confirmation strength | Plain dialog, no typed confirmation | Matches existing person/relation/unlink dialogs. |
| Cross-tree access | 404 via `denyAsNotFound()` | Same as every other resource in the app. |
| Impact flag delivery | `is_last_scan` added to each `confirms` row via `withCount('scans')` | One extra aggregate on page load; no separate request. |

## Scope

**In scope:**
- `ScanPolicy::delete`, `DeleteScan` action, `scans.destroy` route + controller method
- `is_last_scan` on `confirms`; Wayfinder regeneration
- `DeleteScanDialog` and "Usuń" button on `scans/show`
- Feature tests in `tests/Feature/ScansTest.php`

**Out of scope:**
- Trash, soft delete, undo, keeping orphaned files
- Delete from scan list cards; bulk delete; typed confirmation
- Changes to person delete (still keeps scans) or unlinking
- Shared confirm-dialog component; marker on the page's "Potwierdza" card

## Architecture / Approach

`DELETE scans/{scan}` → `ScanController@destroy` authorizes `delete`, calls `DeleteScan::handle` (delete row → FK cascades `event_scan` → derived `is_confirmed` flips automatically; then delete files), flashes a toast and redirects to `scans.index`. The dialog renders from `confirms` props already on the page, now carrying `is_last_scan`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Scan destroy backend | Policy, action, route, controller, `is_last_scan`, Pest coverage | Wrong ordering of row vs file deletion leaving a row without a file |
| 2. Delete dialog on scan preview | Dialog with impact list and trigger on `scans/show` | Unconfirmed marker not distinguishable enough |

**Prerequisites:** S-03 (event–scan linking) done — yes.
**Estimated effort:** ~1 session across 2 phases.

## Open Risks & Assumptions

- A mistaken delete is recoverable only from the nightly backup within its retention (F-01 notes the 5000 MB cap may shorten history).
- Impact list is computed at page load; a link changed in another tab between load and confirm is not reflected (single-user app, accepted).
- When marriage (S-07) lands, a shared event yields one row per spouse, each with the same flag — no change needed here.

## Success Criteria (Summary)

- The user sees exactly which facts lose this scan and which become unconfirmed before confirming.
- After deletion the scan and its files are gone, and sole-source facts show as unconfirmed while others stay confirmed.
- Another user's scan cannot be deleted (404).
