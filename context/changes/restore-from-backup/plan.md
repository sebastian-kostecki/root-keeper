# Restore from Backup — Implementation Plan

## Overview

Test-plan risk #2 says: „Kopia zapasowa nie obejmuje wszystkich danych (w tym plików źródeł po zmianie skany → źródła) albo odtworzenie na czystej instancji nie przywraca kompletnych danych z czytelnymi plikami.” `testing-backup-restore` proved what goes into the archive and that the dump loads into an empty database. It did not prove the restore itself. This change gives the repository its own restore path, an Artisan command `backup:restore`. A Pest round-trip test makes a backup, restores it through that command onto a clean instance (fresh database and fresh storage root) and onto an occupied one (`--force`), and proves three things: every durable table has identical content, every source original and thumbnail opens through the app, and 2FA secrets still decrypt.

All work stays in this repository. The infra runbook and the production drill are not touched.

## Current State Analysis

- **Scope of the backup is complete for today's data** (research §A): the whole default-connection dump with no exclusions, plus all of `storage/app` except `backups`, `backup-temp` and `framework`. The only user files are source originals and thumbnails under `private/sources/{tree}/…`.
- **What is proven** (`tests/Backup/BackupContentsTest.php`): the archive holds every live source file byte for byte (`:50-79`); the dump loads into `testing_restore` with equal `count(*)` for 8 hard-coded tables plus `migrations` (`:81-109`); the AES archive opens only with the password (`:111-134`).
- **What is not proven** (research §B): files extracted into a storage root and served by the app from a restored DB; a restored row pointing at the right file (both fixtures upload identical bytes, `:28-35`); content beyond row counts; `passkeys` (missing from `tests/BackupTestCase.php:28-37`); anything that depends on `APP_KEY`.
- **No restore tool in the repo.** The procedure exists only as manual commands in `../kostecki-dev-infra/docs/root-keeper.md:132-205`. Its "full restore" step is undrilled and would fail on an already-migrated schema, because the dump has no `--clean` (`vendor/spatie/db-dumper/src/Databases/PostgreSql.php:33-66`).
- **The dump is not portable across DB users.** Plain `pg_dump` emits `ALTER … OWNER TO <user>` and `GRANT` lines. A clean host with a different `DB_USERNAME` would fail under `ON_ERROR_STOP=1`. Spatie appends `dump.add_extra_option` from the connection config, and `config/database.php` has no `dump` block today.
- **`APP_KEY` is not in the archive.** Fortify 2FA secrets are encrypted with it (`vendor/laravel/fortify/src/TwoFactorAuthenticatable.php:36-39`), and passkeys are bound to the `APP_URL` host (`config/fortify.php:146-148`).
- **Production runtime supports a PHP restore.** The prod image installs `postgresql-client-18` (`docker/8.5/Dockerfile.prod:82-118`). Artisan runs in the container through `scripts/artisan.sh`. Archives sit on the `backups` disk (`config/filesystems.php:63-68`, bind-mounted at `docker-compose.prod.yml:21`). `ZipArchive` with the configured password reads the AES archives (`config/backup.php:73-75`).
- `app/Console/Commands/` exists but is empty. Spatie registers `backup:run|clean|list|monitor`, and `backup:restore` is free.

## Desired End State

- `vendor/bin/sail artisan backup:restore <archive>` restores the database and `storage/app` files from a spatie archive (encrypted or not) into the current instance:
  - **Clean instance** (no `users` rows, no files under `private/sources`): wipes the schema (an empty `migrate`d schema is fine), loads the dump in one transaction, extracts the files, then verifies that every `sources` row has its files on disk. In `production` it asks for confirmation; `--force` skips that question.
  - **Occupied instance**: refuses without `--force`. With `--force` it prints what will be replaced (rows per durable table, number of source files) and asks for confirmation; `--no-interaction` aborts. Before deleting anything it runs a full `backup:run` as a safety copy and stops if that fails. It then clears `private/sources`, restores and verifies as above.
- New dumps carry no owner/ACL statements, so they load under any DB user.
- `tests/Backup` proves the full cycle on every CI run, in the encrypted production mode, with no skip:
  - content of every durable table (derived from the schema, `passkeys` included) is identical after restore;
  - each source original and thumbnail is served by `sources.file` / `sources.thumbnail` to the restored user with the sha256 of the bytes that were uploaded (two sources with different bytes);
  - the restored 2FA user's recovery codes decrypt with the same `APP_KEY`;
  - a new source can be uploaded after restore (sequences restored);
  - the `--force` path returns the data to the archived state, removes files added after the backup and leaves a safety archive.
- Test-plan §6.3 documents the restore test pattern, the command, the list of what a restore needs besides the archive, and a drill checklist that uses the command and schema-derived counts.

Verify with `vendor/bin/sail artisan test --compact tests/Backup`, plus the CI `tests` job green on the PR.

### Key Discoveries:

- `tests/BackupTestCase.php:42-60` swaps `storage_path` before kernel bootstrap. The disks capture `storage_path('app/…')` at config load, so re-pointing storage mid-test also needs the `filesystems.disks.local.root` / `backups.root` config updated and `Storage::forgetDisk()`.
- `tests/BackupTestCase.php:129-149` already has a `psql` runner (credentials from the connection, `ON_ERROR_STOP=1`). The command needs the same invocation against its target connection.
- `tests/Backup/BackupContentsTest.php:16-48` creates sources through the real `sources.store` endpoint. Keep that, but with two distinct fixture files.
- `database/factories/UserFactory.php:72-78` has a `withTwoFactor()` state. There is no passkey factory, so insert the `passkeys` row directly (`database/migrations/2024_01_01_000000_create_passkeys_table.php:14-24`).
- `app/Http/Controllers/SourceController.php:166-193` serves files through `Gate::authorize('view')` + `Storage::disk('local')`, which makes it the right "readable file" oracle.
- Delete-with-confirmation convention (`CLAUDE.md`, backend conventions): show the affected items first, then ask. The `--force` path follows it in console form.

## What We're NOT Doing

- No changes in `../kostecki-dev-infra` (runbook) and no production drill in this change. §6.3 points the drill at the new command; rerunning it on the server is the existing quarterly gate.
- No off-site copy, no escrow of `APP_KEY` or `.env.prod`. §6.3 names what must be kept off the VPS; doing it is an operational task.
- No change to the pre-deploy `--only-db` backup (`scripts/lib/production-deploy.sh:81-91`). The gap is already named in §6.3.
- No restore from an off-site disk, no interactive picker of the newest archive. The command takes an explicit archive path.
- No `--password` option. The password comes from `BACKUP_ARCHIVE_PASSWORD` config, which keeps it out of shell history.
- No test that a *different* `APP_KEY` breaks 2FA (framework behaviour, no new signal).
- No restore of a partial set (DB only, or files only).
- No edits to test-plan §1–§5 or the §3 status (orchestrator state).

## Implementation Approach

The command first, with its guards proven by small tests, so the round-trip test can call exactly what a human runs on the server. Then the round-trip test replaces the old count-only restore test with a stronger oracle. Its own snapshot is a per-table content hash taken from the source DB right after the backup, not from the archive or from the command's output. Last, the cookbook.

## Critical Implementation Details

**Order inside the command.** Validate everything before anything destructive: the archive opens, the password works, the dump entry exists, entries are safe, the instance is clean or `--force` is set, confirmation is given. Then stage the whole archive (dump + file entries) into a temp dir under `storage_path('app/backup-temp/restore-<random>')` (excluded from backups); every later step reads only from that staging dir, never from the archive file. Then the safety backup (occupied path only). Spatie names archives `Y-m-d-H-i-s.zip` and overwrites an existing file of that name (`vendor/spatie/laravel-backup/src/Tasks/Backup/BackupJob.php:31,94`), so a safety copy made in the same second as the input archive would replace it: if the safety archive's path equals the input path, abort before the wipe. Then wipe and load the DB, clear and extract files, verify. A failure before the wipe leaves the instance untouched. Load the dump with `psql --single-transaction -v ON_ERROR_STOP=1`, so a broken dump leaves an empty schema rather than a half-loaded one.

**Zip-slip.** Archive entries come from a file, which makes them untrusted input. Reject (and abort before writing anything) any entry that is absolute or contains a `..` segment. Extract only non-`db-dumps/` entries, under `storage_path('app')`. Never write into `backups/` or `backup-temp/`.

**Test isolation when switching instances.** The clean-instance scenario re-points the default `pgsql` connection at `testing_restore` (`DB::purge`) and storage at a second temp root (update the disk roots in config, then `Storage::forgetDisk`). `tearDown` truncates and drops on the *original* `testing` database, so it must restore the connection config first. Otherwise it truncates the restore DB and the real `testing` DB keeps committed rows that leak into `Feature`.

**Content oracle snippet.** One generic query per table, independent of primary keys, run on both connections:

```sql
select count(*) as rows, md5(coalesce(string_agg(t::text, '|' order by t::text), '')) as digest from <table> t
```

## Phase 1: `backup:restore` command and portable dump

### Overview

Add the restore command with its safety rules and post-restore verification, make new dumps owner-free, and prove the guards with fast tests that do not need a full round trip.

### Changes Required:

#### 1. Portable dump

**File**: `config/database.php`

**Intent**: Dumps should load under any database user, so a clean host with a different `DB_USERNAME` can restore them.

**Contract**: The `pgsql` connection gets `'dump' => ['add_extra_option' => '--no-owner --no-acl']`. This is read by spatie's `DbDumperFactory` (`vendor/spatie/laravel-backup/src/Tasks/Backup/DbDumperFactory.php:71-72,106-120` maps it to `addExtraOption()`).

#### 2. Restore command

**File**: `app/Console/Commands/RestoreBackup.php` (via `vendor/bin/sail artisan make:command RestoreBackup --no-interaction`)

**Intent**: One repo-owned, tested way to bring an instance back from a spatie archive. The same code runs in CI and on the server through `scripts/artisan.sh backup:restore …`.

**Contract**:

- Signature: `backup:restore {archive : Absolute path, or path relative to the "backups" disk} {--force : Allow overwriting an instance that already has data}`. Uses `ConfirmableTrait` for the production prompt on a clean instance. No `--database` option: the DB, the files, the clean/occupied check and the safety backup always belong to the same instance (the default connection and this app's `storage_path`), so a restore can never mix one instance's DB with another's files.
- Dump entry: exactly one entry matching `db-dumps/*.sql`, found by listing the archive, never derived from the target connection's database name (`database_dump_filename_base = 'database'` names it after the *source* DB, e.g. `postgresql-testing.sql` while the target is `testing_restore`). Zero or more than one match → fail with a clear message before any change.
- Password: `config('backup.backup.password')`. If the archive is encrypted and the password is missing or wrong, it fails with a clear message before any change.
- Clean instance = `users` table empty (or absent) **and** no files under `private/sources` on the `local` disk. Anything else is occupied.
- Occupied without `--force` → exit non-zero, message lists what was found, nothing changed.
- Occupied with `--force`:
  1. print a table of rows per durable table (all `public` tables except `migrations` and the ephemeral set: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`) and the number of files under `private/sources`;
  2. `confirm()` defaulting to no, so `--no-interaction` aborts;
  3. stage the archive into the temp dir (see Critical Implementation Details), then run `backup:run --disable-notifications` (full, not `--only-db`); abort non-zero if it fails or if the new archive's path equals the input archive's path;
  4. delete `private/sources`.
- Restore: `db:wipe --force`, then `psql --single-transaction -v ON_ERROR_STOP=1 -f <staged dump>` against the default connection's host/port/user/db with `PGPASSWORD`. Copy the staged file entries into `storage_path('app')`. Remove the staging dir (also on failure).
- Verify: for every `sources` row, `path` and non-null `thumbnail_path` exist on the `local` disk. Print restored row counts per durable table and the number of files written. Exit non-zero and list missing paths if any are absent.
- Output strings in Polish (UI convention); exit codes `self::SUCCESS` / `self::FAILURE`.
- The command needs no schedule entry and no change to `bootstrap/app.php` (auto-discovered from `app/Console/Commands`).

#### 3. Guard tests

**File**: `tests/Backup/RestoreCommandTest.php`

**Intent**: Each refusal path leaves the instance unchanged. That is cheaper to prove here than inside the round trip.

**Contract**: Uses `BackupTestCase`. Cases:

- occupied instance without `--force` fails, and `users` count and source files are unchanged;
- occupied with `--force` but the confirmation answered "no" (`expectsConfirmation(…, 'no')`) fails, nothing changed, no safety archive written;
- encrypted archive with a wrong configured password fails before any change;
- an archive containing a `../evil` entry (built in the test with `ZipArchive`) fails, and no file appears outside `storage_path('app')`;
- a dump of a fresh backup contains no `OWNER TO` statements.

### Success Criteria:

#### Automated Verification:

- Guard tests pass: `vendor/bin/sail artisan test --compact tests/Backup/RestoreCommandTest.php`
- Existing backup suite still passes: `vendor/bin/sail artisan test --compact tests/Backup`
- Static analysis passes: `vendor/bin/sail composer types:check`
- Formatting is clean: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- `vendor/bin/sail artisan backup:restore --help` shows the archive argument and `--force`, and the refusal messages read clearly in Polish

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Full backup → restore round-trip test

### Overview

Prove on every CI run, in encrypted production mode, that an archive restored by `backup:restore` gives back identical durable data and files the app can serve, on both a clean and an occupied instance.

### Changes Required:

#### 1. Base test case

**File**: `tests/BackupTestCase.php`

**Intent**: Replace the hard-coded table list with a schema-derived one, and give tests the tools to switch the app to a second instance and back.

**Contract**:

- `DOMAIN_TABLES` → `EPHEMERAL_TABLES` (the 7 ephemeral tables + `migrations`) and a `durableTables()` helper. It reads `information_schema.tables` for schema `public` on a connection and subtracts the ephemeral list, so `passkeys` and any future table are included automatically.
- `tableDigests(string $connection): array<string, array{rows: int, digest: string}>` using the snippet above.
- `arrangeBackupData()` moves from `tests/Backup/BackupContentsTest.php:16` into a protected `BackupTestCase` method. Pest loads only the files it is given, so a free function in one test file is undefined when `BackupRestoreTest.php` runs alone (criterion 2.1).
- A helper that switches the app to a clean instance: creates an empty `testing_restore` database, re-points the default connection at it (`DB::purge('pgsql')`), and points storage at a second temp root on both levels: `$this->app->useStoragePath($root)` (the command writes to `storage_path('app')`) **and** the `local`, `public` and `backups` disk roots in config, then `Storage::forgetDisk([...])` (the command verifies through the `local` disk). It remembers the original values for `tearDown`.
- `tearDown` first restores the original storage path, disk roots and connection config (`DB::purge('pgsql')` again so the next query reconnects to `testing`), then truncates the durable tables of `testing` (derived, `RESTART IDENTITY CASCADE`), drops `testing_restore` and deletes both temp roots.

#### 2. Fixture with distinct bytes

**File**: `tests/Fixtures/` (second small JPEG with different bytes, e.g. `portrait-small.jpg`)

**Intent**: Swapped file paths between two sources must change the sha256 and fail the test.

**Contract**: The two uploads in the arrange helper use different fixture files. Their sha256 values are recorded at upload time and act as the oracle for `sources.file`.

#### 3. Round-trip tests

**File**: `tests/Backup/BackupRestoreTest.php` (new); `tests/Backup/BackupContentsTest.php` (calls `$this->arrangeBackupData()` instead of the local function, count-only restore test removed: approved in planning)

**Intent**: Turn "the archive can rebuild the app" into a CI assertion. The old count-only test becomes redundant and is removed.

**Contract**:

- Arrange (`BackupTestCase::arrangeBackupData()`, extended): existing tree/people/events/relations, two sources with distinct bytes (one linked to an event), the user created `withTwoFactor()`, and one `passkeys` row for that user. Encrypted mode: `backup.backup.password` + `encryption=default`, set before spatie config is first used, then `forgetScopedInstances()` (pattern at `BackupContentsTest.php:116-120`). No AES skip: if AES is unsupported the test fails, because production restore would fail too.
- **Clean instance**: backup → snapshot `tableDigests('pgsql')` → switch to the clean instance → `migrate --force` (as on a server where the deploy ran before the restore; the command's `db:wipe` must cope with an already-migrated schema) → `backup:restore <archive> --no-interaction` exits 0 → `tableDigests` on the restored DB equal the snapshot for every durable table, and every durable table has rows → `actingAs(restored user)` GET `sources.file` and `sources.thumbnail` for each restored source: 200, and the original's body sha256 equals the uploaded fixture's sha256 (thumbnail: equals the sha256 recorded from the first instance) → restored user `recoveryCodes()` equals the codes set by the factory → POST a new source through `sources.store` succeeds.
- **Occupied instance**: backup → snapshot → mutate (delete one person through the existing delete endpoint and upload a third source) → `$this->travel(1)->seconds()` (spatie archive names have one-second resolution) → `backup:restore <archive> --force` with `expectsConfirmation(…, 'yes')` exits 0 → digests equal the snapshot → the third source's file no longer exists on the `local` disk → the `backups` disk now holds 2 archives with different names, and the input archive's sha256 is unchanged (the safety copy did not overwrite it).
- The archive-contents test and the AES-only test stay. AES now runs inside the round trip as well; keep the AES-only test as the cheap "no password → unreadable" check.

### Success Criteria:

#### Automated Verification:

- Round-trip tests pass: `vendor/bin/sail artisan test --compact tests/Backup/BackupRestoreTest.php`
- Whole backup suite passes: `vendor/bin/sail artisan test --compact tests/Backup`
- Feature suite is unaffected by leftover rows: `vendor/bin/sail artisan test --compact tests/Feature`
- Static analysis and formatting pass: `vendor/bin/sail composer types:check` and `vendor/bin/sail bin pint --dirty --format agent`
- CI `tests` job is green on the PR (round-trip test reported as passed, not skipped)

#### Manual Verification:

- Mutation check: with the command's file-extraction step disabled, or with the command deleting the `passkeys` rows right after loading the dump, the round-trip test turns red (revert afterwards)
- After the suite runs, the developer's own `storage/app/private/sources` and the `testing` database hold no leftovers

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Cookbook §6.3

### Overview

Make the command and the restore test the documented answer to "how do I add a backup/restore test" and "how do I restore", in this repo.

### Changes Required:

#### 1. Test-plan cookbook

**File**: `context/foundation/test-plan.md` (§6.3 only)

**Intent**: Keep the cookbook in line with the new test, the oracle and the command.

**Contract**:

- **Reference test**: add `tests/Backup/BackupRestoreTest.php`. **Oracle**: add the per-table content digest over schema-derived durable tables, plus file sha256 via `sources.file`. **Pitfalls**: add the instance-switch and `tearDown` order, and the new ephemeral-table rule (a new cache/queue-style table must be added to `EPHEMERAL_TABLES`).
- New block **Odtworzenie (komenda)**: `scripts/artisan.sh down` → `scripts/artisan.sh backup:restore <archiwum>` → `TRUNCATE sessions, cache, cache_locks` (the dump brings back the sessions and cache from backup time) → `scripts/artisan.sh up`. Without `down`, requests hit an empty schema between `db:wipe` and the end of `psql`. This works because the maintenance driver is `file` (`APP_MAINTENANCE_DRIVER=file`, flag in `storage/framework`, which the restore does not touch); with the `cache`/database driver, `db:wipe` would drop the flag. Clean vs occupied behaviour; `--force` makes a safety copy first.
- New block **Potrzebne do odtworzenia poza archiwum**: `BACKUP_ARCHIVE_PASSWORD`, `APP_KEY` (otherwise 2FA stops working), the same `APP_URL` domain (passkeys), DB credentials in `.env.prod`. All of these must be kept off the VPS.
- Drill checklist: step 1 uses durable-table counts derived from the schema (incl. `passkeys`) instead of the fixed 8, and the drill records the numbers; steps 3–4 use `backup:restore` on a separate throwaway instance (its own DB and its own storage, e.g. a local Sail checkout of the deployed commit with the production `APP_KEY` and `BACKUP_ARCHIVE_PASSWORD`), never inside the production container, instead of manual `7z` + `psql`. Keep the `scans/%` query and the drill log.

### Success Criteria:

#### Automated Verification:

- §6.3 names the new test file and command, and no longer lists the fixed 8 tables: `grep -n "BackupRestoreTest\|backup:restore" context/foundation/test-plan.md` returns hits and `grep -c "users\`, \`trees\`, \`people\`" context/foundation/test-plan.md` returns 0

#### Manual Verification:

- §6.3 reads as a complete answer for someone restoring onto a new server who never saw this change

**Implementation Note**: After completing this phase, pause for the human to confirm the cookbook text.

---

## Testing Strategy

### Unit Tests:

- None. All behaviour depends on real PostgreSQL, `psql` and the filesystem.

### Integration Tests:

- `RestoreCommandTest`: refusal paths (occupied without `--force`, declined confirmation, wrong password, zip-slip entry) leave the instance unchanged; dumps are owner-free.
- `BackupRestoreTest`: clean-instance and occupied-instance round trips with a content-digest oracle, files served through the app, 2FA decrypt and a post-restore insert.
- `BackupContentsTest`: archive contents and the AES-only check stay as they are.

### Manual Testing Steps:

1. Run `vendor/bin/sail artisan backup:restore --help` and one refusal on the dev instance (occupied, no `--force`).
2. Run the mutation checks from Phase 2.
3. Read §6.3 as a newcomer.

## Performance Considerations

Each round trip runs two `backup:run` calls (one in the occupied scenario as the safety copy) and two `psql` loads of a tiny DB. Expect a few extra seconds in the `tests` job, well within its budget.

## Migration Notes

The `--no-owner --no-acl` option applies only to new archives. Existing production archives still contain `OWNER TO <prod user>`. Restoring one works when the target DB user has the same name (current setup) and fails clearly otherwise. Note this in §6.3.

## References

- Research: `context/changes/restore-from-backup/research.md`
- Previous phase: `context/archive/2026-10-01-testing-backup-restore/plan.md`, `plan-brief.md`
- Test base and patterns: `tests/BackupTestCase.php:42-149`, `tests/Backup/BackupContentsTest.php:16-134`
- File serving: `app/Http/Controllers/SourceController.php:166-193`, `routes/web.php:25-26`
- Backup config: `config/backup.php:14-77`, `config/filesystems.php:63-80`
- pg_dump flags: `vendor/spatie/db-dumper/src/Databases/PostgreSql.php:33-66`
- 2FA encryption: `vendor/laravel/fortify/src/TwoFactorAuthenticatable.php:36-39`, `database/factories/UserFactory.php:72-78`
- Prod runtime: `docker/8.5/Dockerfile.prod:82-118`, `docker-compose.prod.yml:20-21`, `scripts/artisan.sh`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: `backup:restore` command and portable dump

#### Automated

- [x] 1.1 Guard tests pass: `vendor/bin/sail artisan test --compact tests/Backup/RestoreCommandTest.php` — 75b410e
- [x] 1.2 Existing backup suite still passes: `vendor/bin/sail artisan test --compact tests/Backup` — 75b410e
- [x] 1.3 Static analysis passes: `vendor/bin/sail composer types:check` — 75b410e
- [x] 1.4 Formatting is clean: `vendor/bin/sail bin pint --dirty --format agent` — 75b410e

#### Manual

- [x] 1.5 `vendor/bin/sail artisan backup:restore --help` shows the archive argument and `--force`, and the refusal messages read clearly in Polish — 75b410e

### Phase 2: Full backup → restore round-trip test

#### Automated

- [x] 2.1 Round-trip tests pass: `vendor/bin/sail artisan test --compact tests/Backup/BackupRestoreTest.php`
- [x] 2.2 Whole backup suite passes: `vendor/bin/sail artisan test --compact tests/Backup`
- [x] 2.3 Feature suite is unaffected by leftover rows: `vendor/bin/sail artisan test --compact tests/Feature`
- [x] 2.4 Static analysis and formatting pass: `vendor/bin/sail composer types:check` and `vendor/bin/sail bin pint --dirty --format agent`
- [ ] 2.5 CI `tests` job is green on the PR (round-trip test reported as passed, not skipped)

#### Manual

- [x] 2.6 Mutation check: with the command's file-extraction step disabled, or with the command deleting the `passkeys` rows right after loading the dump, the round-trip test turns red (revert afterwards)
- [x] 2.7 After the suite runs, the developer's own `storage/app/private/sources` and the `testing` database hold no leftovers

### Phase 3: Cookbook §6.3

#### Automated

- [ ] 3.1 §6.3 names the new test file and command, and no longer lists the fixed 8 tables: `grep -n "BackupRestoreTest\|backup:restore" context/foundation/test-plan.md` returns hits and `grep -c "users\`, \`trees\`, \`people\`" context/foundation/test-plan.md` returns 0

#### Manual

- [ ] 3.2 §6.3 reads as a complete answer for someone restoring onto a new server who never saw this change
