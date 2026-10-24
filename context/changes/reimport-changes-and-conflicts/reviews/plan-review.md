<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Kolejny import z podglądem zmian i konfliktów

- **Plan**: context/changes/reimport-changes-and-conflicts/plan.md
- **Mode**: Deep
- **Date**: 2026-10-24
- **Verdict**: REVISE → SOUND (po poprawkach)
- **Findings**: 1 critical · 3 warnings · 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | FAIL |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
12/12 paths ✓, 14/14 symbols ✓, brief↔plan ✓, Progress↔Fazy ✓

## Findings

### F1 — „Nic do zapisania” gubi decyzję „Zostaw usunięte”

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Faza 5 #1 krok 8; Migration Notes
- **Detail**: Warunek „nic do zapisania” pomijał keptDeletedSnapshots. Przebieg, w którym „Zostaw usunięte” było jedyną decyzją, nie zapisywał flagi, więc kolejny przebieg pytał znowu (test „keep → drugi przebieg nie pyta” by padł). Drzewa z S-02/S-03 bez zmian nigdy nie dostawały pamięci, wbrew Migration Notes.
- **Fix A ⭐ Recommended**: Każda różnica w pamięci (snapshots / keptDeletedSnapshots ≠ zapisane S, niepuste forgottenSnapshots) liczy się jako zapis.
  - Strength: Jedna reguła zamyka „zostaw usunięte”, pamięć S-02 i zbieżność R→A.
  - Tradeoff: Przebieg tylko z pamięcią pyta o zgodę i robi kopię na produkcji.
  - Confidence: HIGH — planer ma wszystkie listy.
  - Blind spot: Snapshots muszą być znormalizowane jak zapisane S.
- **Fix B**: Dopisać do listy tylko „zachowane jako usunięte”.
  - Strength: Minimalna edycja.
  - Tradeoff: Drzewa S-02 bez zmian nadal bez pamięci.
  - Confidence: HIGH.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — krok 8 i pytanie o zgodę (`pamięć importu {P}`) w Fazie 5 oraz przypadek testowy drzewa bez pamięci.

### F2 — Plik bez rozpoznanego bloku wygląda na usunięty z repo

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Key Discoveries; Faza 2 #3 i #4
- **Detail**: Plik, którego pierwsza linia nie jest `---`, trafia do `filesWithoutBlock` (ParsePersonBlock.php:38-39), a nie do `skipped`. Osoba wyglądała na usuniętą w repo. Odłączenie jest nieodwracalne i po poprawieniu pliku osoba by się zdublowała.
- **Fix**: Taki plik z linią `id: O-…` podaje id do skippedRepoIds, a ostrzeżenie bez id włącza hasSkippedWithoutId(). Dodano testy w ResearchRepoReadTest i ClassifyRepoRecordsTest.
- **Decision**: FIXED

### F3 — Osoba „zostawiona jako usunięta” nie ma reguł dla swoich kluczy

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 2 #4 grupowanie; Faza 3 graf i małżeństwa
- **Detail**: Klucze osoby z keptDeleted nie były do niej dołączane. Zmiana daty urodzenia w repo dałaby osobne pytanie o przywrócenie urodzenia nieistniejącej osoby. Nowe relacje i śluby z taką osobą nie miały reguły.
- **Fix**: Klucze są dołączane także w keptDeleted, a R≠S dowolnego z nich zadaje ponownie pytanie o osobę. Planer traktuje taką osobę jak pominiętą (powód „osoba usunięta w aplikacji”). Dodano testy.
- **Decision**: FIXED

### F4 — Usunięcie zgonu zostawia pochówek; skutki niepełne

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 #4 RemovalImpact; Faza 4 #2 krok 2
- **Detail**: `$event->delete()` na zgonie zostawiłby pochówek, a UpdatePerson.php:76-77 usuwa oba. RemovalImpact nie wymieniał pochówku ani innych własnych wydarzeń osoby kasowanych przez DeletePerson.
- **Fix**: Zgon jest usuwany razem z pochówkiem, a RemovalImpact pokazuje pochówek i liczbę innych wydarzeń. Dodano testy.
- **Decision**: FIXED

### F5 — `upsert` nie stosuje castów: `values` wymaga json_encode

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 #4
- **Detail**: HasOneOrMany::upsert dokłada tree_id i timestamps, ale Builder::upsert wiąże wartości bez castów.
- **Fix**: W kontrakcie: `values` idzie przez `json_encode`.
- **Decision**: FIXED

### F6 — Ścieżka „data jako obiekt YAML” pomija walidator i id

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State; Faza 2 #3
- **Detail**: ParsePersonBlock.php:64-69 pomija wpis przed walidatorem, więc id nie jest sprawdzane wzorcem.
- **Fix**: Ścieżka wymieniona w kontrakcie, id sprawdzane osobno. Dodano test.
- **Decision**: FIXED

### F7 — Klasyfikacja liczona dwa razy, wbrew deklaracji

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 #4 Intent vs Faza 3 #2
- **Detail**: PlanImport sam wołał ClassifyRepoRecords, więc komenda pytała na podstawie innej klasyfikacji niż plan.
- **Fix**: `PlanImport::handle(Tree, ResearchRepoReading, RepoRecordClasses, ImportDecisions)`.
- **Decision**: FIXED

### F8 — Komenda musi pominąć urodzenia przy pytaniach o usunięte w repo

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 5 #1 krok 3
- **Detail**: Nie było powiedziane, kto wpisuje urodzeniom `detach` ani czy liczą się do N.
- **Fix**: Urodzenia są poza pytaniami i poza k/N, a komenda wpisuje im `detach`.
- **Decision**: FIXED

### F9 — Porównanie dat po label() ukrywa różnice w złych wierszach

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Critical Implementation Details
- **Detail**: label() ignoruje np. miesiąc w zakresie w starych wierszach, a S-03 porównuje obiekty przez `==`.
- **Fix**: R z A są porównywane obiektowo, a label() służy jako postać S. ThreeWayValue dostaje `?bool $repoIsApp`.
- **Decision**: FIXED
