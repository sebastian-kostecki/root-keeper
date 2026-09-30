<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Scans Become Sources

- **Plan**: context/changes/scans-become-sources/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-09-29
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

Automated checks re-run in this review: 177 feature tests pass (1227 assertions), `npm run check`, `npm run types:check`, phpstan and pint pass, `route:list --path=scans` lists nothing, no stray `scan` identifiers.

## Findings

### F1 — Screenshot gate not completed, yet Progress 3.4 is checked

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: context/changes/scans-become-sources/screenshots/verdict.md:1-4 and screenshots/before/
- **Detail**: Plan Phase 3 requires a `before/` baseline shot on `main` and a verdict with no before→after regression in thumbnails, zoom and layout. `before/` is empty. `verdict.md` is titled "first draft", has a "Pending" section, and most state variants are "not inspected" (`*-preview-other` not at all). Progress 3.4 is nevertheless `[x]`. Thumbnails in `after/` are broken-image placeholders (factory rows without files), so thumbnail/zoom regressions cannot be judged from them.
- **Fix**: Shoot `before/` on `main` (`shots.mjs <dir> before`), inspect the remaining variants, finish `verdict.md`, and only then keep 3.4 checked. Thumbnail/zoom rely on the real-data rehearsal (3.5), which the plan records as done.
  - Strength: Closes the gate the plan defines; the script already supports the `before` flavour.
  - Tradeoff: Needs a checkout of `main` plus a re-seed.
  - Confidence: HIGH — evidence is the empty directory and the draft verdict.
  - Blind spot: 3.5/3.6 may have covered the visual regression by hand without recording it.
- **Decision**: FIXED (marked fixed at the user's instruction; the `before/` baseline from `main` was not shot and `verdict.md` still reads as a draft)

### F2 — Schema (rename) migration has no test

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/SourceMigrationTest.php (no case for database/migrations/2026_09_29_100000_rename_scans_to_sources.php)
- **Detail**: Tests cover the file-move migration only. Pivot-row survival, the `kind = metrical_record` backfill, dropping the default, and `down()` are unverified by tests (the "confirmation carry-over" test runs on the already-migrated schema). The plan lists tests only for the file move, so this is a plan gap rather than drift; the live schema was checked by hand (kind NOT NULL, no default).
- **Fix**: Add a test that rolls back one step, seeds `scans`/`event_scan`, runs `up`, and asserts preserved pivot rows, `kind` backfill, insert-without-kind fails, and `down` restores the old names.
- **Decision**: FIXED (four cases added to tests/Feature/SourceMigrationTest.php: links survive under their ids, `kind` backfill, insert without `kind` rejected, `down` restores the old names)

### F3 — Uploads by old code during the deploy window can leave rows pointing at `scans/`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_09_29_100100_move_source_files_to_sources_prefix.php:48-52; change.md runbook
- **Detail**: The file move has no lock. A file written to `scans/` after the row loop is swept to `sources/` as an orphan, but its row keeps a `scans/…` path, so the file/thumbnail routes 404. Low likelihood for a small single-tenant app.
- **Fix**: Add "maintenance mode on for the deploy" to the runbook (or repoint rows once more after the orphan sweep). The runbook's post-deploy `path like 'scans/%'` check would already reveal it.
- **Decision**: FIXED (runbook in change.md: maintenance mode on as step 1, off as step 6; the migration is unchanged)

### F4 — Store/Delete file failures are not fully handled

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Sources/StoreSource.php:33-44; app/Actions/Sources/DeleteSource.php:20-22
- **Detail**: If the thumbnail write throws, the already-written original stays orphaned (only `create()` failure cleans up). `DeleteSource` ignores a `false` from `Storage::delete`. Both behaviours were inherited from the scan code and only leave orphan files, never dangling rows.
- **Fix**: Optional: widen the try block in `StoreSource`; report failed deletes. Accept as inherited otherwise.
- **Decision**: FIXED (`StoreSource` cleans up on any failure after the original is written; `DeleteSource` reports a failed file delete without failing the request; one test each in tests/Feature/SourcesTest.php)

### F5 — Linking a foreign-tree source returns 422 rather than 404; two queries

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Requests/EventSourceStoreRequest.php:33-44
- **Detail**: CLAUDE.md says another user's record is a 404. Here it is a validation error with the same generic message as a nonexistent id (no existence leak), which is how the pre-change `EventScanStoreRequest` behaved and what the plan specifies. The `exists` rule and the akt closure are two queries.
- **Fix**: Accept; optionally fold into one `Rule::exists(...)->where('kind', …)` only if the two messages can stay distinct.
- **Decision**: SKIPPED
