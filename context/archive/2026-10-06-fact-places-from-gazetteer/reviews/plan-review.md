<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Miejsca faktów i pochowania ze spisu (S-07)

- **Plan**: context/changes/fact-places-from-gazetteer/plan.md
- **Mode**: Deep
- **Date**: 2026-10-06
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
40/40 paths ✓, 9/9 symbols ✓, brief↔plan ✓ (lessons.md, contract-surfaces.md — brak, pominięte)

## Findings

### F1 — Dopisana miejscowość niewidoczna w sąsiednim pickerze

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 3 §1 — LocalityPicker
- **Detail**: Zgon i Miejsce pochowania to dwie instancje pickera w jednym formularzu; opcje „dopisane w tej instancji” nie są wspólne. Dopisanie „Lubomla” w Zgonie, potem „Lubomla” w Pochowaniu → tylko „Dodaj…” → `StoreLocality` rzuca „Miejscowość o tej nazwie jest już w spisie.” (`app/Actions/Localities/StoreLocality.php:24`). To samo przy nieaktualnej liście.
- **Fix A ⭐ Recommended**: `POST localities/inline` jako „znajdź albo utwórz” (istniejąca nazwa → 200 z tą miejscowością).
  - Strength: Usuwa całą klasę problemu (rodzeństwo, nieaktualna lista, inna karta).
  - Tradeoff: Zmienia zachowanie endpointu S-01 (`tests/Feature/LocalitiesTest.php:199` oczekuje 422).
  - Confidence: HIGH — naturalne zachowanie „Dodaj X”.
  - Blind spot: Brak.
- **Fix B**: Po dopisaniu `router.reload({only: ['localityOptions']})`.
  - Strength: Bez zmian backendu.
  - Tradeoff: Przerywalne wizyty Inertia 3.8; nie pomaga przy innej karcie.
  - Confidence: MED.
  - Blind spot: Interakcja z zapisem sekcji tuż po dopisaniu.
- **Decision**: FIXED (Fix A) — Faza 2 §8 (nowe `LocalityInlineStoreRequest`, przepisany test 422 → 200), Faza 3 §1 obsługuje 200 i 201, brief „In scope”.

### F2 — Losowy typ w EventFactory da niestabilne testy źródeł

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §7 — Fabryka
- **Detail**: `EventFactory::definition` losuje typ z `EventType::cases()` (`database/factories/EventFactory.php:24`); `EventSourcesTest` i `OwnershipBoundariesTest` tworzą `Event::factory()->create()` bez typu i podpinają źródła → losowe 404 po dodaniu Burial.
- **Fix**: `definition()` losuje tylko typy z `acceptsSources()`; pochowanie przez stan `burial()`.
- **Decision**: FIXED

### F3 — putEvent dla pochowania nadpisze zgon

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §2 — UpdatePerson
- **Detail**: `putEvent` wybiera wiersz ternary `Birth ? birth() : death()` (`app/Actions/Persons/UpdatePerson.php:116`) — pochowanie przez `putEvent` zaktualizowałoby wiersz zgonu.
- **Fix**: `match ($type)` z gałęzią Burial → `burial()`; test, że zapis pochowania nie zmienia wiersza zgonu.
- **Decision**: FIXED

### F4 — Niewymienione miejsca zmiany kontraktu `place`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §5, §9; Faza 3 §4; Faza 4 §1
- **Detail**: (a) `defaultValue` ślubu w `overview-tab.tsx:358-362` buduje `place: marriage.place`; (b) `attachMarriage(..., ?string $place)` w `tests/Pest.php:53,61` i test edycji ślubu w `WriteTargetsTest` wysyłają `place`; (c) `ListPersonCandidates` nie czyta miejsca; (d) `createLocality` już istnieje w `tests/e2e/support/api.ts:195`.
- **Fix**: Dopisać (a), (b) do planu, usunąć (c) z eager-loadów, w E2E użyć istniejącego helpera (d).
- **Decision**: FIXED

### F5 — Błąd burial.locality_id nie trafi pod pole

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §4 — overview-tab.tsx
- **Detail**: `belongsToSection('death', key)` (`overview-tab.tsx:60-61`) nie zna `burial.*` — błąd stałby jako ogólny komunikat karty.
- **Fix**: Sekcja death przyjmuje też `burial.*`.
- **Decision**: FIXED

### F6 — Wdrożenie fazy 1 osobno rozjedzie tekst i FK

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 Implementation Note / brief
- **Detail**: Migracja bez nowego kodu zostawia stary zapis tylko do `events.place`; edycje w tym oknie dałyby nieaktualne `locality_id`.
- **Fix**: „Fazy 1–3 wdrażać razem” w planie i briefie.
- **Decision**: FIXED
