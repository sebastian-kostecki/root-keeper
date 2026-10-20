# Strona „Miejsca” (/places) — plan implementacji

## Overview

Dwie listy, `/localities` „Miejscowości” i `/parishes` „Parafie”, łączymy w jedną stronę `/places` „Miejsca” (wzór A z researchu).
Strona ma przełącznik `SegmentedToggle` „Miejscowości n | Parafie n” zapisany w adresie jako `?kind=parafie`. Wyszukiwarka jest wspólna, a gdy jest aktywna, liczniki pokazują trafienia w obu spisach. Panele zostają takie jak dziś.
Plan zamyka zarzuty C1–C5 z `research.md`:
- C1: dwa wpisy w menu,
- C2: duplikacja stron,
- C3: ręczny pierścień fokusu,
- C4: „Użycia” o dwóch znaczeniach,
- C5: tabela parafii na 375 px.

Zostawia też zabezpieczenie: skan tokenów, regułę, e2e i zrzuty.

## Current State Analysis

- Dwie strony Inertia różnią się tylko nazwami, propsami parafii (`religions`, opcje `localities`) i filtrem po siedzibie. Porównanie: `resources/js/pages/localities/index.tsx:40-147` ≡ `resources/js/pages/parishes/index.tsx:53-160`; różnicę w filtrze widać w `resources/js/lib/parishes-index.ts:38-43`. Funkcje `build*Query`/`with*Search` w `lib/{localities,parishes}-index.ts:8-28` są identyczne.
- Kontrolery renderują po jednej stronie, a resolver panelu jest leniwy: `app/Http/Controllers/LocalityController.php:23-52`, `ParishController.php:24-55`. `normalizeSearchQuery` jest skopiowane dosłownie (`:57-64` / `:60-67`).
- Sześć przekierowań celuje w stare indeksy:
  - `LocalityController.php:79,109`,
  - `LocalityMergeController.php:27`,
  - `ParishController.php:83,114`,
  - `ParishMergeController.php:27`.
  `update` robi `back()`.
- Menu: dwa wpisy z osobnymi licznikami (`resources/js/components/app-sidebar.tsx:47-58`, `app/Http/Middleware/HandleInertiaRequests.php:57-58`).
- Rejestr paneli `PANEL_PROPS` ma już `locality` i `parish`. `withPanel` przy otwieraniu usuwa klucze pozostałych paneli, więc na stronie jest najwyżej jeden panel (`resources/js/lib/side-panel-url.ts:6-13,51-71`).
- `useUnsavedChangesGuard` porównuje gołe `id === openKey` (`resources/js/hooks/use-unsaved-changes-guard.ts:36`). Na wspólnej stronie miejscowość 3 i parafia 3 dałyby ten sam klucz.
- `ParishPanel` przyjmuje `localities: LocalityOption[]` i podaje je do `LocalityPicker` bez `onCreated` (`resources/js/components/parishes/parish-panel.tsx:23-30,144-151`). Picker ma już `onCreated` (`resources/js/components/localities/locality-picker.tsx:21,89`).
- `SegmentedToggle` obsługuje `count` i `disabled` (`resources/js/components/segmented-toggle.tsx:5-66`). Wzór przełącznika sterowanego adresem ma `/sources`: polskie wartości, domyślna wartość pominięta w adresie, normalizacja na serwerze, wizyta przez `router.get` z `only: ['filters']`. Pokazuje go `resources/js/pages/sources/index.tsx:150-190,419-460`, `lib/sources-index.ts:116-143`, `SourceController.php:379-389`.
- Wzór błędu wizyty i oczekiwania: `failureHandlers` (`resources/js/lib/visit-failure.ts`) i `isVisiting` + `busy` (`pages/sources/index.tsx:174-190,413`). Listy miejsc tego nie mają: nieudana wizyta przechodzi cicho.
- Stałe: `focusRing` (`resources/js/lib/focus-ring.ts:7`), `stickyHeadCell`/`stickyCell` z `group` na wierszu (`resources/js/lib/table-styles.ts`, wzór `components/sources/sources-table.tsx:85,125,133`).
- Testy, które dotykają starych adresów:
  - `tests/Feature/LocalitiesTest.php` (indeks, resolver, częściowe przeładowanie `:380-402`, przekierowania),
  - `tests/Feature/ParishesTest.php` (komponent `:29`, `has('localities', 1)` `:35`, przekierowania `:68,:83,:222,:253`),
  - `tests/Feature/OwnershipBoundariesTest.php:210,224,240`,
  - e2e `tests/e2e/places-merge-and-delete.spec.ts:28,48,61` i `tests/e2e/support/api.ts:447,457` (`pageProps` po adresie, podąża za przekierowaniem),
  - Vitest `side-panel-url.test.ts:23-33` (stare ścieżki tylko jako przykładowe napisy).
- Zrzuty „przed” już są: `context/changes/places-page/screenshots/before/01–08` + `shots.mjs`.

## Desired End State

- W menu jest jeden wpis „Miejsca” (MapPin) z licznikiem = miejscowości + parafie. Prowadzi do `/places`.
- `/places` pokazuje nagłówek „Miejsca”, przycisk „Dodaj miejscowość” albo „Dodaj parafię” (zależnie od zakładki), wyszukiwarkę, przełącznik „Miejscowości n | Parafie n” i tabelę wybranej zakładki. `?kind=parafie` wybiera Parafie, a brak parametru albo wartość nieznana oznacza Miejscowości.
- Wyszukiwanie filtruje oba spisy. Bez wyszukiwania liczniki pokazują wielkość spisów, a z wyszukiwaniem liczbę trafień w każdym. Gdy zakładka nie ma trafień, a druga ma, „Brak wyników” proponuje „Pokaż w parafiach (n)” albo „Pokaż w miejscowościach (n)”.
- Segment pustego spisu jest zawsze aktywny. Pusta zakładka pokazuje „Spis jest pusty” z przyciskiem dodania.
- `/localities?…` i `/parishes?…` (GET) przekierowują na `/places`, zachowując `q` i klucz panelu. `/parishes` dodaje `kind=parafie`. Zapis, usunięcie i scalenie wracają na `/places` z właściwą zakładką.
- Kolumna „Użycia” u parafii nazywa się „Źródła”. Link wiersza używa `focusRing`. Na 375 px pierwsza kolumna obu tabel jest przyklejona (`stickyCell`).
- Nieudana wizyta (wyszukiwanie, zmiana zakładki) pokazuje komunikat z „Spróbuj ponownie”. Wyszukiwarka dostaje `busy` w trakcie wizyty.
- Miejscowość dodana inline w panelu parafii od razu pojawia się w zakładce Miejscowości.
- Skan `view-tokens.test.ts` obejmuje pliki `/places`. `CLAUDE.md` to odnotowuje, a spec e2e pilnuje przejścia między zakładkami i przekierowań.

### Key Discoveries:

- Jeden panel naraz (`side-panel-url.ts:61-68`), więc wystarczy jeden strażnik. Klucz z prefiksem typu (`locality:3`) usuwa kolizję id bez zmiany hooka (`use-unsaved-changes-guard.ts:36` porównuje wartości, które poda strona).
- `Locality` jest nadzbiorem `LocalityOption`, więc pełna lista `localities` zastępuje opcje dla panelu parafii. Kolizja propsa `localities` znika.
- Przełączenie zakładki i wyszukiwanie to wizyty `only: ['filters']`, więc obie listy ładuje tylko pełne wejście. Liczniki obu segmentów i tak wymagają obu spisów.
- `useCurrentUrl` podświetla wpis menu po prefiksie ścieżki bez query (`resources/js/hooks/use-current-url.ts:31-35,62-66`), więc `/places?kind=parafie` podświetli „Miejsca”.
- `pageProps` w `tests/e2e/support/api.ts:337-352` robi `page.request.get`, a ten podąża za 302. Sprzątanie i tak przepinamy na `/places`, żeby nie zależało od przekierowania.

## What We're NOT Doing

- Wzory B (hierarchia) i C (mieszana tabela). Nie pokazujemy też parafii z siedzibą w panelu miejscowości.
- Nie zmieniamy wnętrza `locality-panel.tsx`, `parish-panel.tsx` ani dialogów scalania i usuwania. Jedyny wyjątek to opcjonalny prop `onLocalityCreated` w `ParishPanel` (przekazany do istniejącego `onCreated` pickera).
- Liczby w kolumnach zostają bez zmian (decyzja 3): zmienia się tylko nagłówek „Użycia” → „Źródła” u parafii.
- Kształt `navCounts` zostaje: klucze `localities` i `parishes` zostają, a sumę liczy pasek boczny.
- Nie wnioskujemy zakładki z klucza panelu: `/places?parish=3` bez `kind` otwiera panel parafii nad zakładką Miejscowości. Przekierowanie z `/parishes` zawsze dodaje `kind=parafie`.
- Zmiana zakładki nie zamyka otwartego panelu (jak zmiana `kind` na `/sources`), więc nie potrzebuje strażnika.
- Po zapisie (`store`), usunięciu i scaleniu zakładkę wybiera typ rekordu, a nie zakładka, na której był autor. Usunięcie parafii z panelu otwartego nad Miejscowościami ląduje więc na Parafiach. `q` nie przetrwa tych przekierowań (jak dziś). `update` robi `back()` i zostaje na miejscu.
- Wyszukiwanie bez znaków diakrytycznych („lodz” → „Łódź”) zostaje poza zakresem.
- Nie robimy osobnego współdzielonego hooka listy dla `/persons`, `/sources` i `/places`: scalenie samo usuwa kopię.
- Bez `toHaveScreenshot` i bez nowych zależności.
- Nie usuwamy tras `localities.index` i `parishes.index`: zostają jako przekierowania pod tymi samymi nazwami.

## Implementation Approach

Kolejność: backend i szkielet strony (żeby `/places` działało pionowo) → stałe i tabele → stany → utrwalenie.
Strona `pages/places/index.tsx` trzyma jeden stan wyszukiwania i jedną wizytę (`withPlacesQuery`), wzorem `/sources`. Wywołuje `useSidePanelNavigation` dla `locality` i `parish` oraz jeden strażnik z kluczem z prefiksem. Tabele i stany puste zostają per typ (kolumny się nie pokrywają). Wspólne stany puste trafiają do `components/places/places-empty-state.tsx`.
Logika licznika i podpowiedzi drugiej zakładki żyje w czystych funkcjach w `lib/places-index.ts` pod Vitestem. Strona tylko je wywołuje.

## Critical Implementation Details

**Przeniesienie testów.** Funkcje `buildLocalitiesQuery`/`withLocalitiesSearch` i `buildParishesQuery`/`withParishesSearch` znikają na rzecz `withPlacesQuery`. Ich przypadki w `localities-index.test.ts` i `parishes-index.test.ts` przenosimy do `places-index.test.ts` (te same przypadki, ścieżka `/places`).
Testy indeksu, resolvera paneli i częściowego przeładowania przenosimy z `LocalitiesTest.php`/`ParishesTest.php` do `PlacesTest.php`. Testy filtrów i sortowania zostają tam, gdzie są.
Zatwierdzenie tego planu jest zgodą na to przeniesienie (CLAUDE.md: „Do not delete tests without approval”). Żaden przypadek nie znika bez odpowiednika.

**Wizyta z żywego adresu.** Zmiana zakładki, wyszukiwanie i „Pokaż w …” budują adres z `window.location` (nie z kopii z renderu), tak jak dziś. Dzięki temu zachowują klucz otwartego panelu (`context/archive/2026-10-05-places-gazetteer/reviews/impl-review.md:33-50`).
Zmiana zakładki robi `push` historii, a wyszukiwanie `replace` (`context/archive/2026-09-30-sources-list-views/plan.md:134`). Czekające wyszukiwanie (debounce) wchodzi w tę samą wizytę co zmiana zakładki, jak `openFromList` dziś.

**Liczniki z żywego wyszukiwania.** Liczniki segmentów i „Pokaż w …” liczymy z `trimmedSearch` (to, co filtruje widoczną tabelę), a nie z `filters.q`. Tekst `role="status"` zostaje przy `filters.q`, żeby czytnik ekranu nie mówił przy każdym znaku.

## Faza 1: Strona /places i przekierowania

### Overview

Jedna działająca strona `/places` z przełącznikiem, wspólnym wyszukiwaniem i oboma panelami; stare trasy przekierowują; menu ma jeden wpis. Tabele i stany puste na razie bez zmian wyglądu (liczniki = wielkość spisów).

### Changes Required:

#### 1. Kontroler i trasa

**File**: `app/Http/Controllers/PlaceController.php` (nowy, `vendor/bin/sail artisan make:controller PlaceController --no-interaction`), `routes/web.php`

**Intent**: Jeden indeks renderuje oba spisy i oba resolvery paneli; kod przenosimy z `LocalityController::index` i `ParishController::index` razem z jedną kopią `normalizeSearchQuery`.

**Contract**: `GET /places` → `PlaceController@index`, nazwa `places.index`, w grupie `auth` obok tras miejscowości. `Inertia::render('places/index', [...])` z propsami:
- `localities` (closure, `LocalityResource`, `withCount` jak dziś),
- `parishes` (closure, `ParishResource` z `locality` i `sources_count`),
- `religions`,
- `filters: { q: string, kind: 'miejscowosci'|'parafie' }`,
- `localityPanel`, `parishPanel` (closures, kształt i `Gate::authorize('view')` jak dziś).

Prywatne `normalizeKind(mixed): string` zwraca `'parafie'` dla dokładnie `'parafie'`, w każdym innym przypadku `'miejscowosci'` (wzór `SourceController.php:379-389`). Prop `localities` jako opcje znika. Wayfinder: `vendor/bin/sail artisan wayfinder:generate`.

#### 2. Przekierowania ze starych tras

**File**: `app/Http/Controllers/LocalityController.php`, `ParishController.php`, `LocalityMergeController.php`, `ParishMergeController.php`

**Intent**: Stare adresy i zakładki w przeglądarce dalej działają. Każdy zapis wraca na `/places` z właściwą zakładką.

**Contract**:
- `LocalityController::index(Request): RedirectResponse` → `to_route('places.index', <q, locality z query, jeśli są>)`.
- `ParishController::index` → to samo + `kind=parafie` i `parish`. Puste lub nietekstowe parametry pomijamy.
- Kontrolery tracą `normalizeSearchQuery` i `Inertia::render`.
- Sześć przekierowań po zapisie:
  - `localities.index` → `places.index` (bez `kind`),
  - `parishes.index` → `places.index` z `kind=parafie`,
  - klucze `locality`/`parish` zostają.

#### 3. Strona i logika adresu

**File**: `resources/js/pages/places/index.tsx` (nowy), `resources/js/lib/places-index.ts` (nowy), `resources/js/lib/localities-index.ts`, `resources/js/lib/parishes-index.ts`, `resources/js/types/*` (typ propsów, jeśli trzeba)

**Intent**: Jedna strona zastępuje dwie. Logika wyszukiwania, wizyty i otwierania z listy jest jedna, a tabela i panel zależą od zakładki.

**Contract**:
- `lib/places-index.ts`:
  - `type PlacesKind = 'miejscowosci' | 'parafie'`,
  - `withPlacesQuery(url: string, filters: { q?: string; kind?: PlacesKind }): string` podmienia tylko `q` i `kind`, a pustą wartość i domyślną zakładkę usuwa; klucze paneli i hash zostają.
- `lib/{localities,parishes}-index.ts` zachowują tylko `filter*`/`sort*`.
- Strona:
  - `Head`, `Heading variant="page"` i breadcrumb „Miejsca”, opis „Spis miejscowości i parafii Twojego drzewa”;
  - `SegmentedToggle<PlacesKind>` z `ariaLabel="Spis"`, pozycje „Miejscowości” i „Parafie” z `count`; renderowany zawsze, także przy obu pustych spisach, pod nagłówkiem i poza warunkiem wyszukiwarki (inaczej niż na `/sources`, gdzie siedzi w wierszu `role="search"`), żeby „Dodaj parafię” było osiągalne w pustym drzewie;
  - wyszukiwarka `role="search"` widoczna, gdy którykolwiek spis jest niepusty; `ariaLabel="Szukaj miejscowości i parafii"`, placeholder „Szukaj po nazwie”;
  - dwa `useSidePanelNavigation` (`locality`, `parish`) i jeden `useUnsavedChangesGuard`.
- `openKey` to `` `locality:${id|'new'}` `` / `` `parish:${id|'new'}` `` albo `null`. `open` strażnika rozdziela klucz po prefiksie.
- `addButton` i tabela zależą od `filters.kind`. `ParishPanel` dostaje pełne `localities`.
- Usuwamy `resources/js/pages/localities/index.tsx` i `resources/js/pages/parishes/index.tsx`.

#### 4. Menu

**File**: `resources/js/components/app-sidebar.tsx`

**Intent**: Jeden wpis zamiast dwóch (C1).

**Contract**: Pozycja `{ title: 'Miejsca', href: placesIndex(), icon: MapPin, count: (navCounts?.localities ?? 0) + (navCounts?.parishes ?? 0) }` w miejscu „Miejscowości”, gdy `navCounts` istnieje (inaczej `undefined`). Wpis „Parafie” i importy `routes/{localities,parishes}` znikają.

#### 5. Testy

**File**: `tests/Feature/PlacesTest.php` (nowy, `vendor/bin/sail artisan make:test --pest PlacesTest`), `tests/Feature/LocalitiesTest.php`, `tests/Feature/ParishesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `resources/js/lib/places-index.test.ts` (nowy), `resources/js/lib/{localities,parishes}-index.test.ts`, `tests/e2e/places-merge-and-delete.spec.ts`, `tests/e2e/support/api.ts`

**Intent**: Każdy nowy endpoint i przekierowanie pod testem. Przypadki starych indeksów przechodzą na `/places` (patrz *Przeniesienie testów*).

**Contract**:
- `PlacesTest`:
  - gość → login;
  - lista własnych miejscowości i parafii alfabetycznie, bez cudzych;
  - normalizacja `q` (trim, 100 znaków, nietekstowe → '') i `kind` (`parafie`, inne → `miejscowosci`);
  - resolvery obu paneli (null, `new`, własne id, brak → 404);
  - cudze `?locality=` i `?parish=` → 404;
  - częściowe przeładowanie `filters` bez zapytań o spisy (`X-Inertia-Partial-Component: places/index`);
  - `GET /localities?locality=3&q=x` → `/places?locality=3&q=x`; `GET /parishes?parish=5` → `/places?kind=parafie&parish=5`.
- `LocalitiesTest`/`ParishesTest`: asercje przekierowań na `places.index` (z `kind=parafie` dla parafii). Testy `update` (`LocalitiesTest.php:61-63`, `ParishesTest.php:81-83`) przechodzą na `from(route('places.index', [...]))` (u parafii z `kind=parafie`), bo `back()` wraca już tylko na `/places`.
- Przekierowanie po scaleniu miejscowości ma tylko `locality` (bez `kind`), a w przekierowaniach parafii klucz panelu stoi po `kind`. `places-merge-and-delete.spec.ts:48` sprawdza `locality=${keptId}$`, więc za kluczem panelu nie może nic stać.
- `OwnershipBoundariesTest`: zestaw tras `localities.index` → `places.index`, 404 przez `places.index`.
- Vitest: `withPlacesQuery` (zachowanie klucza panelu, usunięcie pustego `q` i domyślnego `kind`).
- e2e: `/places?locality=…` i `/places?kind=parafie&parish=…`; `pageProps(page, '/places')` czyta oba spisy jednym żądaniem.

### Success Criteria:

#### Automated Verification:

- Testy Pest przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PlacesTest.php tests/Feature/LocalitiesTest.php tests/Feature/ParishesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/AppShellTest.php`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Typy i lint frontu przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check`
- Pint i Larastan czyste: `vendor/bin/sail bin pint --dirty --format agent` i `vendor/bin/sail composer types:check`
- Spec scalania przechodzi na nowym adresie: `npx playwright test tests/e2e/places-merge-and-delete.spec.ts`

#### Manual Verification:

- Menu ma jeden wpis „Miejsca” z sumą; `/localities?locality=<id>` i `/parishes?parish=<id>&q=<x>` lądują na `/places` z otwartym panelem, właściwą zakładką i wypełnioną wyszukiwarką
- Przełączenie zakładki zmienia tabelę, przycisk „Dodaj …” i adres (`?kind=parafie`), a „Wstecz” wraca do poprzedniej zakładki
- Przy niezapisanych zmianach w panelu miejscowości kliknięcie parafii pyta o porzucenie zmian, a kliknięcie otwartego rekordu nie robi nic

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych przez człowieka.

---

## Faza 2: Tabele i stałe

### Overview

Tabele używają stałych repo zamiast literałów (C3), mają przyklejoną pierwszą kolumnę na telefonie (C5) i jednoznaczną kolumnę liczby (C4). Stany puste są wspólne dla obu typów.

### Changes Required:

#### 1. Tabele

**File**: `resources/js/components/localities/localities-table.tsx`, `resources/js/components/parishes/parishes-table.tsx`

**Intent**: Usuwamy ręczny pierścień (C3) i ucinanie kolumn na 375 px (C5). Kolumna liczby parafii zmienia nazwę, żeby nie udawała „Użyć” miejscowości (C4).

**Contract**:
- Link nazwy: `cn(focusRing, '-m-1 block rounded-md p-1 font-medium', …)` zamiast literału `focus-visible:ring-[3px]`.
- Nagłówek pierwszej kolumny: `cn(stickyHeadCell, 'px-4')`.
- Komórka: `cn(stickyCell, 'px-4 py-3')`, a `TableRow` z `className="group"` (wzór `sources-table.tsx:85,125,133`).
- `parishes-table.tsx`: nagłówek „Użycia” → „Źródła”.
- Caption: „Miejscowości w drzewie…” i „Parafie w drzewie…” zostają.

#### 2. Wspólne stany puste

**File**: `resources/js/components/places/places-empty-state.tsx` (nowy). Usuwamy `resources/js/components/localities/localities-empty-state.tsx` i `resources/js/components/parishes/parishes-empty-state.tsx`.

**Intent**: Jeden komponent z tekstami per typ zamiast dwóch kopii różniących się ikoną i dwoma napisami (C2).

**Contract**: `PlacesEmptyTree({ kind, action })` (ikona MapPin/Church, „Spis jest pusty”, opis per typ jak dziś) i `PlacesNoResults({ kind, query, onClear, other? })`. `other` to opcjonalne `{ label: string; count: number; onShow: () => void }` dla fazy 3: w fazie 2 nieużywane. Oba używają `EmptyState`.

### Success Criteria:

#### Automated Verification:

- Vitest, typy i lint przechodzą: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check`
- W plikach widoku nie ma literału pierścienia: `grep -rn "ring-\[3px\]" resources/js/components/localities/localities-table.tsx resources/js/components/parishes/parishes-table.tsx resources/js/pages/places` nic nie zwraca

#### Manual Verification:

- Na 375 px w obu zakładkach nazwa zostaje widoczna przy przewijaniu tabeli w bok, a otwarty wiersz ma to samo tło w przyklejonej komórce
- Tab z klawiatury pokazuje pierścień 3 px na linku wiersza (jak przed zmianą)
- Zakładka Parafie ma kolumnę „Źródła”, Miejscowości „Użycia”

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych przez człowieka.

---

## Faza 3: Stany — liczniki trafień, przejście, pusty, błąd, ładowanie

### Overview

Wyszukiwanie mówi, w którym spisie są trafienia. Pusta zakładka pozwala dodać pierwszy rekord. Nieudana wizyta nie przechodzi cicho. Lista miejscowości nie jest nieświeża po dodaniu inline.

### Changes Required:

#### 1. Liczniki i podpowiedź drugiej zakładki

**File**: `resources/js/lib/places-index.ts`, `resources/js/lib/places-index.test.ts`, `resources/js/pages/places/index.tsx`

**Intent**: Szukając „Tarnów”, autor widzi trafienia w obu spisach i jednym kliknięciem przechodzi do drugiego (sedno C1).

**Contract**:
- `placesCounts(localities, parishes, q): { miejscowosci: number; parafie: number }` → wielkości spisów dla pustego `q`, liczba trafień `filter*` dla niepustego.
- `otherKindHint(kind, counts, q): { kind: PlacesKind; count: number } | null` → druga zakładka, gdy `q` niepuste, bieżąca ma 0, a druga ≥ 1; inaczej `null`.
- Strona podaje `counts` do `SegmentedToggle` (żaden segment nie jest `disabled`) i `other` do `PlacesNoResults`. Etykiety: „Pokaż w parafiach (n)” / „Pokaż w miejscowościach (n)”. Kliknięcie robi wizytę `{ kind, q: trimmedSearch }` (push).
- `role="status"`: „Znaleziono: X miejscowość/miejscowości, Y parafia/parafie/parafii” według `pluralPhrase`, albo „Brak wyników”, gdy oba są zerem. Tekst liczony z `filters.q`.

Przykład (4 miejscowości z jedną „Tarnów”, 2 parafie z jedną o siedzibie Tarnów):

| Wyszukiwanie | Liczniki |
|---|---|
| brak | „Miejscowości 4 \| Parafie 2” |
| „Tarnów” | „Miejscowości 1 \| Parafie 1” |
| „św. Marii” (pasuje tylko parafia) na Miejscowościach | „Miejscowości 0 \| Parafie 1”, „Brak wyników” z „Pokaż w parafiach (1)” |

#### 2. Pusta zakładka

**File**: `resources/js/pages/places/index.tsx`

**Intent**: Pusty spis nie blokuje dodania pierwszego rekordu. To świadome odstępstwo od wyłączonego segmentu z `/sources`, nie przeoczenie.

**Contract**: Gdy spis bieżącej zakładki jest pusty, strona pokazuje `PlacesEmptyTree` z tym samym `addButton` co w nagłówku. Ma to pierwszeństwo przed „Brak wyników”. Komentarz w kodzie przy `SegmentedToggle` odnotowuje, że segmenty nigdy nie są wyłączane.

#### 3. Błąd wizyty i oczekiwanie

**File**: `resources/js/pages/places/index.tsx`

**Intent**: Nieudane wyszukiwanie albo zmiana zakładki pokazuje komunikat z ponowieniem, a wyszukiwarka sygnalizuje wizytę w toku.

**Contract**: Wzór `pages/sources/index.tsx:150-190,413`:
- `latestVisit` ref, `isVisiting`, `visitError` i `failureHandlers(VISIT_ERROR, retry, setVisitError, isCurrent)` z `lib/visit-failure.ts`;
- `SearchField busy={isVisiting} busyLabel="Wczytywanie"`;
- komunikat błędu wzorem `/sources` (`pages/sources/index.tsx:498-506`): `InputError role="alert"` i `Button` „Spróbuj ponownie”. Tekst własny, bo `/places` nie ma filtrów: `VISIT_ERROR = 'Nie udało się wczytać listy.'`.

#### 4. Odświeżenie miejscowości po dodaniu inline

**File**: `resources/js/components/parishes/parish-panel.tsx`, `resources/js/pages/places/index.tsx`

**Intent**: Miejscowość utworzona w pickerze siedziby jest od razu widoczna w zakładce Miejscowości i w liczniku menu, nawet gdy autor anuluje parafię.

**Contract**: `ParishPanel` dostaje opcjonalny `onLocalityCreated?: (locality: LocalityOption) => void`, przekazany do `LocalityPicker onCreated`. Strona odpowiada `router.reload({ only: ['localities', 'navCounts'] })`. Innych zmian w panelu nie ma.

### Success Criteria:

#### Automated Verification:

- Vitest dla `placesCounts` i `otherKindHint` (pusty `q`, trafienia w obu, zero w bieżącej i ≥1 w drugiej, zero w obu) przechodzi: `vendor/bin/sail npm run test:js`
- Typy i lint przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check`
- Pest bez regresji: `vendor/bin/sail artisan test --compact tests/Feature/PlacesTest.php tests/Feature/ParishesTest.php`

#### Manual Verification:

- „Tarnów” na zakładce Miejscowości pokazuje liczniki trafień obu spisów; fraza pasująca tylko do parafii daje „Pokaż w parafiach (n)”, który przełącza zakładkę z zachowaną frazą
- W drzewie bez parafii segment „Parafie 0” jest klikalny, a pusta zakładka ma „Dodaj parafię”
- Po zatrzymaniu serwera wyszukiwanie pokazuje komunikat z „Spróbuj ponownie”, a po wznowieniu ponowienie działa
- Miejscowość dodana w pickerze siedziby (bez zapisu parafii) jest widoczna w zakładce Miejscowości i w liczniku „Miejsca”

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych przez człowieka.

---

## Faza 4: Utrwal to i bramka wizualna

### Overview

Zabezpieczenie, żeby następny agent nie cofnął naprawy: skan tokenów obejmuje `/places`, reguła w `CLAUDE.md`, spec e2e na ryzyka przełącznika i przekierowań, zrzuty „po” z werdyktem.

### Changes Required:

#### 1. Skan tokenów

**File**: `resources/js/lib/view-tokens.test.ts`

**Intent**: Regresja literału (np. `ring-[3px]`, klasa palety) w widoku `/places` czerwieni `test:js`.

**Contract**: `VIEW_FILES` dostaje `pages/places/index.tsx`, `components/places/places-empty-state.tsx`, `components/localities/localities-table.tsx` i `components/parishes/parishes-table.tsx`. Komentarz nad listą: „/persons, /sources and /places”.

#### 2. Reguła

**File**: `CLAUDE.md`

**Intent**: Następny agent wie, że miejscowości i parafie mają jedną stronę, i nie dodaje osobnej listy ani wpisu w menu.

**Contract**: W „UI: tokens and shared components” linia „Controls” wymienia `/places`. W „Side panels vs modals” albo „Backend conventions” dochodzi jedno zdanie: miejscowości i parafie to jedna strona `/places` z przełącznikiem `?kind=` i jednym wpisem „Miejsca” w menu. Stare `/localities` i `/parishes` tylko przekierowują, a segmenty z licznikiem 0 nie są wyłączane (w odróżnieniu od `/sources`), bo pusta zakładka dodaje pierwszy rekord.

#### 3. Spec e2e

**File**: `tests/e2e/places-page.spec.ts` (nowy, przez `/10x-e2e`)

**Intent**: Ryzyka widoczne tylko w przeglądarce: stary link z panelem ląduje na właściwej zakładce, a wyszukiwanie przenosi do spisu, w którym są trafienia.

**Contract**: Dwa testy z unikalnym sufiksem i sprzątaniem przez `cleanupBySuffix`:
- (a) `/parishes?parish=<id>` → URL `/places` z `kind=parafie` i `parish=<id>`, dialog parafii widoczny, wiersz zaznaczony;
- (b) parafia o unikalnej nazwie; wyszukiwanie jej nazwy na zakładce Miejscowości → „Pokaż w parafiach (1)” → zakładka Parafie z `q` w adresie i link parafii widoczny.

Lokatory `getByRole`/`getByLabel`, bez `waitForTimeout`.

#### 4. Bramka wizualna

**File**: `context/changes/places-page/screenshots/shots.mjs`, `screenshots/after/NN-*.png`, `screenshots/verdict.md`

**Intent**: Dowód wizualny zamknięcia C1–C5 w 1440×900 i 375×812.

**Contract**: `shots.mjs` dostaje tryb „after” z `/places`:
- obie zakładki (desktop i mobile),
- panel otwarty z zaznaczonym wierszem,
- fokus na przycisku i wierszu (pomiar `box-shadow` po animacjach),
- liczniki trafień przy wyszukiwaniu i „Pokaż w parafiach”,
- pusta zakładka (jeśli dane E2E na to pozwalają; inaczej opis w werdykcie).

`verdict.md`: zarzut → zrzut przed/po → status.

### Success Criteria:

#### Automated Verification:

- Skan tokenów obejmuje `/places` i przechodzi: `vendor/bin/sail npm run test:js`
- Nowy spec przechodzi: `npx playwright test tests/e2e/places-page.spec.ts`
- Spec scalania przechodzi: `npx playwright test tests/e2e/places-merge-and-delete.spec.ts`
- Pełny CI lokalnie: `vendor/bin/sail composer ci:check`

#### Manual Verification:

- Zrzuty „po” w `screenshots/after/` i `verdict.md` potwierdzają zamknięcie C1–C5 na desktopie i 375 px
- Celowo wprowadzony `ring-[3px]` w `places/index.tsx` czerwieni `test:js` (sprawdzenie i cofnięcie)

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych przez człowieka.

---

## Testing Strategy

### Unit Tests:

- `places-index.test.ts`: `withPlacesQuery` (zachowuje klucze paneli i hash, usuwa pusty `q` i `kind=miejscowosci`, ustawia `kind=parafie`); `placesCounts` i `otherKindHint` na przypadkach z fazy 3.
- `localities-index.test.ts`, `parishes-index.test.ts`: filtr i sortowanie bez zmian.
- `view-tokens.test.ts`: pliki `/places`.

### Integration Tests:

- `PlacesTest.php`: indeks, normalizacja, resolvery, 404 cudzych rekordów, częściowe przeładowanie bez zapytań, przekierowania GET starych tras.
- `LocalitiesTest.php`, `ParishesTest.php`: przekierowania zapisu, usunięcia i scalenia na `places.index`.
- `OwnershipBoundariesTest.php`: `places.index` w zestawie tras i 404 dla cudzego `?locality=`/`?parish=`.
- e2e: `places-merge-and-delete.spec.ts` (przepięty), `places-page.spec.ts` (nowy).

### Manual Testing Steps:

1. Wejdź z menu w „Miejsca”, przełącz zakładki, użyj „Wstecz”.
2. Wpisz nazwę pasującą tylko do parafii na zakładce Miejscowości i użyj „Pokaż w parafiach”.
3. Otwórz stary adres `/parishes?parish=<id>` z zakładki przeglądarki.
4. Zmień formularz miejscowości i kliknij parafię o dowolnym id: pojawia się pytanie o porzucenie zmian.
5. Na 375 px przewiń tabelę Parafii w bok.

## Performance Considerations

Pełne wejście na `/places` liczy oba spisy naraz (dziś osobno). Miejscowości to 1 zapytanie z 4 podzapytaniami liczącymi, a parafie to 1 zapytanie + eager `locality` + liczenie. Zakładki i wyszukiwanie przeładowują tylko `filters`, a odświeżenie po dodaniu inline tylko `localities` i `navCounts`. Opcje miejscowości dla panelu parafii znikają (o jedno zapytanie mniej).

## Migration Notes

Bez migracji danych. Stare adresy działają przez 302. Wayfinder po dodaniu trasy: `vendor/bin/sail artisan wayfinder:generate`.

## References

- Research: `context/changes/places-page/research.md`
- Zgłoszenie i przed-audyt: `context/changes/places-page/change.md`
- Wzór przełącznika, stanów i bramki: `context/archive/2026-10-12-sources-page/plan.md`, `resources/js/pages/sources/index.tsx:150-190,413-460`
- Wzór lepkiej kolumny: `resources/js/components/sources/sources-table.tsx:85,125,133`
- Strażnik: `resources/js/hooks/use-unsaved-changes-guard.ts:36`
- Rejestr paneli: `resources/js/lib/side-panel-url.ts:6-13,51-71`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Strona /places i przekierowania

#### Automated

- [x] 1.1 Testy Pest przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PlacesTest.php tests/Feature/LocalitiesTest.php tests/Feature/ParishesTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/AppShellTest.php` — 3a6e9ba
- [x] 1.2 Vitest przechodzi: `vendor/bin/sail npm run test:js` — 3a6e9ba
- [x] 1.3 Typy i lint frontu przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check` — 3a6e9ba
- [x] 1.4 Pint i Larastan czyste: `vendor/bin/sail bin pint --dirty --format agent` i `vendor/bin/sail composer types:check` — 3a6e9ba
- [x] 1.5 Spec scalania przechodzi na nowym adresie: `npx playwright test tests/e2e/places-merge-and-delete.spec.ts` — 3a6e9ba

#### Manual

- [x] 1.6 Menu ma jeden wpis „Miejsca” z sumą; `/localities?locality=<id>` i `/parishes?parish=<id>&q=<x>` lądują na `/places` z otwartym panelem, właściwą zakładką i wypełnioną wyszukiwarką — 3a6e9ba
- [x] 1.7 Przełączenie zakładki zmienia tabelę, przycisk „Dodaj …” i adres (`?kind=parafie`), a „Wstecz” wraca do poprzedniej zakładki — 3a6e9ba
- [x] 1.8 Przy niezapisanych zmianach w panelu miejscowości kliknięcie parafii pyta o porzucenie zmian, a kliknięcie otwartego rekordu nie robi nic — 3a6e9ba

### Phase 2: Tabele i stałe

#### Automated

- [x] 2.1 Vitest, typy i lint przechodzą: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check` — 05b09ef
- [x] 2.2 W plikach widoku nie ma literału pierścienia: `grep -rn "ring-\[3px\]" resources/js/components/localities/localities-table.tsx resources/js/components/parishes/parishes-table.tsx resources/js/pages/places` nic nie zwraca — 05b09ef

#### Manual

- [x] 2.3 Na 375 px w obu zakładkach nazwa zostaje widoczna przy przewijaniu tabeli w bok, a otwarty wiersz ma to samo tło w przyklejonej komórce — 05b09ef
- [x] 2.4 Tab z klawiatury pokazuje pierścień 3 px na linku wiersza (jak przed zmianą) — 05b09ef
- [x] 2.5 Zakładka Parafie ma kolumnę „Źródła”, Miejscowości „Użycia” — 05b09ef

### Phase 3: Stany — liczniki trafień, przejście, pusty, błąd, ładowanie

#### Automated

- [x] 3.1 Vitest dla `placesCounts` i `otherKindHint` (pusty `q`, trafienia w obu, zero w bieżącej i ≥1 w drugiej, zero w obu) przechodzi: `vendor/bin/sail npm run test:js` — 4b85fa9
- [x] 3.2 Typy i lint przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check` — 4b85fa9
- [x] 3.3 Pest bez regresji: `vendor/bin/sail artisan test --compact tests/Feature/PlacesTest.php tests/Feature/ParishesTest.php` — 4b85fa9

#### Manual

- [x] 3.4 „Tarnów” na zakładce Miejscowości pokazuje liczniki trafień obu spisów; fraza pasująca tylko do parafii daje „Pokaż w parafiach (n)”, który przełącza zakładkę z zachowaną frazą — 4b85fa9
- [x] 3.5 W drzewie bez parafii segment „Parafie 0” jest klikalny, a pusta zakładka ma „Dodaj parafię” — 4b85fa9
- [x] 3.6 Po zatrzymaniu serwera wyszukiwanie pokazuje komunikat z „Spróbuj ponownie”, a po wznowieniu ponowienie działa — 4b85fa9
- [x] 3.7 Miejscowość dodana w pickerze siedziby (bez zapisu parafii) jest widoczna w zakładce Miejscowości i w liczniku „Miejsca” — 4b85fa9

### Phase 4: Utrwal to i bramka wizualna

#### Automated

- [x] 4.1 Skan tokenów obejmuje `/places` i przechodzi: `vendor/bin/sail npm run test:js` — 1001ee1
- [x] 4.2 Nowy spec przechodzi: `npx playwright test tests/e2e/places-page.spec.ts`
- [x] 4.3 Spec scalania przechodzi: `npx playwright test tests/e2e/places-merge-and-delete.spec.ts` — 1001ee1
- [x] 4.4 Pełny CI lokalnie: `vendor/bin/sail composer ci:check` — 1001ee1

#### Manual

- [x] 4.5 Zrzuty „po” w `screenshots/after/` i `verdict.md` potwierdzają zamknięcie C1–C5 na desktopie i 375 px
- [x] 4.6 Celowo wprowadzony `ring-[3px]` w `places/index.tsx` czerwieni `test:js` (sprawdzenie i cofnięcie)
