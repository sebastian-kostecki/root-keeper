# Restore from Backup — Plan Brief

> Full plan: `context/changes/restore-from-backup/plan.md`
> Research: `context/changes/restore-from-backup/research.md`

## What & Why

Test-plan risk #2: „Kopia zapasowa nie obejmuje wszystkich danych (w tym plików źródeł po zmianie skany → źródła) albo odtworzenie na czystej instancji nie przywraca kompletnych danych z czytelnymi plikami.” The previous change proved what goes *into* the archive. Nothing yet proves that the archive can *rebuild* a working app. This change adds a repo-owned restore command and a CI test that makes a backup and restores it, all inside this repository.

## Starting Point

`tests/Backup` checks the archive contents (sha256 against the live DB) and that the dump loads into an empty database with equal row counts for 8 hard-coded tables (no `passkeys`). No test extracts files into a new storage root, boots the app on the restored DB or opens a file through it. The repo has no restore tool. The manual runbook lives in the infra repo, and its full-restore step is undrilled. Dumps carry `OWNER TO <user>`, and `APP_KEY` (needed for 2FA) is not in the archive.

## Desired End State

`artisan backup:restore <archive>` restores the DB and `storage/app` files into the current instance. It works on a clean instance, and on an occupied one only with `--force`, after showing the impact, asking for confirmation and making a safety copy. On every CI run, in encrypted mode, a round-trip test proves three things: every durable table has identical content after restore, each source original and thumbnail opens through the app with the uploaded bytes, and 2FA codes still decrypt. Cookbook §6.3 explains the command and what a restore needs besides the archive.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Repo boundary | Everything in this repo; no infra runbook edits, no prod drill | The user wants this repo to prove that create + restore works | Plan |
| Restore path | Artisan `backup:restore`, called by the test | The same code runs in CI and on the server, so the test proves what a human will run | Plan |
| Target instance | Clean instance always; occupied only with `--force` + impact table + confirmation + safety `backup:run` first | One command also covers "I lost data", and a wrong archive choice stays reversible | Plan |
| Data oracle | Durable tables derived from the schema (minus ephemeral list) + per-table content digest | Catches `passkeys`, any future table and changed values, not only counts | Research → Plan |
| File oracle | Restored user downloads each file via `sources.file`/`thumbnail`; two fixtures with different bytes | Proves what the user sees and catches files swapped between sources | Research → Plan |
| APP_KEY / 2FA | Test: 2FA codes decrypt after restore with the same key; §6.3 lists APP_KEY as required off-VPS | Cheap proof plus an explicit "needed besides the archive" list | Research → Plan |
| Portable dump | `--no-owner --no-acl` via `dump.add_extra_option` | A clean host with another DB user can load new dumps | Research → Plan |
| AES in the round trip | Encrypted mode, no skip | Production restore needs AES; a runner without it must fail, not pass | Plan |
| Old count-only restore test | Replaced by the round trip (approved) | The new test is strictly stronger | Plan |

## Scope

**In scope:**
- `app/Console/Commands/RestoreBackup.php` (`backup:restore`), `config/database.php` dump option
- `tests/Backup/RestoreCommandTest.php` (guards), `tests/Backup/BackupRestoreTest.php` (round trip), `tests/BackupTestCase.php` helpers, a second fixture image, `BackupContentsTest.php` cleanup
- `context/foundation/test-plan.md` §6.3

**Out of scope:** the infra runbook and a production drill, off-site copies, escrow of `APP_KEY`/`.env.prod`, the pre-deploy `--only-db` backup, restoring from an off-site disk, partial restores, test-plan §1–§5.

## Architecture / Approach

The command validates everything first (archive, password, dump entry, safe paths, clean/`--force`, confirmation). On an occupied instance it then takes a safety backup. After that it runs `db:wipe`, loads the dump with `psql --single-transaction`, extracts the files into `storage_path('app')` and checks that every `sources` row has its files. The test creates data through the app, backs up, snapshots per-table digests, switches the app to a fresh DB + fresh storage root, runs the command and compares. It then reads files through HTTP as the restored user. A second scenario mutates the data and restores with `--force`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. `backup:restore` + portable dump | Command with guards, verification and owner-free dumps; refusal paths tested | Destructive code: order of validation vs wipe; zip-slip |
| 2. Round-trip test | Clean and occupied restores proven by content digests, HTTP file reads, 2FA decrypt | Isolation when switching DB/storage mid-test; `tearDown` order |
| 3. Cookbook §6.3 | Command, oracle, "needed besides the archive" and drill updated | Docs drifting from the infra runbook (out of scope) |

**Prerequisites:** Sail running (`vendor/bin/sail up -d`; it is not running now), PostgreSQL 18 client in Sail (already used by `tests/Backup`), ability to open a PR for CI.
**Estimated effort:** ~2–3 sessions. Phase 1 and Phase 2 are each about a session, Phase 3 is short.

## Open Risks & Assumptions

- Existing production archives still contain `OWNER TO <prod user>`. They restore only where the DB user has the same name (true today), and §6.3 says so.
- The `--force` path relies on a full `backup:run` succeeding on the server. If the backups disk is full, the restore aborts before deleting anything, which is the intended behaviour.
- The test proves restore with the *same* `APP_KEY`. Whether the key is actually stored off the VPS stays an operational question.

## Success Criteria (Summary)

- Breaking the restore (no file extraction, a lost table, swapped files, an unreadable 2FA secret) turns CI red.
- `backup:restore` refuses to touch an occupied instance without `--force` and explicit confirmation, and always leaves a safety copy first.
- §6.3 lets someone who never saw this change restore onto a new server.
