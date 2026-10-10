<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Zakładki wydarzeń i notatki w panelu osoby (S-14)

- **Plan**: context/changes/person-events-and-note-tabs/plan.md
- **Mode**: Deep
- **Date**: 2026-10-10
- **Verdict**: SOUND
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
17/17 paths ✓, 7/7 symbols ✓ (Gate::inspect w Person*Request, PartialDate::format, PersonPreviewLink, rowDiff, labelFor, marriageSortKey, autofokus bez textarea), brief↔plan ✓

## Findings

### F1 — Pochowanie ląduje za pośmiertnymi wydarzeniami rodziny

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details (klucz sortowania); Faza 2 §1
- **Detail**: Pochowanie nie ma daty, bo wiersz ma tylko miejsce (`UpdatePerson.php:69-75`), więc klucz `[rok null ? 1 : 0, …]` zawsze wrzuca je do grupy „bez roku”. Przy zgonie 1900, zgonie żony 1920 i zgonie syna 1950 lista wyjdzie taka: „Zgon 1900 → Zgon żony 1920 → Zgon syna 1950 → Pochowanie”. Ranga 6 działa tylko przy identycznym kluczu daty, a pochowanie nigdy go nie ma.
- **Fix**: Pochowanie sortuje się kluczem daty własnego zgonu (ranga 6, czyli zaraz po zgonie). Bez roku zgonu idzie na koniec. Do testów porządku dochodzi przypadek „zgon 1900, zgon syna 1950”.
- **Decision**: FIXED

### F2 — „Tylko kolumna note” w WriteTargetsTest nie przejdzie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §4; Testing Strategy
- **Detail**: `rowDiff` (`tests/Pest.php:209`) porównuje całe wiersze i nie pomija znaczników czasu, więc zapis notatki zmieni też `people.updated_at`. Pozostałe testy w pliku filtrują `updated_at` jawnie (`WriteTargetsTest.php:447-450`).
- **Fix**: Kontrakt brzmi teraz „zmienione kolumny ⊆ {note, updated_at}”, według wzorca `array_diff`.
- **Decision**: FIXED

### F3 — Test liczby zapytań nie obejmuje małżonków

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §3–§4
- **Detail**: Plan dokłada dwa eager-loady (`children.events.locality`, `marriages.participants.events.locality`), a test porównuje tylko 1 i 3 dzieci. Projekt nie ma `preventLazyLoading`, więc pominięcie drugiej ścieżki przejdzie po cichu.
- **Fix**: Test zapytań zmienia też liczbę małżonków ze zgonem w miejscowości (1 kontra 2).
- **Decision**: FIXED
