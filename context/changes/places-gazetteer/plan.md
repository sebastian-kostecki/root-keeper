# Spis miejscowości z istniejących miejsc (S-01) — plan implementacji

## Overview

Autor dostaje własny spis miejscowości: nazwa i opis, nazwa unikalna w drzewie bez względu na wielkość liter. Spis startuje z istniejących tekstowych miejsc (`events.place`, `people.residence`), a teksty zostają nietknięte. Autor przegląda spis jako tabelę z wyszukiwaniem, dodaje i poprawia miejscowości w panelu bocznym F-02, a cudza miejscowość to 404 (FR-007, FR-029; roadmapa S-01).

## Current State Analysis

- Tekstowe miejsca to dokładnie dwie kolumny: `events.place` (urodzenie, zgon, ślub; `database/migrations/2026_09_23_045723_create_events_table.php:22`) i `people.residence` (`2026_09_23_045722_create_people_table.php:19`). Obie to `string` nullable z `tree_id` w tym samym wierszu, więc zasiew grupuje per drzewo bez złączeń. `sources.provenance` to archiwum, nie miejscowość — jest poza zasiewem.
- Zapisy przez HTTP są przycinane, a `''` → NULL (globalne `TrimStrings`/`ConvertEmptyStringsToNull`). Seedery, fabryki i tinker tego nie robią, więc migracja musi sama przycinać i pomijać puste napisy.
- Baza (także `testing`) ma `datctype = en_US.utf8`. `lower()` zmienia polskie wielkie litery (`lower('ŁÓDŹ ŻYWIEC') = 'łódź żywiec'`), a `'Kraków' < 'kraków' COLLATE "C"` jest prawdą (wielkie litery przed małymi). Sprawdzone 2026-10-05.
- Lokalne dane: 9 wydarzeń z miejscem (8 różnych wartości) i 1 zamieszkanie. Brak wariantów wielkości liter i spacji. Jedyne lokalne scalenie to „Kraków” ×2 w drzewie 17. Danych produkcyjnych nie sprawdzano.
- Wzorzec modułu należącego do drzewa: Sources (`routes/web.php:25`, `app/Http/Controllers/SourceController.php`, `app/Http/Requests/SourceUpdateRequest.php:23-26`, `app/Policies/SourcePolicy.php`, `app/Actions/Sources/*`, `app/Http/Resources/SourceResource.php`, `app/Models/Tree.php` → `sources()`).
- Powłoka panelu F-02: `resources/js/components/side-panel.tsx` (`SidePanel` z `title`, `contentKey`, `onRequestClose`, `actions`, `footer`), rejestr `PANEL_PROPS` w `resources/js/lib/side-panel-url.ts:6-9`, hook `resources/js/hooks/use-side-panel-navigation.ts`. Wzór resolvera po stronie strony to `app/Http/Controllers/SidePanelDemoController.php:20-37`. Wzór pytania o porzucenie zmian to `resources/js/pages/dev/side-panel.tsx` (`hasUnsavedChanges`, `confirmingClose`). S-01 jest pierwszym prawdziwym konsumentem powłoki z formularzem.
- W repo nie ma jeszcze unikalnego indeksu z `tree_id` ani `Rule::unique` poza e-mailem — S-01 ustanawia ten wzorzec.
- Dwa testy przekrojowe przewrócą się same: strażnik tras `tests/Feature/OwnershipBoundariesTest.php:136-151` (każda trasa kontrolera z `{param}` musi mieć przypadek) oraz `tests/Backup/BackupRestoreTest.php:26` (każda trwała tabela musi mieć wiersze; dane z `arrangeBackupData()` w `tests/BackupTestCase.php:127`).

## Desired End State

- W sidebarze jest pozycja „Miejscowości” z licznikiem. Strona `/localities` pokazuje miejscowości drzewa alfabetycznie po polsku, z wyszukiwaniem po nazwie.
- Po migracji każde drzewo ma po jednej miejscowości na każdą grupę tekstów miejsc o tej samej wartości `lower(btrim(tekst))`. `events.place` i `people.residence` mają wartości bajt w bajt takie jak przed migracją.
- „Dodaj miejscowość” otwiera pusty panel (`?locality=new`). Po zapisie panel pokazuje nowy wpis (`?locality={id}`), a lista go zawiera. Kliknięcie wpisu otwiera panel edycji, a zapis odświeża panel i listę.
- Nazwa, która różni się od istniejącej w tym drzewie tylko wielkością liter, jest odrzucana komunikatem walidacji. Ta sama nazwa w innym drzewie jest dozwolona.
- Cudza miejscowość — w adresie trasy lub w `?locality=` — to 404 bez żadnego zapisu.

Weryfikacja: Pest (`LocalitiesTest`, `LocalitySeedMigrationTest`, `OwnershipBoundariesTest`, `AppShellTest`, `BackupRestoreTest`), Vitest (`localities-index.test.ts`, `side-panel-url.test.ts`) i ręczny przegląd panelu.

### Key Discoveries:

- Mutacja kończy się `back()` na adres z kluczem panelu, więc otwarty panel i lista przeliczają się bez dodatkowego okablowania (`SourceController.php:138-149`; `context/archive/2026-10-04-side-panel-shell/plan.md:9`).
- `panelVisitOptions.only` zawiera wszystkie wartości `PANEL_PROPS` (`side-panel-url.ts:14-24`), a test Vitest sprawdza to w pętli. Nowy wpis wymaga tylko dopisania klucza.
- Autoryzacja aktualizacji w Form Request przez `Gate::inspect('update', $this->route(...))` daje 404 dla cudzego id (`SourceUpdateRequest.php:23-26`).
- Klient filtruje listę jak `resources/js/lib/sources-index.ts:73-88` (`toLocaleLowerCase('pl')`), a sortuje przez `Intl.Collator('pl')` jak `resources/js/lib/persons-index.ts:28`. Parametr `q` normalizuje serwer jak `SourceController::normalizeSearchQuery`.
- Testy migracji ładują plik przez `require database_path(...)` i wołają `up()`/`down()` (`tests/Feature/SourceMigrationTest.php:14-26`). Wzór zasiewu z testem ponownego uruchomienia to `tests/Feature/TreeBackfillTest.php:6-40`.

## What We're NOT Doing

- **Bez powiązania tekst → miejscowość.** Nie ma tabeli mapowania ani kolumn `locality_id` przy `events`/`people`. S-07 i S-08 dopasują miejscowość po nazwie (`lower(btrim)`), a brak dopasowania tworzy nową. Świadomie przyjęte ryzyko: literówka poprawiona w spisie przed S-07 (np. „Lubomla” → „Luboml”) wróci w S-07 jako nowa miejscowość; PRD dopuszcza ręczne poprawki (`prd.md:72`).
- **Bez dopisywania miejscowości przy zapisie osoby.** Formularz osoby nadal zapisuje `events.place`/`people.residence` jako tekst. Teksty wpisane po S-01 trafią do spisu dopiero w S-07/S-08.
- Bez usuwania i scalania miejscowości (S-11). Bez wyboru miejscowości w formularzach faktów i zamieszkania (S-07, S-08) i bez parafii (S-03).
- Bez dodatkowych pól: współrzędnych, jednostki administracyjnej, „czy nadal istnieje”, nazw historycznych. Te informacje idą do opisu (PRD Non-Goals).
- Panel pokazuje tylko formularz: nie ma listy tekstów źródłowych, użyć ani osób.
- Bez sortowania kolumn i widoków alternatywnych na liście.
- Bez testu E2E: Pest pokrywa kontrakty, Vitest pomocniki, a panel sprawdza się ręcznie (test-plan §1, §7).
- Bez ostrzeżenia o niezapisanych zmianach przy wyjściu z `/localities` innym linkiem (sidebar, breadcrumbs) — tylko przy zamknięciu panelu, przełączeniu wpisu i „Dodaj miejscowość”.
- Bez zmian w fabrykach `EventFactory`/`PersonFactory` i w `TreeViewDemoSeeder`.

## Implementation Approach

Trzy fazy od danych do UI. Faza 1 tworzy tabelę i zasiewa ją z tekstów; ma własny test migracji, więc dane są sprawdzone, zanim powstanie interfejs. Faza 2 dodaje endpointy, resolver panelu i licznik według wzorca Sources, razem z przypadkami w testach przekrojowych. Faza 3 dokłada stronę i panel na powłoce F-02.

Nazewnictwo: model `Locality`, tabela `localities`, trasy `localities.*`, klucz panelu `?locality=`, właściwość `localityPanel`. Słowo „place” koliduje z kolumną `events.place`, a CLAUDE.md i roadmapa mówią „locality”; F-02 rezerwował `?place=` tylko roboczo.

## Critical Implementation Details

- **Unikalność bez wielkości liter na dwóch poziomach.** Indeks funkcyjny `UNIQUE (tree_id, lower(name))` tworzy się przez `DB::statement` (Blueprint nie ma indeksu wyrażeniowego). Walidacja w Form Requestach sprawdza to samo zapytaniem w drzewie użytkownika (`lower(name) = lower(?)`), a przy aktualizacji pomija edytowany wpis, więc zmiana „kraków” → „Kraków” przechodzi. Zasiew musi grupować identycznie (`lower(btrim(...))`), inaczej indeks odrzuci wstawienie.
- **Reguła wyboru nazwy przy zasiewie jest deterministyczna.** Nazwa grupy to przycięty wariant o największej liczbie wystąpień, licząc łącznie `events.place` i `people.residence`. Przy remisie wygrywa wariant pierwszy w porządku `COLLATE "C"` (wielka litera przed małą: „Kraków” przed „kraków”). Kolejność id ani wstawiania nie rozstrzyga.

## Faza 1: Model danych i zasiew

### Overview

Tabela `localities`, model z fabryką, relacja drzewa oraz migracja zasiewu z tekstów miejsc z własnym testem. Do tego miejscowość w danych testu kopii zapasowej, żeby `BackupRestoreTest` pozostał zielony.

### Changes Required:

#### 1. Migracja tabeli

**File**: `database/migrations/2026_10_05_XXXXXX_create_localities_table.php` (`vendor/bin/sail artisan make:migration create_localities_table --no-interaction`)

**Intent**: Nowa tabela należąca do drzewa z nazwą i opisem oraz indeksem gwarantującym jedną miejscowość na nazwę (bez wielkości liter) w drzewie.

**Contract**: `localities`: `id`, `tree_id` (FK → `trees`, `cascadeOnDelete`, jak inne tabele drzewa), `name` string(255) not null, `description` text nullable, timestamps. Po `Schema::create`: `DB::statement('CREATE UNIQUE INDEX localities_tree_id_lower_name_unique ON localities (tree_id, lower(name))')`. `down()` = `Schema::dropIfExists('localities')`.

#### 2. Model, fabryka, relacja drzewa

**File**: `app/Models/Locality.php`, `database/factories/LocalityFactory.php`, `app/Models/Tree.php`

**Intent**: Model jak `Source` (PHPDoc `@property`, atrybut `#[Fillable]`, `HasFactory`) z relacją do drzewa. Fabryka daje polskie nazwy, żeby testy nie dostawały `en_US` city.

**Contract**: `Locality` — `@property int $id, int $tree_id, string $name, ?string $description`; `#[Fillable(['tree_id', 'name', 'description'])]`; `tree(): BelongsTo`. `Tree::localities(): HasMany<Locality, $this>` obok `sources()`. `LocalityFactory::definition()` → `tree_id` przez `Tree::factory()`, `name` unikalna w obrębie przebiegu (np. `fake()->unique()->city()`), `description` null.

#### 3. Migracja zasiewu

**File**: `database/migrations/2026_10_05_XXXXXX_seed_localities_from_place_texts.php` (timestamp po migracji tabeli)

**Intent**: Dla każdego drzewa tworzy jedną miejscowość na grupę tekstów miejsc o tej samej wartości `lower(btrim(tekst))`, z nazwą według reguły z „Critical Implementation Details”. Kopiuje, nie usuwa — `events.place` i `people.residence` zostają bez zmian (CLAUDE.md „Copy first, drop later”).

**Contract**:
- Źródła: `events(tree_id, place)` i `people(tree_id, residence)`, z pominięciem NULL i `btrim(...) = ''`.
- Wstawia `(tree_id, name = btrim(wariant), description = NULL, created_at = updated_at = now())` przez `DB::table`/SQL (nie modele, jak `2026_09_23_045726_create_trees_for_existing_users.php`).
- Idempotentna: grupa, dla której drzewo ma już miejscowość o tym samym `lower(name)`, jest pomijana (`ON CONFLICT (tree_id, lower(name)) DO NOTHING` albo `NOT EXISTS`). Ponowne `up()` nie dodaje wierszy ani nie rzuca błędu.
- `down()` nic nie robi, z komentarzem: usunięcie zasianych wierszy skasowałoby poprawki autora (nazwy, opisy) i dodane ręcznie wpisy nie dają się odróżnić od zasianych; tabelę i tak usuwa `down()` migracji tabeli.
- Szkic grupowania (do dostosowania przez implementującego):

```sql
WITH texts AS (
    SELECT tree_id, btrim(place) AS variant FROM events WHERE place IS NOT NULL AND btrim(place) <> ''
    UNION ALL
    SELECT tree_id, btrim(residence) FROM people WHERE residence IS NOT NULL AND btrim(residence) <> ''
), counted AS (
    SELECT tree_id, lower(variant) AS key, variant, count(*) AS uses FROM texts GROUP BY tree_id, lower(variant), variant
)
INSERT INTO localities (tree_id, name, created_at, updated_at)
SELECT DISTINCT ON (tree_id, key) tree_id, variant, now(), now()
FROM counted
ORDER BY tree_id, key, uses DESC, variant COLLATE "C"
ON CONFLICT (tree_id, lower(name)) DO NOTHING;
```

#### 4. Test migracji zasiewu

**File**: `tests/Feature/LocalitySeedMigrationTest.php` (`vendor/bin/sail artisan make:test --pest LocalitySeedMigrationTest --no-interaction`)

**Intent**: Wymagany przez CLAUDE.md test migracji przepisującej dane: wiersze w starym kształcie → `up()` → nowy kształt bez utraty wartości. Wyrocznią są oczekiwane listy nazw zapisane wprost w teście, nie wyprowadzone z kodu.

**Contract**: Plik migracji ładowany przez `require database_path(...)` (wzór `SourceMigrationTest.php:14-26`). Dane wstawiane przez `DB::table(...)->insert` (z pominięciem przycinania HTTP). Przypadki:
- Drzewo A, `events.place`: „Kraków”, „Kraków”, „ Lwów ”, „”, NULL, „Krakow”, „lipnica”; `people.residence`: „kraków”, „Lipnica”. Oczekiwane miejscowości drzewa A (bez kolejności): „Kraków”, „Lwów”, „Krakow”, „Lipnica”. „Kraków” wygrywa 2:1 z „kraków”; remis „Lipnica”/„lipnica” rozstrzyga `COLLATE "C"`; „Krakow” zostaje osobno; pusty napis i NULL nie tworzą wpisu.
- Drzewo B, `events.place`: „Kraków” → jedna miejscowość „Kraków” w drzewie B (grupowanie per drzewo).
- Po `up()` wszystkie wartości `events.place` i `people.residence` są identyczne jak przed migracją (łącznie z „ Lwów ”).
- Drugie `up()` nie zmienia liczby miejscowości i nie rzuca wyjątku. Miejscowość dodana ręcznie przed `up()` („KRAKÓW” w drzewie B) blokuje zasiew „Kraków” w tym drzewie i zostaje nietknięta.
- `down()` zostawia miejscowości bez zmian.

#### 5. Kopia zapasowa

**File**: `tests/BackupTestCase.php`

**Intent**: Nowa trwała tabela musi mieć wiersze w `arrangeBackupData()`, inaczej `BackupRestoreTest` padnie na `rows > 0`.

**Contract**: `arrangeBackupData()` tworzy jedną miejscowość w drzewie użytkownika (`Locality::factory()->for($tree)->create([...])`). Docblock metody wymienia miejscowość. `EPHEMERAL_TABLES` bez zmian.

### Success Criteria:

#### Automated Verification:

- Migracje przechodzą na bazie deweloperskiej: `vendor/bin/sail artisan migrate`
- Test migracji zasiewu przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/LocalitySeedMigrationTest.php`
- Test kopii zapasowej przechodzi: `vendor/bin/sail artisan test --compact tests/Backup/BackupRestoreTest.php`
- Larastan bez błędów: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Liczba miejscowości na drzewo zgodna z zapytaniem liczności na lokalnej bazie (Migration Notes; drzewo 17 ma jedną „Kraków”)

**Implementation Note**: Po fazie 1 i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Backend spisu

### Overview

Endpointy listy, dodawania i edycji, Policy, resolver panelu `?locality=` z gałęzią `new`, licznik w nawigacji. Do tego testy endpointów i przypadki w testach przekrojowych własności i powłoki.

### Changes Required:

#### 1. Trasy

**File**: `routes/web.php`

**Intent**: Zasób w grupie `auth, verified`, obok `sources`.

**Contract**: `Route::resource('localities', LocalityController::class)->only(['index', 'store', 'update']);` → `localities.index` GET `/localities`, `localities.store` POST, `localities.update` PUT `/localities/{locality}`. Po zmianie: `vendor/bin/sail artisan wayfinder:generate`.

#### 2. Policy

**File**: `app/Policies/LocalityPolicy.php`

**Intent**: Własność jak w `SourcePolicy`.

**Contract**: `view(User, Locality): Response`, `update(User, Locality): Response` → `$locality->tree_id === $user->tree->id ? Response::allow() : Response::denyAsNotFound()`. `delete` dojdzie w S-11.

#### 3. Form Requesty i reguła unikalności

**File**: `app/Http/Requests/LocalityStoreRequest.php`, `app/Http/Requests/LocalityUpdateRequest.php`, wspólne reguły w `app/Concerns/LocalityValidationRules.php`

**Intent**: Walidacja nazwy i opisu z unikalnością w drzewie bez względu na wielkość liter. Aktualizacja autoryzuje się w `authorize()` (test-plan wymaga autoryzacji w Form Request).

**Contract**:
- `name`: `required|string|max:255` + domknięcie sprawdzające `$this->user()->tree->localities()->whereRaw('lower(name) = lower(?)', [$value])`, przy aktualizacji z `whereKeyNot($this->route('locality')->id)`. Komunikat: „Miejscowość o tej nazwie jest już w spisie.”
- `description`: `nullable|string|max:5000`.
- `attributes()`: `name` → „nazwa”, `description` → „opis”.
- `LocalityUpdateRequest::authorize()` → `Gate::inspect('update', $this->route('locality'))`. `LocalityStoreRequest::authorize()` → `true` (drzewo bierze się z użytkownika).

#### 4. Akcje

**File**: `app/Actions/Localities/StoreLocality.php`, `app/Actions/Localities/UpdateLocality.php`

**Intent**: Zapis według konwencji kontroler → Form Request → Action.

**Contract**: `StoreLocality::handle(Tree $tree, array $attributes): Locality` tworzy przez `$tree->localities()->create(...)`, więc `tree_id` z ciała żądania jest ignorowany. `UpdateLocality::handle(Locality $locality, array $attributes): Locality` aktualizuje `name` i `description` (brak opisu → NULL). Typy `@param array{name: string, description?: string|null}`.

#### 5. Resource

**File**: `app/Http/Resources/LocalityResource.php`

**Contract**: `toArray()` → `{ id: int, name: string, description: string|null }`.

#### 6. Kontroler i resolver panelu

**File**: `app/Http/Controllers/LocalityController.php`

**Intent**: Strona spisu z listą i leniwą właściwością panelu (wzór `SidePanelDemoController` + własność z `HandleInertiaRequests::previewPerson`). Zapis prowadzi do panelu nowego wpisu, a edycja wraca `back()`, żeby panel i lista się przeliczyły.

**Contract**:
- `index(Request)` → `Inertia::render('localities/index', ['localities' => fn () => LocalityResource::collection($tree->localities()->orderBy('name')->get())->resolve(), 'filters' => ['q' => normalizeSearchQuery(q)], 'localityPanel' => fn (): ?array => ...])`.
- `localityPanel`: brak klucza albo wartość, która nie jest `'new'` ani ciągiem cyfr → `null`; `'new'` → `['locality' => null]`; cyfry → `$tree->localities()->findOrFail($id)` + `Gate::authorize('view', ...)` → `['locality' => LocalityResource::make(...)->resolve()]`. Cudzy lub nieistniejący id → 404.
- `store(LocalityStoreRequest, StoreLocality)` → flash `toast` „Dodano miejscowość” → `to_route('localities.index', ['locality' => $locality->id])`.
- `update(LocalityUpdateRequest, Locality, UpdateLocality)` → flash `toast` „Zapisano miejscowość” → `back()`.
- `normalizeSearchQuery` jak w `SourceController` (trim, max 100 znaków). Nie kopiuj metody trzeci raz, jeśli da się ją wydzielić bez ruszania istniejących kontrolerów — inaczej prywatna kopia jak w `PersonController`.

#### 7. Licznik w nawigacji

**File**: `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/global.d.ts`

**Contract**: `navCounts` dostaje `'localities' => $request->user()->tree->localities()->count()`. Typ `navCounts` w `global.d.ts` dostaje `localities: number`.

#### 8. Testy endpointów

**File**: `tests/Feature/LocalitiesTest.php` (`vendor/bin/sail artisan make:test --pest LocalitiesTest --no-interaction`)

**Intent**: Ścieżki szczęśliwe, walidacja i resolver panelu według `tests/Feature/SourcesTest.php` (gość przekierowany, lista tylko własnych, `assertInertiaFlash`, datasety walidacji). Cudze id sprawdza centralnie `OwnershipBoundariesTest`.

**Contract** — przypadki:
- Gość na `localities.index` → przekierowanie na logowanie.
- Lista zawiera tylko miejscowości własnego drzewa.
- `store` tworzy miejscowość w drzewie użytkownika, przekierowuje na `localities.index?locality={id}` i ustawia toast „Dodano miejscowość”.
- `update` zmienia nazwę i opis, wraca `back()` i ustawia toast „Zapisano miejscowość”. Pusty opis zapisuje NULL.
- Walidacja (dataset): brak nazwy, nazwa > 255, opis > 5000.
- Unikalność: „kraków” przy istniejącym „Kraków” w tym samym drzewie → błąd `name` i brak nowego wiersza. „Kraków” w innym drzewie → zapis przechodzi. Update „kraków” → „Kraków” tego samego wpisu → przechodzi. Update na nazwę innego wpisu tego drzewa (inna wielkość liter) → błąd.
- Resolver: `?locality=new` → `localityPanel.locality` null; `?locality={własne id}` → dane wpisu; `?locality=abc` i brak klucza → `localityPanel` null; `?locality=999999` → 404.

#### 9. Zalążek strony

**File**: `resources/js/pages/localities/index.tsx`

**Intent**: `config/inertia.php:66` ma `ensure_pages_exist => true` w testach, więc `assertInertia(... 'localities/index')` w fazie 2 wymaga istniejącego pliku strony. Faza 2 tworzy minimalny komponent (nagłówek „Miejscowości”), a faza 3 go wypełnia.

**Contract**: domyślny eksport komponentu strony przyjmującego props z kontrolera; bez logiki listy i panelu.

#### 10. Testy przekrojowe

**File**: `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/AppShellTest.php`

**Intent**: Nowa trasa z `{locality}` musi mieć przypadek 404, inaczej strażnik kompletności przewróci suite. Licznik liczy tylko własne drzewo.

**Contract**:
- `ownershipRecords()` dostaje `victimLocality` (miejscowość w drzewie ofiary). `ownershipSnapshot()` (`tests/Pest.php:117`) czyta tabele ze schematu, więc obejmie `localities` bez zmian.
- `ownershipRouteCases()` → `['localities.update', 'put', fn ($r) => ['locality' => $r['victimLocality']->id], fn ($r) => ['name' => 'Przejęta']]` w `$withBodies`.
- Nowy przypadek: `localities.index?locality={victimLocality}` → 404 bez zapisu, wzór testu stron-hostów `?person=` (`:153-172`). Do datasetu stron-hostów `?person=` dochodzi `localities.index`.
- Test wstrzyknięcia: `localities.store` z `tree_id` ofiary w ciele → miejscowość w drzewie atakującego, liczba miejscowości ofiary bez zmian (wzór `:174-210`).
- `AppShellTest`: `navCounts.localities` liczy miejscowości własnego drzewa i nie liczy cudzych.

### Success Criteria:

#### Automated Verification:

- Testy endpointów przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php`
- Testy własności przechodzą (z nowymi przypadkami i strażnikiem kompletności): `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php`
- Test powłoki przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/AppShellTest.php`
- Larastan bez błędów: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`
- Wayfinder wygenerowany i typy przechodzą: `vendor/bin/sail artisan wayfinder:generate && vendor/bin/sail npm run types:check`

**Implementation Note**: Po fazie 2 i zielonej weryfikacji automatycznej przejdź do fazy 3 (faza nie ma kroków ręcznych).

---

## Faza 3: Strona spisu i panel

### Overview

Strona `localities/index` z tabelą i wyszukiwaniem, panel z formularzem nazwy i opisu na powłoce F-02, wpis w rejestrze paneli, pytanie o porzucenie niezapisanych zmian oraz pozycja w sidebarze.

### Changes Required:

#### 1. Rejestr paneli

**File**: `resources/js/lib/side-panel-url.ts`, `resources/js/lib/side-panel-url.test.ts`

**Intent**: Klucz `locality` → właściwość `localityPanel`. Komentarz nad rejestrem przestaje zapowiadać S-01 jako przyszły.

**Contract**: `PANEL_PROPS = { person: 'personPreview', demo: 'demoPanel', locality: 'localityPanel' }`. Test dostaje przypadek `withPanel('/localities?person=3', 'locality', 'new')` → `'/localities?locality=new'` (otwarcie usuwa klucz innego panelu). Pętla `panelVisitOptions` obejmie nowy klucz sama.

#### 2. Pomocnik listy

**File**: `resources/js/lib/localities-index.ts`, `resources/js/lib/localities-index.test.ts`

**Intent**: Czysta logika filtrowania i sortowania, testowalna w Vitest, jak `sources-index.ts`.

**Contract**: `filterLocalities(localities: Locality[], q: string): Locality[]` — dopasowanie podciągu nazwy po `trim()` i `toLocaleLowerCase('pl')` (pusty `q` → wszystkie). `sortLocalities(localities)` — `Intl.Collator('pl')` po nazwie, remis po id. `buildLocalitiesQuery({ q })` — pomija pusty `q`. Testy: „łódź” znajduje „Łódź”; sortowanie „Łódź” po „Lwów” i przed „Mielec”; pusty `q` nie trafia do zapytania. Typ `Locality = { id: number; name: string; description: string | null }` w `resources/js/types`.

#### 3. Strona spisu

**File**: `resources/js/pages/localities/index.tsx`, `resources/js/components/localities/localities-table.tsx`

**Intent**: Lista według wzoru `sources/index.tsx`/`persons-table.tsx` z istniejących klocków (`search-field.tsx`, `table-parts.tsx`, `empty-state.tsx`). Nagłówek „Miejscowości”, breadcrumbs, przycisk „Dodaj miejscowość”.

**Contract**:
- Props: `localities: Locality[]`, `filters: { q: string }`, `localityPanel: { locality: Locality | null } | null`.
- Kolumny: nazwa i początek opisu (jedna linia, obcięta). Wiersz otwiera panel przez `hrefFor(id)` z `useSidePanelNavigation('locality')` i `panelVisitOptions`, z dostępną nazwą linku równą nazwie miejscowości. Wiersz otwartej miejscowości jest wyróżniony.
- Wyszukiwanie z debounce zmienia `?q=` (wzór `sources/index.tsx`); filtrowanie po stronie klienta.
- Puste stany: brak miejscowości w drzewie („Spis jest pusty…” + „Dodaj miejscowość”) oraz brak wyników wyszukiwania.
- „Dodaj miejscowość” → `hrefFor('new')`.

#### 4. Panel miejscowości

**File**: `resources/js/components/localities/locality-panel.tsx`

**Intent**: Formularz nazwy i opisu w `SidePanel` (wariant `standard`). Ten sam komponent obsługuje nowy wpis i edycję. Kopiuje przepływ pytania o porzucenie zmian z demo F-02.

**Contract**:
- `SidePanel` z `title` = „Nowa miejscowość” albo nazwa wpisu, `contentKey` = `id` albo `'new'` (reset formularza przy przełączeniu), `onRequestClose` = zamknięcie z pytaniem.
- Formularz Inertia `<Form>`: `LocalityController.store.form()` dla nowego, `LocalityController.update.form(id)` dla edycji, `options={{ preserveScroll: true }}`. Pola z etykietami „Nazwa” (`Input`) i „Opis” (`Textarea`), błędy przez `InputError`, przycisk „Zapisz”.
- Stan „niezapisane zmiany” pochodzi z formularza (np. `isDirty` z `<Form>`). Gdy jest prawdziwy, zamknięcie panelu, kliknięcie innego wiersza i „Dodaj miejscowość” otwierają modal `Dialog` „Odrzucić niezapisane zmiany?” z przyciskami „Odrzuć” (wykonuje odłożoną akcję) i „Wróć do edycji”. Strona przechwytuje kliknięcia wierszy i „Dodaj” (`preventDefault` + odłożony cel), gdy panel zgłasza niezapisane zmiany.
- Po udanym zapisie formularz nie jest „brudny” (dane przychodzą z serwera przez `back()`/przekierowanie).

#### 5. Nawigacja

**File**: `resources/js/components/app-sidebar.tsx`

**Contract**: Pozycja `{ title: 'Miejscowości', href: localitiesIndex(), icon: MapPin, count: navCounts?.localities }` po „Źródła”; import z `@/routes/localities`.

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą: `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Testy Pest zmiany przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php tests/Feature/LocalitySeedMigrationTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/AppShellTest.php`

#### Manual Verification:

- Sidebar, lista alfabetyczna, wyszukiwanie z polskimi znakami i puste stany („Miejscowości” z licznikiem; „łódź” znajduje „Łódź”)
- Dodanie miejscowości w panelu obok listy i panel nowego wpisu po zapisie (lista się zwęża, bez nakładki)
- Edycja odświeża panel i listę, odświeżenie strony otwiera ten sam wpis (`?locality={id}`)
- Komunikat walidacji dla nazwy różniącej się tylko wielkością liter
- Pytanie o porzucenie niezapisanych zmian przy zamknięciu, przełączeniu i dodaniu („Wróć do edycji” zachowuje wpisany tekst)
- Panel na wąskim ekranie otwiera się i zamyka poprawnie

**Implementation Note**: Po fazie 3 i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie. Następnie poproś o pełną suite: `vendor/bin/sail artisan test --compact`.

---

## Testing Strategy

### Unit Tests:

- Vitest: `localities-index.test.ts` (filtrowanie z polskimi znakami, sortowanie `Intl.Collator('pl')`, budowa zapytania), `side-panel-url.test.ts` (klucz `locality`, wartość `new`).

### Integration Tests:

- `LocalitySeedMigrationTest` — grupowanie po `lower(btrim)`, reguła nazwy (większość, remis `COLLATE "C"`), per drzewo, NULL i pusty napis, teksty bez zmian, idempotencja, `down()`.
- `LocalitiesTest` — gość, lista własnych, store/update z toastem i przekierowaniem, walidacja, unikalność bez wielkości liter w drzewie, resolver panelu.
- `OwnershipBoundariesTest` — `localities.update` cudzy id, `?locality=` cudzy id, wstrzyknięcie `tree_id`, strażnik kompletności.
- `AppShellTest` — `navCounts.localities`. `BackupRestoreTest` — tabela `localities` z wierszami.

### Manual Testing Steps:

1. `vendor/bin/sail artisan migrate` na kopii danych lokalnych; porównaj liczności z zapytania w Migration Notes.
2. Przejdź po kryteriach ręcznych fazy 3 na `/localities`.
3. Sprawdź, że formularz osoby nadal zapisuje i pokazuje tekstowe „Miejsce” i „Zamieszkanie” bez zmian.

## Performance Considerations

Lista idzie w całości do klienta (dziś kilka do kilkudziesięciu wpisów na drzewo); sortowanie i filtrowanie po stronie klienta jak w Źródłach. Indeks `(tree_id, lower(name))` obsługuje sprawdzenie unikalności. Zasiew to jedno zapytanie zbiorcze.

## Migration Notes

- Kolejność wdrożenia bez zmian: `scripts/deploy.sh` robi `backup:run --only-db` przed `migrate` — nie usuwać tego kroku.
- Runbook (precedens `context/archive/2026-09-29-scans-become-sources/change.md`): przed `migrate` zapisz wynik zapytania liczności, po `migrate` porównaj z liczbą miejscowości na drzewo:

```sql
-- przed i po: oczekiwana liczba miejscowości na drzewo
SELECT tree_id, count(DISTINCT lower(btrim(t))) AS expected
FROM (SELECT tree_id, place AS t FROM events UNION ALL SELECT tree_id, residence FROM people) s
WHERE t IS NOT NULL AND btrim(t) <> ''
GROUP BY tree_id ORDER BY tree_id;

-- po: rzeczywista liczba
SELECT tree_id, count(*) FROM localities GROUP BY tree_id ORDER BY tree_id;

-- przed: warianty, które zasiew połączy (różnią się tylko wielkością liter lub spacjami)
SELECT tree_id, lower(btrim(t)) AS key, array_agg(DISTINCT t) AS variants
FROM (SELECT tree_id, place AS t FROM events UNION ALL SELECT tree_id, residence FROM people) s
WHERE t IS NOT NULL AND btrim(t) <> ''
GROUP BY tree_id, lower(btrim(t)) HAVING count(DISTINCT t) > 1;
```

- Wycofanie: `migrate:rollback` cofa zasiew (no-op) i usuwa tabelę; tekstowe miejsca są nietknięte, więc nic nie ginie poza poprawkami w spisie.

## References

- Research: `context/changes/places-gazetteer/research.md`
- Roadmapa: `context/foundation/roadmap.md` (S-01, S-07, S-08, S-11)
- PRD: `context/foundation/prd.md:115` (FR-007), `:187` (FR-029), `:205`, `:219-221`
- Powłoka panelu F-02: `context/archive/2026-10-04-side-panel-shell/plan.md`, `resources/js/components/side-panel.tsx`, `resources/js/lib/side-panel-url.ts`, `resources/js/pages/dev/side-panel.tsx`
- Wzorzec modułu: `app/Http/Controllers/SourceController.php`, `app/Http/Requests/SourceUpdateRequest.php:23-26`, `app/Policies/SourcePolicy.php`
- Wzorce testów: `tests/Feature/SourceMigrationTest.php`, `tests/Feature/TreeBackfillTest.php`, `tests/Feature/SourcesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/SidePanelDemoTest.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Model danych i zasiew

#### Automated

- [x] 1.1 Migracje przechodzą na bazie deweloperskiej — b7d0b50
- [x] 1.2 Test migracji zasiewu przechodzi — b7d0b50
- [x] 1.3 Test kopii zapasowej przechodzi — b7d0b50
- [x] 1.4 Larastan bez błędów — b7d0b50
- [x] 1.5 Pint czysty — b7d0b50

#### Manual

- [x] 1.6 Liczba miejscowości na drzewo zgodna z zapytaniem liczności na lokalnej bazie — b7d0b50

### Phase 2: Backend spisu

#### Automated

- [x] 2.1 Testy endpointów przechodzą — 2a1c747
- [x] 2.2 Testy własności przechodzą (z nowymi przypadkami i strażnikiem kompletności) — 2a1c747
- [x] 2.3 Test powłoki przechodzi — 2a1c747
- [x] 2.4 Larastan bez błędów — 2a1c747
- [x] 2.5 Pint czysty — 2a1c747
- [x] 2.6 Wayfinder wygenerowany i typy przechodzą — 2a1c747

### Phase 3: Strona spisu i panel

#### Automated

- [x] 3.1 Testy Vitest przechodzą — 3103f6b
- [x] 3.2 Format i lint przechodzą — 3103f6b
- [x] 3.3 Typy przechodzą — 3103f6b
- [x] 3.4 Build przechodzi — 3103f6b
- [x] 3.5 Testy Pest zmiany przechodzą — 3103f6b

#### Manual

- [x] 3.6 Sidebar, lista alfabetyczna, wyszukiwanie z polskimi znakami i puste stany — 3103f6b
- [x] 3.7 Dodanie miejscowości w panelu obok listy i panel nowego wpisu po zapisie — 3103f6b
- [x] 3.8 Edycja odświeża panel i listę, odświeżenie strony otwiera ten sam wpis — 3103f6b
- [x] 3.9 Komunikat walidacji dla nazwy różniącej się tylko wielkością liter — 3103f6b
- [x] 3.10 Pytanie o porzucenie niezapisanych zmian przy zamknięciu, przełączeniu i dodaniu — 3103f6b
- [x] 3.11 Panel na wąskim ekranie otwiera się i zamyka poprawnie — 3103f6b
