# Siatka źródeł z filtrami (S-10) — plan implementacji

## Overview

Autor przegląda listę `/sources` jako gęstą siatkę mniejszych kart (rodzaj, typ, parafia lub cmentarz, rok, archiwum) i zawęża ją filtrami po rodzaju, parafii/miejscu, roku i archiwum (FR-005, roadmapa S-10). Nowe filtry — miejsce ze spisu, zakres lat od–do i archiwum z użytych wartości — dołączają do istniejącego wyszukiwania i przełącznika rodzaju, żyją w adresie i działają w obu widokach: Galeria i Tabela.

## Current State Analysis

- Serwer wysyła całą listę źródeł drzewa bez filtrowania (`app/Http/Controllers/SourceController.php:45-47`); filtrowanie i sortowanie dzieje się w przeglądarce (`resources/js/lib/sources-index.ts:105-138`, `:169-208`). To decyzja z `context/archive/2026-09-30-sources-list-views/plan-brief.md` przy skali „mała” z PRD (`context/foundation/prd.md:25`).
- Kontrakt adresu: `q`, `kind` (`akty|nagrobki|inne`, domyślnie `wszystkie`), `view` (`galeria|tabela`), `sort` (tylko w tabeli). Serwer normalizuje i odsyła je w propsie `filters` (`SourceController.php:38-49`, `:185-251`); klient buduje adres przez `buildSourcesQuery`/`withSourcesQuery` (`sources-index.ts:49-93`), które podmieniają tylko swoje klucze, zostawiając klucze paneli.
- `sources` jest domknięciem, więc częściowe przeładowanie `only: ['filters']` nie wykonuje zapytania o źródła — pilnuje tego test `tests/Feature/SourcesTest.php:908`, `:931`.
- Po dodaniu źródła `listQuery` przenosi filtry z Referer, a `filtersShowing` zdejmuje `kind`/`q`, które ukryłyby nowe źródło (`SourceController.php:110-147`, `:202-222`; testy `SourcesTest.php:954-1067`).
- Liczniki przełącznika rodzaju idą za wyszukiwaniem i ignorują wybrany rodzaj; rodzaj z licznikiem 0 jest wyłączony (`sources-index.ts:100-138`, `pages/sources/index.tsx:580-616`).
- Karta galerii już pokazuje nazwę, „rodzaj · rok · parafia/cmentarz”, archiwum i „potwierdza N” (`resources/js/components/sources/sources-gallery.tsx:46-83`, `lib/source-description.ts:234-256`); siatka ma 2/3/4 kolumny wg szerokości ekranu (`sources-gallery.tsx:24`), nie obszaru listy — przy otwartym panelu bocznym karty się ściskają.
- Miejsce źródła: akt → `parish_id` (siedziba parafii przez `parish.locality`), nagrobek → `cemetery_locality_id`; `SourceResource` ma `parish_id`, `parish_label`, `parish_name`, `locality_name` (cmentarz albo siedziba), `cemetery_locality_id`, ale nie id siedziby parafii (`app/Http/Resources/SourceResource.php:84-118`). „Inne” nie ma miejsca.
- S-04 dał czystą funkcję `filterSourcesByPlaceAndYear` (fragment tekstu + dokładny rok, `resources/js/lib/source-place-year-filter.ts:11`) i wzorzec komunikatu „Ukryto N źródeł bez roku lub miejsca” + „Wyczyść filtry” (`components/sources/source-picker-dialog.tsx:297-320`). Lista `/sources` NIE używa tej funkcji (inne znaczenie miejsca i roku — patrz Key Decisions w briefie); wybór źródła do faktu zostaje bez zmian.
- Archiwum to wolny tekst; lista różnych wartości drzewa jest już liczona dla panelu (`SourceController.php:92-98`).
- Zakres lat ma w repo konwencję `year_from`/`year_to`, etykiety „rok od”/„rok do” (`app/Http/Requests/PersonUpdateRequest.php:59-60`, `:87-88`).
- Testy: Pest `tests/Feature/SourcesTest.php` (normalizacja `:852-905`, częściowe przeładowanie `:908-949`, Referer `:954-1067`, klucze zasobu `:51`), Vitest `resources/js/lib/sources-index.test.ts`; brak E2E listy — tylko helper `openSourceFromList` (`tests/e2e/support/navigation.ts:110`).

## Desired End State

- Galeria to gęsta siatka: liczba kolumn zależy od szerokości obszaru listy (ok. 2 na telefonie do 6 na szerokim ekranie, mniej przy otwartym panelu), karta ma mniejszą miniaturę i tę samą treść co dziś.
- Pasek `role="search"` ma obok wyszukiwania i rodzaju: „Parafia / miejsce” (wybór z grupami Miejscowości i Parafie — tylko wpisy użyte w źródłach), „Rok od”, „Rok do”, „Archiwum” (wybór z użytych wartości) i „Wyczyść filtry” (gdy cokolwiek aktywne); na wąskim ekranie pola zawijają się.
- Wybór miejscowości pokazuje akty parafii z siedzibą w tej miejscowości i nagrobki na jej cmentarzu; wybór parafii — tylko jej akty. Zakres lat jest włącznie, każda granica opcjonalna; odwrócony zakres (od > do) nie pasuje do niczego i kończy się pustym wynikiem z „Wyczyść filtry”. Archiwum — dokładna wartość.
- Źródła bez danych potrzebnych aktywnemu filtrowi są ukryte, a nad listą stoi „Ukryto N źródeł bez roku / miejsca / archiwum” (nazywa tylko aktywne kryteria).
- Liczniki rodzaju liczą po wszystkich filtrach poza rodzajem.
- Filtry są w adresie (`place`, `year_from`, `year_to`, `archive`), przeżywają przeładowanie, dodanie i usunięcie źródła; po dodaniu źródła filtr, który by je ukrył, zostaje zdjęty.
- Tabela pokazuje te same przefiltrowane źródła i nadal sortuje.

Weryfikacja: Pest (`SourcesTest`), Vitest (`sources-index.test.ts`), `npm run check`, `types:check`, Larastan, E2E `sources-grid-with-filters.spec.ts`, ręczny przegląd siatki i paska.

### Key Discoveries:

- Częściowe przeładowanie `only: ['filters']` nie może dotknąć tabeli `sources` (`SourcesTest.php:908`) — dlatego normalizacja nowych parametrów na serwerze sprawdza tylko kształt, nie przynależność do drzewa.
- Wysłanie formularza panelu (`<Form>` w `source-panel.tsx:426`) przeładowuje wszystkie propsy, a otwarcie panelu tylko propsy panelu (`lib/side-panel-url.ts:17-27`) — nowe listy opcji odświeżą się po zapisie, dodaniu i usunięciu źródła bez dodatkowej pracy.
- Panel źródła już używa `@container` (`source-panel.tsx:162`) — Tailwind v4 obsługuje zapytania kontenerowe bez wtyczki.
- `SuggestField` w trybie rekordu opiera się na jednym liczbowym `id` (`components/suggest-field.tsx:23-33, 45`), więc nie zmieści miejscowości i parafii na jednej liście; `ui/select.tsx` (Radix, grupy z etykietami) już jest w repo.

## What We're NOT Doing

- Filtrowanie, paginacja ani wyszukiwanie po stronie serwera — skala „mała” z PRD, lista ładuje się w całości jak dziś.
- Zmian w wyborze źródła do faktu (`source-picker-dialog.tsx`, `filterSourcesByPlaceAndYear`) — zostaje fragment tekstu i dokładny rok z S-04.
- Sortowania w widoku Galeria (zostaje kolejność serwera: od najnowszych) ani nowych kolumn sortowania tabeli (parafia/archiwum — wykluczone już w S-03).
- Opcji „bez roku / bez miejsca / bez archiwum” w filtrach — źródła bez danych są ukrywane i liczone, nie wyszukiwane.
- Wielokrotnego wyboru w filtrach (kilka parafii, kilka archiwów naraz) i zwijania polskich znaków.
- Nowej treści kart (miniatura, nazwa, opis, archiwum, „potwierdza N” jak dziś) ani zmian panelu źródła.
- Zarządzanego spisu archiwów (Non-Goal PRD, `prd.md:242`).

## Implementation Approach

Rozszerzamy istniejący kontrakt zamiast budować nowy: cztery nowe klucze adresu idą tą samą drogą co `q`/`kind` — normalizacja i echo na serwerze, budowa adresu w `sources-index.ts`, filtrowanie w przeglądarce w jednej funkcji, która liczy też liczniki rodzaju i ukryte nieopisane źródła. Serwer dokłada dwie listy opcji (miejsca i archiwa) jako domknięcia oraz id siedziby parafii w zasobie, żeby klient mógł dopasować miejscowość bez dodatkowych zapytań. Kolejność faz: kontrakt backendu → czysta logika z testami → UI → jeden test E2E ryzyka podłączenia pól, adresu i siatki.

## Critical Implementation Details

- **Normalizacja tylko kształtu.** `place` (`^(miejscowosc|parafia)-\d+$`), `year_from`/`year_to` (liczba całkowita 1–9999; odwrócony zakres `from > to` NIE jest zamieniany — daje pusty wynik, bo zamiana na serwerze kolidowałaby z wpisywaniem w polach lat) i `archive` (przycięty, maks. 255 znaków) normalizuje się bez zapytań do bazy, inaczej złamie się niezmiennik z `SourcesTest.php:908`. Wartość `place`/`archive`, której nie ma w liście opcji (np. po scaleniu miejscowości), klient traktuje jak nieustawioną przez `resolveFilters` (Faza 2): pole pokazuje „Wszystkie”, a najbliższa wizyta paska usuwa ją z adresu. Dotyczy to też usunięcia ostatniego źródła z danym miejscem/archiwum i zmiany archiwum w panelu, nie tylko scalenia.
- **Zapytanie o miejscowości użyte w źródłach** łączy dwa warunki przez `orWhere` — musi być zgrupowane w `where(fn …)`, inaczej `orWhereHas` wycieknie poza zakres `$tree->localities()` i zwróci miejscowości innych drzew.
- **Pola roku** mają stan lokalny i podążają do adresu z tym samym 300 ms opóźnieniem i `replace: true` co wyszukiwanie; wybory (miejsce, archiwum, rodzaj) idą od razu. Klik w kartę przy czekającej wizycie musi zabrać do adresu także czekające lata (dziś robi to `openFromList` dla `q`, `index.tsx:478-505`).

## Faza 1: Kontrakt adresu i dane (backend)

### Overview

Serwer przyjmuje, normalizuje i odsyła cztery nowe filtry, dostarcza listy opcji miejsc i archiwów, zasób źródła dostaje id siedziby parafii, a przekierowania po dodaniu/usunięciu przenoszą nowe filtry.

### Changes Required:

#### 1. Normalizacja i echo nowych filtrów

**File**: `app/Http/Controllers/SourceController.php`

**Intent**: Nowe filtry przeżywają przeładowanie i niepoprawne wartości z adresu nie trafiają do klienta.

**Contract**: `filters` = `{q, kind, view, sort, place: string ('' gdy brak), year_from: int|null, year_to: int|null, archive: string ('' gdy brak)}`. Nowe prywatne metody `normalizePlace`, `normalizeYear`, `normalizeArchive` (wzorzec `normalizeKind`); `year_from`/`year_to` echo bez zamiany, także gdy odwrócone. Bez zapytań do bazy (patrz Critical Implementation Details).

#### 2. Listy opcji

**File**: `app/Http/Controllers/SourceController.php`

**Intent**: Pola „Parafia / miejsce” i „Archiwum” pokazują tylko wartości, które coś znajdą.

**Contract**: Nowe propsy-domknięcia na `sources/index`:
- `placeOptions: {localities: {id, name}[], parishes: {id, label}[]}` — miejscowości będące cmentarzem jakiegoś nagrobka lub siedzibą parafii jakiegoś aktu w drzewie; parafie użyte przez akty; obie listy po nazwie.
- `archiveOptions: string[]` — ta sama lista co `panelOptions().archives`; wspólne zapytanie wydzielone do prywatnej metody używanej w obu miejscach.

#### 3. Id siedziby parafii w zasobie

**File**: `app/Http/Resources/SourceResource.php`, `resources/js/types/source.ts`

**Intent**: Klient dopasowuje akt do wybranej miejscowości po id, bez porównywania nazw.

**Contract**: Nowe pole `parish_locality_id: int|null` (`$this->parish?->locality_id`; wywołujący już ładują `parish.locality`). Docblock array shape i typ `Source` w TS uzupełnione; test kluczy zasobu (`SourcesTest.php:51`) dostaje nowy klucz.

#### 4. Przenoszenie filtrów po dodaniu i usunięciu

**File**: `app/Http/Controllers/SourceController.php`

**Intent**: Po dodaniu lub usunięciu źródła lista wraca z tymi samymi filtrami, a nowe źródło jest widoczne.

**Contract**: `listQuery` przenosi `place`, `year_from`, `year_to`, `archive` z Referer (te same normalizatory, pominięte puste/domyślne). `filtersShowing` zdejmuje każdy z nich, który ukryłby nowe źródło: `place` niepasujące (miejscowość: cmentarz nagrobka lub siedziba parafii aktu; parafia: `parish_id` aktu), granicę roku, której rok źródła nie spełnia (lub brak roku), `archive` różne od archiwum źródła. Źródło po `StoreSource` ma załadowane `parish` (jeśli nie — `loadMissing('parish')`).

#### 5. Testy

**File**: `tests/Feature/SourcesTest.php`

**Intent**: Kontrakt adresu i przekierowań jest przypięty testami jak dotychczasowe filtry.

**Contract**: Rozszerzenie istniejących testów: domyślne `filters` (`:852`) z nowymi kluczami; echo poprawnych wartości (`place=miejscowosc-3`, `place=parafia-5`, lata, archiwum); odrzucenia (zły prefiks, tablica, `year_from=abc`, 0, 10000, archiwum z samych spacji → ''); odwrócony zakres odesłany bez zamiany; częściowe przeładowanie `filters` z nowymi parametrami nadal bez zapytania o źródła (`:908`). Nowe: `placeOptions` zawiera cmentarz nagrobka i siedzibę parafii aktu, nie zawiera miejscowości nieużytej ani z innego drzewa; `archiveOptions` bez pustych i bez cudzych; `parish_locality_id` w zasobie. Referer: `store` przenosi nowe filtry pasujące do źródła i zdejmuje te, które by je ukryły (osobno miejsce, rok, archiwum); `destroy` przenosi je.

### Success Criteria:

#### Automated Verification:

- Testy źródeł przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Faza 2: Logika filtrowania (lib)

### Overview

Czysta logika w `sources-index.ts` (plus przepięcie wywołań na stronie): typy filtrów, budowa adresu, `resolveFilters` i jedna funkcja filtrująca, która zwraca widoczne źródła, liczniki rodzaju i liczbę ukrytych nieopisanych.

### Changes Required:

#### 1. Typy i adres

**File**: `resources/js/lib/sources-index.ts`

**Intent**: Każda wizyta `/sources` zachowuje wszystkie filtry i klucze paneli, jak dziś dla `q`/`kind`.

**Contract**: `SourcesFilters` dostaje `place: string`, `year_from: number | null`, `year_to: number | null`, `archive: string`. `buildSourcesQuery` pomija puste/null; `withSourcesQuery` podmienia też klucze `place`, `year_from`, `year_to`, `archive`. Typy `SourcesPlaceOptions` dla nowego propsa.

#### 2. Filtrowanie, liczniki, ukryte

**File**: `resources/js/lib/sources-index.ts`

**Intent**: Jedna reguła filtrowania dla galerii, tabeli, licznika statusu i liczników rodzaju.

**Contract**: `filterSources(sources, filters)` (efektywne filtry z `resolveFilters`) → `{visible, counts, hidden: {count, missing: ('year'|'place'|'archive')[]}}` (zastępuje obecną sygnaturę `(sources, q, kind)`; wszyscy wywołujący zaktualizowani). Reguły:
- Wyszukiwanie bez zmian (`matchesSearch`).
- `place=miejscowosc-L`: nagrobek z `cemetery_locality_id === L` albo akt z `parish_locality_id === L`; `place=parafia-P`: akt z `parish_id === P`.
- `resolveFilters(filters, placeOptions, archiveOptions)` zwraca efektywne filtry: `place` spoza `placeOptions` i `archive` spoza `archiveOptions` zamieniane na `''`. Wołana raz na górze strony; jej wynik zasila pola, bazę scalania w `visit()` (ślepa wartość znika z adresu przy najbliższej wizycie), `filterSources`, `isFiltering` i status. `filterSources` i `isFiltering` przyjmują już efektywne filtry, więc nie dostają list opcji.
- Rok: `year_from <= year <= year_to`, każda granica opcjonalna; odwrócony zakres nie pasuje do żadnego źródła; źródło bez roku nie pasuje do aktywnego zakresu.
- Archiwum: dokładna równość.
- `counts` liczą źródła pasujące do wszystkich filtrów poza rodzajem.
- `hidden.count`: źródła pasujące do wyszukiwania i rodzaju, ukryte przez aktywny filtr i bez danych, których ten filtr potrzebuje: brak roku; brak miejsca = `parish_id === null` i `cemetery_locality_id === null` (więc „inne”, akt bez parafii i nagrobek bez cmentarza), jednakowo dla filtra miejscowości i parafii — nagrobek z cmentarzem lub akt z parafią bez siedziby to zwykłe niedopasowanie, nie „bez miejsca”; brak archiwum. `missing` — kryteria aktywne, których brak danych dotyczył, w stałej kolejności rok, miejsce, archiwum.
- Funkcja `isFiltering(filters)` — czy cokolwiek poza widokiem i sortowaniem odbiega od domyślnego (steruje „Wyczyść filtry” i statusem).

#### 3. Przepięcie wywołań na stronie listy

**File**: `resources/js/pages/sources/index.tsx`

**Intent**: Bramka typów fazy przechodzi, zanim powstanie nowy UI.

**Contract**: Dwa wywołania `filterSources` (`index.tsx:165`, `:177`) przepięte na nową sygnaturę z efektywnymi filtrami z `resolveFilters`; strona czyta `placeOptions` i `archiveOptions` z propsów (typ `Props`). Bez zmian wyglądu — pola paska dochodzą w Fazie 3.

#### 4. Testy Vitest

**File**: `resources/js/lib/sources-index.test.ts`

**Intent**: Znaczenie każdego filtra i liczników przypięte na przypadkach granicznych.

**Contract**: Fabryka `source()` dostaje `parish_locality_id`. Przypadki: miejscowość łapie akt parafii z siedzibą tam i nagrobek z cmentarzem tam, nie łapie aktu parafii z inną siedzibą; parafia łapie tylko swoje akty (nie nagrobek w tej samej miejscowości); zakres włącznie na obu granicach, sama granica od / do; brak roku ukryty i policzony; odwrócony zakres nie pasuje do niczego; „brak miejsca” tylko dla źródła bez parafii i bez cmentarza (nagrobek z cmentarzem przy `parafia-P` i akt z parafią bez siedziby przy miejscowości nie są „bez miejsca”); `resolveFilters` zeruje `place`/`archive` spoza opcji; archiwum dokładne („AP Poznań” ≠ „AP Poznań, oddz. Gniezno”);  liczniki po miejscu ignorują rodzaj; `hidden.missing` tylko dla aktywnych kryteriów; `buildSourcesQuery`/`withSourcesQuery` z nowymi kluczami (pomijanie pustych, zachowanie `source=`). Istniejące testy `filterSources` przepięte na nową sygnaturę bez zmiany ich asercji.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Faza 3: Pasek filtrów i gęsta siatka (UI)

### Overview

Nowe pola w pasku wyszukiwania, komunikat o ukrytych, puste stany z „Wyczyść filtry” i siatka liczona od szerokości obszaru listy.

### Changes Required:

#### 1. Pasek filtrów

**File**: `resources/js/pages/sources/index.tsx`, nowy `resources/js/components/sources/sources-filters.tsx`

**Intent**: Autor zawęża listę bez opuszczania strony; wszystkie filtry są widoczne i zawijają się na wąskim ekranie.

**Contract**: Wewnątrz `role="search"`: „Parafia / miejsce” (`ui/select`, opcja „Wszystkie” + grupy „Miejscowości” i „Parafie”, wartości `miejscowosc-<id>`/`parafia-<id>`), „Rok od” i „Rok do” (pola liczbowe z etykietami, stan lokalny + 300 ms jak wyszukiwanie), „Archiwum” (`ui/select`, „Wszystkie” + `archiveOptions`), „Wyczyść filtry” (przycisk, widoczny przy `isFiltering`, zeruje `q`, `kind`, `place`, lata, `archive`; zostawia `view`/`sort`). Pola bez opcji (brak miejsc / archiwów w drzewie) są wyłączone. Każda zmiana idzie przez istniejące `visit()` (`index.tsx:77`), a pola i baza scalania biorą efektywne filtry z `resolveFilters` (Faza 2). „Wszystkie” w `ui/select` to wartość-wartownik (np. `__all__`) mapowana na `''` — Radix odrzuca `value=""`, a wartość archiwum jest wolnym tekstem; trigger każdego selecta ma `<Label htmlFor>`/`id`, żeby działał `getByLabel`. Lata: `visit()` z innych kontrolek (miejsce, archiwum, rodzaj, widok) bierze lata z lokalnego stanu pól, nie z `filters`; lista i status liczą po zatwierdzonych latach (z `filters`). `openFromList` zabiera też czekające lata. „Wyczyść filtry” zeruje lokalne `q` i lata oraz kasuje oczekujące timery (jak `clearSearch`), inaczej debounce przywróciłby lata po wyczyszczeniu. Etykiety dostępne (`getByLabel` w E2E): „Parafia / miejsce”, „Rok od”, „Rok do”, „Archiwum”.

#### 2. Status, ukryte i puste stany

**File**: `resources/js/pages/sources/index.tsx`, `resources/js/components/sources/sources-empty-state.tsx`

**Intent**: Autor wie, ile pokazuje filtr i że część źródeł wypadła przez brak opisu.

**Contract**: Status `role="status"` liczy po wszystkich zatwierdzonych (efektywnych) filtrach (ta sama funkcja; tekst „Znaleziono: N …” bez zmian — używa go `openSourceFromList`). Nad siatką/tabelą, gdy `hidden.count > 0`: „Ukryto N źródło/źródła/źródeł bez roku, miejsca lub archiwum” — nazwy tylko z `hidden.missing`, łączone „, ” i „ lub ” (np. „bez roku”, „bez roku lub miejsca”). Pusty wynik: `SourcesEmptyKind` gdy winny jest tylko rodzaj (jak dziś); w pozostałych przypadkach wariant „Brak wyników” z akcją „Wyczyść filtry” (gdy aktywny jest jakikolwiek filtr poza samym wyszukiwaniem) albo dotychczasowe „Wyczyść wyszukiwanie”.

#### 3. Gęsta siatka

**File**: `resources/js/components/sources/sources-gallery.tsx`

**Intent**: Więcej źródeł na ekranie, karta nie ściska się przy otwartym panelu.

**Contract**: Kolumny z zapytań kontenerowych (`@container` na opakowaniu listy), od 2 do 6 kolumn wg szerokości obszaru listy; mniejsze odstępy, padding i miniatura; treść karty i jej znaczniki dostępności (`Link`, `aria-current`, `data-state`) bez zmian. Tabela dostaje te same `visible` co siatka (bez zmian w komponencie).

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Build przechodzi: `vendor/bin/sail npm run build`
- Dotychczasowe E2E źródeł przechodzą: `npx playwright test tests/e2e/source-panel-edit.spec.ts tests/e2e/source-panel-parish.spec.ts`

#### Manual Verification:

- Wybór miejscowości pokazuje akty jej parafii i nagrobki z jej cmentarza; wybór parafii — tylko jej akty; liczniki rodzaju zmieniają się razem z filtrem
- Zakres „od 1890 do 1895” pokazuje źródła z tych lat włącznie i komunikat „Ukryto N … bez roku”, gdy są akty bez roku
- Przeładowanie strony, otwarcie i zamknięcie panelu źródła oraz dodanie nowego źródła zachowują filtry (albo zdejmują ten, który ukryłby nowe źródło)
- „Wyczyść filtry” zeruje wszystko poza widokiem; tabela pokazuje te same źródła co siatka i nadal sortuje
- Siatka: ok. 5–6 kolumn na 1920 px, mniej przy otwartym panelu, 2 na telefonie; pasek zawija się czytelnie przy 375 px i 1366 px

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Faza 4: E2E filtrów (`/10x-e2e`)

### Overview

Jeden test ryzyka przeglądarkowego: pola paska → adres → siatka, z przeładowaniem i czyszczeniem.

### Changes Required:

#### 1. Test filtrów listy

**File**: `tests/e2e/sources-grid-with-filters.spec.ts` (nowy)

**Intent**: Wyłapuje błąd podłączenia, którego Vitest nie widzi: pole nie zmienia adresu, adres nie odtwarza pól albo siatka nie idzie za filtrem.

**Contract**: Setup przez API (`createLocality`, `createParish` z siedzibą tam, `createSource` ×3 z sufiksem: akt tej parafii z rokiem 1892, akt tej parafii z rokiem 1900, akt bez parafii z rokiem 1892). UI: `/sources` → „Parafia / miejsce” = miejscowość → „Rok od” 1890, „Rok do” 1895 → widoczna tylko karta pierwszego aktu (`getByRole('link', { name })`), status „Znaleziono: 1 źródło” → przeładowanie: pola zachowują wartości, ta sama jedna karta → „Wyczyść filtry” → widoczne wszystkie trzy. Bez `waitForTimeout`, sprzątanie przez `cleanupBySuffix`. Weryfikacja celowym błędem: np. pominięcie `parish_locality_id` w dopasowaniu miejscowości albo zgubienie `year_to` w `withSourcesQuery` musi przewrócić asercję.

### Success Criteria:

#### Automated Verification:

- Nowa specyfikacja przechodzi: `npx playwright test tests/e2e/sources-grid-with-filters.spec.ts`
- Cała suita E2E przechodzi: `npx playwright test`

#### Manual Verification:

- Celowo wprowadzony błąd (np. zgubiony `year_to` w adresie) przewraca test, a po cofnięciu test znów przechodzi

**Implementation Note**: Po fazie i automatycznej weryfikacji zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- Vitest `sources-index.test.ts`: znaczenie miejsca (miejscowość vs parafia), zakres lat włącznie i jednostronny, archiwum dokładne, wartości spoza opcji, liczniki rodzaju po wszystkich filtrach, `hidden` tylko dla aktywnych kryteriów, budowa adresu z nowymi kluczami.

### Integration Tests:

- Pest `SourcesTest.php`: normalizacja i echo `place`/`year_from`/`year_to`/`archive` (odwrócony zakres bez zamiany), `placeOptions`/`archiveOptions` ograniczone do drzewa i użytych wartości, `parish_locality_id`, Referer po `store`/`destroy`, częściowe przeładowanie bez zapytania o źródła.
- E2E `sources-grid-with-filters.spec.ts`: miejsce + zakres lat → adres → przeładowanie → „Wyczyść filtry”.

### Manual Testing Steps:

1. Na drzewie z aktami dwóch parafii w jednej miejscowości i nagrobkiem tamże wybierz miejscowość, potem jedną z parafii — sprawdź karty i liczniki.
2. Ustaw tylko „Rok od”, potem tylko „Rok do”, potem odwrócony zakres w adresie (`?year_from=1900&year_to=1890`) — pola pokazują 1900 i 1890, lista jest pusta z „Wyczyść filtry”.
3. Z aktywnym filtrem archiwum dodaj źródło z innym archiwum — po zapisie filtr archiwum jest zdjęty, nowe źródło widoczne w otwartym panelu i na liście.
4. Otwórz panel źródła przy 1366 px — siatka zmniejsza liczbę kolumn, karty nie są ściśnięte.

## Performance Considerations

Filtrowanie w pamięci po całej liście drzewa, jak dotychczas — skala „mała” z PRD. Dwie nowe listy opcji to po jednym zapytaniu przy pełnym przeładowaniu strony; częściowe przeładowanie `filters` ich nie liczy. Przy tysiącach źródeł trzeba by przejść na filtrowanie po stronie serwera — poza zakresem.

## Migration Notes

Brak zmian w schemacie i danych. Stare adresy `/sources?…` działają bez zmian (nowe klucze są opcjonalne).

## References

- Roadmapa: `context/foundation/roadmap.md` (S-10, pytanie o tabelę)
- PRD: `context/foundation/prd.md:107-109` (FR-005)
- Widoki listy i kontrakt adresu: `context/archive/2026-09-30-sources-list-views/plan.md:35-40`, `plan-brief.md`
- Filtr miejsca i roku w wyborze źródła: `context/archive/2026-10-05-source-found-by-place-and-year/plan.md:214-218`, `resources/js/lib/source-place-year-filter.ts`
- Cmentarz jako miejsce nagrobka: `context/archive/2026-10-06-gravestone-cemetery-and-buried/plan.md:34`, `:145`
- Wzorzec „Ukryto N … / Wyczyść filtry”: `resources/js/components/sources/source-picker-dialog.tsx:297-320`
- Konwencja zakresu lat: `app/Http/Requests/PersonUpdateRequest.php:59-60`
- E2E: `tests/e2e/support/navigation.ts:110`, `tests/e2e/support/api.ts:204-260` (createLocality, createParish, createSource), `:372` (cleanupBySuffix)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kontrakt adresu i dane (backend)

#### Automated

- [x] 1.1 Testy źródeł przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/SourcesTest.php` — aa3dd97
- [x] 1.2 Larastan przechodzi: `vendor/bin/sail composer types:check` — aa3dd97
- [x] 1.3 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent` — aa3dd97

### Phase 2: Logika filtrowania (lib)

#### Automated

- [x] 2.1 Vitest przechodzi: `vendor/bin/sail npm run test:js` — c2e493e
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — c2e493e
- [x] 2.3 Format i lint przechodzą: `vendor/bin/sail npm run check` — c2e493e

### Phase 3: Pasek filtrów i gęsta siatka (UI)

#### Automated

- [x] 3.1 Format i lint przechodzą: `vendor/bin/sail npm run check` — a0d0505
- [x] 3.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — a0d0505
- [x] 3.3 Vitest przechodzi: `vendor/bin/sail npm run test:js` — a0d0505
- [x] 3.4 Build przechodzi: `vendor/bin/sail npm run build` — a0d0505
- [x] 3.5 Dotychczasowe E2E źródeł przechodzą: `npx playwright test tests/e2e/source-panel-edit.spec.ts tests/e2e/source-panel-parish.spec.ts` — a0d0505

#### Manual

- [x] 3.6 Wybór miejscowości pokazuje akty jej parafii i nagrobki z jej cmentarza; wybór parafii — tylko jej akty; liczniki rodzaju zmieniają się razem z filtrem — a0d0505
- [x] 3.7 Zakres „od 1890 do 1895” pokazuje źródła z tych lat włącznie i komunikat „Ukryto N … bez roku”, gdy są akty bez roku — a0d0505
- [x] 3.8 Przeładowanie strony, otwarcie i zamknięcie panelu źródła oraz dodanie nowego źródła zachowują filtry (albo zdejmują ten, który ukryłby nowe źródło) — a0d0505
- [x] 3.9 „Wyczyść filtry” zeruje wszystko poza widokiem; tabela pokazuje te same źródła co siatka i nadal sortuje — a0d0505
- [x] 3.10 Siatka: ok. 5–6 kolumn na 1920 px, mniej przy otwartym panelu, 2 na telefonie; pasek zawija się czytelnie przy 375 px i 1366 px — a0d0505

### Phase 4: E2E filtrów (`/10x-e2e`)

#### Automated

- [x] 4.1 Nowa specyfikacja przechodzi: `npx playwright test tests/e2e/sources-grid-with-filters.spec.ts` — 73a8176
- [x] 4.2 Cała suita E2E przechodzi: `npx playwright test` — 73a8176

#### Manual

- [x] 4.3 Celowo wprowadzony błąd (np. zgubiony `year_to` w adresie) przewraca test, a po cofnięciu test znów przechodzi — 73a8176
