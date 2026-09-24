<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Scan Upload and Preview Implementation Plan

- **Plan**: context/changes/scan-upload-and-preview/plan.md
- **Mode**: Deep
- **Date**: 2026-09-24
- **Verdict**: REVISE → SOUND (all 7 findings fixed in plan)
- **Findings**: 1 critical, 3 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
Grounding: 16/16 paths ✓, 8/8 symbols ✓ (ServeFile signature check, local disk serve=true, mem_limit 1g, CLI memory_limit -1, gd+exif in prod, lang/pl validation, sidebar/Wayfinder imports, backup includes storage/app), brief↔plan ✓, Progress↔Phase ✓ (1.1–1.6, 2.1–2.11)

## Findings

### F1 — Account cleanup can wipe every tree's scans when a user has no tree

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §9 — File cleanup on account deletion
- **Detail**: `User::tree()` is a plain `HasOne` (app/Models/User.php:55-57), so `$user->tree` is `?Tree`. No DB constraint requires a tree, and `UserFactory::withoutTree()` (database/factories/UserFactory.php:33-38) creates tree-less users that tests already use (tests/Feature/TreeBackfillTest.php:22). With a null id, `deleteDirectory("scans/{$treeId}")` becomes `deleteDirectory("scans/")`. Flysystem normalises that to `scans`, which deletes all users' scans. Registration and the backfill make this unlikely, but the failure is irreversible data loss.
- **Fix**: In §9, capture `$treeId = $user->tree?->id` and call `deleteDirectory` only when `$treeId !== null`. Add a test case: "deleting an account without a tree leaves other trees' scans intact".
- **Decision**: FIXED

### F2 — Unit test for the thumbnail generator won't boot Laravel

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §5, §10; Progress 1.1; Testing Strategy
- **Detail**: tests/Pest.php:17-19 binds `TestCase` and `RefreshDatabase` only to `->in('Feature')`. tests/Unit holds pure PHPUnit tests. `GenerateScanThumbnail` calls `report()`, which needs the container, so the "corrupt input returns null" case would error in tests/Unit. The plan also leaves the pixel-cap test undecided ("a small cap override or a synthetic header"), yet the §5 contract exposes no way to override the cap.
- **Fix**: Move the test to `tests/Feature/GenerateScanThumbnailTest.php` and update 1.1's command. Give the generator a constructor parameter `int $maxPixels = 40_000_000` so the test can pass a small cap.
- **Decision**: FIXED

### F3 — The "text renamed to .jpg" test case can't be built with UploadedFile::fake()

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §10 — rejection dataset
- **Detail**: `Testing\File::getMimeType()` returns the MIME type passed in, or falls back to `MimeType::from($this->name)` (Http/Testing/File.php:132-135). It never sniffs content. So `UploadedFile::fake()->createWithContent('akt.jpg', 'text')` reports image/jpeg and passes `image` + `mimes`. The test would fail, or be "fixed" by weakening it. Production is safe, because a real upload is sniffed with finfo.
- **Fix**: Specify that this case uses a real temp file: `new UploadedFile($tmpPathWithText, 'akt.jpg', null, null, true)`, which makes finfo detect text/plain. The other rejections use explicit non-image MIME types (e.g. `create('akt.pdf', 10, 'application/pdf')`).
- **Decision**: FIXED

### F4 — EXIF rotation before scaling doubles peak GD memory

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §5 steps 3–4; Critical Implementation Details (memory guard)
- **Detail**: The plan applies `imagerotate` to the full-size decoded image and only then calls `imagescale`. `imagerotate` allocates a second full-size bitmap, so a 40 Mpx image peaks at about 400 MB, not the budgeted ~200 MB. Two concurrent uploads could therefore approach the 1 GB container limit (4 `artisan serve` workers, `memory_limit = -1`).
- **Fix**: Swap steps 3 and 4. Scale to width 400 first, then rotate the small thumbnail. For orientations 6/8, scale so the *rotated* width is 400, i.e. scale the source height to 400. Update the "≈200 MB" note to state that the peak is one full-size bitmap plus the thumbnail.
- **Decision**: FIXED

### F5 — Unsanitised original_name can make the file response throw

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §6 (original_name), §7 (`file()` response)
- **Detail**: `Storage::response()` builds Content-Disposition via Symfony `HeaderUtils::makeDisposition`. That throws `InvalidArgumentException` when the name contains `/` or `\` or control characters, or when `Str::ascii` yields an empty fallback (e.g. an all-emoji name). Polish diacritics are fine ("Łódź" → "Lodz").
- **Fix**: In `StoreScan`, strip control characters and `/`/`\` from the client name before truncating to 255 characters, and fall back to `skan.{ext}` if the result is empty.
- **Decision**: FIXED

### F6 — CI doesn't pin gd/exif, which the new tests require

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Key Discoveries ("GD is present … in the CI PHP setup (verify in phase 1)")
- **Detail**: .github/workflows/tests.yml:23-40 uses `shivammathur/setup-php` with no `extensions:` key. gd and exif usually come by default, but nothing guarantees them. Because of the `function_exists('exif_read_data')` guard, a missing exif would fail the EXIF-rotation test rather than skip it. The "verify" step is vague.
- **Fix**: Add a Phase 1 change: `extensions: gd, exif` in the tests.yml setup-php step.
- **Decision**: FIXED

### F7 — Stored width/height are pre-orientation for EXIF-rotated photos

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §6 (dimensions from `getimagesize`); Phase 2 §5 (show page lists pixel dimensions)
- **Detail**: `getimagesize` returns the raw sensor dimensions. For a phone photo with orientation 6/8, the preview (which browsers show upright) would list 4000 × 3000 for a portrait image. This is cosmetic, but it contradicts what the user sees.
- **Fix**: Swap width/height in `StoreScan` when EXIF orientation is 5–8. Alternatively, accept the mismatch and drop dimensions from the show page.
- **Decision**: FIXED
