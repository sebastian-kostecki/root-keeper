---
date: 2026-10-06T10:57:32+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: 6c34fd1e16618b5e705d9452b7e7d4987c0f7513
branch: develop
repository: root-keeper
topic: "S-08 residence-history — jak dziś działa zamieszkanie osoby i co trzeba zmienić, by osoba miała kilka miejscowości zamieszkania ze spisu z latami od–do"
tags: [research, persons, residence, localities, gazetteer, person-side-panel, migration, locality-picker]
status: complete
last_updated: 2026-10-06
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dodano decyzje autora (2026-10-06): osobna karta „Zamieszkania”, sortowanie po roku z pozycjami bez lat na końcu, lata opcjonalne z od ≤ do, tylko w panelu, duplikaty dozwolone, usunięcie kolumny w tabeli osób"
---

# Research: S-08 — kilka zamieszkań osoby ze spisu miejscowości, z latami od–do

**Date**: 2026-10-06T10:57:32+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: 6c34fd1
**Branch**: develop
**Repository**: root-keeper

## Research Question

Wycinek S-08 (`context/foundation/roadmap.md:214-224`, PRD FR-011 `context/foundation/prd.md:127-129`): autor ustawia osobie kilka miejscowości zamieszkania ze spisu z opcjonalnymi latami od–do; dotychczasowe pole „Zamieszkanie” staje się pierwszą pozycją listy, bez lat (`prd.md:206`). Zamieszkania nie potwierdza się źródłem (`prd.md:241`). Pytanie badawcze: jak zamieszkanie jest dziś zapisywane, walidowane i wyświetlane; co z S-01 i S-07 jest gotowe do użycia (spis, picker, wzorzec migracji); co S-08 musi zmienić w kontraktach i testach; jakie decyzje pozostają otwarte.

## Summary

- **Zamieszkanie to jedna kolumna tekstowa `people.residence`** (varchar 255, `database/migrations/2026_09_23_045722_create_people_table.php:19`); żadna z migracji w `database/migrations/` jej później nie zmienia. Nie ma kolumny `residence_locality_id`, tabeli zamieszkań ani relacji do miejscowości (`app/Models/Person.php:25,30`).
- **S-01 utworzył miejscowości z tekstów zamieszkania, ale niczego nie powiązał.** Seed bierze `people.residence` razem z `events.place` (`database/migrations/2026_10_05_081632_seed_localities_from_place_texts.php:18-23`) i zostawia teksty nietknięte. Teksty wpisane lub zmienione po S-01 (formularz nadal zapisuje tekst, `app/Actions/Persons/UpdatePerson.php:58`) mogą nie mieć miejscowości — S-07 jawnie przekazał „dosianie” zamieszkań do S-08 (`context/archive/2026-10-06-fact-places-from-gazetteer/plan.md:47`).
- **Wzorzec migracji jest gotowy w S-07:** kolumna FK → dosianie (SQL z S-01 zawężony do jednego źródła, `ON CONFLICT (tree_id, lower(name)) DO NOTHING`) → `UPDATE` po `lower(l.name) = lower(btrim(tekst))` w obrębie drzewa; tekst źródłowy zostaje (`database/migrations/2026_10_06_070850_link_event_places_to_localities.php:18-48`). Test migracji: `tests/Feature/FactPlaceLocalityMigrationTest.php`.
- **W repo nie ma tabeli podrzędnej z pozycją ani zakresem lat** (przeszukane `database/migrations` i `app` pod `position`, `sort_order`, `year_from`, `year_to`, `from_year`, `to_year` — brak trafień). Rok jako `unsignedSmallInteger` nullable ma precedens w `events.date_year` (`database/migrations/2026_09_23_045723_create_events_table.php:18`); walidacja roku `between:1000,{bieżący rok}` w `app/Concerns/PartialDateValidationRules.php:23`.
- **Picker jest gotowy, ale zaprojektowany dla jednego pola na instancję:** `resources/js/components/localities/locality-picker.tsx` (ukryte pole z id, „Dodaj miejscowość „X””, find-or-create `POST localities/inline` 200/201). Przy wielu wierszach: stan wybranego id jest lokalny i ustawiany raz (`locality-picker.tsx:43-45`), a miejscowość dodana w jednym wierszu nie trafia do opcji innych wierszy (stan `created` per instancja, `:46,87`); wzorzec współdzielonego `createdLocalities` jest w `resources/js/components/sources/source-description-fields.tsx:62-82,206-216`.
- **W froncie nie ma edytora listy wierszy** (brak `useForm`, brak nazw pól z indeksem w `resources/js/`). Dwie ścieżki zapisu zachowują się inaczej: Inertia `<Form>` (dialog „Dodaj osobę”, sloty drzewa) sam zamienia `residences.0.locality_id` na tablicę; panel osoby spłaszcza `FormData` do `Record<string,string>` (`resources/js/components/persons/section-card.tsx:32-42`) i buduje payload ręcznie w `resources/js/lib/person-payload.ts` — tu potrzebny nowy parser.
- **Zapis osoby to pełne zastąpienie** (`PUT persons/{id}`, payload z `personFormToPayload` / `mergeSectionIntoPayload`, `person-payload.ts:78,111`). Brak klucza zamieszkań w payloadzie zapisu innej sekcji oznaczałby skasowanie listy — ten sam błąd przypiął impl-review S-07 F2 dla pochowania.
- **Nowa tabela zmieni dokładne listy tabel w testach różnicowych:** `tests/Feature/WriteTargetsTest.php:275-290` (update `residence` → oczekuje tabel `['events','people']` i zmiany tylko kolumny `residence`), `tests/Feature/DeleteImpactTest.php:80-82` (dokładna lista tabel usuwanych z osobą), oraz `tests/Backup/BackupRestoreTest.php:25-26` wymaga >0 wierszy w każdej tabeli (fixture `tests/BackupTestCase.php:129`).
- **Rozstrzygnięcia autora (2026-10-06, sekcja Decisions):** osobna karta „Zamieszkania” w panelu zapisywana razem z osobą; sortowanie po roku, pozycje bez lat na końcu; oba lata opcjonalne, od ≤ do; zamieszkania tylko w panelu (nie w formularzach tworzenia); ta sama miejscowość może wystąpić kilka razy; kolumna „Zamieszkanie” znika z tabeli osób.

## Detailed Findings

### 1. Model danych dziś

- `people.residence` — `string`, nullable (`database/migrations/2026_09_23_045722_create_people_table.php:19`). Tabela nazywa się `people`, nie `persons`.
- `Person`: `@property string|null $residence` (`app/Models/Person.php:25`), w `#[Fillable]` (`:30`), bez castu. Relacje do miejscowości ma tylko `Event` (`app/Models/Event.php` — `locality(): BelongsTo`).
- `Locality` ma dokładnie dwie relacje odwrotne: `parishes()` (`app/Models/Locality.php:38`) i `events()` (`:46`). S-11 (scalanie) potrzebuje kompletu odwołań, więc S-08 dodaje tu trzecią (wniosek z `roadmap.md:254`).
- Unikalność spisu: indeks `localities (tree_id, lower(name))` (`database/migrations/2026_10_05_081630_create_localities_table.php:24`).
- Fabryka: `'residence' => fake()->city()` w stanie domyślnym (`database/factories/PersonFactory.php:27`) — każda osoba z fabryki ma tekst zamieszkania. Seeder demo: `database/seeders/TreeViewDemoSeeder.php:261` (`'Zakopane'`), z pomocnikiem `localityId()` (`:548-551`, `firstOrCreate`).

### 2. Ścieżka zapisu

- Walidacja: `'residence' => ['nullable', 'string', 'max:255']` (`app/Http/Requests/PersonStoreRequest.php:43`), etykieta `'miejsce zamieszkania'` (`:77`). `PersonUpdateRequest`, `PersonParentStoreRequest`, `PersonChildStoreRequest` i `PersonSpouseStoreRequest` dziedziczą po `PersonStoreRequest` (wg raportu z przeglądu requestów), więc ta reguła obowiązuje we wszystkich przepływach tworzenia i edycji osoby.
- Reguła id miejscowości ograniczona do drzewa — gotowy wzorzec: `"{$prefix}.locality_id" => ['nullable', 'integer', Rule::exists('localities', 'id')->where('tree_id', $this->user()->tree->id)]` (`app/Concerns/PartialDateValidationRules.php:27`); to samo dla `burial.locality_id` (`PersonStoreRequest.php:30`).
- Akcje: `CreatePerson` zapisuje `'residence' => $data['residence'] ?? null` (`app/Actions/Persons/CreatePerson.php:53`); `UpdatePerson` tak samo (`app/Actions/Persons/UpdatePerson.php:58`). `CreatePerson` jest używany przez akcje relacji (`app/Actions/Relations/CreateChildForParents.php`, `CreateSpouseForPerson.php`, `CreateParentForPerson.php`), więc sloty drzewa przechodzą tę samą ścieżkę.
- Wzorzec pełnego zastąpienia z pochowania: brak klucza `burial` czyści pochowanie w `UpdatePerson` (S-07 impl-review F2, `context/archive/2026-10-06-fact-places-from-gazetteer/reviews/impl-review.md:36-48`). Lista zamieszkań w tym samym kontrakcie zachowa się analogicznie.

### 3. Ścieżka odczytu

- `PersonResource`: `'residence' => $this->residence` (`app/Http/Resources/PersonResource.php:51`) — używany w panelu i w tabeli osób.
- `PersonFormResource`: `'residence' => $this->residence` (`app/Http/Resources/PersonFormResource.php:47`); prywatny `localityOf()` (`:78`) zwraca `['id','name']` lub null — to kształt, którego picker używa do wstępnego wypełnienia.
- Wyszukiwanie osób dopasowuje tylko imię, nazwisko i nazwisko rodowe (`app/Http/Controllers/PersonController.php:41-52`); zamieszkanie nie jest wyszukiwane, filtrowane ani sortowane w przejrzanym kodzie.
- Eager loading: brak `preventLazyLoading` w repo, więc pominięty `with()` na nowej relacji to ciche N+1 (`context/archive/2026-10-06-fact-places-from-gazetteer/plan.md:13,170`). Miejsca ładujące osoby z relacjami: `app/Actions/Persons/BuildPersonPreview.php:27`, `PersonController.php:37-40` (lista osób).

### 4. Front

- **Jedyne pole edycji:** `Input name="residence"` z etykietą „Miejsce zamieszkania” w `PersonalFields` (`resources/js/components/persons/person-section-fields.tsx:151-160`). `PersonalFields` jest używany w trzech miejscach: karta „Dane osobowe” w panelu (`resources/js/components/persons/overview-tab.tsx:300-306`), dialog „Dodaj osobę” (`resources/js/components/persons/person-create-dialog.tsx`), dialog slotu drzewa przez `PersonFormFields` (`resources/js/components/persons/person-form-fields.tsx:31-37`).
- **Widok:** `<Field label="Zamieszkanie" value={person.residence} />` w karcie „Dane osobowe” (`overview-tab.tsx:294-297`); kolumna „Zamieszkanie” w tabeli osób (`resources/js/components/persons/persons-table.tsx:128,163-169`). Lista osób (widok A), karty drzewa i zakładka rodziny nie pokazują zamieszkania (grep w `persons-list.tsx`, `tree/sheet-person-card.tsx`, `types/tree.ts`).
- **Routing błędów:** `PERSONAL_KEYS` zawiera `'residence'` (`overview-tab.tsx:41-48`), a `belongsToSection` dopasowuje klucz dokładnie przez `includes` (`:51-54`). Klucze `residences.0.locality_id` nie trafią pod pole, tylko do slotu „zbłąkanych” błędów sekcji — potrzebne dopasowanie po prefiksie (to samo zauważył plan-review S-07 F5).
- **Payload panelu:** `PersonPayload.residence` (`resources/js/lib/person-payload.ts:22`), kopiowany w `personFormToPayload` (`:78`) i nadpisywany w `mergeSectionIntoPayload('personal')` (`:111`). Parser pól z prefiksem do skopiowania: `eventPayloadFromValues` (`person-payload.ts:55-66`).
- **Wykrywanie niezapisanych zmian:** `MutationObserver` na `value` ukrytych inputów i `childList` (`section-card.tsx:150-184`, `person-create-dialog.tsx:167-196`). Dodanie/usunięcie wiersza zmienia `childList` i serializację `FormData`, więc strażnik powinien to wykryć — niezweryfikowane w działaniu.
- **Typy:** `Person.residence: string | null` (`resources/js/types/person.ts:71`), `PersonFormData.residence` (`:120`); `LocalityOption {id; name}` w `resources/js/types/parish.ts`.
- **Inertia `<Form>`** zamienia klucze z kropkami i kolejnymi indeksami na zagnieżdżone obiekty/tablice (`formDataToObject`, `node_modules/@inertiajs/core/dist/index.js:4044-4067`, v3.8.0), więc w dialogach tworzenia nazwy `residences.0.locality_id` dotrą jako tablica bez dodatkowego kodu.

### 5. Picker miejscowości (S-07)

- Ścieżka: `resources/js/components/localities/locality-picker.tsx`; propsy `id`, `name` (nazwa ukrytego pola z id), `label`, `value: LocalityOption | null`, `options?`, `errors?`, `onChange?`, `onCreated?` (`:10-22`).
- Opcje: bez `options` czyta współdzielony opcjonalny prop `localityOptions` przez `useOptionalList` i przeładowuje go przy montowaniu (`:39-40,50-57`; prop w `app/Http/Middleware/HandleInertiaRequests.php:62`). Przy N wierszach każda instancja może wywołać własny `router.reload({only:['localityOptions']})` — zachowanie współbieżnych przeładowań niesprawdzone.
- Dopisanie w locie: `useHttp().post(storeInlineLocality.url())` (`:76-94`) → `app/Http/Controllers/InlineLocalityController.php` (find-or-create po `lower(name)`, 200 istniejąca / 201 nowa). Find-or-create rozwiązał w S-07 problem dwóch pickerów w jednym formularzu (plan-review S-07 F1).
- Zastosowania z indeksowaną nazwą pola: `partial-date-input.tsx:111-117` (`name={`${prefix}.locality_id`}`), pochowanie `person-section-fields.tsx:231-237` (`burial.locality_id`).

### 6. Testy, które zależą od `residence` lub od listy tabel

- `tests/Feature/PersonsTest.php:756-777` — update `residence` na `'Kraków'`.
- `tests/Feature/WriteTargetsTest.php:275-290` — update z `'residence' => 'Nowe Miasto'`, oczekuje dokładnie tabel `['events','people']` i zmiany jedynie kolumny `residence`.
- `tests/Feature/DeleteImpactTest.php:80-82` — dokładna lista tabel zmienionych przez usunięcie osoby: `['event_participants','event_source','events','parent_child_relations','people','trees']`. Jeśli osoby w tym teście dostaną wiersze zamieszkań, nowa tabela dojdzie do listy.
- `databaseRows()` (`tests/Pest.php:151`) czyta każdą publiczną tabelę, więc nowa tabela wchodzi w każdy diff, gdy ma wiersze.
- `tests/Backup/BackupRestoreTest.php:25-26` — każda tabela musi mieć >0 wierszy; nowa tabela wymaga rozszerzenia `arrangeBackupData()` (`tests/BackupTestCase.php:129`).
- `tests/Feature/LocalitySeedMigrationTest.php` — używa `people.residence` (wstawianie tekstu zamieszkania); dopóki kolumna zostaje, test się nie zmienia.
- Wzorce testów granicy drzewa do skopiowania: `tests/Feature/PersonsTest.php:1445-1461` (obca lub nieistniejąca `locality_id` → błąd, nic nie zapisane), `:1463-1477` (request slotu dziedziczy regułę).
- Vitest: fixture'y z `residence: null` w `resources/js/lib/person-payload.test.ts:16` i `resources/js/lib/persons-index.test.ts:40`. Brak testów komponentów (`include` tylko `resources/js/**/*.test.ts`, `vite.config.ts:42-44`).
- E2E: żaden spec w `tests/e2e/` nie dotyczy zamieszkania. Szablon: `tests/e2e/fact-places-from-gazetteer.spec.ts` (dodanie miejscowości z pickera, edycja innej sekcji, sprawdzenie po przeładowaniu); pomocnik `createLocality` (`tests/e2e/support/api.ts:194-209`); `PersonFields` w `support/api.ts:16-21` nie ma pola zamieszkania.

## Code References

- `database/migrations/2026_09_23_045722_create_people_table.php:19` — kolumna `residence`
- `database/migrations/2026_10_05_081632_seed_localities_from_place_texts.php:18-23` — seed S-01 bierze `people.residence`
- `database/migrations/2026_10_06_070850_link_event_places_to_localities.php:18-48` — wzorzec: FK + indeks, dosianie, `UPDATE` po nazwie
- `tests/Feature/FactPlaceLocalityMigrationTest.php` — wzorzec testu migracji
- `app/Models/Locality.php:38,46` — relacje odwrotne `parishes()`, `events()`
- `app/Http/Requests/PersonStoreRequest.php:30,43,77` — reguły `burial.locality_id`, `residence`
- `app/Concerns/PartialDateValidationRules.php:27` — reguła id miejscowości ograniczona do drzewa
- `app/Actions/Persons/CreatePerson.php:53`, `app/Actions/Persons/UpdatePerson.php:58` — zapis tekstu
- `app/Http/Resources/PersonResource.php:51`, `app/Http/Resources/PersonFormResource.php:47,78` — odczyt
- `app/Http/Controllers/InlineLocalityController.php` — find-or-create miejscowości
- `resources/js/components/persons/person-section-fields.tsx:151-160` — pole tekstowe
- `resources/js/components/persons/overview-tab.tsx:41-54,294-297` — klucze sekcji i widok
- `resources/js/components/persons/persons-table.tsx:128,163-169` — kolumna w tabeli
- `resources/js/components/persons/section-card.tsx:32-42` — spłaszczanie `FormData`
- `resources/js/lib/person-payload.ts:22,55-66,78,111` — payload pełnego zastąpienia
- `resources/js/components/localities/locality-picker.tsx:10-22,43-46,76-94` — picker
- `resources/js/components/sources/source-description-fields.tsx:62-82,206-216` — współdzielone `createdLocalities`
- `tests/Feature/WriteTargetsTest.php:275-290`, `tests/Feature/DeleteImpactTest.php:80-82`, `tests/Backup/BackupRestoreTest.php:25-26` — testy z dokładnymi listami tabel

## Architecture Insights

- **Kształt danych wynika z wymagań:** „kilka miejscowości z latami” wymaga tabeli podrzędnej (osoba → wiele wierszy z `locality_id` i dwoma opcjonalnymi latami); jedna kolumna FK na `people` nie wystarcza. Konwencje repo dla takiej tabeli: `tree_id` na każdym rekordzie domenowym (CLAUDE.md, „Backend conventions”), FK do `localities` z domyślnym NO ACTION i jawnym `->index()` (powód NO ACTION: kaskada konto → drzewo → miejscowości w jednym poleceniu, `database/migrations/2026_10_05_114218_create_parishes_table.php:20-22`; indeks: S-07 impl-review F1), kaskada z osoby (wzorzec `create_parent_child_relations_table.php:14-24`).
- **Migracja danych wg CLAUDE.md „Copy first, drop later”:** kolumna `people.residence` zostaje; migracja dosiewa brakujące miejscowości z `people.residence`, tworzy jeden wiersz zamieszkania bez lat na osobę z niepustym tekstem; własny test `tests/Feature/<Name>MigrationTest.php`; `down()` odwracalny lub z komentarzem. Usunięcie kolumny tekstowej nie ma właściciela w roadmapie (to samo dotyczy `events.place`, `context/archive/2026-10-06-fact-places-from-gazetteer/plan.md:41`).
- **Wdrożenie razem:** jeśli migracja wejdzie przed kodem, stary formularz dalej pisze tylko tekst, a lista staje się nieaktualna — S-07 wdrażał fazy razem (plan-review S-07 F6).
- **Zamieszkanie nie jest wydarzeniem:** nie ma źródła ani daty dziennej (`prd.md:241`), więc nie pasuje do `events`/`event_source`; osobna tabela nie wchodzi w ścieżki potwierdzania faktów.
- **Kolejność w PHP zamiast kolumny ma precedens:** `app/Support/PersonOrdering.php` (`marriages()`, `marriageSortKey()`) sortuje po dacie z jawnym miejscem dla braków. Alternatywa to kolumna pozycji ustawiana przez autora — w repo nie ma jej precedensu.

## Historical Context (from prior changes)

- `context/archive/2026-10-05-places-gazetteer/plan.md:36-39` — S-01: brak powiązania tekst → miejscowość; S-07/S-08 dopasowują po `lower(btrim)`, brak dopasowania → nowa miejscowość; teksty wpisane po S-01 trafią do spisu dopiero w S-07/S-08. Zaakceptowane ryzyko: miejscowość przemianowana w spisie przed S-08 wróci jako nowa (`:38`). Werdykt: obowiązuje.
- `context/archive/2026-10-05-places-gazetteer/plan-brief.md:26` — teksty usuwa się dopiero po S-07/S-08. Werdykt: częściowo — S-07 też ich nie usunął, a usunięcie nie ma dziś właściciela.
- `context/archive/2026-10-06-fact-places-from-gazetteer/plan.md:35,47,78` — S-07 zawęził dosianie do `events.place`, bo „`people.residence` należy do S-08, także ich dosiewanie”. Werdykt: obowiązuje; potwierdzone w kodzie migracji (`link_event_places_to_localities.php:25-39`).
- `context/archive/2026-10-06-fact-places-from-gazetteer/reviews/impl-review.md:26-58` — F1 (indeks na FK), F2 (brak klucza w pełnym zastąpieniu kasuje dane, przypiąć testem), F3 (gałąź wyścigu w inline store). Werdykt: F1 i F2 dotyczą S-08 bezpośrednio.
- `context/foundation/archive/prd-v2.md:116,181` — prd-v2 (dawne S-14) miał tylko chipy miejsc i jawny Non-Goal „Brak okresów przy miejscach zamieszkania”. Werdykt: zastąpione — obecny PRD dodał lata od–do (`prd.md:127`), a roadmapa wymienia „okresy zamieszkań (`S-08`)” wśród powrotów (`roadmap.md:374`).
- `context/foundation/roadmap.md:254` — S-11 przenosi przy scalaniu także zamieszkania, w jednej transakcji; test scalania obejmuje każdy rodzaj odwołania. Werdykt: obowiązuje; wymaga od S-08 relacji `Locality` → zamieszkania i przepinalnego FK.

## Related Research

- `context/archive/2026-10-05-places-gazetteer/research.md` — spis miejscowości i seed z tekstów (pytania otwarte 4, 5, 10 dotyczą zamieszkania).
- `context/archive/2026-10-06-fact-places-from-gazetteer/research.md` — miejsca faktów, picker, pełne zastąpienie payloadu.

## Decisions (autor, 2026-10-06)

Pytania zadane po badaniu; w przejrzanych artefaktach nie było rozstrzygnięć.

1. **Gdzie edytować:** osobna karta sekcji „Zamieszkania” w panelu osoby — lista wierszy (miejscowość z pickera + rok od + rok do + usuń), zapisywana razem z osobą (ta sama ścieżka `PUT persons/{id}`, pełne zastąpienie). Odrzucone: lista w „Dane osobowe”; karta na każde zamieszkanie z własnymi trasami.
2. **Kolejność:** automatycznie po roku („od”, a gdy brak — „do”), pozycje bez lat na końcu. Bez kolumny pozycji (wzorzec `app/Support/PersonOrdering.php`). Konsekwencja: pozycja z migracji (bez lat) ląduje na końcu listy, gdy pojawią się datowane.
3. **Lata:** oba opcjonalne (tylko od, tylko do, oba lub żadne); gdy oba — od ≤ do; zakres jak w datach, 1000 – bieżący rok (`PartialDateValidationRules.php:23`).
4. **Formularze tworzenia:** zamieszkania tylko w panelu. Dialog „Dodaj osobę” i dialog slotu drzewa tracą pole „Miejsce zamieszkania” (dziś przez wspólne `PersonalFields`, `person-section-fields.tsx:151-160`).
5. **Duplikaty:** dozwolone — bez unikalności (osoba, miejscowość); powrót do miejscowości to osobny wiersz. S-11 nie musi rozwiązywać kolizji przy scalaniu.
6. **Tabela osób:** kolumna „Zamieszkanie” znika (`persons-table.tsx:128,163-169`).

## Open Questions

Luki faktyczne (nierozstrzygnięte w tym badaniu):

- Nie sprawdzono danych produkcyjnych ani lokalnej bazy: ile tekstów `people.residence` nie ma dziś odpowiadającej miejscowości (wpisane po S-01). Łagodzi to runbook z licznikami przed/po, jak w S-07 (`context/archive/2026-10-06-fact-places-from-gazetteer/plan.md:385`).
- Niesprawdzone w działaniu: współbieżne `router.reload({only:['localityOptions']})` z kilku pickerów oraz wykrywanie dodania/usunięcia wiersza przez strażnika niezapisanych zmian.
- Usunięcie kolumny `people.residence` (i `events.place`) nie ma właściciela w roadmapie — poza zakresem S-08 wg CLAUDE.md, ale warto to zapisać.
