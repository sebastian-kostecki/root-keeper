# Cmentarze jako rekord miejsca — Implementation Plan

## Overview

Cmentarz przestaje być rolą miejscowości i staje się rekordem drzewa osadzonym w miejscowości: nazwa (opcjonalna), miejscowość, zarządca (komunalny / wyznaniowy / wojenny / inny), wyznanie i opis. Dostaje trzecią zakładkę na `/places` z panelem, dodawaniem w locie, scalaniem i usuwaniem jak parafia. Nagrobek wskazuje cmentarz, a miejsce pochowania osoby to miejscowość z opcjonalnym cmentarzem. Istniejące dane przechodzą migracją „copy first”: nagrobki dostają cmentarze bez nazwy w swoich miejscowościach, a stare pochowania — cmentarz tylko wtedy, gdy wynika z nagrobka.

Research i decyzje autora 1, 2 i 6: `context/changes/places-review/research.md`.

## Current State Analysis

- Nagrobek wskazuje miejscowość: `sources.cemetery_locality_id` → `localities`, klucz obcy NO ACTION (`database/migrations/2026_10_06_125653_add_cemetery_and_buried_people_to_sources.php:17`); relacje `Source::cemetery()` (`app/Models/Source.php:88-91`) i `Locality::cemeterySources()` (`app/Models/Locality.php:66-69`).
- Pole „Cmentarz” w formularzu nagrobka to `LocalityPicker` z dodawaniem miejscowości w locie (`resources/js/components/sources/source-description-fields.tsx:164-174`).
- Pochowanie to wiersz `events` z `type='burial'`, bez daty i źródeł, z samym `locality_id` (`app/Enums/EventType.php:10,25-28`). Zapis: `PersonStoreRequest.php:29-36`, `CreatePerson.php:57-65`, `UpdatePerson.php:65-79`, `MapsEventInput.php:16-25`, ładunek `resources/js/lib/person-payload.ts:138-142,171-180`.
- `FillBurialPlacesFromGravestone` tworzy brakujące pochowania osób z listy pochowanych z `locality_id = cemetery_locality_id` i nigdy nie nadpisuje istniejącego pochowania (`app/Actions/Sources/FillBurialPlacesFromGravestone.php:18-37`).
- Podpowiedź pochowania: `PersonFormResource::burialSuggestion()` zwraca jedyną miejscowość cmentarza nagrobków osoby (`app/Http/Resources/PersonFormResource.php:55,60-73`); pole w `resources/js/components/persons/person-section-fields.tsx:189-235` („Podpowiedź z nagrobka”).
- Miejsce pochowania czytają: panel osoby (`PersonResource.php:60`, `overview-tab.tsx:95-116`), oś czasu (`TimelineEntryResource.php:46`), karty drzewa (`SheetPersonResource.php:77-81`, `lib/pair-details.ts:24-37`).
- Dopasowanie źródła po miejscu: filtr „Miejsce” na `/sources` buduje opcje w `SourceController::placeOptions` (`app/Http/Controllers/SourceController.php:115-140`), dopasowuje po stronie klienta `sources-index.ts::matchesPlace` (`resources/js/lib/sources-index.ts:261-279`) na kluczach `miejscowosc-{id}` / `parafia-{id}`, a serwer powtarza to w `placeShows` (`SourceController.php:320-338`). Wybór źródła do faktu szuka tekstowo w `parish_name` / `locality_name` (`resources/js/lib/source-place-year-filter.ts:22-30`); dla nagrobka `locality_name` to nazwa miejscowości cmentarza (`app/Http/Resources/SourceResource.php:73`). Decyzja PRD przy FR-004 (`context/foundation/prd.md:247`) wymaga, by nagrobek dalej trafiał się przez miejscowość cmentarza.
- Miejscowość liczy odwołania (`PlaceController.php:27,50`, `LocalityResource.php:36-65`, `lib/places-usage.ts`), blokuje usunięcie przy dowolnym odwołaniu (`DeleteLocality.php:22-25`) i przy scalaniu przenosi parafie, zdarzenia, zamieszkania i nagrobki (`MergeLocality.php:44-47`).
- Wzorzec do powtórzenia: parafia — rekord drzewa osadzony w miejscowości z pełnym zestawem: `Parish` + `ParishPolicy` + `ParishValidationRules` + `Parish{Store,Update,Merge}Request` + `app/Actions/Parishes/{Store,Update,Delete,Merge}Parish.php` + `ParishController` / `InlineParishController` / `ParishMergeController` + `ParishResource`; frontend `components/parishes/{parish-panel,new-parish-dialog,delete-parish-dialog,merge-parish-dialog,parishes-table}.tsx`, `lib/parishes-index.ts`; segment `?kind=` w `PlaceController.php:20-95` i `pages/places/index.tsx`.

## Desired End State

Na `/places` są trzy zakładki: Miejscowości, Parafie, Cmentarze. Cmentarz ma panel (nazwa, miejscowość, zarządca, wyznanie, opis), da się go dodać, zmienić, scalić z innym i usunąć, gdy nic go nie wskazuje. W formularzu nagrobka „Cmentarz” wybiera cmentarz albo dodaje nowy w oknie. W sekcji Zgon osoby obok „Miejsca pochowania” jest opcjonalne pole „Cmentarz” z cmentarzami tej miejscowości, podpowiadane z nagrobka. Panel osoby i oś czasu pokazują „Cmentarz parafialny, Luboml” albo „cmentarz Luboml”, a karty drzewa dalej „poch. Luboml”. Filtr „Miejsce” ma grupę Cmentarze; nagrobek dalej trafia się przez miejscowość cmentarza. Istniejące nagrobki i pochowania mają cmentarze tam, gdzie wynika to z danych.

Weryfikacja: Pest (w tym dwa testy migracji), Vitest, lint, typy, build, zaktualizowany spec `tests/e2e/gravestone-cemetery-and-buried.spec.ts`, zrzuty w `context/changes/cemeteries/screenshots/`.

### Key Discoveries:

- Parafia nie ma miejscowości w kluczu (`database/migrations/2026_10_05_114218_create_parishes_table.php:28`), cmentarz musi ją mieć: „Cmentarz parafialny” powtarza się w wielu miejscowościach, a scalenie miejscowości może zderzyć dwa cmentarze o tej samej nazwie.
- Klucz obcy `sources.cemetery_locality_id` jest NO ACTION. Gdy kod przestanie przenosić tę kolumnę przy scalaniu miejscowości, usunięcie duplikatu złamie klucz — stąd zdjęcie samego ograniczenia w migracji przenoszącej (kolumna i wartości zostają).
- Opcje pickera miejscowości idą przez opcjonalny prop współdzielony `localityOptions` (`app/Http/Middleware/HandleInertiaRequests.php:70-74`), ładowany przez `LocalityPicker` przy montowaniu (`resources/js/components/localities/locality-picker.tsx:41-58`) — picker cmentarza powtarza to z `cemeteryOptions`.
- `places-index.ts::otherKindHint` (`resources/js/lib/places-index.ts:26-43`) i tekst statusu (`pages/places/index.tsx:277-282`) zakładają dokładnie dwie zakładki.
- Rejestry paneli: `app/Support/PersonPanelRedirect.php:17` (`PANEL_KEYS`) i `resources/js/lib/side-panel-url.ts:6-13` (`PANEL_PROPS`) muszą znać `cemetery`.
- Sprzątanie E2E usuwa parafie przed miejscowościami ze względu na klucze obce (`tests/e2e/support/api.ts:423-475`); cmentarze idą między nimi.

## What We're NOT Doing

- Usunięcie kolumny `sources.cemetery_locality_id` — zostaje do odczytu, usuwa ją późniejsza zmiana (jak kolumny legacy z issue #35).
- Współrzędne, „nie istnieje dziś” i nazwy alternatywne cmentarza.
- Listy nagrobków i pochowanych osób w panelu cmentarza (tylko liczniki w tabeli).
- Cmentarz na kartach drzewa i kartach przodków.
- Dopisywanie cmentarza starym pochowaniom bez nagrobka (np. „jedyny cmentarz w miejscowości”) i tworzenie cmentarzy dla miejscowości pochowań.
- Szukanie osób po cmentarzu, data pogrzebu, powiązanie cmentarza z parafią.
- Parafia / urząd z wezwaniem (osobna zmiana z tego samego researchu).
- Nowy spec E2E zakładki Cmentarze (pokrywa ją Pest).

## Implementation Approach

Cmentarz powtarza wzorzec parafii plik w plik, z dwiema różnicami: miejscowość jest wymagana i należy do klucza unikalności, a pusta nazwa jest dozwolona (etykieta „cmentarz {miejscowość}”). Etykietę buduje serwer (`Cemetery::label()`), front ją tylko wyświetla. Pionowe plasterki: rekord i operacje (1), zakładka `/places` (2), nagrobek na cmentarzu z migracją nagrobków (3), pochowanie na cmentarzu z migracją pochowań (4). Schemat wszystkich trzech kolumn powstaje w fazie 1, żeby scalanie i usuwanie cmentarza od początku znały oba rodzaje odwołań; aplikacja zaczyna je zapisywać w fazach 3 i 4.

Niezmiennik: pochowanie z cmentarzem ma `events.locality_id = cemeteries.locality_id`. Pilnują go wszyscy piszący: zapis osoby, `FillBurialPlacesFromGravestone`, `UpdateCemetery` (zmiana miejscowości cmentarza), `MergeCemetery` i `MergeLocality`. Dzięki temu karty drzewa, liczniki „faktów” miejscowości i wszystko, co czyta `events.locality_id`, działa bez zmian.

## Critical Implementation Details

- **Kolejność przy scalaniu miejscowości.** Najpierw scalić cmentarze duplikatu, które w docelowej miejscowości mają już cmentarz o tej samej nazwie (bez wielkości liter, pusta = pusta), potem przenieść pozostałe cmentarze, a dopiero na końcu `events.locality_id`. Odwrotna kolejność łamie indeks unikalności w połowie transakcji.
- **Pusta nazwa.** Nazwę po przycięciu zapisywać jako `null` (nigdy `''`), żeby walidacja, indeks `lower(coalesce(name, ''))` i etykieta widziały jeden stan.
- **Stara kolumna nagrobka.** Migracja fazy 3 zdejmuje klucz obcy z `sources.cemetery_locality_id` w tym samym kroku, w którym kod przestaje ją przenosić i liczyć; inaczej scalenie miejscowości ze starym nagrobkiem kończy się błędem 23503.

## Phase 1: Rekord cmentarza (backend)

### Overview

Schemat, enum zarządcy, model, polityka, walidacja, akcje, kontrolery, trasy, segment `?kind=cmentarze` z panelem w `PlaceController`, odwołania z miejscowości (usuwanie i scalanie), liczniki. Po tej fazie cmentarz działa przez żądania HTTP; UI zakładki jeszcze nie istnieje, nagrobki i pochowania jeszcze go nie wskazują.

### Changes Required:

#### 1. Schemat

**File**: `database/migrations/<timestamp>_create_cemeteries_table.php` (przez `vendor/bin/sail artisan make:migration --no-interaction`)

**Intent**: Tabela cmentarzy i dwie kolumny odwołań, jeszcze bez danych; odwracalna przez usunięcie kolumn i tabeli.

**Contract**: `cemeteries(id, tree_id FK cascadeOnDelete, locality_id FK NOT NULL NO ACTION, name string(255) nullable, manager string nullable, religion string nullable, description text nullable, timestamps)`; komentarz przy NO ACTION jak `create_parishes_table.php:19-20`. Indeks wyrażeniowy surowym SQL: `CREATE UNIQUE INDEX cemeteries_tree_id_locality_id_lower_name_unique ON cemeteries (tree_id, locality_id, lower(coalesce(name, '')))`. `sources.cemetery_id` i `events.cemetery_id`: `foreignId()->nullable()->constrained('cemeteries')` (NO ACTION) + indeks. `down()` usuwa obie kolumny (`dropConstrainedForeignId`), potem tabelę.

#### 2. Enum zarządcy

**File**: `app/Enums/CemeteryManager.php`

**Intent**: Zamknięta lista zarządców z polską etykietą, wzorem `LocalityKind`.

**Contract**: `Municipal='municipal'` (komunalny), `Religious='religious'` (wyznaniowy), `War='war'` (wojenny), `Other='other'` (inny); `label(): string`, `static options(): array<int, array{value: string, label: string}>`.

#### 3. Model, relacje, fabryka

**File**: `app/Models/Cemetery.php`, `app/Models/Tree.php`, `app/Models/Locality.php`, `app/Models/Source.php`, `app/Models/Event.php`, `database/factories/CemeteryFactory.php`, `database/factories/EventFactory.php`

**Intent**: Model cmentarza ze wspólną etykietą i relacjami do miejscowości, nagrobków i pochowań; fabryka tworzy cmentarz zawsze w miejscowości z tego samego drzewa.

**Contract**: `Cemetery`: `#[Fillable]`, rzutowania `manager` → `CemeteryManager`, `religion` → `Religion`; relacje `tree()`, `locality()`, `sources(): HasMany` (`cemetery_id`), `burials(): HasMany` (`Event`, `cemetery_id`); `label(): string` — `name === null` → „cmentarz {locality.name}”, inaczej „{name}, {locality.name}” (wymaga załadowanej `locality`). `Tree::cemeteries()`, `Locality::cemeteries()`, `Event::cemetery(): BelongsTo`; `Source` dostaje `cemetery_id` w `@property` i `#[Fillable]` (relację `cemetery()` przepina faza 3). `CemeteryFactory`: domyślnie nazwa „Cmentarz ######” i nowa miejscowość z drzewem cmentarza; stany `unnamed()`, `in(Locality)`, `withManager()`, `withReligion()`. `EventFactory::burial()` dostaje stan `atCemetery(Cemetery)` ustawiający `cemetery_id` i `locality_id`.

#### 4. Polityka, walidacja, żądania

**File**: `app/Policies/CemeteryPolicy.php`, `app/Concerns/CemeteryValidationRules.php`, `app/Http/Requests/Cemetery{Store,Update,Merge}Request.php`

**Intent**: Własność drzewa z 404 dla cudzego rekordu i walidacja z unikalnością w miejscowości, jak przy parafii.

**Contract**: polityka `view`/`update`/`delete` → `Response::denyAsNotFound()` przy innym `tree_id`. Trait: `cemeteryRules(?Cemetery $ignoring)` — `name` nullable string max 255 z domknięciem unikalności `(tree, locality_id, lower(coalesce(name,'')))` (komunikaty: „W tej miejscowości jest już cmentarz o tej nazwie.” / „W tej miejscowości jest już cmentarz bez nazwy — nadaj nazwę.”), `locality_id` required + exists w drzewie, `manager` / `religion` `Rule::enum` nullable, `description` nullable max 5000; `cemeteryAttributes()`; `cemeteryDetails(): array{name: ?string, locality_id: int, manager: ?CemeteryManager, religion: ?Religion, description: ?string}` (przycięta pusta nazwa → `null`), wzorem `LocalityValidationRules::localityDetails()`. Żądania jak `Parish*Request` (update: `Gate::inspect('update')`, merge: `Gate::inspect('delete')` przed regułami, `target_id` required, exists w drzewie, `notIn` siebie).

#### 5. Akcje

**File**: `app/Actions/Cemeteries/{Store,Update,Delete,Merge}Cemetery.php`, `app/Support/MergedText.php`, `app/Actions/Localities/MergeLocality.php`

**Intent**: Zapis, zmiana, usuwanie tylko nieużywanych i scalanie w transakcji, z pilnowaniem niezmiennika miejscowości pochowań.

**Contract**:
- `StoreCemetery::handle(Tree, array $details): Cemetery` i `UpdateCemetery::handle(Cemetery, array $details): Cemetery` — transakcja, `UniqueConstraintViolationException` → błąd walidacji na `name` (jak `StoreParish.php:18-29`). Zmiana `locality_id` w `UpdateCemetery` przestawia w tej samej transakcji `events.locality_id` wszystkich pochowań z tym `cemetery_id`.
- `DeleteCemetery` — `lockForUpdate`, odmowa przy `sources()->exists() || burials()->exists()` (klucz błędu `cemetery`), SQLSTATE 23503 mapowane na ten sam komunikat (jak `DeleteParish.php:16-43`).
- `MergeCemetery::handle(Cemetery $duplicate, Cemetery $target): Cemetery` — blokady w kolejności id, ten sam `tree_id` (inaczej błąd `target_id`), dozwolone między miejscowościami. Rdzeń `moveInto(Cemetery $duplicate, Cemetery $target): void` bez własnej transakcji i blokad (używa go też `MergeLocality`): `sources.cemetery_id` → cel; pochowania → `cemetery_id` celu i `locality_id` miejscowości celu; puste `manager`, `religion` celu uzupełnione z duplikatu, opisy łączone wspólnym helperem (niżej); nazwa i miejscowość zostają z celu; usunięcie duplikatu.
- `MergeLocality::mergedText()` (`MergeLocality.php:117-131`, dziś `private`) przenieść bez zmiany zachowania do `app/Support/MergedText.php` (`MergedText::of(?string $targetValue, ?string $duplicateValue): ?string`); `MergeLocality` i `MergeCemetery` wołają ten jeden helper — bez kopii reguły.

#### 6. Kontrolery, trasy, zasób

**File**: `app/Http/Controllers/{CemeteryController,InlineCemeteryController,CemeteryMergeController}.php`, `routes/web.php`, `app/Http/Resources/CemeteryResource.php`

**Intent**: Te same punkty wejścia co parafia, bez starego adresu listy (cmentarze nigdy go nie miały).

**Contract**: trasy `cemeteries.inline.store` (POST `cemeteries/inline`, przed zasobem), `Route::resource('cemeteries')->only(['store','update','destroy'])`, `cemeteries.merge` (POST `cemeteries/{cemetery}/merge`). `store` → toast „Dodano cmentarz.” i przekierowanie na `places.index` z `['kind' => 'cmentarze', 'cemetery' => id]`; `update` → `back()` z toastem; `destroy` → `Gate::authorize('delete')`; inline → `CemeteryResource` 201; merge → toast z `label()` celu i przekierowanie na jego panel. `CemeteryResource`: `id, name, locality_id, locality_name, manager, manager_label, religion, religion_label, description, label, sources_count (whenCounted), burials_count (whenCounted)`.

#### 7. `/places`, rejestry paneli, liczniki menu

**File**: `app/Http/Controllers/PlaceController.php`, `app/Support/PersonPanelRedirect.php`, `app/Http/Middleware/HandleInertiaRequests.php`

**Intent**: Trzecia zakładka po stronie serwera i panel `?cemetery=<id|new>` rozwiązywany leniwie jak `parishPanel`.

**Contract**: `normalizeKind` przyjmuje `miejscowosci | parafie | cmentarze` (reszta → `miejscowosci`); props (domknięcia) `cemeteries` (`with('locality')->withCount(['sources','burials'])`), `cemeteryPanel`, `cemeteryManagers => CemeteryManager::options()`. `PANEL_KEYS` + `cemetery`. `navCounts.cemeteries`.

#### 8. Odwołania z miejscowości

**File**: `app/Actions/Localities/DeleteLocality.php`, `app/Actions/Localities/MergeLocality.php`, `app/Http/Resources/LocalityResource.php`, `app/Http/Controllers/PlaceController.php`

**Intent**: Cmentarz jest odwołaniem miejscowości: blokuje jej usunięcie, przechodzi przy scalaniu, a kolidujące cmentarze scalają się w tej samej transakcji.

**Contract**: `DeleteLocality` liczy też `cemeteries()`. `MergeLocality` — przed dotychczasowymi przeniesieniami: dla każdego cmentarza duplikatu, który ma w celu odpowiednik o tym samym `lower(coalesce(name,''))`, `MergeCemetery::moveInto(duplikatowy, docelowy)`; potem `cemeteries()->update(locality_id)`; reszta bez zmian (stara kolumna nagrobka dalej przenoszona do fazy 3). `LocalityResource.usage` dostaje `cemeteries` (z `cemeteries_count`, wliczane w `total`, wymagane w `hasUsageCounts()`); `gravestones` zostaje do fazy 3. `PlaceController` dolicza `cemeteries` w `withCount` listy i panelu.

#### 9. Licznik użycia po stronie klienta

**File**: `resources/js/types/locality.ts`, `resources/js/lib/places-usage.ts`, `resources/js/lib/places-usage.test.ts`

**Intent**: Okna usuwania i scalania miejscowości nazywają cmentarze, bo serwer już je liczy.

**Contract**: `LocalityUsage.cemeteries: number`; frazy „cmentarzu / cmentarzach” (miejscownik) i „cmentarz / cmentarze / cmentarzy” (mianownik) w `localityUsagePhrase` / `localityUsageNominative`; `noLocalityUsage.cemeteries = 0`.

#### 10. Testy Pest

**File**: `tests/Feature/CemeteriesTest.php` (nowy), `tests/Feature/PlacesTest.php`, `tests/Feature/LocalitiesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/DeleteImpactTest.php`, `tests/Feature/AppShellTest.php`, `tests/BackupTestCase.php`

**Intent**: Szczęśliwa ścieżka i 404 dla każdego nowego punktu wejścia, unikalność w miejscowości, scalanie z każdym rodzajem odwołania, kolizja przy scalaniu miejscowości.

**Contract**:
- `CemeteriesTest`: goście; zapis z toastem i przekierowaniem; zmiana i czyszczenie pól; unikalność (ta sama nazwa w innej miejscowości dozwolona, dwa cmentarze bez nazwy w jednej miejscowości odrzucone, wielkość liter, pusta nazwa = `null`); dataset walidacji; miejscowość z cudzego drzewa; inline 201/422; zmiana miejscowości przestawia `locality_id` pochowań; usuwanie nieużywanego / blokowane przez nagrobek i przez pochowanie; scalanie przenosi nagrobki i pochowania (z `locality_id` celu, także między miejscowościami), uzupełnia puste pola, łączy opisy; scalanie odmawia celu cudzego, brakującego, siebie; usunięcie użytkownika kasuje cmentarze.
- `PlacesTest`: `kind=cmentarze` w datasecie normalizacji, panel `?cemetery=` null/new/404, lista z licznikami, częściowe przeładowanie bez zapytań, `usage.cemeteries` miejscowości.
- `LocalitiesTest`: dataset blokady usuwania + `cemetery`; scalanie przenosi cmentarze i scala kolidujące (z nazwą i bez), a ich nagrobki i pochowania trafiają na cmentarz celu.
- `OwnershipBoundariesTest`: `cemeteries.update`, `cemeteries.merge`, `cemeteries.destroy` w datasetach, `?cemetery=` cudzy → 404 bez zapisu, wstrzyknięty `tree_id` ignorowany dla `cemeteries.store` i `cemeteries.inline.store`.
- `DeleteImpactTest`: diff scalania cmentarzy dotyka tylko `cemeteries`, `sources`, `events`. `AppShellTest`: `navCounts.cemeteries`.
- `BackupTestCase`: fikstura dostaje jeden cmentarz (`POST cemeteries.store`) — `BackupRestoreTest.php:25-26` wymaga wierszy w każdej trwałej tabeli, a `cemeteries` istnieje od tej fazy.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate`
- Testy cmentarzy, miejsc i miejscowości przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/CemeteriesTest.php tests/Feature/PlacesTest.php tests/Feature/LocalitiesTest.php`
- Testy granic własności, wpływu zapisu i powłoki przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php tests/Feature/DeleteImpactTest.php tests/Feature/AppShellTest.php`
- Testy kopii zapasowej przechodzą: `vendor/bin/sail artisan test --compact tests/Backup`
- Formatowanie PHP: `vendor/bin/sail bin pint --dirty --format agent`
- Larastan poziom 7 bez błędów: `vendor/bin/sail composer types:check`
- Testy JS, lint i typy przechodzą: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Okno usuwania miejscowości z cmentarzem mówi o cmentarzu i blokuje usunięcie

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie weryfikacji ręcznej przed fazą 2.

---

## Phase 2: Zakładka Cmentarze na `/places` (frontend)

### Overview

Typy, helpery, tabela, panel, okna usuwania i scalania, trzy segmenty z nowym tekstem statusu, licznik w menu, kontrola tokenów i zrzuty.

### Changes Required:

#### 1. Typy i helpery

**File**: `resources/js/types/cemetery.ts` (+ eksport w `resources/js/types/index.ts`), `resources/js/lib/cemeteries-index.ts`, `resources/js/lib/cemeteries-index.test.ts`, `resources/js/lib/places-index.ts`, `resources/js/lib/places-index.test.ts`, `resources/js/lib/places-usage.ts`

**Intent**: Kształt cmentarza po stronie klienta, filtrowanie i sortowanie listy oraz trzy rodzaje miejsc w helperach strony.

**Contract**: `Cemetery` (pola `CemeteryResource`), `CemeteryOption { id, label, locality_id, locality_name }`, `CemeteryManagerOption`. `filterCemeteries` (nazwa, miejscowość, etykieta; bez wielkości liter), `sortCemeteries` (miejscowość, potem nazwa, `pl` Collator, remis po id). `PlacesKind` + `'cmentarze'`; `placesCounts` z trzema licznikami; `otherKindHint` ogólnie dla N zakładek: zwraca tablicę `{ kind, count }[]` — gdy bieżąca nie ma wyników, wszystkie pozostałe z trafieniami w kolejności zakładek, bez trafień → pusta tablica. Strona mapuje ją na listę akcji, a `PlacesNoResults` przyjmuje `others: OtherKindAction[]` (zamiast `other?`) i renderuje po jednym przycisku „Pokaż w {zakładce} (n)” — dotychczasowy tekst zostaje, więc `places-page.spec.ts:69` („Pokaż w parafiach (1)”) przechodzi bez zmian. `cemeteryUsagePhrase` / `cemeteryUsageNominative` (nagrobki i pochowania).

#### 2. Komponenty cmentarzy

**File**: `resources/js/components/cemeteries/{cemeteries-table,cemetery-panel,delete-cemetery-dialog,merge-cemetery-dialog}.tsx`

**Intent**: Kopie odpowiedników parafii z polami cmentarza.

**Contract**: tabela — kolumny Nazwa (etykieta jako link z `panelVisitOptions`, `aria-current`, przyklejona komórka), Miejscowość, Zarządca, Nagrobki, Pochowani. Panel — `SidePanel` jak `parish-panel.tsx` (usuń w `actions`, zapisz / „Scal z…” w stopce, zablokowane przy niezapisanych zmianach), formularz `CemeteryController.store/update.form()`: Nazwa (opis pola: „Puste — „cmentarz {miejscowość}””), Miejscowość (`LocalityPicker`, wymagana), Zarządca i Wyznanie (`Select` z wartością „brak” i ukrytym polem jak `parish-panel.tsx:219-247`), Opis. Okno usuwania rozgałęzia się po `sources_count + burials_count` (zablokowane z frazą i „Scal z…”). Okno scalania — `SuggestField mode="record"` po pozostałych cmentarzach z `labelOf = label`.

#### 3. Strona `/places`, rejestr paneli, menu

**File**: `resources/js/pages/places/index.tsx`, `resources/js/components/places/places-empty-state.tsx`, `resources/js/lib/side-panel-url.ts`, `resources/js/lib/side-panel-url.test.ts`, `resources/js/components/app-sidebar.tsx`, `resources/js/types/global.d.ts`

**Intent**: Trzy segmenty i trzeci panel bez łańcuchów „jeśli parafia, w przeciwnym razie miejscowość”.

**Contract**: `PANEL_PROPS.cemetery = 'cemeteryPanel'`; `openKey` / `openByKey` / `openFromList` / `splitPanelKey` rozpoznają `cemetery:<id>`; trzeci element `kindItems`, przycisk „Dodaj cmentarz”, tekst statusu dla trzech rodzajów, puste stany „Brak cmentarzy” i „brak wyników” w `places-empty-state.tsx`; licznik „Miejsca” w menu sumuje `navCounts.cemeteries`; segment z licznikiem 0 nie jest wyłączany (reguła `CLAUDE.md`).

#### 4. Kontrola tokenów i bramka wizualna

**File**: `resources/js/lib/view-tokens.test.ts`, `context/changes/cemeteries/screenshots/`

**Intent**: Nowe widoki przechodzą kontrolę tokenów; wygląd potwierdzony zrzutami.

**Contract**: `VIEW_FILES` + `components/cemeteries/cemeteries-table.tsx`, `components/cemeteries/cemetery-panel.tsx`. Zrzuty (jasny i ciemny motyw, szeroki i wąski ekran): zakładka Cmentarze z listą, panel cmentarza, okno zablokowanego usunięcia, pusta zakładka.

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą (helpery, rejestr paneli, kontrola tokenów): `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Istniejący spec strony miejsc przechodzi: `npx playwright test tests/e2e/places-page.spec.ts tests/e2e/places-merge-and-delete.spec.ts`

#### Manual Verification:

- Na `/places` zakładka Cmentarze dodaje cmentarz bez nazwy i z nazwą, a lista pokazuje „cmentarz Luboml” i „Cmentarz parafialny, Luboml”
- Drugi cmentarz bez nazwy w tej samej miejscowości pokazuje komunikat pod polem Nazwa
- Scalenie dwóch cmentarzy i próba usunięcia używanego działają jak przy parafii
- Szukanie bez wyników w bieżącej zakładce podpowiada pozostałe zakładki z trafieniami
- Zrzuty ekranu zapisane w `context/changes/cemeteries/screenshots/`

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie weryfikacji ręcznej przed fazą 3.

---

## Phase 3: Nagrobek wskazuje cmentarz

### Overview

Migracja nagrobków na cmentarze, zapis i odczyt `cemetery_id` źródła, picker cmentarza z oknem dodawania, etykiety, grupa Cmentarze w filtrze „Miejsce”, wypełnianie pochowania z cmentarzem, sprzątanie odwołań starej kolumny i aktualizacja speca E2E.

### Changes Required:

#### 1. Migracja nagrobków

**File**: `database/migrations/<timestamp>_move_gravestone_cemeteries_to_cemeteries.php`, `tests/Feature/GravestoneCemeteryMigrationTest.php`

**Intent**: Każda para (drzewo, miejscowość cmentarza) z nagrobków dostaje jeden cmentarz bez nazwy, a nagrobki go wskazują; stara kolumna zostaje do odczytu bez klucza obcego.

**Contract**: `up()`: dla każdej różnej pary `(sources.tree_id, sources.cemetery_locality_id)` z niepustym `cemetery_locality_id` — istniejący cmentarz bez nazwy w tej miejscowości albo nowy (`name = null`); `sources.cemetery_id` ustawione; ograniczenie klucza obcego `sources.cemetery_locality_id` zdjęte (kolumna i wartości bez zmian). `down()`: `sources.cemetery_locality_id` ← `cemeteries.locality_id` dla wierszy z `cemetery_id`, a `null` dla pozostałych (stara wartość mogła po scaleniu miejscowości wskazywać usunięte id), `cemetery_id = null`, klucz obcy przywrócony (cmentarze usuwa wycofanie migracji fazy 1). Test wg `tests/Feature/SourceMigrationTest.php`: dwa nagrobki w jednej miejscowości → jeden cmentarz; ta sama miejscowość w dwóch drzewach → dwa cmentarze; nagrobek bez cmentarza i źródło innego rodzaju bez zmian; istniejący cmentarz bez nazwy użyty ponownie; wartości `cemetery_locality_id` nietknięte; `down()` przywraca stary kształt.

#### 2. Zapis i model źródła

**File**: `app/Models/Source.php`, `app/Models/Locality.php`, `app/Concerns/SourceDescriptionValidationRules.php`, `app/Actions/Sources/{StoreSource,UpdateSource}.php`, `app/Http/Requests/SourceUpdateRequest.php`

**Intent**: Nagrobek zapisuje cmentarz zamiast miejscowości; stara kolumna przestaje być zapisywana.

**Contract**: `Source::cemetery(): BelongsTo` → `Cemetery` (`cemetery_id`); `cemetery_locality_id` usunięte z `#[Fillable]` (zostaje w `@property` z opisem „legacy, tylko do odczytu”); `Locality::cemeterySources()` usunięte. Reguła `cemetery_id`: `excludeIf(kind !== gravestone)`, nullable, integer, exists `cemeteries` w drzewie; atrybut „cmentarz”. Kształty tablic i domyślne wartości akcji z `cemetery_id`; `UpdateSource` czyści `cemetery_id` przy zmianie rodzaju. `wouldEraseRecordFields()` sprawdza `cemetery_id`.

#### 3. Wypełnianie pochowania

**File**: `app/Actions/Sources/FillBurialPlacesFromGravestone.php`

**Intent**: Pochowanie tworzone z nagrobka wskazuje cmentarz i jego miejscowość.

**Contract**: warunek wejścia na `cemetery_id`; nowe pochowanie z `cemetery_id` i `locality_id = cemetery.locality_id`; reszta reguł bez zmian (faza 4 dokłada uzupełnianie pustego cmentarza).

#### 4. Odczyt, opcje, filtr „Miejsce”

**File**: `app/Http/Resources/SourceResource.php`, `app/Http/Resources/PersonResource.php`, `app/Http/Resources/PersonFormResource.php`, `app/Http/Controllers/SourceController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Actions/Persons/BuildPersonPreview.php`, `app/Actions/Localities/{DeleteLocality,MergeLocality}.php`, `app/Http/Resources/LocalityResource.php`, `app/Http/Controllers/PlaceController.php`

**Intent**: Klient dostaje cmentarz z etykietą, a dopasowanie po miejscowości działa jak dotąd przez miejscowość cmentarza; dochodzi dopasowanie po cmentarzu.

**Contract**:
- `SourceResource`: `cemetery_id`, `cemetery_label`, `cemetery_locality_id` (wyliczane z `cemetery.locality_id` — nazwa pola klienta bez zmian), `locality_name` nagrobka = `cemetery.locality.name`; `cemetery_name` usunięte. `PersonResource.gravestones[].cemetery_label` zamiast `cemetery_name`. Eager load `cemetery.locality` w `SourceController.php:53,85`, `HandleInertiaRequests.php:75-77`, `BuildPersonPreview.php:29`.
- `PersonFormResource::burialSuggestion()` czyta relację `gravestones.cemetery`, którą ta faza przepina na `Cemetery` — bez zmiany id cmentarza trafiłoby do `burial.locality_id`. Do fazy 4 podpowiedź zostaje miejscowością: jedyna różna `cemetery.locality` nagrobków osoby w dotychczasowym kształcie `{id, name}` (`LocalityOption`); eager load `gravestones.cemetery.locality` w `BuildPersonPreview.php:29`. Faza 4 przełącza ją na `CemeteryOption`.
- `placeOptions`: `localities` przez `cemeteries.sources` lub `parishes.sources`; nowa grupa `cemeteries: array<int, array{id: int, label: string}>` (cmentarze użyte w nagrobkach, po etykiecie). `placeShows`: klucz `cmentarz-{id}` → nagrobek z tym `cemetery_id`; `miejscowosc-{id}` dla nagrobka → `cemetery?->locality_id`.
- `panelOptions` + `cemeteries` (opcje `CemeteryOption`) i `cemeteryManagers`; nowy opcjonalny prop współdzielony `cemeteryOptions` (`Inertia::optional`, jak `localityOptions`).
- Stara kolumna wypada z odwołań miejscowości: `DeleteLocality` bez `cemeterySources`, `MergeLocality` bez przenoszenia `cemetery_locality_id`, `LocalityResource.usage` bez `gravestones`, `PlaceController` bez `cemeterySources` w `withCount`.

#### 5. Picker cmentarza i okno dodawania

**File**: `resources/js/components/cemeteries/cemetery-picker.tsx`, `resources/js/components/cemeteries/new-cemetery-dialog.tsx`

**Intent**: Wybór cmentarza z listy i dodanie nowego w oknie bez przeładowania formularza, wzorem `LocalityPicker` + `NewParishDialog`.

**Contract**: `CemeteryPicker` props: `id`, `name` (ukryte pole z id), `label`, `value: CemeteryOption | null`, `options?` (bez nich ładuje `cemeteryOptions`), `localityId?: number | null` (zawęża listę do cmentarzy tej miejscowości), `errors`, `onChange`, `onCreated`; `SuggestField mode="record"`, `labelOf = label`, `offerCreate="always"` otwiera okno. `NewCemeteryDialog`: bez `<form>`, `useHttp` POST na `cemeteries.inline.store` (powód jak `new-parish-dialog.tsx:47-51`), pola Nazwa (wstępnie wpisany tekst), Miejscowość (`LocalityPicker`, wstępnie `localityId`), Zarządca, Wyznanie; po zapisie zwraca `CemeteryOption`.

#### 6. Formularz nagrobka, etykiety, filtry

**File**: `resources/js/types/source.ts`, `resources/js/types/person.ts`, `resources/js/types/locality.ts`, `resources/js/lib/places-usage.ts`, `resources/js/components/sources/source-description-fields.tsx`, `resources/js/components/sources/source-panel.tsx`, `resources/js/lib/source-description.ts`, `resources/js/lib/sources-index.ts`, `resources/js/components/sources/sources-filters.tsx`, `resources/js/lib/source-place-year-filter.ts`, `resources/js/components/persons/overview-tab.tsx`

**Intent**: Pole „Cmentarz” wybiera cmentarz, etykiety pokazują etykietę cmentarza, filtr ma grupę Cmentarze, wybór źródła znajduje nagrobek także po nazwie cmentarza.

**Contract**: `source-description-fields.tsx:164-174` → `CemeteryPicker name="cemetery_id" label="Cmentarz"` z cmentarzami dodanymi w panelu (stan jak `createdLocalities`, `source-panel.tsx:382-397`). `sourcePlaceLabel` nagrobka = `cemetery_label`; `erasedGravestoneFields` szuka `cemetery_id` w opcjach i dodanych, etykieta „Cmentarz”. `matchesPlace`: `cmentarz-{id}` → `cemetery_id`; `miejscowosc-{id}` bez zmian (przez `cemetery_locality_id`); `lacksData('place')` przez `parish_id` i `cemetery_id`. `SourcesPlaceOptions.cemeteries` i trzecia grupa „Cmentarze” w `sources-filters.tsx:373-388`. `filterSourcesByPlaceAndYear` dopasowuje też `cemetery_label`. Link nagrobka w panelu osoby: „{nagrobek} ({cemetery_label})”. Serwer przestaje wysyłać `usage.gravestones` miejscowości (§4), więc `LocalityUsage` traci `gravestones`, a `localityUsagePhrase` / `localityUsageNominative` / `noLocalityUsage` w `places-usage.ts` — frazy nagrobków (nagrobek liczy się teraz przez cmentarz).

#### 7. Testy

**File**: `tests/Feature/GravestoneTest.php`, `tests/Feature/SourcesTest.php`, `tests/Feature/WriteTargetsTest.php`, `tests/Feature/LocalitiesTest.php`, `tests/Feature/PlacesTest.php`, `tests/Feature/DeleteImpactTest.php`, `tests/BackupTestCase.php`, `resources/js/lib/{sources-index,source-description,source-place-year-filter,places-usage}.test.ts`, `tests/e2e/support/api.ts`, `tests/e2e/gravestone-cemetery-and-buried.spec.ts`

**Intent**: Istniejące asercje przechodzą na `cemetery_id`; nowe przypadki: grupa Cmentarze, klucz `cmentarz-`, cmentarz z cudzego drzewa, nagrobek znaleziony po miejscowości i po nazwie cmentarza.

**Contract**:
- Pest: `GravestoneTest` na `cemetery_id` (cudzy cmentarz odrzucony, pochowanie z `cemetery_id` i jego miejscowością, bramka zmiany rodzaju, podpowiedź pochowania = miejscowość cmentarza także dla cmentarza bez nazwy); `SourcesTest` klucze zasobu, `placeOptions.cemeteries`, `placeShows` dla `cmentarz-` i `miejscowosc-` nagrobka; `WriteTargetsTest` diff nagrobka (pochowanie z `cemetery_id`); `LocalitiesTest` dataset gravestone → odwołanie przez cmentarz; `PlacesTest` `usage` bez `gravestones`; scalenie miejscowości ze starym nagrobkiem (wartość legacy wskazuje duplikat) przechodzi bez błędu; `BackupTestCase` — nagrobek fikstury zapisuje `cemetery_id` zamiast `cemetery_locality_id` w `PUT sources.update` (`BackupTestCase.php:171-175`; stary klucz byłby po cichu pominięty).
- Vitest: dopasowanie `cmentarz-`, etykieta nazwanego i bez nazwy, wymazywane pole „Cmentarz”, wyszukiwanie po nazwie cmentarza.
- E2E: `api.ts` — `createCemetery(page, { locality, name? })` przez `/cemeteries/inline`, `cleanupBySuffix` usuwa cmentarze między parafiami a miejscowościami (po źródłach i osobach, które je wskazują), wybierając je z propsów `/places?kind=cmentarze` po `locality_name.includes(suffix) || name?.includes(suffix)` — cmentarz speca nie ma nazwy, więc samo `name` go nie znajdzie, typ `gravestones[].cemetery_label`; spec — konfiguracja tworzy miejscowość i cmentarz bez nazwy, krok 1 wybiera opcję „cmentarz {miejscowość}”; `burial_place` wciąż nazwa miejscowości (zmienia faza 4), link i wybór źródła po miejscowości bez zmian.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate`
- Test migracji nagrobków przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneCemeteryMigrationTest.php`
- Testy źródeł, nagrobków i miejsc przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneTest.php tests/Feature/SourcesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/LocalitiesTest.php tests/Feature/PlacesTest.php tests/Feature/DeleteImpactTest.php`
- Testy kopii zapasowej przechodzą: `vendor/bin/sail artisan test --compact tests/Backup`
- Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`
- Testy JS, lint, typy, build: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run build`
- Specy nagrobka i wyboru źródła przechodzą: `npx playwright test tests/e2e/gravestone-cemetery-and-buried.spec.ts tests/e2e/source-found-by-place-and-year.spec.ts tests/e2e/sources-grid-with-filters.spec.ts`

#### Manual Verification:

- W panelu nagrobka „Cmentarz” wybiera cmentarz, a „Dodaj cmentarz” otwiera okno; nowy cmentarz jest od razu wybrany
- Karty i tabela `/sources` pokazują „cmentarz Luboml” dla migrowanych nagrobków i „Cmentarz parafialny, Luboml” dla nazwanych
- Filtr „Miejsce” ma grupę Cmentarze; wybór miejscowości dalej pokazuje jej nagrobki
- Na lokalnej bazie po migracji każdy nagrobek z cmentarzem ma `cemetery_id`, a liczba cmentarzy równa się liczbie różnych miejscowości nagrobków w drzewie

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie weryfikacji ręcznej przed fazą 4.

---

## Phase 4: Pochowanie na cmentarzu

### Overview

Migracja starych pochowań (cmentarz tylko z nagrobka), pole „Cmentarz” w sekcji Zgon z podpowiedzią z nagrobka, uzupełnianie pustego cmentarza z nagrobka, etykieta w panelu osoby i na osi czasu, domknięcie speca E2E, PRD, reguły `CLAUDE.md` i zrzuty.

### Changes Required:

#### 1. Migracja pochowań

**File**: `database/migrations/<timestamp>_assign_cemeteries_to_burials.php`, `tests/Feature/BurialCemeteryMigrationTest.php`

**Intent**: Stare pochowanie dostaje cmentarz tylko wtedy, gdy osoba jest na liście pochowanych nagrobka z cmentarzem w miejscowości pochowania i taki cmentarz jest dokładnie jeden.

**Contract**: `up()`: dla pochowań z `cemetery_id IS NULL` i niepustym `locality_id` — różne `sources.cemetery_id` nagrobków, na których liście (`buried_people`) jest uczestnik pochowania z rolą Subject, a `cemeteries.locality_id = events.locality_id`; dokładnie jeden → `events.cemetery_id`. `down()`: bez zmian, z komentarzem — przypisanie jest addytywne, nie da się odróżnić wpisów migracji od wpisanych później, a kolumnę usuwa wycofanie migracji fazy 1. Test: Maria bez nagrobka w miejscowości z cmentarzem → bez zmian; osoba na nagrobku w tej samej miejscowości → cmentarz; nagrobek w innej miejscowości → bez zmian; dwa cmentarze z dwóch nagrobków w tej samej miejscowości → bez zmian; pochowanie z cmentarzem lub bez miejscowości → nietknięte.

#### 2. Zapis pochowania

**File**: `app/Http/Requests/PersonStoreRequest.php`, `app/Actions/Persons/{CreatePerson,UpdatePerson,MapsEventInput}.php`

**Intent**: Pochowanie zapisuje miejscowość i opcjonalny cmentarz tej miejscowości; wybrany cmentarz wyznacza miejscowość.

**Contract**: `burial.cemetery_id` nullable, integer, exists `cemeteries` w drzewie, `prohibited_unless:is_deceased`; gdy podano `burial.locality_id` i `burial.cemetery_id`, cmentarz musi leżeć w tej miejscowości („Ten cmentarz leży w innej miejscowości.”); atrybut „cmentarz pochowania”. Pochowanie powstaje, gdy wypełniona jest miejscowość lub cmentarz; zapisany `locality_id` = miejscowość cmentarza, gdy cmentarz podany. Create i update zawsze zapisują dla pochowania oba pola — `['locality_id' => …, 'cemetery_id' => $cemeteryId ?? null]` — poza wspólnym `eventAttributesFrom` (który obsługuje też narodziny, zgon i ślub): `putEvent` robi `$event->update(...)` tylko z podanych atrybutów (`UpdatePerson.php:177`), więc pominięty `cemetery_id` zostawiłby stary cmentarz w nowej miejscowości. Podklasy żądań dziedziczą regułę.

#### 3. Uzupełnianie z nagrobka i podpowiedź

**File**: `app/Actions/Sources/FillBurialPlacesFromGravestone.php`, `app/Http/Resources/PersonFormResource.php`

**Intent**: Ta sama reguła co w migracji działa przy zapisie nagrobka; podpowiedź w formularzu podaje cmentarz z miejscowością.

**Contract**: `Fill…`: osoba z pochowaniem bez cmentarza w miejscowości cmentarza nagrobka dostaje `cemetery_id` tylko wtedy, gdy wśród wszystkich nagrobków osoby jest dokładnie jeden różny cmentarz w tej miejscowości — ta sama reguła jednoznaczności co w migracji (§1); przy dwóch różnych cmentarzach pole zostaje puste, a kolejność zapisu nagrobków nie decyduje (wypełnia tylko puste; inne pochowania nietknięte jak dziś). `PersonFormResource`: `burial = { locality, cemetery: CemeteryOption | null }`; `burial_suggestion: CemeteryOption | null` — jedyny różny cmentarz nagrobków osoby (eager load `gravestones.cemetery.locality`).

#### 4. Wyświetlanie

**File**: `app/Http/Resources/PersonResource.php`, `app/Http/Resources/TimelineEntryResource.php`, `app/Actions/Persons/BuildPersonPreview.php`, `app/Http/Controllers/PersonController.php`, `app/Support/PersonTimeline.php` (tylko eager load, jeśli potrzebny)

**Intent**: Panel osoby i oś czasu pokazują pełną etykietę cmentarza; karty drzewa zostają przy miejscowości.

**Contract**: `burial_place` = `cemetery?->label() ?? locality?->name`; miejsce wiersza osi dla pochowania tak samo; eager load `events.cemetery.locality` tam, gdzie dziś `events.locality`. `SheetPersonResource` bez zmian.

#### 5. Pole w sekcji Zgon

**File**: `resources/js/types/person.ts`, `resources/js/components/persons/person-section-fields.tsx`, `resources/js/lib/person-payload.ts`, `resources/js/lib/person-payload.test.ts`

**Intent**: Obok „Miejsca pochowania” opcjonalny „Cmentarz” z cmentarzami tej miejscowości; podpowiedź z nagrobka wypełnia oba pola.

**Contract**: `CemeteryPicker id="burial-cemetery" name="burial.cemetery_id" label="Cmentarz" localityId={wybrana miejscowość}`; wybór cmentarza ustawia miejscowość; zmiana miejscowości na inną czyści cmentarz; okno dodawania startuje z wybraną miejscowością. Podpowiedź działa, gdy pochowanie jest puste albo ma miejscowość podpowiedzi bez cmentarza; dopisek „Podpowiedź z nagrobka” jak dziś. `personFormToPayload` i `mergeSectionIntoPayload` niosą `burial.cemetery_id` tak samo jak `burial.locality_id`.

#### 6. PRD i reguły projektu

**File**: `context/foundation/prd.md`, `CLAUDE.md`

**Intent**: Dokumenty mówią to, co robi kod.

**Contract**: PRD — FR-002 („cmentarz ze spisu cmentarzy, osadzony w miejscowości”, podpowiedź pochowania cmentarzem nagrobka), FR-022 (miejsce pochowania: miejscowość i opcjonalnie cmentarz), FR-004 (nagrobek pasuje przez miejscowość cmentarza lub nazwę cmentarza), akapit modelu danych (`prd.md:219,225`) z cmentarzami jako odwołaniem miejscowości. `CLAUDE.md` „Backend conventions”: cmentarze obok miejscowości i parafii (rekord drzewa, scalanie przenosi nagrobki i pochowania, usuwanie tylko nieużywanych), scalanie miejscowości przenosi cmentarze i scala kolidujące; reguła `/places` — trzy zakładki (Miejscowości, Parafie, Cmentarze).

#### 7. Testy, E2E i bramka wizualna

**File**: `tests/Feature/PersonsTest.php`, `tests/Feature/PersonTimelineTest.php`, `tests/Feature/GravestoneTest.php`, `tests/Feature/TreeTest.php`, `tests/BackupTestCase.php`, `tests/e2e/gravestone-cemetery-and-buried.spec.ts`, `context/changes/cemeteries/screenshots/`

**Intent**: Nowe reguły zapisu i wyświetlania pokryte; spec przeglądarkowy sprawdza pochowanie na cmentarzu.

**Contract**:
- `PersonsTest`: zapis z cmentarzem ustawia jego miejscowość; cmentarz z innej miejscowości odrzucony; cudzy cmentarz odrzucony (dataset pól miejsca); zmiana i czyszczenie cmentarza bez ruszania zgonu; zmiana samej miejscowości pochowania (bez `cemetery_id`) zeruje cmentarz; podgląd wystawia `burial.cemetery`.
- `BackupTestCase`: pochowanie fikstury z `cemetery_id`.
- `PersonTimelineTest`: miejsce pochowania = etykieta cmentarza. `TreeTest`: karta pary dalej „miejscowość”. `GravestoneTest`: podpowiedź = cmentarz (dataset jak `:307`); zapis nagrobka uzupełnia pusty cmentarz pochowania w tej samej miejscowości, nie rusza pochowania w innej miejscowości ani z innym cmentarzem; osoba na dwóch nagrobkach różnych cmentarzy w jednej miejscowości → cmentarz pochowania zostaje pusty.
- E2E spec: `burial_place` = „cmentarz {miejscowość}”, panel osoby pokazuje tę etykietę; kroki z linkiem i wyborem źródła po miejscowości bez zmian.
- Zrzuty (jasny i ciemny, szeroki i wąski): sekcja Zgon z polami Miejsce pochowania + Cmentarz i podpowiedzią, panel osoby z miejscem pochowania na cmentarzu, panel nagrobka z polem Cmentarz i oknem „Dodaj cmentarz”.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate`
- Test migracji pochowań przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/BurialCemeteryMigrationTest.php`
- Testy osób, osi czasu, nagrobków i drzewa przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/PersonTimelineTest.php tests/Feature/GravestoneTest.php tests/Feature/TreeTest.php tests/Backup`
- Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`
- Testy JS, lint, typy, build: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run build`
- Cały zestaw E2E przechodzi: `npx playwright test`

#### Manual Verification:

- W sekcji Zgon wybór cmentarza ustawia miejscowość, zmiana miejscowości czyści cmentarz, a podpowiedź z nagrobka wypełnia oba pola
- Panel osoby i oś czasu pokazują „Cmentarz parafialny, Luboml” albo „cmentarz Luboml”; karta drzewa dalej „poch. Luboml”
- Na lokalnej bazie po migracji pochowania osób z nagrobków mają cmentarz, a pozostałe tylko miejscowość
- PRD i `CLAUDE.md` opisują cmentarze zgodnie z decyzjami
- Zrzuty ekranu zapisane w `context/changes/cemeteries/screenshots/`

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie weryfikacji ręcznej; potem pełny zestaw Pest uruchamia użytkownik.

---

## Testing Strategy

### Unit Tests:

- Vitest: `cemeteries-index` (filtr, sortowanie, remis po id), `places-index` (trzy zakładki, podpowiedź innych zakładek), `places-usage` (frazy cmentarzy), `side-panel-url` (klucz `cemetery`), `sources-index` (`cmentarz-`, brak miejsca), `source-description` (etykiety, wymazywane pole), `source-place-year-filter` (nazwa cmentarza), `person-payload` (cmentarz pochowania).

### Integration Tests:

- Pest: `CemeteriesTest` (cały cykl rekordu i 404), dwa testy migracji (`GravestoneCemeteryMigrationTest`, `BurialCemeteryMigrationTest`), scalanie miejscowości z kolizją cmentarzy, zapis pochowania z cmentarzem, uzupełnianie z nagrobka, granice własności.
- Playwright: zaktualizowany `gravestone-cemetery-and-buried.spec.ts` — jedyny przepływ łączący formularz nagrobka, panel osoby i wybór źródła.

### Manual Testing Steps:

1. `/places` → Cmentarze → dodaj cmentarz bez nazwy w Lubomlu i „Cmentarz parafialny” w Lubomlu; drugi bez nazwy w Lubomlu odrzucony.
2. Nagrobek → Cmentarz → wybierz „Cmentarz parafialny, Luboml”, dodaj osobę zmarłą bez pochowania, zapisz → w jej panelu „Miejsce pochowania: Cmentarz parafialny, Luboml”.
3. Sekcja Zgon tej osoby → zmień miejscowość pochowania → pole Cmentarz się czyści.
4. `/sources` → filtr Miejsce → Cmentarze → „Cmentarz parafialny, Luboml” pokazuje tylko ten nagrobek; Miejscowości → Luboml pokazuje nagrobki obu cmentarzy.
5. Scal miejscowość-duplikat z cmentarzem bez nazwy z Lubomlem → zostaje jeden „cmentarz Luboml” z nagrobkami obu.

## Performance Considerations

Listy cmentarzy i opcje filtra to jedno zapytanie z `withCount` i eager loadem miejscowości na drzewo z dziesiątkami rekordów. Etykieta wymaga załadowanej `locality` — każdy zasób i opcja ładuje `cemetery.locality`, żeby nie było N+1.

## Migration Notes

- Kolejność wdrożenia: migracja schematu (faza 1) → nagrobki (faza 3) → pochowania (faza 4). Produkcja robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`).
- `sources.cemetery_locality_id` zostaje bez klucza obcego jako kolumna legacy; po scaleniach miejscowości jej wartości mogą wskazywać nieistniejące id. Usunięcie kolumny to osobna zmiana.
- Wycofanie fazy 3 przepisuje miejscowość cmentarza z powrotem do starej kolumny i przywraca klucz obcy; wycofanie fazy 4 nic nie zmienia (komentarz w migracji).

## References

- Research: `context/changes/places-review/research.md` (decyzje autora 1, 2, 6)
- Wzorzec parafii: `app/Actions/Parishes/MergeParish.php:19-97`, `app/Http/Controllers/PlaceController.php:20-95`, `resources/js/components/parishes/parish-panel.tsx`, `resources/js/components/parishes/new-parish-dialog.tsx`
- Poprzednia zmiana z tego researchu: `context/archive/2026-10-20-locality-details/plan.md`
- Nagrobek i pochowanie (S-09): `context/archive/2026-10-06-gravestone-cemetery-and-buried/plan.md`
- Scalanie i usuwanie miejsc (S-11): `context/archive/2026-10-06-places-merge-and-delete/plan.md`
- Wzorzec testu migracji: `tests/Feature/SourceMigrationTest.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Rekord cmentarza (backend)

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate` — 8f4e41a
- [x] 1.2 Testy cmentarzy, miejsc i miejscowości przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/CemeteriesTest.php tests/Feature/PlacesTest.php tests/Feature/LocalitiesTest.php` — 8f4e41a
- [x] 1.3 Testy granic własności, wpływu zapisu i powłoki przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php tests/Feature/DeleteImpactTest.php tests/Feature/AppShellTest.php` — 8f4e41a
- [x] 1.4 Testy kopii zapasowej przechodzą: `vendor/bin/sail artisan test --compact tests/Backup` — 8f4e41a
- [x] 1.5 Formatowanie PHP: `vendor/bin/sail bin pint --dirty --format agent` — 8f4e41a
- [x] 1.6 Larastan poziom 7 bez błędów: `vendor/bin/sail composer types:check` — 8f4e41a
- [x] 1.7 Testy JS, lint i typy przechodzą: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check` — 8f4e41a

#### Manual

- [x] 1.8 Okno usuwania miejscowości z cmentarzem mówi o cmentarzu i blokuje usunięcie — 8f4e41a

### Phase 2: Zakładka Cmentarze na `/places` (frontend)

#### Automated

- [x] 2.1 Testy JS przechodzą (helpery, rejestr paneli, kontrola tokenów): `vendor/bin/sail npm run test:js` — 05ca253
- [x] 2.2 Format i lint przechodzą: `vendor/bin/sail npm run check` — 05ca253
- [x] 2.3 Typy przechodzą: `vendor/bin/sail npm run types:check` — 05ca253
- [x] 2.4 Build przechodzi: `vendor/bin/sail npm run build` — 05ca253
- [x] 2.5 Istniejący spec strony miejsc przechodzi: `npx playwright test tests/e2e/places-page.spec.ts tests/e2e/places-merge-and-delete.spec.ts` — 05ca253

#### Manual

- [x] 2.6 Na `/places` zakładka Cmentarze dodaje cmentarz bez nazwy i z nazwą, a lista pokazuje „cmentarz Luboml” i „Cmentarz parafialny, Luboml” — 05ca253
- [x] 2.7 Drugi cmentarz bez nazwy w tej samej miejscowości pokazuje komunikat pod polem Nazwa — 05ca253
- [x] 2.8 Scalenie dwóch cmentarzy i próba usunięcia używanego działają jak przy parafii — 05ca253
- [x] 2.9 Szukanie bez wyników w bieżącej zakładce podpowiada pozostałe zakładki z trafieniami — 05ca253
- [ ] 2.10 Zrzuty ekranu zapisane w `context/changes/cemeteries/screenshots/`

### Phase 3: Nagrobek wskazuje cmentarz

#### Automated

- [x] 3.1 Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate`
- [x] 3.2 Test migracji nagrobków przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneCemeteryMigrationTest.php`
- [x] 3.3 Testy źródeł, nagrobków i miejsc przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneTest.php tests/Feature/SourcesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/LocalitiesTest.php tests/Feature/PlacesTest.php tests/Feature/DeleteImpactTest.php`
- [x] 3.4 Testy kopii zapasowej przechodzą: `vendor/bin/sail artisan test --compact tests/Backup`
- [x] 3.5 Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`
- [x] 3.6 Testy JS, lint, typy, build: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run build`
- [x] 3.7 Specy nagrobka i wyboru źródła przechodzą: `npx playwright test tests/e2e/gravestone-cemetery-and-buried.spec.ts tests/e2e/source-found-by-place-and-year.spec.ts tests/e2e/sources-grid-with-filters.spec.ts`

#### Manual

- [x] 3.8 W panelu nagrobka „Cmentarz” wybiera cmentarz, a „Dodaj cmentarz” otwiera okno; nowy cmentarz jest od razu wybrany
- [x] 3.9 Karty i tabela `/sources` pokazują „cmentarz Luboml” dla migrowanych nagrobków i „Cmentarz parafialny, Luboml” dla nazwanych
- [x] 3.10 Filtr „Miejsce” ma grupę Cmentarze; wybór miejscowości dalej pokazuje jej nagrobki
- [x] 3.11 Na lokalnej bazie po migracji każdy nagrobek z cmentarzem ma `cemetery_id`, a liczba cmentarzy równa się liczbie różnych miejscowości nagrobków w drzewie

### Phase 4: Pochowanie na cmentarzu

#### Automated

- [ ] 4.1 Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate`
- [ ] 4.2 Test migracji pochowań przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/BurialCemeteryMigrationTest.php`
- [ ] 4.3 Testy osób, osi czasu, nagrobków i drzewa przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/PersonTimelineTest.php tests/Feature/GravestoneTest.php tests/Feature/TreeTest.php tests/Backup`
- [ ] 4.4 Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`
- [ ] 4.5 Testy JS, lint, typy, build: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run build`
- [ ] 4.6 Cały zestaw E2E przechodzi: `npx playwright test`

#### Manual

- [ ] 4.7 W sekcji Zgon wybór cmentarza ustawia miejscowość, zmiana miejscowości czyści cmentarz, a podpowiedź z nagrobka wypełnia oba pola
- [ ] 4.8 Panel osoby i oś czasu pokazują „Cmentarz parafialny, Luboml” albo „cmentarz Luboml”; karta drzewa dalej „poch. Luboml”
- [ ] 4.9 Na lokalnej bazie po migracji pochowania osób z nagrobków mają cmentarz, a pozostałe tylko miejscowość
- [ ] 4.10 PRD i `CLAUDE.md` opisują cmentarze zgodnie z decyzjami
- [ ] 4.11 Zrzuty ekranu zapisane w `context/changes/cemeteries/screenshots/`
