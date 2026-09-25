# Scan delete with impact Implementation Plan

## Overview

Let the user delete a scan (akt metrykalny) from its preview page after an explicit confirmation that first lists every fact (event + person) that loses this scan as a source, flagging those that will become unconfirmed because this is their last scan. Deleting removes the scan row, its event links and both files on disk. Roadmap S-08, PRD FR-015 and Business Logic ("Usunięcie ostatniego powiązanego skanu zmienia wydarzenie na niepotwierdzone").

## Current State Analysis

- Scans belong to a tree (`scans.tree_id`, `database/migrations/2026_09_24_043434_create_scans_table.php:16`); the model has no soft deletes or observers (`app/Models/Scan.php`).
- There is no delete path: `routes/web.php:14` registers only `index/create/store/show`, and `ScanPolicy` has only `view` (`app/Policies/ScanPolicy.php:14-19`), which returns `denyAsNotFound()` for another tree.
- `event_scan` cascades on `scan_id` (`database/migrations/2026_09_24_122757_create_event_scan_table.php:17`); `tests/Feature/EventScansTest.php:162` already proves `$scan->delete()` removes pivot rows.
- "Confirmed" is never stored — it is derived as "event has ≥1 scan" (`app/Http/Resources/EventResource.php:36`, `app/Http/Resources/PersonFormResource.php:65`). So removing the pivot rows is sufficient for events to become unconfirmed.
- Files live on the private `local` disk: original at `scans/{tree}/{uuid}.{ext}`, optional thumbnail at `scans/{tree}/thumbnails/{uuid}.jpg` (`app/Actions/Scans/StoreScan.php:29-43`). `StoreScan.php:59` shows the cleanup idiom `Storage::disk('local')->delete(array_filter([$path, $thumbnailPath]))`.
- `ScanController::show` (`app/Http/Controllers/ScanController.php:58-78`) already sends `confirms` — one row per (event, participant) with `event_id`, `event_type`, `event_label`, `person {id, display_name}` — rendered as the "Potwierdza" card in `resources/js/pages/scans/show.tsx:47-77`. It does not say how many scans each event has.
- Delete dialogs follow one copied pattern (plain shadcn `Dialog`, "Anuluj" secondary + destructive action, `router.delete(<Wayfinder>.url)`): `resources/js/components/scans/unlink-scan-dialog.tsx` (already uses `isLastScan` → "Wydarzenie stanie się niepotwierdzone.") and `resources/js/components/persons/delete-person-dialog.tsx`. There is no shared confirm component.
- Person delete deliberately keeps scans ("Skany pozostaną w bibliotece skanów.", `delete-person-dialog.tsx`; `context/archive/2026-09-25-person-edit-and-delete/plan.md:41` defers scan deletion to S-08).

## Desired End State

On `scans/show`, a "Usuń" button next to the heading opens a dialog titled "Usuń skan". The dialog names the scan, states the file will be permanently deleted and the operation cannot be undone, and lists every fact this scan confirms as `<event label> — <person>` with, per row, whether it becomes unconfirmed ("stanie się niepotwierdzone") or stays confirmed by another scan. An unlinked scan shows that it confirms no events. Confirming deletes the scan row, its `event_scan` rows and both files, then redirects to `scans.index` with the toast "Usunięto skan". Facts whose last scan was removed show as unconfirmed on the person page; events and persons themselves are untouched. Another tree's scan returns 404 and nothing is deleted.

### Key Discoveries:

- Unconfirmed state is automatic via the FK cascade + derived `is_confirmed` — no event-side code needed (`EventResource.php:36`, migration `:17`).
- `confirms` is the ready-made impact list; it only needs a per-event "is this the last scan" flag (`ScanController.php:62-77`).
- Marriage (S-07) will make one event have two participants; `confirms` is already flattened per participant, so the flag is per event and repeats on each participant row.
- Cross-tree access is 404 via `denyAsNotFound()` everywhere; tests assert `assertNotFound()` and that nothing was deleted (`tests/Feature/PersonsTest.php:659-667`).
- Toasts are flashed with `Inertia::flash('toast', ['type' => 'success', 'message' => __('…')])` and asserted with `assertSessionHas('inertia.flash_data.toast', …)`.

## What We're NOT Doing

- No trash, soft delete, undo, or keeping orphaned files (PRD Non-Goals: "Brak kosza i cofania usunięć"); recovery of a mistaken delete is only via the nightly backup (F-01).
- No delete control on the scan list cards (`scans/index`) — preview page only.
- No typed/password confirmation — the established plain-dialog pattern.
- No bulk delete.
- No change to person delete (it keeps scans) or to unlinking (`EventScanController@destroy`).
- No extraction of a shared confirm-dialog component — follows the existing per-dialog copy pattern.
- No change to the "Potwierdza" card on the page itself; the last-scan flag is shown only in the dialog.

## Implementation Approach

Backend first, fully covered by Pest: a `delete` policy ability, a small `DeleteScan` action that removes the row (cascading links) and then the files, a resource `destroy` route + controller method, and an `is_last_scan` flag added to each `confirms` row. Then the frontend: extend the `ScanConfirmation` type, add `DeleteScanDialog` modelled on `UnlinkScanDialog`/`DeletePersonDialog`, and place its trigger in the `scans/show` header the way `persons/show.tsx:38-60` places "Usuń".

## Critical Implementation Details

- **Timing & lifecycle** — Capture `path`/`thumbnail_path` before deleting the row, delete the row first, and only then delete the files: a failed file delete leaves a harmless orphan file, whereas deleting files first could leave a row pointing at missing files if the DB delete failed. A missing file (or null thumbnail) must not fail the request.

## Phase 1: Scan destroy backend

### Overview

Deleting a scan works end to end over HTTP, with the impact flag available on the show page props, and is covered by feature tests.

### Changes Required:

#### 1. Delete ability

**File**: `app/Policies/ScanPolicy.php`

**Intent**: Allow deleting only scans in the user's own tree; hide others as not found, consistent with `view`.

**Contract**: `delete(User $user, Scan $scan): Response` — same tree check as `view`, `Response::denyAsNotFound()` otherwise.

#### 2. DeleteScan action

**File**: `app/Actions/Scans/DeleteScan.php` (via `vendor/bin/sail artisan make:class Actions/Scans/DeleteScan --no-interaction`)

**Intent**: Single place that removes a scan permanently: the DB row (its `event_scan` rows cascade, which makes last-scan events unconfirmed) and then the original and thumbnail files. Docblock states that events and persons are never touched.

**Contract**: `handle(Scan $scan): void`. Ordering per Critical Implementation Details; files removed with `Storage::disk('local')->delete(array_filter([...]))`, tolerating a null thumbnail and missing files.

#### 3. Route and controller

**File**: `routes/web.php`, `app/Http/Controllers/ScanController.php`

**Intent**: Expose deletion as the resource `destroy` action and return the user to the scan list with a success toast.

**Contract**: add `destroy` to the `Route::resource('scans', …)->only([...])` list (route `scans.destroy`, `DELETE scans/{scan}`). `destroy(Scan $scan, DeleteScan $deleteScan): RedirectResponse` — `Gate::authorize('delete', $scan)`, `$deleteScan->handle($scan)`, `Inertia::flash('toast', ['type' => 'success', 'message' => __('Usunięto skan')])`, `to_route('scans.index')`.

#### 4. Impact flag on `confirms`

**File**: `app/Http/Controllers/ScanController.php` (`show`)

**Intent**: Let the page tell which facts become unconfirmed if this scan is deleted.

**Contract**: each `confirms` row gains `is_last_scan: bool` — true when the event has exactly one linked scan (this one). Load the events with a scan count (e.g. `withCount('scans')` on the `events` relation load, alongside `events.participants`); no N+1.

#### 5. Wayfinder

**File**: `resources/js/actions/App/Http/Controllers/ScanController.ts`, `resources/js/routes/scans/*` (generated)

**Intent**: Make `ScanController.destroy` available to the frontend.

**Contract**: regenerate with `vendor/bin/sail artisan wayfinder:generate` (or let the Vite plugin do it); do not hand-edit.

#### 6. Feature tests

**File**: `tests/Feature/ScansTest.php`

**Intent**: Cover the new behavior and its failure modes following the file's conventions (`Storage::fake('local')`, factories, 404 for other trees, toast assertion).

**Contract**: tests for —
- the existing guest test also asserts `delete(route('scans.destroy', $scan))` redirects to login;
- destroy of another tree's scan returns not found and deletes nothing (row, pivot rows and files remain);
- destroy deletes the scan row, its `event_scan` rows and both files, keeps the linked events and persons, and redirects to `scans.index` with the "Usunięto skan" toast;
- after destroy, an event that had only this scan has no scans (unconfirmed) while an event that also had another scan keeps it (still confirmed);
- destroy succeeds for a scan without a thumbnail and when a file is already missing on disk;
- `show` `confirms` carries `is_last_scan` true for an event linked only to this scan and false for an event also linked to another scan (update the existing confirms test's assertions accordingly).

### Success Criteria:

#### Automated Verification:

- Scan feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ScansTest.php`
- Existing event-scan and person tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/EventScansTest.php tests/Feature/PersonsTest.php`
- Route is registered: `vendor/bin/sail artisan route:list --name=scans.destroy`
- PHP formatting is clean: `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human before proceeding to the next phase.

---

## Phase 2: Delete dialog on scan preview

### Overview

The user can delete a scan from its preview page through a confirmation dialog showing the impact list.

### Changes Required:

#### 1. Type

**File**: `resources/js/types/scan.ts`

**Intent**: Reflect the new prop field.

**Contract**: `ScanConfirmation` gains `is_last_scan: boolean`.

#### 2. DeleteScanDialog

**File**: `resources/js/components/scans/delete-scan-dialog.tsx`

**Intent**: Confirmation dialog in the established pattern (copy structure from `unlink-scan-dialog.tsx`): title "Usuń skan"; description asks "Czy na pewno chcesz usunąć skan „<original_name>”?", says the scan and its file will be permanently deleted and "Tej operacji nie można cofnąć."; when `confirms` is non-empty, a list headed by the facts that lose this source, one row per confirmation `<event_label> — <display_name>` with "stanie się niepotwierdzone" when `is_last_scan`, otherwise "pozostanie potwierdzone innym skanem"; when empty, a line that the scan confirms no events. Buttons "Anuluj" (secondary, `DialogClose`) and destructive "Usuń" (disabled while processing).

**Contract**: `DeleteScanDialog({ scan: Scan, confirms: ScanConfirmation[], trigger: React.ReactNode })`; confirm calls `router.delete(ScanController.destroy(scan.id).url, { onSuccess: close, onFinish: stop processing })`. The unconfirmed marker must be visually distinguishable (e.g. destructive/emphasised text), not only by wording.

#### 3. Trigger on the preview page

**File**: `resources/js/pages/scans/show.tsx`

**Intent**: Place the delete action next to the heading, matching `persons/show.tsx:38-60`.

**Contract**: wrap `Heading` in the same `flex items-center justify-between gap-4` row; trigger is `<Button variant="outline" size="sm" className="text-destructive hover:text-destructive">Usuń</Button>` passed to `DeleteScanDialog` with `scan` and `confirms`.

### Success Criteria:

#### Automated Verification:

- TypeScript type check passes: `vendor/bin/sail npm run types:check`
- Lint and format check pass: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`
- Scan feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/ScansTest.php`

#### Manual Verification:

- Scan linked to a birth that has a second scan and to a death that has only this scan: the dialog lists both facts, death marked "stanie się niepotwierdzone", birth marked as staying confirmed
- After confirming: redirect to the scan list with toast "Usunięto skan", the scan is gone from the list, and on the person page the death shows as unconfirmed while the birth stays confirmed
- Unlinked scan: dialog states it confirms no events; delete works
- "Anuluj" closes the dialog without deleting anything
- Dialog is readable in light and dark mode and at phone width

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- None — behavior is covered at the HTTP boundary by feature tests.

### Integration Tests:

- Feature tests in `tests/Feature/ScansTest.php` listed in Phase 1 §6: authorization (guest, other tree), full removal (row, pivot, files), confirmed/unconfirmed outcome for shared vs sole-source events, tolerance of missing thumbnail/file, and the `is_last_scan` prop.

### Manual Testing Steps:

1. Upload two scans; link both to a person's birth and only the first to their death.
2. Open the first scan's preview, click "Usuń", read the impact list (death → niepotwierdzone, birth → pozostanie potwierdzone).
3. Confirm; check toast, scan list and the person page states.
4. Delete an unlinked scan; cancel a dialog once to confirm nothing happens.

## Performance Considerations

`show` adds one aggregate count per page load (`withCount` on the eager-loaded events) — negligible at the expected volume (~500 scans/year).

## Migration Notes

None — no schema change. Deleting a scan is irreversible in the app; production recovery relies on the F-01 nightly backup and its retention.

## References

- Roadmap item: `context/foundation/roadmap.md` S-08
- PRD: `context/foundation/prd.md:96` (FR-015), `:122` (Business Logic), `:140` (no trash/undo)
- Deferral to S-08: `context/archive/2026-09-24-scan-upload-and-preview/plan.md:52`, `context/archive/2026-09-25-person-edit-and-delete/plan.md:41`
- Similar implementation: `app/Actions/Persons/DeletePerson.php`, `app/Http/Controllers/PersonController.php:138-147`, `resources/js/components/scans/unlink-scan-dialog.tsx`, `resources/js/components/persons/delete-person-dialog.tsx`
- File cleanup idiom: `app/Actions/Scans/StoreScan.php:59`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Scan destroy backend

#### Automated

- [x] 1.1 Scan feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ScansTest.php` — ced226d
- [x] 1.2 Existing event-scan and person tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/EventScansTest.php tests/Feature/PersonsTest.php` — ced226d
- [x] 1.3 Route is registered: `vendor/bin/sail artisan route:list --name=scans.destroy` — ced226d
- [x] 1.4 PHP formatting is clean: `vendor/bin/sail bin pint --dirty --format agent` — ced226d

### Phase 2: Delete dialog on scan preview

#### Automated

- [x] 2.1 TypeScript type check passes: `vendor/bin/sail npm run types:check`
- [x] 2.2 Lint and format check pass: `vendor/bin/sail npm run check`
- [x] 2.3 Production build succeeds: `vendor/bin/sail npm run build`
- [x] 2.4 Scan feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/ScansTest.php`

#### Manual

- [x] 2.5 Scan linked to a birth that has a second scan and to a death that has only this scan: the dialog lists both facts, death marked "stanie się niepotwierdzone", birth marked as staying confirmed
- [x] 2.6 After confirming: redirect to the scan list with toast "Usunięto skan", the scan is gone from the list, and on the person page the death shows as unconfirmed while the birth stays confirmed
- [x] 2.7 Unlinked scan: dialog states it confirms no events; delete works
- [x] 2.8 "Anuluj" closes the dialog without deleting anything
- [x] 2.9 Dialog is readable in light and dark mode and at phone width
