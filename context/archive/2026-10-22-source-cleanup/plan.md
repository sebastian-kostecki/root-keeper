# Porządki w źródłach — nagrobek bez roku, archiwum i numeru wpisu — Implementation Plan

## Overview

Źródło ma dwa główne rodzaje: akt metrykalny i zdjęcie nagrobka (plus „inne”). Akt zostaje bez zmian. Nagrobek przestaje mieć rok i archiwum, a jego powiązanie z faktem przestaje mieć „Numer wpisu”. Istniejące wartości przechodzą do notatki nagrobka, żeby nic nie zginęło. Filtry Lata i Archiwum na `/sources` pokazują wtedy tylko źródła, które takie dane mają. Picker „Powiąż źródło” nadal odnajduje nagrobek, mimo że podstawia rok faktu.

## Current State Analysis

- Formularz źródła od commita `71089c3` ukrywa pola Rok i Archiwum dla nagrobka tylko przez CSS (`resources/js/components/sources/source-description-fields.tsx:219`, `:230`). Pola zostają zamontowane, więc wysyłają zapisaną wartość przy każdym zapisie. Robią tak celowo, żeby zmiana rodzaju w formularzu nie gubiła danych.
- Walidacja roku i archiwum nie zależy od rodzaju (`app/Concerns/SourceDescriptionValidationRules.php:25-26`), w odróżnieniu od `record_type`, `parish_id`, `cemetery_id` i `buried_person_ids` z `Rule::excludeIf` (`:29-48`).
- `StoreSource` zapisuje cały zwalidowany opis. `UpdateSource` czyści pola innego rodzaju, ale roku i archiwum nie (`app/Actions/Sources/UpdateSource.php:40-48`).
- Rok i archiwum nagrobka są widoczne w wielu miejscach:
  - karta galerii: `formatSourceSummary` w `resources/js/lib/source-description.ts:230-252` oraz archiwum w `sources-gallery.tsx:67-72`;
  - kafelek pickera: `formatSourceMeta` w `source-description.ts:28-36`;
  - kolumny Rok i Archiwum w `sources-table.tsx`;
  - opcje archiwów: `SourceController::distinctArchives()` w `app/Http/Controllers/SourceController.php:182-191`.
- Filtr Lata na `/sources` (`resources/js/lib/sources-index.ts:290-342`) ukrywa każde źródło bez roku i liczy je w „Ukryto N źródeł bez roku”. Tak samo działa filtr Archiwum.
- Picker faktu podstawia rok faktu (`resources/js/components/sources/source-picker-dialog.tsx:~89`) i filtruje po dokładnym roku (`resources/js/lib/source-place-year-filter.ts`). Nagrobek bez roku zniknąłby z pickera przy zgonie. Polega na tym E2E `tests/e2e/gravestone-cemetery-and-buried.spec.ts:98-113`, który tworzy nagrobek z rokiem zgonu.
- „Numer wpisu” (`event_source.entry_number`) nie zależy od rodzaju źródła w żadnej warstwie:
  - zapis: `app/Actions/Events/LinkSourceToEvent.php`, `UpdateEventSourceEntryNumber`, `EventSourceController.php:20-38`;
  - walidacja: `EventSourceStoreRequest.php:42`, `EventSourceUpdateRequest.php:37`;
  - UI: pole w pickerze `source-picker-dialog.tsx:326-361`, przycisk i dialog `resources/js/components/persons/source-group.tsx:127-141` + `sources/entry-number-dialog.tsx`;
  - odczyt „nr X”: `source-group.tsx:93-97`, `persons/overview-tab.tsx:159-163`, `sources/source-panel.tsx:580-586`.
- Frontend zna `kind` źródła we wszystkich tych miejscach (`EventResource.php:72`, `types/person.ts:33-42`, `types/source.ts:18`).
- Dane lokalne: 6 nagrobków, w tym 1 z rokiem (1932) i 0 z archiwum. 3 powiązania fakt↔nagrobek, wszystkie bez numeru. Danych produkcyjnych nie znamy.

## Desired End State

- Nagrobek w bazie zawsze ma `year = null` i `archive = null`. Jego powiązania z faktami mają `entry_number = null`. Serwer pilnuje tego przy zapisie źródła i przy wiązaniu.
- Wartości, które nagrobki miały przed zmianą, stoją w notatce nagrobka jako linie „Rok: …”, „Archiwum: …” i „Numer wpisu: …”. To samo dzieje się, gdy akt lub „inne” zostaje zmienione na nagrobek.
- Na `/sources` ustawione Lata lub Archiwum ukrywają wszystkie nagrobki i nie liczą ich w „Ukryto N źródeł bez roku/archiwum”. Akt i „inne” bez roku dalej są tam liczone.
- W pickerze „Powiąż źródło” nagrobek przechodzi podstawiony rok i jest odnajdywany po miejscu cmentarza. Po wybraniu nagrobka nie ma pola „Numer wpisu”. Przy powiązanym nagrobku nie ma przycisku „Numer wpisu”.
- Akt metrykalny i „inne” zachowują się jak dziś.

Weryfikacja: testy Pest, Vitest i E2E z faz poniżej, plus ręczny przegląd `/sources` i panelu osoby.

### Key Discoveries:

- Wzorzec pól zależnych od rodzaju: `Rule::excludeIf(fn () => $this->input('kind') !== …)` w `SourceDescriptionValidationRules.php:29-48` oraz zerowanie w `UpdateSource.php:40-48`.
- Wzorzec migracji „przenieś do notatki”: `database/migrations/2026_10_21_152703_move_maiden_names_to_last_name.php`. Używa `chunkById`, dopisuje linie po `"\n\n"` i ma `down()` puste z komentarzem o backupie. Jego test to `tests/Feature/SingleSurnameMigrationTest.php`.
- Odmowa edycji dla faktu bez źródeł to `Response::denyAsNotFound()` w `authorize()` (`EventSourceUpdateRequest.php:20-24`). Tę samą formę ma dostać blokada edycji numeru przy nagrobku.
- `filterSources` liczy źródło jako „ukryte z braku danych” tylko wtedy, gdy każde niespełnione kryterium daje `lacksData() === true` (`sources-index.ts:383-389`). Wystarczy więc zmienić `matchesYears`, warunek archiwum i `lacksData`.
- `SourceController::filtersShowing()` (`:340-350`) zdejmuje Lata z przekierowania po zapisie źródła bez roku. Po zmianie dotyczy to każdego nagrobka i dalej ma sens, bo zapisany nagrobek ma być widoczny.

## What We're NOT Doing

- Rodzaj „inne” bez zmian: zachowuje rok, archiwum i numer wpisu, a przy filtrze Lata dalej liczy się jako „bez roku”.
- Nie usuwamy kolumn `sources.year`, `sources.archive` ani `event_source.entry_number`, bo akt ich używa.
- Nie usuwamy ukrytych, zamontowanych pól Rok i Archiwum z formularza. Wartość wpisana przed zmianą rodzaju na nagrobek przetrwa w formularzu, ale serwer jej nie zapisze.
- Nie ruszamy starego `sources.entry_number` („Numer wpisu (stary)”, `legacy-entry-number.tsx`). To osobne pole z własnym przyciskiem usuwania.
- Nie zmieniamy wyświetlania „nr X” ani linii rok/archiwum w opisach. Po migracji wartości nagrobka są `null`, więc te napisy znikają same.
- Nie zmieniamy podstawiania roku w pickerze ani filtra miejsca.

## Implementation Approach

Najpierw backend ustala niezmiennik danych: nagrobek nie ma roku, archiwum ani numeru wpisu. Migracja doprowadza do niego istniejące rekordy, kopiując wartości do notatki, a walidacja i akcje pilnują go przy każdym zapisie. Dzięki temu widoki nie potrzebują warunków po rodzaju. Frontend zmienia tylko reguły filtrów (`/sources` i picker) oraz chowa wejścia „Numer wpisu” dla nagrobka.

## Phase 1: Backend i dane

### Overview

Migracja przenosi istniejące wartości do notatki. Zapis źródła i wiązanie z faktem przestają przyjmować rok, archiwum i numer wpisu dla nagrobka.

### Changes Required:

#### 1. Migracja: rok, archiwum i numery wpisu nagrobków do notatki

**File**: `database/migrations/2026_10_22_100000_move_gravestone_descriptions_to_note.php` (nowy, przez `vendor/bin/sail artisan make:migration`)

**Intent**: Każdy nagrobek z rokiem, archiwum lub numerem wpisu na którymś powiązaniu dostaje te wartości w notatce, a pola zostają wyzerowane. Dzięki temu UI od razu przestaje je pokazywać, a nic nie ginie.

**Contract**:
- Dotyczy tylko `sources.kind = 'gravestone'`.
- Linie są dopisywane w kolejności:
  - `Rok: <year>`;
  - `Archiwum: <archive>`;
  - `Numer wpisu: <entry_number>`, po jednej linii na powiązanie z niepustym numerem, w kolejności `event_source.id`.
- Linie łączy `"\n"`. Do niepustej notatki dopisuje się je po `"\n\n"`, a pusta lub `''` notatka dostaje same linie.
- Puste stringi (`''`, same spacje) traktujemy jak brak wartości.
- Potem `sources.year = null`, `sources.archive = null` oraz `event_source.entry_number = null` dla powiązań tego nagrobka.
- Nagrobek bez żadnej z tych wartości zostaje nietknięty, łącznie z `updated_at`.
- Całość w `DB::transaction`, `chunkById` po `sources`.
- `down()` jest puste, z komentarzem: wartości żyją teraz w notatce, którą autor mógł edytować, więc przywrócenie idzie z kopii `backup:run --only-db` z `scripts/deploy.sh`.
- Wzorzec: `2026_10_21_152703_move_maiden_names_to_last_name.php`.

#### 2. Test migracji

**File**: `tests/Feature/GravestoneDescriptionMigrationTest.php` (nowy)

**Intent**: Pokazać nowy kształt i to, że żadna wartość nie zginęła, zgodnie z regułą CLAUDE.md dla migracji przenoszących dane.

**Contract**: przypadki:
- nagrobek z rokiem, archiwum i notatką: notatka `"<stara>\n\nRok: 1932\nArchiwum: AP Kraków"`, pola są `null`;
- nagrobek z pustą notatką dostaje same linie;
- nagrobek z dwoma powiązaniami z numerami: dwie linie „Numer wpisu: …” w kolejności powiązań, numery na pivocie są `null`;
- akt metrykalny z rokiem, archiwum i numerem na powiązaniu jest nietknięty;
- źródło „inne” z rokiem jest nietknięte;
- nagrobek bez roku, archiwum i numerów ma nietkniętą notatkę.

Helper `require database_path(...)` jak w `SingleSurnameMigrationTest.php`.

#### 3. Walidacja i zapis źródła

**File**: `app/Concerns/SourceDescriptionValidationRules.php`, `app/Actions/Sources/UpdateSource.php`

**Intent**: Nagrobek nie zapisuje roku ani archiwum, także gdy ukryte pola formularza je wyślą. Zmiana rodzaju na nagrobek przenosi rok, archiwum i numery wpisu do notatki i zeruje je, tak jak migracja. Nic nie ginie, a dialog potwierdzenia zmiany rodzaju zostaje bez zmian.

**Contract**:
- `year` i `archive` dostają `Rule::excludeIf(fn (): bool => $this->input('kind') === SourceKind::Gravestone->value)`, tak samo jak pola innych rodzajów. Zaktualizować docblock traitu.
- `UpdateSource::handle()`: gdy `$kind === SourceKind::Gravestone`, `year` i `archive` są `null`. Zaktualizować docblock.
- `UpdateSource::handle()`, gdy `$source->kind !== SourceKind::Gravestone` i `$kind === SourceKind::Gravestone`:
  - W tej samej transakcji przed `update()` zapisane `$source->year` i `$source->archive` oraz niepuste `entry_number` z `$source->events()` stają się liniami notatki.
  - Format linii jak w migracji (§1): kolejność `Rok`, `Archiwum`, `Numer wpisu` po `event_source.id`; łączy je `"\n"`, a od niepustej notatki dzieli `"\n\n"`; puste stringi się pomija.
  - Linie dopisuje się do notatki **wysłanej w tym zapisie** (`$attributes['note']`), nie do starej.
  - Potem `entry_number = null` na wszystkich powiązaniach tego źródła.
  - Bez wartości notatka zostaje bez zmian.
- Logika siedzi w prywatnej metodzie `UpdateSource`. Migracja ma własną kopię, bo migracja nie może zależeć od kodu aplikacji, który później się zmieni. Wspólny jest tylko format linii.
- Zmiana nagrobek → akt nic nie odtwarza z notatki.
- `StoreSource` nie wymaga zmian, bo wykluczone pola nie trafiają do `create()`.

#### 4. Numer wpisu przy wiązaniu i edycji

**File**: `app/Actions/Events/LinkSourceToEvent.php`, `app/Http/Requests/EventSourceUpdateRequest.php`

**Intent**: Powiązanie faktu z nagrobkiem nigdy nie dostaje numeru wpisu, a edycja numeru takiego powiązania nie istnieje.

**Contract**:
- Store: `EventSourceStoreRequest` bez zmian. Laravel wywołuje closure `Rule::excludeIf` przy parsowaniu reguł, czyli przed `integer`/`exists` dla `source_id`. Zapytanie z nieliczbowym `source_id` dałoby 500 na PostgreSQL.
- `LinkSourceToEvent::handle()` działa już po walidacji. Odczytuje rodzaj źródła (`$event->tree->sources()->whereKey($sourceId)->value('kind')` lub równoważnie) i dla nagrobka zapisuje powiązanie z `entry_number = null`. Zaktualizować docblock.
- Update: `authorize()` zwraca `Response::denyAsNotFound()`, gdy `$this->route('source')` jest nagrobkiem, czyli tak samo jak dla faktu, który nie przyjmuje źródeł.

#### 5. Testy Pest

**File**: `tests/Feature/SourcesTest.php`, `tests/Feature/EventSourcesTest.php`

**Intent**: Pokryć nowe reguły i przepisać testy, które utrwalały archiwum nagrobka.

**Contract**:
- `SourcesTest.php`:
  - test „storing a gravestone saves the archive and ignores a submitted parish” (`:1426`) przepisać na „storing a gravestone ignores a submitted year, archive and parish”: wszystkie trzy pola `null`;
  - test zmiany aktu na nagrobek (`:1484`) ma teraz oczekiwać `archive === null` i `year === null` po zmianie rodzaju oraz linii „Rok: …” i „Archiwum: …” w notatce. Asercję zmieniamy celowo, bo zmienia się reguła biznesowa;
  - dodać przypadek: akt `described()` z dwoma powiązaniami z numerami wpisu zmieniony na nagrobek ma w notatce wysłaną notatkę, potem `"\n\n"`, potem linie rok/archiwum/numery w kolejności powiązań, a `entry_number` na pivocie jest `null`;
  - dodać przypadek: aktualizacja nagrobka z `year` i `archive` w body zostawia oba `null`.
- `EventSourcesTest.php`:
  - wiązanie nagrobka z `entry_number` zapisuje powiązanie z `entry_number = null`;
  - PATCH numeru wpisu dla powiązanego nagrobka zwraca 404, a numer pozostaje bez zmian;
  - istniejące testy aktu (numer zapisany i edytowalny) bez zmian.

#### 6. Seeder demo

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: `migrate:fresh --seed` nie odtwarza nagrobka z rokiem, którego migracja już nie poprawi.

**Contract**:
- Nagrobek „Nagrobek rodziny Kowalskich, cmentarz w Lipnicy” (`:369-373`): zamiast `'year' => 1932` dostaje `'note' => 'Rok: 1932'`. Wygląda wtedy tak, jak lokalny rekord po migracji.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi na bazie lokalnej: `vendor/bin/sail artisan migrate`
- Test migracji przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneDescriptionMigrationTest.php`
- Testy źródeł i powiązań przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/EventSourcesTest.php`
- Larastan bez błędów: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Lokalny nagrobek „Nagrobek rodziny Kowalskich, cmentarz w Lipnicy” (id 47) ma po migracji `year = null`, a w notatce linię „Rok: 1932”

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Frontend i E2E

### Overview

Filtry `/sources` i pickera traktują nagrobek zgodnie z decyzjami. Wejścia „Numer wpisu” znikają dla nagrobka. Testy Vitest i E2E odzwierciedlają nagrobek bez roku.

### Changes Required:

#### 1. Filtry na /sources

**File**: `resources/js/lib/sources-index.ts`

**Intent**: Ustawione Lata lub Archiwum oznaczają, że użytkownik szuka aktów, więc nagrobki są wtedy ukryte. Nie liczą się jednak jako „bez roku/archiwum”, bo tych danych z założenia nie mają.

**Contract**:
- `matchesYears` zwraca `false` dla `kind === 'gravestone'`, gdy ustawiona jest dowolna granica lat.
- Kryterium archiwum w `failedCriteria` nie przechodzi dla nagrobka, co wynika już ze zrównania `archive` z `''`.
- `lacksData` zwraca `false` dla nagrobka przy kryteriach `year` i `archive`. Przy `place` bez zmian.
- Licznik zakładki „nagrobki” przy aktywnych Latach lub Archiwum wynosi więc 0.
- Zaktualizować komentarze funkcji.

#### 2. Filtr pickera

**File**: `resources/js/lib/source-place-year-filter.ts`

**Intent**: Rok podstawiony z faktu nie ukrywa nagrobka. Nagrobek odnajduje się po miejscu cmentarza.

**Contract**:
- `FilterFields` zawiera `kind`.
- `yearMatches` jest `true` dla nagrobka.
- Nagrobek nie zwiększa `hiddenUndescribedCount` z powodu roku. Brak miejsca liczy się jak dotąd.
- Zaktualizować docblock.

#### 3. Brak „Numeru wpisu” dla nagrobka

**File**: `resources/js/components/sources/source-picker-dialog.tsx`, `resources/js/components/persons/source-group.tsx`

**Intent**: Po wybraniu nagrobka w „Powiąż źródło” jest tylko „Wybrane: …” i przycisk „Powiąż”. Przy powiązanym nagrobku nie ma przycisku „Numer wpisu”.

**Contract**:
- W pickerze pole „Numer wpisu” i jego błąd renderują się tylko, gdy `selectedSource.kind !== 'gravestone'`.
- Dla nagrobka `handleLink` wysyła `entry_number: null`, nawet jeśli wcześniej wpisano numer przy innym źródle.
- `autoFocus` przechodzi na przycisk „Powiąż”, żeby Enter i klawiatura dalej działały.
- W `source-group.tsx` `EntryNumberDialog` z przyciskiem renderuje się tylko dla `source.kind !== 'gravestone'`.

#### 4. Testy Vitest

**File**: `resources/js/lib/sources-index.test.ts`, `resources/js/lib/source-place-year-filter.test.ts`, `resources/js/lib/source-description.test.ts`, `resources/js/lib/source-description.ts` (tylko docblock)

**Intent**: Fixtury nagrobków przestają mieć rok i archiwum, a nowe reguły filtrów mają swoje testy.

**Contract**:
- `sources-index.test.ts`:
  - nagrobek ukryty przy ustawionych Latach i nieliczony w `hidden`;
  - to samo przy ustawionym Archiwum;
  - akt bez roku nadal liczony jako „bez roku”;
  - licznik „nagrobki” wynosi 0 przy aktywnych Latach;
  - fixtury nagrobków z rokiem lub archiwum w `filterOptionCounts` (`:332-365`) przepisać na `null` i przeliczyć oczekiwania.
- `source-place-year-filter.test.ts`:
  - fixtura dostaje `kind`;
  - test „finds a gravestone by the place of its cemetery and its year” (`:81`) przepisać na nagrobek bez roku, odnajdywany po miejscu przy wypełnionym roku;
  - dodać, że nagrobek nie zwiększa `hiddenUndescribedCount` przy aktywnym roku.
- `source-description.test.ts`: przypadki nagrobka z rokiem (`:56-75`, `~:400`, `:435-444`) przepisać na nagrobek bez roku, np. „nagrobek · cmentarz Luboml”.
- W docblocku `formatSourceSummary` zmienić przykład „nagrobek · 1950 · cmentarz Luboml” na przykład aktu.

#### 5. E2E nagrobka

**File**: `tests/e2e/gravestone-cemetery-and-buried.spec.ts`

**Intent**: Spec odzwierciedla nagrobek bez roku i pilnuje przepuszczania przez rok oraz braku numeru wpisu.

**Contract**:
- `createSource` dla nagrobka bez `year`. Komentarz w setupie bez „of the same year”.
- Krok 5 zostaje: picker ma Rok = rok zgonu, a nagrobek jest widoczny po wpisaniu miejsca. Oczekiwanie jest to samo, zmienia się powód: przepuszczenie przez rok zamiast dopasowania roku.
- Dopisać:
  - wybranie nagrobka: brak pola `getByLabel('Numer wpisu')`;
  - „Powiąż”: w grupie „Zgon” jest powiązany nagrobek bez przycisku „Numer wpisu źródła …”.
- Lokatory według CLAUDE.md (role/label), bez `waitForTimeout`.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- E2E nagrobka przechodzi: `npx playwright test tests/e2e/gravestone-cemetery-and-buried.spec.ts`
- E2E akt z numerem wpisu przechodzi: `npx playwright test tests/e2e/source-found-by-place-and-year.spec.ts`

#### Manual Verification:

- Na `/sources` ustawienie Lat (np. 1900–1949) chowa wszystkie nagrobki, a komunikat „Ukryto N źródeł bez …” nie liczy nagrobków
- Na `/sources` wybór Archiwum chowa nagrobki bez wpisu w komunikacie o ukrytych
- W panelu osoby przy Zgonie picker z podstawionym rokiem pokazuje nagrobek z cmentarza po wpisaniu miejscowości, a po wybraniu nie ma pola „Numer wpisu”
- Przy powiązanym nagrobku nie ma przycisku „Numer wpisu”, a przy akcie jest
- Karta nagrobka w galerii nie pokazuje roku ani archiwum

**Implementation Note**: Po zakończeniu fazy i przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Vitest: reguły `filterSources` (Lata, Archiwum, licznik ukrytych, licznik zakładki) i `filterSourcesByPlaceAndYear` (nagrobek przechodzi rok, nie liczy się jako bez roku).
- Przypadek graniczny: akt bez roku przy aktywnych Latach dalej trafia do „Ukryto N źródeł bez roku”.

### Integration Tests:

- Pest:
  - migracja, czyli nowy kształt i żadna wartość nie ginie, a akt i „inne” są nietknięte;
  - zapis i aktualizacja nagrobka z rokiem i archiwum w body;
  - wiązanie nagrobka z numerem oraz 404 przy edycji numeru.
- E2E: przepływ nagrobka od panelu do powiązania ze zgonem bez numeru.

### Manual Testing Steps:

1. `vendor/bin/sail artisan migrate` i sprawdzenie notatki nagrobka id 47.
2. `/sources` z Latami, potem z Archiwum: nagrobki znikają, komunikat ich nie liczy.
3. Panel osoby, Zgon, „Powiąż źródło”: nagrobek widoczny przy podstawionym roku, bez pola „Numer wpisu”; po powiązaniu brak przycisku „Numer wpisu”.

## Migration Notes

- Migracja nie jest odwracalna. `down()` jest puste z komentarzem, a produkcja robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`).
- Kolumny zostają, bo akt ich używa. Kopiujemy, nic nie usuwamy.
- Numery wpisu przy nagrobkach trafiają do notatki nagrobka, bo nie ma innego miejsca, w którym mogłyby przetrwać. Tego wymaga reguła „no value lost” z CLAUDE.md.

## References

- Wzorzec migracji do notatki: `database/migrations/2026_10_21_152703_move_maiden_names_to_last_name.php`, `tests/Feature/SingleSurnameMigrationTest.php`
- Pola zależne od rodzaju: `app/Concerns/SourceDescriptionValidationRules.php:29-48`, `app/Actions/Sources/UpdateSource.php:40-48`
- Ukrycie pól w formularzu: commit `71089c3` (`context/archive/2026-10-20-cemeteries/`)
- Picker i nagrobek: `context/archive/2026-10-06-gravestone-cemetery-and-buried/plan-brief.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend i dane

#### Automated

- [x] 1.1 Migracja przechodzi na bazie lokalnej: `vendor/bin/sail artisan migrate` — b249377
- [x] 1.2 Test migracji przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/GravestoneDescriptionMigrationTest.php` — b249377
- [x] 1.3 Testy źródeł i powiązań przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/EventSourcesTest.php` — b249377
- [x] 1.4 Larastan bez błędów: `vendor/bin/sail composer types:check` — b249377
- [x] 1.5 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent` — b249377

#### Manual

- [x] 1.6 Lokalny nagrobek „Nagrobek rodziny Kowalskich, cmentarz w Lipnicy” (id 47) ma po migracji `year = null`, a w notatce linię „Rok: 1932” — b249377

### Phase 2: Frontend i E2E

#### Automated

- [x] 2.1 Format i lint przechodzą: `vendor/bin/sail npm run check` — d2aad79
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — d2aad79
- [x] 2.3 Vitest przechodzi: `vendor/bin/sail npm run test:js` — d2aad79
- [x] 2.4 E2E nagrobka przechodzi: `npx playwright test tests/e2e/gravestone-cemetery-and-buried.spec.ts` — d2aad79
- [x] 2.5 E2E akt z numerem wpisu przechodzi: `npx playwright test tests/e2e/source-found-by-place-and-year.spec.ts` — d2aad79

#### Manual

- [x] 2.6 Na `/sources` ustawienie Lat (np. 1900–1949) chowa wszystkie nagrobki, a komunikat „Ukryto N źródeł bez …” nie liczy nagrobków — d2aad79
- [x] 2.7 Na `/sources` wybór Archiwum chowa nagrobki bez wpisu w komunikacie o ukrytych — d2aad79
- [x] 2.8 W panelu osoby przy Zgonie picker z podstawionym rokiem pokazuje nagrobek z cmentarza po wpisaniu miejscowości, a po wybraniu nie ma pola „Numer wpisu” — d2aad79
- [x] 2.9 Przy powiązanym nagrobku nie ma przycisku „Numer wpisu”, a przy akcie jest — d2aad79
- [x] 2.10 Karta nagrobka w galerii nie pokazuje roku ani archiwum — d2aad79
