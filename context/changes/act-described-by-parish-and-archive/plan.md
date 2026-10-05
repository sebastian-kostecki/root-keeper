# Akt opisany parafią, rokiem, typem i archiwum (S-03) — plan implementacji

## Overview

Autor opisuje akt metrykalny osobnymi danymi: parafia ze spisu parafii (nazwa, opcjonalne wyznanie, opcjonalna miejscowość siedziby ze spisu miejscowości), rok, typ aktu i archiwum. Nową parafię dopisuje w locie z panelu źródła, nie tracąc niezapisanego formularza, a brakującą miejscowość siedziby dopisuje jednym kliknięciem z listy podpowiedzi. Archiwum to tekst z podpowiedziami wcześniej wpisanych archiwów, dostępny dla każdego rodzaju źródła. Dotychczasowe „Pochodzenie” jest kopiowane 1:1 do archiwum. Stara kolumna zostaje w bazie do późniejszej migracji. Spis parafii ma własną stronę `/parishes` z panelem edycji (FR-001, FR-008, FR-009, US-01; roadmapa S-03).

## Current State Analysis

- Rok i typ aktu już istnieją: `sources.year` i `sources.record_type` (enum `SourceRecordType`) — `database/migrations/2026_09_30_072040_add_description_to_sources_table.php:16-22`. S-03 dokłada tylko parafię (nowa tabela + FK) i archiwum (tekst).
- „Pochodzenie” to `sources.provenance` (string 255). Formularz opisuje je jako „Parafia, archiwum, sygnatura” (`resources/js/components/sources/source-description-fields.tsx:105-116`). Lokalnie wypełnione w 2 z 19 źródeł (maks. 78 znaków; sprawdzone 2026-10-05). Danych produkcyjnych nie sprawdzano.
- `UpdateSource` zeruje wszystkie siedem pól opisu i nakłada przesłane (`app/Actions/Sources/UpdateSource.php:19-28`). Pola tylko dla aktu (`record_type`, `entry_number`) mają `Rule::excludeIf(kind !== metrical_record)` (`app/Concerns/SourceDescriptionValidationRules.php:28-29`) i są zerowane dla innego rodzaju (`UpdateSource.php:30-33`). Zmiana rodzaju, która skasowałaby zapisane pola aktu, wymaga `discard_record_fields` (`app/Http/Requests/SourceUpdateRequest.php:36-41,65-73`) i dialogu we froncie (`resources/js/lib/source-description.ts:42-82`, `resources/js/components/sources/source-panel.tsx:376-388,501-525`).
- Panel źródła istnieje tylko na `sources/index` — domknięcie `sourcePanel` w `app/Http/Controllers/SourceController.php:46-67`, rejestr `PANEL_PROPS` w `resources/js/lib/side-panel-url.ts:6-11`. Formularz to Inertia `<Form>` z polami niekontrolowanymi (`defaultValue`), więc każda wizyta Inertia z przeładowaniem propsów może zresetować niezapisany opis.
- Wzorzec spisu do skopiowania: moduł miejscowości z S-01 — migracja z indeksem wyrażeniowym (`database/migrations/2026_10_05_081630_create_localities_table.php:15-24`), trait walidacji z domknięciem drzewa (`app/Concerns/LocalityValidationRules.php:16-38`), akcje z transakcją i przechwyceniem `UniqueConstraintViolationException` (`app/Actions/Localities/StoreLocality.php:17-27`), Policy z `denyAsNotFound()` (`app/Policies/LocalityPolicy.php`), kontroler z panelem `?locality=` (`app/Http/Controllers/LocalityController.php`), strona `resources/js/pages/localities/index.tsx`, panel `resources/js/components/localities/locality-panel.tsx`, filtr `resources/js/lib/localities-index.ts`.
- Wyznanie ma gotowy enum `App\Enums\Religion` z `label()` i `options()` (`app/Enums/Religion.php`), używany w `people.religion` (string nullable).
- W aplikacji nie ma comboboxa, popovera ani endpointu JSON. `useHttp` (Inertia v3) jest użyty raz (`resources/js/hooks/use-two-factor-auth.ts:1,23`). Według dokumentacji v3 wysyła zwykłe żądanie bez wizyty Inertia, zwraca JSON i sam parsuje błędy 422 do `errors`.
- Panel boczny zamyka się na Escape, jeśli zdarzenie nie ma `defaultPrevented` (`resources/js/components/side-panel.tsx:192`).
- Usunięcie konta kaskaduje `users → trees → sources/localities/...` (`database/migrations/2026_09_23_045720_create_trees_table.php:16`, `app/Http/Controllers/Settings/ProfileController.php:57`).
- Testy przekrojowe, które nowa tabela i trasy przewrócą: strażnik tras z `{param}` (`tests/Feature/OwnershipBoundariesTest.php:139-150`) i wymóg wiersza w każdej trwałej tabeli (`tests/Backup/BackupRestoreTest.php:25-27`, dane w `tests/BackupTestCase.php:128-174`).

## Desired End State

- W sidebarze jest pozycja „Parafie” z licznikiem. Strona `/parishes` pokazuje parafie drzewa alfabetycznie po polsku z wyszukiwaniem. Panel `?parish=new|{id}` pozwala dodać i edytować parafię: nazwa (wymagana), wyznanie (opcjonalne), miejscowość siedziby (opcjonalna, ze spisu, z dopisaniem nowej z listy).
- Parafia jest unikalna w drzewie po parze (nazwa bez wielkości liter, wyznanie), a brak wyznania liczy się jako równy brakowi wyznania. „Św. Jana” rzymskokatolicka i „św. jana” prawosławna mogą współistnieć, dwie „Św. Jana” bez wyznania — nie.
- W panelu aktu metrykalnego jest pole „Parafia” z podpowiedziami („Nazwa (wyznanie)”). Dla każdego niepustego wpisanego tekstu lista kończy się pozycją „Dodaj parafię „{tekst}””, także gdy parafia o tej nazwie już istnieje (może mieć inne wyznanie). Prawdziwy duplikat (nazwa i wyznanie) zatrzymuje walidacja w modalu. Ta pozycja otwiera modal „Nowa parafia” z nazwą wypełnioną tekstem. Po zapisie modal się zamyka, nowa parafia jest wybrana, a wszystkie wcześniej wpisane pola źródła mają nadal swoje wartości.
- Pole „Archiwum” jest dla każdego rodzaju źródła, podpowiada różne archiwa wpisane wcześniej w drzewie i zapisuje dokładnie wpisany tekst. Pola „Pochodzenie” nie ma w formularzu.
- Po migracji każde źródło z niepustym `provenance` ma `archive` równe tej wartości. `provenance` jest nietknięte i pozostaje nietknięte po edycji źródła.
- Zmiana rodzaju z aktu z zapisaną parafią na inny rodzaj wymaga potwierdzenia w dialogu „Zmienić rodzaj źródła?”, który wymienia parafię. Po zapisie `parish_id` jest NULL.
- Tabela źródeł ma kolumny „Parafia” i „Archiwum” w miejscu „Pochodzenie”. Karta galerii pokazuje „typ · rok · parafia”, a pod spodem archiwum.
- Cudza parafia (w trasie lub w `?parish=`) to 404. Cudza parafia lub miejscowość podana w treści żądania (`parish_id`, `locality_id`) to błąd walidacji bez zapisu.

Weryfikacja: Pest (`ParishesTest`, `SourceArchiveMigrationTest`, `SourcesTest`, `LocalitiesTest`, `OwnershipBoundariesTest`, `AppShellTest`, `BackupRestoreTest`), Vitest (`suggestions.test.ts`, `parishes-index.test.ts`, `source-description.test.ts`, `side-panel-url.test.ts`), spec E2E `source-panel-parish.spec.ts` i ręczny przegląd na prawdziwym akcie.

### Key Discoveries:

- `UpdateSource.php:19-28` zeruje każde nieprzesłane pole opisu. Gdyby `provenance` zostało na tej liście po zdjęciu pola z formularza, pierwsza edycja źródła skasowałaby zachowaną kopię.
- `useHttp` nie dotyka cyklu życia strony, więc dopisanie parafii nie przeładuje `sourcePanel` i nie zresetuje pól `defaultValue` (dokumentacja Inertia v3, „HTTP Requests”).
- Dane spisów są małe (lokalnie 9 miejscowości), więc pełna lista opcji może jechać w `sourcePanel`, a filtr działa po stronie klienta jak `resources/js/lib/localities-index.ts`. Nie jest potrzebny endpoint wyszukiwania.
- `side-panel.tsx:192` — Escape w otwartej liście podpowiedzi musi wywołać `preventDefault()`, inaczej zamknie cały panel źródła.

## What We're NOT Doing

- Nie usuwamy kolumny `sources.provenance`. Robi to późniejsza migracja („kopiuj, potem usuń”). Kolumna znika tylko z formularza, walidacji, zasobu i typów frontu.
- Nie wydzielamy automatycznie parafii ani sygnatury z tekstu „Pochodzenia” — autor robi to ręcznie (decyzja autora 1).
- Nie usuwamy ani nie scalamy parafii i miejscowości — to S-11 (FR-012).
- Nie zmieniamy pickera źródła przy fakcie, dopasowania typu ani szukania po miejscu i roku — to S-04. „Numer wpisu” zostaje w formularzu aktu do S-04.
- Nie dajemy nagrobkowi cmentarza — to S-09. Pola rodzaju „inne” to nazwa, rok, archiwum, link, notatka.
- Typ aktu zostaje enumem `SourceRecordType`, nie spisem.
- Nie dodajemy zależności frontendu (cmdk, popover) — komponent podpowiedzi jest własny.
- Nie dodajemy sortowania tabeli źródeł po parafii ani archiwum (dziś nie ma sortowania po pochodzeniu).
- Nie dodajemy składania polskich znaków w dopasowaniu („swiety” nie znajdzie „Święty”) — dopasowanie jest takie jak w spisie miejscowości.

## Implementation Approach

Backend najpierw, w dwóch fazach: najpierw samodzielny moduł parafii (kopia modułu miejscowości) z trasami JSON do dopisania w locie, potem zmiana opisu źródła z migracją danych. Front w dwóch fazach: najpierw współdzielony komponent podpowiedzi sprawdzony na stronie spisu parafii (bez ryzyka dla formularza źródła), potem formularz i lista źródeł. Na końcu jedna faza E2E na ryzyko z roadmapy: dopisanie parafii z nową miejscowością w panelu nowego aktu bez utraty wpisanych pól.

Komponent podpowiedzi (`SuggestField`) ma dwa tryby: **rekord** (wybór ze spisu → ukryty input z id, opcjonalna pozycja „Dodaj …” wywołująca callback) i **tekst** (wolny tekst w zwykłym inpucie z nazwą pola, podpowiedzi tylko uzupełniają). Pozwala to użyć go dla parafii, miejscowości siedziby i archiwum, a w S-07/S-08/S-09 dla miejsc faktów, zamieszkań i cmentarzy.

Dopisanie w locie korzysta z osobnych tras JSON (`POST parishes/inline`, `POST localities/inline`), które używają tych samych Form Requestów i akcji co strony spisów i zwracają zasób z kodem 201. Strony spisów zostają przy konwencji Inertia `<Form>` → redirect + toast.

## Critical Implementation Details

**Kolejność usuwania i akcje FK.** `sources.parish_id → parishes` i `parishes.locality_id → localities` dostają domyślne `NO ACTION` (bez `restrictOnDelete()`). Usunięcie konta kasuje drzewo, a drzewo kaskadowo jego źródła i parafie w jednym poleceniu. `RESTRICT` jest sprawdzane od razu i przerwałby kaskadę, a `NO ACTION` — na końcu polecenia. Usuwanie pojedynczej parafii lub miejscowości nie istnieje do S-11, a tam jest dozwolone tylko dla nieużywanych.

**Zachowanie `provenance`.** `UpdateSource` i `StoreSource` nie mogą już wymieniać `provenance` w atrybutach. Kolumna jest zapisywana wyłącznie przez migrację kopiującą i zostaje taka, jaka była przed S-03.

**Formularz źródła a dopisanie w locie.** Modal „Nowa parafia” renderuje się w portalu (Radix Dialog z `resources/js/components/ui/dialog.tsx`) i wysyła dane przez `useHttp`, nie przez `<Form>` ani `router`. Zagnieżdżony `<form>` wysłałby formularz źródła — także w portalu, bo zdarzenia Reacta idą po drzewie komponentów, a nie po DOM — a wizyta Inertia przeładowałaby `sourcePanel`. Nowa parafia trafia do lokalnego stanu opcji pola i staje się wybraną wartością. Przy następnym przeładowaniu panelu lista przychodzi z serwera.

**Klawiatura i fokus w liście podpowiedzi.** Pole ma `role="combobox"` z `aria-expanded`, `aria-controls` i `aria-activedescendant`, a lista ma `role="listbox"` z elementami `role="option"`. Strzałki zmieniają aktywną pozycję, Enter wybiera (i nie wysyła formularza źródła), Escape zamyka listę z `preventDefault()`. Kolejność ma znaczenie: panel słucha Escape natywnym listenerem na swoim korzeniu (`side-panel.tsx:189-196`), który odpala przed delegowanym `onKeyDown` Reacta, więc `SuggestField` obsługuje Escape w `onKeyDownCapture`. W modalu Radix łapie Escape na `document` w fazie capture, jeszcze przed Reactem, więc `DialogContent` w `NewParishDialog` dostaje `onEscapeKeyDown`, który woła `preventDefault()`, gdy `event.target` ma `aria-expanded="true"` (otwarta lista siedziby). Wtedy Escape zamyka tylko listę, a nie modal. Utrata fokusu zamyka listę. W trybie rekordu wpisany tekst, który nie został wybrany, nie zmienia wartości. Wyczyszczenie pola zeruje wybór.

## Faza 1: Spis parafii — backend

### Overview

Tabela, model i cała ścieżka zapisu/odczytu parafii jako rekordu należącego do drzewa, strona `/parishes` z panelem `?parish=`, trasy JSON do dopisania parafii i miejscowości w locie oraz licznik w nawigacji.

### Changes Required:

#### 1. Tabela parafii

**File**: `database/migrations/<timestamp>_create_parishes_table.php` (przez `artisan make:migration`)

**Intent**: Parafia należy do drzewa i jest unikalna po (nazwa bez wielkości liter, wyznanie), przy czym brak wyznania jest równy brakowi wyznania (decyzja autora 3).

**Contract**: `parishes`: `id`, `tree_id` (FK `cascadeOnDelete`), `name` string 255, `religion` string nullable (wartości `App\Enums\Religion`), `locality_id` nullable FK do `localities` z domyślnym `NO ACTION` (patrz Critical Implementation Details), `timestamps`. Indeks `CREATE UNIQUE INDEX parishes_tree_id_lower_name_religion_unique ON parishes (tree_id, lower(name), coalesce(religion, ''))` (raw, jak w `create_localities_table.php:23`). `down()` usuwa tabelę.

#### 2. Model, relacje, fabryka

**File**: `app/Models/Parish.php`, `app/Models/Tree.php`, `app/Models/Locality.php`, `database/factories/ParishFactory.php`

**Intent**: Model jak `Locality`, z rzutowaniem wyznania na enum i relacjami do drzewa i miejscowości siedziby.

**Contract**: `Parish` — `#[Fillable(['tree_id','name','religion','locality_id'])]`, cast `religion` → `Religion`, `tree(): BelongsTo`, `locality(): BelongsTo`, `sources(): HasMany` (używane w fazie 2). `Tree::parishes(): HasMany`. `Locality::parishes(): HasMany`. Fabryka: unikalna nazwa „Parafia …” w drzewie, `religion` null, `locality_id` null; stany `withReligion(Religion)` i `seatedIn(Locality)` tylko jeśli testy ich potrzebują.

#### 3. Walidacja, Policy, Form Requests

**File**: `app/Concerns/ParishValidationRules.php`, `app/Policies/ParishPolicy.php`, `app/Http/Requests/ParishStoreRequest.php`, `app/Http/Requests/ParishUpdateRequest.php`

**Intent**: Te same dwie warstwy unikalności co w miejscowościach, zawsze w zakresie drzewa użytkownika. Miejscowość siedziby musi należeć do drzewa użytkownika.

**Contract**: Reguły: `name` → `bail|required|string|max:255` + domknięcie szukające w `$user->tree->parishes()` wiersza z `lower(name) = lower(?)` i `coalesce(religion,'') = coalesce(?, '')` (przy edycji `whereKeyNot`), komunikat „Parafia o tej nazwie i wyznaniu już istnieje.”; `religion` → `nullable`, `Rule::enum(Religion::class)`; `locality_id` → `nullable`, `integer`, `Rule::exists('localities','id')->where('tree_id', $user->tree->id)`. Atrybuty po polsku (nazwa, wyznanie, miejscowość siedziby). Policy `view`/`update` jak `LocalityPolicy` (`denyAsNotFound()`). `ParishUpdateRequest::authorize()` przez `Gate::inspect('update', …)` jak `LocalityUpdateRequest.php:19-22`.

#### 4. Akcje

**File**: `app/Actions/Parishes/StoreParish.php`, `app/Actions/Parishes/UpdateParish.php`

**Intent**: Zapis w transakcji z zamianą wyścigu unikalności na błąd walidacji `name`, jak `StoreLocality.php:17-27`.

**Contract**: `StoreParish::handle(Tree $tree, string $name, ?Religion $religion, ?int $localityId): Parish`; `UpdateParish::handle(Parish $parish, string $name, ?Religion $religion, ?int $localityId): Parish`.

#### 5. Kontroler strony, zasób, trasy

**File**: `app/Http/Controllers/ParishController.php`, `app/Http/Resources/ParishResource.php`, `routes/web.php`

**Intent**: Strona spisu jak `LocalityController`: lista z filtrem `q`, panel `?parish=new|{id}`, store przekierowuje na `index?parish={id}`, update robi `back()`, oba z toastem.

**Contract**: `Route::resource('parishes', ParishController::class)->only(['index','store','update'])`. Propsy strony `parishes/index`: `parishes` (lista zasobów, sort po nazwie), `filters.q`, `religions` (`Religion::options()`), `localities` (`{id, name}` drzewa, sort po nazwie — opcje pola siedziby), `parishPanel` (`null` | `{parish: ParishResource|null}`; cudze id → 404 przez `findOrFail` w drzewie + `Gate::authorize`). `ParishResource`: `id`, `name`, `religion`, `religion_label`, `locality_id`, `locality_name`, `label` (np. „Św. Jana (rzymskokatolickie)”; bez wyznania samo „Św. Jana”), `sources_count` tylko gdy załadowany. Toasty: „Dodano parafię”, „Zapisano parafię”.

#### 6. Trasy JSON do dopisania w locie

**File**: `app/Http/Controllers/InlineParishController.php`, `app/Http/Controllers/InlineLocalityController.php`, `routes/web.php`

**Intent**: Pierwsze endpointy JSON aplikacji: zapis parafii (z panelu źródła) i miejscowości (z pola siedziby) bez wizyty Inertia. Te same Form Requesty i akcje co strony spisów.

**Contract**: `POST parishes/inline` → `parishes.inline.store` (przyjmuje `ParishStoreRequest`, zwraca `ParishResource` z kodem 201). `POST localities/inline` → `localities.inline.store` (przyjmuje `LocalityStoreRequest`; tylko `name`, bez opisu; zwraca `{id, name}` z kodem 201). Błąd walidacji zwraca 422 JSON (żądanie `useHttp`/`postJson`). Trasy bez `{param}` nie wymagają przypadku w strażniku tras. Po dodaniu tras uruchom `vendor/bin/sail artisan wayfinder:generate`.

#### 7. Licznik w nawigacji

**File**: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/global.d.ts`

**Intent**: Sidebar pokaże „Parafie” z licznikiem jak „Miejscowości”.

**Contract**: `navCounts.parishes` (`HandleInertiaRequests.php:53`); typ `navCounts` w `global.d.ts:20` rozszerzony o `parishes: number`.

#### 8. Testy

**File**: `tests/Feature/ParishesTest.php`, `tests/Feature/LocalitiesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/AppShellTest.php`, `tests/BackupTestCase.php`

**Intent**: Szczęśliwe ścieżki, unikalność, granice własności i strażniki przekrojowe.

**Contract**:
- `ParishesTest`: lista pokazuje tylko parafie drzewa; `?parish=new` i `?parish={id}` dają panel; store tworzy parafię z wyznaniem i siedzibą, przekierowuje na `?parish={id}`; update zmienia pola; „św. jana” bez wyznania odrzucona, gdy istnieje „Św. Jana” bez wyznania; „Św. Jana” prawosławna dozwolona obok rzymskokatolickiej; ta sama nazwa w innym drzewie dozwolona; obca `locality_id` → błąd walidacji bez zapisu; `parishes.inline.store` zwraca 201 z `id` i `label`, a duplikat zwraca 422 z błędem `name`; usunięcie użytkownika z parafią mającą siedzibę kończy się sukcesem i usuwa parafie drzewa.
- `LocalitiesTest`: `localities.inline.store` zwraca 201 z `id` i `name`, a duplikat zwraca 422.
- `OwnershipBoundariesTest`: `victimParish` w rekordach, przypadek `parishes.update` (put), przypadek `parishes.index` z `?parish={cudzy id}` → 404, wstrzyknięcie `tree_id` przez `parishes.store` i `parishes.inline.store` nie trafia do cudzego drzewa.
- `AppShellTest`: `navCounts.parishes`.
- `BackupTestCase::arrangeBackupData`: co najmniej jedna parafia (z siedzibą).

### Success Criteria:

#### Automated Verification:

- Migracje przechodzą: `vendor/bin/sail artisan migrate`
- Testy parafii, miejscowości, własności, powłoki i kopii przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ParishesTest.php tests/Feature/LocalitiesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/AppShellTest.php tests/Backup`
- Larastan przechodzi: `vendor/bin/sail composer run types:check`
- Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 2: Parafia i archiwum w źródle — backend i migracja danych

### Overview

Źródło dostaje `parish_id` (tylko akt) i `archive` (każdy rodzaj). „Pochodzenie” jest kopiowane do archiwum i znika ze ścieżki zapisu i odczytu, ale kolumna zostaje nietknięta. Panel źródła dostaje opcje parafii, miejscowości i archiwów.

### Changes Required:

#### 1. Kolumny źródła i kopia „Pochodzenia”

**File**: `database/migrations/<timestamp>_add_parish_and_archive_to_sources_table.php`

**Intent**: Dodaje kolumny i kopiuje `provenance` do `archive` 1:1 (decyzja autora 1). Nie usuwa `provenance` („kopiuj, potem usuń”).

**Contract**: `sources.parish_id` nullable FK do `parishes` z domyślnym `NO ACTION`; `sources.archive` string 255 nullable. Kopia jednym `UPDATE sources SET archive = provenance WHERE archive IS NULL AND provenance IS NOT NULL AND btrim(provenance) <> ''` (idempotentna). `down()` usuwa `parish_id` i `archive` — odwracalna bez utraty, bo `provenance` zostaje.

#### 2. Model i walidacja

**File**: `app/Models/Source.php`, `app/Concerns/SourceDescriptionValidationRules.php`

**Intent**: Parafia tylko dla aktu, archiwum dla każdego rodzaju (decyzja autora 2). `provenance` przestaje być przyjmowane.

**Contract**: `Source`: `parish_id` i `archive` w `#[Fillable]` (bez `provenance`), `parish(): BelongsTo`, PHPDoc właściwości. Reguły: `archive` → `nullable|string|max:255`; `parish_id` → `Rule::excludeIf(kind !== metrical_record)`, `nullable`, `integer`, `Rule::exists('parishes','id')->where('tree_id', $user->tree->id)`; `provenance` usunięte z reguł i atrybutów; atrybuty „archiwum”, „parafia”.

#### 3. Akcje i potwierdzenie zmiany rodzaju

**File**: `app/Actions/Sources/StoreSource.php`, `app/Actions/Sources/UpdateSource.php`, `app/Http/Requests/SourceUpdateRequest.php`

**Intent**: Parafia jest zerowana dla rodzaju innego niż akt, tak jak typ i numer wpisu. Zmiana rodzaju kasująca zapisaną parafię wymaga potwierdzenia. `provenance` nie jest nigdy zapisywane przez akcje.

**Contract**: Kształty PHPDoc `$description`: `provenance` → `archive`, dochodzi `parish_id?: int|null`. `UpdateSource`: lista zerowanych pól = `name, record_type, entry_number, parish_id, year, archive, url, note` (bez `provenance`); dla rodzaju ≠ akt zeruje też `parish_id`. `SourceUpdateRequest::wouldEraseRecordFields` uwzględnia `parish_id !== null`; komunikat: „Zmiana rodzaju usunie typ aktu, numer wpisu lub parafię. Potwierdź zmianę.”

#### 4. Zasób i propsy panelu

**File**: `app/Http/Resources/SourceResource.php`, `app/Http/Controllers/SourceController.php`, `app/Http/Middleware/HandleInertiaRequests.php`

**Intent**: Lista i panel czytają parafię i archiwum. Panel ma wszystko, czego potrzebują pola z podpowiedziami i modal „Nowa parafia”, bez dodatkowych żądań.

**Contract**: `SourceResource`: usuń `provenance`, dodaj `archive`, `parish_id`, `parish_label` (null, gdy brak). Każde miejsce, które buduje `SourceResource`, ładuje `parish` z góry (bez N+1): lista `sources` w `index`, `$source->load([...])` w `sourcePanel` (`SourceController.php:62`) i `availableSources` (`HandleInertiaRequests.php:60-62`, picker źródła przy fakcie). Opcje `parishes` w `sourcePanel` ładują `locality` (dla `locality_name`). `sourcePanel` (gdy otwarty, także `new`) zwraca dodatkowo `parishes` (`ParishResource` drzewa), `localities` (`{id, name}`), `archives` (lista różnych niepustych `archive` w drzewie) oraz `religions` (`Religion::options()`). Klucze `source` i `confirms` bez zmian.

#### 5. Fabryka i seeder demo

**File**: `database/factories/SourceFactory.php`, `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Dane testowe i demo w nowym kształcie.

**Contract**: Stan `described()` ustawia `archive` zamiast `provenance` (`SourceFactory.php:74`); seeder demo tworzy parafię „Parafia rzymskokatolicka w Tarnowie” (lub podobną) z siedzibą i ustawia `archive` i `parish_id` aktów zamiast `provenance` (`TreeViewDemoSeeder.php:37,297,311`).

#### 6. Testy

**File**: `tests/Feature/SourceArchiveMigrationTest.php`, `tests/Feature/SourcesTest.php`

**Intent**: Migracja przenosząca dane z własnym testem (CLAUDE.md) i zachowanie formularza na backendzie.

**Contract**:
- `SourceArchiveMigrationTest` (kształt `tests/Feature/LocalitySeedMigrationTest.php` + ładowanie migracji jak `SourceMigrationTest.php:14-26`): cofnij migrację, wstaw surowe wiersze w dwóch drzewach (pochodzenie pełne, null, `''`, `'   '`), `up()`; asercje: `archive` = `provenance` bajt w bajt dla niepustych, null dla pustych, `provenance` nietknięte we wszystkich wierszach, ponowne `up()` nie zmienia danych, `down()` usuwa kolumny i zostawia `provenance`.
- `SourcesTest` (aktualizacja przypadków z `provenance` w `:48,528,542-553,595,610` na `archive`): akt zapisuje `parish_id` i `archive`; nagrobek zapisuje `archive`, a `parish_id` jest ignorowane; obca `parish_id` → błąd walidacji bez zapisu; edycja źródła z `provenance` w bazie zostawia `provenance` bez zmian; zmiana aktu z parafią na nagrobek bez `discard_record_fields` → błąd `kind`, z potwierdzeniem → `parish_id` null; `sourcePanel` zawiera `parishes`, `localities`, `archives` (różne, bez pustych, tylko z drzewa).

### Success Criteria:

#### Automated Verification:

- Migracje przechodzą w obie strony: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Testy migracji i źródeł przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourceArchiveMigrationTest.php tests/Feature/SourcesTest.php tests/Feature/SourceMigrationTest.php tests/Backup`
- Larastan przechodzi: `vendor/bin/sail composer run types:check`
- Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 3: Komponent podpowiedzi i strona spisu parafii

### Overview

Współdzielone pole z podpowiedziami (tryb rekordu i tryb tekstu) z czystą logiką filtrowania w `lib/`, sprawdzone na stronie spisu parafii z polem siedziby i dopisaniem miejscowości jednym kliknięciem.

### Changes Required:

#### 1. Logika podpowiedzi

**File**: `resources/js/lib/suggestions.ts`, `resources/js/lib/suggestions.test.ts`

**Intent**: Czyste funkcje, które dają się przetestować w Vitest bez DOM: filtrowanie, kolejność i decyzja, czy pokazać „Dodaj …”.

**Contract**: `filterSuggestions<T>(options: T[], query: string, labelOf: (o: T) => string, limit?: number): T[]` — dopasowanie fragmentu bez wielkości liter (`toLocaleLowerCase('pl')`, przycięte zapytanie). Puste zapytanie zwraca wszystkie. Wynik sortowany: najpierw zaczynające się od zapytania, potem reszta, w obu grupach `Intl.Collator('pl')`. `canOfferCreate(options, query, nameOf): boolean` — prawda, gdy przycięte zapytanie jest niepuste i żadna opcja nie ma nazwy równej zapytaniu bez wielkości liter. Testy: „łódź” znajduje „Łódź”, „jana” znajduje „Św. Jana”, prefiks przed środkiem, brak „Dodaj” przy dokładnym dopasowaniu „KRAKÓW” ↔ „Kraków”, brak „Dodaj” dla samych spacji.

#### 2. Komponent `SuggestField`

**File**: `resources/js/components/suggest-field.tsx`

**Intent**: Jedno pole z listą podpowiedzi pod spodem, używane dla parafii, siedziby i archiwum teraz i dla spisów w S-07/S-08/S-09. Bez nowych zależności, w stylu `components/ui/input.tsx`.

**Contract**: Props wspólne: `id`, `name` (nazwa pola w formularzu), `options`, `labelOf`, `placeholder?`, `disabled?`, `aria-invalid` przez błąd. Tryb rekordu (`mode="record"`): `value: number | null`, `onChange(id | null)`, ukryty `<input type="hidden" name={name}>` z id lub `''`, opcjonalne `onCreate?(query: string)` i `createLabel(query)` (np. „Dodaj parafię „{query}””) jako ostatnia pozycja listy. `offerCreate?: 'unmatched' | 'always'` (domyślnie `'unmatched'`): `'unmatched'` pokazuje „Dodaj …” tylko gdy `canOfferCreate` (miejscowości — unikalne po samej nazwie), `'always'` dla każdego niepustego zapytania (parafie — unikalne po nazwie i wyznaniu, więc ta sama nazwa nie wyklucza nowej parafii). Tryb tekstu (`mode="text"`): widoczny input nosi `name` i `defaultValue`, wybór podpowiedzi wpisuje tekst. Lista renderuje się pod polem w panelu (bez portalu), maks. ~8 pozycji z przewijaniem, w szerokości kolumny 20rem. Klawiatura, fokus i Escape — patrz Critical Implementation Details. Wartość wybrana pokazuje `labelOf(option)` w polu. Zmiana wartości musi być widoczna dla strażnika niezapisanych zmian strony. `isDirty` z Inertia `<Form>` liczy się tylko z natywnych zdarzeń `input`/`change`/`reset` na elemencie formularza (`@inertiajs/react` 3.8). Select „Typ aktu” je wysyła, bo Radix Select renderuje ukryty natywny `<select>`. Ukryty input ustawiany przez React i wartość wpisana programowo żadnych zdarzeń nie wysyłają. Dlatego: w trybie rekordu po zatwierdzeniu nowej wartości (efekt po renderze, nie przy montowaniu) `SuggestField` wysyła z ukrytego inputu `new Event('change', { bubbles: true })`. W trybie tekstu wybór podpowiedzi ustawia `value` natywnym setterem (`HTMLInputElement.prototype` `value`) i wysyła `new Event('input', { bubbles: true })`.

#### 3. Strona i panel spisu parafii

**File**: `resources/js/pages/parishes/index.tsx`, `resources/js/components/parishes/parish-panel.tsx`, `resources/js/components/parishes/parishes-table.tsx`, `resources/js/components/parishes/parishes-empty-state.tsx`, `resources/js/lib/parishes-index.ts`, `resources/js/lib/parishes-index.test.ts`, `resources/js/types/parish.ts`, `resources/js/types/index.ts`

**Intent**: Kopia modułu miejscowości (decyzja autora 5): tabela (Nazwa, Wyznanie, Siedziba), wyszukiwanie z debounce, panel z formularzem i strażnikiem niezapisanych zmian.

**Contract**: Panel `?parish=new|{id}` przez `useSidePanelNavigation('parish')`. Formularz: „Nazwa” (wymagana), „Wyznanie” (Select z „brak” jak „Typ aktu”, ukryty input `religion`), „Miejscowość siedziby” (`SuggestField` w trybie rekordu, `name="locality_id"`, `onCreate` → `useHttp` POST `localities.inline.store` z `{name: query}`; sukces dodaje opcję do lokalnej listy i ją wybiera; 422 pokazuje błąd pod polem). Filtr listy przez `parishes-index.ts` (nazwa i siedziba, `toLocaleLowerCase('pl')`, sort `Intl.Collator('pl')`) z testem Vitest.

#### 4. Rejestr panelu i nawigacja

**File**: `resources/js/lib/side-panel-url.ts`, `resources/js/lib/side-panel-url.test.ts`, `resources/js/components/app-sidebar.tsx`

**Intent**: Panel parafii działa jak pozostałe, w menu jest „Parafie” z licznikiem.

**Contract**: `PANEL_PROPS.parish = 'parishPanel'` (test `withPanel` usuwa klucz `parish` przy otwarciu innego panelu); pozycja „Parafie” po „Miejscowości” z `navCounts?.parishes` i ikoną z `lucide-react` (już w projekcie).

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Testy Pest strony parafii nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ParishesTest.php tests/Feature/AppShellTest.php`

#### Manual Verification:

- Na `/parishes` dodaję parafię „Św. Jana” z wyznaniem i siedzibą wybraną z listy; tabela i licznik w menu się aktualizują
- W polu siedziby wpisuję nową nazwę, wybieram „Dodaj miejscowość „…”” — miejscowość jest wybrana i pojawia się na `/localities`
- Klawiatura: strzałki, Enter wybiera bez zapisu formularza, Escape zamyka tylko listę, a nie panel
- Duplikat (ta sama nazwa i wyznanie, inna wielkość liter) pokazuje błąd pod nazwą
- Lista podpowiedzi mieści się w panelu przy 1366 px i wygląda poprawnie w ciemnym motywie

---

## Faza 4: Formularz i lista źródeł

### Overview

Pole „Parafia” z modalem „Nowa parafia” i pole „Archiwum” w panelu źródła, „Pochodzenie” znika z frontu, dialog zmiany rodzaju obejmuje parafię, a lista pokazuje parafię i archiwum.

### Changes Required:

#### 1. Typy i logika opisu

**File**: `resources/js/types/source.ts`, `resources/js/lib/source-description.ts`, `resources/js/lib/source-description.test.ts`, `resources/js/lib/sources-index.test.ts`

**Intent**: Front w kształcie zasobu z fazy 2. Data wgrania znika, gdy karta ma inną linię opisu. Dialog zmiany rodzaju wie o parafii.

**Contract**: `Source`: usuń `provenance`, dodaj `archive`, `parish_id`, `parish_label`; typ propsu `sourcePanel` rozszerzony o `parishes`, `localities`, `archives`, `religions`. `formatSourceMeta` zwraca „typ · rok · parafia” (parafia z `parish_label`). Czyta ją też picker źródła przy fakcie (`resources/js/components/sources/source-picker-dialog.tsx:90`), który przez to zacznie pokazywać parafię — zamierzone, sprawdzić wizualnie przy 4.10. `showsUploadDate` jest prawdą, gdy nie ma linii meta ani archiwum. `ErasedField.field` obejmuje `'parish_id'` (etykieta „Parafia”, wartość = `parish_label` zapisanej lub nazwa wybranej w formularzu). `erasedRecordFields` przyjmuje `formValues.parish_id` i listę parafii do etykiety. `isUndescribed` liczy `archive` i `parish_id` zamiast `provenance`. Testy z „pochodzenie” (`source-description.test.ts:40,236`) przepisane na archiwum, nowe przypadki parafii.

#### 2. Pola opisu

**File**: `resources/js/components/sources/source-description-fields.tsx`

**Intent**: Parafia obok typu aktu (tylko akt, ukryta klasą `hidden` jak „Typ aktu”, więc nadal się wysyła i zostaje zerowana na serwerze), archiwum dla każdego rodzaju, bez „Pochodzenia”.

**Contract**: Kolejność: Nazwa, [akt: Typ aktu, Parafia, Numer wpisu], Rok, Archiwum, Link, Notatka. „Parafia” = `SuggestField` trybu rekordu, `name="parish_id"`, opcje z `sourcePanel.parishes`, `labelOf` = `label`, `offerCreate="always"`, `onCreate(query)` otwiera `NewParishDialog` z nazwą = query. „Archiwum” = `SuggestField` trybu tekstu, `name="archive"`, opcje `sourcePanel.archives`, `defaultValue` = `defaults.archive`. Podpowiedź pod archiwum: „Np. Archiwum Diecezjalne w Tarnowie, sygnatura”. Błędy `errors.parish_id`, `errors.archive`. Nowe propsy komponentu: `parishes`, `archives`, `localities`, `religions`.

#### 3. Modal „Nowa parafia”

**File**: `resources/js/components/parishes/new-parish-dialog.tsx`

**Intent**: Dopisanie parafii bez wychodzenia z panelu i bez utraty formularza źródła (decyzja autora 4). Siedziba z dopisaniem miejscowości jak na stronie spisu.

**Contract**: Radix Dialog (`components/ui/dialog.tsx`), tytuł „Nowa parafia”, pola Nazwa (wypełniona tekstem z pola), Wyznanie, Miejscowość siedziby (`SuggestField` z `onCreate` → `localities.inline.store`). Zapis przez `useHttp` POST `parishes.inline.store` wywołany z `onClick` przycisku `type="button"`. Bez `<form>` w modalu: syntetyczny `submit` Reacta przechodzi przez portal według drzewa React i trafiłby do `onSubmit` Inertia `<Form>` źródła (`preventDefault()` + `submit()`), a to wysłałoby źródło. Jeśli `<form>` będzie potrzebny (Enter w modalu), jego `onSubmit` woła `preventDefault()` i `stopPropagation()`. `onCreated(parish: ParishResource)` dodaje parafię do lokalnych opcji pola i ją wybiera. Opcje miejscowości modal dostaje z propsów od `SourceDescriptionFields`, który trzyma je w stanie (start: `sourcePanel.localities`). Miejscowość dopisana w modalu wraca przez `onLocalityCreated` do tego stanu, więc przy kolejnej nowej parafii w tym samym formularzu jest już na liście (bez drugiego „Dodaj miejscowość” i 422). Błędy 422 pod polami modala. Anulowanie nie zmienia pola parafii. Ten sam komponent można potem użyć na stronie spisu, ale nie jest to wymagane.

#### 4. Panel źródła

**File**: `resources/js/components/sources/source-panel.tsx`, `resources/js/pages/sources/index.tsx`

**Intent**: Przekazanie opcji z `sourcePanel` do pól. Dialog „Zmienić rodzaj źródła?” wymienia parafię.

**Contract**: `SourceFormData` (`source-panel.tsx:55`): `provenance` → `archive`, dochodzi `parish_id`. `NewSourceForm` i `EditSourceForm` przekazują `parishes`, `archives`, `localities`, `religions` do `SourceDescriptionFields`. Wyliczenie `erasedRecordFields` (`:376-388`) dostaje `parish_id` z formularza. Treść dialogu (`:501-525`) wymienia „Parafia: {label}”. Strażnik niezapisanych zmian reaguje na wybór lub dopisanie parafii.

#### 5. Lista źródeł

**File**: `resources/js/components/sources/sources-table.tsx`, `resources/js/components/sources/sources-gallery.tsx`

**Intent**: Widać, które akty mają parafię i skąd pochodzi źródło (odpowiedź na pytanie 2).

**Contract**: Tabela: kolumna „Pochodzenie” (`sources-table.tsx:99,176-185`) zastąpiona kolumnami „Parafia” (`parish_label`) i „Archiwum” (`archive`, obcinane z `title` jak dziś). Bez sortowania. Galeria: linia meta „typ · rok · parafia” (`formatSourceMeta`), pod nią archiwum w miejscu pochodzenia (`sources-gallery.tsx:60-67`).

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Brak odwołań do `provenance` we froncie: `grep -rn provenance resources/js` zwraca pusty wynik
- Istniejące E2E przechodzą: `npx playwright test`

#### Manual Verification:

- Na prawdziwym akcie: wpisuję nazwę, typ, rok i archiwum, potem dopisuję nową parafię z nową miejscowością siedziby przez modal — wpisane pola zostają, parafia jest wybrana, zapis się udaje
- Archiwum podpowiada wcześniej wpisane archiwa (w tym przeniesione z „Pochodzenia”), a wpisany nowy tekst zapisuje się dokładnie tak, jak wpisano
- Zmiana aktu z parafią na nagrobek pokazuje dialog z parafią; po potwierdzeniu parafia znika
- Tabela pokazuje kolumny Parafia i Archiwum, galeria „typ · rok · parafia” i archiwum, przy 1366 px bez poziomego przewijania strony
- Escape w otwartej liście parafii nie zamyka panelu źródła

---

## Faza 5: E2E — parafia dopisana w locie w panelu aktu

### Overview

Jeden spec Playwright na ryzyko z roadmapy: dopisanie parafii (z nową miejscowością siedziby) w panelu nowego aktu nie gubi niezapisanego opisu, a zapisany akt ma parafię i archiwum. Prowadzony przez `/10x-e2e` (eksploracja przez `playwright-cli`, przegląd względem antywzorców, weryfikacja celowym błędem).

### Changes Required:

#### 1. Spec i wsparcie

**File**: `tests/e2e/source-panel-parish.spec.ts`, `context/foundation/test-stack.md`, `context/foundation/roadmap.md` (notatka przy S-11)

**Intent**: Chroni stan formularza w przeglądarce, którego Pest nie widzi.

**Contract**: Scenariusz: otwórz „Dodaj źródło”, wgraj plik, wybierz rodzaj akt, wpisz nazwę i rok, w „Parafia” wpisz unikalną nazwę z sufiksem timestamp, wybierz „Dodaj parafię „…””, w modalu wpisz nową miejscowość siedziby i wybierz „Dodaj miejscowość „…””, zapisz modal, potem asercje: nazwa i rok mają wpisane wartości, pole parafii pokazuje nową parafię; wpisz archiwum, zapisz źródło, a panel zapisanego źródła pokazuje parafię i archiwum. Lokatory `getByRole`/`getByLabel` (z `exact: true` dla „Nazwa” — `tests/e2e/source-panel-edit.spec.ts:35,43,53`), bez `waitForTimeout`, unikalne nazwy (sufiks timestamp). Sprzątanie: utworzone źródło usuwa `cleanupBySuffix` (`tests/e2e/support/api.ts:279-308`). Parafia i miejscowość zostają w bazie `e2e` — jawny wyjątek od zasady czyszczenia do S-11, bo tras usuwania parafii i miejscowości jeszcze nie ma, a baza `e2e` jest trwała (`scripts/e2e-server.sh` robi tylko `migrate`). Sufiks wyklucza kolizje przy ponownych i równoległych uruchomieniach. Wyjątek trafia do `test-stack.md`, a przy S-11 w roadmapie dochodzi notatka: „dopisać sprzątanie parafii i miejscowości w `source-panel-parish.spec.ts`”. Weryfikacja celowym błędem: np. zamiana `useHttp` na wizytę `router` w modalu musi przewrócić asercję o zachowanych polach.

### Success Criteria:

#### Automated Verification:

- Nowy spec przechodzi: `npx playwright test tests/e2e/source-panel-parish.spec.ts`
- Cały zestaw E2E przechodzi: `npx playwright test`

#### Manual Verification:

- Celowo wprowadzony błąd (przeładowanie panelu po dopisaniu parafii) przewraca spec, a po cofnięciu spec znów przechodzi

---

## Testing Strategy

### Unit Tests:

- `suggestions.ts`: dopasowanie fragmentu z polskimi wielkimi literami, kolejność prefiks → reszta, `canOfferCreate` przy dokładnym dopasowaniu innej wielkości liter i przy samych spacjach.
- `source-description.ts`: linia meta z parafią, data wgrania przy archiwum, `erasedRecordFields` z parafią zapisaną, wybraną i wyczyszczoną.
- `parishes-index.ts`, `side-panel-url.ts`: filtr spisu i klucz panelu `parish`.

### Integration Tests:

- Pest: `ParishesTest` (strona, panel, unikalność nazwa+wyznanie z NULL, siedziba z drzewa, trasy JSON, usunięcie konta z kaskadą), `LocalitiesTest` (trasa JSON), `SourcesTest` (parafia tylko dla aktu, archiwum dla każdego rodzaju, zachowanie `provenance`, potwierdzenie zmiany rodzaju, opcje panelu), `SourceArchiveMigrationTest`, strażniki `OwnershipBoundariesTest`, `AppShellTest`, `BackupRestoreTest`.
- E2E: `source-panel-parish.spec.ts` + istniejące `source-panel-edit.spec.ts`, `fact-source-scan.spec.ts` (tworzą akty bez parafii — pole jest opcjonalne, więc zostają zielone).

### Manual Testing Steps:

1. Opisz prawdziwy akt od zera z nową parafią i nową miejscowością siedziby — całość bez wychodzenia z panelu źródła (ryzyko z roadmapy).
2. Otwórz źródło z przeniesionym „Pochodzeniem”: archiwum ma pełny tekst. Wydziel ręcznie parafię i skróć archiwum, potem zapisz.
3. Zmień rodzaj aktu z parafią na „inne” i potwierdź utratę parafii.
4. Sprawdź listę źródeł w tabeli i galerii przy 1366 px i 2560 px.

## Performance Considerations

`sourcePanel` niesie pełne listy parafii, miejscowości i archiwów drzewa przy każdym otwarciu panelu. Przy skali jednego drzewa genealogicznego (dziesiątki do setek wpisów) to kilka KB. Jeśli spisy urosną do tysięcy, zamiana na wyszukiwanie przez `useHttp` GET nie zmieni API `SuggestField` (opcje z zewnątrz). Lista źródeł ładuje `parish` przez eager loading.

## Migration Notes

- Przed `migrate` produkcja robi `backup:run --only-db` (`scripts/deploy.sh`) — bez zmian.
- Kolejność: `create_parishes_table` przed `add_parish_and_archive_to_sources_table` (FK).
- Po wdrożeniu `provenance` zostaje w bazie, niewidoczne w aplikacji. Jego usunięcie to osobna, późniejsza migracja, gdy autor potwierdzi przeniesienie (CLAUDE.md: „Copy first, drop later”).
- Rollback: `down()` obu migracji usuwa nowe kolumny i tabelę. Archiwum wpisane po wdrożeniu przepada, ale `provenance` sprzed wdrożenia jest nietknięte.

## References

- Research: `context/changes/act-described-by-parish-and-archive/research.md` (decyzje autora 1–6)
- Roadmapa: `context/foundation/roadmap.md` — S-03 (`:148-160`)
- PRD: `context/foundation/prd.md` — FR-001 (`:95`), FR-008 (`:118`), FR-009 (`:121`)
- Wzorzec spisu: `context/archive/2026-10-05-places-gazetteer/plan.md`, `app/Http/Controllers/LocalityController.php`, `app/Concerns/LocalityValidationRules.php`, `app/Actions/Localities/StoreLocality.php`
- Panel źródła: `context/archive/2026-10-05-source-side-panel/plan.md`, `resources/js/components/sources/source-panel.tsx`
- Test migracji: `tests/Feature/LocalitySeedMigrationTest.php`, `tests/Feature/SourceMigrationTest.php`
- Inertia v3 `useHttp`: https://inertiajs.com/docs/v3/the-basics/http-requests

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Spis parafii — backend

#### Automated

- [x] 1.1 Migracje przechodzą: `vendor/bin/sail artisan migrate`
- [x] 1.2 Testy parafii, miejscowości, własności, powłoki i kopii przechodzą
- [x] 1.3 Larastan przechodzi
- [x] 1.4 Pint bez zmian

### Phase 2: Parafia i archiwum w źródle — backend i migracja danych

#### Automated

- [ ] 2.1 Migracje przechodzą w obie strony
- [ ] 2.2 Testy migracji i źródeł przechodzą
- [ ] 2.3 Larastan przechodzi
- [ ] 2.4 Pint bez zmian

### Phase 3: Komponent podpowiedzi i strona spisu parafii

#### Automated

- [ ] 3.1 Testy Vitest przechodzą
- [ ] 3.2 Typy przechodzą
- [ ] 3.3 Format i lint przechodzą
- [ ] 3.4 Build przechodzi
- [ ] 3.5 Testy Pest strony parafii nadal przechodzą

#### Manual

- [ ] 3.6 Dodanie parafii z wyznaniem i siedzibą na /parishes aktualizuje tabelę i licznik
- [ ] 3.7 „Dodaj miejscowość „…”” w polu siedziby tworzy i wybiera miejscowość
- [ ] 3.8 Klawiatura: strzałki, Enter bez zapisu formularza, Escape zamyka tylko listę
- [ ] 3.9 Duplikat nazwa+wyznanie pokazuje błąd pod nazwą
- [ ] 3.10 Lista podpowiedzi mieści się w panelu przy 1366 px i w ciemnym motywie

### Phase 4: Formularz i lista źródeł

#### Automated

- [ ] 4.1 Testy Vitest przechodzą
- [ ] 4.2 Typy przechodzą
- [ ] 4.3 Format i lint przechodzą
- [ ] 4.4 Build przechodzi
- [ ] 4.5 Brak odwołań do `provenance` we froncie
- [ ] 4.6 Istniejące E2E przechodzą

#### Manual

- [ ] 4.7 Dopisanie parafii z nową siedzibą przez modal zachowuje wpisane pola aktu
- [ ] 4.8 Archiwum podpowiada wcześniejsze archiwa i zapisuje wpisany tekst
- [ ] 4.9 Zmiana aktu z parafią na nagrobek pokazuje dialog z parafią
- [ ] 4.10 Tabela i galeria pokazują parafię i archiwum przy 1366 px
- [ ] 4.11 Escape w liście parafii nie zamyka panelu źródła

### Phase 5: E2E — parafia dopisana w locie w panelu aktu

#### Automated

- [ ] 5.1 Nowy spec przechodzi
- [ ] 5.2 Cały zestaw E2E przechodzi

#### Manual

- [ ] 5.3 Celowo wprowadzony błąd przewraca spec, po cofnięciu spec przechodzi
