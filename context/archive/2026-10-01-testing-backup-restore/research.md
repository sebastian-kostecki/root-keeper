---
date: 2026-10-01T06:06:28+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: a8b8be118d70813269ba487d267e2f7f6bf1d273
branch: docs/test-plan
repository: root-keeper
topic: "Test plan Phase 1 — does the backup cover the database and every source file, and can a restore on a clean instance be proven?"
tags: [research, backup, restore, spatie-laravel-backup, sources, ci, quality-gates, test-plan-phase-1]
status: complete
last_updated: 2026-10-01
last_updated_by: Claude (Opus 5.5)
---

# Research: Backup coverage and restore proof (test plan Phase 1, risk #2)

**Date**: 2026-10-01T06:06:28+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: a8b8be118d70813269ba487d267e2f7f6bf1d273 (working tree: untracked `context/changes/testing-backup-restore/`)
**Branch**: docs/test-plan
**Repository**: root-keeper

## Research Question

Ground risk #2 of `context/foundation/test-plan.md` (§2, row 2 and its Risk Response Guidance row) for §3 Phase 1 „Kopia i odtworzenie”:

> Kopia zapasowa nie obejmuje wszystkich danych (w tym plików źródeł po zmianie skany → źródła) albo odtworzenie na czystej instancji nie przywraca kompletnych danych z czytelnymi plikami.

The test plan asks `/10x-research` to ground: the backup configuration and file paths after the rename; the F-01 restore procedure; the existing backup-schedule test; what can be checked automatically and what needs a drill on the server. §5 additionally asks Phase 1 research to confirm the typecheck gate is green.

## Summary

- **By configuration, the backup covers the database and every source file.** It dumps the default connection (pgsql) and zips all of `storage/app` minus `backups`, `backup-temp` and `framework` (`config/backup.php:29-48`). Source originals and thumbnails are written to the `local` disk (root `storage/app/private`, `config/filesystems.php:33-35`) under `sources/{tree_id}/` and `sources/{tree_id}/thumbnails/` (`app/Actions/Sources/StoreSource.php:33,47`), which is inside the include path and outside every exclude. A grep of `app/`, `config/`, `bootstrap/`, `routes/`, `docker/`, `scripts/`, `resources/js` and the env/compose files found no storage path still pointing at `scans/` outside the migrations.
- **Nothing proves that coverage today.** The only backup test (`tests/Feature/BackupScheduleTest.php:7-38`) asserts four config values and three schedule entries; it never runs `backup:run`, opens an archive or restores. The package's own `verify_backup` (`config/backup.php:77`) checks only that the zip opens, is non-empty and has as many entries as the manifest (`vendor/spatie/laravel-backup/src/Tasks/Backup/BackupJob.php:269-291`) — a file that was never in the manifest, or a broken dump, passes it.
- **Restore has been proven once, narrowly, and before the rename.** spatie/laravel-backup 10.3.3 ships no restore command (four commands in `vendor/spatie/laravel-backup/src/Commands/`: run, clean, list, monitor), and the repo has no restore script. The only procedure is a manual runbook in the sibling repo (`../kostecki-dev-infra/docs/root-keeper.md:132-199`), drilled once on 2026-09-22 with a hand-placed test file, comparing only `users` and `migrations` counts. The real-scan drill (S-02 item 2.11) is still unchecked, and no drill ran after the scans → sources rename. The full production restore is written down as „not drilled” (runbook `:186`).
- **A CI integration test of backup contents is feasible in the existing `tests` job** (PostgreSQL 18 service), but it must step around three traps: the global `RefreshDatabase` transaction is invisible to `pg_dump`; `Storage::fake` writes outside the backup source; and the runner's `pg_dump` must be 18. An automated *restore* (load the dump into a second database and compare counts) is possible only if the job gets a `psql`/`pg_dump` 18 client.
- **Gates:** typecheck is green — Larastan 0 errors and `tsc --noEmit` exit 0 locally (2026-10-01), and `static-analysis` passed on the latest `main` push. Separately, the CI `lint` workflow is **failing** on `main` at the „Run frontend unit tests (Vitest)” step, while the same suite passes locally (7 files, 124 tests). The cause is unknown.

## Detailed Findings

### 1. What the backup contains (configuration)

- Package: `spatie/laravel-backup` `^10.3` (`composer.json:19`), locked at 10.3.3 (`composer.lock:4169-4170`).
- Database: `databases => [env('DB_CONNECTION', 'pgsql')]` (`config/backup.php:46-48`). Dump file: plain `pg_dump` SQL, no compressor, filename base `database` (`config/backup.php:51-57`). The name is built as `{dbType}-{dbName}{timestamp}.{ext}` (`BackupJob.php:322`), so the entry is `db-dumps/postgresql-<DB_DATABASE>.sql`. The `pgsql` connection has no `dump` key or `dump_binary_path` (`config/database.php:87-100`), so `pg_dump` is taken from `PATH`.
- Files: include `storage_path('app')` (`config/backup.php:29-31`); exclude `app/backups`, `app/backup-temp`, `app/framework` (`:33-37`); `follow_links => false` (`:39`); `ignore_unreadable_directories => false` (`:41`, an unreadable directory fails the run instead of being skipped); `relative_path => storage_path('app')` (`:43`). Entry names strip that relative path (`vendor/spatie/laravel-backup/src/Tasks/Backup/Zip.php:61-63`), so a source file appears as `private/sources/<tree_id>/<uuid>.<ext>`.
- Destination: disk `backups` (local, root `storage/app/backups`, `config/filesystems.php:63-68`); `backups-offsite` (S3) is added only when `BACKUP_OFFSITE_ENABLED` is true (`config/backup.php:14-20`), and the deploy preflight refuses that setting (`scripts/lib/production-deploy.sh:74-76`). In production the `backups` disk is bind-mounted to `${BACKUP_HOST_DIR:-/storage/root-keeper-backups}` on the same VPS (`docker-compose.prod.yml:21`). The archive lands at `<disk>/<APP_NAME>/<Y-m-d-H-i-s>.zip` (`BackupJob.php:31`; `BackupDestination.php:67`).
- Encryption: AES-256 when `BACKUP_ARCHIVE_PASSWORD` is set, `none` otherwise (`config/backup.php:73-75`). The production preflight requires the password (`scripts/lib/production-deploy.sh:66`). `phpunit.xml` does not set it, so in tests the archive is unencrypted.
- Retention/monitor: `DefaultStrategy`, all backups for 7 days, dailies for 7 days, weekly/monthly/yearly 0, oldest deleted above 5000 MB (`config/backup.php:141-161`); monitor `MaximumAgeInDays 1`, `MaximumStorageInMegabytes 5000` (`:130-139`). Notifications by mail on failure and unhealthy backups only (`:84-103`).

### 2. Where source files live (the data the backup must contain)

- Original: `Storage::disk('local')->putFileAs("sources/{$tree->id}", …)`; the row's `path` is `sources/{tree_id}/{uuid}.{ext}` (`app/Actions/Sources/StoreSource.php:33-37`).
- Thumbnail: `sources/{tree_id}/thumbnails/{uuid}.jpg` (`StoreSource.php:47-51`). `thumbnail_path` is `null` when no thumbnail was generated or the write failed (`StoreSource.php:39,45-51`) — a completeness check must allow a null thumbnail.
- Both paths are stored on the `sources` row (`path`, `thumbnail_path`; `app/Models/Source.php:28,36`). This gives the natural invariant for a test: *for every `sources` row, `private/<path>` (and `private/<thumbnail_path>` when not null) is an entry in the archive, with the same bytes.*
- In the inspected `app/` paths, source files are written to the `local` disk only; nothing writes to `public` or `s3`.
- Rename (S-16): `database/migrations/2026_09_29_100000_rename_scans_to_sources.php:16-20` renames the tables and FK column; `2026_09_29_100100_move_source_files_to_sources_prefix.php` moves files `scans/` → `sources/` row by row. A row whose file was missing keeps its `scans/…` path and is only logged (`:52-55`). Such a row points at a file the backup cannot contain. Whether any exist in production is unknown (needs `select count(*) from sources where path like 'scans/%' or thumbnail_path like 'scans/%'` on production).

### 3. When backups run

- Scheduled in `bootstrap/app.php:31-52`, production only, `Europe/Warsaw`, `onOneServer()`: `backup:run` 03:45, `backup:clean` 04:15, `backup:monitor` 04:45. Run by `schedule:work` under supervisor in the app container (`docker/8.5/supervisord.prod.conf:23-29`).
- Before each deploy: `backup:run --only-db` (`scripts/lib/production-deploy.sh:155-165`, called from `scripts/deploy.sh:31`) — **database only**, and skipped when the app container is not running (`production-deploy.sh:158-161`). A migration that moves files (as S-16 did) therefore has no automatic file snapshot right before it; S-16 covered this with a manual full `backup:run` in its runbook (`context/archive/2026-09-29-scans-become-sources/change.md:14-32`).

### 4. Existing tests

- `tests/Feature/BackupScheduleTest.php:7-38` — the only backup test. Asserts `files.include === [storage_path('app')]`, `exclude` contains `app/backups`, `databases === [config('database.default')]`, `disks` contains `backups` (`:8-11`), and the three cron expressions, timezone and `['production']` environment (`:24-37`). It does not execute a backup.
- Source-file tests (`tests/Feature/SourcesTest.php`, e.g. `:123`, `:145`, `:168-171`) use `Storage::fake('local')`, whose root is `storage/framework/testing/disks/<disk>` (`vendor/laravel/framework/src/Illuminate/Support/Facades/Storage.php:157`) — outside the backup's include path, so a backup run in such a test would not see those files.
- All Feature tests use `RefreshDatabase` (`tests/Pest.php:22-24`). On pgsql this wraps each test in a transaction; `pg_dump` connects separately and does not see uncommitted rows.

### 5. Restore procedure

- No restore code in this repo; spatie 10.3.3 has no restore command and `composer.lock` has no restore package (only `spatie/db-dumper` 4.1.1).
- Runbook: `../kostecki-dev-infra/docs/root-keeper.md:132-199` („Restore”). Steps: locate `<APP_NAME>/<ts>.zip`; decrypt with `7z` in a throwaway container (plain `unzip` cannot open AES-256); load `db-dumps/postgresql-<db>.sql` into a throwaway `postgres:18-alpine` with `psql -v ON_ERROR_STOP=1`, with `POSTGRES_USER` equal to `DB_USERNAME` because of `OWNER TO`; compare `\dt` and `count(*)`; copy `private/` back into `storage/app/private`; clean up.
- The runbook text still says „scans under `private/…`” (`:139`); the path structure is still correct (`private/…`), only the wording is stale.
- Full production restore: listed in order but „not drilled” (`:186-192`).

### 6. What can be automated vs what needs the server

| Check | Automatable in CI? | Notes |
|---|---|---|
| Archive contains the DB dump | Yes | `Artisan::call('backup:run', ['--disable-notifications' => true, '--filename' => …])`, then `ZipArchive` lists `db-dumps/postgresql-testing.sql` |
| Archive contains every source original and thumbnail, byte-identical | Yes | Write real files under `storage/app/private/sources/…` (not `Storage::fake`), compare entries and sha256 against `sources.path` / `thumbnail_path` |
| Dump contains committed domain rows | Yes, with isolation change | Needs `DatabaseMigrations`/`DatabaseTruncation` (or a separate test directory without the global `RefreshDatabase`) so rows are committed |
| Dump restores into a clean database with equal counts | Conditional | Needs `psql` 18 on the runner (or `docker exec` into the service container); CI user is `sail`, so `OWNER TO sail` matches (`.github/workflows/tests.yml:19-39`) |
| Encrypted (AES) archive opens with the password | Uncertain | Depends on libzip AES support in the setup-php build — unverified |
| Restore of the *production* archive on a clean instance, files open in the app | No — drill | Off-VPS password, real data, `7z`, real volume; the runbook is the procedure |
| Production rows still pointing at `scans/…` | No — one-off query on production | See §2 |

### 7. Quality gates (§5 of the test plan)

- Typecheck: `vendor/bin/sail composer types:check` → phpstan `passed`, 0 errors; `vendor/bin/sail npm run types:check` → exit 0 (run 2026-10-01 at commit a8b8be1). CI `static-analysis` succeeded on the latest `main` push (run 36726651330). The §5 note „stan zielony do potwierdzenia w badaniu fazy 1” is confirmed.
- CI `tests` job: succeeded on the latest `main` push (run 36726651346). Service `postgres:18-alpine`, db `testing`, user `sail`, `DB_HOST=127.0.0.1` (`.github/workflows/tests.yml:19-39`); no `postgresql-client` install step. The `pg_dump` version on `ubuntu-latest` was not checked on a runner; per `docker/8.5/Dockerfile.prod:82-83`, Ubuntu's own client (16.x) cannot dump a PG 18 server.
- CI `lint` job: failed on all 3 `lint` runs among the 8 most recent workflow runs (`gh run list --limit 8`, 2026-10-01), including `main` push run 36726651377. Pint and `vp check` pass; the „Run frontend unit tests (Vitest)” step exits 1. `gh run view --log-failed` returned no log, so the cause is unknown. Locally `vendor/bin/sail npm run test:js` passes (7 files, 124 tests, exit 0). The §5 gate „frontend unit (Vitest) — required” is therefore red in CI today, independent of this phase.

## Code References

- `config/backup.php:14-20` — destination disks; offsite only when enabled
- `config/backup.php:29-48` — files include/exclude, `relative_path`, databases
- `config/backup.php:73-77` — password, encryption, `verify_backup`
- `config/filesystems.php:33-35` — `local` disk root `storage/app/private`
- `config/filesystems.php:63-80` — `backups` and `backups-offsite` disks
- `app/Actions/Sources/StoreSource.php:33-51` — original and thumbnail paths
- `app/Models/Source.php:28,36` — `path`, `thumbnail_path`
- `database/migrations/2026_09_29_100100_move_source_files_to_sources_prefix.php:52-55` — missing-file rows keep `scans/` path
- `bootstrap/app.php:31-52` — backup schedule
- `scripts/lib/production-deploy.sh:155-165` — pre-deploy DB-only backup
- `tests/Feature/BackupScheduleTest.php:7-38` — config + schedule assertions
- `tests/Pest.php:22-24` — global `RefreshDatabase`
- `vendor/laravel/framework/src/Illuminate/Support/Facades/Storage.php:157` — fake disk root
- `vendor/spatie/laravel-backup/src/Tasks/Backup/BackupJob.php:269-291` — `verifyBackup` (count only)
- `vendor/spatie/laravel-backup/src/Tasks/Backup/BackupJob.php:300-330` — dump file naming
- `vendor/spatie/laravel-backup/src/Tasks/Backup/Zip.php:50-66` — entry naming
- `.github/workflows/tests.yml:19-39` — Postgres 18 service, env
- `../kostecki-dev-infra/docs/root-keeper.md:132-199` — restore runbook (sibling repo)

## Architecture Insights

- Backup coverage is defined by *location*, not by an inventory: everything under `storage/app` is in, so new file types are covered automatically as long as they are written to a disk rooted there. The flip side is that the only proof of completeness is a comparison against the database's own record of files (`sources.path`, `thumbnail_path`) — this is the oracle a test should use, not a re-run of the backup's manifest.
- Backup and restore are split across two repos: backup config and schedule here, restore procedure in `kostecki-dev-infra`. The test plan's cookbook §6.3 expects the pattern to be documented here.

## Historical Context (from prior changes)

- `context/archive/2026-09-22-production-data-safety/plan.md` (F-01) — set up the encrypted nightly backup, mail alerts, deploy preflight and the restore runbook. Drill items 4.1–4.6 ticked in commit `6de5189` (`plan.md:330-342`). **Supported:** a drill ran on 2026-09-22 (runbook `:134`). **Partial:** it verified a hand-placed test file's sha256 and the `users`/`migrations` counts only — no domain tables, scan or thumbnail existed yet. Off-site copy, heartbeat and backup Pest tests were explicitly deferred (`plan.md:36-44`).
- `context/archive/2026-09-24-scan-upload-and-preview/plan.md:442` — item 2.11 (upload a real scan, `backup:run`, restore into a throwaway instance, open the file) is `[ ]`; the change was archived with it open. No later document records it as done.
- `context/archive/2026-09-29-scans-become-sources/` (S-16) — renamed tables and moved files; did not change `config/backup.php` or the restore runbook; added a manual full backup to its production runbook (`change.md:14-32`). Nothing there requires the drill to be redone.
- `context/foundation/infrastructure.md:85` — „repeat after any backup-config change”. The config did not change with the rename, but the data shape did. **Partial / stale:** the same file still describes backups as unencrypted and never restored (`:53,81,105,112-116`), which F-01 superseded.
- `context/foundation/test-plan.md:30` cites „PRD v1 NFR (RPO 24 h)”; that text is no longer in the current `prd.md` (v4). It survives in `prd-v2.md:151`, `prd-v3.md:68-69,133` and git history. **Partial:** the requirement exists, the citation points at a superseded version.
- Backup sizing is open (roadmap `:120`, Block: no): a 20 MB per-file limit from S-02 versus a 5000 MB cleanup cap could quietly shorten retention (`scan-upload-and-preview/plan.md:395`, `infrastructure.md:52,82`).

## Related Research

- Not applicable — no earlier `research.md` covers backup or restore.

## Open Questions

Decisions for `/10x-plan` (product/author choices, not missing evidence):

1. **How far the CI test goes.** (a) contents only — DB dump entry + every source file byte-identical; or (b) also restore the dump into a second database and compare row counts per table, which needs a `postgresql-client-18` step in `tests.yml`.
2. **Test isolation.** Keep the backup test under `tests/Feature` with an opt-out from the global `RefreshDatabase`, or a separate directory/group. Either way it must clean `storage/app/backups` and the files it writes.
3. **Encrypted path.** Whether to cover AES in CI (unverified libzip support) or leave encryption to the drill.
4. **Where the drill lives and how often.** Keep the runbook in `kostecki-dev-infra` and link it from §6.3, or move/copy a checklist here; drill cadence (§5 „rytm ustala faza”). The drill should compare counts of domain tables (persons, events, sources, relations), not only `users`, and open a real source and thumbnail.
5. **Pre-deploy backup scope.** The deploy-time backup is DB-only; whether a file-moving migration should trigger a full backup is a deploy-script decision outside Phase 1's test scope, but the drill/cookbook can name it.

Evidence gaps (unverified in this research):

- `pg_dump`/`psql` version available on `ubuntu-latest` runners.
- Cause of the CI Vitest failure in the `lint` job (passes locally).
- Whether production has `sources` rows still pointing at `scans/…`.
- Whether a post-rename drill happened but was not written down.
