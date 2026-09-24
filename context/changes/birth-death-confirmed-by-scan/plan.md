# Birth and Death Confirmed by Scan Implementation Plan

## Overview

Roadmap slice S-03, the north star of milestone M-1 (FR-014, FR-008, US-01). A logged-in user links one or more of their scans to a person's birth or death event from the person preview, and can unlink them again. The preview shows each event's date and place with an explicit confirmation status ("Potwierdzone aktem" / "Bez źródła") and thumbnails of the linked scans; clicking a thumbnail opens the existing zoomable scan preview. The scan preview, in turn, lists which events that scan confirms.

## Current State Analysis

- S-01 modelled birth and death as `events` rows (tree-scoped, partial date, place) joined to people through `event_participants`, explicitly so that S-03 could attach scans by `event_id` and S-07 could add marriage as one event with two participants (`context/archive/2026-09-23-account-tree-and-first-person/plan.md:7`).
- S-02 added tree-scoped `scans` with authorized file/thumbnail endpoints and a zoom viewer (`app/Http/Controllers/ScanController.php`, `resources/js/components/scans/scan-viewer.tsx`).
- Nothing links events to scans yet. `EventResource` exposes only `type`, `date`, `place` (`app/Http/Resources/EventResource.php:22`), so the preview cannot express confirmation.
- `CreatePerson` always creates a birth event (even with empty date and place) and creates a death event only when the person is marked deceased (`app/Actions/Persons/CreatePerson.php:52-56`). Editing a person is S-05, so a person saved as living cannot yet receive a death event or a death scan.
- Authorization pattern: per-model policies returning `Response::denyAsNotFound()` when `tree_id` differs from the user's tree (`app/Policies/ScanPolicy.php`, `app/Policies/PersonPolicy.php`), discovered automatically (no manual registration in providers).
- The theme is monochrome neutral (`resources/css/app.css:65-88`): no success/warning tokens. shadcn/ui is the repo's single design system (`context/foundation/tech-stack.md:32-34`).

## Desired End State

- On `/persons/{id}`, the birth card (always) and the death card (deceased only) each show a status badge: filled "Potwierdzone aktem" with a check icon when at least one scan is linked, outline "Bez źródła" with a warning icon and muted text otherwise.
- Each event card lists its linked scans as thumbnails (in link order) linking to `/scans/{id}`, each with an "Odepnij" action that asks for confirmation and warns when unlinking the last scan makes the event unconfirmed.
- "Powiąż skan" on an event card opens a dialog that lazily loads the tree's scans (newest first, thumbnails, already-linked scans marked and not selectable). Picking one links it, closes the dialog and shows the toast "Powiązano skan".
- `/scans/{id}` shows a "Potwierdza" section listing linked events as "Urodzenie — Jan Kowalski" / "Zgon — …" links to the person, or a short empty note.
- No endpoint lets a user link, unlink or see another tree's event or scan (404 or validation error), as proven by feature tests.

### Key Discoveries:

- Event → scan linkage by `event_id` was pre-planned in S-01; no event model change is needed, only a pivot (`database/migrations/2026_09_23_045723_create_events_table.php`).
- `Person::birth()` / `death()` read the loaded `events` collection (`app/Models/Person.php:67-75`), so eager-loading `events.scans` on the preview makes scans available without extra queries.
- `JsonResource::resolve()` drops `whenLoaded` misses, so the persons index (which loads `events` without `scans`) keeps its current payload shape.
- Inertia v3 `Inertia::optional()` props are excluded from the first visit and delivered on a partial reload (`router.reload({ only: [...] })`), which fits the lazily loaded picker.
- Confirmation-dialog pattern with shadcn `Dialog`: `resources/js/components/delete-user.tsx`. Thumbnail tile with `ImageOff` fallback: `resources/js/pages/scans/index.tsx:57-73`.

## What We're NOT Doing

- Linking from the scan preview (choosing a person and event there). The scan page only lists what it confirms.
- Opening the scan in a modal on the person page. Clicking a thumbnail navigates to the existing `/scans/{id}` viewer.
- Adding a death event to a person saved as living, or editing event data. This belongs to S-05 (person edit).
- Marriage events and a scan shared by two spouses (S-07). The pivot supports it without changes.
- Deleting scans and the "facts that lose their source" list (S-08).
- Cleaning up events and their links when a person is deleted. Deleting a person cascades only `event_participants`, so the event and its `event_scan` rows stay. This is S-05 (person-edit-and-delete).
- New color tokens or Badge variants for status. The distinction uses existing variants, icon and text.
- Search, filter or pagination in the scan picker, and uploading a new scan from inside the picker.
- Showing confirmation status on the persons list or in the tree view (PRD Non-Goals: tree shows no status).
- Checking that the entered date or place agrees with the scan (PRD Business Logic: "confirmed" means "has a source").

## Implementation Approach

A plain many-to-many `event_scan` pivot carries the business rule "an event is confirmed iff it has at least one linked scan". The rule is computed once on the server (`EventResource.is_confirmed`) so the UI never re-derives it. A dedicated `EventScanController` (store/destroy) mutates links, authorized through a new `EventPolicy` and a tree-scoped `exists` rule on `scan_id`, and redirects back with a toast so the person preview re-renders with fresh props. The picker's scan list is an optional prop on the person preview, fetched only when the dialog opens. UI work reuses shadcn `Card`, `Badge`, `Dialog`, `Button`, `Skeleton` and extracts one shared `ScanThumbnail` tile, currently inlined in the scans grid, because three views now need it.

## Critical Implementation Details

- **Link ordering**: `Event::scans()` orders by the pivot's `created_at` (then pivot id), so "link order" is stable and tests can assert it; relying on insertion order without an explicit `orderBy` would make the thumbnail order database-dependent.
- **Picker state after linking**: the store redirect performs a normal visit that does not include the optional `availableScans` prop, so the dialog must close on success and re-fetch the list on its next open. Otherwise it would show stale "already linked" markers.

## Phase 1: Event–scan linking backend

### Overview

Schema, relations, authorization, link/unlink endpoints and the data the two views need, fully covered by feature tests.

### Changes Required:

#### 1. Pivot table

**File**: `database/migrations/<timestamp>_create_event_scan_table.php` (via `vendor/bin/sail artisan make:migration create_event_scan_table --no-interaction`)

**Intent**: Store which scans back which events, many-to-many, so one scan can confirm many events and one event can have several scans (PRD Business Logic).

**Contract**: table `event_scan`: `id`, `event_id` (FK → events, cascade on delete), `scan_id` (FK → scans, cascade on delete), timestamps, unique `(event_id, scan_id)`, index on `scan_id`.

#### 2. Model relations and event labels

**File**: `app/Models/Event.php`, `app/Models/Scan.php`, `app/Enums/EventType.php`

**Intent**: Expose the links from both sides and give event types Polish labels for the scan page.

**Contract**: `Event::scans(): BelongsToMany<Scan>` on `event_scan` with timestamps, ordered by pivot `created_at` then pivot `id`. `Scan::events(): BelongsToMany<Event>` on `event_scan` with timestamps, same ordering. `EventType::label(): string` returning `Urodzenie` / `Zgon`. Update the PHPDoc `@property`/generics consistently with siblings.

#### 3. Event authorization

**File**: `app/Policies/EventPolicy.php` (via `vendor/bin/sail artisan make:policy EventPolicy --model=Event --no-interaction`, trimmed to what is used)

**Intent**: Only the tree owner may change an event's sources; others get a 404 like every other tree-scoped model.

**Contract**: `update(User $user, Event $event): Response`, allowing when `$event->tree_id === $user->tree->id`, otherwise `Response::denyAsNotFound()`.

#### 4. Link / unlink endpoints

**File**: `app/Http/Controllers/EventScanController.php`, `app/Http/Requests/EventScanStoreRequest.php`, `routes/web.php`

**Intent**: Attach a scan of the user's own tree to an event (idempotently) and detach it again, returning to the page the user came from with a Polish toast.

**Contract**:
- Routes inside the existing `auth`+`verified` group: `POST events/{event}/scans` → `store` (name `events.scans.store`), `DELETE events/{event}/scans/{scan}` → `destroy` (name `events.scans.destroy`).
- `EventScanStoreRequest`: `authorize(): Response` returning `Gate::inspect('update', $this->route('event'))`, so another tree's event is a 404 before validation runs (FormRequest honors a `Response`, `FormRequest::passesAuthorization`). Otherwise a foreign event with a bad `scan_id` would return 422 and reveal that the event exists. `scan_id` required, integer, `Rule::exists('scans', 'id')->where('tree_id', <user tree id>)`; attribute name `skan`.
- `store`: (authorized by the request) `$event->scans()->syncWithoutDetaching([...])`, `Inertia::flash('toast', ['type' => 'success', 'message' => __('Powiązano skan')])`, `back()`.
- `destroy`: authorize `update` on the event and `view` on the scan; detach; if nothing was detached, `abort(404)`. Toast `Odpięto skan`, `back()`.

#### 5. Person preview data

**File**: `app/Http/Resources/EventResource.php`, `app/Http/Resources/PersonResource.php`, `app/Http/Controllers/PersonController.php`

**Intent**: Give the preview everything it needs to show status and sources, and a lazily loaded list of scans for the picker, without changing the persons index payload.

**Contract**:
- `EventResource` adds `id` (always), and via `whenLoaded('scans')`: `is_confirmed: bool` (scans not empty) and `scans: list<{id, original_name, has_thumbnail}>`. Update the array-shape PHPDoc (optional keys).
- `PersonController::show` eager-loads `events.scans` and adds prop `availableScans` as `Inertia::optional(fn () => ScanResource::collection(<tree scans latest, then id desc>)->resolve())`.
- `index` is unchanged (still loads `events` only).

#### 6. Scan preview "confirms" data

**File**: `app/Http/Controllers/ScanController.php`

**Intent**: Let the scan page list the events the scan confirms, linking back to the people.

**Contract**: `show` eager-loads `events.participants` and adds prop `confirms: list<{event_id, event_type, event_label, person: {id, display_name}}>`, one row per event participant, in link order. Build it inline or with a small resource following the `ScanResource` style.

#### 7. Feature tests

**File**: `tests/Feature/EventScansTest.php` (via `vendor/bin/sail artisan make:test --pest EventScansTest --no-interaction`), `tests/Feature/PersonsTest.php`, `tests/Feature/ScansTest.php`

**Intent**: Lock in the confirmation rule, isolation and the payload contracts the UI relies on.

**Contract** (behaviors to cover):
- Guests are redirected to login from both endpoints.
- Linking an own scan to an own event creates the link, redirects back and flashes the toast. Linking the same scan twice keeps a single link.
- Linking another tree's scan fails validation on `scan_id` and creates nothing. Linking to another tree's event returns 404, including when `scan_id` is also foreign or missing (authorization before validation).
- Unlinking removes the link. Unlinking a scan that isn't linked, or one from another tree's event, returns 404.
- One scan linked to two events (birth of A, death of B) confirms both.
- Person show exposes `birth.is_confirmed` false with no scans and true with one, lists `scans` in link order, and does not include `availableScans` on a normal visit. A partial reload with `only: ['availableScans']` returns only the user's own scans, newest first.
- Persons index payload does not contain `is_confirmed`/`scans`.
- Scan show `confirms` lists the linked events with person names, and is empty for an unlinked scan.
- Deleting a scan or an event cascades the pivot rows (database assertion).

### Success Criteria:

#### Automated Verification:

- Migration applies cleanly: `vendor/bin/sail artisan migrate --no-interaction`
- New and affected feature tests pass: `vendor/bin/sail artisan test --compact tests/Feature/EventScansTest.php tests/Feature/PersonsTest.php tests/Feature/ScansTest.php`
- Full test suite passes: `vendor/bin/sail artisan test --compact`
- Static analysis passes: `vendor/bin/sail composer types:check`
- Code style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- Frontend types still compile after Wayfinder regeneration: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- `vendor/bin/sail artisan route:list --path=events` shows exactly the two new routes behind `auth` and `verified`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes for these items live in the `## Progress` section at the bottom of the plan.

---

## Phase 2: Linking UI

### Overview

Person preview event cards with status, source thumbnails, the picker and the unlink confirmation, plus the "Potwierdza" section on the scan page, all built from existing shadcn components and tokens.

### Changes Required:

#### 1. Types

**File**: `resources/js/types/person.ts`, `resources/js/types/scan.ts`

**Intent**: Type the new payloads so the preview cannot confuse the index shape with the sourced shape.

**Contract**: `PersonEvent` gains `id`. New `LinkedScan = {id, original_name, has_thumbnail}`, `SourcedPersonEvent = PersonEvent & {is_confirmed: boolean; scans: LinkedScan[]}` and a preview person type whose `birth`/`death` are `SourcedPersonEvent | null`. New `ScanConfirmation` type matching `confirms` rows.

#### 2. Shared scan thumbnail

**File**: `resources/js/components/scans/scan-thumbnail.tsx`, `resources/js/pages/scans/index.tsx`

**Intent**: One tile for "thumbnail image or `ImageOff` fallback" reused by the scans grid, the picker and event cards, instead of three copies.

**Contract**: `ScanThumbnail({ scan: {id, original_name, has_thumbnail}, className? })` renders the lazy `<img>` from `ScanController.thumbnail.url` or the fallback icon on `bg-muted`. The scans grid switches to it with no visual change.

#### 3. Confirmation status badge

**File**: `resources/js/components/persons/event-source-status.tsx`

**Intent**: The single place that renders the confirmed/unconfirmed distinction required by US-01, without relying on color.

**Contract**: `EventSourceStatus({ isConfirmed })`. Confirmed: `Badge` default variant with a lucide check icon, text "Potwierdzone aktem". Unconfirmed: `Badge` outline variant with a lucide alert icon, `text-muted-foreground`, text "Bez źródła". Existing tokens only.

#### 4. Scan picker dialog

**File**: `resources/js/components/scans/scan-picker-dialog.tsx`

**Intent**: Let the user choose one of their scans for an event, loading the list only when needed.

**Contract**: Props: `eventId`, `linkedScanIds`, `availableScans: Scan[] | undefined`, trigger. Opening calls `router.reload({ only: ['availableScans'] })` and shows a `Skeleton` grid while the prop is undefined or reloading. The grid shows newest first with `ScanThumbnail`, name and upload date. Already-linked scans are disabled and marked "Już powiązany". Selecting a scan posts `{scan_id}` to `EventScanController.store(eventId)` with `preserveScroll` and closes on success. On error it stays open and shows `errors.scan_id` with `InputError` (`resources/js/components/input-error.tsx`). With no scans at all, the dialog shows an empty state linking to "Dodaj skan" (`ScanController.create()`).

#### 5. Unlink confirmation dialog

**File**: `resources/js/components/scans/unlink-scan-dialog.tsx`

**Intent**: Explicit confirmation before removing a source, consistent with the PRD's destructive-action rule.

**Contract**: Props: `eventId`, `scan`, `isLastScan`, `eventLabel`. The description names the scan and the event. When `isLastScan`, it adds "Wydarzenie stanie się niepotwierdzone." The confirm button (destructive variant, "Odepnij") sends `DELETE EventScanController.destroy({event, scan})` with `preserveScroll`. The pattern follows `delete-user.tsx`.

#### 6. Event card and person preview

**File**: `resources/js/components/persons/event-card.tsx`, `resources/js/pages/persons/show.tsx`

**Intent**: Replace the two hand-written birth/death cards with one component that shows data, status and sources, and offers linking.

**Contract**: `EventCard({ title, event: SourcedPersonEvent, availableScans })`: `CardHeader` with the title, `EventSourceStatus` and a "Powiąż skan" `ScanPickerDialog` trigger. Date and place keep the existing "brak danych" fallbacks. A "Źródła" row shows linked scans as `ScanThumbnail` links to `ScanController.show(id)`, each with an `UnlinkScanDialog` trigger. With no sources it shows a short muted note. `persons/show.tsx` receives `availableScans?: Scan[]` and renders `EventCard` for birth (always present) and death (only when present).

#### 7. Scan preview "Potwierdza" section

**File**: `resources/js/pages/scans/show.tsx`

**Intent**: Show which facts this act backs and let the user jump to those people.

**Contract**: Receives `confirms: ScanConfirmation[]`. Below the viewer it renders a compact "Potwierdza" list of `"{event_label} — {person.display_name}"` links to `PersonController.show(person.id)`, or "Ten skan nie potwierdza jeszcze żadnego wydarzenia." when empty. `ScanViewer` and its fixed `h-[calc(100vh-12rem)]` height (`scan-viewer.tsx:46`) stay unchanged, so the act remains fully visible above the fold.

### Success Criteria:

#### Automated Verification:

- Frontend type check passes: `vendor/bin/sail npm run types:check`
- Frontend lint/format check passes: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`
- Feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/EventScansTest.php tests/Feature/PersonsTest.php tests/Feature/ScansTest.php`

#### Manual Verification:

- US-01 end to end: a person with a real birth act linked shows the birth date and place with "Potwierdzone aktem", and clicking the thumbnail opens a readable zoomable scan
- A person with no linked birth scan shows "Bez źródła", clearly distinguishable from the confirmed state in both light and dark themes
- The picker loads with a skeleton, lists scans newest first, marks already-linked scans, links on click with the "Powiązano skan" toast, and shows the empty state for an account with no scans
- Linking a second scan to the same event shows both thumbnails in link order; one scan linked to two different people's events shows as confirmed on both previews
- Unlinking asks for confirmation, warns on the last scan, and afterwards the event shows "Bez źródła" with the "Odpięto skan" toast
- The scan page lists "Urodzenie — …" / "Zgon — …" links that lead to the right people, and shows the empty note for an unlinked scan
- Screenshots of the person preview (confirmed, unconfirmed, two sources, open picker, unlink dialog) and of the scan page with and without confirmations, in light and dark themes, are captured for review

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes for these items live in the `## Progress` section at the bottom of the plan.

---

## Testing Strategy

### Unit Tests:

- None needed. The confirmation rule is a one-line derivation covered through the resource payload in feature tests.

### Integration Tests:

- `EventScansTest`: link, idempotent link, unlink, cross-tree scan (validation), cross-tree event (404, also with an invalid `scan_id`), unlinking a non-linked scan (404), guests, one scan confirming two events, cascade on scan/event delete.
- `PersonsTest`: `is_confirmed`/`scans` on show in link order, `availableScans` absent on a normal visit and tree-scoped on a partial reload, index payload unchanged.
- `ScansTest`: `confirms` rows with person names, empty for an unlinked scan, never exposing another tree's data.

### Manual Testing Steps:

1. Upload two real acts, open a person, link the birth act, and verify the status badge, the thumbnail and navigation to the zoom viewer.
2. Link the second act to the same birth, then unlink the first. Confirm the dialog wording, then unlink the last and check the "Bez źródła" state.
3. Link one act to the birth of person A and the death of person B, then open the scan page and follow both "Potwierdza" links.
4. Log in as a second account and verify that the first account's event URLs (`POST`/`DELETE`) can't be used (404) and that its scans never appear in the picker.

## Performance Considerations

The person preview adds one eager-loaded query for `events.scans`. The picker list (up to ~500 rows in year one per PRD) is sent only on dialog open via the optional prop, and thumbnails are lazy-loaded images. No indexes are needed beyond the pivot's unique `(event_id, scan_id)` and `scan_id` index.

## Migration Notes

Additive migration: one new table, no data backfill. Production deploy runs the pre-migration backup from F-01 as usual. Rollback drops `event_scan` (losing only links, not scans or events).

## References

- Roadmap item: `context/foundation/roadmap.md` (S-03)
- PRD: `context/foundation/prd.md` (FR-014, FR-008, US-01, Business Logic)
- Event model origin: `context/archive/2026-09-23-account-tree-and-first-person/plan.md`
- Scan backend and viewer: `context/archive/2026-09-24-scan-upload-and-preview/plan.md`
- Similar policy: `app/Policies/ScanPolicy.php`
- Similar confirmation dialog: `resources/js/components/delete-user.tsx`
- Thumbnail tile to extract: `resources/js/pages/scans/index.tsx:57-73`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Event–scan linking backend

#### Automated

- [ ] 1.1 Migration applies cleanly
- [ ] 1.2 New and affected feature tests pass
- [ ] 1.3 Full test suite passes
- [ ] 1.4 Static analysis passes
- [ ] 1.5 Code style is clean
- [ ] 1.6 Frontend types still compile after Wayfinder regeneration

#### Manual

- [ ] 1.7 Route list shows exactly the two new routes behind auth and verified

### Phase 2: Linking UI

#### Automated

- [ ] 2.1 Frontend type check passes
- [ ] 2.2 Frontend lint/format check passes
- [ ] 2.3 Production build succeeds
- [ ] 2.4 Feature tests still pass

#### Manual

- [ ] 2.5 US-01 end to end: confirmed birth opens a readable scan
- [ ] 2.6 Unconfirmed event is clearly distinguishable in light and dark themes
- [ ] 2.7 Picker loads, marks linked scans, links with toast, shows empty state
- [ ] 2.8 Multiple scans per event and one scan across two people
- [ ] 2.9 Unlink confirmation with last-scan warning
- [ ] 2.10 Scan page Potwierdza links and empty note
- [ ] 2.11 Screenshots of all states in light and dark themes captured
