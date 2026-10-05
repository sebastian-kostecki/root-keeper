<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Akt odnaleziony po miejscu i roku, numer wpisu przy powiązaniu (S-04)

- **Plan**: context/changes/source-found-by-place-and-year/plan.md
- **Mode**: Deep
- **Date**: 2026-10-05
- **Verdict**: SOUND (after triage; was REVISE)
- **Findings**: 0 critical · 2 warnings · 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
37/37 paths ✓, 8/8 symbols ✓, brief↔plan ✓, Progress↔Phases ✓

## Findings

### F1 — Sugerowany „attach w try/catch” łamie idempotencję na Postgresie

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1, #4 Podpinanie z numerem (`LinkSourceToEvent`)
- **Detail**: Goły `attach` dla istniejącej pary zawsze rzuca naruszenie unikatu; na Postgresie z RefreshDatabase (`tests/Pest.php:28-30`) złapany wyjątek zostawia transakcję przerwaną, więc `EventSourcesTest.php:36-45` padnie („current transaction is aborted”). Dziś `syncWithoutDetaching` najpierw czyta istniejące id. Wzorce w repo: savepoint (`StoreLocality.php:19-24`, `StoreParish.php:20-26`) albo sprawdzenie istnienia (`CreateParentChildRelation.php:22-49`).
- **Fix**: Kontrakt: „jeśli para istnieje (`whereKey($sourceId)->exists()`) → return; inaczej `attach` w `DB::transaction(...)` (savepoint), catch tylko na wyścig”.
- **Decision**: FIXED (Fix in plan)

### F2 — Plan nie mówi, jak Larastan 7 zobaczy `pivot->entry_number`

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Faza 1, #3 Relacje i #6 Serializacja
- **Detail**: Odczyt w domknięciu `fn (Source $source)` (`EventResource.php:62`) i w `confirms()` (`SourceController.php:151-165`, pivot na `$event`). `phpstan.neon` level 7 bez baseline — nietypowany `->pivot->entry_number` prawie na pewno da undefined property. Precedensy: custom pivot `EventParticipant` z `->using()`; adnotacja `@property-read` w `RelativeResource.php:11` (działa tylko na zasobie, nie w domknięciu).
- **Fix**: Model `App\Models\EventSource extends Pivot` (`@property string|null $entry_number`), `->using(EventSource::class)` na obu relacjach, generyki `BelongsToMany<Source, $this, EventSource>` / `BelongsToMany<Event, $this, EventSource>`; #6 czyta numer przez ten typ.
  - Strength: Zgodne z precedensem `EventParticipant`; jedno typowane miejsce dla pivota po obu stronach.
  - Tradeoff: Jeden plik modelu więcej; sprawdzić, że `using()` nie zmienia `id` pivota (rowDiff po `id`).
  - Confidence: MED — wzorzec istnieje, ale nikt nie czyta jeszcze jego pivota, więc inferencja Larastana nieudowodniona tutaj.
  - Blind spot: Czy potrzebny będzie jeszcze inline `/** @var EventSource $pivot */`.
- **Decision**: FIXED (Fix in plan)

### F3 — Faza 1 pisze testy payloadu, których jej komenda nie uruchamia

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1, #7 Testy / Success Criteria 1.2
- **Detail**: #7 dodaje testy payloadu w `PersonsTest.php` / `SourcesTest.php`, a komenda 1.2 ich nie uruchamia — pierwszy raz zielone dopiero w fazie 3.
- **Fix**: Dopisać `tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php` do komendy 1.2.
- **Decision**: FIXED (Fix in plan)
