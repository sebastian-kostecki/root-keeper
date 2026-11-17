<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Źródła ze skanami podpięte do faktów

- **Plan**: context/changes/import-sources-with-scans/plan.md
- **Mode**: Deep
- **Date**: 2026-11-16
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 1 critical, 4 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
54/54 paths ✓, 9/9 claims checked (8 ✓, 1 partial: StoreSource does not validate type/size — the plan already knows), brief↔plan ✓

## Findings

### F1 — Powiązania źródeł pominiętych lub odłączonych w tym przebiegu znikają

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details („Powiązania…”), Faza 2 §4 i §5, Faza 4 §3
- **Detail**:
  - Plan obiecuje, że zdarzenie wskazujące pominięte źródło nie jest porównywane. Faza 2 §4 wyrzucała jednak takie odwołanie z odczytu. Wtedy R≠S przy A=S daje Changed, czyli pozycję „odepnij”, którą „Zatwierdź wszystkie” wykonuje.
  - Ta sama luka dotyczyła źródeł odłączanych, usuwanych i zostawionych usuniętych w tym przebiegu: A liczony w chwili planowania wciąż je zawiera.
  - Pamięć `zrodla` z `RepoValues::event` (nieprzefiltrowana) rozjeżdżała się z przefiltrowanym R.
- **Fix**:
  - Odwołania zostają w `ResearchEvent`.
  - Zbiór niedostępnych ID (pominięte, zostawione usunięte, odłączane, usuwane) filtruje R, S i A spójnie.
  - Pamięć `zrodla` to R po filtrze, budowane w `PlanImport` (Faza 4).
  - Nowe przypadki testów w Fazie 4.
- **Decision**: FIXED

### F2 — `zrodla` tylko u jednego małżonka gubi cały ślub

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Faza 2 §3, Faza 2 §1 (reguła dla repo)
- **Detail**: `resolvePair` (ReadResearchRepo.php:298) pomija małżeństwo przy dowolnej różnicy w obu plikach. Repo cytuje akt w prozie jednej osoby, więc `zrodla` wpisane u jednego małżonka zgubiłoby ślub.
- **Fix A ⭐ Recommended**: porównanie ślubu bez `zrodla`, scalanie list.
  - Lista pusta u jednego małżonka bierze listę drugiego.
  - Dwie niepuste różne listy dają ostrzeżenie i pominięcie.
  - Strength: Nie karze najnaturalniejszego sposobu pisania.
  - Tradeoff: Odrobina logiki w resolvePair.
  - Confidence: HIGH — zmiana lokalna.
  - Blind spot: None significant.
- **Fix B**: ścisłe porównanie plus reguła „wpisz u obojga”.
  - Strength: Zero nowej logiki.
  - Tradeoff: Każde przeoczenie gubi ślub.
  - Confidence: MED.
  - Blind spot: Liczba ślubów cytowanych jednostronnie.
- **Decision**: FIXED (Fix A)

### F3 — Brak mapowania wartości YAML na enumy i reguły dla bloku bez wyznania

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §1, Critical Implementation Details („Parafia jak cmentarz”)
- **Detail**:
  - Enumy `Religion`, `ParishKind`, `SourceKind` i `SourceRecordType` mają angielskie przypadki. Plan nie miał mapowania, a pisownia „greckokatolickie” różniła się od etykiety aplikacji „grekokatolickie”.
  - Blok bez wyznania miałby za kandydatów tylko parafie z religion=null. Obok istniejącej parafii powstałby więc duplikat bez wyznania.
- **Fix**:
  - Tabela mapowania w kontrakcie.
  - Blok bez wyznania: kandydaci o dowolnym wyznaniu, nigdy pewni.
  - Nowe przypadki testów w Fazach 2 i 3.
- **Decision**: FIXED

### F4 — Lista miejsc z `RepoKeyKind` i `SourceResource` jest niepełna

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Current State Analysis, Faza 1 §5, Faza 2 §5, Faza 4 §1
- **Detail**:
  - Poza 4 wymienionymi miejscami są:
    - `RepoKeys::personIdsOf` (bez default),
    - ClassifyRepoRecords :67/:148/:206-219 oraz `records()`,
    - ApplyImport :92/:165-168/:491-494/:601-603,
    - PlanImport (6 miejsc),
    - MatchResearchPlaces :176,
    - PlannedRemoval, PlannedRestoration, RepoRecord,
    - RepoKeysTest.
  - `SourcesTest.php:51-53` (`hasAll`) i `HandleInertiaRequests.php:88` też trzeba uwzględnić.
- **Fix**: Miejsca wpisane do planu, a `records()` ładuje źródła z `repo_id`.
- **Decision**: FIXED

### F5 — Nie wiadomo, jak zapisują się zatwierdzone pola i połączenie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 5 §1
- **Detail**: UpdateSource zeruje każde nieprzekazane pole. Ma też efekty uboczne:
  - synchronizuje buriedPeople,
  - woła FillBurialPlacesFromGravestone,
  - zeruje record_type i parish_id przy rodzaju innym niż akt.
  Nie sprawdza drzewa dla parish_id.
- **Fix**: Połączenie i zmiany pól przez UpdateSource z pełnym bieżącym stanem rekordu. parish_id tylko z tokenów sprawdzonych w verify.
- **Decision**: FIXED

### F6 — LinkSourceToEvent nie sprawdza acceptsSources ani drzewa

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 5 §2
- **Detail**: Oba sprawdzenia żyją tylko w EventSourceStoreRequest.
- **Fix**: `verify` sprawdza, że zdarzenia z dopinanymi źródłami należą do drzewa i spełniają acceptsSources().
- **Decision**: FIXED
