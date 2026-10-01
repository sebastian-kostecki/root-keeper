---
date: 2026-10-01T07:45:23+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: ddedfb68136dff422cc94e9ff51f1ab59f4a9876
branch: main
repository: root-keeper
topic: "Risk #2 after testing-backup-restore: what is still unproven about backup completeness and a clean-instance restore"
tags: [research, backup, restore, spatie-backup, fortify, passkeys, app-key, runbook]
status: complete
last_updated: 2026-10-01
last_updated_by: Claude (Opus 5.5)
---

# Research: Risk #2 after testing-backup-restore — what is still unproven

**Date**: 2026-10-01T07:45:23+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: ddedfb68136dff422cc94e9ff51f1ab59f4a9876
**Branch**: main
**Repository**: root-keeper

## Research Question

Test-plan risk #2 (`context/foundation/test-plan.md:30`): *„Kopia zapasowa nie obejmuje wszystkich danych (w tym plików źródeł po zmianie skany → źródła) albo odtworzenie na czystej instancji nie przywraca kompletnych danych z czytelnymi plikami.”* Sources cited by the risk: interview P1 (data/scan loss), P2 (loss of access after licence expiry), P4 (restore rehearsed once by hand, before the rename); PRD v1 NFR (RPO 24 h) and Guardrails; archive `2026-09-22-production-data-safety`, `2026-09-29-scans-become-sources`.

**Scope assumption.** Test-plan §3 Phase 1 for this risk was already delivered by `context/archive/2026-10-01-testing-backup-restore/` (all Progress items ticked, impl review APPROVED with 0 critical / 0 warnings). This research therefore answers: *given what that change proved, which parts of risk #2 — especially the "clean instance" and "readable files" halves — are still unproven or newly exposed in the current code and runbook?* It does not re-derive the facts that change already established.

## Summary

1. **Backup scope is complete for what the app persists today.** The dump has no table exclusions (`config/backup.php:46-48`, no `dump` options in `config/database.php`), so all 17 tables after migrations go in. The only user files the app writes are source originals and thumbnails on the `local` disk under `sources/{tree_id}/` (`app/Actions/Sources/StoreSource.php:33-35,47-49`), which are inside the `storage/app` include (`config/backup.php:29-43`). No other feature writes files (no avatars, person photos, exports or imports were found in `app/`).
2. **The automated proof covers "archive contents" and "dump loads"; it does not cover "clean instance".** `tests/Backup/BackupContentsTest.php` proves: every live `sources` file is in the archive byte-for-byte (`:62-78`); the dump loads with `ON_ERROR_STOP=1` into an empty DB with equal `count(*)` on 8 hardcoded tables plus `migrations` (`:81-109`); an AES archive's dump entry opens only with the password (`:111-134`). No test extracts files into a storage root, boots the app against the restored DB, or reads/serves a file after restore.
3. **Data the archive cannot make usable on its own.** `APP_KEY` is not in the archive (it lives in `.env.prod`, outside `storage/app`). Fortify 2FA is enabled (`config/fortify.php:166-170`), and `two_factor_secret` / `two_factor_recovery_codes` are encrypted with the app encrypter (`vendor/laravel/fortify/src/Actions/EnableTwoFactorAuthentication.php:44-45`). A restore with a new key leaves those values undecryptable; password reset is not an enabled feature (`config/fortify.php:163-174` lists registration, emailVerification, twoFactorAuthentication, passkeys only). Passkeys are bound to the host of `APP_URL` (`config/fortify.php:146-147`) and their user-handle secret defaults to `APP_KEY` (`:148`).
4. **`APP_KEY` has no documented off-VPS copy**, and the documented first-deploy path generates a new one. `context/foundation/infrastructure.md:72` says secrets live in `.env.prod` "on the VPS only"; the infra runbook's first-deploy block runs `key:generate` (`../kostecki-dev-infra/docs/root-keeper.md:217-226`). The off-VPS instruction exists only for `BACKUP_ARCHIVE_PASSWORD` (`.env.prod.example:66-69`). The deploy preflight requires the archive password, `MAIL_HOST` and `BACKUP_NOTIFICATION_MAIL` (`scripts/lib/production-deploy.sh:66`), not `APP_KEY`.
5. **The restore runbook stops at a throwaway database.** Files: one sentence, no command and no app-level check (`../kostecki-dev-infra/docs/root-keeper.md:194-196`). "Full production restore" is explicitly "not drilled" (`:198-204`). Its step 2 loads the dump into the existing `keeper-pgsql`; the dump is plain `pg_dump` without `--clean` (`vendor/spatie/db-dumper/src/Databases/PostgreSql.php:37-56`), so under `ON_ERROR_STOP=1` it would fail on the first `CREATE TABLE` if the schema already exists (inference from pg_dump semantics; not executed). A clean-host rebuild (new VPS/provider, `.env.prod` reconstruction, ordering restore before `migrate`) is not described anywhere inspected.
6. **Drill and test coverage use a fixed 8-table list that omits `passkeys`.** `tests/BackupTestCase.php:28-37` and test-plan §6.3 step 1 (`context/foundation/test-plan.md:115`) list the same 8 tables. `passkeys` (user data, `database/migrations/2024_01_01_000000_create_passkeys_table.php:14-24`) is in the dump but never counted, and a future table would be silently uncovered because the list is not derived from the schema.
7. **The archives are not independent of the VPS.** Destination is the single local `backups` disk (`config/backup.php:16,66`), bind-mounted from `/storage/root-keeper-backups` (`docker-compose.prod.yml:21`). Off-site is deliberately blocked by the deploy preflight (`scripts/lib/production-deploy.sh:74-78`). `infrastructure.md:64` classifies `/storage` as "different disk, not offsite"; infra docs describe it as a network mount — the documents disagree on its nature (see Open Questions). This bears directly on interview P2 (rebuild elsewhere after losing access).

## Detailed Findings

### A. What the backup includes (scope)

- Tables: 17 after all migrations, confirmed against the dev DB's `information_schema` by the inventory worker. User data: `users` (incl. `two_factor_*` columns, `2025_08_14_170933_add_two_factor_columns_to_users_table.php:15-17`), `trees`, `people`, `events`, `event_participants`, `sources`, `event_source`, `parent_child_relations`, `passkeys`. Ephemeral: `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. Plus `migrations`.
- Dump: default connection only (`config/backup.php:46-48`); no table exclusions or extra dump options found in `config/backup.php` or `config/database.php`.
- Files written by the app (repo-wide grep of `Storage::`, `put`, `store`, `disk(`, `file_put_contents` in `app/` and `database/`):
  - `StoreSource.php:33-35` original → `local` `sources/{tree}/{uuid}.{ext}`; `:47-49` thumbnail → `sources/{tree}/thumbnails/{uuid}.jpg`. Archive entry `private/sources/…` (`relative_path` = `storage/app`, `config/backup.php:43`).
  - `database/seeders/TreeViewDemoSeeder.php` writes to the same prefix (demo only).
  - Logs (`storage/logs`) and framework cache are outside `storage/app` and are ephemeral.
  - The `public` disk is not written by any inspected code path; `follow_links=false` (`config/backup.php:39`).
- Excludes: `backups`, `backup-temp`, `storage_path('app/framework')` (`config/backup.php:33-36`). The last entry points at a directory that does not hold the framework cache (`storage/framework` is outside the include) — harmless no-op.
- Pre-deploy backup is DB-only: `scripts/lib/production-deploy.sh:90` (`backup:run --only-db`), skipped on first deploy (`:84-86`). The testing-backup-restore plan cites `:155-165`; the current anchor is `:81-91`. Test-plan §6.3 already names this gap (`context/foundation/test-plan.md:111`).

### B. What `tests/Backup` proves and does not prove

Proves (CI job `tests`, suite registered in `phpunit.xml:14-16`, PG 18 client guard `.github/workflows/tests.yml:64-72`):
- Real `backup:run` exits 0 and yields exactly one archive (`tests/BackupTestCase.php:82-88`).
- Dump entry exists and contains one surname created in the test (`BackupContentsTest.php:57-58`) — a single-string probe.
- For each `sources` row in the **live** DB, original and thumbnail are in the archive with equal sha256; at least one thumbnail compared (`:62-78`).
- Dump loads into `testing_restore` with `ON_ERROR_STOP=1`; `count(*)` equal for `DOMAIN_TABLES` + `migrations`, each domain count > 0 (`:89-107`).
- AES archive: dump entry unreadable without, readable with password (`:111-134`), using `encryption=default` → `EM_AES_256` (`vendor/spatie/laravel-backup/src/Enums/Encryption.php:20-22`), the same mode production gets when the password is set (`config/backup.php:73-75`).

Does not prove:
- Files restored into a storage root and served by the app (`sources.file` / `sources.thumbnail`, `routes/web.php:25-26`, `app/Http/Controllers/SourceController.php:172-193`) against a restored DB.
- Restored-row → archive-entry link: paths come from the live DB (`:62`), not from `testing_restore`.
- Anything beyond row counts on the restored DB (no model reads, relation traversal, `migrate:status`).
- `passkeys` row count, 2FA/passkey usability, or anything `APP_KEY`-dependent.
- File-mapping mix-ups: both fixture uploads use the same bytes (`:28-35`), so swapped paths between the two sources would still pass sha256.
- AES test can skip when libzip lacks AES (`:112-114`); `phpunit.xml` has no `failOnSkipped`, so a future runner without AES would stay green. §6.3 records a pass at one point in time (`test-plan.md:110`).
- Interop with the production decrypt tool (`7z`, runbook `:142-151`) — only the manual drill covers it.
- `tests/Feature/BackupScheduleTest.php:7-38` asserts include/exclude, DB, disks and cron/timezone/environment; it does not assert monitor health checks (`config/backup.php:130-139`), notification routing (`:84-103`) or retention.

### C. What a clean instance needs that the archive does not carry

- `.env.prod` (outside `storage/app`): `APP_KEY`, `APP_URL`, `SESSION_DOMAIN`, `DB_USERNAME`/`DB_DATABASE` (restore must match `POSTGRES_USER`, runbook `:163,172`), `APP_NAME` (archive subfolder, runbook `:138`), mail settings (preflight requires `MAIL_HOST`), `BACKUP_ARCHIVE_PASSWORD`.
- `APP_KEY` consequences (vendor code read, not executed):
  - 2FA: encrypted with app encrypter (`EnableTwoFactorAuthentication.php:44-45`; `TwoFactorAuthenticatable.php:38,87`). New key → decrypt failure for any user with 2FA; no in-app reset path (no `Features::resetPasswords()` in `config/fortify.php:163-174`).
  - Passkeys: login looks up by `credential_id` and verifies against the stored user handle (`vendor/laravel/passkeys/src/Actions/VerifyPasskey.php:70-80,119-121`); relying party = host of `APP_URL`. Inference: a key change does not break existing passkey login, but a different domain does.
  - No `encrypted` model casts or `Crypt` calls in `app/` (grep).
- App image: rebuilt from GitHub `main` (`scripts/lib/production-deploy.sh:5-21`) with unpinned bases and apt sources (`docker/8.5/Dockerfile.prod:3,11,21-27,95-101`). Restore must use PG 18 (runbook `:156`).
- Archives themselves: only on `/storage` attached to/reachable from the VPS (`docker-compose.prod.yml:21`); no off-site copy.
- Archive password: off-VPS copy instructed (`.env.prod.example:68-69`; runbook `:140`) and its existence ticked in production-data-safety item 3.6; the location is not named in any inspected doc.

### D. Runbook and drill coverage

- Runbook (`../kostecki-dev-infra/docs/root-keeper.md:132-205`): decrypt with `7z` on the VPS (`:142-151`); load dump into throwaway `postgres:18-alpine`, compare the 8-table + `migrations` counts (`:156-192`); file restore is one sentence (`:194-196`); full production restore listed as "not drilled" (`:198-204`).
- Full-restore step 2 targets the live `keeper-pgsql`. The dump has no `--clean`/`--if-exists` (`PostgreSql.php:37-56`), so loading it into a populated schema with `ON_ERROR_STOP=1` would stop at the first existing object; the runbook gives no drop/recreate step (inference, not executed). The same ordering issue applies on a clean host if `deploy.sh` (which runs `migrate --force`) runs before the dump is loaded.
- Test-plan §6.3 step 3 says decrypt "poza VPS-em" (`test-plan.md:117`); the runbook command decrypts on the VPS (`:142-151`). The two disagree.
- Drill log (`test-plan.md:127-128`): 2026-09-22 (users + migrations, one hand-placed file, file survived rebuild on same host); 2026-10-01 (8 tables + migrations, one original + thumbnail sha256, `scans/%` = 0; "confirmed by the author", no numbers recorded — impl-review F1).
- Never exercised (per runbook, drill log, archive plans): full production restore; files from an archive into a running app and opened through it (S-02 item 2.11 still `[ ]`, `context/archive/2026-09-24-scan-upload-and-preview/plan.md:442`); clean-host or new-provider rebuild; login after restore (password, 2FA with original vs new key, passkeys on another domain); retrieving archives without the VPS.

### E. Leftover `scans` naming

- No `scans` references in `app/`, `config/`, `routes/`, `resources/js` (worker grep).
- `Schema::rename` left PostgreSQL object names (`scans_id_seq`, `scans_pkey`, `event_scan_*` constraints/indexes) — observed on the dev DB only. They dump and restore fine; relevant only to future migrations that drop by conventional name.
- `2026_09_29_100100_move_source_files_to_sources_prefix.php:52-55`: a missing file only echoes a warning and the row keeps its `scans/…` path. Drill step 2 (`test-plan.md:116`) checks this; 2026-10-01 result 0.
- Stale doc mentions: `context/foundation/roadmap.md:103`, `context/foundation/stack-assessment.md:123,127`.

## Code References

- `config/backup.php:14-20,29-48,66-77,84-103,130-155` — off-site toggle, include/exclude, dump, destination, password/encryption, notifications, monitor, retention
- `config/filesystems.php:33-35,63-80` — `local` root, `backups` and `backups-offsite` disks
- `config/fortify.php:145-151,163-174` — passkey RP id / user-handle secret; enabled features
- `app/Actions/Sources/StoreSource.php:33-49` — only user-file writer
- `app/Http/Controllers/SourceController.php:172-193`, `routes/web.php:25-26` — file and thumbnail serving
- `tests/BackupTestCase.php:28-37,82-88,98,129-133` — table list, backup runner, dump entry name, psql runner
- `tests/Backup/BackupContentsTest.php:16-48,50-79,81-109,111-134` — fixture, contents, restore, AES tests
- `tests/Feature/BackupScheduleTest.php:7-38` — config/schedule assertions
- `scripts/lib/production-deploy.sh:66-78,81-91` — preflight, pre-migrate DB-only backup
- `scripts/deploy.sh:25-40` — backup host dir, pre-migrate backup, deploy order
- `docker-compose.prod.yml:20-21,50,70` — storage and backup bind mounts, DB volume
- `.env.prod.example:5,29-40,66-74` — APP_URL, drivers, backup env and off-VPS password note
- `vendor/spatie/db-dumper/src/Databases/PostgreSql.php:37-56` — pg_dump flags (no `--clean`)
- `../kostecki-dev-infra/docs/root-keeper.md:132-205,217-228` — restore runbook; first-deploy `key:generate`

## Architecture Insights

- The backup is a whole-`storage/app` zip plus a whole-DB dump, so *scope* drifts only if the app starts writing user data outside `storage/app` or to a non-default connection. The weaker invariants are in the *checks*: fixed table lists (test and drill) and a live-DB file oracle.
- The DB dump is necessary but not sufficient: two classes of data (2FA secrets, passkey identity) are portable only together with `APP_KEY` and the same `APP_URL` host. A restore on a clean instance is therefore "archive + password + `.env.prod` secrets + same domain", and only the first two have a documented off-VPS story.
- Restore procedure lives in the sibling infra repo; the test plan links to it. A planned change touching the procedure spans two repos (as testing-backup-restore Phase 3 did).

## Historical Context (from prior changes)

- `context/archive/2026-09-22-production-data-safety/plan.md:36-44` — deferred: restic/sizing, Pest config tests, heartbeat, off-site copy, rollback mechanism, real-scan drill (to S-02). No mention of `APP_KEY`. Supported: still deferred today.
- `context/archive/2026-09-29-scans-become-sources/plan.md:96,437` — manual full backup before the file-moving migration because pre-deploy is `--only-db`. Supported: `production-deploy.sh:90` still `--only-db`.
- `context/archive/2026-10-01-testing-backup-restore/plan.md` — Current State says pre-deploy backup at `production-deploy.sh:155-165`: **contradicted** (now `:81-91`); otherwise its claims about include paths, entry names and the 8 domain tables hold. Its "Not doing" (no restore script, no off-site, no schedule-fires test) remains true.
- `context/archive/2026-10-01-testing-backup-restore/reviews/impl-review.md` — F1 (drill log has no numbers) skipped; F3 ("scans" in infra runbook) fixed.
- `context/archive/2026-09-24-scan-upload-and-preview/plan.md:442` — drill item 2.11 ("confirm the scan file opens") still unticked.
- `context/foundation/infrastructure.md` — partially stale: "unencrypted / never restored / no pre-migrate backup" lines superseded by F-01; still accurate on `/storage` not being off-site (`:64`) and `APP_KEY` on the VPS only (`:72`).
- `context/foundation/test-plan.md:51` — §3 Phase 1 status still `change opened` although the change is archived (orchestrator state, not edited here).

## Related Research

- `context/archive/2026-10-01-testing-backup-restore/research.md` — backup contents and test-isolation traps (reused, not repeated here)

## Open Questions

Not answerable from the repositories; need the author or production access:

1. Does any production user have 2FA or passkeys enabled? (Decides whether the `APP_KEY` gap is live or latent.)
2. Is a copy of `APP_KEY` (and the rest of `.env.prod`) kept off the VPS, and where? Is the off-VPS archive password stored somewhere independent of this VPS (the only self-hosted password manager in the infra, Vaultwarden, runs on the same VPS — `kostecki-dev-infra/docs/backups.md:21`)?
3. Is `/storage` a disk attached to this VPS (`infrastructure.md:64`) or a network mount from another machine (`kostecki-dev-infra/docs/ARCHITECTURE.md:72`, `docs/SETUP.md:69`)? Same provider/account?
4. Product decision for planning: what does "clean instance" mean for this change — (a) same host, empty volumes; (b) new host, same domain; (c) new provider/domain (interview P2)? Each implies a different drill and a different set of secrets to escrow.
5. Should the restore check derive its table list from the schema (and include `passkeys`), and should the drill record actual counts (impl-review F1)?
6. Unverified inference to confirm in a drill: loading the plain dump into an already-migrated database fails under `ON_ERROR_STOP=1`; the runbook's full-restore step 2 needs a drop/recreate (or the dump needs `--clean`).
