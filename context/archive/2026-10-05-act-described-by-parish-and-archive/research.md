---
date: 2026-10-05T12:41:42+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: 5fac03e
branch: develop
repository: root-keeper
topic: "S-03 act-described-by-parish-and-archive — na czym zbudować spis parafii, pole archiwum z podpowiedziami i opis aktu parafią/rokiem/typem/archiwum (FR-001, FR-008, FR-009) oraz jak przenieść „Pochodzenie”"
tags: [research, codebase, sources, parishes, localities, archive, provenance, data-migration, side-panel, picker]
status: complete
last_updated: 2026-10-05
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje autora 1–6 (AskUserQuestion, 2026-10-05)"
---

# Research: akt opisany parafią, rokiem, typem i archiwum (S-03)

**Date**: 2026-10-05T12:41:42+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: 5fac03e
**Branch**: develop
**Repository**: root-keeper

## Research Question

Co w obecnym kodzie i dokumentach dotyka S-03 (`context/foundation/roadmap.md:148-160`): autor opisuje akt metrykalny osobnymi danymi — parafia ze spisu parafii (nazwa, wyznanie, miejscowość siedziby ze spisu; nową parafię dopisuje w locie, bez wychodzenia z panelu źródła), rok, typ aktu i archiwum jako tekst podpowiadający wcześniej wpisane archiwa; dotychczasowe „Pochodzenie” jest widoczne jako archiwum (FR-001 `prd.md:95-97`, FR-008 `prd.md:118-120`, FR-009 `prd.md:121-123`, US-01 `prd.md:76-82`). Jak wygląda dziś opis źródła, który moduł skopiować dla parafii, jak zrobić wybór z dopisaniem w locie w panelu źródła, co czyta „Pochodzenie” i które testy się przewrócą.

## Summary

- **Rok i typ aktu już istnieją** jako kolumny `sources.year` (smallint, walidacja 1000…bieżący rok) i `sources.record_type` (enum `SourceRecordType`: birth/marriage/death/other) — `database/migrations/2026_09_30_072040_add_description_to_sources_table.php:16-22`, `app/Concerns/SourceDescriptionValidationRules.php:22-30`. S-03 dokłada tylko **parafię** (nowa tabela + FK) i **archiwum** (tekst); typ aktu zostaje enumem, nie spisem.
- **„Pochodzenie” to kolumna `sources.provenance`** (string 255, nullable; `…add_description_to_sources_table.php:20`) i **dziś z założenia miesza parafię, archiwum i sygnaturę**: podpowiedź pod polem brzmi „Parafia, archiwum, sygnatura” (`resources/js/components/sources/source-description-fields.tsx:112-114`), a prd-v2 tak to pole zdefiniował (`context/foundation/archive/prd-v2.md:60`). Wartość w seederze demo to „Archiwum Diecezjalne w Tarnowie, zespół akt parafialnych, sygnatura 1234/56/78” (`database/seeders/TreeViewDemoSeeder.php:311`). Kopia 1:1 do archiwum (`prd.md:207`) przeniesie więc też ewentualne nazwy parafii i sygnatury — automatycznego wydzielenia parafii z tekstu nie da się zrobić wiarygodnie. Badanie S-01 sprawdziło, że w ówczesnych danych pole zawierało nazwy archiwów, nie miejscowości (`context/archive/2026-10-05-places-gazetteer/research.md`, Summary).
- **Spis miejscowości w kodzie nazywa się `Locality`/`localities`, nie „places”** (kolizja z `events.place`; `context/archive/2026-10-05-places-gazetteer/plan-brief.md:27`). Parafia ma gotowy wzorzec do skopiowania 1:1: migracja z unikalnym indeksem wyrażeniowym `(tree_id, lower(name))` (`database/migrations/2026_10_05_081630_create_localities_table.php:15-24`), trait walidacji z domknięciem zakresu drzewa (`app/Concerns/LocalityValidationRules.php:16-38`), akcje z transakcją i przechwyceniem `UniqueConstraintViolationException` (`app/Actions/Localities/StoreLocality.php:17-27`), Policy z `denyAsNotFound()` (`app/Policies/LocalityPolicy.php:14-29`), panel pod `?locality=` (`app/Http/Controllers/LocalityController.php:22-49`).
- **`parishes.locality_id` będzie pierwszym FK do `localities`** — dziś nic do nich nie wskazuje (`events.place`, `people.residence` nadal tekst; `app/Models/Locality.php` ma tylko relację `tree()`). Usuwania miejscowości jeszcze nie ma (`routes/web.php:30` — `only(['index','store','update'])`), więc FK nic dziś nie blokuje; S-11 musi przepinać parafie przy scalaniu.
- **Wyznanie ma gotowy enum** `App\Enums\Religion` (rzymskokatolickie, grekokatolickie, ewangelickie, prawosławne, mojżeszowe, inne; z `options()`) — `app/Enums/Religion.php:5-35`, używany dziś dla `Person.religion`.
- **W aplikacji nie ma żadnego typeahead/comboboxa ani endpointu JSON.** Grep w `resources/js`, `app/`, `routes/` nie znalazł `datalist`, `cmdk`, `Combobox`, `Popover`; w `components/ui/` brak command/popover; `package.json` nie ma `cmdk` ani `@radix-ui/react-popover`. `useHttp` (Inertia v3) użyte w jednym miejscu — `resources/js/hooks/use-two-factor-auth.ts:1,23`. Podpowiedzi archiwów z wcześniej wpisanych wartości też nie mają precedensu. Nowa zależność UI wymaga zgody autora (CLAUDE.md).
- **Istniejące pickery nie zwracają wyboru do formularza.** Wybór osoby i źródła ładują opcje leniwie (`Inertia::optional` + `router.reload({only})`, `resources/js/hooks/use-relation-candidates.ts:15-41`) i kończą się od razu `router.post` (`resources/js/components/tree/pick-or-create-person-dialog.tsx:120-139`, `resources/js/components/sources/source-picker-dialog.tsx:72-86`). S-03 potrzebuje nowego wzorca: wybór/dopisanie parafii **do niezapisanego formularza źródła** (pola niekontrolowane, `defaultValue`), bez przeładowania strony, które zresetowałoby panel.
- **Kolumna formularza w panelu źródła ma 20rem** (`resources/js/components/sources/source-panel.tsx:64-70`; dwie kolumny „skan | 20rem” od 36rem szerokości panelu). Pole wyboru parafii z akcją „dodaj” musi się zmieścić w ~320 px.
- **Testy:** szablon testu migracji przenoszącej dane to `tests/Feature/SourceMigrationTest.php` i bliższy kształtem `tests/Feature/LocalitySeedMigrationTest.php`; nowa tabela `parishes` wymaga wiersza w `tests/BackupTestCase.php::arrangeBackupData` (`tests/Backup/BackupRestoreTest.php:25-27` wymaga ≥1 wiersza w każdej trwałej tabeli); nowe trasy z `{param}` wymagają przypadku w strażniku kompletności `tests/Feature/OwnershipBoundariesTest.php:139-150`. Żaden spec E2E nie używa etykiety „Pochodzenie”; E2E przewróci się, jeśli `sources.store` zacznie wymagać parafii lub roku dla aktu (`tests/e2e/support/api.ts:164-186` tworzy akt tylko z `kind`, `name`, `file`) albo jeśli dojdzie etykieta zawierająca „Nazwa” bez `exact` (`tests/e2e/source-panel-edit.spec.ts:35,43,53`).

## Detailed Findings

### Źródła: schemat i ścieżka zapisu

- Migracje tabeli `sources` (kolejno): `2026_09_24_043434_create_scans_table.php:14-26`, `2026_09_29_100000_rename_scans_to_sources.php:16-29` (dokłada `kind`, NOT NULL), `2026_09_30_072040_add_description_to_sources_table.php:16-22` (siedem pól opisu, wszystkie nullable: `name` 255, `record_type`, `entry_number` 50, `year` unsignedSmallInteger, `provenance` 255, `url` 2048, `note` text; `down()` usuwa wszystkie siedem, :32).
- Enumy: `app/Enums/SourceKind.php` (`MetricalRecord`, `Gravestone`, `Other`), `app/Enums/SourceRecordType.php` (`Birth`, `Marriage`, `Death`, `Other`).
- Model `app/Models/Source.php`: `#[Fillable]` :36 obejmuje `provenance`, rzutowania :47-57, relacje `tree()` i `events()` (:78-84). Odczyt przez `Tree::sources()` (`app/Models/Tree.php:61`).
- Trasy: `routes/web.php:26` (`Route::resource('sources')` — index, store, show, update, destroy), :32-33 (`events.sources.*`).
- Walidacja wspólna: `app/Concerns/SourceDescriptionValidationRules.php:22-30`; pola tylko dla aktu (`record_type`, `entry_number`) mają `Rule::excludeIf(kind !== metrical_record)` (:28-29); nazwy atrybutów po polsku :38-46 (`provenance` → „pochodzenie”, :41). `SourceUpdateRequest.php:65-73` odrzuca zmianę rodzaju aktu z zapisanym typem/numerem bez `discard_record_fields`.
- Akcje: `app/Actions/Sources/StoreSource.php:28-70`, `UpdateSource.php:17-39` — `UpdateSource` zeruje wszystkie siedem pól opisu i nakłada przesłane; dla rodzaju innego niż akt zeruje `record_type` i `entry_number` (:19-34). Nowe pola (`parish_id`, `archive`) wymagają tej samej obsługi, jeśli mają być tylko dla aktu. Kształty PHPDoc `$description` w `StoreSource.php:26` i `UpdateSource.php:15` wymieniają `provenance`.
- Odczyt: jedyny zasób to `app/Http/Resources/SourceResource.php` (`provenance` :30, :56). Lista do pickera faktu to współdzielony `availableSources` (`Inertia::optional`, `app/Http/Middleware/HandleInertiaRequests.php:60-62`). Panel: domknięcie `sourcePanel` w `app/Http/Controllers/SourceController.php:46-67`; `kinds`, `recordTypes` jako propsy strony (:44-45); `store` przekierowuje na `index?source={id}` (:212-224), `update` robi `back()` (:239-250).

### Konsumenci `provenance` / „Pochodzenie” (pełna lista z greps po `app/`, `resources/js/`, `tests/`, `database/`, `lang/`)

- Backend: `Source.php:23,36`; `SourceDescriptionValidationRules.php:25,41`; `StoreSource.php:26`; `UpdateSource.php:15,24`; `SourceResource.php:30,56`; `database/factories/SourceFactory.php:74` (stan `described()`); `database/seeders/TreeViewDemoSeeder.php:37,297,311`.
- Frontend: `resources/js/types/source.ts:18`; formularz `source-description-fields.tsx:105-116`; `SourceFormData` w `source-panel.tsx:55`; karta galerii `sources-gallery.tsx:60-67`; kolumna tabeli „Pochodzenie” `sources-table.tsx:99`, komórki :176-185; `resources/js/lib/source-description.ts` — `showsUploadDate` (:16-21, data wgrania znika, gdy jest typ, rok lub pochodzenie), `isUndescribed` (siedem kolumn; używane tylko w testach).
- Nie czytają `provenance`: picker źródła do faktu (`source-picker-dialog.tsx:90` pokazuje `formatSourceMeta` = typ · rok), dopasowanie typu (`resources/js/lib/source-matching.ts:9-28` — tylko `kind` i `record_type`), wyszukiwarka listy (`resources/js/lib/sources-index.ts:95-105` — tylko `display_name`), dialogi usuwania/odpinania. `lang/` nie zawiera „Pochodzenie”.
- Testy: `tests/Feature/SourcesTest.php:48,528,542-553,595,610`; `tests/Feature/SourceMigrationTest.php:225`; `resources/js/lib/source-description.test.ts:11,39-50,138,164,236-240`; `resources/js/lib/sources-index.test.ts:25`. Żaden spec w `tests/e2e/`.

### Formularz źródła w panelu

- Jedyny formularz źródła to `resources/js/components/sources/source-panel.tsx` (`NewSourceForm` :186-334, `EditSourceForm` :336-528), oba renderują `SourceDescriptionFields` (:322, :439). Inertia `<Form>` + Wayfinder (`SourceController.store.form()` :214, `update.form(id)` :372), `id="source-form"`, przycisk „Zapisz” w stopce panelu przez `form={FORM_ID}`. Pola niekontrolowane (`defaultValue`); Select i ToggleGroup oddają wartość przez ukryte inputy.
- Zależność od rodzaju dotyczy w tym zbadanym kodzie tylko „Typ aktu” i „Numer wpisu” (`source-description-fields.tsx:24,43,60-92`) — ukrywane klasą `hidden`, nie odmontowywane, więc nadal się wysyłają. Nazwa, Rok, Pochodzenie, Link, Notatka pokazują się dla każdego rodzaju.
- Edycja: zmiana rodzaju, która skasowałaby pola aktu, otwiera dialog „Zmienić rodzaj źródła?” (`source-panel.tsx:376-388,501-525`) na podstawie `erasedRecordFields` (`resources/js/lib/source-description.ts:42-82`). Jeśli parafia/archiwum będą tylko dla aktu, ta lista i reguła `SourceUpdateRequest.php:65-73` muszą je objąć.
- Układ: `@container` (:144), od `@xl` (36rem) siatka `minmax(0,1fr) | 20rem` z własnym przewijaniem kolumny formularza (:64-70). Panel `variant="wide"` = `md:w-[clamp(40rem,55vw,72rem)]` (`resources/js/components/side-panel.tsx`, ~:213).
- Panel otwiera parametr `?source={id|new}` (`resources/js/lib/side-panel-url.ts:6-11`, `resources/js/hooks/use-side-panel-navigation.ts:25-44`); zamknięcie to `router.replace` bez żądania. Strażnik niezapisanych zmian: `useUnsavedChangesGuard` + `DiscardChangesDialog` (`resources/js/pages/sources/index.tsx:71-72,320,325`).

### Wzorzec do skopiowania dla parafii (moduł Locality z S-01)

- Tabela/indeks: `create_localities_table.php:15-24` (raw `CREATE UNIQUE INDEX … (tree_id, lower(name))`, bo Blueprint nie ma indeksu wyrażeniowego). Locale bazy `en_US.utf8` — `lower()` obsługuje polskie wielkie litery (sprawdzone w S-01, `context/archive/2026-10-05-places-gazetteer/plan.md:11`).
- Model `app/Models/Locality.php:20-33`; `Tree::localities()` `app/Models/Tree.php:67-71`; fabryka `database/factories/LocalityFactory.php`.
- Walidacja: `app/Concerns/LocalityValidationRules.php:16-38` (`bail|required|string|max:255` + domknięcie `whereRaw('lower(name) = lower(?)')` w drzewie, `whereKeyNot` przy edycji); Form Requests `LocalityStoreRequest.php`, `LocalityUpdateRequest.php:19-22` (`Gate::inspect` → 404).
- Akcje: `app/Actions/Localities/StoreLocality.php:17-27`, `UpdateLocality.php:16-28` — transakcja + `UniqueConstraintViolationException` → `ValidationException` (poprawka F3 z `context/archive/2026-10-05-places-gazetteer/reviews/impl-review.md:75-82`).
- Kontroler `app/Http/Controllers/LocalityController.php:22-93` (lista leniwa, `filters.q`, resolver panelu `?locality=new|id`), zasób `app/Http/Resources/LocalityResource.php:21-28`, licznik nawigacji `HandleInertiaRequests.php:53` + `resources/js/types/global.d.ts:20`.
- Frontend: `resources/js/pages/localities/index.tsx` (szukanie klienckie z debounce `router.get only:['filters']` :54-99, `useSidePanelNavigation('locality')` :45), `components/localities/locality-panel.tsx:30-86`, `lib/localities-index.ts` (filtr `toLocaleLowerCase('pl')`, sort `Intl.Collator('pl')`), rejestr panelu `PANEL_PROPS` w `side-panel-url.ts:5-10`.
- Testy: `tests/Feature/LocalitiesTest.php:10-176`, wpisy w `OwnershipBoundariesTest.php:50,83,175,179-222`, `AppShellTest.php:34-41`, `BackupTestCase.php:151`.

### Wybór z listy i dopisywanie w locie — stan obecny

- Brak typeahead/comboboxa, brak endpointu JSON w kontrolerach aplikacji (patrz Summary). Najbliższe wzorce: filtr kliencki po w pełni załadowanej liście (`components/persons/person-candidate-list.tsx:28-35`) i leniwy prop ładowany przy otwarciu dialogu (`use-relation-candidates.ts:15-41`, `TreeController.php:28,46`).
- `pick-or-create-person-dialog.tsx:63-249` ma przełącznik „Nowa osoba” na osobny `<Form>` w dialogu (:195-245) — ale tworzy i wiąże osobę jednym żądaniem z przekierowaniem; nie przekazuje wybranego id do formularza rodzica.
- Wniosek (nie sprawdzony w działającej aplikacji): dopisanie parafii z panelu źródła nie może być zagnieżdżonym `<form>` w DOM formularza źródła; musi to być portalowany Dialog (Radix, warstwuje się nad panelem — `side-panel.tsx` respektuje zagnieżdżone modale przy Escape) albo żądanie `useHttp` bez wizyty. Wizyta `router` z przekierowaniem przeładowałaby propsy strony i mogłaby zresetować niezapisany formularz źródła (pola `defaultValue`, `<Form key>` zależny od `'new'|id`). CLAUDE.md: „Delete confirmations and small pickers (choose a person, add a parish inline) stay modal”. PRD przy FR-008 mówi zaś, że „każde pole spisu podpowiada po kilku literach i pozwala dopisać nowy wpis bez wychodzenia z panelu” (`prd.md:119-120`) — oba da się pogodzić (pole w panelu z podpowiedziami + modal do dopisania), ale to decyzja UX dla planu.
- Podpowiedzi archiwów: brak wzorca „distinct values”. Natywny `<datalist>` zasilany propsem (np. lista `DISTINCT archive` w drzewie) nie wymaga nowej zależności; własny typeahead wymaga albo zależności (popover/cmdk — zgoda autora), albo ręcznej implementacji listy podpowiedzi.

### Testy i strażniki

- `tests/Feature/SourceMigrationTest.php`: migracje ładowane przez memoizowany `require` (:14-26,62-67), cofnięcie `down()` na schemacie z RefreshDatabase, wstawienie wierszy starego kształtu `DB::table(...)->insertGetId` (:33-55), `up()` i asercje (:73-120), round-trip opisu (:224-238).
- `tests/Feature/LocalitySeedMigrationTest.php:9-130`: najbliższy kształt dla kopii `provenance → archive` (surowe wiersze w dwóch drzewach, `up()`, asercje per drzewo, kolumny źródłowe nietknięte, ponowne uruchomienie). Migracja `2026_10_05_081632_seed_localities_from_place_texts.php:15-45` — jedno SQL, idempotentna, `down()` no-op z komentarzem.
- Kopia zapasowa: `config/backup.php:46-48` zrzuca całe połączenie `pgsql`, `RestoreBackup` wykrywa tabele z `information_schema` (`app/Console/Commands/RestoreBackup.php:276-285`) — konfiguracja bez zmian; jedyna zmiana to wiersz parafii w `tests/BackupTestCase.php:128-174`.
- E2E (`playwright.config.ts:16` → `tests/e2e`): `source-panel-edit.spec.ts` (tylko `getByLabel('Nazwa')`, przyciski panelu), `fact-source-scan.spec.ts` (bez pól opisu), `support/api.ts:164-186` (`createSource` = kind, name, file). Vitest działa bez DOM (tylko `resources/js/**/*.test.ts`), testów komponentów brak.

## Code References

- `database/migrations/2026_09_30_072040_add_description_to_sources_table.php:16-22,32` — kolumny opisu źródła, w tym `provenance`
- `app/Concerns/SourceDescriptionValidationRules.php:22-46` — reguły pól opisu, `excludeIf` dla pól aktu
- `app/Actions/Sources/UpdateSource.php:15-34` — zerowanie pól opisu i pól aktu przy innym rodzaju
- `app/Http/Controllers/SourceController.php:44-67,212-250` — propsy panelu, store/update
- `app/Http/Resources/SourceResource.php:30,56` — `provenance` w zasobie
- `resources/js/components/sources/source-description-fields.tsx:24-143` — pola opisu; „Pochodzenie” :105-116
- `resources/js/components/sources/source-panel.tsx:55,64-70,186-528` — formularze, układ 20rem, dialog zmiany rodzaju
- `resources/js/lib/source-description.ts:16-21,42-82` — `showsUploadDate`, `erasedRecordFields`
- `resources/js/components/sources/sources-table.tsx:99,176-185`, `sources-gallery.tsx:60-67` — wyświetlanie pochodzenia na liście
- `database/migrations/2026_10_05_081630_create_localities_table.php:15-24` — wzorzec tabeli spisu
- `app/Concerns/LocalityValidationRules.php:16-38`, `app/Actions/Localities/StoreLocality.php:17-27` — unikalność w drzewie w dwóch warstwach
- `app/Enums/Religion.php:5-35` — enum wyznań do ponownego użycia
- `resources/js/hooks/use-relation-candidates.ts:15-41` — leniwe ładowanie opcji pickera
- `resources/js/components/tree/pick-or-create-person-dialog.tsx:63-249` — picker z „Nowa osoba”
- `tests/Feature/LocalitySeedMigrationTest.php`, `tests/Feature/SourceMigrationTest.php` — wzorce testów migracji
- `tests/Backup/BackupRestoreTest.php:25-27`, `tests/BackupTestCase.php:128-174` — wymóg wiersza w nowej tabeli
- `tests/Feature/OwnershipBoundariesTest.php:139-150` — strażnik kompletności tras z parametrem
- `tests/e2e/support/api.ts:164-186`, `tests/e2e/source-panel-edit.spec.ts:35,43,53` — E2E wrażliwe na wymagalność pól i etykietę „Nazwa”

## Architecture Insights

- Rekordy spisów są własnością drzewa i mają unikalność w dwóch warstwach (indeks wyrażeniowy + walidacja w drzewie + przechwycenie wyścigu w akcji). Parafia powinna to powtórzyć; otwarte jest tylko, co jest kluczem (sama nazwa czy nazwa + wyznanie — patrz Open Questions).
- Panele są sterowane parametrem zapytania na stronie listy (`?locality=`, `?source=`) z rejestrem `PANEL_PROPS`; spis parafii, jeśli ma własną stronę, wpisuje się w ten sam mechanizm (`?parish=`).
- Opcje do wyboru ładuje się leniwym propem Inertia przy otwarciu dialogu; aplikacja nie ma dziś endpointów JSON. Dopisanie w locie do niezapisanego formularza to nowy wzorzec, który zostanie skopiowany w S-07 (miejsca faktów), S-08 (zamieszkania) i S-09 (cmentarze) — warto go zaprojektować jako komponent wielokrotnego użytku (wybór rekordu spisu do ukrytego inputu formularza).
- Przenoszenie danych: kopiuj, a usuwaj w późniejszej migracji (CLAUDE.md, `roadmap.md:158`); migracja przenosząca dane dostaje własny test w `tests/Feature/<Name>MigrationTest.php`.

## Historical Context (from prior changes)

- `context/archive/2026-10-05-source-side-panel/plan.md:37` — „Zmiany pól opisu źródła (parafia, archiwum, typ aktu ze spisu): to S-03”; S-02 celowo przeniósł opis bez zmiany pól (`plan-brief.md:8,47`) — wspierane przez kod (pola bez zmian względem `…add_description_to_sources_table.php`).
- `context/archive/2026-10-05-source-side-panel/plan-brief.md:24,70` — skan i formularz „zawsze obok siebie”; jeśli szerokość skanu okaże się nieużyteczna, pytanie o układ wraca. Zmierzona szerokość przy 1366 px nie jest zapisana (`plan.md:460`) — luka, istotna, jeśli S-03 zechce poszerzyć kolumnę formularza.
- `context/archive/2026-10-05-places-gazetteer/plan.md:38-40`, `plan-brief.md:44` — parafie, picker miejscowości, usuwanie/scalanie poza S-01; nazwa `Locality` zamiast `Place`.
- `context/archive/2026-10-05-places-gazetteer/research.md:127` i `context/foundation/archive/prd-v2.md:60,99-100,119` — w prd-v2 parafia była polem miejscowości, a „Pochodzenie” wolnym tekstem „parafia, archiwum, sygnatura”; oba założenia zastąpione przez FR-008/FR-009 obecnego PRD.
- `context/foundation/shape-notes.md:35-36,58,131` — osobny spis parafii (nazwa, wyznanie, miejscowość siedziby); pytanie „parafia = miejscowość czy osobny byt” rozstrzygnięte w PRD na rzecz osobnego bytu.
- `prd.md:247` — FR-004 (S-04): szukanie po miejscu obejmuje miejscowość siedziby parafii — S-04 potrzebuje `parishes.locality_id`.

## Related Research

- `context/archive/2026-10-05-places-gazetteer/research.md` — spis miejscowości, zasiew z tekstów, decyzja o `provenance` poza zasiewem
- `context/archive/2026-10-05-source-side-panel/research.md` — panel źródła, układ skan | formularz

## Decyzje autora (2026-10-05, po badaniu)

Rozstrzygają pytania 1–6 poniżej:

1. **„Pochodzenie” → archiwum: kopia 1:1.** Wartość trafia w całości do archiwum; parafię i sygnaturę z tekstu autor wydziela ręcznie. Stara kolumna `provenance` zostaje w bazie do późniejszej migracji (kopiuj, potem usuń).
2. **Archiwum dla każdego rodzaju źródła.** Parafia, typ aktu i numer wpisu tylko dla aktu; archiwum, rok, nazwa, link, notatka dla wszystkich rodzajów. Nagrobek dostaje cmentarz dopiero w S-09.
3. **Unikalność parafii: nazwa + wyznanie w drzewie** (bez wielkości liter w nazwie). Uwaga dla planu: wyznanie jest opcjonalne (pkt 6), więc indeks unikalny musi traktować brak wyznania jako równy — w PostgreSQL 18 `UNIQUE NULLS NOT DISTINCT` albo `coalesce(denomination, '')` w indeksie wyrażeniowym; to samo w domknięciu walidacji.
4. **Wybór parafii: pole z podpowiedziami w panelu + modal „Nowa parafia”.** Własny komponent, bez nowej zależności; projektowany do ponownego użycia w S-07/S-08/S-09. Podpowiedź pokazuje nazwę z wyznaniem.
5. **S-03 obejmuje stronę spisu parafii** (pozycja w menu, lista z wyszukiwaniem, panel edycji — kopia modułu miejscowości).
6. **Obowiązkowa jest tylko nazwa parafii.** Wyznanie i miejscowość siedziby opcjonalne (bez miejscowości akt nie znajdzie się po miejscu w S-04 — świadoma zgoda).

## Open Questions

Pytania produktowe (rozstrzygnięte — patrz „Decyzje autora”):

1. **Co z treścią „Pochodzenia” przy przeniesieniu?** Pole z założenia miesza parafię, archiwum i sygnaturę. Opcje: kopia 1:1 do archiwum (autor ręcznie wydziela parafię i czyści tekst) albo kopia do archiwum + pokazanie starej wartości jako „do przeniesienia” (jak stary „Numer wpisu” w S-04). Parafii nie da się wiarygodnie wydzielić automatycznie.
2. **Archiwum tylko dla aktu czy dla każdego rodzaju?** Dziś „Pochodzenie” jest wypełnialne dla nagrobka i „inne”; jeśli archiwum będzie tylko dla aktu, ich obecne „Pochodzenie” nie miałoby gdzie się pokazać. Powiązane z pytaniem roadmapy: jakie pola zostają dla „inne” (domyślnie nazwa, link, notatka) i dla nagrobka przed S-09.
3. **Unikalność parafii:** sama nazwa w drzewie (jak miejscowości) czy nazwa + wyznanie (w jednym mieście bywa parafia rzymskokatolicka i prawosławna o tej samej nazwie miejscowości)?
4. **UX wyboru parafii:** pole z podpowiedziami w panelu (wymaga własnej listy podpowiedzi lub nowej zależności popover/cmdk) czy przycisk otwierający modal z filtrowaną listą i „Nowa parafia” (zgodne z CLAUDE.md, bez zależności). Podobnie archiwum: natywny `<datalist>` vs własna lista.
5. **Czy S-03 obejmuje stronę spisu parafii** (`/parishes` z panelem, jak `/localities`) — FR-008 mówi „prowadzić spis parafii” — czy tylko dopisywanie i wybór z panelu źródła?
6. **Wymagalność pól parafii:** czy wyznanie i miejscowość siedziby są obowiązkowe; czy miejscowość siedziby też dopisuje się w locie (roadmap `:159` zakłada, że tak).

Do rozstrzygnięcia w planie (techniczne):

- Kształt dopisania w locie: Dialog z `<Form>` + odczyt nowego id po przekierowaniu z zachowaniem stanu, czy `useHttp` z odpowiedzią JSON (pierwszy taki endpoint w aplikacji; sprawdzić API `useHttp` w dokumentacji Inertia v3 — nie zweryfikowane w tym badaniu).
- Akcja FK `parishes.locality_id` przy przyszłym usuwaniu miejscowości (S-11 dopuszcza usunięcie tylko nieużywanej — `restrictOnDelete` pasuje) i `sources.parish_id` przy usuwaniu parafii.
- Czy „Numer wpisu” zostaje w formularzu aktu w S-03 (tak wynika z roadmapy: przeniesienie numeru to S-04, `roadmap.md:164`).
- Miejscowość siedziby w modalu „Nowa parafia”: wybór ze spisu tym samym komponentem podpowiedzi, z dopisaniem nowej miejscowości w locie czy bez (roadmapa `:159` wskazuje to jako największe ryzyko przerwania wprowadzania).
- Źródło podpowiedzi archiwów (np. `DISTINCT archive` w drzewie jako prop) i czy archiwum korzysta z tego samego komponentu podpowiedzi co parafia, czy z natywnego `<datalist>`.
