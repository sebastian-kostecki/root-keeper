# Scalanie i usuwanie miejscowości i parafii (S-11) — plan implementacji

## Overview

Autor scala zduplikowaną miejscowość lub parafię z właściwym wpisem — wszystkie odwołania przechodzą na właściwy w jednej transakcji, a duplikat znika — i usuwa wpis, którego nic nie używa (FR-012, roadmapa S-11). Spisy pokazują liczbę użyć każdego wpisu; scalanie i usuwanie uruchamia się z panelu bocznego wpisu.

## Current State Analysis

- Odwołania do miejscowości to cztery kolumny, wszystkie FK z domyślnym `NO ACTION`: `parishes.locality_id` (siedziba, `2026_10_05_114218_create_parishes_table.php:22`), `sources.cemetery_locality_id` (cmentarz nagrobka, `2026_10_06_125653_add_cemetery_and_buried_people_to_sources.php:17`), `events.locality_id` (urodzenie, ślub, zgon i pochowanie — pochowanie to zdarzenie `burial`, `2026_10_06_070850_link_event_places_to_localities.php:21`, `app/Actions/Sources/FillBurialPlacesFromGravestone.php:30-34`), `residences.locality_id` NOT NULL (`2026_10_06_110402_create_residences_from_residence_texts.php:24`). Relacje istnieją jako punkty zaczepienia: `Locality::parishes|cemeterySources|events|residences` (`app/Models/Locality.php:37-67`).
- Do parafii odwołuje się tylko `sources.parish_id` (`2026_10_05_114945_add_parish_and_archive_to_sources_table.php:20`, `Parish::sources()` `app/Models/Parish.php:63-66`).
- `NO ACTION` zamiast `RESTRICT` jest celowe: kaskada konto → drzewo → spisy działa w jednym poleceniu (`context/archive/2026-10-05-act-described-by-parish-and-archive/plan.md:62`). Baza i tak odrzuci usunięcie używanego wpisu (SQLSTATE 23503).
- Unikalność: miejscowość `(tree_id, lower(name))` (`create_localities_table.php`), parafia `(tree_id, lower(name), coalesce(religion, ''))` (`create_parishes_table.php`). Zamieszkania nie mają żadnej unikalności (ani indeksu, ani reguły w `PersonUpdateRequest.php:52-60`).
- Brak tras usuwania i scalania: `routes/web.php:35-39` — `localities|parishes` mają tylko `index`, `store`, `update` i `inline.store`. Polityki mają tylko `view` i `update` (`app/Policies/LocalityPolicy.php`, `ParishPolicy.php`).
- Panele (`resources/js/components/localities/locality-panel.tsx`, `parishes/parish-panel.tsx`) to sam formularz; tabele (`localities-table.tsx`, `parishes-table.tsx`) nie pokazują użyć. `ParishResource` ma już `sources_count` przez `whenCounted` (`app/Http/Resources/ParishResource.php:30`), ale nic go nie liczy.
- Zamrożone teksty `events.place` i `people.residence` nie są czytane przez UI (`EventResource.php:43`, `PersonResource.php:59` biorą nazwę z `locality`) — scalanie ich nie dotyka.
- E2E: `cleanupBySuffix` (`tests/e2e/support/api.ts:371-404`) usuwa źródła i osoby z sufiksem; miejscowości i parafie z sufiksem zostają w bazie `e2e` w sześciu specyfikacjach (jawny wyjątek w `context/foundation/test-stack.md:18`).

## Desired End State

- `/localities` i `/parishes` mają kolumnę „Użycia” z łączną liczbą odwołań wpisu.
- Panel istniejącej miejscowości/parafii ma „Scal z…” i „Usuń”. „Scal z…” otwiera modal: wybór właściwego wpisu ze spisu, liczby przenoszonych odwołań, potwierdzenie; po scaleniu otwiera się panel właściwego wpisu, duplikatu nie ma w spisie, każde jego odwołanie wskazuje na właściwy wpis.
- „Usuń” dla nieużywanego wpisu pyta o potwierdzenie i usuwa; dla używanego dialog mówi, gdzie wpis jest używany, i podsuwa „Scal z…” — bez przycisku usuwania. Serwer odrzuca usunięcie używanego wpisu także przy wyścigu.
- Cudzy wpis w adresie to 404; właściwy wpis z cudzego drzewa lub ten sam wpis to błąd walidacji; nic nie zostaje zapisane.
- E2E nie zostawia już miejscowości ani parafii w bazie `e2e`.

Weryfikacja: Pest (`LocalitiesTest`, `ParishesTest`, `OwnershipBoundariesTest`, `DeleteImpactTest`), Vitest, `npm run check`, `types:check`, Larastan, E2E przepływu scalania, ręczny przegląd paneli.

### Key Discoveries:

- `OwnershipBoundariesTest` wymaga przypadku dla każdej trasy kontrolera z `{…}` w adresie (`tests/Feature/OwnershipBoundariesTest.php:156-172`) — nowe trasy muszą do niego trafić (`:85-86` to wzorzec).
- `DeleteImpactTest` porównuje całą bazę przed i po operacji i sprawdza zbiór usuniętych wierszy (`tests/Feature/DeleteImpactTest.php:65-94`).
- Kontroler jednoakcyjny: `SourceLegacyEntryNumberController` (`__invoke`, `Gate::authorize`, akcja, toast, redirect).
- Wybór rekordu bez „Dodaj …”: `SuggestField` w trybie `record` bez `onCreate` (`resources/js/components/suggest-field.tsx:23-34`).
- Wzorzec dialogu usuwania: `resources/js/components/sources/delete-source-dialog.tsx` (`router.delete`, `processing`, `DialogClose`).
- Reguła nazwy miejscowości liczona przez drzewo: `app/Concerns/LocalityValidationRules.php`; komunikat kolizji parafii: `app/Actions/Parishes/UpdateParish.php:25`.

## What We're NOT Doing

- Podpowiadanie podobnych nazw („Luboml” / „Lubomla”) ani automatyczne wykrywanie duplikatów — autor sam wybiera, co scalić.
- Scalanie więcej niż dwóch wpisów naraz, zaznaczanie wierszy w tabeli, cofanie scalenia (przed migracją produkcyjną jest kopia, ale scalenie w aplikacji jest nieodwracalne — modal to mówi).
- Wybór pola po polu przy scalaniu — właściwy wpis zachowuje nazwę, puste pola uzupełnia duplikat (patrz Faza 1 i 2).
- Łączenie zamieszkań o różnych latach — redukowane są tylko identyczne (ta sama osoba, miejscowość, rok od i rok do).
- Blokowanie scalenia parafii o różnych wyznaniach — dozwolone; modal pokazuje pełne etykiety z wyznaniem obu wpisów.
- Usuwanie używanego wpisu z listą powiązań (FR-012 to wyklucza) i usuwanie kolumn `events.place` / `people.residence` (osobna, nieprzypisana migracja).
- Zmiany w zamrożonych tekstach `events.place` i `people.residence`.

## Implementation Approach

Każdy spis dostaje dwie akcje (`Merge…`, `Delete…`) i jedną trasę na każdą: `POST {spis}/{rekord}/merge` z `target_id` oraz `DELETE {spis}/{rekord}`. Akcje działają w `DB::transaction` z `lockForUpdate` na obu wierszach (w kolejności id, żeby dwa przeciwne scalenia nie zakleszczyły się). Scalanie przepina odwołania masowymi `UPDATE … WHERE <fk> = duplikat`, po czym usuwa duplikat i dopiero wtedy uzupełnia puste pola właściwego wpisu. Usuwanie liczy użycia pod blokadą, a FK `NO ACTION` jest siatką na wyścig. Liczby użyć wchodzą do zasobów przez `withCount` (spis i panel). Frontend dodaje kolumnę i dwa modale na spis, z jednym wspólnym pomocnikiem tekstu użyć.

## Critical Implementation Details

**Kolejność w scalaniu.** Duplikat musi zniknąć przed uzupełnieniem pól właściwego wpisu: parafia „Luboml” bez wyznania przejmująca wyznanie duplikatu „Luboml (rzymskokatolickie)” zderzyłaby się z nim w indeksie unikalnym. Wyznanie parafii przechodzi tylko wtedy, gdy po usunięciu duplikatu żadna inna parafia drzewa nie ma tej samej nazwy (bez wielkości liter) i wyznania; w przeciwnym razie właściwa zostaje bez wyznania (bez błędu). Opis miejscowości: pusty → opis duplikatu; oba niepuste i różne (po `trim`) → opis właściwego, pusta linia, opis duplikatu. Wynik może przekroczyć 5000 znaków (kolumna `text` to przyjmie); przy następnej edycji walidacja `max:5000` poprosi o skrócenie — świadomie, bo inaczej opis by przepadł.

**Wyścig przy usuwaniu.** Licznik pod `lockForUpdate` na wierszu spisu wystarcza: wstawienie odwołania (np. zamieszkania) bierze w PostgreSQL `FOR KEY SHARE` na rodzicu i czeka na koniec transakcji. `QueryException` z SQLSTATE `23503` przy `delete()` mimo to zamienia się w ten sam `ValidationException`, co niezerowy licznik — obrona w głębi, bez osobnego testu (w Pest nie da się wywołać wyścigu).

**Akcje w panelu a niezapisany formularz.** „Scal z…” i „Usuń” są wyłączone, gdy formularz panelu ma niezapisane zmiany (`isDirty` z render-propu `Form`) — inaczej przekierowanie po scaleniu/usunięciu po cichu porzuciłoby edycję mimo `useUnsavedChangesGuard`.

## Faza 1: Backend — miejscowości

### Overview

Liczby użyć miejscowości, scalanie z przepięciem czterech rodzajów odwołań i usuwanie nieużywanej, z testami Pest.

### Changes Required:

#### 1. Liczby użyć w zasobie i na stronie

**File**: `app/Http/Resources/LocalityResource.php`, `app/Http/Controllers/LocalityController.php`

**Intent**: Spis i panel pokazują, ile odwołań ma miejscowość i jakiego rodzaju — dla kolumny „Użycia”, dialogu usuwania i modalu scalania.

**Contract**: `LocalityResource` += `usage` (tylko gdy wszystkie cztery liczniki są załadowane): `{ facts: int, parishes: int, residences: int, gravestones: int, total: int }` z `events_count`, `parishes_count`, `residences_count`, `cemetery_sources_count`. `index` ładuje `withCount(['events', 'parishes', 'residences', 'cemeterySources'])` zarówno dla listy, jak i dla `localityPanel`. Typ zwrotny w PHPDoc jako array shape.

#### 2. Polityka

**File**: `app/Policies/LocalityPolicy.php`

**Intent**: Usuwanie i scalanie (które usuwa duplikat) wymagają własności wpisu z adresu.

**Contract**: `delete(User, Locality): Response` — jak `update`, `denyAsNotFound()` dla cudzej.

#### 3. Żądanie scalenia

**File**: `app/Http/Requests/LocalityMergeRequest.php` (nowy, `make:request`)

**Intent**: Właściwy wpis musi być inną miejscowością tego samego drzewa.

**Contract**: `target_id`: `required`, `integer`, `Rule::exists('localities', 'id')->where('tree_id', <drzewo użytkownika>)`, `Rule::notIn([$locality->id])` (id z adresu). Komunikaty po polsku: „Wybierz miejscowość ze spisu.”, „Wybierz inną miejscowość niż scalana.”; atrybut „miejscowość docelowa”. `authorize(): Response` = `Gate::inspect('delete', $this->route('locality'))` (jak `LocalityUpdateRequest`) — walidacja biegnie przed kontrolerem, więc cudzy wpis musi dać 404 już tutaj, także przy pustym lub błędnym `target_id`.

#### 4. Akcja scalania

**File**: `app/Actions/Localities/MergeLocality.php` (nowy)

**Intent**: Przenosi każde odwołanie duplikatu na właściwą miejscowość, redukuje identyczne zamieszkania, usuwa duplikat i uzupełnia opis — atomowo.

**Contract**: `handle(Locality $duplicate, Locality $target): Locality` (zwraca `$target`). W jednej transakcji:
1. `lockForUpdate` obu wierszy w kolejności id; ponowne sprawdzenie `tree_id` obu (inaczej `ValidationException` na `target_id`).
2. `UPDATE` z `locality_id = duplikat` na właściwy w `parishes`, `events`, `residences` oraz `cemetery_locality_id` w `sources` — przez relacje duplikatu (`$duplicate->parishes()->update(...)` itd.).
3. Redukcja zamieszkań: wśród wierszy `residences` z `locality_id = właściwy` usuń każdy, dla którego istnieje wiersz o niższym id z tym samym `person_id`, `year_from` i `year_to` (porównanie `IS NOT DISTINCT FROM`, bo lata bywają `NULL`).
4. Usunięcie duplikatu.
5. Opis: patrz Critical Implementation Details. `handle()` czyta opis obu wpisów z modeli zwróconych przez `lockForUpdate`, nie z modeli związanych z trasą przed transakcją.

```sql
DELETE FROM residences r USING residences keep
WHERE r.locality_id = :target AND keep.locality_id = :target
  AND keep.person_id = r.person_id AND keep.id < r.id
  AND keep.year_from IS NOT DISTINCT FROM r.year_from
  AND keep.year_to IS NOT DISTINCT FROM r.year_to
```

#### 5. Akcja usuwania

**File**: `app/Actions/Localities/DeleteLocality.php` (nowy)

**Intent**: Usuwa tylko miejscowość bez żadnego odwołania.

**Contract**: `handle(Locality $locality): void`. W transakcji: `lockForUpdate` wiersza, suma czterech relacji `exists()`/`count()`; > 0 → `ValidationException::withMessages(['locality' => __('Miejscowość jest używana — scal ją z inną zamiast usuwać.')])`. `QueryException` z kodem `23503` przy `delete()` → ten sam wyjątek.

#### 6. Kontrolery i trasy

**File**: `app/Http/Controllers/LocalityController.php`, `app/Http/Controllers/LocalityMergeController.php` (nowy, jednoakcyjny), `routes/web.php`

**Intent**: Wystawia usuwanie i scalanie zgodnie ze ścieżką zapisu z CLAUDE.md.

**Contract**:
- `Route::resource('localities', …)->only([..., 'destroy'])`; `LocalityController::destroy(Locality, DeleteLocality)`: `Gate::authorize('delete')`, akcja, toast „Usunięto miejscowość”, `to_route('localities.index')`.
- `Route::post('localities/{locality}/merge', LocalityMergeController::class)->name('localities.merge')`; `__invoke(LocalityMergeRequest, Locality $locality, MergeLocality)`: `Gate::authorize('delete', $locality)`, target pobrany przez `$request->user()->tree->localities()->findOrFail(...)`, akcja, toast „Scalono z miejscowością „{nazwa}””, `to_route('localities.index', ['locality' => $target->id])`.
- `vendor/bin/sail artisan wayfinder:generate`.

#### 7. Testy

**File**: `tests/Feature/LocalitiesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/DeleteImpactTest.php`

**Intent**: Pokrycie każdego rodzaju odwołania, granic drzewa i reguły „tylko nieużywane”.

**Contract**:
- `LocalitiesTest`: goście przekierowani z `destroy` i `merge`; `usage` w propsach listy i panelu (po jednym odwołaniu każdego rodzaju → liczniki i `total`); scalenie przepina siedzibę parafii, cmentarz nagrobka, miejsce urodzenia/ślubu/zgonu/pochowania i zamieszkanie, a odwołania innej miejscowości i innego drzewa zostają nietknięte; redukcja zamieszkań (identyczne lata → jeden wiersz, `NULL`/`NULL` też; różne lata lub inna osoba → oba zostają); opis: pusty → duplikatu, różne → złączone, równe → bez zmian, duplikat pusty → bez zmian; nazwa właściwego bez zmian; `target_id` z cudzego drzewa, nieistniejący i równy duplikatowi → błąd walidacji, nic nie zmienione; usunięcie nieużywanej → znika; usunięcie używanej (każdy z czterech rodzajów osobno) → błąd `locality`, wpis zostaje.
- `OwnershipBoundariesTest`: przypadki `localities.destroy` (`delete`) i `localities.merge` (`post`, body z `target_id` miejscowości napastnika; harness dodaje też przypadek „pusta treść” — też 404) — nic nie zapisane. `ownershipRecords` dostaje `ownLocality` i `ownParish` (cel scalenia napastnika).
- `DeleteImpactTest`: scalenie miejscowości usuwa dokładnie wiersz duplikatu i zredukowane zamieszkania; zbiór `changed` równa się przepiętym wierszom (`parishes`, `sources`, `events`, `residences`) plus właściwy wpis przy uzupełnionym opisie; `added` puste; usunięcie nieużywanej — tylko jej wiersz.

### Success Criteria:

#### Automated Verification:

- Testy miejscowości przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/DeleteImpactTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Faza 2: Backend — parafie

### Overview

To samo dla parafii: liczba użyć, scalanie z przepięciem źródeł i usuwanie nieużywanej.

### Changes Required:

#### 1. Liczba użyć

**File**: `app/Http/Controllers/ParishController.php`, `app/Http/Resources/ParishResource.php`

**Intent**: Spis i panel parafii pokazują liczbę aktów, które ją wskazują.

**Contract**: `index` ładuje `withCount('sources')` dla listy i `parishPanel`; `sources_count` już jest w zasobie (`ParishResource.php:30`).

#### 2. Polityka, żądanie, akcje

**File**: `app/Policies/ParishPolicy.php`, `app/Http/Requests/ParishMergeRequest.php`, `app/Actions/Parishes/MergeParish.php`, `app/Actions/Parishes/DeleteParish.php` (nowe)

**Intent**: Jak w Fazie 1, dla jednego rodzaju odwołania.

**Contract**:
- `ParishPolicy::delete` jak `update`.
- `ParishMergeRequest`: `target_id` — parafia drzewa (`Rule::exists` z `tree_id`), `Rule::notIn([$parish->id])`; komunikaty „Wybierz parafię ze spisu.”, „Wybierz inną parafię niż scalana.”; `authorize(): Response` = `Gate::inspect('delete', $this->route('parish'))` (jak `ParishUpdateRequest`), by cudzy wpis dawał 404 przed walidacją.
- `MergeParish::handle(Parish $duplicate, Parish $target): Parish` — blokady i kontrola drzewa jak `MergeLocality`; `sources.parish_id` duplikatu → właściwa; usunięcie duplikatu; potem uzupełnienie: `locality_id` gdy pusta, `religion` gdy pusta i brak kolizji (Critical Implementation Details). Nazwa właściwej bez zmian. Siedzibę i wyznanie `handle()` czyta z modeli zwróconych przez `lockForUpdate`.
- `DeleteParish::handle(Parish)` — `sources()` niepuste → `ValidationException` `['parish' => __('Parafia jest używana — scal ją z inną zamiast usuwać.')]`; `23503` → ten sam wyjątek.

#### 3. Kontrolery i trasy

**File**: `app/Http/Controllers/ParishController.php`, `app/Http/Controllers/ParishMergeController.php` (nowy), `routes/web.php`

**Contract**: `parishes.destroy` (toast „Usunięto parafię”, `to_route('parishes.index')`); `POST parishes/{parish}/merge` → `parishes.merge` (toast „Scalono z parafią „{etykieta}””, `to_route('parishes.index', ['parish' => $target->id])`). `wayfinder:generate`.

#### 4. Testy

**File**: `tests/Feature/ParishesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/DeleteImpactTest.php`

**Contract**:
- `ParishesTest`: goście; `sources_count` w liście i panelu; scalenie przepina wszystkie akty duplikatu, akty innej parafii i innego drzewa nietknięte; siedziba: pusta → duplikatu, ustawiona → bez zmian; wyznanie: puste → duplikatu, puste przy kolizji z trzecią parafią (ta sama nazwa i wyznanie) → zostaje puste bez błędu, ta sama nazwa co duplikat z wyznaniem → przejęte (dowód kolejności usunięcia); różne wyznania obu → scalenie dozwolone, właściwa zachowuje swoje; walidacja `target_id` (cudza, nieistniejąca, ta sama); usunięcie nieużywanej i odmowa dla używanej.
- `OwnershipBoundariesTest`: `parishes.destroy`, `parishes.merge`.
- `DeleteImpactTest`: scalenie parafii usuwa tylko wiersz duplikatu; zbiór `changed` równa się przepiętym aktom (`sources`) plus właściwa parafia przy uzupełnionych polach; `added` puste.

### Success Criteria:

#### Automated Verification:

- Testy parafii przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ParishesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/DeleteImpactTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Faza 3: Frontend — kolumna „Użycia”, scalanie i usuwanie w panelach

### Overview

Kolumna „Użycia” w obu tabelach, przyciski „Scal z…” i „Usuń” w panelach oraz modale scalania i usuwania.

### Changes Required:

#### 1. Typy i tekst użyć

**File**: `resources/js/types/locality.ts`, `resources/js/types/parish.ts`, `resources/js/lib/places-usage.ts` (nowy)

**Intent**: Jedno miejsce, które zamienia liczniki na polski tekst z odmianą.

**Contract**: `Locality.usage?: { facts; parishes; residences; gravestones; total }`; `Parish.sources_count?: number`. `localityUsagePhrase(usage): string` → np. „3 faktach, 1 parafii, 2 zamieszkaniach i 1 nagrobku” (tylko niezerowe, z `pluralPhrase` z `@/lib/plural`, przypadek miejscownik do „używana w: …”); `parishUsagePhrase(count)` → „4 aktach”. Wariant mianownikowy dla modalu scalania („przejdą: 3 fakty, 1 parafia…”) — oba w tym samym module.

#### 2. Kolumna „Użycia”

**File**: `resources/js/components/localities/localities-table.tsx`, `resources/js/components/parishes/parishes-table.tsx`

**Intent**: Autor widzi nieużywane wpisy i dublety o małej liczbie użyć bez otwierania paneli.

**Contract**: kolumna „Użycia” (wyrównana do prawej, `tabular-nums`) z `usage.total` / `sources_count`; zero pokazane jako „0”.

#### 3. Modal scalania

**File**: `resources/js/components/localities/merge-locality-dialog.tsx`, `resources/js/components/parishes/merge-parish-dialog.tsx` (nowe)

**Intent**: Wybór właściwego wpisu i potwierdzenie z wyraźnym kierunkiem.

**Contract**: `Dialog` (`ui/dialog.tsx`), tytuł „Scal „{nazwa duplikatu}” z…”. `SuggestField` w trybie `record` bez `onCreate`, etykieta „Właściwa miejscowość” / „Właściwa parafia”, opcje = spis ze strony bez scalanego wpisu (parafie z `label`). Opis: „„{duplikat}” zniknie ze spisu. Jej odwołania ({tekst mianownikowy lub „brak”}) przejdą na wybraną miejscowość. Puste pola wybranej uzupełnią się danymi „{duplikat}”. Tej operacji nie można cofnąć.” Przycisk „Scal” nieaktywny bez wyboru i w trakcie; `router.post(LocalityMergeController(id).url, { target_id })`; błąd walidacji `target_id` pod polem; sukces zamyka modal (przekierowanie otwiera panel właściwego).

#### 4. Dialog usuwania

**File**: `resources/js/components/localities/delete-locality-dialog.tsx`, `resources/js/components/parishes/delete-parish-dialog.tsx` (nowe)

**Intent**: Usuwanie nieużywanego wpisu; dla używanego wyjaśnienie i droga do scalenia.

**Contract**: wzorzec `delete-source-dialog.tsx`. `total === 0`: „Usunąć miejscowość?” / „Miejscowość „{nazwa}” zostanie usunięta ze spisu.” + „Anuluj” / „Usuń” (`router.delete(LocalityController.destroy(id).url)`). `total > 0`: „Nie można usunąć” / „Miejscowość „{nazwa}” jest używana w: {tekst miejscownikowy}. Scal ją z właściwą, aby przenieść te odwołania.” + „Zamknij” / „Scal z…” (zamyka ten dialog i otwiera modal scalania). Błąd serwera (wyścig) pokazany w dialogu (`onError`). Dialog jest kontrolowany (`open`/`onOpenChange` z panelu, bez własnego `DialogTrigger`) i dostaje `onMerge`, którym panel zamyka go i otwiera modal scalania.

#### 5. Przyciski w panelach

**File**: `resources/js/components/localities/locality-panel.tsx`, `resources/js/components/parishes/parish-panel.tsx`, `resources/js/pages/localities/index.tsx`, `resources/js/pages/parishes/index.tsx`

**Intent**: Akcje dostępne z panelu istniejącego wpisu.

**Contract**: obok „Zapisz” (tylko gdy rekord istnieje): „Scal z…” (`variant="outline"`) i „Usuń” (`variant="destructive"` lub `ghost` z tekstem destrukcyjnym), wyłączone przy `isDirty` (Critical Implementation Details). Przyciski mają `type="button"` (stoją w render-propie `Form`); panel trzyma stan otwarcia obu dialogów, a dialogi renderuje poza `<Form>`, obok niego w `SidePanel`. Strony przekazują do paneli listę wpisów (`localities` / `parishes`) dla modalu scalania.

#### 6. Testy Vitest

**File**: `resources/js/lib/places-usage.test.ts` (nowy)

**Contract**: odmiana (1 / 2–4 / 5+ / 12–14 / 22), pomijanie zer, łączenie „i” przed ostatnim, oba przypadki gramatyczne, `total === 0`.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`

#### Manual Verification:

- Kolumna „Użycia” w `/localities` i `/parishes` zgadza się z liczbą odwołań; nowo dodany wpis ma 0
- Scalenie „Lubomla” → „Luboml” z panelu „Lubomla”: modal pokazuje liczby, po „Scal” otwiera się panel „Luboml” z toastem, „Lubomla” znika ze spisu, fakty, zamieszkania i siedziby parafii pokazują „Luboml”
- „Usuń” przy używanym wpisie pokazuje miejsca użycia i „Scal z…” otwiera modal scalania; przy nieużywanym usuwa i zamyka panel
- Przy niezapisanej zmianie w formularzu panelu „Scal z…” i „Usuń” są nieaktywne
- Układ przy 1366 px: przyciski panelu mieszczą się w jednym wierszu lub zawijają czytelnie

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Faza 4: E2E (`/10x-e2e`)

### Overview

Sprzątanie miejscowości i parafii w E2E oraz test przepływu scalania z panelu duplikatu.

### Changes Required:

#### 1. Sprzątanie po sufiksie

**File**: `tests/e2e/support/api.ts`, `context/foundation/test-stack.md`

**Intent**: Zamyka wyjątek od zasady czyszczenia — baza `e2e` przestaje rosnąć o parafie i miejscowości z każdego uruchomienia.

**Contract**: `cleanupBySuffix` po źródłach i osobach usuwa parafie (`pageProps('/parishes').parishes`, nazwa zawiera sufiks), potem miejscowości (`/localities`) przez `deleteRecord` (DELETE sprawdzany). Kolejność wynika z FK: źródła → osoby (zdarzenia, zamieszkania) → parafie → miejscowości. Aktualizacja komentarza funkcji oraz JSDoc `createLocality` i `createParish` („no delete route until S-11”). Teardown jest ścisły, więc przed pełną suitą uruchom każdą z sześciu specyfikacji z miejscowościami (`residence-history`, `source-panel-parish`, `fact-places-from-gazetteer`, `source-found-by-place-and-year`, `gravestone-cemetery-and-buried`, `fact-source-scan`) osobno; 422 z DELETE oznacza rekord bez sufiksu, który nadal używa wpisu spisu — popraw kolejność lub nazwę w tej specyfikacji. Linia „known cleanup exception” w `test-stack.md:18` zostaje usunięta (sekcję prowadzi `/10x-e2e-setup` — to jedyna zmiana, odnotowana w commicie).

#### 2. Test scalania

**File**: `tests/e2e/places-merge-and-delete.spec.ts` (nowy)

**Intent**: Ryzyko przeglądarkowe: kierunek scalania w modalu i stan po przekierowaniu.

**Contract**: setup przez API (`createLocality` ×2: „Lubomla-{suffix}” i „Luboml-{suffix}”, `createParish` z siedzibą „Lubomla-{suffix}”); UI: `/localities?locality=<duplikat>` → „Scal z…” → wybór „Luboml-{suffix}” → „Scal”; asercje: otwarty panel ma tytuł „Luboml-{suffix}”, wiersz „Lubomla-{suffix}” nie istnieje, a świeże żądanie panelu parafii pokazuje siedzibę „Luboml-{suffix}” (wyrocznia na danych). Lokatory `getByRole`/`getByLabel`/`getByText`, bez `waitForTimeout`, sprzątanie przez `cleanupBySuffix`. Weryfikacja celowym błędem: np. zamiana miejscami `duplicate`/`target` w kontrolerze musi przewrócić asercję.

### Success Criteria:

#### Automated Verification:

- Nowa specyfikacja przechodzi: `npx playwright test tests/e2e/places-merge-and-delete.spec.ts`
- Specyfikacja z dotychczasowym wyjątkiem przechodzi i sprząta: `npx playwright test tests/e2e/source-panel-parish.spec.ts`
- Cała suita E2E przechodzi: `npx playwright test`

#### Manual Verification:

- Po pełnym uruchomieniu E2E w bazie `e2e` nie ma miejscowości ani parafii z prefiksem testowym

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Vitest: `places-usage` — odmiana i składanie tekstu użyć.

### Integration Tests:

- Pest: scalanie każdego rodzaju odwołania (siedziba parafii, cmentarz, fakty z pochowaniem, zamieszkania; akty parafii), redukcja identycznych zamieszkań, uzupełnianie pól z kolizją wyznania, walidacja celu, „tylko nieużywane”, 404 cudzego rekordu (`OwnershipBoundariesTest`), dokładny zbiór usuniętych wierszy (`DeleteImpactTest`).
- E2E: przepływ scalania z panelu duplikatu; sprzątanie spisu w każdej specyfikacji.

### Manual Testing Steps:

1. Utwórz „Lubomla” z opisem i „Luboml” bez opisu; podepnij „Lubomla” jako miejsce urodzenia, zamieszkanie, siedzibę parafii i cmentarz nagrobka.
2. Scal „Lubomla” z „Luboml” z panelu „Lubomla”; sprawdź panel „Luboml” (opis przejęty), osobę, parafię i nagrobek.
3. Spróbuj usunąć „Luboml” — dialog wymienia użycia; usuń nową, nieużywaną miejscowość.
4. Powtórz scalenie i usunięcie dla parafii, w tym parafii o różnych wyznaniach.

## Performance Considerations

`withCount` czterech relacji na liście miejscowości to cztery podzapytania skorelowane po indeksowanych kolumnach (`events.locality_id`, `residences.locality_id` mają indeksy; `parishes.locality_id` i `sources.cemetery_locality_id` — przy setkach wpisów pomijalne). Scalanie to kilka masowych `UPDATE` w jednej transakcji.

## Migration Notes

Bez migracji schematu. Scalanie i usuwanie zmieniają dane produkcyjne tylko na żądanie autora; kopia `backup:run --only-db` w `scripts/deploy.sh` jest jedyną drogą odwrotu po scaleniu.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-11, uwagi o zamieszkaniach i E2E)
- PRD: `context/foundation/prd.md` (FR-012, Business Logic Changes)
- Decyzja FK `NO ACTION`: `context/archive/2026-10-05-act-described-by-parish-and-archive/plan.md:62`
- Wzorzec dialogu usuwania: `resources/js/components/sources/delete-source-dialog.tsx`
- Wzorzec kontrolera jednoakcyjnego: `app/Http/Controllers/SourceLegacyEntryNumberController.php`
- Granice własności: `tests/Feature/OwnershipBoundariesTest.php:71-172`
- Sprzątanie E2E: `tests/e2e/support/api.ts:371-404`, `context/foundation/test-stack.md:18`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend — miejscowości

#### Automated

- [x] 1.1 Testy miejscowości przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/DeleteImpactTest.php` — 5413a42
- [x] 1.2 Larastan przechodzi: `vendor/bin/sail composer types:check` — 5413a42
- [x] 1.3 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent` — 5413a42

### Phase 2: Backend — parafie

#### Automated

- [x] 2.1 Testy parafii przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ParishesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/DeleteImpactTest.php` — c879c7d
- [x] 2.2 Larastan przechodzi: `vendor/bin/sail composer types:check` — c879c7d
- [x] 2.3 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent` — c879c7d

### Phase 3: Frontend — kolumna „Użycia”, scalanie i usuwanie w panelach

#### Automated

- [x] 3.1 Vitest przechodzi: `vendor/bin/sail npm run test:js`
- [x] 3.2 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [x] 3.3 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [x] 3.4 Build przechodzi: `vendor/bin/sail npm run build`

#### Manual

- [x] 3.5 Kolumna „Użycia” w `/localities` i `/parishes` zgadza się z liczbą odwołań; nowo dodany wpis ma 0
- [x] 3.6 Scalenie „Lubomla” → „Luboml” z panelu „Lubomla”: modal pokazuje liczby, po „Scal” otwiera się panel „Luboml” z toastem, „Lubomla” znika ze spisu, fakty, zamieszkania i siedziby parafii pokazują „Luboml”
- [x] 3.7 „Usuń” przy używanym wpisie pokazuje miejsca użycia i „Scal z…” otwiera modal scalania; przy nieużywanym usuwa i zamyka panel
- [x] 3.8 Przy niezapisanej zmianie w formularzu panelu „Scal z…” i „Usuń” są nieaktywne
- [x] 3.9 Układ przy 1366 px: przyciski panelu mieszczą się w jednym wierszu lub zawijają czytelnie

### Phase 4: E2E (`/10x-e2e`)

#### Automated

- [ ] 4.1 Nowa specyfikacja przechodzi: `npx playwright test tests/e2e/places-merge-and-delete.spec.ts`
- [ ] 4.2 Specyfikacja z dotychczasowym wyjątkiem przechodzi i sprząta: `npx playwright test tests/e2e/source-panel-parish.spec.ts`
- [ ] 4.3 Cała suita E2E przechodzi: `npx playwright test`

#### Manual

- [ ] 4.4 Po pełnym uruchomieniu E2E w bazie `e2e` nie ma miejscowości ani parafii z prefiksem testowym
