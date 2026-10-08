<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Scalanie i usuwanie miejscowości i parafii (S-11)

- **Plan**: context/changes/places-merge-and-delete/plan.md
- **Mode**: Deep
- **Date**: 2026-10-08
- **Verdict**: REVISE
- **Findings**: 1 critical · 1 warning · 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | FAIL |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding

25/25 paths ✓, 12/12 symbols ✓, brief↔plan ✓, Progress↔Faza ✓

## Findings

### F1 — Form Requesty scalania z authorize() = true łamią 404 i test granic

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 1 pkt 3 (LocalityMergeRequest), Faza 2 pkt 2 (ParishMergeRequest)
- **Detail**: Plan: „authorize() zwraca true (autoryzacja przez Gate w kontrolerze, jak w pozostałych)". LocalityUpdateRequest:19-22 i ParishUpdateRequest mają `authorize(): Response` z `Gate::inspect('update', $this->route(...))`. Form Request waliduje przed kontrolerem, więc cudzy wpis z pustym/błędnym `target_id` da 422, nie 404. Przewraca to auto-generowany przypadek „/ pusta treść" w `OwnershipBoundariesTest` i ujawnia istnienie cudzych id. Fixture `ownershipRecords` nie ma własnej miejscowości/parafii na `target_id`.
- **Fix**: `authorize(): Response` = `Gate::inspect('delete', $this->route('locality'|'parish'))` w obu requestach; „różny od scalanej" przez `Rule::notIn([$record->id])`; dodaj `ownLocality` / `ownParish` do `ownershipRecords`.
- **Decision**: FIXED (Fix in plan)

### F2 — Ścisłe sprzątanie spisów w teardown może uczynić zielone specyfikacje czerwonymi

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 4 pkt 1 (cleanupBySuffix)
- **Detail**: `deleteRecord` sprawdza odpowiedź, a sprzątanie to teardown fixture `suffix` w sześciu specyfikacjach. Miejscowość z sufiksem nadal używana przez rekord bez sufiksu da 422 i przewróci dotąd zielony test. Plan nie każe tego sprawdzić. JSDoc `createLocality` / `createParish` („no delete route until S-11") stanie się nieprawdziwy.
- **Fix**: Dodaj krok w Fazie 4: uruchom sześć specyfikacji pojedynczo przed pełną suitą; 422 → napraw kolejność/nazwę. Zaktualizuj oba JSDoc.
- **Decision**: FIXED (Fix in plan)

### F3 — DeleteImpactTest: scalanie zmienia wiersze, nie tylko je usuwa

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 pkt 7, Faza 2 pkt 4
- **Detail**: Plan: scalenie „usuwa dokładnie wiersz duplikatu i zredukowane zamieszkania", ale `rowDiff` ma też `changed`, a scalenie zmienia `events`, `residences`, `parishes`, `sources` i opis właściwego wpisu.
- **Fix**: Dopisz: zbiór `changed` równa się przepiętym wierszom (plus właściwy wpis przy uzupełnionym opisie); `added` puste.
- **Decision**: FIXED (Fix in plan)

### F4 — Okablowanie przycisków i dialogów w panelu niedookreślone

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 pkt 3-5
- **Detail**: `delete-source-dialog.tsx` to samodzielny dialog z `DialogTrigger` i własnym stanem; „Scal z…" w dialogu „Nie można usunąć" ma zamykać go i otwierać modal scalania, co wymaga stanu w panelu i propa `onMerge`. Przyciski są w render-propie `Form` (`isDirty`), więc muszą mieć `type="button"`, a dialogi stać poza `<Form>`.
- **Fix**: Opisz w planie: stan obu dialogów w panelu, `onMerge` w dialogu usuwania, `type="button"`, dialogi poza `<Form>`.
- **Decision**: FIXED (Fix in plan)

### F5 — Uzasadnienie wyścigu niedokładne; uzupełnienia pól z zablokowanych wierszy

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details, Faza 1 pkt 4-5
- **Detail**: W PostgreSQL wstawienie wiersza z FK bierze `FOR KEY SHARE` na rodzicu, który koliduje z `FOR UPDATE`, więc wstawienie poczeka i po usunięciu rodzica dostanie błąd FK; siatka `23503` jest raczej zbędna i nietestowalna w Pest. Opis i pola uzupełniane po scaleniu powinny pochodzić z zablokowanych wierszy, nie z modeli związanych przed transakcją.
- **Fix**: Przeredaguj uzasadnienie (siatka zostaje jako obrona) i dopisz, że `handle()` czyta opis/wyznanie/siedzibę z zablokowanych modeli.
- **Decision**: FIXED (Fix in plan)
