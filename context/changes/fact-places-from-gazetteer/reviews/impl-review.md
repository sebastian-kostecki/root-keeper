<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Miejsca faktów i pochowania ze spisu (S-07)

- **Plan**: context/changes/fact-places-from-gazetteer/plan.md
- **Scope**: Full plan (Phases 1–4 of 4)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-06
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Success criteria run: Pest (migration, Persons, Marriages, EventSources, OwnershipBoundaries, WriteTargets, Localities) 265 passed; Larastan 0 errors. Manual items in Progress are all ticked with commit shas.

## Findings

### F1 — Brak indeksu na events.locality_id

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_10_06_070850_link_event_places_to_localities.php:21
- **Detail**: Plan required „foreignId nullable, constrained, indeks”. `foreignId()->constrained()` w Postgresie nie tworzy indeksu na kolumnie FK, więc liczenie odwołań i scalanie w S-11 będą skanować `events`.
- **Fix**: Dodaj `->index()` w migracji (lub osobną migracją, jeśli ta poszła już na produkcję).
- **Decision**: FIXED — Fix now (dodano ->index() w migracji, develop only)

### F2 — Brak klucza `burial` w payloadzie kasuje pochowanie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Persons/UpdatePerson.php:64-72
- **Detail**: Dla osoby zmarłej brak `burial.locality_id` usuwa pochowanie. Zgodne z planowanym „pełnym zastąpieniem” i obecny front zawsze wysyła `burial`, ale klient z częściowym payloadem po cichu straci pochowanie.
- **Fix**: Zostaw kontrakt i przypnij go testem Pest (zapis bez klucza `burial` usuwa pochowanie) z komentarzem w akcji.
  - Strength: Spójne z `birth`/`death` i planem; zero zmiany zachowania.
  - Tradeoff: Ryzyko dla przyszłych klientów pozostaje, tylko udokumentowane.
  - Confidence: HIGH — kontrakt jest wprost w sekcji Critical Implementation Details.
  - Blind spot: None significant.
- **Decision**: FIXED — Fix now (test pinning full-replace + komentarz w UpdatePerson)

### F3 — Brak testu gałęzi wyścigu w inline store

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: app/Http/Controllers/InlineLocalityController.php, tests/Feature/LocalitiesTest.php
- **Detail**: Ścieżka `ValidationException` → ponowne wyszukanie → 200 nie ma testu.
- **Fix**: Dodaj test z mockiem/spy `StoreLocality` rzucającym `ValidationException` przy istniejącej miejscowości.
- **Decision**: FIXED — Fix now (test wyścigu w LocalitiesTest)

### F4 — Testy własności poza OwnershipBoundariesTest

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/OwnershipBoundariesTest.php
- **Detail**: Plan wymieniał ten plik, ale nie zmieniono go; obce/nieistniejące `*.locality_id` są pokryte w PersonsTest (dataset) i MarriagesTest. Pokrycie kompletne.
- **Fix**: Brak — odnotuj odstępstwo (pokrycie istnieje).
- **Decision**: ACCEPTED — odstępstwo odnotowane, pokrycie w PersonsTest/MarriagesTest

### F5 — `burial_place` zawsze null na liście osób

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: app/Http/Resources/PersonResource.php:58, app/Http/Controllers/PersonController.php:37
- **Detail**: Indeks ładuje tylko Birth/Death, więc `burial_place` jest tam null. Nieszkodliwe dziś, pułapka, gdy lista pokaże pochowanie.
- **Fix**: Zwracaj `burial_place` tylko gdy relacja załadowana lub zostaw.
- **Decision**: FIXED — Fix differently (indeks ładuje też pochowania, więc burial_place jest poprawne zamiast null)
