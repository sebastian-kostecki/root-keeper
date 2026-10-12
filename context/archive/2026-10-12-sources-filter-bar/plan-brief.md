# Pasek filtrów z pigułkami i okienkami na /sources — Plan Brief

> Full plan: `context/changes/sources-filter-bar/plan.md`

## What & Why

Wdrażamy wzorzec `FilterBar` z design systemu Root Keeper na `/sources`. Pod rzędem wyszukiwania pojawia się rząd pigułek Miejsce / Lata / Archiwum, z których każda otwiera okienko wyboru. Aktywne filtry są widoczne z wartością i zdejmuje się je jednym kliknięciem. Jeden rząd o jednej wysokości zastępuje etykiety nad polami. Filtry działają jak dziś, zmienia się tylko sposób wyboru.

## Starting Point

Dziś `sources-filters.tsx` rysuje dwa selecty z etykietami („Parafia / miejsce”, „Archiwum”) i dwa pola liczbowe („Rok od”, „Rok do”) w tym samym rzędzie co wyszukiwanie, rodzaj i widok. Liczba wyników jest tylko w ukrytym statusie „Znaleziono: N źródło”. Logika (`filterSources`, `resolveFilters`, `yearFieldsError`) i parametry URL są w `lib/sources-index.ts` i zostają.

## Desired End State

Rząd 1: wyszukiwanie, rodzaj, widok. Rząd 2:
- pigułki (nieaktywna przerywana z „+”; aktywna `accent` z wartością i ✕);
- „Wyczyść filtry”;
- po prawej widoczna liczba „12 z 64 źródeł” (`role="status"`).

Okienka Miejsce i Archiwum:
- lista jednego wyboru z liczbą źródeł przy każdej pozycji (0 = wyszarzona);
- wyszukiwarka powyżej 7 pozycji.

Okienko Lata:
- pola „Rok od” / „Rok do” z dzisiejszym błędem zakresu;
- szybki wybór półwieczy;
- „Wyczyść lata”.

Na telefonie pigułki chowają się za „Filtry (N)”.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) |
| --- | --- | --- |
| Status wyników | Jeden widoczny „N z M źródeł” (`role="status"`, z zatwierdzonych filtrów) zamiast ukrytego „Znaleziono”; bez filtrów „M źródła” | Jeden region na żywo, ogłaszany raz na pauzę; oko i czytnik widzą to samo |
| Etykiety i komunikat lat | Zostają „Rok od” / „Rok do” i obecny komunikat `yearFieldsError` | Zgodne z „ten sam yearError”; komunikat cytuje widoczne etykiety |
| „Filtry (N)” | Liczy aktywne pigułki (zakres lat = 1) | Liczba równa temu, co widać po rozwinięciu, jak w makiecie |
| Półwiecza | Wyliczane z lat źródeł drzewa (każde półwiecze z ≥1 źródłem) | Każdy przycisk coś daje, jak liczby przy pozycjach |
| Liczby przy pozycjach | `filterSources` z podstawioną pozycją, przy szukaniu, rodzaju i pozostałych filtrach | Pokazuje dokładnie, ile kart zostanie po wyborze; bez endpointu |
| Okienko | shadcn `Popover` (`@radix-ui/react-popover`, zgoda 2026-10-12) + własny `role="listbox"` | `DropdownMenu` przechwytuje klawiaturę; `cmdk` to dodatkowa zależność |
| Format lat w pigułce | „1880 – 1900”, „od 1880”, „do 1900” (nowa funkcja, `formatYearRange` bez zmian) | Jak okres życia w design systemie; pobyty zostają przy „1900–1910” |

## Scope

**In scope:**
- `components/ui/popover.tsx` (shadcn, fokus jak `ui/*`);
- `sources-filters.tsx` jako `SourcesFilterBar`: pigułki, okienka, status, wersja poniżej `sm`;
- przestawienie rzędów w `pages/sources/index.tsx`;
- nowe funkcje w `lib/sources-index.ts` z testami Vitest;
- zrzuty jasny/ciemny/telefon;
- E2E `sources-grid-with-filters`, `sources-page-states` i pomocnik `openSourceFromList` na role pigułek, okienek i statusu.

**Out of scope:**
- backend, parametry URL, reguły filtrowania;
- etykiety „Od roku” / „Do roku” z design systemu;
- `cmdk` / `Command`;
- FilterBar na `/persons` i `/places`;
- zmiany asercji biznesowych w E2E.

## Architecture / Approach

Czyste funkcje w `lib/sources-index.ts` (`hiddenFilterCount` z nową semantyką, `filterOptionCounts`, `formatYearsFilter`, `halfCenturyPresets`, `resultCountText`) liczą wszystko, co pokazuje pasek. `pages/sources/index.tsx` przekazuje ich wyniki i dotychczasowe callbacki (`onPlaceChange`, `onArchiveChange`, `onYearFromChange`, `onYearToChange`, `onClear`) do `SourcesFilterBar`. Stan lat, debounce, `visit()` i `yearError` zostają w stronie bez zmian, więc pola w okienku Lata to tylko inne miejsce dla tych samych pól.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Logika paska (lib) | Licznik pigułek, liczby pozycji, format lat, półwiecza, tekst statusu + Vitest | Odmiana „z 1 źródła” / „z 64 źródeł” |
| 2. Popover i pasek pigułek (UI) | `ui/popover.tsx`, nowy `sources-filters.tsx`, rzędy strony, status, zrzuty | Dostępna klawiatura listy (pomijanie 0, fokus po ✕); CLI shadcn może dodać `radix-ui` zamiast `@radix-ui/react-popover` |
| 3. E2E na role | Specy i pomocnik na pigułki, okienka i status ze wzorcem | M zmienne przy równoległych testach — tylko wzorce |

**Prerequisites:** Sail uruchomiony; zgoda na `@radix-ui/react-popover` (jest, 2026-10-12).
**Estimated effort:** ~2 sesje w 3 fazach (faza 2 największa).

## Open Risks & Assumptions

- Zakładamy, że zmiana tekstu statusu („Znaleziono: 1 źródło” → „1 z M źródeł”) nie dotyka innych speców niż `openSourceFromList` i `sources-grid-with-filters`. Pełny pakiet E2E w fazie 3 to sprawdza.
- `Tooltip` na wyłączonej pigułce wymaga owijającego elementu (wyłączony przycisk nie dostaje zdarzeń). Powód jest też w `sr-only` opisie.

## Success Criteria (Summary)

- Filtrowanie po miejscu, latach i archiwum daje te same karty i ten sam adres co przed zmianą, a każdy aktywny filtr widać w pigułce i zdejmuje się go jednym kliknięciem.
- Przy każdej pozycji widać, ile źródeł zostanie po wyborze; liczba wyników „N z M źródeł” jest widoczna i ogłaszana raz na pauzę.
- `check`, `types:check`, `test:js`, `build` i cały pakiet E2E przechodzą; zrzuty zgodne z makietą.
