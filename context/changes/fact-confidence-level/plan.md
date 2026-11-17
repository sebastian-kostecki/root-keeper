# Poziom pewności A–D przy fakcie — Implementation Plan

## Overview

Każdy fakt (urodzenie, zgon, pochowanie, ślub) dostaje osobne pole „poziom pewności” A–D (A akt pierwotny, B indeks / źródło wtórne, C wskazówka, D hipoteza), niezależne od źródeł. Istniejące fakty mają poziom nieokreślony (null). Autor widzi poziom w panelach faktów i ustawia go ręcznie. Import przenosi `poziom` z bloku repo regułą trzech wartości, zamiast dopisywać go do notatki osoby (FR-018, FR-015, roadmapa S-08).

## Current State Analysis

- `App\Enums\ConfidenceLevel` (A–D, string-backed) już istnieje (`app/Enums/ConfidenceLevel.php`), bez etykiet. Używa go tylko parser importu.
- Tabela `events` nie ma kolumny poziomu (`app/Models/Event.php:36` — fillable). Fakt to wiersz `events`: urodzenie, zgon i pochowanie mają jednego uczestnika Subject, a ślub to jedno wydarzenie z dwojgiem Spouse.
- Ręczny zapis idzie przez `MapsEventInput::eventAttributesFrom()` (`app/Actions/Persons/MapsEventInput.php:19-31`), `CreatePerson`, `UpdatePerson::putEvent()` (`app/Actions/Persons/UpdatePerson.php:127-150`), `CreateMarriage`, `UpdateMarriage` (`marriageAttributesFrom`, `MapsEventInput.php:39-45`). Aktualizacja osoby to pełne zastąpienie: brak klucza czyści wartość (`resources/js/lib/person-payload.ts:136-162`).
- Pochowanie w UI to tylko wybór cmentarza w sekcji Zgon (`burial: {cemetery}` w `PersonFormResource.php:51`, `burial_place` w `PersonResource.php:59`). Nie ma karty ani daty.
- Import już waliduje `poziom` (`app/Actions/Import/ParsePersonBlock.php:212`) dla `urodzenie`, `zgon`, `pochowek` i `malzenstwa[]`, i trzyma go w `ResearchEvent::$level`. Wartość trafia tylko do bloku notatki osoby jako „poziom X” (`app/Support/Import/RepoValues.php:200`) i do licznika „poziomy pewności: N (S-08)” (`app/Console/Commands/ImportResearch.php:1184`).
- Ponowny import porównuje datę, miejsce i źródła wydarzenia regułą trzech wartości (`PlanImport::planCompared()`, `app/Actions/Import/PlanImport.php:1306-1333`; `dateItem()` :1209-1229; `ThreeWayValue::compare()`). Pamięć ostatniego importu to `repo_snapshots.values` per klucz wydarzenia (`O-0001/urodzenie`, `O-0001+O-0004/slub`), budowana z `RepoValues::event()` (`RepoValues.php:43-55`).

## Desired End State

- `events.confidence` (nullable `A|B|C|D`, cast `ConfidenceLevel`). Istniejące wiersze mają null.
- W panelu osoby (Przegląd) karty Urodzenie, Ślub i Zgon pokazują znacznik „Poziom A” obok statusu źródła oraz pole „Poziom pewności: A — akt pierwotny” w widoku faktu. Bez poziomu znacznika nie ma, a pole pokazuje „nieokreślony”. Karta Zgon pokazuje też „Poziom pewności pochowania” przy miejscu pochowania. Panel małżeństwa i nagłówki grup w zakładce Źródła pokazują ten sam znacznik.
- Formularze urodzenia, zgonu (z pochowaniem), ślubu i panelu dodawania osoby mają Select „Poziom pewności” z opcjami „nieokreślony / A — akt pierwotny / B — indeks / źródło wtórne / C — wskazówka / D — hipoteza”. Zapis jednej sekcji nie kasuje poziomu w innej.
- Import zapisuje poziom przy faktach (tworzenie, przywrócenie, nowy ślub). Ponowny import porównuje go regułą trzech wartości (zmiana, uzupełnienie, konflikt, zachowana ręczna zmiana) z kluczem pamięci `poziom`. „poziom X” znika z notatki osoby.
- Weryfikacja: testy Pest (zapis, odczyt, walidacja, import z drugim kontem), Vitest (payload), jeden spec Playwright i ręczny przegląd ze zrzutami ekranu.

### Key Discoveries:

- Wzorzec nullable enum ustawianego ręcznie: `app/Enums/MarriageForm.php` (`label()`, `options()`), prop `marriageForms` w `app/Http/Middleware/HandleInertiaRequests.php:80`, Select z wartością `none` i ukrytym polem w `resources/js/components/persons/person-section-fields.tsx:269-288`, testy `tests/Feature/MarriagesTest.php:253-361`.
- `ApplyImport::markEvent()` (`app/Actions/Import/ApplyImport.php:1617-1634`) bierze z `eventAttributesFrom()` tylko kolumny daty (`Arr::only`), więc nowy klucz `confidence` go nie dotknie. Poziom trzeba tam przekazać jawnie.
- `ApplyImport::createEvent()` (:1642-1658) i nowy ślub przez `CreateMarriage` (:336-341) budują atrybuty z `eventAttributesFrom()`. Bez jawnego `confidence` w wejściu zapiszą null.
- `writeUpdate()` rozdziela wartości planu `match` po nazwie pola (:936-946), a nieznane pole rzuca „Plan ma niewłaściwą wartość pola”. `guardPerson()` (:1114-1138) ma `match` z wyjątkiem dla nieznanego pola. Nowe pola wymagają ramion w obu miejscach.
- Wzorzec dodania pola do importu: `context/archive/2026-11-16-surname-variant-search/plan.md` (Faza 2). Brak klucza w pamięci daje uzupełnienie (gdy aplikacja pusta) albo konflikt, nigdy cichy zapis.
- Vitest zbiera tylko `resources/js/**/*.test.ts` (`vite.config.ts:47-48`). Testować da się czyste funkcje w `lib/`, nie komponenty.
- `resources/js/lib/view-tokens.test.ts:20-63` skanuje stałą listę `VIEW_FILES`. Nowy plik widoku trzeba do niej dopisać.

## What We're NOT Doing

- Poziomu na osi czasu (zakładka Wydarzenia), na liście i w tabeli osób, na kartach drzewa i przodków ani przy węźle małżeństwa.
- Kolorów per poziom (nowych tokenów `confidence-*`). Znacznik jest neutralny dla A–D.
- Poziomu przy zamieszkaniach, imionach, nazwisku i wariantach nazwiska. Tylko wydarzenia.
- Wyprowadzania poziomu ze źródeł ani ostrzeżeń typu „A bez źródła”. Poziom jest niezależny od źródeł (FR-018).
- Migracji przepisującej dane ani starych notatek. Notatki poprawia kolejny import jako zwykłą zmianę Notatki.
- Zmiany numeru `wersja` kontraktu bloku. Zmienia się tylko opis pola `poziom`.
- Zmian w seederach demo.

## Implementation Approach

Kolumna i enum są wspólne dla wszystkich ścieżek. Ręczny zapis rozszerza istniejący szew `MapsEventInput`, więc osoba, panel dodawania i małżeństwo dostają poziom w jednym miejscu, z tą samą semantyką pełnego zastąpienia co forma ślubu. Front pokazuje poziom tam, gdzie fakt ma już status źródła (slot `badge` w `SectionCard` i `SourceGroup`). Import dodaje poziom jako czwartą porównywaną wartość wydarzenia obok daty, miejsca i źródeł, z własnym kluczem pamięci. Na koniec jeden spec E2E pilnuje jedynego ryzyka niewidocznego w testach serwera: przeglądarka musi wysłać poziom każdej sekcji przy pełnym zapisie.

## Critical Implementation Details

- **Pełne zastąpienie.** `PUT persons/{id}` i `PUT marriages/{id}` traktują brak `confidence` jak null. Każda ścieżka frontu, która buduje payload (`eventToPayload`, `eventPayloadFromValues`, `personFormToPayload`, `mergeSectionIntoPayload`, `marriageFieldDefaults`, `marriageSaveRequest`) musi nieść poziom, także dla sekcji, której autor nie edytował. Dla pochowania: `burial.confidence` obok `burial.cemetery_id`.
- **Pochowanie bez cmentarza nie istnieje** (`UpdatePerson.php:68-74`). Poziom pochowania zapisuje się tylko razem z cmentarzem. Select poziomu pochowania jest wyłączony, dopóki nie wybrano cmentarza.
- **Pierwszy ponowny import po wdrożeniu.** Pamięć zaimportowanych już wydarzeń nie ma klucza `poziom`. Pusta kolumna i poziom w repo dają wtedy uzupełnienie, a inna wartość w aplikacji konflikt. Jednocześnie każda osoba, której notatka miała „poziom X”, dostaje jedną zmianę Notatki do zatwierdzenia. To zamierzone (decyzja autora). Ten sam jednorazowy efekt ma jeszcze dwa skutki, również zamierzone, wzorem wariantów nazwiska:
  - `ClassifyRepoRecords::differsFromMemory()` (:369-384) porównuje sumę kluczy z repo i z pamięci. Każdy rekord, którego usunięcie autor wcześniej zachował, a który ma poziom w repo, wraca więc raz jako pytanie o usunięcie.
  - `ImportPlan::changedSnapshots()` (:118-145) zalicza każdą pamięć wydarzenia do zmienionych (nowy klucz, nawet z null). Licznik „pamięć importu” jest raz zawyżony, a import bez zmian w repo raz prosi o zapis.

## Phase 1: Serwer — kolumna, zapis ręczny i odczyt

### Overview

Kolumna `events.confidence`, enum z etykietami, zapis przez formularze osoby i małżeństwa, odczyt w zasobach i prop z opcjami. Testy Pest.

### Changes Required:

#### 1. Migracja i model

**File**: `database/migrations/<timestamp>_add_confidence_to_events_table.php` (przez `vendor/bin/sail artisan make:migration --no-interaction`), `app/Models/Event.php`

**Intent**: Dodać nullable kolumnę poziomu bez przepisywania danych. Istniejące fakty zostają nieokreślone.

**Contract**: `events.confidence` `string(1)` nullable, po `marriage_form`. `down()` usuwa kolumnę. `Event`: `confidence` w `#[Fillable]`, cast `ConfidenceLevel::class`, phpdoc `@property ConfidenceLevel|null $confidence`. Bez `MigrationTest`, bo migracja nie rusza istniejących wierszy.

#### 2. Enum z etykietami i opcjami

**File**: `app/Enums/ConfidenceLevel.php`, `app/Http/Middleware/HandleInertiaRequests.php`

**Intent**: Jedno źródło polskich opisów poziomów dla zasobów, formularzy i importu.

**Contract**: `label(): string` → A `akt pierwotny`, B `indeks / źródło wtórne`, C `wskazówka`, D `hipoteza`. `options(): array<int, array{value: string, label: string}>` jak w `MarriageForm`. Prop współdzielony `confidenceLevels` => `ConfidenceLevel::options()` obok `marriageForms`.

#### 3. Fabryka

**File**: `database/factories/EventFactory.php`

**Intent**: Testy ustawiają poziom bez ręcznych `update`.

**Contract**: stan `confidence(ConfidenceLevel $level)`. `definition()` zostawia null.

#### 4. Walidacja

**File**: `app/Http/Requests/PersonStoreRequest.php` (reguły i atrybuty dziedziczą `PersonUpdateRequest` oraz `PersonChildStoreRequest`, `PersonParentStoreRequest` i `PersonSpouseStoreRequest`, więc poziom obejmuje też dodawanie krewnych z drzewa), `app/Http/Requests/MarriageStoreRequest.php`, `app/Http/Requests/MarriageUpdateRequest.php`

**Intent**: Przyjąć poziom dla każdego faktu i odrzucić wartość spoza A–D.

**Contract**: `birth.confidence`, `death.confidence` (z `prohibited_unless:is_deceased,1,true` jak reszta reguł zgonu), `burial.confidence` (z `prohibited_unless:is_deceased`), `marriage.confidence`. Wszystkie `['nullable', Rule::enum(ConfidenceLevel::class)]`. Polskie nazwy atrybutów: „poziom pewności urodzenia / zgonu / pochowania / ślubu”.

#### 5. Mapowanie wejścia na atrybuty

**File**: `app/Actions/Persons/MapsEventInput.php`, `app/Actions/Persons/CreatePerson.php`, `app/Actions/Persons/UpdatePerson.php`

**Intent**: Jeden szew zapisuje poziom dla urodzenia, zgonu, ślubu i pochowania, z semantyką pełnego zastąpienia (brak klucza = null), jak `marriage_form`.

**Contract**: `eventAttributesFrom()` zwraca dodatkowo `confidence` z `$date['confidence'] ?? null` (pusty tekst = null). Rozszerzyć phpdoc wejścia i wyjścia. Pochowanie w `CreatePerson` i `UpdatePerson` przekazuje `confidence` z `$data['burial']['confidence']` razem z `locality_id`. Bez cmentarza pochowania nie ma, więc poziom pochowania przepada. `CreateMarriage` i `UpdateMarriage` dostają poziom przez `marriageAttributesFrom()` bez zmian w akcjach. `attach` i `CreateSpouseForPerson` (wejście `[]`) zapisują null. `markEvent` w imporcie się nie zmienia (`Arr::only`).

#### 6. Zasoby

**File**: `app/Http/Resources/EventResource.php`, `app/Http/Resources/PersonResource.php`, `app/Http/Resources/PersonFormResource.php`

**Intent**: Front dostaje poziom i jego opis dla każdego faktu, który pokazuje lub edytuje.

**Contract**:
- `EventResource`: `confidence` (`'A'|…|null`) i `confidence_label` (`string|null`) dla każdego typu. Przechodzi do `MarriageResource` przez spread.
- `PersonResource`: `burial_confidence` i `burial_confidence_label` obok `burial_place`.
- `PersonFormResource::formEvent()`: `confidence`. `burial` → `{cemetery, confidence}`.
- Zaktualizować phpdoc kształtów.

#### 7. Testy Pest

**File**: `tests/Feature/PersonsTest.php`, `tests/Feature/MarriagesTest.php`, `tests/Feature/WriteTargetsTest.php`

**Intent**: Udowodnić zapis, odczyt, czyszczenie i walidację, wzorem testów formy ślubu.

**Contract**:
- Persons: store i update zapisują `confidence` urodzenia, zgonu i pochowania. Payload bez klucza czyści poziom. Wartość `E` jest odrzucona (zbiór danych). `burial.confidence` bez zgonu jest odrzucone. Formularz (`personForm`) i Przegląd zwracają `confidence`, `confidence_label` i `burial_confidence`. Update cudzej osoby z poziomem daje 404.
- Marriages: update zapisuje poziom, panel i Przegląd zwracają go z opisem. Brak klucza albo null resetuje do nieokreślonego. Zła wartość jest odrzucona. Store zapisuje, `attach` i spouse store zostawiają null. Cudzy ślub daje 404.
- WriteTargets: zmiana poziomu urodzenia zmienia tylko wiersz urodzenia.
- Prop `confidenceLevels` ma 4 opcje z opisami.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1` i ponownie `migrate`
- Testy osób, małżeństw i celów zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/WriteTargetsTest.php`
- Pint bez zmian po uruchomieniu: `vendor/bin/sail bin pint --dirty --format agent`
- Larastan bez nowych błędów: `vendor/bin/sail composer types:check`

**Implementation Note**: Faza nie ma kroków ręcznych (UI w Fazie 2). Po zielonej automatyce przejdź do Fazy 2.

---

## Phase 2: Frontend — wybór i wyświetlanie

### Overview

Typy, payload pełnego zapisu, Select poziomu w formularzach faktów oraz znacznik i pole w panelach faktów. Vitest i zrzuty ekranu.

### Changes Required:

#### 1. Typy

**File**: `resources/js/types/person.ts`, `resources/js/types/global.d.ts`

**Intent**: Poziom jest częścią każdego faktu w danych z serwera i w formularzu.

**Contract**: typ `ConfidenceLevel = 'A' | 'B' | 'C' | 'D'`. `PersonEvent` (i pochodne `MarriageEvent`, `SourcedPersonEvent`): `confidence: ConfidenceLevel | null`, `confidence_label: string | null`. `Person`: `burial_confidence`, `burial_confidence_label`. `PersonFormEvent`: `confidence`. `PersonFormData.burial`: `{cemetery, confidence}`. Współdzielony prop `confidenceLevels: ConfidenceLevelOption[]` (`{value, label}`).

#### 2. Payload pełnego zapisu

**File**: `resources/js/lib/person-payload.ts`

**Intent**: Każdy zapis sekcji osoby i ślubu niesie poziom każdego faktu, żeby pełne zastąpienie po stronie serwera nie wyczyściło poziomu w nieedytowanej sekcji.

**Contract**: `PartialDatePayload` (albo payload wydarzenia) z `confidence`. Poziom przenoszą `eventToPayload`, `eventPayloadFromValues` (czyta ukryte pole `{prefix}.confidence`), `personFormToPayload` (w tym `burial.confidence`), `mergeSectionIntoPayload`, `marriageFieldDefaults` i `marriageSaveRequest`. Wartość `''` i `'none'` dają null. Poza A–D też null, wzorem whitelisty `QUALIFIERS` (:65-75).

#### 3. Select poziomu w formularzach

**File**: `resources/js/components/persons/confidence-field.tsx` (nowy), `resources/js/components/persons/person-section-fields.tsx`

**Intent**: Autor ustawia poziom w każdym formularzu faktu, widząc znaczenie liter.

**Contract**: `ConfidenceField({ name, defaultValue, disabled?, label })`: kontrolowany Radix `Select` z sentinelem `none` („nieokreślony”) i ukrytym `<input name={name}>` z prawdziwą wartością, wzorem pola „Forma ślubu” (`person-section-fields.tsx:269-288`). Opcje z propu `confidenceLevels` jako „A — akt pierwotny”. Gdzie się pojawia:
- `BirthFields`: `birth.confidence`.
- `DeathFields`: `death.confidence` i „Poziom pewności pochowania” `burial.confidence`, wyłączony bez wybranego cmentarza.
- `MarriageFields`: `marriage.confidence`.

Panel dodawania osoby (`person-form-fields.tsx`) dostaje pola przez `BirthFields` i `DeathFields` bez zmian. Błędy `*.confidence` mapuje już `belongsToSection` (`overview-tab.tsx:50-72`).

#### 4. Znacznik i pole w panelach faktów

**File**: `resources/js/components/persons/confidence-badge.tsx` (nowy), `resources/js/components/persons/overview-tab.tsx`, `resources/js/components/marriages/marriage-panel.tsx`, `resources/js/components/persons/source-group.tsx`

**Intent**: Autor widzi poziom tam, gdzie fakt ma już status źródła, a w widoku faktu czyta, co znaczy litera.

**Contract**:
- `ConfidenceBadge({ level, label })`: neutralny `Badge variant="outline"` z tekstem „Poziom A” i dostępną nazwą „Poziom pewności: A — akt pierwotny”. Przy `level === null` nic nie renderuje. Tylko tokeny (`muted`, `border`, `foreground`), wzorem `event-source-status.tsx`.
- Przegląd: znacznik obok `EventSourceStatus` w slocie `badge` kart Urodzenie (:381-387), Ślub (:424-428) i Zgon (:459-465). W `FactView` pole „Poziom pewności” z wartością „A — akt pierwotny” albo „nieokreślony”. W karcie Zgon przy „Miejsce pochowania” pole „Poziom pewności pochowania”, tylko gdy jest pochowanie.
- Panel małżeństwa: znacznik w karcie „Ślub” (:168-175) i pole w widoku (:180-191).
- Zakładka Źródła: znacznik w nagłówku grupy obok `EventSourceStatus` (`source-group.tsx:51-63`).

#### 5. Skan tokenów i Vitest

**File**: `resources/js/lib/view-tokens.test.ts`, `resources/js/lib/person-payload.test.ts`

**Intent**: Nowe pliki widoku są objęte skanem tokenów, a payload nie gubi poziomu.

**Contract**:
- Dopisać `confidence-badge.tsx` i `confidence-field.tsx` do `VIEW_FILES`.
- Vitest: `personFormToPayload` niesie poziom urodzenia, zgonu i pochowania. `mergeSectionIntoPayload` dla sekcji „personal” zachowuje poziomy z `personForm`, a dla „birth” bierze nowy poziom z wartości formularza. `'none'` i `''` dają null. `marriageSaveRequest` niesie poziom.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Testy Pest z Fazy 1 nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php`

#### Manual Verification:

- W panelu osoby ustawienie poziomu B urodzenia pokazuje znacznik „Poziom B” obok statusu źródła i pole „Poziom pewności: B — indeks / źródło wtórne”. Fakt bez poziomu nie ma znacznika, a pole pokazuje „nieokreślony”.
- Zapis sekcji „Dane osobowe” po ustawieniu poziomu urodzenia i zgonu zostawia oba poziomy.
- Select poziomu pochowania jest wyłączony bez cmentarza. Z cmentarzem poziom zapisuje się i pokazuje przy miejscu pochowania.
- Panel małżeństwa: ustawienie poziomu ślubu pokazuje znacznik w karcie „Ślub” i w zakładce Źródła.
- Panel dodawania osoby zapisuje poziom urodzenia.
- Zrzuty ekranu (jasny i ciemny motyw, karta z poziomem i bez) w `context/changes/fact-confidence-level/screenshots/`.

**Implementation Note**: Po zielonej automatyce zatrzymaj się na ręczne potwierdzenie przed Fazą 3.

---

## Phase 3: Import — poziom z repo

### Overview

Import zapisuje `poziom` przy faktach i porównuje go przy ponownym imporcie regułą trzech wartości. „poziom X” znika z notatki osoby. Komenda i kontrakt bloku są aktualizowane.

### Changes Required:

#### 1. Pamięć i notatka

**File**: `app/Support/Import/RepoValues.php`

**Intent**: Poziom żyje w jednym miejscu (kolumna) i ma klucz pamięci do reguły trzech wartości.

**Contract**: `RepoValues::event()` dodaje `'poziom' => $event->level?->value` (dla każdego typu, także pochowania). `eventDetails()` (:195-204) przestaje emitować „poziom X”.

#### 2. Planowanie

**File**: `app/Actions/Import/PlanImport.php`, `app/Support/Import/PlannedMarriage.php`, `app/Support/Import/PlannedChange.php`, `app/Support/Import/PlannedFill.php`, `app/Support/Import/ValueConflict.php`, `app/Support/Import/NoteValueCounts.php`

**Intent**: Poziom to czwarta porównywana wartość wydarzenia obok daty, miejsca i źródeł.

**Contract**:
- Nowa metoda `confidenceItem()` wzorem `dateItem()`:
  - R = poziom z repo, S = `values['poziom']` z pamięci, A = `$event->confidence`; porównanie literami.
  - Bez „uściślenia”: `refines` = false.
  - Klucz zmiany `{key}/poziom`.
  - Pola `birth_confidence`, `death_confidence`, `burial_confidence` z etykietami „Poziom pewności urodzenia / zgonu / pochowania”.
  - Wynik idzie przez `itemFor()`; zachowana ręczna zmiana zwiększa `kept`.
- `planCompared()` (:1306-1333) dodaje ten element dla urodzenia, zgonu i pochowania, także dla wydarzenia bez `repo_id` (pusta pamięć).
- Biała lista `PlanImport::FIELDS` (:88, filtr :443-453) i `hasWork` (:488) obejmują `birth_confidence`, `death_confidence`, `burial_confidence`. Bez wpisu pozycja poziomu cicho wypada z planu.
- Śluby (`planMarriages()`, :1781-1803 i :1829-1831): element `marriage_confidence` („Poziom pewności ślubu”) z kluczem `{repoId}/poziom`. `plannedWedding()` (:1860-1886) rozdziela go na nowe sloty `PlannedMarriage::$confidenceFill/$confidenceConflict/$confidenceChange`. Warunek planowania znanego ślubu (:1798, dziś tylko data, miejsce albo linki) uwzględnia pozycję poziomu, żeby sama zmiana poziomu nie przepadła. `ImportPlan::fillCount()` (:98) liczy uzupełnienie poziomu ślubu.
- Unie typu `repoValue` w `itemFor()`, `PlannedChange`, `ValueConflict` i `PlannedFill` obejmują `ConfidenceLevel`. `PlannedFill::valueLabel()` zwraca literę.
- `NoteValueCounts::$levels` zostaje jako licznik poziomów przy faktach.

#### 3. Zapis

**File**: `app/Actions/Import/ApplyImport.php`

**Intent**: Poziom trafia do kolumny przy tworzeniu, przywróceniu, nowym ślubie i zatwierdzonej zmianie, a guardy wykrywają zmianę poziomu w aplikacji po planowaniu.

**Contract**:
- `createEvent()` (:1642-1658) zapisuje `confidence` z `ResearchEvent::$level`. Przywrócenia, które przez niego przechodzą, dostają poziom automatycznie.
- Nowy ślub (:336-341) przekazuje `confidence` w wejściu `CreateMarriage`.
- `markEvent()` (:1617-1634) przyjmuje opcjonalny poziom do ustawienia albo wyczyszczenia, niezależnie od daty i miejsca.
- `writeUpdate()`:
  - `match` pól (:936-946) dostaje ramię dla `*_confidence` (`null|ConfidenceLevel`).
  - Pętla wydarzeń (:964-1008) woła `markEvent`, gdy zmienia się data, miejsce albo poziom, także w gałęzi `markKey`.
- `writeMatch()` (pierwsze dopasowanie osoby, pusta pamięć, więc uzupełnienia poziomu): własny `match` pól (:1043-1052) dostaje ramię dla `*_confidence` (`null|ConfidenceLevel`), a poziom trafia do `markEvent` jak data i miejsce. Bez tego import rzuca „Plan ma niewłaściwą wartość pola”.
- Ślub istniejący (:282-333): fill, konflikt i zmiana poziomu wzorem daty i miejsca.
- `guardPerson()` (:1114-1138) dostaje ramiona `birth_confidence`, `death_confidence` i `burial_confidence` (litera albo null).
- Nowy `guardWeddingConfidence()` wywoływany tam, gdzie `guardWedding()` i `guardWeddingPlace()` w `verify()`.

#### 4. Komenda

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Podsumowanie mówi, że poziomy idą do faktów, nie do notatki.

**Contract**: usunąć linię „poziomy pewności: %d (S-08)” spod „Do notatki osoby” (:1184). Dodać linię „Poziomy pewności przy faktach: %d” obok „Warianty nazwiska przy osobach”. Zmiany, uzupełnienia i konflikty poziomu wchodzą w istniejące tabele bez nowych pytań. Wydruk uzupełnień ślubu (:1227-1236) pokazuje też uzupełnienie poziomu.

#### 5. Kontrakt bloku

**File**: `context/foundation/research-block-contract.md`

**Intent**: Autor repo wie, że poziom trafia do pola faktu i jest porównywany przy ponownym imporcie.

**Contract**: opis `poziom` (:61) i wiersz pola wydarzenia (:54) mówią, że import zapisuje go przy fakcie (urodzenie, zgon, pochowanie, ślub) i porównuje przy ponownym imporcie. Frontmatter `updated:`. Bez zmiany `wersja` (nowe zachowanie, to samo znaczenie pola).

#### 6. Testy Pest

**File**: `tests/Feature/ImportPlanTest.php`, `tests/Feature/ImportApplyTest.php`, `tests/Feature/ImportResearchCommandTest.php`, `tests/Feature/ResearchRepoReadTest.php`, fixture'y w `tests/Fixtures/research-repo/`

**Intent**: Udowodnić regułę trzech wartości, zapis i granicę własności (drugie konto z nakładającymi się danymi).

**Contract**:
- Plan:
  - Asercje „poziom X” w notatce (:255-298, :681-684) zmieniają się na brak poziomu w notatce.
  - Zmiana poziomu w repo daje zmianę. Usunięcie w repo daje zmianę na „—”. Ręczna zmiana zostaje (kept). Obie zmienione dają konflikt z „poprzednio w repo”.
  - Brak klucza `poziom` w pamięci daje uzupełnienie przy pustej kolumnie i konflikt przy innej wartości (pomocnik usuwający klucz z `repo_snapshots.values`, wzorem `replanForgetVariants`). To samo dla ślubu i pochowania.
  - Wydarzenie wpisane ręcznie z innym poziomem daje konflikt.
  - Zachowane usunięcie z poziomem w repo i bez klucza `poziom` w pamięci wraca jako pytanie o usunięcie.
- Apply:
  - Pierwszy import zapisuje poziomy z fixture'ów (urodzenie A, ślub B).
  - Pierwsze dopasowanie istniejącej osoby z poziomem w repo zapisuje uzupełnienie poziomu przez `writeMatch` (bez wyjątku).
  - Znany ślub, w którym zmienił się tylko poziom, dostaje zmianę.
  - Zatwierdzona i odrzucona zmiana poziomu. Konflikt rozstrzygnięty na repo i na aplikację. Ślub. Pamięć zapisuje `poziom`.
  - Odmowa, gdy poziom zmienił się w aplikacji po planowaniu.
  - Drugie konto bez zmian (`expectOnlyAuthorRowsChanged`).
- Komenda: zmiana poziomu w tabeli zmian (`expectsTable`) i nowa linia podsumowania. Dodać `poziom` do pochowania w jednym fixture.

### Success Criteria:

#### Automated Verification:

- Testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Feature/ImportResearchCommandTest.php tests/Feature/ResearchRepoReadTest.php tests/Unit/NoteBlockTest.php`
- Pint bez zmian po uruchomieniu: `vendor/bin/sail bin pint --dirty --format agent`
- Larastan bez nowych błędów: `vendor/bin/sail composer types:check`

#### Manual Verification:

- `vendor/bin/sail artisan import:research <konto testowe> <kopia repo>` z odpowiedzią „nie” pokazuje uzupełnienia poziomów, jednorazowe zmiany Notatki bez „poziom X” i linię „Poziomy pewności przy faktach”.
- Po odpowiedzi „tak” na koncie testowym poziomy są widoczne w panelu osoby.

**Implementation Note**: Po zielonej automatyce zatrzymaj się na ręczne potwierdzenie przed Fazą 4.

---

## Phase 4: E2E — poziom przeżywa zapis innej sekcji

### Overview

Jeden spec Playwright przez `/10x-e2e` pilnuje ryzyka pełnego zastąpienia w przeglądarce: formularz → payload → serwer → widok.

### Changes Required:

#### 1. Spec

**File**: `tests/e2e/fact-confidence-level.spec.ts`, ewentualnie `tests/e2e/support/api.ts`

**Intent**: Poziom ustawiony w jednej sekcji nie znika po zapisie innej.

**Contract**:
- Scenariusz: osoba z unikalną nazwą (sufiks timestamp), autor ustawia „B — indeks / źródło wtórne” w sekcji Urodzenie i zapisuje. Potem edytuje i zapisuje „Dane osobowe”. Po ponownym otwarciu panelu znacznik „Poziom B” i pole poziomu urodzenia nadal są widoczne.
- Lokatory `getByRole` i `getByLabel`. Bez `waitForTimeout`. Sprzątanie po teście.
- Weryfikacja przez celowe zepsucie: usunięcie `confidence` z `eventToPayload` musi dać czerwony test.

### Success Criteria:

#### Automated Verification:

- Spec przechodzi: `vendor/bin/sail npx playwright test tests/e2e/fact-confidence-level.spec.ts`
- Celowe zepsucie `eventToPayload` daje czerwony spec, a przywrócenie zielony
- Format, lint i typy przechodzą: `vendor/bin/sail npm run check` i `vendor/bin/sail npm run types:check`

---

## Testing Strategy

### Unit Tests:

- Vitest `person-payload.test.ts`: poziom w `personFormToPayload`, `mergeSectionIntoPayload` (sekcja edytowana i nieedytowana), `marriageSaveRequest`; `'none'` i `''` dają null.

### Integration Tests:

- Pest: zapis, odczyt, czyszczenie i walidacja poziomu dla osoby (urodzenie, zgon, pochowanie) i małżeństwa. Cudzy rekord daje 404. Zapis zmienia tylko swój wiersz.
- Pest import: reguła trzech wartości dla poziomu (zmiana, usunięcie, zachowana ręczna, konflikt, brak pamięci), zapis przy tworzeniu i dla ślubu, guard po planowaniu, drugie konto nietknięte, linia podsumowania.
- Playwright: poziom przeżywa zapis innej sekcji.

### Manual Testing Steps:

1. Ustaw poziom B urodzenia i poziom D zgonu, zapisz „Dane osobowe” i sprawdź oba znaczniki.
2. Wybierz cmentarz, ustaw poziom pochowania C i sprawdź pole przy miejscu pochowania. Usuń cmentarz i sprawdź, że poziomu pochowania nie ma.
3. W panelu małżeństwa ustaw poziom A i sprawdź znacznik w karcie i w zakładce Źródła.
4. Uruchom import na koncie testowym z odpowiedzią „nie” i przejrzyj uzupełnienia oraz zmiany Notatki.

## Performance Considerations

Brak: jedna kolumna w istniejących zapytaniach, bez nowych relacji i indeksów.

## Migration Notes

Nowa nullable kolumna bez kopiowania danych. Produkcja robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`). Rollback usuwa kolumnę: poziomy ustawione po wdrożeniu przepadają, a te z importu wrócą przy kolejnym imporcie (pamięć ma klucz `poziom`, więc wyjdą jako uzupełnienia).

## References

- Roadmapa: `context/foundation/roadmap.md` (S-08), PRD: `context/foundation/prd.md` (FR-018, FR-015)
- Kontrakt bloku: `context/foundation/research-block-contract.md`
- Wzorzec pola importu: `context/archive/2026-11-16-surname-variant-search/plan.md`
- Wzorzec pełnego zastąpienia i E2E: `context/archive/2026-10-23-fuzzy-event-dates/plan.md`, `tests/e2e/fuzzy-event-dates.spec.ts`
- Wzorzec nullable enum: `app/Enums/MarriageForm.php`, `tests/Feature/MarriagesTest.php:253-361`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Serwer — kolumna, zapis ręczny i odczyt

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony — 9f8bff1
- [x] 1.2 Testy osób, małżeństw i celów zapisu przechodzą — 9f8bff1
- [x] 1.3 Pint bez zmian po uruchomieniu — 9f8bff1
- [x] 1.4 Larastan bez nowych błędów — 9f8bff1

### Phase 2: Frontend — wybór i wyświetlanie

#### Automated

- [x] 2.1 Vitest przechodzi
- [x] 2.2 Format i lint przechodzą
- [x] 2.3 Typy przechodzą
- [x] 2.4 Build przechodzi
- [x] 2.5 Testy Pest z Fazy 1 nadal przechodzą

#### Manual

- [ ] 2.6 Znacznik i pole poziomu przy urodzeniu, „nieokreślony” bez poziomu
- [ ] 2.7 Zapis „Dane osobowe” zostawia poziomy urodzenia i zgonu
- [ ] 2.8 Poziom pochowania wyłączony bez cmentarza, zapisany z cmentarzem
- [ ] 2.9 Poziom ślubu w panelu małżeństwa i w zakładce Źródła
- [ ] 2.10 Panel dodawania osoby zapisuje poziom urodzenia
- [ ] 2.11 Zrzuty ekranu w screenshots/

### Phase 3: Import — poziom z repo

#### Automated

- [ ] 3.1 Testy importu przechodzą
- [ ] 3.2 Pint bez zmian po uruchomieniu
- [ ] 3.3 Larastan bez nowych błędów

#### Manual

- [ ] 3.4 Podgląd importu pokazuje uzupełnienia poziomów i zmiany Notatki
- [ ] 3.5 Poziomy z importu widoczne w panelu osoby

### Phase 4: E2E — poziom przeżywa zapis innej sekcji

#### Automated

- [ ] 4.1 Spec przechodzi
- [ ] 4.2 Celowe zepsucie eventToPayload daje czerwony spec
- [ ] 4.3 Format, lint i typy przechodzą
