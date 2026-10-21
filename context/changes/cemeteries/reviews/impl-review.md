<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Cmentarze jako rekord miejsca

- **Plan**: context/changes/cemeteries/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-21
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 5 warnings, 2 observations

Automated checks run in this review: Pest (15 plików testowych + `tests/Backup`, 648 passed), Larastan (0 errors), `npm run check`, `npm run types:check`, `npm run test:js` (392 passed). Not re-run: build, Playwright, pint, rollback migracji.

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | FAIL |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Findings

### F1 — Faza 4 odeszła od planu: pochowanie tylko na cmentarzu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Plan Adherence
- **Location**: app/Http/Requests/PersonStoreRequest.php:30-35, app/Actions/Persons/MapsEventInput.php:29-47, database/migrations/2026_10_21_100000_assign_cemeteries_to_burials.php, app/Http/Resources/PersonFormResource.php:53
- **Detail**: Plan: pochowanie = miejscowość + opcjonalny cmentarz (`burial.locality_id` i `burial.cemetery_id`, reguła „Ten cmentarz leży w innej miejscowości.”, `PersonFormResource.burial = {locality, cemetery}`); „What We're NOT Doing” wyklucza tworzenie cmentarzy dla miejscowości pochowań, a test migracji wymaga, by pochowanie z samą miejscowością zostało. Zbudowano: brak `burial.locality_id`, pochowanie istnieje tylko z cmentarzem, migracja po dopasowaniu z nagrobków tworzy/ponownie używa cmentarza bez nazwy dla każdego pozostałego pochowania z miejscowością, `PersonFormResource.burial = {cemetery}`. PRD (FR-022) i CLAUDE.md zostały dopisane do zbudowanej wersji, plan nie. Krok 4.9 Progress („pozostałe tylko miejscowość”) jest odhaczony, choć stan faktyczny jest inny. Skutek: nie da się już wpisać pochowania znanego tylko z miejscowości.
- **Fix A ⭐ Recommended**: Dopisać do planu addendum opisujące zbudowany model (cmentarz jedynym miejscem pochowania, migracja tworzy cmentarze bez nazwy) i poprawić krok 4.9
  - Strength: PRD, CLAUDE.md, kod i testy już są spójne; źródło prawdy znów zgadza się z kodem.
  - Tradeoff: Plan staje się ruchomym celem; decyzja (utrata pochowania „tylko miejscowość”) zostaje.
  - Confidence: MED — wygląda na świadomą decyzję z implementacji, ale w repo nie ma jej uzasadnienia.
  - Blind spot: Nie sprawdzono, czy autor zatwierdził zmianę poza planem.
- **Fix B**: Przywrócić model z planu (locality + opcjonalny cmentarz)
  - Strength: Zgodność z planem i z NOT-doing.
  - Tradeoff: Przeróbka żądania, akcji, formularza, migracji, PRD, CLAUDE.md i testów.
  - Confidence: LOW — duży zakres, a migracja już mogła zostać uruchomiona na lokalnej bazie.
  - Blind spot: Nie sprawdzono, jak daleko zbudowane zachowanie zależy od modelu cmentarz-only.
- **Decision**: FIXED via Fix A (addendum w plan.md, adnotacja przy 4.9)

### F2 — Scalanie miejscowości przenosi cmentarze bez blokad

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Localities/MergeLocality.php (mergeCollidingCemeteries, cemeteries()->update)
- **Detail**: Akcja blokuje tylko dwa wiersze miejscowości, a cmentarze czyta zwykłym `get()` i scala przez `MergeCemetery::moveInto` bez blokad. Równoległe `MergeCemetery`/`DeleteCemetery` nie jest szeregowane; cmentarz dodany do duplikatu po odczycie łamie klucz obcy przy usuwaniu miejscowości (500).
- **Fix**: Zablokować (`lockForUpdate()`, `orderBy id`) cmentarze obu miejscowości w transakcji i dopiero po tym je wczytać.
  - Strength: Taki sam wzorzec jak `DeleteCemetery`/`MergeCemetery`.
  - Tradeoff: Dodatkowe blokady przy scalaniu; dla aplikacji jednego użytkownika ryzyko niskie.
  - Confidence: MED — recenzent nie uruchamiał scenariusza współbieżnego.
  - Blind spot: Nie sprawdzono, czy kontroler łapie SQLSTATE 23503.
- **Decision**: FIXED (lockForUpdate na cmentarzach obu miejscowości w mergeCollidingCemeteries)

### F3 — Store/UpdateCemetery: brak obsługi 23503 i blokady wiersza

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Cemeteries/StoreCemetery.php:24, app/Actions/Cemeteries/UpdateCemetery.php:24-27
- **Detail**: Miejscowość usunięta/scalona między walidacją a zapisem daje niezłapany błąd 23503 (500); `UpdateCemetery` pracuje na nieaktualnym modelu bez `lockForUpdate()` i po równoległym scaleniu zapisuje 0 wierszy, a kontroler pokazuje „Zapisano”.
- **Fix**: W `UpdateCemetery` pobrać wiersz z `lockForUpdate()->firstOrFail()`; w obu akcjach zamienić 23503 na `ValidationException` na `locality_id`.
- **Decision**: FIXED (UpdateCemetery lockForUpdate; 23503 -> błąd walidacji locality_id w Store/UpdateCemetery)

### F4 — Migracja nagrobków: brak strażnika idempotencji i niepełny komentarz `down()`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_10_20_130000_move_gravestone_cemeteries_to_cemeteries.php:39-42, :56-57
- **Detail**: `up()` nadpisuje `sources.cemetery_id` także tam, gdzie już jest ustawione (brak `whereNull('cemetery_id')`). `down()` gubi nazwy, zarządców i opisy cmentarzy, a komentarz wspomina tylko przypadek pustej miejscowości.
- **Fix**: Dodać `whereNull('cemetery_id')` w `up()` i dopisać w komentarzu `down()`, że wycofanie jest stratne dla pól cmentarza.
- **Decision**: FIXED (whereNull('cemetery_id') w up(), komentarz down())

### F5 — Okno „Dodaj cmentarz” nie pokazuje błędów pól

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/cemeteries/new-cemetery-dialog.tsx:116-118
- **Detail**: Każdy błąd zamieniany na ogólne „Nie udało się dodać cmentarza.”; 422 dla `name` (np. „W tej miejscowości jest już cmentarz bez nazwy”) i `locality_id` nie jest wyświetlany. Subagent nie czytał pliku do końca — do potwierdzenia przed poprawką, porównać z `new-parish-dialog.tsx`.
- **Fix**: Pokazać `http.errors.name` / `http.errors.locality_id` pod polami, jak w oknie parafii.
- **Decision**: DISMISSED (fałszywy alarm: new-cemetery-dialog.tsx:157,166,199,228 pokazuje błędy pól jak new-parish-dialog.tsx; ogólny komunikat to tylko zapasowy saveError)

### F6 — Porównanie nazw przy scalaniu miejscowości w PHP zamiast w SQL

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Localities/MergeLocality.php (mergeCollidingCemeteries)
- **Detail**: `mb_strtolower` w PHP vs `lower()` w indeksie Postgresa; dla nietypowego Unicode mogą się rozjechać i wywołać 500 na indeksie. Ryzyko niskie dla polskich nazw.
- **Fix**: Wykrywać kolizje zapytaniem z tym samym wyrażeniem `lower(coalesce(name,''))`.
- **Decision**: FIXED (klucz nazwy z SQL lower(coalesce(name,'')) w mergeCollidingCemeteries)

### F7 — Pole `cemetery_locality_id` w SourceResource to dziś wartość wyliczana

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Resources/SourceResource.php:42,77
- **Detail**: Zgodnie z planem nazwa pola klienta została, ale wartość pochodzi z `cemetery.locality_id`; użycia w `sources-index.ts:284` i `source-description-fields.tsx:99`. Przy usunięciu starej kolumny trzeba pamiętać, że to nie ta kolumna.
- **Fix**: Zanotować w zmianie sprzątającej starą kolumnę (bez edycji teraz).
- **Decision**: FIXED (notatka w follow-ups/review-fixes.md dla zmiany usuwającej starą kolumnę)
