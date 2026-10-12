# Miejsca faktów i pochowania ze spisu (S-07) — plan implementacji

## Overview

Autor wybiera miejsce urodzenia, ślubu, zgonu i pochowania ze spisu miejscowości, z podpowiedziami od pierwszej litery i dopisaniem brakującej miejscowości w locie. Migracja przepina istniejące tekstowe miejsca faktów (`events.place`) na miejscowości ze spisu i dosiewa brakujące. Pochowanie to nowe wydarzenie bez daty (`EventType::Burial`), edytowane w sekcji Zgon. Przy podpinaniu źródła filtr „Miejsce” wypełnia się miejscowością faktu (FR-010, FR-022; roadmapa S-07).

## Current State Analysis

- Miejsce urodzenia, zgonu i ślubu to jedna kolumna tekstowa `events.place` (`database/migrations/2026_09_23_045723_create_events_table.php:22`). `events.type` to zwykły `string` (`:17`), więc nowy typ wydarzenia nie wymaga zmiany schematu. Miejsca pochowania nie ma w kodzie.
- Spis to `localities`, unikalny `(tree_id, lower(name))` (`database/migrations/2026_10_05_081630_create_localities_table.php:24`). Seed S-01 utworzył miejscowości z `events.place` i `people.residence` (`lower(btrim)`, najczęstszy wariant), ale nie zapisał powiązania tekst → miejscowość (`database/migrations/2026_10_05_081632_seed_localities_from_place_texts.php:17-34`). Teksty wpisane po S-01 nie są w spisie.
- Zapis jest wąski i wspólny: reguła `"{$prefix}.place"` (`app/Concerns/PartialDateValidationRules.php:26`) i mapowanie `'place' => …` (`app/Actions/Persons/MapsEventInput.php:23`) obsługują urodzenie, zgon i ślub we wszystkich przepływach: `PersonStoreRequest` (dziedziczą go `PersonUpdateRequest` i trzy żądania slotów drzewa), `MarriageStoreRequest:26`, `MarriageUpdateRequest:40`. Reguły zgonu mają `prohibited_unless:is_deceased,1,true` (`app/Http/Requests/PersonStoreRequest.php:25`).
- `UpdatePerson` aktualizuje wiersze urodzenia/zgonu w miejscu (żeby nie gubić źródeł) i usuwa zgon po odznaczeniu „Osoba nie żyje” (`app/Actions/Persons/UpdatePerson.php:61-67,114-130`). Zapis osoby to pełne zastąpienie: front składa cały payload (`resources/js/lib/person-payload.ts:71-121`).
- Odczyt: `EventResource.place` (`app/Http/Resources/EventResource.php:42`) czytają `PersonResource`, `MarriageResource` (spread, `:44`), a na froncie `FactView`, `family-tab.tsx:213`, `persons-index.ts` (`formatEventMeta`). `PersonFormResource::formEvent` zwraca `place` jako wartość domyślną pola (`app/Http/Resources/PersonFormResource.php:57-68`). Eager-load wydarzeń: `BuildPersonPreview.php:27`, `PersonController.php:37`, `ListPersonCandidates.php:20`, `BuildFamilySheet.php:338`. Repo nie włącza `preventLazyLoading`, więc brak eager-loadu da N+1, a nie błąd.
- Pole „Miejsce” to jeden `<Input name="{prefix}.place">` w `resources/js/components/persons/partial-date-input.tsx:109-119`, używany przez `BirthFields`/`DeathFields`/`MarriageFields` (`person-section-fields.tsx:165-252`) w panelu osoby (`overview-tab.tsx`), modalu tworzenia (`person-create-dialog.tsx:239-242`) i dialogach slotów drzewa (`person-form-fields.tsx:43-50`). Te formularze żyją na różnych stronach.
- Picker gotowy: `SuggestField` w trybie `record` (`resources/js/components/suggest-field.tsx:23-33`), dopisanie przez `useHttp` → `POST localities/inline` → `{id, name}` (`app/Http/Controllers/InlineLocalityController.php:14-23`). Logika dopisania jest zduplikowana w `parish-panel.tsx:66-99` i `new-parish-dialog.tsx:389-405`.
- Listy ładowane na żądanie: `availableSources` jako `Inertia::optional` w `HandleInertiaRequests::share` (`app/Http/Middleware/HandleInertiaRequests.php:61-63`) + `useOptionalList` (`resources/js/hooks/use-relation-candidates.ts:15-41`).
- Podpinanie źródła: `EventSourceStoreRequest::authorize` sprawdza tylko `update` na wydarzeniu (`app/Http/Requests/EventSourceStoreRequest.php:17-20`), bez typu. `source-picker-dialog.tsx:80-85` przy otwarciu czyści „Miejsce” i wpisuje rok faktu.
- E2E: helper `tests/e2e/support/api.ts:16,105-106` wysyła `birth[place]`; `source-found-by-place-and-year.spec.ts:43` tworzy osobę z miejscem „Kraków” i sam wpisuje miejsce w pickerze.

## Desired End State

- Każde pole miejsca faktu (urodzenie, zgon, ślub) w panelu osoby, modalu tworzenia i dialogach slotów drzewa to picker miejscowości: podpowiedzi od pierwszej litery, „Dodaj miejscowość „X”” przy braku dokładnego dopasowania, bez opuszczania formularza.
- W sekcji Zgon, przy zaznaczonym „Osoba nie żyje”, jest pole „Miejsce pochowania” (picker, bez daty). Widok sekcji pokazuje miejsce pochowania. Odznaczenie zgonu lub wyczyszczenie pola usuwa pochowanie.
- Po migracji każde wydarzenie z niepustym `events.place` ma `locality_id` miejscowości z tego samego drzewa o nazwie `lower(btrim(place))`. Brakujące miejscowości są dosiane. `events.place` jest nietknięte i nigdzie nieczytane.
- Pochowania nie da się potwierdzić źródłem (404), nie pojawia się w zakładce „Źródła”.
- Picker źródła przy fakcie otwiera się z „Miejsce” = nazwa miejscowości faktu i rokiem faktu.
- Obca lub nieistniejąca miejscowość w `*.locality_id` daje błąd walidacji bez zapisu.

Weryfikacja: testy Pest (migracja, zapis, własność, pochowanie, źródła), Vitest (payload, filtr), E2E strażników F-01 i nowego ryzyka S-07, ręczny przegląd panelu.

### Key Discoveries:

- Zmiana zawartości `EventResource.place` z tekstu na nazwę miejscowości (`$this->locality?->name`) zachowuje kontrakt odczytu wszystkich konsumentów frontu. Zmienia się tylko kontrakt zapisu i `personForm`.
- `MarriageResource` spreaduje `EventResource` (`app/Http/Resources/MarriageResource.php:44`), więc ślub dostaje miejscowość automatycznie, jeśli `marriages.locality` jest załadowane.
- Seed S-01 to gotowy SQL dosiania (`2026_10_05_081632_seed_localities_from_place_texts.php:19-35`). S-07 używa go tylko dla `events.place`, bo `people.residence` należy do S-08.
- `useOptionalList` zachowuje ostatnio załadowaną listę po wizycie, która gubi optional prop (np. po błędzie walidacji) — dokładnie to, czego potrzebuje picker w dialogach.
- Wzorzec FK do spisu: `NO ACTION`, nie `restrictOnDelete`, żeby działała kaskada konto → drzewo (`create_parishes_table.php:20-22`).

## What We're NOT Doing

- Usuwanie kolumny `events.place` (późniejsza migracja, bez właściciela w roadmapie) ani wyświetlanie starego tekstu obok wyboru.
- Pisanie do `events.place` z formularzy — kolumna zostaje zamrożona w stanie z migracji.
- Notatka o niepewności miejsca (S-14).
- Próg minimalnej liczby liter w podpowiedziach.
- Data pochowania (PRD Non-Goals).
- Potwierdzanie pochowania źródłem i nagrobek jako źródło pochowania (S-09).
- Zamieszkania (`people.residence`) — S-08, także ich dosiewanie.
- Scalanie i usuwanie miejscowości oraz liczenie odwołań w spisie (S-11); S-07 dodaje tylko relację `Locality::events()` jako punkt zaczepienia.
- Składanie znaków diakrytycznych przy dopasowaniu („Krakow” ≠ „Kraków”) i naprawa miejscowości przemianowanych przed S-07 (dopasowanie utworzy je ponownie — ryzyko przyjęte w S-01, poprawka ręczna).
- Miejsce faktu na kartach drzewa i w wyszukiwaniu osób.

## Implementation Approach

Najpierw dane: nullable `events.locality_id` i przepięcie w jednej migracji z własnym testem (kopiuj najpierw, tekst zostaje). Potem backend zmienia kontrakt zapisu z `{prefix}.place` na `{prefix}.locality_id` w jednym miejscu (reguła + mapowanie), dodaje pochowanie jako czwarty typ wydarzenia przez te same akcje osoby i wystawia listę miejscowości jako współdzielony optional prop. Odczyt podmienia źródło `place` w `EventResource` na miejscowość. Front dostaje jeden komponent `LocalityPicker` (SuggestField + dopisanie w locie + ładowanie listy), który zastępuje pole tekstowe w `PartialDateInput`, obsługuje pochowanie i zastępuje zduplikowaną logikę w dwóch formularzach parafii. Na końcu E2E przez `/10x-e2e`.

## Critical Implementation Details

- **Kolejność w migracji:** najpierw kolumna, potem dosianie, potem `UPDATE` przepięcia — dosianie musi poprzedzić przepięcie, żeby teksty wpisane po S-01 też dostały FK. Dopasowanie `lower(l.name) = lower(btrim(e.place))` odpowiada semantyce unikalnego indeksu.
- **Pełne zastąpienie a pochowanie:** `personFormToPayload` musi nieść `burial.locality_id` z `personForm`, gdy osoba nie żyje. Inaczej zapis sekcji Dane osobowe albo Urodzenie po cichu usunie pochowanie (`UpdatePerson` usuwa pochowanie przy braku miejscowości).
- **Wartość przed załadowaniem listy:** `personForm` niesie `{id, name}` bieżącej miejscowości każdego faktu. Picker dokleja ją do opcji, żeby nazwa była widoczna, zanim optional prop się załaduje — `SuggestField` w trybie `record` szuka etykiety wartości w `options`.

## Faza 1: Migracja i model

### Overview

Dodaje odwołanie wydarzenia do miejscowości i przepina istniejące teksty, nie dotykając ich.

### Changes Required:

#### 1. Migracja przepięcia

**File**: `database/migrations/<timestamp>_link_event_places_to_localities.php` (`vendor/bin/sail artisan make:migration`)

**Intent**: Dodaje `events.locality_id`, dosiewa miejscowości dla tekstów `events.place` bez odpowiednika w spisie i ustawia FK każdego wydarzenia z niepustym tekstem. Spełnia „istniejące tekstowe miejsca faktów wskazują na miejscowości” od razu po wdrożeniu.

**Contract**:
- `events.locality_id` — `foreignId` nullable, `constrained('localities')` z domyślnym `NO ACTION`, indeks.
- Dosianie: SQL seeda S-01 ograniczony do `events.place` (CTE `texts` bez gałęzi `people.residence`): grupa `(tree_id, lower(btrim(place)))`, nazwa = najczęstszy przycięty wariant, remis → pierwszy w `COLLATE "C"`, `ON CONFLICT (tree_id, lower(name)) DO NOTHING`.
- Przepięcie: `UPDATE events e SET locality_id = l.id FROM localities l WHERE l.tree_id = e.tree_id AND lower(l.name) = lower(btrim(e.place)) AND e.place IS NOT NULL AND btrim(e.place) <> ''`.
- `events.place` nie jest modyfikowane.
- `down()`: usuwa wydarzenia `type = 'burial'` (bez kolumny miejscowości nie mają treści, a enum po wycofaniu kodu ich nie zna), potem kolumnę `locality_id`. Komentarz: dosiane miejscowości zostają (jak w S-01 nie da się ich odróżnić od dodanych ręcznie); wybory miejscowości zapisane po migracji przepadają, a `events.place` pokazuje stan sprzed migracji.

#### 2. Model i relacje

**File**: `app/Models/Event.php`, `app/Models/Locality.php`

**Intent**: Relacje do odczytu nazwy miejscowości faktu i, w S-11, do liczenia i przepinania odwołań.

**Contract**: `Event::locality(): BelongsTo<Locality>`; `locality_id` w `#[Fillable]` i w `@property`. `Locality::events(): HasMany<Event>`. `place` zostaje w `Fillable` (kolumna istnieje), ale nie jest już mapowane z wejścia.

#### 3. Test migracji

**File**: `tests/Feature/FactPlaceLocalityMigrationTest.php`

**Intent**: Dowodzi, że przepięcie nie gubi i nie przekręca żadnego miejsca, na wzór `tests/Feature/LocalitySeedMigrationTest.php` i `tests/Feature/SourceArchiveMigrationTest.php:31-37` (najpierw `down()`, wiersze w starym kształcie przez `DB::table`, potem `up()`).

**Contract**: Przypadki: tekst pasujący do istniejącej miejscowości różniący się wielkością liter i spacjami → FK na nią, bez nowej miejscowości; tekst bez miejscowości (wpisany po S-01) → dosiana miejscowość z najczęstszym wariantem i FK; dwa drzewa z tym samym tekstem → każde wskazuje na swoją miejscowość; `NULL`, `''`, `'  '` → `locality_id` NULL, bez miejscowości; `events.place` bajt w bajt bez zmian; wszystkie typy (urodzenie, zgon, ślub); `down()` usuwa kolumnę i wydarzenia pochowania, zostawia teksty.

### Success Criteria:

#### Automated Verification:

- Migracja i jej wycofanie przechodzą: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Test migracji przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/FactPlaceLocalityMigrationTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Na lokalnej bazie zapytanie `select count(*) from events where btrim(coalesce(place,'')) <> '' and locality_id is null` zwraca 0, a próbka wydarzeń pokazuje FK na miejscowość o tej samej nazwie (bez względu na wielkość liter).

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Backend miejsc faktów i pochowania

### Overview

Zmienia kontrakt zapisu miejsca faktu na odwołanie do spisu, dodaje pochowanie, przełącza odczyt na nazwę miejscowości i wystawia listę miejscowości formularzom.

### Changes Required:

#### 1. Reguła i mapowanie miejsca faktu

**File**: `app/Concerns/PartialDateValidationRules.php`, `app/Actions/Persons/MapsEventInput.php`

**Intent**: Jedno miejsce zmiany kontraktu dla urodzenia, zgonu i ślubu we wszystkich przepływach (osoba create/update, sloty drzewa, małżeństwo create/update).

**Contract**: `"{$prefix}.place"` zastępuje `"{$prefix}.locality_id" => ['nullable', 'integer', Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id)]` (wzór `app/Concerns/ParishValidationRules.php:95-99`); typ zwracany traitu poszerzony o obiekty reguł. `eventAttributesFrom` zwraca `locality_id` zamiast `place` — `events.place` nie jest zapisywane. Klucz `place` w żądaniu jest ignorowany. Etykiety w `attributes()` żądań: `birth.locality_id` „miejsce urodzenia”, `death.locality_id` „miejsce zgonu”, `marriage.locality_id` „miejsce ślubu”. PHPDoc kształtów `$data` w akcjach zaktualizowany.

#### 2. Pochowanie jako wydarzenie

**File**: `app/Enums/EventType.php`, `app/Models/Person.php`, `app/Actions/Persons/CreatePerson.php`, `app/Actions/Persons/UpdatePerson.php`, `app/Http/Requests/PersonStoreRequest.php`

**Intent**: Miejsce pochowania osoby (FR-022) jako wydarzenie bez daty, istniejące tylko przy zgonie i tylko z wybraną miejscowością.

**Contract**:
- `EventType::Burial = 'burial'`, etykieta „Pochowanie”.
- `Person::burial(): ?Event` na wzór `death()` (`Person.php:100-103`).
- `PersonStoreRequest`: `'burial.locality_id'` z tą samą regułą `exists` w drzewie oraz `prohibited_unless:is_deceased,1,true` (jak reguły zgonu, `:25`); etykieta „miejsce pochowania”.
- `CreatePerson`: przy zgonie i niepustym `burial.locality_id` tworzy wydarzenie `Burial` (daty puste, `date_is_approximate` false) z uczestnikiem `Subject`.
- `UpdatePerson`: przy zgonie i niepustym `burial.locality_id` aktualizuje istniejące pochowanie w miejscu albo je tworzy; przy braku miejscowości lub odznaczonym zgonie usuwa pochowanie (razem z usuwaniem zgonu, `:63-67`). `putEvent` wybiera istniejący wiersz przez `match ($type)` (Birth → `birth()`, Death → `death()`, Burial → `burial()`) zamiast ternary `:116`, który dla każdego typu innego niż urodzenie zwraca zgon — inaczej zapis pochowania nadpisałby wiersz zgonu.

#### 3. Pochowanie wyłączone ze źródeł

**File**: `app/Enums/EventType.php`, `app/Http/Requests/EventSourceStoreRequest.php`, `app/Http/Requests/EventSourceUpdateRequest.php`

**Intent**: FR-025 potwierdza źródłem tylko urodzenie, ślub i zgon; podpięcie źródła do pochowania nie może się udać nawet przez API.

**Contract**: `EventType::acceptsSources(): bool` (false dla `Burial`). `authorize()` obu żądań zwraca `Response::denyAsNotFound()` dla wydarzenia, które nie przyjmuje źródeł, przed sprawdzeniem polityki. `destroy` nie wymaga zmiany (pochowanie nie ma powiązań).

#### 4. Odczyt: nazwa miejscowości zamiast tekstu

**File**: `app/Http/Resources/EventResource.php`, `app/Http/Resources/PersonFormResource.php`, `app/Http/Resources/PersonResource.php`

**Intent**: Wszyscy konsumenci `place` widzą nazwę miejscowości, a formularze dostają `{id, name}` jako wartość domyślną pickera.

**Contract**:
- `EventResource`: `'place' => $this->locality?->name` (kształt bez zmian) plus `'locality_id'`.
- `PersonFormResource::formEvent`: `'place'` zastąpione `'locality' => {id, name}|null`; nowy klucz `'burial' => ['locality' => {id, name}|null]|null`.
- `PersonResource`: nowy klucz `'burial_place' => string|null` (nazwa miejscowości pochowania) do widoku sekcji Zgon.

#### 5. Eager-load miejscowości

**File**: `app/Actions/Persons/BuildPersonPreview.php:27`, `app/Http/Controllers/PersonController.php:37`

**Intent**: Uniknąć N+1 po przełączeniu `place` na relację.

**Contract**: `events.locality`, `marriages.locality` w każdym miejscu, które serializuje `EventResource` lub `PersonFormResource` (ten drugi czyta te same `events` z loadu `BuildPersonPreview.php:27`). `ListPersonCandidates` czyta tylko `birth()?->date` — bez zmian. Wydarzenia krewnych (`RelativeResource`) nie zwracają miejsca — bez zmian. `BuildFamilySheet` i `SheetPersonResource` nie zwracają miejsca — bez zmian. `PersonController@index` filtruje typy `[Birth, Death]` — pochowanie nie wchodzi na listę, bez zmian.

#### 6. Lista miejscowości dla formularzy

**File**: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/global.d.ts`

**Intent**: Formularze faktów żyją na kilku stronach (drzewo, lista osób, panel na dowolnej stronie), więc lista jest współdzielona i ładowana na żądanie, jak `availableSources`.

**Contract**: shared prop `localityOptions` = `Inertia::optional(...)` zwracające `{id, name}[]` miejscowości drzewa użytkownika posortowane po nazwie (kształt jak `SourceController::panelOptions`, `:89-91`); `[]` bez użytkownika. Typ w `global.d.ts`: `localityOptions?: LocalityOption[]`.

#### 7. Fabryka i seeder demo

**File**: `database/factories/EventFactory.php`, `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Dane testowe i demo w nowym kształcie; tekst `place` przestaje być czytany.

**Contract**: `EventFactory` nie wypełnia już `place`; `definition()` losuje typ tylko spośród typów z `acceptsSources()` (Birth/Death/Marriage), bo `EventSourcesTest` i `OwnershipBoundariesTest` tworzą `Event::factory()->create()` bez typu i podpinają źródła — losowe pochowanie dałoby losowe 404; pochowanie tylko przez stan `burial()`. Stan `withLocality()` tworzy miejscowość w drzewie wydarzenia i ustawia `locality_id`; stan `burial()`. Seeder zamienia teksty „Kraków”/„Nowy Targ” na miejscowości drzewa (`firstOrCreate` po nazwie) i dodaje jedno pochowanie.

#### 8. Dopisanie w locie: znajdź albo utwórz

**File**: `app/Http/Controllers/InlineLocalityController.php`, `app/Http/Requests/LocalityInlineStoreRequest.php` (nowy), `tests/Feature/LocalitiesTest.php:199-213`

**Intent**: Kilka pickerów w jednym formularzu (Zgon i Miejsce pochowania w modalu i dialogach slotów) albo nieaktualna lista nie mogą zablokować autora: „Dodaj miejscowość „X”” dla nazwy, która jest już w spisie, wybiera istniejącą miejscowość zamiast błędu „Miejscowość o tej nazwie jest już w spisie.”.

**Contract**: `POST localities/inline` przy istniejącej miejscowości drzewa o `lower(name) = lower(?)` zwraca ją jako `200 {id, name}` bez zapisu; w przeciwnym razie tworzy przez `StoreLocality` i zwraca `201` (wyścig: `ValidationException` z `StoreLocality` → ponowne wyszukanie i `200`). Nowe żądanie `LocalityInlineStoreRequest` ma regułę `name` bez sprawdzania unikalności (`bail|required|string|max:255`) i te same etykiety; `LocalityStoreRequest` (strona `/localities`) bez zmian — tam duplikat nadal jest błędem. Zmiana zachowania endpointu S-01 jest celowa: test `the inline store returns the locality as 201 JSON and a duplicate is a 422` przepisany na „duplikat (inna wielkość liter) zwraca istniejącą miejscowość jako 200, liczba miejscowości bez zmian”. Formularze parafii korzystają z tego samego wyniku przez `LocalityPicker`.

#### 9. Testy Pest

**File**: `tests/Feature/PersonsTest.php`, `tests/Feature/MarriagesTest.php`, `tests/Feature/EventSourcesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/WriteTargetsTest.php`, `tests/Feature/LocalitiesTest.php`, `tests/Pest.php`

**Intent**: Pokrycie nowego kontraktu i jego trybów awarii; istniejące asercje na `place` tekstowe przepisane na miejscowości (zmiana kontraktu jest celem tej zmiany).

**Contract**:
- Osoba: zapis `birth.locality_id`/`death.locality_id` ustawia FK, odczyt `place` = nazwa miejscowości; zapis bez klucza czyści FK; klucz `place` ignorowany i `events.place` nietknięte; pochowanie: tworzone przy zgonie z miejscowością, aktualizowane w miejscu (to samo id) bez zmiany daty, miejscowości i id wiersza zgonu, usuwane po wyczyszczeniu i po odznaczeniu zgonu, odrzucone bez zgonu (`prohibited_unless`).
- Sloty drzewa: jeden przypadek, że `birth.locality_id` przechodzi przez `PersonParentStoreRequest` (dziedziczenie reguły).
- Małżeństwo: create/update z `marriage.locality_id`.
- Źródła: podpięcie źródła do pochowania → 404 bez zapisu; aktualizacja numeru wpisu na pochowaniu → 404.
- Własność: obca i nieistniejąca miejscowość w `birth.locality_id`, `death.locality_id`, `burial.locality_id`, `marriage.locality_id` → ten sam błąd walidacji, bez zapisu (`context/foundation/test-plan.md:173`).
- `WriteTargetsTest.php`: lista kluczy payloadu osoby (`:267-316`) i payload edycji ślubu (test „editing a marriage from the preview…”, wysyła dziś `'place' => $marriage['place']`) zaktualizowane na `locality_id`.
- Helper `attachMarriage(..., ?string $place)` w `tests/Pest.php:53,61` przyjmuje miejscowość (`?Locality` albo `?int $localityId`) zamiast tekstu; jego wywołania zaktualizowane.
- Shared prop `localityOptions` ładuje tylko miejscowości własnego drzewa.

### Success Criteria:

#### Automated Verification:

- Testy domen przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/EventSourcesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/LocalitiesTest.php`
- Test migracji nadal przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/FactPlaceLocalityMigrationTest.php`
- Larastan (poziom 7) przechodzi bez nowych błędów: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`
- Wayfinder wygenerowany bez błędów: `vendor/bin/sail artisan wayfinder:generate`

**Implementation Note**: Faza nie ma kroków ręcznych; po zielonej weryfikacji przejdź do fazy 3. Front do tego czasu wysyła stary klucz `place` (ignorowany) — nie wdrażać fazy 2 bez fazy 3. Fazy 1–3 wdrażać razem: sama migracja na produkcji przed fazą 2 zostawia stary kod piszący tylko `events.place`, więc zmiana miejsca w tym oknie zostawiłaby nieaktualne `locality_id`, które faza 2 zacznie wyświetlać.

---

## Faza 3: Frontend — picker miejscowości, pochowanie, filtr źródła

### Overview

Zastępuje pole tekstowe „Miejsce” pickerem ze spisu we wszystkich formularzach faktów, dodaje „Miejsce pochowania” w sekcji Zgon i wypełnia filtr miejsca przy podpinaniu źródła.

### Changes Required:

#### 1. Wspólny picker miejscowości

**File**: `resources/js/components/localities/locality-picker.tsx` (nowy)

**Intent**: Jedno miejsce dla wyboru miejscowości z podpowiedziami i dopisania w locie, zamiast trzeciej kopii logiki z `parish-panel.tsx:66-99`.

**Contract**: props `{ id, name, label, value: LocalityOption | null, options?: LocalityOption[], errors?, onChange? }`. Gdy `options` nie podano, czyta `localityOptions` z `usePage().props` i ładuje je przez `useOptionalList('localityOptions', …)` przy montowaniu, jeśli lista nie jest jeszcze załadowana. Renderuje `SuggestField` `mode="record"`, `offerCreate="unmatched"`, `createLabel` „Dodaj miejscowość „X””; opcje = lista ∪ bieżąca wartość ∪ miejscowości dopisane w tej instancji. Dopisanie: `useHttp` → `storeInlineLocality` (bez zagnieżdżonego `<form>` i bez wizyty); odpowiedź `200` (miejscowość już w spisie) i `201` (nowa) obsługiwane tak samo — wynik trafia do opcji instancji i zostaje wybrany; błąd z `http.errors.name` albo „Nie udało się dodać miejscowości.”. Ukryte pole `name` niesie id — zgodne z wykrywaniem zmian `section-card.tsx:151-182` bez zmian w nim.

#### 2. Formularze parafii na wspólnym pickerze

**File**: `resources/js/components/parishes/parish-panel.tsx`, `resources/js/components/parishes/new-parish-dialog.tsx`

**Intent**: Usunięcie zduplikowanej logiki dopisania; zachowanie bez zmian.

**Contract**: oba przekazują swoją listę `localities` jako `options`; nazwy pól, etykiety i komunikaty bez zmian (chroni je `source-panel-parish.spec.ts`).

#### 3. Pole miejsca faktu

**File**: `resources/js/components/persons/partial-date-input.tsx`

**Intent**: „Miejsce” wybierane ze spisu dla urodzenia, zgonu i ślubu.

**Contract**: `defaultValue.place` zastąpione `defaultValue.locality: LocalityOption | null`; pole `<Input name="{prefix}.place">` zastąpione `LocalityPicker` z `name="{prefix}.locality_id"`, etykieta „Miejsce”, błąd z `errors['{prefix}.locality_id']`.

#### 4. Pochowanie w sekcji Zgon

**File**: `resources/js/components/persons/person-section-fields.tsx`, `resources/js/components/persons/overview-tab.tsx`

**Intent**: Miejsce pochowania edytowane razem ze zgonem — w panelu, modalu tworzenia i dialogach slotów drzewa, bo wszystkie używają `DeathFields`.

**Contract**: `DeathFields` dostaje prop `burial: { locality: LocalityOption | null } | null` i pod `PartialDateInput` zgonu renderuje `LocalityPicker` „Miejsce pochowania” (`name="burial.locality_id"`), tylko przy zaznaczonym zgonie. Widok sekcji Zgon w `overview-tab.tsx` pokazuje „Miejsce pochowania” z `person.burial_place`, gdy jest; sekcja jest „wypełniona”, gdy zgon ma datę, miejsce albo pochowanie. `belongsToSection('death', key)` (`overview-tab.tsx:60-61`) przyjmuje też `burial.*`, żeby błąd miejsca pochowania stał pod polem, a nie jako ogólny komunikat karty. Formularz ślubu w `overview-tab.tsx:358-362` buduje `defaultValue` jako `{...marriage.date_parts, locality: marriage.locality_id === null ? null : { id: marriage.locality_id, name: marriage.place }}` zamiast `place: marriage.place`.

#### 5. Payload pełnego zastąpienia i typy

**File**: `resources/js/lib/person-payload.ts`, `resources/js/types/person.ts`, `resources/js/components/persons/person-create-dialog.tsx`, `resources/js/components/persons/person-form-fields.tsx`

**Intent**: Nowe klucze przechodzą całą ścieżkę payloadu, żeby edycja jednej sekcji nie zerowała innej.

**Contract**: `PartialDatePayload.place` → `locality_id: number | null`; `eventPayloadFromValues` czyta `${prefix}.locality_id`; `PersonPayload.burial?: { locality_id: number | null }` — `personFormToPayload` wypełnia je z `personForm.burial` przy zgonie, `mergeSectionIntoPayload('death')` z `values['burial.locality_id']`, usuwa przy odznaczonym zgonie. Typy: `PersonFormEvent.place` → `locality: LocalityOption | null`; `PersonFormData.burial`; `Person.burial_place: string | null`; `PersonEvent.locality_id`. Modal tworzenia i dialog slotu składają `burial` tak samo.

#### 6. Filtr miejsca przy podpinaniu źródła

**File**: `resources/js/components/sources/source-picker-dialog.tsx`, `resources/js/components/persons/sources-tab.tsx`

**Intent**: Autor odnajduje akt po miejscu i roku bez wpisywania miejsca (domknięcie US-01 po S-04).

**Contract**: nowy prop `eventPlace: string | null`; `handleOpenChange` przy otwarciu ustawia `place` na `eventPlace ?? ''` zamiast `''`. `sources-tab.tsx` przekazuje `event.place`. Autor może wyczyścić pole.

#### 7. Testy Vitest

**File**: `resources/js/lib/person-payload.test.ts`, `resources/js/lib/persons-index.test.ts`

**Intent**: Regresja pełnego zastąpienia z pochowaniem.

**Contract**: zapis sekcji `personal` i `birth` zachowuje `burial.locality_id` osoby zmarłej; sekcja `death` z odznaczonym zgonem nie wysyła `death` ani `burial`; `eventPayloadFromValues` czyta `locality_id`. Fixtures `persons-index.test.ts` zaktualizowane, jeśli typy się zmieniły.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Build przechodzi: `vendor/bin/sail npm run build`
- Testy Pest z fazy 2 nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/EventSourcesTest.php`

#### Manual Verification:

- W panelu osoby (szerokość `standard`, 1366 px) edycja Urodzenia: wpisanie jednej litery pokazuje podpowiedzi, lista nie wychodzi poza panel ani nie zasłania przycisków zapisu; wybór i zapis pokazuje nazwę w widoku sekcji.
- „Dodaj miejscowość „X”” w sekcji Ślub dopisuje miejscowość, wybiera ją i po zapisie widać ją na stronie `/localities`.
- Oznaczenie zgonu z miejscem pochowania, potem edycja Danych osobowych — pochowanie zostaje; odznaczenie zgonu usuwa je.
- Modal tworzenia osoby i dialog slotu drzewa (dodaj rodzica) zapisują miejsce urodzenia ze spisu.
- Escape w otwartej liście podpowiedzi zamyka listę, nie panel.
- Picker źródła przy urodzeniu z miejscowością otwiera się z wypełnionym „Miejsce” i rokiem.

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: E2E (przez `/10x-e2e`)

### Overview

Dostosowuje strażników F-01 do nowego kontraktu i dodaje test ryzyka S-07 w przeglądarce. Prowadzone umiejętnością `/10x-e2e` (ryzyko → eksploracja → generowanie → przegląd antywzorców → weryfikacja celowym błędem).

### Changes Required:

#### 1. Kontrakt helpera API

**File**: `tests/e2e/support/api.ts`

**Intent**: Helper tworzy osoby w nowym kształcie zamiast wysyłać ignorowany tekst.

**Contract**: `PersonFields.birth.place` → `birth.locality_id?: number`; miejscowości tworzy istniejący `createLocality(page, name)` (`tests/e2e/support/api.ts:195`, zwraca id; nazwę z sufiksem testu zna wywołujący) — bez nowej funkcji. Miejscowości z E2E zostają w bazie `e2e` do S-11 (`context/foundation/test-stack.md:18`).

#### 2. Strażnicy F-01

**File**: `tests/e2e/fact-source-scan.spec.ts`, `tests/e2e/source-found-by-place-and-year.spec.ts`

**Intent**: Strażnicy działają na nowym kontrakcie; S-04 sprawdza nowe zachowanie (wypełniony filtr) w przejrzanym diffie.

**Contract**: `fact-source-scan` tworzy osobę bez miejsca albo z miejscowością z helpera. `source-found-by-place-and-year` tworzy osobę z miejscowością urodzenia = miejscowość parafii aktu i sprawdza, że picker otwiera się z tą nazwą w „Miejsce” i od razu zawęża listę do aktu; reszta asercji (rok, numer wpisu, widoczność przy fakcie i źródle) bez zmian.

#### 3. Ryzyko S-07

**File**: `tests/e2e/fact-places-from-gazetteer.spec.ts` (nowy)

**Intent**: Wybór i dopisanie miejscowości faktu oraz pochowanie działają w prawdziwym panelu osoby, a pełne zastąpienie nie gubi pochowania.

**Contract**: osoba z helpera; w panelu: Urodzenie — wpisanie nowej nazwy z sufiksem, „Dodaj miejscowość „…””, zapis, nazwa w widoku; Zgon — oznaczenie zgonu i wybór miejsca pochowania, zapis; edycja Danych osobowych i zapis; miejsce pochowania nadal widoczne. Lokatory `getByRole`/`getByLabel`, bez `waitForTimeout`, sprzątanie osób przez `cleanupBySuffix`.

### Success Criteria:

#### Automated Verification:

- Strażnicy F-01 przechodzą: `npx playwright test tests/e2e/fact-source-scan.spec.ts tests/e2e/source-found-by-place-and-year.spec.ts`
- Nowy test przechodzi: `npx playwright test tests/e2e/fact-places-from-gazetteer.spec.ts`
- Cały zestaw E2E przechodzi: `npx playwright test`

#### Manual Verification:

- Weryfikacja celowym błędem: wyłączenie przenoszenia `burial` w `personFormToPayload` czerwieni nowy test, a usunięcie wypełniania `eventPlace` czerwieni strażnika S-04.

---

## Testing Strategy

### Unit Tests:

- Vitest: `person-payload` — pełne zastąpienie z pochowaniem, odczyt `locality_id`.

### Integration Tests:

- Pest: migracja (dopasowanie, dosianie, puste, wiele drzew, nietknięte teksty, `down`), zapis miejsc i pochowania we wszystkich przepływach, reguła `prohibited_unless`, 404 przy źródle pochowania, własność `*.locality_id`, shared prop w drzewie.
- E2E: strażnicy F-01 na nowym kontrakcie + ryzyko S-07 w panelu osoby.

### Manual Testing Steps:

1. Po `migrate` na kopii danych sprawdź, że żadne wydarzenie z niepustym tekstem nie ma pustego `locality_id`.
2. W panelu osoby wybierz i dopisz miejscowość w każdej z sekcji Urodzenie, Ślub, Zgon i Miejsce pochowania.
3. Zapisz Dane osobowe osoby zmarłej z pochowaniem — pochowanie zostaje.
4. Otwórz picker źródła przy fakcie z miejscowością — „Miejsce” wypełnione.

## Performance Considerations

Lista miejscowości trafia do klienta w całości i jest filtrowana lokalnie (spis jest mały z założenia), ale tylko na żądanie — optional prop nie obciąża zwykłych wizyt. Eager-load `events.locality` dokłada jedno zapytanie na widok.

## Migration Notes

- Kopiuj najpierw, usuwaj później: `events.place` zostaje nietknięte i nieczytane; usunięcie kolumny to osobna, przyszła migracja.
- Produkcja wykonuje `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`) — bez zmian. Nowa kolumna jest objęta kopią automatycznie (`config/backup.php:46-48`); nowej tabeli nie ma, więc `BackupRestoreTest` nie wymaga zmian.
- Runbook produkcyjny (wzór `context/archive/2026-10-05-places-gazetteer/plan.md:244-263`): przed migracją policz wydarzenia z niepustym `place` i liczbę miejscowości; po migracji — wydarzenia z niepustym `place` i `locality_id IS NULL` (oczekiwane 0) oraz liczbę dosianych miejscowości (różnica). Przejrzyj dosiane nazwy pod kątem miejscowości przemianowanych przed S-07 i scal je ręcznie zmianą nazwy (do S-11 bez scalania).
- Wycofanie (`down()`) usuwa pochowania i wybory miejscowości zapisane po migracji; teksty wracają w stanie sprzed migracji.

## References

- Related research: `context/changes/fact-places-from-gazetteer/research.md`
- Plan S-01 (seed, przekazany dług): `context/archive/2026-10-05-places-gazetteer/plan.md:38-40`
- Seed SQL: `database/migrations/2026_10_05_081632_seed_localities_from_place_texts.php:17-34`
- Test migracji wzorcowy: `tests/Feature/LocalitySeedMigrationTest.php`, `tests/Feature/SourceArchiveMigrationTest.php:31-37`
- Picker i dopisanie w locie: `resources/js/components/suggest-field.tsx:23-33`, `resources/js/components/parishes/parish-panel.tsx:66-99`
- Optional prop na żądanie: `app/Http/Middleware/HandleInertiaRequests.php:61-63`, `resources/js/hooks/use-relation-candidates.ts:15-41`
- Reguła `exists` w drzewie: `app/Concerns/ParishValidationRules.php:95-99`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Migracja i model

#### Automated

- [x] 1.1 Migracja i jej wycofanie przechodzą — 42482a1
- [x] 1.2 Test migracji przechodzi — 42482a1
- [x] 1.3 Larastan przechodzi — 42482a1
- [x] 1.4 Pint czysty — 42482a1

#### Manual

- [x] 1.5 Lokalnie żadne wydarzenie z niepustym tekstem nie ma pustego locality_id — 42482a1

### Phase 2: Backend miejsc faktów i pochowania

#### Automated

- [x] 2.1 Testy domen przechodzą — e0642f4
- [x] 2.2 Test migracji nadal przechodzi — e0642f4
- [x] 2.3 Larastan (poziom 7) przechodzi bez nowych błędów — e0642f4
- [x] 2.4 Pint czysty — e0642f4
- [x] 2.5 Wayfinder wygenerowany bez błędów — e0642f4

### Phase 3: Frontend — picker miejscowości, pochowanie, filtr źródła

#### Automated

- [x] 3.1 Format i lint przechodzą — 494583c
- [x] 3.2 Typy przechodzą — 494583c
- [x] 3.3 Vitest przechodzi — 494583c
- [x] 3.4 Build przechodzi — 494583c
- [x] 3.5 Testy Pest z fazy 2 nadal przechodzą — 494583c

#### Manual

- [x] 3.6 Podpowiedzi od jednej litery w panelu 1366 px, wybór i zapis pokazują nazwę — 494583c
- [x] 3.7 Dodaj miejscowość w sekcji Ślub dopisuje i wybiera miejscowość — 494583c
- [x] 3.8 Pochowanie zostaje po edycji Danych osobowych i znika po odznaczeniu zgonu — 494583c
- [x] 3.9 Modal tworzenia i dialog slotu drzewa zapisują miejsce urodzenia ze spisu — 494583c
- [x] 3.10 Escape zamyka listę podpowiedzi, nie panel — 494583c
- [x] 3.11 Picker źródła otwiera się z wypełnionym miejscem i rokiem — 494583c

### Phase 4: E2E (przez /10x-e2e)

#### Automated

- [x] 4.1 Strażnicy F-01 przechodzą — 5fee7cd
- [x] 4.2 Nowy test przechodzi — 5fee7cd
- [x] 4.3 Cały zestaw E2E przechodzi — 5fee7cd

#### Manual

- [x] 4.4 Weryfikacja celowym błędem czerwieni nowy test i strażnika S-04 — 5fee7cd
