# Kilka zamieszkań z latami (S-08) — plan implementacji

## Overview

Autor ustawia osobie kilka miejscowości zamieszkania ze spisu z opcjonalnymi latami od–do (FR-011, roadmapa S-08). Dotychczasowe tekstowe pole „Zamieszkanie” (`people.residence`) staje się pierwszą pozycją listy, bez lat. Lista jest edytowana w nowej karcie „Zamieszkania” w panelu osoby i zapisywana razem z osobą (`PUT persons/{id}`, pełne zastąpienie). Zamieszkania nie potwierdza się źródłem (PRD Non-Goals).

## Current State Analysis

- Zamieszkanie to jedna kolumna `people.residence` (varchar 255, nullable, `database/migrations/2026_09_23_045722_create_people_table.php:19`). Brak tabeli zamieszkań i relacji do miejscowości (`app/Models/Person.php:25,30`).
- Seed S-01 utworzył miejscowości m.in. z `people.residence` (`database/migrations/2026_10_05_081632_seed_localities_from_place_texts.php:18-23`), ale niczego nie powiązał. Teksty wpisane po S-01 mogą nie mieć miejscowości. S-07 jawnie przekazał to dosianie do S-08 (`context/archive/2026-10-06-fact-places-from-gazetteer/plan.md:47`).
- Wzorzec migracji z S-07: FK + indeks → dosianie (SQL z S-01 zawężony do jednego źródła, `ON CONFLICT (tree_id, lower(name)) DO NOTHING`) → powiązanie po `lower(l.name) = lower(btrim(tekst))` w drzewie; tekst źródłowy nietknięty (`database/migrations/2026_10_06_070850_link_event_places_to_localities.php:18-48`). Po migracji S-07 przestał pisać `events.place` (`app/Actions/Persons/MapsEventInput.php:17-24`).
- Zapis: reguła `'residence' => ['nullable','string','max:255']` w `PersonStoreRequest.php:43` (dziedziczą ją `PersonUpdateRequest` i trzy żądania slotów drzewa); `CreatePerson.php:53` i `UpdatePerson.php:58` piszą tekst. `UpdatePerson` to pełne zastąpienie — brak klucza `burial` usuwa pochowanie (`UpdatePerson.php:64-71`).
- Odczyt: `PersonResource.php:51` i `PersonFormResource.php:47` zwracają tekst; panel ładuje osobę w `app/Actions/Persons/BuildPersonPreview.php:27` (jedyny użytkownik `PersonFormResource`).
- Front: pole `Input name="residence"` w `PersonalFields` (`resources/js/components/persons/person-section-fields.tsx:151-160`), używanym w karcie „Dane osobowe”, dialogu „Dodaj osobę” i dialogach slotów drzewa; widok `Field "Zamieszkanie"` (`overview-tab.tsx:294-297`); kolumna w tabeli osób (`persons-table.tsx:128,163-169`); `PERSONAL_KEYS` z `'residence'` (`overview-tab.tsx:41-48`).
- Panel spłaszcza `FormData` do `Record<string,string>` (`section-card.tsx:31-41`) i buduje payload w `resources/js/lib/person-payload.ts:72-129`; `SectionId` / `PersonSection` to zamknięte unie (`section-editor.tsx:13`, `person-payload.ts:7`). Strażnik niezapisanych zmian porównuje serializację `FormData` i obserwuje `childList` (`section-card.tsx:149-182`).
- `LocalityPicker` (`resources/js/components/localities/locality-picker.tsx`) trzyma wybrane id w stanie lokalnym (`:43-45`), miejscowości dopisane w jednej instancji nie trafiają do innych (`:46,87`), a bez `options` każda instancja sama ładuje `localityOptions` (`:50-57`). Wzorzec współdzielonego `createdLocalities`: `resources/js/components/sources/source-description-fields.tsx:62-82`.
- Testy z dokładnymi listami tabel: `tests/Feature/WriteTargetsTest.php:267-292` (update `residence`), `tests/Feature/DeleteImpactTest.php:80-82`; `tests/Backup/BackupRestoreTest.php:25-26` wymaga wierszy w każdej tabeli (fixture `tests/BackupTestCase.php:129`).
- Nie ma trasy usuwania miejscowości (`localities.index|store|update|inline.store`), więc nic nie liczy dziś odwołań do spisu.

## Desired End State

- Tabela `residences`: osoba → wiele wierszy (miejscowość ze spisu tego samego drzewa, `year_from`, `year_to` opcjonalne). Ta sama miejscowość może wystąpić kilka razy.
- Po migracji każda osoba z niepustym `people.residence` ma dokładnie jedno zamieszkanie bez lat, wskazujące miejscowość o nazwie `lower(btrim(residence))` z jej drzewa; brakujące miejscowości są dosiane. `people.residence` jest nietknięte, nigdzie nieczytane i niezapisywane.
- Panel osoby ma kartę „Zamieszkania” pod „Danymi osobowymi”: widok listy (nazwa miejscowości + lata), edycja wierszy (picker miejscowości z „Dodaj miejscowość „X””, rok od, rok do, usuń, „Dodaj zamieszkanie”), zapis razem z osobą.
- Kolejność listy: po roku sortowania = `year_from`, a gdy brak — `year_to`, rosnąco; pozycje bez lat na końcu; przy równym roku (także między pozycjami bez lat) — kolejność dodania.
- Walidacja: całkiem pusty wiersz jest pomijany; wiersz z latami bez miejscowości → „Wybierz miejscowość.” pod polem tego wiersza; lata w zakresie 1000 – bieżący rok; gdy oba lata podane — od ≤ do. Obca lub nieistniejąca miejscowość → błąd walidacji bez zapisu.
- Zapis innej sekcji panelu nie zmienia zamieszkań (ani nie przepisuje ich wierszy). Payload bez klucza `residences` czyści listę (pełne zastąpienie, jak pochowanie).
- Pole „Miejsce zamieszkania” znika z „Danych osobowych”, dialogu „Dodaj osobę” i dialogów slotów drzewa; kolumna „Zamieszkanie” znika z tabeli osób.

Weryfikacja: Pest (migracja, zapis, kolejność, walidacja, własność, cele zapisu i usuwania, kopia), Vitest (payload, format lat), E2E ryzyka S-08, ręczny przegląd panelu.

### Key Discoveries:

- Zamieszkanie nie jest wydarzeniem (bez źródła i daty dziennej, `context/foundation/prd.md:241`), więc dostaje własną tabelę zamiast `events` — nie wchodzi w ścieżki potwierdzania faktów.
- Sortowanie w PHP ma precedens: `app/Support/PersonOrdering.php` (`marriages()`, jawne miejsce dla braków). Kolumna pozycji nie jest potrzebna, bo kolejność dodania = rosnące `id`.
- Rok jako `unsignedSmallInteger` nullable ma precedens w `events.date_year`; zakres `between:1000,{bieżący rok}` w `app/Concerns/PartialDateValidationRules.php:20-23`.
- FK do spisu: domyślne `NO ACTION` (kaskada konto → drzewo → miejscowości w jednym poleceniu, `create_parishes_table.php:20-22`) i jawny indeks (S-07 impl-review F1).
- Inertia `router.put` wysyła JSON, więc `residences` dociera jako tablica obiektów; dialogi tworzenia nie wysyłają zamieszkań wcale.

## What We're NOT Doing

- Usuwanie kolumny `people.residence` (tak jak `events.place` — przyszła migracja bez właściciela w roadmapie) ani pokazywanie starego tekstu obok listy.
- Zamieszkania w dialogu „Dodaj osobę” i dialogach slotów drzewa — tylko w panelu (decyzja autora 4).
- Potwierdzanie zamieszkań źródłem, daty dzienne, „ok.” przy latach, notatka do zamieszkania.
- Ręczne ustawianie kolejności (kolumna pozycji, przeciąganie).
- Unikalność (osoba, miejscowość) — duplikaty dozwolone (decyzja autora 5).
- Zamieszkania na kartach drzewa, w zakładce rodziny, w wyszukiwaniu i filtrach listy osób.
- Scalanie i usuwanie miejscowości (S-11); S-08 dodaje tylko relację `Locality::residences()` jako punkt zaczepienia.
- Składanie diakrytyków przy dopasowaniu („Krakow” ≠ „Kraków”) i naprawa miejscowości przemianowanych przed S-08 — ryzyko przyjęte w S-01.
- Policy dla `Residence`: model nie ma własnych tras; dostęp tylko przez `persons.update` autoryzowane `PersonPolicy`.

## Implementation Approach

Najpierw dane: tabela `residences`, dosianie i kopia tekstów w jednej migracji z własnym testem (kopiuj najpierw, tekst zostaje). Potem backend: `PersonUpdateRequest` przyjmuje listę `residences`, `UpdatePerson` ją zastępuje (bez zapisu, gdy się nie zmieniła), zasoby zwracają posortowaną listę, a stary klucz `residence` przestaje być przyjmowany i zapisywany. Front dostaje kartę „Zamieszkania” z edytorem wierszy na `LocalityPicker` (jedna lista miejscowości i wspólne dopisane miejscowości dla wszystkich wierszy), a payload pełnego zastąpienia zawsze niesie listę. Na końcu E2E przez `/10x-e2e`.

## Critical Implementation Details

- **Indeksy błędów a pomijanie pustych wierszy:** front wysyła wszystkie wiersze w kolejności widoku (indeks = pozycja wiersza w karcie), a serwer w `prepareForValidation` usuwa całkiem puste wiersze **z zachowaniem kluczy**. Dzięki temu błąd `residences.3.locality_id` trafia pod czwarty wiersz karty. Akcja używa `array_values` dopiero po walidacji. Filtrowanie na froncie przesunęłoby indeksy i błąd stanąłby pod złym wierszem.
- **Kolejność dodania przy pełnym zastąpieniu:** zapis usuwa wiersze osoby i wstawia nowe w kolejności z payloadu, więc `id` rosną w kolejności widoku (nowe wiersze na końcu). Remis w sortowaniu rozstrzyga `id`, więc względna kolejność pozycji o równym roku przetrwa każdy zapis. Gdy przesłana lista (`locality_id`, `year_from`, `year_to` w kolejności) jest równa bieżącej posortowanej liście, zapis nic nie robi — inaczej każdy zapis „Danych osobowych” przepisywałby wiersze zamieszkań (`WriteTargetsTest`).
- **Pełne zastąpienie:** `personFormToPayload` musi zawsze nieść `residences` z `personForm` — brak klucza czyści listę na serwerze (ten sam błąd co S-07 F2 dla pochowania).
- **Wdrożenie razem:** fazy 1–3 wdrażać jednocześnie. Sama migracja przed kodem zostawia stary formularz piszący tylko tekst, którego nowa lista nie zobaczy.

## Faza 1: Migracja i model

### Overview

Tworzy tabelę zamieszkań i przenosi do niej istniejące teksty, nie dotykając ich.

### Changes Required:

#### 1. Migracja tabeli i kopii

**File**: `database/migrations/<timestamp>_create_residences_from_residence_texts.php` (`vendor/bin/sail artisan make:migration`)

**Intent**: Tworzy `residences`, dosiewa miejscowości dla tekstów `people.residence` bez odpowiednika w spisie i tworzy jedno zamieszkanie bez lat na każdą osobę z niepustym tekstem — spełnia „dotychczasowe pole jest pierwszą pozycją listy, bez lat” od razu po wdrożeniu.

**Contract**:
- `residences`: `id`; `tree_id` — `foreignId` `constrained()->cascadeOnDelete()` + indeks; `person_id` — `constrained('people')->cascadeOnDelete()` + indeks; `locality_id` — `constrained('localities')` z domyślnym `NO ACTION` + indeks; `year_from`, `year_to` — `unsignedSmallInteger` nullable; `timestamps`. Bez indeksu unikalnego.
- Dosianie: SQL z `link_event_places_to_localities.php:25-39` z CTE `texts` czytającym `people.residence` zamiast `events.place` (grupa `(tree_id, lower(btrim))`, najczęstszy wariant, remis → `COLLATE "C"`, `ON CONFLICT (tree_id, lower(name)) DO NOTHING`).
- Kopia: `INSERT INTO residences (tree_id, person_id, locality_id, created_at, updated_at) SELECT p.tree_id, p.id, l.id, now(), now() FROM people p JOIN localities l ON l.tree_id = p.tree_id AND lower(l.name) = lower(btrim(p.residence)) WHERE p.residence IS NOT NULL AND btrim(p.residence) <> '' ORDER BY p.id`. Lata NULL.
- `people.residence` nie jest modyfikowane.
- `down()`: usuwa tabelę. Komentarz: dosiane miejscowości zostają (jak w S-01/S-07 nie da się ich odróżnić od dodanych ręcznie); zamieszkania zapisane po migracji przepadają, a `people.residence` pokazuje stan sprzed migracji.

#### 2. Model i relacje

**File**: `app/Models/Residence.php` (nowy, `make:model`), `app/Models/Person.php`, `app/Models/Locality.php`, `database/factories/ResidenceFactory.php` (nowy), `database/factories/PersonFactory.php`

**Intent**: Relacje do zapisu i odczytu listy oraz, w S-11, do przepinania odwołań przy scalaniu miejscowości.

**Contract**:
- `Residence`: `#[Fillable(['tree_id','person_id','locality_id','year_from','year_to'])]`, `@property` dla kolumn, casty `year_from`/`year_to` → `integer`; `tree()`, `person()`, `locality()` — `BelongsTo`.
- `Person::residences(): HasMany<Residence>`; `residence` zostaje w `Fillable` i `@property` (kolumna istnieje), ale nie jest mapowane z wejścia — jak `place` w `Event` po S-07.
- `Locality::residences(): HasMany<Residence>`.
- `ResidenceFactory`: osoba z fabryki, `tree_id` z osoby, miejscowość w tym samym drzewie, lata NULL; stan `years(?int $from, ?int $to)`.
- `PersonFactory`: domyślny stan nie wypełnia już `residence` (tekst jest zamrożony i nieczytany, jak `place` w `EventFactory` po S-07).

#### 3. Test migracji

**File**: `tests/Feature/ResidenceMigrationTest.php`

**Intent**: Dowodzi, że przeniesienie nie gubi i nie przekręca żadnego zamieszkania, na wzór `tests/Feature/FactPlaceLocalityMigrationTest.php` (najpierw `down()`, wiersze w starym kształcie przez `DB::table`, potem `up()`).

**Contract**: Przypadki: tekst różniący się wielkością liter i spacjami od istniejącej miejscowości → zamieszkanie na nią, bez nowej miejscowości; tekst bez miejscowości → dosiana miejscowość z najczęstszym wariantem i zamieszkanie; ten sam tekst w dwóch drzewach → każde drzewo wskazuje swoją miejscowość; `NULL`, `''`, `'  '` → brak zamieszkania i brak miejscowości; dokładnie jedno zamieszkanie na osobę, oba lata NULL; `people.residence` bajt w bajt bez zmian; `down()` usuwa tabelę i zostawia teksty.

### Success Criteria:

#### Automated Verification:

- Migracja i jej wycofanie przechodzą: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Test migracji przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/ResidenceMigrationTest.php`
- Testy migracji S-01 i S-07 nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LocalitySeedMigrationTest.php tests/Feature/FactPlaceLocalityMigrationTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Na lokalnej bazie `select count(*) from people p where btrim(coalesce(p.residence,'')) <> '' and not exists (select 1 from residences r where r.person_id = p.id)` zwraca 0, a próbka zamieszkań wskazuje miejscowość o tej samej nazwie co tekst (bez względu na wielkość liter).

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Backend zapisu i odczytu zamieszkań

### Overview

Zmienia kontrakt zapisu z tekstu `residence` na listę `residences` (tylko edycja osoby), zastępuje listę w `UpdatePerson`, zwraca posortowaną listę w zasobach panelu i dostosowuje testy różnicowe.

### Changes Required:

#### 1. Walidacja listy

**File**: `app/Http/Requests/PersonStoreRequest.php`, `app/Http/Requests/PersonUpdateRequest.php`

**Intent**: Lista zamieszkań przyjmowana tylko przy edycji osoby (decyzja autora 4); stary klucz `residence` nie jest już przyjmowany w żadnym przepływie.

**Contract**:
- `PersonStoreRequest`: usunięte reguła i etykieta `residence` (dziedziczące żądania slotów drzewa też ich nie mają; klucz w treści jest ignorowany).
- `PersonUpdateRequest::rules()` = reguły rodzica + `residences` → `['nullable','array']`, `residences.*` → `['array']`, `residences.*.locality_id` → `['required','integer', Rule::exists('localities','id')->where('tree_id', <drzewo użytkownika>)]`, `residences.*.year_from` i `residences.*.year_to` → `['nullable','integer',"between:1000,{bieżący rok}"]` (ten sam rok co `PartialDateValidationRules.php:20`).
- `prepareForValidation`: z `residences` usuwa wiersze, w których `locality_id`, `year_from` i `year_to` są puste (`null` albo `''`), **zachowując klucze** (patrz Critical Implementation Details).
- `withValidator` (po `parent::withValidator`): dla każdego wiersza z oboma latami i `year_from > year_to` → błąd na `residences.{i}.year_to` „Rok „do” nie może być wcześniejszy niż rok „od”.”.
- Komunikat `residences.*.locality_id.required` → „Wybierz miejscowość.”; etykiety: `residences.*.locality_id` „miejscowość zamieszkania”, `residences.*.year_from` „rok od”, `residences.*.year_to` „rok do”.

#### 2. Zapis listy

**File**: `app/Actions/Persons/UpdatePerson.php`, `app/Actions/Persons/CreatePerson.php`

**Intent**: Pełne zastąpienie listy w tej samej transakcji co reszta osoby; tekst `residence` zamrożony.

**Contract**:
- `UpdatePerson`: PHPDoc `$data` — `residence` zastąpione `residences?: array<int, array{locality_id: int|string, year_from?: int|string|null, year_to?: int|string|null}>`; `'residence'` usunięte z `$person->update([...])`. Prywatna metoda zastępuje listę: przesłane wiersze (`array_values`, brak klucza = pusta lista) normalizuje do krotek `(locality_id, year_from, year_to)`; gdy są równe krotkom bieżącej listy posortowanej `PersonOrdering::residences()` — nic nie robi; w przeciwnym razie usuwa zamieszkania osoby i tworzy nowe w kolejności przesłanej, z `tree_id` osoby.
- `CreatePerson`: `'residence'` usunięte z tworzenia i PHPDoc; zamieszkań nie tworzy.

#### 3. Kolejność

**File**: `app/Support/PersonOrdering.php`

**Intent**: Jedna reguła kolejności dla widoku, formularza i porównania w zapisie (decyzje autora 2 i plan: remis → kolejność dodania).

**Contract**: `PersonOrdering::residences(Collection<int, Residence>): Collection<int, Residence>` z kluczem `[rok === null ? 1 : 0, rok ?? 0, id]`, gdzie `rok = year_from ?? year_to`.

#### 4. Odczyt

**File**: `app/Http/Resources/ResidenceResource.php` (nowy), `app/Http/Resources/PersonResource.php`, `app/Http/Resources/PersonFormResource.php`, `app/Actions/Persons/BuildPersonPreview.php:27`

**Intent**: Panel dostaje posortowaną listę do widoku i do formularza; tekst znika z odczytu.

**Contract**:
- `ResidenceResource`: `{id: int, locality: {id: int, name: string}, year_from: int|null, year_to: int|null}`.
- `PersonResource`: `residence` usunięte; `'residences' => $this->whenLoaded('residences', …)` — kolekcja `ResidenceResource` z `PersonOrdering::residences()`, rozwiązana do tablicy. Lista osób relacji nie ładuje, więc klucza tam nie ma.
- `PersonFormResource`: `residence` zastąpione `residences` w tym samym kształcie (zawsze obecne; resource jest używany tylko z `BuildPersonPreview`).
- `BuildPersonPreview`: eager-load `residences.locality` (repo nie włącza `preventLazyLoading`, brak loadu = ciche N+1).

#### 5. Seeder demo

**File**: `database/seeders/TreeViewDemoSeeder.php:253-262`

**Intent**: Demo w nowym kształcie.

**Contract**: osoba z `'residence' => 'Zakopane'` dostaje dwa zamieszkania przez `localityId()` (`:548-551`): Nowy Targ 1920–1935 i Zakopane bez lat; klucze `residence` usunięte z tworzenia osób.

#### 6. Testy Pest

**File**: `tests/Feature/PersonsTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/WriteTargetsTest.php`, `tests/Feature/DeleteImpactTest.php`, `tests/BackupTestCase.php`

**Intent**: Pokrycie nowego kontraktu i trybów awarii; asercje na tekst `residence` przepisane (zmiana kontraktu jest celem tej zmiany).

**Contract**:
- `PersonsTest`: test `update changes names, residence, …` (`:756-777`) bez `residence`. Nowe: zapis listy tworzy wiersze, a podgląd osoby zwraca je w kolejności (rok od, a bez niego do; bez lat na końcu; remis → kolejność dodania); ta sama miejscowość dwa razy jest dozwolona; payload bez `residences` czyści listę; całkiem pusty wiersz pominięty bez błędu; wiersz z latami bez miejscowości → błąd na `residences.{ten sam indeks}.locality_id` bez zapisu; `year_from > year_to` → błąd na `year_to`; rok poza 1000 – bieżący → błąd; klucz `residence` ignorowany i `people.residence` nietknięte.
- `OwnershipBoundariesTest`: obca i nieistniejąca miejscowość w `residences.0.locality_id` → ten sam błąd walidacji, `ownershipSnapshot()` bez zmian (`context/foundation/test-plan.md:173`, wyrocznia 2).
- `WriteTargetsTest` (`:267-292`): test edycji `residence` przepisany — osoba z zamieszkaniem, zmiana listy → diff obejmuje tylko zamieszkania tej osoby (i wydarzenie urodzenia, jeśli test nadal zmienia rok), wiersz `people` bez zmian; nowy przypadek: zapis „Danych osobowych” osoby z zamieszkaniami bez zmiany listy → tabela `residences` nieobecna w diffie.
- `DeleteImpactTest` (`:80-82`): osoba usuwana dostaje zamieszkanie; lista tabel zawiera `residences`, usunięte są tylko jej wiersze; miejscowość zostaje.
- `BackupTestCase::arrangeBackupData()`: zamieszkanie dla `$parent` w miejscowości `$seat`, żeby `BackupRestoreTest` miał wiersz w nowej tabeli.

### Success Criteria:

#### Automated Verification:

- Testy domen przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/DeleteImpactTest.php`
- Testy kopii przechodzą: `vendor/bin/sail artisan test --compact tests/Backup`
- Test migracji nadal przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/ResidenceMigrationTest.php`
- Larastan (poziom 7) przechodzi bez nowych błędów: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: Faza nie ma kroków ręcznych; po zielonej weryfikacji przejdź do fazy 3. Front do tego czasu wysyła stary klucz `residence` (ignorowany) i nie wysyła `residences`, więc zapis panelu czyściłby listę — nie wdrażać fazy 2 bez fazy 3.

---

## Faza 3: Frontend — karta „Zamieszkania”

### Overview

Dodaje kartę z listą i edytorem wierszy, przenosi listę przez payload pełnego zastąpienia i usuwa stare pole i kolumnę.

### Changes Required:

#### 1. Typy

**File**: `resources/js/types/person.ts`

**Intent**: Nowy kształt odczytu w miejsce tekstu.

**Contract**: `PersonResidence = { id: number; locality: LocalityOption; year_from: number | null; year_to: number | null }`; `Person.residence` → `residences?: PersonResidence[]`; `PersonFormData.residence` → `residences: PersonResidence[]`.

#### 2. Payload

**File**: `resources/js/lib/person-payload.ts`

**Intent**: Lista przechodzi przez każdy zapis osoby, a edycja karty czyta wiersze z płaskich wartości formularza.

**Contract**:
- `PersonSection` += `'residences'`; `PersonPayload.residence` → `residences: ResidencePayload[]`, gdzie `ResidencePayload = { locality_id: number | null; year_from: number | null; year_to: number | null }`.
- `personFormToPayload`: `residences` z `personForm.residences` (kolejność zachowana).
- `mergeSectionIntoPayload('residences')`: `residencesFromValues(values)` grupuje klucze `residences.{i}.locality_id|year_from|year_to` po `i`, sortuje numerycznie i zwraca **wszystkie** wiersze, także puste (serwer je pomija, zachowując indeksy).
- `'personal'` nie czyta już `residence`.

#### 3. Edytor wierszy

**File**: `resources/js/components/persons/residences-fields.tsx` (nowy)

**Intent**: Lista wierszy w formularzu karty: picker miejscowości + rok od + rok do + usuń; „Dodaj zamieszkanie” dopisuje pusty wiersz.

**Contract**:
- Props `{ residences: PersonResidence[]; errors: Record<string, string> }`. Stan: wiersze ze stabilnym kluczem React (id zamieszkania albo licznik dla nowych), nie indeksem — stan `LocalityPicker` musi zostać przy swoim wierszu po usunięciu innego.
- Nazwy pól z indeksem pozycji wiersza: `residences.{i}.locality_id` (ukryte pole pickera), `residences.{i}.year_from`, `residences.{i}.year_to` (`Input type="number"`, `min=1000`, `max` = bieżący rok). Etykiety: „Miejscowość”, „Od roku”, „Do roku”; przycisk usuwania z `aria-label` „Usuń zamieszkanie {n}”; przycisk „Dodaj zamieszkanie”.
- Jedna lista miejscowości dla wszystkich wierszy: komponent czyta `localityOptions` przez `useOptionalList` raz (wzorzec `locality-picker.tsx:39-57`) i przekazuje `options` każdemu pickerowi; miejscowości dopisane w dowolnym wierszu (`onCreated`) trafiają do wspólnego stanu i do opcji wszystkich wierszy (wzorzec `source-description-fields.tsx:62-82`).
- Błędy: `errors['residences.{i}.locality_id']` przez prop `errors` pickera, `errors['residences.{i}.year_from|year_to']` przez `InputError` pod polem.
- Bez wierszy: tekst „Brak zamieszkań.” i przycisk „Dodaj zamieszkanie”.

#### 4. Karta w panelu

**File**: `resources/js/components/persons/overview-tab.tsx`, `resources/js/components/persons/section-editor.tsx`

**Intent**: Osobna karta „Zamieszkania” pod „Danymi osobowymi” (decyzja autora 1), zapisywana przez `PUT persons/{id}`.

**Contract**:
- `SectionId` += `'residences'`; etykieta karty w strażniku niezapisanych zmian (`labelFor`, `section-editor.tsx:43`) „Zamieszkania”.
- `belongsToSection('residences', key)` → `key === 'residences' || key.startsWith('residences.')`; `'residence'` usunięte z `PERSONAL_KEYS`, `Field "Zamieszkanie"` usunięte z widoku „Dane osobowe”.
- Nagłówek sekcji „Zamieszkania” + `SectionCard` `id="residences"`: widok to lista „{nazwa miejscowości}” z latami z `formatYearRange`; gdy lista pusta i karta nie edytuje — `EmptyFact` „Nie znamy miejsc zamieszkania.” z przyciskiem „Dodaj zamieszkanie” (`editor.start('residences')`), jak urodzenie (`overview-tab.tsx:312-345`). Edycja renderuje `ResidencesFields` z `personForm.residences`.

#### 5. Format lat

**File**: `resources/js/lib/year-range.ts` (nowy)

**Intent**: Jeden zapis lat w widoku.

**Contract**: `formatYearRange(from, to): string` → `"1900–1910"` (półpauza), `"od 1900"`, `"do 1910"`, `""` gdy brak obu.

#### 6. Usunięcie starego pola i kolumny

**File**: `resources/js/components/persons/person-section-fields.tsx:151-160`, `resources/js/components/persons/persons-table.tsx:128,163-169`

**Intent**: Zamieszkania tylko w panelu (decyzja autora 4); kolumna znika z tabeli osób (decyzja autora 6).

**Contract**: `PersonalFields` bez pola „Miejsce zamieszkania” (zmiana obejmuje kartę „Dane osobowe”, dialog „Dodaj osobę” i dialogi slotów drzewa); tabela bez nagłówka i komórki „Zamieszkanie”.

#### 7. Testy Vitest

**File**: `resources/js/lib/person-payload.test.ts`, `resources/js/lib/year-range.test.ts` (nowy), `resources/js/lib/persons-index.test.ts`

**Intent**: Regresja pełnego zastąpienia i formatu.

**Contract**: zapis sekcji `personal`, `birth` i `death` niesie `residences` z `personForm` bez zmian; `residences` z wartości: kolejność po indeksie numerycznie (`10` po `2`), puste wiersze zachowane na swoich pozycjach, liczby z pustych pól → `null`; `formatYearRange` dla czterech przypadków. Fixtures z `residence: null` zaktualizowane do nowych typów.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Build przechodzi: `vendor/bin/sail npm run build`
- Testy Pest z fazy 2 nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/WriteTargetsTest.php`

#### Manual Verification:

- W panelu osoby (1366 px) karta „Zamieszkania” pokazuje zamieszkanie z migracji; dodanie dwóch wierszy (jeden przez „Dodaj miejscowość „X””) i zapis pokazuje listę w kolejności po roku, z pozycją bez lat na końcu.
- Miejscowość dopisana w jednym wierszu jest od razu do wyboru w drugim wierszu.
- Usunięcie środkowego wiersza zostawia właściwe miejscowości w pozostałych wierszach (brak przesunięcia stanu pickera).
- Wiersz z rokiem bez miejscowości pokazuje „Wybierz miejscowość.” pod tym wierszem; całkiem pusty wiersz znika po zapisie bez błędu; od > do pokazuje błąd pod „Do roku”.
- Dodanie wiersza i próba przejścia do innej osoby uruchamia strażnika niezapisanych zmian.
- Zapis „Danych osobowych” zostawia listę zamieszkań bez zmian.
- Dialog „Dodaj osobę”, dialog slotu drzewa i „Dane osobowe” nie mają pola „Miejsce zamieszkania”; tabela osób nie ma kolumny „Zamieszkanie”.

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: E2E (przez `/10x-e2e`)

### Overview

Dodaje test ryzyka S-08 w przeglądarce, prowadzony umiejętnością `/10x-e2e` (ryzyko → eksploracja → generowanie → przegląd antywzorców → weryfikacja celowym błędem).

### Changes Required:

#### 1. Ryzyko S-08

**File**: `tests/e2e/residence-history.spec.ts` (nowy)

**Intent**: Edytor wielu wierszy z pickerami i pełne zastąpienie działają w prawdziwym panelu osoby — tego nie sprawdzi Pest ani Vitest (brak testów komponentów).

**Contract**: osoba z `createPerson` (`tests/e2e/support/api.ts:130`), miejscowość z `createLocality` (`:195`), nazwy z sufiksem testu. W panelu: karta „Zamieszkania” → dwa wiersze: miejscowość z helpera z „Od roku” późniejszym oraz nowa nazwa przez „Dodaj miejscowość „…”” z rokiem wcześniejszym; zapis; widok pokazuje obie pozycje w kolejności po roku. Potem edycja „Danych osobowych” i zapis; obie pozycje nadal widoczne po przeładowaniu. Lokatory `getByRole`/`getByLabel`, bez `waitForTimeout`, sprzątanie osób przez `cleanupBySuffix`; miejscowości zostają w bazie `e2e` do S-11 (`context/foundation/test-stack.md:18`).

### Success Criteria:

#### Automated Verification:

- Nowy test przechodzi: `npx playwright test tests/e2e/residence-history.spec.ts`
- Cały zestaw E2E przechodzi: `npx playwright test`

#### Manual Verification:

- Weryfikacja celowym błędem: usunięcie `residences` z `personFormToPayload` czerwieni nowy test.

---

## Testing Strategy

### Unit Tests:

- Vitest: `person-payload` — pełne zastąpienie niesie listę, parser wierszy (kolejność, puste pozycje); `formatYearRange`.

### Integration Tests:

- Pest: migracja (dopasowanie, dosianie, puste, wiele drzew, nietknięte teksty, `down`); zapis i kolejność listy, pomijanie pustych wierszy z zachowaniem indeksów błędów, od ≤ do, zakres lat, duplikaty, czyszczenie bez klucza, ignorowany `residence`; własność `residences.*.locality_id`; cele zapisu (brak przepisywania przy niezmienionej liście) i usuwania (kaskada z osobą); kopia z wierszem w nowej tabeli.
- E2E: ryzyko S-08 w panelu osoby.

### Manual Testing Steps:

1. Po `migrate` na kopii danych sprawdź, że każda osoba z niepustym tekstem ma jedno zamieszkanie bez lat.
2. W panelu dodaj, przestaw latami i usuń zamieszkania; sprawdź kolejność i błędy pod właściwym wierszem.
3. Zapisz „Dane osobowe” — lista zostaje.

## Performance Considerations

Panel dokłada jedno zapytanie (`residences.locality`) na podgląd osoby. Lista miejscowości ładuje się raz na kartę (optional prop), nie raz na wiersz. Niezmieniona lista nie generuje zapisów.

## Migration Notes

- Kopiuj najpierw, usuwaj później: `people.residence` zostaje nietknięte, nieczytane i niezapisywane; usunięcie kolumny to osobna przyszła migracja.
- Produkcja wykonuje `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`) — bez zmian. Nowa tabela jest objęta kopią automatycznie; `BackupRestoreTest` dostaje jej wiersz w fixture.
- Runbook produkcyjny (wzór `context/archive/2026-10-06-fact-places-from-gazetteer/plan.md:385`): przed migracją policz osoby z niepustym `residence` i liczbę miejscowości; po migracji — liczba wierszy `residences` (oczekiwana = liczba osób z niepustym tekstem), osoby z niepustym tekstem bez zamieszkania (oczekiwane 0) i liczba dosianych miejscowości (różnica). Przejrzyj dosiane nazwy pod kątem miejscowości przemianowanych przed S-08 i popraw ręcznie zmianą nazwy (do S-11 bez scalania).
- Wycofanie (`down()`) usuwa tabelę i zamieszkania zapisane po migracji; tekst pokazuje stan sprzed migracji.

## References

- Related research: `context/changes/residence-history/research.md` (sekcja Decisions — decyzje autora 1–6)
- Plan S-07 (wzorzec migracji, pełne zastąpienie): `context/archive/2026-10-06-fact-places-from-gazetteer/plan.md`
- Impl-review S-07 (F1 indeks na FK, F2 pełne zastąpienie): `context/archive/2026-10-06-fact-places-from-gazetteer/reviews/impl-review.md:26-58`
- Migracja wzorcowa: `database/migrations/2026_10_06_070850_link_event_places_to_localities.php`
- Test migracji wzorcowy: `tests/Feature/FactPlaceLocalityMigrationTest.php`
- Kolejność w PHP: `app/Support/PersonOrdering.php`
- Picker i współdzielone miejscowości: `resources/js/components/localities/locality-picker.tsx`, `resources/js/components/sources/source-description-fields.tsx:62-82`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Migracja i model

#### Automated

- [x] 1.1 Migracja i jej wycofanie przechodzą — 02e532d
- [x] 1.2 Test migracji przechodzi — 02e532d
- [x] 1.3 Testy migracji S-01 i S-07 nadal przechodzą — 02e532d
- [x] 1.4 Larastan przechodzi — 02e532d
- [x] 1.5 Pint czysty — 02e532d

#### Manual

- [x] 1.6 Lokalnie każda osoba z niepustym tekstem ma zamieszkanie na miejscowość o tej samej nazwie — 02e532d

### Phase 2: Backend zapisu i odczytu zamieszkań

#### Automated

- [x] 2.1 Testy domen przechodzą — de174da
- [x] 2.2 Testy kopii przechodzą — de174da
- [x] 2.3 Test migracji nadal przechodzi — de174da
- [x] 2.4 Larastan (poziom 7) przechodzi bez nowych błędów — de174da
- [x] 2.5 Pint czysty — de174da

### Phase 3: Frontend — karta „Zamieszkania”

#### Automated

- [x] 3.1 Format i lint przechodzą
- [x] 3.2 Typy przechodzą
- [x] 3.3 Vitest przechodzi
- [x] 3.4 Build przechodzi
- [x] 3.5 Testy Pest z fazy 2 nadal przechodzą

#### Manual

- [x] 3.6 Karta pokazuje zamieszkanie z migracji, dwa nowe wiersze zapisują się w kolejności po roku
- [x] 3.7 Miejscowość dopisana w jednym wierszu jest do wyboru w drugim
- [x] 3.8 Usunięcie środkowego wiersza nie przesuwa stanu pickerów
- [x] 3.9 Błędy walidacji stoją pod właściwym wierszem, pusty wiersz znika bez błędu
- [x] 3.10 Strażnik niezapisanych zmian reaguje na dodanie wiersza
- [x] 3.11 Zapis Danych osobowych zostawia listę zamieszkań
- [x] 3.12 Stare pole zniknęło z formularzy i kolumna z tabeli osób

### Phase 4: E2E (przez /10x-e2e)

#### Automated

- [ ] 4.1 Nowy test przechodzi
- [ ] 4.2 Cały zestaw E2E przechodzi

#### Manual

- [ ] 4.3 Weryfikacja celowym błędem czerwieni nowy test
