---
date: 2026-10-05T15:20:10+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: f9b8a82
branch: develop
repository: root-keeper
topic: "S-04 source-found-by-place-and-year — jak zawęzić wybór źródła do faktu po miejscu (miejscowość parafii aktu) i roku oraz przenieść „Numer wpisu” ze źródła na powiązanie fakt–źródło (FR-003, FR-004, FR-025, US-01)"
tags: [research, codebase, sources, source-picker, event-source, entry-number, parishes, localities, data-migration, e2e]
status: complete
last_updated: 2026-10-05
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje autora 1–4 (AskUserQuestion, 2026-10-05)"
---

# Research: akt odnaleziony po miejscu i roku, numer wpisu przy powiązaniu (S-04)

**Date**: 2026-10-05T15:20:10+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: f9b8a82
**Branch**: develop
**Repository**: root-keeper

## Research Question

Co w obecnym kodzie i dokumentach dotyka S-04 (`context/foundation/roadmap.md:162-172`): przy podpinaniu źródła do faktu autor zawęża listę po miejscu (miejscowość parafii aktu) i roku, widzi przy każdym źródle rok i parafię, a pasujący typ aktu nadal jest pierwszy; podaje numer wpisu przy powiązaniu i widzi go przy fakcie i przy źródle; stary „Numer wpisu” źródła pozostaje widoczny do ręcznego przeniesienia, a każdy istniejący fakt prowadzi do tego samego źródła i skanu (FR-003 `prd.md:101`, FR-004 `prd.md:104`, FR-025 `prd.md:175`, US-01 `prd.md:76-89`, usunięcie pola `prd.md:193`). Jak dziś działa picker, co niesie payload, jak wygląda tabela powiązań, kto czyta `entry_number` i które testy/strażniki się przewrócą.

## Summary

- **Picker nie ma dziś żadnego wyszukiwania ani filtra** (`resources/js/components/sources/source-picker-dialog.tsx`, cały plik; lista `max-h-96`, siatka 2–3 kolumny w dialogu `sm:max-w-lg`, :125, :167). „Widzi przy każdym źródle rok i parafię” jest już spełnione przez S-03: kafel pokazuje `formatSourceMeta` = „typ · rok · parafia” (`resources/js/lib/source-description.ts:8-17`, użycie `source-picker-dialog.tsx:90`), przy czym `parish_label` to nazwa parafii + „(wyznanie)” (`app/Models/Parish.php:42-45`), **bez miejscowości siedziby**. Realne delty S-04: filtr miejsce+rok, miejscowość w payloadzie, numer wpisu na powiązaniu, obsługa starej wartości.
- **Payload pickera nie zna miejscowości.** `availableSources` = `Inertia::optional(...)` z `tree->sources()->with('parish')->latest()->orderByDesc('id')` (`app/Http/Middleware/HandleInertiaRequests.php:60-62`) — cała lista drzewa, bez paginacji, zakres przez drzewo użytkownika. `SourceResource` wysyła `year`, `parish_id`, `parish_label`, `archive`, `entry_number`, ale nie `locality_id`/`locality_name`. Dodanie miejscowości wymaga `with('parish.locality')` (inaczej N+1); wzorzec pola jest w `app/Http/Resources/ParishResource.php:28-29` (`locality_id`, `locality_name`).
- **Miejsce źródła to wyłącznie `sources.parish_id → parishes.locality_id → localities.name`**; `parishes.locality_id` jest nullable (`database/migrations/2026_10_05_114218_create_parishes_table.php:22`). Akt z parafią bez siedziby (lub bez parafii) nie odnajdzie się po miejscu — przyjęte świadomie w S-03 (decyzja 6, `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md:141`). Miejsce faktu (`events.place`) to wciąż wolny tekst (`app/Models/Event.php:24,29`; FK dopiero w S-07), więc filtra miejsca nie da się wiarygodnie wypełnić z faktu; rok faktu jest dostępny w `date_parts.year` payloadu osoby.
- **Tabela powiązań `event_source` ma surogatowe `id`, timestampy i unikat `(event_id, source_id)`** (`database/migrations/2026_09_24_122757_create_event_scan_table.php:14-21`, przemianowana w `2026_09_29_100000_rename_scans_to_sources.php:17-22`; w lokalnej bazie indeksy nadal nazywają się `event_scan_pkey`, `event_scan_event_id_scan_id_unique`, `event_scan_scan_id_index`). Numer wpisu na powiązaniu to jedna nullable kolumna — istniejące powiązania zostają nietknięte (to samo `id`, `event_id`, `source_id`), więc „każdy fakt prowadzi do tego samego źródła i skanu” wynika z addytywności migracji. Unikat oznacza jeden numer na parę (fakt, źródło).
- **Ścieżka zapisu powiązania nie przyjmuje danych powiązania i nie umie ich zaktualizować.** `EventSourceController::store` robi `syncWithoutDetaching([$source_id])` i łapie `UniqueConstraintViolationException` (`app/Http/Controllers/EventSourceController.php:18-29`); request waliduje tylko `source_id` (`app/Http/Requests/EventSourceStoreRequest.php:25-35`); brak klasy Action i brak trasy edycji powiązania (`routes/web.php:39-40`: tylko `store` i `destroy`). Relacje `Event::sources()` (`app/Models/Event.php:69-75`) i `Source::events()` (`app/Models/Source.php:87-93`) nie mają `withPivot`.
- **Fakt nie pokazuje dziś żadnych danych powiązania.** `EventResource` serializuje podpięte źródło jako `{id, kind, kind_short_label, original_name, display_name, has_thumbnail, uploaded_at}` (`app/Http/Resources/EventResource.php:62-70`; TS `LinkedSource` `resources/js/types/person.ts:30-38`). Miejsca wyświetlania przy fakcie: zakładka Przegląd (`resources/js/components/persons/overview-tab.tsx:113-136`) i zakładka Źródła (`resources/js/components/persons/sources-tab.tsx:39-158`). Przy źródle: karta „Potwierdza” w panelu źródła (`resources/js/components/sources/source-panel.tsx:498-536`, dane z `SourceController::confirms()` :151-166).
- **Pułapka utraty starej wartości (zweryfikowana):** `UpdateSource::handle` ustawia `entry_number => null` jako domyślne i nadpisuje przesłanym opisem (`app/Actions/Sources/UpdateSource.php:19-30`), a dla rodzaju innego niż akt zeruje go jawnie (:33-37). Jeśli S-04 usunie pole z formularza bez zmiany akcji, pierwszy zapis źródła skasuje stary numer — ta sama pułapka, którą S-03 obeszło dla `provenance` (`context/archive/2026-10-05-act-described-by-parish-and-archive/plan.md`, „Key Discoveries”).
- **Strażniki testowe:** E2E `tests/e2e/fact-source-scan.spec.ts` nie dotyka UI pickera (powiązanie przez API `tests/e2e/support/api.ts:189-200`, tylko `{source_id}`); przewróci się, jeśli numer stanie się wymagany przy `events.sources.store`, albo jeśli numer wejdzie do nazwy dostępnej linku „Otwórz źródło …” (`tests/e2e/support/navigation.ts:69-71`) lub do tekstu linku „{event_label} — {person}” na karcie „Potwierdza” (spec :81-83). Pest: `tests/Feature/EventSourcesTest.php:36` przypina idempotencję podwójnego powiązania; `tests/Feature/SourcesTest.php:51` sprawdza ścisły zestaw kluczy `SourceResource` (`hasAll`); nowa trasa z `{event}/{source}` wymaga dwóch przypadków w `ownershipRouteCases()` (`tests/Feature/OwnershipBoundariesTest.php:71-121`, strażnik :142-157).
- **Otwarte decyzje autora** (sekcja Open Questions): gdzie widać i jak usuwa się stary „Numer wpisu” (jedyny nierozstrzygnięty unknown z roadmapy, `roadmap.md:171`), czy numer podaje się przy podpinaniu, czy też edytuje później, kształt filtra miejsca i roku, czy automatycznie przenosić numer, gdy źródło ma dokładnie jedno powiązanie.

## Detailed Findings

### Picker źródła do faktu — przepływ dziś

- Wejścia (oba w podglądzie osoby; `availableSources` czytane w `person-preview-dialog.tsx:33`, przekazywane przez `person-modal.tsx:77,92`):
  - Przegląd: `overview-tab.tsx:141` (`FactView`), przycisk „Powiąż źródło” tylko gdy fakt nie ma źródeł (gałąź `:122-152`); wywołania dla urodzenia/małżeństwa/zgonu `:304,344,380`.
  - Źródła: `sources-tab.tsx:145` (`SourceGroup`), zawsze, „Powiąż źródło”/„Powiąż kolejne źródło”, z `linkedSourceIds`.
- Ładowanie: przy otwarciu `router.reload({ only: ['availableSources'] })`, lista buforowana w stanie lokalnym, bo pełna wizyta gubi prop optional (`source-picker-dialog.tsx:51-70`); szkielety :139-147, błąd :148-152, pusty stan z linkiem do `/sources?source=new` :153-165.
- Kafel (`renderTile` :88-122): miniatura, `display_name` (obcięty), plakietka `kind_short_label`, linia `formatSourceMeta`; „Już powiązane” albo data wgrania, gdy brak linii meta. Powiązane kafle są wyłączone; wszystkie wyłączone w trakcie żądania.
- Kolejność: serwer — najnowsze pierwsze (`HandleInertiaRequests.php:61`); klient — `splitSourcesForEvent` (`resources/js/lib/source-matching.ts:7-27`) dzieli na „Pasujące akty” (`kind === 'metrical_record' && record_type === eventType`) i „Pozostałe źródła”, zachowując kolejność wejścia (render :168-208). Pin kolejności: `tests/Feature/PersonsTest.php:1015-1028`.
- Zapis: `router.post(EventSourceController.store(eventId).url, { source_id }, { preserveScroll })` (:72-86) → `routes/web.php:39` → kontroler (patrz niżej); błąd `errors.source_id` przez `InputError`. Odpinanie: `unlink-source-dialog.tsx:37-38` → `routes/web.php:40` → `EventSourceController::destroy` (:34-49, 404 gdy nic nie odpięto).
- Układ: picker to zwykły `DialogContent` (`resources/js/components/ui/dialog.tsx:58`, `sm:max-w-lg`) zagnieżdżony nad podglądem osoby (`form-dialog.tsx:22-23`, `sm:max-w-2xl sm:h-[90vh]`); między nagłówkiem (:134-137) a listą nie ma dziś miejsca na filtry; stopka ma tylko „Zamknij”. Linia meta `text-xs` nie jest obcinana (:109).

### Dane do filtra miejsca i roku

- `SourceResource` (`app/Http/Resources/SourceResource.php:45-68`; TS `resources/js/types/source.ts:8-32`): `year`, `parish_id`, `parish_label`, `archive`, `entry_number` i pola pliku; brak nazwy parafii osobno, brak `locality_id`/`locality_name`. Lista źródeł (`SourceController.php:43-44`) też ładuje tylko `with('parish')`.
- Pomocniki klienckie do wyszukiwania (wszystkie: `toLocaleLowerCase('pl')` + `includes`, sort `Intl.Collator('pl')`, bez zwijania diakrytyków): `resources/js/lib/sources-index.ts` (`filterSources` :59-92 po słowach w `display_name`; sort „rok” z pustymi na końcu :141-150; reguła lustrzana po stronie serwera wg komentarza `SourceController.php:182`), `resources/js/lib/localities-index.ts` (`filterLocalities` :30-43), `resources/js/lib/parishes-index.ts` (`filterParishes` :31-44 — dopasowuje nazwę parafii **lub** `locality_name`; najbliższy wzorzec predykatu „miejsce” dla źródła).
- Unikalność miejscowości: `lower(name)` w drzewie (`create_localities_table.php:15-24`); duplikaty diakrytyczne/literówki zostają do S-11.

### Tabela i relacja powiązania fakt–źródło

- Ostateczny kształt: `event_source(id, event_id, source_id, created_at, updated_at)`, oba FK `cascadeOnDelete`, unikat `(event_id, source_id)`, indeks `source_id` (migracje jw.; nazwy indeksów sprawdzone zapytaniem `pg_indexes` w lokalnej bazie).
- `Event::sources()` — `belongsToMany(Source::class, 'event_source')->withTimestamps()->orderBy('event_source.created_at')->orderBy('event_source.id')` (`app/Models/Event.php:69-75`); `Source::events()` lustrzanie (`app/Models/Source.php:87-93`). Brak `withPivot`, brak modelu pivotu; dla porównania `participants()` używa `->using(EventParticipant::class)->withPivot('role')` (`Event.php:61-63`).
- Ładowanie przy osobie: `app/Actions/Persons/BuildPersonPreview.php:27` (`events.sources`, `marriages.sources`; bez parafii).
- Zapis: kontroler pisze inline, bez Action (odstępstwo od ścieżki zapisu z CLAUDE.md); `syncWithoutDetaching` z listą samych id nigdy nie aktualizuje istniejącego wiersza — nadanie numeru istniejącemu powiązaniu wymaga `updateExistingPivot` albo nowej trasy.
- Lokalna baza deweloperska (nie produkcja; zapytanie 2026-10-05): 19 źródeł, 1 z `entry_number` (id 132, „47”, 0 powiązań — źródło z seedera demo), 0 z `parish_id`, 4 z `year`, 13 wierszy `event_source`. Produkcji nie sprawdzano.

### Konsumenci `entry_number` (grep po `app/`, `resources/js/` bez katalogów generowanych, `tests/`, `database/`, `lang/`, `routes/`, `config/`)

- Backend: `Source.php:21` (docblock), `:37` (`#[Fillable]`, bez rzutowania); `app/Concerns/SourceDescriptionValidationRules.php:29` (`Rule::excludeIf(kind !== metrical_record)`, `nullable|string|max:50`), :51 (etykieta „numer wpisu”) — trait używany przez `SourceStoreRequest.php` i `SourceUpdateRequest.php`; `SourceUpdateRequest.php:38` (komunikat „Zmiana rodzaju usunie typ aktu, numer wpisu lub parafię…”), :44 (`discard_record_fields`), :65-72 (`wouldEraseRecordFields`, :72 sprawdza `entry_number`); `StoreSource.php:26` (PHPDoc; wartość przez `...$description` :55); `UpdateSource.php:15,22,34`; `SourceResource.php:28,56`; migracja `2026_09_30_072040_add_description_to_sources_table.php:18` (`string(50)->nullable()`, `down()` :32); `database/factories/SourceFactory.php:72` (stan `described()` → „47”); `database/seeders/TreeViewDemoSeeder.php:305` (jedno źródło demo przez `described()`, niepowiązane). `lang/`: brak.
- Frontend: `resources/js/types/source.ts:17`; pole formularza `source-description-fields.tsx:145-151` (ukrywane klasą `hidden` dla rodzaju ≠ akt, nadal wysyłane); `source-panel.tsx:60` (`SourceFormData`), `:26,:97,:388` (dialog kasowania przy zmianie rodzaju); tabela `sources-table.tsx:98` (kolumna „Nr wpisu”, między Rok i Parafia), :170-174; `resources/js/lib/source-description.ts` — `erasedRecordFields` :101-111 (etykieta „Numer wpisu”), `isUndescribed` :120,:133 (bez konsumenta poza testami), `formatSourceSummary` :143,:178-179 („nr X” na karcie galerii, `sources-gallery.tsx:58`). Picker i fakt numeru nie pokazują.
- Testy: `tests/Feature/SourcesTest.php:51,531,546,558,575,582,605,617,630,647,650,662,669`; `tests/Feature/SourceMigrationTest.php:225`; `resources/js/lib/source-description.test.ts` (wiele linii, m.in. :73-122,:167-190,:227,:273); `resources/js/lib/sources-index.test.ts:23`.

### Strażniki testowe i wzorce testów

- E2E (`tests/e2e/fact-source-scan.spec.ts`): osoba z urodzeniem + źródło przez API, powiązanie `linkSource` (spec :50 → `api.ts:189-200`), potem UI: region „Urodzenie” → „Potwierdzone” → link → panel źródła → sha256 skanu → fakt na liście źródła. Wrażliwe miejsca wymienione w Summary. Zalecenie F-01: asercje nie dotyczą treści kafla pickera ani miejsca wyświetlania „nr”; zmiany dla S-04 idą do `tests/e2e/support/`, nie do asercji (`context/archive/2026-10-04-critical-flows-e2e-guard/research.md:124`, `plan.md:24`; `navigation.ts:1-4`). S-02 zostawiło `aria-label` linku bez zmian ze względu na E2E (`context/archive/2026-10-05-source-side-panel/plan.md:292`).
- `tests/e2e/source-panel-parish.spec.ts:34,62,81` używa `panel.getByLabel('Rok')` bez `exact` — nowa etykieta zawierająca „Rok” w panelu źródła (np. „Rok od”) uczyni go niejednoznacznym. Picker to osobny dialog, więc filtr „Rok” w pickerze tego lokatora nie dotyka.
- `context/foundation/test-plan.md:86` — bramka E2E „required przed S-04 – S-06” (lokalnie); §6.5 (:170-175) — nowa trasa z `{param}` → wpis w `ownershipRouteCases()`, trasa z dwoma parametrami → przypadek na każdy (wzór `events.sources.destroy`); nowe id w ciele żądania → `Rule::exists(...)->where('tree_id')` + test braku zapisu; :99 — nowy endpoint zapisu → happy path, 404 innego użytkownika i `rowDiff` na `familyScene`; §6.6 (:196-212) — setup przez `support/api.ts`, wyrocznią są dane, celowe zepsucie przed uznaniem testu, `--repeat-each=2`.
- `tests/Feature/EventSourcesTest.php` (link, idempotencja :36, źródło innego drzewa, każdy rodzaj, odpinanie, 404, jedno źródło na dwóch faktach, kaskady). Jeśli numer pójdzie przez `store`, test :36 musi dostać rozstrzygnięcie: drugie powiązanie z innym numerem aktualizuje pivot czy jest no-op.
- Backup: `tests/BackupTestCase.php:129-176` tworzy dokładnie jeden wiersz `event_source` przez `events.sources.store` z samym `source_id` (:168); `tableDigests()` haszuje całe wiersze (:276-292), więc nowa kolumna jest porównywana automatycznie, ale z wartością NULL nie dowodzi przetrwania numeru — warto podać numer w :168. `tests/Backup/BackupRestoreTest.php:25-27` wymaga wierszy w każdej trwałej tabeli (nie kolumnie) — istotne tylko przy nowej tabeli.
- Wzorce testu migracji: `tests/Feature/SourceMigrationTest.php` (`require database_path(...)`, `down()` → insert → `up()` → asercje; :73 „the rename keeps every event link under its id” — precedens dla „fakt prowadzi do tego samego źródła”) i bliższy `tests/Feature/SourceArchiveMigrationTest.php` (kopia bajt w bajt :62, stara kolumna nietknięta :76, ponowny przebieg :86, `down` zachowuje :100). Czysto addytywna nullable kolumna na pivocie nie przepisuje wierszy, więc reguła CLAUDE.md „migration that moves existing data” formalnie jej nie obejmuje; automatyczne kopiowanie `sources.entry_number` na powiązania — obejmuje.

## Code References

- `resources/js/components/sources/source-picker-dialog.tsx:51-208` — picker: ładowanie, kafel, sekcje, zapis
- `resources/js/lib/source-matching.ts:7-27` — `splitSourcesForEvent` (pasujący typ pierwszy)
- `resources/js/lib/source-description.ts:8-17` — `formatSourceMeta` „typ · rok · parafia”; :101-111 `erasedRecordFields`; :171-195 `formatSourceSummary`
- `resources/js/lib/parishes-index.ts:31-44` — predykat nazwa parafii lub miejscowość
- `app/Http/Middleware/HandleInertiaRequests.php:60-62` — `availableSources` (`with('parish')`)
- `app/Http/Resources/SourceResource.php:45-68` — pola źródła; `ParishResource.php:28-29` — wzór `locality_id`/`locality_name`
- `app/Models/Parish.php:42-45` — `label()` nazwa + wyznanie
- `database/migrations/2026_10_05_114218_create_parishes_table.php:22` — `locality_id` nullable
- `database/migrations/2026_09_24_122757_create_event_scan_table.php:14-21` — pivot
- `app/Models/Event.php:69-75`, `app/Models/Source.php:87-93` — relacje bez `withPivot`
- `app/Http/Controllers/EventSourceController.php:18-49`, `app/Http/Requests/EventSourceStoreRequest.php:15-35`, `routes/web.php:39-40` — zapis powiązania
- `app/Http/Resources/EventResource.php:62-70`, `resources/js/types/person.ts:30-38` — źródło przy fakcie
- `resources/js/components/sources/source-panel.tsx:498-536`, `app/Http/Controllers/SourceController.php:151-166` — „Potwierdza” przy źródle
- `app/Actions/Sources/UpdateSource.php:19-37` — zerowanie `entry_number`
- `app/Http/Requests/SourceUpdateRequest.php:38,44,65-72` — potwierdzenie kasowania pól aktu
- `tests/e2e/fact-source-scan.spec.ts:50,81-83`, `tests/e2e/support/api.ts:189-200`, `tests/e2e/support/navigation.ts:69-71`
- `tests/Feature/EventSourcesTest.php:36`, `tests/Feature/SourcesTest.php:51`, `tests/Feature/OwnershipBoundariesTest.php:71-121,142-157`, `tests/BackupTestCase.php:168`

## Architecture Insights

- Dopasowanie w pickerze jest czystą funkcją klienta nad jedną listą drzewa (`source-matching.ts`); filtr miejsca i roku naturalnie dokłada się jako kolejna czysta funkcja nad tą samą listą, bez parametru zdarzenia na serwerze (zgodnie z decyzją S-19, że `availableSources` zostaje jednym propem drzewa).
- Miejsce źródła warto liczyć jako pochodną „miejscowość źródła” (dziś: parafia → siedziba), a nie przywiązywać filtr do `parish_id` — S-09 dołoży miejscowość cmentarza nagrobka (`roadmap.md:228-236`, `prd.md:247`). Nie należy denormalizować miejscowości na `sources` ani na `event_source`: byłby to nowy typ referencji, którego S-11 nie ma na liście scalania (CLAUDE.md, „Merging a duplicate…”).
- Predykat miejsca/roku warto trzymać w funkcji lib wielokrotnego użytku — S-10 (siatka źródeł z filtrami po rodzaju, parafii/miejscu, roku, archiwum; `roadmap.md:239-250`) użyje tego samego.
- Numer wpisu na powiązaniu to dana pivotu: `withPivot('entry_number')` po obu stronach (lub model pivotu przez `using()`, jak `EventParticipant`), serializacja w `EventResource` i w `confirms`.
- Ścieżka zapisu powiązania nie ma Action ani trasy edycji; dodanie edycji numeru to nowy endpoint z dwoma parametrami (koszt: Form Request, Action, test 404 dla obcego faktu i obcego źródła, wpisy w strażniku kompletności).

## Historical Context (from prior changes)

- `context/archive/2026-09-30-matching-sources-first/` (S-19) — pasujący typ pierwszy jako czysta funkcja klienta; `availableSources` jeden prop drzewa, najnowsze pierwsze; wyszukiwanie/filtr/paginacja świadomie odłożone (v2:FR-006) — S-04 je przejmuje (`plan-brief.md:21-30`, „NOT Doing”). Wspierane przez bieżący kod.
- `context/archive/2026-09-30-fact-confirmed-by-any-source/` (S-18) — każdy rodzaj źródła potwierdza każdy z trzech faktów; dopasowanie typu nie może blokować powiązania; „każdy fakt potwierdzony przed wdrożeniem jest potwierdzony po nim” (`plan-brief.md:18-29`).
- `context/archive/2026-10-04-critical-flows-e2e-guard/` (F-01) — setup przez istniejące trasy, w tym `events.sources.store`; uruchamiać lokalnie przed S-04–S-06; uwaga odpornościowa dla S-04 (`research.md:124`).
- `context/archive/2026-10-05-act-described-by-parish-and-archive/` (S-03) — odłożone do S-04: zmiany pickera, szukanie po miejscu i roku, a „Numer wpisu” zostaje w formularzu aktu do S-04 (`plan.md:45`, `plan-brief.md:46`, `research.md:158`); decyzja 2: parafia, typ aktu i numer wpisu tylko dla aktu, rok i archiwum dla każdego rodzaju (`research.md:137`); decyzja 6: siedziba parafii opcjonalna (`research.md:141`); decyzja 1: `provenance` skopiowane 1:1 do archiwum, stara kolumna ukryta w UI — opcja „pokazać starą wartość do przeniesienia, jak stary Numer wpisu w S-04” była rozważana i nie wybrana dla `provenance` (`research.md:147`); impl-review F-1: gdy UI przestaje pisać starą kolumnę, `down()` traci wartości z po wdrożeniu — opisać w docblocku.
- `context/archive/2026-10-05-source-side-panel/` (S-02) — picker zostaje modalem (CLAUDE.md, `research.md:83-87,154`); wejścia z faktu na `/sources?source={id}`.
- `context/archive/2026-10-05-places-gazetteer/` (S-01) — brak `locality_id` na `events`/`people`; miejsce zdarzenia zostaje tekstem do S-07 (`plan.md:38-40`).
- Uwaga: starsze archiwa (`2026-09-25-person-search`, `2026-09-23-account-tree-and-first-person`) mówią o innym „S-04” z roadmapy M-1 — nie dotyczą tej zmiany.

## Related Research

- `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md` — S-03 (parafia, archiwum, pułapka zerowania pól w `UpdateSource`)
- `context/archive/2026-10-05-places-gazetteer/research.md` — S-01 (spis miejscowości)

## Decyzje autora (2026-10-05, AskUserQuestion)

1. **Stary „Numer wpisu”:** widoczny w panelu aktu jako tekst tylko do odczytu (np. „Numer wpisu (stary): 47”) z akcją usunięcia; znika z formularza, kolumny tabeli i „nr X” w galerii. Kolumna `sources.entry_number` zostaje w bazie do późniejszej migracji (copy first, drop later). Konsekwencja: `UpdateSource` nie może zerować `entry_number` przy zwykłym zapisie (`UpdateSource.php:19-30`); zachowanie przy zmianie rodzaju (:33-37, `SourceUpdateRequest.php:65-72`) do ustalenia w planie.
2. **Bez automatycznego przeniesienia:** migracja tylko dodaje pustą nullable kolumnę na `event_source`; żadne istniejące wiersze nie są przepisywane.
3. **Numer przy podpinaniu i edycja później:** opcjonalne pole w pickerze (rozszerzenie `events.sources.store`, nie wymagane — E2E `linkSource` wysyła tylko `source_id`) oraz edycja numeru podpiętego źródła (nowa trasa z `{event}/{source}` → dwa przypadki w `ownershipRouteCases()`).
4. **Filtr:** pole „Miejsce” dopasowuje fragment nazwy miejscowości siedziby lub nazwy parafii (wzorzec `parishes-index.ts:31-44`), pole „Rok” — rok dokładny, wstępnie wypełniony rokiem faktu (`date_parts.year`), jeśli znany; pasujący typ aktu nadal pierwszy.

## Open Questions

Pytania postawione autorowi (rozstrzygnięte powyżej, w sekcji „Decyzje autora”):

1. **Stary „Numer wpisu” źródła** — gdzie autor go widzi do ręcznego przeniesienia i jak go usuwa, gdy skończy (`roadmap.md:171`). Kandydaci na miejsce: panel źródła (tylko do odczytu, z akcją „usuń”), kolumna tabeli i „nr X” w galerii (`sources-table.tsx:170-174`, `source-description.ts:178-179`). CLAUDE.md mówi „stay readable until the user removes them” — nie precyzuje, czy per źródło, czy decyzją o usunięciu kolumny.
2. **Automatyczne przeniesienie** — czy przenieść numer na powiązanie, gdy źródło ma dokładnie jedno powiązanie (wtedy test migracji wg CLAUDE.md), czy wszystko ręcznie.
3. **Kiedy autor podaje numer** — w pickerze przy podpinaniu (rozszerzenie `events.sources.store`, opcjonalne pole) i/lub edycja numeru istniejącego powiązania (nowa trasa `PATCH events/{event}/sources/{source}`). Bez edycji istniejące powiązania nie dostaną numeru inaczej niż przez odpięcie i ponowne podpięcie.
4. **Kształt filtra** — miejsce jako tekst dopasowywany do nazwy miejscowości (i ewentualnie nazwy parafii, jak `parishes-index.ts`) czy wybór miejscowości ze spisu; rok dokładny czy z tolerancją/zakresem; czy rok wstępnie wypełniać rokiem faktu (`date_parts.year`).

Kwestie techniczne dla planu (nie blokują):

- Semantyka ponownego powiązania z innym numerem (aktualizacja pivotu vs no-op) i test `EventSourcesTest.php:36`.
- Gdzie pokazać numer przy źródle, nie zmieniając tekstu linku „{event_label} — {person}” ani `aria-label` „Otwórz źródło …” (E2E).
- Komunikat w pickerze dla aktów bez parafii/siedziby, gdy filtr miejsca jest aktywny.
- Liczba źródeł z `entry_number` w produkcji — nieznana (sprawdzona tylko baza lokalna); decyduje o nakładzie ręcznego przenoszenia.
