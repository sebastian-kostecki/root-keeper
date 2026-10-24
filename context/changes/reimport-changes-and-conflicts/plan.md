# Kolejny import z podglądem zmian i konfliktów — plan implementacji

## Overview

`import:research` przestaje odmawiać drzewu, które ma już rekordy z importu. Komenda rozpoznaje rekordy po id repo i zapamiętuje, co import wpisał ostatnio, więc dla każdego pola zna trzy wartości: repo teraz (R), repo przy ostatnim imporcie (S) i aplikację (A). Na tej podstawie pokazuje:
- nowe osoby i rekordy,
- wartości zmienione w repo, których nikt nie ruszał w aplikacji (zatwierdzane zbiorczo albo pojedynczo),
- konflikty z ręcznymi zmianami (obie wartości),
- rekordy usunięte w repo (usuń albo odłącz),
- rekordy usunięte w aplikacji, które repo nadal ma (zostaw usunięte albo przywróć).

Każda decyzja zapada raz. Rekordy bez id repo nigdy nie są ruszane.

To roadmapa S-04 (US-02, FR-005, FR-006, FR-008, FR-014, FR-015). Podgląd w terminalu (PRD Open Questions 4) używa wzoru z S-03.

## Current State Analysis

**Import po S-02 i S-03 (`context/archive/2026-10-23-first-import-into-empty-tree/`, `context/archive/2026-10-24-first-import-matches-hand-entered/`):**
- Komenda (`app/Console/Commands/ImportResearch.php:45`) przechodzi przez kolejne kroki:
  1. raport odczytu,
  2. `MatchResearchPersons`,
  3. decyzje o tożsamości (`:185`),
  4. `PlanFirstImport` i podsumowanie (`:391`),
  5. konflikty (`:348`),
  6. zgoda (`:123`),
  7. kopia bazy na produkcji (`:129`),
  8. `ApplyFirstImport`.
- Odpowiedzi idą przez `Laravel\Prompts\select` (`:371`), a tabele przez `$this->table`.
- **Blokada ponownego importu** to `MatchResearchPersons::BLOCKED_REASON` (`app/Actions/Import/MatchResearchPersons.php:24`). Sprawdzają ją:
  - `MatchResearchPersons.php:39`,
  - `PlanFirstImport.php:233`,
  - `ApplyFirstImport.php:57` (pod blokadą drzewa),
  - komenda (`:78`, `:104`).
- `PlanFirstImport` (`app/Actions/Import/PlanFirstImport.php`, 882 linie) zawiera:
  - porównanie pól dopasowanej osoby (`compareMatch` `:314`, `compareText` `:346`, `compareDate` `:371`, `refines` `:397`),
  - wspólny graf relacji (`sharedGraph` `:277`, `planRelations` `:466`, `relationConflict` `:550`, cykle repo Tarjanem `:599`),
  - konflikt płci (`sameSexOffender` `:430`),
  - małżeństwa (`planMarriages` `:640`, `weddingOf` `:695`),
  - blok notatki „Z repo badawczego (O-…):” (`noteFor` `:811`). Jego linie nie zawierają pustej linii, a S-03 dopisuje go po `"\n\n"` (`ApplyFirstImport.php:288`).
- `ApplyFirstImport` (`app/Actions/Import/ApplyFirstImport.php`) zapisuje w jednej transakcji. Pod blokadą ponownie sprawdza dopasowane osoby i wartości (`guardPerson` `:326`, `guardWedding` `:349`, poprawka F1 z przeglądu S-03). Daty zmienia przez model (`markEvent` `:368`).
- Klucze repo (`repo_id`) mają unikalny indeks `(tree_id, repo_id)` na `people`, `events` i `parent_child_relations` (`database/migrations/2026_10_24_053038_…:25`). Ich postać:
  - osoba `O-0042`,
  - `O-0042/urodzenie`, `O-0042/zgon`,
  - relacja `O-0084>O-0042`,
  - ślub `O-0001+O-0004/slub`.

  Składane są dziś jako tekst w kilku miejscach planera (`:146`, `:511`, `:648`).
- Pominięty wpis odczytu to `ImportIssue(file, message)` (`app/Support/Import/ImportIssue.php`), **bez id**. Walidator `ParsePersonBlock` sprawdza cały blok naraz (`app/Actions/Import/ParsePersonBlock.php:71`), więc `id` zgodne z wzorcem jest znane także przy pominięciu z innego powodu. Wyjątek: data jako obiekt YAML (`:64-69`) pomija wpis przed walidatorem, więc tam id trzeba sprawdzić osobno. Powtórzone id (`ReadResearchRepo.php:103`) jest znane zawsze.

**Aplikacja (raport ścieżek zapisu):**
- Nie ma historii zmian, obserwatorów ani audytu. `updated_at` nie nadaje się do wykrywania ręcznej zmiany. Podbijają go:
  - scalanie miejscowości (`MergeLocality.php:55`),
  - zdjęcie i notatka,
  - sam import.
- `UpdatePerson` (`app/Actions/Persons/UpdatePerson.php`):
  - zapisuje tylko zmienione atrybuty;
  - urodzenie i zgon zmienia w miejscu (`putEvent` `:126-150`), więc `repo_id` zostaje;
  - brakujące urodzenie tworzy z `repo_id = null` (`:144`);
  - zgon kasuje przy odznaczeniu „zmarł” (`:76`).
- `UpdateMarriage` zmienia ślub w miejscu.
- Relację i ślub UI usuwa wprost przez `->delete()` (`ParentChildRelationController.php:36`, `MarriageController.php:51`).
- `DeletePerson` (`app/Actions/Persons/DeletePerson.php:24`):
  - usuwa wszystkie małżeństwa osoby (także wspólne) i jej wydarzenia bez innych uczestników;
  - relacje, zamieszkania, `buried_people` i `event_participants` znikają kaskadą, a `trees.root_person_id` dostaje `null`;
  - **pliki zdjęcia kasuje zaraz po swojej `DB::transaction`** (`:41`). Wewnątrz transakcji importu to tylko savepoint, więc wycofanie importu zostawiłoby wiersz osoby bez pliku zdjęcia.
- `repo_id` poza `app/Actions/Import` występuje tylko w `$fillable` modeli. Nie ma go w Resources ani w UI.

**Testy:**
- Blokadę ponownego importu sprawdzają:
  - `tests/Feature/FirstImportPlanTest.php:140`,
  - `tests/Feature/FirstImportApplyTest.php:199`, `:225`, `:560`,
  - `tests/Feature/ImportResearchCommandTest.php:110`,
  - `tests/Feature/ResearchPersonMatchTest.php:376`.
- Wzór tymczasowego repo: `sys_get_temp_dir()/<prefiks>-uniqid` z `drzewo/<id>.md`, sprzątane w `finally` (`FirstImportPlanTest.php:47`, `ImportResearchCommandTest.php:634`).
- Helpery `tests/Pest.php`:
  - `attachBirth` `:58`, `attachMarriage` `:72`, `linkParent` `:94`, `attachDeath` `:106`,
  - `ownershipSnapshot` `:148`, `rowDiff` `:227`.
- `fakeBackupRun` jest w `ImportResearchCommandTest.php:55`.

## Desired End State

`vendor/bin/sail artisan import:research <e-mail> <repo>` na drzewie po imporcie:
1. Drukuje raport odczytu jak w S-01.
2. Drukuje sekcję „Stan względem ostatniego importu”: znane osoby, nowe w repo, usunięte w repo, usunięte w aplikacji, nie do oceny.
3. Nowe osoby repo przechodzą dopasowanie z S-03 (pewne zbiorczo, wątpliwe pojedynczo). Kandydatami są wyłącznie osoby drzewa bez `repo_id`.
4. Przeprowadza przez każdy rekord usunięty w repo („Usunięte w repo k/N”). Przed pytaniem pokazuje tabelę skutków: zdjęcie, powiązania ze źródłami, zamieszkania, relacje i małżeństwa (w tym bez id repo), nagrobki, probant. Wybór to „Zostaw — odłącz od repo” (domyślne) albo „Usuń z aplikacji”. Usunięte urodzenie jest tylko odłączane, bez pytania.
5. Przeprowadza przez każdy rekord usunięty w aplikacji, który repo nadal ma i który nie był jeszcze rozstrzygnięty albo zmienił się w repo („Usunięte w aplikacji k/N”). Wybór to „Zostaw usunięte” (domyślne) albo „Przywróć z repo”.
6. Drukuje podsumowanie S-03 rozszerzone o:
   - zmiany z repo,
   - zachowane ręczne zmiany,
   - usunięcia, odłączenia, przywrócenia,
   - tabelę „Nie do oceny”.
7. Pokazuje tabelę „Zmiany z repo” (osoba, pole, w aplikacji, teraz w repo) i pyta „Zatwierdź wszystkie” albo „Przejrzyj pojedynczo” („Zmiana k/N”: „Weź z repo” / „Zostaw”).
8. Przeprowadza przez konflikty jak S-03, z dodatkową linią „poprzednio w repo: …”. Konflikt bloku notatki pokazuje przed pytaniem oba bloki.
9. Pyta o zgodę (domyślnie „nie”). Na produkcji robi kopię bazy, a potem w jednej transakcji:
   - usuwa i odłącza,
   - tworzy i przywraca,
   - zmienia zatwierdzone wartości,
   - oznacza istniejące rekordy,
   - zapisuje pamięć ostatniego importu.
10. Przerwanie przed zgodą niczego nie zapisuje. Wycofanie zapisu zostawia także pliki zdjęć.

Drugi przebieg na tym samym repo bez zmian po żadnej stronie kończy się komunikatem „Nic do zapisania.” i nie zadaje pytań. Pierwszy import (puste drzewo albo drzewo tylko z ręcznymi osobami) działa jak w S-02 i S-03, a dodatkowo zapisuje pamięć ostatniego importu. Cudze drzewa są nietknięte.

Weryfikacja:
- testy Pest dla reguły trzech wartości, klasyfikacji, planu, zapisu i komendy, każdy z drugim użytkownikiem o nakładających się danych;
- ręczny przebieg na kopii `test-pack`, zmienionej przez autora, przeciw drzewu dev.

### Key Discoveries:

- **Pamięć ostatniego importu musi żyć poza rekordem.** Decyzja „zapytaj raz o rekord usunięty w aplikacji” wymaga wiedzy, że rekord był zaimportowany, także po jego usunięciu (kaskady z `DeletePerson`). Kolumna na `people` czy `events` znika razem z wierszem. Stąd osobna tabela kluczowana `(tree_id, repo_id)`, bez klucza obcego do rekordu.
- **Pamięć przechowuje wyłącznie wartości z repo, nigdy z aplikacji.** Przy „Zostaw z aplikacji”, „Odrzuć zmianę” i „Zostaw usunięte” zapisuje się R. Dzięki temu ta sama wartość z repo nie wraca, a jej ponowna zmiana w repo daje konflikt, bo A ≠ S.
- **Rekordy z S-02/S-03 nie mają pamięci.** Nie uzupełniamy jej migracją: dla dopasowanych osób S-03 wartość w aplikacji mogła celowo różnić się od repo („Zostaw z aplikacji”). Brak S włącza regułę z S-03: puste pole w aplikacji albo doprecyzowanie daty to uzupełnienie, każda inna różnica to konflikt. Na produkcji importu jeszcze nie było (czeka na S-05).
- **Urodzenie i zgon dopisane w aplikacji** (`repo_id = null`, uwaga z przeglądu S-02 w roadmapie): gdy repo później doda `urodzenie` albo `zgon`, istniejące wydarzenie dostaje `repo_id`, a data jest uzupełnieniem albo konfliktem jak w S-03. Brakujące wydarzenie powstaje. UI tworzy urodzenie przy każdej osobie, więc to częsty przypadek.
- **Urodzenia nie usuwamy importem.** Aplikacja zakłada urodzenie przy każdej osobie (`CreatePerson`, `UpdatePerson::putEvent`). Usunięcie `urodzenie` w repo tylko odłącza wydarzenie (czyści `repo_id` i pamięć) i trafia do tabeli „Odłączone” w podsumowaniu.
- **Plik z błędnym blokiem nie może wyglądać na usunięty.** Dotyczy to też pliku, którego blok nie zaczyna się w pierwszej linii (`filesWithoutBlock` z ostrzeżeniem). Pominięty wpis z czytelnym id wyłącza z „usuniętych w repo” każdy klucz zawierający to id, także relację czy ślub z tą osobą. Gdy jakiś wpis pominięto bez czytelnego id (YAML, brak lub zły `id`, niezamknięty frontmatter), wszystkie kandydaty do usunięcia idą do „Nie do oceny — popraw pominięte wpisy” i nic się z nimi nie dzieje.
- **Prompts w testach** (z S-03): `expectsChoice` jest kluczowane treścią pytania, więc każda treść zawiera „k/N” i klucz repo. `select()` zawsze dostaje `default`.

## What We're NOT Doing

- **Miejsc ze spisu, poziomów A–D, wariantów nazwiska jako pól** (S-05, S-08, S-09). Do tych wycinków żyją w bloku notatki, który ponowny import aktualizuje jako jedno pole. Każdy z tych wycinków doda swoje pole do reguły trzech wartości.
- **Źródeł i skanów z repo** (S-06).
- **Zmian w UI.** Edycja, usuwanie i dodawanie w aplikacji działają jak dotąd (FR-015). Pamięć ostatniego importu jest niewidoczna w aplikacji.
- **Uzupełniania pamięci dla rekordów z S-02/S-03** migracją ani komendą. Pierwszy ponowny import zapisze ją po drodze.
- **Ponownego zadawania pytań o odrzucone wartości.** „Wziąć” odrzuconą wartość można tylko ręcznie w aplikacji.
- **Automatycznego podłączania rekordu odłączonego**, gdy jego id wróci do repo. Osoba przejdzie dopasowanie z S-03, a relacja, wydarzenie i ślub wejdą jako nowe albo oznaczą istniejące jak w S-03.
- **Usuwania urodzenia** importem (wyżej).
- **Zapisu i wznawiania decyzji, trybu nieinteraktywnego z pytaniami.** Każde pytanie przy `--no-interaction` kończy się odmową bez zapisu.
- **Scalania osób w aplikacji.** Scalenie w repo widać jako usunięcie jednej osoby i nowe relacje drugiej, pominięte z powodem, jeśli łamią reguły drzewa.
- **Zmian w kontrakcie bloku** (`wersja: 1` bez zmian).

## Implementation Approach

Jeden uogólniony potok importu zamiast drugiego obok. „Pierwszy import” to szczególny przypadek: żadnej znanej osoby i żadnej pamięci.

1. **`ClassifyRepoRecords`** (bez zapisu) porównuje klucze z odczytu, rekordy drzewa z `repo_id` i pamięć ostatniego importu. Każdy klucz trafia do jednej grupy:
   - znany,
   - nowy,
   - usunięty w aplikacji,
   - usunięty w repo,
   - usunięty po obu stronach,
   - nie do oceny.
2. **`MatchResearchPersons`** dopasowuje tylko nowe osoby repo, a kandydatami są tylko osoby drzewa bez `repo_id`.
3. Komenda zbiera decyzje: tożsamość, usunięte w repo, usunięte w aplikacji (`ImportDecisions`).
4. **`PlanImport`** (dziś `PlanFirstImport`) buduje pełny plan:
   - nowe i dopasowane osoby jak w S-03,
   - znane osoby z regułą trzech wartości (`ThreeWayValue`) dla pól osoby, dat urodzenia, zgonu i ślubu oraz bloku notatki,
   - usunięcia i odłączenia z listą skutków,
   - przywrócenia,
   - relacje i małżeństwa na wspólnym grafie po usunięciach,
   - wpisy pamięci do zapisania i usunięcia.
5. Komenda zbiera zatwierdzenia zmian (`ChangeApprovals`) i rozstrzygnięcia konfliktów (`ConflictResolutions`), potem pyta o zgodę.
6. **`ApplyImport`** (dziś `ApplyFirstImport`) zapisuje wszystko w jednej transakcji, po ponownym sprawdzeniu pod blokadą, że rekordy i wartości są takie, jak widział plan.

## Critical Implementation Details

- **Reguła trzech wartości.** Wartości porównujemy w postaci znormalizowanej:
  - imiona i nazwisko → `PlanImport::cleanText`,
  - płeć → wartość enuma,
  - daty → R z A porównywane obiektowo (`ResearchDate::fromEvent` i `==`, jak `compareDate` w S-03), a z S przez `ResearchDate::label()`, które jest postacią daty w pamięci (label pomija np. miesiąc w zakresie w starych, błędnych wierszach, więc nie służy do porównania R z A),
  - blok notatki → tekst bloku.

  Kolejność sprawdzeń:
  1. R = A → nic (pamięć ← R);
  2. jest S i R = S → ręczna zmiana zostaje (pamięć bez zmian);
  3. jest S i A = S → zmiana z repo (także R puste: wyczyszczenie pola);
  4. jest S → konflikt;
  5. brak S i R puste → nic;
  6. brak S i A puste albo R doprecyzowuje datę A → uzupełnienie;
  7. brak S → konflikt.

  Przypadki do testu, w zapisie (R, S, A):
  - (1801, 1801, 1830) → zostaje;
  - (1802, 1801, 1801) → zmiana;
  - (1802, 1801, 1830) → konflikt;
  - (1802, 1801, 1802) → nic;
  - („—”, 1801, 1801) → zmiana na puste;
  - (1801, brak, puste) → uzupełnienie;
  - (1801-05-12, brak, 1801) → uzupełnienie;
  - (1801, brak, 1830) → konflikt;
  - (puste, brak, 1830) → nic.
- **Pamięć zapisuje się z R przy każdej decyzji o kluczu obecnym w repo** (nic, zmiana, odrzucona zmiana, konflikt w dowolną stronę, uzupełnienie, oznaczenie, utworzenie, „Zostaw usunięte”). Wyjątki:
  - wartość pominięta przez regułę drzewa (`skippedValues`, np. płeć) zostawia stare S, żeby wróciła przy następnym imporcie;
  - osoba pominięta przy dopasowaniu nie dostaje pamięci.

  Odłączenie, usunięcie i „usunięte po obu stronach” usuwają wpis pamięci.
- **Usunięte w aplikacji: kiedy pytać.** Rekord brakuje, a pamięć istnieje. Pytanie pada, gdy wpis nie ma flagi `deletion_kept`, albo gdy ją ma, ale R ≠ S. „Zostaw usunięte” ustawia `deletion_kept = true` i S = R. „Przywróć z repo” tworzy rekord z wartości R i zeruje flagę.
- **Grupowanie pytań o osobę.** Usunięcie albo przywrócenie osoby obejmuje jej urodzenie, zgon oraz relacje i małżeństwa, których klucz też zniknął (albo też brakuje). Nie ma o nie osobnych pytań. Odłączenie osoby odłącza je razem z nią. Osobne pytanie dostaje tylko relacja, zgon albo ślub osoby, która sama zostaje.
- **Kolejność w zapisie.** Najpierw usunięcia i odłączenia, potem nowe i przywrócone osoby, zmiany znanych osób, relacje, małżeństwa, a na końcu pamięć. Planer liczy wspólny graf relacji już bez usuwanych krawędzi, więc zapis musi je usunąć przed `CreateParentChildRelation`.
- **`DeletePerson` w transakcji importu.** Kasowanie plików zdjęcia przechodzi na `DB::afterCommit`, czyli po zatwierdzeniu najbardziej zewnętrznej transakcji. W UI zachowanie jest bez zmian (jedna transakcja), a wycofany import nie kasuje plików.
- **Kolejność w komendzie.** Dopasowanie nowych osób, usunięte w repo i usunięte w aplikacji muszą zapaść przed planem, bo zmieniają graf i listę osób. Zmiany i konflikty zależą od planu, dlatego kolejność to:
  1. odczyt,
  2. klasyfikacja,
  3. tożsamość,
  4. usunięte w repo,
  5. usunięte w aplikacji,
  6. plan,
  7. zmiany,
  8. konflikty,
  9. zgoda,
  10. kopia,
  11. zapis.

---

## Faza 1: Pamięć ostatniego importu

### Overview

Tabela i model pamięci ostatniego importu, wspólny format kluczy repo i ogólne nazwy klas importu. Pierwszy import zaczyna zapisywać pamięć. `DeletePerson` jest gotowy do wywołania w transakcji importu. Blokada ponownego importu jeszcze działa.

### Changes Required:

#### 1. Tabela i model `repo_snapshots`

**File**: `database/migrations/<data>_create_repo_snapshots_table.php` (`vendor/bin/sail artisan make:model RepoSnapshot -m --no-interaction`), `app/Models/RepoSnapshot.php`, `app/Models/Tree.php`

**Intent**: Trwała pamięć wartości z repo przy ostatnim imporcie, kluczowana tak jak `repo_id`, niezależna od istnienia rekordu.

**Contract**:
- Kolumny tabeli `repo_snapshots`:
  - `id`, `tree_id` (FK `cascadeOnDelete`), `repo_id` (string),
  - `values` (`jsonb`, mapa pole → znormalizowany tekst albo `null`),
  - `deletion_kept` (bool, domyślnie `false`), timestamps;
  - unikalny indeks `(tree_id, repo_id)`.
- `down()` usuwa tabelę. Komentarz mówi, że po cofnięciu ponowny import traktuje wszystkie rekordy jak z S-02 (każda różnica to konflikt).
- Migracja nie przenosi danych, więc nie ma `MigrationTest`.
- Model `RepoSnapshot`: `values` → `array`, `deletion_kept` → `bool`. `Tree::repoSnapshots(): HasMany`.
- Pola `values` według rodzaju klucza:
  - osoba: `imiona`, `nazwisko`, `plec`, `notatka`;
  - urodzenie, zgon, ślub: `data`;
  - relacja: pusta mapa.

#### 2. Klucze repo

**File**: `app/Support/Import/RepoKeys.php`

**Intent**: Jedno miejsce formatu kluczy, z którego korzystają planer, klasyfikacja i zapis zamiast sklejania tekstu w kilku miejscach.

**Contract**: statyczne metody:
- `person(id)`, `birth(id)`, `death(id)`, `relation(parentId, childId)`, `marriage(firstId, secondId)` — ten sam kształt co dziś, kolejność osób ślubu jak w `PlanFirstImport.php:648`;
- `kindOf(key)`: osoba, urodzenie, zgon, relacja, ślub;
- `personIdsOf(key)`: id osób w kluczu.

Dotychczasowe miejsca w planerze używają `RepoKeys`.

#### 3. Ogólne nazwy klas importu

**File**:
- `app/Actions/Import/PlanFirstImport.php` → `PlanImport.php`,
- `app/Actions/Import/ApplyFirstImport.php` → `ApplyImport.php`,
- `app/Support/Import/FirstImportPlan.php` → `ImportPlan.php`,
- `app/Support/Import/FirstImportResult.php` → `ImportResult.php`,
- `tests/Feature/FirstImportPlanTest.php` → `ImportPlanTest.php`,
- `tests/Feature/FirstImportApplyTest.php` → `ImportApplyTest.php`,
- odwołania w komendzie i testach.

**Intent**: Te same klasy obsłużą pierwszy i ponowny import, a nazwa „First” byłaby myląca. Zmiana jest mechaniczna (`git mv`) i nie zmienia zachowania.

**Contract**: nazwy klas i plików jak wyżej, sygnatury bez zmian.

#### 4. Zapis pamięci przy pierwszym imporcie

**File**: `app/Actions/Import/PlanImport.php`, `app/Support/Import/ImportPlan.php`, `app/Actions/Import/ApplyImport.php`

**Intent**: Od teraz każdy zapis importu zostawia S dla każdego klucza, który zapisał albo oznaczył, z wartości R. Następny import ma wtedy pełną regułę trzech wartości.

**Contract**:
- `ImportPlan` dostaje `snapshots: array<string, array<string, string|null>>` (klucz → wartości R) oraz `forgottenSnapshots: list<string>` (w tej fazie puste).
- Planer wypełnia `snapshots` dla każdej nowej i dopasowanej osoby, jej urodzenia i zgonu, zaplanowanej relacji (nowej i oznaczanej) oraz ślubu.
- Konflikt, w którym autor zostawił wartość z aplikacji, też zapisuje R. Wartość z `skippedValues` zostawia pole bez wpisu, a przy pierwszym imporcie wpis bez tego pola.
- Pole `notatka` osoby to tekst bloku z `noteFor`.
- `ApplyImport` na końcu transakcji robi `upsert` do `$tree->repoSnapshots()` (`deletion_kept = false`) i usuwa klucze z `forgottenSnapshots`. `upsert` przez relację dokłada `tree_id` i `created_at`/`updated_at` (`HasOneOrMany::upsert`), ale nie stosuje castów modelu, więc `values` idzie jako `json_encode(...)`.

#### 5. `DeletePerson` w zewnętrznej transakcji

**File**: `app/Actions/Persons/DeletePerson.php`

**Intent**: Usunięcie osoby wywołane w transakcji importu nie może skasować pliku zdjęcia, zanim transakcja się zatwierdzi.

**Contract**: kasowanie plików (`:41`) idzie przez `DB::afterCommit`. Komunikat `report()` przy błędzie zostaje. Zachowanie w UI jest bez zmian (`tests/Feature/PersonsTest.php:1125` przechodzi bez zmian).

#### 6. Testy

**File**: `tests/Feature/ImportApplyTest.php`, `tests/Feature/PersonsTest.php`

**Intent**: Dowodzi zapisu pamięci i bezpieczeństwa plików przy wycofaniu.

**Contract**:
- Import fixture do pustego drzewa → wpis pamięci dla każdej osoby, urodzenia, zgonu, relacji i ślubu z wartościami R. Drugi użytkownik nie ma żadnego wpisu.
- Dopasowana osoba z konfliktem „z aplikacji” → pamięć ma wartość z repo, a osoba wartość z aplikacji.
- Wycofany zapis (podmieniony `CreateMarriage`, wzór z S-02) → zero wpisów pamięci.
- `DeletePerson` w zewnętrznej transakcji, która się wycofuje → plik zdjęcia nadal na dysku (`Storage::fake('local')`).

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi i da się ją cofnąć: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Testy importu i osób przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Feature/ImportResearchCommandTest.php tests/Feature/PersonsTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 2: Klasyfikacja rekordów i reguła trzech wartości

### Overview

Czyste reguły, na których stoi ponowny import: porównanie R/S/A, wydzielenie bloku notatki i podział każdego klucza na znany, nowy, usunięty w aplikacji, usunięty w repo, usunięty po obu stronach albo nie do oceny. Dopasowanie z S-03 obejmuje tylko nowe osoby. Bez zapisu.

### Changes Required:

#### 1. Reguła trzech wartości

**File**: `app/Support/Import/ThreeWayValue.php`, `app/Enums/ThreeWayOutcome.php`

**Intent**: Jedno miejsce zasady „wartość z repo zastępuje wartość w aplikacji tylko wtedy, gdy nikt jej nie zmienił od ostatniego importu”, testowane jednostkowo na przypadkach granicznych.

**Contract**:
- `ThreeWayOutcome` (string-backed, TitleCase): `Same`, `KeptManual`, `Changed`, `Conflict`, `Fill`.
- `ThreeWayValue::compare(?string $repo, bool $hasLast, ?string $last, ?string $app, bool $refines = false): ThreeWayOutcome` według reguły z Critical Implementation Details:
  - `$refines` mówi, że R doprecyzowuje datę A, a liczy go planer (`refines`, `PlanImport.php:397`);
  - przy `hasLast` doprecyzowanie nie ma znaczenia;
  - opcjonalne `?bool $repoIsApp = null` zastępuje tekstowe R = A; planer podaje je dla dat z porównania obiektów `ResearchDate` (Critical Implementation Details).

#### 2. Blok notatki

**File**: `app/Support/Import/NoteBlock.php`

**Intent**: Blok „Z repo badawczego (O-…):” jest polem. Trzeba go znaleźć w notatce, podmienić albo dopisać, nie ruszając reszty notatki.

**Contract**:
- `NoteBlock::find(?string $note, string $repoId): ?string`: od linii nagłówka `Z repo badawczego ({repoId}):` do pierwszej pustej linii albo końca notatki.
- `replace(?string $note, string $repoId, ?string $block): ?string`:
  - podmienia znaleziony blok;
  - gdy bloku nie ma, dopisuje po `"\n\n"` (pusty `note` → sam blok);
  - `null` jako blok usuwa blok razem z pustą linią przed nim.

#### 3. Id pominiętego wpisu

**File**: `app/Support/Import/ImportIssue.php`, `app/Support/Import/ParsedPersonBlock.php`, `app/Actions/Import/ParsePersonBlock.php`, `app/Actions/Import/ReadResearchRepo.php`, `app/Support/Import/ResearchRepoReading.php`

**Intent**: Klasyfikacja musi odróżnić „plik usunięty z repo” od „plik z błędem w bloku”.

**Contract**:
- `ImportIssue` dostaje opcjonalne `?string $repoId = null`.
- `ParsedPersonBlock::skipped(file, reason, ?repoId)`: `ParsePersonBlock` podaje `id` z bloku, gdy pasuje do `ID_PATTERN`, na każdej ścieżce, na której blok jest już sparsowany: data jako obiekt YAML (`:64-69`, przed walidatorem, więc id trzeba sprawdzić wzorcem osobno) i błąd walidatora (`:73-74`). Niezamknięty frontmatter, błąd YAML i blok niebędący mapą dają `null`. Powtórzone id (`ReadResearchRepo::dropDuplicateIds`) podaje id.
- **Plik bez rozpoznanego bloku** (pierwsza linia nie jest `---`, `ParsePersonBlock.php:38-39`) z ostrzeżeniem `misplacedBlockWarnings` (linia `id: O-…` albo `wersja:`, `:101`): ostrzeżenie dostaje `repoId` z linii `id: O-…`, gdy pasuje do `ID_PATTERN`, inaczej `null`. Taki plik liczy się jak pominięty wpis: jego id idzie do `skippedRepoIds()`, a ostrzeżenie bez id włącza `hasSkippedWithoutId()`. Plik bez bloku i bez ostrzeżenia (zwykła notatka) nie wpływa na klasyfikację. Pliki `_…` i bez `.md` (`ReadResearchRepo.php:88`) nie są skanowane i świadomie liczą się jak usunięte.
- `ResearchRepoReading` dostaje metody `skippedRepoIds(): list<string>` i `hasSkippedWithoutId(): bool`.
- Raport w komendzie bez zmian.

#### 4. `ClassifyRepoRecords`

**File**: `app/Actions/Import/ClassifyRepoRecords.php`, `app/Support/Import/RepoRecordClasses.php`, `app/Support/Import/RepoRecord.php`, `app/Support/Import/RemovalImpact.php`

**Intent**: Jedyne miejsce, które mówi, czym jest każdy klucz względem ostatniego importu. Planer i komenda opierają się na nim bez ponownego liczenia.

**Contract**: `handle(Tree $tree, ResearchRepoReading $reading): RepoRecordClasses`. Czyta wyłącznie przez `$tree`:
- `people()`, `events()`, `parentChildRelations()` z `repo_id`,
- `repoSnapshots()`.

Klucze repo z odczytu: każda osoba, jej `urodzenie` i `zgon` (gdy klucz jest w bloku), relacja (gdy oba końce są w odczycie) i ślub z `$reading->marriages`.

`RepoRecord` (`final readonly`): klucz, rodzaj, `?Model` rekord drzewa, `?RepoSnapshot`, id osób, `?RemovalImpact` (tylko w `removedInRepo`, poza urodzeniem).

`RemovalImpact` mówi, co zniknie przy „Usuń”:
- zdjęcie (bool),
- liczba powiązań ze źródłami (`event_source` wydarzeń, które znikną),
- pochówek (bool) i liczba innych własnych wydarzeń osoby z jednym uczestnikiem, które skasuje `DeletePerson` (`:33-36`); przy samym zgonie: pochówek, który znika razem z nim,
- zamieszkania, relacje i małżeństwa (w tym bez `repo_id`),
- nagrobki (`buried_people`),
- czy osoba jest probantem.

Liczy się przez `$tree`, tak jak usunie je `DeletePerson` albo `->delete()`. Komenda pokazuje je przed pytaniem, a planer przepisuje do `PlannedRemoval`.

`RepoRecordClasses`:
- `knownPersons` (`array<string, Person>`) — osoba z kluczem w repo i rekordem;
- `newPersonIds` (`list<string>`) — w repo, bez rekordu i bez pamięci;
- `deletedInApp` (`list<RepoRecord>`) — w repo, bez rekordu, z pamięcią, do zapytania (bez `deletion_kept` albo R ≠ S);
- `keptDeleted` (`list<string>`) — jak wyżej, ale już rozstrzygnięte;
- `removedInRepo` (`list<RepoRecord>`) — rekord z `repo_id`, klucza nie ma w repo; urodzenie oznaczone do samego odłączenia;
- `goneBoth` (`list<string>`) — pamięć bez rekordu i bez klucza w repo, do zapomnienia;
- `unassessable` (`list<RepoRecord>`) — kandydaci do `removedInRepo`, gdy wpis pominięto bez id;
- grupowanie: rekord, którego osoba sama jest w `removedInRepo`, `deletedInApp` albo `keptDeleted`, zostaje dołączony do tej osoby (`RepoRecord::$members`) zamiast osobnej pozycji. Osoba zostawiona jako usunięta wraca do `deletedInApp` (jedno pytanie o osobę), gdy R ≠ S dla niej albo dla któregokolwiek dołączonego klucza; dołączony klucz nigdy nie jest osobnym pytaniem.

Klucz zawierający id z `skippedRepoIds()` nie trafia do `removedInRepo`, `deletedInApp` ani `goneBoth`.

#### 5. Dopasowanie tylko nowych osób

**File**: `app/Actions/Import/MatchResearchPersons.php`, `app/Console/Commands/ImportResearch.php`

**Intent**: Osoba repo wpisana ręcznie w międzyczasie nie może się zdublować. Osoba już zaimportowana nie jest kandydatem.

**Contract**:
- `handle(Tree $tree, ResearchRepoReading $reading, ?list<string> $repoIds = null): PersonMatches`:
  - klasyfikuje tylko podane osoby repo (`null` = wszystkie);
  - kandydatami są tylko osoby drzewa z `repo_id = null`.
- Blokada (`BLOCKED_REASON`, `:39`) znika z Action, a `PersonMatches::blockedReason` z klasy.
- **Stan przejściowy do Fazy 5:** komenda sama odmawia drzewu z osobą z `repo_id` (ten sam komunikat, `FAILURE`). `PlanImport` i `ApplyImport` zachowują swoje bramki do Faz 3 i 4.

#### 6. Testy

**File**: `tests/Unit/ThreeWayValueTest.php`, `tests/Unit/NoteBlockTest.php`, `tests/Feature/ClassifyRepoRecordsTest.php`, `tests/Feature/ResearchPersonMatchTest.php`, `tests/Feature/ResearchRepoReadTest.php`

**Intent**: Dowodzi reguł bezpośrednio (CLAUDE.md: „Test the planning Action directly”). Każdy test klasyfikacji ma drugiego użytkownika z tymi samymi id repo, rekordami i pamięcią.

**Contract**:
- `ThreeWayValueTest`: każdy przypadek z Critical Implementation Details.
- `NoteBlockTest`:
  - blok w środku notatki z ręcznym tekstem przed i po → podmiana tylko bloku;
  - brak bloku → dopisanie;
  - pusta notatka;
  - blok innej osoby (`O-0043`) nie jest znaleziony;
  - usunięcie bloku.
- `ResearchRepoReadTest`:
  - zła data przy poprawnym id → `ImportIssue::$repoId`;
  - data bez cudzysłowu (obiekt YAML) przy poprawnym id → `ImportIssue::$repoId`, a przy id niezgodnym ze wzorcem → `null`;
  - błąd YAML → `null`;
  - powtórzone id → id;
  - pusta linia przed `---` w pliku z `id: O-0042` → `O-0042` w `skippedRepoIds()`.
- `ClassifyRepoRecordsTest`, przypadki:
  - znana osoba;
  - nowa w repo;
  - zgon usunięty w aplikacji z pamięcią → `deletedInApp`;
  - ten sam z `deletion_kept` i R = S → `keptDeleted`, a z R ≠ S → `deletedInApp`;
  - osoba zostawiona jako usunięta, repo zmieniło tylko datę jej urodzenia → osoba w `deletedInApp` z urodzeniem w `$members`, brak osobnej pozycji urodzenia;
  - osoba bez pliku → `removedInRepo` z dołączonymi wydarzeniami, relacjami i ślubami oraz `RemovalImpact` (zdjęcie, źródło przy urodzeniu, zamieszkanie, ręczna relacja, probant);
  - usunięty w repo tylko `zgon` → osobna pozycja, a `RemovalImpact` pokazuje pochówek;
  - usunięte `urodzenie` → do odłączenia;
  - plik pominięty z id → osoba i jej relacje nie są usunięte;
  - plik osoby z tekstem przed blokiem (`filesWithoutBlock` z ostrzeżeniem i id) → osoba nie jest usunięta;
  - wpis pominięty bez id → wszystko w `unassessable`;
  - pamięć bez rekordu i bez klucza → `goneBoth`;
  - rekordy z `repo_id` bez pamięci (S-02) → znane;
  - nic z drzewa drugiego użytkownika nie trafia do żadnej grupy.
- `ResearchPersonMatchTest`:
  - test blokady (`:376`) zmienia się na: osoba z `repo_id` nie jest kandydatem, a osoba bez `repo_id` o tych samych danych jest;
  - podanie `$repoIds` ogranicza wynik.
- `ImportResearchCommandTest.php:110` (odmowa) przechodzi z odmową przeniesioną do komendy.

### Success Criteria:

#### Automated Verification:

- Testy reguł i klasyfikacji przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/ThreeWayValueTest.php tests/Unit/NoteBlockTest.php tests/Feature/ClassifyRepoRecordsTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/ResearchRepoReadTest.php`
- Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 3: Plan ponownego importu

### Overview

`PlanImport` przyjmuje wszystkie decyzje sprzed planu i buduje plan dla drzewa po imporcie:
- znane osoby z regułą trzech wartości,
- nowe i dopasowane osoby jak w S-03,
- usunięcia, odłączenia i przywrócenia z listą skutków,
- relacje i małżeństwa na wspólnym grafie po usunięciach,
- wpisy pamięci.

Bez zapisu.

### Changes Required:

#### 1. Decyzje i elementy planu

**File**:
- `app/Support/Import/ImportDecisions.php`, `app/Support/Import/PlannedUpdate.php`, `app/Support/Import/PlannedChange.php`,
- `app/Support/Import/PlannedRemoval.php`, `app/Support/Import/PlannedRestoration.php`,
- zmiany w `ValueConflict.php`, `ImportPlan.php`.

**Intent**: Typowany kształt decyzji autora sprzed planu i tego, co zostanie zapisane. Komenda drukuje go bez liczenia, a zapis wykonuje bez ponownych decyzji.

**Contract**:
- `ImportDecisions`:
  - `IdentityDecisions $identity` (obejmuje dokładnie `newPersonIds`);
  - `removals: array<string, 'delete'|'detach'>` dla każdego klucza z `removedInRepo` (urodzenie tylko `detach`);
  - `deletions: array<string, 'restore'|'keep'>` dla każdego klucza z `deletedInApp`;
  - fabryka `forFirstImport(IdentityDecisions)`.
- `PlannedChange`:
  - klucz pytania (jak `ValueConflict`, np. `O-0042/urodzenie/data`, `O-0042/notatka`),
  - osoba, pole, etykieta pola,
  - etykieta wartości w aplikacji, etykieta R, wartość R (`string|Gender|ResearchDate|null`).
- `ValueConflict` dostaje `?string $lastRepoLabel` (poprzednio w repo; `null` bez pamięci). Pole `notatka` jest dozwolone.
- `PlannedUpdate` dla znanej osoby:
  - `ResearchPerson`, id osoby;
  - `list<PlannedChange>`, `list<PlannedFill>`, `list<ValueConflict>`;
  - liczba zachowanych ręcznych zmian;
  - urodzenie i zgon do oznaczenia, utworzenia albo bez zmian (jak `PlannedMatch`).
- `PlannedRemoval`: klucz, rodzaj, id rekordu, decyzja, `RemovalImpact` z klasyfikacji (dla `delete`), klucze dołączone.
- `PlannedRestoration`: klucz, rodzaj, to, co powstanie (osoba jak `PlannedPerson` albo wydarzenie, relacja, ślub z wartości R).
- `ImportPlan` dostaje:
  - `updates`, `changes` (zebrane ze wszystkich znanych osób i ślubów), `removals`, `restorations`;
  - `detachedBirths`, `unassessable` (`list<ImportIssue>`), `keptManual` (liczba);
  - `snapshots`, `forgottenSnapshots`, `keptDeletedSnapshots` (klucz → R przy „Zostaw usunięte”).

  Pole `blockedReason` znika. `conflicts` obejmuje także konflikty znanych osób.

#### 2. `PlanImport`

**File**: `app/Actions/Import/PlanImport.php`

**Intent**: Podejmuje każdą decyzję zapisu przed zgodą, żeby autor zobaczył dokładnie to, co wejdzie, a zapis nie trafił na regułę aplikacji ani nie nadpisał ręcznej zmiany bez pytania.

**Contract**: `handle(Tree $tree, ResearchRepoReading $reading, RepoRecordClasses $classes, ImportDecisions $decisions): ImportPlan`.

Planer nie liczy klasyfikacji ponownie: dostaje tę, na której komenda zadała pytania (testy podają wynik `ClassifyRepoRecords`). Sprawdza, że decyzje pokrywają każdy klucz:
- `identity` = `newPersonIds`,
- `removals` = `removedInRepo`,
- `deletions` = `deletedInApp`.

Inaczej rzuca `InvalidArgumentException`.

Reguły:
- **Znane osoby:**
  - pola `imiona`, `nazwisko`, `plec`, `notatka` (`NoteBlock::find` w notatce osoby) oraz daty urodzenia i zgonu przez `ThreeWayValue`;
  - `Changed` → `PlannedChange`, `Conflict` → `ValueConflict` z `lastRepoLabel`, `Fill` → `PlannedFill`, `KeptManual` → licznik;
  - zmiana i konflikt płci przechodzą przez regułę `sameSexOffender` jak w S-03, a naruszenie idzie do `skippedValues`;
  - wydarzenie bez `repo_id` u znanej osoby: gdy repo ma klucz, to oznaczenie z porównaniem bez S (S-03); gdy brak wydarzenia, utworzenie.
- **Nowe osoby:** tożsamość jak w S-03 (`PlannedPerson`, `PlannedMatch`, pominięte).
- **Usunięte w repo:**
  - `delete` → `PlannedRemoval` z `RemovalImpact` z klasyfikacji;
  - `detach` → `PlannedRemoval` bez skutków;
  - urodzenie → `detachedBirths`.

  Klucze dołączone idą za osobą.
- **Usunięte w aplikacji:**
  - `restore` → `PlannedRestoration` (osoba przywrócona to nowa osoba z notatką jak w S-02, a jej relacje i małżeństwa planowane jak nowe);
  - `keep` → `keptDeletedSnapshots`.
- **Wspólny graf relacji** jak w S-03 (`sharedGraph`, `planRelations`), z dwiema różnicami:
  - krawędzie usuwane (`delete`) nie wchodzą do grafu, a osoby usuwane nie są węzłami;
  - relacja z kluczem w `keptDeleted` albo z decyzją `keep` nie jest planowana;
  - osoba z `keptDeleted` albo z decyzją `keep` nie jest węzłem i liczy się jak pominięta (`$skippedIds`): każda jej relacja i ślub z repo, także nowe, są pomijane z powodem „osoba usunięta w aplikacji”, jak pominięcie przy dopasowaniu w S-03 (`PlanFirstImport.php:494-499`, `:649-655`).

  Znana relacja z `repo_id` zostaje bez zmian i trafia do `snapshots`.
- **Małżeństwa:**
  - znany ślub → data przez `ThreeWayValue`;
  - nowy ślub między osobami, które już mają ślub w drzewie bez `repo_id` → oznaczenie jak w S-03;
  - pozostałe nowe.
- **Pamięć:** reguły z Critical Implementation Details. `forgottenSnapshots` = klucze odłączane, usuwane (z dołączonymi) i `goneBoth`.
- **Nie do oceny:** każdy `unassessable` jako `ImportIssue` („nie do oceny: popraw pominięte wpisy repo”).
- **Granica drzewa:** każde zapytanie przez `$tree` (FR-016).

#### 3. Testy planowania

**File**: `tests/Feature/ImportPlanTest.php`

**Intent**: Dowodzi reguł planu bezpośrednio przez Action. Każdy test ma drugiego użytkownika z tymi samymi id repo, rekordami i pamięcią, a nic z jego drzewa nie trafia do planu.

**Contract**:
- Istniejące testy przekazują wynik `ClassifyRepoRecords` i `ImportDecisions::forFirstImport(...)`. Test blokady (`:140`) znika: drzewo z osobą z `repo_id` planuje się bez blokady.
- Nowy helper: import repo tymczasowego przez `PlanImport` i `ApplyImport`, potem drugie repo tymczasowe ze zmianami.

Przypadki:
- repo bez zmian, aplikacja bez zmian → plan bez zmian, konfliktów, usunięć („nic do zapisania”);
- data urodzenia zmieniona w repo, nietknięta w aplikacji → `PlannedChange`;
- zmieniona w obu → `ValueConflict` z `lastRepoLabel`;
- zmieniona tylko w aplikacji → brak pozycji, `keptManual` = 1;
- rekord z S-02 bez pamięci z inną datą → konflikt bez `lastRepoLabel`;
- blok notatki:
  - nietknięty, a repo zmieniło miejsce urodzenia → `PlannedChange` pola `notatka`;
  - z ręcznym dopiskiem w bloku → konflikt;
  - ręczny tekst poza blokiem → zmiana;
- płeć zmieniona w repo, która dałaby dwoje rodziców tej samej płci → `skippedValues`, brak zmiany i brak nowej pamięci płci;
- repo dodało `zgon`, a w aplikacji jest zgon bez `repo_id` → oznaczenie z uzupełnieniem albo konfliktem daty; bez zgonu → utworzenie;
- repo zmieniło ojca (O-1 → O-3), decyzja `delete` starej relacji → nowa relacja planowana, a przy `detach` → nowa pominięta z powodem „w drzewie inny ojciec”;
- osoba usunięta w repo, `delete` → `PlannedRemoval` ze skutkami z klasyfikacji; dołączone klucze w `forgottenSnapshots`;
- zgon usunięty w aplikacji, `restore` → `PlannedRestoration`, a `keep` → `keptDeletedSnapshots` z R;
- relacja w `keptDeleted` → nieplanowana;
- nowa w repo relacja i ślub z osobą zostawioną jako usunięta → pominięte z powodem „osoba usunięta w aplikacji”, bez wpisu pamięci;
- `unassessable` → `ImportIssue`, żadnego `PlannedRemoval`;
- nowa osoba repo dopasowana do ręcznej osoby → `PlannedMatch` jak w S-03;
- decyzje bez jednego klucza z `removedInRepo` albo `deletedInApp` → wyjątek.

### Success Criteria:

#### Automated Verification:

- Testy planowania przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ClassifyRepoRecordsTest.php`
- Testy odczytu i dopasowania przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ResearchPersonMatchTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 4: Zapis ponownego importu

### Overview

`ApplyImport` zapisuje plan z zatwierdzeniami i rozstrzygnięciami w jednej transakcji. Pod blokadą sprawdza, że wszystko, co widział plan, nadal tak wygląda. Usuwa i odłącza przed tworzeniem, a pamięć zapisuje na końcu.

### Changes Required:

#### 1. Zatwierdzenia i wynik

**File**: `app/Support/Import/ChangeApprovals.php`, `app/Support/Import/ImportResult.php`

**Intent**: Odpowiedzi autora na zmiany z repo i liczby zapisu do podsumowania.

**Contract**:
- `ChangeApprovals`: `rejected: list<string>` (klucze zmian odrzuconych); brak klucza = zatwierdzona, co odpowiada zbiorczemu „Zatwierdź wszystkie”.
- `ImportResult` dostaje liczby: zmiany wzięte z repo, zmiany odrzucone, usunięte osoby i rekordy, odłączone, przywrócone, zachowane jako usunięte.

#### 2. `ApplyImport`

**File**: `app/Actions/Import/ApplyImport.php`

**Intent**: Jedyne miejsce zapisu importu. Wszystko albo nic, tylko w drzewie konta. Żadna ręczna zmiana nie znika bez zatwierdzenia albo rozstrzygnięcia z planu.

**Contract**: `handle(Tree $tree, ImportPlan $plan, ChangeApprovals $approvals, ConflictResolutions $resolutions): ImportResult`. Bramka „drzewo ma osoby z importu” (`:57`) znika. W `DB::transaction`:
1. **Blokady i sprawdzenia.** Blokada drzewa, potem blokada w kolejności id osób znanych, dopasowanych i usuwanych. Następnie sprawdzenia, każde z `RuntimeException` („… zmieniła się po planowaniu; nic nie zostało zapisane.”):
   - każda istnieje w `$tree` i ma `repo_id` z planu (dopasowane: `null`);
   - każde pole zmiany, uzupełnienia i konfliktu rozstrzygniętego „z repo” ma nadal wartość z planu (`guardPerson` i `guardWedding` rozszerzone o `notatka`);
   - rekordy usuwane i odłączane istnieją z `repo_id` z planu;
   - rekordy przywracane nadal nie istnieją (klucz wolny);
   - wpisy pamięci, które plan widział, nie zmieniły się (`updated_at`).
2. **Usunięcia i odłączenia:**
   - osoba przez `DeletePerson`;
   - zgon przez `$event->delete()` razem z pochówkiem osoby (`$person->burial()?->delete()`), tak jak `UpdatePerson.php:76-77`;
   - relacja i ślub przez `->delete()`;
   - odłączenie = `repo_id = null` (także klucze dołączone i `detachedBirths`).
3. **Nowe, dopasowane i przywrócone osoby** jak w S-03 (`createEvent`, `writeMatch`).
4. **Znane osoby:**
   - zatwierdzone zmiany, uzupełnienia i konflikty „z repo”;
   - imiona, nazwisko, płeć przez `update` z regułą płci rodzica (`GuardsParentSexChange`);
   - daty przez `markEvent` (model, `date_is_approximate` podąża);
   - notatka przez `NoteBlock::replace`;
   - tworzenie i oznaczanie urodzenia i zgonu.
5. **Relacje i małżeństwa** jak w S-03: oznaczenie albo `CreateParentChildRelation` / `CreateMarriage` + `repo_id`. Przywrócone tak samo.
6. **Pamięć:**
   - `upsert` `snapshots` (`deletion_kept = false`),
   - `upsert` `keptDeletedSnapshots` (`deletion_kept = true`),
   - usunięcie `forgottenSnapshots`.

Wyjątek z dowolnego kroku cofa całą transakcję. Pliki zdjęć usuniętych osób znikają dopiero po zatwierdzeniu (Faza 1).

#### 3. Testy zapisu

**File**: `tests/Feature/ImportApplyTest.php`

**Intent**: Dowodzi zachowania ręcznych zmian, usuwania z listą skutków, granicy drzewa i atomowości. Komenda omija Policy, więc to dowód FR-014 i FR-016.

**Contract**:
- Testy blokady (`:199`, `:225`, `:560`) zmieniają się:
  - plan z drzewem po imporcie zapisuje się;
  - osoba z `repo_id` dodana po planowaniu z kluczem, który plan chciał utworzyć → wyjątek i zero zmian.
- Wszystkie przypadki sprawdzają `rowDiff` / `ownershipSnapshot`: żaden wiersz drugiego użytkownika (z tymi samymi id repo i pamięcią) się nie zmienia.

Przypadki:
- zatwierdzona zmiana daty → nowa data, `date_is_approximate` zgodne z `date_qualifier`, pamięć = R;
- odrzucona zmiana → data z aplikacji, pamięć = R;
- konflikt „z aplikacji” → bez zmian, pamięć = R; „z repo” → R;
- zmiana bloku notatki → ręczny tekst przed i po bloku bez zmian;
- usunięcie osoby → osoba, jej urodzenie, zgon, małżeństwa i relacje zniknęły, źródła zostały, `root_person_id` = `null`, wpisy pamięci zapomniane, plik zdjęcia skasowany po zatwierdzeniu;
- odłączenie osoby → osoba z `repo_id = null`, jej wydarzenia i relacje z kluczami też, a zdjęcie, zamieszkanie i `event_source` bez zmian;
- usunięte `urodzenie` → urodzenie zostaje z `repo_id = null`;
- usunięty w repo `zgon` z decyzją `delete` → zgon i pochówek osoby zniknęły, osoba i jej urodzenie zostały;
- przywrócenie zgonu → nowy zgon z `repo_id` i datą R, `deletion_kept = false`; „zostaw usunięte” → brak zgonu, `deletion_kept = true`;
- zmiana ojca z usunięciem starej relacji → stara usunięta, nowa utworzona w tej samej transakcji;
- ręczna zmiana daty po planowaniu → wyjątek i zero zmian, także w usunięciach;
- błąd w środku zapisu (podmieniony `CreateMarriage`) po usunięciu osoby → osoba, jej relacje i plik zdjęcia na miejscu, pamięć bez zmian.

### Success Criteria:

#### Automated Verification:

- Testy zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportApplyTest.php`
- Testy osób i relacji przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 5: Komenda — podgląd zmian w terminalu

### Overview

`import:research` prowadzi autora przez nowe osoby, usunięte w repo, usunięte w aplikacji, zmiany i konflikty w jednym przebiegu, a potem pyta o zgodę i zapisuje. Odmowa ponownego importu znika.

### Changes Required:

#### 1. Komenda

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Dziesiątki decyzji w jednym przebiegu, zbiorczo tam, gdzie nikt nie zmieniał wartości ręcznie, pojedynczo z obiema wartościami tam, gdzie zmieniał. Nic nie zapisuje się bez ostatniej zgody.

**Contract**: sygnatura bez zmian. Przebieg po raporcie odczytu:
1. `ClassifyRepoRecords`. Sekcja „Stan względem ostatniego importu”: znane osoby, nowe w repo, usunięte w repo, usunięte w aplikacji, nie do oceny. Odmowa ze stanu przejściowego (Faza 2) znika.
2. `MatchResearchPersons` dla `newPersonIds` i decyzje o tożsamości jak w S-03 (`decideIdentities` bez zmian).
3. **Usunięte w repo**, w kolejności kluczy, bez urodzeń: urodzenie nie dostaje pytania ani nie liczy się do `{N}`, a komenda sama wpisuje mu `detach` do `ImportDecisions::$removals`. Dla pozostałych:
   - `$this->table` ze skutkami (`RepoRecord::$impact` z klasyfikacji) i kluczami dołączonymi;
   - `select("Usunięte w repo {k}/{N}: {opis} ({klucz}) — co zrobić?", ['detach' => 'Zostaw — odłącz od repo', 'delete' => 'Usuń z aplikacji'], default: 'detach')`.
4. **Usunięte w aplikacji:** `select("Usunięte w aplikacji {k}/{N}: {opis} ({klucz}) — co zrobić?", ['keep' => 'Zostaw usunięte', 'restore' => 'Przywróć z repo'], default: 'keep')`, z wartościami R w linii nad pytaniem.
5. `PlanImport` (wyjątek `InvalidArgumentException` → `error`, `FAILURE`) → podsumowanie S-03 rozszerzone o:
   - linie „Zmiany z repo: N”, „Zachowane ręczne zmiany: N”, „Usunięcia: N, odłączenia: N, przywrócenia: N”;
   - tabele „Odłączone urodzenia” i „Nie do oceny”.
6. **Zmiany z repo:**
   - `$this->table(['Osoba', 'Pole', 'W aplikacji', 'Teraz w repo'], …)`, a blok notatki skrócony do pierwszej różniącej się linii;
   - `select("Zmiany z repo ({N}): co zrobić?", ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'], default: 'all')`;
   - przy `each`: `select("Zmiana {k}/{N}: {osoba} — {pole}", ['repo' => 'Weź z repo: …', 'app' => 'Zostaw: …'], default: 'repo')`.
7. **Konflikty** jak w S-03 (`resolveConflicts`). Przed pytaniem linia „poprzednio w repo: …”, gdy jest `lastRepoLabel`. Konflikt `notatka` drukuje przed pytaniem oba bloki pełnym tekstem.
8. Nic do zapisania → „Nic do zapisania.”, `SUCCESS`. „Nic” oznacza jednocześnie:
   - żadnych nowych, dopasowanych, zmian, uzupełnień, rozstrzygniętych konfliktów, usunięć, odłączeń, przywróceń, nowych relacji ani ślubów;
   - każdy wpis `snapshots` i `keptDeletedSnapshots` jest równy zapisanej pamięci (te same `values` i ta sama flaga `deletion_kept`; porównanie z `RepoRecord::$snapshot` z klasyfikacji);
   - puste `forgottenSnapshots`.

   Sama różnica w pamięci (np. „Zostaw usunięte” jako jedyna decyzja, rekordy z S-02/S-03 bez pamięci, wartość R zbieżna z A) jest zapisem: idzie przez zgodę i kopię jak każdy inny.
9. `$this->confirm("Zapisać do drzewa konta {email}: nowe osoby {N}, dopasowane {M}, zmiany {Z}, usunięcia {U}, pamięć importu {P}?", false)`, gdzie `{P}` to liczba wpisów pamięci do zapisania lub usunięcia.
10. Kopia na produkcji, `ApplyImport`, podsumowanie zapisu z nowymi liczbami. Linia o probancie pojawia się, gdy `root_person_id` jest `null`, także po usunięciu probanta.
- **Tryb nieinteraktywny:** gdy którekolwiek pytanie z kroków 2–7 byłoby zadane, komenda drukuje „Import wymaga odpowiedzi w terminalu; nic nie zostało zapisane.” i kończy się `FAILURE`. Sprawdzenie zmian i konfliktów przychodzi po planie, przed pierwszym pytaniem.

Etykiety opcji mieszczą się w `OPTION_WIDTH` (74), a szczegóły są w tabelach.

#### 2. Testy komendy

**File**: `tests/Feature/ImportResearchCommandTest.php`

**Intent**: Dowodzi przebiegu przez `$this->artisan(...)` z `expectsChoice` (klucz opcji) i `expectsConfirmation`.

**Contract**:
- Test odmowy (`:110`) zmienia się w test ponownego importu.
- Istniejące testy oczekują nowej treści pytania o zgodę.
- Nowy helper: autor po imporcie repo tymczasowego (przez komendę albo `PlanImport` + `ApplyImport`) i drugie repo tymczasowe ze zmianami. Drugi użytkownik ma ten sam import i te same zmiany w aplikacji.

Przypadki:
- repo i aplikacja bez zmian → „Nic do zapisania.”, brak pytań, brak zmian w obu drzewach;
- zmiana daty w repo + `all` + „yes” → nowa data w drzewie autora, cudze bez zmian;
- `each` z odrzuceniem jednej zmiany → ta wartość bez zmian, druga zmieniona;
- ręczna zmiana daty w aplikacji i inna w repo → pytanie „Konflikt 1/1: …” i linia „poprzednio w repo”; „app” → bez zmian;
- osoba usunięta w repo:
  - tabela skutków w wyjściu;
  - `delete` + „yes” → osoby nie ma;
  - `detach` → osoba bez `repo_id`;
  - drugi przebieg nie pyta o nią ponownie;
- zgon usunięty w aplikacji: `keep` (jedyna decyzja w przebiegu) + „yes” → drugi przebieg nie pyta; `restore` → zgon wraca;
- drzewo po imporcie bez pamięci (rekordy z `repo_id`, brak `repo_snapshots`), repo i aplikacja bez zmian → pytanie o zgodę z `pamięć importu {P}`; po „yes” drugi przebieg kończy się „Nic do zapisania.”;
- nowa osoba w repo z imiennikiem wpisanym ręcznie → pytanie o tożsamość jak w S-03;
- wpis pominięty bez id → tabela „Nie do oceny”, brak pytań o usunięcie;
- wejście nieinteraktywne przy zmianach (`Artisan::call` z `--no-interaction`) → `FAILURE`, brak zapisu;
- produkcja z błędną kopią po zgodzie (`fakeBackupRun`) → nic nie zapisane.

### Success Criteria:

#### Automated Verification:

- Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- Wszystkie testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/ClassifyRepoRecordsTest.php tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Unit/ResearchDateTest.php tests/Unit/NameKeyTest.php tests/Unit/YearSpanTest.php tests/Unit/ThreeWayValueTest.php tests/Unit/NoteBlockTest.php`
- Pełny zestaw testów przechodzi: `vendor/bin/sail artisan test --compact`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Przebieg na kopii `test-pack` po pierwszym imporcie, z ręczną zmianą jednej daty w aplikacji i zmianą tej oraz innej daty w kopii repo (odpowiedź „nie” na końcu): tabele zmian, konfliktów i skutków są czytelne w terminalu, etykiety się nie ucinają, a podział na zmiany i konflikty zgadza się z oceną autora
- Ten sam przebieg z odpowiedzią „tak” po lokalnej kopii bazy: zmieniona w aplikacji wartość została tam, gdzie autor tak wybrał; osoba usunięta z kopii repo została odłączona albo usunięta zgodnie z odpowiedzią; bazę potem przywrócono z kopii
- Drugi przebieg na tej samej kopii repo kończy się „Nic do zapisania.” bez pytań

**Implementation Note**: Po fazie i zielonych testach automatycznych poprosić autora o ręczny przebieg. Autor kopiuje `storage/gen-tree-import/test-pack/` do `storage/gen-tree-import/test-pack-v2/` i sam zmienia w kopii daty, usuwa plik jednej osoby i dopisuje `zgon`. Aplikacja nie pisze w repo badawczym ani w jego kopii. Przed przebiegiem: `vendor/bin/sail artisan backup:run --only-db`. Po nim: `vendor/bin/sail artisan backup:restore`.

---

## Testing Strategy

### Unit Tests:

- `ThreeWayValue`: każdy przypadek graniczny z Critical Implementation Details (z S i bez S, wyczyszczenie, doprecyzowanie daty).
- `NoteBlock`:
  - znalezienie, podmiana, dopisanie i usunięcie bloku;
  - ręczny tekst przed i po bloku nietknięty;
  - blok innej osoby ignorowany.

### Integration Tests:

- **Odczyt:** id pominiętego wpisu (zła data, YAML, powtórzone id).
- **Klasyfikacja (Action):**
  - znane, nowe, usunięte w aplikacji (z flagą i bez), usunięte w repo (osoba z dołączonymi, sam zgon, urodzenie);
  - po obu stronach, nie do oceny, pominięty plik z id.
- **Dopasowanie (Action):** tylko nowe osoby, kandydaci bez `repo_id`.
- **Plan (Action):**
  - zmiany, konflikty z poprzednią wartością, zachowane ręczne zmiany, rekordy bez pamięci;
  - blok notatki, płeć na grafie;
  - zmiana rodzica z usunięciem i odłączeniem starej relacji;
  - skutki usunięcia, przywrócenia;
  - walidacja decyzji.
- **Zapis (Action):**
  - zatwierdzone i odrzucone zmiany, rozstrzygnięcia, pamięć = R;
  - usunięcie osoby z kaskadą i zachowaniem źródeł;
  - odłączenie, przywrócenie, „zostaw usunięte”;
  - wyścig po planowaniu, wycofanie z plikiem zdjęcia na miejscu.
- **Komenda (`$this->artisan`):**
  - bez zmian → nic;
  - zmiany zbiorczo i pojedynczo, konflikt;
  - usunięte w repo (usuń, odłącz, brak ponownego pytania);
  - usunięte w aplikacji (zostaw, przywróć);
  - nowa osoba z imiennikiem, nie do oceny;
  - tryb nieinteraktywny, kopia na produkcji.
- Każdy test importu ma drugiego użytkownika z tymi samymi id repo, rekordami, pamięcią i zmianami w aplikacji (CLAUDE.md), a test dowodzi, że nic z jego drzewa nie zostało dopasowane, zaplanowane ani zapisane.

### Manual Testing Steps:

1. `vendor/bin/sail artisan backup:run --only-db` (kopia bazy dev).
2. Pierwszy import `test-pack` do konta dev, jeśli jeszcze go nie ma: `vendor/bin/sail artisan import:research <e-mail konta dev> /var/www/html/storage/gen-tree-import/test-pack`.
3. W aplikacji zmień datę urodzenia jednej zaimportowanej osoby i usuń zgon innej.
4. Skopiuj `test-pack` do `test-pack-v2`. W kopii zmień tę samą datę (konflikt) i inną datę (zmiana), usuń plik jednej osoby i dopisz `zgon` innej.
5. `vendor/bin/sail artisan import:research <e-mail> /var/www/html/storage/gen-tree-import/test-pack-v2`: przejdź usunięte w repo, usunięte w aplikacji, zmiany i konflikty, odpowiedz „nie”, sprawdź, że nic nie zapisano.
6. Powtórz z „tak”. W aplikacji sprawdź zmienioną datę, zachowaną ręczną zmianę, usuniętą albo odłączoną osobę i nowy zgon.
7. Uruchom jeszcze raz na `test-pack-v2`: „Nic do zapisania.”.
8. Przywróć bazę z kopii z kroku 1 (`vendor/bin/sail artisan backup:restore`) i usuń `test-pack-v2`.

## Performance Considerations

Klasyfikacja czyta raz rekordy drzewa z `repo_id` i pamięć (kilka zapytań). Planer dokłada do ładowania z S-03 wydarzenia znanych osób. Liczenie skutków usunięcia to kilka zapytań na usuwaną osobę, a takich osób jest najwyżej kilka. Zapis to setki małych zapytań w jednej transakcji, rzadko i ręcznie. Nie optymalizujemy.

## Migration Notes

- Jedna nowa tabela (`repo_snapshots`), bez przenoszenia danych i bez `MigrationTest`. `down()` ją usuwa.
- Na produkcji `scripts/deploy.sh` robi kopię bazy przed `migrate` (bez zmian).
- Rekordy z S-02/S-03 bez pamięci są traktowane bezpiecznie (każda różnica to konflikt). Pierwszy ponowny import zapisuje ich pamięć.
- Cofnięcie importu to kopia bazy sprzed zapisu (PRD Non-Goals). Na produkcji powstaje automatycznie po zgodzie.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-04, uwaga z przeglądu S-02 F3; Open Roadmap Questions 1)
- PRD: `context/foundation/prd.md` (US-02, FR-005, FR-006, FR-008, FR-014, FR-015, FR-016; Business Logic Changes — pierwszeństwo wartości, zniknięcie z repo)
- Kontrakt bloku: `context/foundation/research-block-contract.md`
- Poprzednie wycinki: `context/archive/2026-10-24-first-import-matches-hand-entered/plan.md` (podgląd w terminalu, reguły S-03), `reviews/impl-review.md` (F1: sprawdzenie pod blokadą), `follow-ups/review-fixes.md`; `context/archive/2026-10-23-first-import-into-empty-tree/reviews/impl-review.md` (F3: urodzenie z `repo_id = null`)
- Kod:
  - `app/Actions/Import/PlanFirstImport.php:314` (porównanie), `:466` (graf), `:811` (blok notatki);
  - `app/Actions/Import/ApplyFirstImport.php:326` (`guardPerson`), `:368` (`markEvent`);
  - `app/Actions/Persons/DeletePerson.php:24`; `app/Actions/Persons/UpdatePerson.php:126` (`putEvent`).
- Testy i helpery:
  - `tests/Feature/FirstImportPlanTest.php:47`, `tests/Feature/ImportResearchCommandTest.php:55`, `:634`;
  - `tests/Pest.php:58-106`, `:148`, `:227`.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Pamięć ostatniego importu

#### Automated

- [x] 1.1 Migracja przechodzi i da się ją cofnąć: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate` — 2c12c0a
- [x] 1.2 Testy importu i osób przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Feature/ImportResearchCommandTest.php tests/Feature/PersonsTest.php` — 2c12c0a
- [x] 1.3 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check` — 2c12c0a
- [x] 1.4 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent` — 2c12c0a

### Phase 2: Klasyfikacja rekordów i reguła trzech wartości

#### Automated

- [x] 2.1 Testy reguł i klasyfikacji przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/ThreeWayValueTest.php tests/Unit/NoteBlockTest.php tests/Feature/ClassifyRepoRecordsTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/ResearchRepoReadTest.php` — fc390e3
- [x] 2.2 Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php` — fc390e3
- [x] 2.3 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check` — fc390e3
- [x] 2.4 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent` — fc390e3

### Phase 3: Plan ponownego importu

#### Automated

- [x] 3.1 Testy planowania przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ClassifyRepoRecordsTest.php`
- [x] 3.2 Testy odczytu i dopasowania przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ResearchPersonMatchTest.php`
- [x] 3.3 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [x] 3.4 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

### Phase 4: Zapis ponownego importu

#### Automated

- [ ] 4.1 Testy zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportApplyTest.php`
- [ ] 4.2 Testy osób i relacji przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php`
- [ ] 4.3 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [ ] 4.4 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

### Phase 5: Komenda — podgląd zmian w terminalu

#### Automated

- [ ] 5.1 Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- [ ] 5.2 Wszystkie testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/ClassifyRepoRecordsTest.php tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Unit/ResearchDateTest.php tests/Unit/NameKeyTest.php tests/Unit/YearSpanTest.php tests/Unit/ThreeWayValueTest.php tests/Unit/NoteBlockTest.php`
- [ ] 5.3 Pełny zestaw testów przechodzi: `vendor/bin/sail artisan test --compact`
- [ ] 5.4 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [ ] 5.5 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual

- [ ] 5.6 Przebieg na kopii `test-pack` po pierwszym imporcie, z ręczną zmianą jednej daty w aplikacji i zmianą tej oraz innej daty w kopii repo (odpowiedź „nie” na końcu): tabele zmian, konfliktów i skutków są czytelne w terminalu, etykiety się nie ucinają, a podział na zmiany i konflikty zgadza się z oceną autora
- [ ] 5.7 Ten sam przebieg z odpowiedzią „tak” po lokalnej kopii bazy: zmieniona w aplikacji wartość została tam, gdzie autor tak wybrał; osoba usunięta z kopii repo została odłączona albo usunięta zgodnie z odpowiedzią; bazę potem przywrócono z kopii
- [ ] 5.8 Drugi przebieg na tej samej kopii repo kończy się „Nic do zapisania.” bez pytań
