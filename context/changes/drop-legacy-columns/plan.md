# Usunięcie starych kolumn po migracjach M-2 — Implementation Plan

## Overview

Baza ma sześć kolumn, których po migracjach M-2 i późniejszych porządkach nikt już nie powinien czytać ani zapisywać: `people.residence`, `people.maiden_name`, `events.place`, `sources.entry_number`, `sources.provenance`, `sources.cemetery_locality_id`. Usuwamy je razem z UI „Stary numer wpisu”. Trzy stare numery wpisu z produkcji najpierw przechodzą automatycznie na powiązania fakt–źródło, a migracja usuwająca przerywa się, jeśli jakakolwiek wartość nie ma kopii w nowym miejscu. Zamyka issue #35.

## Current State Analysis

Dane produkcyjne (zapytania tylko do odczytu, 2026-10-22):

| kolumna | niepuste | bez odpowiednika | gdzie jest kopia |
|---|---|---|---|
| `people.residence` | 44 | 0 | `residences` |
| `events.place` | 71 | 0 | `events.locality_id` |
| `people.maiden_name` | 25 | 0 | `people.last_name` (migracja 2026-10-21) |
| `sources.cemetery_locality_id` | 10 | 0 | `sources.cemetery_id` |
| `sources.provenance` | 25 | 4 | `sources.archive`; 4 nagrobki z pustym archiwum mają ten tekst w `note` (migracja 2026-10-22) |
| `sources.entry_number` | 3 | 3 | brak — akty 2, 3, 7, każdy z jednym powiązaniem bez numeru |

Lokalna baza deweloperska ma dodatkowo 347 zdarzeń z tekstem badawczym w `events.place` bez `locality_id` (import z 2026-10-15, którego nie ma na produkcji) i 1 stary numer wpisu.

Kod (mapa odwołań z badań przed planem):

- **Martwe w kodzie aplikacji** — tylko metadane modelu: `app/Models/Person.php:24` (`@property maiden_name`), `:26` + `:34` (`residence` w `@property` i `Fillable`); `app/Models/Event.php:25` + `:33` (`place`); `app/Models/Source.php:24` (`@property cemetery_locality_id`). `provenance` nie ma żadnego odwołania w aplikacji.
- **Pola API o tej samej nazwie, ale wyliczane** (zostają): `place` w `EventResource.php:46`, `TimelineEntryResource.php:46`, `SheetPersonResource.php:101`, `MarriageResource.php:32`; `cemetery_locality_id` w `SourceResource.php:75` (`$this->cemetery?->locality_id`) i w TS (`types/source.ts:35`, `sources-index.ts:284`, `source-description-fields.tsx:102`).
- **Żywe: „Stary numer wpisu”** — `SourceResource.php:67` (`legacy_entry_number`), `app/Actions/Sources/ClearLegacyEntryNumber.php`, `app/Http/Controllers/SourceLegacyEntryNumberController.php`, `routes/web.php:24,45` (`sources.legacy-entry-number.destroy`), `app/Models/Source.php:21` + `:39` (`entry_number` w `@property` i `Fillable`; `withPivot('entry_number')` w `:114` to pivot i zostaje), `resources/js/types/source.ts:25`, `resources/js/components/sources/legacy-entry-number.tsx`, `source-panel.tsx:11,538`, `database/factories/SourceFactory.php:72` (stan `described()`), fixture `resources/js/lib/sources-index.test.ts:32`.
- **Brak zależności w bazie**: żadnych indeksów, widoków ani CHECK na tych kolumnach; jedyny klucz obcy (`cemetery_locality_id`) usunięty w `2026_10_20_130000_move_gravestone_cemeteries_to_cemeteries.php:47`.
- **Testy migracji danych** (`tests/Feature/*MigrationTest.php`) działają na w pełni zmigrowanym schemacie (`RefreshDatabase`, `tests/Pest.php:29-31`): wywołują `down()`/`up()` jednej starej migracji i wstawiają wiersze do starych kolumn. Po usunięciu kolumn psuje się 8 plików: `ResidenceMigrationTest`, `LocalitySeedMigrationTest`, `FactPlaceLocalityMigrationTest`, `EventSourceEntryNumberMigrationTest`, `SourceArchiveMigrationTest`, `SourceMigrationTest` (test opisu, `:224-238`), `SingleSurnameMigrationTest`, `GravestoneCemeteryMigrationTest`.
- Aplikacja nie używa `Model::shouldBeStrict`, więc odczyt usuniętego atrybutu zwraca po cichu `null` — usunięcie metadanych i testów nie wyjdzie samo z siebie, trzeba je zrobić świadomie.

## Desired End State

- Po `migrate` żadna z sześciu kolumn nie istnieje; trzy stare numery wpisu z produkcji są na powiązaniach aktów 2, 3, 7 i widać je przy fakcie („nr 38” itd.).
- Panel źródła nie ma sekcji „Stary numer wpisu”; trasa `sources.legacy-entry-number.destroy` nie istnieje.
- Migracja usuwająca na bazie z wartościami bez kopii przerywa się z komunikatem, który nazywa kolumnę i liczbę wartości, i nic nie usuwa.
- Wszystkie testy (Pest, Vitest, E2E) zielone; 8 testów migracji nadal sprawdza swoje stare migracje.

### Key Discoveries:

- `database/migrations/2026_10_05_135710_add_entry_number_to_event_source_table.php:12` — „`sources.entry_number` is left untouched (copy first, drop later)”: stary numer nigdy nie został skopiowany, stąd osobna migracja kopiująca.
- `tests/Feature/GravestoneCemeteryMigrationTest.php:19-22` — wzorzec „cofnij migrację w `beforeEach`, transakcja testu to odwraca”; zamrożenie testów to ten sam ruch o krok wcześniej.
- Typy kolumn do `down()`: `people.residence` string nullable (`create_people_table.php:19`), `people.maiden_name` string nullable po `last_name` (`add_maiden_name_to_people_table.php:15`), `events.place` string nullable (`create_events_table.php:22`), `sources.entry_number` string(50) nullable i `sources.provenance` string(255) nullable (`add_description_to_sources_table.php:18,20`), `sources.cemetery_locality_id` foreignId nullable **bez** klucza obcego (stan po `2026_10_20_130000`).

## What We're NOT Doing

- Zmiany nazw pól API `place` i `cemetery_locality_id` (wyliczane, zostają).
- Usuwania pivotu `event_source.entry_number` ani UI numeru wpisu przy powiązaniu.
- Ratowania lokalnych notatek z importu z 15.10 w `events.place` — lokalna baza jest odświeżana z kopii produkcji (faza 3).
- Usuwania testów migracji danych (są zamrażane, nie kasowane).
- Przenoszenia numerów, gdy źródło ma kilka powiązań lub powiązanie ma już numer — takie wartości zatrzymują bezpiecznik; na produkcji ich nie ma.
- Uruchamiania deployu — robi go użytkownik (`scripts/deploy.sh`, z `backup:run --only-db` przed `migrate`).

## Implementation Approach

Najpierw znika funkcja, która czyta i czyści stary numer (faza 1) — aplikacja działa dalej, bo kolumny jeszcze są. Potem dwie migracje w jednym wydaniu (faza 2): kopiująca stary numer na jedyne puste powiązanie, i usuwająca sześć kolumn, która najpierw liczy wartości bez kopii i przerywa przy niezerowym wyniku. Warunki bezpiecznika są te same, co w zapytaniu kontrolnym użytym na produkcji, z jedną różnicą dla `entry_number`: kopia istnieje, gdy któreś powiązanie źródła ma ten sam numer. Faza 3 odświeża lokalną bazę, przepuszcza zmianę przez CI i kończy się kontrolą produkcji po deployu użytkownika.

## Critical Implementation Details

**Kolejność migracji.** Migracja kopiująca musi mieć wcześniejszy znacznik czasu niż usuwająca; obie w jednym deployu. Zasada z CLAUDE.md („nigdy nie usuwaj kolumny w migracji, która z niej kopiuje”) jest zachowana, bo to dwie migracje.

**Warunki bezpiecznika (wartość bez kopii):**
- `people.residence` niepusty i osoba nie ma żadnego wiersza w `residences`;
- `people.maiden_name` niepusty i różny (bez wielkości liter) od `last_name`;
- `events.place` niepusty i `locality_id` puste;
- `sources.entry_number` niepusty i żadne powiązanie `event_source` tego źródła nie ma tego samego numeru;
- `sources.provenance` niepusty, `archive` puste i tekstu (po `btrim`) nie ma w `note`;
- `sources.cemetery_locality_id` ustawione i `cemetery_id` puste.

„Niepusty” = `btrim(coalesce(x, '')) <> ''`. Bezpiecznik rzuca wyjątek przed jakimkolwiek `dropColumn`, z listą kolumn i liczbami.

**Zamrożenie testów migracji.** Każdy z 8 plików na początku `beforeEach` (przed swoim dotychczasowym `down()`) wywołuje `down()` migracji usuwającej, która przywraca puste kolumny na czas testu; transakcja testu to cofa. `down()` migracji usuwającej musi więc być idempotentnie wykonalny na w pełni zmigrowanym schemacie.

## Phase 1: Usunięcie „Stary numer wpisu”

### Overview

Znika jedyna funkcja, która czyta i zapisuje `sources.entry_number`. Kolumna jeszcze istnieje, aplikacja działa.

### Changes Required:

#### 1. Backend funkcji

**File**: `app/Http/Controllers/SourceLegacyEntryNumberController.php`, `app/Actions/Sources/ClearLegacyEntryNumber.php` (usunięte), `routes/web.php`, `app/Http/Resources/SourceResource.php`, `database/factories/SourceFactory.php`

**Intent**: Usunąć kontroler, akcję i trasę czyszczenia starego numeru oraz klucz `legacy_entry_number` z zasobu źródła; stan fabryki `described()` przestaje ustawiać `entry_number`.

**Contract**: Trasa `sources.legacy-entry-number.destroy` i jej `use` w `routes/web.php:24,45` znikają; `SourceResource` traci klucz `legacy_entry_number` i wpis w docblocku (`:33`, `:67`); `SourceFactory::described()` bez `entry_number`. Po zmianie: `vendor/bin/sail artisan wayfinder:generate`.

#### 2. Frontend funkcji

**File**: `resources/js/components/sources/legacy-entry-number.tsx` (usunięty), `resources/js/components/sources/source-panel.tsx`, `resources/js/types/source.ts`, `resources/js/lib/sources-index.test.ts`

**Intent**: Panel źródła przestaje renderować sekcję starego numeru; typ źródła traci pole `legacy_entry_number`.

**Contract**: Import (`:11`) i użycie `<LegacyEntryNumber>` (`:538`) usunięte; `legacy_entry_number` usunięte z typu i z fixture testu.

#### 3. Testy funkcji

**File**: `tests/Feature/SourcesTest.php`, `tests/Feature/WriteTargetsTest.php`, `tests/Feature/OwnershipBoundariesTest.php`

**Intent**: Usunąć testy funkcji, która znika (decyzja użytkownika: testy usuwanej funkcji usuwamy): struktura JSON bez `legacy_entry_number` (`SourcesTest:52`), asercje `null` po zapisie (`:534,561,584`), testy zachowania i czyszczenia starego numeru (`:612-723`), `WriteTargetsTest:488-516`, `OwnershipBoundariesTest:124`.

**Contract**: Żaden test nie odwołuje się do `legacy_entry_number` ani do trasy `sources.legacy-entry-number.*`; testy pivotu `event_source.entry_number` zostają nietknięte.

### Success Criteria:

#### Automated Verification:

- Pełny Pest przechodzi: `vendor/bin/sail artisan test --compact`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`
- Front: `vendor/bin/sail npm run check`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run test:js`
- Brak odwołań do starej funkcji: `grep -rn 'legacy_entry_number\|LegacyEntryNumber\|legacy-entry-number' app routes resources/js tests database` zwraca pusto (poza wygenerowanymi plikami, które `wayfinder:generate` usuwa)

#### Manual Verification:

- Panel źródła z aktem nie pokazuje sekcji „Stary numer wpisu”; numer przy powiązaniu („nr …”) działa jak dotąd

**Implementation Note**: Po zielonej automatyce zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Migracje i model

### Overview

Migracja kopiująca stary numer wpisu, migracja usuwająca sześć kolumn z bezpiecznikiem, sprzątanie metadanych modeli i testów.

### Changes Required:

#### 1. Migracja kopiująca stary numer wpisu

**File**: `database/migrations/<timestamp>_move_legacy_entry_numbers_to_links.php` (nowa, `make:migration`)

**Intent**: Dla każdego źródła z niepustym `sources.entry_number`, które ma dokładnie jedno powiązanie `event_source` z pustym numerem, wpisać ten numer na to powiązanie. Kolumny źródła nie zmienia (copy first). Inne przypadki zostawia — zatrzyma je bezpiecznik.

**Contract**: `up()` w transakcji, tylko `event_source.entry_number`; `down()` czyści numery, które `up()` wpisał (te powiązania, których numer równa się numerowi źródła, a źródło ma jedno powiązanie) — albo komentarz, dlaczego nie da się tego odróżnić od ręcznie wpisanych; implementujący wybiera i opisuje w docblocku.

#### 2. Test migracji kopiującej

**File**: `tests/Feature/LegacyEntryNumberMigrationTest.php` (nowy, wzorzec `tests/Feature/SourceMigrationTest.php` i `EventSourceEntryNumberMigrationTest.php`)

**Intent**: Wstawić wiersze w starym kształcie, uruchomić migrację, sprawdzić nowy kształt i że nic nie zginęło.

**Contract**: Przypadki: jedno puste powiązanie → numer przeniesiony; powiązanie z już wpisanym numerem → bez zmian; dwa powiązania → bez zmian; źródło bez numeru → bez zmian; `sources.entry_number` po migracji nadal ma wartość. Test działa na schemacie przed usunięciem kolumn (w `beforeEach` cofa migrację usuwającą — jak testy zamrożone w punkcie 5).

#### 3. Migracja usuwająca kolumny z bezpiecznikiem

**File**: `database/migrations/<timestamp>_drop_legacy_columns.php` (nowa, późniejsza niż kopiująca)

**Intent**: Policzyć wartości bez kopii według warunków z „Critical Implementation Details” i przerwać z czytelnym komunikatem, gdy którakolwiek liczba > 0; w przeciwnym razie usunąć sześć kolumn.

**Contract**: `up()`: bezpiecznik → `dropColumn` w `people` (`residence`, `maiden_name`), `events` (`place`), `sources` (`entry_number`, `provenance`, `cemetery_locality_id`). Wyjątek (np. `RuntimeException`) z treścią w stylu „Nie usuwam starych kolumn: events.place — 347 wartości bez kopii; …” i wskazówką, że dane trzeba przenieść lub odtworzyć bazę z kopii. `down()` przywraca puste kolumny o typach z Key Discoveries (`cemetery_locality_id` bez klucza obcego) i w docblocku mówi, że wartości wracają tylko z kopii `backup:run --only-db` sprzed `migrate`.

#### 4. Test migracji usuwającej

**File**: `tests/Feature/DropLegacyColumnsMigrationTest.php` (nowy)

**Intent**: Udowodnić, że bezpiecznik zatrzymuje utratę danych, a przy pełnych kopiach kolumny znikają.

**Contract**: Po `down()` tej migracji: (a) dla każdej z sześciu kolumn wartość bez kopii → `up()` rzuca wyjątek, który nazywa tę kolumnę, i żadna kolumna nie znika; (b) wartości z kopią (residence z wierszem `residences`, place z `locality_id`, maiden_name równe `last_name`, entry_number równy numerowi powiązania, provenance w `note` przy pustym `archive`, cemetery_locality_id z `cemetery_id`) → `up()` przechodzi i `Schema::hasColumn` zwraca `false` dla wszystkich sześciu.

#### 5. Zamrożenie testów migracji danych

**File**: `ResidenceMigrationTest`, `LocalitySeedMigrationTest`, `FactPlaceLocalityMigrationTest`, `EventSourceEntryNumberMigrationTest`, `SourceArchiveMigrationTest`, `SourceMigrationTest` (test opisu), `SingleSurnameMigrationTest`, `GravestoneCemeteryMigrationTest` w `tests/Feature/`

**Intent**: Testy dalej sprawdzają swoje stare migracje: najpierw cofają migrację usuwającą (kolumny wracają na czas testu), potem robią to, co dotąd.

**Contract**: Pierwszy krok `beforeEach` (lub początek testu, gdy plik nie ma `beforeEach`) to `down()` migracji usuwającej, przez wspólny helper (np. w `tests/Pest.php` albo lokalny w stylu `static $migration`, jak w `GravestoneCemeteryMigrationTest.php:12-17`). Asercje bez zmian.

#### 6. Metadane modeli i testy starych kluczy

**File**: `app/Models/Person.php`, `app/Models/Event.php`, `app/Models/Source.php`, `tests/Feature/PersonsTest.php`, `tests/Feature/MarriagesTest.php`, `tests/Feature/SourcesTest.php`, `tests/Feature/LocalitiesTest.php`

**Intent**: Modele przestają deklarować usunięte kolumny; znikają testy, które istnieją tylko dla starych kluczy (decyzja użytkownika).

**Contract**:
- `Person`: bez `@property` `maiden_name` (`:24`) i `residence` (`:26`), bez `residence` w `Fillable` (`:34`).
- `Event`: bez `@property place` (`:25`) i `place` w `Fillable` (`:33`).
- `Source`: bez `@property` `entry_number` (`:21`) i `cemetery_locality_id` (`:24`), bez `entry_number` w `Fillable` (`:39`); `withPivot('entry_number')` zostaje.
- Testy usunięte: `PersonsTest:924-935` (ignorowany klucz residence), `:1392-1407`, `:1419-1441`, `:1443-1460` (maiden_name), `:1492-1506` (place); 4 asercje `$marriage->place` w `MarriagesTest:224,434,552,596`; `SourcesTest:565` (odczyt provenance) i `:1471-1483`; `LocalitiesTest:345-356` (zapis `cemetery_locality_id`). Numery linii z badań — mogą się przesunąć po fazie 1.

### Success Criteria:

#### Automated Verification:

- Nowe testy migracji przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LegacyEntryNumberMigrationTest.php tests/Feature/DropLegacyColumnsMigrationTest.php`
- Celowe zepsucie: wyłączony warunek bezpiecznika dla `events.place` daje czerwony `DropLegacyColumnsMigrationTest` (przywrócone po sprawdzeniu)
- Pełny Pest przechodzi (w tym 8 zamrożonych testów migracji): `vendor/bin/sail artisan test --compact`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`
- Migracje od zera przechodzą na pustej bazie testowej (CI `tests` job robi `migrate` w `composer setup`)

#### Manual Verification:

- Komunikat bezpiecznika jest zrozumiały (sprawdzony na lokalnej bazie przed restore: `vendor/bin/sail artisan migrate` przerywa się i nazywa `events.place` oraz liczbę)

**Implementation Note**: Lokalne `migrate` na obecnej bazie deweloperskiej MA się przerwać (347 notatek z importu) — to dowód bezpiecznika, nie błąd. Nie poprawiaj danych ręcznie; faza 3 odświeża bazę.

---

## Phase 3: Lokalna baza i wydanie

### Overview

Lokalna baza staje się kopią produkcji, zmiana przechodzi przez CI, użytkownik robi deploy, a zapytanie kontrolne po deployu potwierdza wynik.

### Changes Required:

#### 1. Odświeżenie lokalnej bazy

**Intent**: Lokalna baza przestaje mieć dane z importu z 15.10, których nie ma na produkcji; `migrate` przechodzi przez bezpiecznik.

**Contract**: Użytkownik kopiuje najnowsze archiwum produkcji i uruchamia `vendor/bin/sail artisan backup:restore` (komenda z `app/Console/Commands/RestoreBackup.php`); potem `vendor/bin/sail artisan migrate`.

#### 2. PR i CI

**Intent**: Gałąź zmiany do `develop` z zielonymi `lint`, `static-analysis`, `tests`, `e2e`.

**Contract**: PR z opisem migracji nieodwracalnej co do danych i wskazaniem kopii przed `migrate`.

#### 3. Deploy i kontrola

**Intent**: Po merge'u do `main` użytkownik robi deploy; zapytanie kontrolne potwierdza, że kolumn nie ma, a trzy numery są na powiązaniach.

**Contract**: Zapytanie tylko do odczytu na produkcji: `information_schema.columns` dla sześciu kolumn zwraca 0 wierszy; `event_source.entry_number` dla źródeł 2, 3, 7 ma 38, 145, 118. Issue #35 zamknięte.

### Success Criteria:

#### Automated Verification:

- Workflowy `lint`, `static-analysis`, `tests`, `e2e` zielone na PR

#### Manual Verification:

- Lokalnie po `backup:restore` z kopii produkcji `migrate` przechodzi, a panel źródła aktu 2 pokazuje przy fakcie „nr 38”
- Po deployu na produkcji zapytanie kontrolne: brak sześciu kolumn, numery 38, 145, 118 na powiązaniach aktów 2, 3, 7
- Issue #35 zamknięte z wynikiem kontroli

---

## Testing Strategy

### Unit / Feature Tests:

- `LegacyEntryNumberMigrationTest`: przeniesienie numeru tylko na jedyne puste powiązanie; reszta nietknięta; nic nie ginie.
- `DropLegacyColumnsMigrationTest`: bezpiecznik dla każdej z sześciu kolumn osobno + szczęśliwa ścieżka.
- 8 zamrożonych testów migracji danych — bez zmian w asercjach.

### Integration Tests:

- Pełny Pest i pełny E2E w CI (numer wpisu przy powiązaniu i panel źródła są pokryte istniejącymi specyfikacjami).

### Manual Testing Steps:

1. Przed restore: lokalne `migrate` przerywa się komunikatem bezpiecznika (`events.place`).
2. Po restore z kopii produkcji: `migrate` przechodzi; akt 2 pokazuje „nr 38” przy fakcie; panel źródła bez „Stary numer wpisu”.
3. Po deployu: zapytanie kontrolne na produkcji.

## Migration Notes

- Nieodwracalne co do danych: `down()` przywraca tylko puste kolumny. Kopia `backup:run --only-db` w `scripts/deploy.sh` przed `migrate` jest jedyną drogą powrotu — nie ruszać.
- Kolejność w jednym deployu: kopiująca → usuwająca. Jeśli bezpiecznik zatrzyma deploy na produkcji, migracja usuwająca nie zostaje zapisana w `migrations`, aplikacja (bez odwołań do kolumn) działa dalej; wartości trzeba przenieść i powtórzyć `migrate`.

## References

- Issue: #35 „Usunięcie starych kolumn po migracjach M-2”
- Wzorzec testu migracji: `tests/Feature/SourceMigrationTest.php`, `tests/Feature/GravestoneCemeteryMigrationTest.php`
- Zasady: CLAUDE.md „Migrations that move existing data”
- Poprzednie zmiany: `context/archive/2026-10-05-source-found-by-place-and-year/`, `context/archive/2026-10-06-residence-history/`, `context/archive/2026-10-06-fact-places-from-gazetteer/`, `context/archive/2026-10-21-single-birth-surname/`, `context/archive/2026-10-20-cemeteries/`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Usunięcie „Stary numer wpisu”

#### Automated

- [x] 1.1 Pełny Pest przechodzi — ab5e887
- [x] 1.2 Larastan przechodzi — ab5e887
- [x] 1.3 Pint czysty — ab5e887
- [x] 1.4 Front: check, types:check, test:js — ab5e887
- [x] 1.5 Brak odwołań do starej funkcji — ab5e887

#### Manual

- [x] 1.6 Panel źródła z aktem nie pokazuje sekcji „Stary numer wpisu”; numer przy powiązaniu działa — ab5e887

### Phase 2: Migracje i model

#### Automated

- [x] 2.1 Nowe testy migracji przechodzą — 78d5495
- [x] 2.2 Celowe zepsucie bezpiecznika daje czerwony test — 78d5495
- [x] 2.3 Pełny Pest przechodzi (w tym zamrożone testy migracji) — 78d5495
- [x] 2.4 Larastan przechodzi — 78d5495
- [x] 2.5 Pint czysty — 78d5495
- [x] 2.6 Migracje od zera przechodzą na pustej bazie — 78d5495

#### Manual

- [x] 2.7 Komunikat bezpiecznika jest zrozumiały na lokalnej bazie przed restore — 78d5495

### Phase 3: Lokalna baza i wydanie

#### Automated

- [ ] 3.1 Workflowy lint, static-analysis, tests, e2e zielone na PR

#### Manual

- [ ] 3.2 Lokalnie po restore z kopii produkcji migrate przechodzi, akt 2 pokazuje „nr 38”
- [ ] 3.3 Po deployu: brak sześciu kolumn, numery 38, 145, 118 na powiązaniach
- [ ] 3.4 Issue #35 zamknięte z wynikiem kontroli
