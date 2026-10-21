# Parafia / urząd z wezwaniem — Implementation Plan

## Overview

Parafia staje się rekordem **prowadzącego akta**: siedziba (wymagana), rodzaj (parafia / USC-Standesamt / okręg bóżniczy lub metrykalny / inny), wyznanie i „Wezwanie / wyróżnik” (opcjonalne). Te pola tworzą klucz unikalności, a etykietę w stylu Geneteka buduje serwer: „Płock św. Bartłomieja (rzym.-kat.)”, „Gostynin USC”, „Andrzejewo okręg bóżniczy”. Dotychczasowa `name` zostaje w bazie jako „dawna nazwa” tylko do odczytu, dopóki autor jej nie usunie. W UI zakładka i grupa filtra nazywają się „Parafie i urzędy”, a pole w źródle „Parafia / urząd”. Archiwum zostaje tekstem.

Research i decyzje autora 1–4: `context/changes/parish-or-office/research.md`; źródło decyzji 3 i 4: `context/changes/places-review/research.md:166-167`.

## Current State Analysis

- Klucz to nazwa + wyznanie, siedziba jest opcjonalna: indeks `parishes_tree_id_lower_name_religion_unique` na `(tree_id, lower(name), coalesce(religion,''))` (`database/migrations/2026_10_05_114218_create_parishes_table.php:28`), `name` NOT NULL (`:18`), `locality_id` nullable FK NO ACTION (`:20-22`). Ten sam klucz powtarzają reguła (`app/Concerns/ParishValidationRules.php:27-42`) i `MergeParish::nameAndReligionTaken()` (`app/Actions/Parishes/MergeParish.php:89-97`).
- Etykieta: `Parish::label()` → „Nazwa (rzymskokatolickie)” (`app/Models/Parish.php:42-45`). `Religion` ma tylko pełne przymiotniki (`app/Enums/Religion.php:14-24`).
- Zapis: kontrolery składają argumenty ręcznie (`ParishController.php:35-40,52-57`, `InlineParishController.php:18-23`) do `StoreParish::handle(Tree, string, ?Religion, ?int)` / `UpdateParish::handle(Parish, string, ?Religion, ?int)`; nie ma helpera `parishDetails()`.
- Scalanie miejscowości przenosi parafie zwykłym `update` (`app/Actions/Localities/MergeLocality.php:50`), a kolizje obsługuje tylko dla cmentarzy (`:47,68-82` + `MergeCemetery::moveInto()` `app/Actions/Cemeteries/MergeCemetery.php:50-66`). `MergeParish` trzyma całą logikę w `handle()`.
- Surowe `name` czyta 8 miejsc: `parish-panel.tsx:80` (tytuł), `parishes-table.tsx:78` (link), `lib/parishes-index.ts:5-19` (szukanie i sortowanie), `SourceResource.php:72` (`parish_name`) → `lib/source-place-year-filter.ts:24-35`, SQL `orderBy('name')` w `PlaceController.php:32` i `SourceController.php:108,138`, sprzątanie E2E `tests/e2e/support/api.ts:478-483`.
- „Dodaj parafię „X”” wstawia wpisany tekst jako `name` do `NewParishDialog` (`new-parish-dialog.tsx:28,60`; `source-description-fields.tsx:272-283`). Okno dostaje już miejscowości drzewa (`localities`, `new-parish-dialog.tsx:31-32`), ale siedzibę ma na sztywno pustą (`useState(null)` `:62`, `value={null}` `:157`). Nazwy miejscowości są unikalne w drzewie bez względu na wielkość liter (`create_localities_table.php:24`), więc wpisany tekst trafia w co najwyżej jedną miejscowość.
- Fabryka domyślnie tworzy parafię bez siedziby (`database/factories/ParishFactory.php:25-27`); większość testów na tym polega.
- Dane lokalne: 2 parafie, 1 bez siedziby, 0 kolizji pod nowym kluczem; produkcji nie sprawdzano (`research.md` §5).

## Desired End State

Na `/places` zakładka „Parafie i urzędy” pokazuje etykiety z pól, wyszukuje po etykiecie i dawnej nazwie i sortuje po etykiecie. Panel ma pola Rodzaj, Miejscowość siedziby (wymagana), Wezwanie / wyróżnik (wymagane dla rodzaju „inny”) i Wyznanie. Gdy rekord ma dawną nazwę, panel pokazuje ją tylko do odczytu z przyciskiem „Usuń dawną nazwę”. W źródle pole „Parafia / urząd” podpowiada po etykiecie, a „Dodaj parafię / urząd „Płock”” otwiera okno z wybraną siedzibą Płock (gdy taka miejscowość istnieje; inaczej siedziba jest pusta). Dwa rekordy o tej samej siedzibie, rodzaju, wyznaniu i wezwaniu są niemożliwe, także po scaleniu miejscowości. Istniejące parafie zachowują siedzibę, wyznanie, źródła i dawną nazwę. Te, które kolidowałyby pod nowym kluczem, mają dawną nazwę skopiowaną do „Wezwanie / wyróżnik”.

Weryfikacja: Pest (w tym `tests/Feature/ParishSeatMigrationTest.php`), Vitest, lint, typy, build, zaktualizowane specyfikacje Playwright, zrzuty w `context/changes/parish-or-office/screenshots/`.

### Key Discoveries:

- Wiersz bez siedziby nie może wejść do klucza z siedzibą: przy `NULLS NOT DISTINCT` dwie stare parafie bez siedziby o tym samym wyznaniu zderzyłyby się. Stąd indeks częściowy `WHERE locality_id IS NOT NULL`; stary indeks po nazwie znika, bo nowe rekordy nie mają `name`, a dawnej nazwy nie da się edytować, tylko usunąć.
- Kopia dawnej nazwy do wyróżnika w grupach kolizji zawsze daje unikalny klucz: stary indeks gwarantował unikalność `(tree, lower(name), religion)`, a grupa kolizji ma wspólne drzewo i wyznanie.
- Wzorzec do powtórzenia: `CemeteryValidationRules` (`required` miejscowość, reguła `implicit` `:84-112`, `cemeteryDetails()` `:48-59`), `StoreCemetery::handle(Tree, array $details)`, `CemeteryManager::label()/options()`, `MergeCemetery::moveInto()` i `MergeLocality::mergeCollidingCemeteries()`.
- Opcje enumów trafiają do formularzy przez `PlaceController.php:37-38` i `SourceController.php:110,115` (`religions`, `cemeteryManagers`); tak samo pójdą `parishKinds`.
- `ParishMergeController.php:25` i `SourceController::placeOptions` (`:136-140`) wołają `label()` bez załadowanej `locality` — po zmianie etykiety to N+1 albo błąd; trzeba ładować relację.

## What We're NOT Doing

- Nie rozbijamy dawnych nazw na siedzibę i wezwanie automatycznie (`change.md`); jedyna automatyczna zmiana to kopia całej nazwy do wyróżnika w grupach kolizji.
- Nie usuwamy kolumny `name` („copy first, drop later”); usunięcie to osobna zmiana po ręcznym przeniesieniu.
- Nie zmieniamy nazw w kodzie: tabela `parishes`, `parish_id`, `Parish*`, trasy `parishes.*`, slug `?kind=parafie`, klucz filtra `parafia-{id}` zostają.
- Nie dodajemy archiwum jako rekordu — zostaje tekstem.
- Nie pokazujemy parafii w panelu miejscowości (`context/archive/2026-10-12-places-page/plan.md:63`).
- Nie dodajemy znacznika „nie istnieje” do etykiety parafii ani pickera parafii (`context/archive/2026-10-20-locality-details/plan.md:24`).
- Nie ustawiamy wyznania automatycznie z rodzaju (okręg bóżniczy nie wymusza „mojżeszowe”).
- Nie rozdzielamy okręgu bóżniczego i metrykalnego na dwa rodzaje.

## Implementation Approach

Najpierw backend z migracją, bo nowy klucz i etykieta wymuszają zmiany w każdym konsumencie. Potem scalanie (dwie akcje, jedna współdzielona metoda `moveInto()`), potem UI na gotowym kontrakcie zasobu, na końcu E2E, dokumentacja i bramka wizualna. Klucz żyje w czterech miejscach — indeks, reguła walidacji, `MergeParish`, `MergeLocality` — i każde z nich dostaje ten sam skład: `(tree_id, locality_id, kind, coalesce(religion,''), lower(coalesce(dedication,'')))`.

Etykieta (`Parish::label()`, wymaga załadowanej `locality`):
- z siedzibą: `{siedziba}[ {dopisek rodzaju}][ {wyróżnik}][ ({skrót wyznania})]` — „Płock św. Bartłomieja (rzym.-kat.)”, „Gostynin USC”, „Andrzejewo okręg bóżniczy”, „Płock Konsystorz”;
- bez siedziby (tylko stare wiersze): `{dawna nazwa}[ ({skrót wyznania})]` — „Parafia w Lubomlu (rzym.-kat.)”.

## Critical Implementation Details

- **Kolejność w migracji:** kopia nazwy do `dedication` w grupach kolizji musi nastąpić po dodaniu kolumn, a przed utworzeniem nowego indeksu; stary indeks można zdjąć dopiero po kopii (gwarantuje unikalność, na której ona polega). Grupa kolizji to wiersze z `locality_id IS NOT NULL` o wspólnym `(tree_id, locality_id, coalesce(religion,''))` i liczności > 1; wszystkie wiersze grupy dostają kopię.
- **Spójność bez siedziby i bez nazwy:** ograniczenie `CHECK (locality_id IS NOT NULL OR name IS NOT NULL)` jest siatką bezpieczeństwa; walidacja usunięcia dawnej nazwy i tak wymaga siedziby, bo każda edycja jej wymaga.

## Phase 1: Schemat, migracja i ścieżka zapisu

### Overview

Nowe pola, nowy klucz, etykieta z pól i zapis wymagający siedziby. Po tej fazie backend i jego testy działają w nowym kształcie; UI jeszcze wysyła stare pola (naprawia to faza 3).

### Changes Required:

#### 1. Enum rodzaju i skrót wyznania

**File**: `app/Enums/ParishKind.php` (nowy), `app/Enums/Religion.php`

**Intent**: Rodzaj prowadzącego akta jako enum z opisem do selecta i dopiskiem do etykiety; wyznanie dostaje krótki opis w stylu Geneteka obok pełnego.

**Contract**: `enum ParishKind: string` — `Parish = 'parish'` („parafia”, dopisek `null`), `CivilRegistry = 'civil_registry'` („USC / Standesamt”, dopisek „USC”), `JewishDistrict = 'jewish_district'` („okręg bóżniczy / metrykalny”, dopisek „okręg bóżniczy”), `Other = 'other'` („inny”, dopisek `null`); metody `label(): string`, `labelSuffix(): ?string`, `static options()` jak `CemeteryManager`. `Religion::shortLabel(): string` — „rzym.-kat.”, „gr.-kat.”, „ewang.”, „prawosł.”, „mojż.”, „inne”.

#### 2. Migracja

**File**: `database/migrations/<timestamp>_add_kind_and_dedication_to_parishes.php` (przez `vendor/bin/sail artisan make:migration --no-interaction`)

**Intent**: Dodać rodzaj i wyróżnik, uczynić `name` dawną nazwą (nullable), przenieść klucz na siedzibę bez gubienia danych.

**Contract**: `up()`: `kind` string NOT NULL default `'parish'`; `dedication` string(255) nullable; `name` → nullable; w grupach kolizji `dedication = name` (patrz Critical Implementation Details); drop `parishes_tree_id_lower_name_religion_unique`; `CREATE UNIQUE INDEX parishes_tree_seat_kind_religion_dedication_unique ON parishes (tree_id, locality_id, kind, coalesce(religion, ''), lower(coalesce(dedication, ''))) WHERE locality_id IS NOT NULL`; `CHECK parishes_seat_or_name_check (locality_id IS NOT NULL OR name IS NOT NULL)`. `down()`: zdejmuje check i nowy indeks, uzupełnia puste `name` z nazwy siedziby i wyróżnika, przywraca NOT NULL i stary indeks, usuwa kolumny; komentarz w `down()` mówi, że przywrócenie starego indeksu nie przejdzie, gdy nowe rekordy dają tę samą nazwę i wyznanie (trzeba je najpierw scalić).

#### 3. Model, fabryka, seeder

**File**: `app/Models/Parish.php`, `database/factories/ParishFactory.php`, `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Model zna nowe pola i buduje etykietę z siedziby; fabryka domyślnie tworzy rekord w nowym kształcie, a stary kształt jest osobnym stanem.

**Contract**: `#[Fillable]` + `kind`, `dedication`; cast `kind` → `ParishKind`; phpdoc `name string|null` („dawna nazwa, tylko do odczytu”), `dedication string|null`. `label()` według formatu z Implementation Approach (docblock: wymaga załadowanej `locality`). `toOption(): array{id: int, label: string, locality_id: ?int, locality_name: ?string}` jak `Cemetery::toOption()`, jeśli opcje parafii tego potrzebują. Fabryka: domyślnie `name => null`, `kind => ParishKind::Parish`, `dedication => null`, `locality_id` = nowa miejscowość tego samego drzewa; stany `withReligion()`, `seatedIn()`, nowe `ofKind(ParishKind)`, `dedicatedTo(string)`, `legacy(string $name, ?Locality $seat = null)` (dawna nazwa, domyślnie bez siedziby). Seeder demo: „Tarnów” z wezwaniem zamiast nazwy.

#### 4. Walidacja, żądania, akcje, kontrolery

**File**: `app/Concerns/ParishValidationRules.php`, `app/Http/Requests/{ParishStoreRequest,ParishUpdateRequest}.php`, `app/Actions/Parishes/{StoreParish,UpdateParish}.php`, `app/Http/Controllers/{ParishController,InlineParishController}.php`

**Intent**: Siedziba wymagana przy każdym zapisie, nowy klucz sprawdzany regułą `implicit`, dawną nazwę da się tylko usunąć; kontrolery przekazują jedną tablicę szczegółów jak cmentarze.

**Contract**: reguły: `kind` required `Rule::enum(ParishKind)`; `locality_id` required, integer, exists w drzewie; `religion` nullable enum; `dedication` nullable string max:255, wymagane gdy `kind = other` (komunikat „Podaj wezwanie lub wyróżnik dla rodzaju „inny”.”) + reguła unikalności `implicit` po kluczu (pomija nieliczbowe `locality_id`, ignoruje edytowany rekord, porównuje `trim`), komunikat „Ta parafia / urząd już istnieje: „:label”.”; `clear_name` (tylko update) `sometimes|boolean`. `parishDetails(): array{locality_id: int, kind: ParishKind, religion: ?Religion, dedication: ?string, clears_name: bool}` (pusty wyróżnik → null). `StoreParish::handle(Tree $tree, array $details): Parish`, `UpdateParish::handle(Parish $parish, array $details): Parish` (update zeruje `name` tylko przy `clears_name`); `UniqueConstraintViolationException` → `ValidationException` na `dedication` z tym samym komunikatem. Atrybuty: rodzaj, miejscowość siedziby, wyznanie, wezwanie / wyróżnik. Toasty: „Dodano parafię / urząd”, „Zapisano parafię / urząd”, „Usunięto parafię / urząd”; pozostałe komunikaty „parafi*” z `research.md` §4 dostosowane do „parafia / urząd” tam, gdzie mowa o rekordzie.

#### 5. Zasoby, opcje i sortowanie

**File**: `app/Http/Resources/{ParishResource,SourceResource}.php`, `app/Http/Controllers/{PlaceController,SourceController,ParishMergeController}.php`

**Intent**: Klient dostaje nowe pola i etykietę; wszystkie listy parafii sortują się po etykiecie i ładują siedzibę.

**Contract**: `ParishResource` + `kind`, `kind_label`, `dedication`; `name` zostaje (dawna nazwa, może być null). `SourceResource`: `parish_name` usunięte (konsument przechodzi na `parish_label` w fazie 3); `parish_label` z załadowaną `parish.locality`. `orderBy('name')` w `PlaceController.php:32`, `SourceController.php:108,138` → `with('locality')` + sortowanie w PHP po `mb_strtolower(label())` (jak `SourceController.php:147,164`). `placeOptions` ładuje `locality`; `ParishMergeController` ładuje `locality` celu przed toastem. Nowy prop `parishKinds => ParishKind::options()` obok `religions` w `PlaceController` i `SourceController::panelOptions`.

#### 6. Testy Pest

**File**: `tests/Feature/ParishSeatMigrationTest.php` (nowy), `tests/Feature/{ParishesTest,PlacesTest,SourcesTest,DeleteImpactTest,LocalitiesTest,OwnershipBoundariesTest,AppShellTest,SourceArchiveMigrationTest}.php`, `tests/BackupTestCase.php`

**Intent**: Udowodnić migrację bez utraty danych oraz nowy klucz, etykietę i wymóg siedziby; dostosować testy oparte na `name`.

**Contract**: test migracji wg `tests/Feature/SourceMigrationTest.php`: `down()`, wiersze w starym kształcie w dwóch drzewach (bez siedziby; z siedzibą bez kolizji; para kolidująca — ta sama siedziba i wyznanie; ta sama siedziba, inne wyznanie; taka sama para w innym drzewie), `up()`; asercje: `kind = parish` wszędzie, `dedication = name` tylko w parze kolidującej, `name`, `religion`, `locality_id` i `sources.parish_id` nietknięte, runda `down()`/`up()`. `ParishesTest`: zapis bez siedziby → błąd `locality_id`; ta sama siedziba + rodzaj + wyznanie + wyróżnik → błąd (wielkość liter i spacje ignorowane); parafia i USC w tej samej siedzibie → dwa rekordy; `kind = other` bez wyróżnika → błąd; `clear_name` czyści dawną nazwę, a inne pole `name` w żądaniu jest ignorowane; etykiety: „Płock św. Bartłomieja (rzym.-kat.)”, „Gostynin USC”, „Andrzejewo okręg bóżniczy”, stary wiersz bez siedziby „Parafia w Lubomlu (rzym.-kat.)”; inline store zwraca `label`; 404 dla cudzej siedziby / parafii. Istniejące asercje etykiet („Katedralna (rzymskokatolickie)”, „Św. Jana (rzymskokatolickie)”) przepisane na nowy format; testy „parafia bez siedziby” (`SourcesTest.php:991-1002,1544-1560`) używają stanu `legacy()`.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate`
- Test migracji parafii przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/ParishSeatMigrationTest.php`
- Testy parafii, miejsc, źródeł i miejscowości przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ParishesTest.php tests/Feature/PlacesTest.php tests/Feature/SourcesTest.php tests/Feature/LocalitiesTest.php tests/Feature/DeleteImpactTest.php`
- Testy granic własności, powłoki i migracji archiwum przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php tests/Feature/AppShellTest.php tests/Feature/SourceArchiveMigrationTest.php`
- Testy kopii zapasowej przechodzą: `vendor/bin/sail artisan test --compact tests/Backup`
- Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`

#### Manual Verification:

- Na lokalnej bazie po migracji „Parafia w Lubomlu” ma `kind = parish`, brak siedziby i nietkniętą nazwę, a parafia demo Tarnów zachowuje siedzibę i źródła

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Scalanie pod nowym kluczem

### Overview

Scalanie parafii i miejscowości nie może złamać nowego indeksu ani zgubić źródeł. `MergeParish` dostaje `moveInto()`, którego używa też `MergeLocality`.

### Changes Required:

#### 1. `MergeParish`

**File**: `app/Actions/Parishes/MergeParish.php`

**Intent**: Wydzielić rdzeń scalania bez własnej transakcji i przepisać uzupełnianie celu pod nowy klucz.

**Contract**: `handle()` bez zmian w kontrakcie (blokady, ten sam komunikat błędu) i woła `moveInto()`. `public function moveInto(Parish $duplicate, Parish $target): void` (wywołujący trzyma transakcję i blokady): przenosi `sources.parish_id`, usuwa duplikat, potem uzupełnia puste pola celu z duplikatu — pola klucza (`locality_id`, `religion`, `dedication`) razem i tylko wtedy, gdy wynikowy klucz jest wolny (sprawdzenie zastępuje `nameAndReligionTaken()`, savepoint jak w `fillTarget()` jako ochrona przed współbieżnością); dawna nazwa (`name`) niezależnie, gdy cel jej nie ma. Rodzaj celu się nie zmienia.

#### 2. `MergeLocality`

**File**: `app/Actions/Localities/MergeLocality.php`

**Intent**: Przed przeniesieniem parafii scalić te, które mają odpowiednik w miejscowości docelowej, jak cmentarze.

**Contract**: `mergeCollidingParishes(Locality $duplicate, Locality $target): void` wywołane przed `$lockedDuplicate->parishes()->update(...)`; klucz porównania to krotka `(kind, coalesce(religion,''), lower(coalesce(dedication,'')))` — trzy osobne kolumny z `selectRaw`, w PHP złożone przez `json_encode([...])`, nie sklejane bez separatora („parish”+„other”+„” = „parish”+„”+„other”) (wzór `mergeCollidingCemeteries()` `:68-82`, z `lockForUpdate`); odpowiednik → `app(MergeParish::class)->moveInto($parish, $counterpart)`. Docblock `handle()` wspomina parafie.

#### 3. Testy

**File**: `tests/Feature/ParishesTest.php`, `tests/Feature/LocalitiesTest.php`

**Intent**: Pokryć nowe reguły uzupełniania i scalanie kolidujących parafii przy scalaniu miejscowości.

**Contract**: scalanie parafii: źródła przechodzą na cel; cel bez wyznania bierze wyznanie duplikatu, gdy klucz wolny, i nie bierze, gdy zajęty; stary cel bez siedziby bierze siedzibę duplikatu; cel bez dawnej nazwy bierze ją z duplikatu; 404 dla cudzej parafii. Scalanie miejscowości: dwie parafie o tym samym kluczu w obu miejscowościach → jedna parafia z obydwoma źródłami; parafia i USC w obu → nie są scalane między rodzajami; inne wyróżniki → obie przeniesione.

### Success Criteria:

#### Automated Verification:

- Testy parafii i miejscowości przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ParishesTest.php tests/Feature/LocalitiesTest.php`
- Testy wpływu usunięcia i granic własności przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/DeleteImpactTest.php tests/Feature/OwnershipBoundariesTest.php`
- Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej przejdź do fazy 3 (brak kroków ręcznych).

---

## Phase 3: UI „Parafia / urząd”

### Overview

Panel, okno dodawania, lista i źródło używają nowych pól i etykiety; nazwy w UI zmieniają się na „Parafie i urzędy” / „Parafia / urząd”.

### Changes Required:

#### 1. Typy i helpery

**File**: `resources/js/types/{parish,source}.ts`, `resources/js/lib/{parishes-index,source-place-year-filter,places-usage}.ts`

**Intent**: Typy odpowiadają zasobowi; wyszukiwanie i sortowanie idą za etykietą, a stare rekordy da się znaleźć po dawnej nazwie.

**Contract**: `Parish` + `kind`, `kind_label`, `dedication`, `name: string | null`; `ParishKindOption` jak `ReligionOption`; `Source.parish_name` usunięte. `filterParishes` po `label`, `name` (dawna) i `locality_name`; `sortParishes` po `label` (ta sama kolacja co dziś). `source-place-year-filter` dopasowuje po `parish_label`. Napisy w `places-usage.ts` („Pokaż w parafiach…”) → „Pokaż w parafiach i urzędach…”.

#### 2. Panel, okno dodawania, tabela, dialogi

**File**: `resources/js/components/parishes/{parish-panel,new-parish-dialog,parishes-table,merge-parish-dialog,delete-parish-dialog}.tsx`

**Intent**: Formularz w nowym kształcie i dawna nazwa tylko do odczytu.

**Contract**: pola w kolejności: Rodzaj (Select z `parishKinds`, domyślnie „parafia”, hidden `kind`), Miejscowość siedziby (`LocalityPicker`, bez `required` po stronie klienta — błąd z serwera jak `new-cemetery-dialog.tsx:160-171`), Wezwanie / wyróżnik (`Input name="dedication"`, podpowiedź „np. św. Bartłomieja, Fara, Cyrkuł I”), Wyznanie (bez zmian). Gdy `parish.name !== null`: blok „Dawna nazwa” z tekstem i przyciskiem „Usuń dawną nazwę” (ustawia hidden `clear_name=1`, formularz staje się zmieniony; po kliknięciu blok pokazuje „zostanie usunięta po zapisaniu” z możliwością cofnięcia) i zdanie „Przepisz siedzibę i wezwanie z dawnej nazwy, potem ją usuń.”. Tytuł panelu `parish?.label ?? 'Nowa parafia / urząd'`, przyciski „Zapisz”, „Usuń”. `NewParishDialog`: te same pola bez dawnej nazwy, tytuł „Nowa parafia / urząd”, prop `initialName` → `initialSeatName`: miejscowość z `localities` o tej nazwie (bez wielkości liter, po `trim`) jest wybraną siedzibą — jej id idzie do `value` pickera i do początkowego `locality_id`; bez trafienia siedziba jest pusta. `SuggestField` i `LocalityPicker` bez zmian. Tabela: tekst linku = `label`, kolumna Rodzaj. Dialogi scalania i usuwania: napisy „parafia / urząd”.

#### 3. Źródło, `/places`, filtry

**File**: `resources/js/components/sources/{source-description-fields,sources-table,sources-filters}.tsx`, `resources/js/lib/source-description.ts`, `resources/js/pages/places/index.tsx`, `resources/js/components/places/places-empty-state.tsx`

**Intent**: Nazwy z decyzji 4 i przekazanie wpisanego tekstu do okna.

**Contract**: pole „Parafia / urząd”, `createLabel` „Dodaj parafię / urząd „X””, wpisany tekst → `initialSeatName` okna; nagłówek kolumny „Parafia / urząd / cmentarz”; grupa filtra i zakładka „Parafie i urzędy”; pusty stan „Dodaj pierwszą parafię lub urząd: siedzibę, rodzaj, wyznanie i wezwanie.”; `parishKinds` przekazane do panelu i okna. Slug `?kind=parafie` i klucz `parafia-{id}` bez zmian.

#### 4. Testy Vitest i kontrola tokenów

**File**: `resources/js/lib/{parishes-index,source-description,source-place-year-filter,places-index,places-usage,sources-index,side-panel-url,suggestions}.test.ts`, `resources/js/lib/view-tokens.test.ts`

**Intent**: Fixtury w nowym kształcie; nowe przypadki dla wyszukiwania po dawnej nazwie i dopasowania po etykiecie.

**Contract**: `filterParishes` znajduje stary rekord po dawnej nazwie i nowy po wyróżniku; `sortParishes` po etykiecie; filtr źródła do faktu trafia po siedzibie w `parish_label`. `view-tokens.test.ts` dalej obejmuje `parishes-table.tsx` i `parish-panel.tsx` (bez nowych plików komponentów).

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą: `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Testy Pest stron i źródeł dalej przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PlacesTest.php tests/Feature/SourcesTest.php`

#### Manual Verification:

- Na `/places?kind=parafie` dodanie „Płock”, wezwanie „św. Bartłomieja”, wyznanie rzymskokatolickie daje wiersz „Płock św. Bartłomieja (rzym.-kat.)”; drugi taki sam zapis pokazuje błąd przy wyróżniku
- „Parafia w Lubomlu” pokazuje dawną nazwę; po wybraniu siedziby Luboml i „Usuń dawną nazwę” zapis daje etykietę „Luboml (rzym.-kat.)”
- W źródle wpisanie „Płock” w „Parafia / urząd” i „Dodaj parafię / urząd „Płock”” otwiera okno z wybraną siedzibą Płock; nieznana nazwa otwiera okno z pustą siedzibą
- Rodzaj „inny” bez wyróżnika pokazuje błąd przy wyróżniku

**Implementation Note**: Po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Phase 4: E2E, dokumentacja i bramka wizualna

### Overview

Specyfikacje Playwright idą za zamierzoną zmianą zachowania i nazw; PRD i reguły projektu opisują nowy model.

### Changes Required:

#### 1. Pomocnik i specyfikacje E2E

**File**: `tests/e2e/support/api.ts`, `tests/e2e/{source-panel-parish,places-page,places-merge-and-delete,sources-grid-with-filters,source-found-by-place-and-year}.spec.ts`

**Intent**: Zmienione pola i napisy to zamierzona zmiana zachowania; specyfikacje opisują nowy przepływ, a sprzątanie znajduje rekordy bez `name`.

**Contract**: `createParish({ localityId, kind?, dedication?, religion? })` (siedziba wymagana); sprzątanie po `locality_name` z sufiksem (jak cmentarze `:485-494`). `source-panel-parish.spec.ts`: combobox „Parafia / urząd”, opcja „Dodaj parafię / urząd „…””, okno „Nowa parafia / urząd” z wybraną siedzibą, wartość pola = etykieta. `places-page.spec.ts`: tytuł panelu i link = etykieta, zakładka „Parafie i urzędy”, „Pokaż w parafiach i urzędach (1)”. Lokatory wg `CLAUDE.md` (role/label/text, bez CSS, bez `waitForTimeout`), unikalne sufiksy.

#### 2. PRD i reguły projektu

**File**: `context/foundation/prd.md`, `CLAUDE.md`

**Intent**: Dokumenty opisują prowadzącego akta, nowy klucz i scalanie kolidujących parafii.

**Contract**: FR-008 (`prd.md:118`) → spis parafii i urzędów: siedziba (wymagana), rodzaj, wyznanie, wezwanie / wyróżnik; FR-001 (`:95`) → „parafia / urząd”. `CLAUDE.md:87` — klucz parafii / urzędu: siedziba + rodzaj + wyznanie + wezwanie; `:88` — scalanie miejscowości najpierw scala kolidujące cmentarze i parafie; `:90` — zakładka „Parafie i urzędy”.

#### 3. Bramka wizualna

**File**: `context/changes/parish-or-office/screenshots/`

**Intent**: Zrzuty zakładki, panelu z dawną nazwą i okna dodawania w źródle.

**Contract**: co najmniej trzy zrzuty (jasny motyw): lista „Parafie i urzędy”, panel starego rekordu z blokiem „Dawna nazwa”, okno „Nowa parafia / urząd” z wybraną siedzibą.

### Success Criteria:

#### Automated Verification:

- Specyfikacje E2E parafii i miejsc przechodzą: `npx playwright test tests/e2e/source-panel-parish.spec.ts tests/e2e/places-page.spec.ts tests/e2e/places-merge-and-delete.spec.ts`
- Specyfikacje E2E źródeł przechodzą: `npx playwright test tests/e2e/sources-grid-with-filters.spec.ts tests/e2e/source-found-by-place-and-year.spec.ts`
- Zrzuty istnieją: `ls context/changes/parish-or-office/screenshots/*.png`

#### Manual Verification:

- Zrzuty pokazują etykiety z pól, blok „Dawna nazwa” i okno z wybraną siedzibą, bez palety Tailwind i z tokenami ról
- Autor sprawdza `CLAUDE.md` i PRD pod kątem nowego modelu

**Implementation Note**: Po tej fazie poproś autora o pełny zestaw testów: `vendor/bin/sail artisan test --compact`.

---

## Testing Strategy

### Unit Tests:

- `ParishKind`/`Religion::shortLabel()` przez asercje etykiet w `ParishesTest` (bez osobnych testów enumów).
- Vitest: `filterParishes` po dawnej nazwie i wyróżniku, `sortParishes` po etykiecie, `source-place-year-filter` po `parish_label`.

### Integration Tests:

- Pest: migracja z grupą kolizji i dwoma drzewami; zapis bez siedziby; klucz z rodzajem; „inny” bez wyróżnika; usunięcie dawnej nazwy; scalanie parafii z regułami uzupełniania; scalanie miejscowości z kolidującymi parafiami; 404 dla cudzych rekordów.
- Playwright: dodanie parafii w locie ze źródła z podstawioną siedzibą; strona `/places`; scalanie i usuwanie.

### Manual Testing Steps:

1. Migracja na kopii lokalnej bazy, przegląd „Parafia w Lubomlu” i parafii demo.
2. Przeniesienie dawnej nazwy na siedzibę + wyróżnik i usunięcie jej w panelu.
3. Dodanie USC w tej samej siedzibie co parafia — dwa rekordy, etykiety „Płock (rzym.-kat.)” i „Płock USC”.
4. Scalenie dwóch miejscowości, z których każda ma „Płock (rzym.-kat.)” — jedna parafia ze źródłami obu.

## Performance Considerations

Sortowanie w PHP po etykiecie wymaga `with('locality')` na każdej liście parafii (inaczej N+1). Liczba parafii w drzewie to dziesiątki, więc sortowanie w pamięci nie jest problemem; precedens `SourceController.php:147,164`.

## Migration Notes

- Produkcja robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`) — bez zmian.
- Danych produkcyjnych nie sprawdzano. Jedyna automatyczna zmiana treści to kopia dawnej nazwy do wyróżnika w grupach kolizji; etykiety takich rekordów będą brzydkie („Płock Parafia św. Bartłomieja w Płocku (rzym.-kat.)”) do ręcznej poprawki.
- Po wdrożeniu autor przenosi dawne nazwy ręcznie; kolumna `name` zostaje do osobnej zmiany.

## References

- Research: `context/changes/parish-or-office/research.md`
- Decyzje źródłowe: `context/changes/places-review/research.md:166-167`
- Wzorzec: `context/archive/2026-10-20-cemeteries/plan.md`, `app/Concerns/CemeteryValidationRules.php:22-113`, `app/Actions/Cemeteries/MergeCemetery.php:50-66`, `app/Actions/Localities/MergeLocality.php:68-82`
- Test migracji: `tests/Feature/SourceMigrationTest.php`, `tests/Feature/GravestoneCemeteryMigrationTest.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Schemat, migracja i ścieżka zapisu

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony — 124eeb7
- [x] 1.2 Test migracji parafii przechodzi — 124eeb7
- [x] 1.3 Testy parafii, miejsc, źródeł i miejscowości przechodzą — 124eeb7
- [x] 1.4 Testy granic własności, powłoki i migracji archiwum przechodzą — 124eeb7
- [x] 1.5 Testy kopii zapasowej przechodzą — 124eeb7
- [x] 1.6 Formatowanie PHP i Larastan — 124eeb7

#### Manual

- [x] 1.7 Lokalna baza po migracji zachowuje Luboml i parafię demo Tarnów — 124eeb7

### Phase 2: Scalanie pod nowym kluczem

#### Automated

- [x] 2.1 Testy parafii i miejscowości przechodzą
- [x] 2.2 Testy wpływu usunięcia i granic własności przechodzą
- [x] 2.3 Formatowanie PHP i Larastan

### Phase 3: UI „Parafia / urząd”

#### Automated

- [ ] 3.1 Testy JS przechodzą
- [ ] 3.2 Format i lint przechodzą
- [ ] 3.3 Typy przechodzą
- [ ] 3.4 Build przechodzi
- [ ] 3.5 Testy Pest stron i źródeł dalej przechodzą

#### Manual

- [ ] 3.6 Dodanie parafii z wezwaniem i błąd duplikatu na /places
- [ ] 3.7 Przeniesienie i usunięcie dawnej nazwy Lubomla
- [ ] 3.8 Dodanie w locie ze źródła podstawia siedzibę
- [ ] 3.9 Rodzaj „inny” bez wyróżnika pokazuje błąd

### Phase 4: E2E, dokumentacja i bramka wizualna

#### Automated

- [ ] 4.1 Specyfikacje E2E parafii i miejsc przechodzą
- [ ] 4.2 Specyfikacje E2E źródeł przechodzą
- [ ] 4.3 Zrzuty istnieją

#### Manual

- [ ] 4.4 Zrzuty pokazują etykiety, dawną nazwę i okno z siedzibą
- [ ] 4.5 Autor sprawdza CLAUDE.md i PRD
