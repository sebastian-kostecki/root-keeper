# Źródło w szerokim panelu bocznym (S-02) — plan implementacji

## Overview

Autor dodaje, ogląda i edytuje źródło w szerokim panelu bocznym F-02 (`?source=`) na liście `/sources`, a nie na osobnej stronie i nie w modalach. W panelu skan z powiększeniem stoi obok formularza rodzaju i opisu. Usuwanie po liście faktów tracących potwierdzenie działa jak dziś (FR-006, FR-027). Pola opisu się nie zmieniają, bo zmienia je S-03. Przy okazji strażnik niezapisanych zmian staje się wspólny dla miejscowości i źródeł, a powłoka dostaje `inert` poniżej `md` (odłożone F2 z F-02).

## Current State Analysis

- Podgląd źródła to osobna strona `sources/show` (`app/Http/Controllers/SourceController.php:110-133`, `resources/js/pages/sources/show.tsx`), na której skan i opis stoją obok siebie w siatce `lg:grid-cols-[minmax(0,1fr)_20rem]` (`show.tsx:84`). Dodawanie (`source-create-dialog.tsx`) i edycja (`source-edit-dialog.tsx`, z zagnieżdżonym „Zmienić rodzaj źródła?”) to modale. Usuwanie (`delete-source-dialog.tsx`) jest modalem z listą wpływu z `confirms`.
- Powłoka F-02 jest gotowa: `SidePanel` z wariantem `wide` (`md:w-[clamp(40rem,55vw,72rem)]`, `resources/js/components/side-panel.tsx:162-163`), przypiętą stopką, `actions` w nagłówku i natywnym Escape, który respektuje zagnieżdżone modale (`:131-150`). Rejestr `PANEL_PROPS` (`resources/js/lib/side-panel-url.ts:6-10`) nie zna `source`.
- Wzorzec konsumenta pochodzi z S-01 (miejscowości). Resolver panelu jest domknięciem w `LocalityController@index` (`:31-47`). `store` przekierowuje na `index?locality={id}`, `update` używa `back()`. Panel to `<Form key>` z `DirtyReporter` (`resources/js/components/localities/locality-panel.tsx:21-35`). Strażnik `guard/guardLink/discard` z dialogiem „Odrzucić niezapisane zmiany?” jest skopiowany w `pages/localities/index.tsx:99-136,219-244` i `pages/dev/side-panel.tsx:91-108,216-240`.
- Wizyty filtrów listy źródeł budują adres wyłącznie z `buildSourcesQuery(filters)` (`resources/js/pages/sources/index.tsx:53-67`), więc zgubiłyby klucz panelu. To ten sam błąd co F1 z przeglądu S-01, naprawiony tam przez `withLocalitiesSearch` (`resources/js/lib/localities-index.ts:16-28`).
- `SourceViewer` ma wysokość liczoną od okna, `h-[calc(100vh-12rem)]` (`resources/js/components/sources/source-viewer.tsx:46`).
- Od trasy i strony `sources.show` oraz od przekierowania `store → sources.show` zależą `SourcesTest`, `DeleteImpactTest` (wyrocznia FR-027, `:119-131`), `MarriagesTest:179-192`, `PersonsTest:965-970`, `EventSourcesTest:105-118`, `OwnershipBoundariesTest` (`:95`, `:176`) i pomocnik E2E `tests/e2e/support/api.ts:181-182`.
- Vitest działa bez DOM (`vite.config.ts` → `include: resources/js/**/*.test.ts`), więc hooki i komponenty React nie mają testów jednostkowych. Pokrywają je Pest (kontrakt właściwości), E2E i sprawdzenie ręczne.

## Desired End State

- `/sources?source={id}` otwiera obok listy szeroki panel z tytułem nazwy źródła. Po lewej stoi skan z Powiększ/Pomniejsz/Dopasuj, wypełniający wysokość panelu. Po prawej jest kolumna 20rem z rodzajem, opisem, listą „Potwierdza” i przypiętym „Zapisz” w stopce. W nagłówku jest „Usuń”, które otwiera dotychczasowy modal z listą wpływu.
- `/sources?source=new` otwiera panel „Nowe źródło”. W kolumnie skanu stoi wybór pliku. Po wyborze pliku widać tam jego lokalny podgląd z „Zmień plik”, a obok rodzaj i opis. Zapis otwiera panel nowego źródła z filtrami listy, jakie były.
- Usunięcie zamyka panel i zostawia filtry listy (szukanie, rodzaj, Galeria/Tabela, sortowanie).
- `/sources/{id}` przekierowuje na `/sources?source={id}`. Cudze id daje 404 w obu adresach.
- Przełączenie rekordu i zamknięcie panelu przy niezapisanych zmianach pyta „Odrzucić niezapisane zmiany?”. Tak samo działa to dalej w miejscowościach, przez wspólny kod.
- Poniżej `md` treść strony za pełnoekranowym panelem jest `inert`.
- Weryfikacja: Pest (resolver, przekierowania, FR-027, 404), Vitest (`withSourcesQuery`), nowy spec E2E edycji z ochroną zmian oraz zielony `fact-source-scan.spec.ts` zawężony do panelu.

### Key Discoveries:

- `idFromLocation(response, key)` czyta id z parametru zapytania (`tests/e2e/support/api.ts:76-93`), więc `createSource` potrzebuje tylko nowego regexu i klucza `'source'`.
- Host `?person=` z przypadku `'sources.show (własne źródło w adresie)'` (`OwnershipBoundariesTest.php:176`) przestaje działać, gdy `show` jest przekierowaniem: odpowiedź 302 nie wylicza współdzielonych właściwości, więc nie da 404. Host przechodzi na `sources.index` z `?source={własne}`.
- `withPerson` nie czyści innych kluczy (`person-preview-provider.tsx:39-49`), więc link „Potwierdza” w panelu daje `?source=X&person=Y`: modal osoby otwiera się nad panelem źródła. To akceptowane zachowanie do czasu S-06 (`PersonsTest:965` przechodzi na ten host).
- `LocalitiesTable` wyróżnia otwarty wiersz przez `data-state="selected"` i `aria-current` (`resources/js/components/localities/localities-table.tsx:47-65`). Galeria i tabela źródeł robią to samo.
- `useIsMobile()` (`resources/js/hooks/use-mobile.tsx`) używa progu 768 px, czyli tego samego co `md` powłoki.

## What We're NOT Doing

- Zmiany pól opisu źródła (parafia, archiwum, typ aktu ze spisu): to S-03.
- Panel źródła poza `/sources` (np. nad drzewem albo modalem osoby). Wejścia z modala osoby prowadzą na `/sources?source={id}` i opuszczają bieżącą stronę jak dziś (decyzja z badań nr 2).
- Strefa przeciągnij i upuść dla pliku: wybór pliku zostaje zwykłym polem.
- Przenoszenie osoby do panelu i czyszczenie `?source=` przez `withPerson`: to S-06.
- Strażnik niezapisanych zmian przy wyjściu z listy menu bocznym lub przyciskiem Wstecz przeglądarki. Miejscowości też tego nie mają, a decyzja z modala źródła brzmiała „Esc/X porzuca”.
- Usuwanie źródła z menu wiersza tabeli: lista wpływu żyje w panelu, jak dotąd na podglądzie.
- Zachowanie `?person=` przy przekierowaniu `/sources/{id}` → `/sources?source={id}` (po S-02 nikt nie linkuje na stary adres z `?person=`).
- Test E2E dodawania z lokalnym podglądem: zostaje sprawdzenie ręczne (decyzja: jeden nowy spec, scenariusz edycji).

## Implementation Approach

Kolejność: najpierw wspólny strażnik i `inert`, czyli refaktor bez zmiany zachowania z zieloną listą miejscowości. Potem kontrakt serwera z przepiętymi testami Pest. Dalej panel na liście, na końcu wejścia spoza listy, sprzątanie martwego kodu i E2E.

Stan panelu żyje w adresie (`?source=`), dane w leniwej właściwości `sourcePanel` liczonej tylko na `/sources` (wzór `LocalityController`). Filtry listy po dodaniu i usunięciu serwer odczytuje z adresu poprzedniej strony, tym samym mechanizmem, którego używa `back()`. Normalizuje je istniejącymi `normalize*` i buduje adres przez `route('sources.index', …)`, więc nie powstaje otwarte przekierowanie.

Między fazami 2 i 3 spec `fact-source-scan.spec.ts` jest czerwony: `/sources/{id}` już przekierowuje na listę, a panelu jeszcze nie ma. E2E jest poza CI, a faza 3 to naprawia, faza 5 zaś zawęża lokatory.

## Critical Implementation Details

- **Filtry po zapisie nie mogą iść w adresie akcji formularza.** Filtr `kind` (`akty|nagrobki|inne`) ma tę samą nazwę co pole formularza `kind` (`metrical_record|…`), a Laravel scala query z body przy walidacji. Przy braku pola w body walidowany byłby filtr. Dlatego `store` i `destroy` czytają filtry z `url()->previous()` (nagłówek Referer, jak `back()`), a nie z bieżącego żądania. Gdy poprzedni adres nie ma ścieżki `/sources` albo go brak (np. `page.request` w E2E), filtrów nie ma.
- **`inert` a przywracanie fokusu.** `SidePanelView` przy odmontowaniu oddaje fokus elementowi sprzed otwarcia (`side-panel.tsx:94-117`), a ten leży w treści strony. Jeśli `inert` zdejmie dopiero późniejszy render, `focus()` na elemencie w `inert` cicho się nie uda. Zdjęcie `inert` musi nastąpić przed przywróceniem fokusu, w tym samym sprzątaniu albo wcześniej.
- **Lokalny podgląd pliku.** Object URL tworzy się przy wyborze pliku i zwalnia (`URL.revokeObjectURL`) przy zmianie pliku i odmontowaniu panelu. Po zapisie `<Form key>` zmienia się z `'new'` na id, więc panel dodawania się odmontowuje. Pole pliku musi pozostać zamontowane w `<Form>` także po wyborze, bo wybranego pliku nie da się wstawić z powrotem. „Zmień plik” klika to samo pole.
- **Powiększenie przy zmianie obrazu.** `TransformWrapper` trzyma skalę między renderami. Przy przełączeniu rekordu i zmianie pliku podglądu przeglądarka musi zacząć od dopasowania, np. przez `key` na `src`.

## Faza 1: Wspólny strażnik zmian i `inert` w powłoce

### Overview

Wydzielenie kopii strażnika z miejscowości i strony demo do wspólnego kodu, bez zmiany zachowania. Dodanie `inert` dla treści strony za pełnoekranowym panelem poniżej `md`.

### Changes Required:

#### 1. Raportowanie stanu formularza

**File**: `resources/js/components/dirty-reporter.tsx` (nowy), `resources/js/components/localities/locality-panel.tsx`

**Intent**: Przenieść `DirtyReporter` z panelu miejscowości do wspólnego komponentu, bo panel źródła potrzebuje tego samego mostu z render-propa `<Form>` do strony.

**Contract**: `DirtyReporter({ isDirty, onDirtyChange })`. Zgłasza zmianę i `false` przy odmontowaniu, jak dziś. `locality-panel.tsx` importuje go zamiast lokalnej kopii.

#### 2. Hook strażnika i dialog porzucenia

**File**: `resources/js/hooks/use-unsaved-changes-guard.ts` (nowy), `resources/js/components/discard-changes-dialog.tsx` (nowy)

**Intent**: Jedna implementacja `guard / guardLink / discard` i dialogu „Odrzucić niezapisane zmiany?”, wyjęta z `pages/localities/index.tsx:99-136,219-244` z zachowaniem zachowania. Kliknięcie ze skrótem (nowa karta) przechodzi, klik w już otwarty rekord nic nie robi, brudny formularz pyta.

**Contract**:
- `useUnsavedChangesGuard({ openKey, open })`, gdzie `openKey: number | string | null` to klucz rekordu w panelu (`id`, `'new'` albo `null`), a `open: (id) => void` to przełączenie panelu. Zwraca `{ setIsDirty, guard(action), guardLink(event, id), discardDialog }`, gdzie `discardDialog` to propsy dialogu (`open`, `onCancel`, `onDiscard`).
- `DiscardChangesDialog` renderuje dotychczasowy tekst: tytuł „Odrzucić niezapisane zmiany?”, opis „Zmiany wprowadzone w panelu zostaną utracone.”, przyciski „Wróć do edycji” i „Odrzuć”.

#### 3. Przepięcie konsumentów

**File**: `resources/js/pages/localities/index.tsx`, `resources/js/pages/dev/side-panel.tsx`

**Intent**: Oba konsumenty używają hooka i dialogu zamiast własnych kopii, bez zmiany widocznego zachowania.

**Contract**: Usunięte lokalne `isDirty/pendingAction/guard/guardLink/discard` i inline'owy `<Dialog>`. Strona demo zachowuje własne scenariusze.

#### 4. `inert` poniżej `md`

**File**: `resources/js/components/side-panel.tsx`, `resources/js/layouts/app/app-sidebar-layout.tsx`

**Intent**: Gdy panel jest otwarty i pełnoekranowy (szerokość < 768 px), treść strony za nim (`AppContent` z nagłówkiem mobilnym) nie przyjmuje fokusu ani nie trafia do drzewa dostępności. Od `md` nic się nie zmienia, bo panel stoi obok treści.

**Contract**: Provider slotu wie, czy panel jest zamontowany. Treść strony dostaje `inert`, gdy panel jest otwarty i `useIsMobile()` zwraca prawdę. Dialogi Radix (portal do `body`) i modal osoby nie są objęte. Kolejność względem przywracania fokusu: patrz „Critical Implementation Details”.

### Success Criteria:

#### Automated Verification:

- Testy miejscowości przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php`
- Format i lint: `vendor/bin/sail npm run check`
- Typy: `vendor/bin/sail npm run types:check`
- Vitest: `vendor/bin/sail npm run test:js`

#### Manual Verification:

- W `/localities` przełączenie wiersza i zamknięcie panelu (X, Escape) przy brudnym formularzu pytają „Odrzucić niezapisane zmiany?”. „Wróć do edycji” zostawia zmiany, „Odrzuć” wykonuje akcję, a klik w otwarty wiersz nic nie robi.
- Przy szerokości < 768 px z otwartym panelem Tab nie wychodzi z panelu na stronę, a po zamknięciu fokus wraca na element, z którego otwarto panel. Przy ≥ 768 px Tab dalej przechodzi do listy obok panelu.

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Backend panelu źródła

### Overview

Kontrakt serwera: leniwa właściwość `sourcePanel` na liście, przekierowania `store`/`destroy`/`show` z zachowaniem filtrów oraz przepięte i nowe testy Pest. Do tego regex przekierowania w pomocniku E2E.

### Changes Required:

#### 1. Resolver panelu i przekierowania

**File**: `app/Http/Controllers/SourceController.php`

**Intent**: `index` rozwiązuje `?source=` wzorem `LocalityController@index`. `show` zostaje trasą, ale tylko przekierowuje. `store` otwiera panel nowego źródła, a `destroy` zamyka panel, oba z filtrami listy, jakie były.

**Contract**:
- `index` dostaje `'sourcePanel' => fn (): ?array`. Brak klucza albo wartość, która nie jest `'new'` ani ciągiem cyfr, daje `null`. `'new'` daje `['source' => null, 'confirms' => []]`. Cyfry dają `$tree->sources()->findOrFail($id)` + `Gate::authorize('view')` + eager load z dzisiejszego `show`, a wynik to `['source' => SourceResource::make(...)->resolve(), 'confirms' => [...]]`. Kształt `confirms` bez zmian: `{event_id, event_type, event_label, is_last_source, person{id, display_name}}`, budowany w prywatnej metodzie wyjętej z `show`. Cudze lub nieistniejące id daje 404.
- `show(Source)`: `Gate::authorize('view')` (cudze daje 404, a przypadek `OwnershipBoundariesTest:95` zostaje) i potem `to_route('sources.index', ['source' => $source->id])`.
- `store`: toast bez zmian, potem `to_route('sources.index', [...listQuery, 'source' => $source->id])`.
- `destroy`: toast bez zmian, potem `to_route('sources.index', listQuery)`.
- `listQuery` to prywatna metoda. Parsuje `url()->previous()`, a jeśli ścieżka to nie `/sources`, zwraca `[]`. Inaczej normalizuje `q/kind/view/sort` istniejącymi `normalize*` i pomija wartości domyślne oraz puste, jak `buildSourcesQuery` (`resources/js/lib/sources-index.ts`): `sort` tylko przy `view=tabela` i różny od `-dodano`. Klucze paneli i inne parametry nie przechodzą. Uzasadnienie w „Critical Implementation Details”.

#### 2. Testy Pest

**File**: `tests/Feature/SourcesTest.php`, `tests/Feature/DeleteImpactTest.php`, `tests/Feature/MarriagesTest.php`, `tests/Feature/PersonsTest.php`, `tests/Feature/EventSourcesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`

**Intent**: Przepiąć testy z komponentu `sources/show` i właściwości `confirms` na `sources/index` z `sourcePanel`, bez osłabiania asercji. Dopisać przypadki nowego kontraktu.

**Contract**: przepięcia
- `SourcesTest.php:60-112, 180-187, 687-698, 744-751`: `GET sources.index?source={id}` → `component('sources/index')`, asercje na `sourcePanel.source.*` i `sourcePanel.confirms`.
- `SourcesTest.php:127, 206, 226`: `store` → `assertRedirect(route('sources.index', ['source' => $source->id]))`.
- `SourcesTest.php:564-579` i `EventSourcesTest.php:105-118`: `from(route('sources.index', ['source' => …]))` → przekierowanie na ten sam adres.
- `DeleteImpactTest.php:119-131` (wyrocznia FR-027): `sourcePanel.confirms` z `GET sources.index?source={id}`, porównane z faktycznie odpiętymi wierszami jak dziś.
- `MarriagesTest.php:179-192`: `sourcePanel.confirms`.
- `PersonsTest.php:965-970`: host `sources.index` z `['source' => $source->id]` + `?person=` → `component('sources/index')`, `personPreview.person.id` i `sourcePanel.source.id`.
- `OwnershipBoundariesTest.php:176`: przypadek hosta zmieniony na `'sources.index (otwarty panel własnego źródła)' => ['sources.index', fn ($r) => ['source' => $r['ownSource']->id]]`. Przypadek `:95` zostaje, strażnik kompletności `:139-155` bez zmian.

**Contract**: nowe przypadki
- Resolver: `?source=new` daje `sourcePanel.source` null i pustą listę `confirms`. `?source=abc` i brak klucza dają `sourcePanel` null. `?source=999999` daje 404.
- `GET sources.show` własnego źródła przekierowuje na `sources.index?source={id}`.
- `store` z `from('/sources?view=tabela&kind=akty&q=Kraków&sort=nazwa&source=new')` przekierowuje na `sources.index` z `['q' => 'Kraków', 'kind' => 'akty', 'view' => 'tabela', 'sort' => 'nazwa', 'source' => id]`. Z `from` spoza `/sources` (np. `/persons`) daje tylko `?source={id}`. Nieprawidłowe wartości filtrów (`kind=zle`) są pominięte.
- `destroy` z `from('/sources?view=tabela&source={id}')` przekierowuje na `sources.index?view=tabela` (bez `source`). Istniejące `SourcesTest.php:416-476` i `DeleteImpactTest.php:146-148` (bez `from`) zostają na `sources.index`.
- Częściowe przeładowanie `only: ['filters']` z `?source={id}` nie liczy `sources` ani `sourcePanel` (wzór `LocalitiesTest.php:167-176` i `SourcesTest.php:821-845`).
- `OwnershipBoundariesTest`: `?source=` cudzego źródła na `sources.index` daje 404 i nic nie zapisuje (wzór `?locality=`, `:179-193`).

#### 3. Pomocnik E2E

**File**: `tests/e2e/support/api.ts`

**Intent**: `createSource` przyjmuje nowe przekierowanie `store`.

**Contract**: `expectRedirect(response, /^\/sources\?source=\d+$/)` i `idFromLocation(response, 'source')`. Żądanie z `page.request` nie wysyła Referer, więc w adresie nie ma filtrów.

### Success Criteria:

#### Automated Verification:

- Testy źródeł i przepięte testy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/DeleteImpactTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonsTest.php tests/Feature/EventSourcesTest.php`
- Testy własności przechodzą (z nowym `?source=` i zmienionym hostem): `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php`
- Przywracanie kopii nadal serwuje pliki: `vendor/bin/sail artisan test --compact tests/Backup/BackupRestoreTest.php`
- Larastan bez błędów: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`
- Wayfinder i typy: `vendor/bin/sail artisan wayfinder:generate && vendor/bin/sail npm run types:check`

**Implementation Note**: Faza nie ma kroków ręcznych. Po zielonej weryfikacji przejdź do fazy 3. `fact-source-scan.spec.ts` jest do fazy 3 oczekiwanie czerwony.

---

## Faza 3: Panel źródła na liście

### Overview

Panel źródła w wariancie szerokim, z edycją, dodawaniem z lokalnym podglądem i usuwaniem. Otwierają go galeria, tabela i „Dodaj źródło”. Filtry zachowują klucz panelu.

### Changes Required:

#### 1. Rejestr paneli i typy

**File**: `resources/js/lib/side-panel-url.ts`, `resources/js/types/*` (tam, gdzie leżą `Source` i `SourceConfirmation`)

**Intent**: Klucz `source` z właściwością `sourcePanel`, żeby `withPanel`, `panelVisitOptions.only` i `useSidePanelNavigation('source')` działały bez dodatkowego kodu.

**Contract**: `PANEL_PROPS.source = 'sourcePanel'` i typ `SourcePanelProps = { source: Source | null; confirms: SourceConfirmation[] } | null`. Test `side-panel-url.test.ts:44-49` pokrywa nowy wpis automatycznie.

#### 2. Filtry zachowujące klucz panelu

**File**: `resources/js/lib/sources-index.ts`, `resources/js/lib/sources-index.test.ts`

**Intent**: Odpowiednik `withLocalitiesSearch`. Wizyta filtra zmienia tylko `q/kind/view/sort` w bieżącym adresie, a `?source=` i inne parametry zostają (naprawa F1 z S-01 dla źródeł).

**Contract**: `withSourcesQuery(url: string, filters: Partial<SourcesFilters>): string`. Ustawia klucze z `buildSourcesQuery(filters)`, usuwa te, których `buildSourcesQuery` nie zwraca (wartości domyślne) i zachowuje pozostałe parametry oraz hash. Przypadki Vitest: zachowanie `source=5` i `source=new`, usunięcie `sort` przy powrocie do galerii, usunięcie pustego `q`, brak zmian w innych parametrach.

#### 3. Przeglądarka skanu w panelu

**File**: `resources/js/components/sources/source-viewer.tsx`

**Intent**: Przeglądarka wypełnia wysokość rodzica zamiast liczyć ją od okna, żeby w panelu zajmowała całe ciało.

**Contract**: Propsy `{ src, alt, className? }`. Pasek przycisków bez zmian (Powiększ, Pomniejsz, Dopasuj), ramka obrazu to `flex-1 min-h-0` w kolumnie `h-full`. `minScale 0.5`, `maxScale 32` bez zmian.

#### 4. Panel źródła

**File**: `resources/js/components/sources/source-panel.tsx` (nowy)

**Intent**: Jeden komponent dla „Nowe źródło” i edycji, wzorem `LocalityPanel`, w wariancie `wide`. Zawiera to, co dziś robią strona podglądu, modal edycji i modal dodawania. Pola opisu (`SourceKindField`, `SourceDescriptionFields`) zostają bez zmian.

**Contract**:
- `SourcePanel({ source: Source | null, confirms, kinds, recordTypes, onRequestClose, onDirtyChange })` → `<SidePanel variant="wide" title={source?.display_name ?? 'Nowe źródło'} contentKey={source?.id ?? 'new'}>`, `<Form key={source?.id ?? 'new'} setDefaultsOnSuccess options={{ preserveScroll: true }}>` (store albo update) i `DirtyReporter`.
- Układ: ciało panelu to kontener (`@container`). Od 36rem szerokości kontenera są dwie kolumny `minmax(0,1fr) | 20rem`, a skan wypełnia wysokość ciała. Kolumna formularza przewija się sama. Poniżej 36rem (pełny ekran poniżej `md`) skan ma stałą wysokość, a pod nim leży formularz i całe ciało się przewija. Przy 1366 px panel ma zawsze dwie kolumny (decyzja z badań nr 3).
- Edycja: kolumna skanu to `SourceViewer` z `SourceController.file.url(id)`. Kolumna formularza zawiera: `SourceKindField`, nagłówek „Opis”, `SourceDescriptionFields` z ostrzeżeniem o usuwanych polach, ukryte pole `discard_record_fields` i zagnieżdżony modal „Zmienić rodzaj źródła?” przeniesiony 1:1 z `source-edit-dialog.tsx:138-176,229-253` (`onBefore` + `erasedRecordFields`). Pod formularzem jest sekcja „Potwierdza” przeniesiona z `show.tsx:107-136` (`PersonPreviewLink`, pusty stan „To źródło nie potwierdza jeszcze żadnego wydarzenia.”). Błąd walidacji przewija do pierwszego `[data-slot="input-error"]` w kolumnie formularza.
- Dodawanie: kolumna skanu przed wyborem pliku zawiera pole „Plik” (`accept="image/jpeg,image/png,image/webp"`), podpowiedź „JPG, PNG lub WebP, do 20 MB” i `InputError` dla `file`. Po wyborze pokazuje `SourceViewer` z lokalnym object URL, przycisk „Zmień plik” i pasek postępu wysyłania. Kolumna formularza to `SourceKindField`, nagłówek „Opis (opcjonalnie)” i `SourceDescriptionFields` z `fileName` jako placeholderem nazwy. Cykl życia object URL opisuje „Critical Implementation Details”.
- `actions` (nagłówek, tylko edycja): „Usuń” jako wyzwalacz `DeleteSourceDialog` z `confirms`.
- `footer`: „Zapisz” (`type="submit"`, atrybut `form` wskazujący formularz panelu), wyłączony w trakcie wysyłania. Stan wysyłania wychodzi z render-propa obok `isDirty`.

#### 5. Strona listy

**File**: `resources/js/pages/sources/index.tsx`

**Intent**: Strona renderuje panel z `sourcePanel`, używa wspólnego strażnika, a wszystkie wizyty filtrów idą przez `withSourcesQuery` na żywym adresie (`window.location`, jak `localities/index.tsx:59-75`). „Dodaj źródło” otwiera `?source=new` zamiast modala.

**Contract**:
- Props `+ sourcePanel: SourcePanelProps`. `useSidePanelNavigation('source')` i `useUnsavedChangesGuard({ openKey, open })`, gdzie `openKey = sourcePanel === null ? null : (sourcePanel.source?.id ?? 'new')`. Panel zamyka `guard(close)`. Na stronie renderowany jest `DiscardChangesDialog`.
- „Dodaj źródło” (nagłówek i pusty stan): `<Button asChild><Link href={hrefFor('new')} {...panelVisitOptions} onClick={(e) => guardLink(e, 'new')}>`.
- `visit()` buduje adres przez `withSourcesQuery(liveUrl, merged)` z `only: ['filters']`, bez innych zmian w logice debounce i historii.

#### 6. Galeria i tabela

**File**: `resources/js/components/sources/sources-gallery.tsx`, `resources/js/components/sources/sources-table.tsx`

**Intent**: Karta galerii, nazwa w tabeli i pozycja menu „Otwórz” otwierają panel na tej samej stronie (`hrefFor` + `panelVisitOptions` + `guardLink`). Otwarty rekord jest wyróżniony. Pozycja menu „Edytuj” znika razem z `SourceEditDialog` w tabeli (decyzja: tylko „Otwórz”).

**Contract**: Oba komponenty dostają `openId: number | null`, `hrefFor: (id) => string` i `onOpen: (event, id) => void` (wzór `LocalitiesTable`). Otwarta karta lub wiersz ma `aria-current="true"` i `data-state="selected"` z widocznym wyróżnieniem. Usunięty stan `editing/editSession/editOpen` w tabeli.

### Success Criteria:

#### Automated Verification:

- Vitest (z `withSourcesQuery` i rejestrem paneli): `vendor/bin/sail npm run test:js`
- Format i lint: `vendor/bin/sail npm run check`
- Typy: `vendor/bin/sail npm run types:check`
- Build: `vendor/bin/sail npm run build`
- Testy źródeł nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php`

#### Manual Verification:

- Przy 1366 px panel ma skan obok formularza. Zmierz szerokość skanu (oczekiwane ok. 375 px). Powiększanie do szczegółu wpisu działa, a formularz przewija się niezależnie od skanu.
- Przy 2560 px panel ma ok. 72rem, a skan zajmuje całą resztę obok kolumny 20rem.
- Edycja: zmiana opisu i „Zapisz” daje toast „Zapisano źródło”, a panel i karta na liście pokazują nowe dane. Zmiana rodzaju z aktu z typem lub numerem otwiera „Zmienić rodzaj źródła?”, a Escape zamyka tylko ten modal, nie panel.
- Dodawanie: „Dodaj źródło” → panel „Nowe źródło” z polem pliku w kolumnie skanu → wybór pliku daje lokalny podgląd z powiększeniem → „Zmień plik” podmienia podgląd → „Zapisz” daje panel nowego źródła ze skanem z serwera. W widoku Tabela z filtrem rodzaju i szukaniem filtry zostają po zapisie.
- Usuwanie: „Usuń” w panelu pokazuje listę faktów („stanie się niepotwierdzone” / „pozostanie potwierdzone innym źródłem”). Po potwierdzeniu panel się zamyka, lista zostaje z tymi samymi filtrami i widokiem.
- Strażnik: zmiana pola, a potem klik w inną kartę, X lub Escape pyta „Odrzucić niezapisane zmiany?”. Zmiana filtra rodzaju albo widoku przy otwartym panelu nie zamyka panelu i nie gubi zmian.
- „Potwierdza” → klik w osobę otwiera modal osoby nad panelem źródła, a jego zamknięcie zostawia panel.

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: Wejścia spoza listy i sprzątanie

### Overview

Linki do źródła spoza listy prowadzą prosto do panelu. Usunięcie komponentów, których po S-02 nikt nie używa.

### Changes Required:

#### 1. Wejścia z modala osoby i pickera

**File**: `resources/js/components/persons/overview-tab.tsx`, `resources/js/components/persons/sources-tab.tsx`, `resources/js/components/sources/source-picker-dialog.tsx`

**Intent**: Nazwa źródła przy fakcie i „Otwórz” w zakładce „Źródła” prowadzą na `/sources?source={id}` bez przekierowania, z istniejącym `guardClick` modala osoby. „Dodaj źródło” w pustym pickerze prowadzi na `/sources?source=new`. Etykiety (`aria-label="Otwórz źródło …"`) bez zmian, bo zależy od nich `navigation.ts:65-72`.

**Contract**: `SourceController.index({ query: { source: id } })` i `SourceController.index({ query: { source: 'new' } })` zamiast `SourceController.show(id)` i `sourcesIndex()`. W `resources/js` (poza wygenerowanymi `actions/routes/wayfinder`) nie zostaje żadne `SourceController.show`.

#### 2. Usunięcie martwego kodu

**File**: `resources/js/pages/sources/show.tsx`, `resources/js/components/sources/source-edit-dialog.tsx`, `resources/js/components/sources/source-create-dialog.tsx`, `resources/js/components/sources/source-details.tsx`

**Intent**: Po fazach 2–3 żaden z tych plików nie ma importu ani renderu (`show` przekierowuje, panel zastąpił oba modale i widok szczegółów).

**Contract**: Pliki usunięte. Przed usunięciem `grep` potwierdza zero importów. Trasa `sources.show` zostaje (przekierowanie).

### Success Criteria:

#### Automated Verification:

- Brak odwołań: `grep -rnE "SourceController\.show|source-edit-dialog|source-create-dialog|source-details|sources/show" resources/js --exclude-dir=actions --exclude-dir=routes --exclude-dir=wayfinder` nie zwraca nic
- Format i lint: `vendor/bin/sail npm run check`
- Typy: `vendor/bin/sail npm run types:check`
- Build: `vendor/bin/sail npm run build`
- Testy osób i źródeł: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php`

#### Manual Verification:

- Z modala osoby: nazwa źródła przy fakcie i „Otwórz” w zakładce „Źródła” otwierają `/sources` z panelem tego źródła. Przy brudnym formularzu osoby najpierw pojawia się pytanie strażnika modala osoby.
- Picker źródła w pustym drzewie: „Dodaj źródło” otwiera panel „Nowe źródło”.
- Stary adres `/sources/{id}` (zakładka przeglądarki) otwiera listę z panelem źródła.

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 5.

---

## Faza 5: E2E panelu źródła

### Overview

Pierwszy test E2E panelu bocznego (odłożony przez F-02 i S-01) oraz zawężenie istniejącego testu fakt → źródło do panelu. Fazę prowadzi `/10x-e2e` (pętla ryzyko → eksploracja `playwright-cli` → generowanie → przegląd → celowe uszkodzenie).

### Changes Required:

#### 1. Nawigacja

**File**: `tests/e2e/support/navigation.ts`

**Intent**: Jedno miejsce na lokator panelu źródła, zawężony do dialogu, bo nie-modalny panel zostawia widoczne nagłówki listy obok (`test-plan.md:209`).

**Contract**: `sourcePanel(page, sourceName): Locator` → `page.getByRole('dialog', { name: sourceName })`. Opcjonalnie `openSourceFromList(page, sourceName)` (szukanie po nazwie z sufiksem, potem klik w kartę).

#### 2. Fakt → źródło → skan w panelu

**File**: `tests/e2e/fact-source-scan.spec.ts`

**Intent**: Ta sama ochrona (fakt prowadzi do tego samego źródła i skanu, sha256 bajt w bajt, fakt widoczny z powrotem na źródle) z lokatorami zawężonymi do panelu źródła zamiast całej strony.

**Contract**: Nagłówek, `img` z `src` zawierającym `/sources/{id}/` i link „Urodzenie — {osoba}” szukane w `sourcePanel(page, sourceName)`. Wyrocznia sha256 bez zmian.

#### 3. Nowy spec: edycja z ochroną zmian

**File**: `tests/e2e/source-panel-edit.spec.ts` (nowy)

**Intent**: Pokrywa ryzyka, których Pest nie widzi: panel otwarty z listy, strażnik niezapisanych zmian i zapis, który odświeża panel oraz listę.

**Contract** — scenariusz:
- Przygotowanie: `createSource` z nazwą z sufiksem (`uniqueSuffix`).
- `/sources` → szukanie po nazwie → klik w kartę → `sourcePanel(page, nazwa)` widoczny, w nim `img` z `src` `/sources/{id}/`.
- Zmiana pola „Nazwa” na nową nazwę **zachowującą sufiks** (sprzątanie usuwa źródła po id z `createSource`, ale nazwa z sufiksem jest siatką bezpieczeństwa) → „Zamknij panel” → dialog „Odrzucić niezapisane zmiany?” → „Wróć do edycji” → pole ma dalej nową wartość.
- „Zapisz” → panel ma tytuł z nową nazwą.
- Wyrocznia na danych: ponowne wejście na `/sources?source={id}` (świeże żądanie) pokazuje nową nazwę w polu i w tytule panelu. Bez asercji na toaście (`test-plan.md`, „Pułapki”).
- Celowe uszkodzenie: `UpdateSource` ignoruje `name` → spec czerwony na asercji nazwy. Potem cofnięcie. Dodatkowo `--repeat-each=2`.

### Success Criteria:

#### Automated Verification:

- Nowy spec przechodzi stabilnie: `npx playwright test tests/e2e/source-panel-edit.spec.ts --repeat-each=2`
- Fakt → źródło → skan przechodzi: `npx playwright test tests/e2e/fact-source-scan.spec.ts --repeat-each=2`
- Cały zestaw E2E zielony: `npx playwright test`
- Format i lint: `vendor/bin/sail npm run check`

#### Manual Verification:

- Celowe uszkodzenie `UpdateSource` daje czerwony `source-panel-edit.spec.ts` na asercji nazwy, a po cofnięciu jest zielony.
- Po zielonym przebiegu baza `e2e` nie ma źródeł z prefiksem `e2etest`.

**Implementation Note**: Po zielonej weryfikacji i ręcznym potwierdzeniu zmiana jest gotowa do `/10x-impl-review`.

---

## Testing Strategy

### Unit Tests:

- Vitest `withSourcesQuery`: zachowanie `source` (`5`, `new`), usunięcie `sort` przy `view=galeria`, usunięcie pustego `q`, nietknięte inne parametry i hash.
- `side-panel-url.test.ts` pokrywa wpis `source` w `PANEL_PROPS` bez zmian w teście.

### Integration Tests:

- Pest: resolver `sourcePanel` (`new`, id, nie-cyfry, brak, 404), przekierowania `show/store/destroy` z filtrami z poprzedniego adresu i bez nich, wyrocznia FR-027 na `sourcePanel.confirms`, `?source=` cudzego źródła → 404, host `?person=` z otwartym panelem źródła, częściowe przeładowanie `filters` bez liczenia listy i panelu.
- E2E: edycja z ochroną zmian (nowy spec) oraz fakt → źródło → skan zawężony do panelu.

### Manual Testing Steps:

1. 1366 px: zmierz szerokość skanu w panelu i sprawdź, czy da się odczytać wpis po powiększeniu.
2. Dodaj źródło w widoku Tabela z filtrem „Akty” i szukaniem: podgląd lokalny, „Zmień plik”, zapis, filtry zostają.
3. Edytuj akt z typem i numerem, zmień rodzaj na „Inne”: modal potwierdzenia, Escape zamyka tylko modal.
4. Usuń źródło potwierdzające fakty: lista wpływu, panel zamknięty, filtry zostają.
5. < 768 px: panel pełnoekranowy, Tab nie wychodzi na stronę, po zamknięciu fokus wraca.
6. `/localities`: strażnik działa jak przed fazą 1.

## Performance Considerations

`sourcePanel` jest domknięciem, więc wizyty `only: ['filters']` nie ładują źródła ani `confirms` (test w fazie 2). Wizyty panelu (`panelVisitOptions.only`) nie przeładowują listy `sources`. Eager load `events.participants` i `withCount('sources')` przechodzi bez zmian z `show`.

## Migration Notes

Brak migracji danych. Stare zakładki `/sources/{id}` działają dzięki przekierowaniu. Trasy `sources.file` i `sources.thumbnail` bez zmian (korzysta z nich `tests/Backup/BackupRestoreTest.php:34-35`).

## References

- Research: `context/changes/source-side-panel/research.md` (decyzje 1–7)
- Roadmapa: `context/foundation/roadmap.md` — S-02; PRD FR-006 (`prd.md:110`), FR-027 (`prd.md:181`)
- Wzorzec konsumenta: `app/Http/Controllers/LocalityController.php:21-47,76,92`, `resources/js/components/localities/locality-panel.tsx`, `resources/js/pages/localities/index.tsx`, `resources/js/lib/localities-index.ts:16-28`
- Powłoka: `resources/js/components/side-panel.tsx`, `resources/js/lib/side-panel-url.ts`, `resources/js/hooks/use-side-panel-navigation.ts`
- Dzisiejsze źródło: `app/Http/Controllers/SourceController.php`, `resources/js/pages/sources/show.tsx`, `resources/js/components/sources/source-edit-dialog.tsx`, `source-create-dialog.tsx`, `source-viewer.tsx`, `delete-source-dialog.tsx`
- Wcześniejsze zmiany: `context/archive/2026-10-04-side-panel-shell/`, `context/archive/2026-10-05-places-gazetteer/` (F1, F2 z przeglądu), `context/archive/2026-09-30-source-description-in-modal/`, `context/archive/2026-09-25-scan-delete-with-impact/`
- E2E: `context/foundation/test-plan.md` (wzorzec testu, `:209`), `context/foundation/test-stack.md`, `tests/e2e/support/{api,navigation}.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Wspólny strażnik zmian i `inert` w powłoce

#### Automated

- [x] 1.1 Testy miejscowości przechodzą bez zmian: `vendor/bin/sail artisan test --compact tests/Feature/LocalitiesTest.php` — cf25b21
- [x] 1.2 Format i lint: `vendor/bin/sail npm run check` — cf25b21
- [x] 1.3 Typy: `vendor/bin/sail npm run types:check` — cf25b21
- [x] 1.4 Vitest: `vendor/bin/sail npm run test:js` — cf25b21

#### Manual

- [x] 1.5 Strażnik w `/localities` działa jak przed refaktorem (przełączenie, X, Escape, klik w otwarty wiersz) — cf25b21
- [x] 1.6 Poniżej 768 px Tab nie wychodzi z panelu, fokus wraca po zamknięciu; od 768 px lista obok panelu dostępna — cf25b21

### Phase 2: Backend panelu źródła

#### Automated

- [x] 2.1 Testy źródeł i przepięte testy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php tests/Feature/DeleteImpactTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonsTest.php tests/Feature/EventSourcesTest.php` — 7668ecf
- [x] 2.2 Testy własności przechodzą (z nowym `?source=` i zmienionym hostem): `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php` — 7668ecf
- [x] 2.3 Przywracanie kopii nadal serwuje pliki: `vendor/bin/sail artisan test --compact tests/Backup/BackupRestoreTest.php` — 7668ecf
- [x] 2.4 Larastan bez błędów: `vendor/bin/sail composer types:check` — 7668ecf
- [x] 2.5 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent` — 7668ecf
- [x] 2.6 Wayfinder i typy: `vendor/bin/sail artisan wayfinder:generate && vendor/bin/sail npm run types:check` — 7668ecf

### Phase 3: Panel źródła na liście

#### Automated

- [x] 3.1 Vitest (z `withSourcesQuery` i rejestrem paneli): `vendor/bin/sail npm run test:js` — 8215c92
- [x] 3.2 Format i lint: `vendor/bin/sail npm run check` — 8215c92
- [x] 3.3 Typy: `vendor/bin/sail npm run types:check` — 8215c92
- [x] 3.4 Build: `vendor/bin/sail npm run build` — 8215c92
- [x] 3.5 Testy źródeł nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php` — 8215c92

#### Manual

- [x] 3.6 1366 px: skan obok formularza, szerokość skanu zmierzona, powiększanie działa, formularz przewija się niezależnie — 8215c92
- [x] 3.7 2560 px: panel ok. 72rem, skan zajmuje resztę obok kolumny 20rem — 8215c92
- [x] 3.8 Edycja: zapis odświeża panel i listę; zmiana rodzaju pyta, Escape zamyka tylko modal — 8215c92
- [x] 3.9 Dodawanie: pole pliku w kolumnie skanu, lokalny podgląd, „Zmień plik”, zapis otwiera nowe źródło z zachowanymi filtrami — 8215c92
- [x] 3.10 Usuwanie: lista wpływu, panel zamknięty, filtry i widok zachowane — 8215c92
- [x] 3.11 Strażnik pyta przy przełączeniu/X/Escape; zmiana filtra nie zamyka panelu ani nie gubi zmian — 8215c92
- [x] 3.12 „Potwierdza” otwiera modal osoby nad panelem, zamknięcie zostawia panel — 8215c92

### Phase 4: Wejścia spoza listy i sprzątanie

#### Automated

- [x] 4.1 Brak odwołań do usuniętych plików i `SourceController.show` w `resources/js` (grep pusty) — 148a063
- [x] 4.2 Format i lint: `vendor/bin/sail npm run check` — 148a063
- [x] 4.3 Typy: `vendor/bin/sail npm run types:check` — 148a063
- [x] 4.4 Build: `vendor/bin/sail npm run build` — 148a063
- [x] 4.5 Testy osób i źródeł: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php` — 148a063

#### Manual

- [x] 4.6 Z modala osoby fakt i zakładka „Źródła” otwierają panel źródła na `/sources` (strażnik osoby najpierw) — 148a063
- [x] 4.7 Pusty picker: „Dodaj źródło” otwiera panel „Nowe źródło” — 148a063
- [x] 4.8 Stary adres `/sources/{id}` otwiera listę z panelem źródła — 148a063

### Phase 5: E2E panelu źródła

#### Automated

- [x] 5.1 Nowy spec przechodzi stabilnie: `npx playwright test tests/e2e/source-panel-edit.spec.ts --repeat-each=2` — 9396f5a
- [x] 5.2 Fakt → źródło → skan przechodzi: `npx playwright test tests/e2e/fact-source-scan.spec.ts --repeat-each=2` — 9396f5a
- [x] 5.3 Cały zestaw E2E zielony: `npx playwright test` — 9396f5a
- [x] 5.4 Format i lint: `vendor/bin/sail npm run check` — 9396f5a

#### Manual

- [x] 5.5 Celowe uszkodzenie `UpdateSource` daje czerwony spec, po cofnięciu zielony — 9396f5a
- [x] 5.6 Po przebiegu baza `e2e` bez źródeł z prefiksem `e2etest` — 9396f5a
