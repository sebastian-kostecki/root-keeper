# Widok /sources zgodny z systemem projektowym — Implementation Plan

## Overview

Widok `/sources` dostaje to samo co `/persons` w `persons-index-ui`:
- pierścień fokusu ze wspólnej stałej i obsługę nieudanej wizyty,
- widoczny fokus na karcie otwartego źródła,
- jeden zapis stanu „otwarty rekord” (także w lepkiej kolumnie tabeli),
- filtry zwinięte pod przyciskiem „Filtry (N)” na telefonie,
- komunikat przy błędnym roku,
- poprawne stany pusty, wyłączony i ładowania.

Na koniec krok „Utrwal to”: skan tokenów obejmuje `/sources`, a do tego reguła w `CLAUDE.md`, spec E2E i bramka zrzutów. Plan zamyka zarzuty C1–C5 z `research.md`. D1–D9 zostają odroczone z powodem (D9 tylko oceniany na zrzucie).

## Current State Analysis

- **C1 — fokus.**
  - Ręczny pierścień `outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px]` występuje w `resources/js/components/sources/sources-gallery.tsx:45` (karta) i `resources/js/components/sources/sources-table.tsx:145` (link wiersza). Wspólna stała to `resources/js/lib/focus-ring.ts:7-8`.
  - Zaznaczona karta (`sources-gallery.tsx:46`: `data-[state=selected]:ring-2 data-[state=selected]:ring-primary`) nadpisuje pierścień fokusu, bo reguły `data-[state=selected]` stoją w zbudowanym CSS później. Fokus na karcie otwartego źródła jest przez to niewidoczny.
- **C2 — zaznaczenie.**
  - Każde miejsce zapisuje stan zaznaczenia inaczej:
    - karta galerii: `border-primary bg-accent ring-2 ring-primary`,
    - wiersz tabeli: `data-[state=selected]:bg-muted` (`resources/js/components/ui/table.tsx:58`) + podkreślenie nazwy (`sources-table.tsx:153-158`).
  - Lepka kolumna `stickyCell` (`resources/js/lib/table-styles.ts:7`, wspólna z `/persons`) ma `max-md:bg-background max-md:group-hover:bg-muted` i nie zna stanu `selected`. Poniżej `md` pierwsza komórka otwartego wiersza ma tło strony, a na hover jest ciemniejsza (`muted`) niż reszta wiersza (`muted/50`).
- **C3 — błąd.**
  - Pola „Rok od/Rok do” (`resources/js/components/sources/sources-filters.tsx:100-126`) nie mają `aria-invalid` ani komunikatu.
  - `parseYearInput` (`resources/js/lib/sources-index.ts:168-178`) zwraca `null` dla wartości spoza 1–9999, więc rok jest po cichu pomijany. Odwrócony zakres daje `SourcesFilteredNoResults` bez wyjaśnienia.
  - `visit()` (`resources/js/pages/sources/index.tsx:133-162`) nie ma `onHttpException`/`onNetworkError`. `/persons` ma `failureHandlers` + `InputError role="alert"` + „Spróbuj ponownie” (`resources/js/pages/persons/index.tsx:44-69`, `:285-296`).
- **C4 — telefon.**
  - Wiersz `role="search"` (`index.tsx:358-447`) to `flex flex-wrap gap-3` z polami ustawianymi przez `order-1…order-8`. Pola filtrów renderuje `SourcesFilterFields` (`order-4…order-7`, „Wyczyść filtry” `order-8`, `sources-filters.tsx:151-157`).
  - Na 375 px pasek ma 5–6 wierszy (wniosek z klas). Pojawienie się „Wyczyść filtry” dodaje lub usuwa cały wiersz.
- **C5 — stany.**
  - `SourcesEmptyKind` przy pustym `q` mówi „Nie masz jeszcze {aktów}.” (`resources/js/components/sources/sources-empty-state.tsx:91`), także gdy rodzaj jest pusty tylko przez filtr miejsca, roku lub archiwum.
  - `Label` nad wyłączonym selectem nie przygasa: `peer-disabled` wymaga poprzedzającego rodzeństwa, a `ui/label.tsx` ma też `group-data-[disabled=true]:opacity-50`, nieużywane tutaj.
  - `SearchField` nie dostaje `busy` (`index.tsx:363-370`).
  - `<img>` w `resources/js/components/sources/source-thumbnail.tsx:27-33` nie ma `onError`.
- **Filtrowanie jest po stronie klienta.**
  - `filterSources` używa `q` z pola na żywo, a pozostałych filtrów z propsa `filters`. Wizyty mają `only: ['filters']`.
  - Lista dla wpisanej frazy jest więc aktualna od razu. Przełącznik rodzaju, selecty i lata zmieniają wynik dopiero po odpowiedzi.
- **Testy i reguły.**
  - Wszystkie specy E2E działają w Desktop Chrome 1366×768 (`playwright.config.ts:32-34`). Etykiety „Parafia / miejsce”, „Rok od”, „Rok do”, „Archiwum” i „Wyczyść filtry” są kontraktem `tests/e2e/sources-grid-with-filters.spec.ts:70-94`.
  - Skan tokenów `resources/js/lib/persons-view-tokens.test.ts:15-23` obejmuje tylko `/persons`.
  - `CLAUDE.md:55-60` ma blok „UI: tokens and shared components”. Blok 10x-cli zajmuje linie 1–36.
  - `.ai/rules` i `context/foundation/lessons.md` nie istnieją.
  - `context/foundation/roadmap.md` nie ma pozycji `sources-page`.

## Desired End State

- Tab po galerii i tabeli pokazuje ten sam pierścień fokusu co reszta aplikacji, także na karcie otwartego źródła. Bez fokusu otwarta karta wygląda jak dziś (ramka, tło i pierścień `primary`).
- W tabeli poniżej 768 px lepka pierwsza komórka ma to samo tło co reszta wiersza w stanach default, hover i otwarty, i pozostaje nieprzezroczysta.
- Poniżej 640 px pola „Parafia / miejsce”, „Rok od”, „Rok do” i „Archiwum” są schowane pod przyciskiem „Filtry (N)”:
  - N to liczba tych czterech pól z zatwierdzoną wartością, więc Rok od 1850 + Rok do 1900 daje „Filtry (2)”; przy N = 0 przycisk mówi „Filtry”.
  - Blok przy wejściu na stronę jest zawsze zwinięty.
  - „Wyczyść filtry” stoi w tym samym wierszu co przycisk.
  - Od 640 px pasek wygląda jak dziś.
- Komunikat pod polami roku pojawia się po 300 ms przerwy w pisaniu, w tokenie `destructive`, a błędne pole dostaje `aria-invalid`:
  - Rok spoza 1–9999 (np. „12345”) daje „Rok musi być liczbą od 1 do 9999.”.
  - „Rok od” 1900 z „Rok do” 1850 daje „„Rok od” nie może być późniejszy niż „Rok do”.”.
  - Wartości nie są zamieniane, a pusty stan zostaje jak dziś.
- Nieudana wizyta filtra, widoku albo sortu zostawia użytkownika na stronie z komunikatem „Nie udało się zastosować filtrów.” i przyciskiem „Spróbuj ponownie”. Pole wyszukiwania pokazuje spinner, a wyniki mają `aria-busy`, dopóki trwa wizyta.
- Pusty rodzaj przy aktywnym filtrze miejsca, roku lub archiwum ma tekst „Wśród {aktów} nic nie pasuje do wybranych filtrów.” i obie akcje: „Pokaż wszystkie rodzaje” i „Wyczyść filtry”. Pasek nie pokazuje wtedy drugiego „Wyczyść filtry”.
- Etykieta nad wyłączonym selectem przygasa razem z nim.
- Miniatura, której plik się nie wczyta, pokazuje ikonę `ImageOff` jak źródło bez miniatury.
- Skan tokenów (`resources/js/lib/view-tokens.test.ts`) obejmuje pliki `/persons` i `/sources`. `CLAUDE.md` wskazuje stałą zaznaczenia i nowy plik skanu.

Weryfikacja:
- Vitest: `yearFieldsError`, `hiddenFilterCount`, skan tokenów.
- Spec E2E `tests/e2e/sources-page-states.spec.ts`.
- Zrzuty „przed/po” z `verdict.md`.
- Macierz 7 stanów (Faza 3).

### Key Discoveries:

- **Radix `CollapsibleContent` z `forceMount`** dostaje `present = true`, więc `isOpen` jest prawdą, `hidden` fałszem, a treść jest renderowana. `data-state` nadal przyjmuje `closed`/`open` (`node_modules/@radix-ui/react-collapsible/dist/index.mjs:84`, `:97`, `:129`). Dzięki temu jeden zestaw pól może być zwinięty tylko poniżej `sm` (`max-sm:data-[state=closed]:hidden`), a od `sm` zawsze widoczny. Pola nie są duplikowane, a etykiety (kontrakt E2E) mają jedno źródło.
- **`display: contents` na `Collapsible` i `CollapsibleContent` (od `sm`)** wpuszcza pola z powrotem do flexa `role="search"`, więc ich klasy `order-*` działają jak dziś. Pasek desktopowy się nie zmienia.
- **`ui/label.tsx` ma `group-data-[disabled=true]:opacity-50`**: wystarczy `group` + `data-disabled` na opakowaniu pola. Kolejność DOM zostaje bez zmian.
- **Tailwind v4 ma wariant `not-focus-visible:`.** Pierścień zaznaczenia ograniczony do `data-[state=selected]:not-focus-visible:` nie konkuruje z `focusRing`, niezależnie od kolejności reguł w CSS.
- **Wzorzec przerywania wizyt Inertia** w E2E: `page.route` z abortem żądań z nagłówkiem `x-inertia` (`tests/e2e/persons-add-person-panel.spec.ts:124-131`). Helpery `createSource`, `uniqueSuffix` i `cleanupBySuffix` są w `tests/e2e/support/api.ts:48`, `:284`, `:414`.
- **`TreeViewDemoSeeder`** daje źródła z miniaturą i bez niej (`database/seeders/TreeViewDemoSeeder.php:57-60`). Uszkodzoną miniaturę na zrzucie symuluje `page.route` z abortem `…/thumbnail`.

## What We're NOT Doing

- D1: ręczny pierścień w 10 plikach poza `/sources` (`parishes-table.tsx`, `localities-table.tsx`, `tree/card-styles.ts` itd.). Jeden widok na zmianę.
- Zapis zaznaczenia karty drzewa (`tree/card-styles.ts:26`) zostaje bez zmian. Wspólna stała obejmuje tylko elementy list.
- D2 (dwa „Dodaj źródło” przy pustym drzewie), D3 (404 dla cudzego `?source=`), D4 (`cursor: default`), D5 (`shadow-[1px_0_0_…]` w `table-styles.ts`), D6 (rozjazd parsowania roku serwer/klient), D7 (nieistniejący `place`/`archive` w URL), D8 (`style={{ width }}` w `source-panel.tsx`).
- D9 (przepełnienie przełącznika rodzajów przy 3-cyfrowych licznikach na 375 px): tylko ocena na zrzucie „przed”. Naprawa poza zakresem, chyba że zrzut pokaże ucinanie. Wtedy wpis w `verdict.md` jako deferred z wagą.
- Bez tooltipów i opisów przy wyłączonych kontrolkach (decyzja C5).
- Bez zamiany odwróconego zakresu lat i bez zmiany pustego stanu dla niego (decyzja C3, GF `plan.md:25`).
- Bez zmian w pasku na desktopie i w położeniu „Wyczyść filtry” od `sm` (S-10 zostaje).
- Bez sygnalizacji błędu roku na przycisku „Filtry (N)”, gdy blok jest zwinięty. Błąd pojawia się, gdy użytkownik pisze w rozwiniętym bloku.
- Bez przygaszania wyników w trakcie wizyty: lista dla frazy jest aktualna od razu, więc wystarczą `busy` i `aria-busy`.
- Bez zmian w backendzie, bez kitchen sinka i `toHaveScreenshot`. Bramka to zrzuty w folderze zmiany (jak `persons-index-ui`).
- Wnętrze panelu źródła (`source-panel.tsx`) poza zakresem.

## Implementation Approach

Kolejność według `/10x-ui`: wspólne stałe → jeden widok (układ) → stany → utrwalenie.
- Faza 1 robi zrzuty „przed”, które potwierdzają wnioski z klas (C2, C4, C5, D9), i wprowadza stałe.
- Faza 2 dotyka tylko układu paska.
- Faza 3 dodaje czyste funkcje w `lib/sources-index.ts` (testowane w Vitest) oraz stan błędu i ładowania na stronie. `failureHandlers` przechodzi z `pages/persons/index.tsx` do wspólnego `lib/`, żeby obie listy miały jedno źródło.
- Faza 4 zamyka zmianę testem przeglądarkowym, skanem, regułą i zrzutami „po”.

## Critical Implementation Details

- **Moment komunikatu roku.** Błąd liczony jest z tekstu pól, a nie z wizyty. Wpisanie „12345” daje ten sam `null` co puste pole, więc wizyta debounce'u nie odpala (`index.tsx:195-201`), a komunikat i tak musi się pojawić. Pokazywany błąd to osobny stan, ustawiany po 300 ms przerwy w pisaniu w pola roku, niezależnie od tego, czy wizyta odpala. Dzięki temu „od > do” nie mruga przy każdym znaku pola „Rok do”.
  - Stan ustawia jeden efekt na `[yearFrom, yearTo]`, a nie handlery `onChange`, bo tekst lat zmienia też „Wyczyść filtry” (`clearFilters`, `index.tsx:290-302`) i historia (`index.tsx:173-185`).
  - Gdy `yearFieldsError(yearFrom, yearTo)` daje `null`, komunikat znika od razu. Poprawienie, wyczyszczenie i powrót w historii nie zostawiają nieaktualnego błędu.
  - W przeciwnym razie błąd ustawia `setTimeout(300)`, a cleanup efektu go anuluje.
- **Lepka komórka na hover.** Komórka musi zostać nieprzezroczysta, bo przewijana treść przechodzi pod nią, więc nie może dostać `bg-muted/50` jak wiersz. To samo wizualne tło daje gradient o jednym kolorze nałożony na `bg-background`. Przy zaznaczeniu komórka dostaje nieprzezroczyste `bg-muted` jak wiersz, a gradient hover jest wtedy wyłączony (`bg-none`), bo zaznaczony wiersz na hover zostaje `bg-muted`.
- **Jeden przycisk „Wyczyść filtry”.** Przycisk zostaje jeden: `order-4 sm:order-8`. Na telefonie stoi obok wyzwalacza „Filtry (N)” (`order-4 sm:hidden`), przed zwijaną treścią (`order-5`). Dwa egzemplarze ukrywane breakpointem rozjechałyby się w stanie i w drzewie dostępności.

## Faza 1: Zrzuty „przed” i stałe fokusu oraz zaznaczenia

### Overview

Stan wyjściowy na zrzutach. Galeria i tabela używają `focusRing`, karta otwartego źródła ma widoczny fokus, lepka kolumna zna stan otwarty. Zamyka C1 i C2.

### Changes Required:

#### 1. Zrzuty „przed”

**File**: `context/changes/sources-page/screenshots/shots.mjs` (nowy), `context/changes/sources-page/screenshots/before/` (nowy), `context/changes/sources-page/screenshots/verdict.md` (nowy, sekcja „przed”)

**Intent**: Potwierdzić zrzutem wnioski z klas: wysokość paska na 375 px (C4), lepką komórkę zaznaczonego wiersza (C2), niewidoczny fokus na otwartej karcie (C1), uszkodzoną miniaturę (C5) i licznik rodzajów (D9).

**Contract**: wzorzec `context/archive/2026-10-11-persons-index-ui/screenshots/shots.mjs`: serwer E2E `scripts/e2e-server.sh` (port 8100), dane z `TreeViewDemoSeeder`, 1440×900 i 375×812, log `:focus-visible`. Ujęcia:
- `01-gallery-desktop`, `02-gallery-mobile`, `03-table-desktop`
- `04-table-mobile-selected` (otwarte źródło, przewinięta tabela)
- `05-focus-selected-card` (Tab na otwartą kartę)
- `06-filters-active-mobile` (aktywny filtr i „Wyczyść filtry”)
- `07-year-reversed-desktop` (1900–1850)
- `08-empty-kind-with-place` (rodzaj pusty przez filtr miejsca)
- `09-thumbnail-broken` (`page.route` abort miniatury)
- `10-kind-toggle-mobile` (liczniki rodzajów, D9)

`verdict.md` zapisuje dla każdego wniosku z klas: potwierdzony albo nie. Dla D9: ucina albo nie ucina.

#### 2. Pierścień fokusu ze stałej

**File**: `resources/js/components/sources/sources-gallery.tsx:45`, `resources/js/components/sources/sources-table.tsx:145`

**Intent**: Karta i link wiersza używają wspólnej stałej zamiast ręcznego ciągu, z tym samym wyglądem.

**Contract**: `cn(focusRing, …)`. Reszta klas karty i linku bez zmian (wzorzec `persons-table.tsx:142-145`). `grep "ring-\[3px\]"` na obu plikach zwraca pusto.

#### 3. Stała zaznaczenia elementu listy

**File**: `resources/js/lib/list-item-styles.ts` (nowy), `resources/js/components/sources/sources-gallery.tsx:46`

**Intent**: Jedno źródło dla wyglądu otwartego rekordu na karcie listy. Fokus klawiatury wygrywa z pierścieniem zaznaczenia (decyzja „Fokus wygrywa”). Bez fokusu wygląd zostaje jak dziś.

**Contract**: `export const selectedListItem: string` = `data-[state=selected]:border-primary data-[state=selected]:bg-accent data-[state=selected]:not-focus-visible:ring-2 data-[state=selected]:not-focus-visible:ring-primary`. JSDoc mówi, że element ustawia `data-state="selected"` i `aria-current`, a pierścień fokusu pochodzi z `focusRing`. Karta galerii używa `cn(focusRing, <klasy karty>, selectedListItem)`. Na otwartej karcie z fokusem widać pierścień `ring/50` 3 px oraz ramkę i tło `primary`/`accent`.

#### 4. Lepka kolumna zna zaznaczenie

**File**: `resources/js/lib/table-styles.ts:7`

**Intent**: Pierwsza komórka otwartego wiersza ma to samo tło co reszta wiersza poniżej `md`, a na hover nie jest ciemniejsza od wiersza (C2). Dotyczy `/sources` i `/persons` (`persons-table.tsx:139` nie ustawia `data-state`, więc tam zmienia się tylko hover).

**Contract**: `stickyCell` zostaje nieprzezroczysty (`max-md:bg-background`):
- Hover: kolor `muted/50` nałożony gradientem jednokolorowym zamiast `max-md:group-hover:bg-muted`.
- Zaznaczenie: `max-md:group-data-[state=selected]:bg-muted` + `max-md:group-data-[state=selected]:bg-none`.

Bez nowego tokenu i bez wartości arbitralnych w pikselach.

```ts
// hover: muted/50 over the opaque background, the same colour as the row's hover:bg-muted/50
'max-md:group-hover:bg-linear-to-r max-md:group-hover:from-muted/50 max-md:group-hover:to-muted/50 max-md:group-data-[state=selected]:bg-muted max-md:group-data-[state=selected]:bg-none'
```

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą: `vendor/bin/sail npm run test:js`
- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Brak ręcznego pierścienia w plikach `/sources`: `grep -rn "ring-\[3px\]" resources/js/pages/sources resources/js/components/sources` zwraca pusto
- Specy list przechodzą: `npx playwright test tests/e2e/sources-grid-with-filters.spec.ts tests/e2e/seed.spec.ts`

#### Manual Verification:

- Zrzuty `before/01–10` zapisane. W `verdict.md` (sekcja „przed”) odnotowane: C1, C2, C4, uszkodzona miniatura, D9
- Tab na kartę otwartego źródła w galerii pokazuje pierścień fokusu, a bez fokusu karta wygląda jak przed zmianą (jasny i ciemny motyw)
- Tabela przy 375 px, otwarte źródło, przewinięcie w prawo: lepka komórka ma to samo tło co wiersz. Hover na innym wierszu: lepka komórka ma ten sam odcień co reszta wiersza, a treść nie prześwituje
- `/persons` w tabeli przy 375 px: hover i lepka kolumna bez regresji

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 2: Filtry zwinięte na telefonie

### Overview

Poniżej `sm` cztery pola filtrów chowają się pod przyciskiem „Filtry (N)”, a „Wyczyść filtry” stoi z nim w jednym wierszu. Od `sm` pasek się nie zmienia. Zamyka C4.

### Changes Required:

#### 1. Licznik schowanych filtrów

**File**: `resources/js/lib/sources-index.ts`, `resources/js/lib/sources-index.test.ts`

**Intent**: Jedna funkcja liczy N dla „Filtry (N)” z zatwierdzonych filtrów, czyli tego, co faktycznie filtruje listę, a nie z tekstu wpisywanego w pola.

**Contract**: `hiddenFilterCount(filters: SourcesFilters): number` = liczba niepustych spośród `place`, `year_from`, `year_to`, `archive` (0–4). `q`, `kind`, `view` i `sort` się nie liczą. Vitest:
- `year_from = 1850`, `year_to = 1900` → 2,
- same domyślne → 0,
- wszystkie cztery → 4,
- `kind = 'akty'` → 0.

#### 2. Zwijany blok pól

**File**: `resources/js/components/sources/sources-filters.tsx`, `resources/js/pages/sources/index.tsx:430-445`

**Intent**: Pola są renderowane raz, w `Collapsible` z `ui/collapsible.tsx`.
- Poniżej `sm` pola są widoczne po rozwinięciu przyciskiem.
- Od `sm` `Collapsible` i jego treść znikają z układu (`display: contents`), a pola stoją w pasku jak dziś.
- Blok przy wejściu jest zwinięty (`useState(false)`), także przy aktywnych filtrach. `preserveState` utrzymuje stan rozwinięcia między wizytami.

**Contract**:
- `SourcesFilterFields` dostaje `activeCount: number` (z `hiddenFilterCount(filters)`).
- Struktura w pasku:
  - `Collapsible` z `className="contents"`.
  - `CollapsibleTrigger asChild` → `Button variant="outline"` z ikoną filtra, tekst „Filtry” albo „Filtry ({N})” przy N > 0, `order-4 sm:hidden`.
  - „Wyczyść filtry” jako jeden przycisk `order-4 sm:order-8`, warunek `showClear` jak dziś.
  - `CollapsibleContent forceMount` z `className` = `order-5 flex w-full flex-wrap gap-3 max-sm:data-[state=closed]:hidden sm:contents` (selecty `w-full`, lata `w-28` obok siebie jak dziś).
- Pola wewnątrz zachowują swoje `order-4…order-7` dla `sm` oraz etykiety i `id`.
- `aria-expanded` i `aria-controls` daje Radix.
- `grep -c "<Label" sources-filters.tsx` = 4 (brak duplikatów pól).

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą (`hiddenFilterCount`): `vendor/bin/sail npm run test:js`
- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Specy filtrów (desktop) przechodzą bez zmian w lokatorach: `npx playwright test tests/e2e/sources-grid-with-filters.spec.ts tests/e2e/source-found-by-place-and-year.spec.ts`

#### Manual Verification:

- 375 px bez filtrów: pasek ma wyszukiwarkę, przełączniki i przycisk „Filtry”. Pierwsza karta galerii jest widoczna na pierwszym ekranie (812 px)
- 375 px: „Filtry” → rozwijają się cztery pola z etykietami. Ustawienie Rok od 1850 i Rok do 1900 → przycisk „Filtry (2)”, „Wyczyść filtry” w tym samym wierszu. Zwinięcie → pola znikają, licznik zostaje
- 375 px: wejście z adresem z `place=…` → blok zwinięty, „Filtry (1)”
- 1440 px i 640 px: pasek wygląda jak na zrzucie `before/01`, przycisku „Filtry” nie ma, „Wyczyść filtry” na końcu paska
- Klawiatura na 375 px: Tab dochodzi do „Filtry”, Enter rozwija, kolejny Tab trafia w „Parafia / miejsce”

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 3: Stany — błąd, ładowanie, pusty, wyłączony

### Overview

Komunikat przy polach roku, obsługa nieudanej wizyty z ponowieniem, wskaźnik oczekiwania, poprawny tekst pustego rodzaju, przygaszona etykieta wyłączonego selecta, fallback miniatury. Macierz 7 stanów jako kryteria. Zamyka C3 i C5.

### Changes Required:

#### 1. Walidacja pól roku

**File**: `resources/js/lib/sources-index.ts`, `resources/js/lib/sources-index.test.ts`

**Intent**: Czysta funkcja mówi, które pole roku jest błędne i dlaczego, według tych samych reguł co `parseYearInput`.

**Contract**: `yearFieldsError(fromText: string, toText: string): { from: boolean; to: boolean; message: string } | null`.
- Puste pole nie jest błędem.
- Niepusty tekst, dla którego `parseYearInput` daje `null`, to błąd tego pola: „Rok musi być liczbą od 1 do 9999.”. Ten błąd ma pierwszeństwo.
- Oba poprawne i od > do: błąd obu pól, „„Rok od” nie może być późniejszy niż „Rok do”.”.
- Równe lata nie są błędem.

Vitest: `('12345','')`, `('0','')`, `('','-5')`, `('1900','1850')`, `('1850','1850')`, `('1850','1900')`, `('','')`, `('12345','1850')` (pierwszeństwo zakresu).

#### 2. Komunikat przy polach roku

**File**: `resources/js/components/sources/sources-filters.tsx:100-126`, `resources/js/pages/sources/index.tsx`

**Intent**: Genealog wpisujący „1900–1850” albo „18500” widzi przy polach, co jest nie tak. Wartości zostają, filtr działa jak dziś (decyzja C3).

**Contract**:
- Strona trzyma pokazywany błąd w stanie ustawianym po 300 ms przerwy w pisaniu w pola roku (patrz Critical Implementation Details) i przekazuje go do `SourcesFilterFields` jako `yearError`.
- „Rok od” i „Rok do” zostają osobnymi polami (`order-5`, `order-6`), bez wspólnego opakowania: wiersz `role="search"` ma `items-center`, więc rosnące opakowanie przesuwałoby resztę paska w pionie.
- `InputError` z `id` to osobny element `basis-full order-6 sm:order-9`, w DOM zaraz po „Rok do”. Od `sm` jest nowym wierszem pod paskiem (po „Wyczyść filtry” `order-8`). Na telefonie stoi w rozwiniętym bloku pod polami roku, przed „Archiwum” (`order-7`).
- Błędne pole ma `aria-invalid` (styl błędu daje `ui/input.tsx`) i `aria-describedby` na komunikat.
- Etykiety „Rok od” i „Rok do” bez zmian.

#### 3. Wspólna obsługa nieudanej wizyty

**File**: `resources/js/lib/visit-failure.ts` (nowy), `resources/js/pages/persons/index.tsx:44-69`, `resources/js/pages/sources/index.tsx`

**Intent**: Nieudana wizyta (4xx/5xx, sieć) na obu listach zostawia użytkownika na stronie z komunikatem i ponowieniem. Logika pochodzi z jednego miejsca, a nie z kopii.

**Contract**:
- `VisitError` i `failureHandlers(message, retry, setVisitError, isCurrent?)` przeniesione 1:1 z `pages/persons/index.tsx` (eksport). `/persons` importuje je bez zmiany zachowania.
- Na `/sources` `visit()` dostaje `failureHandlers('Nie udało się zastosować filtrów.', () => visit(sameArgs, replace), …, isCurrent)`, `onStart` czyści błąd, a `isCurrent` porównuje numer wizyty (tylko najnowsza raportuje).
- Komunikat i „Spróbuj ponownie” stoją pod paskiem wyszukiwania w tym samym układzie co `/persons` (`InputError role="alert"` + `Button variant="outline" size="sm"`).

#### 4. Wskaźnik oczekiwania

**File**: `resources/js/pages/sources/index.tsx`

**Intent**: Użytkownik widzi, że kliknięcie filtra „coś robi”, zanim przełącznik zmieni stan po odpowiedzi.

**Contract**: `isVisiting` ustawiane w `onStart` i czyszczone w `onFinish` najnowszej wizyty (ten sam numer wizyty co błąd). `SearchField busy={isVisiting} busyLabel="Wczytywanie"`. Kontener wyników (galeria, tabela, puste stany wyników) ma `aria-busy={isVisiting}`, bez przygaszania. `SourcesEmptyTree` jest poza nim.

#### 5. Tekst pustego rodzaju

**File**: `resources/js/components/sources/sources-empty-state.tsx:73-101`, `resources/js/pages/sources/index.tsx:313-329`, `:462-467`

**Intent**: Przy pustym rodzaju z aktywnym filtrem miejsca, roku lub archiwum użytkownik nie czyta, że „nie ma jeszcze aktów”, i może jednym kliknięciem wyczyścić filtry.

**Contract**: `SourcesEmptyKind` dostaje `hasOtherFilters: boolean` i `onClearFilters`. Opis:
- bez innych filtrów: jak dziś,
- z innymi filtrami i pustym `q`: „Wśród {aktów} nic nie pasuje do wybranych filtrów.”,
- z innymi filtrami i `q`: „Wśród {aktów} nic nie pasuje do „{q}” i wybranych filtrów.”.

Akcje: „Pokaż wszystkie rodzaje”, a przy `hasOtherFilters` także „Wyczyść filtry” (`Button variant="outline"`).

`hasOtherFilters` = `hiddenFilterCount(filters) > 0` (ten sam predykat co licznik z Fazy 2, bez drugiej definicji). `emptyStateClears` obejmuje też przypadek pustego rodzaju z `hasOtherFilters`, więc pasek nie pokazuje drugiego „Wyczyść filtry” (GF F3).

#### 6. Etykieta wyłączonego selecta

**File**: `resources/js/components/sources/sources-filters.tsx:56`, `:128`

**Intent**: Wyłączony select „Parafia / miejsce” albo „Archiwum” (brak opcji) przygasa razem ze swoją etykietą (decyzja C5: tylko poprawka, bez opisu).

**Contract**: opakowanie pola dostaje `group` i `data-disabled={String(disabled)}`. Działa reguła `group-data-[disabled=true]:opacity-50` z `ui/label.tsx`. Bez zmian w `ui/`.

#### 7. Fallback uszkodzonej miniatury

**File**: `resources/js/components/sources/source-thumbnail.tsx:27-33`

**Intent**: Gdy plik miniatury się nie wczyta (404, sieć), użytkownik widzi tę samą ikonę `ImageOff` co przy źródle bez miniatury, a nie pustą ramkę.

**Contract**: stan `failed` ustawiany w `onError` `<img>`. Przy `failed` renderuje się gałąź `ImageOff`. Klasy i rozmiary bez zmian (miejsce zarezerwowane, bez skoku układu).

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą (`yearFieldsError`): `vendor/bin/sail npm run test:js`
- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Specy list przechodzą (także błąd wyszukiwania `/persons` po przeniesieniu `failureHandlers`): `npx playwright test tests/e2e/sources-grid-with-filters.spec.ts tests/e2e/source-found-by-place-and-year.spec.ts tests/e2e/persons-add-person-panel.spec.ts`

#### Manual Verification:

Macierz 7 stanów (1440 px i 375 px, jasny motyw; ciemny dla `error` i `disabled`):

- Pola roku: default / hover / focus-visible (pierścień `Input`) / error („12345” i „1900–1850”: komunikat po pauzie, oba przypadki, wartości niezamienione, poprawienie usuwa komunikat) / empty (puste pola, brak błędu) / disabled: N/A / loading: spinner w wyszukiwarce w trakcie wizyty
- Selecty „Parafia / miejsce” i „Archiwum”: default / hover / focus-visible / disabled (nowe konto bez miejsc i archiwów: select i etykieta przygaszone) / error: N/A / loading: spinner w wyszukiwarce
- Przełącznik rodzaju: default / hover / focus-visible / disabled (segment z licznikiem 0) / error (odcięta sieć → „Nie udało się zastosować filtrów.”, „Spróbuj ponownie” po przywróceniu zmienia rodzaj) / loading (spinner w wyszukiwarce do odpowiedzi)
- Przełącznik Galeria/Tabela i nagłówki sortowania: default / hover / focus-visible / error (jak przełącznik rodzaju) / loading (spinner)
- Karta galerii i wiersz tabeli: default / hover / focus-visible (także otwarta karta) / loading (`aria-busy` na wynikach) / empty (`SourcesNoResults`, `SourcesFilteredNoResults`, `SourcesEmptyKind` z filtrem miejsca: nowy tekst i „Wyczyść filtry” tylko w pustym stanie)
- Miniatura: z plikiem / bez pliku / uszkodzona (abort w DevTools → `ImageOff`, bez skoku układu)
- „Filtry (N)” (375 px): default / hover / focus-visible / N = 0, 1, 2

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 4: Utrwal to i bramka wizualna

### Overview

Test przeglądarkowy nowych zachowań, skan tokenów obejmujący `/sources`, reguła agenta, zrzuty „po”. E2E prowadzone przez `/10x-e2e` (ryzyko → eksploracja `playwright-cli` → generowanie → przegląd antywzorców → weryfikacja przez wprowadzony błąd).

### Changes Required:

#### 1. Spec E2E stanów `/sources`

**File**: `tests/e2e/sources-page-states.spec.ts` (nowy)

**Intent**: Ryzyka:
- (a) odwrócony lub zły rok daje pustą albo nieprzefiltrowaną listę bez wyjaśnienia;
- (b) nieudana wizyta filtra wygląda jak „nic się nie stało”;
- (c) na telefonie filtry są nieosiągalne albo licznik kłamie.

Scenariusze:
1. Desktop: dwa źródła z latami z sufiksem → „Rok od” 1900, „Rok do” 1850 → widoczny komunikat o kolejności, oba pola `aria-invalid="true"`, wartości 1900/1850 zostają → poprawienie „Rok do” na 1950 → komunikat znika.
2. Desktop: akt z sufiksem (`createSource`; segment „Akty” jest włączony tylko przy liczniku > 0) → `page.route` przerywa żądania z nagłówkiem `x-inertia` → klik „Akty” → alert „Nie udało się zastosować filtrów.” → odblokowanie → „Spróbuj ponownie” → `waitForURL` z `kind=akty`.
3. `test.use({ viewport: { width: 375, height: 812 } })`: źródło z sufiksem (`createSource`; pasek renderuje się tylko przy `sources.length > 0`) → „Rok od” niewidoczne → „Filtry” → „Rok od” widoczne → wpisanie roku → przycisk „Filtry (1)”.

**Contract**:
- Lokatory `getByRole`/`getByLabel`, bez `waitForTimeout`.
- Dane przez `createSource` z `uniqueSuffix`, sprzątanie `cleanupBySuffix` (`tests/e2e/support/api.ts:48`, `:284`, `:414`).
- Wzorzec abortu `tests/e2e/persons-add-person-panel.spec.ts:124-131`.
- Weryfikacja przez błąd: `yearFieldsError` zwracający `null` czerwieni scenariusz 1, a usunięcie `max-sm:data-[state=closed]:hidden` czerwieni scenariusz 3.

#### 2. Skan tokenów obejmuje `/sources`

**File**: `resources/js/lib/persons-view-tokens.test.ts` → `resources/js/lib/view-tokens.test.ts` (`git mv`)

**Intent**: Regresja literału w widoku `/sources` jest czerwona w `test:js` tak samo jak w `/persons`.

**Contract**:
- `VIEW_FILES` += `pages/sources/index.tsx` oraz `components/sources/sources-{filters,gallery,table,empty-state}.tsx` i `components/sources/source-thumbnail.tsx`.
- Komentarz nagłówkowy i nazwa `describe` mówią o widokach list `/persons` i `/sources`.
- Reguły i wyjątki bez zmian (`aspect-[3/2]` nie pasuje do wzorca pikseli, więc nie potrzebuje wyjątku).

#### 3. Reguła agenta

**File**: `CLAUDE.md` (sekcja „UI: tokens and shared components”, poza blokiem `<!-- BEGIN/END @przeprogramowani/10x-cli -->` w liniach 1–36)

**Intent**: Następny agent wie, że stan otwartego rekordu na karcie listy pochodzi ze stałej, a skan obejmuje `/sources`.

**Contract**:
- Linia o `focusRing` rozszerzona: stan otwartego rekordu na kartach list = `selectedListItem` z `resources/js/lib/list-item-styles.ts` (fokus wygrywa z pierścieniem zaznaczenia).
- Lepka kolumna tabel = `lib/table-styles.ts`.
- Linia „Controls” wskazuje `resources/js/lib/view-tokens.test.ts`, który skanuje widoki `/persons` i `/sources`.
- Krótko, po angielsku jak reszta sekcji.

#### 4. Zrzuty „po” i werdykt

**File**: `context/changes/sources-page/screenshots/after/`, `context/changes/sources-page/screenshots/verdict.md`

**Intent**: Te same ujęcia co `before/01–10` + `11-filters-expanded-mobile` i `12-visit-error-desktop`. Werdykt porównuje każdą parę i zapisuje deferred z powodem (D1–D9).

**Contract**: nazwy plików jak w `before/`; `verdict.md` z sekcjami „przed”, „po”, „deferred”.

### Success Criteria:

#### Automated Verification:

- Spec przechodzi: `npx playwright test tests/e2e/sources-page-states.spec.ts`
- Scenariusz 1 czerwienieje, gdy `yearFieldsError` zwraca `null`, a scenariusz 3 po usunięciu `max-sm:data-[state=closed]:hidden`; oba zielenieją po przywróceniu
- Skan przechodzi i czerwienieje po wstawieniu `text-red-600` do `sources-gallery.tsx` (cofnięte): `vendor/bin/sail npm run test:js`
- Pełny zestaw E2E przechodzi: `npx playwright test`
- `CLAUDE.md` wskazuje nowe pliki, blok 10x-cli nietknięty: `grep -n "list-item-styles.ts\|view-tokens.test.ts" CLAUDE.md` i `git diff CLAUDE.md` bez zmian w liniach 1–36; `grep -rn "persons-view-tokens" CLAUDE.md resources` zwraca pusto
- Lint i format przechodzą: `vendor/bin/sail npm run check`

#### Manual Verification:

- Zrzuty `after/01–12` porównane z `before/` w `verdict.md`; każda różnica zamierzona
- Pełny zestaw Pest uruchomiony przez użytkownika: `vendor/bin/sail artisan test --compact`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `sources-index.test.ts`:
  - `hiddenFilterCount`: lata liczone osobno, `kind`/`q` nie liczą się;
  - `yearFieldsError`: poza zakresem, nie-cyfry, odwrócony zakres, równe lata, pierwszeństwo błędu zakresu, puste pola.
- `view-tokens.test.ts`: skan literałów w plikach `/persons` i `/sources`.

### Integration Tests:

- E2E `sources-page-states.spec.ts`: błąd zakresu roku, nieudana wizyta i ponowienie, „Filtry (N)” na 375 px.
- Istniejące `sources-grid-with-filters.spec.ts` i `source-found-by-place-and-year.spec.ts` pilnują kontraktu etykiet na desktopie. `persons-add-person-panel.spec.ts` pilnuje przeniesionego `failureHandlers`.

### Manual Testing Steps:

1. Faza 1: fokus na otwartej karcie, lepka kolumna przy 375 px (`/sources` i `/persons`).
2. Faza 2: 375 px — pierwszy ekran, rozwinięcie, licznik, „Wyczyść filtry” w wierszu; 640 px i 1440 px bez zmian.
3. Faza 3: macierz 7 stanów, w tym błąd przy odciętej sieci i uszkodzona miniatura.

## References

- Research: `context/changes/sources-page/research.md` (Charges C1–C5, Deferred D1–D9, Decisions, macierz 7 stanów)
- Wzorzec zmiany: `context/archive/2026-10-11-persons-index-ui/plan.md`, `screenshots/shots.mjs`, `screenshots/verdict.md`
- Obsługa błędu i `busy`: `resources/js/pages/persons/index.tsx:44-69`, `:258`, `:285-296`
- Kontrakt filtrów: `context/archive/2026-10-09-sources-grid-with-filters/plan.md:24-25`, `:196`, `:204`; `reviews/impl-review.md:111-119` (F3)
- Radix Collapsible: `resources/js/components/ui/collapsible.tsx`, `node_modules/@radix-ui/react-collapsible/dist/index.mjs:82-137`
- Inertia v3 per-visit callbacks: https://inertiajs.com/docs/v3/the-basics/manual-visits

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Zrzuty „przed” i stałe fokusu oraz zaznaczenia

#### Automated

- [x] 1.1 Testy JS przechodzą: `vendor/bin/sail npm run test:js` — 410ae5f
- [x] 1.2 Typy, lint i build przechodzą — 410ae5f
- [x] 1.3 Brak ręcznego pierścienia w plikach `/sources` — 410ae5f
- [x] 1.4 Specy list przechodzą (`sources-grid-with-filters`, `seed`) — 410ae5f

#### Manual

- [x] 1.5 Zrzuty `before/01–10` zapisane; C1, C2, C4, miniatura i D9 odnotowane w `verdict.md` — 410ae5f
- [x] 1.6 Fokus widoczny na otwartej karcie; bez fokusu wygląd jak przed zmianą (oba motywy) — 410ae5f
- [x] 1.7 Lepka komórka przy 375 px: to samo tło co wiersz (otwarty, hover), nieprzezroczysta — 410ae5f
- [x] 1.8 `/persons` w tabeli przy 375 px bez regresji — 410ae5f

### Phase 2: Filtry zwinięte na telefonie

#### Automated

- [x] 2.1 Testy JS przechodzą (`hiddenFilterCount`) — 48d99ce
- [x] 2.2 Typy, lint i build przechodzą — 48d99ce
- [x] 2.3 Specy filtrów (desktop) przechodzą bez zmian w lokatorach — 48d99ce

#### Manual

- [x] 2.4 375 px bez filtrów: pierwsza karta na pierwszym ekranie — 48d99ce
- [x] 2.5 375 px: rozwinięcie, „Filtry (2)” dla zakresu lat, „Wyczyść filtry” w tym samym wierszu, zwinięcie zachowuje licznik — 48d99ce
- [x] 2.6 375 px: wejście z `place=…` → blok zwinięty, „Filtry (1)” — 48d99ce
- [x] 2.7 1440 px i 640 px: pasek jak `before/01`, bez przycisku „Filtry” — 48d99ce
- [x] 2.8 Klawiatura na 375 px: Tab → „Filtry” → Enter → Tab trafia w „Parafia / miejsce” — 48d99ce

### Phase 3: Stany — błąd, ładowanie, pusty, wyłączony

#### Automated

- [x] 3.1 Testy JS przechodzą (`yearFieldsError`)
- [x] 3.2 Typy, lint i build przechodzą
- [x] 3.3 Specy list przechodzą (`sources-grid-with-filters`, `source-found-by-place-and-year`, `persons-add-person-panel`)

#### Manual

- [x] 3.4 Pola roku: 7 stanów, w tym oba komunikaty błędu, wartości niezamienione
- [x] 3.5 Selecty: stany, wyłączony select z przygaszoną etykietą
- [x] 3.6 Przełącznik rodzaju: stany, komunikat błędu i ponowienie po odcięciu sieci
- [x] 3.7 Przełącznik Galeria/Tabela i sortowanie: stany i błąd
- [x] 3.8 Karta i wiersz: stany, `aria-busy`, puste stany z nowym tekstem `SourcesEmptyKind` i jednym „Wyczyść filtry”
- [x] 3.9 Miniatura: z plikiem / bez pliku / uszkodzona → `ImageOff` bez skoku
- [x] 3.10 „Filtry (N)” na 375 px: default / hover / focus-visible / N = 0, 1, 2

### Phase 4: Utrwal to i bramka wizualna

#### Automated

- [ ] 4.1 Spec `sources-page-states.spec.ts` przechodzi
- [ ] 4.2 Scenariusze 1 i 3 czerwienieją po wprowadzonym błędzie i zielenieją po przywróceniu
- [ ] 4.3 Skan przechodzi i czerwienieje po wstawieniu klasy palety do `sources-gallery.tsx`
- [ ] 4.4 Pełny zestaw E2E przechodzi: `npx playwright test`
- [ ] 4.5 `CLAUDE.md` wskazuje `list-item-styles.ts` i `view-tokens.test.ts`, blok 10x-cli nietknięty, brak odwołań do `persons-view-tokens`
- [ ] 4.6 Lint i format przechodzą: `vendor/bin/sail npm run check`

#### Manual

- [ ] 4.7 Zrzuty `after/01–12` porównane z `before/` w `verdict.md`
- [ ] 4.8 Pełny zestaw Pest uruchomiony przez użytkownika: `vendor/bin/sail artisan test --compact`
