<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Restore from Backup

- **Plan**: context/changes/restore-from-backup/plan.md
- **Scope**: Full plan (3 of 3)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-01
- **Verdict**: APPROVED
- **Findings**: 0 critical 1 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Success criteria re-run: `tests/Backup` 9 passed (139 assertions), `tests/Feature` 386 passed, phpstan 0 errors, pint clean, cookbook greps OK (hits for `BackupRestoreTest`/`backup:restore`, 0 for the fixed 8-table list), CI checks lint/static-analysis/tests all pass on the PR.

## Findings

### F1 — Safety backup can overwrite the input archive before the collision guard fires

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/RestoreBackup.php:makeSafetyBackup
- **Detail**: The plan notes spatie names archives `Y-m-d-H-i-s.zip` and overwrites a same-named file. The command detects the collision only after `backup:run` finished (`$created === []` or same realpath), at which point the input archive is already replaced by the safety copy. The abort protects the DB, but the archive being restored is gone from disk (its content survives only in the staging dir, which is deleted in `finally`). Tests dodge it with `travel(1)->seconds()`. Practical odds are low (needs a restore within the same second as the archive's creation), but the cost is losing the only copy.
- **Fix**: Before `backup:run`, if the input archive's basename equals `now()->format('Y-m-d-H-i-s').'.zip'`, `sleep(1)`; keep the post-check as a backstop.
  - Strength: Removes the collision instead of detecting it late; one-line change.
  - Tradeoff: Up to 1 s delay in a vanishingly rare case.
  - Confidence: HIGH — the name format is fixed by spatie (BackupJob.php:31).
  - Blind spot: Custom `filename_prefix`/format config is not covered (none set today).
- **Decision**: FIXED (Fix now: sleep(1) on same-second archive name before backup:run)

### F2 — Failure after the wipe does not point back to the safety copy

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/RestoreBackup.php (psql failure and missing-files branches)
- **Detail**: With `--force`, if `psql` fails (empty schema left) or files are missing, the error output does not repeat where the safety archive is; the path was printed only earlier, in the scrollback.
- **Fix**: Keep the safety archive path in a property and append "Kopia bezpieczeństwa: <path>" to both failure messages.
- **Decision**: FIXED (Fix now: safety archive path repeated in post-wipe failure messages)

### F3 — Staged plaintext dump relies on `finally` for cleanup

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/RestoreBackup.php:handle (staging dir)
- **Detail**: An AES-protected archive is unpacked to `storage/app/backup-temp/restore-*` with default permissions. `finally` covers exceptions but not SIGINT/kill, leaving an unencrypted PII dump on disk. Low risk on a single-tenant VPS.
- **Fix**: `chmod 0700` the staging dir after creating it.
- **Decision**: FIXED (Fix now: staging dir chmod 0700)
