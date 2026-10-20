# Szczegóły miejscowości — plan implementacji

## Overview

Miejscowość w spisie dostaje cztery nowe informacje: **rodzaj** (miasto, wieś, kolonia, osada, przysiółek, folwark / majątek, inne), **współrzędne** (wklejane jako „50.0614, 19.9366”, z odnośnikiem „Pokaż na mapie” do OpenStreetMap), **przynależność historyczną** (jedno pole tekstowe, np. „gm. Lipnica, pow. bocheński, Galicja”) oraz **„nie istnieje dziś”** (przełącznik + opcjonalny opis zaniku, np. „zajęta pod zalew 1975”). Nieistniejąca miejscowość jest oznaczona na liście `/places`, w podpowiedziach wyboru miejscowości (fakty, zamieszkanie, cmentarz, siedziba parafii, scalanie) i na górze swojego panelu. To pierwsza z trzech zmian wydzielonych z `context/changes/places-review/research.md` (decyzje autora 5 i 6).

## Current State Analysis

- `localities(id, tree_id, name, description)` z unikalnością `(tree_id, lower(name))` (`database/migrations/2026_10_05_081630_create_localities_table.php:15-24`). Ujednoznacznienie tych samych nazw odbywa się w samej nazwie („Lipnica (pow. bocheński)”) — tak zostaje.
- Zapis: `LocalityValidationRules::localityRules()` (`app/Concerns/LocalityValidationRules.php:16-38`) → `StoreLocality::handle(Tree, name, description)` / `UpdateLocality::handle(Locality, name, description)` (`app/Actions/Localities/StoreLocality.php:17`, `UpdateLocality.php:16`) → `LocalityController::store/update` (`app/Http/Controllers/LocalityController.php:32-59`). `InlineLocalityController` dodaje miejscowość w locie z samą nazwą (`app/Http/Controllers/InlineLocalityController.php:30`) i zwraca `{id, name}` (`:51`).
- Odczyt: `LocalityResource` zwraca `id, name, description` + `usage` (`app/Http/Resources/LocalityResource.php:21-45`); `PlaceController` ładuje listę i panel (`app/Http/Controllers/PlaceController.php:25-54`).
- Podpowiedzi dostają `LocalityOption {id, name}` (`resources/js/types/parish.ts:19-22`) z `HandleInertiaRequests.php:70-73` (wspólny prop `localityOptions`), `SourceController.php:107-108` (sugestie panelu źródła), z listy `LocalityResource` strony `/places` (wybór siedziby w panelu parafii i dialog scalania, `resources/js/pages/places/index.tsx:412,423`) i z odpowiedzi inline. `SourceController::placeOptions()` (`:121-135`) to filtr listy `/sources`, nie podpowiedź — bez zmian. `SuggestField` renderuje w `<li>` wyłącznie `labelOf(option)` (`resources/js/components/suggest-field.tsx:303-307`); z niego korzystają `LocalityPicker` (`resources/js/components/localities/locality-picker.tsx:101-120`) i `MergeLocalityDialog` (`resources/js/components/localities/merge-locality-dialog.tsx:85-91`).
- Panel: pola Nazwa i Opis (`resources/js/components/localities/locality-panel.tsx:134-160`); `SidePanel.title` to `string`, wspólny dla czterech paneli (`resources/js/components/side-panel.tsx:100`).
- Lista: `LocalitiesTable` z kolumnami Nazwa (przyklejona) / Opis / Użycia (`resources/js/components/localities/localities-table.tsx:39-95`); szukanie tylko po nazwie (`resources/js/lib/localities-index.ts:6-19`).
- Scalanie przenosi odwołania i łączy opisy (`app/Actions/Localities/MergeLocality.php:49-55`).
- PRD Non-Goal „Brak nazw historycznych i zmian granic miejscowości — takie informacje trafiają do opisu miejscowości” (`context/foundation/prd.md:238`) jest sprzeczny z tą zmianą.
- Roadmapa (M-2 zamknięty) nie ma pozycji `locality-details`.

## Desired End State

- Panel miejscowości ma pola: Nazwa, Rodzaj (lista, „brak” = nie podano), Współrzędne (jedno pole, „szer., dł.”), Przynależność historyczna, Opis, przełącznik „Nie istnieje dziś” i — gdy włączony — „Co się stało” (opis zaniku). Przy zapisanych współrzędnych widać odnośnik „Pokaż na mapie” (OpenStreetMap, nowa karta).
- Zapisana nieistniejąca miejscowość pokazuje na górze panelu plakietkę „nie istnieje” z opisem zaniku.
- Lista `/places` ma kolumnę „Rodzaj”, a przy nazwie nieistniejącej miejscowości plakietkę „nie istnieje”.
- Każda podpowiedź miejscowości (fakty, pochowanie, zamieszkanie, cmentarz nagrobka, siedziba parafii, scalanie) pokazuje przy nieistniejącej miejscowości dopisek „nie istnieje”, który nie wpływa na dopasowanie wpisywanego tekstu.
- Scalanie: wartości miejscowości docelowej wygrywają, jej puste pola uzupełniają się z duplikatu; różne przynależności historyczne łączą się jak opisy; „nie istnieje” jest włączone, jeśli było włączone w którejkolwiek.
- Weryfikacja: Pest (`LocalitiesTest`, `PlacesTest`, zaktualizowane asercje kształtu opcji), Vitest (`localities-index.test.ts`, `view-tokens.test.ts`), zrzuty ekranu w `context/changes/locality-details/screenshots/`.

### Key Discoveries:

- Wzorzec checkboxa z polami zależnymi: `is_deceased` — `Checkbox` + ukryte `<input name="is_deceased" value="1">` tylko gdy zaznaczone (`resources/js/components/persons/person-section-fields.tsx:200-208`), po stronie serwera `'sometimes', 'boolean'` i pola zależne z `prohibited_unless:is_deceased,1,true` (`app/Http/Requests/PersonStoreRequest.php:25-44`).
- Wzorzec enuma z etykietą i listą opcji: `App\Enums\Religion::label()` / `options()` (`app/Enums/Religion.php:14-36`); zasób wystawia `religion` i `religion_label` (`app/Http/Resources/ParishResource.php:26-27`); `Select` z wartością „brak” przez stałą `NONE` (`resources/js/components/parishes/parish-panel.tsx:38,220-232`).
- Testy kształtu, które ta zmiana kontraktu (nie zachowania) świadomie aktualizuje: `tests/Feature/PersonsTest.php:1696` (`localityOptions` przez `toBe([...])`), `tests/Feature/SourcesTest.php:1474` (`where('sourcePanel.localities', [[id, name]])` — dokładne porównanie), `tests/Feature/LocalitiesTest.php:300,306,325` (`assertExactJson([id, name])` odpowiedzi inline), `tests/Feature/PlacesTest.php:63` (`hasAll([...])` w domkniętym scope, bez `etc()`), oraz bezpośrednie wywołania starej sygnatury `StoreLocality/UpdateLocality::handle($x, 'nazwa', null)` w `tests/Feature/LocalitiesTest.php:117-118`. Mock `handle()` w `LocalitiesTest.php:314` nie zależy od sygnatury.
- Vitest testuje wyłącznie funkcje z `resources/js/lib/` (brak `*.test.tsx`), więc logika dopisku i formatowania współrzędnych trafia do `lib/localities-index.ts`.
- `view-tokens.test.ts` obejmuje już `localities-table.tsx` i `locality-panel.tsx` (`resources/js/lib/view-tokens.test.ts:37,56`); `suggest-field.tsx` i `locality-picker.tsx` trzeba dopisać, jeśli wnoszą nowe klasy.

## What We're NOT Doing

- Nazwy alternatywne / historyczne miejscowości (decyzja autora 5) — dawne nazwy dalej w opisie.
- Mapa w panelu ani wybór punktu na mapie (brak nowej zależności); tylko odnośnik do OpenStreetMap.
- Format współrzędnych w stopniach/minutach (DMS) i przecinek dziesiętny — tylko zapis dziesiętny z kropką.
- Strukturalna przynależność (osobne gmina / powiat / gubernia / zabór) ani datowana hierarchia jednostek.
- Szukanie na `/places` po przynależności historycznej lub rodzaju; filtr „tylko nieistniejące”.
- Znacznik „nie istnieje” przy faktach w panelu osoby, na osi czasu, w panelu źródła i na kartach drzewa.
- Dodawanie szczegółów przy „Dodaj miejscowość” w podpowiedzi — dopisanie w locie zostaje samą nazwą.
- Zmiana klucza unikalności nazwy, cmentarze i parafia / urząd (osobne zmiany z `places-review`).
- Testy E2E (decyzja planu: żadne ryzyko tej zmiany nie wymaga przeglądarki; `context/foundation/test-plan.md` „koszt × sygnał”).

## Implementation Approach

Najpierw backend z pełnym kontraktem danych i testami Pest (kolumny, walidacja z parsowaniem współrzędnych, akcje, zasób, flaga w opcjach podpowiedzi, scalanie), potem frontend, który tylko formatuje i wyświetla (pola panelu, kolumna i plakietka na liście, dopisek w `SuggestField`). Parsowanie współrzędnych żyje po stronie serwera; front wysyła tekst pola i formatuje zapisane liczby z powrotem do „szer., dł.”. Wszystkie nowe kolumny są nullable albo z wartością domyślną — bez przenoszenia danych, więc reguły „Migrations that move existing data” nie mają zastosowania poza `down()`.

## Critical Implementation Details

- **Współrzędne jako para.** Pole `coordinates` jest albo puste (obie kolumny `null`), albo poprawne (obie ustawione) — nigdy jedna z dwóch. Ten sam niezmiennik obowiązuje przy scalaniu: współrzędne duplikatu przechodzą tylko jako para, gdy cel nie ma żadnych.
- **Opis zaniku bez przełącznika.** Przy wyłączonym „Nie istnieje dziś” serwer odrzuca niepusty `defunct_note` (`prohibited_unless`, jak przy `is_deceased`), a akcja zapisu zeruje go, gdy flaga jest wyłączona — formularz nie wysyła ukrytego pola, ale stary opis w bazie nie może przetrwać wyłączenia.

## Phase 1: Model i zapis (backend)

### Overview

Kolumny, enum rodzaju, walidacja, zapis, zasób, flaga `is_defunct` w opcjach podpowiedzi, reguła scalania, poprawka PRD i testy Pest. Po tej fazie wszystko działa przez żądania HTTP, UI jeszcze nie pokazuje nowych pól.

### Changes Required:

#### 1. Migracja i model

**File**: `database/migrations/<timestamp>_add_details_to_localities_table.php` (przez `vendor/bin/sail artisan make:migration --no-interaction`), `app/Models/Locality.php`, `database/factories/LocalityFactory.php`

**Intent**: Dodać szczegóły jako kolumny miejscowości; migracja jest odwracalna przez usunięcie kolumn. Fabryka dostaje stan dla nieistniejącej miejscowości, żeby testy nie składały jej ręcznie.

**Contract**: kolumny `kind` string nullable, `latitude` decimal(8,6) nullable, `longitude` decimal(9,6) nullable, `historical_affiliation` text nullable, `is_defunct` boolean default false, `defunct_note` string(255) nullable; `down()` usuwa te kolumny. Model: `#[Fillable]` rozszerzone o nowe pola, rzutowania `kind` → `LocalityKind`, `latitude`/`longitude` → `float`, `is_defunct` → `boolean`; PHPDoc `@property` dla każdego pola. Fabryka: domyślnie nowe pola puste / `false`, stan `defunct(?string $note = null)`.

#### 2. Enum rodzaju

**File**: `app/Enums/LocalityKind.php`

**Intent**: Zamknięta lista rodzajów z polską etykietą, wzorem `Religion`.

**Contract**: przypadki `City='city'` (miasto), `Village='village'` (wieś), `Colony='colony'` (kolonia), `Settlement='settlement'` (osada), `Hamlet='hamlet'` (przysiółek), `Manor='manor'` (folwark / majątek), `Other='other'` (inne); `label(): string`, `static options(): array<int, array{value: string, label: string}>`.

#### 3. Parsowanie współrzędnych

**File**: `app/Support/Coordinates.php` (przez `vendor/bin/sail artisan make:class --no-interaction`)

**Intent**: Jedno miejsce, które zamienia wklejony tekst na parę liczb i sprawdza zakres; używane przez walidację i przez akcję zapisu.

**Contract**: `static parse(string $text): ?array{latitude: float, longitude: float}` — przyjmuje dwie liczby dziesiętne z kropką, opcjonalnym znakiem, rozdzielone przecinkiem, średnikiem i/lub białymi znakami („50.0614, 19.9366”, „50.0614 19.9366”, „-33.9, 151.2”); zwraca `null` dla innego kształtu albo poza zakresem (szerokość −90…90, długość −180…180). Wynik zaokrąglony do 6 miejsc (precyzja kolumn).

#### 4. Walidacja

**File**: `app/Concerns/LocalityValidationRules.php`

**Intent**: Reguły dla nowych pól we wspólnym traicie, więc obejmują zapis i edycję; dopisanie inline (`LocalityInlineStoreRequest`) zostaje przy samej nazwie.

**Contract**: `kind` → `nullable`, `Rule::enum(LocalityKind::class)`; `coordinates` → `nullable`, `string`, `max:100`, domknięcie z `Coordinates::parse()` i komunikatem „Wpisz współrzędne jako „50.0614, 19.9366” (szerokość, długość).”; `historical_affiliation` → `nullable`, `string`, `max:1000`; `is_defunct` → `sometimes`, `boolean`; `defunct_note` → `nullable`, `string`, `max:255`, `prohibited_unless:is_defunct,1,true`. `localityAttributes()` rozszerzone o polskie nazwy (rodzaj, współrzędne, przynależność historyczna, nie istnieje dziś, opis zaniku).

#### 5. Akcje zapisu i kontroler

**File**: `app/Actions/Localities/StoreLocality.php`, `app/Actions/Localities/UpdateLocality.php`, `app/Http/Controllers/LocalityController.php`, `app/Http/Controllers/InlineLocalityController.php`

**Intent**: Akcje zapisują pełny zestaw pól (pusta wartość → `null`, wyłączony przełącznik → `defunct_note` `null`); kontroler przekazuje zwalidowane dane zamiast dwóch argumentów. Dopisanie inline dalej podaje tylko nazwę, a jego odpowiedź JSON dostaje `is_defunct`, żeby nowa opcja miała ten sam kształt co lista.

**Contract**: `StoreLocality::handle(Tree $tree, array $details): Locality` i `UpdateLocality::handle(Locality $locality, array $details): Locality`, gdzie `$details` to `array{name: string, description: ?string, kind: ?LocalityKind, latitude: ?float, longitude: ?float, historical_affiliation: ?string, is_defunct: bool, defunct_note: ?string}`; przy aktualizacji brakujące pola są czyszczone (jak dziś opis). Mapowanie żądania → `$details` (z `Coordinates::parse()`) w jednym miejscu — metoda Form Requestu wspólna dla store/update (np. w traicie). Odpowiedź inline: `{id, name, is_defunct}`. Obsługa `UniqueConstraintViolationException` bez zmian.

#### 6. Zasób i opcje podpowiedzi

**File**: `app/Http/Resources/LocalityResource.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/SourceController.php`

**Intent**: Lista i panel dostają wszystkie szczegóły; każda lista opcji miejscowości niesie flagę, z której podpowiedź robi dopisek.

**Contract**: `LocalityResource` dodaje `kind` (wartość lub `null`), `kind_label` (`label()` lub `null`), `latitude`, `longitude` (float lub `null`), `historical_affiliation`, `is_defunct`, `defunct_note`; PHPDoc `@return` rozszerzony. `PlaceController::index` przekazuje `localityKinds => LocalityKind::options()`. Opcje w `HandleInertiaRequests.php:70-73` i `SourceController.php:107-108`: `{id, name, is_defunct}` (zapytanie wybiera też `is_defunct`).

#### 7. Scalanie

**File**: `app/Actions/Localities/MergeLocality.php`

**Intent**: Szczegóły duplikatu nie giną przy scalaniu, ale nie nadpisują tego, co autor wpisał w miejscowości docelowej — rozszerzenie dzisiejszego łączenia opisów.

**Contract**: w tej samej transakcji, po przeniesieniu odwołań: `historical_affiliation` — łączona jak opis (ten sam helper co `mergedDescription()`, uogólniony na dowolny tekst: pusty/równy → wartość celu, pusta w celu → duplikatu, inaczej `cel."\n\n".duplikat`); jak przy opisie, suma może przekroczyć limit walidacji (`max:1000`) — kolumna `text` ją przyjmie, autor skraca przy następnej edycji; `kind` — z duplikatu tylko gdy cel ma `null`; współrzędne — z duplikatu jako para tylko gdy cel nie ma żadnych; `is_defunct` = cel OR duplikat; `defunct_note` = notatka celu, a gdy jej brak i wynik jest „nie istnieje” — notatka duplikatu. Docblock `handle()` zaktualizowany.

#### 8. PRD

**File**: `context/foundation/prd.md`

**Intent**: Usunąć sprzeczność z Non-Goal i odnotować rozszerzenie spisu.

**Contract**: Non-Goal `:238` przeredagowany na „Brak nazw alternatywnych i datowanej hierarchii jednostek — dawne nazwy trafiają do opisu; przynależność historyczna to jedno pole tekstowe miejscowości”; przy FR-007 dopisek „(rozszerzone 2026-10-20, `locality-details`: rodzaj, współrzędne, przynależność historyczna, „nie istnieje dziś”)”. Numeracja FR bez zmian.

#### 9. Testy Pest

**File**: `tests/Feature/LocalitiesTest.php`, `tests/Feature/PlacesTest.php`, `tests/Feature/PersonsTest.php`, `tests/Feature/SourcesTest.php` (tylko asercje kształtu opcji, jeśli porównują `{id, name}` dokładnie)

**Intent**: Pokryć nowe zachowanie i jego tryby błędów, bez testów poza nim (`testing-best-practices`).

**Contract** — nowe testy:
- zapis i edycja zapisują rodzaj, współrzędne (z „50.0614, 19.9366” → dwie liczby), przynależność, „nie istnieje” z opisem; edycja z pustymi polami czyści je do `null`;
- współrzędne: zbiór danych z poprawnymi wariantami separatora i znakiem minus oraz błędnymi (jedna liczba, przecinek dziesiętny, DMS, szerokość 91, długość −181, tekst) → błąd na `coordinates` i nic nie zapisane;
- nieznany `kind` → błąd na `kind`;
- `defunct_note` bez `is_defunct` → błąd; wyłączenie „nie istnieje” w edycji zeruje zapisany opis zaniku;
- scalanie: puste pola celu uzupełnione z duplikatu, wypełnione pola celu zostają, różne przynależności historyczne połączone jak opisy, współrzędne przechodzą tylko jako para, `is_defunct` = OR;
- `PlacesTest`: lista i panel niosą nowe pola i `kind_label`, strona niesie `localityKinds`;
- opcje: `localityOptions` (aktualizacja `PersonsTest.php:1696`), sugestie panelu źródła i odpowiedź inline niosą `is_defunct`.

**Contract** — aktualizacje istniejących asercji (zmiana kontraktu w przeglądanym diffie, nie dopasowanie do błędu): `PersonsTest.php:1696` i `SourcesTest.php:1474` dostają `'is_defunct' => false`; `LocalitiesTest.php:300,306,325` — `is_defunct` w `assertExactJson`; `PlacesTest.php:63` — nowe klucze zasobu w `hasAll`; `LocalitiesTest.php:117-118` — wywołania `handle()` z tablicą `$details`.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1` → `vendor/bin/sail artisan migrate`
- Testy miejscowości i miejsc przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php tests/Feature/PlacesTest.php`
- Testy z asercjami kształtu opcji przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php`
- Formatowanie PHP: `vendor/bin/sail bin pint --dirty --format agent`
- Larastan poziom 7 bez błędów: `vendor/bin/sail composer types:check`

#### Manual Verification:

- PRD: Non-Goal i dopisek przy FR-007 brzmią zgodnie z decyzjami (bez nazw alternatywnych, przynależność jako tekst)

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na potwierdzenie weryfikacji ręcznej przed fazą 2.

---

## Phase 2: Panel, lista i podpowiedzi (frontend)

### Overview

Pola w panelu, status „nie istnieje” nad formularzem, odnośnik do mapy, kolumna „Rodzaj” i plakietka na liście, dopisek w podpowiedziach; helpery z testami Vitest i bramka wizualna.

### Changes Required:

#### 1. Typy

**File**: `resources/js/types/locality.ts`, `resources/js/types/parish.ts`

**Intent**: Odzwierciedlić kontrakt zasobu i opcji.

**Contract**: `Locality` dostaje `kind: string | null`, `kind_label: string | null`, `latitude: number | null`, `longitude: number | null`, `historical_affiliation: string | null`, `is_defunct: boolean`, `defunct_note: string | null`; `LocalityOption` dostaje `is_defunct?: boolean` (opcjonalne, bo wybrane wartości z zasobów osoby/wydarzeń dalej mają tylko `{id, name}`). Typ opcji rodzaju — istniejący `ReligionOption`-podobny `{value, label}` (użyj wspólnego typu, jeśli jest; inaczej `LocalityKindOption`).

#### 2. Helpery i Vitest

**File**: `resources/js/lib/localities-index.ts`, `resources/js/lib/localities-index.test.ts`

**Intent**: Logika wyświetlania w czystych funkcjach, testowalna bez DOM.

**Contract**: `localityHint(option: { is_defunct?: boolean }): string | undefined` → „nie istnieje” albo `undefined`; `formatCoordinates(latitude: number | null, longitude: number | null): string` → „50.0614, 19.9366” albo `''` gdy brak; `openStreetMapUrl(latitude: number, longitude: number): string` → `https://www.openstreetmap.org/?mlat=<lat>&mlon=<lon>#map=14/<lat>/<lon>`. Testy: każda funkcja z przypadkiem pustym, ujemne współrzędne, brak zbędnych zer z liczby; istniejące fixtures `Locality` uzupełnione o nowe pola.

#### 3. Dopisek w podpowiedziach

**File**: `resources/js/components/suggest-field.tsx`, `resources/js/components/localities/locality-picker.tsx`, `resources/js/components/localities/merge-locality-dialog.tsx`

**Intent**: Wspólne pole podpowiedzi umie pokazać krótki dopisek przy opcji, nie zmieniając dopasowania; oba miejsca wyboru miejscowości go używają.

**Contract**: `CommonProps<T>` dostaje opcjonalne `hintOf?: (option: T) => string | undefined`; dopisek renderowany w `<li>` po etykiecie jako `{' '}<span className="text-muted-foreground">…</span>` — jawna spacja, bo `<li role="option">` nie ma `aria-label` i jego nazwa dostępna to cała treść; nazwa opcji nieistniejącej miejscowości brzmi więc „<nazwa> nie istnieje” (lokatory E2E z `exact: true`, np. `places-merge-and-delete.spec.ts:43`, muszą to uwzględnić dla takich miejscowości); filtrowanie i `setNativeValue` dalej tylko po `labelOf`. `LocalityPicker` i `MergeLocalityDialog` przekazują `hintOf={localityHint}`. Pozostali użytkownicy `SuggestField` bez zmian. Tekst `DialogDescription` w `MergeLocalityDialog` (`:76-77`): „…a różne opisy i przynależności historyczne zostaną połączone.”

#### 4. Panel miejscowości

**File**: `resources/js/components/localities/locality-panel.tsx`

**Intent**: Formularz z nowymi polami w kolejności Nazwa → Rodzaj → Współrzędne → Przynależność historyczna → Opis → Nie istnieje dziś (+ Co się stało), oraz status zapisanej nieistniejącej miejscowości na górze treści panelu.

**Contract**:
- Rodzaj: `Select` z opcjami `localityKinds` i pozycją „brak” (wzorzec `NONE` z `parish-panel.tsx:38`), ukryte pole `kind`.
- Współrzędne: `Input name="coordinates"` z `defaultValue={formatCoordinates(...)}`; klucz `<Form>` (`locality-panel.tsx:109`) rozszerzony do `` `${locality?.id ?? 'new'}-${formatCoordinates(latitude, longitude)}` ``, bo `update` wraca przez `back()` i bez nowego klucza pole po zapisie zostaje z surowym wklejonym tekstem zamiast znormalizowanych liczb (remount przy zmianie współrzędnych bierze wszystkie wartości domyślne z zapisanych danych); podpowiedź „Wklej z mapy, np. 50.0614, 19.9366”; obok, gdy zapisana miejscowość ma współrzędne, link „Pokaż na mapie” (`openStreetMapUrl`, `target="_blank"`, `rel="noreferrer"`, `focusRing`).
- Przynależność historyczna: `Textarea name="historical_affiliation"` (2 wiersze, `maxLength=1000`) z podpowiedzią „Np. gm. Lipnica, pow. bocheński, Galicja”.
- Nie istnieje dziś: `Checkbox` + ukryte `is_defunct=1` gdy zaznaczony (wzorzec `is_deceased`); gdy zaznaczony — `Input name="defunct_note"` „Co się stało” (`maxLength=255`, podpowiedź „Np. zajęta pod zalew 1975, włączona do …”).
- Status: gdy `locality?.is_defunct`, nad formularzem `Badge` „nie istnieje” i `defunct_note` w `text-muted-foreground`. `SidePanel` bez zmian.
- `InputError` pod każdym polem; `LocalityPanel` dostaje prop `localityKinds` przekazany z `pages/places/index.tsx`.

#### 5. Lista `/places`

**File**: `resources/js/components/localities/localities-table.tsx`, `resources/js/pages/places/index.tsx`

**Intent**: Rodzaj widoczny na liście, nieistniejąca miejscowość oznaczona przy nazwie; szukanie bez zmian.

**Contract**: nowa kolumna „Rodzaj” między Nazwą a Opisem (`kind_label` albo `EmptyCell`); w przyklejonej komórce nazwy po linku `Badge` „nie istnieje” (zawija się na wąskim ekranie razem z nazwą, zgodnie z `8830e58`). Strona przyjmuje prop `localityKinds` i przekazuje go do panelu.

#### 6. Kontrola tokenów i bramka wizualna

**File**: `resources/js/lib/view-tokens.test.ts`, `context/changes/locality-details/screenshots/`

**Intent**: Nowe klasy przechodzą kontrolę tokenów; wygląd potwierdzony zrzutami.

**Contract**: dopisać `components/suggest-field.tsx` i `components/localities/locality-picker.tsx` do `VIEW_FILES` (`view-tokens.test.ts:20-58` ich dziś nie skanuje). Zrzuty (jasny i ciemny motyw, szeroki i wąski ekran): lista z kolumną Rodzaj i plakietką, panel nieistniejącej miejscowości ze statusem i odnośnikiem mapy, podpowiedź z dopiskiem przy wyborze miejsca faktu.

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą (helpery i kontrola tokenów): `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`

#### Manual Verification:

- W panelu miejscowości zapis rodzaju, współrzędnych wklejonych z mapy, przynależności i „nie istnieje” z opisem; ponowne otwarcie pokazuje te same wartości, a „Pokaż na mapie” otwiera OpenStreetMap we właściwym punkcie
- Błędne współrzędne pokazują komunikat pod polem i nie gubią reszty formularza
- Lista `/places` pokazuje kolumnę Rodzaj i plakietkę „nie istnieje”, także na wąskim ekranie
- Podpowiedź miejsca faktu, zamieszkania, cmentarza, siedziby parafii i scalania pokazuje dopisek „nie istnieje”; wpisywanie „nie istnieje” nie dopasowuje miejscowości
- Zrzuty ekranu zapisane w `context/changes/locality-details/screenshots/`

---

## Testing Strategy

### Unit Tests:

- Vitest: `localityHint`, `formatCoordinates`, `openStreetMapUrl` w `localities-index.test.ts`; `view-tokens.test.ts` dla nowych klas.

### Integration Tests:

- Pest feature: zapis/edycja/czyszczenie pól, parsowanie i zakres współrzędnych (dataset), `prohibited_unless` opisu zaniku i jego zerowanie, nieznany rodzaj, reguła scalania, kształt zasobu i opcji podpowiedzi. Izolacja drzew (404) jest już pokryta przez istniejące testy tras miejscowości — trasy się nie zmieniają.

### Manual Testing Steps:

1. Otwórz `/places`, dodaj „Nowa miejscowość” z rodzajem „wieś”, współrzędnymi wklejonymi z OpenStreetMap i przynależnością; zapisz i otwórz ponownie.
2. Zaznacz „Nie istnieje dziś” z opisem „zajęta pod zalew 1975”; sprawdź status nad formularzem i plakietkę na liście.
3. Odznacz „Nie istnieje dziś”, zapisz, zaznacz ponownie — pole „Co się stało” jest puste.
4. W panelu osoby wybierz miejsce urodzenia — podpowiedź pokazuje dopisek przy nieistniejącej miejscowości.
5. Scal duplikat z pustą miejscowością docelową — cel przejmuje rodzaj, współrzędne i „nie istnieje”.

## Performance Considerations

Opcje podpowiedzi pobierają jedną kolumnę więcej (`is_defunct`); lista `/places` ładuje te same wiersze z kilkoma kolumnami więcej. Brak nowych zapytań.

## Migration Notes

Tylko nowe kolumny nullable / z wartością domyślną; istniejące miejscowości dostają `is_defunct = false` i puste pozostałe pola. `down()` usuwa kolumny (dane szczegółów przepadają przy wycofaniu — akceptowalne, bo przed wdrożeniem ich nie ma). Produkcja: `scripts/deploy.sh` robi kopię bazy przed `migrate`.

## References

- Related research: `context/changes/places-review/research.md` (decyzje autora 5 i 6)
- Wzorzec przełącznika z polami zależnymi: `resources/js/components/persons/person-section-fields.tsx:200-208`, `app/Http/Requests/PersonStoreRequest.php:25-44`
- Wzorzec enuma i Select „brak”: `app/Enums/Religion.php`, `resources/js/components/parishes/parish-panel.tsx:38,220-232`
- Scalanie: `app/Actions/Localities/MergeLocality.php:49-55`
- Wcześniejsza decyzja odkładająca szczegóły: `context/archive/2026-10-05-places-gazetteer/plan.md:41`, PRD `context/foundation/prd.md:238`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Model i zapis (backend)

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1` → `vendor/bin/sail artisan migrate` — a50e32e
- [x] 1.2 Testy miejscowości i miejsc przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php tests/Feature/PlacesTest.php` — a50e32e
- [x] 1.3 Testy z asercjami kształtu opcji przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php` — a50e32e
- [x] 1.4 Formatowanie PHP: `vendor/bin/sail bin pint --dirty --format agent` — a50e32e
- [x] 1.5 Larastan poziom 7 bez błędów: `vendor/bin/sail composer types:check` — a50e32e

#### Manual

- [x] 1.6 PRD: Non-Goal i dopisek przy FR-007 brzmią zgodnie z decyzjami (bez nazw alternatywnych, przynależność jako tekst) — a50e32e

### Phase 2: Panel, lista i podpowiedzi (frontend)

#### Automated

- [x] 2.1 Testy JS przechodzą (helpery i kontrola tokenów): `vendor/bin/sail npm run test:js` — d68ca89
- [x] 2.2 Format i lint przechodzą: `vendor/bin/sail npm run check` — d68ca89
- [x] 2.3 Typy przechodzą: `vendor/bin/sail npm run types:check` — d68ca89
- [x] 2.4 Build przechodzi: `vendor/bin/sail npm run build` — d68ca89

#### Manual

- [x] 2.5 W panelu miejscowości zapis rodzaju, współrzędnych wklejonych z mapy, przynależności i „nie istnieje” z opisem; ponowne otwarcie pokazuje te same wartości, a „Pokaż na mapie” otwiera OpenStreetMap we właściwym punkcie — d68ca89
- [x] 2.6 Błędne współrzędne pokazują komunikat pod polem i nie gubią reszty formularza — d68ca89
- [x] 2.7 Lista `/places` pokazuje kolumnę Rodzaj i plakietkę „nie istnieje”, także na wąskim ekranie — d68ca89
- [x] 2.8 Podpowiedź miejsca faktu, zamieszkania, cmentarza, siedziby parafii i scalania pokazuje dopisek „nie istnieje”; wpisywanie „nie istnieje” nie dopasowuje miejscowości — d68ca89
- [x] 2.9 Zrzuty ekranu zapisane w `context/changes/locality-details/screenshots/` — d6e7495
