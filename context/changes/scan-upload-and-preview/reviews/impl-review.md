<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Scan Upload and Preview

- **Plan**: context/changes/scan-upload-and-preview/plan.md
- **Scope**: Full plan (Phases 1–2 of 2; Progress item 2.11 — post-deploy backup/restore drill — still pending by design)
- **Reviewed phases**: 1, 2
- **Date**: 2026-09-24
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 6 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automated criteria were re-run on 2026-09-24 and all passed:
- the scan tests (28 tests, 102 assertions);
- the full suite (88 tests, 321 assertions);
- phpstan (0 errors), `tsc`, `npm run check`, pint `--test` and `npm run build`.

Manual items 1.6 and 2.7–2.10 are checked. Item 2.11 is a production drill and is pending.

## Findings

### F1 — Zoom ceiling is relative to the fitted image, so high-dpi scans never reach 100 %

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/scans/scan-viewer.tsx:14
- **Detail**:
  - The plan's intent is "zoom well beyond 100 % of the original pixels" (Phase 2 §5, Desired End State). `maxScale={8}` is a multiplier of the `object-contain`-fitted `<img>`, not of the image's natural pixels.
  - Example: a 600 dpi A4 flatbed scan (~4960×7016) in a ~700 px tall viewport fits at ~0.1×, so maximum zoom is ~0.8× of the original. It never reaches 1:1.
  - Phone photos pass, which explains why manual check 2.8 succeeded.
  - This is a flaw in the plan itself: the fixed `maxScale ~8` contract does not guarantee the intent.
- **Fix**: Measure `naturalWidth / renderedWidth` in the `<img>` `onLoad`, and pass `maxScale={Math.max(8, fitRatio * 4)}` (at least 4× the original pixels).
  - Strength: guarantees the plan's readability intent for any scan resolution.
  - Tradeoff: a small state and effect in `ScanViewer`; the ratio must be recomputed on resize.
  - Confidence: MED. The ratio maths is simple. It is not yet verified that `TransformWrapper` picks up a changed `maxScale` prop after mount (a `key` remount is the fallback).
  - Blind spot: not tested with a real 600 dpi scan in the browser.
- **Decision**: FIXED (fixed differently: raised the fixed `maxScale` from 8 to 32 instead of deriving it from the natural size)

### F2 — The 40 Mpx memory guard under-budgets PNG, WebP and progressive JPEG

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Scans/GenerateScanThumbnail.php:14,33
- **Detail**:
  - The plan assumes ~5 B/px, i.e. ~200 MB at 40 Mpx (plan.md:75). Peak RSS was measured in the Sail container (GD 2.3.3) at 36 Mpx; the table projects it to 40 Mpx. The measurement was done by the review sub-agent and not re-run here.

    | Format | Bytes per pixel | Projected at 40 Mpx |
    |---|---|---|
    | Baseline JPEG | 3.8 | ~153 MB |
    | Progressive JPEG / PNG | 6.7 | ~267 MB |
    | PNG with alpha / WebP | 7.6 | ~305 MB |

  - The PNG and WebP decoders use their own intermediate buffers.
  - Four concurrent large PNG or WebP uploads come to about 1.2 GB plus the framework baseline, which exceeds the 1 GB container.
  - With `memory_limit = -1` and libgd using libc malloc, nothing else bounds this.
  - The practical risk is low at `qps: low`, but the plan's guard does not hold the budget it claims.
- **Fix A ⭐ Recommended**: Lower the default cap to 25 Mpx (≈190 MB at 7.6 B/px) and correct the plan's figure to ~8 B/px.
  - Strength: a one-constant change that keeps the "one decode fits comfortably" invariant true for every allowed format.
  - Tradeoff: images between 25 and 40 Mpx (e.g. a 600 dpi A4 at ~35 Mpx) get the placeholder instead of a thumbnail.
  - Confidence: MED. The measurements are plausible and consistent with how the libpng and libwebp decoders work, but they were not reproduced in this session.
  - Blind spot: the real distribution of act-scan resolutions; a per-format cap (JPEG 40 Mpx, PNG/WebP 25 Mpx) is a middle ground.
- **Fix B**: Keep 40 Mpx and serialise thumbnail generation with `Cache::lock('scan-thumbnail')->block(...)`.
  - Strength: large scans keep their thumbnails, and at most one ~300 MB decode runs at a time.
  - Tradeoff: adds a lock dependency on the cache store, and a waiting upload holds one of only 4 `artisan serve` workers.
  - Confidence: MED. The effect depends on the cache driver's lock support.
  - Blind spot: the cache store configured in production has not been checked.
- **Decision**: FIXED via Fix A (cap 25 Mpx; plan figures corrected at lines 75, 146, 152, 389)

### F3 — Storage write results are ignored, so a row can point at a missing file

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Scans/StoreScan.php:27,36
- **Detail**:
  - The `local` disk has `'throw' => false` (config/filesystems.php:36). On a full disk or a permissions error, `putFileAs()` and `put()` return `false`, and the row is still created.
  - The preview then 404s on `scans.file`.
  - A failed thumbnail write leaves `has_thumbnail = true`, so the grid shows a broken image instead of the placeholder.
- **Fix**: If `putFileAs` returns `false`, throw before the insert. If the thumbnail `put` returns `false`, set `$thumbnailPath = null`.
- **Decision**: FIXED

### F4 — A filename whose ASCII form is only `%` makes `scans.file` return 500

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/ScanController.php:75-76
- **Detail**:
  - The guard checks `Str::ascii($name) !== ''`. Laravel's `FilesystemAdapter::fallbackName()` (vendor FilesystemAdapter.php:421-424) additionally strips `%`, so for names like `%` or `😀%` the fallback ends up empty.
  - Symfony's `HeaderUtils::makeDisposition` then falls back to the raw name and throws, which gives a 500. This was reproduced in Sail by the review sub-agent.
  - Only the uploader can hit it, but it is user-controlled input that causes a server error.
- **Fix**: Mirror Laravel's fallback: use `str_replace('%', '', Str::ascii($name)) !== ''` as the condition, and add `%` and emoji-only names to a test dataset.
- **Decision**: FIXED (test `scan file builds a safe download name from the original name`, 4-case dataset; it fails without the fix)

### F5 — `imagedestroy()` is deprecated in PHP 8.5 and does nothing, so the full bitmap is never freed early

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Scans/GenerateScanThumbnail.php:54,69,117,134
- **Detail**:
  - Verified on PHP 8.5.10: "Function imagedestroy() is deprecated since 8.5, as it has no effect since PHP 8.0".
  - The plan's "free the full-size image right after scaling" step therefore doesn't happen: `$image` stays alive until `handle()` returns.
  - Peak memory doesn't double, because later allocations are thumbnail-sized.
  - Each upload emits deprecation noise once `LOG_DEPRECATIONS_CHANNEL` is set.
- **Fix**: Replace the calls with `$image = null;` (and the same in the helpers), which actually releases the GdImage.
- **Decision**: FIXED (`unset()` in `handle()`; the calls were removed from the helpers, where the caller's reassignment releases the images)

### F6 — Several tests are weaker than the plan specifies; one can pass without testing anything

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/ScansTest.php:252-265 (plus 110-111, 118-142, 30-34); tests/Feature/GenerateScanThumbnailTest.php
- **Detail**:
  - The tree-less account-deletion test never asserts that the deletion happened: there is no `assertGuest()` or `$user->fresh()` null check. If the request fails early, it still passes.
  - The JPG store test does not assert that the file name is a UUID.
  - The PNG and WebP happy paths only assert a count of 1, not row, location and thumbnail as the Testing Strategy says.
  - The index `assertInertia` checks only count and ids, not the prop shape.
  - The "fills png transparency with white" test uses an image without alpha, so it only checks the MIME type.
- **Fix**: In the tree-less test, add `->assertRedirect('/')` plus `assertGuest()` and `expect($user->fresh())->toBeNull()`. Assert the UUID with a regex on `path`. Assert `thumbnail_path` and the file location for PNG and WebP. Add `has_thumbnail` and `uploaded_at` to the index assertion.
- **Decision**: FIXED. Changes made:
  - the tree-less deletion test asserts the redirect, `assertGuest()` and `fresh()` null;
  - the JPG store test asserts the UUID path with a regex;
  - the PNG and WebP tests assert the redirect, MIME type, file location and thumbnail;
  - the index asserts the exact 8-key prop shape;
  - the transparency test now uses a real alpha PNG and checks for a white pixel;
  - the 400 px test also checks the aspect ratio (267 px height).

### F7 — EXIF orientation handling differs between StoreScan and the thumbnail generator

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Scans/StoreScan.php:69,76-89; app/Actions/Scans/GenerateScanThumbnail.php:44,89-111
- **Detail**:
  - `StoreScan` swaps the stored dimensions for orientations 5–8, but the generator rotates only 3, 6 and 8.
  - For mirrored orientations 5 and 7 (rare from cameras), the stored width and height are swapped while the thumbnail stays sideways.
  - `readOrientation()` is duplicated in both classes, and `getimagesize` and EXIF are parsed twice per upload.
- **Fix**: Extract the orientation read into one shared helper, and handle 5 and 7 in the generator (rotate plus `imageflip`).
- **Decision**: FIXED. Details:
  - The new `app/Actions/Scans/ReadExifOrientation.php` (`handle()` plus `swapsDimensions()`) is injected into both classes.
  - The generator now handles all 8 orientations, including the mirrored ones: 2 and 4, and 5 and 7 as a rotate followed by a horizontal flip.
  - Orientations 5 and 7 have no fixture test (there is no EXIF writer). `getimagesize` still runs twice per upload.

### F8 — The Scan model lacks `casts()`, and factory paths ignore the owning tree

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Models/Scan.php; database/factories/ScanFactory.php:22,38
- **Detail**:
  - The plan says Scan "follows the model conventions: … `casts()`". Siblings have one (Person.php:36, Event.php:40); Scan does not.
  - The factory builds `path` and `thumbnail_path` as `scans/{random 1..1000}/…` instead of `scans/{tree_id}/…`. That breaks the "file lives under its tree's directory" invariant for any future cleanup test that uses factory rows.
- **Fix**: Add `casts()` (`size`, `width`, `height` → integer). Build the factory paths with `fn (array $attributes) => "scans/{$attributes['tree_id']}/".Str::uuid().'.jpg'`.
- **Decision**: FIXED

### F9 — The scan-privacy invariant relies on conventions not enforced where the files live

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architecture
- **Location**: config/filesystems.php:35; app/Http/Controllers/Settings/ProfileController.php
- **Detail**:
  - The `local` disk that holds the scans still has `'serve' => true`, which registers the signature-only `storage.local` route. Nothing calls `temporaryUrl()` or `Storage::url()` today (grepped app/, routes/, resources/js), but a future call on a scan path would bypass `ScanPolicy`. The plan's critical rule is "never serve scans through a signed URL".
  - Separately, file cleanup lives only in `ProfileController::destroy`, so any other path that deletes a user or tree (cascade, a future admin action) orphans files.
- **Fix**: Set `'serve' => false` on the `local` disk. Nothing uses it; confirm with `route:list --path=storage`. Leave cleanup where it is until a second deletion path appears (then move it to a `Tree` `deleting` hook).
  - Strength: closes the bypass structurally instead of by convention, with a one-line config change.
  - Tradeoff: if a future feature wants signed local URLs, it must re-enable serving or use a separate disk.
  - Confidence: HIGH. Nothing in the repo references the serve route.
  - Blind spot: whether any starter-kit feature (e.g. avatars) is expected to use `local` serving later.
- **Decision**: FIXED. Details:
  - The `local` disk is now `'serve' => false`; `route:list --path=storage` shows no routes.
  - A regression test asserts that `storage.local` is not registered.
  - Cleanup stays in `ProfileController` as recommended.

### F10 — Toolkit update bundled into the feature commit

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: CLAUDE.md, .claude/.10x-cli-manifest.json (commit 8ca2b9e)
- **Detail**:
  - The 10x-cli lesson switch (Lesson 3 → Lesson 5 instructions, plus a 225-line manifest diff) landed inside the "Scan backend (p1)" commit.
  - It is unrelated to S-02 and makes the feature commit harder to revert or review cleanly.
  - The only other unplanned file, `context/foundation/roadmap.md`, is an expected status flip.
- **Fix**: No change to history. Keep toolkit and CLAUDE.md updates in their own `chore:` commits going forward.
- **Decision**: FIXED (process only: history left as is; future toolkit updates go in separate `chore:` commits)

## Triage Summary (2026-09-24)

| Outcome | Findings | Count |
|---|---|---|
| Fixed | F2 (Fix A), F3, F4, F5, F6, F7, F8, F9, F10 (process) | 9 |
| Fixed differently | F1 (`maxScale` 32) | 1 |
| Skipped / Accepted / Rule | none | 0 |

Checks after triage, all passing:
- pint;
- full suite (93 tests, 362 assertions);
- phpstan (0 errors);
- `tsc`, `npm run check` and `npm run build`.
