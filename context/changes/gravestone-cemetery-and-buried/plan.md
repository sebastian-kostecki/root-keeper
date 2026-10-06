# Nagrobek z cmentarzem i osobami pochowanymi (S-09) — plan implementacji

## Overview

Autor opisuje zdjęcie nagrobka cmentarzem (miejscowość ze spisu) i listą osób pochowanych. Zapis nagrobka uzupełnia puste miejsce pochowania zmarłych osób z listy cmentarzem nagrobka (zostaje edytowalne przy osobie). Picker źródła przy fakcie odnajduje nagrobek po miejscowości cmentarza i roku i pokazuje cmentarz tam, gdzie akt pokazuje parafię. Panel osoby prowadzi do jej nagrobka i przy edycji Zgonu z pustym pochowaniem wstępnie ustawia cmentarz. Domyka FR-002 i część „cmentarz” FR-004 (roadmapa S-09).

## Current State Analysis

- Cmentarza nie ma w kodzie. Źródło ma `parish_id` (tylko akt) i `archive` (wszystkie rodzaje) — `app/Models/Source.php:37,79`. Brak relacji osoba↔źródło poza `event_source`.
- Pola zależne od rodzaju: walidacja wycina je `Rule::excludeIf` (`app/Concerns/SourceDescriptionValidationRules.php:28-34`), `UpdateSource` zeruje je przy innym rodzaju (`app/Actions/Sources/UpdateSource.php:31-34`), a potwierdzenie zmiany rodzaju zna tylko pola aktu: `SourceUpdateRequest::wouldEraseRecordFields()` (`app/Http/Requests/SourceUpdateRequest.php:65-73`) i `erasedRecordFields` (`resources/js/lib/source-description.ts:46-96`).
- `SourceResource.locality_name` = siedziba parafii (`app/Http/Resources/SourceResource.php:66`); docblock (:19) rezerwuje go dla cmentarza S-09. Filtr pickera `filterSourcesByPlaceAndYear` (`resources/js/lib/source-place-year-filter.ts:11`) czyta tylko `parish_name`, `locality_name`, `year` — nagrobek z `locality_name` = cmentarz odnajdzie się bez zmiany filtra.
- `SourceResource` budują trzy miejsca, każde ładuje `parish.locality`: `availableSources` (`app/Http/Middleware/HandleInertiaRequests.php:67-69`), lista `sources` i panel `sourcePanel` (`app/Http/Controllers/SourceController.php:44-45,65`).
- Parafia źródła widoczna w trzech miejscach: `formatSourceMeta` (kafel pickera, `source-description.ts:9-17`), `formatSourceSummary` (karta galerii, `source-description.ts:145-165`), kolumna „Parafia” tabeli (`resources/js/components/sources/sources-table.tsx:98,168-179`).
- Pochowanie to wydarzenie `EventType::Burial` bez daty (`Person::burial()`, `app/Models/Person.php:114-116`), istniejące tylko przy zgonie: zapis osoby to pełne zastąpienie, a brak zgonu usuwa pochowanie (`app/Actions/Persons/UpdatePerson.php:65-79`). Zgon bez daty jest dozwolony (`CreatePerson.php:57-65`). `is_deceased` = istnieje wiersz zgonu (`PersonFormResource.php:49`).
- Formularz Zgonu: `DeathFields` (`resources/js/components/persons/person-section-fields.tsx:178-231`) z `LocalityPicker` `burial.locality_id` (:220-226); karta Zgon w `overview-tab.tsx:437-479` renderuje się tylko przy `person.death`.
- Wybór osoby: `PickOrCreatePersonDialog` (`resources/js/components/tree/pick-or-create-person-dialog.tsx`) jest związany z `router.post` relacji drzewa; generyczna `PersonCandidateList` (`resources/js/components/persons/person-candidate-list.tsx:12-51`) i prop `personCandidates` (`app/Actions/Trees/ListPersonCandidates.php`) są dostępne tylko w `TreeController`.
- Formularz źródła: Inertia `<Form>` z niekontrolowanymi i ukrytymi inputami (`source-panel.tsx:245-252,417-447`), pola aktu w `source-description-fields.tsx` (ukryte klasą `hidden` dla innych rodzajów). `LocalityPicker` (`resources/js/components/localities/locality-picker.tsx`) przyjmuje opcjonalne `options` — panel źródła ma już `localities` w `panelOptions()` (`SourceController.php:87-88`).
- Usuwanie źródła: `DeleteSource` (`app/Actions/Sources/DeleteSource.php:17-26`) — `event_source` kaskadą; dialog listuje `confirms` (`delete-source-dialog.tsx:57-81`).
- Kopia zapasowa: tabele z `information_schema`, ale `tests/Backup/BackupRestoreTest.php:25-27` wymaga ≥1 wiersza w każdej tabeli — seed w `arrangeBackupData()` (`tests/BackupTestCase.php:~127-177`).
- Kasowanie konta: kaskada `tree_id`; FK między tabelami drzewa są `NO ACTION` (sprawdzane na końcu instrukcji) — `2026_10_05_114945_add_parish_and_archive_to_sources_table.php:18-20`.

## Desired End State

- Panel źródła rodzaju „zdjęcie nagrobka” ma pola „Cmentarz” (picker spisu miejscowości z „Dodaj miejscowość „X””) i „Osoby pochowane” (lista z linkami do osób, „Dodaj osobę” z wyborem z drzewa, „Usuń” przy każdej). Lista zapisuje się razem z formularzem przyciskiem „Zapisz”.
- Każdy zapis nagrobka z cmentarzem ustawia cmentarz jako miejsce pochowania każdej osobie z listy, która ma zgon i nie ma miejsca pochowania. Przykład: nagrobek „Luboml”, lista Jan (zgon, bez pochowania), Maria (zgon, pochowanie „Chełm”), Anna (bez zgonu) → Jan ma „Luboml”, Maria „Chełm”, Anna bez zgonu i pochowania. Usunięcie z listy, zmiana cmentarza czy zmiana rodzaju nie cofa ustawionych miejsc.
- Picker źródła przy fakcie z filtrem „Miejsce: Luboml”, „Rok: 1950” pokazuje nagrobek z cmentarzem Luboml i rokiem 1950; kafel, karta galerii i tabela pokazują „cmentarz Luboml” w miejscu parafii.
- Panel osoby z listy pokazuje „Nagrobek: <nazwa źródła> (cmentarz Luboml)” jako link do panelu źródła — w karcie Zgon, a gdy osoba nie ma zgonu, w Przeglądzie. Edycja Zgonu z pustym pochowaniem (także „Oznacz jako zmarłą”) ustawia wstępnie „Miejsce pochowania” na cmentarz nagrobka — tylko gdy nagrobki osoby mają dokładnie jeden wspólny cmentarz.
- Zmiana rodzaju nagrobka z cmentarzem lub pochowanymi pyta o potwierdzenie i po nim usuwa cmentarz i listę. Usunięcie nagrobka pokazuje pochowanych i usuwa tylko listę (miejsca pochowania zostają). Cudzy cmentarz lub osoba → błąd walidacji bez zapisu.

Weryfikacja: Pest (`GravestoneTest`, `SourcesTest`, backup), Vitest, E2E `gravestone-cemetery-and-buried.spec.ts` oraz cała suita.

### Key Discoveries:

- `locality_name` jest jedynym wejściem filtra miejsca — cmentarz wpięty w ten klucz daje FR-004 bez zmiany `filterSourcesByPlaceAndYear` (`source-place-year-filter.ts:23-39`).
- `excludeIf` + zerowanie w `UpdateSource` to wzorzec pól rodzaju — cmentarz i lista idą tą samą drogą (`SourceDescriptionValidationRules.php:28-34`, `UpdateSource.php:31-34`).
- Pochowanie bez wiersza zgonu znika przy następnym zapisie osoby (`UpdatePerson.php:76-79`) — stąd reguła „tylko osoby ze zgonem”.
- `SourcesTest.php:51` sprawdza dokładną listę kluczy `SourceResource`; `WriteTargetsTest` sprawdza `rowDiff` zapisów — zapis nagrobka będzie dotykał też `events`/`event_participants`.

## What We're NOT Doing

- Tworzenie nowej osoby z poziomu nagrobka — tylko wybór istniejących (decyzja autora).
- Podpinanie nagrobka jako źródła zgonu przy dodaniu do listy — zgon potwierdza się pickerem przy fakcie jak dziś (decyzja autora; FR-025 bez zmian).
- Oznaczanie zgonu osobie z listy; nadpisywanie istniejącego miejsca pochowania; cofanie ustawionych miejsc po usunięciu z listy, zmianie cmentarza, rodzaju lub usunięciu nagrobka.
- Nazwa/rodzaj cmentarza poza miejscowością (np. „cmentarz parafialny”) — cmentarz = miejscowość ze spisu (FR-002); dopisek autor robi w nazwie lub notatce.
- Kolejność/pozycja na nagrobku, daty z inskrypcji, numer kwatery.
- Filtry i siatka listy `/sources` (S-10); scalanie i usuwanie miejscowości z przepięciem cmentarzy (S-11).
- Zmiana filtra pickera lub dzielenia „Pasujące akty / Pozostałe” — nagrobek zostaje w „Pozostałe źródła”.
- Wpis o nagrobku w dialogu usuwania osoby (lista znika kaskadą; źródło zostaje).

## Implementation Approach

Backend najpierw: schemat (FK `sources.cemetery_locality_id` + tabela `buried_people`), ścieżka zapisu przez istniejący trait walidacji i akcje źródła, uzupełnianie pochowania w osobnej akcji wywoływanej w tej samej transakcji. Potem odczyt: `locality_name` nagrobka = cmentarz, lista pochowanych w panelu, nagrobki i podpowiedź cmentarza w zasobach osoby. Frontend w dwóch fazach: panel źródła i lista, potem panel osoby. Na końcu E2E przez `/10x-e2e`. Fazy 1–3 wdrażać razem (backend bez UI nie psuje istniejących formularzy, ale nie daje funkcji).

## Critical Implementation Details

- **Pełne zastąpienie listy:** `buried_person_ids` dla nagrobka to stan docelowy — brak klucza = pusta lista (formularz nie wysyła ukrytych inputów przy zerze osób). Dla innych rodzajów klucz jest wycinany, a `UpdateSource` odpina wszystkich.
- **Kolejność w transakcji:** synchronizacja listy i zapis cmentarza przed uzupełnieniem pochowania; akcja uzupełniająca ładuje `events` osób świeżo (`Person::burial()`/`death()` czytają załadowaną kolekcję).
- **Dirty-state formularza:** Inertia `<Form>` zapamiętuje FormData przy montowaniu i porównuje je tylko przy zdarzeniach `input`/`change`/`reset` na formularzu — bez MutationObservera (`@inertiajs/react` `dist/index.js:982-1005`). Samo dodanie/usunięcie ukrytego inputu nie wyzwala `isDirty`. `SuggestField` rozwiązuje to `useEffect` na `[value]`, który wysyła `change` z ukrytego inputu (`suggest-field.tsx`). Po usunięciu ostatniej osoby nie ma już inputu, z którego dałoby się wysłać zdarzenie, więc `BuriedPeopleField` wysyła je z elementu zamontowanego zawsze (np. kontener listy).

## Faza 1: Backend — model i zapis

### Overview

Schemat, relacje, walidacja w drzewie, zapis cmentarza i listy, uzupełnianie pochowania i potwierdzenie zmiany rodzaju.

### Changes Required:

#### 1. Migracja

**File**: `database/migrations/<ts>_add_cemetery_and_buried_people_to_sources.php` (`vendor/bin/sail artisan make:migration`)

**Intent**: Dodać cmentarz nagrobka i listę pochowanych. Migracja tylko schematu — nie przenosi istniejących danych, więc bez osobnego testu migracji.

**Contract**: `sources.cemetery_locality_id` nullable `foreignId` → `localities` z domyślnym `NO ACTION` (komentarz jak w `2026_10_05_114945…:18-20`). Tabela `buried_people`: `id`, `source_id` (`cascadeOnDelete`), `person_id` (`cascadeOnDelete`), `timestamps`, unikalne `(source_id, person_id)`, indeks `person_id`. `down()` usuwa tabelę i `dropConstrainedForeignId`.

#### 2. Modele

**File**: `app/Models/Source.php`, `app/Models/Person.php`, `app/Models/Locality.php`

**Intent**: Relacje cmentarza i pochowanych w obie strony.

**Contract**: `Source::cemetery(): BelongsTo<Locality>` (`cemetery_locality_id` w fillable); `Source::buriedPeople(): BelongsToMany<Person>` przez `buried_people`, `withTimestamps`, sortowane po `buried_people.created_at`, `buried_people.id`; `Person::gravestones(): BelongsToMany<Source>`; `Locality::cemeterySources(): HasMany<Source>` (potrzebne S-11 do liczenia odwołań).

#### 3. Walidacja

**File**: `app/Concerns/SourceDescriptionValidationRules.php`

**Intent**: Cmentarz i lista tylko dla nagrobka, ograniczone do drzewa użytkownika.

**Contract**: `cemetery_locality_id`: `excludeIf(kind ≠ gravestone)`, `nullable|integer|exists:localities,id` z `tree_id`. `buried_person_ids`: `excludeIf(kind ≠ gravestone)`, `nullable|array`; `buried_person_ids.*`: `integer|distinct|exists:people,id` z `tree_id`. Etykiety: „cmentarz”, „osoby pochowane”.

#### 4. Akcje źródła i uzupełnianie pochowania

**File**: `app/Actions/Sources/StoreSource.php`, `app/Actions/Sources/UpdateSource.php`, nowy `app/Actions/Sources/FillBurialPlacesFromGravestone.php`

**Intent**: Zapis cmentarza i listy jako pełnego zastąpienia, potem uzupełnienie pochowania — w jednej transakcji.

**Contract**:
- `StoreSource`/`UpdateSource` wyjmują `buried_person_ids` z opisu i robią `buriedPeople()->sync(ids)` (dla nagrobka; brak klucza = `[]`). `UpdateSource` dla rodzaju ≠ gravestone zeruje `cemetery_locality_id` i odpina wszystkich (obok obecnego zerowania pól aktu).
- `FillBurialPlacesFromGravestone::handle(Source $gravestone): void` — nic, gdy rodzaj ≠ gravestone lub brak cmentarza; inaczej dla każdej osoby z listy z `death() !== null` i `burial() === null` tworzy wydarzenie `EventType::Burial` w drzewie źródła z `locality_id` = cmentarz i uczestnikiem `ParticipantRole::Subject` (jak `CreatePerson::createEvent`, `CreatePerson.php:74-82`). Nie dotyka istniejących pochowań ani osób bez zgonu. Wywoływana na końcu `StoreSource` i `UpdateSource`.
- Transakcja (dziś żadna z akcji jej nie ma): `StoreSource` opakowuje `create` + `sync` + `FillBurialPlacesFromGravestone` w `DB::transaction` **wewnątrz** istniejącego `try`, aby `catch` nadal kasował zapisany plik i miniaturę; `UpdateSource` opakowuje `update` + `sync` + uzupełnianie w `DB::transaction`.

#### 5. Potwierdzenie zmiany rodzaju

**File**: `app/Http/Requests/SourceUpdateRequest.php`

**Intent**: Zmiana rodzaju nagrobka z cmentarzem lub listą wymaga potwierdzenia jak dziś pola aktu.

**Contract**: `wouldEraseRecordFields()` zwraca też `true`, gdy zapisany rodzaj to gravestone, nowy jest inny i `cemetery_locality_id !== null` lub lista pochowanych niepusta. Komunikat błędu `kind` uogólniony: „Zmiana rodzaju usunie pola opisu tego rodzaju. Potwierdź zmianę.” (zaktualizować asercje w `SourcesTest.php:635-690`). Ten sam `discard_record_fields` potwierdza.

#### 6. Testy

**File**: nowy `tests/Feature/GravestoneTest.php`, `tests/BackupTestCase.php`, `tests/Feature/WriteTargetsTest.php`

**Intent**: Pokryć zapis, granice drzewa i regułę pochowania na przypadkach brzegowych.

**Contract**: przypadki — nagrobek zapisuje cmentarz i listę (kolejność dodania); edycja zastępuje listę, brak klucza czyści; akt ignoruje cmentarz i listę; cudzy cmentarz / cudza osoba → błąd walidacji, `rowDiff` pusty; reguła Jan/Maria/Anna z „Desired End State”; cmentarz dopisany przy późniejszym zapisie uzupełnia osobę dodaną wcześniej; usunięcie z listy i zmiana cmentarza nie zmieniają pochowania; zmiana rodzaju bez potwierdzenia → błąd `kind`, z potwierdzeniem → cmentarz `null`, lista pusta, pochowanie zostaje; usunięcie źródła i usunięcie osoby czyszczą `buried_people`; wyjątek w uzupełnianiu pochowania przy dodawaniu nagrobka → brak wiersza `sources`, `buried_people` i plików na dysku; cudze źródło w `update` → 404. `arrangeBackupData()` zasiewa cmentarz i wiersz `buried_people` bez nowego źródła: przestawia `akt-2` przez `sources.update` na gravestone z cmentarzem `$seat` i pochowanym `$parent` (liczniki źródeł `BackupTestCase.php:173` = 2 i `BackupRestoreTest.php:45` = 3 zostają bez zmian). Nowy przypadek `WriteTargetsTest`: zapis nagrobka dotyka tylko `sources`, `buried_people`, `events`, `event_participants` właściwych wierszy.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w górę i w dół: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1` + ponowne `migrate`
- Testy nagrobka przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneTest.php`
- Testy źródeł, zapisów i kopii przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/WriteTargetsTest.php tests/Backup`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 2: Backend — odczyt

### Overview

Cmentarz i pochowani w `SourceResource`, opcje osób na stronie źródeł, nagrobki i podpowiedź cmentarza po stronie osoby.

### Changes Required:

#### 1. `SourceResource` i eager-loading

**File**: `app/Http/Resources/SourceResource.php`, `app/Http/Middleware/HandleInertiaRequests.php:67-69`, `app/Http/Controllers/SourceController.php:44-45,65`

**Intent**: Nagrobek niesie cmentarz; `locality_name` staje się miejscowością źródła dla obu rodzajów, dzięki czemu filtr pickera działa bez zmian.

**Contract**: nowe pola `cemetery_locality_id: int|null`, `cemetery_name: string|null`, `buried_people` (`whenLoaded`: `list<{id, display_name}>`). `locality_name` = `cemetery?->name` dla gravestone, `parish?->locality?->name` dla aktu (docblock zaktualizowany). Wszystkie trzy miejsca ładują `cemetery`; panel dodatkowo `buriedPeople`. `SourcesTest.php:51` (`hasAll`) i `:1209-1230` uzupełnione o przypadek nagrobka.

#### 2. Kandydaci osób na stronie źródeł

**File**: `app/Http/Controllers/SourceController.php`

**Intent**: Dialog „Dodaj osobę” potrzebuje listy osób drzewa.

**Contract**: prop `personCandidates` jako `Inertia::optional` z `ListPersonCandidates` (jak `TreeController.php:28,46`); test: brak w pełnym ładowaniu, obecny w częściowym przeładowaniu, tylko osoby z drzewa.

#### 3. Nagrobki i podpowiedź po stronie osoby

**File**: `app/Actions/Persons/BuildPersonPreview.php:31`, `app/Http/Resources/PersonResource.php`, `app/Http/Resources/PersonFormResource.php`

**Intent**: Panel osoby pokazuje nagrobki osoby i wie, jaki cmentarz podpowiedzieć przy pustym pochowaniu.

**Contract**: `BuildPersonPreview` ładuje `gravestones.cemetery`. `PersonResource.gravestones` (`whenLoaded`): `list<{id, display_name, cemetery_name}>` w kolejności dodania. `PersonFormResource.burial_suggestion` (`whenLoaded('gravestones')`, inaczej `null`): `{id, name}` miejscowości, gdy nagrobki osoby z cmentarzem mają dokładnie jeden wyróżniony cmentarz; `null` przy zerze lub kilku różnych. Testy w `GravestoneTest`: jeden cmentarz, dwa nagrobki z tym samym, dwa różne → `null`, brak nagrobków → `null`.

### Success Criteria:

#### Automated Verification:

- Testy nagrobka i źródeł przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneTest.php tests/Feature/SourcesTest.php`
- Testy osób przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 3: Frontend — panel nagrobka i lista źródeł

### Overview

Pola „Cmentarz” i „Osoby pochowane” w panelu, dialogi zmiany rodzaju i usuwania, cmentarz w kaflu, galerii i tabeli.

### Changes Required:

#### 1. Typy

**File**: `resources/js/types/source.ts`

**Intent**: Odzwierciedlić nowe pola zasobu.

**Contract**: `Source` += `cemetery_locality_id: number | null`, `cemetery_name: string | null`, `buried_people?: { id: number; display_name: string }[]`.

#### 2. Pole „Cmentarz” i lista pochowanych

**File**: `resources/js/components/sources/source-description-fields.tsx`, nowy `resources/js/components/sources/buried-people-field.tsx`, nowy `resources/js/components/persons/pick-person-dialog.tsx`

**Intent**: Pola nagrobka widoczne tylko dla rodzaju „zdjęcie nagrobka”, zmontowane i ukryte dla innych (jak pola aktu).

**Contract**:
- „Cmentarz”: `LocalityPicker` z `name="cemetery_locality_id"`, `options` = `localities` panelu + miejscowości dopisane w panelu (wspólny stan z `createdLocalities`, `source-description-fields.tsx:64-83`), `onCreated` dopisuje nową miejscowość do `createdLocalities` (dziś zasila je tylko `NewParishDialog.onLocalityCreated`, `:211`), błąd `errors.cemetery_locality_id`.
- „Osoby pochowane”: lista `PersonPreviewLink` (klik przez `onPersonLinkClick` ze strażnikiem niezapisanych zmian), „Usuń” przy każdej, ukryte inputy `buried_person_ids[]`, pusty stan „Nikt nie jest jeszcze na liście.”, błędy `buried_person_ids*`. `useEffect` na `[ids]` (z pominięciem pierwszego renderu) wysyła `new Event('change', { bubbles: true })` z zawsze zamontowanego kontenera listy, aby `<Form>` przeliczył `isDirty` także po usunięciu ostatniej osoby.
- `PickPersonDialog`: tytuł „Dodaj osobę pochowaną”, `PersonCandidateList` z `useOptionalList('personCandidates')`, `getBlockedReason` = „Już na liście”. `PersonCandidateList` ma `onSelect(candidateId)` i wymagany `processingId` (`person-candidate-list.tsx:12-27`): `onSelect` szuka `display_name` w załadowanej liście kandydatów i woła `onPick({ id, display_name })` dialogu zamiast `router.post`; `processingId = null` (nic nie jest wysyłane). Escape zamyka dialog, nie panel (jak `SuggestField`).

#### 3. Dialog zmiany rodzaju

**File**: `resources/js/lib/source-description.ts:46-96`, `resources/js/components/sources/source-panel.tsx:495-505`

**Intent**: Zmiana rodzaju nagrobka wymienia cmentarz i liczbę pochowanych, które znikną.

**Contract**: `ErasedField.field` += `'cemetery_locality_id' | 'buried_person_ids'`; `erasedRecordFields` dla gravestone → inny rodzaj zwraca „Cmentarz: Luboml” i „Osoby pochowane: N” według tej samej reguły co pola aktu: wartość zapisana liczy się, nawet gdy w formularzu ją wyczyszczono (inaczej serwer, który patrzy na stan zapisany, zwróci błąd `kind` bez dialogu do potwierdzenia). Cmentarz = wpisany w formularzu lub zapisany (`source.cemetery_locality_id`); N = liczba osób w sumie zbiorów `source.buried_people` ∪ ids z formularza.

#### 4. Usuwanie nagrobka

**File**: `resources/js/components/sources/delete-source-dialog.tsx`

**Intent**: Pokazać, kto zniknie z listy, i że miejsca pochowania zostają.

**Contract**: przy niepustym `source.buried_people` sekcja „Ten nagrobek zniknie z listy pochowanych:” z imionami i zdaniem „Miejsca pochowania tych osób zostają.”

#### 5. Cmentarz w kaflu, galerii i tabeli

**File**: `resources/js/lib/source-description.ts:9-17,145-165`, `resources/js/components/sources/sources-table.tsx:98,168-179`

**Intent**: Nagrobek pokazuje cmentarz tam, gdzie akt pokazuje parafię (FR-004 „widzi rok i parafię lub cmentarz”).

**Contract**: `formatSourceMeta` i `formatSourceSummary` dla gravestone używają „cmentarz {cemetery_name}” w miejscu `parish_label`. Nagłówek kolumny tabeli „Parafia / cmentarz”; komórka nagrobka = „cmentarz {cemetery_name}”.

#### 6. Testy Vitest

**File**: `resources/js/lib/source-description.test.ts`, `resources/js/lib/source-place-year-filter.test.ts`

**Contract**: meta/podsumowanie nagrobka z cmentarzem i bez; `erasedRecordFields` gravestone → other z cmentarzem i listą, bez nich (pusto), zapisana lista i cmentarz wyczyszczone w formularzu (nadal w wyniku), lista zapisana + nowe osoby (suma zbiorów); zaktualizować dzisiejszą asercję „gravestone → other = []” (`source-description.test.ts:153`); filtr: nagrobek z `locality_name` „Luboml” i rokiem 1950 pasuje do „lubo”/1950 (fixture rozszerzony o `kind` tylko jeśli potrzebny).

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`

#### Manual Verification:

- Nagrobek z nowym cmentarzem („Dodaj miejscowość”) i dwiema osobami zapisuje się bez utraty wpisanych pól
- Dodanie lub usunięcie osoby z listy uruchamia strażnika niezapisanych zmian przy zamknięciu panelu
- Zmiana nagrobka na „inne” pokazuje cmentarz i liczbę pochowanych w dialogu; po potwierdzeniu znikają
- Kafel pickera, karta galerii i tabela pokazują „cmentarz Luboml”

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Faza 4: Frontend — panel osoby

### Overview

Link do nagrobka w panelu osoby i wstępne ustawienie „Miejsca pochowania” cmentarzem.

### Changes Required:

#### 1. Typy osoby

**File**: `resources/js/types/person.ts`

**Contract**: `Person` += `gravestones?: { id: number; display_name: string; cemetery_name: string | null }[]`; `PersonForm` += `burial_suggestion?: { id: number; name: string } | null`.

#### 2. Link „Nagrobek”

**File**: `resources/js/components/persons/overview-tab.tsx:437-479`

**Intent**: Od osoby dociera się do jej nagrobka.

**Contract**: dla każdego nagrobka wiersz „Nagrobek: {display_name} (cmentarz {cemetery_name})” jako link do `SourceController.index({ query: { source: id } })` (wzorzec `sources-tab.tsx:109-115`). W trybie widoku karty Zgon, gdy `person.death`; w przeciwnym razie osobny wiersz w Przeglądzie. Bez nagrobków nic się nie pojawia.

#### 3. Wstępne ustawienie pochowania

**File**: `resources/js/components/persons/person-section-fields.tsx:178-231`, `overview-tab.tsx`

**Intent**: Przy edycji Zgonu z pustym pochowaniem picker startuje z cmentarzem nagrobka (do zmiany lub wyczyszczenia); zapis dopiero po „Zapisz”.

**Contract**: `DeathFields` dostaje opcjonalne `burialSuggestion`; wartość początkowa pickera = `burial?.locality ?? burialSuggestion`; pod polem przy użytej podpowiedzi tekst „Podpowiedź z nagrobka”. Przekazuje je tylko `overview-tab.tsx` (z `personForm.burial_suggestion`); modal tworzenia i dialogi drzewa bez zmian.
- Przyjęte ryzyko: `SectionCard` serializuje FormData przy montowaniu (`section-card.tsx:149-181`), więc podpowiedź staje się stanem „czystym” — strażnik nie ostrzega, a „Zapisz” wysyła `burial.*` (`overview-tab.tsx:65-70`). U osoby z celowo wyczyszczonym pochowaniem każda edycja Zgonu przywraca cmentarz, jeśli autor nie wyczyści pola ponownie. Chroni przed tym tylko widoczny dopisek „Podpowiedź z nagrobka” — spójne z decyzją „każdy zapis nagrobka, cała lista”.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Osoba z listy pokazuje link „Nagrobek: …”, który otwiera panel tego źródła
- „Oznacz jako zmarłą” u żyjącej osoby z nagrobka ustawia wstępnie cmentarz; wyczyszczenie pola i zapis zostawia pochowanie puste; ponowna edycja Zgonu tej osoby pokazuje cmentarz z dopiskiem „Podpowiedź z nagrobka”
- Osoba z dwoma nagrobkami o różnych cmentarzach nie dostaje podpowiedzi

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Faza 5: E2E (`/10x-e2e`)

### Overview

Jedno ryzyko przeglądarkowe: opis nagrobka w panelu (cmentarz + osoba przez dialog, ukryte inputy, strażnik) kończy się pochowaniem osoby i odnalezieniem nagrobka w pickerze przy jej zgonie.

### Changes Required:

#### 1. Spec

**File**: `tests/e2e/gravestone-cemetery-and-buried.spec.ts`

**Intent**: Zmarła osoba bez pochowania (z rokiem zgonu, unikalne nazwy z sufiksem czasu) zostaje dodana do nagrobka z cmentarzem i rokiem; po zapisie jej panel pokazuje miejsce pochowania i link „Nagrobek”, a picker źródła przy zgonie z „Miejsce” = cmentarz i rokiem zgonu pokazuje nagrobek z „cmentarz …”.

**Contract**: lokatory `getByRole`/`getByLabel`/`getByText`, bez `waitForTimeout`; setup i sprzątanie przez UI/API jak `source-found-by-place-and-year.spec.ts` (miejscowość zostaje w bazie `e2e` — brak trasy usuwania, S-11).

### Success Criteria:

#### Automated Verification:

- Nowy spec przechodzi: `npx playwright test tests/e2e/gravestone-cemetery-and-buried.spec.ts`
- Cała suita E2E przechodzi: `npx playwright test`
- Spec czerwienieje po celowym wyłączeniu uzupełniania pochowania (weryfikacja przez wprowadzenie błędu)

---

## Testing Strategy

### Unit Tests:

- Vitest: `formatSourceMeta`/`formatSourceSummary` dla nagrobka, `erasedRecordFields` gravestone → inny rodzaj, filtr miejsca dla nagrobka.

### Integration Tests:

- Pest `GravestoneTest`: zapis i zastąpienie listy, granice drzewa (walidacja, 404), reguła pochowania (zgon/brak zgonu/istniejące pochowanie, cmentarz dopisany później, brak cofania), zmiana rodzaju, kaskady usuwania, zasoby osoby (`gravestones`, `burial_suggestion`), `personCandidates`.
- `SourcesTest` (klucze, `locality_name` nagrobka), `WriteTargetsTest` (zakres zapisu), backup (seed nowej tabeli).

### Manual Testing Steps:

1. Opisz nagrobek: cmentarz nowy przez „Dodaj miejscowość”, dwie osoby (jedna zmarła bez pochowania, jedna żyjąca), zapisz.
2. Sprawdź panel zmarłej (pochowanie = cmentarz, link „Nagrobek”) i żyjącej (link w Przeglądzie, brak zgonu); oznacz żyjącą jako zmarłą i zobacz podpowiedź.
3. Przy zgonie zmarłej otwórz picker źródła — nagrobek widoczny po cmentarzu i roku.
4. Zmień rodzaj nagrobka na „inne” i usuń inny nagrobek — sprawdź treść obu dialogów.

## Performance Considerations

`buriedPeople` ładowane tylko w panelu; `cemetery` to jedno eager-loading w trzech listach. `personCandidates` ładowane na żądanie (spis drzewa jest mały z założenia).

## Migration Notes

Migracja tylko dodaje kolumnę i tabelę — bez przenoszenia danych; `down()` odwracalne (cofnięcie gubi wpisane cmentarze i listy). Produkcja robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`). Pochowania utworzone przez nagrobek są zwykłymi wydarzeniami i zostają po cofnięciu.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-09); PRD: `context/foundation/prd.md` FR-002, FR-004, FR-022
- Wzorce: `context/archive/2026-10-05-act-described-by-parish-and-archive/plan.md` (pola rodzaju, `SuggestField`), `context/archive/2026-10-05-source-found-by-place-and-year/plan.md` (filtr pickera), `context/archive/2026-10-06-fact-places-from-gazetteer/plan.md` (pochowanie, `LocalityPicker`)
- Kod: `app/Concerns/SourceDescriptionValidationRules.php:28-34`, `app/Actions/Sources/UpdateSource.php:31-34`, `app/Http/Resources/SourceResource.php:66`, `resources/js/lib/source-place-year-filter.ts:11`, `app/Actions/Persons/UpdatePerson.php:65-79`, `resources/js/components/tree/pick-or-create-person-dialog.tsx`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend — model i zapis

#### Automated

- [x] 1.1 Migracja przechodzi w górę i w dół: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1` + ponowne `migrate` — ef7d89d
- [x] 1.2 Testy nagrobka przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneTest.php` — ef7d89d
- [x] 1.3 Testy źródeł, zapisów i kopii przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/WriteTargetsTest.php tests/Backup` — ef7d89d
- [x] 1.4 Larastan przechodzi: `vendor/bin/sail composer types:check` — ef7d89d
- [x] 1.5 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent` — ef7d89d

### Phase 2: Backend — odczyt

#### Automated

- [x] 2.1 Testy nagrobka i źródeł przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneTest.php tests/Feature/SourcesTest.php`
- [x] 2.2 Testy osób przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- [x] 2.3 Larastan przechodzi: `vendor/bin/sail composer types:check`
- [x] 2.4 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

### Phase 3: Frontend — panel nagrobka i lista źródeł

#### Automated

- [ ] 3.1 Vitest przechodzi: `vendor/bin/sail npm run test:js`
- [ ] 3.2 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [ ] 3.3 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [ ] 3.4 Build przechodzi: `vendor/bin/sail npm run build`

#### Manual

- [ ] 3.5 Nagrobek z nowym cmentarzem („Dodaj miejscowość”) i dwiema osobami zapisuje się bez utraty wpisanych pól
- [ ] 3.6 Dodanie lub usunięcie osoby z listy uruchamia strażnika niezapisanych zmian przy zamknięciu panelu
- [ ] 3.7 Zmiana nagrobka na „inne” pokazuje cmentarz i liczbę pochowanych w dialogu; po potwierdzeniu znikają
- [ ] 3.8 Kafel pickera, karta galerii i tabela pokazują „cmentarz Luboml”

### Phase 4: Frontend — panel osoby

#### Automated

- [ ] 4.1 Vitest przechodzi: `vendor/bin/sail npm run test:js`
- [ ] 4.2 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [ ] 4.3 Typy przechodzą: `vendor/bin/sail npm run types:check`

#### Manual

- [ ] 4.4 Osoba z listy pokazuje link „Nagrobek: …”, który otwiera panel tego źródła
- [ ] 4.5 „Oznacz jako zmarłą” u żyjącej osoby z nagrobka ustawia wstępnie cmentarz; wyczyszczenie pola i zapis zostawia pochowanie puste; ponowna edycja Zgonu tej osoby pokazuje cmentarz z dopiskiem „Podpowiedź z nagrobka”
- [ ] 4.6 Osoba z dwoma nagrobkami o różnych cmentarzach nie dostaje podpowiedzi

### Phase 5: E2E (`/10x-e2e`)

#### Automated

- [ ] 5.1 Nowy spec przechodzi: `npx playwright test tests/e2e/gravestone-cemetery-and-buried.spec.ts`
- [ ] 5.2 Cała suita E2E przechodzi: `npx playwright test`
- [ ] 5.3 Spec czerwienieje po celowym wyłączeniu uzupełniania pochowania (weryfikacja przez wprowadzenie błędu)
