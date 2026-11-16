# Źródła ze skanami podpięte do faktów: plan implementacji

## Overview

`import:research` zaczyna przenosić źródła. Plik w `zrodla/` repo badawczego dostaje blok danych z listą skanów. Każdy skan ma:
- stały identyfikator `ZR-NNNN`,
- ścieżkę do pliku w repo,
- opis: rodzaj, typ aktu, rok, parafię, archiwum, sygnaturę, link i notatkę.

Fakt w bloku osoby (urodzenie, zgon, ślub) wskazuje źródła listą `zrodla: [{id, wpis}]`, gdzie `wpis` to numer wpisu. Import:
- kopiuje skan z repo do magazynu aplikacji i tworzy źródło z opisem,
- dopasowuje parafię do spisu drzewa,
- podpina źródło do faktów z numerem wpisu.

Repo badawcze jest tylko czytane. Ręcznie wgrane skany zostają.

To roadmapa S-06 (FR-001, FR-011, US-01). Decyzja z 2026-11-15: import przenosi tylko źródła ze skanem. Opis bez pliku i plik, którego aplikacja nie przyjmie, są pomijane z powodem w raporcie. Kolejny import doda źródło, gdy skan pojawi się w repo.

## Current State Analysis

**Źródło w aplikacji:**
- **Plik.** Źródło ma jeden plik skanu (`sources.path`, `original_name`, `mime_type`, `size` NOT NULL) i opcjonalną miniaturę. Plik leży na dysku `local` w `sources/{tree}/{uuid}.{ext}`, miniatura w `sources/{tree}/thumbnails/{uuid}.jpg` (`app/Actions/Sources/StoreSource.php:36,50`). Przyjmowane są tylko jpg, png i webp do 20 MB, a sprawdza to wyłącznie Form Request (`app/Http/Requests/SourceStoreRequest.php:85-86`).
- **Opis.** Pola `kind` (`SourceKind`: akt metrykalny, nagrobek, inne), `name`, `record_type` (`SourceRecordType`: urodzenie, ślub, zgon, inne), `year`, `parish_id` (FK do `parishes`), `archive` (wolny tekst), `url` i `note`. **Nie ma sygnatury ani `repo_id`.** Podpowiedź pola archiwum mówi „Np. Archiwum Diecezjalne w Tarnowie, sygnatura” (`resources/js/components/sources/source-description-fields.tsx:243`). Filtr `/sources` buduje listę wyboru z różnych wartości archiwum (`app/Http/Controllers/SourceController.php:178-190`).
- **Powiązanie z faktem.** Pivot `event_source` łączy zdarzenie ze źródłem: `entry_number` string(50), unikalne `(event_id, source_id)`, kaskada z obu stron. `LinkSourceToEvent::handle(Event, int, ?string)` nie zmienia istniejącego powiązania (`app/Actions/Events/LinkSourceToEvent.php:16`). `UpdateEventSourceEntryNumber` zmienia numer. Pochowanie nie przyjmuje źródeł (`EventType::acceptsSources`, `app/Enums/EventType.php:25-28`). Jedna rozkładówka z aktami 113–116 to jedno źródło z kilkoma powiązaniami o różnych numerach.
- **Zapis i usuwanie.** `StoreSource::handle(Tree, UploadedFile, SourceKind, array)` (`:31`) przyjmuje tylko `UploadedFile`. Spośród jego wywołań tylko `getClientOriginalName()` (`:64`) nie istnieje w `Illuminate\Http\File`. Przy błędzie sprząta własne pliki (`:81-85`). `DeleteSource` kasuje pliki od razu, nie po commicie (`app/Actions/Sources/DeleteSource.php:17-26`). `UpdateSource` zeruje każde nieprzekazane pole opisu (`app/Actions/Sources/UpdateSource.php:36`).
- **Parafie.** `MergeParish` już przenosi `sources.parish_id` (`app/Actions/Parishes/MergeParish.php:53`). `StoreParish::handle(Tree, array{locality_id, kind, religion, dedication})` zamienia naruszenie unikalności na `ValidationException` (`app/Actions/Parishes/StoreParish.php:22`).

**Import po S-01 do S-05:**
- **Odczyt.** `ReadResearchRepo` czyta tylko `drzewo/`, płasko, bez plików `_*` (`app/Actions/Import/ReadResearchRepo.php:27,95-116`). Nic nie czyta `zrodla/` ani `skany/`.
- **Klucze repo.** `RepoKeys` / `RepoKeyKind` znają osobę, urodzenie, zgon, pochowanie, parę i ślub. `kindOf` każdy nieznany klucz uznaje za osobę (`app/Support/Import/RepoKeys.php:48-58`). Wyczerpujące `match` / `switch` po rodzaju klucza stoją w:
  - `RepoValues::forKey` (`:65-92`),
  - `ApplyImport::guardRemoval` / `remove` / przywracaniu (`:600-604`, `:710-747`, `:164-169`),
  - `ClassifyRepoRecords::impactOf` (`:355-365`),
  - `ImportResearch::describe` (`:634-641`),
  - `RepoKeys::personIdsOf` (`:70-77`, bez `default`),
  - `ClassifyRepoRecords` (`:67`, `:148`, `:206-219`); `records()` (`:201-224`) ładuje tylko osoby, zdarzenia i relacje,
  - `ApplyImport` (`:92`, przywracanie `:165-168` z `default` rzucającym, `countKeptRecords` `:491-494`, `:601-603`),
  - `PlanImport` (`:357`, `:826`, `:847`, `:1079-1081`, `:1433`, `:1635`),
  - `MatchResearchPlaces` (`:176`), `PlannedRemoval`, `PlannedRestoration`, `RepoRecord` (`:35`) i `tests/Unit/RepoKeysTest.php`.
- **Pamięć.** `repo_snapshots.values` to mapa `pole => tekst|null` (`app/Models/RepoSnapshot.php`). Reguła trzech wartości to `ThreeWayValue::compare` (`app/Support/Import/ThreeWayValue.php:22-42`). Miejsca porównuje się tokenami `L:id` / `C:id` (`PlanImport.php:1159`, `:1202-1212`).
- **Miejsca.** `MatchResearchPlaces` → `PlaceDecisions` → `PlaceResolver` obsługuje miejscowości i cmentarze. Parafii nie dopasowuje nic. S-05 przekazał je do S-06 (`context/archive/2026-10-30-import-places-matched-to-gazetteer/plan.md:94`).
- **Wielkość.** `PlanImport.php` ma 1921 linii, `ApplyImport.php` 1419, `ImportResearch.php` 1093. Testy komendy sprawdzają dokładne liczniki („Pliki przejrzane: 33 …”, `saveQuestion(…)` w `tests/Feature/ImportResearchCommandTest.php:80,101`) i dokładne pola pamięci zdarzeń (`tests/Feature/ImportPlanTest.php:840,860,906`).

**Prawdziwe repo badawcze (2026-11-16, tylko odczyt):**
- **Brak bloków.** Żaden z 280 plików osób ani ze 108 opisów źródeł nie ma jeszcze bloku. Bloki z polem `wersja: 1` mają tylko kopie `test-pack` i `test-pack-places` w `storage/gen-tree-import/`, każda po 7.
- **Opisy.** Opis źródła to proza (transkrypcja, fakty, wnioski). Jeden plik obejmuje zwykle wiele aktów: ok. 780 numerów „N/RRRR” w 108 plikach. Sygnatury wyglądają tak: „AP Lublin 35/1813/0/2.4/58, skan 34”, „AGAD 1/436/852, k. 558, poz. 15”.
- **Skany w repo.** `zrodla/skany/` ma 136 plików (jpg, png, jeden webp). Akt ma zwykle rozkładówkę i folder kadrów, a jedna rozkładówka mieści kilka aktów. 53 zdjęcia AGAD są wyłączone z gita.
- **Skany poza repo.** Główny zbiór leży w folderze Windows „AKTA …”: ok. 600 JPG i 43 PDF, w tym 27 PDF-ów ślubów z Dubienki.
- **Cytowanie.** Fakty cytują akt w prozie osoby („akt 15/1837 … `zrodla/plik.md`, pkt 12”). Jeden akt potwierdza zwykle kilka faktów u kilku osób.
- **Kolizja prefiksu.** `Z-` to już identyfikatory zadań (`Z-01`…`Z-06`).

## Desired End State

Autor wpisuje bloki w repo i uruchamia `import:research`.
- **Raport** pokazuje pliki źródeł przejrzane, skany w blokach oraz skany pominięte z plikiem, ID i powodem (np. „brak skanu”, „PDF — zapisz skan jako JPG”, „plik poza repo”).
- **Pytania o miejsca** dostają nową sekcję. Nowe parafie i urzędy wchodzą w tabeli „Zatwierdź wszystkie / Przejrzyj pojedynczo”, a parafię wybiera się z listy, gdy w siedzibie jest kilka pasujących.
- **Ręczny skan.** Gdy fakt dopasowany do repo ma już ręcznie wgrane źródło zgodne typem i rokiem, komenda pyta „To ten sam skan — połącz / Dodaj jako nowe” (domyślnie „Dodaj jako nowe”).

Po zgodzie, w jednej transakcji, import:
- dodaje parafie,
- kopiuje skany do magazynu aplikacji (z miniaturą),
- tworzy źródła z opisem i sygnaturą,
- łączy wskazane ręczne źródła (plik z aplikacji zostaje, puste pola opisu dostają wartości z repo),
- podpina źródła do faktów z numerem wpisu.

Kolejny import rozpoznaje źródło po `ZR-…`:
- **Opis i powiązania** porównuje regułą trzech wartości, jak osoby i zdarzenia.
- **Nowy plik skanu** w repo (inny hash) to zmiana do zatwierdzenia.
- **Źródło usunięte w repo**: komenda pyta „Zostaw — odłącz od repo” albo „Usuń z aplikacji”.
- **Źródło usunięte w aplikacji**: komenda pyta „Zostaw usunięte” albo „Przywróć z repo”.
- **Drugi przebieg bez zmian** kończy się „Nic do zapisania.”.

Formularz i panel źródła mają pole „Sygnatura”, a archiwum zostaje samą nazwą.

Weryfikacja: testy Pest każdej warstwy z drugim użytkownikiem o tych samych parafiach, ID i plikach. Potem ręczny przebieg na kopii `test-pack-sources` przygotowanej przez autora.

### Key Discoveries:

- `Storage::putFileAs` przyjmuje `File|UploadedFile|string`, a `StoreSource` używa poza nazwą klienta tylko metod obecnych w `Illuminate\Http\File`. Wystarczy więc szerszy typ parametru i nazwa pliku z `getFilename()` (`StoreSource.php:31,64`).
- `ReadResearchRepo::resolvePair` porównuje dane ślubu z obu plików luźnym `!=` na obiektach (`:298`) i przy różnicy pomija małżeństwo. Lista `zrodla` dołączyłaby do porównania sama, więc ślub z `zrodla` wpisanym tylko u jednego małżonka (repo cytuje akt w prozie jednej osoby) zginąłby. Dlatego `resolvePair` porównuje zdarzenie bez `zrodla`, a listy scala (Faza 2 §3).
- `ClassifyRepoRecords::differsFromMemory` pomija pola kończące się na `_id` (`:309-324`). `plik_hash` będzie więc porównywany, a `parafia_id` nie.
- `ClassifyRepoRecords` grupuje klucze pod osobą przez `personIdsOf`. Klucz źródła nie ma osoby, więc trafia do `$loose` (`:105-117`) i potrzebuje własnej gałęzi stanów.
- Wstrzymanie usunięć przy pominiętym wpisie bez ID jest dziś globalne (`ClassifyRepoRecords.php:131-133`).
- `ImportPlan::sameValues` porównuje pamięć dokładnie. Klucz `zrodla` w pamięci zdarzenia dochodzi więc tylko przy zdarzeniu z powiązaniami, inaczej każde istniejące drzewo dostałoby zmianę.
- `DeletePerson` kasuje pliki w `DB::afterCommit` (`app/Actions/Persons/DeletePerson.php:41-46`). To wzór dla starych plików źródeł usuwanych przez import.
- Reguła z `CLAUDE.md`: nowa tabela odwołująca się do parafii wchodzi do scalania. Import nie dodaje tabeli, a `sources.parish_id` scalanie już obsługuje.

## What We're NOT Doing

- Nagrobki z repo (`rodzaj: nagrobek`, cmentarz, pochowani). Wpis z takim rodzajem jest pomijany z powodem „nagrobki: osobna zmiana”.
- Źródła na pochowaniu. `pochowek.zrodla` daje ostrzeżenie „nieznany klucz”, bo pochowanie w aplikacji nie przyjmuje źródeł.
- PDF-y i konwersja PDF → JPG. Pliki poza repo: autor kopiuje skan do `zrodla/skany/` (decyzja autora).
- Kilka plików na jedno źródło (kadry plus rozkładówka). Blok wskazuje jeden plik. Wiele skanów naraz to parked #39.
- Odczyt opisu, parafii, numeru wpisu ani ścieżki skanu z prozy, z tabel `## Metadane` ani z `manifest.tsv`. Wszystko niesie blok.
- Konwersja prawdziwego repo na bloki. To praca autora w repo badawczym. Aplikacja daje kontrakt, regułę do `CLAUDE.md` repo i raport.
- Przenoszenie sygnatur z istniejącego pola archiwum do nowej kolumny (bez migracji danych; autor poprawia ręcznie, jeśli zechce).
- Szukanie po sygnaturze, kolumna sygnatury w tabeli i galerii `/sources`, sygnatura jako filtr.
- Parafia na zdarzeniu (`events.parish_id`). Parafię ma tylko źródło.
- Poziomy pewności A–D (S-08) i linie boczne.
- Edycja pliku skanu w UI. `ReplaceSourceFile` służy tylko importowi.
- `wersja: 2` bloku osoby. `zrodla` to nowy opcjonalny klucz v1.

## Implementation Approach

Ten sam podział „decyzja / zapis” co w S-03 do S-05.

- **Faza 1** przygotowuje źródło w aplikacji niezależnie od importu: kolumny `signature` i `repo_id`, pole w formularzu, zapis pliku z dysku i podmianę pliku.
- **Faza 2** rozszerza kontrakt i odczyt. Nowa Action `ParseSourceBlock` czyta bloki `skany:` z `zrodla/**`, sprawdza plik skanu w repo i liczy jego hash. Blok osoby dostaje `zrodla` przy zdarzeniach. `RepoKeys` dostaje rodzaj `Source`.
- **Faza 3** dodaje dwa źródła decyzji bez zapisu:
  - werdykty parafii w torze miejsc S-05 (siedziba jak miejscowość, potem parafia jak cmentarz),
  - `MatchResearchSources`, czyli kandydatów „ten sam skan” spośród ręcznych źródeł przy dopasowanych faktach.
- **Faza 4.** `PlanImport` deleguje planowanie źródeł do nowej Action `PlanResearchSources`, żeby nie rozdmuchać pliku o 1900 liniach. Powiązania z faktami są kolejnym polem zdarzenia (`zrodla`) w istniejącym `planCompared`.
- **Faza 5.** `ApplyImport` deleguje zapis do `WriteResearchSources` w tej samej transakcji, pod blokadą drzewa i ze sprawdzeniem. Pliki skopiowane w nieudanej transakcji są kasowane, a stare pliki dopiero po commicie.
- **Faza 6** zbiera pytania, podsumowanie i wynik w komendzie.

## Critical Implementation Details

**Ścieżka skanu jest niezaufana.** `plik` to ścieżka względna od korzenia repo. Odczyt rozwiązuje ją przez `realpath` i przyjmuje tylko plik zwykły leżący wewnątrz `realpath` korzenia repo. Ścieżka bezwzględna, `..` wychodzące poza repo albo dowiązanie symboliczne poza repo to pominięcie „plik skanu poza repo”. Typ sprawdza się po rozszerzeniu i po MIME z zawartości (jpg, png, webp), a rozmiar do 20 MB, tak jak w `SourceStoreRequest`, bo `StoreSource` sam niczego nie sprawdza. Import tylko czyta i kopiuje (`putFileAs` ze strumienia). Nigdy nie przenosi ani nie usuwa pliku w repo.

**Kolejność w `ApplyImport` i pliki.** Źródła powstają po `writePlaces` (parafie muszą już istnieć). Powiązania zapisują się na końcu, przed notatkami, gdy istnieją już wszystkie nowe i przywrócone zdarzenia. Każdy plik skopiowany przez import (nowe źródło, przywrócone, nowy plik) trafia na listę. Gdy transakcja się wycofa, `try/catch` wokół `DB::transaction` w `ApplyImport::handle` kasuje te pliki i rzuca dalej. Stare pliki (podmieniony skan, usunięte źródło) kasuje `DB::afterCommit`. Import nie używa `DeleteSource`, który kasuje pliki od razu.

**Plik skanu porównuje się hashem, nie ścieżką.** Pamięć źródła trzyma `plik` (ścieżkę w repo) i `plik_hash` (sha256 zawartości).
- **Zmiana zawartości.** Aplikacja nie ma własnej podmiany pliku, więc A = S. Inny hash w repo to zawsze „zmiana” na liście zmian, a zatwierdzona podmienia plik i miniaturę przez `ReplaceSourceFile`.
- **Sama zmiana ścieżki** przy tym samym hashu aktualizuje tylko pamięć.
- **Źródło połączone z ręcznym** zapisuje hash pliku z repo. Późniejsza zmiana pliku w repo jest więc zmianą do zatwierdzenia, a autor może ją odrzucić i zachować swój skan.

**Powiązania to pole zdarzenia `zrodla`.** Pamięć zdarzenia dostaje `zrodla` jako kanoniczny tekst `ZR-0012=15/1837;ZR-0013=`, posortowany po ID, z pustym numerem po `=`. Klucz dochodzi tylko wtedy, gdy zdarzenie ma w repo powiązania albo miało je w pamięci. Reguła trzech wartości:
- R = powiązania z repo tylko ze źródłami, które po tym imporcie będą miały rekord: znane, nowe, połączone, przywrócone,
- S = `zrodla` z pamięci,
- A = powiązania zdarzenia ze źródłami, które mają `repo_id`. Ręczne powiązania nie liczą się nigdy, więc nie dają konfliktu ani nie znikają.

**Niedostępne ID tego przebiegu** to źródła:
- pominięte w odczycie,
- zostawione usunięte w aplikacji („Zostaw usunięte”),
- odłączane („Zostaw — odłącz od repo”),
- usuwane („Usuń z aplikacji”).

Decyzje o usunięciach zapadają przed planem, więc planer zna ten zbiór od początku. R, S i A pomijają niedostępne ID tak samo, więc ich powiązania nie dają pozycji „odepnij”: odłączane zostają jako ręczne, a usuwane znikają kaskadą. Pamięć `zrodla` to R po tym filtrze. Buduje ją `PlanImport` (Faza 4), nie `RepoValues::event`, więc pamięć i R się nie rozjeżdżają.

Zatwierdzona zmiana ustawia dokładnie zbiór R wśród powiązań ze źródłami z repo: dopina brakujące, odpina nadmiarowe, poprawia numer.

Wyjątki:
- Zdarzenie, którego R albo S wskazuje źródło **pominięte w tym odczycie** (np. skan chwilowo zniknął), nie jest porównywane w tym przebiegu. Nie ma pozycji, a pamięć zostaje bez zmian. Działa to dlatego, że odczyt zostawia odwołanie w `ResearchEvent` (Faza 2 §4).
- **Przywrócenie źródła** dopina jego powiązania ze zdarzeń, których R je zawiera.
- **Źródło połączone w tym przebiegu**: istniejące ręczne powiązanie dostaje numer z repo tylko wtedy, gdy jest pusty.

**Połączenie z ręcznym źródłem nie pyta o konflikty.** Połączone źródło dostaje `repo_id`, a pola opisu puste w aplikacji dostają wartość z repo. Pole z inną wartością zostaje z aplikacji bez pytania, a pamięć zapisuje wartość repo. Kolejny import widzi więc „ręczna zmiana zostaje”, dopóki repo się nie zmieni.

**Parafia jak cmentarz, tokenem `P:id`.**
- **Siedziba.** `parafia.miejscowosc` przechodzi przez werdykt miejscowości S-05 (pewna, niepewna, nowa, raz na nazwę).
- **Kandydaci.** Po decyzji o siedzibie kandydaci to parafie tej siedziby o tym samym rodzaju i wyznaniu. Parafia bez wyznania w spisie jest kandydatem, ale nigdy pewnym. Blok bez `wyznanie` ma za kandydatów parafie tej siedziby i rodzaju o dowolnym wyznaniu i nigdy nie daje werdyktu pewnego. Nowa parafia bez wyznania powstaje tylko przy braku kandydatów albo po wyborze „Dodaj nową”, inaczej obok istniejącej parafii powstałby jej duplikat bez wyznania, którego indeks unikalny nie wykryje.
- **Werdykt.**
  - Pewna: jeden kandydat z tym samym wezwaniem (bez wielkości liter i polskich znaków) albo jeden kandydat, gdy blok nie podaje wezwania.
  - Nowa: brak kandydatów.
  - W innym razie wybór z listy: kandydaci, „Dodaj nową”, „Bez parafii”.
- **Siedziba „Bez miejsca”** daje źródło bez parafii. Tekst `parafia` zostaje w pamięci, więc pytanie wraca dopiero przy zmianie w repo.

Nowe parafie wchodzą przez tabelę „Zatwierdź wszystkie / Przejrzyj pojedynczo” („Dodaj do spisu / Bez parafii”). Pamięć źródła trzyma `parafia` (kanoniczny tekst `miejscowość|wyznanie|rodzaj|wezwanie`) i `parafia_id` (token), a porównanie idzie tokenami, jak `miejsce_id`.

**Wstrzymanie usunięć tylko dla źródeł.** Plik w `zrodla/` z błędem YAML albo wpis bez czytelnego ID wstrzymuje tylko usuwanie źródeł („usunięte w repo” dla kluczy `ZR-`). Osób nie wstrzymuje. Wpis pominięty z czytelnym ID chroni tylko swoje źródło.

## Faza 1: Źródło gotowe na import

### Overview

Źródło dostaje sygnaturę (kolumna, walidacja, formularz, panel) i `repo_id`. Akcje źródeł przyjmują plik z dysku i potrafią podmienić plik. Faza nie dotyka importu.

### Changes Required:

#### 1. Migracja i model

**File**: `database/migrations/<data>_add_signature_and_repo_id_to_sources_table.php`, `app/Models/Source.php`, `database/factories/SourceFactory.php`

**Intent**: Źródło ma osobne pole sygnatury i stały klucz z repo, unikalny w drzewie. Same nowe puste kolumny, bez przepisywania danych, więc bez `MigrationTest`.

**Contract**:
- `sources.signature` string(255) nullable.
- `sources.repo_id` string nullable z `unique(['tree_id', 'repo_id'])`, tak jak w `2026_10_24_053038_add_repo_id_to_people_events_and_parent_child_relations.php`.
- `down()` usuwa indeks i obie kolumny.
- `Source`: `signature` i `repo_id` w `#[Fillable]` oraz w `@property`.
- `SourceFactory::described()` ustawia `signature`.

#### 2. Sygnatura w zapisie i odczycie

**File**: `app/Concerns/SourceDescriptionValidationRules.php`, `app/Actions/Sources/StoreSource.php`, `app/Actions/Sources/UpdateSource.php`, `app/Http/Resources/SourceResource.php`, `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Sygnatura zachowuje się jak archiwum: opcjonalna, do 255 znaków, wykluczona przy nagrobku. Przy zmianie aktu na nagrobek przechodzi do notatki linią „Sygnatura: …” obok „Archiwum: …”. `UpdateSource` czyści ją, gdy klucza brak, jak pozostałe pola opisu. Seeder demo rozdziela archiwum i sygnaturę.

**Contract**:
- Reguła `signature` (`nullable|string|max:255`, `excludeIf` nagrobek) z nazwą atrybutu „sygnatura”.
- Kształty `@param array{…signature?: string|null…}` w `StoreSource` i `UpdateSource`.
- `SourceResource` zwraca `signature`.

#### 3. Pole „Sygnatura” w formularzu i panelu

**File**: `resources/js/types/source.ts`, `resources/js/lib/source-description.ts`, `resources/js/components/sources/source-description-fields.tsx`, `resources/js/components/sources/source-panel.tsx`

**Intent**: Pod archiwum dochodzi zwykłe pole „Sygnatura” (ukryte przy nagrobku, jak archiwum). Podpowiedź archiwum traci „sygnatura” („Np. Archiwum Diecezjalne w Tarnowie”). Źródło z samą sygnaturą nie liczy się jako „bez opisu”. Panel źródła to formularz edycji, więc pokazuje pole bez osobnego widoku.

**Contract**:
- `Source.signature: string | null` w typie.
- `SourceFormData.signature`.
- `isUndescribed` / `DescriptionFields` uwzględniają `signature`.
- Tokeny i komponenty według `resources/js/CLAUDE.md`.

#### 4. Zapis pliku z dysku i podmiana pliku

**File**: `app/Actions/Sources/StoreSource.php`, nowe `app/Actions/Sources/StoreSourceFile.php`, nowe `app/Actions/Sources/ReplaceSourceFile.php`

**Intent**: Zapis pliku, wymiary z EXIF i miniatura żyją w jednym miejscu, z którego korzystają `StoreSource` i nowa podmiana.
- `StoreSource` przyjmuje też plik z dysku (`Illuminate\Http\File`). Nazwą oryginału jest wtedy nazwa pliku, a plik źródłowy zostaje na miejscu.
- `ReplaceSourceFile` zapisuje nowy plik i miniaturę, aktualizuje kolumny pliku i zwraca ścieżki starych plików. Nie kasuje ich: robi to wywołujący po commicie.
- Przy błędzie każda z akcji sprząta tylko pliki, które sama zapisała.

**Contract**:
- `StoreSource::handle(Tree, UploadedFile|File $file, SourceKind, array $description = []): Source`.
- `StoreSourceFile::handle(Tree, UploadedFile|File): array{path, thumbnail_path, original_name, mime_type, size, width, height}`.
- `ReplaceSourceFile::handle(Source, File): list<string>` (stare ścieżki).

#### 5. Testy

**File**: `tests/Feature/SourcesTest.php`, `tests/Feature/SourceFileTest.php` (nowy, `make:test --pest`), `resources/js/lib/source-description.test.ts`, `resources/js/lib/sources-index.test.ts`

**Intent**:
- **Sygnatura** w pełnym opisie przy zapisie i edycji, w zasobie, w walidacji (256 znaków), w przejściu akt → nagrobek (do notatki) i zignorowana przy nagrobku.
- **Plik z dysku:** `StoreSource` z `File` tworzy źródło z nazwą pliku i miniaturą, a plik źródłowy nadal istnieje z tą samą zawartością.
- **Podmiana:** `ReplaceSourceFile` zmienia plik, wymiary i miniaturę i zwraca stare ścieżki, które nadal istnieją.
- **Błąd zapisu:** nieudany zapis nie zostawia sierot.
- **Fikstury JS** dostają `signature: null`.
- **Kształt zasobu:** asercja `hasAll` w `tests/Feature/SourcesTest.php:51-53` dostaje `signature`. `SourceResource` trafia też do `availableSources` (`app/Http/Middleware/HandleInertiaRequests.php:88`), więc typ TS tej listy dostaje pole.
- **Cudze dane:** istniejące testy 404 cudzych źródeł przechodzą bez zmian.

**Contract**: przypadki w istniejących plikach plus nowy plik testów akcji, dane z fabryk, `Storage::fake('local')`.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi i da się ją cofnąć: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Testy źródeł przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/SourceFileTest.php tests/Feature/EventSourcesTest.php tests/Feature/OwnershipBoundariesTest.php`
- Analiza statyczna i formatowanie: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent`
- Frontend: `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run test:js`

#### Manual Verification:

- W `/sources` panel aktu ma pole „Sygnatura” pod archiwum, zapis i ponowne otwarcie pokazują wartość, a nagrobek nie ma pola. Podpowiedź archiwum nie wspomina sygnatury, a lista filtra archiwum nie zmienia się po wpisaniu sygnatury

**Implementation Note**: Po fazie i zielonych testach automatycznych poprosić autora o ręczne sprawdzenie panelu.

---

## Faza 2: Kontrakt i odczyt źródeł

### Overview

Kontrakt v1 dostaje blok źródła i klucz `zrodla` przy zdarzeniu. Odczyt repo czyta bloki źródeł, sprawdza skany i raportuje pominięcia. Komenda wypisuje to w raporcie, więc autor może sprawdzać konwersję repo odpowiedzią „nie”, zanim powstanie zapis.

### Changes Required:

#### 1. Kontrakt

**File**: `context/foundation/research-block-contract.md`

**Intent**: Dochodzi sekcja „Blok źródła” w dwóch wersjach: przykład na wymyślonych danych i tabela pól. Opis zdarzenia dostaje `zrodla`. Lista pominięć i ostrzeżeń obejmuje źródła. Reguła do `CLAUDE.md` repo badawczego dostaje punkty o źródłach:
- jeden skan = jeden wpis,
- rozkładówka z kilkoma aktami = jeden wpis z kilkoma numerami przy faktach,
- ID `ZR-` nigdy nieużywane ponownie,
- skan musi leżeć w `zrodla/skany/` jako jpg, png albo webp,
- PDF zapisz jako JPG,
- `zrodla` ślubu wystarczy wpisać u jednego małżonka; jeśli są u obojga, muszą być takie same.

Notka: dodanie kluczy to poprawka v1 (nowe opcjonalne klucze, bez zmiany znaczenia). `updated:` dostaje datę.

**Contract**: blok frontmattera pliku w `zrodla/` (rekurencyjnie, bez plików `_*`):

```yaml
---
wersja: 1                         # wymagane, tylko 1
skany:                            # wymagane, lista
  - id: ZR-0012                   # wymagane, ^ZR-\d{4,}$, stałe, nigdy nie używane ponownie
    plik: zrodla/skany/wies_1837_akt15.jpg   # ścieżka od korzenia repo; brak = wpis pominięty („brak skanu”)
    rodzaj: akt                   # wymagane: akt | inne
    typ: urodzenie                # tylko przy akt: urodzenie | slub | zgon | inne
    nazwa: Akt urodzenia 15/1837  # opcjonalne
    rok: 1837                     # opcjonalne, 1000…bieżący
    parafia:                      # tylko przy akt
      miejscowosc: Wieś Przykładowa   # wymagane w parafii; siedziba, mianownik
      wyznanie: rzymskokatolickie     # opcjonalne: rzymskokatolickie | grekokatolickie | ewangelickie | prawoslawne | mojzeszowe | inne
      rodzaj: parafia                 # opcjonalne, domyślnie parafia: parafia | usc | okreg_bozniczy | inny
      wezwanie: św. Jana              # opcjonalne
    archiwum: AP Przykładowe      # opcjonalne, sama nazwa archiwum
    sygnatura: 35/1813/0/2.4/58   # opcjonalne
    link: https://example.org/skan/34   # opcjonalne, http(s)
    notatka: "skan 34, prawa strona"    # opcjonalne
---
```

Zdarzenie w bloku osoby (`urodzenie`, `zgon`, pozycja `malzenstwa`): `zrodla: [{id: ZR-0012, wpis: "15/1837"}]`, gdzie `wpis` jest opcjonalny i ma do 50 znaków.

Kontrakt ma tabelę mapowania wartości bloku na enumy aplikacji. Pisownia jest zgodna z etykietami aplikacji, np. „grekokatolickie”.
- `rodzaj` → `SourceKind`: akt → `metrical_record`, inne → `other`.
- `typ` → `SourceRecordType`: urodzenie → `birth`, slub → `marriage`, zgon → `death`, inne → `other`.
- `parafia.wyznanie` → `Religion`: rzymskokatolickie → `roman_catholic`, grekokatolickie → `greek_catholic`, ewangelickie → `evangelical`, prawoslawne → `orthodox`, mojzeszowe → `jewish`, inne → `other`.
- `parafia.rodzaj` → `ParishKind`: parafia → `parish`, usc → `civil_registry`, okreg_bozniczy → `jewish_district`, inny → `other`.

#### 2. Odczyt bloku źródła

**File**: nowe `app/Actions/Import/ParseSourceBlock.php`, nowe DTO w `app/Support/Import/` (`ResearchSource`, `ResearchParish`, `ResearchSourceLink`, `ParsedSourceBlock`)

**Intent**: Czyta frontmatter pliku źródła i waliduje każdy wpis osobno (`Validator::make`). Zły wpis jest pomijany z plikiem, ID i powodem, a reszta pliku idzie dalej. Błąd YAML, brak albo zła `wersja` czy `skany` niebędące listą pomijają cały plik. Wyodrębnianie frontmattera korzysta z tego samego kodu co `ParsePersonBlock` (wydzielony helper, bez zmiany zachowania).

Sprawdzenie pliku skanu (zob. Critical Implementation Details) daje powody pominięcia:
- „brak skanu — źródło wejdzie, gdy skan pojawi się w repo”,
- „plik skanu nie istnieje: <ścieżka> — skopiuj skan do zrodla/skany/”,
- „plik skanu poza repo”,
- „PDF nie jest obsługiwany — zapisz skan jako JPG”,
- „nieobsługiwany format pliku (tylko jpg, png, webp)”,
- „plik skanu większy niż 20 MB”,
- „nagrobki: osobna zmiana”.

Ostrzeżenia (wpis przeczytany):
- nieznany klucz,
- `typ` lub `parafia` przy `rodzaj: inne` (pole pominięte),
- archiwum z cyfrą po przecinku („archiwum wygląda na sygnaturę”).

**Contract**:
- `ParseSourceBlock::handle(string $repoRoot, string $file, string $contents): ParsedSourceBlock`.
- `ResearchSource` niesie:
  - ID, plik bloku (ścieżka względna), `scanPath` (względna), `scanAbsolutePath`, `scanHash` (sha256),
  - `SourceKind`, `?SourceRecordType`, `?ResearchParish`,
  - nazwę, rok, archiwum, sygnaturę, link i notatkę.

#### 3. `zrodla` w bloku osoby

**File**: `app/Actions/Import/ParsePersonBlock.php`, `app/Support/Import/ResearchEvent.php`, `app/Actions/Import/ReadResearchRepo.php`

**Intent**: Urodzenie, zgon i ślub przyjmują `zrodla`:
- zły ID albo typ pomija osobę jak inne złe wartości,
- liczbowy `wpis` zamienia się na tekst,
- powtórzone ID w jednym zdarzeniu daje ostrzeżenie i zostaje pierwsze,
- lista jest sortowana po ID,
- `pochowek.zrodla` to nieznany klucz (ostrzeżenie),
- `resolvePair` porównuje ślub bez `zrodla`, a listy scala:
  - lista pusta u jednego małżonka bierze listę drugiego,
  - dwie niepuste różne listy dają ostrzeżenie z obiema listami i pomijają małżeństwo, jak dziś inne rozbieżne dane.

**Contract**: `ResearchEvent::$sources: list<ResearchSourceLink>` (ID, `?entry`). `EVENT_KEYS` + `zrodla`. Reguły walidacji `*.zrodla`, `*.zrodla.*.id` (`ZR-\d{4,}`), `*.zrodla.*.wpis` (max 50).

#### 4. Odczyt repo i raport

**File**: `app/Actions/Import/ReadResearchRepo.php`, `app/Support/Import/ResearchRepoReading.php`, `app/Console/Commands/ImportResearch.php` (tylko `report`)

**Intent**: Odczyt przechodzi rekurencyjnie `zrodla/` (pliki `.md`, bez `_*`). Brak katalogu to zero źródeł, nie błąd. Plik bez frontmattera to „bez bloku”.
- **Duplikaty.** ID w dwóch wpisach (w jednym pliku albo w różnych) pomija wszystkie te wpisy, jak duplikat osoby.
- **Ten sam plik skanu** pod dwoma ID daje ostrzeżenie.
- **Odwołania.** Odwołanie z faktu do ID bez poprawnego wpisu daje ostrzeżenie „źródło ZR-… nie istnieje albo zostało pominięte”. Odwołanie zostaje w `ResearchEvent`, a o tym, czy wchodzi do porównania, rozstrzyga planer według zbioru niedostępnych ID (Critical Implementation Details).
- **Raport** dostaje osobne wiersze („Pliki źródeł przejrzane: N (z blokiem: N, bez bloku: N)”, „Skany w blokach: N, pominięte: N”) i tabelę pominiętych skanów (plik, ID, powód). Istniejący wiersz „Pliki przejrzane…” dotyczy dalej tylko `drzewo/`.

**Contract**:
- `ResearchRepoReading` dostaje `sources` (po ID), `skippedSources`, `sourceFilesScanned`, `sourceFilesWithoutBlock` oraz metody `skippedSourceIds()` i `hasSkippedSourceWithoutId()`.
- Plik w raporcie to ścieżka względna od korzenia repo.

#### 5. Klucz źródła

**File**: `app/Support/Import/RepoKeys.php`, `app/Enums/RepoKeyKind.php`, `app/Support/Import/RepoValues.php`, każdy plik z `match` / `switch` po `RepoKeyKind` wymieniony w Current State Analysis, `tests/Unit/RepoKeysTest.php`

**Intent**: Klucz `ZR-0012` to osobny rodzaj `Source`, bez osób. `RepoValues::source` buduje pamięć źródła z odczytu: `rodzaj`, `typ`, `nazwa`, `rok`, `parafia`, `archiwum`, `sygnatura`, `link`, `notatka`, `plik`, `plik_hash`. `parafia_id` dopisuje planer, jak `miejsce_id`. `RepoValues::event` się nie zmienia: pamięć `zrodla` dopisuje `PlanImport` w Fazie 4, więc do tego czasu pamięć zdarzeń jest taka jak dziś. Gałąź `Source` dostaje każdy `match` / `switch` po `RepoKeyKind` z listy w Current State Analysis, w tym `RepoKeys::personIdsOf` bez `default` i przywracanie w `ApplyImport` z `default`, który rzuca wyjątek. Ich zachowanie podłączają fazy 4 do 6, a do tego czasu odczyt nie daje kluczy źródeł do klasyfikacji.

**Contract**: `RepoKeys::source(string): string`, `kindOf` rozpoznaje prefiks `ZR-` przed gałęzią domyślną, `personIdsOf` zwraca `[]` dla źródła, `RepoKeyKind::Source = 'source'`.

#### 6. Testy

**File**: `tests/Feature/ResearchRepoReadTest.php`, `tests/Feature/ImportPlanTest.php` (tylko gdy przesunie się pamięć)

**Intent**: Repo tymczasowe budowane w teście (wzorzec `planTemporaryRepo`), z obrazkami generowanymi w teście i bez binariów w gicie. Wspólny helper buduje repo ze skanami. Przypadki:
- poprawny blok z rozkładówką o dwóch ID i odczyt hasha,
- każdy powód pominięcia (brak `plik`, brak pliku, `../`, ścieżka bezwzględna, dowiązanie poza repo, PDF, gif, plik > 20 MB, nagrobek),
- duplikat ID w pliku i między plikami,
- plik z błędem YAML,
- podkatalog `zrodla/` i pliki `_*`,
- odwołanie do nieznanego i do pominiętego ID (ostrzeżenie, odwołanie zostaje w `ResearchEvent`),
- `pochowek.zrodla`,
- ślub:
  - te same źródła w innej kolejności: zostaje,
  - źródła tylko u jednego małżonka: zostaje z tą listą,
  - dwie różne niepuste listy: pominięty,
- każda wartość `rodzaj`, `typ`, `wyznanie` i `parafia.rodzaj` mapuje się na właściwy przypadek enumu, a wartość spoza listy (np. „greckokatolickie”) pomija wpis z powodem,
- hash wszystkich plików repo przed odczytem i po nim jest ten sam,
- istniejący fixture `tests/Fixtures/research-repo` daje te same liczniki co dziś.

**Contract**: nowe przypadki w istniejącym pliku testów odczytu, bez zmian `tests/Fixtures/research-repo`.

### Success Criteria:

#### Automated Verification:

- Testy odczytu i istniejące testy importu przechodzą bez zmiany liczników: `vendor/bin/sail artisan test --compact tests/Unit/RepoKeysTest.php tests/Feature/ResearchRepoReadTest.php tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Feature/ImportResearchCommandTest.php tests/Feature/ClassifyRepoRecordsTest.php`
- Analiza statyczna i formatowanie: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- `vendor/bin/sail artisan import:research <e-mail> <kopia test-pack-sources>` z odpowiedzią „nie” pokazuje wiersze plików źródeł i tabelę pominiętych skanów z czytelnymi powodami, a kopia repo jest niezmieniona (`git status` czysty)

**Implementation Note**: Po fazie poprosić autora o przygotowanie kopii `storage/gen-tree-import/test-pack-sources` (kilka plików `zrodla/` z blokami i skanami, osoby z `zrodla`) i o ręczne sprawdzenie raportu.

---

## Faza 3: Dopasowanie parafii i ręcznych źródeł

### Overview

Dwa źródła decyzji bez zapisu: werdykty parafii w torze miejsc S-05 i kandydaci do połączenia z ręcznie wgranymi źródłami.

### Changes Required:

#### 1. Parafie w torze miejsc

**File**: `app/Actions/Import/MatchResearchPlaces.php`, `app/Support/Import/PlaceMatches.php`, `app/Support/Import/PlaceDecisions.php`, `app/Support/Import/PlaceResolver.php`, nowe DTO parafii w `app/Support/Import/`

**Intent**:
- **Siedziby.** Siedziby parafii ze źródeł, które wymagają rozstrzygnięcia, dołączają do nazw miejscowości. Pamięć bez `parafia` albo z innym tekstem pyta, a bez zmian nie pyta (wzór `needsDecision`).
- **Werdykty.** Po decyzjach o miejscowościach `parishes()` wydaje dla każdej parafii jeden z werdyktów: brak, nowa, pewna, wybór. Reguły w Critical Implementation Details. Jedno pytanie przypada na parafię (klucz: siedziba, rodzaj, wyznanie, wezwanie), nie na źródło.
- **Resolver** zna tokeny `P:id` i nowe parafie, tak jak `C:`.

Wszystkie odczyty spisu idą przez drzewo konta.

**Contract**:
- `PlaceMatches::$parishRequests`.
- `MatchResearchPlaces::parishes(Tree, PlaceMatches, PlaceDecisions): ParishMatches`.
- `PlaceDecisions::$parishes[key] = int|'new'|'none'` i `PlaceDecisions::parishKey(...)`.
- `PlaceResolver::parish(...)`, a `tokenExists` / `tokenLabel` / `existingFromToken` obsługują `P:`.

#### 2. Kandydaci „ten sam skan”

**File**: nowe `app/Actions/Import/MatchResearchSources.php`, nowe `app/Support/Import/SourceMatches.php`, nowe `app/Support/Import/SourceDecisions.php`

**Intent**: Działa dla każdego źródła z repo, które nie ma rekordu ani pamięci (nowe).
- **Zdarzenia.** Zbiera zdarzenia aplikacji, które wskazuje przez `zrodla`: po `repo_id` albo po osobie dopasowanej w `ImportDecisions`.
- **Kandydaci** to źródła drzewa bez `repo_id`, podpięte do tych zdarzeń, niebędące nagrobkiem, o typie aktu i roku pustym albo zgodnym.
- **Wynik** to lista kandydatów na źródło. Decyzja domyślna to „nowe”, a jedno ręczne źródło można połączyć tylko z jednym źródłem z repo.

**Contract**:
- `MatchResearchSources::handle(Tree, ResearchRepoReading, RepoRecordClasses, ImportDecisions): SourceMatches`.
- `SourceDecisions::$merges[ZR-id] = source id` (brak klucza = nowe).

#### 3. Testy

**File**: `tests/Feature/ResearchPlaceMatchTest.php`, `tests/Feature/ResearchSourceMatchTest.php` (nowy)

**Intent**:
- **Parafie:**
  - pewna przy jednej zgodnej,
  - pewna po wezwaniu przy kilku,
  - wybór przy kilku bez wezwania,
  - nowa przy braku,
  - parafia bez wyznania jako kandydat niepewny,
  - blok bez wyznania przy istniejącej parafii rzymskokatolickiej daje wybór, nie nową parafię,
  - siedziba niepewna / „Bez miejsca”,
  - nowa siedziba daje nową parafię,
  - pamięć bez zmian nie pyta,
  - jedna parafia z kilku źródeł pyta raz.
- **Kandydaci:**
  - ręczne źródło przy dopasowanym urodzeniu,
  - wykluczone przy innym typie, innym roku, nagrobku, z `repo_id`,
  - nieprzypięte do faktu nie jest kandydatem.
- **Drugi użytkownik** z tymi samymi siedzibami, parafiami i ręcznymi skanami przy tych samych osobach nigdy nie jest kandydatem.

**Contract**: testy Action bezpośrednio, dane z fabryk i repo tymczasowe z fazy 2.

### Success Criteria:

#### Automated Verification:

- Testy dopasowań przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ResearchPlaceMatchTest.php tests/Feature/ResearchSourceMatchTest.php tests/Feature/ImportPlanTest.php`
- Analiza statyczna i formatowanie: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 4: Plan importu źródeł

### Overview

Plan wie, które źródła są nowe, znane, połączone, przywrócone, usunięte w repo i usunięte w aplikacji, co zmienia się w ich opisie i pliku oraz jak zmieniają się powiązania faktów.

### Changes Required:

#### 1. Klasyfikacja źródeł

**File**: `app/Actions/Import/ClassifyRepoRecords.php`, `app/Support/Import/RepoRecordClasses.php`, `app/Support/Import/RemovalImpact.php`

**Intent**: Klucze źródeł z odczytu i rekordy `sources` z `repo_id` drzewa wchodzą do stanów.
- **Stany.** Źródła mają własną gałąź poza grupowaniem pod osobą: znane, nowe, usunięte w aplikacji, usunięte w repo, „zostaw usunięte” z ponownym pytaniem po zmianie w repo.
- **Pominięte źródło** nie jest „usunięte w repo”.
- **Wstrzymanie** usunięć źródeł działa według Critical Implementation Details.
- **Skutek usunięcia źródła** podaje liczbę powiązań z faktami i informację, że plik skanu zostanie usunięty.

**Contract**: `RepoRecordClasses::$knownSources`, `$newSourceIds`; `RemovalImpact` z polem skanu; `impactOf` z gałęzią `Source`; `records()` (`:201-224`) ładuje też źródła drzewa z `repo_id`.

#### 2. Planowanie źródeł

**File**: nowe `app/Actions/Import/PlanResearchSources.php`, `app/Actions/Import/PlanImport.php`, `app/Support/Import/ImportPlan.php`, nowe DTO planu źródeł w `app/Support/Import/`

**Intent**: `PlanImport` sprawdza decyzje parafii i źródeł (wzór `checkCemeteryDecisions`), a planowanie źródeł oddaje `PlanResearchSources`. Ten tworzy:
- **nowe źródła** z opisem, parafią przez resolver i plikiem do skopiowania,
- **połączenia** (ręczne źródło, pola do uzupełnienia, pamięć z wartościami repo),
- **przywrócenia**,
- **porównanie znanych.** Każde pole opisu przechodzi przez `ThreeWayValue` (parafia tokenem, plik hashem, zob. Critical Implementation Details) i daje istniejące `PlannedChange` / `ValueConflict` / `PlannedFill` z kluczem pola `ZR-0012/<pole>`,
- **pamięć** (`snapshots`, `seenSnapshots`).

`ImportPlan` dostaje listy źródeł i `newParishes` z resolvera.

**Contract**: `PlanResearchSources::handle(Tree, ResearchRepoReading, RepoRecordClasses, SourceDecisions, PlaceResolver, array $memory): SourcePlan`. `PlanImport::handle` dostaje `SourceDecisions`. Klucze zmian pól źródła mieszczą się w istniejących `ChangeApprovals` / `ConflictResolutions`.

#### 3. Powiązania jako pole zdarzenia

**File**: `app/Actions/Import/PlanImport.php`, `app/Support/Import/RepoValues.php`

**Intent**: `planCompared` dostaje trzecią pozycję `{klucz}/zrodla` obok daty i miejsca, a reguły R, S i A są opisane w Critical Implementation Details. Nowe i przywrócone zdarzenia oraz śluby niosą powiązania do zapisu. `eventMemory` zapisuje `zrodla` jako R po filtrze niedostępnych ID, a `RepoValues::event` zostaje bez zmian. Zbiór niedostępnych ID (pominięte, zostawione usunięte, odłączane, usuwane) wyznacza `PlanResearchSources` z odczytu i decyzji o usunięciach. `planCompared` dostaje go razem z planem źródeł. `PlannedChange` / `ValueConflict` pokazują listę powiązań czytelnie („ZR-0012 (wpis 15/1837), ZR-0013”).

**Contract**: `FIELDS` + pola `*_sources`; kształt `EventPlan` z powiązaniami; `PlannedMarriage` z powiązaniami.

#### 4. Testy

**File**: `tests/Feature/ImportPlanTest.php`, `tests/Feature/ClassifyRepoRecordsTest.php`

**Intent**:
- **Pierwszy import:** nowe źródło z parafią pewną, nową i „bez parafii” oraz z powiązaniami do urodzenia i ślubu.
- **Połączenie:** puste pola uzupełnione, inne zostają, pamięć z repo.
- **Kolejny import:**
  - bez zmian (zero pozycji),
  - zmiana opisu nietkniętego,
  - ręczna zmiana zostaje,
  - konflikt,
  - nowy hash pliku jako zmiana,
  - sama zmiana ścieżki (bez pozycji),
  - nowe / usunięte / zmienione powiązanie,
  - ręczne powiązanie nieruszane,
  - źródło pominięte wstrzymuje porównanie powiązań zdarzenia, a pamięć zdarzenia zostaje bez zmian,
  - „Zostaw — odłącz od repo” zostawia powiązania bez pozycji „odepnij”,
  - „Usuń z aplikacji” i „Zostaw usunięte” nie dają pozycji odpięcia.
- **Usunięcia:** usunięte w repo i w aplikacji (w tym zostaw usunięte), wstrzymanie przy złym pliku źródła.
- **Drzewo po S-05 bez powiązań** nie dostaje żadnej zmiany pamięci zdarzeń.
- **Drugi użytkownik** z tymi samymi ID `ZR-`, parafiami i plikami pozostaje nietknięty i nie jest liczony.

**Contract**: przypadki w istniejących plikach, repo tymczasowe ze skanami.

### Success Criteria:

#### Automated Verification:

- Testy planu i klasyfikacji przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ClassifyRepoRecordsTest.php tests/Feature/ResearchSourceMatchTest.php`
- Istniejące testy importu przechodzą bez zmiany oczekiwanych pól pamięci: `vendor/bin/sail artisan test --compact tests/Feature/ImportApplyTest.php tests/Feature/ImportResearchCommandTest.php`
- Analiza statyczna i formatowanie: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 5: Zapis źródeł

### Overview

`ApplyImport` zapisuje parafie, źródła z kopią skanu, połączenia, podmiany plików, usunięcia, odłączenia, przywrócenia i powiązania. Wszystko dzieje się w jednej transakcji, a sprzątanie plików jest poprawne w obu kierunkach.

### Changes Required:

#### 1. Zapis źródeł i powiązań

**File**: nowe `app/Actions/Import/WriteResearchSources.php`, `app/Actions/Import/ApplyImport.php`, `app/Support/Import/WrittenPlaces.php`, `app/Support/Import/ImportResult.php`

**Intent**:
- **`writePlaces`** dodaje nowe parafie przez `StoreParish` po miejscowościach.
- **`WriteResearchSources`** po `writePlaces`:
  - tworzy źródła przez `StoreSource` z `File` z repo (z `repo_id` i sygnaturą),
  - łączy ręczne źródła (ustawia `repo_id`, uzupełnia puste pola),
  - aplikuje zatwierdzone zmiany pól, a plików przez `ReplaceSourceFile`,
  - **połączenie i zmiany pól idą przez `UpdateSource`** z pełnym bieżącym stanem rekordu: wszystkie pola opisu i bieżące `buried_person_ids`, nadpisane tylko polami uzupełnianymi albo zatwierdzonymi. `UpdateSource` zeruje każde nieprzekazane pole, zawsze synchronizuje `buriedPeople`, woła `FillBurialPlacesFromGravestone` i przy rodzaju innym niż akt zeruje `record_type` i `parish_id`. Nie sprawdza też drzewa dla `parish_id`, więc `parish_id` pochodzi wyłącznie z tokenów sprawdzonych w `verify`,
  - przywraca źródła.
- **Powiązania** zapisują się na końcu, przed notatkami, według R z planu. Dopięcie idzie przez `LinkSourceToEvent`, numer przez `UpdateEventSourceEntryNumber`, odpięcie przez `detach` tylko powiązań ze źródłami z `repo_id`.
- **Usunięcia.** Usunięcie w repo z wyborem „Usuń” kasuje rekord (powiązania kaskadą), a pliki po commicie. „Odłącz” zeruje `repo_id`.
- **Pamięć źródeł** z `parafia_id` uzupełnionym po utworzeniu nowych parafii. `rememberSnapshots` traktuje `parafia_id` jak inne pola id.
- **`ImportResult`** dostaje liczniki: parafie nowe, źródła nowe, połączone, zmienione, pliki podmienione, usunięte, odłączone, powiązania dopięte i odpięte.

**Contract**: kolejność opisana w Critical Implementation Details. `WriteResearchSources::handle(Tree, ImportPlan, ChangeApprovals, ConflictResolutions, WrittenPlaces, CopiedFiles): SourceWriteResult`, gdzie `CopiedFiles` to lista ścieżek do skasowania przy wycofaniu.

#### 2. Sprawdzenie pod blokadą

**File**: `app/Actions/Import/ApplyImport.php`

**Intent**:
- **`verify`** sprawdza, że źródła z planu (znane, łączone, usuwane) nadal należą do drzewa i mają oczekiwany `repo_id` (łączone: nadal bez `repo_id`), a wskazane parafie nadal istnieją w drzewie. Każde zdarzenie, do którego import dopina źródło, należy do drzewa i spełnia `acceptsSources()`. `LinkSourceToEvent` nie sprawdza ani jednego, ani drugiego (robi to tylko `EventSourceStoreRequest`).
- **`guardFreeKeys`** obejmuje `sources.repo_id`.
- **`guardRemoval`** przelicza skutek usunięcia źródła.
- **Plik skanu** z planu nadal istnieje w repo i ma ten sam hash. Inaczej import przerywa przed zapisem z komunikatem „repo zmieniło się w trakcie importu”.

**Contract**: wyjątki jak w istniejących strażnikach (`InvalidArgumentException` → FAILURE w komendzie).

#### 3. Testy

**File**: `tests/Feature/ImportApplyTest.php`

**Intent**:
- **Zapis źródeł:**
  - nowe źródło z plikiem w `sources/{tree}/`, miniaturą, opisem, sygnaturą, parafią i `repo_id`,
  - rozkładówka podpięta do dwóch faktów z różnymi numerami,
  - połączenie zachowuje plik ręczny i uzupełnia tylko puste pola.
- **Zmiany:**
  - zatwierdzona zmiana pliku podmienia plik, a stary znika po commicie,
  - odrzucona zmiana nic nie zmienia.
- **Usunięcia i przywrócenia:**
  - „Usuń” kasuje rekord i pliki, „Odłącz” zeruje `repo_id`,
  - przywrócenie kopiuje plik ponownie i dopina powiązania.
- **Wycofanie:** wymuszony błąd po skopiowaniu plików zostawia dysk bez nowych plików i bazę bez zmian, a stare pliki nadal istnieją.
- **Strażnicy:** zmiana pliku w repo między planem a zapisem przerywa import.
- **Repo** nietknięte: hashe plików przed zapisem i po nim są te same.
- **Cudze dane:** `ownershipSnapshot` drugiego użytkownika z tymi samymi danymi jest bez zmian.

**Contract**: przypadki w istniejącym pliku, `Storage::fake('local')`, repo tymczasowe ze skanami.

### Success Criteria:

#### Automated Verification:

- Testy zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportApplyTest.php`
- Testy źródeł i parafii przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/SourceFileTest.php tests/Feature/ParishesTest.php`
- Analiza statyczna i formatowanie: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 6: Komenda

### Overview

Komenda zadaje pytania o parafie i połączenia, pokazuje źródła w podsumowaniu, zmianach, usunięciach i wyniku oraz kończy „Nic do zapisania.”, gdy nic się nie zmienia.

### Changes Required:

#### 1. Pytania i wypisywanie

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**:
- **`decidePlaces`** dostaje po cmentarzach dwie sekcje parafii (wzór S-05):
  - tabelę „Nowe parafie i urzędy” (siedziba, rodzaj, wyznanie, wezwanie, liczba źródeł) z „Zatwierdź wszystkie / Przejrzyj pojedynczo” i pojedynczo „Dodaj do spisu / Bez parafii”,
  - wybór parafii, gdy jest kilka.
- **Po miejscach** każde źródło z kandydatami pyta osobno: ID, nazwa, rok, typ i fakt, wobec kandydata (nazwa pliku, typ, rok). Opcje to „Dodaj jako nowe” (domyślnie) i „Połącz z <nazwa pliku>” dla każdego kandydata.
- **Opisy pozycji.** `describe` / `repoValuesText` opisują źródło (ID, nazwa albo plik). Tabele usunięć pokazują skutek (powiązania, skan do usunięcia).
- **Podsumowanie, `nothingToWrite`, pytanie o zapis i `written`** liczą źródła, parafie i powiązania. Przykład: „Źródła: 12 nowych, 2 połączone z ręcznymi, 1 nowy plik skanu; powiązania z faktami: 30; nowe parafie: 3”.
- **`needsTerminal`** obejmuje nowe pytania.

**Contract**: pytania przez istniejące `choose()` (`Laravel\Prompts\select`). Kolejność: tożsamość → usunięte w repo → usunięte w aplikacji → miejsca (z parafiami) → źródła do połączenia → plan → zmiany → konflikty → zgoda → kopia bazy → zapis.

#### 2. Testy komendy

**File**: `tests/Feature/ImportResearchCommandTest.php`

**Intent**:
- **Pierwszy import ze źródłami:** raport pominiętych, tabela nowych parafii zatwierdzona zbiorczo, wybór parafii, pytanie o połączenie (domyślnie nowe), zgoda, wynik z licznikami. W bazie i na dysku powstają źródła.
- **Drugi przebieg bez zmian** daje „Nic do zapisania.”.
- **Kolejne przebiegi:**
  - zmiana numeru wpisu w repo jako zmiana do zatwierdzenia,
  - nowy plik skanu odrzucony pojedynczo,
  - źródło usunięte w repo z wyborem „Usuń”,
  - źródło usunięte w aplikacji z wyborem „Zostaw usunięte” (drugi przebieg nie pyta).
- **Bez terminala:** `--no-interaction` przy pytaniach o parafie kończy się błędem.
- **Cudze dane:** drugi użytkownik z tymi samymi ID, parafiami i skanami pozostaje nietknięty (`treeState`, `ownershipSnapshot`).
- **Liczniki** istniejących testów na `tests/Fixtures/research-repo` bez zmian.

**Contract**: `expectsChoice` / `expectsQuestion` / `expectsConfirmation` (składnię sprawdzić w `search-docs`, wzór istniejących testów), `fakeBackupRun()`, `commandTemporaryRepo` ze skanami.

#### 3. Reguła dla repo badawczego i roadmapa

**File**: `context/foundation/research-block-contract.md` (sekcja „Reguła do wklejenia…”)

**Intent**: Reguła do wklejenia opisuje pytania o parafie i połączenia oraz to, jak czytać raport pominiętych skanów. Literówka w siedzibie wychodzi jako nowa miejscowość albo parafia: popraw blok zamiast dodawać do spisu.

**Contract**: tylko sekcja reguły.

### Success Criteria:

#### Automated Verification:

- Testy komendy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- Pełny zestaw testów PHP przechodzi: `vendor/bin/sail artisan test --compact`
- Analiza statyczna i formatowanie: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent`
- Frontend: `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run test:js`

#### Manual Verification:

- Import kopii `test-pack-sources` na koncie testowym: raport pokazuje pominięte skany z powodami, pytania o parafie i połączenia są czytelne, a po zgodzie `/sources` pokazuje źródła ze skanem, miniaturą, parafią, archiwum i sygnaturą
- Panel osoby pokazuje przy urodzeniu i ślubie podpięte źródła z numerem wpisu, a rozkładówka jest podpięta do dwóch faktów z różnymi numerami
- Drugi przebieg bez zmian kończy się „Nic do zapisania.”, a ręcznie wgrany skan przy dopasowanej osobie zostaje
- Kopia repo badawczego jest niezmieniona po imporcie (`git status` czysty, pliki skanów na miejscu)

**Implementation Note**: Po zielonych testach poprosić autora o ręczny przebieg na `test-pack-sources` przed archiwizacją.

---

## Testing Strategy

### Unit Tests:

- Testy Action bezpośrednio, jak w S-03 do S-05:
  - `ParseSourceBlock` i odczyt repo: każdy powód pominięcia i ostrzeżenia, bezpieczeństwo ścieżki,
  - `MatchResearchPlaces` (parafie), `MatchResearchSources`,
  - `PlanResearchSources` przez `PlanImport`: reguła trzech wartości dla opisu, pliku i powiązań,
  - `StoreSourceFile` / `ReplaceSourceFile`.

### Integration Tests:

- `ApplyImport`: atomowość z plikami (wycofanie, kasowanie po commicie), strażnicy pod blokadą.
- `import:research` przez `$this->artisan(...)`: pełne przebiegi pierwszy / drugi / zmiany / usunięcia.
- W każdym teście importu drugi użytkownik z tymi samymi danymi. Asercja, że nic z jego drzewa nie jest dopasowane ani zapisane, i że pliki repo mają te same hashe.

### Manual Testing Steps:

1. Autor przygotowuje `storage/gen-tree-import/test-pack-sources`: kopię `test-pack-places` z kilkoma plikami `zrodla/` z blokami. Wśród nich rozkładówka z dwoma aktami, skan PDF, wpis bez `plik` i siedziba z istniejącą parafią. Osoby dostają `zrodla` przy urodzeniu i ślubie.
2. Uruchomienie z odpowiedzią „nie” i sprawdzenie raportu.
3. Uruchomienie z zapisem na koncie testowym. Potem sprawdzenie `/sources`, panelu osoby i `/places` („Parafie i urzędy”).
4. Drugi przebieg bez zmian, potem zmiana numeru wpisu i podmiana skanu w kopii, potem kolejny przebieg.

## Performance Considerations

Odczyt liczy sha256 każdego skanu w blokach. Przy ok. 150 plikach i do 8 MB to kilkaset MB czytania i kilka sekund. Kopiowanie i miniatury działają tylko dla nowych, przywróconych i podmienionych źródeł. Transakcja trzyma blokadę drzewa podczas kopiowania. Przy pierwszym imporcie to sekundy, co jest akceptowalne dla rzadkiej komendy jednego autora.

## Migration Notes

- Migracja dodaje dwie puste kolumny i unikalny indeks. Nie przepisuje danych, więc nie ma `MigrationTest`. Da się ją cofnąć.
- Na produkcji `scripts/deploy.sh` robi kopię bazy przed `migrate`, a import robi kopię przed zapisem. Pliki skanów nie są w kopii bazy. Przy wycofaniu transakcji import sam kasuje skopiowane pliki, a po przywróceniu bazy z kopii pliki nowszych źródeł zostają na dysku bez rekordu (do ręcznego sprzątnięcia, jak przy zwykłym uploadzie).
- Kopia repo na serwerze musi zawierać skany wyłączone z gita (AGAD). Do skopiowania służy kopia katalogu, nie `git clone`.

## References

- Roadmapa S-06 i decyzja o źródłach bez skanu: `context/foundation/roadmap.md:156-167`
- PRD: `context/foundation/prd.md:101,124-125` (FR-001, FR-011)
- Kontrakt bloku: `context/foundation/research-block-contract.md`
- Wzór planu i toru miejsc: `context/archive/2026-10-30-import-places-matched-to-gazetteer/plan.md`
- Zapis źródła: `app/Actions/Sources/StoreSource.php:31-85`; powiązanie: `app/Actions/Events/LinkSourceToEvent.php:16`
- Reguła trzech wartości: `app/Support/Import/ThreeWayValue.php:22-42`; pamięć zdarzenia: `app/Actions/Import/PlanImport.php:1202-1212`
- Pliki po commicie: `app/Actions/Persons/DeletePerson.php:41-46`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Źródło gotowe na import

#### Automated

- [x] 1.1 Migracja przechodzi i da się ją cofnąć — e4f0633
- [x] 1.2 Testy źródeł przechodzą — e4f0633
- [x] 1.3 Analiza statyczna i formatowanie — e4f0633
- [x] 1.4 Frontend: check, types:check, test:js — e4f0633

#### Manual

- [ ] 1.5 Pole „Sygnatura” w panelu aktu, brak przy nagrobku, filtr archiwum bez zmian

### Phase 2: Kontrakt i odczyt źródeł

#### Automated

- [x] 2.1 Testy odczytu i istniejące testy importu przechodzą bez zmiany liczników — 25aef2b
- [x] 2.2 Analiza statyczna i formatowanie — 25aef2b

#### Manual

- [ ] 2.3 Raport na `test-pack-sources` z odpowiedzią „nie” i niezmieniona kopia repo

### Phase 3: Dopasowanie parafii i ręcznych źródeł

#### Automated

- [x] 3.1 Testy dopasowań przechodzą — 70fbc4d
- [x] 3.2 Analiza statyczna i formatowanie — 70fbc4d

### Phase 4: Plan importu źródeł

#### Automated

- [x] 4.1 Testy planu i klasyfikacji przechodzą — d14b38b
- [x] 4.2 Istniejące testy importu przechodzą bez zmiany oczekiwanych pól pamięci — d14b38b
- [x] 4.3 Analiza statyczna i formatowanie — d14b38b

### Phase 5: Zapis źródeł

#### Automated

- [ ] 5.1 Testy zapisu przechodzą
- [ ] 5.2 Testy źródeł i parafii przechodzą
- [ ] 5.3 Analiza statyczna i formatowanie

### Phase 6: Komenda

#### Automated

- [ ] 6.1 Testy komendy przechodzą
- [ ] 6.2 Pełny zestaw testów PHP przechodzi
- [ ] 6.3 Analiza statyczna i formatowanie
- [ ] 6.4 Frontend: check, types:check, test:js

#### Manual

- [ ] 6.5 Import `test-pack-sources`: raport, pytania, źródła w `/sources` z opisem i sygnaturą
- [ ] 6.6 Panel osoby: źródła z numerem wpisu, rozkładówka przy dwóch faktach
- [ ] 6.7 Drugi przebieg „Nic do zapisania.”, ręczny skan zostaje
- [ ] 6.8 Kopia repo badawczego niezmieniona po imporcie
