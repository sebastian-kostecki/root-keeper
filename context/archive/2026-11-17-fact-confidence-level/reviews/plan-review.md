<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Poziom pewności A–D przy fakcie

- **Plan**: context/changes/fact-confidence-level/plan.md
- **Mode**: Deep
- **Date**: 2026-11-17
- **Verdict**: REVISE (po poprawkach: SOUND)
- **Findings**: 1 critical, 1 warning, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding
44/44 paths ✓, 13/13 symbols ✓, brief↔plan ✓

## Findings

### F1 — Import: brakujące miejsca dla nowych pól poziomu

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 — Planowanie (2) i Zapis (3)
- **Detail**: Plan wymienia ramiona tylko w `writeUpdate()` i `guardPerson()`. Pomija: białą listę `PlanImport::FIELDS` (:88, filtr :443-453) i `hasWork` (:488), gdzie pozycje poziomu cicho wypadają; `ApplyImport::writeMatch()` (:1043-1052), gdzie pierwsze dopasowanie osoby rzuca „Plan ma niewłaściwą wartość pola”; warunek planowania znanego ślubu (PlanImport:1798), przez który przepada sama zmiana poziomu ślubu; `ImportPlan::fillCount()` (:98) i wydruk uzupełnień ślubu (ImportResearch:1227-1236).
- **Fix**: Dopisać te miejsca do Fazy 3 oraz testy Apply: pierwsze dopasowanie z poziomem i znany ślub ze zmianą samego poziomu.
- **Decision**: FIXED

### F2 — Pierwszy ponowny import ponownie pyta o zachowane usunięcia

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details — „Pierwszy ponowny import”
- **Detail**: `ClassifyRepoRecords::differsFromMemory()` (:369-384) porównuje sumę kluczy repo i pamięci, więc zachowane usunięcia rekordów z poziomem w repo wracają raz jako pytanie. `ImportPlan::changedSnapshots()` (:118-145) jednorazowo zawyża licznik „pamięć importu” i wymusza zapis bez zmian w repo.
- **Fix**: Opisać oba efekty jako zamierzone i dodać przypadek w ImportPlanTest.
- **Decision**: FIXED

### F3 — Odwrócone dziedziczenie żądań

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 — Walidacja (4)
- **Detail**: To `PersonUpdateRequest` oraz żądania dodania dziecka, rodzica i małżonka dziedziczą `PersonStoreRequest`, nie odwrotnie. Reguły poziomu obejmą więc też dodawanie krewnych z drzewa.
- **Fix**: Poprawić opis w Fazie 1.
- **Decision**: FIXED
