<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Porządki w źródłach — nagrobek bez roku, archiwum i numeru wpisu

- **Plan**: context/changes/source-cleanup/plan.md
- **Mode**: Deep
- **Date**: 2026-10-22
- **Verdict**: REVISE → SOUND (po poprawkach)
- **Findings**: 1 critical, 2 warnings, 0 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | FAIL |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | PASS |

## Grounding
17/17 paths ✓, 6/6 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — Zmiana rodzaju akt → nagrobek łamie niezmiennik i gubi dane

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Faza 1 §3 (UpdateSource), Desired End State
- **Detail**: `UpdateSource` nie rusza pivotu (`UpdateSource.php:17`). Akt z numerami wpisu zmieniony na nagrobek zachowuje `entry_number`, a „nr X” dalej się wyświetla. Przycisku nie ma, a PATCH zwraca 404, więc numeru nie da się usunąć. Rok i archiwum są kasowane bez potwierdzenia, bo `wouldEraseRecordFields` (`SourceUpdateRequest.php:66-79`) i `erasedRecordFields` (`source-description.ts:65`) ich nie obejmują.
- **Fix A ⭐ Recommended**: Zmiana na nagrobek przenosi rok, archiwum i numery wpisu do notatki (jak migracja) i zeruje pivot
  - Strength: Jedna reguła dla starych i nowych danych; nic nie ginie; dialog bez zmian.
  - Tradeoff: Logika „do notatki” w dwóch miejscach (migracja + UpdateSource).
  - Confidence: HIGH — format linii już ustalony w planie.
  - Blind spot: Limit notatki 5000 znaków po doklejeniu.
- **Fix B**: Zerowanie pivotu + rozszerzenie potwierdzenia zmiany rodzaju
  - Strength: Zgodne z istniejącym wzorcem potwierdzenia.
  - Tradeoff: Wartości giną; formularz musi znać numery z pivotu.
  - Confidence: MED
  - Blind spot: Dostarczenie numerów z pivotu do formularza źródła.
- **Decision**: FIXED (Fix A) — Faza 1 §3, §5, Desired End State

### F2 — excludeIf w EventSourceStoreRequest wysyła zapytanie z niezwalidowanym source_id

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 §4 (Store)
- **Detail**: Closure `Rule::excludeIf` wykonuje się przy parsowaniu reguł, przed `integer`/`exists` dla `source_id`. Nieliczbowe `source_id` daje QueryException na PostgreSQL, czyli 500 zamiast 422.
- **Fix**: Bez excludeIf; `LinkSourceToEvent` po walidacji odczytuje rodzaj źródła i dla nagrobka zapisuje `entry_number = null`.
- **Decision**: FIXED — Faza 1 §4

### F3 — TreeViewDemoSeeder tworzy nagrobek z rokiem 1932

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 (brak w planie)
- **Detail**: `database/seeders/TreeViewDemoSeeder.php:369-373` tworzy nagrobek z `'year' => 1932`; po `migrate:fresh --seed` niezmiennik jest złamany.
- **Fix**: `'year' => 1932` → `'note' => 'Rok: 1932'`.
- **Decision**: FIXED — Faza 1 §6
