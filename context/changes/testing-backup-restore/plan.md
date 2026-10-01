# Backup Contents and Restore Proof (Test Plan Phase 1) Implementation Plan

## Overview

Prove risk #2 of `context/foundation/test-plan.md`: the backup archive contains the database and every source file (original and thumbnail), and a restore on a clean instance returns complete data with readable files. Today nothing proves it — the only backup test checks config values and the schedule.

The work has three parts:

1. A new `tests/Backup` suite that runs the real `backup:run`, checks the archive against the database's own record of files, restores the dump into an empty database and compares row counts, and opens an AES-encrypted archive with its password.
2. CI wiring: a PostgreSQL 18 client in the `tests` job (needed by `pg_dump` and `psql`), and the fix for the Vitest step in the `lint` job, which has never passed in CI.
3. A restore drill on production after the scans → sources rename, driven by a checklist in test-plan §6.3, with the infra runbook brought up to date.

## Current State Analysis

- **Coverage by configuration.** The backup dumps the default `pgsql` connection and zips all of `storage/app` except `backups`, `backup-temp` and `framework` (`config/backup.php:29-48`). Source originals and thumbnails are written to the `local` disk (root `storage/app/private`, `config/filesystems.php:33-35`) under `sources/{tree_id}/` and `sources/{tree_id}/thumbnails/` (`app/Actions/Sources/StoreSource.php:33,47`). Entry names inside the zip are relative to `storage/app` (`vendor/spatie/laravel-backup/src/Tasks/Backup/Zip.php:61-63`), so a source file appears as `private/sources/<tree_id>/<uuid>.<ext>`, and the dump as `db-dumps/postgresql-<DB_DATABASE>.sql` (`BackupJob.php:322`).
- **No proof.** `tests/Feature/BackupScheduleTest.php:7-38` asserts config and schedule only. spatie's `verify_backup` counts manifest entries (`BackupJob.php:269-291`) — a file that was never in the manifest, or a broken dump, passes it.
- **Restore proven once, narrowly, before the rename.** The F-01 drill (2026-09-22) compared `users` and `migrations` counts and one hand-placed file. The real-scan drill (S-02 item 2.11) was never ticked. No drill ran after S-16 moved files `scans/` → `sources/`. The procedure lives in `../kostecki-dev-infra/docs/root-keeper.md:132-199` and still says „scans” (`:139`) and compares only `users`.
- **Test-isolation traps** (from research, confirmed in vendor):
  - Global `RefreshDatabase` for `tests/Feature` (`tests/Pest.php:22-24`) keeps rows in a transaction that `pg_dump` cannot see.
  - `DatabaseTruncation` truncates *before* each test after the first run (`vendor/laravel/framework/src/Illuminate/Foundation/Testing/DatabaseTruncation.php:27-43`), so rows committed by the last backup test would stay for later suites unless cleaned.
  - The real `storage/app/private/sources/` on a dev machine holds real files under tree ids `1`, `3`, `17`, and the `testing` database also numbers trees from 1 — a test writing to the real `storage/app` could overwrite dev files and would zip them into its archive.
  - `Storage::fake('local')` writes outside the backup include path (`vendor/laravel/framework/src/Illuminate/Support/Facades/Storage.php:157`).
- **spatie config is `scoped`** (`vendor/spatie/laravel-backup/src/BackupServiceProvider.php:64`) — it is built once per app instance from `config('backup')`.
- **Upload accepts images only** (`app/Http/Requests/SourceStoreRequest.php:25`), so every source normally gets a thumbnail; `thumbnail_path` is `null` only when generation fails (`StoreSource.php:39-51`).
- **Clients.** Sail's app container has `pg_dump` 18.6 (`docker/8.5/Dockerfile:75`). The CI `tests` job (`.github/workflows/tests.yml`) installs no PostgreSQL client; Ubuntu's own client is 16.x and cannot dump a PG 18 server (`docker/8.5/Dockerfile.prod:82`). In Sail, `ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)` is `true`; in CI it is unverified.
- **Vitest in CI.** The `lint` job's „Run frontend unit tests (Vitest)” step (added in `a392d82`, person-modal) has failed on every run since: `laravel-vite-plugin` refuses to start the Vite server when `CI` is set — „You should not run the Vite HMR server in CI environments … set LARAVEL_BYPASS_ENV_CHECK=1” (job log of run 36726651377). Reproduced locally with `sail exec -e CI=true … npm run test:js`; with `LARAVEL_BYPASS_ENV_CHECK=1` added, 7 files / 124 tests pass.

## Desired End State

- `vendor/bin/sail artisan test --compact tests/Backup` runs three tests that:
  - fail if any `sources` row's original or thumbnail is missing from the archive or differs in bytes;
  - fail if the dump does not restore with `ON_ERROR_STOP=1` into an empty database, or if any domain table's row count differs after restore;
  - fail if an encrypted archive cannot be opened with the backup password (skipped with a message where AES is unsupported).
- The suite never touches the developer's real `storage/app` and leaves no rows behind for other suites.
- CI `tests` runs that suite with a PostgreSQL 18 client; CI `lint` is green including Vitest.
- One restore drill after the rename is done on production and recorded; test-plan §6.3 is the canonical „how to add a backup test / run the drill” answer; §5 gates for backup content and restore drill are marked as wired, with the drill cadence set.

### Key Discoveries:

- The oracle is the database's own record of files — `sources.path` and `sources.thumbnail_path` (`app/Models/Source.php:28,36`) — not the backup's manifest (research, Architecture Insights).
- `Tests\TestCase::createApplication()` (inherited from `vendor/laravel/framework/src/Illuminate/Foundation/Testing/TestCase.php:45-62`) requires `bootstrap/app.php` and then bootstraps the kernel; calling `$app->useStoragePath()` between those two steps makes the real `config/backup.php` and `config/filesystems.php` resolve `storage_path()` to a temporary root.
- `backup:run` accepts `--disable-notifications` and `--filename` (`vendor/spatie/laravel-backup/src/Commands/BackupCommand.php:19-30`).
- Domain tables after the rename: `users`, `trees`, `people`, `events`, `event_participants`, `sources`, `event_source`, `parent_child_relations` (`database/migrations/*_rename_scans_to_sources.php:16-17`, other create migrations).
- The CI database user is `sail` and the restore runs as the same user, so the dump's `OWNER TO sail` statements succeed (`.github/workflows/tests.yml:19-39`).

## What We're NOT Doing

- **No change to the pre-deploy backup scope.** `scripts/lib/production-deploy.sh:155-165` stays `--only-db`. §6.3 names the gap (a file-moving migration has no automatic file snapshot) so the next such migration adds a manual full `backup:run`, as S-16 did.
- No restore command or restore script in this repo; the runbook in `kostecki-dev-infra` stays the technical procedure.
- No off-site copy, heartbeat or backup-size work (roadmap `:120`, open and non-blocking).
- No change to `config/backup.php`, `config/filesystems.php` or `tests/Feature/BackupScheduleTest.php`.
- No edits to test-plan §1–§3 (frozen strategy; the §3 status column belongs to the `/10x-test-plan` orchestrator). The stale „PRD v1 NFR (RPO 24 h)” citation in §2 and the stale backup lines in `context/foundation/infrastructure.md` are left for `/10x-test-plan --refresh` and a separate docs pass.
- No test of the production schedule actually firing (covered by `backup:monitor` mail alerts).
- No new `docs/` directory — the drill checklist lives in test-plan §6.3.

## Implementation Approach

Test first, then CI, then the human drill. Phase 1 builds the suite and proves it green in Sail (which already has the PG 18 client and AES). Phase 2 makes CI capable of running it and fixes the unrelated-but-required Vitest gate in the same pass over workflow files. Phase 3 writes the cookbook entry and drill checklist, updates the infra runbook, and runs the drill on production — the only place the real password, real volume and real data meet.

The test creates sources through the real upload endpoint (`sources.store`), so the archive is compared with where the application actually writes files, not with a path re-typed in the test.

## Critical Implementation Details

**Storage isolation must happen before bootstrap.** The temporary storage root must exist with `app/private`, `framework/views`, `framework/cache` and `logs` *before* the kernel bootstraps: the framework's default `view.compiled` is `realpath(storage_path('framework/views'))`, which resolves to `false` for a missing directory. Use a unique directory under `sys_get_temp_dir()` per test and delete it in `tearDown`.

**Clean up after, not only before.** `DatabaseTruncation` cleans before a test, so the Backup test case must also truncate domain tables (and `DROP DATABASE IF EXISTS testing_restore WITH (FORCE)`) in `tearDown`. Register the `Backup` testsuite last in `phpunit.xml` so a full run executes it after `Feature`.

**Password and the scoped config.** spatie builds its `Config` object once per app. The AES test must set `backup.backup.password` and `backup.backup.encryption` before anything resolves `Spatie\Backup\Config\Config`, or call `$this->app->forgetScopedInstances()` after setting them.

**`pg_dump` on the CI runner.** The runner image ships PostgreSQL 16 with a `16/main` cluster on port 5432, so Ubuntu's `/usr/bin/pg_dump` wrapper may pick 16 even after 18 is installed. Put `/usr/lib/postgresql/18/bin` first on `PATH` and assert the version in the workflow.

## Phase 1: Backup test suite (contents + dump restore + AES)

### Overview

A new `tests/Backup` suite with its own test case that isolates storage and database state, and three tests that turn risk #2's „what would prove protection” into assertions. Green locally in Sail.

### Changes Required:

#### 1. Backup testsuite registration

**File**: `phpunit.xml`

**Intent**: Run `tests/Backup` with the normal `artisan test`, after `Feature`.

**Contract**: New `<testsuite name="Backup"><directory>tests/Backup</directory></testsuite>` listed after `Feature`. No new `<env>` entries (the archive stays unencrypted by default; the AES test sets the password at runtime).

#### 2. Pest binding for the suite

**File**: `tests/Pest.php`

**Intent**: Bind `tests/Backup` to the new test case with `DatabaseTruncation` instead of the global `RefreshDatabase`, so rows are committed and visible to `pg_dump`.

**Contract**: `pest()->extend(Tests\BackupTestCase::class)->use(Illuminate\Foundation\Testing\DatabaseTruncation::class)->in('Backup');` next to the existing `Feature` binding.

#### 3. Isolated test case

**File**: `tests/BackupTestCase.php` (new, namespace `Tests`, extends `Tests\TestCase`)

**Intent**: Point the whole app's storage at a fresh temporary root before config loads, and leave no rows, databases or files behind.

**Contract**:
- Overrides `createApplication()`: require `bootstrap/app.php`, create the temp root and its `app/private`, `framework/views`, `framework/cache` and `logs` subdirectories, call `$app->useStoragePath($root)`, then bootstrap the kernel (same steps as the parent otherwise).
- Exposes the temp root to tests (e.g. a protected property) and helpers the tests share. Helpers include: run a backup and return the archive path, open an entry's bytes, and a `psql` runner using the `pgsql` connection's host, port, username and password (`PGPASSWORD` env, Symfony `Process`).
- `tearDown()`: truncate the domain tables, drop `testing_restore` if present, delete the temp root, then `parent::tearDown()`.
- Invariant: nothing under the repository's real `storage/app` is created or changed by the suite.

#### 4. Backup tests

**File**: `tests/Backup/BackupContentsTest.php` (new)

**Intent**: Encode the three proofs of risk #2 against real data created through the application.

**Contract** — shared arrangement: a user with a tree, at least two people, a birth event with a participant, a parent-child relation, a marriage, and two sources uploaded through `post(route('sources.store'), …)` with a real image (`tests/Fixtures/rotated-exif.jpg` or `UploadedFile::fake()->image()`), one of them linked to an event (`event_source`). Backup runs via `Artisan::call('backup:run', ['--disable-notifications' => true])` with exit code 0; the archive is the single zip on the `backups` disk.

- `test('the backup archive contains the database dump and every source original and thumbnail byte for byte')`:
  - the entry `db-dumps/postgresql-testing.sql` exists and contains a person's name created in the test (proves committed rows reached the dump);
  - for every `sources` row, `private/<path>` exists in the archive with the same sha256 as `Storage::disk('local')->get(path)`, and the same for `thumbnail_path` when not null;
  - at least one thumbnail was compared (the check is not vacuous).
- `test('the database dump restores into an empty database with the same row counts')`:
  - `CREATE DATABASE testing_restore`;
  - extract the dump and load it with `psql -v ON_ERROR_STOP=1 -f`; the process exits 0;
  - for each domain table plus `migrations`, `count(*)` on a temporary `restore` connection equals `count(*)` on `pgsql`, and each domain count is > 0.
- `test('an encrypted backup archive opens only with the backup password')`:
  - skip with a clear message when `ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)` is false;
  - otherwise set the password and `encryption = default` (see Critical Implementation Details), run the backup;
  - assert the dump entry cannot be read without a password and is read in full with it.

### Success Criteria:

#### Automated Verification:

- Backup suite passes in Sail with 3 passed and 0 skipped: `vendor/bin/sail artisan test --compact tests/Backup`
- Full suite passes with the Backup suite included: `vendor/bin/sail artisan test --compact`
- PHP style is clean: `vendor/bin/sail bin pint --dirty --format agent`
- The developer's real storage is untouched by the suite: no new file under `storage/app/backups` or `storage/app/private` after the run (`git status --porcelain storage/` and a before/after `find storage/app -newer <marker>` show nothing new)

#### Manual Verification:

- Mutation check: temporarily add `storage_path('app/private/sources')` to `backup.source.files.exclude`; the contents test fails naming a missing source entry; revert
- Mutation check: temporarily make the restore load only half the dump (or point it at an empty file); the restore test fails on a row-count mismatch; revert

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: CI gates (PostgreSQL 18 client, Vitest)

### Overview

Make the CI `tests` job able to run the Backup suite, and turn the required „frontend unit (Vitest)” gate green.

### Changes Required:

#### 1. PostgreSQL 18 client in the tests job

**File**: `.github/workflows/tests.yml`

**Intent**: `backup:run` calls `pg_dump` and the restore test calls `psql`; both must be major version 18 to talk to the `postgres:18-alpine` service.

**Contract**: One step before „Run tests (Pest)” that installs `postgresql-client-18` from the PGDG apt repository, puts `/usr/lib/postgresql/18/bin` first on `PATH` for later steps, and prints `pg_dump --version` (fails the job if it is not 18). Follows the PGDG pattern in `docker/8.5/Dockerfile.prod:100-118`.

```yaml
- name: Install PostgreSQL 18 client (pg_dump/psql for backup tests)
  run: |
    sudo apt-get install -y postgresql-common
    sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
    sudo apt-get install -y postgresql-client-18
    echo "/usr/lib/postgresql/18/bin" >> "$GITHUB_PATH"
- name: Check pg_dump version
  run: pg_dump --version | grep -q ' 18\.'
```

#### 2. Vitest step in the lint job

**File**: `.github/workflows/lint.yml`

**Intent**: Vitest starts a Vite server from `vite.config.ts`, and `laravel-vite-plugin` refuses to do that when `CI` is set. Bypass that check for the test step only.

**Contract**: `env: LARAVEL_BYPASS_ENV_CHECK: 1` on the „Run frontend unit tests (Vitest)” step, with a one-line comment naming the reason. No change to `vite.config.ts`.

### Success Criteria:

#### Automated Verification:

- Vitest passes under CI conditions locally: `vendor/bin/sail exec -e CI=true -e LARAVEL_BYPASS_ENV_CHECK=1 laravel.test npm run test:js`
- On the pushed branch's PR, the `tests` and `lint` checks pass: `gh pr checks <pr-number>`

#### Manual Verification:

- In the `tests` job log, the version step prints PostgreSQL 18 and the Backup suite's three tests ran; the AES test's outcome in CI (passed or skipped) is noted for §6.3

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Restore drill and cookbook

### Overview

Write the cookbook entry and drill checklist, bring the infra runbook in line with the post-rename data, and run the drill on production once.

### Changes Required:

#### 1. Cookbook §6.3 and gates

**File**: `context/foundation/test-plan.md`

**Intent**: Make §6.3 the canonical answer to „how do I add a backup test / run the restore drill”, and record that the two Phase 1 gates are now wired.

**Contract**:
- §6.3 replaces its `TBD` with:
  - **Location** `tests/Backup/`, test case `tests/BackupTestCase.php`, **Reference test** `tests/Backup/BackupContentsTest.php`, **Run locally** `vendor/bin/sail artisan test --compact tests/Backup`;
  - the oracle (`sources.path`/`thumbnail_path`, not the manifest);
  - the traps (no `Storage::fake`, isolated `storage_path`, committed rows, clean up after);
  - the AES outcome in CI;
  - the pre-deploy `--only-db` gap — run a full `backup:run` by hand before any migration that moves files.
- §6.3 adds a **Restore drill checklist** that links the runbook in `kostecki-dev-infra` for commands. Its steps:
  1. Record the production row counts of the 8 domain tables plus `migrations`.
  2. Run `select count(*) from sources where path like 'scans/%' or thumbnail_path like 'scans/%'`; when > 0, note the rows and open a follow-up.
  3. Decrypt the latest nightly archive off-VPS with `7z`.
  4. Load the dump into a throwaway `postgres:18-alpine` and compare the same counts.
  5. Compare sha256 of one real source original and its thumbnail with production.
  6. Clean up.
  7. Append a dated line to a „Drill log” list in §6.3.
- Cadence: quarterly, and after any change to backup config, file storage layout, PostgreSQL major version or server.
- §5: „weryfikacja zawartości kopii” → `required` (CI `tests`, `tests/Backup`). „drill odtworzenia” → `required — kwartalnie i po zmianach (§6.3)`. Typecheck row: drop „stan zielony do potwierdzenia w badaniu fazy 1” (confirmed green 2026-10-01).
- §4 backup/restore row notes point at `tests/Backup` and §6.3 instead of „see Phase 1”.
- Header `Last updated` → the date of the edit.

#### 2. Infra restore runbook

**File**: `../kostecki-dev-infra/docs/root-keeper.md` (sibling repo; its own commit there)

**Intent**: The runbook still describes pre-rename data and compares only `users`; make it match what the drill now checks.

**Contract** (Restore section only, `:132-199`):
- „scans under `private/…`” → source originals and thumbnails under `private/sources/<tree_id>/` and `private/sources/<tree_id>/thumbnails/`.
- The count comparison becomes one query over the 8 domain tables plus `migrations`, run on both production and the throwaway database.
- Add a pointer to root-keeper `context/foundation/test-plan.md` §6.3 as the drill checklist.
- Update the „Drilled once on 2026-09-22” line after the drill.

### Success Criteria:

#### Automated Verification:

- §6.3 no longer has its placeholder: `grep -c 'TBD — see §3 Phase 1' context/foundation/test-plan.md` prints `0`
- The infra runbook change is committed: `git -C ../kostecki-dev-infra status --porcelain docs/root-keeper.md` prints nothing and `git -C ../kostecki-dev-infra log -1 --oneline` shows the runbook commit

#### Manual Verification:

- Restore drill run on production per §6.3: every domain-table count matches between production and the restored database
- During the drill, a real source original and its thumbnail match production by sha256
- The `scans/%` query result is recorded in the §6.3 drill log (0, or a follow-up is named)
- §6.3 read cold: adding a new backup assertion or running the drill is possible from §6.3 and the linked runbook alone

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- None — the risk is the integration of backup config, storage layout, `pg_dump` and restore; no pure logic is added.

### Integration Tests:

- `tests/Backup/BackupContentsTest.php`: archive contents against `sources` rows (byte-identical), dump restore with equal per-table counts, AES archive opens with the password.
- Existing `tests/Feature/BackupScheduleTest.php` keeps guarding schedule and config.

### Manual Testing Steps:

1. Phase 1 mutation checks (exclude the sources directory; truncate the dump) each turn a test red.
2. Phase 2: read the CI `tests` log for the PG 18 version line and the three Backup tests.
3. Phase 3: run the restore drill on production per §6.3 and log it.

## Performance Considerations

The Backup suite runs `pg_dump`, zips a few small images and loads one dump — a few seconds per test against a 15-minute job budget. The temp storage root keeps the archive small (no dev or vendor files).

## Migration Notes

No schema or data migration. The drill reads production and restores only into a throwaway container.

## References

- Research: `context/changes/testing-backup-restore/research.md`
- Test plan: `context/foundation/test-plan.md` (§2 risk #2, §5, §6.3)
- F-01 plan: `context/archive/2026-09-22-production-data-safety/plan.md`
- S-16 rename: `context/archive/2026-09-29-scans-become-sources/`
- Restore runbook: `../kostecki-dev-infra/docs/root-keeper.md:132-199`
- Backup config: `config/backup.php:29-77`; storage: `config/filesystems.php:33-35,63-68`; writer: `app/Actions/Sources/StoreSource.php:33-51`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backup test suite (contents + dump restore + AES)

#### Automated

- [x] 1.1 Backup suite passes in Sail with 3 passed and 0 skipped — dbb7a5e
- [x] 1.2 Full suite passes with the Backup suite included — dbb7a5e
- [x] 1.3 PHP style is clean — dbb7a5e
- [x] 1.4 The developer's real storage is untouched by the suite — dbb7a5e

#### Manual

- [x] 1.5 Mutation check: excluding the sources directory fails the contents test — dbb7a5e
- [x] 1.6 Mutation check: a partial dump fails the restore test — dbb7a5e

### Phase 2: CI gates (PostgreSQL 18 client, Vitest)

#### Automated

- [x] 2.1 Vitest passes under CI conditions locally — a16de37
- [x] 2.2 On the pushed branch's PR, the tests and lint checks pass — a16de37

#### Manual

- [x] 2.3 CI tests log shows PostgreSQL 18 and the Backup suite ran; AES outcome noted — a16de37

### Phase 3: Restore drill and cookbook

#### Automated

- [ ] 3.1 §6.3 no longer has its placeholder
- [ ] 3.2 The infra runbook change is committed

#### Manual

- [ ] 3.3 Restore drill run on production: every domain-table count matches
- [ ] 3.4 A real source original and its thumbnail match production by sha256
- [ ] 3.5 The scans/% query result is recorded in the §6.3 drill log
- [ ] 3.6 §6.3 read cold is enough to add a backup assertion or run the drill
