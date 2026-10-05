---
date: 2026-10-05T09:53:57+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: 9185e390e48c34eaafbfe689c66f90c31e195b3f
branch: develop
repository: root-keeper
topic: "S-01 places-gazetteer — na czym zbudować spis miejscowości (FR-007, FR-029) i jak zasiać go z istniejących tekstowych miejsc"
tags: [research, codebase, localities, gazetteer, data-migration, side-panel, ownership, events, people]
status: complete
last_updated: 2026-10-05
last_updated_by: Claude (Opus 5.5)
---

# Research: spis miejscowości z istniejących miejsc (S-01)

**Date**: 2026-10-05T09:53:57+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: 9185e390e48c34eaafbfe689c66f90c31e195b3f
**Branch**: develop
**Repository**: root-keeper

## Research Question

Co w obecnym kodzie i dokumentach dotyka S-01 (`context/foundation/roadmap.md` — „Spis miejscowości z istniejących miejsc”): autor otwiera spis miejscowości, który powstał automatycznie z istniejących tekstowych miejsc (identyczne teksty → jedna miejscowość), dodaje i poprawia miejscowości z opisem w panelu bocznym; cudza miejscowość to 404 (FR-007 `prd.md:115`, FR-029 `prd.md:187`). Gdzie dziś leżą tekstowe miejsca, jaki istniejący moduł skopiować, jak użyć powłoki panelu z F-02, które testy przewrócą się same i jakie decyzje są otwarte.

## Summary

- **Tekstowe miejsca to dokładnie dwie kolumny objęte PRD**: `events.place` (`database/migrations/2026_09_23_045723_create_events_table.php:22`) dla urodzenia, zgonu i ślubu oraz `people.residence` (`database/migrations/2026_09_23_045722_create_people_table.php:19`); obie `string` nullable, bez indeksu. PRD wskazuje jako źródło zasiewu właśnie „miejsca wydarzeń i zamieszkanie” (`prd.md:205`). Trzecia tekstowa kolumna, `sources.provenance` (`2026_09_30_072040_add_description_to_sources_table.php:20`), w sprawdzonych danych i seederze zawiera nazwy archiwów, nie miejscowości — poza zasiewem.
- **Typy wydarzeń to birth, death, marriage** (`app/Enums/EventType.php`); nie ma kolumny ani wydarzenia pochowania — miejsce pochowania dochodzi dopiero w S-07.
- **Zapisy przez HTTP są przycinane i `''` → NULL** (domyślne globalne `TrimStrings`/`ConvertEmptyStringsToNull`; `bootstrap/app.php:18-25` ich nie usuwa) i walidowane `nullable|string|max:255` (`app/Concerns/PartialDateValidationRules.php:26`, `app/Http/Requests/PersonStoreRequest.php:34`). Seedery, fabryki i tinker tego nie przechodzą.
- **Lokalne dane (read-only SELECT, baza `laravel`)**: `events` 65 wierszy, 9 z miejscem, 8 różnych wartości — tyle samo po `lower(btrim())`; `people.residence` 1 wartość niepusta; zero pustych napisów i zero nieprzyciętych. Jedyne scalenie identycznych tekstów lokalnie: „Kraków” ×2 w drzewie 17 (dane `TreeViewDemoSeeder`). Danych produkcyjnych nie sprawdzano.
- **Wzorzec do skopiowania**: moduł Sources dla warstw backendu i strony listy (`routes/web.php:25`, `SourceController`, `SourceStoreRequest`/`SourceUpdateRequest`, `app/Actions/Sources/*`, `SourcePolicy`, `SourceResource`, `Tree::sources()` `app/Models/Tree.php:61-64`) oraz demo `/dev/side-panel` dla panelu (`SidePanelDemoController.php:20-37`, `resources/js/pages/dev/side-panel.tsx`, `tests/Feature/SidePanelDemoTest.php`).
- **Powłoka panelu działa na każdej stronie z `AppLayout`** — slot stoi w layoucie (`layouts/app/app-sidebar-layout.tsx:40-47`), nie w widoku drzewa. Stan otwarcia trzyma URL: rejestr `PANEL_PROPS` w `resources/js/lib/side-panel-url.ts:6-9` ma dziś klucze `person` i `demo`, a komentarz nad nim (`:1-5`) zapowiada wpis S-01. S-01 jest **pierwszym prawdziwym konsumentem** powłoki z formularzem; przepływ „formularz w panelu → `back()` → odświeżony panel” nie był jeszcze uruchomiony.
- **W `database/migrations` nie ma żadnego unikalnego indeksu z `tree_id`** (grep `unique(`: tylko pivoty `event_scan`, `event_participants`, `parent_child_relations`, `trees.user_id` i kolumny systemowe), **a w `app/` jedyne `Rule::unique` dotyczy e-maila** (`app/Concerns/ProfileValidationRules.php:59-60`). S-01 ustanawia wzorzec unikalności w drzewie.
- **Dwa testy przewrócą się same po dodaniu tabeli i tras**: strażnik kompletności tras w `tests/Feature/OwnershipBoundariesTest.php:136-151` (każda trasa kontrolera z `{param}` musi mieć przypadek w `ownershipRouteCases()`) oraz `tests/Backup/BackupRestoreTest.php:26` (każda trwała tabela musi mieć wiersze, a tabele trwałe są brane ze schematu — `tests/BackupTestCase.php:260`), więc `arrangeBackupData()` (`tests/BackupTestCase.php:127`) musi dodać miejscowość.
- **Reguły z CLAUDE.md już obowiązują** dla tej zmiany: panel boczny zamiast modala (`CLAUDE.md:55-60`), migracja przenosząca dane z własnym `tests/Feature/<Name>MigrationTest.php`, kopiuj-potem-usuń, odwracalność (`CLAUDE.md:62-67`), spis unikalny w drzewie z Policy `denyAsNotFound()` (`CLAUDE.md:77-79`).
- **Otwarte decyzje (szczegóły w Open Questions)**: pola poza nazwą i opisem; czy dwie miejscowości mogą mieć tę samą nazwę; co znaczy „identyczny tekst”; jak S-07/S-08 odnajdą miejscowość dla tekstu po zmianie nazwy w S-01; co z tekstowymi miejscami wpisanymi po wdrożeniu S-01; klucz URL panelu (`place` vs `locality`) i sposób otwarcia panelu „nowa miejscowość”.

## Detailed Findings

### 1. Tekstowe miejsca dziś — schemat, zapis, odczyt

| Kolumna | Znaczenie (etykieta UI) | Migracja | Model |
|---|---|---|---|
| `events.place` | „Miejsce” urodzenia, zgonu, ślubu | `2026_09_23_045723_create_events_table.php:22` | `app/Models/Event.php:24,29` |
| `people.residence` | „Miejsce zamieszkania” / „Zamieszkanie” | `2026_09_23_045722_create_people_table.php:19` | `app/Models/Person.php:25,30` |
| `sources.provenance` (poza zasiewem) | „Pochodzenie” — archiwum/sygnatura | `2026_09_30_072040_add_description_to_sources_table.php:20` | `app/Models/Source.php:23,36` |

- Wydarzenie należy do drzewa przez `events.tree_id` (`create_events_table.php:16`), osoba przez `people.tree_id` (`create_people_table.php:16`) — zasiew może grupować per `tree_id` bez złączeń.
- **Zapis**: jedno mapowanie wejścia formularza na `place` dla wszystkich wydarzeń — `app/Actions/Persons/MapsEventInput.php:23`; zamieszkanie zapisują `CreatePerson.php:52` i `UpdatePerson.php:57`; ślub `CreateMarriage.php:43-46`, `UpdateMarriage.php:20`. Walidacja miejsca wydarzenia: `PartialDateValidationRules.php:26` (używana w `PersonStoreRequest.php:24-39`, `MarriageStoreRequest.php:26`, `MarriageUpdateRequest.php:40`); zamieszkanie `PersonStoreRequest.php:34`, dziedziczone przez `PersonUpdateRequest`, `PersonChildStoreRequest`, `PersonParentStoreRequest`, `PersonSpouseStoreRequest` (raport pracownika; kotwice `:10`, `:11`, `:12`, `:8`).
- **Odczyt**: `EventResource.php:41`, `PersonResource.php:50`, `PersonFormResource.php:46,68`; front: pole „Miejsce” `resources/js/components/persons/partial-date-input.tsx:110-118`, zamieszkanie `person-section-fields.tsx:151-158`, lista osób `persons-table.tsx:79-81,164-165`, `lib/persons-index.ts:192-199`. Wyszukiwanie osób (`PersonController.php:39-51`) nie przeszukuje miejsc.
- **Fabryki/seedery**: `EventFactory.php:29` i `PersonFactory.php:27` dają `fake()->city()` (locale `en_US`); `TreeViewDemoSeeder.php` wpisuje polskie nazwy (np. `:137`, `:224`, `:233`, `:243`). S-01 nie musi ich zmieniać, dopóki kolumny tekstowe zostają.

### 2. Dane lokalne a reguła „identyczne teksty → jedna miejscowość”

- Na tej sprawdzonej bazie lokalnej (4 drzewa, dane w drzewach 3 i 17): grupowanie dokładne i grupowanie po `lower(btrim())` dają te same zbiory — brak wariantów wielkości liter, spacji i pustych napisów; NULL to jedyny brak wartości.
- Zapisy przez HTTP są przycinane, więc warianty spacji z UI są mało prawdopodobne; warianty wielkości liter i diakrytyków („Krakow”/„Kraków”) są możliwe i reguła PRD ich nie łączy — PRD zostawia je ręcznemu scalaniu (`prd.md:221`; scalanie to S-11, do tego czasu poprawka nazwy w spisie — roadmap S-11 „Risk”).
- PostgreSQL porównuje napisy z rozróżnieniem wielkości liter, więc `unique(tree_id, name)` bez dodatkowej normalizacji dopuści „Kraków” i „kraków” jako dwa wpisy.

### 3. Wzorzec modułu należącego do drzewa (Sources)

- Trasy: `Route::resource('sources', …)->only([...])` w grupie `auth, verified` (`routes/web.php:18-43`, `:25`).
- Kontroler: `index` czyta `$request->user()->tree->sources()` i przekazuje listę przez `SourceResource::collection(...)->resolve()` (`SourceController.php:31-47`); `store` → Action → `Inertia::flash('toast', …)` → `to_route` (`:93-105`); `update` kończy się `back()` (`:138-149`) — to jest mechanizm odświeżenia otwartego panelu.
- Autoryzacja aktualizacji w Form Request: `SourceUpdateRequest.php:23-26` (`Gate::inspect('update', $this->route('source'))`); `test-plan.md:174` wymaga autoryzacji w `authorize()` Form Requesta.
- Policy: `SourcePolicy.php:12-37` — `tree_id === $user->tree->id ? allow : denyAsNotFound()`; polityki są wykrywane automatycznie.
- Strona listy: `resources/js/pages/sources/index.tsx` (wyszukiwanie z debounce, filtrowanie po stronie klienta w `lib/sources-index.ts`, puste stany, breadcrumbs `:258`); prostszy wzór tabeli: `components/persons/persons-table.tsx` + `lib/persons-index.ts`. Wspólne klocki: `components/search-field.tsx`, `table-parts.tsx`, `empty-state.tsx`, `lib/table-sort.ts`.
- Formularze: Inertia `<Form {...SourceController.update.form(id)} options={{preserveScroll:true}}>` (`components/sources/source-edit-dialog.tsx:134-160`).
- Nawigacja: pozycje sidebaru w `resources/js/components/app-sidebar.tsx:27-45` (Drzewo, Osoby, Źródła); licznik w `navCounts` (`HandleInertiaRequests.php:50-53`) wymagałby zmiany typu (`resources/js/types/global.d.ts:17`) i asercji w `tests/Feature/AppShellTest.php`.

### 4. Powłoka panelu bocznego (F-02) i co S-01 do niej dokłada

- Komponent `resources/js/components/side-panel.tsx`: `SidePanel` z propsami `title`, `description?`, `variant?: 'standard'|'wide'`, `contentKey?`, `onRequestClose`, `actions?`, `footer?` (`:54-66`); `role="dialog" aria-modal="false"` nazwany tytułem (`:152-158`); przycisk „Zamknij panel” (`:191`). Wariant `standard` przewidziano dla miejscowości (`context/archive/2026-10-04-side-panel-shell/research.md:117`).
- URL: `withPanel` (`side-panel-url.ts:32-50`) trzyma najwyżej jeden klucz panelu; `panelVisitOptions` (`:14-24`) robi częściowe przeładowanie wszystkich właściwości z rejestru. Hook `resources/js/hooks/use-side-panel-navigation.ts:18-37` daje `hrefFor`/`open`/`close`.
- Serwer: właściwość strony jako closure — brak lub nienumeryczny klucz → `null`, nieznane id → 404 (`SidePanelDemoController.php:20-37`); wzór z własnością i memoizacją: `previewPerson()` w `HandleInertiaRequests.php:71-91` (`$user->tree->people()->findOrFail()` + `Gate::authorize('view', …)`).
- **Do rozstrzygnięcia przez S-01** (nie zrobione w F-02):
  - wpis w `PANEL_PROPS` (zmienia też `side-panel-url.test.ts`) i resolver po stronie serwera (`side-panel-shell/plan.md:75`);
  - klucz URL: plan-brief F-02 zarezerwował `?place=` (`side-panel-shell/plan-brief.md:23`), a CLAUDE.md i roadmapa mówią „locality” (`CLAUDE.md:57`);
  - otwarcie panelu bez rekordu („Dodaj miejscowość”): demo przyjmuje tylko cyfry (`SidePanelDemoController.php:24`), więc `?locality=new` wymaga osobnej gałęzi resolvera; alternatywą jest stan lokalny strony;
  - czy `store` przekierowuje na `index?<klucz>={id}`;
  - pierwszy E2E panelu (`getByRole('dialog', { name })`) przypada pierwszemu konsumentowi — S-01 lub S-02 (`side-panel-shell/plan.md:237`); test-plan §7 wyłącza widoki/układ z testów (`test-plan.md:233-234`) — do pogodzenia w planie;
  - odroczone z przeglądu F-02: inert strony za pełnoekranowym panelem mobilnym (`side-panel-shell/reviews/impl-review.md:52`) i Escape poza fokusem panelu (`:71`).

### 5. Testy, które S-01 musi dopisać albo które przewrócą się same

- **Własność (ryzyko #4, `test-plan.md:32,42`)**: `OwnershipBoundariesTest.php` — `ownershipRecords()` (`:20-58`) potrzebuje miejscowości ofiary; `ownershipRouteCases()` (`:67-118`) potrzebuje `localities.update` (i `destroy`, jeśli powstanie); strażnik kompletności (`:136-151`) wywali suite bez tych wpisów. Test stron-hostów z `?person=` (`:153-172`) to wzór dla przypadku obcego id w kluczu panelu na stronie spisu. Testy wstrzyknięcia `tree_id` (`:174-210`) — wzór dla `localities.store`.
- **Kopia zapasowa (ryzyko #2)**: `BackupRestoreTest.php:26` wymaga `rows > 0` dla każdej trwałej tabeli; nowa tabela trafia do porównania automatycznie (`test-plan.md:113`), więc `arrangeBackupData()` musi utworzyć miejscowość. Tabela nie jest efemeryczna, więc `EPHEMERAL_TABLES` się nie zmienia.
- **Migracja zasiewu**: reguła `CLAUDE.md:62-67` — osobny `tests/Feature/<Name>MigrationTest.php` wg `SourceMigrationTest.php` (wczytanie pliku migracji przez `require`, wiersze w starym kształcie przez `DB::table(...)->insert`, `up()`, asercje na tabelach, idempotencja przez dwukrotne `up()` `:144-145`, `down()` `:109-120`). Najbliższy analog „zasiej z istniejących danych”: `tests/Feature/TreeBackfillTest.php:6-40` (znajduje migrację przez `glob`, sprawdza brak duplikatów po ponownym uruchomieniu) dla `2026_09_23_045726_create_trees_for_existing_users.php:11-27` (`DB::table` + pętla insertów, `down()` pusty).
- **Endpointy**: `tests/Feature/<Domain>Test.php` ze ścieżką szczęśliwą i walidacją wg `tests/Feature/SourcesTest.php` (gość przekierowany `:17-27`, lista tylko własnych `:33-52`, `assertInertiaFlash('toast', …)` `:564-588`, datasety walidacji `:540-562`); 404 dla cudzego rekordu jest centralnie w `OwnershipBoundariesTest`, nie w `SourcesTest`.
- **Panel**: `SidePanelDemoTest.php` (otwarcie, nieznane id 404, zniekształcone id → panel zamknięty) — wzór testu resolvera.

### 6. Komponent wyboru miejscowości (dla następnych wycinków)

- W sprawdzonym `resources/js` nie ma comboboxa/autocomplete (brak cmdk, Popover, `role=combobox`). Najbliższe: `components/tree/pick-or-create-person-dialog.tsx` (lista kandydatów + przełącznik na formularz nowego rekordu) i `components/sources/source-picker-dialog.tsx:57-86` (optional prop + `router.post`). CLAUDE.md każe małe pickery trzymać w modalu (`CLAUDE.md:57`). S-01 wybierania miejscowości nie potrzebuje — to zakres S-03/S-07/S-08.

## Code References

- `database/migrations/2026_09_23_045723_create_events_table.php:22` — `events.place` string nullable
- `database/migrations/2026_09_23_045722_create_people_table.php:19` — `people.residence` string nullable
- `app/Actions/Persons/MapsEventInput.php:23` — jedyne mapowanie miejsca wydarzenia
- `app/Concerns/PartialDateValidationRules.php:26` — walidacja miejsca wydarzenia
- `app/Http/Controllers/SourceController.php:31-163` — wzorzec CRUD (index/store/show/update/destroy)
- `app/Http/Requests/SourceUpdateRequest.php:23-26` — autoryzacja 404 w Form Request
- `app/Policies/SourcePolicy.php:12-37` — Policy z `denyAsNotFound()`
- `app/Models/Tree.php:61-64` — relacja `sources()`, obok niej `localities()`
- `app/Http/Middleware/HandleInertiaRequests.php:50-91` — `navCounts`, resolver `personPreview`
- `app/Http/Controllers/SidePanelDemoController.php:20-37` — resolver panelu (null / 404)
- `resources/js/components/side-panel.tsx:54-191` — API powłoki panelu
- `resources/js/lib/side-panel-url.ts:1-50` — rejestr `PANEL_PROPS`, `withPanel`
- `resources/js/hooks/use-side-panel-navigation.ts:18-37` — otwieranie/zamykanie panelu
- `resources/js/layouts/app/app-sidebar-layout.tsx:40-47` — slot panelu obok `AppContent`
- `resources/js/components/app-sidebar.tsx:27-45` — pozycje nawigacji
- `tests/Feature/OwnershipBoundariesTest.php:20-210` — rekordy ofiary, przypadki tras, strażnik kompletności
- `tests/Backup/BackupRestoreTest.php:26` — każda trwała tabela musi mieć wiersze
- `tests/BackupTestCase.php:127,260` — `arrangeBackupData()`, `durableTables()`
- `tests/Feature/SourceMigrationTest.php:14-222` — wzorzec testu migracji danych
- `tests/Feature/TreeBackfillTest.php:6-40` — wzorzec testu migracji zasiewającej

## Architecture Insights

- Własność to zawsze `tree_id` + zapytanie przez `$request->user()->tree->…()`; 404 dla cudzego rekordu daje Policy (`denyAsNotFound`) wywoływana w Form Request lub `Gate::authorize`, a resolver panelu dodatkowo `findOrFail` w relacji drzewa.
- Panel to stan URL + właściwość Inertii rozwiązywana leniwie; mutacja kończy się `back()`, żeby ta sama właściwość przeliczyła się z nowymi danymi.
- Testy przekrojowe (własność, kopia) wyprowadzają listy tras i tabel z aplikacji, więc nowy moduł nie „prześlizgnie się” — przewróci je, dopóki nie dopisze swoich przypadków.
- Migracje zasiewające w tym repo (`create_trees_for_existing_users`) używają `DB::table`, nie modeli, i są testowane wywołaniem `up()` z pliku.

## Historical Context (from prior changes)

- `context/foundation/archive/prd-v2.md:110-111,125,165,180,194` — odłożony projekt spisu: nazwa, „czy nadal istnieje”, jednostka/parafia/notatka, ręczne współrzędne, bez mapy; dawne nazwy w notatce. **Częściowo zastąpione**: obecne FR-007 to nazwa + opis (`prd.md:115`), parafia jest osobnym spisem (FR-008 `prd.md:118`), zakaz podmiotów trzecich usunięto (`prd.md:214`); reguła „edycja miejscowości zmienia ją wszędzie” pozostaje zgodna z obecnym modelem odwołań (`prd.md:219`).
- `prd-v2.md:144,182` — „istniejące tekstowe miejsca mogą zostać utracone, bez przenoszenia”. **Zastąpione**: obecne PRD zasiewa spis automatycznie (`prd.md:205`), roadmapa wymaga kopiowania, nie usuwania (S-01 Unknowns).
- `prd-v2.md:114,164` — usuwanie z listą użyć. **Zastąpione**: FR-012 i `CLAUDE.md:79` — scalanie + usuwanie tylko nieużywanych (S-11).
- `context/archive/2026-10-04-side-panel-shell/plan-brief.md:23,46` — klucz na typ panelu (`?place=` zarezerwowany), wzorzec URL → właściwość → częściowe przeładowanie. **Obowiązuje**; nazwa klucza do potwierdzenia.
- `context/archive/2026-09-29-scans-become-sources/plan.md` i `change.md` — precedens migracji danych z testem `up()`/`down()` i runbookiem wdrożenia z zapytaniami liczności przed/po (raport pracownika: `plan.md:240-247,498-503`, `change.md:14-31`).
- `context/foundation/health-check.md:134,205-208` — mówi, że reguły paneli/migracji/spisu nie są wklejone do CLAUDE.md. **Nieaktualne**: są w `CLAUDE.md:55-79`.
- `context/foundation/roadmap.md` (Backlog Handoff, wiersz S-01) — „Czeka na F-02”, Ready = no. **Nieaktualne**: F-02 ma status `done` w tabeli „At a glance”.

## Related Research

- `context/archive/2026-10-04-side-panel-shell/research.md` — powłoka panelu, wariant dla miejscowości.
- `context/archive/2026-10-05-maiden-name-and-age/research.md` — precedens: dodanie kolumny bez przepisywania wierszy nie wymaga testu migracji.

## Open Questions

1. **Pola miejscowości poza nazwą i opisem** (współrzędne, „czy nadal istnieje” z prd-v2). Owner: user; roadmapa: Block: no. Obecne PRD wymaga tylko nazwy i opisu.
2. **Czy dwie miejscowości mogą mieć tę samą nazwę** (np. dwie Lipnice w różnych gminach). `CLAUDE.md:77` mówi „unique per tree”; `prd.md:219` „każda miejscowość istnieje w drzewie raz” — to nie przesądza unikalności nazwy. Wybór wpływa na indeks `unique(tree_id, name)` i walidację. Odziedziczone niezamknięte pytanie ze starej roadmapy.
3. **Co znaczy „identyczny tekst” przy zasiewie**: dokładna równość po `btrim` (zgodne z literą PRD; lokalnie bez różnicy) czy dodatkowo bez wielkości liter. Powiązane z (2): jeśli unikalność ma być bez wielkości liter, zasiew musi grupować tak samo, inaczej indeks odrzuci wstawienie.
4. **Jak S-07 i S-08 odnajdą miejscowość dla tekstu po zmianie nazwy w S-01**. Jeśli autor poprawi „Lubomla” na „Luboml” w spisie przed S-07, dopasowanie po tekście w migracji S-07 nie trafi. Opcje dla planu: (a) S-01 zapisuje powiązanie tekst → miejscowość już teraz (np. nullable `locality_id` przy `events`/`people` wypełniane w migracji zasiewu, tekst zostaje — zgodne z „kopiuj, potem usuń”), (b) osobna tabela mapowania tekstów, (c) S-07/S-08 dopasowują po tekście i akceptują ręczne poprawki (PRD je dopuszcza, `prd.md:72`).
5. **Tekstowe miejsca wpisane po wdrożeniu S-01, a przed S-07/S-08**: formularz osoby nadal zapisuje `events.place`/`people.residence` jako tekst (`MapsEventInput.php:23`, `UpdatePerson.php:57`), więc nowe wartości nie trafią do spisu. Do decyzji: czy S-07/S-08 ponownie uruchamiają zasiew (idempotentny), czy S-01 dopisuje miejscowość przy zapisie osoby.
6. **`down()` migracji zasiewu**: usunięcie zasianych wierszy skasowałoby też poprawki autora (opis, zmienione nazwy). Wzorzec `create_trees_for_existing_users` ma pusty `down()`; CLAUDE.md wymaga wtedy komentarza z uzasadnieniem (`CLAUDE.md:66`). Migracja tworząca tabelę ma zwykły `down()`.
7. **Klucz URL i otwarcie „nowej miejscowości”**: `?place=` (rezerwacja F-02) vs `?locality=`; `?locality=new` vs stan lokalny; czy `store` otwiera panel nowego wpisu.
8. **Pierwszy E2E panelu** — w S-01 czy w S-02 (`side-panel-shell/plan.md:237` vs `test-plan.md:233-234`).
9. **Licznik w nawigacji** dla Miejscowości — opcjonalny; zmienia `navCounts`, typ i `AppShellTest`.
10. **Luka dowodowa**: dane produkcyjne nie były sprawdzane; warianty wielkości liter/diakrytyków w prawdziwych miejscach są nieznane. Zapytanie liczności przed/po w runbooku wdrożenia (precedens `scans-become-sources/change.md`) by to pokazało.
