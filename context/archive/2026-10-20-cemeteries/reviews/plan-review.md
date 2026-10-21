<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Cmentarze jako rekord miejsca

- **Plan**: context/changes/cemeteries/plan.md
- **Mode**: Deep
- **Date**: 2026-10-20
- **Verdict**: REVISE → SOUND (after triage: 8/8 fixed)
- **Findings**: 1 critical, 5 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
39/39 paths ✓, 6/6 symbols ✓, brief↔plan ✓; Progress↔Phase 4/4 phases ✓, 29/29 criteria ✓

## Findings

### F1 — Faza 3 psuje podpowiedź pochowania przed fazą 4

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 3 §2 (Source::cemetery()) vs Phase 4 §3
- **Detail**: `PersonFormResource::burialSuggestion()` (`PersonFormResource.php:64-73`) czyta relację `gravestones.cemetery`. Faza 3 przepina `Source::cemetery()` na `Cemetery`, a `PersonFormResource` zmienia dopiero faza 4. W międzyczasie podpowiedź zwraca `{id: cemetery.id, name: null|cemetery.name}`, a `DeathFields` (`person-section-fields.tsx:195,224-230`) wysyła ją jako `burial.locality_id` — id cmentarza jako id miejscowości (422 albo cicho zła miejscowość), dla każdego migrowanego nagrobka. `GravestoneTest.php:306-330` z nazwanymi cmentarzami z fabryki może to przepuścić.
- **Fix**: W fazie 3 dodać `PersonFormResource` do §4: podpowiedź mapowana na `cemetery.locality` (ten sam kształt `LocalityOption`; eager load `gravestones.cemetery.locality` w `BuildPersonPreview.php:29`); faza 4 przełącza na `CemeteryOption`. Przypadek testu z cmentarzem bez nazwy w fazie 3.
- **Decision**: FIXED (Fix in plan)

### F2 — Test kopii zapasowej czerwony od fazy 1 do fazy 3

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 1 §10 / Success Criteria; Phase 3 §7
- **Detail**: `tests/Backup/BackupRestoreTest.php:25-26` wymaga >0 wierszy w każdej tabeli po odtworzeniu. Tabela `cemeteries` istnieje od fazy 1, fikstura `BackupTestCase` dostaje cmentarz dopiero w fazie 3, a faza 1 nie uruchamia `tests/Backup`. `BackupTestCase.php:171-175` wysyła `cemetery_locality_id` przez `PUT sources.update` — po fazie 3 klucz jest cicho pomijany.
- **Fix**: Fiksturę (jeden cmentarz) przenieść do fazy 1 i dodać `vendor/bin/sail artisan test --compact tests/Backup` do jej kryteriów; w fazie 3 przełączyć fiksturę na `cemetery_id`, w fazie 4 dodać pochowanie z `cemetery_id`.
- **Decision**: FIXED (Fix in plan)

### F3 — Edycja pochowania może zostawić stary cemetery_id

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 4 §2 — Zapis pochowania
- **Detail**: `UpdatePerson::putEvent()` robi `$event->update(eventAttributesFrom(...))` (`UpdatePerson.php:177`), a `MapsEventInput` mapuje tylko datę i `locality_id`. Plan nie mówi, że `cemetery_id` jest zawsze zapisywany (null przy braku) — zmiana miejscowości bez cmentarza zostawia stary cmentarz i łamie niezmiennik `events.locality_id = cemeteries.locality_id`.
- **Fix**: W fazie 4 §2: create i update zawsze zapisują dla pochowania `['locality_id' => …, 'cemetery_id' => $cemeteryId ?? null]` (poza wspólnym `eventAttributesFrom`, żeby nie dotykać narodzin, zgonu i ślubu). Przypadek `PersonsTest`: zmiana miejscowości bez cmentarza → `cemetery_id` null.
- **Decision**: FIXED (Fix in plan)

### F4 — Nowy tekst podpowiedzi łamie places-page.spec.ts

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §1 (otherKindHint) vs criterion 2.5
- **Detail**: Plan zmienia podpowiedź na „Pasuje w zakładkach: Parafie (2), Cmentarze (1)”, a `tests/e2e/places-page.spec.ts:69` szuka przycisku „Pokaż w parafiach (1)”; kryterium 2.5 zakłada spec bez zmian. `otherKindHint` zwraca jeden obiekt, `PlacesNoResults.other` (`places-empty-state.tsx:61-77`) renderuje jeden przycisk — plan nie mówi, jak renderować listę.
- **Fix**: Zostawić przyciski „Pokaż w {zakładce} (n)” — po jednym na zakładkę z trafieniami; `otherKindHint` zwraca tablicę, `PlacesNoResults.other` → `others: OtherKindAction[]`. Spec przechodzi bez zmian.
- **Decision**: FIXED (Fix in plan)

### F5 — Sprzątanie E2E nie znajdzie cmentarza bez nazwy

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §7 — E2E api.ts
- **Detail**: `cleanupBySuffix` (`api.ts:423-475`) dopasowuje parafie i miejscowości po `name.includes(suffix)`. Cmentarz speca ma `name = null` — nie zostanie znaleziony, zablokuje `DeleteLocality`, a testowa miejscowość zostanie w bazie.
- **Fix**: Cmentarze z propsów `/places?kind=cmentarze` dopasowywać po `locality_name.includes(suffix) || name?.includes(suffix)`; usuwać po źródłach i osobach, przed miejscowościami.
- **Decision**: FIXED (Fix in plan)

### F6 — Faza 3 zdejmuje usage.gravestones bez klienta

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 §4 / §6
- **Detail**: Serwer przestaje wysyłać `LocalityResource.usage.gravestones`, ale `resources/js/types/locality.ts:5` i `lib/places-usage.ts:9,60,94` nie są w fazie 3 (jest tylko `places-usage.test.ts`). Typ zostaje z nieistniejącym kluczem, a fraza po cichu czyta `undefined`.
- **Fix**: Dodać `types/locality.ts` i `lib/places-usage.ts` do fazy 3 §6: usunąć `gravestones` z `LocalityUsage`, fraz i `noLocalityUsage`.
- **Decision**: FIXED (Fix in plan)

### F7 — MergeLocality::mergedText() jest prywatne

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 §5 — MergeCemetery
- **Detail**: „Opisy łączone jak `MergeLocality::mergedText()`” — metoda jest `private` (`MergeLocality.php:117-131`). Implementator wybierze między kopią a wydzieleniem; kopia to drugie źródło tej samej reguły.
- **Fix**: Wydzielić do jednego wspólnego helpera używanego przez obie akcje (np. statyczna metoda w `app/Support/`) i zapisać to w fazie 1 §5.
- **Decision**: FIXED (Fix in plan)

### F8 — Reguła uzupełniania z nagrobka ≠ reguła migracji

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 4 §1 vs §3
- **Detail**: Plan mówi „ta sama reguła co w migracji”, ale migracja wymaga dokładnie jednego cmentarza wśród nagrobków osoby w miejscowości pochowania, a `Fill…` wypełnia z bieżącego nagrobka. Osoba na dwóch nagrobkach różnych cmentarzy w jednej miejscowości: migracja zostawia puste, aplikacja wpisuje cmentarz pierwszego zapisanego — remis nierozstrzygnięty.
- **Fix**: `Fill…` stosuje to samo sprawdzenie jednoznaczności (jedyny różny cmentarz nagrobków osoby w tej miejscowości), inaczej zostawia puste; przypadek w `GravestoneTest`.
- **Decision**: FIXED (Fix in plan)
