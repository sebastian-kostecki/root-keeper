# Source Description in Modal Implementation Plan

## Overview

Roadmap S-17 (prd-v3 FR-006, FR-007, US-02; prd-v2 FR-001 – FR-005, FR-014, US-01). A source is still only a file with a kind. This change gives it a description — nazwa, rok, pochodzenie, link, notatka, and for an akt metrykalny also typ and numer wpisu — and moves the work into two modals: „Dodaj źródło" replaces the upload page, „Edytuj źródło" corrects kind and description. Changing the kind away from akt metrykalny never erases typ or numer wpisu silently. The description shows on the list cards and next to the image on the preview page.

## Current State Analysis

**Data.** `sources` holds `tree_id`, `kind`, `original_name`, `path`, `thumbnail_path`, `mime_type`, `size`, `width`, `height` (`app/Models/Source.php:28`). There is no description column. `SourceKind` has three cases with lowercase Polish labels and an `options()` helper (`app/Enums/SourceKind.php:11-29`).

**Backend.** `routes/web.php:24` exposes `index, create, store, show, destroy` — no `update`. `SourcePolicy` has `view` and `delete` only. `StoreSource::handle(Tree, UploadedFile, SourceKind)` writes the file, the thumbnail and the row (`app/Actions/Sources/StoreSource.php:26`). `store` redirects to the new source's preview (`SourceController.php:56`). Only `person` has a numeric route pattern (`app/Providers/AppServiceProvider.php:29`).

**Frontend.** Upload is a page (`resources/js/pages/sources/create.tsx`) with the extracted `SourceKindField`. The list card shows thumbnail, file name, kind badge and upload date (`pages/sources/index.tsx:61-79`). The preview page shows the file name as its title, a full-width viewer and the „Potwierdza" card (`pages/sources/show.tsx`). `original_name` is rendered directly in about ten places, including the person modal (`sources-tab.tsx:81-83`, `overview-tab.tsx:110`, `source-picker-dialog.tsx:135-137`, both source dialogs, `source-thumbnail.tsx:22`).

**Design-system charges (the contract for this change).**

| Category | Charge | Where | User impact |
| --- | --- | --- | --- |
| Missing shared component | No `Textarea` in `resources/js/components/ui/` | the note field | A multi-line note has no styled control |
| Missing shared component | Description fields exist nowhere; two modals need the same group | upload and edit modals | Fields would drift between add and edit |
| Accidental architecture | The display label of a source is `original_name`, read in ~10 components | list, preview, picker, person modal, dialogs | A named source would still show „IMG_2034.jpg" in most places |
| Missing tokens | None. The erase warning uses the existing `destructive` token; `resources/css/app.css` has no `warning` token and this change does not add one | — | — |

**Linking rule.** Until S-18 only an akt metrykalny can be linked (`EventSourceStoreRequest.php:37-46`), the picker lists only akts (`HandleInertiaRequests.php:56-58`) and the badge says „Potwierdzone aktem" (`event-source-status.tsx`).

**Screenshot gate.** There is no automated screenshot test. S-16 used a per-change `shots.mjs` against `TreeViewDemoSeeder` data; its `before/` folder was never shot, so no trustworthy baseline of the source pages exists.

## Desired End State

- On „Źródła", „Dodaj źródło" opens a modal. A file and a kind are enough to save; every description field is optional. After saving, the new source's preview page opens, as today.
- For akt metrykalny the modal also offers „Typ aktu" and „Numer wpisu"; for the other kinds these two fields are absent.
- On the preview page, „Edytuj" opens a modal prefilled with kind and description. Saving returns to the same preview with „Zapisano źródło".
- Switching an akt to another kind, when typ or numer wpisu has a value (saved, or typed in the open modal), shows which fields will be erased and saves only after explicit confirmation. With both empty it saves with no extra step.
- Changing the kind of a linked akt keeps its links; every fact keeps its confirmed state.
- Wherever a source is shown, its name appears, or the file name when the name is empty. Downloads still use the original file name.
- A list card shows thumbnail, name, kind badge, „typ · rok" when set, and pochodzenie when set; the upload date shows only when none of those three is set.
- The preview page shows the description next to the image on wide screens — numer wpisu and notatka included — with the link clickable in a new tab.
- `/sources/create` returns 404.

Verification: the Pest tests named in Phase 1 – 3, the two JS unit tests, and the screenshot verdict in Phase 4.

### Key Discoveries:

- `resources/js/components/form-dialog.tsx:14-89` is the shared long-form modal shell (full-screen below `sm`, pinned header/footer, scrolling body); the person-form change marked it reusable for this slice.
- The simplest `<Form>`-in-modal precedent is `resources/js/components/tree/pick-or-create-person-dialog.tsx:196-244` (`options={{ preserveScroll: true }}`, `onSuccess` closes, `onError` scrolls the first `[data-slot="input-error"]` into view).
- Inertia v3 `<Form>` exposes `submit()` and `getData()` through a ref (docs: The Basics → Forms → Programmatic Access), and an `onBefore` that returns `false` cancels the visit; together they let a confirmation step intercept „Zapisz".
- Optional `Select` with a `NONE` sentinel mirrored into a hidden input: `resources/js/components/persons/person-section-fields.tsx:117-135`.
- Shared validation rules live in traits under `app/Concerns/` (`PartialDateValidationRules.php:22` sets the year range `between:1000,<current year>`).
- Confirm dialogs are plain nested `Dialog`s; there is no `alert-dialog` (`delete-source-dialog.tsx:36-103`).
- Removing a `create` route without a numeric pattern makes `GET /…/create` hit `{model}` and fail with a PostgreSQL invalid-integer 500 (precedent: `context/archive/2026-09-28-person-form-in-modal/plan.md:55`).
- `TreeViewDemoSeeder` is append-only: earlier scripts rely on creation order (`database/seeders/TreeViewDemoSeeder.php:23-28`, `:240-248`). Its sources have no files on disk.
- Enum options reach the client as `[{value, label}]` from a static `options()` (`SourceKind.php:23-29`).

## What We're NOT Doing

- No adding or editing a source from the person modal. Both modals open only in „Źródła". Worth revisiting together with S-18, when any kind can be linked.
- No linking of non-akt kinds and no kind next to a fact (S-18). The badge stays „Potwierdzone aktem" even for a linked akt whose kind was changed — accepted until S-18.
- No „matching akts first" in the picker (S-19).
- No search by name (prd-v2 FR-006, parked in the roadmap).
- No link on the list cards; the link is clickable only on the preview page.
- No „Zapisz i dodaj kolejne", no multi-file upload, no replacing the file of an existing source.
- No backfill of `name`; existing sources keep showing their file name.
- No unsaved-changes guard in the source modals. Esc, X and „Anuluj" close and discard.
- No confirmation when the kind changes inside „Dodaj źródło" — nothing is saved yet, the akt fields just hide.
- No edit or delete actions on list cards.
- No list of entries per source, no marking of an area on the image, no note tied to a specific event (prd-v2 Non-Goals).
- Pochodzenie is free text, not a gazetteer place.
- No new design tokens, no edits to `resources/js/components/ui/dialog.tsx`, no second design system.
- No automated screenshot test and no blind baseline updates.
- No change to accepted file types, the 20 MB limit or thumbnail generation.

## Implementation Approach

Contract before pixels, in four phases.

1. **Contract.** Columns, enum, requests, actions, policy, `update` endpoint and resource fields land with their tests while the UI stays as it is. The phase ends by shooting the `before/` baseline, so the comparison data is identical before and after.
2. **Upload modal and list.** The missing shared components come first (`Textarea`, the description field group), then the modal, then the page and route removal, then the single display label everywhere.
3. **Edit modal and preview.** The kind-change rule is a pure function with unit tests; the modal and the preview panel consume it.
4. **Gate.** `after/` screenshots, a written verdict, copy check, full suite.

The server is the authority on the erase rule. The client predicate is a superset of the server's, so a confirmed save is never rejected and an unconfirmed erase never succeeds.

## Critical Implementation Details

**Route removal and the numeric pattern land together (Phase 2).** Dropping `create` from the resource without `Route::pattern('source', '[0-9]+')` turns `GET /sources/create` into a 500. The pattern, the removal and the 404 test belong to one commit.

**Hidden akt fields still submit.** The akt-only group stays mounted while another kind is selected, so values survive toggling back. The server must drop them whenever the submitted kind is not akt metrykalny; the client is never the authority.

**Confirm-then-submit ordering (Phase 3).** The confirmation flag has to be in the form's DOM before `formRef.current.submit()` runs. Setting React state and calling `submit()` in the same tick submits without it: the ref handle is rebuilt only on commit and `submit()` reads `new FormData(form)` synchronously. So the flow is DOM-driven, with no React state in the submit path:

- The intercept is `<Form onBefore>`, not a click handler on „Zapisz" and not `onSubmit` (`<Form>` overwrites a consumer `onSubmit`). `onBefore` covers the button and the Enter key in any field; returning `false` cancels the visit.
- The flag is an always-mounted `<input type="hidden" name="discard_record_fields" defaultValue="0">` inside the form. Confirming writes `'1'` to it through a ref and then calls `formRef.current.submit()`; `onFinish` resets it to `'0'`.
- The typed typ and numer wpisu are read with `formRef.current.getData()`; the description inputs stay uncontrolled, as Phase 2 specifies.

This is the first use of `onBefore` and `<Form ref>` in the repo. Not yet exercised in a browser: check that cancelling in `onBefore` does not leave the buttons disabled (`processing`).

**Text inputs for rok and link.** `type="number"` and `type="url"` raise the browser's native bubble, which covers the inline Polish error (seen in `context/archive/2026-09-29-person-modal/screenshots/verdict.md:18`). Use text inputs with `inputMode` and let the server validate.

**Baseline before pixels.** `before/` must be shot at the end of Phase 1, before any Phase 2 frontend edit. S-16 left its baseline empty and the gap could not be repaired afterwards.

## Phase 1: Contract — the description in the data and the backend

### Overview

`sources` gains the description columns; store accepts them; a new `update` endpoint edits kind and description under the erase rule; the resource exposes the description and one display label. The UI does not change in this phase.

### Changes Required:

#### 1. Record type enum

**File**: `app/Enums/SourceRecordType.php` (new)

**Intent**: Name the type of an akt metrykalny so S-19 can match it against an event type later.

**Contract**: Backed string enum with cases `Birth = 'birth'`, `Marriage = 'marriage'`, `Death = 'death'`, `Other = 'other'`; `label()` returns `urodzenia`, `małżeństwa`, `zgonu`, `inny`; static `options()` with the same shape as `SourceKind::options()`.

#### 2. Schema migration

**File**: `database/migrations/<timestamp>_add_description_to_sources_table.php` (new, via `vendor/bin/sail artisan make:migration`)

**Intent**: Add the description as nullable columns so existing rows need no backfill and the deploy carries no data risk.

**Contract**: On `sources`, all nullable: `name` string(255), `record_type` string, `entry_number` string(50), `year` unsigned small integer, `provenance` string(255), `url` string(2048), `note` text. `down()` drops exactly these seven columns. Anonymous class with docblocks on `up()`/`down()`, as in `2026_09_29_100000_rename_scans_to_sources.php`.

#### 3. Model and factory

**File**: `app/Models/Source.php`, `database/factories/SourceFactory.php`

**Intent**: Make the description assignable and typed, and give tests and the seeder a described state.

**Contract**: The seven columns join `#[Fillable]` and the `@property` block; `record_type` casts to `SourceRecordType`, `year` to integer. A `displayName(): string` method returns `name` when set, otherwise `original_name`. The factory default leaves the description null; a new `described()` state fills every field for an akt.

#### 4. Shared description validation

**File**: `app/Concerns/SourceDescriptionValidationRules.php` (new)

**Intent**: One definition of the description rules and their Polish attribute names for store and update.

**Contract**: Trait with two helpers, `sourceDescriptionRules()` and `sourceDescriptionAttributes()`, returning the rules and attribute names below. It defines neither `rules()` nor `attributes()`: `SourceStoreRequest.php:31-37` already has its own `attributes()`, and a class method wins over a trait method without any error (pattern: `PartialDateValidationRules::partialDateRules()`, merged by hand in `PersonStoreRequest.php:37-38`, `:47-67`).

| Field | Rule | Attribute |
| --- | --- | --- |
| `name` | nullable, string, max 255 | nazwa |
| `year` | nullable, integer, between 1000 and the current year | rok |
| `provenance` | nullable, string, max 255 | pochodzenie |
| `url` | nullable, `url:http,https`, max 2048 | link |
| `note` | nullable, string, max 5000 | notatka |
| `record_type` | excluded unless `kind` is `metrical_record`; nullable, `SourceRecordType` enum | typ aktu |
| `entry_number` | excluded unless `kind` is `metrical_record`; nullable, string, max 50 | numer wpisu |

„Rok" is one four-digit year; „numer wpisu" is free text („47", „47a", „12/1878").

#### 5. Form requests

**File**: `app/Http/Requests/SourceStoreRequest.php`, `app/Http/Requests/SourceUpdateRequest.php` (new)

**Intent**: Store keeps file and kind required and gains the optional description. Update edits kind and description, never the file, and enforces the erase rule.

**Contract**: Both use the trait and spread its two helpers into their own `rules()` and `attributes()`. `SourceUpdateRequest::authorize()` returns `Gate::inspect('update', $this->route('source'))` (pattern: `EventSourceStoreRequest.php:19-22`). Update rules: `kind` required enum, the description rules, and `discard_record_fields` boolean. The erase rule: when the stored source is an akt metrykalny, the submitted kind is not, and the stored `record_type` or `entry_number` is non-null, the request fails on `kind` unless `discard_record_fields` is accepted. Message: „Zmiana rodzaju usunie typ aktu i numer wpisu. Potwierdź zmianę." With both stored values null the rule does not apply.

#### 6. Actions

**File**: `app/Actions/Sources/StoreSource.php`, `app/Actions/Sources/UpdateSource.php` (new)

**Intent**: Persist the description on upload and on edit, keeping the invariant that only an akt carries typ and numer wpisu.

**Contract**: `StoreSource::handle(Tree $tree, UploadedFile $file, SourceKind $kind, array $description = [])` with an array-shape PHPDoc; file, thumbnail and cleanup behaviour unchanged. `UpdateSource::handle(Source $source, SourceKind $kind, array $description): Source` updates kind and description and sets `record_type` and `entry_number` to null whenever the kind is not `MetricalRecord`. It never touches `original_name`, `path`, `thumbnail_path` or the `event_source` rows.

#### 7. Policy, controller, routes

**File**: `app/Policies/SourcePolicy.php`, `app/Http/Controllers/SourceController.php`, `routes/web.php`

**Intent**: Expose the edit endpoint under the same ownership rule and give both pages the option lists their modals need.

**Contract**: `SourcePolicy::update` mirrors `view` (foreign tree → `denyAsNotFound`). The resource route gains `update`; `create` stays until Phase 2. `SourceController::update(SourceUpdateRequest, Source, UpdateSource)` flashes `['type' => 'success', 'message' => __('Zapisano źródło')]` and redirects `back()`. `store` passes the validated description to `StoreSource` and keeps redirecting to `sources.show`. `index` and `show` add the props `kinds` and `recordTypes`. Regenerate Wayfinder afterwards.

#### 8. Resources

**File**: `app/Http/Resources/SourceResource.php`, `app/Http/Resources/EventResource.php`, `app/Http/Resources/MarriageResource.php`

**Intent**: One display label for every consumer, and the description for the list and preview.

**Contract**: `SourceResource` adds `name`, `display_name`, `record_type`, `record_type_label`, `entry_number`, `year`, `provenance`, `url`, `note` (all but `display_name` nullable) and keeps `original_name`. The inline source shape in `EventResource` (`:55`) adds `display_name`, and so does the `sources?` shape in its own docblock (`:30`); the `MarriageResource` docblock follows.

#### 9. Kitchen sink data

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Render every description state from seeded data. Appended after the existing sources so earlier scripts keep their order.

**Contract**: Three new sources at the end of `sourceStates()`, and the docblock at `:19-31` lists them:

- a fully described akt — name „Urodzenia Lipnica 1878 s. 12", typ urodzenia, rok 1878, pochodzenie „Archiwum Akt Dawnych w Warszawie", numer wpisu „47", a two-paragraph note, an `https` link;
- a gravestone with only name and rok;
- an akt with a name and pochodzenie long enough to truncate on a card.

Each of the three gets an explicit `original_name` (the factory default is a random slug), so the screenshot script can find them by file name before Phase 2 and by name after.

The existing undescribed sources stay untouched and serve as the empty state.

#### 10. Tests

**File**: `tests/Feature/SourcesTest.php`, `tests/Feature/SourceMigrationTest.php`, `tests/Feature/PersonsTest.php`

**Intent**: Cover the new endpoint's happy path and other-user 404, the erase rule at its boundary, and that nothing existing moved.

**Contract**: New tests, named for the behaviour:

- storing with only a file and a kind leaves the description empty
- storing with a full description persists every field
- storing a gravestone ignores a submitted record type and entry number
- store rejects a year outside 1000–current year, a non-http link (`javascript:`, `ftp:`), an unknown record type and over-long fields, with Polish messages
- updating changes the kind and the description and redirects back with the toast
- updating a source from another user's tree returns not found and changes nothing
- guests are redirected from the update route
- changing an akt with a stored record type or entry number to another kind without confirmation is rejected and nothing changes
- the same change with confirmation saves the kind and erases both fields
- changing an akt with an empty record type and entry number to another kind needs no confirmation
- changing the kind of a linked akt keeps its event links and the events stay confirmed
- updating never changes the original name or the stored file paths
- the source resource falls back to the file name when the name is empty
- the index and show pages expose the kind and record type options
- the file download name ignores the source name
- the person preview exposes the display name of an event's sources (`PersonsTest.php`)
- the description migration keeps existing rows with an empty description and rolls back cleanly (`SourceMigrationTest.php`)

Modified test:

- `sources index shows only the authenticated user own sources, newest first` (`SourcesTest.php:31`): its scoped `hasAll([...])` on `sources.0` fails on unexpected keys, so extend the list with `name`, `display_name`, `record_type`, `record_type_label`, `entry_number`, `year`, `provenance`, `url`, `note`. Extend the list rather than add `->etc()` — this test is the resource-shape contract.

#### 11. Baseline screenshots

**File**: `context/changes/source-description-in-modal/screenshots/shots.mjs` (new, copied from `context/archive/2026-09-29-scans-become-sources/screenshots/shots.mjs`)

**Intent**: Shoot the current source views from the seeded data before any frontend edit, so Phase 4 has a real comparison.

**Contract**: `node shots.mjs <out-dir> [before|after]`, light and dark × 1280×900 and 390×844, ids read from rendered lists by card text. The archived `before` flavour is `/scans`-specific (root at `:19`, the „skan" regex at `:107`, the needle `akt-urodzenia-jan` at `:118`) and fails if copied verbatim — rewrite it for `/sources`, with file names (`original_name`) as needles. The `before` flavour covers: the list, the upload page (empty, and with the kind error), the preview of an unlinked source, the preview of the linked akt, and the person modal's „Źródła" tab. Output goes to `screenshots/before/`.

### Success Criteria:

#### Automated Verification:

- Migration applies and rolls back cleanly: `vendor/bin/sail artisan migrate`, then `vendor/bin/sail artisan migrate:rollback --step=1`, then `vendor/bin/sail artisan migrate`
- Wayfinder regenerated and the update route exists: `vendor/bin/sail artisan wayfinder:generate` and `vendor/bin/sail artisan route:list --name=sources.update` lists one route
- Source, migration, event-source and person tests pass: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/SourceMigrationTest.php tests/Feature/EventSourcesTest.php tests/Feature/PersonsTest.php`
- PHP style: `vendor/bin/sail bin pint --dirty --format agent`
- Static analysis: `vendor/bin/sail composer run types:check`
- Frontend still compiles and lints: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`
- Demo seeder runs: `vendor/bin/sail artisan migrate:fresh && vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- Baseline screenshots produced: `node context/changes/source-description-in-modal/screenshots/shots.mjs context/changes/source-description-in-modal/screenshots/before before` exits 0 and writes one PNG per state × theme × width

#### Manual Verification:

- Uploading through the existing upload page still works and opens the preview of the new source
- The `before/` PNGs were opened and show the real list, upload page, preview and person-modal tab in both themes and both widths — not a login page or a blank frame

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Upload modal and the list

### Overview

The missing shared components land, the upload page becomes the „Dodaj źródło" modal, the list cards show the description, and every place that shows a source uses its display label.

### Changes Required:

#### 1. Textarea (missing shared component)

**File**: `resources/js/components/ui/textarea.tsx` (new)

**Intent**: Give the note a styled multi-line control from the design system already in the repo.

**Contract**: The stock shadcn `Textarea` for this repo's `components.json`, using the same tokens as `ui/input.tsx`. It adds a file, not an npm dependency.

#### 2. Types

**File**: `resources/js/types/source.ts`, `resources/js/types/person.ts`

**Intent**: Mirror the resource contract from Phase 1.

**Contract**: `Source` gains the nine fields of `SourceResource`; a `SourceRecordType` union is exported; the event source shape in `person.ts:33` gains `display_name`.

#### 3. Card meta line helper

**File**: `resources/js/lib/source-description.ts` (new), `resources/js/lib/source-description.test.ts` (new)

**Intent**: One tested place decides what a card shows under the name.

**Contract**: A pure function returning the „typ · rok" line (`urodzenia · 1878`, only `1878`, only `urodzenia`, or nothing) and whether the upload date is shown — it is shown only when typ, rok and pochodzenie are all empty.

#### 4. Kind field

**File**: `resources/js/components/sources/source-kind-field.tsx`

**Intent**: Let the surrounding form react to the chosen kind.

**Contract**: Adds an optional `onValueChange(kind)` prop. Existing props and the hidden `name="kind"` input are unchanged.

#### 5. Description field group (missing shared component)

**File**: `resources/js/components/sources/source-description-fields.tsx` (new)

**Intent**: The same description fields in both modals.

**Contract**: Props: the current kind, `recordTypes`, `errors`, optional defaults from a `Source`. Uncontrolled inputs with `name=`, each as `grid gap-2` + `Label` + control + `InputError`, in this order and with these labels:

- „Nazwa" — placeholder is the file name when known
- „Typ aktu" — `Select` with a „brak" sentinel mirrored into a hidden `record_type` input (akt only)
- „Numer wpisu" (akt only)
- „Rok" — text input, `inputMode="numeric"`
- „Pochodzenie" — hint „Parafia, archiwum, sygnatura"
- „Link" — text input, `inputMode="url"`, hint „Adres strony z aktem, np. w archiwum internetowym"
- „Notatka" — `Textarea`, hint „Kto, kiedy, co i jak zrobił"

The two akt-only fields stay mounted and hidden while another kind is selected.

#### 6. Upload modal

**File**: `resources/js/components/sources/source-create-dialog.tsx` (new)

**Intent**: Replace the upload page with a modal that needs only a file and a kind.

**Contract**: Takes `trigger`, `kinds`, `recordTypes`. `FormDialogContent` / `FormDialogHeader` (title „Dodaj źródło", description „Prześlij skan aktu, zdjęcie nagrobka lub inny dokument") / `FormDialogBody` / `FormDialogFooter`. `<Form {...SourceController.store.form()}>` wraps body and footer with `className="flex min-h-0 flex-1 flex-col"` (without it the body does not scroll and the footer is not pinned; precedent: `pick-or-create-person-dialog.tsx:201`, `person-create-dialog.tsx:128`) and `options={{ preserveScroll: true }}`, closes on success and scrolls the first error into view. Body order: file input with the „JPG, PNG lub WebP, do 20 MB" hint, the upload progress bar from `create.tsx:55-69` directly under the file input (so it is visible without scrolling when only a file and a kind are given), kind field, a small „Opis (opcjonalnie)" heading, the description group. Footer: „Anuluj" and „Zapisz", both disabled while `processing`.

#### 7. List page

**File**: `resources/js/pages/sources/index.tsx`

**Intent**: Open the modal from the list and make a source findable from its card.

**Contract**: The header button and the empty-state link become triggers of `SourceCreateDialog` (`kinds`, `recordTypes` from page props). A card shows: thumbnail, `display_name` (truncated, full text in `title`), kind badge, the meta line from the helper, pochodzenie on its own truncated muted line when set, and the upload date only when the helper says so.

#### 8. Remove the upload page and its route

**File**: `resources/js/pages/sources/create.tsx` (deleted), `app/Http/Controllers/SourceController.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`, `tests/Feature/SourcesTest.php`

**Intent**: One way to add a source, and an honest 404 on the old address.

**Contract**: `create` leaves the resource route and the controller; `Route::pattern('source', '[0-9]+')` joins the `person` pattern. The test „sources create shows the upload form" is replaced by „the old upload page returns not found", and the guest-redirect test drops the create URL. Regenerate Wayfinder.

#### 9. One display label everywhere

**File**: `resources/js/pages/sources/show.tsx`, `resources/js/components/sources/{source-picker-dialog,delete-source-dialog,unlink-source-dialog,source-thumbnail}.tsx`, `resources/js/components/persons/{sources-tab,overview-tab}.tsx`

**Intent**: A named source shows its name wherever it appears.

**Contract**: Every rendered `original_name` becomes `display_name` — titles, breadcrumb, `alt`, `title`, `aria-label`, dialog sentences. No layout change in these files in this phase.

#### 10. Picker empty state

**File**: `resources/js/components/sources/source-picker-dialog.tsx`

**Intent**: The link to the removed page needs a target.

**Contract**: The „Dodaj źródło" link at `:105` points to the „Źródła" list (`sourcesIndex()`). The `SourceController` import at `:4` is then unused — drop it; lint runs with `denyWarnings`.

### Success Criteria:

#### Automated Verification:

- Source, event-source and person tests pass, including the 404 for the old upload page: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/EventSourcesTest.php tests/Feature/PersonsTest.php`
- Wayfinder regenerated without the create route: `vendor/bin/sail artisan wayfinder:generate` and `vendor/bin/sail artisan route:list --name=sources.create` lists nothing
- JS unit tests pass: `vendor/bin/sail npm run test:js`
- Types, lint and format: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`
- Production build: `vendor/bin/sail npm run build`
- No source label read from the file name: `grep -rn "original_name" resources/js/pages resources/js/components` shows at most the „Nazwa" placeholder in `source-description-fields.tsx`
- PHP style and static analysis: `vendor/bin/sail bin pint --dirty --format agent` and `vendor/bin/sail composer run types:check`

#### Manual Verification:

- „Dodaj źródło" with only a file and a kind saves, shows „Dodano źródło" and opens the new source's preview
- „Typ aktu" and „Numer wpisu" appear only for akt metrykalny; typing them, switching to zdjęcie nagrobka and saving stores neither
- A validation error keeps the modal open with the typed description, the error is scrolled into view, and the progress bar shows while a large file uploads
- List cards: the described akt shows its name, badge, „urodzenia · 1878" and pochodzenie; an undescribed source shows its file name and upload date; long values truncate at 390 px without breaking the card
- The person modal shows source names; the empty „Powiąż akt" link leads to „Źródła"; `/sources/create` answers 404
- At 390 px the modal is full-screen with a scrolling body and a pinned footer; focus is visible on every control; light and dark both read correctly

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Edit modal and the preview

### Overview

The kind and description become editable in a modal with the erase confirmation, and the preview page shows the description next to the image.

### Changes Required:

#### 1. Erase rule as a pure function

**File**: `resources/js/lib/source-description.ts`, `resources/js/lib/source-description.test.ts`

**Intent**: One tested definition of when the confirmation appears and what it lists.

**Contract**: Given the stored source, the selected kind and the current form values of typ and numer wpisu (the caller reads them from the form ref's `getData()`), return the list of fields that would be erased (label and value). It is non-empty only when the stored kind is akt metrykalny, the selected kind is not, and typ or numer wpisu has a value — stored or typed. A stored value counts even when the field was cleared in the open modal. Tests cover: both empty → nothing; stored typ only; typed numer only; stored value cleared in the form → still listed; kind unchanged → nothing; source that was never an akt → nothing.

The same file exports the „undescribed" predicate for the preview panel: a source is undescribed only when all seven description columns (`name`, `record_type`, `entry_number`, `year`, `provenance`, `url`, `note`) are null. Rodzaj does not count. Tests cover: all seven null → undescribed; name only → described; note only → described.

#### 2. Edit modal

**File**: `resources/js/components/sources/source-edit-dialog.tsx` (new)

**Intent**: Correct kind and description without leaving the preview, and never erase typ or numer wpisu silently.

**Contract**: Takes `source`, `kinds`, `recordTypes`, `trigger`. Same shell as the upload modal (including the `flex min-h-0 flex-1 flex-col` class on the form), title „Edytuj źródło", `<Form {...SourceController.update.form(source.id)}>` with a ref, fields prefilled from `source`, no file input. While the erase list is non-empty, the akt-only fields are replaced in place by a `text-destructive` notice naming the fields that will be erased; the list is recomputed in `SourceKindField.onValueChange` from the kind passed to the callback and `formRef.current.getData()`. „Zapisz" stays `type="submit"`. `onBefore` rebuilds the erase list from `getData()`; when it is non-empty and the hidden `discard_record_fields` input is not `'1'`, it returns `false` and opens a nested plain `Dialog`, rendered as a sibling after `</Form>` — title „Zmienić rodzaj źródła?", the same list, „Anuluj" and a destructive „Zmień rodzaj i zapisz". Confirming writes `'1'` to the hidden input through a ref and calls `formRef.current.submit()`; `onFinish` resets it to `'0'` (see „Confirm-then-submit ordering"). With an empty erase list „Zapisz" submits directly. Success closes the modal; „Anuluj" and „Zapisz" are disabled while `processing`.

#### 3. Description panel

**File**: `resources/js/components/sources/source-details.tsx` (new)

**Intent**: The entry and the note are readable while looking at the image.

**Contract**: Takes `source` and an `emptyAction` slot (`ReactNode`). A definition list with rows only for filled fields: Rodzaj, Typ aktu, Numer wpisu, Rok, Pochodzenie, Link, Notatka. The note keeps its line breaks. The link renders as an anchor with `target="_blank"` and `rel="noopener noreferrer"`, showing the host name. A separate „Plik" group shows file name, upload date, size and dimensions. When the source is undescribed — the predicate from `source-description.ts`, all seven description columns null; a source with only a name is described — it shows „To źródło nie ma jeszcze opisu." followed by `emptyAction`. The panel does not import the edit modal.

#### 4. Preview page layout

**File**: `resources/js/pages/sources/show.tsx`

**Intent**: Put the description next to the image and give the page its edit action.

**Contract**: Heading is `display_name`; the description line under it is „rodzaj · typ · rok · nr wpisu" built from what is set (replacing date, size and dimensions, which move to the panel). The kind `Badge` at `show.tsx:46` is removed — the header line and the panel's Rodzaj row carry the kind. Actions: „Edytuj" (opens `SourceEditDialog`) and „Usuń". The page passes `SourceDetails` an `emptyAction`: a second `SourceEditDialog` whose trigger is the „Dodaj opis" button, with the same `source`, `kinds` and `recordTypes` from page props. From `lg` up, the viewer and the panel sit side by side with the panel in a fixed-width column; below `lg` the panel follows the viewer and precedes „Potwierdza".

### Success Criteria:

#### Automated Verification:

- Source tests pass: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php`
- JS unit tests pass, including the erase rule: `vendor/bin/sail npm run test:js`
- Types, lint and format: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`
- Production build: `vendor/bin/sail npm run build`

#### Manual Verification:

- „Edytuj" opens prefilled; saving shows „Zapisano źródło" and the preview updates in place
- An akt with typ and numer wpisu switched to zdjęcie nagrobka: the notice lists both fields, „Zapisz" and Enter in a field both ask for confirmation, „Anuluj" leaves everything unchanged with the buttons enabled, confirming saves and both fields are gone
- An akt with empty typ and numer wpisu switched to another kind saves with no confirmation
- A linked akt changed to zdjęcie nagrobka keeps its facts „Potwierdzone aktem" in the person modal and its „Potwierdza" list
- Wide screen: the panel is next to the image with numer wpisu and notatka; the link opens in a new tab; an undescribed source shows the empty state with „Dodaj opis"
- 390 px: the header line carries numer wpisu, the panel follows the image, „Edytuj" and „Usuń" fit beside the title
- Esc, X and „Anuluj" discard edits; the buttons are disabled while saving

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: Screenshot gate

### Overview

Every state is rendered from the kitchen-sink data, shot, compared with the Phase 1 baseline and judged in writing.

### Changes Required:

#### 1. After-flavour script

**File**: `context/changes/source-description-in-modal/screenshots/shots.mjs`

**Intent**: Shoot every new state the same way the baseline was shot.

**Contract**: The `after` flavour covers, in both themes and both widths:

- the list with described, undescribed and long-valued cards
- the upload modal: empty; akt selected with the akt fields; zdjęcie nagrobka selected; validation errors
- the edit modal: prefilled akt; kind switched with the erase notice; the confirmation dialog
- the preview: fully described akt; undescribed source; the linked akt
- the person modal's „Źródła" tab showing source names

It no longer visits `/sources/create`. Cards now show `display_name`, so the needles for the described seeds are their names, not file names. Output goes to `screenshots/after/`.

#### 2. Verdict

**File**: `context/changes/source-description-in-modal/screenshots/verdict.md` (new)

**Intent**: A written judgement per state instead of a folder of images.

**Contract**: A table with State, Shots, Expected, Seen; a before → after line for the list and the preview; a row per charge from Current State Analysis marked closed or open. No state is left „not inspected".

### Success Criteria:

#### Automated Verification:

- Demo seeder runs: `vendor/bin/sail artisan migrate:fresh && vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- Screenshots produced: `node context/changes/source-description-in-modal/screenshots/shots.mjs context/changes/source-description-in-modal/screenshots/after` exits 0 and writes one PNG per state × theme × width
- No stray „skan" copy: `grep -rn "[Ss]kan" resources/js/pages resources/js/components` shows only the modal description „Prześlij skan aktu, …"
- Full suite green: `vendor/bin/sail artisan test --compact`
- Types, lint, format and build: `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check` and `vendor/bin/sail npm run build`

#### Manual Verification:

- `verdict.md` reviewed: every state has Expected and Seen filled, the list and preview are compared with `before/`, and every charge is marked closed
- One real image uploaded through the modal and described: thumbnail, zoom and the description panel are judged next to an actual image, because seeded sources have no files

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/source-description.test.ts`: the card meta line (each combination of typ, rok, pochodzenie), the erase rule at its boundaries (both empty, stored only, typed only, stored-then-cleared, kind unchanged, never an akt), and the „undescribed" predicate (all seven null, name only, note only).

### Integration Tests:

- Pest feature tests in `tests/Feature/SourcesTest.php` for store with and without a description, update happy path, other-user 404, guest redirect, the erase rule with and without confirmation, linked-akt kind change, untouched file fields, display-name fallback, and the 404 on the old upload page.
- `tests/Feature/SourceMigrationTest.php` for the description migration up and down.
- `tests/Feature/PersonsTest.php` for the display name in the person preview.
- Upload tests keep the existing conventions: a real temp file for content sniffing, explicit MIME types for rejected formats.

### Manual Testing Steps:

1. Upload a gravestone photo with only file and kind; confirm it opens the preview and shows its file name on the list.
2. Edit it: add a name and a year; confirm the list card and the person modal show the name.
3. Upload an akt with typ, numer wpisu, pochodzenie, link and a two-paragraph note; confirm the preview panel and the card.
4. Change that akt to zdjęcie nagrobka: cancel the confirmation once, then confirm; switch back to akt and confirm both fields are empty.
5. Change the kind of an akt linked to a fact; confirm the fact stays confirmed.
6. Repeat steps 1 and 4 at 390 px and in dark mode.

## Performance Considerations

None beyond today. The list still loads all sources of the tree in one query; the new columns are short scalars plus one text column. No new queries on the list or preview.

## Migration Notes

- Schema-only and additive: seven nullable columns, no backfill, no file moves. Existing sources keep working and show their file name.
- Deploy with `scripts/deploy.sh` as usual; its automatic database backup before `migrate` covers this change. No maintenance mode and no full file backup are needed.
- Rollback is code-only: revert the merge on `main` and run `scripts/deploy.sh`. The seven nullable columns stay in place — the previous code never reads them, no description is lost, and a later redeploy finds the migration already recorded. `migrate:rollback` is not a rollback step: production prohibits it (`DB::prohibitDestructiveCommands` in `AppServiceProvider.php:39-41`).

## References

- Roadmap slice: `context/foundation/roadmap.md:334-345` (S-17)
- Requirements: `context/foundation/prd-v3.md:108-111` (FR-006, FR-007), `:132`; `context/foundation/prd-v2.md:91-100`, `:171-174`
- Predecessor: `context/archive/2026-09-29-scans-become-sources/plan.md`, `plan-brief.md`, `reviews/impl-review.md`
- Modal shell and form precedent: `resources/js/components/form-dialog.tsx:14-89`, `resources/js/components/tree/pick-or-create-person-dialog.tsx:196-244`
- Optional select with sentinel: `resources/js/components/persons/person-section-fields.tsx:117-135`
- Confirm dialog pattern: `resources/js/components/sources/delete-source-dialog.tsx:36-103`
- Shared validation trait pattern: `app/Concerns/PartialDateValidationRules.php:17-26`
- Route-removal pitfall: `context/archive/2026-09-28-person-form-in-modal/plan.md:55`
- Screenshot script to copy: `context/archive/2026-09-29-scans-become-sources/screenshots/shots.mjs`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract — the description in the data and the backend

#### Automated

- [x] 1.1 Migration applies and rolls back cleanly — 70d1e92
- [x] 1.2 Wayfinder regenerated and the update route exists — 70d1e92
- [x] 1.3 Source, migration, event-source and person tests pass — 70d1e92
- [x] 1.4 PHP style — 70d1e92
- [x] 1.5 Static analysis — 70d1e92
- [x] 1.6 Frontend still compiles and lints — 70d1e92
- [x] 1.7 Demo seeder runs — 70d1e92
- [x] 1.8 Baseline screenshots produced — 70d1e92

#### Manual

- [x] 1.9 Uploading through the existing upload page still works and opens the preview of the new source — 70d1e92
- [x] 1.10 The `before/` PNGs were opened and show the real list, upload page, preview and person-modal tab in both themes and both widths — 70d1e92

### Phase 2: Upload modal and the list

#### Automated

- [x] 2.1 Source, event-source and person tests pass, including the 404 for the old upload page — 23f6370
- [x] 2.2 Wayfinder regenerated without the create route — 23f6370
- [x] 2.3 JS unit tests pass — 23f6370
- [x] 2.4 Types, lint and format — 23f6370
- [x] 2.5 Production build — 23f6370
- [x] 2.6 No source label read from the file name — 23f6370
- [x] 2.7 PHP style and static analysis — 23f6370

#### Manual

- [x] 2.8 „Dodaj źródło" with only a file and a kind saves, shows „Dodano źródło" and opens the new source's preview — 23f6370
- [x] 2.9 „Typ aktu" and „Numer wpisu" appear only for akt metrykalny; typing them, switching to zdjęcie nagrobka and saving stores neither — 23f6370
- [x] 2.10 A validation error keeps the modal open with the typed description, the error is scrolled into view, and the progress bar shows while a large file uploads — 23f6370
- [x] 2.11 List cards show name, badge, „typ · rok" and pochodzenie when set, the file name and upload date when not, and truncate long values at 390 px — 23f6370
- [x] 2.12 The person modal shows source names; the empty „Powiąż akt" link leads to „Źródła"; `/sources/create` answers 404 — 23f6370
- [x] 2.13 At 390 px the modal is full-screen with a scrolling body and a pinned footer; focus is visible; light and dark both read correctly — 23f6370

### Phase 3: Edit modal and the preview

#### Automated

- [x] 3.1 Source tests pass
- [x] 3.2 JS unit tests pass, including the erase rule
- [x] 3.3 Types, lint and format
- [x] 3.4 Production build

#### Manual

- [x] 3.5 „Edytuj" opens prefilled; saving shows „Zapisano źródło" and the preview updates in place
- [x] 3.6 An akt with typ and numer wpisu switched to zdjęcie nagrobka lists both fields, asks for confirmation, cancels cleanly and erases both on confirm
- [x] 3.7 An akt with empty typ and numer wpisu switched to another kind saves with no confirmation
- [x] 3.8 A linked akt changed to zdjęcie nagrobka keeps its facts „Potwierdzone aktem" and its „Potwierdza" list
- [x] 3.9 Wide screen: the panel is next to the image with numer wpisu and notatka; the link opens in a new tab; an undescribed source shows the empty state with „Dodaj opis"
- [x] 3.10 390 px: the header line carries numer wpisu, the panel follows the image, „Edytuj" and „Usuń" fit beside the title
- [x] 3.11 Esc, X and „Anuluj" discard edits; the buttons are disabled while saving

### Phase 4: Screenshot gate

#### Automated

- [ ] 4.1 Demo seeder runs
- [ ] 4.2 Screenshots produced
- [ ] 4.3 No stray „skan" copy
- [ ] 4.4 Full suite green
- [ ] 4.5 Types, lint, format and build

#### Manual

- [ ] 4.6 `verdict.md` reviewed: every state has Expected and Seen filled, the list and preview are compared with `before/`, and every charge is marked closed
- [ ] 4.7 One real image uploaded through the modal and described: thumbnail, zoom and the description panel are judged next to an actual image
