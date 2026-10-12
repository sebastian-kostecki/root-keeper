
# Widok /persons zgodny z systemem projektowym — Implementation Plan

## Overview

Widok `/persons` (strona startowa po zalogowaniu) zostaje dopasowany do systemu projektowego i do kierunku „osoba w panelu bocznym”. „Dodaj osobę” otwiera panel dodawania z drzewa w trybie samego formularza (`?add=person`). Po zapisie lista zostaje z filtrami, a panel pokazuje podgląd nowej osoby. Nagłówek i wyszukiwarka przechodzą na wzorzec sąsiednich list. Skoki do litery dostają prawdziwy `Button`, ręczny pierścień fokusu przechodzi na wspólną stałą, a błąd daty na token `destructive`. Wyszukiwanie dostaje stan błędu. Na koniec krok „Utrwal to” (reguła + test skanu) i bramka zrzutów. Plan zamyka zarzuty C1–C5 z `research.md`; D2 znika razem z C1.

## Current State Analysis

- **C1 — tworzenie w modalu.** Wyzwalacz `resources/js/pages/persons/index.tsx:125` (nagłówek `:138-141`, pusty stan `:186-191`) otwiera `resources/js/components/persons/person-create-dialog.tsx:77-147` (`Dialog` + `FormDialogContent sm:h-[90vh]`, lokalny `Card` `:48-64`, ręczny awatar `:100-108`). Zapis `app/Http/Controllers/PersonController.php:106` przekierowuje na `/tree/{id}?person={id}`, przypięte w `tests/Feature/PersonsTest.php:444` (`personPreviewUrl($person, 'tree.show', [$person])`). Żaden spec E2E nie używa tego modala, ale helper `createPerson` (`tests/e2e/support/api.ts:143`) zależy od ścieżki tego redirectu (Faza 3, pkt 3).
- **Panel dodawania z drzewa** (`tree-add-person-panel`, Faza 2 zamknięta w `9350c59`, Faza 3 zamknięta w `af8606e`; kolejne fazy drzewa w toku w drzewie roboczym) ma już tryb „sam formularz”: `startsInForm` w `resources/js/components/tree/add-person-config.ts:46` (pusty proband bez osób). Panel (`resources/js/components/tree/add-person-panel.tsx`) sam rozwiązuje cel z arkusza (`:56`, `resolveAddTarget(sheet, value, { hasPeople })`), ładuje kandydatów przy każdym montowaniu (`:111-113`, także w trybie samego formularza) i ma zagnieżdżony strażnik `SectionEditorProvider record={{ key: 'add', id: value }}` (`:65`).
- **Redirect „ten sam widok + podgląd”** istnieje: `app/Support/PersonPanelRedirect.php` (Referer → ścieżka + query bez kluczy paneli + `person=<id>`, fallback z `person=`).
- **Wyszukiwanie gubi klucze paneli:** `visitSearch`/`visitDisplay` (`index.tsx:52-96`) budują URL z `PersonController.index({ query: buildPersonsQuery(...) })` (`resources/js/lib/persons-index.ts`), czyli tylko `q/view/sort`. „Źródła” zachowują panel przez `withSourcesQuery` (`resources/js/lib/sources-index.ts:116-141`).
- **C2:** `Heading` w wariancie domyślnym (`index.tsx:133-136` → `<h2>` + `mb-8`, `resources/js/components/heading.tsx:13-32`), wiersz wyszukiwania bez `role="search"` (`index.tsx:145`), `SegmentedToggle` bez `iconOnlyBelowSm` (`:156-172`). Sąsiedzi: `pages/sources/index.tsx:350,360,414`.
- **C3:** skoki do litery `resources/js/components/persons/persons-list.tsx:105-111` odtwarzają `Button outline sm` z klas; ręczny pierścień `focus-visible:ring-ring/50 … focus-visible:ring-[3px]` w `persons-list.tsx:60` (wiersz, `ring-inset`), `:108` (skok), `persons-table.tsx:141` (link w tabeli), `components/table-parts.tsx:44` (przycisk sortowania, współdzielony przez tabele osób, źródeł, miejscowości i parafii).
- **C4:** `resources/js/components/persons/partial-date-input.tsx:102`: `text-red-600 dark:text-red-400`; `InputError` używa `text-destructive-foreground` (`resources/js/components/input-error.tsx:13`). `PartialDateInput` renderuje się w `PersonFormFields`, więc też w nowym panelu.
- **C5:** brak `onHttpException`/`onNetworkError` w `index.tsx`; gałąź „brak wyników” (`:194-195`) nie jest przygaszana ani `aria-busy`.
- **Reguły agenta:** `CLAUDE.md` nie ma bloku UI o tokenach/komponentach; blok `<!-- BEGIN @przeprogramowani/10x-cli -->…<!-- END … -->` zajmuje linie 1–36 i CLI go przepisuje. `.ai/rules` i `context/foundation/lessons.md` nie istnieją.

## Desired End State

- „Dodaj osobę” (nagłówek i pusty stan) to link `?add=person`, który otwiera po prawej panel „Dodaj osobę” z formularzem nowej osoby. Lista zwęża się obok, a URL zostaje na `/persons` z bieżącymi `q/view/sort`.
- Zapis zamienia panel na podgląd nowej osoby. Lista zostaje z tymi samymi filtrami, nawet gdy nowa osoba ich nie spełnia. Toast „Dodano osobę”.
- Brudny formularz chroni pasek „Masz niezapisane zmiany w nowej osobie” przy X, Escape, kliknięciu osoby na liście i nawigacji. Pisanie w wyszukiwarce, przełączanie widoku i sortowanie **nie** zamykają panelu (ani tworzenia, ani podglądu osoby).
- Strona ma `<h1>` „Osoby”, landmark `role="search"`, przełącznik z samymi ikonami poniżej `sm`. Skoki do litery wyglądają jak każdy przycisk obrysowany, a pierścień fokusu wierszy i nagłówków sortowania pochodzi z jednej stałej.
- Nieudane wyszukiwanie lub przełączenie pokazuje przy polu komunikat z „Spróbuj ponownie” (token `destructive`). Każda gałąź wyników jest przygaszana i `aria-busy` w trakcie wyszukiwania.
- `person-create-dialog.tsx` nie istnieje. `CLAUDE.md` ma blok UI, a „Side panels vs modals” mówi, że tworzenie osoby z `/persons` odbywa się w panelu. Test Vitest pilnuje braku zakodowanych wartości w plikach widoku.

Weryfikacja: Pest (redirect `store`), Vitest (`withPersonsQuery`, cel `person`, skan widoku), spec E2E tworzenia, strażnika i błędu wyszukiwania, zrzuty „przed/po” z `verdict.md`.

### Key Discoveries:

- Tryb `startsInForm` + `PersonController.store.form()` daje „Dodaj osobę” bez nowego komponentu. Brakuje tylko celu bez arkusza i wyłączenia ładowania kandydatów, gdy listy nie ma (`add-person-panel.tsx:111-113`).
- `PersonPanelRedirect::to($request, $person, route('persons.index'))` z Referera `/persons?q=Kowal&add=person` daje `/persons?q=Kowal&person=<id>`: usuwa `add`, zachowuje filtry. To zachowanie z decyzji „Zachowaj filtry”.
- Strażnik zagnieżdżonego providera przepuszcza GET, który zachowuje `add=person` (`keepsRecord` porównuje `searchParams.get('add')`). Gdy wyszukiwanie zachowuje klucze paneli, nie wywoła paska, a pasek zatrzyma tylko wizyty, które naprawdę opuszczają panel.
- `visitDisplay` używa `only: ['filters']`, więc `personPreview` zostaje w propsach przy zachowanym `?person=`. `visitSearch` to pełne przeładowanie, a serwer rozwiąże panel ponownie z URL.
- Inertia v3: `onHttpException`/`onNetworkError` zwracające `false` zostawiają użytkownika na stronie bez strony błędu (search-docs, „Manual Visits → Event Callbacks”).
- Vitest działa w środowisku node (`vite.config.ts:42-44`, `@types/node` w `package.json`), więc test skanu może czytać pliki przez `node:fs`.

## What We're NOT Doing

- Brak stałej fokusu w pozostałych 10 plikach z ręcznym pierścieniem (decyzja C3 — deferred). Wyjątek: `table-parts.tsx` jest w zakresie widoku i zmienia się dla wszystkich tabel, z identycznym wyglądem.
- „Dom” rozdzielony (logowanie → `/persons`, logo → `/tree`) — D1 deferred.
- D3 (badge „Probant”), D4 (`PersonAvatar` vs `ui/avatar`), D5 (paginacja), D7 (`align-[-2px]`) bez zmian. D6 (tabela na 375 px) tylko oceniany na zrzucie „przed”; naprawa poza zakresem, chyba że zrzut pokaże blokadę akcji (wtedy wpis w `verdict.md` jako deferred z wagą).
- Bez przenoszenia `add-person-panel.tsx`/`add-person-config.ts` z `components/tree/` (zmiana drzewa wciąż je edytuje) i bez serwerowego propa dla `add`.
- Bez kitchen sinka i `toHaveScreenshot`. Bramka to zrzuty w folderze zmiany (decyzja z research).
- Bez zmian w ścieżce edycji osoby i w `PUT persons/{person}`.

## Implementation Approach

Kolejność wg `/10x-ui`: tokeny/stałe → jeden widok → stany. Faza 3 (panel) jest jedyną zależną od zmiany drzewa i startuje po zacommitowaniu (lub odłożeniu) zmian drzewa w `pages/tree/show.tsx` i `components/tree/family-sheet.tsx`. Fazy 1–2 dotykają plików, których drzewo nie edytuje, więc mogą wejść wcześniej. Panel dodawania przestaje sam rozwiązywać cel: strona przekazuje gotowy `AddTarget`. Drzewo woła `resolveAddTarget`, a `/persons` ma własny jednolinijkowy resolver. Dzięki temu panel nie zna stron. Faza 5 zamyka zmianę testem przeglądarkowym, regułą i kontrolą, które utrzymają widok przy kontrakcie.

## Critical Implementation Details

- **Kolejność względem `tree-add-person-panel`.** Faza 3 zmienia sygnaturę `AddPersonPanel` i `AddPersonConfig`, które Faza 3 drzewa już zacommitowała (`git log --oneline -- resources/js/components/tree/add-person-panel.tsx` pokazuje commit „p3”). Kolejne fazy drzewa nie dotykają `add-person-*`, ale edytują `pages/tree/show.tsx` i `family-sheet.tsx`: start Fazy 3 po ich zacommitowaniu. Faza 5 drzewa przepisuje linię „small pickers…” w `CLAUDE.md` z wyjątkiem „„Dodaj osobę” na `/persons` do czasu osobnej zmiany”. Faza 5 tej zmiany usuwa ten wyjątek niezależnie od tego, która wersja linii jest wtedy w pliku.
- **Adres na żywo przy wyszukiwaniu.** `withPersonsQuery` musi dostać `window.location` (jak `liveUrl()` w `hooks/use-side-panel-navigation.ts:18-22`), nie `usePage().url`. Wizyta z debounce'u odpala 300 ms po wpisaniu, a panel mógł się w tym czasie otworzyć lub zamknąć.

## Faza 1: Tokeny i stała fokusu

### Overview

Zrzuty „przed”, wspólna stała pierścienia fokusu w miejscach widoku z ręcznym ciągiem klas, błąd daty na token. Zamyka C4 i część C3 (stała).

### Changes Required:

#### 1. Zrzuty „przed”

**File**: `context/changes/persons-index-ui/screenshots/before/` (nowy), `context/changes/persons-index-ui/screenshots/shots.mjs` (nowy)

**Intent**: Stan wyjściowy do porównania i potwierdzenia wniosków z kodu: przesunięcie „Dodaj osobę” przez `mb-8` (C2), waga D6 (menu akcji tabeli na 375 px), fokus skoku do litery i wiersza tabeli (nigdy niesfotografowany).

**Contract**: wzorzec `context/archive/2026-09-29-persons-list-views/screenshots/` (serwer E2E `scripts/e2e-server.sh`, port 8100, dane z `database/seeders/TreeViewDemoSeeder.php`); 1440×900 i 375×812. Ujęcia: `01-list-desktop`, `02-list-mobile`, `03-table-desktop`, `04-table-mobile`, `05-no-results-desktop`, `06-create-modal-desktop`, `07-create-modal-mobile`, `08-focus-jump-link`, `09-focus-table-row`.

#### 2. Stała pierścienia fokusu

**File**: `resources/js/lib/focus-ring.ts` (nowy)

**Intent**: Jedno źródło dla pierścienia fokusu elementów interaktywnych spoza `ui/` (wiersze-linki, surowe przyciski) o tym samym wyglądzie co pierścień przycisków shadcn.

**Contract**: `export const focusRing: string` = `outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px]` (wartości z `ui/button.tsx`). Wariant wewnętrzny (`ring-inset`) i tło fokusu dokłada miejsce użycia przez `cn()`. PHPDoc/JSDoc wskazuje, że `components/ui/*` mają własne pierścienie.

#### 3. Użycie stałej w widoku

**File**: `resources/js/components/persons/persons-list.tsx:60`, `resources/js/components/persons/persons-table.tsx:141`, `resources/js/components/table-parts.tsx:44`

**Intent**: Te trzy miejsca zamiast ręcznego ciągu używają `focusRing` (wiersz listy z `focus-visible:ring-inset` i `focus-visible:bg-accent/50` jak dziś). Skok do litery (`:108`) przechodzi na `Button` w Fazie 2 i nie potrzebuje stałej.

**Contract**: `grep -n "focus-visible:ring-\[3px\]" ` na tych trzech plikach zwraca pusto; wygląd fokusu bez zmian.

#### 4. Token błędu daty

**File**: `resources/js/components/persons/partial-date-input.tsx:102`

**Intent**: Komunikat błędu daty w tym samym kolorze co każdy inny błąd walidacji, sterowany tokenem w obu motywach.

**Contract**: klasa błędu = ta sama co `InputError` (`text-destructive-foreground text-sm`); `aria`/`id` podpowiedzi bez zmian (podpowiedź i błąd dzielą jeden `<p>`, więc nie zamieniamy go na `InputError`).

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą: `vendor/bin/sail npm run test:js`
- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Brak ręcznego pierścienia w plikach fazy: `grep -n "focus-visible:ring-\[3px\]" resources/js/components/persons/persons-table.tsx resources/js/components/table-parts.tsx` zwraca pusto, a w `persons-list.tsx` zostaje tylko skok do litery (do Fazy 2)
- Brak klas palety w polu daty: `grep -nE "text-red-|dark:text-red-" resources/js/components/persons/partial-date-input.tsx` zwraca pusto

#### Manual Verification:

- Zrzuty `before/01–09` zapisane; odnotowane w `screenshots/verdict.md` (sekcja „przed”): czy `mb-8` przesuwa „Dodaj osobę”, waga D6
- Tab po liście, tabeli i nagłówkach sortowania (także na „Źródłach”) pokazuje ten sam pierścień co przed zmianą
- Błędna data w panelu osoby (np. 31.02) pokazuje komunikat w kolorze błędu w jasnym i ciemnym motywie

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 2: Widok listy — nagłówek, wyszukiwarka, skoki do litery

### Overview

Wzorzec sąsiednich list w nagłówku i wierszu wyszukiwania oraz prawdziwy przycisk dla skoków do litery. Zamyka C2 i resztę C3.

### Changes Required:

#### 1. Nagłówek i wiersz wyszukiwania

**File**: `resources/js/pages/persons/index.tsx`

**Intent**: Strona startowa ma `<h1>` i landmark wyszukiwania jak „Źródła”, „Miejscowości” i „Parafie”. Na wąskim ekranie przełącznik pokazuje same ikony.

**Contract**: `Heading variant="page"` (`:133`); kontener wiersza wyszukiwania (`:145`) z `role="search"`; `SegmentedToggle … iconOnlyBelowSm` (`:156`). Wyrównanie nagłówka i „Dodaj osobę” jak w `pages/sources/index.tsx:348-358`.

#### 2. Skoki do litery

**File**: `resources/js/components/persons/persons-list.tsx:105-111`

**Intent**: Skok wygląda i reaguje (hover, fokus, obramowanie) jak każdy przycisk obrysowany w aplikacji.

**Contract**: `<Button variant="outline" size="sm" asChild><a href="#grupa-…">…</a></Button>` z zachowanym `min-w-8` (wąskie litery). `nav aria-label="Przejdź do litery"` bez zmian. Po zmianie plik nie zawiera `ring-[3px]` poza stałą.

### Success Criteria:

#### Automated Verification:

- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Specy listy przechodzą: `npx playwright test tests/e2e/seed.spec.ts tests/e2e/person-side-panel.spec.ts tests/e2e/person-panel-page-navigation-guard.spec.ts`
- Brak ręcznego pierścienia w widoku: `grep -rn "focus-visible:ring-\[3px\]" resources/js/pages/persons resources/js/components/persons/persons-list.tsx resources/js/components/persons/persons-table.tsx resources/js/components/table-parts.tsx` zwraca pusto

#### Manual Verification:

- 1440 px: „Dodaj osobę” w linii tytułu „Osoby”; drzewo dostępności pokazuje `heading level 1 "Osoby"` i landmark `search`
- 375 px: przełącznik Lista/Tabela z samymi ikonami, wiersz wyszukiwania nie zawija się poza ekran
- Skoki do litery: hover i fokus jak „Dodaj osobę” w wariancie obrysowanym; klik przewija do grupy

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 3: Panel tworzenia osoby

### Overview

„Dodaj osobę” otwiera panel dodawania w trybie samego formularza; zapis zostawia listę z filtrami i otwiera podgląd nowej osoby; wyszukiwanie nie zamyka paneli. Zamyka C1 (i D2). **Warunek startu:** Faza 3 `tree-add-person-panel` zacommitowana (`af8606e`, spełniony) oraz brak niezacommitowanych zmian drzewa w `pages/tree/show.tsx` i `components/tree/family-sheet.tsx` (`git status`). `show.tsx` ma dwa wywołania `AddPersonPanel` (pusty proband i arkusz); oba dostają `target`.

### Changes Required:

#### 1. Redirect po zapisie

**File**: `app/Http/Controllers/PersonController.php:100-107`

**Intent**: Zapis wraca na stronę, z której przyszedł użytkownik (lista z jej filtrami), z otwartym podglądem nowej osoby, zamiast przenosić go na drzewo.

**Contract**: `return PersonPanelRedirect::to($request, $person, route('persons.index'));`. PHPDoc metody mówi o powrocie na stronę Referera. Toast bez zmian.

#### 2. Testy Pest

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Asercja redirectu zmienia się celowo (nowe zachowanie); dochodzą przypadki filtrów i braku Referera.

**Contract**:
- `:444` → `personPreviewUrl($person, 'persons.index', ['q' => 'Kowal'])`.
- Nowy: Referer `/persons?q=Kowal&view=tabela&add=person` → redirect `persons.index` z `q=Kowal&view=tabela&person=<id>` (bez `add`).
- Nowy: bez Referera → `persons.index?person=<id>`.

#### 3. Helper E2E `createPerson`

**File**: `tests/e2e/support/api.ts:143-154`

**Intent**: Po zmianie redirectu `store` helper nadal tworzy osoby przez API we wszystkich specach (używa go 18 plików `tests/e2e`). Bez Referera redirect to fallback `/persons?person=<id>`.

**Contract**: `expectRedirect(response, /^\/persons$/)` zamiast `/^\/tree\/\d+$/`; JSDoc: „the id is read from `?person=` of the redirect to `/persons`". Odczyt id (`idFromLocation(response, 'person')`) bez zmian. Bramką jest 3.5 (specy drzewa tworzą osoby przez `createPerson`).

#### 4. Cel `person` i panel przyjmujący gotowy cel

**File**: `resources/js/lib/add-person-target.ts`, `resources/js/lib/add-person-target.test.ts`, `resources/js/components/tree/add-person-config.ts`, `resources/js/components/tree/add-person-panel.tsx`, `resources/js/pages/tree/show.tsx`

**Intent**: Panel dostaje rozwiązany cel od strony zamiast arkusza. Nowy cel `person` to sam formularz bez listy i bez ładowania kandydatów. Drzewo zachowuje się jak dziś.

**Contract**:
- `AddTarget` += `{ kind: 'person' }`; `resolvePersonsAddTarget(value: string | null): AddTarget | null` (`'person'` → cel, reszta → `null`); `resolveAddTarget` dla `'person'` zwraca `null` (drzewo nie otwiera panelu); `addValueFor({ kind: 'person' })` = `'person'`. Vitest: `person` na `/persons`, `person` na drzewie (`null`, z arkuszem i bez), inna wartość na `/persons` (`null`).
- `AddPersonConfig.pick` staje się opcjonalne; config `person`: tytuł „Dodaj osobę”, opis „Po zapisaniu otworzy się podgląd tej osoby.”, `form.definition = PersonController.store.form()`, `submitTestId: 'create-person-button'`, `startsInForm: true`, bez `pick`.
- `AddPersonPanel({ value, target, personCandidates?, genders, religions })`: renderuje `null`, gdy `value`/`target` = `null`. Kandydatów ładuje tylko, gdy `!config.startsInForm` (dotyczy też probanta bez osób). Widok listy jest niedostępny, gdy brak `pick`.
- `pages/tree/show.tsx` liczy `target` przez `resolveAddTarget(sheet, add, { hasPeople })` i przekazuje go panelowi.

#### 5. Wyzwalacze i panel na `/persons`

**File**: `resources/js/pages/persons/index.tsx`, `resources/js/components/persons/persons-empty-state.tsx` (tylko JSDoc), `resources/js/components/persons/person-create-dialog.tsx` (usunięty)

**Intent**: Oba „Dodaj osobę” to linki otwierające panel (wzorzec „Dodaj źródło”, `pages/sources/index.tsx:330-340`); strona renderuje `AddPersonPanel` dla `add` z `usePage().url`.

**Contract**: `Button asChild` + `Link href={hrefFor('person')} {...panelVisitOptions}` z `useSidePanelNavigation('add')`; `<AddPersonPanel value={add} target={resolvePersonsAddTarget(add)} genders religions />`. Props strony bez zmian (`genders`, `religions` już są). `grep -rn "PersonCreateDialog" resources/js` zwraca pusto.
- **Wyścig z debounce'em.** Timer wyszukiwania dziś żyje w `useEffect` i po kliknięciu „Dodaj osobę” w ciągu 300 ms odpaliłby `visitSearch` z adresem bez `add`, anulując wizytę otwarcia panelu. Oba linki dostają `onClick` wzorowany na `openFromList` (`pages/sources/index.tsx:236-262`): gdy czeka niewysłane wyszukiwanie (`pendingSearch` ref zamiast samego `useEffect`), kasuje timer, ustawia `lastVisitedQuery` i otwiera panel jedną wizytą `router.visit(withPersonsQuery(hrefFor('person'), { ...filters, q: trimmed }), { replace, preserveState, preserveScroll })` (bez `only`, bo lista musi się przeładować). Klik z modyfikatorem i brak oczekującego wyszukiwania zostają zwykłym linkiem.

#### 6. Wyszukiwanie zachowuje panele

**File**: `resources/js/lib/persons-index.ts`, `resources/js/lib/persons-index.test.ts`, `resources/js/pages/persons/index.tsx`

**Intent**: Wpisywanie, czyszczenie wyszukiwania, przełączanie widoku i sortowanie ustawiają tylko `q/view/sort` na bieżącym adresie i nie ruszają kluczy paneli (`add`, `person`). Dzięki temu można sprawdzić duplikat bez utraty formularza (decyzja „Panel zostaje”), a podgląd osoby też zostaje.

**Contract**: `withPersonsQuery(url: string, filters: Partial<PersonsFilters>): string`, lustro `withSourcesQuery` (`lib/sources-index.ts:116`): ustawia/usuwa `q`, `view`, `sort` wg `buildPersonsQuery`, pozostałe parametry bez zmian. `visitSearch`/`visitDisplay` wołają `router.get(withPersonsQuery(<adres na żywo>, …), {}, …)`. Vitest: zachowuje `add`/`person`, usuwa puste `q`, `sort` tylko z `view=tabela`.

### Success Criteria:

#### Automated Verification:

- Testy Pest przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/WriteTargetsTest.php`
- Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent && vendor/bin/sail bin phpstan analyse`
- Testy JS przechodzą (`add-person-target`, `persons-index`): `vendor/bin/sail npm run test:js`
- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Specy drzewa nadal przechodzą (tworzą osoby przez `createPerson`, więc łapią też zmianę `api.ts`): `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts`
- Brak odwołań do modala: `grep -rn "PersonCreateDialog\|person-create-dialog" resources/js` zwraca pusto

#### Manual Verification:

- `/persons?q=Kowal` → „Dodaj osobę” otwiera panel z formularzem obok listy (lista zwężona, nie przyciemniona); zapis „Jan Nowak” zamienia panel na podgląd Jana Nowaka, URL `/persons?q=Kowal&person=<id>`, toast „Dodano osobę”
- Brudny formularz + klik osoby na liście / X / Escape → pasek „Masz niezapisane zmiany w nowej osobie”; „Porzuć” i „Zapisz” działają
- Brudny formularz + pisanie w wyszukiwarce i przełączenie na tabelę → lista się zmienia, panel i wpisane dane zostają
- Wpisanie frazy i kliknięcie „Dodaj osobę” w ciągu 300 ms → panel się otwiera i lista pokazuje wyniki frazy (wizyta nie jest anulowana)
- Otwarty podgląd osoby + wyszukiwanie → podgląd zostaje
- Pusty `/persons` (nowe konto): „Dodaj osobę” w pustym stanie otwiera ten sam panel
- 375 px: panel pełnoekranowy, zamknięcie wraca do listy w tym samym miejscu
- `/tree/<id>?add=person` nie otwiera panelu i nie wywala strony

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 4: Stany wyszukiwania

### Overview

Stan błędu dla nieudanych wizyt i spójne „w trakcie” dla każdej gałęzi wyników; macierz 7 stanów jako kryteria. Zamyka C5.

### Changes Required:

#### 1. Błąd wyszukiwania i przełączania

**File**: `resources/js/pages/persons/index.tsx`

**Intent**: Gdy wizyta wyszukiwania albo widoku/sortu padnie (4xx/5xx, sieć), użytkownik zostaje na stronie i widzi przy polu, że wyniki mogą być nieaktualne, z możliwością ponowienia, zamiast cicho gasnącego spinnera.

**Contract**: `onHttpException` i `onNetworkError` w obu wizytach zwracają `false` (bez strony błędu) i ustawiają stan błędu z funkcją ponowienia ostatniej wizyty. `onStart` czyści błąd. Komunikat pod wierszem wyszukiwania z `role="alert"` i klasą `text-destructive-foreground text-sm` (jak `InputError`): „Nie udało się wyszukać — pokazane wyniki mogą być nieaktualne.” / „Nie udało się zmienić widoku.” + `Button variant="outline" size="sm"` „Spróbuj ponownie”. Wyścig: błąd ustawia tylko najnowsza wizyta wyszukiwania (ten sam `visitId` co spinner).

#### 2. „W trakcie” dla każdej gałęzi

**File**: `resources/js/pages/persons/index.tsx:185-221`

**Intent**: Kolejne wyszukiwanie przygasza i oznacza `aria-busy` także stan „brak wyników”, nie tylko listę.

**Contract**: kontener `aria-busy={isSearching}` + `opacity-60 transition-opacity` obejmuje gałęzie „brak wyników” i wyniki; pusty stan drzewa (bez `q`) poza nim.

### Success Criteria:

#### Automated Verification:

- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Testy JS przechodzą: `vendor/bin/sail npm run test:js`

#### Manual Verification:

Macierz 7 stanów (desktop + 375 px, jasny motyw; ciemny dla `error`):

- Pole wyszukiwania: default / hover / focus-visible (pierścień `Input`) / error (komunikat + „Spróbuj ponownie” po odcięciu sieci w DevTools, ponowienie po przywróceniu pokazuje wyniki) / empty (placeholder) / loading (spinner w polu); disabled: N/A
- Przełącznik Lista/Tabela: default / hover / focus-visible / error (komunikat „Nie udało się zmienić widoku.” po odcięciu sieci) / loading: N/A (wizyta `only: ['filters']`, natychmiastowa); disabled: N/A
- Nagłówki sortowania: default / hover / focus-visible (stała) / error (jak przełącznik); disabled, empty, loading: N/A
- Skoki do litery: default / hover / focus-visible (`Button`); pozostałe N/A
- Wiersz osoby (lista i tabela): default / hover / focus-visible (stała) / empty (`PersonsEmptyTree`, `PersonsNoResults`) / loading (przygaszenie + `aria-busy` także przy „brak wyników”)
- „Dodaj osobę”: default / hover / focus-visible / error (błędy walidacji w panelu, data w tokenie) / empty (CTA pustego stanu) / loading (spinner na „Zapisz” w panelu)
- Menu akcji w tabeli: default / hover / focus-visible (`Button ghost icon`)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 5: Utrwal to i bramka wizualna

### Overview

Test przeglądarkowy nowego zachowania, kontrola zakodowanych wartości, reguła agenta, zrzuty „po”. E2E prowadzone przez `/10x-e2e` (ryzyko → eksploracja `playwright-cli` → generowanie → przegląd antywzorców → weryfikacja przez wprowadzony błąd).

### Changes Required:

#### 1. Spec E2E

**File**: `tests/e2e/persons-add-person-panel.spec.ts` (nowy)

**Intent**: Ryzyka: (a) osoba dodana z listy ląduje gdzie indziej albo lista znika; (b) wpisane dane giną przy kliknięciu w listę lub wyszukiwaniu obok niemodalnego panelu; (c) nieudane wyszukiwanie wygląda jak wynik. Scenariusze: (1) `/persons?q=<sufiks>` → „Dodaj osobę” → wpisz imię i nazwisko z sufiksem → wpisz w wyszukiwarkę → panel i dane zostają → „Zapisz” → widoczny dialog nazwany imieniem i nazwiskiem nowej osoby, `waitForURL` na `/persons` z `person=`; (2) brudny formularz → klik osoby na liście → alert „Masz niezapisane zmiany w nowej osobie” → „Porzuć” otwiera panel klikniętej osoby i nie tworzy osoby; (3) `page.route` przerywa żądanie wyszukiwania → widoczny alert błędu → odblokowanie i „Spróbuj ponownie” → wyniki.

**Contract**: lokatory `getByRole`/`getByLabel`, bez `waitForTimeout`; dane z sufiksem timestamp przez `createPerson` i sprzątanie `cleanupBySuffix` (`tests/e2e/support/api.ts:144,430`); wzorzec `tests/e2e/person-panel-unsaved-guard.spec.ts`. Weryfikacja przez błąd: wyłączenie `markDirty` w panelu czerwieni scenariusz 2.

#### 2. Test skanu zakodowanych wartości

**File**: `resources/js/lib/persons-view-tokens.test.ts` (nowy)

**Intent**: Regresja literału w widoku `/persons` jest czerwona w `test:js`, a nie zależy od pamięci agenta.

**Contract**: test czyta przez `node:fs` listę plików widoku: `pages/persons/index.tsx`, `components/persons/persons-{list,table,empty-state}.tsx`, `components/persons/person-list-parts.tsx`, `components/persons/partial-date-input.tsx`, `components/table-parts.tsx`. Failuje na: hex/`rgb(`/`oklch(`, klasach palety Tailwind (`(bg|text|border|ring|from|to|via)-(red|blue|…|zinc|neutral|stone|gray|slate)-\d{2,3}`), wartościach arbitralnych `-\[-?\d+px\]` (także ujemnych) i inline `style=`. Wyjątki jako jawna lista z powodem: `align-[-2px]` w `person-list-parts.tsx` (D7). Komunikat błędu podaje plik, linię i token do użycia.

#### 3. Reguła agenta

**File**: `CLAUDE.md` (poza blokiem `<!-- BEGIN/END @przeprogramowani/10x-cli -->`)

**Intent**: Następny agent wie, gdzie są tokeny, komponenty i stała fokusu oraz że tworzenie osoby z `/persons` odbywa się w panelu.

**Contract**:
- Nowa sekcja „UI: tokens and shared components” (krótko, po angielsku jak reszta pliku): tokeny w `resources/css/app.css` (`:root`/`.dark` → `@theme`), używać ról (`text-destructive-foreground`, `bg-accent`), żadnych klas palety, hex ani wartości arbitralnych w widokach. Komponenty: sprawdzić `resources/js/components/ui/` i współdzielone `components/{heading,search-field,segmented-toggle,empty-state,side-panel,table-parts}.tsx` przed stworzeniem nowego, brakujące shadcn przez `npx shadcn add <name>` (z akceptacją zależności). Pierścień fokusu elementów spoza `ui/` = `focusRing` z `resources/js/lib/focus-ring.ts`. Strony list: `Heading variant="page"` + `role="search"`. Kontrola: `resources/js/lib/persons-view-tokens.test.ts`; bramka wizualna = zrzuty w `context/changes/<id>/screenshots/`.
- „Side panels vs modals”: usunięty wyjątek „Dodaj osobę” na `/persons` (jeśli Faza 5 drzewa go dopisała); dopisane, że tworzenie osoby z `/persons` to panel dodawania `?add=person` (`resources/js/components/tree/add-person-panel.tsx`).

#### 4. Zrzuty „po” i werdykt

**File**: `context/changes/persons-index-ui/screenshots/after/`, `context/changes/persons-index-ui/screenshots/verdict.md`

**Intent**: Te same ujęcia co `before/01–09` (`06–07` jako panel zamiast modala) + `10-search-error-desktop`; werdykt porównuje każdą parę i zapisuje deferred z powodem (D1–D7).

**Contract**: nazwy plików jak w `before/`; `verdict.md` z sekcjami „przed”, „po”, „deferred”.

### Success Criteria:

#### Automated Verification:

- Spec przechodzi: `npx playwright test tests/e2e/persons-add-person-panel.spec.ts`
- Scenariusz strażnika czerwienieje po celowym wyłączeniu `markDirty` w panelu i zielenieje po przywróceniu
- Test skanu przechodzi i czerwienieje po wstawieniu `text-red-600` do `persons-list.tsx` (cofnięte): `vendor/bin/sail npm run test:js`
- Pełny zestaw E2E przechodzi: `npx playwright test`
- `CLAUDE.md` zawiera `focus-ring.ts` i `add=person`, a blok 10x-cli jest nietknięty: `grep -n "focus-ring.ts\|add=person" CLAUDE.md` i `git diff CLAUDE.md` bez zmian w liniach 1–36
- Lint i format przechodzą: `vendor/bin/sail npm run check`

#### Manual Verification:

- Zrzuty `after/01–10` porównane z `before/` w `verdict.md`; każda różnica zamierzona
- Pełny zestaw Pest uruchomiony przez użytkownika: `vendor/bin/sail artisan test --compact`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `add-person-target.test.ts`: cel `person` na `/persons`, `person` na drzewie → `null`, inne wartości → `null`.
- `persons-index.test.ts`: `withPersonsQuery` zachowuje klucze paneli i inne parametry, normalizuje `q`/`view`/`sort` jak `buildPersonsQuery`.
- `persons-view-tokens.test.ts`: skan literałów w plikach widoku.

### Integration Tests:

- Pest `PersonsTest`: redirect `store` z filtrami z Referera, bez `add`; fallback bez Referera.
- E2E `persons-add-person-panel.spec.ts`: tworzenie z panelu, panel przeżywa wyszukiwanie, strażnik, błąd wyszukiwania.

### Manual Testing Steps:

1. Fazy 1–2: fokus i hover skoków/wierszy/sortowania, `<h1>` i landmark, 375 px.
2. Faza 3: dodanie osoby przy aktywnym wyszukiwaniu; strażnik; wyszukiwanie przy otwartym panelu; pusty `/persons`.
3. Faza 4: macierz 7 stanów, w tym błąd przy odciętej sieci.

## References

- Research: `context/changes/persons-index-ui/research.md` (Charges C1–C5, Deferred D1–D7, Decisions)
- Panel dodawania: `context/changes/tree-add-person-panel/plan.md` (Fazy 2–3, `startsInForm`), `resources/js/components/tree/add-person-panel.tsx`
- Redirect: `app/Support/PersonPanelRedirect.php`
- Wzorzec listy z panelem tworzenia: `resources/js/pages/sources/index.tsx:122-141,330-360`, `resources/js/lib/sources-index.ts:116`
- Zrzuty: `context/archive/2026-09-29-persons-list-views/screenshots/`
- Inertia v3 per-visit callbacks: https://inertiajs.com/docs/v3/the-basics/manual-visits

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tokeny i stała fokusu

#### Automated

- [x] 1.1 Testy JS przechodzą: `vendor/bin/sail npm run test:js` — c8eec4c
- [x] 1.2 Typy, lint i build przechodzą — c8eec4c
- [x] 1.3 Brak ręcznego pierścienia w plikach fazy (poza skokiem do litery) — c8eec4c
- [x] 1.4 Brak klas palety w polu daty — c8eec4c

#### Manual

- [x] 1.5 Zrzuty `before/01–09` zapisane; `mb-8` i waga D6 odnotowane w `verdict.md` — c8eec4c
- [x] 1.6 Pierścień fokusu listy, tabeli i sortowania (także „Źródła”) bez zmian — c8eec4c
- [x] 1.7 Błąd daty w kolorze błędu w obu motywach — c8eec4c

### Phase 2: Widok listy — nagłówek, wyszukiwarka, skoki do litery

#### Automated

- [x] 2.1 Typy, lint i build przechodzą — abd0be5
- [x] 2.2 Specy listy przechodzą (`seed`, `person-side-panel`, `person-panel-page-navigation-guard`) — abd0be5
- [x] 2.3 Brak ręcznego pierścienia w widoku — abd0be5

#### Manual

- [x] 2.4 1440 px: „Dodaj osobę” w linii tytułu; `<h1>` i landmark `search` — abd0be5
- [x] 2.5 375 px: przełącznik z samymi ikonami, wiersz wyszukiwania mieści się — abd0be5
- [x] 2.6 Skoki do litery: hover i fokus jak przycisk obrysowany; klik przewija do grupy — abd0be5

### Phase 3: Panel tworzenia osoby

#### Automated

- [x] 3.1 Testy Pest przechodzą (`PersonsTest`, `OwnershipBoundariesTest`, `WriteTargetsTest`) — 6fccebc
- [x] 3.2 Formatowanie PHP i Larastan — 6fccebc
- [x] 3.3 Testy JS przechodzą (`add-person-target`, `persons-index`) — 6fccebc
- [x] 3.4 Typy, lint i build przechodzą — 6fccebc
- [x] 3.5 Specy drzewa nadal przechodzą — 6fccebc
- [x] 3.6 Brak odwołań do modala — 6fccebc

#### Manual

- [x] 3.7 Zapis przy `?q=Kowal` otwiera podgląd nowej osoby, lista z filtrami zostaje — 6fccebc
- [x] 3.8 Strażnik: pasek przy kliknięciu osoby / X / Escape; Porzuć i Zapisz działają — 6fccebc
- [x] 3.9 Wyszukiwanie i przełączenie widoku nie zamykają panelu tworzenia ani danych — 6fccebc
- [x] 3.10 Wyszukiwanie nie zamyka podglądu osoby — 6fccebc
- [x] 3.11 Pusty `/persons`: „Dodaj osobę” otwiera ten sam panel — 6fccebc
- [x] 3.12 375 px: panel pełnoekranowy, zamknięcie wraca do listy — 6fccebc
- [x] 3.13 `/tree/<id>?add=person` nie otwiera panelu i nie wywala strony — 6fccebc

### Phase 4: Stany wyszukiwania

#### Automated

- [ ] 4.1 Typy, lint i build przechodzą
- [ ] 4.2 Testy JS przechodzą

#### Manual

- [ ] 4.3 Pole wyszukiwania: 7 stanów, w tym błąd i ponowienie po odcięciu sieci
- [ ] 4.4 Przełącznik Lista/Tabela: stany i komunikat błędu
- [ ] 4.5 Nagłówki sortowania: stany i błąd
- [ ] 4.6 Skoki do litery: default / hover / focus-visible
- [ ] 4.7 Wiersz osoby: stany, przygaszenie także przy „brak wyników”
- [ ] 4.8 „Dodaj osobę”: stany, w tym błędy walidacji i spinner zapisu w panelu
- [ ] 4.9 Menu akcji w tabeli: default / hover / focus-visible

### Phase 5: Utrwal to i bramka wizualna

#### Automated

- [ ] 5.1 Spec `persons-add-person-panel.spec.ts` przechodzi
- [ ] 5.2 Scenariusz strażnika czerwienieje po wyłączeniu `markDirty` i zielenieje po przywróceniu
- [ ] 5.3 Test skanu przechodzi i czerwienieje po wstawieniu klasy palety
- [ ] 5.4 Pełny zestaw E2E przechodzi: `npx playwright test`
- [ ] 5.5 `CLAUDE.md` zawiera regułę UI i `add=person`, blok 10x-cli nietknięty
- [ ] 5.6 Lint i format przechodzą: `vendor/bin/sail npm run check`

#### Manual

- [ ] 5.7 Zrzuty `after/01–10` porównane z `before/` w `verdict.md`
- [ ] 5.8 Pełny zestaw Pest uruchomiony przez użytkownika: `vendor/bin/sail artisan test --compact`
