# Pierwszy import przodków do pustego drzewa — plan implementacji

## Overview

Rozszerzamy komendę `import:research` z S-01 o zapis. Po raporcie odczytu komenda pokazuje podsumowanie importu i pyta o zgodę. Na produkcji robi kopię bazy, a potem w jednej transakcji zapisuje do drzewa wskazanego konta:
- osoby,
- relacje rodzic–dziecko,
- małżeństwa,
- urodzenia, śluby i zgony z każdym rodzajem daty.

Każdy rekord niesie identyfikator z repo. Wartości z bloku, które nie mają jeszcze kolumny, trafiają do notatki osoby. Import działa wyłącznie na drzewie bez osób (np. konto testowe). Dopasowanie do ręcznych osób to S-03, a ponowny import S-04.

To gwiazda przewodnia M-3 (roadmapa S-02): najmniejszy kompletny przebieg bloki → podsumowanie → zatwierdzenie → zapis → drzewo na prawdziwych plikach.

## Current State Analysis

**Import (S-01, zarchiwizowany w `context/archive/2026-10-23-research-blocks-read-and-report/`):**
- `app/Console/Commands/ImportResearch.php`: `import:research {email} {path}`. Komenda rozpoznaje konto (`User::where('email')`, błąd przy braku konta lub drzewa) i wywołuje `ReadResearchRepo`. Drukuje raport i kończy się komunikatem „Nic nie zostało zapisane.”
- `app/Actions/Import/ReadResearchRepo.php:23` zwraca `ResearchRepoReading`:
  - `persons` — osoby kluczowane id repo,
  - `marriages` — małżeństwa rozstrzygnięte po obu stronach,
  - `skipped`, `warnings`,
  - `parentChildRelations` — tylko licznik.
- Odczyt sprawdza:
  - odwołania do rodziców bez bloku (ostrzeżenie, `:148`),
  - płeć rodzica niezgodną z rolą (tylko ostrzeżenie, `:156`),
  - małżeństwa (`:196`).
- Odczyt **nie wykrywa cykli rodzic–dziecko** (przegląd S-01, F3; uwaga w roadmapie S-02).
- Klasy wartości w `app/Support/Import/`:
  - `ResearchPerson`,
  - `ResearchEvent` (`date`, `place` jako tekst, `level`, `note`),
  - `ResearchDate` (`qualifier`, `year`, `month`, `day`, `untilYear`),
  - `ResearchMarriage` (`personIds` jako posortowana para).
- Enumy `DateQualifier` i `ConfidenceLevel` nie są dziś nigdzie zapisywane.

**Model danych:**
- `people`:
  - `first_name` — wszystkie imiona,
  - `last_name` — nazwisko rodowe,
  - `gender` — `male` | `female` | null,
  - `note`.
- `events`:
  - `type` — birth | death | marriage | burial,
  - `date_year` / `date_month` / `date_day` / `date_is_approximate`,
  - `locality_id`, `cemetery_id`, `marriage_form`.
- `events` nie ma kolumny notatki, poziomu pewności ani rodzaju daty.
- Uczestników wydarzenia trzyma `event_participants` (`subject` | `spouse`). Małżeństwo to wydarzenie `marriage` z dwoma `spouse`.
- `parent_child_relations` (`tree_id`, `parent_id`, `child_id`, unique para).
- **Żadna tabela nie ma identyfikatora z repo.**
- Relacja drzewa na osoby to `Tree::people()` (`app/Models/Tree.php:45`), nie `persons()`.

**Ścieżka zapisu w aplikacji:**
- `CreateParentChildRelation::handle(Tree, Person $parent, Person $child)` (`app/Actions/Relations/CreateParentChildRelation.php:20`) pilnuje w transakcji:
  - duplikatu,
  - najwyżej dwojga rodziców,
  - braku cyklu,
  - dwóch rodziców tej samej znanej płci.
  
  Przy każdym wywołaniu buduje graf drzewa od nowa.
- `CreateMarriage::handle(Tree, Person, Person, array $input)` (`app/Actions/Relations/CreateMarriage.php:25`) odrzuca powtórzoną parę i tworzy wydarzenie z obojgiem małżonków.
- Dane wydarzenia przechodzą przez `MapsEventInput` (`app/Actions/Persons/MapsEventInput.php`).

**Widoczność w drzewie:**
- `/tree` pokazuje osoby dopiero przy ustawionym probancie (`trees.root_person_id`). Bez niego widać stan „Nie wybrano probanta” (`resources/js/pages/tree/show.tsx:109`).
- Widok przodków idzie po `parent_child_relations` (`app/Actions/Trees/BuildAncestorChart.php`).
- Osoba bez wydarzenia zgonu wygląda na żyjącą („1850 –”).

**Kopia bazy:**
- `spatie/laravel-backup` jest w `require`.
- `RestoreBackup::makeSafetyBackup` (`app/Console/Commands/RestoreBackup.php:316`) woła `$this->call('backup:run', [...])` i sprawdza kod wyjścia.
- `BackupCommand` przy błędzie zwraca `FAILURE` i nie rzuca wyjątku (`vendor/spatie/laravel-backup/src/Commands/BackupCommand.php:125`).

## Desired End State

Dla konta z drzewem bez osób `vendor/bin/sail artisan import:research <e-mail> <repo>`:
1. Drukuje raport odczytu jak w S-01.
2. Drukuje podsumowanie importu:
   - liczby osób, relacji, małżeństw i wydarzeń,
   - liczby dat według rodzaju,
   - pominięte relacje z powodem,
   - liczby wartości przenoszonych do notatki osoby.
3. Pyta „Zapisać N osób do drzewa konta <e-mail>?” z domyślnym „nie”.
4. Po „tak” na produkcji uruchamia `backup:run --only-db`. Jeśli kopia się nie uda, nic nie jest zapisywane.
5. Zapisuje wszystko w jednej transakcji do drzewa tego konta. Każda osoba, wydarzenie i relacja dostaje `repo_id`.
6. Kończy podsumowaniem zapisu i podpowiedzią, że probanta wybiera się w aplikacji, z osobą o numerze Ahnentafel 1, jeśli taka jest.

Na drzewie z co najmniej jedną osobą komenda drukuje raport i podsumowanie, a potem kończy się błędem bez pytania i bez zapisu. Cudze drzewa są nietknięte.

Weryfikacja:
- Testy Pest dla planowania, zapisu i komendy, każdy z drugim użytkownikiem o nakładających się danych.
- Ręczny przebieg na kopii prawdziwego repo na koncie testowym. Po wyborze probanta w UI przodkowie są widoczni w `/tree` i w widoku przodków z relacjami i datami.

### Key Discoveries:

- `ReadResearchRepo` już rozstrzyga małżeństwa i odwołania. Planer bierze `ResearchRepoReading` bez ponownego parsowania (`app/Support/Import/ResearchRepoReading.php`).
- `CreateParentChildRelation` nie blokuje relacji osoby z samą sobą ani nie sprawdza, czy osoby należą do `$tree` (pilnują tego tylko Form Requests). Planer musi zagwarantować, że obie osoby pochodzą z zapisywanego drzewa. Relacji z własnym id nie ma, bo S-01 pomija taki wpis.
- `Person::events()` i `Event::participants()` nie mają `withTimestamps()` (`app/Models/Person.php:70`, `app/Models/Event.php:79`). Import dołącza uczestników tak samo jak aplikacja i nie poprawia tego przy okazji.
- Fixture `tests/Fixtures/research-repo/` ma już wszystkie przypadki S-02:
  - 5 rodzajów daty (`nowak_piotr.md`: zakres, `nowak_wojciech.md`: „po”, `lis_marta.md`: „przed”),
  - miejsce, poziom i notatkę wydarzenia, warianty i pochowanie (`nowak_jan_1801.md`),
  - `zgon: {}` (`nowak_piotr.md`),
  - rodzica o sprzecznej płci (`kos_ewa.md`: `ojciec: O-0003` to Marta Lis, K),
  - małżeństwo zapisane tylko po jednej stronie (`wrona_adam.md` ↔ `kos_ewa.md`),
  - implex (`kos_katarzyna.md`: `[5, 15]`).
- Test S-01 „writes nothing for either account” (`tests/Feature/ImportResearchCommandTest.php:41`) używa autora z osobami w drzewie. Po tej zmianie to przypadek odmowy, więc test dalej dowodzi braku zapisu, ale z innego powodu.

## What We're NOT Doing

- **Import do drzewa z osobami.** Bez dopasowania do ręcznych osób (S-03) i bez ponownego importu, aktualizacji czy podglądu zmian (S-04). Niepuste drzewo = odmowa bez zapisu.
- **Probant.** Import nie ustawia `root_person_id`. Autor wybiera probanta w UI; zostaje decyzja „bez automatycznego probanta” z `context/archive/2026-09-28-graphical-tree-view/plan.md`. Komenda tylko podpowiada osobę z Ahnentafel 1.
- **Miejsca ze spisu, wydarzenie pochowania i cmentarze** (S-05). Miejsca i pochowanie idą tekstem do notatki osoby.
- **Kolumny poziomu pewności** (S-08), **wariantów nazwiska i wyszukiwania po nich** (S-09) oraz **notatki wydarzenia**. Wszystko to idzie tekstem do notatki osoby.
- **Rodzaju daty w schemacie i UI.** Robi to S-07 przed tą zmianą. S-02 tylko mapuje `ResearchDate` na kontrakt S-07.
- **Zapisu numerów Ahnentafel** i źródeł ze skanami (S-06).
- **Importu „od zera” z czyszczeniem drzewa** (S-10).
- **Wycofania importu.** To non-goal PRD; ochroną jest kopia bazy na produkcji.
- **Zmian w `CreateParentChildRelation` i `CreateMarriage`.** Brak `withTimestamps()` na pivocie zostaje.

## Implementation Approach

Zgodnie z CLAUDE.md („Research repo import”) rozdzielamy decyzję od zapisu:
- `PlanFirstImport` buduje z `ResearchRepoReading` typowany plan bez zapisu. Sprawdza pustość drzewa, wycina relacje z cykli i ze sprzeczną płcią, mapuje daty, układa tekst notatek i liczy dane do podsumowania.
- `ApplyFirstImport` zapisuje plan w jednej transakcji, pod blokadą wiersza drzewa i z ponownym sprawdzeniem pustości. Relacje i małżeństwa idą przez istniejące Actions, więc niezmienniki aplikacji są ostatnią linią obrony. Każdy wyjątek cofa cały zapis.
- Komenda tylko rozpoznaje argumenty, drukuje, pyta, robi kopię na produkcji i wywołuje Actions.

Identyfikator repo to jedna kolumna `repo_id` w trzech tabelach, unikalna w drzewie. Wydarzenia i relacje dostają klucz wyprowadzony z bloku. Dzięki temu S-04 rozpozna każdy zaimportowany rekord i odróżni go od dodanego ręcznie, także ślub dopisany w aplikacji między dwiema zaimportowanymi osobami.

## Critical Implementation Details

- **Kontrakt dat z S-07** (zaimplementowane, `context/archive/2026-10-23-fuzzy-event-dates/`). `MapsEventInput::eventAttributesFrom()` (`app/Actions/Persons/MapsEventInput.php:19`) przyjmuje klucze `year`, `month`, `day`, `qualifier` (`DateQualifier` albo jego wartość) i `until_year`. Zapisuje kolumny `date_qualifier` i `date_until_year`, przy czym rok końca tylko dla `Range`. Flagę `date_is_approximate` ustawia hook `saving` w `Event`. `ApplyFirstImport` używa tego traitu dla urodzeń i zgonów, a `CreateMarriage` przyjmuje ten sam kształt. Dzięki temu `ResearchDate` (`qualifier`, `year`, `month`, `day`, `untilYear`) mapuje się 1:1, a kolumny są spójne z ręcznym zapisem.
- **Cykle.** Pomijamy każdą relację, której oba końce leżą w tej samej silnie spójnej składowej grafu rodzic → dziecko o rozmiarze > 1. Każda taka krawędź leży na cyklu, więc nie wybieramy arbitralnie, którą krawędź „przeciąć”. Graf obejmuje tylko relacje, które przeszły sprawdzenie płci.
- **Kolejność zapisu w transakcji:**
  1. blokada drzewa i ponowne sprawdzenie `people()->exists()`,
  2. osoby,
  3. urodzenia i zgony,
  4. relacje,
  5. małżeństwa.
  
  `CreateParentChildRelation` i `CreateMarriage` otwierają własne `DB::transaction`. W zewnętrznej transakcji stają się savepointami, a wyjątek cofa całość.

## Faza 1: Identyfikator repo w schemacie

### Overview

Kolumna `repo_id` na osobach, wydarzeniach i relacjach rodzic–dziecko, unikalna w drzewie. Rekordy dodane w aplikacji mają `null`.

### Changes Required:

#### 1. Migracja

**File**: `database/migrations/<timestamp>_add_repo_id_to_people_events_and_parent_child_relations.php` (przez `vendor/bin/sail artisan make:migration`)

**Intent**: Dodaje miejsce na stały identyfikator z repo, żeby S-04 rozpoznał zaimportowane rekordy i nigdy nie ruszał rekordów bez niego. Migracja nie przenosi danych.

**Contract**: `repo_id` — string, nullable — w `people`, `events` i `parent_child_relations`, z indeksem unikalnym `(tree_id, repo_id)` w każdej tabeli. PostgreSQL dopuszcza wiele `NULL`, więc rekordy z aplikacji się nie zderzają. `down()` usuwa indeksy i kolumny.

#### 2. Modele

**File**: `app/Models/Person.php`, `app/Models/Event.php`, `app/Models/ParentChildRelation.php`

**Intent**: Pozwala Actions importu ustawić `repo_id`.

**Contract**: `repo_id` w atrybucie `#[Fillable([...])]` każdego modelu (nie we właściwości `$fillable`) i w PHPDoc właściwości (Larastan). Aplikacja nie ma `Model::shouldBeStrict`, więc pominięty wpis po cichu gubi `repo_id`; testy zapisu to wychwytują. Zasoby API i widoki się nie zmieniają, bo `repo_id` nie trafia do UI.

### Success Criteria:

#### Automated Verification:

- Migracja i jej cofnięcie działają: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`
- Dotychczasowe testy osób, relacji i drzewa przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/AncestorsTest.php`

---

## Faza 2: Planowanie importu

### Overview

Action `PlanFirstImport` zamienia odczyt repo na plan zapisu bez dotykania bazy (poza sprawdzeniem, czy drzewo ma osoby).

### Changes Required:

#### 1. Klasy wartości planu

**File**: `app/Support/Import/FirstImportPlan.php` (oraz małe klasy obok, jeśli upraszczają kształt, np. `PlannedPerson`, `PlannedRelation`)

**Intent**: Typowany wynik planowania, który komenda drukuje, a `ApplyFirstImport` zapisuje bez ponownego liczenia.

**Contract**: `final readonly`, wzorem `ResearchRepoReading`.
- Osoby do zapisania: `ResearchPerson` i gotowa treść notatki (`?string`).
- Relacje do zapisania: id repo rodzica i dziecka oraz klucz `repo_id`.
- Małżeństwa do zapisania: `ResearchMarriage` i klucz `repo_id`.
- Pominięte relacje: `list<ImportIssue>`.
- Liczby do podsumowania:
  - urodzenia, zgony i śluby,
  - daty według `DateQualifier` oraz wydarzenia bez daty,
  - wartości przeniesione do notatki według rodzaju: miejsca, pochowania, poziomy, notatki wydarzeń, warianty nazwiska.
- Podpowiedź probanta: jedyna osoba z numerem Ahnentafel 1 albo `null`. Gdy numer 1 ma kilka osób, podpowiedzi nie ma, bo raport odczytu już ostrzega o powtórzonym numerze.
- Powód blokady: `?string`, ustawiany, gdy drzewo ma osoby.

#### 2. `PlanFirstImport`

**File**: `app/Actions/Import/PlanFirstImport.php`

**Intent**: Podejmuje każdą decyzję importu przed zapisem, żeby autor zobaczył w podsumowaniu dokładnie to, co zostanie zapisane, a test mógł to sprawdzić bez bazy zapisu.

**Contract**: `handle(Tree $tree, ResearchRepoReading $reading): FirstImportPlan`.

Reguły:
- **Pustość:** `$tree->people()->exists()` ustawia powód blokady „Drzewo konta ma już osoby. Import do drzewa z osobami (dopasowanie — S-03, ponowny import — S-04) jeszcze nie działa; nic nie zostanie zapisane.” Plan i tak liczy podsumowanie, żeby autor zobaczył, co by weszło.
- **Osoby:** wszystkie z `$reading->persons`, także bez numeru Ahnentafel.
- **Relacje:**
  - Z `fatherId` i `motherId`, gdy rodzic jest w `$reading->persons`.
  - Gdy `fatherId === motherId`, obie relacje są pomijane z powodem „relacje pominięte: ten sam id jako ojciec i matka”. Parser S-01 tego nie odrzuca, a przy rodzicu bez płci odczyt nie ostrzega; bez tej reguły drugie `CreateParentChildRelation` rzuciłoby „Ta relacja już istnieje.” i cofnęło cały import.
  - Relacja, w której znana płeć rodzica przeczy roli (`ojciec` z płcią K, `matka` z płcią M), jest pomijana z powodem „relacja pominięta: płeć rodzica niezgodna z rolą”.
  - Potem pomijamy relacje leżące na cyklu (zob. Critical Implementation Details) z powodem wymieniającym id i pliki osób w cyklu.
- **Klucze `repo_id`:**
  - osoba: `O-0042`,
  - urodzenie: `O-0042/urodzenie`, zgon: `O-0042/zgon`,
  - ślub: `O-0001+O-0004/slub` (posortowana para z `ResearchMarriage::pair`),
  - relacja: `O-0084>O-0042` (rodzic>dziecko).
- **Wydarzenia:**
  - Urodzenie, zgon i ślub powstają, gdy klucz jest w bloku. `zgon: {}` daje zgon bez daty.
  - Data przechodzi wszystkimi pięcioma rodzajami (kontrakt S-07).
  - Pochowanie nie jest wydarzeniem w S-02.
- **Notatka osoby:** tekst tylko z linii, które mają treść; brak linii daje `null`. Format:

  ```
  Z repo badawczego (O-0001):
  Urodzenie: Zalesie; poziom A; 24 IV 1801 st.st.
  Zgon: poziom C
  Pochowanie: 1870-03-02; Cmentarz w Zalesiu
  Ślub z Anna Wrona (O-0004): Zalesie; poziom B
  Warianty nazwiska: Nowakowski
  ```

  Przykład pokazuje wszystkie rodzaje linii (wartości ilustracyjne). Kolejność linii: urodzenie, zgon, pochowanie, śluby (według id współmałżonka), warianty nazwiska. Dla `O-0001` z fixture (`nowak_jan_1801.md`) oczekiwana notatka to dokładnie:

  ```
  Z repo badawczego (O-0001):
  Urodzenie: Zalesie; poziom A; 24 IV 1801 st.st.
  Pochowanie: Cmentarz w Zalesiu
  Ślub z Anna Wrona (O-0004): Zalesie; poziom B
  Warianty nazwiska: Nowakowski
  ```

  - Linia urodzenia lub zgonu ma części: miejsce, `poziom X`, notatka. Data idzie do wydarzenia, nie do notatki.
  - Linia pochowania ma części: data, miejsce, `poziom X`, notatka. Pusty `pochowek: {}` daje „Pochowanie: bez szczegółów”.
  - Linia ślubu trafia do notatek obojga małżonków. Imię i nazwisko współmałżonka to „NN”, gdy brak.
  - Datę w notatce zapisujemy w gramatyce bloku („ok. 1825”, „między 1760 a 1765”, „1801-05-12”) metodą `ResearchDate::label()` (nowa, w `app/Support/Import/ResearchDate.php`).
- Planer nie czyta ani nie pisze niczego poza `$tree` (granica drzewa, FR-016).

#### 3. Testy planowania

**File**: `tests/Feature/FirstImportPlanTest.php`

**Intent**: Dowodzi reguł planowania na wymyślonym repo, bezpośrednio przez Action (CLAUDE.md: „Test the planning Action directly”).

**Contract**: `app(ReadResearchRepo::class)->handle(base_path('tests/Fixtures/research-repo'))`, potem `app(PlanFirstImport::class)->handle($tree, $reading)`. Każdy test ma drugiego użytkownika z nakładającymi się osobami (wzór `arrangeOverlappingUsers` w `tests/Feature/ImportResearchCommandTest.php:12`). Przypadki:
- puste drzewo autora → brak blokady; liczby osób, relacji, małżeństw i wydarzeń zgodne z fixture;
- drugi użytkownik ma osoby, a drzewo autora jest puste → brak blokady (cudze osoby się nie liczą);
- drzewo autora z jedną osobą → powód blokady ustawiony;
- relacja `kos_ewa.md` → `O-0003` pominięta z powodem płci;
- ten sam id jako `ojciec` i `matka` (rodzic bez płci, repo tymczasowe) → obie relacje pominięte z powodem, reszta zostaje;
- cykl A → B → A i dłuższy A → B → C → A: wszystkie krawędzie cyklu pominięte, relacje poza cyklem zostają (repo zbudowane w katalogu tymczasowym w teście, jak przypadki CRLF w `tests/Feature/ResearchRepoReadTest.php:164`, żeby nie zmieniać liczb fixture S-01);
- notatka `O-0001` równa oczekiwanemu tekstowi dla fixture powyżej; osoba bez miejsc, poziomów, notatek, wariantów i pochowania ma notatkę `null`;
- linia ślubu w notatkach obojga małżonków;
- liczby dat według rodzaju (zakres, przed, po, ok., dokładne, bez daty);
- `repo_id` dla osoby, wydarzeń, relacji i ślubu;
- podpowiedź probanta = `O-0001`; repo bez numeru 1 → `null`; dwie osoby z numerem 1 (repo tymczasowe) → `null`.

Plus test jednostkowy `ResearchDate::label()` dla pięciu rodzajów, dopisany do istniejącego `tests/Unit/ResearchDateTest.php` (dziś testy `parse()`).

### Success Criteria:

#### Automated Verification:

- Testy planowania i dat przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/FirstImportPlanTest.php tests/Unit/ResearchDateTest.php`
- Testy odczytu S-01 przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 3: Zapis importu

### Overview

Action `ApplyFirstImport` zapisuje plan w jednej transakcji do drzewa konta.

### Changes Required:

#### 1. `ApplyFirstImport`

**File**: `app/Actions/Import/ApplyFirstImport.php` (wynik, np. `app/Support/Import/FirstImportResult.php` z liczbami zapisanych rekordów)

**Intent**: Jedyne miejsce zapisu importu. Wszystko albo nic, tylko w drzewie wskazanego konta.

**Contract**: `handle(Tree $tree, FirstImportPlan $plan): FirstImportResult`. Rzuca wyjątek (np. `RuntimeException`), gdy plan ma powód blokady.

W `DB::transaction`:
1. `Tree::whereKey($tree->id)->lockForUpdate()` i ponowne `people()->exists()`. Drzewo z osobami → wyjątek, nic nie zapisane. Blokada serializuje tylko równoległe importy. Zapis osoby z UI (`CreatePerson`) nie blokuje drzewa, więc osoba dodana w aplikacji w trakcie transakcji nie zostanie wykryta. To świadomie przyjęte: jeden autor, konto testowe, skutkiem jest mieszane drzewo bez uszkodzenia danych.
2. Osoby przez `$tree->people()->create(...)`:
   - `first_name` ← `givenNames`,
   - `last_name` ← `surname`,
   - `gender`,
   - `note` ← notatka z planu,
   - `repo_id`.
   
   Mapa id repo → `Person`.
3. Urodzenia i zgony przez `$tree->events()->create(...)`: typ, data mapowana jak w formularzach (kontrakt S-07), `repo_id`. Osoba dołączona jako `ParticipantRole::Subject`.
4. Relacje przez `CreateParentChildRelation::handle($tree, $parent, $child)`, potem `repo_id` na zwróconej relacji.
5. Małżeństwa przez `CreateMarriage::handle($tree, $person, $spouse, $input)` z datą w kształcie wejścia formularza, potem `repo_id` na zwróconym wydarzeniu.

Wyjątek z dowolnego kroku (`ValidationException`, `RuntimeException`) cofa całą transakcję i wychodzi z Action.

#### 2. Testy zapisu

**File**: `tests/Feature/FirstImportApplyTest.php`

**Intent**: Dowodzi zapisu, granicy drzewa i atomowości. Komenda omija Policy, więc to jest dowód FR-016.

**Contract**: plan z fixture dla autora z pustym drzewem i drugiego użytkownika z nakładającymi się osobami (te same imiona i nazwiska). Przypadki:
- zapis osób z `repo_id`, imionami, nazwiskiem, płcią i notatką;
- urodzenia i zgony z rodzajem daty (zakres, przed, po, ok., dokładna), `zgon: {}` jako zgon bez daty, osoba jako `subject`;
- relacje z `repo_id`; relacja ze sprzeczną płcią nie istnieje;
- małżeństwa z obojgiem `spouse`, datą i `repo_id`;
- `root_person_id` autora zostaje `null`;
- wszystkie nowe wiersze mają `tree_id` autora; wiersze drugiego użytkownika bez zmian (`ownershipSnapshot` / `rowDiff` z `tests/Pest.php`);
- drzewo, które dostało osobę po planowaniu (plan bez blokady, potem osoba dodana) → wyjątek i zero nowych wierszy;
- błąd w środku zapisu (np. `CreateMarriage` podmieniony w kontenerze tak, by rzucał `ValidationException`) → zero nowych osób, wydarzeń i relacji.

### Success Criteria:

#### Automated Verification:

- Testy zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/FirstImportApplyTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 4: Komenda — podsumowanie, zgoda, kopia, zapis

### Overview

`import:research` dostaje podsumowanie importu, pytanie o zgodę, kopię bazy na produkcji i zapis. Kontrakt bloku dostaje zaktualizowaną regułę dla repo badawczego.

### Changes Required:

#### 1. Komenda

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Prowadzi autora od raportu do zapisu. Bez zgody i bez udanej kopii na produkcji nic się nie zapisuje.

**Contract**: sygnatura bez zmian (`{email} {path}`); nowy opis w `#[Description]` („Zaimportuj przodków z repo badawczego do pustego drzewa konta”). Przebieg po dotychczasowym raporcie (bez końcowego „Nic nie zostało zapisane.”):
1. `PlanFirstImport` → sekcja „Podsumowanie importu”:
   - osoby,
   - relacje (zapisane / pominięte),
   - małżeństwa,
   - urodzenia i zgony,
   - daty według rodzaju i bez daty,
   - „Do notatki osoby” (miejsca, pochowania, poziomy, notatki wydarzeń, warianty nazwiska, każde z odsyłaczem do wycinka, np. „miejsca: 4 (spis miejsc — S-05)”),
   - tabela „Pominięte relacje” (plik, powód).
2. Plan z powodem blokady → `error` z powodem, `FAILURE`, bez pytania.
3. Zero osób → „Nic do zapisania.”, `SUCCESS`, bez pytania.
4. `$this->confirm("Zapisać {N} osób do drzewa konta {email}?", false)`. „Nie” lub `--no-interaction` → „Nic nie zostało zapisane.”, `SUCCESS`.
5. `app()->isProduction()` → `$this->call('backup:run', ['--only-db' => true, '--disable-notifications' => true])`. Wynik ≠ `SUCCESS` → „Kopia bazy nie powiodła się, nic nie zostało zapisane.”, `FAILURE` (wzór `RestoreBackup.php:316`).
6. `ApplyFirstImport`. Wyjątek → `error` z komunikatem i „Nic nie zostało zapisane.”, `FAILURE`.
7. Podsumowanie zapisu (liczby z `FirstImportResult`) i linia o probancie: „Import nie ustawia probanta — wybierz go w aplikacji (Drzewo → Wybierz probanta). Ahnentafel 1: Jan Nowak (O-0001).” Druga część tylko przy podpowiedzi.

#### 2. Testy komendy

**File**: `tests/Feature/ImportResearchCommandTest.php`

**Intent**: Dowodzi przebiegu komendy przez `$this->artisan(...)` z `expectsConfirmation` (CLAUDE.md) i dostosowuje testy S-01 do nowego zakończenia.

**Contract**: Istniejące testy:
- raport S-01 sprawdzany na autorze z pustym drzewem i odpowiedzią „no”;
- „lists files without a block only with -v” (`:430`) przechodzi na autora z pustym drzewem i w obu wywołaniach odpowiada „no” (`expectsConfirmation`); dziś używa autora z osobami i po zmianie padłby na odmowie;
- „writes nothing for either account” zostaje jako przypadek drzewa z osobami (odmowa, `assertFailed`, brak zmian w obu drzewach);
- przypadki konta, drzewa i katalogu bez zmian.

Nowy helper obok `arrangeOverlappingUsers`: autor z pustym drzewem i drugi użytkownik z osobami o imionach i nazwiskach z fixture. Każdy test komendy na pustym drzewie musi odpowiedzieć na pytanie o zgodę (`expectsConfirmation`), bo pytanie bez oczekiwania kończy się błędem mocka wyjścia.

Nowe przypadki, każdy z drugim użytkownikiem o nakładających się danych:
- „yes” → osoby zapisane w drzewie autora, cudze bez zmian, w wyjściu linia o probancie z `O-0001`;
- „no” → nic nie zapisane;
- środowisko produkcyjne (`app()->detectEnvironment(fn () => 'production')`) z podmienionym `backup:run`, które zwraca błąd → nic nie zapisane, `assertFailed`, komunikat o kopii;
- produkcja z kopią zwracającą sukces → zapis i kopia wywołana z `--only-db`;
- poza produkcją `backup:run` nie jest wywołane.

Podmiana `backup:run` w teście: zastępcza komenda `Artisan::command('backup:run {--only-db} {--disable-notifications}', …)`. Sygnatura musi deklarować obie opcje, inaczej Symfony rzuci „option does not exist” i test błędu kopii przejdzie z niewłaściwego powodu. Rejestracja musi być przed pierwszym `$this->artisan()` w teście, bo instancja Artisan powstaje leniwie i później nie widzi nowej komendy. Zastępcza komenda zapisuje swoje wywołanie (licznik i wartość `--only-db`), a test sprawdza, że była wywołana z `--only-db` albo, poza produkcją, że nie była wywołana wcale. Jeśli to się okaże zawodne, wywołanie kopii idzie do małej Action (np. `app/Actions/Import/BackupDatabaseBeforeImport.php`) mockowanej przez `$this->mock()`, jak w `tests/Feature/PersonPhotoTest.php:86`.

#### 3. Kontrakt bloku — reguła dla repo badawczego

**File**: `context/foundation/research-block-contract.md`

**Intent**: Reguła do wklejenia w `CLAUDE.md` repo badawczego mówi dziś, że komenda „nic nie zapisuje”. Po S-02 zapisuje po zgodzie.

**Contract**: Linia „Sprawdź: …” w sekcji „Reguła do wklejenia…” mówi, że komenda pokazuje raport i podsumowanie, a zapisuje dopiero po odpowiedzi „tak”, więc do samego sprawdzenia bloku odpowiada się „nie”. Wersja kontraktu (`version: 1`) bez zmian, bo pola się nie zmieniają.

### Success Criteria:

#### Automated Verification:

- Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- Wszystkie testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/FirstImportPlanTest.php tests/Feature/FirstImportApplyTest.php tests/Unit/ResearchDateTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Przebieg na kopii prawdziwego repo z pliku przodków na nowym koncie testowym: podsumowanie jest czytelne, liczby zgadzają się z repo, odpowiedź „tak” zapisuje bez błędu
- Po wyborze probanta (Ahnentafel 1) w `/tree` przodkowie są widoczni z rodzicami i małżonkami, a widok przodków pokazuje wszystkie zaimportowane pokolenia
- Panel osoby pokazuje urodzenie i zgon z datą (także zakres, „przed” i „po” wg S-07) oraz notatkę „Z repo badawczego (…)” z miejscami, poziomami i wariantami
- Ponowne uruchomienie na tym samym koncie kończy się odmową bez zapisu

**Implementation Note**: Po fazie i zielonych testach automatycznych poprosić autora o ręczny przebieg na prawdziwych plikach (konto testowe, świeża kopia repo), potem o pełny zestaw `vendor/bin/sail artisan test --compact`.

---

## Testing Strategy

### Unit Tests:

- `ResearchDate::label()` dla pięciu rodzajów daty.

### Integration Tests:

- **Planowanie (Action bezpośrednio):**
  - pustość drzewa,
  - płeć rodzica,
  - cykle (repo tymczasowe),
  - format notatki,
  - klucze `repo_id`,
  - liczby,
  - podpowiedź probanta.
- **Zapis (Action bezpośrednio):**
  - wszystkie rodzaje rekordów,
  - granica drzewa z drugim użytkownikiem,
  - wyścig (osoba dodana po planowaniu),
  - wycofanie przy błędzie w środku.
- **Komenda (`$this->artisan`):**
  - zgoda „tak” i „nie”,
  - odmowa na drzewie z osobami,
  - kopia na produkcji: błąd i sukces,
  - brak kopii poza produkcją.
- Każdy test importu ma drugiego użytkownika o tych samych imionach i nazwiskach (CLAUDE.md).

### Manual Testing Steps:

1. Załóż nowe konto testowe (drzewo bez osób) i skopiuj świeże repo badawcze do `storage/gen-tree-import/`.
2. Uruchom `vendor/bin/sail artisan import:research <e-mail> <ścieżka w kontenerze>`, przeczytaj podsumowanie i odpowiedz „nie”. Sprawdź, że nic nie zapisano.
3. Uruchom ponownie i odpowiedz „tak”.
4. W aplikacji wybierz probanta (osoba z Ahnentafel 1). Przejdź `/tree` i widok przodków przez wszystkie pokolenia.
5. Otwórz kilka osób z datą nieostrą i z notatką z repo.
6. Uruchom import trzeci raz i sprawdź odmowę.

## Performance Considerations

`CreateParentChildRelation` buduje graf drzewa przy każdej relacji. Przy ok. 190 przodkach i ok. 300 relacjach to kilkaset małych zapytań w jednej transakcji, co mieści się w sekundach. Import uruchamia się rzadko i ręcznie, więc nie optymalizujemy.

## Migration Notes

- Migracja tylko dodaje kolumny nullable i indeksy; nie przenosi danych, więc nie wymaga testu migracji danych. Jest odwracalna.
- Na produkcji `scripts/deploy.sh` robi kopię bazy przed `migrate` jak dotąd.
- Pierwszy import na produkcji i tak czeka na S-03 i S-05 (drzewo autora ma osoby, więc S-02 odmówi).

## References

- Roadmapa: `context/foundation/roadmap.md` (S-02, S-07)
- PRD: `context/foundation/prd.md` (US-01, FR-003, FR-005, FR-006, FR-008, FR-009, FR-016)
- Kontrakt bloku: `context/foundation/research-block-contract.md`
- Poprzedni wycinek: `context/archive/2026-10-23-research-blocks-read-and-report/plan.md` (nota o datach dla S-02 w „What We're NOT Doing”) i `reviews/impl-review.md` (F3: cykle)
- Decyzja o probancie: `context/archive/2026-09-28-graphical-tree-view/plan.md:98`
- Wzór kopii przed zapisem: `app/Console/Commands/RestoreBackup.php:305`
- Wzór Action z transakcją: `app/Actions/Persons/CreatePerson.php:45`, `app/Actions/Parishes/MergeParish.php:21`
- Testy i helpery: `tests/Feature/ImportResearchCommandTest.php:12`, `tests/Pest.php` (`ownershipSnapshot`, `rowDiff`)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Identyfikator repo w schemacie

#### Automated

- [x] 1.1 Migracja i jej cofnięcie działają: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate` — ce58d82
- [x] 1.2 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check` — ce58d82
- [x] 1.3 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent` — ce58d82
- [x] 1.4 Dotychczasowe testy osób, relacji i drzewa przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/AncestorsTest.php` — ce58d82

### Phase 2: Planowanie importu

#### Automated

- [x] 2.1 Testy planowania i dat przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/FirstImportPlanTest.php tests/Unit/ResearchDateTest.php`
- [x] 2.2 Testy odczytu S-01 przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php`
- [x] 2.3 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [x] 2.4 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

### Phase 3: Zapis importu

#### Automated

- [ ] 3.1 Testy zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/FirstImportApplyTest.php`
- [ ] 3.2 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [ ] 3.3 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

### Phase 4: Komenda — podsumowanie, zgoda, kopia, zapis

#### Automated

- [ ] 4.1 Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- [ ] 4.2 Wszystkie testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/FirstImportPlanTest.php tests/Feature/FirstImportApplyTest.php tests/Unit/ResearchDateTest.php`
- [ ] 4.3 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [ ] 4.4 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual

- [ ] 4.5 Przebieg na kopii prawdziwego repo z pliku przodków na nowym koncie testowym: podsumowanie jest czytelne, liczby zgadzają się z repo, odpowiedź „tak” zapisuje bez błędu
- [ ] 4.6 Po wyborze probanta (Ahnentafel 1) w `/tree` przodkowie są widoczni z rodzicami i małżonkami, a widok przodków pokazuje wszystkie zaimportowane pokolenia
- [ ] 4.7 Panel osoby pokazuje urodzenie i zgon z datą (także zakres, „przed” i „po” wg S-07) oraz notatkę „Z repo badawczego (…)” z miejscami, poziomami i wariantami
- [ ] 4.8 Ponowne uruchomienie na tym samym koncie kończy się odmową bez zapisu
