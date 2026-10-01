<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Restore from Backup

- **Plan**: context/changes/restore-from-backup/plan.md
- **Mode**: Deep
- **Date**: 2026-10-01
- **Verdict**: REVISE → SOUND after triage (all 7 findings fixed in plan)
- **Findings**: 1 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | WARNING |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
11/11 paths ✓ (app/Console/Commands exists, contrary to plan — F7), 5/5 symbols ✓ (dump.add_extra_option, withTwoFactor, sources.file, BackupJob::FILENAME_FORMAT, ZipArchive AES), brief↔plan ✓

## Findings

### F1 — Kopia bezpieczeństwa może nadpisać przywracane archiwum

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 §2 (occupied + --force, krok 3) · Phase 2 scenariusz „Occupied”
- **Detail**: Spatie nazywa archiwa z dokładnością do sekundy (`vendor/spatie/laravel-backup/src/Tasks/Backup/BackupJob.php:31,94`) i nadpisuje plik o tej samej nazwie (writeStream). W teście „occupied” sekwencja backup → mutacja → restore --force → safety backup:run zwykle mieści się w 1 s, więc kopia bezpieczeństwa zastępuje przywracane archiwum: oryginał przepada, pliki wypakowują się z kopii bezpieczeństwa (trzecie źródło wraca), asercja „2 archiwa” pada. Test flaky; na produkcji mało prawdopodobne, ale nieodwracalne.
- **Fix**: Komenda wypakowuje dump i pliki do katalogu tymczasowego przed kopią bezpieczeństwa i przerywa, jeśli ścieżka kopii == ścieżka archiwum wejściowego; test robi `$this->travel(1)->seconds()` przed restore i sprawdza różne nazwy archiwów.
  - Strength: Restore nie zależy od pliku archiwum po walidacji; usuwa klasę problemu.
  - Tradeoff: Tymczasowe miejsce na dysku równe rozpakowanemu archiwum.
  - Confidence: HIGH — format nazwy i nadpisywanie potwierdzone w vendor.
  - Blind spot: Nie mierzono realnego czasu sekwencji w CI.
- **Decision**: FIXED (Fix in plan)

### F2 — --database obejmuje tylko bazę; pliki i kontrole idą w bieżącą instancję

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Lean Execution
- **Location**: Phase 1 §2 (Signature) · Phase 3 (drill, kroki 3–4)
- **Detail**: `--database` zmienia tylko cel db:wipe/psql; rozpakowanie plików, kontrola „clean” i safety backup:run nadal dotyczą bieżącej instancji / domyślnego połączenia. Żaden test jej nie używa. Drill w §6.3 ma używać komendy „on a throwaway instance” — `--database=drill --force` na produkcji usunąłby produkcyjne `private/sources`.
- **Fix A ⭐ Recommended**: Usuń `--database`; §6.3 opisuje postawienie osobnej jednorazowej instancji do drillu.
  - Strength: Komenda zawsze działa na jednej spójnej instancji; mniej kodu i ścieżek.
  - Tradeoff: Drill wymaga drugiej instancji, nie tylko drugiej bazy.
  - Confidence: HIGH — opcja nie ma konsumenta w planie.
  - Blind spot: Nie sprawdzono, jak wygodnie postawić drugą instancję na VPS.
- **Fix B**: Zostaw `--database`, dodaj `--storage-root` i zabroń kopii bezpieczeństwa dla niedomyślnego połączenia.
  - Strength: Drill na tym samym hoście bez drugiego compose.
  - Tradeoff: Więcej opcji i kombinacji do przetestowania.
  - Confidence: MED — łatwo przeoczyć kolejne miejsce zależne od instancji.
  - Blind spot: Uprawnienia zapisu w innym katalogu w kontenerze prod.
- **Decision**: FIXED (Fix A)

### F3 — Nieokreślony sposób znalezienia wpisu dumpa w archiwum

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §2 (walidacja „the dump entry exists”)
- **Detail**: `database_dump_filename_base = 'database'` → wpis `postgresql-<baza_źródłowa>.sql`. Wzorzec `BackupTestCase::dumpEntryName()` wylicza nazwę z bieżącego połączenia — w komendzie da `postgresql-testing_restore.sql` w scenariuszu clean i chybi na serwerze z innym `DB_DATABASE`.
- **Fix**: Komenda szuka dokładnie jednego wpisu `db-dumps/*.sql`; 0 lub >1 → czytelny błąd przed jakąkolwiek zmianą.
- **Decision**: FIXED (Fix in plan)

### F4 — arrangeBackupData nie będzie widoczny w BackupRestoreTest.php

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §3 („Arrange (shared helper, extended)”)
- **Detail**: Helper jest w `tests/Backup/BackupContentsTest.php:16`; Pest ładuje tylko wskazane pliki, więc kryterium 2.1 (sam `BackupRestoreTest.php`) padnie na „undefined function”. `attachBirth`/`linkParent` żyją w `tests/Pest.php`.
- **Fix**: Przenieś `arrangeBackupData` do `tests/Pest.php` albo zrób z niego metodę `BackupTestCase`; zapisz w kontrakcie Phase 2.
- **Decision**: FIXED (Fix in plan — BackupTestCase method)

### F5 — Kontrakt przełączenia instancji pomija useStoragePath

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1 (helper „switches the app to a clean instance”)
- **Detail**: Komenda wypakowuje do `storage_path('app')`, a weryfikuje przez dysk `local`. Helper aktualizuje tylko korzenie dysków; bez `$app->useStoragePath()` pliki trafią do starego korzenia, a weryfikacja zgłosi braki.
- **Fix**: Kontrakt helpera: `useStoragePath(nowy root)` + korzenie `local`/`public`/`backups` + `Storage::forgetDisk` + `DB::purge`; `tearDown` cofa wszystko przed TRUNCATE.
- **Decision**: FIXED (Fix in plan)

### F6 — Brak trybu konserwacji podczas restore na produkcji

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 (blok „Odtworzenie (komenda)”)
- **Detail**: Między `db:wipe` a końcem `psql` żądania trafiają w pusty schemat; dump przywraca też `sessions`/`cache` z chwili kopii (stare sesje znów ważne).
- **Fix**: §6.3: przed restore `scripts/artisan.sh down`, po `up` (opcjonalnie TRUNCATE `sessions`/`cache`).
- **Decision**: FIXED (Fix in plan)

### F7 — Drobne nieścisłości w planie

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State Analysis · Phase 2 §3 („Clean instance”)
- **Detail**: (a) „no app/Console/Commands directory” — katalog istnieje (pusty); (b) „every durable table except passkeys has rows” — arrange wstawia passkeys, wyjątek zbędny; (c) „optionally migrate --force” — test powinien wybrać jedną ścieżkę.
- **Fix**: Popraw (a); usuń wyjątek dla passkeys; zawsze `migrate --force` przed restore.
- **Decision**: FIXED (Fix in plan)
