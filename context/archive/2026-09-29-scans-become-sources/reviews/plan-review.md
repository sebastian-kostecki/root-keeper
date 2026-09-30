<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Scans Become Sources

- **Plan**: context/changes/scans-become-sources/plan.md
- **Mode**: Deep
- **Date**: 2026-09-29
- **Verdict**: REVISE → SOUND (all 7 findings fixed in plan)
- **Findings**: 1 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding

20/20 paths ✓, 9/9 symbols ✓ (withinTransaction, Gender::options, scans_exists, availableScans, attachScan, ProfileController:60, ToggleGroup+hidden input, Postgres compileRename, wayfinder gitignore), brief↔plan ✓ except one promise (F4); Progress↔Phase ✓ (9/9/6).

## Findings

### F1 — Prod file move creates root-owned 0700 dirs the app can't read

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details (file move); Phase 3 §3 runbook
- **Detail**: `scripts/deploy.sh:40` runs `$COMPOSE exec -T app php artisan migrate --force` without `-u`. `docker/8.5/Dockerfile.prod` has no `USER` line (gosu only wraps the entrypoint CMD, not `exec`), so migrate runs as root. The `local` disk sets no `directory_visibility`, so Flysystem creates the parents of a move with `Visibility::PRIVATE` = 0700 (`FilesystemManager.php:184`, `PortableVisibilityConverter.php:16`). `sources/`, `sources/{tree}/` and `thumbnails/` therefore become root:root 0700. PHP runs as sail, so every moved file 404s and new uploads fail. The chown in `deploy.sh:37` runs before migrate. Local Sail runs as sail, so neither the tests nor the rehearsal can catch this.
- **Fix A ⭐ Recommended**: Run migrate as sail in `deploy.sh` (`exec -T -u sail app php artisan migrate --force`). Add `deploy.sh` to Phase 3, and add a runbook post-check: open an old thumbnail and upload a test source.
  - Strength: Fixes the whole class (any root-created file); matches the PHP runtime user.
  - Tradeoff: Touches shared deploy tooling.
  - Confidence: HIGH — Dockerfile, compose and Flysystem defaults read directly.
  - Blind spot: Not executed against the prod image.
- **Fix B**: Runbook step after `deploy.sh`: `exec -u root app chown -R sail:sail storage`.
  - Strength: No change to deploy tooling.
  - Tradeoff: Manual step, and every source 404s between migrate and the chown.
  - Confidence: HIGH
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A)

### F2 — Rehearsal uses seeder data that has no files, so the gate can't pass

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 Manual (1.8); Phase 3 Manual (3.5); Manual Testing Steps 1
- **Detail**: `ScanFactory` writes rows only. A seeded-then-migrated DB reports every row as missing and leaves it under `scans/`, so 1.8 ("working thumbnails") and 3.5 ("no scans/ paths") can't pass.
- **Fix**: On main, upload 3+ real images via /scans/create (one linked to an event) before migrating. Expect those to move and exactly the seeded rows to be reported missing. Reword 1.8, 3.5 and Manual Testing step 1.
- **Decision**: FIXED

### F3 — Move algorithm: silent move() failure and undecided per-file cases

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details — file move steps 1–4
- **Detail**: The `local` disk is `'throw' => false`, so `Storage::move()` returns false instead of throwing. An unchecked move would repoint the row at a missing file. "Both are missing" is also ambiguous: old+new of one file, or original+thumbnail? The mixed case has no stated outcome.
- **Fix**: Resolve `path` and `thumbnail_path` independently: old exists → move, and throw if it returns false; old missing and new exists → adopt new; both missing → keep old and report. Add the mixed case to SourceMigrationTest.
- **Decision**: FIXED

### F4 — Leftover scans/ files outlive account deletion

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: End-State Alignment
- **Location**: Critical Implementation Details (dir cleanup); Phase 1 §6 ProfileController
- **Detail**: Only empty `scans/{tree}` dirs are removed, and orphan files keep a dir alive. `ProfileController::destroy` will delete only `sources/{treeId}`, so a deleted account's documents stay on disk. The brief promises "no files … left under scans/".
- **Fix**: After the row loop, move the remaining (unreferenced) files under `scans/` to the same path under `sources/`, delete `scans/`, and report the orphan count.
- **Decision**: FIXED

### F5 — Rollback order and kind loss not written down

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Migration Notes
- **Detail**: `migrate:rollback --step=2` needs the new code (which has the migration files) to still be deployed. deploy.sh pulls main ff-only, so going back means a revert commit. `down()` drops `kind`, so gravestone/other sources uploaded after the deploy become scans.
- **Fix**: Document the order (rollback on new code → revert on main → deploy.sh) and the kind loss in Migration Notes.
- **Decision**: FIXED

### F6 — One exists-rule conflates "not yours" with "wrong kind"

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 1 §6 EventSourceStoreRequest
- **Detail**: A single `Rule::exists(...)->where('tree_id')->where('kind')` shows either the „tylko akt metrykalny” message for another tree's id, or the generic message for a gravestone.
- **Fix**: Keep the tree-scoped exists rule, add a separate kind check with the Polish message, and test both.
- **Decision**: FIXED

### F7 — Two files and the NOT NULL backfill order are missing

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §2, §7
- **Detail**: `person-list-parts.tsx:4` and `section-editor.tsx:209` mention "scan" but are not in §7, and gate 1.4 will flag them. §2 never gives the order for adding NOT NULL `kind` to a non-empty table on Postgres.
- **Fix**: Add both files to §7. In §2, add with `default('metrical_record')` and then drop the default in the same migration.
- **Decision**: FIXED
