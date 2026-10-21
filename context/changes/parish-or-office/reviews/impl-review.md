<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Parafia / urząd z wezwaniem

- **Plan**: context/changes/parish-or-office/plan.md
- **Scope**: Full plan (4 of 4)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-21
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Verification run (Krok 3)

Wszystkie kryteria automatyczne z planu uruchomione na HEAD (6a0af67), poza rollbackiem migracji na lokalnej bazie (pominięty celowo — dev z danymi; rundę `down()`/`up()` robi `ParishSeatMigrationTest` na bazie testowej):

- Pest fazy 1–3 (ParishSeatMigration, Parishes, Places, Sources, Localities, DeleteImpact, OwnershipBoundaries, AppShell, SourceArchiveMigration): 398 passed
- `tests/Backup`: 13 passed
- Pint: passed; Larastan (`composer types:check`): 0 errors
- Vitest (`npm run test:js`): 394 passed; `npm run check`: czysto; `types:check`: czysto; `npm run build`: OK
- Playwright (5 specyfikacji z fazy 4): 7 passed
- Zrzuty: 3 pliki PNG w `screenshots/`
- Progress: 24/24 pól `[x]`, wszystkie z SHA; ręczne 1.7, 3.6–3.9, 4.4–4.5 mają dowody w diffie lub zrzutach, poza lukami z F2.

Plan Adherence: wszystkie elementy „Changes Required” MATCH, żadna granica „What We're NOT Doing” nie naruszona (kolumna `name` zostaje, nazwy w kodzie bez zmian, archiwum jako tekst, brak auto-wyznania).

## Findings

### F1 — Store/UpdateParish mniej odporne niż odpowiedniki dla cmentarzy

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Parishes/StoreParish.php:23-32, app/Actions/Parishes/UpdateParish.php:34-38
- **Detail**: `StoreCemetery`/`UpdateCemetery` łapią `QueryException` z kodem `23503` (miejscowość usunięta lub scalona między walidacją a zapisem) i zamieniają go na `ValidationException` na `locality_id`; `UpdateCemetery` dodatkowo blokuje wiersz `lockForUpdate`. Parafie łapią tylko `UniqueConstraintViolationException`. Przy wyścigu z `DeleteLocality`/`MergeLocality` użytkownik dostaje 500 zamiast błędu pola, a `UpdateParish` równoległy do `MergeParish` (usuwającego rekord) kończy UPDATE na zerze wierszy z toastem sukcesu.
- **Fix**: Skopiuj z `StoreCemetery`/`UpdateCemetery` `catch (QueryException)` dla `23503` → `ValidationException::withMessages(['locality_id' => __('Wybierz miejscowość ze spisu.')])` oraz `lockForUpdate` w `UpdateParish`.
- **Decision**: FIXED (Fix now) — `StoreParish`/`UpdateParish` łapią `QueryException` 23503 → błąd `locality_id`; `UpdateParish` blokuje wiersz `lockForUpdate` i zwraca `$parish->refresh()`. Pint, Larastan, ParishesTest/LocalitiesTest/OwnershipBoundariesTest (195) zielone.

### F2 — Podstawianie siedziby w „Dodaj parafię / urząd „X”” bez automatycznego dowodu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Success Criteria
- **Location**: resources/js/components/parishes/new-parish-dialog.tsx:55 (`seatNamed`), tests/e2e/source-panel-parish.spec.ts
- **Detail**: Plan czyni z tej funkcji element Desired End State (po zmianie planu w plan-review F1), kryterium 3.8 i punkt Testing Strategy („dodanie parafii w locie ze źródła z podstawioną siedzibą”). `seatNamed()` nie jest eksportowana i nie ma testu Vitest; `source-panel-parish.spec.ts` wpisuje „Parafia {suffix}”, co nie pasuje do żadnej miejscowości, więc ścieżka z trafieniem nie jest wykonywana. Dowodem jest tylko zrzut `okno-nowa-parafia-urzad.png`. Dodatkowo `source-place-year-filter.test.ts` zmienił tylko nazwę pola w fixturach — plan wymagał przypadku „trafia po siedzibie w `parish_label`”.
- **Fix A ⭐ Recommended**: Wyciągnij `seatNamed` do `lib/` (np. obok `parishes-index.ts`), dodaj test Vitest (trafienie bez wielkości liter i po `trim`, brak trafienia → null) oraz przypadek „po siedzibie w `parish_label`” w `source-place-year-filter.test.ts`.
  - Strength: Tani, deterministyczny, nie dotyka E2E; domyka dokładnie to, co plan zapowiadał w Vitest.
  - Tradeoff: Nie pokazuje, że podstawiona siedziba trafia do zapisu w prawdziwym oknie.
  - Confidence: HIGH — funkcja jest czysta i ma dwa argumenty.
  - Blind spot: Przepływ `initialSeatName` → `localityId` w komponencie nadal bez testu.
- **Fix B**: Dodaj w `source-panel-parish.spec.ts` drugi test: wpisz nazwę istniejącej miejscowości (utworzonej przez `api.ts`), oczekuj, że combobox „Miejscowość siedziby” ma tę wartość i zapis daje etykietę.
  - Strength: Dowodzi całego przepływu z zapisem.
  - Tradeoff: Wolniejszy test, więcej fixture’ów i sprzątania.
  - Confidence: MED — wymaga pomocnika do tworzenia miejscowości z sufiksem.
  - Blind spot: Nie sprawdzono, czy `api.ts` ma już `createLocality`.
- **Decision**: FIXED (Fix A) — `seatNamed` przeniesiona do `lib/parishes-index.ts` z 3 testami Vitest; dodany przypadek „siedziba w `parish_label`” w `source-place-year-filter.test.ts`. Vitest 398 passed, lint i typy czyste.

### F3 — Docblock `MergeParish::moveInto` mówi „razem”, kod uzupełnia pola osobno

- **Severity**: OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Actions/Parishes/MergeParish.php:12-15, 54-58
- **Detail**: Docblock: „Seat, religion and dedication move together”. Kod liczy `$key` jako `target ?? duplicate` osobno dla każdego pola i stosuje całość tylko gdy wynikowy klucz jest wolny. Cel `{A, bez wyznania, bez wezwania}` + duplikat `{B, rzym.-kat., „Fara”}` daje `{A, rzym.-kat., „Fara”}` — rekord, którego nie było w żadnym z dwóch. Plan („pola klucza razem i tylko wtedy, gdy wynikowy klucz jest wolny”) jest dwuznaczny; test `ParishesTest.php:373` („together”) obejmuje tylko cel bez siedziby. Przypadku z siedzibą po obu stronach nie pokrywa żaden test.
- **Fix A ⭐ Recommended**: Zostaw zachowanie (uzupełnianie pustych pól, spójne ze starym scalaniem), popraw docblock („puste pola klucza uzupełniane osobno, wynik zapisany tylko gdy klucz wolny”) i dodaj test przypadku mieszanego.
  - Strength: Nie zmienia kontraktu scalania; domyka lukę w testach.
  - Tradeoff: Hybryda pozostaje możliwa — autor może dostać wezwanie z innej siedziby.
  - Confidence: MED — to decyzja produktowa, nie techniczna.
  - Blind spot: Jak autor realnie scala (zwykle duplikaty tej samej parafii, gdzie hybryda nie występuje).
- **Fix B**: Przenoś trójkę atomowo, tylko gdy cel nie ma siedziby; inaczej uzupełniaj wyłącznie `name`.
  - Strength: Wynik zawsze odpowiada jednemu z dwóch rekordów.
  - Tradeoff: Zmiana kontraktu z planu; cel bez wyznania nie dostanie wyznania z duplikatu, gdy ma siedzibę (regresja wobec starego scalania).
  - Confidence: MED — wymaga zmiany testu `:349`.
  - Blind spot: Czy ktoś polega na uzupełnianiu samego wyznania.
- **Decision**: FIXED (Fix A) — docblock `moveInto` opisuje uzupełnianie pól osobno z zapisem całości przy wolnym kluczu; nowy test „merging fills only the empty key fields of a target that has a seat and keeps its seat” (ParishesTest 45 passed).

### F4 — Komunikaty: wyścig bez etykiety i brakujące „i urzędy” w statusie

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Parishes/StoreParish.php:31, UpdateParish.php:37, resources/js/pages/places/index.tsx:322
- **Detail**: Plan: komunikat z wyścigu „ten sam” co reguły („Ta parafia / urząd już istnieje: „:label”.”); akcje zwracają wersję bez etykiety. Status wyników wyszukiwania na `/places` (`Znaleziono: … parafie …`) nie dostał „i urzędy”, choć reszta nazw w pliku tak.
- **Fix**: Dodaj etykietę do komunikatu akcji (lub zostaw krótszy i zapisz decyzję) oraz zmień „parafia/parafie/parafii” w statusie na formy „parafia / urząd” spójne z zakładką.
- **Decision**: FIXED (Fix now) — status na `/places` używa „parafia / urząd”, „parafie / urzędy”, „parafii / urzędów”; komunikat akcji z wyścigu zostaje bez etykiety (etykieta wymagałaby dodatkowego zapytania w ścieżce wyścigu).

### F5 — Higiena testów: stary kształt wiersza i nieaktualny tytuł

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/BackupTestCase.php:156, tests/Feature/ParishesTest.php:326-349
- **Detail**: Plan kazał dostosować `BackupTestCase`; nadal tworzy `Parish::factory()->seatedIn($seat)->create(['name' => 'Św. Leonarda'])` (działa, ale to nowy rekord ze starą nazwą zamiast `dedicatedTo`). Test `ParishesTest.php:349` ma tytuł „no other parish has that name and religion” (klucz to już siedziba + rodzaj + wyznanie + wezwanie), a testy `:326-345` używają `create(['name' => …])` zamiast stanu `legacy()`.
- **Fix**: `BackupTestCase` → `->dedicatedTo('Św. Leonarda')`; przemianuj test `:349` i użyj `legacy()` w `:326-345`.
- **Decision**: FIXED (Fix now) — `BackupTestCase` używa `dedicatedTo('Św. Leonarda')`, testy scalania siedziby używają `legacy()`, tytuł testu o wyznaniu mówi „the resulting key is free”. ParishesTest + tests/Backup (58) zielone.
