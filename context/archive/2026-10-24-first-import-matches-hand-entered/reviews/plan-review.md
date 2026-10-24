<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Pierwszy import dopasowuje osoby wpisane ręcznie

- **Plan**: context/changes/first-import-matches-hand-entered/plan.md
- **Mode**: Deep
- **Date**: 2026-10-24
- **Verdict**: REVISE
- **Findings**: 1 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding
Grounding: 12/12 paths ✓, 11/11 symbols ✓, brief↔plan ✓, Progress↔Fazy ✓

## Findings

### F1 — Fazy 2–3 łamią komendę i własne kryteria

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 (pkt 2), Faza 3 (pkt 2), kryteria 2.3 i 3.2
- **Detail**: Faza 2 zmienia `PlanFirstImport::handle` na 3 argumenty, Faza 3 `ApplyFirstImport::handle` na 3 argumenty; komenda woła oba z 2 (`ImportResearch.php:55`, `:89`) i zmienia się dopiero w Fazie 4. Larastan (poziom 7) zgłosi błąd liczby argumentów, więc kryteria 2.3 i 3.2 nie przejdą. Dopisanie w komendzie samego `allNew` przy nowej bramce (`repo_id`) dałoby stan pośredni, w którym import na ręcznym drzewie tworzy duplikaty. Istniejący test „person added after planning” w `FirstImportApplyTest` zakłada starą bramkę.
- **Fix**: W Fazie 2 i 3 minimalna zmiana komendy: zachować odmowę dla drzewa z osobami do Fazy 4, przekazać `IdentityDecisions::allNew(...)` i puste `ConflictResolutions`. W Fazie 3 wymienić test „person added after planning” do przepisania.
- **Decision**: FIXED

### F2 — guardParentSexChange jest prywatne, a UpdatePerson poza zakresem

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Faza 3, pkt 2 vs „What We're NOT Doing”
- **Detail**: `UpdatePerson::guardParentSexChange` (`:130`) jest `private`, czyta przez globalne `Person::query()` i rzuca `ValidationException`. Plan każe ją ponownie sprawdzić w `ApplyFirstImport`, a jednocześnie zabrania zmian w `UpdatePerson` — zostaje kopia reguły.
- **Fix A ⭐ Recommended**: Wyciągnąć regułę do wspólnego miejsca (trait w `app/Actions/Persons/`) używanego przez `UpdatePerson` i `ApplyFirstImport`; dopisać wyjątek do „NOT Doing”.
  - Strength: Jedna reguła; istniejące testy `UpdatePerson` pilnują refaktoru.
  - Tradeoff: Dotyka `UpdatePerson` (mały, mechaniczny diff).
  - Confidence: HIGH — metoda jest samodzielna, zależy tylko od `ParentChildGraph`.
  - Blind spot: Globalne `Person::query()` w regule warto zawęzić do drzewa.
- **Fix B**: Pominąć ponowne sprawdzenie w zapisie; polegać na planerze i `CreateParentChildRelation`.
  - Strength: Zero zmian poza importem.
  - Tradeoff: Relacja dodana ręcznie w trakcie sesji przejdzie bez kontroli płci.
  - Confidence: MED — brief akceptuje ryzyko zmian w trakcie sesji.
  - Blind spot: None significant.
- **Decision**: FIXED

### F3 — Dopasowana osoba bez urodzenia traci urodzenie z repo

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Key Discoveries, Faza 2 `PlannedMatch`, Faza 3 pkt 3
- **Detail**: „Ręczna osoba ma zawsze urodzenie” jest prawdą tylko dla ścieżek przez `CreatePerson` (dev 0/110 bez urodzenia), ale nie dla `RestoreBackup` ani `Person::factory()` (oba helpery `ImportResearchCommandTest`). Plan nie mówi, co zrobić, gdy repo ma `urodzenie`, a drzewo nie — data i klucz `O-x/urodzenie` przepadają.
- **Fix**: W `PlannedMatch` dodać „urodzenie do utworzenia” (jak zgon), tworzyć w Fazie 3 przez `createEvent`; przypadek testowy w Fazie 2.
- **Decision**: FIXED

### F4 — Uzupełnienie i konflikt daty ślubu nie mają miejsca w modelu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 pkt 1 (`PlannedMarriage`, `PlannedFill`), Faza 3 pkt 2.6
- **Detail**: Data ślubu „idzie przez PlannedFill / ValueConflict dopasowanej pary”, ale `PlannedFill` nie wskazuje wydarzenia, a para to dwa `PlannedMatch` — grozi podwójnym pytaniem albo zgadywaniem. Klucz konfliktu ślubu niezdefiniowany; typ „wartości z repo” mieszany.
- **Fix**: `PlannedMarriage` dostaje `?PlannedFill $dateFill` i `?ValueConflict $dateConflict` z kluczem `<repo_id ślubu>/data`; `FirstImportPlan.conflicts` zbiera z dopasowań i małżeństw; typ wartości `PlannedFill` w PHPDoc (`string|Gender|ResearchDate`).
- **Decision**: FIXED

### F5 — W trybie „each” domyślny kandydat może być już zajęty

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 4, pkt 5
- **Detail**: Przy `each` pewne i wątpliwe są przeplatane wg id repo, a opcje pomijają wybranych wcześniej kandydatów — wątpliwa osoba może zająć jedynego kandydata pewnej, więc domyślna opcja nie istnieje. Pytania o pewne mają tytuł „Wątpliwe k/N”.
- **Fix**: Przy `each` najpierw pewne („Pewne k/N”), potem wątpliwe; gdy kandydat zajęty, domyślne = `skip`.
- **Decision**: FIXED

### F6 — Dokładniejsza data z repo to zawsze konflikt

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 2 pkt 1 („Data różna w czymkolwiek → konflikt”)
- **Detail**: Drzewo „1801” vs repo „1801-05-12” to konflikt, nie uzupełnienie. W dev 75/110 osób ma tylko rok — to dziesiątki pytań o samo doprecyzowanie, a domyślne „zostaw z aplikacji” gubi datę dzienną. Nie zdefiniowano też „różne” dla imion („Łukasz”/„Lukasz”, podwójna spacja).
- **Fix**: Doprecyzowanie (ten sam rodzaj i rok, drzewo bez miesiąca/dnia) = uzupełnienie; imiona porównywać po przycięciu i scaleniu spacji, z rozróżnieniem znaków.
  - Strength: Mniej decyzji; wprost cel PRD Open Questions 4.
  - Tradeoff: Doprecyzowanie widać tylko w tabeli „Uzupełnienia”.
  - Confidence: MED — skala oszacowana, repo nie ma jeszcze bloków.
  - Blind spot: Czy autor chce widzieć każdą zmianę daty pojedynczo.
- **Decision**: FIXED

### F7 — Płeć: relacje cięte przed rozstrzygnięciem, raport w złej liście

- **Severity**: 🔍 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details (wspólny graf), Faza 2 „Konflikt płci”
- **Detail**: Graf liczy się z płcią z aplikacji przy konfliktach; relacja odcięta „ta sama płeć” nie wraca, gdy autor wybierze płeć z repo. Wpisy „płeć z repo pominięta” trafiają do `skippedPersons`, choć osoba jest importowana — liczba „pominięte osoby” zawyżona. Awarii zapisu nie ma.
- **Fix**: Osobna lista (`skippedValues`, „Pominięte wartości”) i zdanie o ograniczeniu w „What We're NOT Doing”.
- **Decision**: FIXED
