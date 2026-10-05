# Akt odnaleziony po miejscu i roku, numer wpisu przy powiązaniu (S-04) — Implementation Plan

## Overview

Przy podpinaniu źródła do faktu autor zawęża listę pickera po miejscu (miejscowość siedziby parafii aktu lub nazwa parafii) i roku (wstępnie rok faktu), wybiera akt, podaje numer wpisu i podpina. Numer wpisu przestaje być polem źródła: należy do powiązania fakt–źródło (`event_source.entry_number`), jest widoczny przy fakcie i na karcie „Potwierdza” źródła i można go edytować przy fakcie. Stary „Numer wpisu” źródła zostaje widoczny w panelu źródła tylko do odczytu, dopóki autor go nie usunie. Domyka US-01 i FR-003/FR-004 (bez cmentarza — S-09), zachowując guardrail FR-025.

## Current State Analysis

Pełna analiza: `context/changes/source-found-by-place-and-year/research.md`. Najważniejsze:

- Picker (`resources/js/components/sources/source-picker-dialog.tsx`) nie ma filtra; klik w kafel od razu wysyła `{ source_id }` (:72-86). Kafel pokazuje już „typ · rok · parafia” (`formatSourceMeta`, `resources/js/lib/source-description.ts:8-17`), a pasujące akty są pierwsze (`splitSourcesForEvent`, `resources/js/lib/source-matching.ts:7-27`).
- `availableSources` ładuje `with('parish')` (`app/Http/Middleware/HandleInertiaRequests.php:61-62`); `SourceResource` nie zna miejscowości ani osobnej nazwy parafii (`app/Http/Resources/SourceResource.php:45-68`). Miejsce źródła = `sources.parish_id → parishes.locality_id (nullable) → localities.name`.
- `event_source(id, event_id, source_id, timestamps)`, unikat `(event_id, source_id)`; relacje bez `withPivot` (`app/Models/Event.php:69-75`, `app/Models/Source.php:87-93`). Zapis powiązania jest inline w kontrolerze bez Action i bez trasy edycji (`app/Http/Controllers/EventSourceController.php:18-29`, `routes/web.php:39-40`).
- Fakt pokazuje podpięte źródła bez danych powiązania (`app/Http/Resources/EventResource.php:62-70`; `resources/js/components/persons/overview-tab.tsx:106-136`, `resources/js/components/persons/sources-tab.tsx:58-136`); karta „Potwierdza” bierze dane z `SourceController::confirms()` (:151-166), render `resources/js/components/sources/source-panel.tsx:498-536`.
- `UpdateSource` zeruje `entry_number` przy każdym zapisie (`app/Actions/Sources/UpdateSource.php:19-37`), a potwierdzenie zmiany rodzaju liczy numer jako kasowane pole (`app/Http/Requests/SourceUpdateRequest.php:38,65-72`, `resources/js/lib/source-description.ts:101-111`).

## Desired End State

- Picker ma pola „Miejsce” i „Rok” (rok wstępnie = rok faktu, jeśli znany). Lista pokazuje tylko źródła pasujące do obu aktywnych filtrów; pasujący typ aktu nadal jest pierwszy. Gdy filtr ukrywa źródła bez roku lub bez miejsca, picker mówi ile; „Wyczyść filtry” przywraca pełną listę.
- Klik w kafel zaznacza źródło; pod listą pojawia się opcjonalne „Numer wpisu” i „Powiąż” (Enter w polu też podpina). Powiązanie zapisuje numer.
- Numer jest widoczny przy fakcie (Przegląd, zakładka Źródła) i na karcie „Potwierdza” źródła jako „nr 95”, poza tekstem linku i `aria-label`. W zakładce Źródła przycisk „Numer wpisu” otwiera mały modal edycji (puste pole czyści numer).
- Formularz źródła, tabela i galeria nie znają już „Numeru wpisu”. Panel źródła z niepustym `sources.entry_number` pokazuje „Numer wpisu (stary): 47” z „Usuń” (potwierdzenie w małym modalu). Żaden zapis formularza ani zmiana rodzaju go nie zeruje.
- Każde istniejące powiązanie ma to samo `id`, `event_id`, `source_id` co przed migracją; fakt prowadzi do tego samego źródła i skanu.
- Weryfikacja: Pest (`EventSourcesTest`, `SourcesTest`, `OwnershipBoundariesTest`, `WriteTargetsTest`, nowy test migracji, backup), Vitest (filtr, opis źródła), cała suita E2E + nowy spec US-01.

### Key Discoveries:

- E2E `tests/e2e/fact-source-scan.spec.ts` linkuje przez API samym `source_id` (`tests/e2e/support/api.ts:189-200`) i opiera się na `aria-label` „Otwórz źródło …” (`tests/e2e/support/navigation.ts:69-71`) oraz tekście linku „Urodzenie — osoba” (spec :81-83) — numer musi stać obok tych tekstów i `store` musi akceptować brak numeru.
- `tests/Feature/EventSourcesTest.php:36` przypina idempotencję podwójnego powiązania — `store` dla istniejącej pary pozostaje no-opem (nie zmienia numeru).
- `tests/Feature/OwnershipBoundariesTest.php:71-121` + strażnik kompletności :142-157 — każda nowa trasa z `{param}` potrzebuje przypadków; trasa z `{event}/{source}` po jednym na parametr (wzór `events.sources.destroy` :107-116).
- `tests/Feature/WriteTargetsTest.php:342,368` — wzór `rowDiff`/`familyScene` dla tras powiązania; test-plan :99 wymaga tego dla każdej nowej trasy zapisu.
- `tests/BackupTestCase.php:168` tworzy jedyny wiersz `event_source` samym `source_id` — z numerem `tableDigests()` dowiedzie przetrwania nowej kolumny.
- `tests/e2e/source-panel-parish.spec.ts:34,62,81` używa `panel.getByLabel('Rok')` bez `exact` — w panelu źródła nie wolno dodać etykiety zawierającej „Rok”; picker to osobny dialog, więc jego „Rok” nie koliduje.
- Wzorzec predykatu „parafia lub miejscowość”: `resources/js/lib/parishes-index.ts:31-44` (`toLocaleLowerCase('pl')` + `includes`, bez zwijania diakrytyków).

## What We're NOT Doing

- Szukanie po miejscowości cmentarza nagrobka (FR-004 część „cmentarz”) — S-09.
- Automatyczne przeniesienie `sources.entry_number` na powiązania ani wstępne wypełnianie numeru w pickerze starym numerem (decyzja 2 researchu).
- Usunięcie kolumny `sources.entry_number` — późniejsza migracja (copy first, drop later).
- Edycja numeru z karty „Potwierdza” w panelu źródła — tam numer jest tylko wyświetlany.
- Wybór miejscowości ze spisu, zakres lat, tolerancja roku, zwijanie diakrytyków w filtrze.
- Filtrowanie po stronie serwera, paginacja `availableSources`, zmiana kolejności serwera.
- Filtry na liście źródeł `/sources` (S-10 — użyje tej samej funkcji filtra).
- Zmiana `store` na aktualizację numeru istniejącego powiązania.

## Implementation Approach

Najpierw dana i kontrakty po stronie serwera (pivot, zapis, serializacja), potem widoki, które je czytają, potem picker, który zapisuje numer, potem wygaszenie starego pola źródła, a na końcu E2E na gotowej funkcji. Filtr jest czystą funkcją klienta nad jedną listą `availableSources` (jak `splitSourcesForEvent`). Numer na powiązaniu to dana pivotu (`withPivot`), nie nowy model.

## Critical Implementation Details

- **Kolejność w fazie 4:** `UpdateSource` musi przestać zerować `entry_number` w tym samym kroku, w którym pole znika z formularza i walidacji — inaczej pierwszy zapis źródła skasuje stary numer (ta sama pułapka co `provenance` w S-03).
- **Strażnik E2E:** numer w UI zawsze jako osobny element obok linku; nie zmieniać `aria-label` „Otwórz źródło {display_name}” ani tekstu „{event_label} — {person}”.

## Phase 1: Numer wpisu na powiązaniu (backend)

### Overview

Kolumna `entry_number` na `event_source`, odczyt przez `withPivot`, zapis przy podpinaniu i nowa trasa edycji; serializacja przy fakcie i w „Potwierdza”.

### Changes Required:

#### 1. Migracja pivota

**File**: `database/migrations/<timestamp>_add_entry_number_to_event_source_table.php` (przez `vendor/bin/sail artisan make:migration`)

**Intent**: Dodać pustą, nullable kolumnę numeru wpisu na powiązaniu; nie przepisywać żadnego wiersza.

**Contract**: `event_source.entry_number string(50) nullable`. `down()` usuwa kolumnę; docblock mówi, że numery wpisane po wdrożeniu giną przy cofnięciu (odwracalna, ale stratna).

#### 2. Test migracji

**File**: `tests/Feature/EventSourceEntryNumberMigrationTest.php`

**Intent**: Przypiąć guardrail „każdy istniejący fakt prowadzi do tego samego źródła”: wiersze `event_source` sprzed migracji zachowują `id`, `event_id`, `source_id`, a `entry_number` jest NULL; `sources.entry_number` nietknięte; `down()` po `up()` zostawia powiązania.

**Contract**: wzór `tests/Feature/SourceArchiveMigrationTest.php` (`require database_path(...)`, `down()` → insert → `up()` → asercje).

#### 3. Relacje

**File**: `app/Models/EventSource.php` (nowy), `app/Models/Event.php`, `app/Models/Source.php`

**Intent**: Udostępnić numer powiązania po obu stronach, z typem widocznym dla Larastana (level 7, bez baseline).

**Contract**: `EventSource extends Pivot` na wzór `app/Models/EventParticipant.php` — tylko `@property int $id`, `event_id`, `source_id`, `string|null $entry_number`, `created_at`/`updated_at`. `Event::sources()` i `Source::events()` dostają `->using(EventSource::class)->withPivot('entry_number')`; generyki `BelongsToMany<Source, $this, EventSource>` i `BelongsToMany<Event, $this, EventSource>`. `using()` nie może zmienić `id` istniejących wierszy ani kształtu `rowDiff` (`WriteTargetsTest.php:342,368`). Jeśli Larastan nie wywnioskuje typu `->pivot` z generyka, odczyt przez `/** @var EventSource $pivot */`.

#### 4. Podpinanie z numerem

**File**: `app/Http/Requests/EventSourceStoreRequest.php`, `app/Actions/Events/LinkSourceToEvent.php` (nowy, katalog `app/Actions/Events/`), `app/Http/Controllers/EventSourceController.php`

**Intent**: `store` przyjmuje opcjonalny numer i zapisuje go w nowym powiązaniu; zapis przechodzi przez Action zgodnie z CLAUDE.md. Istniejąca para (fakt, źródło) zostaje bez zmian (no-op, także numer).

**Contract**: reguła `'entry_number' => ['nullable', 'string', 'max:50']`, atrybut „numer wpisu”. `LinkSourceToEvent::handle(Event $event, int $sourceId, ?string $entryNumber): void` — `syncWithoutDetaching([$sourceId => ['entry_number' => $entryNumber]])` nadpisałby pivot istniejącej pary, więc Action dołącza tylko, gdy pary nie ma: jeśli `$event->sources()->whereKey($sourceId)->exists()` → return; inaczej `attach` wewnątrz `DB::transaction(...)` (savepoint, wzór `app/Actions/Localities/StoreLocality.php:19-24`) w try/catch `UniqueConstraintViolationException` tylko na wyścig. Goły `attach` w try/catch nie wystarczy: na Postgresie złapane naruszenie przerywa otaczającą transakcję (RefreshDatabase) i `EventSourcesTest.php:36` padnie. Toast „Powiązano źródło” bez zmian.

#### 5. Edycja numeru powiązania

**File**: `routes/web.php`, `app/Http/Requests/EventSourceUpdateRequest.php`, `app/Actions/Events/UpdateEventSourceEntryNumber.php`, `app/Http/Controllers/EventSourceController.php`

**Intent**: Nowa trasa ustawia lub czyści numer istniejącego powiązania.

**Contract**: `PATCH events/{event}/sources/{source}` → `events.sources.update` → `EventSourceController::update`. Autoryzacja: `update` na fakcie i `view` na źródle (jak `destroy`), obca własność = 404. Reguła `entry_number` jak w `store` (pusty string → NULL przez middleware). Brak powiązania pary → 404 (`updateExistingPivot` zwraca 0). Toast „Zapisano numer wpisu”. Po dodaniu trasy `vendor/bin/sail artisan wayfinder:generate`.

#### 6. Serializacja

**File**: `app/Http/Resources/EventResource.php`, `app/Http/Controllers/SourceController.php` (`confirms()`), `resources/js/types/person.ts` (`LinkedSource`), `resources/js/types/source.ts` (`SourceConfirmation`)

**Intent**: Numer powiązania dociera do faktu i do karty „Potwierdza”.

**Contract**: każde źródło w `EventResource.sources` dostaje `entry_number: string|null` (z `$source->pivot`, typ `EventSource`); każdy wiersz `confirms` dostaje `entry_number: string|null` (z `$event->pivot` — `confirms()` iteruje `$source->events`, więc pivot jest na fakcie). Typy TS i PHPDoc shape uzupełnione.

#### 7. Testy backendu

**File**: `tests/Feature/EventSourcesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/WriteTargetsTest.php`, `tests/BackupTestCase.php`, `tests/Feature/PersonsTest.php` lub `SourcesTest.php` (payload)

**Intent**: Happy path i granice nowych kontraktów.

**Contract**:
- `EventSourcesTest`: podpięcie z numerem zapisuje go; bez numeru → NULL; ponowne podpięcie istniejącej pary z innym numerem nie zmienia numeru (obok istniejącego :36); `max:50`; `update` ustawia i czyści numer; `update` dla niepowiązanej pary tego samego drzewa → 404.
- `OwnershipBoundariesTest`: dwa przypadki `events.sources.update` (cudzy fakt + własne źródło; własny fakt + cudze źródło) z ciałem `['entry_number' => '1']`.
- `WriteTargetsTest`: `rowDiff` dla `events.sources.update` — zmienia się tylko `entry_number` jednego wiersza `event_source`.
- `BackupTestCase.php:168`: podpięcie z `entry_number`, żeby digest dowodził przetrwania kolumny.
- Payload: podgląd osoby niesie `entry_number` przy źródle faktu; `sources.show`/panel niesie `entry_number` w `confirms`.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Testy migracji, powiązań, własności, zapisu i backupu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/EventSourceEntryNumberMigrationTest.php tests/Feature/EventSourcesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php tests/Backup`
- Larastan przechodzi: `vendor/bin/sail bin phpstan analyse`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`
- Typy TS przechodzą: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Istniejące powiązanie w lokalnej bazie po migracji nadal otwiera to samo źródło i skan z faktu

**Implementation Note**: Po fazie i zielonych automatach zatrzymaj się na ręczne potwierdzenie.

---

## Phase 2: Numer przy fakcie i przy źródle + edycja

### Overview

„nr 95” przy fakcie i na karcie „Potwierdza”; mały modal edycji numeru w zakładce Źródła.

### Changes Required:

#### 1. Przegląd osoby

**File**: `resources/js/components/persons/overview-tab.tsx`

**Intent**: Przy podpiętym źródle faktu pokazać numer, gdy jest.

**Contract**: osobny element „nr {entry_number}” (`text-muted-foreground text-xs`) obok plakietki rodzaju, poza `<Link>`; `aria-label` linku bez zmian.

#### 2. Zakładka Źródła

**File**: `resources/js/components/persons/sources-tab.tsx`, `resources/js/components/sources/entry-number-dialog.tsx` (nowy)

**Intent**: Pokazać numer w linii meta wiersza źródła i dać edycję.

**Contract**: „nr {entry_number}” w linii z plakietką i „dodano …”. Przycisk „Numer wpisu” (`variant="ghost"`, `aria-label` „Numer wpisu źródła {display_name}”) obok „Otwórz”/„Odepnij” otwiera `EntryNumberDialog` — mały modal (`resources/js/components/ui/dialog.tsx`, wzór `unlink-source-dialog.tsx`) z polem „Numer wpisu” (wartość bieżąca), „Zapisz” i „Anuluj”; `router.patch(EventSourceController.update([eventId, sourceId]).url, { entry_number }, { preserveScroll })`, błąd `errors.entry_number` przez `InputError`, zamknięcie po sukcesie. Puste pole zapisuje NULL.

#### 3. Karta „Potwierdza”

**File**: `resources/js/components/sources/source-panel.tsx`

**Intent**: Pokazać numer przy każdym wierszu karty.

**Contract**: po `PersonPreviewLink` osobny tekst „ · nr {entry_number}” (tylko gdy niepusty); tekst linku „{event_label} — {person}” bez zmian. Dla małżeństwa oba wiersze uczestników pokazują ten sam numer (jedno powiązanie).

### Success Criteria:

#### Automated Verification:

- Typy, lint i format przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check`
- Testy Vitest przechodzą: `vendor/bin/sail npm run test:js`
- Istniejąca suita E2E przechodzi (strażnik linków): `vendor/bin/sail npx playwright test`

#### Manual Verification:

- W zakładce Źródła ustawiam, zmieniam i czyszczę numer; „nr …” pojawia się i znika w Przeglądzie, w zakładce Źródła i na karcie „Potwierdza”
- Długi numer (50 znaków) nie rozbija wiersza w zakładce Źródła ani w Przeglądzie

**Implementation Note**: Po fazie i zielonych automatach zatrzymaj się na ręczne potwierdzenie.

---

## Phase 3: Picker — filtr miejsca i roku + krok numeru

### Overview

Miejscowość i nazwa parafii w payloadzie, czysta funkcja filtra, pola „Miejsce”/„Rok” z rokiem faktu, licznik ukrytych i „Wyczyść filtry”, wybór kafla → „Numer wpisu” → „Powiąż”.

### Changes Required:

#### 1. Miejsce źródła w payloadzie

**File**: `app/Http/Resources/SourceResource.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/SourceController.php`, `resources/js/types/source.ts`, `tests/Feature/SourcesTest.php`

**Intent**: Picker potrzebuje nazwy parafii i nazwy miejscowości siedziby źródła.

**Contract**: `SourceResource` dostaje `parish_name: string|null` (`parish?->name`) i `locality_name: string|null` (miejscowość źródła — dziś siedziba parafii; docblock: S-09 dołoży cmentarz). Każde miejsce budujące `SourceResource` ładuje `parish.locality` (`HandleInertiaRequests.php:62`, `SourceController.php:44,64`) — brak N+1. `SourcesTest.php:51` (`hasAll`) uzupełniony; test payloadu: źródło z parafią z siedzibą niesie obie nazwy, bez siedziby — `locality_name` NULL.

#### 2. Funkcja filtra

**File**: `resources/js/lib/source-place-year-filter.ts`, `resources/js/lib/source-place-year-filter.test.ts`

**Intent**: Czysta funkcja do pickera i później S-10.

**Contract**: `filterSourcesByPlaceAndYear<T extends Pick<Source, 'year' | 'parish_name' | 'locality_name'>>(sources: readonly T[], filter: { place: string; year: string }): { visible: T[]; hiddenUndescribedCount: number }`.
- `place` po `trim()`; pusty = bez ograniczenia; inaczej źródło pasuje, gdy `toLocaleLowerCase('pl')` `parish_name` **lub** `locality_name` zawiera frazę (wzór `parishes-index.ts:31-44`).
- `year` po `trim()`; pusty lub nie-liczba całkowita = bez ograniczenia; inaczej `source.year === Number(year)` (rok dokładny; źródło bez roku nie pasuje).
- `visible` zachowuje kolejność wejścia (potem `splitSourcesForEvent`).
- `hiddenUndescribedCount` = liczba ukrytych źródeł, którym brakuje danej aktywnego filtra (brak roku przy aktywnym roku albo brak `parish_name` i `locality_name` przy aktywnym miejscu).
- Testy: puste filtry; fraza w miejscowości; fraza w nazwie parafii; wielkość liter („luboml”); rok dokładny vs inny rok; brak roku ukryty i policzony; brak miejsca ukryty i policzony; źródło z innym rokiem ukryte, ale niepoliczone; kolejność zachowana.

#### 3. Picker

**File**: `resources/js/components/sources/source-picker-dialog.tsx`, `resources/js/components/persons/overview-tab.tsx`, `resources/js/components/persons/sources-tab.tsx`

**Intent**: Filtry i krok numeru zgodnie z decyzjami.

**Contract**:
- Nowy prop `eventYear: number | null` (z `event.date_parts.year`) przekazywany z obu wejść.
- Przy każdym otwarciu: `place = ''`, `year = eventYear ?? ''`, brak zaznaczenia, pusty numer.
- Między opisem a listą: pola z etykietami „Miejsce” i „Rok” (`inputMode="numeric"`). Lista = `splitSourcesForEvent(filterSourcesByPlaceAndYear(...).visible, eventType)` z dotychczasowymi sekcjami.
- Gdy filtr aktywny: przycisk „Wyczyść filtry”; gdy `hiddenUndescribedCount > 0`: tekst „Ukryto {N} {źródło|źródła|źródeł} bez roku lub miejsca” (odmiana przez `pluralPhrase` z `resources/js/lib/plural.ts`); gdy `visible` puste przy niepustej liście drzewa: „Żadne źródło nie pasuje do filtrów.” + „Wyczyść filtry”. Pusty stan drzewa i błąd ładowania bez zmian.
- Klik w kafel zaznacza (`aria-pressed`, wyróżnienie ramką), drugi klik w ten sam kafel odznacza; kafle „Już powiązane” nadal wyłączone.
- Po zaznaczeniu pod listą: „Wybrane: {display_name}”, pole „Numer wpisu” (opcjonalne, autofocus) i przycisk „Powiąż”; Enter w polu wysyła. `router.post(store(eventId).url, { source_id, entry_number }, { preserveScroll })`; błędy `errors.source_id` i `errors.entry_number`; sukces zamyka dialog.
- Szerokość dialogu może wzrosnąć (`sm:max-w-2xl`), jeśli pola nie mieszczą się w jednym wierszu.

### Success Criteria:

#### Automated Verification:

- Testy filtra i Vitest przechodzą: `vendor/bin/sail npm run test:js`
- Testy payloadu źródła przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/PersonsTest.php`
- Typy, lint i format przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check`
- Larastan przechodzi: `vendor/bin/sail bin phpstan analyse`

#### Manual Verification:

- Przy fakcie z rokiem picker otwiera się z tym rokiem; „Wyczyść filtry” pokazuje wszystkie źródła; licznik ukrytych zgadza się z liczbą źródeł bez roku
- „Luboml” znajduje akt po miejscowości siedziby i po nazwie parafii; pasujący typ aktu jest pierwszy
- Zaznaczenie → numer → Enter podpina i zamyka picker; numer widać przy fakcie
- Przy fakcie bez roku pole „Rok” jest puste i lista pełna

**Implementation Note**: Po fazie i zielonych automatach zatrzymaj się na ręczne potwierdzenie.

---

## Phase 4: Stary „Numer wpisu” tylko do odczytu

### Overview

Pole znika z formularza, walidacji, tabeli i galerii; wartość jest nietykalna poza jawnym „Usuń”.

### Changes Required:

#### 1. Zapis źródła nie dotyka starego numeru

**File**: `app/Concerns/SourceDescriptionValidationRules.php`, `app/Actions/Sources/UpdateSource.php`, `app/Actions/Sources/StoreSource.php` (PHPDoc), `app/Http/Requests/SourceUpdateRequest.php`

**Intent**: Usunąć `entry_number` z reguł i atrybutów opisu; `UpdateSource` nie ustawia go ani domyślnie, ani przy zmianie rodzaju; potwierdzenie zmiany rodzaju liczy tylko typ aktu i parafię.

**Contract**: `entry_number` znika z tablicy domyślnej i gałęzi `kind !== MetricalRecord` w `UpdateSource` oraz z PHPDoc shape; `wouldEraseRecordFields` sprawdza tylko `record_type` i `parish_id`; komunikat: „Zmiana rodzaju usunie typ aktu lub parafię. Potwierdź zmianę.” Przesłane `entry_number` w żądaniu jest ignorowane (nie ma go w `validated()`).

#### 2. Usuwanie starego numeru

**File**: `routes/web.php`, `app/Http/Controllers/SourceLegacyEntryNumberController.php`, `app/Actions/Sources/ClearLegacyEntryNumber.php`

**Intent**: Jedyna droga do skasowania starej wartości.

**Contract**: `DELETE sources/{source}/legacy-entry-number` → `sources.legacy-entry-number.destroy`; `Gate::authorize('update', $source)` (obcy = 404); Action ustawia `sources.entry_number = NULL`; toast „Usunięto stary numer wpisu”; redirect back. Brak Form Requestu (brak ciała), jak `EventSourceController::destroy`. `wayfinder:generate`.

#### 3. Payload i typy

**File**: `app/Http/Resources/SourceResource.php`, `resources/js/types/source.ts`

**Intent**: Nazwa pola mówi, że to stara wartość źródła, nie numer powiązania.

**Contract**: klucz `entry_number` → `legacy_entry_number` w `SourceResource` i typie `Source`; `SourcesTest.php:51` uzupełniony.

#### 4. UI źródła

**File**: `resources/js/components/sources/source-description-fields.tsx`, `resources/js/components/sources/source-panel.tsx`, `resources/js/components/sources/legacy-entry-number.tsx` (nowy), `resources/js/components/sources/sources-table.tsx`, `resources/js/lib/source-description.ts`, `resources/js/lib/source-description.test.ts`, `resources/js/lib/sources-index.test.ts`

**Intent**: Pole znika z formularza, kolumna „Nr wpisu” z tabeli, „nr X” z karty galerii; panel pokazuje starą wartość do odczytu z „Usuń”.

**Contract**:
- `source-description-fields.tsx`: bez pola „Numer wpisu”; `SourceFormData` bez `entry_number`.
- `source-description.ts`: `ErasedField.field` bez `'entry_number'`, `erasedRecordFields` bez gałęzi numeru, `isUndescribed` i `formatSourceSummary` bez numeru; testy zaktualizowane.
- `sources-table.tsx`: bez kolumny „Nr wpisu”.
- `LegacyEntryNumber` w kolumnie formularza panelu edycji (pod polami opisu, przed „Potwierdza”), renderowany gdy `legacy_entry_number !== null`, dla każdego rodzaju: „Numer wpisu (stary): {value}”, krótka podpowiedź „Przenieś go do powiązania przy fakcie, potem usuń.” i przycisk „Usuń” → mały modal „Usunąć stary numer wpisu?” z wartością, „Anuluj” / „Usuń” (`variant="destructive"`) → `router.delete(...)` z `preserveScroll`. Etykieta nie zawiera słowa „Rok” (lokator `source-panel-parish.spec.ts`). Element nie jest polem `<Form>` (nie wpływa na dirty-state).

#### 5. Testy

**File**: `tests/Feature/SourcesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/WriteTargetsTest.php`

**Intent**: Przypiąć nietykalność i jedyną drogę usunięcia.

**Contract**:
- Zapis aktu ze starym numerem (z i bez `entry_number` w ciele) zostawia `sources.entry_number`.
- Zmiana aktu na inny rodzaj: bez typu i parafii nie wymaga potwierdzenia mimo starego numeru, a numer zostaje; z typem/parafią — potwierdzenie bez wzmianki o numerze.
- `sources.legacy-entry-number.destroy` czyści wartość; obce źródło → 404 (przypadek `withoutBody` w `OwnershipBoundariesTest`); `rowDiff` — zmienia się tylko `sources.entry_number` jednego wiersza.
- Istniejące testy `SourcesTest` dotyczące `entry_number` (research: :531-669) przepisane na nowe zachowanie, nie usunięte.

### Success Criteria:

#### Automated Verification:

- Testy źródeł, własności i zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/SourceMigrationTest.php`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Typy, lint i format przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check`
- Larastan i Pint czyste: `vendor/bin/sail bin phpstan analyse && vendor/bin/sail bin pint --dirty --format agent`
- Brak `entry_number` w formularzu źródła i tabeli: `grep -n "entry_number" resources/js/components/sources/source-description-fields.tsx resources/js/components/sources/sources-table.tsx` nie zwraca nic

#### Manual Verification:

- Źródło demo z numerem „47” (`TreeViewDemoSeeder`) pokazuje „Numer wpisu (stary): 47”; zapis formularza i zmiana rodzaju go nie kasują; „Usuń” po potwierdzeniu go kasuje
- Tabela i galeria źródeł nie pokazują numeru

**Implementation Note**: Po fazie i zielonych automatach zatrzymaj się na ręczne potwierdzenie.

---

## Phase 5: E2E US-01

### Overview

Jeden nowy spec przez `/10x-e2e` dla ryzyka „autor odnajduje akt po miejscu i roku i podaje numer wpisu”, plus cała suita na zielono (bramka test-plan :86).

### Changes Required:

#### 1. Spec US-01

**File**: `tests/e2e/source-found-by-place-and-year.spec.ts`, `tests/e2e/support/api.ts`

**Intent**: Przeglądarka sprawdza filtr wstępnie wypełniony rokiem, wybór, krok numeru i widoczność numeru przy fakcie i przy źródle.

**Contract**: setup przez API (`support/api.ts`): osoba z urodzeniem w roku R, parafia z siedzibą „{miejscowość} {suffix}” (trasy `localities.inline.store`/`parishes.inline.store` albo istniejące helpery), akt urodzenia z tą parafią i rokiem R, drugi akt z innym rokiem (musi zniknąć z listy). `createSource` może dostać opcjonalne `year`, `parish_id`, `record_type`. Kroki w UI: zakładka Źródła → „Powiąż źródło” → pole „Rok” ma R → wpisanie miejscowości → widoczny tylko pasujący akt → klik → „Numer wpisu” 95 → „Powiąż” → przy fakcie „nr 95” → panel źródła → „Potwierdza” zawiera „nr 95”. Wyrocznią są dane (numer w `personPreview` przez API) oprócz widoku. Lokatory tylko role/label/text; bez `waitForTimeout`; unikalny sufiks; sprzątanie przez fixture. Celowe zepsucie (np. filtr ignorujący rok) przed uznaniem testu; `--repeat-each=2`.

### Success Criteria:

#### Automated Verification:

- Nowy spec przechodzi dwukrotnie: `vendor/bin/sail npx playwright test tests/e2e/source-found-by-place-and-year.spec.ts --repeat-each=2`
- Cała suita E2E przechodzi: `vendor/bin/sail npx playwright test`

#### Manual Verification:

- Celowe zepsucie filtra roku (lub zapisu numeru) czerwieni nowy spec; po przywróceniu jest zielony

**Implementation Note**: Po fazie i zielonych automatach zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Vitest `source-place-year-filter.test.ts`: puste filtry, fraza w miejscowości / nazwie parafii, wielkość liter, rok dokładny, licznik ukrytych bez roku/miejsca, kolejność.
- Vitest `source-description.test.ts`: brak numeru w `erasedRecordFields`, `isUndescribed`, `formatSourceSummary`.

### Integration Tests:

- Pest: migracja pivota (ciągłość powiązań), podpinanie z/bez numeru i idempotencja, edycja/czyszczenie numeru i 404 dla niepowiązanej pary, 404 innego użytkownika dla obu nowych tras, `rowDiff` obu nowych tras, nietykalność starego numeru przy zapisie i zmianie rodzaju, payloady (`entry_number` przy fakcie i w `confirms`, `parish_name`/`locality_name`, `legacy_entry_number`), backup z numerem.
- E2E: nowy spec US-01 + cała suita (zwłaszcza `fact-source-scan`, `source-panel-parish`, `source-panel-edit`).

### Manual Testing Steps:

1. Przy urodzeniu z rokiem 1908 otwórz picker; sprawdź rok, wpisz „Luboml”, wybierz akt, podaj 95, Enter.
2. Sprawdź „nr 95” w Przeglądzie, zakładce Źródła i na karcie „Potwierdza” źródła.
3. Zmień numer na 96, potem wyczyść — w zakładce Źródła.
4. Otwórz źródło demo z „47”: zapisz formularz, zmień rodzaj — numer zostaje; „Usuń” go kasuje.

## Performance Considerations

`availableSources` dokłada `parish.locality` (jedno zapytanie więcej na listę, bez N+1). Filtr działa w kliencie na liście drzewa (setki źródeł), jak dotychczasowy podział.

## Migration Notes

Migracja czysto addytywna: nullable kolumna na `event_source`, żaden wiersz nie jest przepisywany, `sources.entry_number` zostaje do późniejszej migracji usuwającej. Produkcja wykonuje `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`). Cofnięcie usuwa numery wpisane po wdrożeniu (opis w `down()`). Liczba źródeł z niepustym `entry_number` w produkcji jest nieznana — decyduje tylko o nakładzie ręcznego przenoszenia.

## References

- Research: `context/changes/source-found-by-place-and-year/research.md`
- Roadmap: `context/foundation/roadmap.md` (S-04); PRD: `context/foundation/prd.md` (FR-003, FR-004, FR-025, US-01)
- Wzorzec testu migracji: `tests/Feature/SourceArchiveMigrationTest.php`
- Wzorzec małego modala: `resources/js/components/sources/unlink-source-dialog.tsx`
- Wzorzec predykatu miejsca: `resources/js/lib/parishes-index.ts:31-44`
- Poprzednia zmiana: `context/archive/2026-10-05-act-described-by-parish-and-archive/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Numer wpisu na powiązaniu (backend)

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony — 51f56fa
- [x] 1.2 Testy migracji, powiązań, własności, zapisu i backupu przechodzą — 51f56fa
- [x] 1.3 Larastan przechodzi — 51f56fa
- [x] 1.4 Pint czysty — 51f56fa
- [x] 1.5 Typy TS przechodzą — 51f56fa

#### Manual

- [x] 1.6 Istniejące powiązanie w lokalnej bazie po migracji nadal otwiera to samo źródło i skan z faktu — 51f56fa

### Phase 2: Numer przy fakcie i przy źródle + edycja

#### Automated

- [x] 2.1 Typy, lint i format przechodzą — 9af785f
- [x] 2.2 Testy Vitest przechodzą — 9af785f
- [x] 2.3 Istniejąca suita E2E przechodzi (strażnik linków) — 9af785f

#### Manual

- [x] 2.4 W zakładce Źródła ustawiam, zmieniam i czyszczę numer; „nr …” pojawia się i znika w Przeglądzie, w zakładce Źródła i na karcie „Potwierdza” — 9af785f
- [x] 2.5 Długi numer (50 znaków) nie rozbija wiersza w zakładce Źródła ani w Przeglądzie — 9af785f

### Phase 3: Picker — filtr miejsca i roku + krok numeru

#### Automated

- [x] 3.1 Testy filtra i Vitest przechodzą — 57af585
- [x] 3.2 Testy payloadu źródła przechodzą — 57af585
- [x] 3.3 Typy, lint i format przechodzą — 57af585
- [x] 3.4 Larastan przechodzi — 57af585

#### Manual

- [x] 3.5 Przy fakcie z rokiem picker otwiera się z tym rokiem; „Wyczyść filtry” pokazuje wszystkie źródła; licznik ukrytych zgadza się z liczbą źródeł bez roku — 57af585
- [x] 3.6 „Luboml” znajduje akt po miejscowości siedziby i po nazwie parafii; pasujący typ aktu jest pierwszy — 57af585
- [x] 3.7 Zaznaczenie → numer → Enter podpina i zamyka picker; numer widać przy fakcie — 57af585
- [x] 3.8 Przy fakcie bez roku pole „Rok” jest puste i lista pełna — 57af585

### Phase 4: Stary „Numer wpisu” tylko do odczytu

#### Automated

- [x] 4.1 Testy źródeł, własności i zapisu przechodzą — 0754514
- [x] 4.2 Vitest przechodzi — 0754514
- [x] 4.3 Typy, lint i format przechodzą — 0754514
- [x] 4.4 Larastan i Pint czyste — 0754514
- [x] 4.5 Brak `entry_number` w formularzu źródła i tabeli — 0754514

#### Manual

- [x] 4.6 Źródło demo z numerem „47” (`TreeViewDemoSeeder`) pokazuje „Numer wpisu (stary): 47”; zapis formularza i zmiana rodzaju go nie kasują; „Usuń” po potwierdzeniu go kasuje — 0754514
- [x] 4.7 Tabela i galeria źródeł nie pokazują numeru — 0754514

### Phase 5: E2E US-01

#### Automated

- [x] 5.1 Nowy spec przechodzi dwukrotnie
- [x] 5.2 Cała suita E2E przechodzi

#### Manual

- [x] 5.3 Celowe zepsucie filtra roku (lub zapisu numeru) czerwieni nowy spec; po przywróceniu jest zielony
