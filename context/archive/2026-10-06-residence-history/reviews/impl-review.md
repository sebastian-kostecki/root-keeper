<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Kilka zamieszkań z latami (S-08)

- **Plan**: context/changes/residence-history/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-06
- **Verdict**: APPROVED
- **Findings**: 0 critical 3 warnings 1 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated checks re-run: Pest (226 passed: migrations, Persons, Ownership, WriteTargets, DeleteImpact, Backup), Larastan 0 errors, `npm run check`, `types:check`, Vitest 207 passed. E2E and build not re-run.

## Findings

### F1 — Zapis innej sekcji może cofnąć zamieszkania z nieaktualnego panelu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Persons/UpdatePerson.php (replaceResidences); resources/js/lib/person-payload.ts (personFormToPayload)
- **Detail**: Każdy zapis Danych osobowych/Urodzenia/Zgonu wysyła całą `personForm.residences` i serwer robi pełne zastąpienie. Panel otwarty ze starą listą (druga karta przeglądarki) po zapisie innej sekcji po cichu przywraca starą listę. To świadoma decyzja planu (pełne zastąpienie jak pochowanie w S-07), przypięta testem „bez klucza czyści listę”.
- **Fix**: Zaakceptuj ryzyko (jeden autor, jedno drzewo) i zapisz je w change.md.
  - Strength: Zero zmian w kontrakcie; spójne z S-07.
  - Tradeoff: Ryzyko utraty edycji przy dwóch kartach pozostaje.
  - Confidence: HIGH — aplikacja jednoosobowa na drzewo.
  - Blind spot: Nie sprawdzano, czy inne pola osoby mają ten sam problem.
- **Decision**: ACCEPTED: ryzyko przyjęte, zapisane w change.md

### F2 — Brak limitu rozmiaru tablicy `residences`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Requests/PersonUpdateRequest.php (rules)
- **Detail**: `Rule::exists` biegnie raz na wiersz, a zapis robi INSERT na wiersz; duży payload to N zapytań. Użytkownik uwierzytelniony, własne drzewo — ryzyko niskie.
- **Fix**: `'residences' => ['nullable','array','max:50']` + test.
- **Decision**: FIXED: max:50 w PersonUpdateRequest + test w PersonsTest

### F3 — Powtarzające się nazwy dostępności wierszy zamieszkań

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/persons/residences-fields.tsx:107-142
- **Detail**: Każdy wiersz ma te same etykiety („Miejscowość”, „Od roku”, „Do roku”); czytnik ekranu nie odróżni wierszy, E2E musi używać `.last()`. Błędy lat nie są powiązane z inputami (`aria-describedby`/`aria-invalid`).
- **Fix**: Owiń wiersz w `role="group"` z `aria-label="Zamieszkanie {n}"`; sprawdź, czy `InputError`/`Input` w repo obsługują powiązanie błędu.
- **Decision**: FIXED: role=group + aria-label na wierszu (powiązanie błędów lat z inputami nie wykonane)

### F4 — Brak właściciela dla usunięcia `people.residence` i dla S-11

- **Severity**: 👁 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Models/Person.php:26,31
- **Detail**: Zamrożona kolumna `residence` (jak `events.place`) nie ma w roadmapie migracji usuwającej. Przy S-11 `residences` musi wejść do scalania i sprawdzenia „usuń tylko gdy nieużywana” (`Locality::residences()` już istnieje).
- **Fix**: Dopisz do roadmapy/S-11 oba punkty.
- **Decision**: FIXED: notatka w roadmapie przy S-11
