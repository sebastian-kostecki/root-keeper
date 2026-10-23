# Daty nieostre: zakres, przed, po — plan implementacji

## Overview

Data wydarzenia (urodzenie, zgon, ślub) może dziś być tylko dokładna albo przybliżona („ok.”). Repo badawcze ma daty nieostre: „ok. 1799”, „przed 1809”, „po 1801”, „między 1805 a 1811”. S-07 dodaje do `events` rodzaj daty (`exact`, `approximate`, `before`, `after`, `range`) i rok końca zakresu przez migrację kopiującą. Autor wpisuje te rodzaje w tym samym polu „Data” i widzi je w formularzu, na osi czasu, w panelu osoby i na kartach. Istniejące daty wyglądają jak dotąd. Zmiana idzie przed S-02 (`first-import-into-empty-tree`), który importuje te rodzaje przez kontrakt ustalony tutaj.

## Current State Analysis

- **Schemat:** `events.date_year` / `date_month` / `date_day` (nullable) i `date_is_approximate` (bool, domyślnie false) — `database/migrations/2026_09_23_045723_create_events_table.php:18-21`. Brak pojęcia zakresu ani roku końca.
- **Wartość:** `App\Support\PartialDate` (`app/Support/PartialDate.php`) — `year`, `month`, `day`, `isApproximate`. `format()` daje `12.05.1850` / `05.1850` / `1850` z przedrostkiem „ok. ”; `formatYear()` daje „1850” / „ok. 1850”. Budowana w `Event::date()` (`app/Models/Event.php:102-111`) i w `Age::from()` dla „dziś” (`app/Support/Age.php:46`).
- **Zapis:** jedno wejście `{prefix: {year, month, day, is_approximate, locality_id}}` dla `birth`, `death` i `marriage`. Walidacja w `app/Concerns/PartialDateValidationRules.php:18-51` (rok wymagany przy „ok.”, `checkdate` w hooku `after`). Mapowanie w `app/Actions/Persons/MapsEventInput.php:18-27`; brak klucza = `false`, bo każdy zapis to pełna podmiana. Używają go `CreatePerson`, `UpdatePerson`, `CreateMarriage`, `UpdateMarriage`.
- **Odczyt:**
  - `EventResource` (`app/Http/Resources/EventResource.php:43-53`) daje `date` (`format()`) i `date_parts {year, month, day, is_approximate}`.
  - `MarriageResource` i `SheetPersonResource.details` (`:88-103`) mają ten sam kształt `date_parts`.
  - `PersonFormResource` (`:72-86`) daje płaskie `{year, month, day, is_approximate, …}`.
  - `TimelineEntryResource` (`:41-44`) daje `date`, `year`, `is_approximate`, `date_has_month`.
  - Lata życia: `SheetPersonResource.lifespan` (`:56-61`) z `formatYear()`; ten sam ciąg trafia na kartę przodka (`BuildAncestorChart.php:218-227`).
  - Rok ślubu: `BuildFamilySheet.php:107,115,390` z `formatYear()`.
- **Wiek:** `Age::from()` (`app/Support/Age.php:33-71`) ignoruje „ok.” (test `tests/Unit/AgeTest.php:84`, `tests/Feature/PersonTimelineTest.php:424`).
- **Sortowanie:** `PersonTimeline` (`:161-176`), `PersonOrdering` (`:113-157`) i `/persons` w przeglądarce (`resources/js/lib/persons-index.ts:153-209`) sortują po roku, miesiącu i dniu; „ok.” ignorują.
- **Frontend:**
  - Jedno pole „Data” (`resources/js/components/persons/partial-date-input.tsx`).
  - Parser `parsePartialDate` (`resources/js/lib/partial-date.ts:96-159`) rozpoznaje „ok.” z przedrostka i celowo odrzuca „przed”, „po” i zakresy (`partial-date.test.ts:65`). Wynik idzie do ukrytych pól `{prefix}.year|month|day|is_approximate`.
  - `formatPartialDate` short (lustro PHP `format()`) i long („12 marca 1948”, „około …”).
  - Zapis sekcji panelu osoby i panelu małżeństwa idzie przez `eventPayloadFromValues` / `eventToPayload` (`resources/js/lib/person-payload.ts:63-85`). Panel dodawania osoby wysyła ukryte pola natywnie (`add-person-panel.tsx:247`).
- **Oś czasu:** kolumna roku pokazuje małe „ok.” nad rokiem (`resources/js/components/persons/events-tab.tsx:25-29`). Linia szczegółów pokazuje serwerowe `date` (z „ok. ”), gdy data ma miesiąc (`resources/js/lib/person-timeline.ts:133`). Stąd dziś podwójne „ok.”.
- **Import:**
  - `App\Enums\DateQualifier` (`exact`, `approximate`, `before`, `after`, `range`) i `App\Support\Import\ResearchDate` (`qualifier`, `year`, `month`, `day`, `untilYear`) istnieją od S-01, ale nic ich nie zapisuje.
  - Zakres w bloku to tylko gołe lata, `od < do` (`ResearchDate.php:26-35`). „przed” i „po” mogą mieć miesiąc i dzień.

## Desired End State

- `events` ma `date_qualifier` (wartości `DateQualifier`, domyślnie `exact`, nie null) i `date_until_year` (tylko dla zakresu). Istniejące wiersze mają `approximate` tam, gdzie flaga była `true`, a `exact` w pozostałych. Flaga `date_is_approximate` zostaje i jest zawsze zgodna z rodzajem.
- W polu „Data” autor wpisuje „1850”, „ok. 1850”, „przed 1809”, „po 12.05.1801”, „1805–1811”, „1805-1811” albo „między 1805 a 1811”. Podpowiedź pod polem nazywa rozpoznany rodzaj. Po zapisie i ponownym otwarciu pole pokazuje tę samą datę.
- Wyświetlanie:

  | Rodzaj | Krótko (karty, lata życia, listy) | Długo (panel osoby, karta pary) |
  | --- | --- | --- |
  | dokładna | `12.05.1850`, `1850` | `12 maja 1850` |
  | ok. | `ok. 1850` | `około 1850` |
  | przed | `przed 1809`, `przed 12.05.1809` | `przed 12 maja 1809` |
  | po | `po 1801` | `po 1801` |
  | zakres | `1805–1811` | `między 1805 a 1811` |

  Lata życia: `1805–1811 – 1870`, `przed 1809 – 1870`. Karta drzewa i karta przodka pokazują ten sam ciąg.
- Wiek (karta, nagłówek panelu, oś czasu) nie pokazuje się, gdy data urodzenia albo data końca (zgon, wydarzenie) jest „przed”, „po” lub zakresem. „ok.” liczy się jak dziś.
- Oś czasu: małe „ok.” / „przed” / „po” nad rokiem; zakres w kolumnie jako „1805–” nad „1811”; linia szczegółów pokazuje datę z miesiącem bez słowa rodzaju (koniec podwójnego „ok.”).
- S-02 zapisuje datę importu tą samą drogą co formularze: `MapsEventInput` z kluczami `qualifier` i `until_year`.

Weryfikacja: testy Pest (migracja, walidacja, zapis, zasoby, wiek, oś czasu), Vitest (parser, formatter, payload, sortowanie), jedna specyfikacja Playwright z obiegiem zakresu przez edycję, zrzuty ekranu w `context/changes/fuzzy-event-dates/screenshots/`.

### Key Discoveries:

- `CLAUDE.md` (sekcja „Migrations that move existing data”) rozstrzyga model danych: nowa kolumna, kopia `true → approximate`, `false → exact`, test migracji, usunięcie flagi dopiero w kolejnej zmianie.
- `MapsEventInput::eventAttributesFrom()` (`app/Actions/Persons/MapsEventInput.php:18`) to jedyny szew zapisu dat. S-02 zakłada, że nowy kontrakt idzie przez niego (`context/changes/first-import-into-empty-tree/plan.md:130`).
- `section-card.tsx:149-171` wykrywa zmiany przez `MutationObserver` na wartościach ukrytych pól, więc nowe ukryte pola są objęte automatycznie. `eventPayloadFromValues` i `eventToPayload` trzeba jednak rozszerzyć ręcznie, inaczej pełna podmiana zgubi rodzaj daty.
- `partial-date-input.tsx:58-62` wylicza klucze błędów serwera jawnie. Nowe klucze (`qualifier`, `until_year`) trzeba tam dopisać.
- `WriteTargetsTest.php:386-388` odsyła `date_parts` z powrotem w PUT. Nowe klucze `date_parts` muszą być akceptowanym wejściem.
- `SEPARATORS` w parserze zawiera `-` (`partial-date.ts:67`), więc „1805-1811” dziś daje błąd „miesiąc 1805”. Rozpoznanie zakresu musi nastąpić przed rozbiciem na części.
- `resources/js/lib/year-range.ts:2-19` (`formatYearRange`) już daje „1900–1910” (półpauza bez spacji) dla zamieszkań. Ten sam znak dla zakresu dat.

## What We're NOT Doing

- **Usunięcie `date_is_approximate`.** Zostaje do osobnej zmiany. Wzór: `context/archive/2026-10-22-drop-legacy-columns`.
- **Import dat z repo.** Robi go S-02 przez kontrakt z tej zmiany. `ResearchDate` zostaje bez zmian.
- **Zakres z miesiącem lub dniem, zakres otwarty („od 1805”), pory roku, styl juliański.** Juliański zostaje w notatce faktu (PRD FR-009).
- **Wiek jako przedział** („59–65 lat”, „ponad 61 lat”). Decyzja: brak wieku.
- **Sortowanie zależne od rodzaju.** „przed 1809” i „po 1809” sortują się jak 1809, zakres po roku początku, tak jak dziś „ok.”.
- **Rodzaj daty dla zamieszkań, roku źródła i pochowania.** Zamieszkania mają własne lata, a pochowanie nie ma daty.
- **Osobna kontrolka wyboru rodzaju.** Decyzja: tekst w polu „Data”.
- **Zmiana kart przodka poza ciągiem lat życia.** Karta przodka dostaje ten sam `lifespan` co karta drzewa (decyzja zapisana tu zgodnie z `CLAUDE.md`), bez innych zmian.

## Implementation Approach

Kolejność: dane → serwer → klient → przeglądarka. Każda faza kończy się zielonymi testami.

1. Migracja dodaje kolumny i kopiuje wartości z flagi.
2. `PartialDate` staje się jedynym nośnikiem rodzaju i roku końca. Formatowanie, wiek, walidacja i zasoby czytają go stamtąd.
3. Model `Event` wylicza flagę z rodzaju przy każdym zapisie. Dzięki temu fabryki, seedery i `FillBurialPlacesFromGravestone` nie mogą jej rozjechać z nową kolumną.
4. Wejście `is_approximate` znika z API na rzecz `qualifier` i `until_year`. Brak `qualifier` oznacza `exact`, tak jak dziś brak flagi oznacza `false`. Pomocnik E2E (`tests/e2e/support/api.ts`), który wysyła tylko `birth[year]`, działa dalej.
5. Na kliencie parser rozpoznaje nowe słowa i zakres, a formatter ma krótką formę (lustro PHP) i długą.

## Critical Implementation Details

- **Kolejność w parserze.** Zakres („RRRR–RRRR”, „RRRR-RRRR”, „RRRR - RRRR”, „między RRRR a RRRR”) trzeba rozpoznać przed przedrostkami i przed rozbiciem na separatory. Inaczej „1805-1811” trafi do ścieżki „miesiąc-rok”. Przedrostki rozpoznajemy jak dziś „ok.”, z normalizacją diakrytyków: „przed”, „po”, „około”/„ok”/„ca”/„~”. „po” musi być całym słowem, żeby nie złapać tekstu zaczynającego się od „po…”.
- **Spójność krótkiej formy PHP ↔ TS.** `formatPartialDate(…, 'short')` jest lustrem `PartialDate::format()`, a pole „Data” startuje od krótkiej formy (`partial-date-input.tsx:35-37`). Krótka forma każdego rodzaju musi wracać przez parser do tych samych części (test round-trip w `partial-date.test.ts:271`).
- **Flaga a pełna podmiana.** Rodzaj inny niż `exact` bez roku jest błędem walidacji, nie cichym `exact`. Zgadza się to z dzisiejszą regułą „ok. tylko z rokiem” (`PersonsTest.php:568-576`).

## Phase 1: Schemat i model

### Overview

Kolumny rodzaju daty i roku końca w `events` z kopią z flagi, test migracji, model z rzutowaniem na enum i flagą wyliczaną z rodzaju. Po tej fazie dane mają nowy kształt, a aplikacja zachowuje się jak dotąd.

### Changes Required:

#### 1. Migracja

**File**: `database/migrations/<timestamp>_add_date_qualifier_to_events_table.php` (przez `vendor/bin/sail artisan make:migration --no-interaction`)

**Intent**: Dodać rodzaj daty i rok końca zakresu, potem skopiować rodzaj z flagi. Flaga zostaje (copy first, drop later).

**Contract**:
- `date_qualifier` string(16), not null, default `'exact'`.
- `date_until_year` unsignedSmallInteger, nullable, po `date_day`.
- `up()`: `UPDATE events SET date_qualifier = 'approximate' WHERE date_is_approximate = true`. Pozostałe dostają `exact` z domyślnej wartości.
- `down()`: usuwa obie kolumny. Flaga jest zawsze zgodna z rodzajem, więc „ok.” przetrwa. Komentarz w `down()` mówi, że „przed”, „po” i zakres wpisane po migracji giną przy cofnięciu, bo stary schemat ich nie zna.

#### 2. Test migracji

**File**: `tests/Feature/DateQualifierMigrationTest.php` (wzór: `tests/Feature/SourceMigrationTest.php`)

**Intent**: Dowieść, że żadna wartość nie ginie i istniejące daty mają ten sam sens.

**Contract**:
- Stan sprzed migracji przez `->down()` i surowe `DB::table('events')->insert(...)`. Wiersze: dokładna z pełną datą, „ok.” z samym rokiem, „ok.” z miesiącem, bez daty, małżeństwo, pochowanie.
- Po `->up()`: `date_qualifier` zgodny z flagą, rok, miesiąc i dzień bez zmian, `date_until_year` null.
- `->down()` → `->up()` round-trip zachowuje „ok.”.
- Jeśli inne testy migracji wymagają starszego schematu, `restoreLegacyColumns()` z `tests/Pest.php`.

#### 3. Model `Event`

**File**: `app/Models/Event.php`

**Intent**: Rodzaj jest źródłem prawdy, flaga jego cieniem do czasu usunięcia.

**Contract**:
- `date_qualifier` i `date_until_year` w `#[Fillable]`; rzutowanie `date_qualifier` na `DateQualifier`, `date_until_year` na int.
- Hook `saving`: `date_is_approximate = date_qualifier === DateQualifier::Approximate`.
- `date_is_approximate` znika z `#[Fillable]`, żeby nikt nie zapisał go wprost. Kolumna i rzutowanie zostają.
- Akcesor `date` przekazuje rodzaj i rok końca do `PartialDate` (kontrakt w fazie 2). W tej fazie wystarczy, że `isApproximate` pochodzi z rodzaju.

#### 4. Fabryka, helpery i seedery

**Files**:
- `database/factories/EventFactory.php`
- `tests/Pest.php` (`attachMarriage`, `attachDeath`)
- `tests/Feature/PersonTimelineTest.php` (`attachTimelineEvent`; wywołania `:218`, `:429`, `:440`)
- `tests/Feature/TreeTest.php:835`
- `database/seeders/TreeViewDemoSeeder.php` (`:201`, `:549`)
- `app/Actions/Sources/FillBurialPlacesFromGravestone.php:43`
- `app/Actions/Persons/MapsEventInput.php` (most do fazy 2)

**Intent**: Wszystkie miejsca, które piszą `date_is_approximate` wprost, piszą rodzaj. Aplikacja nie ma `Model::shouldBeStrict`, więc klucz spoza `#[Fillable]` ginie po cichu przy `fill`/`update`, a hook nadpisuje flagę ustawioną w fabryce. Każde takie miejsce trzeba przepiąć w tej fazie.

**Contract**:
- Stan `approximate()` ustawia `date_qualifier => DateQualifier::Approximate`.
- Nowe stany `before()`, `after()`, `range(int $from, int $until)` (bez miesiąca i dnia).
- `attachDeath($person, $year, bool $approximate)` zachowuje sygnaturę i mapuje na rodzaj.
- `TreeTest.php:835`, `PersonTimelineTest.php:218` (atrybut fabryki) oraz `PersonTimelineTest.php:429,440` (`->update(...)`) ustawiają `date_qualifier => DateQualifier::Approximate` zamiast flagi. Asercje się nie zmieniają.
- `TreeViewDemoSeeder.php:201` (`->update`) i `:549` (fabryka) piszą `date_qualifier`.
- `FillBurialPlacesFromGravestone` przestaje ustawiać flagę.
- `MapsEventInput::eventAttributesFrom()` w tej fazie czyta jeszcze wejście `is_approximate`, ale oddaje `date_qualifier` (`Approximate` przy zaznaczonej, `Exact` w pozostałych) zamiast `date_is_approximate`. Faza 2 zamienia wejście na `qualifier` / `until_year`.
- Seedery wejściem do `CreateMarriage` przechodzą w fazie 2 razem ze zmianą klucza `is_approximate` → `qualifier`.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1`, potem ponownie `migrate`
- Test migracji przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/DateQualifierMigrationTest.php`
- Istniejące testy dat przechodzą bez zmian asercji: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonTimelineTest.php tests/Feature/TreeTest.php tests/Feature/WriteTargetsTest.php`
- Larastan i Pint czyste: `vendor/bin/sail composer types:check` i `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: Po fazie 1 i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie przed fazą 2.

---

## Phase 2: Serwer — zapis, walidacja, formatowanie, wiek

### Overview

`PartialDate` niesie rodzaj i rok końca. Walidacja i mapowanie przyjmują `qualifier` i `until_year`. Zasoby wysyłają nowy kształt. Formatowanie daje nowe etykiety, a wiek znika przy datach nieostrych.

### Changes Required:

#### 1. `PartialDate`

**File**: `app/Support/PartialDate.php`

**Intent**: Jeden nośnik rodzaju daty po stronie serwera, z którego czytają formatowanie, wiek i zasoby.

**Contract**:
- Konstruktor `(?int $year, ?int $month, ?int $day, DateQualifier $qualifier = DateQualifier::Exact, ?int $untilYear = null)` zastępuje `bool $isApproximate`. Wszystkie wywołania (`Event::date()`, `Age::from()`, `AgeTest`) dostosowane.
- `format()`:
  - dokładna: `12.05.1850` / `05.1850` / `1850`;
  - `ok. …`, `przed …`, `po …` z tą samą precyzją;
  - zakres: `1805–1811` (U+2013, bez spacji).
- `formatYear()`: ta sama reguła, tylko z rokiem (`przed 1809`, `1805–1811`).
- Nowa `formatWithoutQualifier()`: data z miesiącem bez słowa rodzaju, dla linii szczegółów osi czasu.
- Nowa `isFuzzy(): bool`: prawda dla `before`, `after`, `range`.
- Martwa `isEmpty()` (nigdzie niewołana) zostaje usunięta.

#### 2. Walidacja

**File**: `app/Concerns/PartialDateValidationRules.php` (+ `PersonStoreRequest`, `MarriageStoreRequest`, `MarriageUpdateRequest` — tylko nazwy atrybutów)

**Intent**: Rodzaj i rok końca walidowane tak samo jak gramatyka bloku importu, żeby formularz i import przyjmowały to samo.

**Contract**:
- `{prefix}.qualifier`: `nullable`, `Rule::enum(DateQualifier::class)`.
- `{prefix}.year`: `required_with:month`; dodatkowo wymagany, gdy `qualifier` nie jest null ani `exact` (zastępuje `required_if_accepted:is_approximate`).
- `{prefix}.until_year`: `nullable|integer|between:1000,<rok bieżący>`, wymagany przy `range`, zabroniony przy innych rodzajach.
- W hooku `after` (obok `validateFullDate`):
  - przy `range` `until_year > year`, błąd na `{prefix}.until_year`: „Rok końca zakresu musi być późniejszy niż rok początku.”;
  - przy `range` miesiąc i dzień zabronione, błąd na `{prefix}.month`.
- Reguła `is_approximate` znika. Polskie nazwy atrybutów: „rodzaj daty”, „rok końca zakresu”.

#### 3. Mapowanie zapisu

**File**: `app/Actions/Persons/MapsEventInput.php`

**Intent**: Jedyny szew zapisu daty. S-02 importuje przez niego.

**Contract**:
- Wejście `{year?, month?, day?, qualifier?, until_year?, locality_id?}`. `qualifier` to `DateQualifier|string|null`: formularze wysyłają string (`DateQualifier::tryFrom`), a S-02 przekazuje enum z `ResearchDate::$qualifier` wprost.
- Wyjście `date_year`, `date_month`, `date_day`, `date_qualifier` (brak lub pusty = `DateQualifier::Exact`), `date_until_year` (null poza zakresem), `locality_id`.
- `isChecked()` usunięte, jeśli nie ma innych użyć. PHPDoc z kształtami tablic zaktualizowany.
- Seedery (`TreeViewDemoSeeder.php:220`) przechodzą na `qualifier`.

#### 4. Zasoby

**Files**:
- `app/Http/Resources/EventResource.php`, `MarriageResource.php`, `SheetPersonResource.php` (`details`)
- `PersonFormResource.php`
- `TimelineEntryResource.php`

**Intent**: Klient dostaje rodzaj i rok końca wszędzie tam, gdzie dziś dostaje flagę.

**Contract**:
- `date_parts` = `{year, month, day, qualifier, until_year}` (bez `is_approximate`).
- `PersonFormResource` birth i death: płaskie `{year, month, day, qualifier, until_year, locality, is_confirmed}`.
- `TimelineEntryResource`:
  - `qualifier` i `until_year` zamiast `is_approximate`;
  - `date` = `formatWithoutQualifier()`, gdy data ma miesiąc (słowo rodzaju pokazuje kolumna).
- Lata życia (`SheetPersonResource.lifespan`) i rok ślubu (`BuildFamilySheet`) bez zmian w kodzie: biorą `formatYear()`, który zna nowe rodzaje. Karta przodka dostaje ten sam ciąg.

#### 5. Wiek

**File**: `app/Support/Age.php`

**Intent**: Nie pokazywać wieku, którego nie znamy.

**Contract**:
- `Age::from()` zwraca null, gdy `$birth->isFuzzy()` albo (dla zmarłego) `$end->isFuzzy()`.
- `Age::at()` dziedziczy regułę. „ok.” liczy się jak dziś.
- `PartialDate` dla „dziś” budowany z `DateQualifier::Exact`.

#### 6. Testy Pest

**Files**:
- `tests/Unit/PartialDateTest.php`, `tests/Unit/AgeTest.php`
- `tests/Feature/PersonsTest.php`, `MarriagesTest.php`, `PersonTimelineTest.php`, `TreeTest.php`, `WriteTargetsTest.php`

**Intent**: Zachowanie istniejących dat jest nietknięte, a nowe rodzaje działają na każdej ścieżce zapisu i odczytu.

**Contract**:
- `PartialDateTest`: `format()` i `formatYear()` dla każdego rodzaju i precyzji; `formatWithoutQualifier()`.
- `AgeTest`: brak wieku przy urodzeniu lub zgonie przed, po i zakresie; „ok.” bez zmian (istniejący test `:84` zostaje).
- `PersonsTest`:
  - zapis i edycja urodzenia „przed 12.05.1809” i zakresu 1805–1811 (wiersz w `events` z rodzajem i flagą false);
  - walidacja: rodzaj bez roku, zakres bez roku końca, `until_year <= year`, zakres z miesiącem, `until_year` przy `before`, nieznany rodzaj;
  - `qualifier` pominięty → `exact`.
- `MarriagesTest`: zapis i edycja ślubu „po 1830”; istniejący test „ok.” (`:20-45`) przechodzi na `qualifier: approximate` z flagą true w bazie.
- `PersonTimelineTest`: payload `qualifier` / `until_year`; `date` bez „ok.”; brak wieku przy „przed”.
- `TreeTest`: lata życia `1805–1811 – 1870` i `przed 1809 – 1870`, `marriage_year` „po 1830”; istniejące literały („ok. 1850 – 1912”) bez zmian.
- `WriteTargetsTest`: edycja odsyłająca nowe `date_parts` zmienia tylko oczekiwane wiersze.

### Success Criteria:

#### Automated Verification:

- Testy jednostkowe dat przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/PartialDateTest.php tests/Unit/AgeTest.php`
- Testy funkcjonalne dat przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonTimelineTest.php tests/Feature/TreeTest.php tests/Feature/WriteTargetsTest.php tests/Feature/DateQualifierMigrationTest.php`
- Testy odczytu repo (używają `DateQualifier`) przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/ResearchDateTest.php tests/Feature/ResearchRepoReadTest.php`
- Larastan poziom 7 i Pint czyste: `vendor/bin/sail composer types:check` i `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: Po fazie 2 i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie przed fazą 3. Klient w tej fazie może się rozjechać z serwerem (wysyła `is_approximate`). Fazy 2 i 3 wdrażamy razem.

---

## Phase 3: Frontend — pole „Data” i wyświetlanie

### Overview

Parser rozpoznaje „przed”, „po” i zakres. Formatter zna nowe rodzaje. Pole wysyła `qualifier` i `until_year`, payload sekcji je zachowuje. Oś czasu pokazuje słowo rodzaju nad rokiem, a `/persons` sortuje zakres po roku początku.

### Changes Required:

#### 1. Parser i formatter

**File**: `resources/js/lib/partial-date.ts` (+ `partial-date.test.ts`)

**Intent**: Ta sama gramatyka co blok repo, wpisywana w jedno pole.

**Contract**:
- `PartialDateParts` = `{year, month, day, qualifier, until_year}`. Typ `DateQualifier` = `'exact' | 'approximate' | 'before' | 'after' | 'range'`.
- `parsePartialDate` rozpoznaje:
  - zakres: `RRRR–RRRR`, `RRRR-RRRR`, `RRRR - RRRR`, `między RRRR a RRRR` (także „miedzy”);
  - przedrostki `przed`, `po` (całe słowo) i dotychczasowe „ok.”, każdy z dowolną dotychczasową precyzją.
- Nowe kody błędów: zakres z końcem nie późniejszym niż początek (`range-order`).
- `formatPartialDate(parts, 'short')` to lustro `PartialDate::format()`.
- `formatPartialDate(parts, 'long')`: słowo rodzaju + dotychczasowa długa data (`około 12 marca 1948`, `przed 12 marca 1809`, `po 1801`); zakres `między 1805 a 1811`.
- `describePrecision` (podpowiedź): „przed 1809 tylko rok”, „zakres lat 1805–1811”.
- Testy:
  - każdy rodzaj i separator zakresu;
  - „przed wojną” nadal niepoprawne (test `:65` zostaje);
  - tabela odrzuceń `:210-228`: przypadki `'1948-1950'`, `'1948 - 1950'`, `'przed 1948'`, `'po 1948'` (`:211-214`) przechodzą do przypadków rozpoznanych, reszta tabeli zostaje;
  - „1811–1805” → `range-order`;
  - „po” nie łapie „pon.” ani tekstu zaczynającego się od „po”;
  - round-trip short → parse dla każdego rodzaju.

#### 2. Pole „Data”

**File**: `resources/js/components/persons/partial-date-input.tsx`

**Intent**: To samo pole, nowe ukryte wartości.

**Contract**:
- Ukryte pola `{prefix}.qualifier` i `{prefix}.until_year` zastępują `{prefix}.is_approximate`.
- `defaultValue` przyjmuje nowe `PartialDateParts`.
- Podpowiedź domyślna: „np. 1948, 03.1948, ok. 1948, przed 1950, 1940–1945”.
- Lista kluczy błędów serwera (`:58-62`) obejmuje `qualifier` i `until_year`.

#### 3. Payload i typy

**Files**:
- `resources/js/lib/person-payload.ts` (+ test)
- `resources/js/types/person.ts`, `resources/js/types/tree.ts`

**Intent**: Pełna podmiana przy zapisie sekcji nie gubi rodzaju daty.

**Contract**:
- `PartialDatePayload`, `eventPayloadFromValues`, `eventToPayload`, `marriageFieldDefaults`: `qualifier` i `until_year` zamiast `is_approximate`.
- `date_parts`, `PersonFormEvent` i `TimelineEntry` z nowymi polami (`TimelineEntry.is_approximate` → `qualifier`, `until_year`).
- Test payloadu: zakres i „przed” z wartości formularza trafiają do payloadu sekcji i małżeństwa.

#### 4. Oś czasu

**Files**:
- `resources/js/lib/person-timeline.ts` (+ test)
- `resources/js/components/persons/events-tab.tsx`

**Intent**: Słowo rodzaju w kolumnie roku, bez powtórzenia w szczegółach.

**Contract**:
- Wiersz osi czasu ma `qualifierLabel: 'ok.' | 'przed' | 'po' | null` zamiast `approximate: boolean`. Ma też `year` jako ciąg: przy zakresie „1805–” i drugi wiersz „1811”.
- `events-tab.tsx`:
  - etykieta nad rokiem jak dziś „ok.”;
  - zakres w dwóch liniach w tej samej kolumnie;
  - `sr-only` z pełną datą słownie („między 1805 a 1811”), żeby czytnik nie czytał „1805 minus 1811”.
- Szczegóły pokazują serwerowe `date` (już bez słowa rodzaju).

#### 5. Sortowanie `/persons`

**File**: `resources/js/lib/persons-index.ts` (+ `persons-index.test.ts`)

**Intent**: Nowe pola nie psują sortowania. Zakres sortuje się po roku początku, rodzaj ignorowany (jak „ok.” dziś).

**Contract**: `dateKey` czyta `year`, `month`, `day` jak dotąd. Test potwierdza miejsce zakresu i „przed” w kolejności.

#### 6. Pozostałe miejsca wyświetlania

**Files**:
- `resources/js/lib/pair-details.ts` (+ test)
- `components/persons/overview-tab.tsx`, `components/marriages/marriage-panel.tsx`
- `lib/lifespan.ts`, `lib/marriage-details.ts`

**Intent**: Wszędzie, gdzie dziś stoi „około”, działa każdy rodzaj.

**Contract**:
- Te miejsca używają `formatPartialDate` albo serwerowych ciągów, więc zmiana sprowadza się do typów i testu `pair-details` („ur. między 1805 a 1811, Wieś”).
- `eventYear` dla wyszukiwarki źródeł zostaje `date_parts.year` (rok początku).

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą: `vendor/bin/sail npm run test:js` (w tym `partial-date.test.ts`, `person-payload.test.ts`, `person-timeline.test.ts`, `persons-index.test.ts`, `pair-details.test.ts`, `view-tokens.test.ts`)
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Istniejące specyfikacje E2E z datami przechodzą: `marriage-side-panel.spec.ts`, `person-events-and-note-tabs.spec.ts`, `source-found-by-place-and-year.spec.ts`, `gravestone-cemetery-and-buried.spec.ts`

#### Manual Verification:

- W panelu osoby wpisanie „przed 1809”, „po 12.05.1801”, „1805-1811” i „między 1805 a 1811” pokazuje właściwą podpowiedź, a „1811–1805” blokuje zapis z komunikatem
- Po zapisie i ponownym otwarciu pole pokazuje „przed 1809” / „po 12.05.1801” / „1805–1811”
- Karta drzewa i karta przodka pokazują „1805–1811 – 1870”, bez wieku przy dacie nieostrej; „ok. 1850 – 1912” wygląda jak dotąd
- Zakładka Wydarzenia: „przed” nad rokiem, zakres w dwóch liniach, brak podwójnego „ok.” przy dacie z miesiącem
- Ślub „po 1830” widoczny w panelu małżeństwa, na łączniku w drzewie („ślub po 1830”) i w zakładce Rodzina
- Zrzuty ekranu (pole z podpowiedzią, oś czasu, karta drzewa, karta przodka; jasny i ciemny motyw) w `context/changes/fuzzy-event-dates/screenshots/`

**Implementation Note**: Po fazie 3 i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Phase 4: E2E — zakres przeżywa edycję

### Overview

Jedna specyfikacja Playwright dla ryzyka nr 1 z `context/foundation/test-plan.md` („fakt zmienia się po cichu po edycji”). Nowe pola idą przez pełną podmianę sekcji, a pominięcie ich w payloadzie cofnęłoby datę do „dokładnej” bez błędu. Prowadzone przez `/10x-e2e` (eksploracja, generowanie, przegląd wobec antywzorców, weryfikacja celowym błędem).

### Changes Required:

#### 1. Specyfikacja

**File**: `tests/e2e/fuzzy-event-dates.spec.ts`

**Intent**: Dowieść w przeglądarce, że data nieostra przeżywa zapis sekcji, zapis innej sekcji tej samej osoby i ponowne otwarcie.

**Contract**:
- Osoba z unikalnym nazwiskiem (sufiks timestamp) przez `tests/e2e/support/api.ts`.
- W panelu osoby urodzenie „1805–1811” i zapis sekcji.
- Edycja i zapis sekcji zgonu „przed 1870”.
- Ponowne otwarcie: pole urodzenia ma wartość „1805–1811”, a zakładka Wydarzenia pokazuje zakres i „przed”.
- Lokatory: `getByRole('textbox', { name: 'Data' })` w obrębie sekcji, bez `waitForTimeout`.
- Sprzątanie osoby po teście.
- Weryfikacja celowym błędem: usunięcie `qualifier` z `eventToPayload` daje czerwony test.

### Success Criteria:

#### Automated Verification:

- Nowa specyfikacja przechodzi lokalnie: `npx playwright test tests/e2e/fuzzy-event-dates.spec.ts` (komenda wg `context/foundation/test-stack.md`)
- Specyfikacja jest czerwona po celowym usunięciu `qualifier` z `eventToPayload` i zielona po przywróceniu
- Cały zestaw E2E przechodzi w CI

---

## Testing Strategy

### Unit Tests:

- `PartialDate`: formatowanie każdego rodzaju i precyzji, `formatWithoutQualifier`, `isFuzzy`.
- `Age`: brak wieku przy przed, po i zakresie po każdej stronie; „ok.” bez zmian.
- `partial-date.ts`:
  - parsowanie wszystkich form zakresu i przedrostków;
  - kolejność zakresu;
  - „po” jako całe słowo;
  - round-trip short.

### Integration Tests:

- Migracja: kopia z flagi, brak utraty wartości, round-trip `down` → `up`.
- Zapis i edycja osoby i małżeństwa z każdym rodzajem; walidacja kombinacji niepoprawnych; brak `qualifier` = `exact`.
- Zasoby: `date_parts`, lata życia, rok ślubu, oś czasu.
- `WriteTargetsTest`: tylko oczekiwane wiersze się zmieniają.
- E2E: zakres i „przed” przeżywają zapisy sekcji.

### Manual Testing Steps:

1. Na danych deweloperskich sprawdź, że istniejące „ok.” wygląda identycznie na karcie, w panelu i na osi czasu.
2. Wpisz każdy rodzaj daty w urodzeniu, zgonie i ślubie; zapisz, odśwież, sprawdź pole i wyświetlanie.
3. Wpisz „1811–1805” i „przed” bez roku — zapis zablokowany z komunikatem.
4. Sprawdź `/persons` posortowane po urodzeniu z osobą z zakresem.

## Performance Considerations

Brak. Jedna aktualizacja wierszy z flagą przy migracji (tabela `events` ma setki do tysięcy wierszy); bez nowych zapytań przy odczycie.

## Migration Notes

- Wdrożenie: `scripts/deploy.sh` robi `backup:run --only-db` przed `migrate`; bez zmian.
- Fazy 2 i 3 muszą trafić na produkcję razem z fazą 1 (lekcja z `context/archive/2026-10-06-residence-history/plan.md:61`). Stary klient wysyłający `is_approximate` po nowym serwerze zapisałby „ok.” jako `exact`.
- Usunięcie `date_is_approximate` w osobnej, późniejszej zmianie: strażnik liczący wiersze bez kopii jak w `2026_10_22_124123_drop_legacy_columns.php:87-98`.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-07, S-02)
- PRD: `context/foundation/prd.md` (FR-009, FR-015, „Istniejące dane”)
- Kontrakt bloku (gramatyka dat): `context/foundation/research-block-contract.md`
- Plan zależny: `context/changes/first-import-into-empty-tree/plan.md:113,130`
- Pochodzenie dat częściowych i „ok.”: `context/archive/2026-09-23-account-tree-and-first-person/plan.md`, pole „Data”: `context/archive/2026-09-29-person-modal/plan.md:214-280`
- Wiek: `context/archive/2026-10-05-maiden-name-and-age/plan.md:56-62`; oś czasu: `context/archive/2026-10-19-person-events-timeline/plan.md`
- Wzór testu migracji: `tests/Feature/SourceMigrationTest.php`; wzór usunięcia kolumn: `context/archive/2026-10-22-drop-legacy-columns/`
- Enum i parser importu: `app/Enums/DateQualifier.php`, `app/Support/Import/ResearchDate.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Schemat i model

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1`, potem ponownie `migrate` — 34b7cb6
- [x] 1.2 Test migracji przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/DateQualifierMigrationTest.php` — 34b7cb6
- [x] 1.3 Istniejące testy dat przechodzą bez zmian asercji: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonTimelineTest.php tests/Feature/TreeTest.php tests/Feature/WriteTargetsTest.php` — 34b7cb6
- [x] 1.4 Larastan i Pint czyste: `vendor/bin/sail composer types:check` i `vendor/bin/sail bin pint --dirty --format agent` — 34b7cb6

### Phase 2: Serwer — zapis, walidacja, formatowanie, wiek

#### Automated

- [x] 2.1 Testy jednostkowe dat przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/PartialDateTest.php tests/Unit/AgeTest.php` — 1a1a689
- [x] 2.2 Testy funkcjonalne dat przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonTimelineTest.php tests/Feature/TreeTest.php tests/Feature/WriteTargetsTest.php tests/Feature/DateQualifierMigrationTest.php` — 1a1a689
- [x] 2.3 Testy odczytu repo (używają `DateQualifier`) przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/ResearchDateTest.php tests/Feature/ResearchRepoReadTest.php` — 1a1a689
- [x] 2.4 Larastan poziom 7 i Pint czyste: `vendor/bin/sail composer types:check` i `vendor/bin/sail bin pint --dirty --format agent` — 1a1a689

### Phase 3: Frontend — pole „Data” i wyświetlanie

#### Automated

- [x] 3.1 Testy Vitest przechodzą: `vendor/bin/sail npm run test:js` (w tym `partial-date.test.ts`, `person-payload.test.ts`, `person-timeline.test.ts`, `persons-index.test.ts`, `pair-details.test.ts`, `view-tokens.test.ts`) — 3fd3138
- [x] 3.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — 3fd3138
- [x] 3.3 Format i lint przechodzą: `vendor/bin/sail npm run check` — 3fd3138
- [x] 3.4 Build przechodzi: `vendor/bin/sail npm run build` — 3fd3138
- [x] 3.5 Istniejące specyfikacje E2E z datami przechodzą: `marriage-side-panel.spec.ts`, `person-events-and-note-tabs.spec.ts`, `source-found-by-place-and-year.spec.ts`, `gravestone-cemetery-and-buried.spec.ts` — 3fd3138

#### Manual

- [x] 3.6 W panelu osoby wpisanie „przed 1809”, „po 12.05.1801”, „1805-1811” i „między 1805 a 1811” pokazuje właściwą podpowiedź, a „1811–1805” blokuje zapis z komunikatem — 3fd3138
- [x] 3.7 Po zapisie i ponownym otwarciu pole pokazuje „przed 1809” / „po 12.05.1801” / „1805–1811” — 3fd3138
- [x] 3.8 Karta drzewa i karta przodka pokazują „1805–1811 – 1870”, bez wieku przy dacie nieostrej; „ok. 1850 – 1912” wygląda jak dotąd
- [x] 3.9 Zakładka Wydarzenia: „przed” nad rokiem, zakres w dwóch liniach, brak podwójnego „ok.” przy dacie z miesiącem — 3fd3138
- [x] 3.10 Ślub „po 1830” widoczny w panelu małżeństwa, na łączniku w drzewie („ślub po 1830”) i w zakładce Rodzina
- [ ] 3.11 Zrzuty ekranu (pole z podpowiedzią, oś czasu, karta drzewa, karta przodka; jasny i ciemny motyw) w `context/changes/fuzzy-event-dates/screenshots/`

### Phase 4: E2E — zakres przeżywa edycję

#### Automated

- [x] 4.1 Nowa specyfikacja przechodzi lokalnie: `npx playwright test tests/e2e/fuzzy-event-dates.spec.ts` (komenda wg `context/foundation/test-stack.md`) — 111f621
- [x] 4.2 Specyfikacja jest czerwona po celowym usunięciu `qualifier` z `eventToPayload` i zielona po przywróceniu — 111f621
- [ ] 4.3 Cały zestaw E2E przechodzi w CI
