# Miejsca z repo dopasowane do spisu: plan implementacji

## Overview

`import:research` przestaje wpisywać miejsca do notatki osoby. Urodzenie, zgon i ślub dostają miejscowość ze spisu drzewa (`events.locality_id`), a pochowanie wchodzi jako osobne wydarzenie na cmentarzu (`events.cemetery_id` i miejscowość cmentarza). Nazwa z repo pasuje do spisu pewnie albo niepewnie, albo jest nowa:
- **pewnie:** ta sama nazwa bez wielkości liter i polskich znaków albo zatwierdzony wcześniej wariant,
- **niepewnie:** podobna pisownia albo te same słowa w innej kolejności,
- **nowa:** cała reszta.

Nowe miejsca i niepewne dopasowania autor potwierdza w podglądzie, raz na nazwę. Zatwierdzony wariant („Bokinia” to „Bukinia”) aplikacja zapamiętuje w drzewie, więc to samo pytanie nie wraca. Spis nie dubluje miejscowości ani cmentarzy.

To roadmapa S-05 (FR-009, FR-010, FR-017). Parafie przechodzą do S-06, bo w aplikacji parafię ma tylko źródło (decyzja autora 2026-10-30).

## Current State Analysis

**Import po S-02, S-03 i S-04:**
- Kontrakt bloku v1 (`context/foundation/research-block-contract.md`):
  - `miejsce` to dowolny tekst,
  - `pochowek.miejsce` to „cmentarz jako tekst”.
- Parser czyta `miejsce` do `ResearchEvent::$place` (`app/Actions/Import/ParsePersonBlock.php:343-352`, `app/Support/Import/ResearchEvent.php:9-14`). Pochowanie trafia do `ResearchPerson::$burial`.
- Miejsca i pochowanie lądują wyłącznie w bloku notatki „Z repo badawczego (O-…)” (`app/Support/Import/RepoValues.php:84-152`):
  - `Urodzenie: <miejsce>; poziom …`,
  - `Pochowanie: <data>; <miejsce>` albo `Pochowanie: bez szczegółów`,
  - `Ślub z … : <miejsce>`.
- `ApplyImport::createEvent` (`app/Actions/Import/ApplyImport.php:918-928`) ustawia tylko datę i `repo_id`. `markEvent` (`:899-916`) zmienia tylko kolumny daty. Ślub idzie przez `CreateMarriage` z samą datą (`:234-239`). Pochowania import nie tworzy, a usuwa je tylko razem ze zgonem (`:618`).
- **Reguła trzech wartości** (`app/Support/Import/ThreeWayValue.php:22-42`):
  - pamięć zdarzenia to tylko `data` (`RepoValues::event`, `:39-42`),
  - porównanie daty robi `PlanImport::dateItem` (`app/Actions/Import/PlanImport.php:815-833`),
  - plan urodzenia i zgonu (własne, nieoznaczone, przywrócone, nowe) to `planEvent` (`:844-891`),
  - śluby obsługuje `planMarriages` (`:1193-1259`).
- **Klucze repo** (`app/Support/Import/RepoKeys.php`, `app/Enums/RepoKeyKind.php`) znają osobę, urodzenie, zgon, parę i ślub. Pochowania nie znają.
- **Dopasowanie osób** (`app/Actions/Import/MatchResearchPersons.php:139-143`) uznaje miejsce urodzenia za zgodne przy równości `NameKey::normalize` z nazwą miejscowości urodzenia. Nie zna wariantów.
- **Komenda** (`app/Console/Commands/ImportResearch.php:56-175`) idzie w tej kolejności:
  1. raport,
  2. tożsamość,
  3. usunięte w repo,
  4. usunięte w aplikacji,
  5. `PlanImport` i podsumowanie (`:747-748` wypisuje „miejsca: N (spis miejsc — S-05)”),
  6. zmiany,
  7. konflikty,
  8. zgoda, kopia bazy, `ApplyImport`.

  Odpowiedzi zbiera `Laravel\Prompts\select` przez `choose()` (`:680-683`). Testy używają `expectsChoice` / `expectsQuestion` (`tests/Feature/ImportResearchCommandTest.php:359`, `:374-391`).

**Spis miejsc:**
- **Miejscowość** jest unikalna po `(tree_id, lower(name))` (`database/migrations/2026_10_05_081630_create_localities_table.php:24`). Nie ma kolumny ani tabeli wariantów nazw. Dopasowanie w aplikacji jest dokładne, bez wielkości liter, ale z polskimi znakami (`app/Concerns/LocalityValidationRules.php:28`).
- **Cmentarz** ma wymaganą miejscowość, opcjonalną nazwę, zarządcę i wyznanie. Jest unikalny po `(tree_id, locality_id, lower(coalesce(name,'')))` (`2026_10_20_111716_create_cemeteries_table.php:30`). `StoreCemetery::handle(Tree, array{name, locality_id, manager, religion, description})` (`app/Actions/Cemeteries/StoreCemetery.php:22`).
- **Niezmiennik pochowania:** miejsce to cmentarz, a `events.locality_id` = miejscowość cmentarza. Pilnują go tylko Actions (`app/Actions/Persons/MapsEventInput.php:48-65` `burialPlaceFrom`). Pochowanie istnieje w UI tylko przy osobie zmarłej (`PersonStoreRequest.php:29-35`, `UpdatePerson.php:64-77`). UI nie pokazuje daty pochowania.
- **Zdarzenia nie mają parafii.** Parafię ma tylko źródło (`sources.parish_id`).
- **Scalanie miejscowości** (`app/Actions/Localities/MergeLocality.php:26-60`) przenosi cmentarze, parafie, zdarzenia i zamieszkania. **Usuwanie** (`app/Actions/Localities/DeleteLocality.php:16-43`) liczy te same odwołania.
- **Panel miejscowości** to `resources/js/components/localities/locality-panel.tsx`, dane z `app/Http/Resources/LocalityResource.php` (`PlaceController.php:29`, `:65`).
- Dev: jedno drzewo z 19 miejscowościami (wszystkie bez rodzaju), 3 parafiami i 4 cmentarzami bez nazwy. Każde z 16 pochowań ma cmentarz.

**Prawdziwe repo badawcze (2026-10-30, tylko odczyt):**
- 280 plików osób i 0 bloków. `test-pack` ma 7 bloków: `miejsce` to sama nazwa, raz wieś, raz parafia (`Uchanie`). `pochowek` nie ma nigdzie.
- W prozie jest ok. 60–70 miejscowości i ok. 22 siedziby parafii. Warianty: Bukinia/Bokinia, Binduga/Bindyuga/Бендюга, Rostoka/Roztoka, Kolemczyce/Kołemczyce, Stary Majdan/Majdan Stary, Barbarówka/Barbarowina.
- Cmentarz w prozie to prawie zawsze „cmentarz rz.-kat. w Bindudze”: miejscowość w miejscowniku, bez własnej nazwy.

## Desired End State

Autor uruchamia `import:research` na drzewie (pustym, z ręcznymi osobami albo po wcześniejszym imporcie). Po decyzjach o tożsamości i usunięciach komenda pokazuje miejsca:
- tabelę nowych miejscowości z „Zatwierdź wszystkie / Przejrzyj pojedynczo”; pojedynczo „Dodaj do spisu / Bez miejsca”. Nowy cmentarz (w nowej miejscowości albo w miejscowości bez cmentarzy) wchodzi bez pytania i jest liczony w podsumowaniu,
- każde niepewne dopasowanie osobno: „To <kandydat> (zapamiętaj wariant) / Dodaj jako nową / Bez miejsca”, domyślnie „Bez miejsca”,
- wybór cmentarza, gdy w miejscowości jest kilka cmentarzy, a blok nie wskazuje jednego.

Każde pytanie pada raz na nazwę, nie na zdarzenie. Po zgodzie, w jednej transakcji, import:
- dodaje nowe miejscowości i cmentarze,
- zapamiętuje warianty,
- przypisuje zdarzeniom miejscowość albo cmentarz,
- tworzy pochowania (i zgon bez daty, gdy blok ma `pochowek` bez `zgon`).

Zdarzenie „bez miejsca” zachowuje tekst w bloku notatki. Rozpoznane miejsce znika z notatki.

Kolejny import porównuje miejsce regułą trzech wartości jak datę:
- ręczna zmiana miejscowości w aplikacji nie znika bez pytania,
- zmiana w repo nietkniętego miejsca wchodzi po zbiorczym zatwierdzeniu,
- drugi przebieg bez zmian kończy się „Nic do zapisania.”.

Scalenie miejscowości w `/places` przenosi warianty, a nazwa duplikatu zostaje wariantem celu. Panel miejscowości pokazuje warianty do odczytu.

Weryfikacja: testy Pest każdej warstwy z drugim użytkownikiem o tych samych miejscach, a potem ręczny przebieg na kopii `test-pack` z dopisanymi przez autora miejscami i pochowaniem.

### Key Discoveries:

- `MapsEventInput::eventAttributesFrom` przyjmuje już `locality_id` (`app/Actions/Persons/MapsEventInput.php:19-29`), a `CreateMarriage::handle` przyjmuje `locality_id` w `$input` (`app/Actions/Relations/CreateMarriage.php:26`). Import tylko ich nie przekazuje.
- `burialPlaceFrom` (`MapsEventInput.php:48-65`) bierze miejscowość z cmentarza drzewa. Zapis pochowania z importu powinien przez niego przejść, żeby niezmiennik został w jednym miejscu.
- `ThreeWayValue::compare` porównuje R, S i A jako tekst (`ThreeWayValue.php:22-42`); nadpisać można tylko `$repoIsApp`. Miejsce porównuje się więc tokenami id (zob. Critical Implementation Details), nie tekstem repo.
- Pamięć bez klucza pola daje `hasLast = false` (`PlanImport.php:785-792`). Zdarzenia z S-02 do S-04 dostaną więc miejsce jak przy pierwszym imporcie: puste w aplikacji to uzupełnienie, inne to konflikt.
- `NameKey::normalize` (`app/Support/Import/NameKey.php:12`) daje klucz bez polskich znaków i wielkości liter, ten sam co przy osobach.
- Reguła z `CLAUDE.md`: nowa tabela odwołująca się do miejscowości wchodzi do `MergeLocality` i do testu scalania miejscowości. Usuwanie miejscowości jest dozwolone tylko bez odwołań.

## What We're NOT Doing

- Parafie: ani dopasowania, ani zapisu parafii z bloku, ani `events.parish_id` (decyzja autora; parafia wchodzi w S-06 przy źródle aktu).
- Klucz `parafia` w bloku zdarzenia.
- Edycja wariantów w UI (dodawanie, usuwanie). Panel tylko je pokazuje.
- Szukanie miejscowości po wariancie w `LocalityPicker` i na liście `/places`.
- Zawieranie się nazw jako niepewne dopasowanie (Siedliszcze / Siedliszcze Bramowe, Kajetanówka / Nowokajetanówka). Takie pary wyjdą jako nowe miejscowości do późniejszego scalenia (decyzja autora).
- Rodzaj, współrzędne i opis nowej miejscowości oraz zarządca i wyznanie nowego cmentarza. Import ustawia tylko nazwę (i miejscowość cmentarza).
- Odczytywanie miejsca z prozy, z odmian („w Bindudze”) albo z nawiasów. Blok niesie nazwę w mianowniku. Tekst z nawiasem, przecinkiem albo „par.” / „parafia” daje ostrzeżenie, nie poprawkę.
- `wersja: 2` bloku. Kontrakt v1 dostaje poprawione znaczenie `pochowek.miejsce` (decyzja autora: pole nie było jeszcze nigdzie użyte).
- Pokazywanie daty pochowania w UI. Data trafia do wydarzenia i zostaje w bloku notatki.
- Poziomy A–D i notatki wydarzeń jako pola (S-08). Zostają w bloku notatki.
- Uzupełnianie starych notatek w drzewach poza ponownym importem.

## Implementation Approach

Ten sam podział decyzji i zapisu co w S-03 i S-04. Nowa Action `MatchResearchPlaces` (bez zapisu) zbiera z odczytu nazwy miejsc, które wymagają rozstrzygnięcia. Zwraca werdykty: pewne, niepewne z kandydatami, nowe, a dla cmentarzy także „do wyboru”. Komenda zbiera odpowiedzi w `PlaceDecisions`, które `PlanImport` dostaje razem z `ImportDecisions`.

`PlanImport` traktuje miejsce jak kolejne pole zdarzenia: reguła trzech wartości (R, S, A porównane po rozpoznanej miejscowości lub cmentarzu), pamięć `miejsce` (i `cmentarz` przy pochowaniu), notatka bez rozpoznanych miejsc. Pochowanie dostaje własny klucz `O-…/pochowek` i cały tor S-04: nowe, oznaczone, przywrócone, usunięte w repo i w aplikacji.

`ApplyImport` najpierw dodaje nowe miejscowości, warianty i cmentarze (przez `StoreLocality` i `StoreCemetery`), potem zapisuje wydarzenia z odwołaniami. Wszystko dzieje się w tej samej transakcji, pod blokadą drzewa i ze sprawdzeniem, że wskazane miejsca nadal należą do drzewa.

Warianty żyją w nowej tabeli `locality_aliases`. Spis, scalanie, usuwanie i panel miejscowości dostają je w pierwszej fazie, niezależnie od importu.

## Critical Implementation Details

**Kolejność decyzji w komendzie.** Pytania o miejsca padają po tożsamości, usuniętych w repo i usuniętych w aplikacji, a przed `PlanImport`. Inaczej import pytałby o miejsca osób pominiętych albo zdarzeń zostawionych jako usunięte. Cmentarz rozstrzyga się dopiero po decyzji o jego miejscowości (nowa miejscowość oznacza nowy cmentarz bez pytania).

**Które nazwy wymagają rozstrzygnięcia.** Tylko miejsca zdarzeń, których pamięć nie ma klucza `miejsce` (lub `cmentarz`) albo ma inny tekst niż repo teraz. Miejsce bez zmian w repo od ostatniego importu nie pyta: reguła daje wtedy „bez zmian”, „ręczna zmiana zostaje” albo, gdy spis rozpoznaje teraz pewnie tekst, który wcześniej był „bez miejsca”, zbiorczą zmianę (zob. „Pamięć miejsca trzyma tekst i rozpoznane id”).

**„Bez miejsca” nigdy nie zeruje kolumny.** Nierozpoznane miejsce nie tworzy zmiany ani konfliktu dla kolumny. Zdarzenie zachowuje swoją miejscowość, a tekst idzie do notatki. Tylko usunięcie `miejsce` w repo (R = brak) może wyczyścić nietkniętą miejscowość, jak przy dacie.

**Pamięć miejsca trzyma tekst i rozpoznane id.** Pamięć zdarzenia ma `miejsce` (tekst repo) i `miejsce_id` (id miejscowości, którą ten tekst oznaczał przy imporcie; null przy „bez miejsca”), a pochowania także `cmentarz` i `cmentarz_id`. Id to rozpoznanie wartości repo, nie wartość z aplikacji: zapisuje się także wtedy, gdy autor zostawił ręczną wartość. Dla nowych miejsc id uzupełnia `ApplyImport` po ich utworzeniu. Reguła trzech wartości dostaje tokeny id jako tekst (np. `L:12`, `C:4`, null), więc `ThreeWayValue` się nie zmienia:
- R = id, które daje tekst repo teraz (decyzja albo pewne dopasowanie); nierozpoznane R nie tworzy pozycji,
- S = `miejsce_id` z pamięci; gdy tej miejscowości nie ma już w drzewie (scalenie, usunięcie), S = rozpoznanie tekstu `miejsce` dziś (scalenie dopisuje wariant, więc trafia w cel),
- A = `locality_id` zdarzenia (przy pochowaniu `cemetery_id`).

Pamięć bez `miejsce_id` (importy S-02 do S-04) liczy się jak brak pamięci (`hasLast = false`). Skutek: dawne „bez miejsca” (S = null, A = null) uzupełnia się zbiorczą zmianą, gdy spis później rozpozna tekst, a zmiana nazwy miejscowości w aplikacji nie daje konfliktu.

**Notatka według faktycznego miejsca.** Tekst miejsca znika z notatki tylko wtedy, gdy wydarzenie po tym imporcie ma miejsce, które daje R (zapisane teraz albo już równe). W każdym innym razie (bez miejsca, ręczna wartość zostaje, konflikt rozstrzygnięty „z aplikacji”) tekst zostaje w notatce. Ta sama reguła działa dla nazw, o które przebieg nie pyta, więc drugi przebieg bez zmian daje tę samą notatkę.

**Pochowanie tylko z cmentarzem.** W aplikacji pochowanie istnieje tylko z cmentarzem: `UpdatePerson` (`app/Actions/Persons/UpdatePerson.php:67-73`) kasuje pochowanie bez cmentarza przy każdym zapisie formularza osoby. Import tworzy więc wydarzenie pochowania tylko wtedy, gdy cmentarz jest rozpoznany (pewny, wybrany albo nowy). Gdy cmentarz jest „bez miejsca” albo `pochowek` nie ma `miejsce`, wydarzenie nie powstaje: data i tekst zostają w linii „Pochowanie” notatki, a pamięć klucza `O-…/pochowek` zapisuje się bez rekordu (wzór `ImportPlan::$keptDeletedSnapshots`). `ClassifyRepoRecords` nie traktuje takiej pamięci jako „usunięte w aplikacji”, więc kolejny przebieg bez zmian nie pyta. Gdy w repo zmieni się `pochowek.miejsce` lub `cmentarz`, nazwa idzie do rozstrzygnięcia jak przy pierwszym imporcie, a rozpoznany cmentarz tworzy pochowanie.

## Faza 1: Warianty nazw miejscowości w spisie

### Overview

Drzewo pamięta inne zapisy nazwy miejscowości. Scalanie i usuwanie miejscowości je obsługują, a panel miejscowości je pokazuje. Faza nie dotyka importu.

### Changes Required:

#### 1. Tabela i model wariantów

**File**: `database/migrations/<data>_create_locality_aliases_table.php`, `app/Models/LocalityAlias.php`, `app/Models/Locality.php`, `app/Models/Tree.php`

**Intent**: Wariant nazwy należy do miejscowości i do drzewa, jest unikalny w drzewie bez wielkości liter i znika razem z miejscowością.

**Contract**:
- `locality_aliases`: `id`, `tree_id` (FK, cascade), `locality_id` (FK, cascade), `name` string, timestamps.
- Unikalny indeks `(tree_id, lower(name))`.
- `down()` usuwa tabelę.
- `Locality::aliases(): HasMany`, `Tree::localityAliases(): HasMany`.
- Fabryka `LocalityAliasFactory`.

#### 2. Scalanie i usuwanie miejscowości

**File**: `app/Actions/Localities/MergeLocality.php`, `app/Actions/Localities/DeleteLocality.php`

**Intent**: Scalenie przenosi warianty duplikatu do celu. Pomija wariant, który cel już ma (bez wielkości liter) albo który równa się nazwie celu. Nazwę duplikatu dopisuje jako wariant celu, żeby import rozpoznał ją później. Usuwanie nie liczy wariantów jako odwołań (kasują się kaskadą).

**Contract**: bez zmian sygnatur. Opis `MergeLocality::handle` wymienia warianty. Komunikat usuwania bez zmian.

#### 3. Warianty w panelu miejscowości

**File**: `app/Http/Resources/LocalityResource.php`, `app/Http/Controllers/PlaceController.php`, `resources/js/components/localities/locality-panel.tsx`, typ miejscowości w `resources/js/types/`

**Intent**: Panel szczegółów miejscowości pokazuje do odczytu wiersz „Inne zapisy nazwy: Bokinia, Bukini”, gdy miejscowość ma warianty. Lista `/places` ładuje warianty bez N+1.

**Contract**: `LocalityResource` dostaje `aliases: list<string>` (posortowane, tylko gdy relacja jest załadowana). Tokeny i komponenty według `resources/js/CLAUDE.md`.

#### 4. Testy

**File**: `tests/Feature/LocalitiesTest.php`

**Intent**:
- Test scalania obejmuje warianty: przeniesione, zdublowane pominięte, nazwa duplikatu jako wariant.
- Usunięcie miejscowości z wariantami przechodzi.
- Warianty innego użytkownika nie pojawiają się w jego drzewie ani w zasobie.
- Istniejący test 404 scalania (`tests/Feature/OwnershipBoundariesTest.php:99`, `localities.merge`) nadal przechodzi.

**Contract**: nowe przypadki w istniejącym pliku, z danymi z fabryk. Rozszerzony test „merging moves every kind of reference…” (`LocalitiesTest.php:297`) obejmuje warianty.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi i da się ją cofnąć: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Testy miejscowości i miejsc przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php tests/Feature/CemeteriesTest.php tests/Feature/OwnershipBoundariesTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`
- Frontend: `vendor/bin/sail npm run check` i `vendor/bin/sail npm run types:check` oraz `vendor/bin/sail npm run test:js`

#### Manual Verification:

- W `/places` po scaleniu dwóch miejscowości panel celu pokazuje nazwę duplikatu w „Inne zapisy nazwy”, a miejscowość bez wariantów nie ma tego wiersza

**Implementation Note**: Po fazie i zielonych testach automatycznych poprosić autora o ręczne sprawdzenie panelu.

---

## Faza 2: Kontrakt bloku i odczyt miejsc

### Overview

Kontrakt v1 mówi jasno, że `miejsce` to miejscowość, a pochowanie to miejscowość cmentarza z opcjonalną nazwą cmentarza. Odczyt niesie nazwę cmentarza, ostrzega przy miejscu wyglądającym na opis i dodaje zgon wynikający z pochowania. Klucze repo znają pochowanie.

### Changes Required:

#### 1. Kontrakt bloku

**File**: `context/foundation/research-block-contract.md`

**Intent**: Poprawić v1 bez zmiany numeru wersji, z dopiskiem o decyzji (pole `pochowek` nie było użyte w żadnym bloku przed 2026-10-30).

**Contract**:
- `miejsce` = nazwa miejscowości w mianowniku, bez parafii, gminy i nawiasów. Gdy znana jest tylko parafia, autor zostawia `miejsce` puste; parafia przychodzi z S-06.
- `pochowek.miejsce` = miejscowość cmentarza. Nowy opcjonalny `pochowek.cmentarz` = nazwa cmentarza, gdy w miejscowości jest ich kilka.
- `pochowek` bez `zgon` = zgon bez daty (ostrzeżenie).
- Nowe ostrzeżenie: „miejsce wygląda na opis, nie nazwę miejscowości”.
- Przykład w kontrakcie i reguła do `CLAUDE.md` repo badawczego zaktualizowane: nazwa w mianowniku, wariant pisowni zostaje w prozie, a w bloku jedna nazwa.

#### 2. Odczyt

**File**: `app/Actions/Import/ParsePersonBlock.php`, `app/Actions/Import/ReadResearchRepo.php`, `app/Support/Import/ResearchEvent.php`

**Intent**:
- `cmentarz` jest znanym kluczem tylko w `pochowek`; w innych zdarzeniach jest nieznanym kluczem z ostrzeżeniem.
- `ResearchEvent` niesie `?string $cemetery`.
- Miejsce zawierające nawias, przecinek albo `par.` / `parafi` daje ostrzeżenie z plikiem; wartość zostaje.
- Blok z `pochowek` bez `zgon` dostaje `death = new ResearchEvent()` i ostrzeżenie „pochowanie bez zgonu: dodano zgon bez daty”.

**Contract**: `ResearchEvent(date, place, level, note, cemetery = null)`. Typ i walidacja `cmentarz` jak `miejsce` (nullable string).

#### 3. Klucze repo pochowania

**File**: `app/Support/Import/RepoKeys.php`, `app/Enums/RepoKeyKind.php`, `app/Support/Import/RepoValues.php`

**Intent**:
- Pochowanie ma klucz `O-0042/pochowek` i rodzaj `Burial`.
- Pamięć zdarzenia trzyma `data` i `miejsce`; pamięć pochowania także `cmentarz`. Id (`miejsce_id`, `cmentarz_id`) dopisuje planer w Fazie 4, bo `RepoValues` nie zna drzewa.
- `forKey` obsługuje pochowanie.

**Contract**:
- `RepoKeys::burial(string $id): string`, `kindOf` i `personIdsOf` dla `/pochowek`. `kindOf` (`RepoKeys.php:45-51`) ma `default => Person`, więc gałąź `/pochowek` jest konieczna; `personIdsOf` (`:64`) i `describe` w komendzie (`ImportResearch.php:436`) to `match` bez `default`, a `RepoValues::forKey` (`:52`) to `switch` bez `default`: każdy dostaje przypadek `Burial`.
- `RepoKeyKind::Burial = 'burial'`.
- `RepoValues::event` zwraca `array{data: string|null, miejsce: string|null, cmentarz?: string|null}`; pełna pamięć po planie: `+ miejsce_id: string|null, cmentarz_id?: string|null`.

#### 4. Fixture i testy odczytu

**File**: `tests/Fixtures/research-repo/drzewo/*.md` (wymyślone dane), `tests/Feature/ResearchRepoReadTest.php`, `tests/Unit/` testy kluczy, jeśli istnieją

**Intent**:
- `nowak_jan_1801.md`: `pochowek.miejsce: Zalesie`.
- Jedna osoba z `pochowek` bez `zgon`.
- Jedno miejsce z nawiasem (ostrzeżenie).
- Jeden `cmentarz` w urodzeniu (nieznany klucz).
- Testy odczytu sprawdzają te ostrzeżenia, nazwę cmentarza i dodany zgon.
- Oczekiwania innych testów korzystających z fixture zaktualizowane tylko tam, gdzie zmieniła się treść pochowania.

**Contract**: dane w fixture są wymyślone (reguła „Research data” w `CLAUDE.md`).

### Success Criteria:

#### Automated Verification:

- Testy odczytu i klasyfikacji przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ClassifyRepoRecordsTest.php`
- Wszystkie testy importu przechodzą po zmianie fixture: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/ClassifyRepoRecordsTest.php tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Feature/ImportResearchCommandTest.php tests/Unit`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 3: Dopasowanie miejsc do spisu (bez zapisu)

### Overview

Nowa Action rozstrzyga, jak każda nazwa z repo pasuje do spisu drzewa: pewnie, niepewnie (z kandydatami) albo jako nowa. Dla cmentarzy: pewnie, do wyboru albo nowy. Niczego nie zapisuje. Reguła podobieństwa jest osobna i testowana na przypadkach granicznych.

### Changes Required:

#### 1. Reguła podobieństwa nazw

**File**: `app/Support/Import/PlaceName.php` (nowa), `tests/Unit/PlaceNameTest.php`

**Intent**: Klucz nazwy to `NameKey::normalize`. Dwie nazwy o różnych kluczach są „podobne”, gdy:
- odległość Levenshteina między kluczami wynosi najwyżej 1, jeśli krótszy klucz ma do 6 znaków, a najwyżej 2 przy dłuższym,
- albo klucze mają te same słowa w innej kolejności.

**Contract**: `PlaceName::key(?string): ?string`, `PlaceName::similar(string $keyA, string $keyB): bool`.

Przypadki testowe:
| Para | Wynik |
|---|---|
| Kołemczyce / Kolemczyce | ten sam klucz |
| Bukinia / Bokinia | podobne (7 znaków, odległość 1) |
| Roztoka / Rostoka | podobne |
| Barbarówka / Barbarowina | podobne (odległość 2) |
| Bielin / Bielsk | niepodobne (6 znaków, odległość 2) |
| Rudka / Rutka | podobne (5 znaków, odległość 1) |
| Stary Majdan / Majdan Stary | podobne (szyk) |
| Siedliszcze / Siedliszcze Bramowe | niepodobne |

#### 2. `MatchResearchPlaces`

**File**: `app/Actions/Import/MatchResearchPlaces.php` (nowa), `app/Support/Import/PlaceMatches.php`, `app/Support/Import/PlaceVerdict.php` (lub podobne klasy wartości obok `PersonMatches`)

**Intent**: Zebrać nazwy miejsc, które wymagają rozstrzygnięcia (zob. Critical Implementation Details): zdarzenia osób planowanych (bez pominiętych przy dopasowaniu i bez kluczy zostawionych jako usunięte), których pamięć nie ma `miejsce` albo ma inny tekst. Pogrupować po kluczu nazwy. Dla każdej nazwy:
- **pewne**: klucz równy kluczowi wariantu drzewa (wariant to decyzja autora i wygrywa z nazwą miejscowości), a bez takiego wariantu klucz równy kluczowi nazwy dokładnie jednej miejscowości,
- **niepewne**: inaczej, gdy istnieją miejscowości (po nazwie lub wariancie) podobne według `PlaceName::similar`, albo gdy klucz bez wariantu pasuje do nazw więcej niż jednej miejscowości; kandydaci posortowani po odległości i nazwie,
- **nowe**: reszta.

Miejscowości i warianty czyta wyłącznie przez `$tree->localities()` i `$tree->localityAliases()`.

Nazwy o tym samym kluczu w różnej pisowni („Kołemczyce”, „Kolemczyce”) to jedno pytanie. Nowa miejscowość dostaje pisownię z polskimi znakami, a przy remisie najczęstszą w repo, potem pierwszą alfabetycznie.

Druga metoda rozstrzyga cmentarze, gdy znane są decyzje o miejscowościach. Dla każdej pary (miejscowość cmentarza, klucz nazwy cmentarza):
- **nowy**: nowa miejscowość albo miejscowość bez cmentarzy; wchodzi bez pytania (bez decyzji w `PlaceDecisions`),
- **pewny**: nazwa podana i równa (kluczem) nazwie dokładnie jednego cmentarza tej miejscowości, albo nazwa nie podana i miejscowość ma dokładnie jeden cmentarz,
- **do wyboru**: każdy inny przypadek (lista cmentarzy miejscowości z `Cemetery::label()`),
- **bez miejsca**: miejscowość „bez miejsca” oznacza cmentarz „bez miejsca”.

**Contract**:
- `handle(Tree, ResearchRepoReading, RepoRecordClasses, ImportDecisions): PlaceMatches` (miejscowości). Pamięci nie ma w `RepoRecordClasses` ani `ImportDecisions`, więc Action ładuje ją sama przez `$tree->repoSnapshots()` (jak `PlanImport.php:89`). Osoby planowane to `$classes->knownPersons` i `$decisions->identity->matched` / `new`, bez `identity->skipped`.
- `PlanImport` wywołuje tę samą Action (te same dane wejściowe), żeby sprawdzić, że `PlaceDecisions` pokrywa dokładnie nazwy z werdyktów.
- `cemeteries(Tree, PlaceMatches, PlaceDecisions): CemeteryMatches`.
- Każda nazwa niesie listę plików / osób, w których wystąpiła (do podglądu), i liczbę zdarzeń.

#### 3. Decyzje o miejscach

**File**: `app/Support/Import/PlaceDecisions.php` (nowa), `app/Support/Import/ImportDecisions.php`

**Intent**: Odpowiedzi autora na każdą nazwę:
- miejscowość: istniejąca (id, z flagą „zapamiętaj wariant” przy niepewnym), nowa albo bez miejsca,
- cmentarz: istniejący (id), nowy albo bez miejsca.

Pewne dopasowania wchodzą bez pytania. `ImportDecisions` niesie `PlaceDecisions`; `forFirstImport` daje puste.

**Contract**: `PlaceDecisions` ze stałymi `NEW`, `NONE` i mapami `localities: array<string key, int|NEW|NONE>`, `aliases: array<string key, true>`, `cemeteries: array<string key, int|NEW|NONE>`. Planer sprawdza, że decyzje pokrywają dokładnie nazwy z werdyktów i wskazują rekordy drzewa (jak `checkDecisions`).

#### 4. Testy dopasowania miejsc

**File**: `tests/Feature/ResearchPlaceMatchTest.php` (nowy)

**Intent**: Dla każdego werdyktu jeden przypadek na wymyślonym repo w katalogu tymczasowym. Pokryte:
- pewne po wariancie,
- niepewne przy kolizji dwóch miejscowości o tym samym kluczu, a po zapamiętaniu wariantu dla jednej z nich pewne (wariant wygrywa z nazwą drugiej),
- grupowanie tej samej nazwy z kilku zdarzeń w jedno pytanie,
- pominięcie nazw bez zmian w pamięci,
- pominięcie osób pominiętych przy dopasowaniu,
- cmentarz pewny, nowy i do wyboru,
- drugi użytkownik z miejscowością „Zalesie”, wariantem „Bokinia” i cmentarzem w Zalesiu: nic z jego spisu nie jest kandydatem.

**Contract**: wzór `tests/Feature/ResearchPersonMatchTest.php`.

### Success Criteria:

#### Automated Verification:

- Testy reguły i dopasowania przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/PlaceNameTest.php tests/Feature/ResearchPlaceMatchTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 4: Plan importu z miejscami i pochowaniem

### Overview

`PlanImport` przypisuje miejsca według decyzji, porównuje je regułą trzech wartości, planuje pochowanie jak urodzenie i zgon, zdejmuje rozpoznane miejsca z notatki i zapisuje pamięć miejsca. Klasyfikacja i dopasowanie osób znają pochowanie i warianty.

### Changes Required:

#### 1. Odwołanie do miejsca w planie

**File**: `app/Support/Import/PlannedPlace.php` (nowa), `app/Support/Import/ImportPlan.php`, `app/Support/Import/PlannedPerson.php`, `PlannedUpdate.php`, `PlannedMarriage.php`, `PlannedRestoration.php`

**Intent**: Plan mówi, gdzie ma trafić miejsce zdarzenia: istniejąca miejscowość lub cmentarz (id) albo nowe miejsce z planu (klucz nazwy). `ImportPlan` niesie listy nowych miejscowości, nowych cmentarzy i wariantów do zapisania. Nowe miejsce trafia do planu, gdy używa go jakaś pozycja planu; czy zostanie zapisane, rozstrzyga `ApplyImport` po odrzuconych zmianach i konfliktach (Faza 5.1). Warianty zapisują się zawsze, bo to decyzja o nazwie, nie o zdarzeniu.

**Contract**:
- `PlannedPlace` (miejscowość: `?int $localityId`, `?string $newLocality`; cmentarz: `?int $cemeteryId` albo nowy cmentarz z miejscowością i `?string` nazwą).
- `ImportPlan::$newLocalities`, `$aliases` (klucz nazwy => miejscowość), `$newCemeteries`.
- Pola `place` / `burial` w planowanych rekordach obok dat.

#### 2. Miejsce w regule trzech wartości

**File**: `app/Actions/Import/PlanImport.php`

**Intent**: Nowa metoda `placeItem` obok `dateItem`. R, S i A to tokeny id według Critical Implementation Details („Pamięć miejsca trzyma tekst i rozpoznane id”):
- R = id z tekstu repo (decyzja albo pewne dopasowanie),
- S = `miejsce_id` z pamięci, a gdy tej miejscowości już nie ma, rozpoznanie tekstu `miejsce` dziś,
- A = miejscowość zdarzenia (przy pochowaniu: cmentarz).

`ThreeWayValue::compare` bez zmian, bez `repoIsApp`. Nierozpoznane R nie tworzy pozycji. Planer dopisuje `miejsce_id` / `cmentarz_id` do pamięci (dla nowych miejsc klucz nazwy, który `ApplyImport` zamienia na id).

Etykiety:
- A: nazwa miejscowości / `Cemetery::label()`,
- R: nazwa ze spisu, z tekstem repo w nawiasie, gdy się różni („Bukinia (w repo: Bokinia)”), albo „nowa: Bokinia”.

Pola `birth_place`, `death_place`, `marriage_place`, `burial_place`, etykiety „Miejsce urodzenia” itd. Klucz pytania `{eventKey}/miejsce`. `FIELDS` w kolejności wyświetlania dostaje nowe pola.

**Contract**: wartości pozycji (`PlannedChange::$value` / `ValueConflict::$repoValue` / `PlannedFill`) rozszerzone o `PlannedPlace`. Rozstrzygnięcia konfliktów (`ConflictResolutions`) działają bez zmian, po kluczu pytania.

#### 3. Plan pochowania

**File**: `app/Actions/Import/PlanImport.php`, `app/Actions/Import/ClassifyRepoRecords.php`

**Intent**: `planEvent` obsługuje trzeci rodzaj: pochowanie, ze ścieżkami własne / nieoznaczone (ręczne pochowanie osoby dopasowanej w S-03) / przywrócone / nowe. Datę porównuje jak dziś, cmentarz przez `placeItem`.

`ClassifyRepoRecords` zna klucz pochowania: usunięte w repo, usunięte w aplikacji, nie do oceny. Konkretnie: lista rodzajów wydarzeń z `repo_id` (`ClassifyRepoRecords.php:186`, dziś `[Birth, Death, Marriage]`) dostaje `Burial`, inaczej pochowanie usunięte w aplikacji nie zostanie wykryte; `impactOf` (`:327-334`) zwraca dla pochowania pusty `RemovalImpact`. Skutek usunięcia zgonu nadal wymienia pochowanie. Dla klucza pochowania przy usunięciu jest tylko „usuń” albo „odłącz”, bez skutków.

Zgon dodany przy odczycie (pochowanie bez zgonu) idzie zwykłą ścieżką zgonu z kluczem `O-…/zgon`.

Pochowanie bez rozpoznanego cmentarza nie jest planowane jako wydarzenie, tylko jako pamięć bez rekordu (zob. Critical Implementation Details, „Pochowanie tylko z cmentarzem”). Klasyfikacja nie liczy jej jako usuniętej w aplikacji.

**Contract**: `EventPlan` dostaje pozycje miejsca; `planEvent(EventType $type, …)` zamiast `bool $birth`. `ImportPlan` niesie pamięć pochowań bez rekordu (osobna lista albo flaga obok `keptDeletedSnapshots`).

#### 4. Notatka bez rozpoznanych miejsc

**File**: `app/Support/Import/RepoValues.php`, `app/Actions/Import/PlanImport.php`

**Intent**: `RepoValues::note` pomija miejsce zdarzenia tylko wtedy, gdy wydarzenie po imporcie ma miejsce, które daje R (zob. Critical Implementation Details, „Notatka według faktycznego miejsca”). Zostawia je przy „bez miejsca”, przy ręcznej wartości, która zostaje, i przy konflikcie rozstrzygniętym „z aplikacji”. Uwaga: rozstrzygnięcia konfliktów zapadają po planie, więc notatka zależna od konfliktu musi mieć dwa warianty albo być liczona w `ApplyImport`.

Linia pochowania zostaje tylko z datą, poziomem i notatką (UI nie pokazuje daty pochowania), bez „bez szczegółów”. Linia ślubu i zdarzeń bez innych szczegółów znika.

Pamięć `notatka` dostaje nowy blok. Na drzewach z S-02 do S-04 nietknięty blok wyjdzie więc raz jako zmiana do zbiorczego zatwierdzenia.

**Contract**: `note(ResearchPerson, ResearchRepoReading, array $skipped = [], array $placed = [])`, gdzie `$placed` to klucze zdarzeń, które po imporcie mają miejsce z R.

#### 5. Dopasowanie osób po rozpoznanej miejscowości

**File**: `app/Actions/Import/MatchResearchPersons.php`

**Intent**: Element „miejsce urodzenia” jest zgodny, gdy klucz nazwy z repo jest równy kluczowi nazwy albo wariantu miejscowości urodzenia osoby z drzewa. Niepewne podobieństwo nie liczy się jako zgodność. Miejsce nadal nigdy nie przeczy.

**Contract**: bez zmian werdyktów i sygnatury; warianty ładowane razem z miejscowością (`events.locality.aliases`).

#### 6. Podsumowanie

**File**: `app/Support/Import/NoteValueCounts.php`, `app/Actions/Import/PlanImport.php`

**Intent**: Liczniki „miejsca” i „pochowania w notatce” zastępują liczniki:
- nowych miejscowości,
- nowych cmentarzy,
- zapamiętanych wariantów,
- zdarzeń z miejscem,
- zdarzeń „bez miejsca”,
- pochowań.

**Contract**: pola liczników w `NoteValueCounts` (lub nowej klasie podsumowania miejsc) dla komendy.

#### 7. Testy planowania

**File**: `tests/Feature/ImportPlanTest.php`, `tests/Feature/ClassifyRepoRecordsTest.php`, `tests/Feature/ResearchPersonMatchTest.php`, `tests/Unit/NoteBlockTest.php` (jeśli dotyczy)

**Intent**: Przypadki:
- pierwszy import z miejscem pewnym, nowym, „bez miejsca” (tekst w notatce) i wariantem,
- pochowanie z cmentarzem i pochowanie bez zgonu,
- pochowanie z cmentarzem „bez miejsca” i `pochowek` bez `miejsce`: brak wydarzenia, pamięć bez rekordu, tekst w notatce; ponowny import bez zmian nie pyta i nie widzi „usuniętego w aplikacji”,
- dopasowana osoba z ręczną miejscowością: puste uzupełnione, różne to konflikt z domyślnym „zostaw z aplikacji”,
- ponowny import:
  - miejsce bez zmian bez pozycji,
  - zmiana w repo nietkniętego miejsca jako zmiana,
  - ręczna zmiana i zmiana w repo jako konflikt z poprzednią wartością,
  - „Bokinia” po zapamiętanym wariancie jako „bez zmian”,
  - pamięć bez `miejsce` / `miejsce_id` (stare importy) jako uzupełnienie,
  - zmiana nazwy miejscowości w aplikacji: bez konfliktu i bez pozycji,
  - po scaleniu miejscowości (zapamiętane id nie istnieje): „bez zmian”,
  - dawne „bez miejsca”, które spis rozpoznaje teraz: zbiorcza zmiana, a po niej tekst znika z notatki,
  - ręczna miejscowość, która zostaje: tekst repo zostaje w notatce,
- pochowanie usunięte w repo i w aplikacji,
- drugi użytkownik z tymi samymi miejscami: jego miejscowości i cmentarze nie trafiają do planu.

**Contract**: wzór istniejących testów planu (`expect` na `ImportPlan`).

### Success Criteria:

#### Automated Verification:

- Testy planowania, klasyfikacji i dopasowania przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ClassifyRepoRecordsTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/ResearchPlaceMatchTest.php`
- Testy jednostkowe importu przechodzą: `vendor/bin/sail artisan test --compact tests/Unit`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 5: Zapis miejsc i pochowań

### Overview

`ApplyImport` dodaje do spisu nowe miejscowości, warianty i cmentarze, zapisuje miejsca zdarzeń i tworzy pochowania, w tej samej transakcji co reszta importu.

### Changes Required:

#### 1. Nowe miejsca i warianty

**File**: `app/Actions/Import/ApplyImport.php`

**Intent**: Po blokadzie drzewa i sprawdzeniach, przed osobami:
1. utworzyć nowe miejscowości przez `StoreLocality` (sama nazwa),
2. zapisać warianty w `$tree->localityAliases()`,
3. utworzyć nowe cmentarze przez `StoreCemetery` (miejscowość i opcjonalna nazwa).

Tworzy tylko te nowe miejscowości i cmentarze, których używa element zapisywany po `ChangeApprovals` i `ConflictResolutions` (nowy cmentarz liczy się jako użycie swojej miejscowości). Pozostałe pomija, a pamięć ich zdarzeń dostaje `miejsce_id` = null. `written()` w komendzie podaje liczbę faktycznie dodanych miejsc z `ImportResult`.

Mapa „klucz nazwy => id” obsługuje odwołania do nowych miejsc. Błąd walidacji (np. miejscowość o tej nazwie dodana w trakcie sesji) cofa całość komunikatem `withContext`.

**Contract**: kolejność w `handle`:
1. blokady i sprawdzenia,
2. usunięcia,
3. nowe miejsca,
4. osoby,
5. zmiany,
6. relacje i śluby,
7. pamięć.

#### 2. Sprawdzenie pod blokadą

**File**: `app/Actions/Import/ApplyImport.php`

**Intent**: Każda istniejąca miejscowość i cmentarz z planu nadal istnieje i należy do drzewa (`$tree->localities()->whereKey(...)`, `$tree->cemeteries()->whereKey(...)`). Żadna miejscowość ani wariant drzewa nie ma już klucza nazwy (`NameKey::normalize`) nowej miejscowości z planu; indeks unikalny jest po `lower(name)` z polskimi znakami, więc sam nie złapie „Kołemczyce” dodanej w trakcie zamiast „Kolemczyce”. Inaczej wyjątek „Miejsce … zmieniło się po planowaniu; nic nie zostało zapisane.” Pozycje miejsca z ręczną wartością sprawdzają ją jak daty (`verify`).

**Contract**: rozszerzenie `verify` / `guard*` o miejsca.

#### 3. Miejsca na wydarzeniach

**File**: `app/Actions/Import/ApplyImport.php`, `app/Actions/Persons/MapsEventInput.php` (tylko użycie)

**Intent**:
- `createEvent` i `markEvent` ustawiają `locality_id`.
- Pochowanie ustawia `cemetery_id` i `locality_id` przez `burialPlaceFrom`, nigdy osobno, więc niezmiennik cmentarza zostaje w jednym miejscu. Pochowanie bez rozpoznanego cmentarza nie powstaje; zapisuje się tylko jego pamięć.
- Ślub dostaje `locality_id` w `$input` `CreateMarriage`, a zmiana miejsca istniejącego ślubu idzie przez model.
- Zastosowane zmiany i konflikty „weź z repo” zapisują miejsce.
- Usunięcie `miejsce` w repo przy nietkniętym miejscu zeruje kolumny (przy pochowaniu oba pola).
- Pamięć zapisuje wartości z repo (`miejsce`, `cmentarz`) i ich rozpoznane id (`miejsce_id`, `cmentarz_id`, dla nowych miejsc id nadane w tym zapisie), nigdy wartości z aplikacji.

**Contract**: `markEvent(Event, string $repoId, ?ResearchDate, bool $clear = false, ?PlannedPlace $place = null, bool $clearPlace = false)` lub osobna metoda `placeEvent`. Opis metody zmieniony: miejsce zmienia się tylko według planu.

#### 4. Usuwanie i przywracanie pochowania

**File**: `app/Actions/Import/ApplyImport.php`

**Intent**: Klucz pochowania obsługuje „usuń”, „odłącz” i „przywróć”, jak zgon. Usunięcie zgonu nadal usuwa pochowanie osoby.

**Contract**: gałęzie `RepoKeyKind::Burial` w usuwaniu (`remove()`, `:592-625`) i przywracaniu. Przywracanie wybiera dziś typ jako `Birth ? Birth : Death` (`ApplyImport.php:145`), więc bez zmiany pochowanie wróciłoby jako zgon; typ bierze się z rodzaju klucza dla wszystkich trzech. Przywrócone pochowanie ustawia cmentarz przez `burialPlaceFrom`.

#### 5. Testy zapisu

**File**: `tests/Feature/ImportApplyTest.php`

**Intent**: Przypadki:
- nowe miejscowości, cmentarze i warianty zapisane raz, mimo kilku zdarzeń z tą samą nazwą,
- nowa miejscowość, której jedyna zmiana została odrzucona albo konflikt rozstrzygnięty „z aplikacji”, nie powstaje,
- zdarzenia z `locality_id`, pochowanie z cmentarzem i miejscowością cmentarza, zgon dodany przy pochowaniu,
- „bez miejsca” zostawia kolumnę i tekst w notatce,
- pochowanie bez rozpoznanego cmentarza nie tworzy wydarzenia, a jego pamięć jest zapisana,
- miejscowość usunięta po planowaniu cofa cały zapis,
- wyjątek w środku nie zostawia nowych miejscowości ani wariantów,
- drugi użytkownik z miejscowością „Zalesie”, wariantem „Bokinia” i cmentarzem: jego spis, wydarzenia i warianty nietknięte, a nic z importu nie wskazuje jego rekordów,
- drugi zapis tego samego planu po imporcie nie dubluje miejsc.

**Contract**: wzór istniejących testów zapisu.

### Success Criteria:

#### Automated Verification:

- Testy zapisu i planowania przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportApplyTest.php tests/Feature/ImportPlanTest.php`
- Testy osób i miejsc przechodzą (niezmiennik pochowania): `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/LocalitiesTest.php tests/Feature/CemeteriesTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 6: Komenda: podgląd miejsc w terminalu

### Overview

Komenda pyta o miejsca raz na nazwę, pokazuje je w podsumowaniu i w tabelach zmian i konfliktów. Reguła dla repo badawczego dostaje nowy zapis miejsc. Autor sprawdza całość na kopii `test-pack`.

### Changes Required:

#### 1. Pytania o miejsca

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Nowy krok `decidePlaces()` po `decideDeletions()`, a przed `PlanImport`:
1. Tabela „Nowe miejscowości” (Nazwa | Zdarzeń | Osoby, np. „Bukinia | 3 | Jan Nowak (O-0001), …”), potem select „Nowe miejscowości (N): co zrobić?” z `all` / `each`. Pojedynczo: „Nowa miejscowość i/n: Bukinia (3 zdarzenia)” z „Dodaj do spisu” / „Bez miejsca”. Nowe cmentarze nie mają pytania; podsumowanie podaje ich liczbę.
2. Każde niepewne: „Niepewne i/n: Bokinia (2 zdarzenia: …)” z opcjami „To Bukinia (zapamiętaj wariant)” dla każdego kandydata, „Dodaj jako nową” i „Bez miejsca” (domyślne).
3. Cmentarze do wyboru: „Cmentarz i/n: Husynne (1 pochowanie)” z cmentarzami miejscowości, „Nowy cmentarz” i „Bez miejsca” (domyślne).

Bez pytań, gdy wszystko jest pewne. Tryb nieinteraktywny z pytaniami o miejsca kończy się `needsTerminal()` jak dziś.

**Contract**: `PlaceDecisions` przekazane do `ImportDecisions`. Pomocnik `choose()` bez zmian.

#### 2. Podsumowanie i tabele

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**:
- Linie „miejsca: N (spis miejsc — S-05)” i „pochowania: N (…)” zastąpione liczbami z planu: nowe miejscowości, cmentarze, warianty, zdarzenia z miejscem i bez miejsca, pochowania.
- `describe` zna pochowanie (usunięte w repo / w aplikacji).
- `shown()` pokazuje pola miejsca w tabelach zmian i konfliktów.
- `written()` wypisuje liczbę dodanych miejsc.

**Contract**: etykiety po polsku, bez ucinania przy długich nazwach.

#### 3. Testy komendy

**File**: `tests/Feature/ImportResearchCommandTest.php`

**Intent**: Przypadki:
- nowe miejsca zatwierdzone zbiorczo,
- jedno odrzucone pojedynczo,
- niepewne zatwierdzone jako wariant (zapisany wariant i miejscowość zdarzenia),
- niepewne z domyślnym „Bez miejsca”,
- cmentarz do wyboru,
- ponowny import tej samej kopii repo kończy się „Nic do zapisania.” bez pytań o miejsca,
- tryb `--no-interaction` z niepewnym miejscem kończy się błędem bez zapisu,
- drugi użytkownik z tymi samymi miejscami nietknięty.

**Contract**: `expectsChoice` / `expectsQuestion` / `expectsConfirmation` jak w istniejących testach (sprawdzić aktualne API przez `search-docs`).

#### 4. Reguła w kontrakcie

**File**: `context/foundation/research-block-contract.md`

**Intent**: Sekcja „Reguła do wklejenia w `CLAUDE.md` repo badawczego” dostaje zapis miejsc (z Fazy 2) i krok sprawdzenia nowych miejsc w podglądzie.

**Contract**: tylko treść sekcji.

### Success Criteria:

#### Automated Verification:

- Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- Wszystkie testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchRepoReadTest.php tests/Feature/ResearchPersonMatchTest.php tests/Feature/ResearchPlaceMatchTest.php tests/Feature/ClassifyRepoRecordsTest.php tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Unit`
- Pełny zestaw testów przechodzi: `vendor/bin/sail artisan test --compact`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Przebieg na kopii `test-pack` z miejscami dopisanymi przez autora (wariant znanej miejscowości, nowa miejscowość, `pochowek` z miejscowością mającą dwa cmentarze), odpowiedź „nie” na końcu: tabele i pytania o miejsca są czytelne, pada jedno pytanie na nazwę, a podział na pewne, niepewne i nowe zgadza się z oceną autora
- Ten sam przebieg z odpowiedzią „tak” po lokalnej kopii bazy: zdarzenia w panelu osoby mają miejscowości, pochowanie ma cmentarz, nowa miejscowość jest w `/places`, a wariant widać w panelu miejscowości; bazę potem przywrócono z kopii
- Drugi przebieg na tej samej kopii kończy się „Nic do zapisania.” bez pytań o miejsca

**Implementation Note**: Po fazie i zielonych testach automatycznych poprosić autora o ręczny przebieg. Autor sam przygotowuje kopię `storage/gen-tree-import/test-pack-places/` z miejscami w blokach. Aplikacja nie pisze w repo badawczym ani w jego kopii. Przed przebiegiem: `vendor/bin/sail artisan backup:run --only-db`. Po nim: `vendor/bin/sail artisan backup:restore`.

---

## Testing Strategy

### Unit Tests:

- `PlaceNameTest`: klucz nazwy i podobieństwo na przypadkach granicznych z Fazy 3 (6 / 7 znaków, odległość 1 / 2, szyk, polskie znaki, zawieranie się nazw jako niepodobne).
- `ThreeWayValueTest` bez zmian. Miejsce podaje tokeny id jako tekst.

### Integration Tests:

- `LocalitiesTest`: warianty w scalaniu, usuwaniu, zasobie i granica drzewa.
- `ResearchRepoReadTest`: `cmentarz`, ostrzeżenia o opisie w `miejsce`, zgon z pochowania.
- `ResearchPlaceMatchTest`: werdykty miejscowości i cmentarzy, grupowanie, pomijanie nazw bez zmian, drugi użytkownik.
- `ImportPlanTest` / `ClassifyRepoRecordsTest`: reguła trzech wartości dla miejsca, pochowanie we wszystkich ścieżkach S-04, notatka bez rozpoznanych miejsc.
- `ImportApplyTest`: zapis nowych miejsc raz, niezmiennik pochowania, cofnięcie przy zmianie spisu w trakcie, drugi użytkownik.
- `ImportResearchCommandTest`: pytania o miejsca, ponowny przebieg bez pytań, tryb nieinteraktywny.

Każdy test importu ma drugiego użytkownika z tymi samymi nazwami osób i miejsc (reguła `CLAUDE.md`).

### Manual Testing Steps:

1. Autor kopiuje `test-pack` do `test-pack-places` i dopisuje:
   - `miejsce: Bokinia` przy miejscowości „Bukinia” w spisie konta testowego,
   - jedną nową miejscowość,
   - `pochowek` z miejscowością, w której są dwa cmentarze.
2. `vendor/bin/sail artisan backup:run --only-db`, potem `vendor/bin/sail artisan import:research <e-mail> storage/gen-tree-import/test-pack-places` i odpowiedź „nie”: sprawdzić tabele i pytania.
3. Ponowić z „tak” i sprawdzić panel osoby, `/places` i panel miejscowości.
4. Ponowić import: „Nic do zapisania.”.
5. `vendor/bin/sail artisan backup:restore`.

## Performance Considerations

Spis ma dziesiątki miejscowości, a repo ok. 70 różnych nazw, więc porównanie każdej nazwy z każdą miejscowością i wariantem w pamięci (Levenshtein na krótkich kluczach) jest pomijalne. Miejscowości z wariantami i cmentarzami ładowane są raz na przebieg, bez zapytań w pętli.

## Migration Notes

- Nowa tabela `locality_aliases` bez przepisywania danych, więc bez `MigrationTest`. `down()` usuwa tabelę. Produkcja robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`).
- Drzewa po S-02 do S-04 (tylko dev i konta testowe; na produkcji importu jeszcze nie było) przy pierwszym imporcie po S-05:
  - dostaną miejsca jako uzupełnienia (pamięć bez `miejsce`),
  - zobaczą raz zmianę bloku notatki (miejsca z niej znikają).
- Po S-05 i S-03 nic z roadmapy nie blokuje już pierwszego importu na produkcji.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-05), PRD: `context/foundation/prd.md` (FR-009, FR-010, FR-017)
- Kontrakt bloku: `context/foundation/research-block-contract.md`
- Plany poprzednich wycinków: `context/archive/2026-10-23-first-import-into-empty-tree/plan.md`, `context/archive/2026-10-24-first-import-matches-hand-entered/plan.md`, `context/archive/2026-10-24-reimport-changes-and-conflicts/plan.md`
- Reguła trzech wartości: `app/Support/Import/ThreeWayValue.php:22`, `app/Actions/Import/PlanImport.php:815` (`dateItem`), `:844` (`planEvent`)
- Niezmiennik pochowania: `app/Actions/Persons/MapsEventInput.php:48`
- Scalanie miejscowości: `app/Actions/Localities/MergeLocality.php:26`
- Wzór pytań w komendzie: `app/Console/Commands/ImportResearch.php:479` (`decideIdentities`), `:642` (`resolveConflicts`)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Warianty nazw miejscowości w spisie

#### Automated

- [x] 1.1 Migracja przechodzi i da się ją cofnąć — bd7fb01
- [x] 1.2 Testy miejscowości i miejsc przechodzą — bd7fb01
- [x] 1.3 Analiza statyczna przechodzi — bd7fb01
- [x] 1.4 Formatowanie — bd7fb01
- [x] 1.5 Frontend: check, types:check i test:js — bd7fb01

#### Manual

- [x] 1.6 Panel celu pokazuje nazwę duplikatu w „Inne zapisy nazwy” po scaleniu — bd7fb01

### Phase 2: Kontrakt bloku i odczyt miejsc

#### Automated

- [ ] 2.1 Testy odczytu i klasyfikacji przechodzą
- [ ] 2.2 Wszystkie testy importu przechodzą po zmianie fixture
- [ ] 2.3 Analiza statyczna przechodzi
- [ ] 2.4 Formatowanie

### Phase 3: Dopasowanie miejsc do spisu (bez zapisu)

#### Automated

- [ ] 3.1 Testy reguły i dopasowania przechodzą
- [ ] 3.2 Analiza statyczna przechodzi
- [ ] 3.3 Formatowanie

### Phase 4: Plan importu z miejscami i pochowaniem

#### Automated

- [ ] 4.1 Testy planowania, klasyfikacji i dopasowania przechodzą
- [ ] 4.2 Testy jednostkowe importu przechodzą
- [ ] 4.3 Analiza statyczna przechodzi
- [ ] 4.4 Formatowanie

### Phase 5: Zapis miejsc i pochowań

#### Automated

- [ ] 5.1 Testy zapisu i planowania przechodzą
- [ ] 5.2 Testy osób i miejsc przechodzą (niezmiennik pochowania)
- [ ] 5.3 Analiza statyczna przechodzi
- [ ] 5.4 Formatowanie

### Phase 6: Komenda: podgląd miejsc w terminalu

#### Automated

- [ ] 6.1 Testy komendy przechodzą
- [ ] 6.2 Wszystkie testy importu przechodzą
- [ ] 6.3 Pełny zestaw testów przechodzi
- [ ] 6.4 Analiza statyczna przechodzi
- [ ] 6.5 Formatowanie

#### Manual

- [ ] 6.6 Przebieg na kopii test-pack z odpowiedzią „nie”: czytelne pytania, jedno na nazwę
- [ ] 6.7 Przebieg z odpowiedzią „tak”: miejsca, pochowanie, nowa miejscowość i wariant widoczne w UI
- [ ] 6.8 Drugi przebieg kończy się „Nic do zapisania.” bez pytań o miejsca
