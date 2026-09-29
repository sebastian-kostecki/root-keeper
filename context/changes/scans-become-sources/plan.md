# Scans Become Sources Implementation Plan

## Overview

Roadmap S-16 (prd-v3 FR-005, FR-009, FR-010, FR-011). „Skany” become „Źródła”. Every source has a kind: akt metrykalny, zdjęcie nagrobka or inne. All existing scans become akt metrykalny.

The rename goes all the way down: tables, model, routes, components and files on disk (`scans/` → `sources/`). Files, thumbnails, zoom, and every fact's „potwierdzone/niepotwierdzone” state carry over 1:1.

Two user-visible changes:

- The upload page requires a kind.
- Deleting a source with no links is a short „Usunąć źródło?”. A linked source still shows the facts that will lose confirmation.

Until S-18, only akt metrykalny can be linked to a fact.

This is a UI change with a design-system contract. Semantic tokens stay in `resources/css/app.css`, and components come from `resources/js/components/ui/`. **Missing tokens: none.** The kind is shown with the existing `Badge` (`variant="secondary"`) and chosen with the existing `ToggleGroup`. **Missing shared component:** the kind field, extracted so S-17 can move it into the modal. **Accidental architecture removed:** the words `scan`/`skan` in code, the DB and the storage prefix.

## Current State Analysis

- **Schema.**
  - `scans` has `id`, `tree_id` (FK, cascade), `original_name`, `path`, `thumbnail_path?`, `mime_type`, `size`, `width?`, `height?` and timestamps, with an index on (`tree_id`,`created_at`). See `database/migrations/2026_09_24_043434_create_scans_table.php:14-26`.
  - `event_scan` has `id`, `event_id` and `scan_id` (both FK, cascade), timestamps, `unique(event_id, scan_id)` and `index(scan_id)`. See `database/migrations/2026_09_24_122757_create_event_scan_table.php:14-21`.
  - No later migration touches either table.
- **„Potwierdzone” is never stored.** It is derived as "the event has ≥1 pivot row" in:
  - `app/Http/Resources/EventResource.php:46-51`
  - `app/Http/Resources/PersonFormResource.php:67`
  - `app/Http/Controllers/PersonController.php:39` (`withExists('scans')`)
  - `app/Http/Controllers/ScanController.php:63-78` (`is_last_scan`)
  - So if the pivot rows survive, confirmation survives.
- **Files.**
  - `app/Actions/Scans/StoreScan.php:29-43` writes `scans/{tree_id}/{uuid}.{ext}` and `scans/{tree_id}/thumbnails/{uuid}.jpg` on the `local` disk (`storage/app/private`).
  - The full paths are stored on the row, and serving reads them verbatim (`ScanController.php:99-125`).
  - The prefix is also hard-coded in `app/Http/Controllers/Settings/ProfileController.php:60` (account deletion), `database/factories/ScanFactory.php:25,40` and `tests/Feature/ScansTest.php:164,330-355`.
- **Routes** (`routes/web.php:24-29`): `scans.{index,create,store,show,destroy,file,thumbnail}`, `events.scans.{store,destroy}`.
- **Linking.** `EventScanStoreRequest.php:29` only checks `exists:scans,id` within the user's tree. Any scan can be linked, and today every scan is an akt.
- **Deleting.** `resources/js/components/scans/delete-scan-dialog.tsx` is a single dialog. When `confirms` is empty it only adds the line „Ten skan nie potwierdza żadnego wydarzenia.” (:47-50). There is no short variant.
- **Uploading.** `resources/js/pages/scans/create.tsx` uses `<Form {...ScanController.store.form()}>` with a `scan` file input and a progress bar. There is no radio-group in `components/ui/`. The established choice-in-`<Form>` pattern is a `ToggleGroup` plus a hidden input (`resources/js/components/persons/person-section-fields.tsx:85-111`).
- **Frontend consumers.** In `components/persons/`:
  - `sources-tab.tsx`, `overview-tab.tsx`, `person-modal.tsx`, `person-preview-dialog.tsx` — use `availableScans` / `ScanPickerDialog` / `ScanThumbnail`.
  - `person-section-fields.tsx` — `CONFIRMED_HINT`.
  - `delete-person-dialog.tsx`, `remove-marriage-dialog.tsx` — link counts and copy.
  - Elsewhere: `app-sidebar.tsx` („Skany”), `types/{scan,person,index,global.d}.ts`.
  - The „Źródła” tab in the person modal already uses the word.
- **Translations.** The flash messages („Dodano skan”, „Usunięto skan”, „Powiązano skan”, „Odpięto skan”) and `PersonUpdateRequest.php:41` are inline `__()` literals. `lang/pl.json` has no scan keys.
- **Deploy.** `scripts/deploy.sh` backs up **only the DB** before `migrate --force` (`scripts/lib/production-deploy.sh:81-91`). The nightly Spatie backup covers `storage/app` (`config/backup.php:29-36`).

## Desired End State

- The sidebar says „Źródła” → `/sources`. The list shows every existing file as a source with a „akt metrykalny” badge, and the thumbnails load.
- `/sources/create` requires a kind (akt / nagrobek / inne) in addition to the file. The new source lands on its preview page with the kind in the heading.
- The preview page zooms exactly as before, lists what the source confirms, and offers delete:
  - no links → short „Usunąć źródło?” (Anuluj / Usuń);
  - links → the impact list as today, reworded to „źródło”.
- The fact picker (person modal: overview and „Źródła” tab) lists only akt metrykalny. The server rejects linking any other kind.
- Every event confirmed before the deploy is confirmed after it, and vice versa. Every file and thumbnail sits under `sources/{tree}/…`, and no `scans/` directory is left on disk.
- `/scans` returns 404. There are no redirects (prd-v3 Non-Goals).

### Key Discoveries:

- A table rename keeps the pivot rows and their ids, so the confirmation state is preserved without a data copy (`EventResource.php:46-51`).
- Serving reads `path` / `thumbnail_path` verbatim (`ScanController.php:99-125`). Moving files needs only the file move plus a row update per source. Nothing else computes paths.
- Migrations on PostgreSQL run inside a transaction by default (`Migration::$withinTransaction`, `vendor/laravel/framework/src/Illuminate/Database/Migrations/Migration.php:19`). File moves are not transactional, so they need their own migration.
- Enums follow `app/Enums/Gender.php` / `Religion.php`: string-backed, `label()` in Polish, static `options()` returning `[{value,label}]`.
- The Wayfinder output (`resources/js/{actions,routes,wayfinder}`) is gitignored and regenerated. Old `ScanController.ts` files linger locally until deleted.
- The kitchen sink is `database/seeders/TreeViewDemoSeeder.php` (`attachScan()` at :232-236, used at :146, :147, :156, :185). It is created with factories and writes no real files.

## What We're NOT Doing

- No upload modal and no description fields (name, year, origin, note, akt type, entry number). No kind editing. All of that is S-17 (`source-description-in-modal`).
- No linking of gravestone/other sources to facts, and no kind shown next to a fact. The wording „Potwierdzone aktem” stays. That is S-18 (`fact-confirmed-by-any-source`).
- No matching-type ordering in the picker (S-19).
- No redirects from `/scans/*` (prd-v3 Non-Goals, FR-010).
- No multi-file upload (prd-v3 Non-Goals).
- No renaming of PostgreSQL sequence, index or FK constraint names (e.g. `scans_id_seq`, `event_scan_event_id_scan_id_unique`). They are invisible and harmless.
- No delete from the list cards. Delete stays on the preview page, as decided in S-08.
- No change to the file types or size limit (JPG/PNG/WebP, 20 MB), and no change to thumbnail generation.
- No screenshot test wired into CI. The repo has none, so the gate is the per-change `shots.mjs` script.

## Implementation Approach

Contract before pixels. Phase 1 changes the data and the whole backend in one commit, because the table rename forces the model, routes and generated Wayfinder files to change together. The frontend gets only the mechanical import/type rename in that phase, so `types:check` and `check` stay green. Phase 2 adds the user-visible behaviour: kind choice, kind badge, akt-only picker copy, and the short delete. Phase 3 renders every state in the kitchen sink, screenshots it against a `before/` baseline taken from `main`, and writes the deploy runbook steps (full backup, confirmed-count check).

## Critical Implementation Details

- **Two migrations, in this order.**
  1. **Schema** (transactional). Rename `scans` → `sources` and `event_scan` → `event_source`, and `scan_id` → `source_id`. Add `kind` (string, not null), backfill every existing row to `metrical_record`, and leave no DB default. `down()` reverses all of it.
  2. **File move** (`public $withinTransaction = false;`). Uses only `DB::table('sources')` and `Storage::disk('local')`, never models or Actions, so later code changes cannot break it.
- **The file move goes row by row and is idempotent.** For each row whose `path` or `thumbnail_path` starts with `scans/`, resolve each of the two columns **independently** (a `null` thumbnail is skipped):
  1. Old file exists → `Storage::move()` it to the same relative path under `sources/`. The `local` disk is `'throw' => false`, so check the return value: `false` → throw and stop the migration (the row is not updated).
  2. Old file missing, new file present (a re-run after a crash) → adopt the new path.
  3. Both old and new missing → keep the old path and write one line to the migration output (row id + column).
  4. Update the row once with whatever each column resolved to. A row can end with a moved original and a reported, still-`scans/` thumbnail.

  After the loop, sweep orphans: move every file still under `scans/` (referenced by no row, e.g. left by a failed `DeleteScan`) to the same path under `sources/`, print their count, then delete `scans/` entirely. `down()` does the reverse (`sources/` → `scans/`) with the same rules. A crash mid-run therefore leaves each row consistent with its own files, and a re-run finishes the job.
- **Deploy user.** `scripts/deploy.sh` runs `migrate` via `docker compose exec` with no `-u`, and `Dockerfile.prod` has no `USER`, so it runs as root. The `local` disk creates directories `0700` (Flysystem `Visibility::PRIVATE`), so a root-run move would leave `sources/{tree}/` as `root:root 0700`, unreadable and unwritable for PHP (`sail`). The `chown` step runs *before* migrate. Therefore `deploy.sh` must run migrate as `sail` (Phase 3). Local Sail already runs artisan as `sail`, so no local test can catch this.
- **Backup.** `deploy.sh` backs up only the DB, and this change moves files. A full `./scripts/artisan.sh backup:run` is a manual pre-deploy step (Phase 3).

## Phase 1: Contract — sources in the data and the backend

### Overview

The data model, migrations, backend and generated routes speak „source” and carry `kind`. The frontend compiles against the renamed routes and types with no visible change beyond copy that the rename forces. The tests pin the migration guarantees.

### Changes Required:

#### 1. Source kind enum

**File**: `app/Enums/SourceKind.php` (new, `vendor/bin/sail artisan make:enum`)

**Intent**: One source of truth for the three kinds and their Polish labels, shared with the frontend through `options()`.

**Contract**: string-backed cases `MetricalRecord = 'metrical_record'`, `Gravestone = 'gravestone'`, `Other = 'other'`. `label()` returns „akt metrykalny” / „zdjęcie nagrobka” / „inne”. Static `options(): array<int, array{value: string, label: string}>` follows `Gender::options()`.

#### 2. Schema migration

**File**: `database/migrations/2026_09_29_*_rename_scans_to_sources.php` (new)

**Intent**: Rename the tables and pivot column, and add the kind, without touching any pivot row.

**Contract**:
- Tables: `scans` → `sources`, `event_scan` → `event_source`.
- Pivot column: `event_source.scan_id` → `source_id`.
- `sources.kind` is a string, not null, and existing rows are backfilled to `metrical_record`. Order: add the column with `->default('metrical_record')` (Postgres rejects a NOT NULL add without a default on a non-empty table), then drop the default in the same migration (`->default(null)->change()` or a raw `ALTER COLUMN kind DROP DEFAULT`).
- `down()` restores the exact previous schema.
- Anonymous class with docblocks on `up()`/`down()` (repo style).

#### 3. File-move migration

**File**: `database/migrations/2026_09_29_*_move_source_files_to_sources_prefix.php` (new, timestamp after #2)

**Intent**: Move every original and thumbnail from `scans/{tree}/…` to `sources/{tree}/…` and point the rows at the new paths, safely and resumably (see Critical Implementation Details).

**Contract**:
- `public $withinTransaction = false`.
- Uses `DB::table('sources')` + `Storage::disk('local')` only.
- Post-condition: no row's `path`/`thumbnail_path` starts with `scans/` unless that column's old and new files are both missing (reported).
- A `false` from `Storage::move()` throws; the row is never repointed at a file that did not move.
- Orphan files under `scans/` are moved to `sources/` (count printed) and `scans/` is deleted.
- `down()` is the mirror image.

#### 4. Model, factory, relations

**Files**:
- `app/Models/Scan.php` → `app/Models/Source.php`
- `database/factories/ScanFactory.php` → `SourceFactory.php`
- `app/Models/Event.php`, `app/Models/Tree.php`

**Intent**: Rename the model and relations, and cast `kind` to the enum.

**Contract**:
- `Source` has `kind` in `#[Fillable]` and cast to `SourceKind`.
- `Source::events()`, `Event::sources()` and `Tree::sources()` use the `event_source` pivot (`source_id`), with the same ordering as today (pivot `created_at`, then `id`).
- Factory defaults: `kind = MetricalRecord`, `path = "sources/{tree_id}/{uuid}.jpg"`.
- Factory states: existing `withThumbnail()` (path under `sources/…/thumbnails/`), plus new `gravestone()` and `other()`.

#### 5. Actions

**Files**:
- `app/Actions/Scans/*` → `app/Actions/Sources/`: `StoreSource`, `DeleteSource`, `GenerateSourceThumbnail`, `ReadExifOrientation` (moved)
- Docblocks in `app/Actions/Persons/{BuildPersonPreview,DeletePerson,UpdatePerson}.php`, `app/Actions/Relations/UpdateMarriage.php`

**Intent**: Store new sources under `sources/{tree}/` with their kind, and keep the delete semantics (row first, then files, missing files tolerated).

**Contract**:
- `StoreSource::handle(Tree $tree, UploadedFile $file, SourceKind $kind): Source` writes `sources/{tree}/{uuid}.{ext}` and `sources/{tree}/thumbnails/{uuid}.jpg`.
- The fallback original name is `zrodlo.{ext}`.
- `BuildPersonPreview` eager-loads `events.sources` / `marriages.sources`.

#### 6. HTTP layer

**Files**:
- `ScanController` → `SourceController`, `EventScanController` → `EventSourceController`
- `ScanStoreRequest` → `SourceStoreRequest`, `EventScanStoreRequest` → `EventSourceStoreRequest`
- `ScanResource` → `SourceResource`, `ScanPolicy` → `SourcePolicy`
- `EventResource`, `MarriageResource`, `PersonFormResource`, `PersonController`, `PersonUpdateRequest`, `HandleInertiaRequests`, `Settings/ProfileController`
- `routes/web.php`

**Intent**: Rename the resource end to end, require the kind on upload, and allow only akt metrykalny to be linked to an event.

**Contract**:
- **Routes:** `Route::resource('sources', SourceController::class)->only([...same...])`, `sources.file`, `sources.thumbnail`, `events.sources.store` (POST `events/{event}/sources`), `events.sources.destroy` (DELETE `events/{event}/sources/{source}`). No `/scans` routes remain.
- **Inertia pages:** `sources/index`, `sources/create`, `sources/show`.
- **`show` props:** `source`, `confirms`, where each row has `is_last_source` (was `is_last_scan`).
- **`create` props:** `kinds` = `SourceKind::options()`.
- **`SourceStoreRequest`:**
  - `file` field (renamed from `scan`), with the same image/size rules.
  - `kind` is required and uses `Rule::enum(SourceKind::class)`.
  - Polish attribute names: „plik” and „rodzaj”.
- **`EventSourceStoreRequest`:**
  - `source_id` must exist in `sources` for the user's tree (the same tree-scoped `Rule::exists` as today, with its message unchanged).
  - A **separate** rule (closure or `Rule::exists(...)->where('kind', …)` with its own message key) requires `kind = metrical_record`, with the Polish message „Do wydarzenia można na razie powiązać tylko akt metrykalny.”
  - Another tree's id never produces the akt message, and a gravestone never produces the generic one. Tests assert both.
- **`SourceResource`:** adds `kind` (the value) and `kind_label`.
- **`EventResource`:** exposes `sources` (same shape as today's `scans`, plus `kind`) and `is_confirmed` from `sources` / `sources_exists`.
- **`availableSources` shared prop** (renamed): only akt metrykalny sources, newest first.
- **Flash messages:** „Dodano źródło”, „Usunięto źródło”, „Powiązano akt”, „Odpięto źródło”.
- **`PersonUpdateRequest`:** „Najpierw odepnij źródła od zgonu na podglądzie osoby.”
- **`ProfileController::destroy`:** deletes `sources/{treeId}`.
- **Download fallback name:** `zrodlo-{id}.{ext}`.

#### 7. Generated routes and mechanical frontend rename

**Files**:
- `resources/js/pages/scans/*` → `resources/js/pages/sources/*`
- `resources/js/components/scans/*` → `resources/js/components/sources/*` (`source-thumbnail`, `source-viewer`, `source-picker-dialog`, `delete-source-dialog`, `unlink-source-dialog`)
- `resources/js/types/scan.ts` → `source.ts`, `resources/js/types/{person,index,global.d}.ts`
- `components/persons/{sources-tab,overview-tab,person-modal,person-preview-dialog}.tsx`
- `components/persons/person-list-parts.tsx:4`, `components/persons/section-editor.tsx:209` (doc comments only)
- `components/app-sidebar.tsx`

**Intent**: Regenerate Wayfinder, delete the stale local `ScanController.ts` / `routes/scans` output, and rename imports, props and types so the app compiles against the new contract. Behaviour and layout stay the same in this phase. The upload form sends `file`, and the page sends a hidden `kind=metrical_record` until Phase 2 adds the choice, so upload keeps working between phases.

**Contract**:
- Types:
  - `Source` = today's `Scan` plus `kind: SourceKind`, `kind_label: string`.
  - `SourceKind = 'metrical_record' | 'gravestone' | 'other'`.
  - `SourceConfirmation.is_last_source`.
  - `LinkedSource` (+ `kind`).
  - `SourcedPersonEvent.sources`.
  - `availableSources?: Source[]`.
- Sidebar item: `{ title: 'Źródła', href: sourcesIndex() }`, same icon.

#### 8. Tests

**Files**:
- `tests/Feature/ScansTest.php` → `SourcesTest.php`
- `EventScansTest.php` → `EventSourcesTest.php`
- `GenerateScanThumbnailTest.php` → `GenerateSourceThumbnailTest.php`
- `MarriagesTest.php`, `PersonsTest.php`
- New `tests/Feature/SourceMigrationTest.php`

**Intent**: Keep every existing guarantee under the new names, and pin the new ones.

**Contract**: the renamed tests assert `sources`/`event_source` tables, `sources/{tree}/…` paths, and the `sources.*` routes and components. New tests:
- Store without `kind` fails (Polish error). Store with `gravestone` persists the kind.
- The index/show props carry `kind`.
- Linking a `gravestone` source to an event fails with the akt message. Linking another tree's source fails with the generic message. Linking a `metrical_record` source succeeds.
- `availableSources` excludes non-akt sources.
- `/scans` returns 404.
- File-move migration, run by requiring the migration file and calling `up()` on rows seeded with `scans/…` paths under `Storage::fake('local')`:
  - files and thumbnails end up under `sources/…` and the rows point there;
  - a second `up()` is a no-op;
  - a row whose file is already under `sources/` only gets its path updated;
  - a row with both files missing is left untouched;
  - mixed row: original present, thumbnail missing everywhere → `path` moves, `thumbnail_path` stays `scans/…`;
  - an unreferenced orphan file under `scans/` ends up under `sources/`, and `scans/` no longer exists;
  - `down()` restores `scans/…`.
- Confirmation carry-over: events with pivot rows keep `is_confirmed = true`, and events without keep `false` (asserted through the person preview props).

### Success Criteria:

#### Automated Verification:

- Migrations apply and roll back cleanly: `vendor/bin/sail artisan migrate:fresh` then `vendor/bin/sail artisan migrate:rollback --step=2` then `vendor/bin/sail artisan migrate`
- Wayfinder regenerated with no `/scans` routes: `vendor/bin/sail artisan wayfinder:generate` and `vendor/bin/sail artisan route:list --path=scans` lists nothing
- Source, event-source, migration, marriage and person tests pass: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/EventSourcesTest.php tests/Feature/SourceMigrationTest.php tests/Feature/GenerateSourceThumbnailTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonsTest.php`
- No `scan` identifiers left in app code: `grep -rniE "scan" app routes database/factories database/migrations/2026_09_29_* resources/js --exclude-dir=actions --exclude-dir=routes --exclude-dir=wayfinder` shows only the migration docblocks and the two-factor QR code false positives
- PHP style: `vendor/bin/sail bin pint --dirty --format agent`
- Static analysis: `vendor/bin/sail composer run types:check` (Larastan level 7, `phpstan analyse`)
- Types and lint: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`

#### Manual Verification:

- On `main`, upload 3+ real images through `/scans/create` (link one to an event) and run the demo seeder, **then** switch branch and migrate. The uploaded files show under `/sources` with working thumbnails. The migration output reports exactly the seeded rows as missing (the factory writes no files). `storage/app/private/scans/` no longer exists.
- Uploading a file on `/sources/create` still works (hidden kind = akt) and opens its preview with zoom

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: The view — kind, akt-only picker and the short delete

### Overview

The user sees and chooses the kind, the picker speaks of akts, and deleting an unlinked source is a short confirmation. All remaining „skan” copy becomes „źródło”, except where the text truly means an akt.

### Changes Required:

#### 1. Kind field (shared for S-17)

**File**: `resources/js/components/sources/source-kind-field.tsx` (new)

**Intent**: A labelled, required kind choice usable inside a native Inertia `<Form>`, so S-17 can drop it into the upload modal unchanged.

**Contract**:
- Props: `{ kinds: {value: SourceKind; label: string}[]; defaultValue?: SourceKind; error?: string }`.
- Renders the `ToggleGroup` (type single, three items) plus `<input type="hidden" name="kind">` and `InputError`, following `person-section-fields.tsx:85-111`.
- No preselected value on create. An empty submission shows the server error „Pole rodzaj jest wymagane.” (or the repo's Polish equivalent).

#### 2. Upload page

**File**: `resources/js/pages/sources/create.tsx`

**Intent**: Replace the hidden `kind` from Phase 1 with the kind field, placed above the file input, and reword the copy.

**Contract**:
- Heading „Dodaj źródło”, description „Prześlij skan aktu, zdjęcie nagrobka lub inny dokument”.
- File label „Plik”.
- Breadcrumbs „Źródła” › „Dodaj źródło”.
- Receives the `kinds` prop.

#### 3. List and preview

**Files**: `resources/js/pages/sources/index.tsx`, `resources/js/pages/sources/show.tsx`

**Intent**: Show the kind wherever a source is identified, and reword the copy.

**Contract**:
- **List:**
  - Each card shows `Badge variant="secondary"` with `kind_label` under the file name.
  - Heading „Źródła” / „Akty, zdjęcia nagrobków i inne dokumenty Twojego drzewa”.
  - Button „Dodaj źródło”.
  - Empty state „Nie masz jeszcze żadnych źródeł.” / „Dodaj pierwsze źródło”.
- **Preview:**
  - The kind badge sits next to the heading.
  - „Potwierdza” card with the empty text „To źródło nie potwierdza jeszcze żadnego wydarzenia.”

#### 4. Delete dialog: two variants

**File**: `resources/js/components/sources/delete-source-dialog.tsx`

**Intent**: FR-011. With no links, show a short confirmation. With links, keep today's impact list.

**Contract**:
- **`confirms.length === 0`:**
  - Title „Usunąć źródło?”, description „Źródło „{original_name}” i jego plik zostaną trwale usunięte.”
  - Buttons „Anuluj” / destructive „Usuń”.
  - No list, no extra paragraph.
- **Otherwise:**
  - Title „Usuń źródło” and today's description, reworded to „źródło”.
  - List header „Te fakty stracą to źródło:”.
  - Row suffix „stanie się niepotwierdzone” (when `is_last_source`) or „pozostanie potwierdzone innym źródłem”.
  - „Tej operacji nie można cofnąć.”
- Same `router.delete(SourceController.destroy(...))` call in both variants.

#### 5. Picker, unlink and fact copy

**Files**:
- `components/sources/{source-picker-dialog,unlink-source-dialog}.tsx`
- `components/persons/{sources-tab,overview-tab,person-section-fields,delete-person-dialog,remove-marriage-dialog}.tsx`

**Intent**: Before S-18, the picker offers only akts, so its copy says „akt”. Generic places say „źródło”.

**Contract**:
- **Picker:**
  - Title „Powiąż akt”, description „Wybierz akt metrykalny, który potwierdza to wydarzenie.”
  - Empty state „Nie masz jeszcze żadnych aktów metrykalnych.”, with the link „Dodaj źródło” → `SourceController.create()`.
  - Load error „Nie udało się wczytać źródeł. Zamknij okno i spróbuj ponownie.”
- **Sources tab and overview:**
  - Buttons „Powiąż akt” / „Powiąż kolejny akt”.
  - Missing-akt line „Brak aktu. Powiąż akt {eventGenitive}, żeby potwierdzić zdarzenie.”
  - `aria-label` „Otwórz źródło …” / „Odepnij źródło …”.
  - Overview count „{n} źródło/źródła/źródeł · {name}”.
- **Unlink dialog:** „Odepnij źródło”, with the question reworded accordingly.
- **`CONFIRMED_HINT`:** „Potwierdzone aktem — upewnij się, że zmiana zgadza się z aktem.”
- **Delete-person and remove-marriage dialogs:** counts „powiązań ze źródłami”, „Źródła pozostaną w bibliotece źródeł.”, „— źródła pozostaną”.
- „Potwierdzone aktem” / „Bez źródła” in `event-source-status.tsx` stay unchanged (still true under the akt-only rule).

### Success Criteria:

#### Automated Verification:

- Feature tests still pass: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/EventSourcesTest.php tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php`
- Types, lint and format: `vendor/bin/sail npm run types:check` and `vendor/bin/sail npm run check`
- Production build: `vendor/bin/sail npm run build`
- No user-visible „skan” left outside intentional „skan aktu”: `grep -rn "[Ss]kan" resources/js/pages resources/js/components` shows only the upload description „Prześlij skan aktu, …”

#### Manual Verification:

- Uploading without choosing a kind shows the Polish error next to the kind field, and the chosen file must be picked again (browser behaviour); uploading a gravestone photo shows the „zdjęcie nagrobka” badge on the list and preview
- Deleting an unlinked source shows the short „Usunąć źródło?” and lands on `/sources` with „Usunięto źródło”
- Deleting a linked source shows the impact list with „stanie się niepotwierdzone” / „pozostanie potwierdzone innym źródłem”
- The picker in the person modal (overview and „Źródła” tab) lists only akts, and the gravestone source is absent
- Light and dark mode, desktop and 390 px wide, keyboard focus visible on the kind toggle

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Kitchen sink, screenshot gate and deploy

### Overview

Every state is rendered from the demo seeder and screenshotted against a baseline from `main`. The deploy checks that confirmation and files survived are written down and run on real-shaped data.

### Changes Required:

#### 1. Kitchen sink data

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Render every source state (append-only, as in earlier changes).

**Contract**:
- `attachScan()` → `attachSource()`; the existing four linked akts stay.
- Add one unlinked `gravestone()` source and one unlinked `other()` source (short delete variant, kind badges).
- Add one akt linked to two events (impact list with „pozostanie potwierdzone innym źródłem” next to „stanie się niepotwierdzone”).
- Update the docblock (:24-26).

#### 2. Screenshot script and verdict

**Files**:
- `context/changes/scans-become-sources/screenshots/shots.mjs` (new, out of the app, Playwright from the npx cache, as in `context/archive/2026-09-29-person-modal/screenshots/shots.mjs`)
- `screenshots/{before,after}/`
- `screenshots/verdict.md`

**Intent**: Visual gate over every state touched by this change.

**Contract**:
- `before/` is shot on `main` (`/scans`, `/scans/create`, `/scans/{id}`, delete dialog, picker).
- `after/` is shot on this branch. It covers:
  - list with three kinds;
  - upload with the kind error;
  - preview of a gravestone;
  - short delete dialog;
  - linked delete dialog with both row suffixes;
  - akt-only picker;
  - person-modal „Źródła” tab.
- Every state is shot in light and dark, at 1280 px and 390 px.
- `verdict.md` has one line per state: expected vs seen. Baselines are never blind-updated.

#### 3. Migrations run as the app user

**File**: `scripts/deploy.sh`

**Intent**: The file move must create `sources/…` directories that PHP can read and write (see Critical Implementation Details, "Deploy user").

**Contract**: the migrate line becomes `$COMPOSE exec -T -u sail app php artisan migrate --force`. Nothing else in the script changes.

#### 4. Deploy runbook steps

**File**: `context/changes/scans-become-sources/change.md` (Notes section)

**Intent**: Record the pre-deploy and post-deploy steps that the automated deploy does not cover.

**Contract**: a short ordered list.
1. Full backup `./scripts/artisan.sh backup:run` (files and DB), then `./scripts/artisan.sh backup:list`.
2. Record the confirmed-event count before the deploy (SQL below).
3. `scripts/deploy.sh`.
4. The same count after the deploy must be equal. `sources` must have no row whose `path` starts with `scans/`, and the migration output must name no missing file.
5. `ls -ld storage/app/private/sources storage/app/private/sources/*` shows `sail`-owned directories. Open an old source's thumbnail and full view, then upload a test source, open it, and delete it.

The count queries (before: `event_scan`/`scan_id`; after: `event_source`/`source_id`):
```sql
-- before deploy
select count(distinct event_id) from event_scan;
-- after deploy
select count(distinct event_id) from event_source;
select count(*) from sources where path like 'scans/%' or thumbnail_path like 'scans/%';
```

### Success Criteria:

#### Automated Verification:

- Demo seeder runs: `vendor/bin/sail artisan migrate:fresh && vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder`
- Screenshots produced: `node context/changes/scans-become-sources/screenshots/shots.mjs context/changes/scans-become-sources/screenshots/after` exits 0 and writes one PNG per state × theme × width
- Full suite green: `vendor/bin/sail artisan test --compact`

#### Manual Verification:

- `verdict.md` reviewed: every state matches its expected description, with no before→after regression in thumbnails, zoom or layout
- Rehearsal on real-shaped data (on `main`: upload 3+ real images, link one, then seed; record the confirmed-event count; switch branch, `migrate`, count again): equal counts, the only `scans/%` rows are the seeded, file-less ones the migration reported, and the uploaded files open in the viewer
- Deploy runbook in `change.md` followed on production: backup listed, counts equal, `sources/` directories owned by `sail`, `/sources` shows every file, a test upload opens and deletes

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None beyond `GenerateSourceThumbnailTest` (renamed). The enum is covered through the feature tests.

### Integration Tests:

- Pest feature tests for:
  - store with/without/invalid kind;
  - kind in the props;
  - akt-only linking (validation and `availableSources`);
  - other-user 404 on every `sources.*` and `events.sources.*` route (renamed from existing);
  - `/scans` 404;
  - the file-move migration (move, idempotency, partially moved row, missing files, empty dir cleanup, `down()`);
  - confirmation carry-over through preview props.

### Manual Testing Steps:

1. On `main`, upload a few real images (link one) and seed the demo. Note `select count(distinct event_id) from event_scan`, switch to this branch, `migrate`, and compare against `event_source`. Only the seeded, file-less rows may be reported missing.
2. Open `/sources`, check the thumbnails, open a source and zoom.
3. Upload a gravestone photo, check the badge, try to link it from the person modal (absent from the picker), then delete it (short dialog).
4. Delete a linked akt and read the impact list.

## Performance Considerations

The file-move migration does one filesystem rename per file on the same disk, which is instant regardless of file size. Row-by-row updates suit the expected volume (hundreds to low thousands of sources). No query shapes change apart from the extra `kind` filter on `availableSources`.

## Migration Notes

- This is the only change to existing data in prd-v3. Rollback is `migrate:rollback --step=2` (both migrations have a mirror `down()`), with restore from the pre-deploy full backup as the last resort.
- Rollback order: run `./scripts/artisan.sh migrate:rollback --step=2` **while the new code is still deployed** (the old code has no such migration files). Then revert the merge on `main` and run `scripts/deploy.sh`, which pulls `main` ff-only. `down()` drops `kind`, so gravestone/other sources uploaded after the deploy come back as plain scans. That loss is accepted.
- Postgres keeps the old sequence/index/constraint names after the rename. Accepted (see What We're NOT Doing).
- Bookmarks to `/scans/*` stop working. Accepted by prd-v3 FR-010.

## References

- Roadmap item: `context/foundation/roadmap.md` S-16 (:321-332)
- PRD: `context/foundation/prd-v3.md` FR-005, FR-009, FR-010, FR-011, Constraints & Compatibility
- Prior plans:
  - `context/archive/2026-09-24-scan-upload-and-preview/plan-brief.md` (storage, thumbnails)
  - `context/archive/2026-09-25-scan-delete-with-impact/plan.md:143` (impact dialog)
  - `context/archive/2026-09-24-birth-death-confirmed-by-scan/plan-brief.md:24-32` (derived confirmation, picker)
  - `context/archive/2026-09-22-production-data-safety/plan.md:186` (backup)
- Pattern for a choice in `<Form>`: `resources/js/components/persons/person-section-fields.tsx:85-111`
- Enum pattern: `app/Enums/Gender.php`
- Screenshot gate precedent: `context/archive/2026-09-29-person-modal/screenshots/shots.mjs`, `context/archive/2026-09-29-persons-list-views/plan.md:390-430`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Contract — sources in the data and the backend

#### Automated

- [x] 1.1 Migrations apply and roll back cleanly — 162a2a1
- [x] 1.2 Wayfinder regenerated with no /scans routes — 162a2a1
- [x] 1.3 Source, event-source, migration, marriage and person tests pass — 162a2a1
- [x] 1.4 No scan identifiers left in app code — 162a2a1
- [x] 1.5 PHP style — 162a2a1
- [x] 1.6 Static analysis — 162a2a1
- [x] 1.7 Types and lint — 162a2a1

#### Manual

- [x] 1.8 Real uploads made before migration show under /sources with thumbnails and scans/ is gone — 162a2a1
- [x] 1.9 Upload on /sources/create still works and opens its preview with zoom — 162a2a1

### Phase 2: The view — kind, akt-only picker and the short delete

#### Automated

- [x] 2.1 Feature tests still pass — 137688b
- [x] 2.2 Types, lint and format — 137688b
- [x] 2.3 Production build — 137688b
- [x] 2.4 No user-visible skan left outside intentional skan aktu — 137688b

#### Manual

- [x] 2.5 Upload without kind shows the error; gravestone upload shows its badge — 137688b
- [x] 2.6 Deleting an unlinked source shows the short confirmation — 137688b
- [x] 2.7 Deleting a linked source shows the impact list — 137688b
- [x] 2.8 Picker lists only akts — 137688b
- [x] 2.9 Light/dark, desktop/390 px, visible focus on the kind toggle — 137688b

### Phase 3: Kitchen sink, screenshot gate and deploy

#### Automated

- [x] 3.1 Demo seeder runs — 38a3426
- [x] 3.2 Screenshots produced — 38a3426
- [x] 3.3 Full suite green — 38a3426

#### Manual

- [x] 3.4 verdict.md reviewed
- [x] 3.5 Rehearsal on real-shaped data: equal confirmed counts, only seeded file-less rows reported
- [x] 3.6 Deploy runbook followed on production
