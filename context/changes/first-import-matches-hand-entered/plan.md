# Pierwszy import dopasowuje osoby wpisane ręcznie — plan implementacji

## Overview

Rozszerzamy `import:research` z S-02 tak, żeby pierwszy import działał także na drzewie z ręcznie wpisanymi osobami. Komenda dla każdej osoby z repo szuka kandydata w drzewie konta. Osoba pewna (jeden kandydat, co najmniej dwa zgodne niezależne elementy poza imieniem i nazwiskiem, brak sprzeczności) idzie do zbiorczego zatwierdzenia. Osoba wątpliwa idzie do autora pojedynczo („to ta sama”, „inna — dodaj jako nową”, „pomiń”). Dopasowana osoba dostaje `repo_id`. Puste pola uzupełniają się z repo, a różne wartości to konflikty do rozstrzygnięcia. Brakujące relacje i małżeństwa powstają, a sprzeczne są pomijane z raportem. Zdjęcie, skany, notatka, zamieszkania i inne dane z aplikacji zostają.

To roadmapa S-03 i odpowiedź na PRD Open Questions 4 (podgląd w terminalu przy dziesiątkach decyzji). Ponowny import (drzewo z rekordami z `repo_id`) pozostaje w S-04.

## Current State Analysis

**Import po S-02 (`context/archive/2026-10-23-first-import-into-empty-tree/`):**
- `app/Console/Commands/ImportResearch.php:26` przechodzi przez kroki: raport odczytu, `PlanFirstImport`, podsumowanie, `$this->confirm()`, kopia bazy na produkcji (`:81`), `ApplyFirstImport` i podsumowanie zapisu. Wyjątek zapisu idzie do `report()` (`:91`).
- `PlanFirstImport` (`app/Actions/Import/PlanFirstImport.php:25`) blokuje każde drzewo z osobami (`:20`, `:93`). Wycina relacje z cykli (Tarjan, `:172`) i ze sprzeczną płcią, ale tylko na grafie repo. Układa notatkę „Z repo badawczego (O-…)” (`:263`).
- `ApplyFirstImport` (`app/Actions/Import/ApplyFirstImport.php:34`) zapisuje w jednej transakcji: najpierw blokuje drzewo i ponownie sprawdza pustość (`:41-45`), potem tworzy osoby, urodzenia i zgony (`createEvent`, `:106`), a relacje i małżeństwa przez `CreateParentChildRelation` / `CreateMarriage`, z `repo_id` ustawianym po fakcie (`:80`, `:92`).
- `repo_id` na `people`, `events` i `parent_child_relations` ma unikalny indeks `(tree_id, repo_id)` (`database/migrations/2026_10_24_053038_add_repo_id_to_people_events_and_parent_child_relations.php:25`). Klucze: `O-0042`, `O-0042/urodzenie`, `O-0042/zgon`, `O-0084>O-0042`, `O-0001+O-0004/slub`.
- Komenda używa Symfony (`$this->confirm`, `$this->table`). Laravel Prompts (0.3.24) nie są jeszcze nigdzie używane.

**Osoba wpisana ręcznie (dev: 110 osób, 1 drzewo, 0 z `repo_id`):**
- `people`:
  - `first_name` to wszystkie imiona w jednym polu, `last_name` to nazwisko rodowe;
  - `gender`, `religion`, `note`, `photo_path`.
  
  Nie ma pola na inne nazwiska. Część kobiet może mieć nazwisko po mężu wpisane jako rodowe, a 7 osób ma linię „Nazwisko po mężu” w notatce (`context/archive/2026-10-21-single-birth-surname/plan-brief.md`).
- Każda osoba z UI ma wydarzenie urodzenia, także puste (`app/Actions/Persons/CreatePerson.php:56`). 75/110 ma rok, 60/110 miejscowość. Zgon jest tylko przy „zmarł”, a 24/110 ma rok zgonu.
- Miejsce w aplikacji to wyłącznie `events.locality_id` → `localities.name`, unikalne w drzewie bez wielkości liter. W repo miejsce jest wolnym tekstem.
- Relacje mają ograniczenia: `parent_child_relations` z unikalną parą `(parent_id, child_id)`, a `CreateParentChildRelation` (`app/Actions/Relations/CreateParentChildRelation.php:20`) odrzuca duplikat, trzeciego rodzica, cykl i dwoje rodziców tej samej znanej płci (`:62`). Małżeństwo to wydarzenie `marriage` z dwoma `spouse`, a `CreateMarriage` (`app/Actions/Relations/CreateMarriage.php:26`) odrzuca powtórzoną parę.
- `UpdatePerson` (`app/Actions/Persons/UpdatePerson.php:50`) zastępuje wszystko, co dostanie. Kasuje zgon i pochowanie bez `is_deceased` i zastępuje zamieszkania, więc nie nadaje się do zapisu dopasowania. Jego reguła płci rodzica (`guardParentSexChange`, `:130`) jest wzorem dla zmiany płci z importu.
- Do osoby i jej wydarzeń wiszą:
  - `event_participants`,
  - `event_source` (z numerem wpisu),
  - `residences`,
  - `buried_people`,
  - `trees.root_person_id`.

  Zapis dopasowania nie usuwa żadnego z tych wierszy.
- `Event::booted()` (`app/Models/Event.php:47`) wylicza `date_is_approximate` z `date_qualifier` przy zapisie modelu. Daty zmieniamy więc przez model, nie zapytaniem zbiorczym (CLAUDE.md).
- Nazwy nigdzie nie są sprowadzane do jednej formy pisowni. Spis miejsc porównuje tylko `lower(name)`. `Str::ascii` (voku/portable-ascii, zależność `laravel/framework`) jest dostępne na produkcji.

**Skala (szacunek z prozy repo, bo prawdziwe repo nie ma jeszcze bloków):**
- ok. 51 z 280 osób repo ma imiennika w drzewie dev, w tym 11 kilku;
- rok urodzenia ±2 i imię ojca zgadzają się razem w ok. 19 przypadkach;
- przypadków wątpliwych i konfliktów będą dziesiątki.

Bloki ma dziś tylko pilotażowy `storage/gen-tree-import/test-pack/` (7 plików).

## Desired End State

`vendor/bin/sail artisan import:research <e-mail> <repo>` na drzewie z ręcznymi osobami i bez rekordów z `repo_id`:
1. Drukuje raport odczytu jak w S-01.
2. Drukuje sekcję „Dopasowanie do osób w drzewie” z liczbami: nowe (bez kandydata), pewne, wątpliwe.
3. Pokazuje tabelę dopasowań pewnych i pyta: „zatwierdź wszystkie” albo „przejrzyj pojedynczo”.
4. Przeprowadza przez każdy przypadek wątpliwy (oraz pewny przy przeglądzie pojedynczym) jako „Wątpliwe k/N”. Przy każdym pokazuje tabelę porównania repo ↔ kandydaci i pytanie z opcjami „To <kandydat>”, „Inna osoba — dodaj jako nową” i „Pomiń — nie importuj teraz” (domyślne).
5. Drukuje podsumowanie importu z S-02, rozszerzone o:
   - dopasowane, nowe i pominięte osoby,
   - uzupełnienia pustych pól,
   - pominięte relacje i małżeństwa z powodem.
6. Przeprowadza przez każdy konflikt wartości jako „Konflikt k/N” z wyborem „Zostaw z aplikacji” (domyślne) albo „Weź z repo”.
7. Pyta o zgodę (domyślnie „nie”). Na produkcji robi kopię bazy, a potem w jednej transakcji:
   - nadaje `repo_id` dopasowanym osobom i ich wydarzeniom,
   - stosuje uzupełnienia i rozstrzygnięcia,
   - tworzy nowe osoby, wydarzenia, relacje i małżeństwa,
   - oznacza istniejące relacje i małżeństwa,
   - dopisuje blok „Z repo badawczego” pod notatką dopasowanych.
8. Przerwanie w dowolnym momencie przed zgodą niczego nie zapisuje.

Puste drzewo działa jak w S-02, bo nie ma kandydatów. Drzewo z osobą z `repo_id` to odmowa bez pytań i bez zapisu („ponowny import — S-04”). Cudze drzewa są nietknięte.

Weryfikacja:
- testy Pest dla dopasowania, planowania, zapisu i komendy, każdy z drugim użytkownikiem o nakładających się danych;
- ręczny przebieg na `test-pack` przeciw drzewu dev.

### Key Discoveries:

- `ApplyFirstImport` już używa `CreateParentChildRelation` i `CreateMarriage` jako ostatniej linii obrony, a ich wyjątek cofa cały import. Planer musi więc sprawdzić niezmienniki na **wspólnym** grafie drzewa i repo. Inaczej jedna relacja sprzeczna z ręcznymi danymi wysadzi cały zapis.
- Prompts w testach przechodzą na pytania Symfony (`vendor/laravel/framework/src/Illuminate/Console/Concerns/ConfiguresPrompts.php:37`):
  - `select()` odpowiada się przez `expectsChoice` / `expectsQuestion` z **kluczem** opcji;
  - `expectsChoice` jest kluczowane treścią pytania (`vendor/laravel/framework/src/Illuminate/Testing/PendingCommand.php:134`), więc każda treść pytania musi być unikalna (stąd „k/N” i id repo w treści);
  - `select()` bez `default` rzuca przy braku interakcji;
  - `datatable()` i `autocomplete()` nie mają zastępczego pytania w testach, więc ich nie używamy.
- `$this->table()` (Symfony) zostaje do tabel. Wyjście sprawdzamy przez `expectsOutputToContain`, a Prompts używamy tylko do `select()`.
- Osoba wpisana przez UI ma zawsze urodzenie, puste albo z datą (`CreatePerson`; w dev 0/110 osób bez urodzenia). Wydarzenie urodzenia dopasowanej osoby jest więc zwykle oznaczane `O-x/urodzenie`, o ile repo ma klucz `urodzenie`. Osoba spoza `CreatePerson` (`RestoreBackup`, `Person::factory()` w testach) może nie mieć urodzenia. Wtedy urodzenie z repo powstaje jak brakujący zgon.
- `MapsEventInput::eventAttributesFrom()` (`app/Actions/Persons/MapsEventInput.php:19`) mapuje datę 1:1 z `ResearchDate` (kontrakt S-07). Zmiana daty istniejącego wydarzenia dotyka tylko kolumn daty, a `locality_id`, `cemetery_id` i `marriage_form` zostają.

## What We're NOT Doing

- **Ponownego importu, „zmienione od ostatniego importu” i „usuniętych w repo”** (S-04). Drzewo z osobą z `repo_id` = odmowa. Osoba pominięta teraz nie da się zaimportować, dopóki S-04 nie obsłuży nowych osób w drzewie z `repo_id`.
- **Miejsc ze spisu** (S-05). Miejsce urodzenia z repo tylko porównujemy z nazwą miejscowości. Do wydarzeń go nie zapisujemy (zostaje tekstem w notatce), a ręczne `locality_id` się nie zmienia. Miejsce nigdy nie jest sprzecznością, bo warianty pisowni to S-05.
- **Poziomów A–D i wariantów nazwiska w schemacie** (S-08, S-09). Warianty nazwiska z bloku służą tylko do szukania kandydatów, a w notatce zostają jak w S-02.
- **Szukania kandydatów po nazwisku męża.** Kobieta wpisana ręcznie pod nazwiskiem po mężu nie będzie kandydatem i przy braku innego kandydata wejdzie jako nowa (decyzja z wywiadu).
- **Krewnych jako dopasowanych osób.** Ojciec, matka i małżonek zgadzają się po imieniu i nazwisku, bez propagacji dopasowań.
- **Zamiany i usuwania ręcznych relacji.** Sprzeczna relacja z repo jest pomijana z raportem.
- **Ponownego planowania relacji po rozstrzygnięciu płci.** Graf relacji liczy się z płcią z aplikacji przy konfliktach. Relacja odcięta przez „tę samą płeć” nie wraca, gdy autor wybierze potem płeć z repo; zostaje w „Pominięte relacje”.
- **Zapisu i wznawiania decyzji.** Jeden przebieg, decyzje tylko w pamięci, a przerwanie oznacza powtórzenie wszystkiego.
- **Trybu nieinteraktywnego z dopasowaniami.** Gdy są pewne lub wątpliwe dopasowania albo konflikty, `--no-interaction` kończy się odmową bez zapisu.
- **Zmian w UI, `CreateParentChildRelation`, `CreateMarriage` i kontrakcie bloku** (pola bez zmian, `wersja: 1`). W `UpdatePerson` tylko wyciągnięcie reguły płci rodzica do traitu (Faza 3), bez zmiany zachowania.
- **Scalania dwóch ręcznych osób** ani wykrywania duplikatów wewnątrz drzewa.

## Implementation Approach

Rozdzielamy decyzję od zapisu (CLAUDE.md, „Research repo import”) na trzech poziomach:
- `MatchResearchPersons` czyta drzewo i odczyt repo, a zwraca dla każdej osoby repo werdykt (nowa / pewna / wątpliwa) z kandydatami, zgodnymi i sprzecznymi elementami oraz wierszami porównania. Niczego nie zapisuje.
- Komenda zbiera od autora decyzje o tożsamości (`IdentityDecisions`).
- `PlanFirstImport` z odczytu i decyzji buduje pełny plan:
  - nowe i dopasowane osoby,
  - uzupełnienia i konflikty wartości,
  - relacje i małżeństwa do utworzenia albo oznaczenia,
  - wszystko pominięte z powodem,
  - notatki i liczby.
- Komenda zbiera rozstrzygnięcia konfliktów (`ConflictResolutions`) i pyta o zgodę.
- `ApplyFirstImport` zapisuje plan z rozstrzygnięciami w jednej transakcji. Pod blokadą ponownie sprawdza bramkę i dopasowane osoby.

Dotychczasowe Actions zachowują nazwy. „Pierwszy import” to teraz import do drzewa bez osób z `repo_id`, a puste drzewo jest jego szczególnym przypadkiem.

## Critical Implementation Details

- **Reguła lat (zgodność i sprzeczność).** Data to przedział lat:
  - dokładna i „ok.” → `[r, r]`,
  - zakres → `[od, do]`,
  - „przed r” → `(−∞, r]`,
  - „po r” → `[r, +∞)`.

  Luka = odległość między przedziałami (0 przy nakładaniu). **Zgodne:** oba przedziały ograniczone z obu stron i luka ≤ 2, gdy co najmniej jeden to pojedynczy rok, albo luka = 0, gdy oba to zakresy. **Sprzeczne:** luka > 2, gdy oba ograniczone, a gdy któryś jest nieograniczony („przed” / „po”) — luka > 0. „Przed” i „po” nigdy nie dają zgodności.

  Przypadki graniczne do testu:
  - 1801/1803 zgodne, 1801/1804 sprzeczne;
  - „ok. 1801”/1803 zgodne;
  - „między 1798 a 1802”/1800 zgodne, a z 1804 też (luka 2);
  - zakresy 1790–1795/1797–1799 sprzeczne (luka 2, oba zakresy);
  - „przed 1809”/1790: ani zgodne, ani sprzeczne;
  - „przed 1809”/1810 sprzeczne.
- **Kolejność w komendzie.** Konflikty zależą od decyzji o tożsamości, a sprawdzenie płci rodziców w konflikcie zależy od zaplanowanych relacji. Dlatego kolejność to:
  1. dopasowanie,
  2. decyzje o tożsamości,
  3. plan,
  4. rozstrzygnięcia konfliktów,
  5. zgoda,
  6. kopia,
  7. zapis.

  Planer od razu wycina konflikty płci, których wybranie dałoby dziecku dwoje rodziców tej samej płci, i zgłasza je jako pominięte. Każda opcja pokazana autorowi da się więc zapisać.
- **Wspólny graf relacji.** Węzły to dopasowane i nowe osoby repo (po id repo) oraz niedopasowane osoby drzewa. Krawędzie to istniejące relacje drzewa i kandydaci z repo. Kandydaci sprawdzani są w ustalonej kolejności (id dziecka, potem ojciec przed matką), przyrostowo względem grafu:
  1. para już jest w drzewie → oznaczenie;
  2. dziecko ma już w drzewie innego rodzica tej samej znanej płci co rodzic z repo → pominięta („w drzewie inny ojciec: …”);
  3. trzeci rodzic → pominięta;
  4. dwoje rodziców tej samej znanej płci (płeć po uzupełnieniach, przy konfliktach płeć z aplikacji) → pominięta;
  5. cykl na wspólnym grafie → pominięta.

  Istniejące krawędzie drzewa nigdy nie są usuwane.

## Faza 1: Dopasowanie osób

### Overview

Action `MatchResearchPersons` porównuje osoby z odczytu repo z osobami drzewa konta i klasyfikuje każdą osobę repo. Bez zapisu.

### Changes Required:

#### 1. Klucz nazwy i przedział lat

**File**: `app/Support/Import/NameKey.php`, `app/Support/Import/YearSpan.php`

**Intent**: Dwie małe, czyste reguły, na których stoi dopasowanie. Testujemy je jednostkowo, bo przypadki graniczne decydują o sklejeniu albo duplikacie.

**Contract**:
- `NameKey`:
  - normalizuje tekst przez `Str::ascii`, małe litery, przycięcie i scalenie spacji;
  - pierwsze imię to pierwsze słowo pola imion;
  - klucze nazwiska osoby repo to nazwisko oraz każdy wariant z `warianty_nazwiska`.
- `YearSpan`:
  - powstaje z `ResearchDate` albo z kolumn wydarzenia (`date_qualifier`, `date_year`, `date_until_year`), a `null`, gdy brak roku;
  - ma metody „zgodny z” i „sprzeczny z” według reguły lat z Critical Implementation Details.

#### 2. Klasy wartości dopasowania

**File**:
- `app/Enums/MatchElement.php`, `app/Enums/MatchVerdict.php`,
- `app/Support/Import/MatchCandidate.php`, `app/Support/Import/PersonMatch.php`, `app/Support/Import/PersonMatches.php`.

**Intent**: Typowany wynik dopasowania, który komenda drukuje i z którego buduje pytania, bez ponownego liczenia.

**Contract**:
- `MatchElement` (string-backed, TitleCase, jak `app/Enums/LocalityKind.php`): `BirthYear`, `BirthPlace`, `Father`, `Mother`, `Spouse`, `DeathYear`, z polską etykietą.
- `MatchVerdict`: `New`, `Sure`, `Doubtful`.
- `MatchCandidate` (`final readonly`):
  - osoba drzewa,
  - `list<MatchElement>` zgodnych,
  - `list<string>` opisów sprzeczności (np. „rok urodzenia: repo 1801, drzewo 1830”),
  - wiersze porównania `list<array{0: string, 1: string, 2: string}>` (pole, repo, drzewo).
- `PersonMatch`: `ResearchPerson`, `MatchVerdict`, `list<MatchCandidate>`.
- `PersonMatches`:
  - `array<string, PersonMatch>` kluczowane id repo,
  - `?string $blockedReason` (ponowny import).

#### 3. `MatchResearchPersons`

**File**: `app/Actions/Import/MatchResearchPersons.php`

**Intent**: Jedyne miejsce reguły tożsamości z PRD („co najmniej dwa niezależne elementy poza imieniem i nazwiskiem; w każdym innym przypadku decyduje autor”).

**Contract**: `handle(Tree $tree, ResearchRepoReading $reading): PersonMatches`. Czyta wyłącznie przez `$tree->people()` z jednym ładowaniem zależności:
- wydarzenia z miejscowością,
- rodzice,
- małżeństwa z uczestnikami.

Reguły:
- **Bramka:** jest osoba drzewa z `repo_id` → `blockedReason` = „Drzewo konta ma już osoby z importu. Ponowny import (S-04) jeszcze nie działa; nic nie zostanie zapisane.”, a pozostałe pola są puste.
- **Kandydat:** osoba drzewa, której klucz pierwszego imienia równa się kluczowi pierwszego imienia osoby repo i której klucz `last_name` należy do kluczy nazwiska osoby repo. Brak imienia (albo nazwiska) po jednej stronie pasuje tylko do braku po drugiej.
- **Elementy** (każdy osobno; nieznany po którejkolwiek stronie = ani zgodny, ani sprzeczny):
  - rok urodzenia i rok zgonu — `YearSpan` z wydarzeń urodzenia i zgonu;
  - miejsce urodzenia — klucz tekstu `miejsce` z repo = klucz nazwy miejscowości urodzenia w drzewie; tylko zgodność;
  - ojciec / matka:
    - **Zgodny**, gdy któryś rodzic w drzewie spełnia regułę kandydata względem rodzica z repo (osoby z `$reading->persons`) i jego znana płeć nie przeczy roli.
    - **Sprzeczny**, gdy w drzewie jest rodzic o płci tej roli, a jego pierwsze imię różni się od imienia rodzica z repo. Nazwisko nie przeczy, bo bywa wpisane po mężu.
  - małżonek — zgodny, gdy któryś małżonek z drzewa spełnia regułę kandydata względem któregoś małżonka z repo; nigdy sprzeczny.
- **Płeć:** obie znane i różne → sprzeczność (bez elementu zgodnego).
- **Werdykt:**
  - brak kandydatów → `New`;
  - dokładnie jeden kandydat, co najmniej 2 zgodne i 0 sprzecznych → `Sure`;
  - każdy inny przypadek z kandydatem → `Doubtful`;
  - dwie lub więcej osób repo z werdyktem `Sure` wskazujących tę samą osobę drzewa → wszystkie `Doubtful`.
- **Wiersze porównania** dla każdego kandydata:
  - imiona i nazwisko, płeć, urodzenie (data i miejsce), zgon,
  - ojciec, matka, małżonkowie,
  - id osoby w drzewie.

  Daty w gramatyce bloku (`ResearchDate::label()` po stronie repo; po stronie drzewa ten sam format z kolumn wydarzenia).

#### 4. Testy dopasowania

**File**: `tests/Unit/NameKeyTest.php`, `tests/Unit/YearSpanTest.php`, `tests/Feature/ResearchPersonMatchTest.php`

**Intent**: Dowodzi reguły tożsamości bezpośrednio przez Action (CLAUDE.md: „Test the planning Action directly”), na repo tymczasowym (wzór `planTemporaryRepo` w `tests/Feature/FirstImportPlanTest.php:41`) i osobach drzewa z helperów `tests/Pest.php` (`attachBirth`, `attachDeath`, `linkParent`, `attachMarriage`).

**Contract**:
- `NameKeyTest`:
  - „Łukasz” = „lukasz”;
  - „Jan Józef” ma pierwsze imię „jan”;
  - wariant nazwiska jest kluczem.
- `YearSpanTest`: każdy przypadek graniczny z Critical Implementation Details.
- `ResearchPersonMatchTest`. Każdy test ma drugiego użytkownika z tymi samymi imionami, nazwiskami, rodzicami i datami, a żaden kandydat nie pochodzi z jego drzewa. Przypadki:
  - brak imiennika → `New`;
  - „Jan Józef Nowak” vs „Jan Nowak” oraz „jan nowakowski” przy wariancie → kandydaci; „Józef Nowak” → nie;
  - ojciec i matka po imieniu i nazwisku, bez roku → `Sure` (2 elementy);
  - jeden element → `Doubtful`;
  - dwóch kandydatów, z których jeden ma 3 zgodne → `Doubtful`;
  - 2 zgodne + sprzeczny rok (1801 vs 1830) → `Doubtful` z opisem sprzeczności;
  - inny znany ojciec po imieniu → sprzeczność; ten sam ojciec z innym nazwiskiem → brak sprzeczności;
  - matka bez płci w drzewie → zgodność możliwa, sprzeczności brak;
  - miejsce urodzenia zgodne bez wielkości liter i polskich znaków, różne miejsce → brak sprzeczności;
  - różna znana płeć → sprzeczność;
  - dwie osoby repo pewnie wskazujące tę samą osobę drzewa → obie `Doubtful`;
  - drzewo z osobą z `repo_id` → `blockedReason`;
  - puste drzewo → wszystkie `New`.

### Success Criteria:

#### Automated Verification:

- Testy dopasowania przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/NameKeyTest.php tests/Unit/YearSpanTest.php tests/Feature/ResearchPersonMatchTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 2: Plan importu z decyzjami

### Overview

`PlanFirstImport` przyjmuje decyzje o tożsamości i buduje plan dla drzewa z ręcznymi osobami: nowe i dopasowane osoby, uzupełnienia, konflikty, relacje i małżeństwa sprawdzone na wspólnym grafie, pominięcia z powodem.

### Changes Required:

#### 1. Decyzje i nowe elementy planu

**File**:
- `app/Support/Import/IdentityDecisions.php`,
- `app/Support/Import/PlannedMatch.php`, `app/Support/Import/PlannedFill.php`, `app/Support/Import/ValueConflict.php`,
- zmiany w `FirstImportPlan.php`, `PlannedRelation.php`, `PlannedMarriage.php`.

**Intent**: Typowany kształt tego, co autor zatwierdził, i tego, co zostanie zapisane. Komenda go drukuje, a `ApplyFirstImport` zapisuje bez ponownego liczenia.

**Contract**:
- `IdentityDecisions`:
  - dla **każdej** osoby repo jedno z: nowa, pominięta albo dopasowana do id osoby drzewa;
  - fabryka `allNew(ResearchRepoReading)` dla pustego drzewa i dla testów S-02.
- `PlannedMatch`:
  - `ResearchPerson`, id osoby drzewa;
  - tekst do dopisania w notatce (`?string`);
  - `repo_id` istniejącego urodzenia i zgonu do oznaczenia (`?string`; `null`, gdy repo nie ma klucza);
  - urodzenie i zgon do utworzenia (repo ma `urodzenie` / `zgon`, drzewo nie);
  - `list<PlannedFill>`, `list<ValueConflict>`.
- `PlannedFill` (pole, wartość z repo typu `string|Gender|ResearchDate` w PHPDoc, etykieta) i `ValueConflict` (stały klucz, np. `O-0042/urodzenie/data`, osoba, pole, etykieta wartości z aplikacji, etykieta wartości z repo). Pola:
  - osoby (`PlannedMatch`): imiona, nazwisko, płeć, data urodzenia i zgonu;
  - małżeństwa (`PlannedMarriage`): data ślubu, klucz konfliktu `<repo_id ślubu>/data` (np. `O-0001+O-0004/slub/data`), jedno pytanie na parę.

  Pole puste w drzewie i niepuste w repo → uzupełnienie. Oba niepuste i różne → konflikt.
  - **Tekst (imiona, nazwisko):** porównanie po przycięciu i scaleniu spacji, z rozróżnieniem wielkości liter i polskich znaków („Łukasz” / „Lukasz” to konflikt, „Jan  Józef” / „Jan Józef” nie).
  - **Data:** doprecyzowanie to uzupełnienie. Doprecyzowanie oznacza ten sam rodzaj, rok i rok końca, przy czym w drzewie brakuje miesiąca (albo przy tym samym miesiącu brakuje dnia), a repo je ma. Każda inna różnica (rodzaj, rok, miesiąc, dzień, rok końca) → konflikt.
- `PlannedRelation`: końce jako id repo; `?int` id istniejącej relacji do oznaczenia.
- `PlannedMarriage`:
  - `?int` id istniejącego wydarzenia ślubu do oznaczenia;
  - `?PlannedFill $dateFill` i `?ValueConflict $dateConflict` (tylko przy oznaczeniu).
- `FirstImportPlan` dostaje:
  - `matched` (`list<PlannedMatch>`),
  - `skippedPersons` (`list<ImportIssue>`),
  - `skippedMarriages` (`list<ImportIssue>`),
  - `skippedValues` (`list<ImportIssue>`, np. pominięta płeć z repo),
  - `conflicts` (`list<ValueConflict>`, zebrane ze wszystkich dopasowań i małżeństw).

  Liczby podsumowania obejmują teraz także nowe, dopasowane, pominięte osoby i uzupełnienia. `persons` to tylko osoby nowe.

#### 2. `PlanFirstImport`

**File**: `app/Actions/Import/PlanFirstImport.php`

**Intent**: Podejmuje każdą decyzję zapisu przed zgodą, żeby autor zobaczył dokładnie to, co wejdzie, a zapis nigdy nie trafił na niezmiennik aplikacji.

**Contract**: `handle(Tree $tree, ResearchRepoReading $reading, IdentityDecisions $decisions): FirstImportPlan`.

Reguły:
- **Bramka:** stała `BLOCKED_REASON` „drzewo ma osoby” znika. Blokuje osoba z `repo_id` w drzewie (ten sam tekst co w `MatchResearchPersons`).
- **Decyzje:** zawierają każdą osobę repo, wskazują wyłącznie osoby z `$tree` i każdą osobę drzewa najwyżej raz. Inaczej `InvalidArgumentException`, bo to błąd komendy, nie danych.
- **Osoby:**
  - nowa → `PlannedPerson` jak w S-02;
  - dopasowana → `PlannedMatch` z uzupełnieniami i konfliktami;
  - pominięta → `skippedPersons` („osoba pominięta przy dopasowaniu”).
- **Relacje:**
  1. Reguły z S-02 dla grafu repo: ten sam id jako ojciec i matka, płeć rodzica niezgodna z rolą.
  2. Relacja z osobą pominiętą → pominięta.
  3. Pozostałe sprawdzane na wspólnym grafie w kolejności z Critical Implementation Details: oznaczenie istniejącej, sprzeczny rodzic, trzeci rodzic, ta sama płeć, cykl. Każde pominięcie ma powód z id repo i osobą drzewa.
- **Małżeństwa:**
  - z osobą pominiętą → `skippedMarriages`;
  - oboje dopasowani i para ma już ślub w drzewie → oznaczenie, uzupełnienie i konflikt daty;
  - w przeciwnym razie nowe.
- **Konflikt płci:** jeśli płeć z repo dałaby dziecku dwoje rodziców tej samej znanej płci w zaplanowanym grafie, konfliktu nie ma i wpis idzie do `skippedValues` („płeć z repo pominięta: …”). Osoba jest importowana, więc wpis nie trafia do `skippedPersons`. Ta sama reguła dotyczy uzupełnienia płci.
- **Notatka:**
  - nowa osoba → jak w S-02;
  - dopasowana → ten sam blok „Z repo badawczego (O-…)” jako tekst do dopisania;
  - linia ślubu z osobą pominiętą nie powstaje.
- **Granica drzewa:** każde zapytanie przez `$tree` (FR-016).

#### 3. Testy planowania

**File**: `tests/Feature/FirstImportPlanTest.php`

**Intent**: Dowodzi reguł planu bezpośrednio przez Action i utrzymuje przypadki S-02 dla pustego drzewa.

**Contract**:
- `planFixtureImport` i `planTemporaryRepo` przekazują `IdentityDecisions::allNew(...)`.
- Test „drzewo autora z jedną osobą → blokada” zmienia się na: osoba z `repo_id` → blokada, a osoba bez `repo_id` → brak blokady.
- Nowe przypadki, każdy z drugim użytkownikiem o nakładających się danych:
  - dopasowana osoba z pustym rokiem urodzenia i płcią → uzupełnienia, bez konfliktów;
  - różna data urodzenia i imiona → dwa konflikty ze stałymi kluczami;
  - drzewo „1801”, repo „1801-05-12” → uzupełnienie, bez konfliktu; drzewo „1801-04”, repo „1801-05-12” → konflikt; imiona różniące się tylko spacjami → nic;
  - zgon w repo, brak w drzewie → zgon do utworzenia;
  - zgon w drzewie, brak w repo → nic;
  - dopasowana osoba bez urodzenia w drzewie (`Person::factory()`), repo ma `urodzenie` → urodzenie do utworzenia;
  - relacja już w drzewie → oznaczenie;
  - dziecko z innym ojcem w drzewie → pominięta z powodem;
  - dziecko z dwojgiem rodziców w drzewie → trzeci pominięty;
  - cykl przez istniejącą relację drzewa (repo: A rodzicem B; drzewo: B rodzicem A) → pominięta;
  - osoba pominięta → jej relacje i małżeństwa pominięte, brak linii ślubu w notatce partnera;
  - ślub pary dopasowanych już w drzewie → oznaczenie z konfliktem daty;
  - konflikt płci, który dałby dwoje rodziców tej samej płci → brak konfliktu, wpis w `skippedValues`, `skippedPersons` bez zmian;
  - decyzje wskazujące osobę z cudzego drzewa albo tę samą osobę dwa razy → wyjątek.

#### 4. Komenda — stan przejściowy do Fazy 4

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Po zmianie sygnatury Larastan i komenda muszą działać między fazami. Nowa bramka (`repo_id`) nie może sama otworzyć importu do ręcznego drzewa bez dopasowania, bo wtedy powstałyby duplikaty.

**Contract**:
- Przed planowaniem komenda sama odmawia drzewu z jakąkolwiek osobą. Treść jest dotychczasowa (`PlanFirstImport::BLOCKED_REASON` przeniesiona do komendy), a odmowa daje `FAILURE`.
- `PlanFirstImport::handle($tree, $reading, IdentityDecisions::allNew($reading))`.
- Faza 4 usuwa tę odmowę i zastępuje ją dopasowaniem.

### Success Criteria:

#### Automated Verification:

- Testy planowania przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/FirstImportPlanTest.php tests/Feature/ResearchPersonMatchTest.php`
- Testy odczytu S-01 przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 3: Zapis importu do drzewa z ręcznymi osobami

### Overview

`ApplyFirstImport` zapisuje plan z rozstrzygnięciami konfliktów w jednej transakcji, wpinając repo w istniejące rekordy bez usuwania niczego z aplikacji.

### Changes Required:

#### 1. Rozstrzygnięcia i wynik

**File**: `app/Support/Import/ConflictResolutions.php`, `app/Support/Import/FirstImportResult.php`

**Intent**: Odpowiedzi autora na konflikty i liczby zapisu do podsumowania.

**Contract**:
- `ConflictResolutions`: klucz konfliktu → „z repo” albo „z aplikacji”; brak klucza = „z aplikacji”.
- `FirstImportResult` dostaje liczby: dopasowane osoby, uzupełnienia, wartości wzięte z repo, oznaczone relacje i małżeństwa.

#### 2. `ApplyFirstImport`

**File**: `app/Actions/Import/ApplyFirstImport.php`

**Intent**: Jedyne miejsce zapisu importu. Wszystko albo nic, tylko w drzewie konta. Ręczne rekordy dostają `repo_id` i zatwierdzone wartości, a nic z nich nie znika.

**Contract**: `handle(Tree $tree, FirstImportPlan $plan, ConflictResolutions $resolutions): FirstImportResult`. W `DB::transaction`:
1. **Blokady i sprawdzenia.** Blokada drzewa, potem ponowne sprawdzenie, że w drzewie nie ma osoby z `repo_id`. Następnie blokada każdej dopasowanej osoby w id order; każda musi istnieć w `$tree` i mieć `repo_id = null`. Inaczej `RuntimeException`, nic nie zapisane.
2. **Dopasowane osoby (bez `UpdatePerson`):**
   - `repo_id`, uzupełnienia, konflikty rozstrzygnięte „z repo” (imiona, nazwisko, płeć);
   - notatka = istniejąca + pusta linia + blok (albo sam blok, gdy pusta).

   Zmiana płci ponownie sprawdza regułę płci rodzica (trait `GuardsParentSexChange`, niżej) na stanie bazy i przy naruszeniu rzuca. Zdjęcie, religia, zamieszkania, nagrobki i źródła się nie zmieniają.
3. **Wydarzenia dopasowanych:**
   - Urodzenie i zgon dostają `repo_id` z planu. Data jest uzupełniana albo brana z repo przez zapis modelu (`eventAttributesFrom`, tylko kolumny daty). `locality_id`, `cemetery_id` i powiązania `event_source` zostają.
   - Brakujące urodzenie i zgon tworzą się jak w S-02 (`createEvent`).
4. **Nowe osoby, urodzenia i zgony** jak w S-02.
5. **Relacje:**
   - oznaczenie istniejących (`repo_id`);
   - nowe przez `CreateParentChildRelation::handle`, a potem `repo_id`.
6. **Małżeństwa:**
   - oznaczenie istniejących, z datą wg `dateFill` lub rozstrzygnięcia `dateConflict` przez zapis modelu (bez `UpdateMarriage`, który czyści miejscowość i formę);
   - nowe przez `CreateMarriage::handle`, a potem `repo_id`.

Wyjątek z dowolnego kroku cofa całą transakcję i wychodzi z Action.

#### 3. Reguła płci rodzica jako trait

**File**: `app/Actions/Persons/GuardsParentSexChange.php` (nowy; wzór traitu: `MapsEventInput.php`), `app/Actions/Persons/UpdatePerson.php`

**Intent**: Jedna reguła płci rodzica dla formularza i importu zamiast kopii prywatnej metody.

**Contract**:
- `guardParentSexChange` przenosi się z `UpdatePerson.php:130` do traitu `GuardsParentSexChange` bez zmiany zachowania. Komunikaty i `ValidationException` na `gender` zostają.
- Odczyt zapisanej płci idzie przez drzewo osoby (`$person->tree->people()`), a nie przez globalne `Person::query()`.
- Używają go `UpdatePerson` i `ApplyFirstImport`. Komenda łapie `Throwable`, więc `ValidationException` z zapisu kończy się komunikatem i zerem zmian.
- Istniejące testy reguły (`tests/Feature/PersonsTest.php`, `tests/Feature/ParentChildRelationsTest.php`) przechodzą bez zmian.

#### 4. Testy zapisu

**File**: `tests/Feature/FirstImportApplyTest.php`

**Intent**: Dowodzi zapisu do ręcznych rekordów, zachowania danych z aplikacji, granicy drzewa i atomowości. Komenda omija Policy, więc to dowód FR-014 i FR-016.

**Contract**:
- Istniejące testy S-02 przekazują puste `ConflictResolutions`.
- Test „person added after planning” zakłada starą bramkę („drzewo ma osoby”) i trzeba go przepisać. Osoba bez `repo_id` dodana po planowaniu nie blokuje zapisu, a osoba z `repo_id` blokuje: wyjątek i zero zmian.
- Komenda (stan przejściowy z Fazy 2) przekazuje do `ApplyFirstImport::handle` puste `ConflictResolutions`.
- Nowy helper: drzewo autora z ręcznymi osobami o imionach z fixture, a w nich:
  - urodzenie z miejscowością i źródłem z numerem wpisu,
  - zdjęcie, notatka „Nazwisko po mężu: …”,
  - zamieszkanie, relacja, ślub.

  Drugi użytkownik ma te same dane.
- Przypadki:
  - dopasowana osoba: `repo_id`, uzupełniona płeć, notatka = stara + blok; zdjęcie, zamieszkanie, `event_source` z numerem wpisu, `locality_id` urodzenia bez zmian;
  - konflikt „z aplikacji” → wartość bez zmian; „z repo” → nowa wartość, `date_is_approximate` zgodne z `date_qualifier`;
  - istniejące urodzenie oznaczone `O-x/urodzenie`, bez drugiego urodzenia; zgon utworzony, gdy go brakowało;
  - istniejąca relacja i ślub oznaczone, a nowe utworzone między dopasowaną i nową osobą;
  - liczba relacji dopasowanego dziecka bez zmian przy sprzecznym ojcu;
  - `rowDiff` / `ownershipSnapshot` (`tests/Pest.php:148`, `:227`): żaden wiersz drugiego użytkownika się nie zmienia; nowe i zmienione wiersze należą do drzewa autora; usunięte wiersze — zero;
  - dopasowana osoba usunięta albo z `repo_id` po planowaniu → wyjątek i zero zmian;
  - błąd w środku zapisu (podmieniony `CreateMarriage`, wzór z S-02) → zero zmian także w dopasowanych osobach.

### Success Criteria:

#### Automated Verification:

- Testy zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/FirstImportApplyTest.php`
- Testy reguły płci rodzica przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 4: Komenda — podgląd dopasowań i konfliktów w terminalu

### Overview

`import:research` prowadzi autora przez dopasowania pewne (zbiorczo), wątpliwe i konflikty, a potem pyta o zgodę i zapisuje. To odpowiedź na PRD Open Questions 4.

### Changes Required:

#### 1. Komenda

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Dziesiątki decyzji muszą dać się rozstrzygnąć w jednym przebiegu: zbiorczo tam, gdzie reguła jest pewna, pojedynczo z pełnym porównaniem tam, gdzie nie jest. Nic nie zapisuje się bez ostatniej zgody.

**Contract**: sygnatura bez zmian; `#[Description]` „Zaimportuj przodków z repo badawczego do drzewa konta”. Przebieg po raporcie odczytu:
1. `MatchResearchPersons`. Przy `blockedReason` → `error`, `FAILURE`, bez pytań.
2. Sekcja „Dopasowanie do osób w drzewie” z liczbami nowe / pewne / wątpliwe.
3. Są pewne lub wątpliwe, a wejście nie jest interaktywne (`$this->input->isInteractive()`) → „Dopasowanie wymaga odpowiedzi w terminalu; nic nie zostało zapisane.”, `FAILURE`.
4. **Pewne:**
   - `$this->table` (id repo, osoba z repo, osoba w drzewie z id i rokiem urodzenia, zgodne elementy);
   - `Laravel\Prompts\select("Pewne dopasowania ({N}): co zrobić?", ['all' => 'Zatwierdź wszystkie', 'each' => 'Przejrzyj pojedynczo'], default: 'all')`.
5. **Pojedynczo**: najpierw pewne przy `each` („Pewne {k}/{N}”), potem wątpliwe („Wątpliwe {k}/{N}”), w każdej grupie w kolejności id repo:
   - `$this->table` z wierszami porównania (kolumny: pole, repo, kandydat 1, kandydat 2…; na końcu zgodne i sprzeczne);
   - `select("{Pewne|Wątpliwe} {k}/{N}: {imię nazwisko} ({id repo}) — kto to?", …)` z opcjami:
     - `"person:{id}" => "To {imię nazwisko} (#{id}, ur. …)"` dla każdego kandydata niewybranego wcześniej,
     - `'new' => 'Inna osoba — dodaj jako nową'`,
     - `'skip' => 'Pomiń — nie importuj teraz'`.
   - Domyślna opcja: `skip`, a przy przeglądzie pewnych — kandydat. Gdy ten kandydat jest już zajęty i nie ma go wśród opcji, domyślna jest `skip`.

   Etykiety opcji mieszczą się w ok. 74 znakach, szczegóły są w tabeli.
6. `IdentityDecisions` (osoby bez kandydata → nowe) → `PlanFirstImport` → podsumowanie S-02 rozszerzone o:
   - osoby nowe / dopasowane / pominięte,
   - uzupełnienia,
   - tabele: „Pominięte osoby”, „Pominięte relacje”, „Pominięte małżeństwa”, „Pominięte wartości”, „Uzupełnienia” (osoba, pole, wartość z repo).
7. **Konflikty:** dla każdego `select("Konflikt {k}/{M}: {imię nazwisko} ({id repo}) — {pole}", ['app' => 'Zostaw z aplikacji: …', 'repo' => 'Weź z repo: …'], default: 'app')`.
8. Nic do zapisania (zero nowych i dopasowanych) → „Nic do zapisania.”, `SUCCESS`.
9. `$this->confirm("Zapisać do drzewa konta {email}: nowe osoby {N}, dopasowane {M}?", false)`. „Nie” → „Nic nie zostało zapisane.”, `SUCCESS`.
10. Kopia na produkcji i `ApplyFirstImport` jak w S-02 (`report()` przy wyjątku), potem podsumowanie zapisu z nowymi liczbami i linią o probancie. Linia o probancie pojawia się tylko wtedy, gdy drzewo nie ma `root_person_id`.

#### 2. Testy komendy

**File**: `tests/Feature/ImportResearchCommandTest.php`

**Intent**: Dowodzi przebiegu przez `$this->artisan(...)` z `expectsChoice` / `expectsQuestion` (klucz opcji) i `expectsConfirmation` oraz dostosowuje testy S-01/S-02 do nowej treści pytania o zgodę.

**Contract**:
- Istniejące testy pustego drzewa oczekują nowej treści pytania o zgodę.
- „writes nothing for either account” (autor z osobami `Jan Nowak`, `Anna Wrona` bez dat i krewnych) przechodzi teraz przez dopasowanie:
  - ci kandydaci są wątpliwi (0–1 element);
  - odpowiedź `skip` dla obu i „no” → brak zmian w obu drzewach.
- Nowy helper: autor z ręcznymi osobami odpowiadającymi fixture tak, że jedna jest pewna (rodzice po imieniu), jedna wątpliwa i jedna ma konflikt daty. Drugi użytkownik ma te same dane.
- Przypadki:
  - pewne `all` + wątpliwa „person:…” + konflikt `repo` + „yes” → zapis w drzewie autora, cudze bez zmian, wyjście zawiera tabelę pewnych i porównanie;
  - wątpliwa `new` → nowa osoba obok ręcznej;
  - wątpliwa `skip` → brak osoby i jej relacji, wpis w „Pominięte osoby”;
  - `each` → pytanie „Pewne 1/N” o osobę pewną przed pytaniami o wątpliwe;
  - kandydat wybrany wcześniej nie jest opcją w kolejnym pytaniu (`expectsChoice` z pełną listą opcji klucz => etykieta);
  - drzewo z osobą z `repo_id` → odmowa, brak pytań;
  - wejście nieinteraktywne przy dopasowaniach (`Artisan::call` z `--no-interaction`, jak w S-02) → `FAILURE`, brak zapisu;
  - produkcja z błędną kopią po zgodzie → nic nie zapisane (wzór `fakeBackupRun`, `tests/Feature/ImportResearchCommandTest.php:48`).

### Success Criteria:

#### Automated Verification:

- Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- Wszystkie testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/FirstImportPlanTest.php tests/Feature/FirstImportApplyTest.php tests/Unit/ResearchDateTest.php tests/Unit/NameKeyTest.php tests/Unit/YearSpanTest.php`
- Pełny zestaw testów przechodzi: `vendor/bin/sail artisan test --compact`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Przebieg `test-pack` przeciw drzewu dev z ręcznymi osobami (odpowiedź „nie” na końcu): tabela pewnych i porównania wątpliwych są czytelne w terminalu, etykiety opcji się nie ucinają, a werdykty zgadzają się z oceną autora
- Ten sam przebieg z odpowiedzią „tak” po lokalnej kopii bazy: dopasowane osoby zachowują zdjęcie, notatkę (z dopisanym blokiem), źródła i miejscowości; nowe osoby są w drzewie z relacjami do dopasowanych; bazę potem przywrócono z kopii
- Ponowne uruchomienie po zapisie kończy się odmową „ponowny import — S-04”

**Implementation Note**: Po fazie i zielonych testach automatycznych poprosić autora o ręczny przebieg. Wykonuje go na `storage/gen-tree-import/test-pack/`, bo prawdziwe repo nie ma jeszcze bloków. Najpierw kopia bazy dev (`vendor/bin/sail artisan backup:run --only-db`), na końcu przywrócenie (`vendor/bin/sail artisan backup:restore`, `app/Console/Commands/RestoreBackup.php`).

---

## Testing Strategy

### Unit Tests:

- `NameKey`:
  - polskie znaki i wielkość liter,
  - pierwsze imię,
  - warianty nazwiska.
- `YearSpan`: każdy przypadek graniczny reguły lat (Critical Implementation Details).

### Integration Tests:

- **Dopasowanie (Action):**
  - kandydaci,
  - każdy z sześciu elementów zgodny i sprzeczny,
  - werdykty, dwie osoby repo na jedną osobę drzewa,
  - bramka ponownego importu.
- **Plan (Action):**
  - uzupełnienia i konflikty,
  - relacje na wspólnym grafie (oznaczenie, sprzeczny rodzic, trzeci rodzic, cykl przez drzewo),
  - pominięte osoby, małżeństwa, konflikt płci,
  - walidacja decyzji.
- **Zapis (Action):**
  - zachowanie danych z aplikacji (zdjęcie, notatka, zamieszkanie, `event_source`, `locality_id`),
  - rozstrzygnięcia,
  - oznaczenia,
  - wyścig po planowaniu,
  - wycofanie przy błędzie.
- **Komenda (`$this->artisan`):**
  - pewne zbiorczo i pojedynczo,
  - wątpliwe: ta sama, nowa, pomiń,
  - konflikty, zgoda,
  - odmowa ponownego importu i trybu nieinteraktywnego,
  - kopia na produkcji.
- Każdy test importu ma drugiego użytkownika z tymi samymi imionami, nazwiskami, krewnymi i datami (CLAUDE.md), a test dowodzi, że nic z jego drzewa nie zostało dopasowane ani zapisane.

### Manual Testing Steps:

1. `vendor/bin/sail artisan backup:run --only-db` (kopia bazy dev).
2. `vendor/bin/sail artisan import:research <e-mail konta dev> /var/www/html/storage/gen-tree-import/test-pack` (ścieżka w kontenerze). Przejdź pewne, wątpliwe i konflikty, odpowiedz „nie” i sprawdź, że nic nie zapisano.
3. Uruchom ponownie, odpowiedz tak samo i zakończ „tak”. W aplikacji otwórz kilka dopasowanych osób (zdjęcie, notatka, źródła, miejscowość urodzenia) i nowych (relacje do dopasowanych).
4. Uruchom trzeci raz i sprawdź odmowę.
5. Przywróć bazę z kopii z kroku 1 (`vendor/bin/sail artisan backup:restore`).

## Performance Considerations

Dopasowanie ładuje osoby drzewa raz, z wydarzeniami, rodzicami i małżeństwami, i indeksuje je po kluczu nazwy. Przy setkach osób to kilka zapytań. Zapis jak w S-02: kilkaset małych zapytań w jednej transakcji, rzadko i ręcznie. Nie optymalizujemy.

## Migration Notes

- Bez migracji. `repo_id` istnieje od S-02.
- Na produkcji pierwszy import nadal czeka na S-05 (spis miejsc bez duplikatów, roadmapa). Ten plan go nie odblokowuje sam.
- Cofnięcie importu to kopia bazy sprzed zapisu (PRD Non-Goals).

## References

- Roadmapa: `context/foundation/roadmap.md` (S-03; Open Roadmap Questions 1 = PRD Open Questions 4)
- PRD: `context/foundation/prd.md` (US-01, FR-005, FR-006, FR-007, FR-014, FR-016; Business Logic Changes — reguła tożsamości)
- Kontrakt bloku: `context/foundation/research-block-contract.md`
- Poprzedni wycinek: `context/archive/2026-10-23-first-import-into-empty-tree/plan.md` i `reviews/impl-review.md` (F3: urodzenie z `repo_id = null`)
- Inne nazwiska poza zakresem: `context/archive/2026-10-21-single-birth-surname/plan-brief.md`
- Wzory: `app/Actions/Import/PlanFirstImport.php:172` (Tarjan), `app/Actions/Persons/UpdatePerson.php:130` (reguła płci rodzica), `app/Actions/Relations/CreateParentChildRelation.php:62`
- Prompts w testach: `vendor/laravel/framework/src/Illuminate/Console/Concerns/ConfiguresPrompts.php:37`, `vendor/laravel/framework/src/Illuminate/Testing/PendingCommand.php:132`
- Testy i helpery: `tests/Feature/FirstImportPlanTest.php:41`, `tests/Feature/ImportResearchCommandTest.php:16`, `tests/Pest.php:58-106`, `:148`, `:227`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Dopasowanie osób

#### Automated

- [x] 1.1 Testy dopasowania przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/NameKeyTest.php tests/Unit/YearSpanTest.php tests/Feature/ResearchPersonMatchTest.php` — 222abdb
- [x] 1.2 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check` — 222abdb
- [x] 1.3 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent` — 222abdb

### Phase 2: Plan importu z decyzjami

#### Automated

- [x] 2.1 Testy planowania przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/FirstImportPlanTest.php tests/Feature/ResearchPersonMatchTest.php` — 83daa76
- [x] 2.2 Testy odczytu S-01 przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php` — 83daa76
- [x] 2.3 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check` — 83daa76
- [x] 2.4 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent` — 83daa76

### Phase 3: Zapis importu do drzewa z ręcznymi osobami

#### Automated

- [x] 3.1 Testy zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/FirstImportApplyTest.php`
- [x] 3.2 Testy reguły płci rodzica przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/ParentChildRelationsTest.php`
- [x] 3.3 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [x] 3.4 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

### Phase 4: Komenda — podgląd dopasowań i konfliktów w terminalu

#### Automated

- [ ] 4.1 Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- [ ] 4.2 Wszystkie testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/FirstImportPlanTest.php tests/Feature/FirstImportApplyTest.php tests/Unit/ResearchDateTest.php tests/Unit/NameKeyTest.php tests/Unit/YearSpanTest.php`
- [ ] 4.3 Pełny zestaw testów przechodzi: `vendor/bin/sail artisan test --compact`
- [ ] 4.4 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [ ] 4.5 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual

- [ ] 4.6 Przebieg `test-pack` przeciw drzewu dev z ręcznymi osobami (odpowiedź „nie” na końcu): tabela pewnych i porównania wątpliwych są czytelne w terminalu, etykiety opcji się nie ucinają, a werdykty zgadzają się z oceną autora
- [ ] 4.7 Ten sam przebieg z odpowiedzią „tak” po lokalnej kopii bazy: dopasowane osoby zachowują zdjęcie, notatkę (z dopisanym blokiem), źródła i miejscowości; nowe osoby są w drzewie z relacjami do dopasowanych; bazę potem przywrócono z kopii
- [ ] 4.8 Ponowne uruchomienie po zapisie kończy się odmową „ponowny import — S-04”
