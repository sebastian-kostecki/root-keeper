---
date: 2026-10-06T08:27:56+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: 0bd881e4c6f08479ddc2dfb333fa331d0b135ae7
branch: develop
repository: root-keeper
topic: "S-07 fact-places-from-gazetteer — jak dziś działają miejsca faktów i co trzeba zmienić, by wybierać je ze spisu miejscowości"
tags: [research, events, localities, gazetteer, person-side-panel, migration, suggest-field]
status: complete
last_updated: 2026-10-06
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dodano decyzje autora: pochowanie jako wydarzenie, migracja przepięcia od razu, bez notatki w S-07, podpowiedzi od 1 litery"
---

# Research: S-07 — miejsca urodzenia, ślubu, zgonu i pochowania ze spisu miejscowości

**Date**: 2026-10-06T08:27:56+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: 0bd881e
**Branch**: develop
**Repository**: root-keeper

## Research Question

Wycinek S-07 (`context/foundation/roadmap.md:201-212`, PRD FR-010, FR-022): autor wybiera miejsce urodzenia, ślubu, zgonu i pochowania ze spisu miejscowości (z podpowiedziami i dopisaniem w locie); istniejące tekstowe miejsca faktów wskazują na miejscowości ze spisu; niepewność opisuje w notatce. Pytanie badawcze: jak miejsca faktów są dziś zapisywane, walidowane i wyświetlane; co dał spis z S-01 i jakie wzorce (picker, dopisywanie w locie, migracje kopiujące) są gotowe do użycia; jakie wcześniejsze decyzje i ograniczenia wiążą S-07.

## Summary

- **Spis to `localities`, nie `places`.** Model `App\Models\Locality`, trasy `localities.*`, unikalność `(tree_id, lower(name))` (`database/migrations/2026_10_05_081630_create_localities_table.php:24`). Jedyne dzisiejsze odwołanie do miejscowości to `parishes.locality_id`.
- **Miejsce urodzenia, zgonu i ślubu to jedna kolumna tekstowa `events.place`** (`database/migrations/2026_09_23_045723_create_events_table.php:22`); ślub to wiersz `events` typu `marriage` z dwoma uczestnikami (`app/Actions/Relations/CreateMarriage.php:111-119`). `EventType` ma dokładnie trzy przypadki: Birth, Death, Marriage (`app/Enums/EventType.php:7-9`). **Miejsca pochowania nie ma w kodzie** — ani kolumny, ani typu wydarzenia.
- **S-01 nie zapisał powiązania tekst → miejscowość.** Seed (`database/migrations/2026_10_05_081632_seed_localities_from_place_texts.php:17-34`) tylko utworzył miejscowości z `events.place` i `people.residence` (grupa `lower(btrim)`, nazwa = najczęstszy wariant), nie dotykając tekstów. Plan S-01 przekazał S-07 dopasowanie po nazwie `lower(btrim)`, a brak dopasowania → nowa miejscowość (`context/archive/2026-10-05-places-gazetteer/plan.md:38-39`).
- **Ścieżka zapisu jest wąska i wspólna:** reguła `"{$prefix}.place" => nullable|string|max:255` (`app/Concerns/PartialDateValidationRules.php:26`) i mapowanie `'place' => $date['place']` (`app/Actions/Persons/MapsEventInput.php:23`) obsługują wszystkie trzy fakty we wszystkich przepływach (osoba create/update, małżeństwo create/update, sloty drzewa).
- **Picker jest gotowy:** `resources/js/components/suggest-field.tsx` w trybie `record` (ukryte pole z id, „Dodaj …”, `offerCreate='unmatched'`) plus `useHttp` → `POST localities/inline` zwracające `{id, name}` 201 (`app/Http/Controllers/InlineLocalityController.php:14-23`). Logika dopisania miejscowości jest dziś zduplikowana w `parish-panel.tsx` i `new-parish-dialog.tsx` — naturalny kandydat na wspólny komponent/hook.
- **Edycja sekcji w panelu osoby jest zgodna z `SuggestField`:** `section-card.tsx:151-182` wykrywa zmiany przez serializację `FormData` plus `input`/`change` i obserwację atrybutu `value`; zapis czyta `FormData` (`section-card.tsx:31-38`). Ale **zapis osoby to pełne zastąpienie** — nowe pole musi przejść całą ścieżkę payloadu (`resources/js/lib/person-payload.ts:55-121`), inaczej edycja innej sekcji je wyzeruje.
- **Opcje pickera trzeba dostarczyć do panelu osoby:** `BuildPersonPreview` (`app/Actions/Persons/BuildPersonPreview.php:25-34`) nie wysyła dziś miejscowości; spis jest przekazywany jako pełna lista w propsach (wzorzec `SourceController::panelOptions`, `app/Http/Controllers/SourceController.php:81-98`).
- **Rozstrzygnięcia autora (2026-10-06, sekcja Decisions):** pochowanie = wydarzenie bez daty; przepięcie tekstów migracją od razu; bez notatki w S-07; podpowiedzi od 1 litery. Pierwotne pytania: (1) pochowanie jako pole osoby czy wydarzenie bez daty; (2) mechanizm przepięcia tekstów (migracja dopasowująca + dosianie brakujących, vs dopasowanie przy zapisie); (3) „notatka” na niepewność, skoro notatka osoby (S-14) jeszcze nie istnieje; (4) próg „po kilku literach”, którego `SuggestField` nie implementuje.

## Detailed Findings

### 1. Model danych dziś

- `events.place` — `string` nullable, długość domyślna 255 (`database/migrations/2026_09_23_045723_create_events_table.php:22`), w `#[Fillable]` modelu (`app/Models/Event.php:29`). Tabela `people` nie ma kolumny miejsca faktu; ma tylko `residence` (S-08).
- Uczestnicy: `event_participants(event_id, person_id, role)`, role Subject/Spouse (`app/Enums/ParticipantRole.php`). `Person::birth()`/`death()` to `firstWhere` po załadowanych `events` (`app/Models/Person.php:95-103`), `marriages()` filtruje po typie (`:90-93`).
- Na ścieżce `CreatePerson` każda osoba dostaje wiersz urodzenia (także pusty) (`app/Actions/Persons/CreatePerson.php:56`); wiersz zgonu powstaje tylko przy `is_deceased` (`:58-60`) i jest usuwany po odznaczeniu (`app/Actions/Persons/UpdatePerson.php:144`).
- Pochowanie: grep po `burial|pochow|buried|pogrzeb|cmentarz|cemetery` w `app`, `database`, `resources/js`, `routes`, `tests`, `config` trafia tylko w `SourceKind::Gravestone` (`app/Enums/SourceKind.php:8`) i tekst seedera. Potwierdza to `context/archive/2026-10-05-places-gazetteer/research.md:29`.

### 2. Spis `localities` (S-01)

- Kolumny: `id, tree_id (cascade), name varchar(255), description text null, timestamps` (`create_localities_table.php:15-21`); indeks `UNIQUE (tree_id, lower(name))` (`:24`) — bez `btrim` i bez składania znaków diakrytycznych („Krakow” ≠ „Kraków”).
- Walidacja unikalności: closure `whereRaw('lower(name) = lower(?)')` w drzewie użytkownika (`app/Concerns/LocalityValidationRules.php:24-34`); akcje `StoreLocality`/`UpdateLocality` w `DB::transaction` zamieniają `UniqueConstraintViolationException` na błąd walidacji `name` (`app/Actions/Localities/StoreLocality.php:17-27`). Przycinanie przy zapisie HTTP robi domyślny globalny `TrimStrings` — `bootstrap/app.php` go nie wyłącza (wniosek z braku odwołania; nie testowane).
- Wzorzec walidacji FK w drzewie: `Rule::exists('localities','id')->where('tree_id', $this->user()->tree->id)` (`app/Concerns/ParishValidationRules.php:95-99`) — do użycia dla `locality_id` faktu.
- `Locality` ma relacje tylko `tree()` i `parishes()` (`app/Models/Locality.php:38-41`); brak `events()`. `LocalityPolicy` ma `view` i `update` (`app/Policies/LocalityPolicy.php:14,24`), bez `delete` (przyjdzie w S-11 — `context/archive/2026-10-05-places-gazetteer/plan.md:174`).
- Nic nie liczy odwołań do miejscowości (brak tras destroy/merge, `routes/web.php:35-39`; `LocalityResource` zwraca `{id, name, description}` — `app/Http/Resources/LocalityResource.php:21-28`; tabela spisu ma kolumny Nazwa i Opis — `resources/js/components/localities/localities-table.tsx:39-40`).

### 3. Seed S-01 i dług przekazany do S-07

- SQL seeda (`2026_10_05_081632_seed_localities_from_place_texts.php:17-34`): źródła `events.place` (wszystkie typy) i `people.residence` z `btrim(x) <> ''`; grupa `(tree_id, lower(btrim(x)))`; nazwa = najczęściej używany przycięty wariant, remis → pierwszy w `COLLATE "C"`; `ON CONFLICT (tree_id, lower(name)) DO NOTHING`; teksty nietknięte; `down()` no-op z komentarzem (`:40-45`).
- Test: `tests/Feature/LocalitySeedMigrationTest.php` — wymaga pliku migracji (`:9-14`), wstawia wiersze przez `DB::table` (`:16-35`), sprawdza grupowanie/nazwę (`:77`), teksty bajt w bajt (`:87`), idempotencję (`:98`), pomijanie istniejących (`:110`), drzewo bez tekstów (`:122`), `down` (`:130`). Ten sam zestaw przypadków (wielkość liter, spacje, puste, NULL) nadaje się jako wyrocznia dla migracji S-07.
- Ryzyka przyjęte w S-01, które przechodzą na S-07 (`context/archive/2026-10-05-places-gazetteer/plan.md:38-39`, `plan-brief.md:67-68`):
  - teksty wpisane po wdrożeniu S-01 nie są w spisie (formularz osoby nadal zapisuje tekst);
  - miejscowość przemianowana w spisie przed S-07 nie pasuje już do starego tekstu → dopasowanie po nazwie utworzy ją ponownie („Lubomla” → „Luboml”). PRD dopuszcza ręczne poprawki (`context/foundation/prd.md:204`).

### 4. Ścieżka zapisu miejsca faktu

- Walidacja: `"{$prefix}.place" => ['nullable', 'string', 'max:255']` (`app/Concerns/PartialDateValidationRules.php:26`), dla prefiksów `birth`, `death`, `marriage`.
  - `PersonStoreRequest` (`app/Http/Requests/PersonStoreRequest.php:106-122`, etykiety „miejsce urodzenia/zgonu” `:156,161`); `PersonUpdateRequest extends PersonStoreRequest` (`PersonUpdateRequest.php:10`).
  - `MarriageStoreRequest.php:26` (etykieta „miejsce ślubu” `:54`) i `MarriageUpdateRequest`.
  - Sloty drzewa dziedziczą `PersonStoreRequest`: `PersonParentStoreRequest`, `PersonChildStoreRequest`, `PersonSpouseStoreRequest`.
- Mapowanie: `MapsEventInput::eventAttributesFrom` → `'place' => $date['place'] ?? null` (`app/Actions/Persons/MapsEventInput.php:23`), używane przez `CreatePerson`, `UpdatePerson` (`putEvent` `:190-208`), `CreateMarriage`, `UpdateMarriage` (`app/Actions/Relations/UpdateMarriage.php:142-147`).
- Na sprawdzonych ścieżkach `CreateSpouseForPerson.php:26-28` i `PersonSpouseController::attach` (`:25`) tworzą małżeństwo z `[]` (bez daty i miejsca); żądania attach nie niosą miejsca.
- Pełne zastąpienie: „klucz nieobecny w żądaniu → `null`” (`context/archive/2026-10-05-maiden-name-and-age/research.md:31`). Front składa cały payload (`resources/js/lib/person-payload.ts:71-121`), mapowanie sekcji `place: blankToNull(values[`${prefix}.place`])` (`:63`). `tests/Feature/WriteTargetsTest.php:267-316` wymienia klucze payloadu jawnie.

### 5. Ścieżka odczytu i UI

- Resources: `EventResource` `'place'` (`app/Http/Resources/EventResource.php:29,42`) → `PersonResource` birth/death (`:55-56`) i `MarriageResource` (`:32`); `PersonFormResource::formEvent` (`:119-120,154`).
- Bez miejsca: `SheetPersonResource` (karty drzewa, `:32-46`), `RelativeResource`, wyszukiwanie osób (`app/Http/Controllers/PersonController.php:40-51` — tylko nazwiska), lista faktów w panelu źródła (`app/Http/Controllers/SourceController.php:159-175`).
- Typy TS: `PersonEvent.place: string | null` (`resources/js/types/person.ts:17`, dziedziczą `IndexPersonEvent`, `SourcedPersonEvent`, `Marriage`), `PersonFormEvent.place` (`:106`), `PartialDatePayload.place` (`resources/js/lib/person-payload.ts:14`), `LocalityOption {id, name}` (`resources/js/types/parish.ts:17-20`).
- Pole edycji: zwykły `<Input name="{prefix}.place">` „Miejsce”, `maxLength` 255 (`resources/js/components/persons/partial-date-input.tsx:109-119`). Używane w `BirthFields`/`DeathFields`/`MarriageFields` (`person-section-fields.tsx:164,188,234`), sekcjach panelu (`overview-tab.tsx:318,357-362,394`), `person-create-dialog.tsx:239-242` i `person-form-fields.tsx:43-50` (dialog slotu drzewa `tree/pick-or-create-person-dialog.tsx`). Picker musi więc działać w panelu, w modalu tworzenia osoby i w dialogu slotu.
- Wyświetlanie: `FactView` „Miejsce” (`overview-tab.tsx:102`; `hasBirth` patrzy na miejsce `:194`), `family-tab.tsx:213`, `sources-tab.tsx:42`, `persons-table.tsx:58,79-81`, `persons-list.tsx:27,53-54` przez `formatEventMeta` (`resources/js/lib/persons-index.ts:190-199`).
- S-04 nie używa miejsca faktu: `filterSourcesByPlaceAndYear` porównuje wpisany tekst z `parish_name`/`locality_name` źródła (`resources/js/lib/source-place-year-filter.ts:11-44`); picker czyści „Miejsce” i wypełnia tylko rok (`source-picker-dialog.tsx:83-85`). Wypełnienie miejsca z miejscowości faktu po S-07 to niezadecydowana okazja (`context/archive/2026-10-05-source-found-by-place-and-year/research.md:31`).
- Podpinanie źródła: `EventSourceController` autoryzuje `update` na wydarzeniu i `view` na źródle (`app/Http/Controllers/EventSourceController.php:48-49`), bez ograniczenia typu wydarzenia — istotne, jeśli pochowanie zostanie wydarzeniem.

### 6. Gotowe wzorce do ponownego użycia

- **`SuggestField`** (`resources/js/components/suggest-field.tsx`): tryb `record` (`:23-33`) z ukrytym `<input name>` (`:238-245`), `onChange(id|null)`, `onCreate(query)`, `offerCreate` `'unmatched'` (domyślnie) | `'always'`, `createLabel`; wysyła natywne `change` na ukrytym polu (`:109-119`); przechwytuje Escape, by nie zamykać panelu (`:181-191`). Filtrowanie w `resources/js/lib/suggestions.ts:7-37` (zawieranie bez wielkości liter, prefiks najpierw, kolacja `pl`, maks. 50 pozycji — `suggest-field.tsx:8`). **Filtruje od pierwszej litery; pusty query zwraca wszystkie** (`context/archive/2026-10-05-act-described-by-parish-and-archive/plan.md:241`) — progu „po kilku literach” z PRD (`prd.md:119-120`) nie ma.
- **Dopisanie miejscowości w locie** (`parish-panel.tsx:66-99,163-189`; `new-parish-dialog.tsx:389-405,494-518`): `useHttp<{name}, LocalityOption>` → `.post(storeInlineLocality.url())` → dopisanie do lokalnego `createdLocalities` i `setLocalityId(created.id)`; błędy z `http.errors.name`. Bez zagnieżdżonego `<form>` i bez wizyty Inertia (`new-parish-dialog.tsx:362-366`; `act-described-by-parish-and-archive/plan.md:66`).
- **Opcje:** pełna lista w propsach, filtrowana na kliencie (`SourceController.php:81-98`). W repo są `Inertia::optional` + `useOptionalList` (`resources/js/hooks/use-relation-candidates.ts:15-41`) dla list ładowanych na żądanie; `Inertia::defer` nie jest używane. Rejestr propsów panelu: `resources/js/lib/side-panel-url.ts:6-12`.
- **Zgodność z edytorem sekcji:** `section-card.tsx:151-182` (dirty = serializacja `FormData` ≠ początkowa; nasłuch `input`/`change`; `MutationObserver` na `value`), zapis przez `readValues(form)` z `FormData` (`:31-38`). `SuggestField` jest z tym zgodny bez zmian; `person-create-dialog.tsx:162-196` ma ten sam mechanizm.

### 7. Migracje i testy — wzorzec

- Kopiuj najpierw, usuwaj później; własny `tests/Feature/<Name>MigrationTest.php`; odwracalny `down()` lub komentarz (CLAUDE.md, sekcja „Migrations that move existing data”).
- Precedensy: seed S-01 (wyżej); `2026_10_05_114945_add_parish_and_archive_to_sources_table.php` (nowy nullable FK + kopia `provenance` → `archive`, `down()` usuwa kolumny z opisem straty, test `tests/Feature/SourceArchiveMigrationTest.php` uruchamia najpierw `down()`, `:31-37`); `2026_10_05_135710_add_entry_number_to_event_source_table.php`.
- FK do `localities`/`parishes` mają domyślne `NO ACTION`, nie `restrictOnDelete`, by działała kaskada konto → drzewo (`create_parishes_table.php:20-22`; `act-described-by-parish-and-archive/plan.md:62`) — to samo dotyczy przyszłego `events.locality_id`.
- Kopie zapasowe zrzucają całe połączenie `pgsql` bez listy tabel (`config/backup.php:46-48`), więc nowe kolumny są objęte automatycznie. `BackupRestoreTest` wymaga wierszy w każdej trwałej tabeli — nowa tabela (np. dla pochowania) wymagałaby uzupełnienia `arrangeBackupData()` (`context/archive/2026-10-05-places-gazetteer/research.md:35,86`).

### 8. Testy, fabryki i E2E dotykające miejsc

- Fabryka: `EventFactory` `'place' => fake()->city()` (`database/factories/EventFactory.php:29`); S-01 jej nie zmienił (`places-gazetteer/plan.md:46`). Seeder `TreeViewDemoSeeder` zapisuje teksty („Kraków” `:139,280`, „Nowy Targ” `:270`) i jedną miejscowość „Tarnów” (`:330`).
- Pest: `PersonsTest.php:755-782,815-823`, `MarriagesTest.php:28-53,196-217,307,425,456-469`, `WriteTargetsTest.php:267,316`.
- Front: `persons-index.test.ts:14-20,200-222`, `person-payload.test.ts`, `source-place-year-filter.test.ts`.
- E2E: helper `tests/e2e/support/api.ts:20,105-106` wysyła `birth[place]`; `fact-source-scan.spec.ts:38` i `source-found-by-place-and-year.spec.ts:43` tworzą osobę z miejscem „Kraków” — strażnicy F-01, którzy pękną, jeśli zmieni się kontrakt pola (`context/archive/2026-10-04-critical-flows-e2e-guard/reviews/impl-review.md:80`). `cleanupBySuffix` sprząta tylko źródła i osoby (`api.ts:362-392`); miejscowości z E2E zostają do S-11 (`context/foundation/test-stack.md:18`).
- Granice własności: obce id w ciele żądania ma dawać błąd walidacji bez zapisu, z tym samym komunikatem co nieistniejące id (`context/foundation/test-plan.md:173`) — każde nowe pole `*.locality_id` potrzebuje przypadku w `OwnershipBoundariesTest`.

## Code References

- `database/migrations/2026_09_23_045723_create_events_table.php:22` — `events.place` (tekst)
- `app/Enums/EventType.php:7-9` — Birth, Death, Marriage; brak pochowania
- `app/Concerns/PartialDateValidationRules.php:26` — reguła miejsca dla wszystkich faktów
- `app/Actions/Persons/MapsEventInput.php:23` — jedyne mapowanie wejścia → `events.place`
- `database/migrations/2026_10_05_081630_create_localities_table.php:24` — `UNIQUE (tree_id, lower(name))`
- `database/migrations/2026_10_05_081632_seed_localities_from_place_texts.php:17-34` — seed `lower(btrim)`
- `app/Concerns/ParishValidationRules.php:95-99` — `Rule::exists` w drzewie
- `app/Http/Controllers/InlineLocalityController.php:14-23` — `POST localities/inline` → `{id, name}` 201
- `resources/js/components/suggest-field.tsx:23-39,109-119,181-191,238-245` — picker
- `resources/js/components/parishes/parish-panel.tsx:66-99` — wzorzec dopisania miejscowości
- `resources/js/components/persons/partial-date-input.tsx:109-119` — dzisiejsze pole „Miejsce”
- `resources/js/components/persons/section-card.tsx:31-38,151-182` — wartości i dirty sekcji
- `resources/js/lib/person-payload.ts:55-121` — payload pełnego zastąpienia
- `app/Actions/Persons/BuildPersonPreview.php:25-34` — props panelu osoby (bez miejscowości)
- `app/Http/Controllers/SourceController.php:81-98` — wzorzec opcji w propsach
- `tests/Feature/LocalitySeedMigrationTest.php` — wyrocznia grupowania tekstów
- `tests/e2e/support/api.ts:20,105-106` — E2E wysyła `birth[place]`

## Architecture Insights

- Fakty to wiersze `events`; jedno pole (`place`) i jedno mapowanie obsługują wszystkie trzy typy, więc zamiana tekstu na `locality_id` dotyka jednej reguły, jednego mapowania, jednego pola UI (`PartialDateInput`) i typów — ale wszystkich konsumentów (create/update osoby i małżeństwa, sloty drzewa, panel, modal, listy).
- Spis jest „mały” z założenia (`act-described-by-parish-and-archive/plan-brief.md:69`): pełna lista w propsach, filtrowanie na kliencie; zmiana na wyszukiwanie przez `useHttp` nie zmieni API `SuggestField`.
- Nowe odwołanie do miejscowości to nowy typ referencji dla S-11 (scalanie musi przenieść „event places, burial places”, CLAUDE.md „Backend conventions”); relacja `Locality::events()` (lub odpowiednik dla pochowania) będzie punktem zaczepienia dla liczenia i przepinania.
- Nie denormalizować miejscowości na `sources`/`event_source` — każde dodatkowe miejsce to kolejny typ referencji do scalania (`source-found-by-place-and-year/research.md:104`).

## Historical Context (from prior changes)

- `context/archive/2026-10-05-places-gazetteer/plan.md:38-40` — brak powiązania tekst → miejscowość; S-07/S-08 dopasują po `lower(btrim)`, brak dopasowania tworzy nową; wybór miejsca w formularzach faktów należy do S-07. **Werdykt:** obowiązuje (kod potwierdza brak `locality_id` przy `events`).
- `context/archive/2026-10-05-places-gazetteer/plan-brief.md:26` — teksty usuwane „dopiero po S-07/S-08”. **Werdykt:** obowiązuje; żaden wycinek roadmapy nie jest właścicielem usunięcia `events.place` (`roadmap.md:211` mówi „w późniejszej migracji”).
- `context/archive/2026-10-05-places-gazetteer/research.md:146` — otwarte: czy S-07 ponownie uruchamia seed, czy dopasowuje przy zapisie. **Werdykt:** nadal otwarte.
- `context/archive/2026-10-05-act-described-by-parish-and-archive/plan.md:56,247-249` — `SuggestField` zbudowany z myślą o S-07/S-08/S-09; `offerCreate='unmatched'` dla miejscowości; lista pod polem, bez portalu. **Werdykt:** obowiązuje (komponent istnieje, `suggest-field.tsx`).
- `context/archive/2026-10-05-act-described-by-parish-and-archive/plan-brief.md:28` — brakującą miejscowość dodaje jedno kliknięcie „Dodaj miejscowość „X”” z samej nazwy, bez modala. **Werdykt:** obowiązuje w panelu parafii; dla S-07 to precedens, nie decyzja.
- `context/archive/2026-10-05-maiden-name-and-age/plan.md:14,66` — nowe pole musi przejść całą ścieżkę pełnego zastąpienia. **Werdykt:** obowiązuje (`person-payload.ts:71-121`).
- `context/archive/2026-10-05-person-side-panel/plan.md:23,246` — panel `standard` (~410 px przy 1366 px) mieści edycję sekcji Urodzenie/Małżeństwo. **Werdykt:** potwierdzone dla dzisiejszych pól; lista podpowiedzi w tej szerokości niesprawdzona.
- `context/archive/2026-10-05-source-found-by-place-and-year/research.md:31` — filtr miejsca przy podpinaniu źródła nie wypełnia się z faktu, bo miejsce faktu to tekst. **Werdykt:** obowiązuje do czasu S-07.

## Related Research

- `context/archive/2026-10-05-places-gazetteer/research.md`
- `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md`
- `context/archive/2026-10-05-source-found-by-place-and-year/research.md`
- `context/archive/2026-10-05-person-side-panel/research.md`
- `context/archive/2026-10-05-maiden-name-and-age/research.md`

## Decisions (autor, 2026-10-06)

Rozstrzygnięte po badaniu przez autora (AskUserQuestion); zastępują pytania 1–4 poniżej.

1. **Pochowanie = wydarzenie bez daty** (nowy `EventType::Burial` w `events` z odwołaniem do miejscowości), wbrew rekomendacji „pole osoby”. Konsekwencje dla planu, wynikające z kodu:
   - `EventType` (`app/Enums/EventType.php:7-9`) dostaje czwarty przypadek z etykietą; `Person` potrzebuje akcesora na wzór `birth()`/`death()` (`app/Models/Person.php:95-103`).
   - Data pochowania nie istnieje (`prd.md:241`), więc formularz pochowania pokazuje tylko miejsce — `PartialDateInput` (`partial-date-input.tsx`) nie pasuje 1:1; reguły daty z `PartialDateValidationRules` nie dotyczą tego prefiksu.
   - `EventSourceController` (`:48-49`) nie filtruje typu, a FR-025 potwierdza źródłem tylko urodzenie, ślub i zgon — plan musi zdecydować, czy pochowanie jest wyłączone z podpinania źródeł (i z list „Źródła” w panelu), czy dopuszczone.
   - Listy filtrujące typy jawnie (np. `PersonController.php:37-38` — `whereIn('type', [Birth, Death])`) trzeba przejrzeć; plan powinien przejść wszystkie 9 plików z `EventType::` w `app/` (lista z grepu: `CreatePerson`, `UpdatePerson`, `BuildFamilySheet`, `MarriageUpdateRequest`, `CreateMarriage`, `MarriageController`, `PersonController`, `Person`, `Event`).
   - Kiedy powstaje wiersz pochowania (tylko gdy miejsce podane, jak zgon tylko przy `is_deceased`) i czy wymaga `is_deceased` — do ustalenia w planie.
2. **Przepięcie tekstów migracją od razu:** nullable `events.locality_id` (FK `NO ACTION`), dosianie brakujących miejscowości tym samym SQL co seed S-01 (`lower(btrim)`, najczęstszy wariant, `ON CONFLICT DO NOTHING`), ustawienie FK po `lower(btrim(place)) = lower(name)` w drzewie; `events.place` zostaje nietknięte. Własny `*MigrationTest` na wzór `LocalitySeedMigrationTest`.
3. **Bez notatki w S-07:** S-07 tylko wybiera miejsce ze spisu; niepewność trafi do notatki osoby z S-14. Brak zależności S-07 → S-14.
4. **Podpowiedzi od pierwszej litery, jak dziś:** `SuggestField` bez progu minimalnej długości; spójnie z pickerami parafii i siedziby.

## Open Questions

1. ~~**Pochowanie: pole osoby czy wydarzenie bez daty?**~~ Rozstrzygnięte: wydarzenie (zob. Decisions 1). (`roadmap.md:210`, owner `/10x-plan`). Ograniczenia: brak daty pogrzebu (`prd.md:241`); FR-025 potwierdza źródłem tylko urodzenie, ślub i zgon; S-09 podpowiada miejsce z nagrobka, ale pozostaje edytowalne przy osobie (`prd.md:98-100`). Wydarzenie wchodzi w `EventType`, w `EventSourceController` (bez ograniczenia typu) i w listy faktów; kolumna osoby (`people.burial_locality_id`) jest prostsza, ale to osobny typ referencji dla S-11.
2. ~~**Mechanizm przepięcia tekstów.**~~ Rozstrzygnięte: wariant (a) (zob. Decisions 2). Warianty: (a) migracja dodaje nullable `events.locality_id`, dosiewa brakujące miejscowości (teksty po S-01) tym samym SQL co S-01 i ustawia FK po `lower(btrim)`; (b) dopasowanie leniwie przy zapisie. Wariant (a) spełnia „istniejące tekstowe miejsca faktów wskazują na miejscowości” od razu po wdrożeniu; ryzyko „przemianowanej miejscowości” dotyczy obu.
3. ~~**„Niepewność opisuje w notatce” — w której?**~~ Rozstrzygnięte: poza S-07 (zob. Decisions 3). Notatka osoby to FR-023 / S-14 (proposed, `roadmap.md:60`); dziś notatki osoby nie ma. Brak decyzji, czy S-07 od niej zależy.
4. ~~**Próg „po kilku literach”**~~ Rozstrzygnięte: bez progu (zob. Decisions 4). (`prd.md:119-120`) — `SuggestField` pokazuje listę od pustego zapytania; nikt nie ustalił N.
5. **Dane produkcyjne** — liczba tekstów `events.place` bez pasującej miejscowości nie była sprawdzana (ani w S-01, `places-gazetteer/research.md:151`, ani tu); runbook S-01 z zapytaniami przed/po (`places-gazetteer/plan.md:244-263`) to precedens.
6. **Co z tekstem `events.place` po przepięciu** — czy zostaje tylko do odczytu (ukryty), czy widoczny obok wyboru; usunięcie kolumny nie ma właściciela w roadmapie.
