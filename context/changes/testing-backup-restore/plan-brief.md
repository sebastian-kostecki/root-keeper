# Backup Contents and Restore Proof — Plan Brief

> Full plan: `context/changes/testing-backup-restore/plan.md`
> Research: `context/changes/testing-backup-restore/research.md`

## What & Why

Test plan Phase 1, risk #2: „Kopia zapasowa nie obejmuje wszystkich danych (w tym plików źródeł po zmianie skany → źródła) albo odtworzenie na czystej instancji nie przywraca kompletnych danych z czytelnymi plikami.” The product has no trash and no undo. The backup is the only way back from data loss, and today nothing proves it holds.

## Starting Point

The config does cover the database and all of `storage/app`, including `private/sources/…`. The only test, though, asserts config values and the cron schedule. Restore was drilled once (2026-09-22), before the scans → sources rename, and compared only `users` and `migrations`. The runbook lives in `kostecki-dev-infra`. Separately, the CI Vitest step has failed on every run since it was added.

## Desired End State

Every PR runs a `tests/Backup` suite. It fails if any source original or thumbnail is missing from the archive or differs in bytes, if the dump does not restore into an empty database with equal per-table counts, or if an AES archive will not open with its password. CI `tests` and `lint` are green. One post-rename restore drill has run on production, and test-plan §6.3 tells anyone how to add a backup test or rerun the drill.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Oracle for completeness | `sources.path` / `thumbnail_path` + sha256, not spatie's manifest | spatie's `verify_backup` only counts manifest entries | Research |
| CI test depth | Contents **and** dump restore into `testing_restore` with per-table counts | Proves the dump is restorable on every PR; the drill only covers production specifics | Plan |
| Test location | New `tests/Backup` suite + `tests/BackupTestCase.php` with `DatabaseTruncation` | Avoids fighting the global `RefreshDatabase`; rows must be committed for `pg_dump` | Plan |
| Storage isolation | `useStoragePath(temp)` before kernel bootstrap | Real config loads unchanged; dev files under `storage/app/private/sources/{1,3,17}` stay safe | Plan |
| How sources are created | Through the real `sources.store` endpoint | Compares the archive with where the app actually writes files | Plan |
| AES | Covered in CI, skipped with a message when libzip lacks AES | Mirrors production (password required); Sail supports AES | Plan |
| PG client in CI | PGDG `postgresql-client-18`, `/usr/lib/postgresql/18/bin` first on PATH, version asserted | Runner's own client is 16 and its wrapper may still pick 16 | Plan |
| Vitest in CI | `LARAVEL_BYPASS_ENV_CHECK=1` on the Vitest step | `laravel-vite-plugin` blocks the Vite server when `CI` is set; fix verified locally (124/124) | Plan |
| Drill home and cadence | Checklist in test-plan §6.3, commands stay in the infra runbook; quarterly + after backup/storage/PG/server changes | Criteria live next to the test without duplicating commands | Plan |
| Extras in scope | `scans/%` production query in the drill, Vitest fix, infra runbook update | User selection | Plan |

## Scope

**In scope:**
- `tests/Backup` suite (3 tests), `tests/BackupTestCase.php`, `phpunit.xml` testsuite, `tests/Pest.php` binding
- `.github/workflows/tests.yml` PG 18 client step; `.github/workflows/lint.yml` Vitest env
- test-plan §6.3 (pattern + drill checklist + drill log), §5 gate rows, §4 backup row
- Restore section of `../kostecki-dev-infra/docs/root-keeper.md`
- One restore drill on production

**Out of scope:** making the pre-deploy backup full (gap named in §6.3 instead), a restore script, off-site copy, backup sizing, edits to test-plan §1–§3, `infrastructure.md` cleanup, any change to `config/backup.php`.

## Architecture / Approach

Order: the test first, then CI, then the human drill. The test case redirects `storage_path()` to a temp root before config loads, creates real data through the app (upload endpoint, factories), and runs the real `backup:run`. It then opens the zip and compares entries with `sources` rows by sha256. For the restore it shells out to `psql` 18 to load the dump into `testing_restore` and compares counts for `users`, `trees`, `people`, `events`, `event_participants`, `sources`, `event_source`, `parent_child_relations` and `migrations`. The drill repeats the restore on production data with `7z` and the off-VPS password.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Backup test suite | 3 green tests in Sail, no trace in real storage or other suites | Isolation leaks: config loaded before storage swap, committed rows leaking into `Feature` |
| 2. CI gates | `tests` runs the suite with PG 18; `lint` green incl. Vitest | Runner's `pg_dump` wrapper picking 16; AES unsupported in CI (→ skip) |
| 3. Drill and cookbook | §6.3 pattern + checklist, runbook updated, production drill logged | Drill needs VPS access and the off-VPS password |

**Prerequisites:** Sail running; ability to push a branch and open a PR; VPS access and the off-VPS `BACKUP_ARCHIVE_PASSWORD` for Phase 3.
**Estimated effort:** ~2–3 sessions: Phase 1 is the bulk, Phase 2 is small, Phase 3 is mostly the drill.

## Open Risks & Assumptions

- AES support in the CI PHP build is unverified. If it is missing, the AES test is skipped there and only Sail and the drill prove encryption.
- Production may hold `sources` rows still pointing at `scans/…` (files the backup cannot contain). The drill's query surfaces them, and fixing them is a follow-up, not this change.
- The `tests` job time grows by a few seconds plus the apt install. That fits in the 15-minute budget.

## Success Criteria (Summary)

- Removing source files from the backup scope, or breaking the dump, turns CI red.
- CI `tests` and `lint` are green on the PR.
- A logged post-rename drill shows equal domain counts and byte-identical real source files.
