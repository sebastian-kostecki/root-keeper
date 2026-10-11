# Scan Upload and Preview Implementation Plan

## Overview

Roadmap slice S-02 (FR-013, FR-016, US-01 acceptance "Skan można powiększyć na tyle, by odczytać treść aktu"). A logged-in user uploads a scan of a vital-record act (one JPG/PNG/WebP file, ≤ 20 MB) into their tree, browses their scans as a thumbnail grid and opens a zoomable preview readable at full resolution. Scans are stored on the private disk and served only to the owning user; guests and other users get a redirect to login or a 404. Linking scans to events (S-03) and deleting scans (S-08) are out of scope.

## Current State Analysis

- The domain is established by S-01: `User` has one `Tree` (`app/Models/User.php:53-57`), and every domain table carries a `tree_id` FK with `cascadeOnDelete()`. Queries start from `$request->user()->tree` (`app/Http/Controllers/PersonController.php:23`).
- Isolation convention: a policy returns `Response::denyAsNotFound()` for records of another tree (`app/Policies/PersonPolicy.php:14-19`), and the controller calls `Gate::authorize('view', $model)` (`PersonController.php:63`). Policies are auto-discovered.
- Controller conventions:
  - thin controller, a Form Request with Polish `attributes()` (`app/Http/Requests/PersonStoreRequest.php`), and an Action class `handle(Tree $tree, array $data)` inside `DB::transaction` (`app/Actions/Persons/CreatePerson.php`);
  - props shaped by an API Resource that is `->resolve()`d;
  - a toast via `Inertia::flash('toast', …)` with `__('…')` Polish keys, then a redirect to show (`PersonController.php:49-56`).
- Routes: `Route::resource(...)->only([...])` inside the `['auth', 'verified']` group (`routes/web.php:8-10`).
- Frontend conventions:
  - Pages live in `resources/js/pages/persons/{index,create,show}.tsx` and use the Inertia `<Form {...Controller.store.form()}>` render prop with `Label` + `Input` + `InputError`.
  - Breadcrumbs are set via `Page.layout = { breadcrumbs }` (static) or `setLayoutProps` (dynamic, `persons/show.tsx:14`).
  - Sidebar items are listed in `resources/js/components/app-sidebar.tsx:19-25`.
  - Types live in `resources/js/types/*.ts` and are re-exported from `@/types`.
- Storage:
  - The `local` disk has root `storage/app/private` (`config/filesystems.php:33-39`). It is bind-mounted in prod (`docker-compose.prod.yml:20`) and included in Spatie backups (`config/backup.php:29-31`).
  - Its `'serve' => true` route checks **only a URL signature, not the logged-in user** (`vendor/laravel/framework/src/Illuminate/Filesystem/ServeFile.php:27-29`). A signed link would be a bearer link that anyone could open.
- Nothing handles uploads yet (no `UploadedFile`/`Storage::` usage in `app/`). The only size cap is PHP's `upload_max_filesize`/`post_max_size = 100M` (`docker/8.5/php.ini:2-3`).
- Images:
  - GD is installed in prod (`docker/8.5/Dockerfile.prod:107`); the dev container reports JPEG, PNG and WebP support, and exif is loaded.
  - Imagick exists only in dev, so it must not be used.
  - PHP runs through the CLI SAPI (`artisan serve`), where `memory_limit` is `-1`. Only the 1 GB container limit (`docker-compose.prod.yml:13`) bounds a GD decode.
- No zoom/pan library exists. `react-zoom-pan-pinch` 4.2.0 (peer `react: *`, maintained) was approved by the user as a new dependency.
- Account deletion is handled in `app/Http/Controllers/Settings/ProfileController.php:49-55`. Database rows cascade from `users`, but files on disk would be orphaned.

## Desired End State

- A "Skany" sidebar entry opens `/scans`: a grid of the user's scans, newest first. Each card shows a thumbnail (or a placeholder icon when no thumbnail exists), the original filename and the upload date.
- `/scans/create` accepts one JPG/PNG/WebP file up to 20 MB. On success the user lands on the scan's preview with a toast "Dodano skan". Invalid files show a Polish validation error.
- `/scans/{scan}` shows the scan with wheel zoom, drag-to-pan, +/−/reset controls and zoom beyond 100 % of the original pixels, so handwriting in a real act is readable.
- The original file and thumbnail are served only through `scans.file` / `scans.thumbnail`, which require login and ownership. Another user's scan returns 404 and a guest is redirected to login.
- Deleting an account removes that tree's scan files from disk.
- Verify: the feature tests pass, and manual reading of a real act at zoom succeeds.

### Key Discoveries:

- `ServeFile` authorizes only by URL signature (`ServeFile.php:27-29`), so scans must never be exposed through `Storage::temporaryUrl()` or `/storage/...`.
- GD ignores EXIF orientation. Phone photos of acts would produce sideways thumbnails unless orientation is applied. Browsers already honour EXIF on the original `<img>` (`image-orientation: from-image` is the default).
- `memory_limit = -1` under the CLI SAPI, so a huge image (e.g. 12 000 × 9 000 px) decoded by GD could push the container toward its 1 GB OOM kill. The thumbnail step needs a pixel-count guard.
- `UploadedFile::fake()->image()` needs GD in the test environment. GD is present in Sail. CI's `shivammathur/setup-php` does not pin extensions, so phase 1 adds `extensions: gd, exif` (Phase 1 §11).
- Tests run on sqlite in CI and pgsql locally; the schema must stay portable (`context/archive/2026-09-23-account-tree-and-first-person/plan.md:32`).

## What We're NOT Doing

- Linking scans to events and showing them on the person preview — S-03.
- Deleting scans and showing the list of facts that lose their source — S-08.
- Descriptions, labels or renaming of scans (PRD non-goal "brak opisu skanów"). A scan is identified by its original filename and upload date.
- Multi-page scans. One scan is one file; a two-page act is two scans, both linked to the event in S-03.
- PDF, TIFF, GIF, HEIC or any format other than JPG/PNG/WebP. The user converts other formats before upload.
- Multi-file upload in one submission. One file per upload (planning default, see brief).
- Search, filtering or pagination of the scan list (volume is ~500 scans per year).
- Image editing (crop, rotate, contrast) and OCR.
- Changing backup retention or caps. The worst-case volume derived from the 20 MB limit is handed back to F-01's open unknown (see Migration Notes).
- Changing the Cloudflare proxy setting (the owner's earlier decision stands).

## Implementation Approach

Follow the S-01 Person pattern end to end, adding file handling.

The backend phase delivers a fully tested model, upload, thumbnail and authorized file-serving path. The UI phase then adds three pages and the zoom viewer on top of routes that are already proven.

- **Storage layout:** files go under `scans/{tree_id}/` on the `local` disk, with server-generated UUID names. The extension is derived from the detected MIME type, never from the client filename. Thumbnails go under `scans/{tree_id}/thumbnails/`.
- **Thumbnails:** a small, dedicated service generates the thumbnail with GD. It applies EXIF orientation, skips generation above a pixel-count cap, and never fails the upload: a scan without a thumbnail is valid and the UI shows a placeholder.
- **Failure cleanup:** if the database insert fails after files were written, the Action deletes those files so no orphans remain.

## Critical Implementation Details

- **Never serve scans through a signed URL.** The `local` disk's built-in `serve` route authorizes by signature only, so every scan byte must go through `ScanController` actions guarded by `Gate::authorize('view', $scan)`. Responses carry `Cache-Control: private` and `X-Content-Type-Options: nosniff`, so a shared cache (Cloudflare) never stores a scan and a mislabeled file is never content-sniffed.
- **Thumbnail memory guard.** Read dimensions with `getimagesize()` *before* decoding. If width × height exceeds 25 megapixels, skip thumbnail generation: GD's PNG/WebP/progressive-JPEG decoders peak at roughly 7–8 bytes per pixel (measured in Sail at impl-review F2), so 25 Mpx ≈ 190 MB, which keeps a decode well within the 1 GB container shared by 4 server workers. This budget holds only if exactly one full-size bitmap exists at a time: scale first, then rotate the thumbnail (rotating the full-size image would double the peak to ~400 MB). Free GD images explicitly after use.

## Phase 1: Scan backend

### Overview

Scan model, storage, validated upload, GD thumbnail, authorized file serving, file cleanup on account deletion, and feature tests. At the end of this phase every route works and is covered by tests. The pages render but may be placeholders until phase 2.

### Changes Required:

#### 1. Migration and model

**File**: `database/migrations/<timestamp>_create_scans_table.php`, `app/Models/Scan.php`, `app/Models/Tree.php`

**Intent**: Persist one row per uploaded scan, owned by a tree and removed with it.

**Contract**:
- Table `scans` with the columns:
  - `id`;
  - `tree_id` (FK, constrained, cascadeOnDelete);
  - `original_name` string;
  - `path` string;
  - `thumbnail_path` nullable string;
  - `mime_type` string;
  - `size` unsignedBigInteger (bytes);
  - `width`, `height` nullable unsignedInteger;
  - timestamps.
- Index `[tree_id, created_at]`.
- `Scan` follows the model conventions: `#[Fillable]` attribute, `casts()`, PHPDoc `@property`, `tree(): BelongsTo`.
- `Tree::scans(): HasMany`.
- Create both with `vendor/bin/sail artisan make:model Scan -mf --no-interaction`.

#### 2. Factory

**File**: `database/factories/ScanFactory.php`

**Intent**: Let tests create scan rows (with or without real files) for scoping and isolation cases.

**Contract**:
- `tree_id => Tree::factory()`; a faker `original_name` ending in `.jpg`; `path` of the form `scans/{tree}/{uuid}.jpg`; `mime_type` `image/jpeg`; plausible `size`/`width`/`height`; `thumbnail_path` null by default.
- A state `withThumbnail()`.
- Files are written by the tests themselves via `Storage::fake('local')`.

#### 3. Policy

**File**: `app/Policies/ScanPolicy.php`

**Intent**: Enforce the tree-isolation rule for single scans, 404 for foreign ones.

**Contract**: `view(User $user, Scan $scan): Response` — the same shape as `PersonPolicy::view`. Only this method (impl-review F5: no unused stubs).

#### 4. Form Request

**File**: `app/Http/Requests/ScanStoreRequest.php`

**Intent**: Validate the upload: required single file, image, JPG/PNG/WebP only, at most 20 MB.

**Contract**:
- Field `scan`, validated with `['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(20 * 1024)]`.
- `attributes()` maps `scan` to `'skan'`.
- The validation message comes from laravel-lang Polish. The 20 MB value (20 480 KB) is the agreed limit.

#### 5. Thumbnail generator

**File**: `app/Actions/Scans/GenerateScanThumbnail.php`

**Intent**: Produce a ~400 px-wide JPEG thumbnail with GD, oriented correctly, without ever breaking the upload.

**Contract**:
- `handle(string $sourceAbsolutePath, string $mimeType): ?string` returns the thumbnail's binary JPEG content, or null when it is skipped or fails.
- Steps:
  1. `getimagesize` guard: skip above `$maxPixels` (25 Mpx by default; lowered from 40 Mpx by impl-review F2).
  2. Decode by MIME type (`imagecreatefromjpeg`/`png`/`webp`).
  3. Read the EXIF `Orientation` for JPEG (`exif_read_data` guarded with `function_exists`).
  4. `imagescale` so the *final* (upright) width is 400, keeping the aspect ratio and never upscaling small images. For orientation 6/8, the source height becomes the final width. Free the full-size image right after scaling.
  5. Apply orientation 3/6/8 with `imagerotate` on the small scaled image, never on the full-size bitmap, so peak memory stays at one full-size copy.
  6. Encode with `imagejpeg` at quality ~80; fill PNG/WebP transparency with white first.
- Constructor: `__construct(private int $maxPixels = 25_000_000)`, so tests can lower the cap.
- Catches any `Throwable`, reports it with `report()` and returns null.

#### 6. Store action

**File**: `app/Actions/Scans/StoreScan.php`

**Intent**: Store the original and the optional thumbnail on the private disk and create the row, leaving no orphan files if anything fails.

**Contract**:
- `handle(Tree $tree, UploadedFile $file): Scan`.
- Name: `Str::uuid()`. Extension from `$file->extension()` (content-based). Path `scans/{tree_id}/{uuid}.{ext}` on disk `local`.
- Dimensions come from `getimagesize`. For a JPEG with EXIF orientation 5–8, swap width and height so the stored dimensions match the upright image the browser shows.
- `original_name` is `$file->getClientOriginalName()` with control characters and `/` / `\` stripped, then truncated to 255 characters. If nothing remains, use `skan.{ext}`. Symfony's `HeaderUtils::makeDisposition` throws on those characters.
- The thumbnail is written to `scans/{tree_id}/thumbnails/{uuid}.jpg` when the generator returns content.
- The row is created via `$tree->scans()->create(...)`.
- If creating the row throws, delete the written files and rethrow.

#### 7. Controller and routes

**File**: `app/Http/Controllers/ScanController.php`, `routes/web.php`

**Intent**: Resource-style pages plus two authorized binary endpoints.

**Contract**:
- `Route::resource('scans', ScanController::class)->only(['index', 'create', 'store', 'show'])`, plus `GET scans/{scan}/file` named `scans.file` and `GET scans/{scan}/thumbnail` named `scans.thumbnail`. All go in the existing `['auth', 'verified']` group.
- `index(Request)`: `$request->user()->tree->scans()->latest()->orderByDesc('id')->get()`, rendered as `scans/index` with `scans` => `ScanResource::collection(...)->resolve()`.
- `create()`: renders `scans/create` with no props.
- `store(ScanStoreRequest, StoreScan)`: stores the file, flashes a toast `__('Dodano skan')`, and redirects with `to_route('scans.show', $scan)`.
- `show(Scan)`: `Gate::authorize('view', $scan)`, then renders `scans/show` with `scan` => `ScanResource::make($scan)->resolve()`.
- `file(Scan)`: `Gate::authorize('view', $scan)`, then returns `Storage::disk('local')->response($scan->path, $scan->original_name, [...])`.
  - Headers: `Content-Type` = stored `mime_type`, `Cache-Control: private, max-age=86400`, `X-Content-Type-Options: nosniff`.
  - Returns 404 if the file is missing.
  - The download name is `original_name` when `Str::ascii()` of it is non-empty, otherwise `skan-{id}.{ext}`. An all-non-Latin name (e.g. emoji) would leave Symfony with no ASCII fallback, and it would throw.
- `thumbnail(Scan)`: authorizes the same way; returns 404 when `thumbnail_path` is null or the file is missing; otherwise serves it with `image/jpeg` and the same headers.

#### 8. API Resource

**File**: `app/Http/Resources/ScanResource.php`

**Intent**: Shape scan props for the pages.

**Contract**:
- Returns `{id, original_name, mime_type, size, width, height, has_thumbnail, uploaded_at}`.
- `uploaded_at` is an ISO 8601 string. The array shape is documented in PHPDoc.
- URLs are built on the frontend with Wayfinder, not included here.

#### 9. File cleanup on account deletion

**File**: `app/Http/Controllers/Settings/ProfileController.php`

**Intent**: The guardrail says scans must not remain reachable. Rows cascade, but files would be orphaned on disk, so the tree's scan directory is removed with the account.

**Contract**:
- Before `$user->delete()`, capture `$treeId = $user->tree?->id` (`tree()` is a nullable `HasOne`; `UserFactory::withoutTree()` creates tree-less users).
- After a successful delete, and **only when `$treeId !== null`**, call `Storage::disk('local')->deleteDirectory("scans/{$treeId}")`. A null id would otherwise resolve to `scans/` and wipe every tree's scans.
- Deleting files after the DB delete means a failed DB delete never loses files.

#### 10. Feature tests

**File**: `tests/Feature/ScansTest.php`, plus `tests/Feature/GenerateScanThumbnailTest.php` for the generator (in Feature because `tests/Pest.php` boots Laravel only there, and the generator calls `report()`)

**Intent**: Cover upload, validation, isolation and serving, following the `PersonsTest.php` structure. Use `Storage::fake('local')` and `UploadedFile::fake()->image()`.

**Contract**: named cases:
- A guest is redirected to login from `scans.index`, `scans.create`, `scans.store`, `scans.show`, `scans.file` and `scans.thumbnail`.
- The index lists only the user's own scans, newest first.
- `show`, `file` and `thumbnail` for another user's scan return 404.
- Storing a JPG:
  - creates a row with the user's `tree_id`, the original filename, the MIME type, the size and the dimensions;
  - puts the file under `scans/{tree_id}/` with a UUID name and no client name in the path;
  - creates a thumbnail;
  - redirects to `scans.show`.
- PNG and WebP are accepted.
- Rejected: a missing file; a file over 20 MB (`UploadedFile::fake()->create('akt.jpg', 20481, 'image/jpeg')`); PDF; GIF; SVG; a text file renamed to `.jpg`. Validation messages are in Polish (one assertion).
  - Fake files report a MIME type from their name or the explicit argument, never from content (`Illuminate/Http/Testing/File.php:132-135`). So PDF/GIF/SVG use `UploadedFile::fake()->create('akt.pdf', 10, 'application/pdf')` etc. with an explicit MIME type.
  - The renamed-text case must be a **real** temp file so finfo sniffs it: write plain text to a temp path, then `new UploadedFile($tmpPath, 'akt.jpg', null, null, true)`. `fake()->createWithContent('akt.jpg', …)` would report `image/jpeg` and pass validation.
- `file` returns the original's bytes with the stored `Content-Type`, `Cache-Control` containing `private`, and `X-Content-Type-Options: nosniff`.
- `thumbnail` returns 404 when the scan has no thumbnail.
- An image above the pixel cap is stored without a thumbnail (generator test, constructing `new GenerateScanThumbnail(maxPixels: 100)` with a small faked image).
- An EXIF-rotated JPEG yields a portrait thumbnail (generator test, fixture file `tests/Fixtures/rotated-exif.jpg` if faker cannot produce EXIF).
- Deleting the account removes `scans/{tree_id}` from disk.
- Deleting an account without a tree (`UserFactory::withoutTree()`) leaves another tree's scan files intact.

#### 11. CI extensions

**File**: `.github/workflows/tests.yml`

**Intent**: Guarantee the GD and EXIF functions that the thumbnail tests rely on, instead of relying on runner defaults.

**Contract**: Add `extensions: gd, exif` to the `shivammathur/setup-php` step.

### Success Criteria:

#### Automated Verification:

- Scan feature and unit tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ScansTest.php tests/Feature/GenerateScanThumbnailTest.php`
- Full test suite passes: `vendor/bin/sail artisan test --compact`
- Static analysis passes: `vendor/bin/sail composer types:check`
- Code style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- Frontend types still compile after Wayfinder regeneration: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Opening `/scans/<id>/file` of another user's scan in the browser returns 404, and logged out it redirects to login

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes for these items live in the `## Progress` section at the bottom of the plan.

---

## Phase 2: Scan UI

### Overview

The scan list grid, the upload page, the zoom preview with `react-zoom-pan-pinch`, and a sidebar entry. UI copy is Polish.

### Changes Required:

#### 1. Dependency

**File**: `package.json`, `package-lock.json`

**Intent**: Add the approved pan/zoom library.

**Contract**: `vendor/bin/sail npm install react-zoom-pan-pinch@^4.2.0`.

#### 2. Types

**File**: `resources/js/types/scan.ts`, `resources/js/types/index.ts`

**Intent**: A typed `Scan` mirroring `ScanResource`.

**Contract**: `Scan = { id: number; original_name: string; mime_type: string; size: number; width: number | null; height: number | null; has_thumbnail: boolean; uploaded_at: string }`, re-exported from `@/types`.

#### 3. Scan list page

**File**: `resources/js/pages/scans/index.tsx`

**Intent**: A responsive grid of scan cards with the heading "Skany" and a "Dodaj skan" button linking to create.

**Contract**:
- Each card links to `ScanController.show(id)`.
- The thumbnail `<img src={ScanController.thumbnail.url(id)} loading="lazy">` is shown when `has_thumbnail`; otherwise a lucide placeholder icon is shown.
- Each card shows the filename (truncated, full name in `title`) and the upload date formatted `pl-PL`.
- The empty state reads "Nie masz jeszcze żadnych skanów" and includes a link to upload.
- Breadcrumbs are set via static `.layout`, as in `persons/index.tsx`.

#### 4. Upload page

**File**: `resources/js/pages/scans/create.tsx`

**Intent**: A single-file upload form.

**Contract**:
- `<Form {...ScanController.store.form()}>` with a file `Input` named `scan`, `accept="image/jpeg,image/png,image/webp"`, a hint "JPG, PNG lub WebP, do 20 MB", `InputError` for `errors.scan`, and a submit button disabled while `processing`.
- Upload progress comes from the render prop's `progress`.
- Breadcrumbs are Skany → Dodaj skan.

#### 5. Zoom preview page

**File**: `resources/js/pages/scans/show.tsx`, `resources/js/components/scans/scan-viewer.tsx`

**Intent**: Let the user read a handwritten act: fit-to-view on open, wheel zoom, drag-to-pan, zoom well beyond 100 % of the original pixels.

**Contract**:
- `ScanViewer({ src, alt })` wraps `TransformWrapper`/`TransformComponent`:
  - `minScale` about 0.5, `maxScale` about 8;
  - `centerOnInit`;
  - toolbar buttons Powiększ / Pomniejsz / Dopasuj (`zoomIn`, `zoomOut`, `resetTransform`);
  - the viewport fills the available height (roughly `h-[calc(100vh-12rem)]`) with a neutral background.
- The page shows the filename as the heading, then the upload date, size in MB and pixel dimensions, then the viewer with `src={ScanController.file.url(id)}`.
- Breadcrumbs are set dynamically via `setLayoutProps`: Skany → filename.

#### 6. Sidebar entry

**File**: `resources/js/components/app-sidebar.tsx`

**Intent**: Make scans reachable from navigation.

**Contract**: Append `{ title: 'Skany', href: scansIndex(), icon: FileImage }` after 'Osoby' (`import { index as scansIndex } from '@/routes/scans'`). Active highlighting already uses `isCurrentOrParentUrl`.

#### 7. Page render tests

**File**: `tests/Feature/ScansTest.php`

**Intent**: Assert the Inertia component and props per page. These may already exist from phase 1; extend them rather than duplicate.

**Contract**: `assertInertia` checks:
- `scans/index` with `scans` of the expected count and shape;
- `scans/create`;
- `scans/show` with `scan.id` and `scan.has_thumbnail`.

### Success Criteria:

#### Automated Verification:

- Scan tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ScansTest.php`
- Full test suite passes: `vendor/bin/sail artisan test --compact`
- Frontend type check passes: `vendor/bin/sail npm run types:check`
- Frontend lint/format check passes: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`
- Code style is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Uploading a real act scan via "Dodaj skan" lands on the preview with the toast "Dodano skan", and the scan appears first in the Skany grid with an upright thumbnail
- In the preview, the handwriting of a real act is comfortably readable using wheel zoom and drag-to-pan, and Dopasuj resets to fit
- Uploading a PDF and a file over 20 MB shows a Polish validation error and stores nothing
- A second test account sees an empty Skany grid and gets 404 on the first account's scan preview and file URLs
- After deploying to production, the F-01 handoff drill passes: upload a real scan via the UI, run `./scripts/artisan.sh backup:run`, restore the archive into a throwaway instance and confirm the scan file opens

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase. Phase blocks use plain bullets — the corresponding `- [ ]` checkboxes for these items live in the `## Progress` section at the bottom of the plan.

---

## Testing Strategy

### Generator Tests (`tests/Feature/GenerateScanThumbnailTest.php`):

- `GenerateScanThumbnail`: width ≤ 400 px with the aspect ratio kept; EXIF orientation 6 produces a portrait result; above the pixel cap it returns null; corrupt input returns null without throwing.

### Integration Tests:

- `ScansTest.php`: guest redirects on all six routes; the list is scoped to the user's tree; 404 isolation on show, file and thumbnail; the store happy path for JPG, PNG and WebP (row, file location, thumbnail, redirect); the validation rejection dataset; response headers on `file`; `thumbnail` 404 without a thumbnail; account deletion removes files; Inertia page props.

### Manual Testing Steps:

1. Upload a real phone photo of an act. Check the upright thumbnail in the grid.
2. Open the preview, zoom into a handwritten name, and confirm it is readable.
3. Try a PDF, a file over 20 MB and a renamed `.txt`. Each shows a Polish error.
4. Log in as a second account and try the first account's `/scans/<id>` and `/scans/<id>/file`. Both return 404.
5. On production, run the backup/restore drill with the uploaded scan.

## Performance Considerations

- Thumbnails keep the list light: about 400 px JPEG, tens of KB each, lazy-loaded. Originals load only in the preview.
- Uploads of up to 20 MB each occupy one of the 4 `artisan serve` workers for the duration of the upload. This is acceptable at `qps: low`.
- GD decoding is bounded by the 25 Mpx guard (about 190 MB peak at ~7.6 B/px) inside the 1 GB container.
- `Cache-Control: private, max-age=86400` lets the browser reuse scan bytes while revisiting the grid or preview without shared caching.

## Migration Notes

- A new `scans` table only; no data migration. Rollback is `migrate:rollback` of the single migration, plus removing `storage/app/private/scans/` if any test uploads exist.
- **Backup sizing hand-off to F-01:** the agreed 20 MB limit bounds the worst case at about 500 scans × 20 MB ≈ 10 GB per full archive. That exceeds the current 5000 MB Spatie cap (`config/backup.php`). This plan does not change backups. The open F-01 unknown (measure 20–30 real scans, then raise the caps or move scans to restic) remains the owner's follow-up. Record the 20 MB decision in the roadmap's S-02 unknown when this change is archived.

## References

- Roadmap slice: `context/foundation/roadmap.md` (S-02, lines 111-122)
- PRD: `context/foundation/prd.md` (FR-013, FR-016, US-01, Access Control, Non-Goals)
- Infrastructure guidance on the upload limit: `context/foundation/infrastructure.md:115`
- S-01 plan and reviews (conventions): `context/archive/2026-09-23-account-tree-and-first-person/plan.md`, `reviews/impl-review.md`
- F-01 restore-drill hand-off: `context/archive/2026-09-22-production-data-safety/plan.md:44`
- Similar implementation: `app/Http/Controllers/PersonController.php:21-70`, `app/Policies/PersonPolicy.php:14-19`, `app/Actions/Persons/CreatePerson.php:40`, `resources/js/pages/persons/create.tsx:48`
- Signed-URL caveat: `vendor/laravel/framework/src/Illuminate/Filesystem/ServeFile.php:27-29`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Scan backend

#### Automated

- [x] 1.1 Scan feature and unit tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ScansTest.php tests/Feature/GenerateScanThumbnailTest.php` — 8ca2b9e
- [x] 1.2 Full test suite passes: `vendor/bin/sail artisan test --compact` — 8ca2b9e
- [x] 1.3 Static analysis passes: `vendor/bin/sail composer types:check` — 8ca2b9e
- [x] 1.4 Code style is clean: `vendor/bin/sail bin pint --dirty --format agent` — 8ca2b9e
- [x] 1.5 Frontend types still compile after Wayfinder regeneration: `vendor/bin/sail npm run types:check` — 8ca2b9e

#### Manual

- [x] 1.6 Opening `/scans/<id>/file` of another user's scan in the browser returns 404, and logged out it redirects to login — 8ca2b9e

### Phase 2: Scan UI

#### Automated

- [x] 2.1 Scan tests pass: `vendor/bin/sail artisan test --compact tests/Feature/ScansTest.php` — 18ee96e
- [x] 2.2 Full test suite passes: `vendor/bin/sail artisan test --compact` — 18ee96e
- [x] 2.3 Frontend type check passes: `vendor/bin/sail npm run types:check` — 18ee96e
- [x] 2.4 Frontend lint/format check passes: `vendor/bin/sail npm run check` — 18ee96e
- [x] 2.5 Production build succeeds: `vendor/bin/sail npm run build` — 18ee96e
- [x] 2.6 Code style is clean: `vendor/bin/sail bin pint --dirty --format agent` — 18ee96e

#### Manual

- [x] 2.7 Uploading a real act scan via "Dodaj skan" lands on the preview with the toast "Dodano skan", and the scan appears first in the Skany grid with an upright thumbnail — 18ee96e
- [x] 2.8 In the preview, the handwriting of a real act is comfortably readable using wheel zoom and drag-to-pan, and Dopasuj resets to fit — 18ee96e
- [x] 2.9 Uploading a PDF and a file over 20 MB shows a Polish validation error and stores nothing — 18ee96e
- [x] 2.10 A second test account sees an empty Skany grid and gets 404 on the first account's scan preview and file URLs — 18ee96e
- [x] 2.11 After deploying to production, the F-01 handoff drill passes: upload a real scan via the UI, run `./scripts/artisan.sh backup:run`, restore the archive into a throwaway instance and confirm the scan file opens
