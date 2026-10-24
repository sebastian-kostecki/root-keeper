<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Pierwszy import przodków do pustego drzewa

- **Plan**: context/changes/first-import-into-empty-tree/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-24
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 5 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Weryfikacja automatyczna (2026-10-24): wszystkie testy z kryteriów faz 1–4 przechodzą (426 testów, 2375 asercji); `composer types:check` — 0 błędów; Pint — czysto; migracja `migrate:rollback --step=1` + `migrate` działa (sprawdzone na bazie `testing`, nie na dev).

Sprawdzone i poprawne: każdy odczyt i zapis idzie przez `$tree`; klucze `repo_id` nie mogą się zderzyć w drzewie; po filtrach planera `CreateParentChildRelation` i `CreateMarriage` nie odrzucą poprawnego planu; jedna transakcja z blokadą i ponownym sprawdzeniem pustości; kopia na produkcji po zgodzie i przed zapisem; żadna granica z „What We're NOT Doing” nie została przekroczona.

## Findings

### F1 — Ręczne kroki 4.5–4.8 odhaczone bez śladu przebiegu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/first-import-into-empty-tree/plan.md:524-527
- **Detail**: Ręczne punkty 4.5–4.8 (przebieg na prawdziwym repo, `/tree`, panel osoby, odmowa przy drugim uruchomieniu) odhaczono w tym samym commicie co kod fazy 4 (52a8c9f). Plan każe po fazie poprosić autora o ręczny przebieg. W lokalnej bazie dev nie ma ani jednej osoby z `repo_id`, więc nic tu nie potwierdza przebiegu. Mógł odbyć się gdzie indziej, a konto mogło zostać usunięte. To gwiazda przewodnia M-3, więc ręczny przebieg jest właściwym dowodem wyniku.
- **Fix**: Potwierdź, że przebieg 4.5–4.8 się odbył; jeśli nie — odznacz punkty i wykonaj „Manual Testing Steps” przed archiwizacją.
- **Decision**: DISMISSED — autor potwierdził, że ręczny przebieg 4.5–4.8 się odbył.

### F2 — Wyjątek zapisu łapany jako `Throwable` bez `report()`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/ImportResearch.php:88-95
- **Detail**: Komenda łapie każdy `Throwable` z `ApplyFirstImport`, drukuje tylko `getMessage()` i „Nic nie zostało zapisane.”. Komunikat jest prawdziwy, bo transakcja się cofa. Gubi się jednak ślad stosu, więc nieoczekiwanego `QueryException` lub `Error` na prawdziwych danych nie da się potem zdiagnozować. W `app/Actions` błędy pomocnicze idą przez `report()` (np. `GenerateSourceThumbnail.php:75`).
- **Fix**: Dodaj `report($exception);` w bloku `catch` (komunikat dla autora bez zmian).
- **Decision**: FIXED — `report($exception)` w `catch` (ImportResearch.php:91).

### F3 — Import nie tworzy pustego urodzenia, w przeciwieństwie do aplikacji

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Pattern Consistency
- **Location**: app/Actions/Import/ApplyFirstImport.php:63-71 (vs app/Actions/Persons/CreatePerson.php:56)
- **Detail**: `CreatePerson` zawsze tworzy wydarzenie urodzenia, także puste. Import tworzy je tylko przy kluczu `urodzenie` w bloku, więc w fixture 8 z 13 osób nie ma urodzenia. Aplikacja to znosi, bo `UpdatePerson::putEvent` dopisuje brakujące wydarzenie. To jednak nowy kształt danych, którego aplikacja wcześniej nie tworzyła. S-04 musi go znać: urodzenie dopisane w aplikacji później ma `repo_id = null` i nie może wyglądać na zmianę z repo.
- **Fix A ⭐ Recommended**: Zostaw zachowanie i dopisz jedno zdanie do planu (Key Discoveries) lub uwagę do S-04 w roadmapie.
  - Strength: Import zapisuje tylko to, co jest w bloku, a `repo_id` = `O-x/urodzenie` zawsze odpowiada kluczowi z bloku. Dla S-04 to czystsze.
  - Tradeoff: Dwa kształty danych osoby w aplikacji: z pustym urodzeniem albo bez niego.
  - Confidence: MED — `putEvent` obsługuje brak; nie sprawdzałem każdego widoku na osobie bez urodzenia poza testami.
  - Blind spot: Widoki lub zapytania, które zakładają istnienie urodzenia (np. sortowanie po dacie urodzenia).
- **Fix B**: Twórz puste urodzenie bez `repo_id` dla osób bez klucza `urodzenie`, jak `CreatePerson`.
  - Strength: Jeden kształt danych w całej aplikacji.
  - Tradeoff: Rekord bez odpowiednika w bloku. S-04 musi go traktować jak rekord z aplikacji, a zmiana dotyka zapisu i testów.
  - Confidence: MED — prosta zmiana, ale wpływa na liczby w testach zapisu.
  - Blind spot: Jak S-04 rozstrzygnie urodzenie, które pojawi się w repo później.
- **Decision**: FIXED via Fix A — dopisane w plan.md (Key Discoveries) i roadmap.md (S-04).

### F4 — Brak testów trzech gałęzi komendy

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/ImportResearchCommandTest.php
- **Detail**: Plan (kroki 3, 4 i 6 komendy) opisuje gałęzie „Nic do zapisania.” przy zerze osób, `--no-interaction` → nic nie zapisane oraz wyjątek z `ApplyFirstImport` → „Nic nie zostało zapisane.” + `FAILURE`. Kod je ma, ale testy nie. Lista testów w planie ich nie wymagała. Ostatnia gałąź to komunikat bezpieczeństwa dla autora, a F2 ją zmienia.
- **Fix**: Dodaj trzy testy komendy: puste repo (tymczasowe), `--no-interaction` oraz `ApplyFirstImport` podmieniony przez `$this->mock()` tak, by rzucał.
- **Decision**: FIXED — trzy testy w ImportResearchCommandTest.php. `--no-interaction` przez `Artisan::call`, bo testowy podwójnik wyjścia przechwytuje pytania. Błąd zapisu wywołany podmienionym `CreateMarriage` (prawdziwy rollback), raportowanie sprawdzone przez `Exceptions::fake()` (mutacja bez `report()` pada).

### F5 — Drobiazgi w podsumowaniu: licznik pominiętych i powielone ostrzeżenie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Console/Commands/ImportResearch.php:107, app/Actions/Import/PlanFirstImport.php:109
- **Detail**:
  - „N pominiętych” liczy wpisy `ImportIssue`, nie relacje. Przy tym samym id jako ojciec i matka jeden wpis oznacza dwie pominięte relacje, więc suma „do zapisania + pominiętych” może być mniejsza niż liczba relacji w raporcie odczytu.
  - Rodzic o sprzecznej płci pojawia się dwa razy: jako ostrzeżenie odczytu i jako pominięta relacja.
  - `FirstImportPlan::$weddings` zawsze równa się `count($marriages)`.
  - Linia „notatki wydarzeń” nie ma odsyłacza do wycinka. Plan nie wskazuje dla niej wycinka, więc to akceptowalne.
- **Fix**: Licz w podsumowaniu pominięte relacje, nie wpisy (2 dla przypadku „ten sam id”); resztę zostaw.
- **Decision**: FIXED — planer daje jeden wpis na pominiętą relację (ojciec i matka osobno, PlanFirstImport.php:109-111), więc licznik w komendzie liczy relacje; test planowania oczekuje 2 wpisów. Reszta pozostawiona.

### F6 — Test granicy drzewa nie sprawdza uczestników wydarzeń

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: tests/Feature/FirstImportApplyTest.php:175
- **Detail**: Test `rowDiff` wyłącza z kontroli `tree_id` nowe wiersze `event_participants` (tabela nie ma `tree_id`). Nic nie sprawdza, że ich `person_id` i `event_id` wskazują rekordy autora. Dziś kod jest bezpieczny, bo dołącza tylko osoby z lokalnej mapy, ale test tego nie dowodzi. Brakuje też testu zapisu dziecka z rodzicami nieznanej płci, czyli prawdziwej ścieżki `guardSameSexParent`.
- **Fix**: Dodaj asercję, że każdy dodany `event_participants` wskazuje osobę i wydarzenie z drzewa autora.
- **Decision**: FIXED — asercja w teście „writes only into the author tree…” (dodani uczestnicy ⊆ dodane osoby i wydarzenia). Test zapisu dla rodziców nieznanej płci pominięty.

### F7 — Cofnięcie migracji trwale gubi `repo_id`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: database/migrations/2026_10_24_053038_add_repo_id_to_people_events_and_parent_child_relations.php:31-41
- **Detail**: `down()` jest poprawne, ale usuwa każdy zapisany identyfikator z repo. Gdy S-04 zacznie na nich polegać, rollback zamieni zaimportowane rekordy w „dodane w aplikacji”, a tego ponowny import już nie naprawi.
- **Fix**: Dopisz w docblocku `down()` zdanie, że cofnięcie trwale usuwa identyfikatory z repo.
- **Decision**: FIXED — docblock `down()` w migracji.
