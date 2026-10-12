<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Nazwisko rodowe i wiek osoby (S-13)

- **Plan**: context/changes/maiden-name-and-age/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-10-05
- **Verdict**: APPROVED
- **Findings**: 0 critical, 2 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

Automatyczne kryteria uruchomione 2026-10-05:
- Pest: 167 testów zielonych (AgeTest, PersonsTest, WriteTargetsTest, TreeTest).
- Vitest: 136/136.
- `types:check` i `check` bez błędów.
- phpstan: 0 błędów; pint: passed.
- E2E: 4 passed (setup + 3 specy slotów).
- Migracja: status „Ran”, `down()` sprawdzone przez lekturę. Rollbacku nie uruchomiono na bazie deweloperskiej, żeby nie stracić wpisanych nazwisk rodowych.

Reguła wieku w `app/Support/Age.php` zgadza się z każdym punktem planu, a każdy przykład z planu daje oczekiwany wynik.

## Findings

### F1 — Ryzyko „pełnego zastąpienia” na froncie bez testu automatycznego

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/PersonsTest.php:1251
- **Detail**: „Critical Implementation Details” w planie mówi, że test Fazy 1 sprawdza, czy zapis innej sekcji nie czyści `maiden_name`. Test Pest wysyła jednak `maiden_name` w PUT wprost. Sprawdza więc tylko serwer, a nie `personFormToPayload` ani `mergeSectionIntoPayload`. `person-payload.test.ts` nie istnieje. `WriteTargetsTest.php:274` używa `array_intersect_key`, który po cichu pomija brakujący klucz. Dziś to ryzyko łapie tylko ręczna kontrola 1.10.
- **Fix**: Dodać `resources/js/lib/person-payload.test.ts` (Vitest). Test sprawdza, że `mergeSectionIntoPayload` dla sekcji innej niż `personal` (np. urodzenie) zachowuje `maiden_name` z formularza.
- **Decision**: FIXED — dodano resources/js/lib/person-payload.test.ts (sprawdzony mutacją: bez maiden_name w personFormToPayload 2 testy padają)

### F2 — Typ `SheetPerson` w BuildFamilySheet nie ma `age`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Trees/BuildFamilySheet.php:19
- **Detail**: `@phpstan-type SheetPerson` to nadal `array{id, display_name, gender, lifespan, is_deceased}`. Brakuje w nim klucza `age`, który zwraca `SheetPersonResource.php:47` i który ma typ TS `SheetPerson` w `resources/js/types/tree.ts`. Larastan tego nie widzi, bo `render()` rzutuje przez `/** @var SheetPerson */`.
- **Fix**: Dopisać `age: array{unit: 'years'|'months'|'days'|'under_year', value: int|null}|null` do phpstan-type.
- **Decision**: FIXED — dodano age do @phpstan-type SheetPerson; phpstan 0 błędów

### F3 — Wiek na karcie drzewa w osobnej linii, nie w spanie żywotu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/tree/sheet-person-card.tsx:40
- **Detail**: Plan (Faza 2, §5) przewidywał tę samą regułę co w nagłówku: `${lifespan} (${age})` w jednym spanie. Kod renderuje `(72 lata)` w osobnym bloku `whitespace-nowrap` pod żywotem. Wygląda to na świadomą decyzję pod wąską kartę (kontrola 2.6). Nagłówek jest zgodny z planem.
- **Fix**: Dopisać w planie notatkę o zmianie układu (osobna linia na wąskiej karcie).
- **Decision**: FIXED — odchylenie opisane w plan.md (Faza 2 §5)

### F4 — `age` wysyłany także na liście osób

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: app/Http/Resources/PersonResource.php:57
- **Detail**: `PersonResource` obsługuje też `persons/index` (`PersonController.php:35`). Każdy wiersz listy liczy więc i wysyła `age`, choć plan wyklucza wiek z listy („What We're NOT Doing”). Lista go nie wyświetla. Nie ma N+1, bo `events` (birth/death) są już eager-loaded. To martwy payload, który wynika z kontraktu samego planu.
- **Fix**: Zaakceptować i dopisać to w planie. Ewentualnie wyłączyć `age` na indeksie, jeśli lista ma zostać lekka.
- **Decision**: FIXED via Fix A — zaakceptowane, opisane w plan.md (Faza 2 §3)

### F5 — Semantyka końca miesiąca w wieku niemowląt nieprzypięta testem

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/Age.php:66
- **Detail**: `fromFullDates` opiera się na `DateTime::diff`. Dla 31.01 → 01.03 daje 0 mies. 29 dni („29 dni”). Dla 29.02.2000 → 28.02.2001 daje 11 mies. Wyniki są obronne (nigdy ujemne, zawsze właściwa jednostka), ale żaden test ich nie utrwala.
- **Fix**: Dodać w `tests/Unit/AgeTest.php` jeden przypadek końca miesiąca (31.01 → 01.03 → `days`, 29).
- **Decision**: FIXED — przypadek końca miesiąca w tests/Unit/AgeTest.php (31.01→01.03 = 29 dni, 31.01→31.03 = 2 mies.)

### F6 — Wklejenie wyświetlanej nazwy „… z d. …” do wyszukiwarki nic nie znajduje

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/PersonController.php:41
- **Detail**: Każde słowo musi pasować do którejś kolumny (AND). „d.” nie pasuje do żadnej, więc zapytanie „Anna Kowalska z d. Nowak” daje pustą listę. To kwestia UX, nie bezpieczeństwa.
- **Fix**: Pomijać tokeny „z” i „d.” w słowach wyszukiwania. Można też zostawić to jako follow-up poza zakresem S-13.
- **Decision**: FIXED (inaczej niż w propozycji) — z zapytania usuwana jest tylko fraza „z d.” (kropka opcjonalna), a nie każde „z”; dodano przypadek „Anna Kowalska z d. Nowak” w PersonsTest (sprawdzony mutacją)
