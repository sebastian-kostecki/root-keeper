# Scan Upload and Preview — Plan Brief

> Full plan: `context/changes/scan-upload-and-preview/plan.md`

## What & Why

Roadmap slice S-02 (FR-013, FR-016). The user uploads scans of vital-record acts into their tree and opens them in a preview zoomed enough to read the handwriting (US-01). Without scans there is nothing to link in S-03, the north-star slice. The roadmap names two risks: a file leaking through public access, and zoom too weak to read the act.

## Starting Point

S-01 delivered a tree per user, people and events. It also set the isolation pattern (policy → 404 for other trees), the Action/Form Request/Resource pattern and Polish UI conventions. Nothing handles uploads yet. The private `local` disk exists and is backed up. Its built-in `serve` route authorizes only by signed URL, which is not safe for scans.

## Desired End State

- A "Skany" sidebar entry shows a grid of the user's scans (thumbnail, original filename, date).
- "Dodaj skan" uploads one JPG/PNG/WebP file of up to 20 MB and lands on its preview.
- The preview supports wheel zoom, drag-to-pan and +/−/fit buttons, well beyond 100 % of the original pixels.
- Scan bytes are served only through authorized controller routes; another user's scan returns 404.
- Deleting an account removes its scan files.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Max file size | 20 MB | Fits high-res photos and archive downloads, and gives F-01 a hard backup bound (~10 GB worst case for 500 scans). |
| Formats | JPG, PNG, WebP | Browser-native, so one viewer covers all and no PDF/TIFF conversion is needed. |
| Multi-page acts | 1 scan = 1 file | Simplest model; S-03 already allows several scans per event. |
| Zoom | `react-zoom-pan-pinch` (new npm dep, approved) | Smooth wheel/drag zoom with little custom code; addresses the "unreadable act" risk. |
| Scan identity | Original filename + upload date | Meaningful without breaking the PRD's "no description" non-goal. |
| List view | Grid with thumbnails | Visual recognition of acts alongside the name. |
| Thumbnails | GD at upload (~400 px JPG), EXIF-oriented, skipped above 40 Mpx | No new PHP dependency; the pixel guard prevents a container OOM, since CLI `memory_limit` is `-1`. |
| File serving | Authorized `ScanController` routes, `Cache-Control: private` | Laravel's signed `serve` URLs would be bearer links, which breaks the access guardrail. |
| Upload flow | One file per submission (planning default, not asked) | Keeps validation and errors simple; change at review if bulk upload matters. |
| Account deletion | Delete `scans/{tree_id}` after the user row is deleted | Database rows cascade, but files would be orphaned. |

## Scope

**In scope:**
- `scans` table, model and factory; `ScanPolicy`; `ScanStoreRequest`; `StoreScan` and `GenerateScanThumbnail` actions
- `ScanController`: index, create, store and show, plus `file` and `thumbnail` endpoints
- Scan file cleanup on account deletion
- Pages `scans/index`, `scans/create` and `scans/show`, the zoom viewer component, and the sidebar entry
- Feature and unit tests

**Out of scope:** linking to events (S-03); deleting scans (S-08); descriptions or labels; multi-page scans; PDF, TIFF or HEIC; bulk upload; search or pagination; image editing or OCR; changing backup caps; the Cloudflare setting.

## Architecture / Approach

Upload → `ScanStoreRequest` (image, jpg/png/webp, ≤ 20 480 KB) → `StoreScan`:
1. Store the original at `scans/{tree_id}/{uuid}.{ext}` on the private disk.
2. `GenerateScanThumbnail` (GD, EXIF rotation, 40 Mpx guard) writes `thumbnails/{uuid}.jpg`, or skips it.
3. Create the row; if that fails, delete the files.

Reads go through `ScanController::file` / `thumbnail` after `Gate::authorize('view')`, streamed by `Storage::response` with private and no-sniff headers. The React pages build the URLs with Wayfinder, and the viewer wraps `<img>` in `TransformWrapper`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Scan backend | Model, upload, thumbnail, authorized serving, account cleanup, tests | Leaking a file (wrong serving path) or OOM on a huge image |
| 2. Scan UI | Grid, upload form, zoom preview, sidebar entry, production restore drill | Zoom not comfortable enough to read a real act |

**Prerequisites:** S-01 done (it is). F-01 done (it is); its restore drill with a real scan is completed here as a manual step.
**Estimated effort:** ~2 sessions (one per phase).

## Open Risks & Assumptions

- The worst-case backup volume at the 20 MB limit (~10 GB) exceeds the current 5000 MB Spatie cap. The owner's F-01 follow-up (measure 20–30 real scans, then raise the caps or move scans to restic) must happen before the scan count grows.
- Assumes the production `php8.5-gd` has the same JPEG/PNG/WebP support and exif as dev (same ondrej packages). This is verified by the production drill.
- Scans still pass through Cloudflare (proxy kept by the owner's earlier decision); `Cache-Control: private` prevents edge caching.
- A 20 MB upload holds one of the 4 `artisan serve` workers while it runs. This is acceptable at the current load.

## Success Criteria (Summary)

- A real act uploaded through the UI is readable in the zoom preview, and its thumbnail is upright in the grid.
- A second account or a guest can never see the first account's scans or their bytes (404 or login redirect), as proven by tests.
- The production backup/restore drill recovers an uploaded scan.
