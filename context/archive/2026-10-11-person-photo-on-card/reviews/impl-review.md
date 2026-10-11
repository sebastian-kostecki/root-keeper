<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Zdjęcie osoby na karcie drzewa (S-16 `person-photo-on-card`)

- **Plan**: context/changes/person-photo-on-card/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-11
- **Verdict**: APPROVED
- **Findings**: 0 critical 2 warnings 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Automated verification (run during review)

- `artisan test` (PersonPhotoTest, OwnershipBoundariesTest, PersonsTest, ProfileUpdateTest, tests/Backup, TreeTest, AncestorsTest): 284 passed
- `composer types:check` (Larastan): 0 errors; `pint --dirty`: passed
- `npm run check`: 234 files formatted, no lint warnings; `npm run types:check`: passed; `npm run test:js`: 266 passed
- Playwright (person-photo-on-card, person-side-panel, person-panel-unsaved-guard, person-panel-page-navigation-guard, tree-card-recentre, ancestors-view): 8 passed
- `route:list --path=persons`: three `persons.photo.*` routes present
- Not re-run: migrate/rollback (1.1), `npm run build` (2.3), remaining tree-slot/marriage E2E, `--repeat-each=2`, deliberate-break check (4.4) — checked `[x]` in Progress with commit SHAs.

## Findings

### F1 — Ownership test for `persons.photo.show` cannot detect a missing authorization

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/OwnershipBoundariesTest.php:110 (record built at :24)
- **Detail**: `victimPerson` has no photo and `PersonPhotoController::show` also returns 404 for a person without a photo. If `Gate::authorize('view')` were removed, this row would still be green. The plan requires "404 for another user on all three endpoints".
- **Fix**: Give `victimPerson` photo columns and thumbnail bytes on the faked disk in `ownershipRecords` (or add a dedicated test in `PersonPhotoTest.php` with a foreign person that has a thumbnail).
- **Decision**: FIXED via Fix now (victimPerson has photo + bytes; mutation check: removing Gate::authorize in show turns the row red)

### F2 — Weak assertions for rejecting gif/svg/pdf

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/PersonPhotoTest.php:96-99
- **Detail**: `UploadedFile::fake()->create('x.gif', ..., 'image/gif')` yields a file with no real content, so it is rejected because it cannot be decoded, not because `types([jpg,jpeg,png,webp])` excludes it. A real GIF/SVG would not be tested; if someone widened `types()`, these cases would stay green.
- **Fix**: Use `createWithContent` with minimal real content (`GIF89a…` header, `<svg xmlns=…/>`) for gif and svg.
- **Decision**: FIXED via Fix now (real gif/svg content; thumbnail generator mocked with shouldNotReceive so only the validation rule can reject; text-in-jpg case moved to its own test; mutation check: allowing gif turns the test red)

### F3 — Concurrent replacement orphans files in `StorePersonPhoto`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Persons/StorePersonPhoto.php:33,48-62
- **Detail**: `$previousPaths` is read from the in-memory model and `update` has no row lock. Two simultaneous uploads for the same person leave the loser's new files on disk unreferenced until account deletion. Same if the person is deleted between authorization and `update`. Practical risk is low (single-author trees, user-triggered uploads).
- **Fix A ⭐ Recommended**: Accept and leave as is (optionally a one-line comment).
  - Strength: no extra complexity for a single-user tree; matches `StoreSource`.
  - Tradeoff: orphans possible in a rare race.
  - Confidence: HIGH — trees have one owner; files are reclaimed on account deletion.
  - Blind spot: double-click on the upload; the avatar is disabled during upload, so unlikely.
- **Fix B**: Wrap read of previous paths + `update` in `DB::transaction` with `lockForUpdate()` (pattern in `UpdatePerson`).
  - Strength: removes the race entirely.
  - Tradeoff: more code and a test for a case that is hard to reproduce.
  - Confidence: MEDIUM — not verified how `UpdatePerson` locks.
  - Blind spot: file I/O inside a transaction holds the lock longer.
- **Decision**: ACCEPTED via Fix A (single-owner tree; orphaned files in a rare race are reclaimed with the account)

### F4 — Trivial assertion in the `show` test

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/PersonPhotoTest.php:171
- **Detail**: `$stored['thumbnail']->toEndWith('.jpg')` checks a value set by the test's own helper, not application behaviour.
- **Fix**: Remove the assertion.
- **Decision**: FIXED via Fix now (assertion and unused `$stored` removed)

### F5 — No test that `backup:restore --force` clears `persons/` on an occupied instance

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Backup/RestoreCommandTest.php:111-123
- **Detail**: `RestoreBackup` deletes `persons` before restoring (plan §1.6), but the only test covers refusal without `--force`. Also, the "restore on clean instance" and "missing file" cases live in `BackupRestoreTest.php:78-116` instead of `RestoreCommandTest.php` as the plan said (coverage is complete, only the file differs).
- **Fix**: Add a `--force` test with a leftover `persons/…` file asserting it is gone after restore.
- **Decision**: FIXED via Fix now (the --force occupied-instance restore test now plants `persons/{tree}/po-kopii.jpg` and asserts it is gone; mutation check: removing deleteDirectory(PERSONS_DIRECTORY) turns it red)

### F6 — Duplicated broken-image logic in avatar and card strip

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/person-avatar.tsx:39-40, resources/js/components/tree/sheet-person-card.tsx:40-41
- **Detail**: Identical `failedPhotoUrl` / `visiblePhotoUrl` state with `onError` reset-by-URL in two components.
- **Fix**: Extract a small `useVisiblePhoto(url)` hook in `resources/js/hooks/`.
- **Decision**: FIXED via Fix now (new `resources/js/hooks/use-visible-photo.ts`, used by PersonAvatar and PhotoStrip)
