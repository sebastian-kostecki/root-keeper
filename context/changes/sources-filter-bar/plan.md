# Pasek filtrów z pigułkami i okienkami na /sources — plan implementacji

## Overview

Wdrażamy wzorzec `FilterBar` z design systemu Root Keeper na `/sources`. Selecty „Parafia / miejsce” i „Archiwum” oraz pola „Rok od” / „Rok do” stojące dziś w jednym rzędzie z wyszukiwaniem zastępuje drugi rząd z pigułkami Miejsce / Lata / Archiwum. Każda pigułka otwiera okienko (`Popover`). Aktywna pigułka pokazuje wartość i ma osobny przycisk ✕. Po prawej stoi widoczna liczba wyników „N z M źródeł”. Filtry działają jak dziś: te same parametry URL, te same reguły (`filterSources`, `resolveFilters`), ten sam `yearError`. Zmienia się sposób wyboru.

## Current State Analysis

- `resources/js/components/sources/sources-filters.tsx:30-217` (`SourcesFilterFields`) rysuje dwa Radix `Select` z etykietami („Parafia / miejsce”, „Archiwum”, wartość pusta = sentinel `__all__`) i dwa `Input type="number"` („Rok od”, „Rok do”). Poniżej `sm` wszystko siedzi w `Collapsible` za „Filtry (N)” (`order-*` + `sm:contents`), a „Wyczyść filtry” to `outline` w tym samym rzędzie.
- `resources/js/pages/sources/index.tsx:403-496` ma jeden rząd `role="search"` (`flex-wrap`, kolejność przez `order-*`): `SearchField`, rodzaj, widok, potem pola filtrów. Ukryty status `sr-only role="status"` (`:511-513`) mówi „Znaleziono: N źródło/źródła/źródeł” albo „Brak wyników” tylko przy `isFiltering`, licząc z **zatwierdzonych** filtrów (`committedCount`, `:350-356`). Dzięki temu ogłasza się raz na pauzę, a nie przy każdym klawiszu.
- Stan lat: `yearFrom`/`yearTo` (tekst) żyje w stronie, wspólny debounce 300 ms z wyszukiwaniem (`:232-278`), `yearError` pojawia się 300 ms po ostatnim klawiszu (`:217-230`). Miejsce i archiwum idą natychmiast przez `visit({ place })` / `visit({ archive })`. `visit()` scala wszystko przez `withSourcesQuery` (`:147-192`).
- `resources/js/lib/sources-index.ts` ma potrzebną logikę: `filterSources` (`:333-393`, zwraca `visible`, `counts`, `hidden`), `resolveFilters` (`:139-160`), `yearFieldsError` (`:183-213`, komunikat „„Rok od” nie może być późniejszy niż „Rok do”.”), `hiddenFilterCount` (`:228-236`, liczy `year_from` i `year_to` osobno → zakres = 2), `isFiltering` (`:216-226`). Testy: `resources/js/lib/sources-index.test.ts` (m.in. `hiddenFilterCount` `:277-295`).
- Format lat: `lib/year-range.ts` (`formatYearRange`) pisze „1900–1910” bez spacji (pobyty). Format okresu życia ze spacjami „A – B” jest w `lib/lifespan.ts`. Design system każe pisać lata pigułki jak okres życia: „1880 – 1900”, „od 1880”, „do 1900”.
- Reszta design systemu już jest w kodzie: `focusRingOffset` (`lib/focus-ring.ts:16-17`), pełny `ring-ring` + `ring-[3px]` w `components/ui/*` (np. `button.tsx:8`, `input.tsx:12`). `components/ui/popover.tsx` nie istnieje, `@radix-ui/react-popover` nie ma w `package.json` (zgoda na dodanie: 2026-10-12). `components.json`: styl `new-york`, ikony lucide.
- `resources/js/lib/view-tokens.test.ts:31` skanuje `components/sources/sources-filters.tsx` i `pages/sources/index.tsx`: żadnych heksów, klas palety, arbitralnych pikseli ani `style=`.
- E2E zależne od dzisiejszych pól i statusu:
  - `tests/e2e/sources-grid-with-filters.spec.ts:62-99` — `getByLabel('Parafia / miejsce')`, `getByLabel('Rok od'|'Rok do')`, `getByText('Znaleziono: 1 źródło')`, `toHaveText('Wszystkie')`;
  - `tests/e2e/sources-page-states.spec.ts:33-50` (błąd zakresu), `:95-121` (telefon: „Filtry”, „Rok od”, „Filtry (1)”);
  - `tests/e2e/support/navigation.ts:123-131` (`openSourceFromList` czeka na „Znaleziono: 1 źródło”; używa go `tests/e2e/source-panel-edit.spec.ts`).
- E2E działają równolegle na jednym koncie (`playwright.config.ts:17,35`), więc łączna liczba źródeł M w „N z M” jest w testach zmienna.

## Desired End State

- **Rząd 1** (`role="search"`): `SearchField`, `SegmentedToggle` rodzaju z licznikami, po prawej (`ml-auto`) `SegmentedToggle` widoku.
- **Rząd 2** („Filtry”): pigułki Miejsce, Lata, Archiwum; „Wyczyść filtry” (`ghost sm`, ta sama reguła widoczności co dziś); po prawej `role="status"` z „12 z 64 źródeł” przy filtrowaniu albo „64 źródła” bez filtrów.
- **Pigułka nieaktywna**: ramka przerywana `border-input`, `text-muted-foreground`, ikona `Plus`, sama nazwa.
- **Pigułka aktywna**: `bg-accent text-accent-foreground`, ramka `primary/35`, ikona filtru, „Miejsce: **Luboml**” i osobny przycisk ✕ „Usuń filtr: miejsce Luboml”.
- **Pigułka otwarta**: obwódka `primary`. Fokus: `focusRingOffset`.
- **Pigułka bez danych** (brak miejsc lub archiwów): `disabled`, krycie 50%, z powodem w podpowiedzi i opisie dla czytnika.
- **Okienko Miejsce i Archiwum**: lista jednego wyboru.
  - Pierwsza pozycja „Wszystkie miejsca” / „Wszystkie archiwa”.
  - Przy każdej pozycji liczba źródeł, jakie pokaże lista po wybraniu tej pozycji. Pozycja z 0 jest wyszarzona i pomijana strzałkami.
  - Wyszukiwarka tylko powyżej 7 pozycji. Miejsce ma grupy „Miejscowości” / „Parafie”.
  - Wybór zamyka okienko i od razu filtruje.
- **Okienko Lata**:
  - pola „Rok od” / „Rok do” (`inputMode="numeric"`) podpięte pod ten sam stan i debounce co dziś;
  - błąd zakresu pod polami (`yearError`, `aria-invalid`);
  - szybki wybór półwieczy wyliczony z lat źródeł;
  - „Wyczyść lata”.
- **Poniżej `sm`**: rząd 1 to wyszukiwanie + widok, potem pełna szerokość rodzaju. Rząd 2 to „Filtry (N)” (`outline`, N = liczba aktywnych pigułek) i liczba wyników po prawej. Po rozwinięciu na tle `muted` stoją pigułki jedna pod drugą (`h-11`, pełna szerokość) i „Wyczyść filtry”.
- Adresy, filtrowanie, `hidden` („Ukryto N źródeł bez …”), puste stany i obsługa błędu wizyty działają jak dziś.

### Key Discoveries:

- Liczby przy pozycjach nie potrzebują serwera: `filterSources(sources, { ...filters, q, place: option }).visible.length` daje dokładnie to, co pokaże lista po wyborze (`lib/sources-index.ts:333`).
- Status zatwierdzony vs. wpisany: liczba wyników musi iść za `filters` (zatwierdzonymi), nie za wpisywanym tekstem. Inaczej `role="status"` ogłasza każdy klawisz (`pages/sources/index.tsx:349-350`).
- `hasOtherFilters = hiddenFilterCount(filters) > 0` (`pages/sources/index.tsx:369`) zależy tylko od `> 0`. Zmiana semantyki licznika (zakres = 1) jej nie rusza.
- `filters.place` / `filters.archive` są już rozwiązane (`resolveFilters`), więc etykieta pigułki zawsze ma pozycję w opcjach.

## What We're NOT Doing

- Żadnych zmian w backendzie, parametrach URL (`place`, `year_from`, `year_to`, `archive`), normalizacji ani w `filterSources` / `resolveFilters`.
- Bez zmiany etykiet „Rok od” / „Rok do” i komunikatu `yearFieldsError` na wersję z design systemu („Od roku” / „Do roku”). Zostają dzisiejsze; komunikat cytuje widoczne etykiety.
- Bez nowych endpointów i bez zapytań o liczby. Wszystko liczy przeglądarka z `sources`.
- Bez `cmdk` / shadcn `Command` (nowa zależność bez zgody). Lista w okienku jest własna, na `role="listbox"`.
- Bez zmian w przełączniku rodzaju, widoku, `SearchField`, pustych stanach, tabeli i galerii (poza ich miejscem w rzędach).
- Bez wzorca FilterBar na `/persons` i `/places`. Ten plan dotyczy tylko `/sources`.
- Bez zmian zachowania w E2E. Zmieniają się lokatory i oczekiwany tekst statusu, nie asercje biznesowe (które karty widać, co jest w adresie).

## Implementation Approach

Najpierw czysta logika w `lib/sources-index.ts` z testami Vitest: licznik pigułek, liczby przy pozycjach, etykieta lat, półwiecza, tekst statusu. Strona i komponent tylko je wywołują. Potem `ui/popover.tsx` z shadcn i przepisany `sources-filters.tsx` jako zestaw małych części: `FilterPill`, okienko z listą jednego wyboru, okienko lat. Wszystkie są sterowane z `pages/sources/index.tsx` tymi samymi callbackami co dziś (`onPlaceChange`, `onArchiveChange`, `onYearFromChange`, `onYearToChange`, `onClear`). Na końcu E2E przechodzą na role pigułek, okienek i opcji.

## Critical Implementation Details

- **User experience spec — fokus.** Po wyborze pozycji okienko się zamyka, a fokus wraca na pigułkę (domyślne zachowanie Radix Popover). Po ✕ pigułka staje się nieaktywna i przycisk ✕ znika, więc fokus trzeba jawnie przenieść na główny przycisk tej pigułki. Inaczej ląduje na `body`.
- **User experience spec — klawiatura listy.** Otwarcie okienka ustawia fokus na wybranej pozycji albo, gdy jest wyszukiwarka, na wyszukiwarce. Strzałki góra/dół (i Home/End) przechodzą między pozycjami z pominięciem tych z liczbą 0 (`aria-disabled`). Wyjątek: wybrana pozycja i „Wszystkie …” nigdy nie są wyłączone. Enter/Spacja wybiera. Strzałka w dół z wyszukiwarki wchodzi do listy. To nie `DropdownMenu` (przechwytuje klawiaturę i nie znosi pól tekstowych) — powód wyboru `Popover` w design systemie.
- **State sequencing — lata.** Pola w okienku Lata są sterowane stanem strony (`yearFrom`/`yearTo`), nie lokalnym. Zamknięcie okienka nie gubi wpisanego tekstu, debounce i „wizyta z innej kontrolki zatwierdza wpisane lata” (`pages/sources/index.tsx:154-155`) działają bez zmian. Szybki wybór półwiecza i „Wyczyść lata” ustawiają oba pola przez te same callbacki. Wizyta przychodzi z debouncem jak przy pisaniu. ✕ na pigułce Lata czyści oba pola tak samo jak „Wyczyść lata”.

## Faza 1: Logika paska (lib)

### Overview

Czyste funkcje, z których korzysta pasek, z testami Vitest. Bez zmian w widoku poza tym, że licznik „Filtry (N)” liczy już pigułki.

### Changes Required:

#### 1. Licznik aktywnych pigułek

**File**: `resources/js/lib/sources-index.ts`

**Intent**: „Filtry (N)” ma liczyć aktywne pigułki, więc zakres lat to jeden filtr niezależnie od tego, czy ustawiono jedną czy obie granice.

**Contract**: `hiddenFilterCount(filters: SourcesFilters): number` zachowuje nazwę i sygnaturę. Zwraca `[place !== '', year_from !== null || year_to !== null, archive !== ''].filter(Boolean).length` (0–3). Komentarz w JSDoc mówi o pigułkach.

#### 2. Liczby przy pozycjach okienek

**File**: `resources/js/lib/sources-index.ts`

**Intent**: Każda pozycja okienka Miejsce / Archiwum pokazuje, ile źródeł zostanie na liście po jej wybraniu, przy wszystkich pozostałych filtrach (szukanie, rodzaj, lata, drugi filtr).

**Contract**: `filterOptionCounts(sources: IndexSource[], filters: SourcesFilters, key: 'place' | 'archive', values: string[]): Record<string, number>`. Dla każdej wartości (w tym `''` = „Wszystkie …”) zwraca `filterSources(sources, { ...filters, [key]: value }).visible.length`. `filters` przychodzi rozwiązane, z `q` takim, jaki ma lista (wpisany, przycięty).

#### 3. Etykieta pigułki lat

**File**: `resources/js/lib/sources-index.ts`

**Intent**: Wartość pigułki Lata w formie okresu życia.

**Contract**: `formatYearsFilter(from: number | null, to: number | null): string` → „1880 – 1900” (spacje wokół półpauzy), „od 1880”, „do 1900”, `''` gdy obie puste. Nie zmieniamy `formatYearRange` (pobyty używają formy bez spacji).

#### 4. Półwiecza z lat źródeł

**File**: `resources/js/lib/sources-index.ts`

**Intent**: Przyciski szybkiego wyboru prowadzą tylko do półwieczy, w których jest co najmniej jedno źródło z rokiem.

**Contract**: `halfCenturyPresets(sources: IndexSource[]): { from: number; to: number }[]` zwraca rosnąco, bez powtórzeń, przedziały `[floor(year / 50) * 50, start + 49]` dla każdego `source.year !== null`. Pusta tablica, gdy żadne źródło nie ma roku. Liczy ze wszystkich źródeł drzewa (nie z przefiltrowanych), żeby przyciski nie znikały przy filtrowaniu.

#### 5. Tekst statusu wyników

**File**: `resources/js/lib/sources-index.ts`

**Intent**: Jedno miejsce na odmianę „N z M źródeł”.

**Contract**: `resultCountText(shown: number, total: number, filtering: boolean): string`:
- bez filtrów: `"${total} " + pluralPhrase(total, 'źródło', 'źródła', 'źródeł')`, np. „1 źródło”, „3 źródła”, „64 źródła”;
- przy filtrowaniu: `"${shown} z ${total} " + (total === 1 ? 'źródła' : 'źródeł')`, np. „12 z 64 źródeł”, „0 z 64 źródeł”, „1 z 1 źródła”, „1 z 2 źródeł” (dopełniacz po „z”).

#### 6. Testy

**File**: `resources/js/lib/sources-index.test.ts`

**Intent**: Pokryć nowe funkcje i nową semantykę licznika.

**Contract**:
- `hiddenFilterCount`: zakres 1850–1900 → 1; samo `year_from` → 1; wszystkie trzy → 3; domyślne → 0. Zastępuje dzisiejsze przypadki „2” i „4”.
- `filterOptionCounts`: liczba uwzględnia szukanie, rodzaj i lata; pozycja `''` = `visible.length` bez tego filtru; pozycja bez pasujących źródeł → 0.
- `formatYearsFilter`: trzy formy + pusta.
- `halfCenturyPresets`: rok 1850 → 1850–1899, 1849 → 1800–1849, duplikaty scalone, kolejność rosnąca, brak lat → `[]`.
- `resultCountText`: „1 źródło”, „3 źródła”, „5 źródeł”, „12 z 64 źródeł”, „1 z 1 źródła”, „0 z 64 źródeł”.

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`

#### Manual Verification:

- Na telefonie (375 px) zakres lat od–do daje „Filtry (1)”, a nie „Filtry (2)”.

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Popover i pasek pigułek (UI)

### Overview

Nowy `ui/popover.tsx`, przepisany `sources-filters.tsx` i przestawione rzędy w `pages/sources/index.tsx` z widocznym statusem zamiast ukrytego.

### Changes Required:

#### 1. Popover z shadcn

**File**: `resources/js/components/ui/popover.tsx` (nowy), `package.json`, `package-lock.json`

**Intent**: Okienko filtrów z polami tekstowymi. `DropdownMenu` przechwytuje klawiaturę. Fokus zgodny z resztą `components/ui`.

**Contract**:
- `vendor/bin/sail npx shadcn@latest add popover` dodaje `Popover`, `PopoverTrigger`, `PopoverContent`, `PopoverAnchor`.
- Jedyna nowa zależność to `@radix-ui/react-popover`. Jeśli CLI doda zbiorczy pakiet `radix-ui`, zamień import na `@radix-ui/react-popover` i usuń `radix-ui` z `package.json`, jak w pozostałych plikach `ui/*`.
- W pliku nie ma `ring-ring/50` ani `ring-2`. Elementy z fokusem, jeśli jakieś są, mają pełny `ring-ring` 3 px jak `button.tsx:8`.
- Oxfmt formatuje plik przez `npm run check:fix`.

#### 2. Pasek pigułek

**File**: `resources/js/components/sources/sources-filters.tsx`

**Intent**: Zamiast selectów z etykietami i pól roku w rzędzie — pasek `FilterBar` z trzema pigułkami, okienkami, „Wyczyść filtry” i statusem wyników. Na telefonie za „Filtry (N)” w `Collapsible`.

**Contract**:
- Eksport `SourcesFilterBar` (zastępuje `SourcesFilterFields`). Propsy:
  - dzisiejsze: `place`, `placeOptions`, `archive`, `archiveOptions`, `yearFrom`, `yearTo`, `yearError`, `activeCount`, `showClear` i callbacki `onPlaceChange`, `onArchiveChange`, `onYearFromChange`, `onYearToChange`, `onClear`;
  - nowe: `committedYears: { from: number | null; to: number | null }` (wartość pigułki Lata), `placeCounts` i `archiveCounts` (`Record<string, number>` z `filterOptionCounts`), `yearPresets` (z `halfCenturyPresets`), `resultText: string`.
- Kontener rzędu: `role="group"` z `aria-label="Filtry"`.
- **Pigułka** (`FilterPill`, lokalna dla pliku):
  - główny przycisk to `PopoverTrigger` z `aria-haspopup` (`listbox` dla Miejsca i Archiwum, `dialog` dla Lat) i `aria-expanded`;
  - nazwa dostępna = widoczny tekst: „Miejsce”, „Miejsce: Luboml”, „Lata: 1880 – 1900”;
  - ikony lucide: `Plus` dla nieaktywnej; `MapPin` / `CalendarDays` / `Landmark` dla aktywnej;
  - przycisk ✕ (`XIcon`) z `aria-label` „Usuń filtr: miejsce Luboml” / „Usuń filtr: lata 1880 – 1900” / „Usuń filtr: archiwum AP Lublin”;
  - klasy: `h-8 rounded-md text-sm font-medium`, ikona `size-3.5`. Nieaktywna: `border border-dashed border-input text-muted-foreground`. Aktywna: `bg-accent text-accent-foreground border border-primary/35`, wartość `font-semibold`. Otwarta: `data-[state=open]:border-primary`. Fokus: `focusRingOffset`;
  - poniżej `sm`: `h-11 w-full`, a ✕ ma szerokość `w-11`.
- **Pigułka bez danych** (Miejsce przy pustym `placeOptions`, Archiwum przy pustym `archiveOptions`):
  - przycisk `disabled`, `opacity-50`;
  - powód „Żadne źródło nie ma jeszcze miejsca” / „Żadne źródło nie ma jeszcze archiwum” jako `Tooltip` z `components/ui/tooltip.tsx` na owijającym elemencie i jako `sr-only` tekst podpięty przez `aria-describedby`.
  - Lata nigdy nie są wyłączone, jak dziś pola roku.
- **Okienko z listą** (Miejsce i Archiwum):
  - `PopoverContent` `align="start"`, `w-80`, `rounded-lg border shadow-md` (tło `popover` z shadcn), `aria-label` „Miejsce” / „Archiwum”;
  - wewnątrz `role="listbox"` z tą samą etykietą, pozycje `role="option"` z `aria-selected`;
  - wybrana pozycja: `CheckIcon` w `text-primary` i tło `bg-accent`;
  - liczba po prawej `text-xs tabular-nums text-muted-foreground`, `aria-hidden`, z `sr-only` „, 18 źródeł” w treści pozycji. Nazwa dostępna: „Luboml, 18 źródeł”;
  - pozycja z 0: `aria-disabled="true"`, wyszarzona, nie do wybrania (poza wybraną i „Wszystkie …”);
  - pierwsza pozycja „Wszystkie miejsca” / „Wszystkie archiwa” (wartość `''`);
  - grupy w Miejscu: `role="group"` z nagłówkiem `text-xs text-muted-foreground` („Miejscowości”, „Parafie”), wartości `miejscowosc-{id}` / `parafia-{id}` jak dziś;
  - wyszukiwarka (`Input`, `aria-label` „Szukaj miejsca” / „Szukaj archiwum”) tylko gdy pozycji (bez „Wszystkie …”) jest więcej niż 7. Filtruje po nazwie bez wielkości liter (`toLocaleLowerCase('pl')`);
  - wybór woła `onPlaceChange` / `onArchiveChange` i zamyka okienko. Klawiatura zgodnie z „Critical Implementation Details”.
- **Okienko Lata**:
  - `PopoverContent` z `aria-label` „Lata”;
  - dwa `Input` z etykietami „Rok od” / „Rok do” (`Label` + `htmlFor`), `inputMode="numeric"`, `type="text"`, wartości `yearFrom` / `yearTo`, `aria-invalid` i `aria-describedby` z `yearError` jak dziś;
  - `InputError` z `yearError?.message` pod polami;
  - grupa `aria-label="Szybki wybór"` z przyciskami `outline sm` „1850 – 1899” (ustawiają oba pola); grupy nie ma, gdy `yearPresets` jest puste;
  - stopka: `text-xs text-muted-foreground` „Lista odświeża się po wpisaniu roku” i „Wyczyść lata” (`ghost sm`, czyści oba pola).
- **„Wyczyść filtry”**: `Button variant="ghost" size="sm"`, ta sama reguła `showClear` i ten sam `onClear` co dziś.
- **Status**: `<p role="status" aria-live="polite">` z `resultText`, `ml-auto text-sm tabular-nums text-muted-foreground`.
- **Poniżej `sm`**:
  - rząd: `CollapsibleTrigger` „Filtry” / „Filtry (N)” (`outline`, `FilterIcon`, `h-11`) i status po prawej;
  - `CollapsibleContent`: `bg-muted rounded-lg p-3 flex flex-col gap-2` z pigułkami i „Wyczyść filtry”;
  - od `sm` treść jest zawsze widoczna w jednym rzędzie, a trigger ukryty (wzorzec `forceMount` + `max-sm:data-[state=closed]:hidden` jak dziś);
  - status renderowany raz, żeby nie było dwóch regionów `role="status"`.

#### 3. Rzędy strony i status

**File**: `resources/js/pages/sources/index.tsx`

**Intent**: Rząd 1 to wyszukiwanie, rodzaj i widok. Rząd 2 to pasek filtrów. Widoczna liczba wyników zastępuje ukryty status.

**Contract**:
- Kontener z `role="search"` zostaje jeden (obejmuje oba rzędy, `flex flex-col gap-3`).
- Rząd 1: `SearchField`, rodzaj, widok z `ml-auto`. Poniżej `sm` kolejność jak dziś: wyszukiwanie + widok, rodzaj na pełną szerokość.
- Rząd 2: `<SourcesFilterBar …>` z:
  - `placeCounts` / `archiveCounts` z `filterOptionCounts(sources, { ...filters, q: trimmedSearch }, …)`, z wartościami z `placeOptions` / `archiveOptions` i `''`;
  - `yearPresets` z `halfCenturyPresets(sources)`;
  - `committedYears` z `filters.year_from` / `filters.year_to`;
  - `resultText` z `resultCountText(committedCount, sources.length, isFiltering(filters))`.
- Usuń `<p className="sr-only" role="status">` i `statusText` (`:349-356`, `:511-513`).
- Reszta logiki (`visit`, debounce, `clearFilters`, `emptyStateClears`, puste stany, `hidden`) bez zmian.

#### 4. Kontrola tokenów i zrzuty

**File**: `resources/js/lib/view-tokens.test.ts` (bez zmian, tylko musi przejść); `context/changes/sources-filter-bar/screenshots/`

**Intent**: Bramka wizualna z CLAUDE.md.

**Contract**: Zrzuty `/sources` jasny i ciemny:
- desktop z aktywnymi Miejscem i Latami oraz otwartym okienkiem Miejsce;
- desktop z otwartym okienkiem Lata i błędem zakresu;
- telefon 390 px z rozwiniętym „Filtry (2)”;
- pigułka wyłączona (drzewo bez archiwów).

### Success Criteria:

#### Automated Verification:

- Testy Vitest (w tym `view-tokens.test.ts`) przechodzą: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- `package.json` ma jedną nową zależność `@radix-ui/react-popover` i nie ma `radix-ui`

#### Manual Verification:

- Wybór miejsca, archiwum i wpisanie lat filtrują listę i zmieniają adres tak samo jak przed zmianą (`place`, `year_from`, `year_to`, `archive`); przeładowanie odtwarza pigułki z wartościami
- Liczby przy pozycjach zgadzają się z liczbą kart po wybraniu pozycji; pozycje z 0 są wyszarzone i pomijane strzałkami
- Klawiatura: Tab do pigułki, Enter otwiera, strzałki/Enter wybierają, Escape zamyka i wraca na pigułkę; ✕ zostawia fokus na pigułce
- Błąd zakresu lat w okienku działa jak dziś (komunikat po 300 ms, `aria-invalid` na obu polach)
- Status pokazuje „N z M źródeł” przy filtrach i „M źródła/źródeł” bez nich; czytnik ogłasza go raz na pauzę w pisaniu
- Zrzuty jasny/ciemny/telefon zapisane w `context/changes/sources-filter-bar/screenshots/` i zgodne z makietą (`Sources.dc.html`, `FiltersStates.dc.html`)

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: E2E na role pigułek i okienek

### Overview

Testy E2E szukające pól po etykietach przechodzą na role przycisków pigułek, okienek i opcji. Pomocnik czeka na nowy status. Asercje biznesowe bez zmian.

### Changes Required:

#### 1. Pomocnik nawigacji

**File**: `tests/e2e/support/navigation.ts`

**Intent**: `openSourceFromList` czeka, aż lista zawęzi się do jednego źródła; status ma teraz formę „1 z M źródeł”.

**Contract**: Zamiast `getByText('Znaleziono: 1 źródło', { exact: true })` użyj `page.getByRole('status').filter({ hasText: /^1 z \d+ źród(ła|eł)$/ })` albo równoważnego lokatora roli z wzorcem. M zależy od innych testów działających równolegle. Zaktualizuj komentarz.

#### 2. Siatka z filtrami

**File**: `tests/e2e/sources-grid-with-filters.spec.ts`

**Intent**: Ten sam scenariusz (miejsce → lata → przeładowanie → „Wyczyść filtry”) przez pigułki i okienka.

**Contract**:
- Miejsce: `getByRole('button', { name: 'Miejsce', exact: true })` → `getByRole('option', { name: new RegExp('^' + localityName) })`.
- Lata: `getByRole('button', { name: 'Lata', exact: true })` → w `getByRole('dialog', { name: 'Lata' })` pola `getByLabel('Rok od' | 'Rok do')`.
- Status przez lokator roli z `/^1 z \d+ źród(ła|eł)$/`.
- Po przeładowaniu: pigułki `getByRole('button', { name: \`Miejsce: ${localityName}\` })` i `getByRole('button', { name: 'Lata: 1890 – 1895' })`.
- Po „Wyczyść filtry”: pigułki wracają do „Miejsce” i „Lata”, adres bez `place|year_from|year_to`.
- Asercje kart i adresu bez zmian. Zaktualizuj komentarz nagłówka (pigułki zamiast pól).

#### 3. Stany strony

**File**: `tests/e2e/sources-page-states.spec.ts`

**Intent**: Błąd zakresu i telefon przez okienko Lata.

**Contract**:
- Test błędu zakresu: najpierw otwórz pigułkę Lata, potem te same `getByLabel('Rok od' | 'Rok do')` w okienku; komunikat i `aria-invalid` bez zmian.
- Test telefonu:
  - zwinięty stan → pigułka Lata ukryta;
  - „Filtry” rozwija → pigułka widoczna → klik → wpisanie „1890” → `getByRole('button', { name: 'Filtry (1)', exact: true })`.
- Komentarze w krokach zaktualizowane.

### Success Criteria:

#### Automated Verification:

- Zmienione specy przechodzą: `npx playwright test tests/e2e/sources-grid-with-filters.spec.ts tests/e2e/sources-page-states.spec.ts tests/e2e/source-panel-edit.spec.ts`
- W `tests/e2e` nie zostało żadne `getByLabel('Parafia / miejsce')` ani `Znaleziono: 1 źródło`: `grep -rn "Parafia / miejsce\|Znaleziono: 1 źródło" tests/e2e` zwraca pusto
- Cały pakiet E2E przechodzi: `npx playwright test`

#### Manual Verification:

- Przegląd diffu speców: zmieniły się tylko lokatory, oczekiwany tekst statusu i komentarze; żadna asercja o kartach ani o adresie nie została osłabiona ani usunięta
- Weryfikacja przez celowe wprowadzenie błędu: chwilowe zepsucie `onPlaceChange` (np. brak wywołania) daje czerwony `sources-grid-with-filters`

---

## Testing Strategy

### Unit Tests:

- `hiddenFilterCount` z nową semantyką (zakres = 1).
- `filterOptionCounts`: wpływ szukania, rodzaju, lat i drugiego filtru; pozycja `''`; pozycja z 0.
- `formatYearsFilter`, `halfCenturyPresets` (granice 1849/1850, duplikaty, brak lat), `resultCountText` (odmiana, „z 1 źródła”, 0).
- `view-tokens.test.ts` pilnuje tokenów w `sources-filters.tsx` i `pages/sources/index.tsx`.

### Integration Tests:

- E2E `sources-grid-with-filters`: miejsce + lata zawężają siatkę, przeżywają przeładowanie, czyszczą się razem.
- E2E `sources-page-states`: błąd zakresu w okienku; telefon za „Filtry (N)”.
- E2E `source-panel-edit` przez `openSourceFromList` (nowy status).

### Manual Testing Steps:

1. Drzewo z kilkoma miejscowościami, parafiami i archiwami: otwórz Miejsce, sprawdź grupy, liczby i wyszukiwarkę (powyżej 7 pozycji).
2. Wybierz miejsce, potem otwórz Archiwum: liczby uwzględniają wybrane miejsce. Pozycja z 0 jest wyszarzona, strzałki ją pomijają.
3. Otwórz Lata, kliknij półwiecze: oba pola wypełnione, po pauzie lista i adres się zmieniają, pigułka pokazuje „1850 – 1899”.
4. Wpisz 1900 / 1850: komunikat po 300 ms, oba pola `aria-invalid`. Zamknij i otwórz okienko: tekst i błąd zostają.
5. ✕ na każdej pigułce zdejmuje tylko ten filtr, a fokus zostaje na pigułce.
6. Telefon 390 px: „Filtry (N)” z liczbą pigułek, rozwinięcie, pigułki na pełną szerokość z wysokością 44 px, okienka nie wychodzą poza ekran.
7. Drzewo bez archiwów: pigułka Archiwum wyłączona, podpowiedź i opis dla czytnika mówią dlaczego.

## Performance Considerations

`filterOptionCounts` wykonuje `filterSources` raz na pozycję (O(pozycje × źródła)). Przy skali z PRD („mała”, setki źródeł, dziesiątki miejsc) to ułamek milisekundy. Licz w komponencie okienka tylko wtedy, gdy jest otwarte (`PopoverContent` odmontowuje się po zamknięciu). React Compiler zadba o zapamiętanie.

## Migration Notes

Nie dotyczy. Brak zmian danych i adresów. Stare adresy z `place`, `year_*` i `archive` działają bez zmian.

## References

- Design system: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU — `project/components/FilterBar/README.md`, `project/implementation.md` (wiersze `components/ui/popover.tsx`, `components/sources/sources-filters.tsx` ×2, `pages/sources/index.tsx`)
- Makieta: https://claude.ai/artifact/Nvqqp3f43K3Kf22tNudBuS — `project/Sources.dc.html`, `project/FiltersStates.dc.html`
- Poprzednia zmiana filtrów: `context/archive/2026-10-09-sources-grid-with-filters/plan.md`
- Logika filtrów: `resources/js/lib/sources-index.ts:216-393`
- Obecny pasek: `resources/js/components/sources/sources-filters.tsx:30-217`, `resources/js/pages/sources/index.tsx:349-513`
- Wzorzec komponentu `ui`: `resources/js/components/ui/button.tsx:8`; fokus spoza `ui`: `resources/js/lib/focus-ring.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Logika paska (lib)

#### Automated

- [x] 1.1 Testy Vitest przechodzą: `vendor/bin/sail npm run test:js` — 0cd384a
- [x] 1.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — 0cd384a
- [x] 1.3 Format i lint przechodzą: `vendor/bin/sail npm run check` — 0cd384a

#### Manual

- [x] 1.4 Na telefonie (375 px) zakres lat od–do daje „Filtry (1)”, a nie „Filtry (2)” — 0cd384a

### Phase 2: Popover i pasek pigułek (UI)

#### Automated

- [x] 2.1 Testy Vitest (w tym `view-tokens.test.ts`) przechodzą: `vendor/bin/sail npm run test:js`
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [x] 2.3 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [x] 2.4 Build przechodzi: `vendor/bin/sail npm run build`
- [x] 2.5 `package.json` ma jedną nową zależność `@radix-ui/react-popover` i nie ma `radix-ui`

#### Manual

- [x] 2.6 Wybór miejsca, archiwum i wpisanie lat filtrują listę i zmieniają adres tak samo jak przed zmianą; przeładowanie odtwarza pigułki z wartościami
- [x] 2.7 Liczby przy pozycjach zgadzają się z liczbą kart po wybraniu pozycji; pozycje z 0 są wyszarzone i pomijane strzałkami
- [x] 2.8 Klawiatura: Tab do pigułki, Enter otwiera, strzałki/Enter wybierają, Escape zamyka i wraca na pigułkę; ✕ zostawia fokus na pigułce
- [x] 2.9 Błąd zakresu lat w okienku działa jak dziś (komunikat po 300 ms, `aria-invalid` na obu polach)
- [x] 2.10 Status pokazuje „N z M źródeł” przy filtrach i „M źródła/źródeł” bez nich; czytnik ogłasza go raz na pauzę w pisaniu
- [ ] 2.11 Zrzuty jasny/ciemny/telefon zapisane w `context/changes/sources-filter-bar/screenshots/` i zgodne z makietą

### Phase 3: E2E na role pigułek i okienek

#### Automated

- [ ] 3.1 Zmienione specy przechodzą: `npx playwright test tests/e2e/sources-grid-with-filters.spec.ts tests/e2e/sources-page-states.spec.ts tests/e2e/source-panel-edit.spec.ts`
- [ ] 3.2 W `tests/e2e` nie zostało żadne `getByLabel('Parafia / miejsce')` ani `Znaleziono: 1 źródło`
- [ ] 3.3 Cały pakiet E2E przechodzi: `npx playwright test`

#### Manual

- [ ] 3.4 Przegląd diffu speców: zmieniły się tylko lokatory, oczekiwany tekst statusu i komentarze; żadna asercja o kartach ani o adresie nie została osłabiona ani usunięta
- [ ] 3.5 Weryfikacja przez celowe wprowadzenie błędu: chwilowe zepsucie `onPlaceChange` daje czerwony `sources-grid-with-filters`
