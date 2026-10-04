# Wspólna powłoka panelu bocznego (F-02) Implementation Plan

## Overview

Jedna powłoka panelu wysuwanego z prawej, bez nakładki: otwarty panel zwęża treść strony obok siebie, ma wariant standardowy i szeroki (źródło), zamyka się do tego samego miejsca, a otwarcie innego rekordu podmienia zawartość. Do tego wspólny pomocnik adresu „klucz na typ” (`?person=`, później `?source=`, `?place=`, `?marriage=`), żeby wycinki S-01, S-02, S-06 i S-12 podłączały do powłoki tylko własną treść i własny resolver danych. F-02 nie przenosi żadnego rekordu — powłokę sprawdza test Vitest pomocnika, test Pest i strona demonstracyjna dostępna tylko w środowisku `local`.

## Current State Analysis

- Rekord otwierany „nad stroną” to dziś tylko podgląd osoby: modal sterowany `?person=`. Współdzielona właściwość `personPreview` jest niepusta tylko przy tym parametrze (`app/Http/Middleware/HandleInertiaRequests.php:54-58`), cudzy/nieistniejący id to 404 (`:71-91`). Otwarcie to częściowa wizyta `only: ['personPreview']` z `replace`, `preserveState`, `preserveScroll` (`resources/js/components/persons/person-preview-provider.tsx:22-33,66-68`), zamknięcie to `router.replace` po stronie klienta bez żądania (`:85-92`). Mutacje wracają `back()` na adres z parametrem, więc otwarty rekord dostaje świeże dane bez okablowania (`context/archive/2026-09-28-person-preview-in-modal/plan.md:33`).
- Pozycja drzewa siedzi w URL (`tree/{person}` + `?spouse=`); dodanie/usunięcie samego parametru panelu z `preserveScroll`/`preserveState` nie zmienia centrum ani pary.
- Layout nie ma miejsca na prawy panel: `AppShell` (`SidebarProvider`, `flex min-h-svh w-full`, `resources/js/components/ui/sidebar.tsx:139`) ma dwoje dzieci — `AppSidebar` i `AppContent` (`<main>`, `flex-1`) (`resources/js/layouts/app/app-sidebar-layout.tsx:13-21`). `AppLayout` jest trwałym layoutem domyślnym (`resources/js/app.tsx:13-22`) i hostuje provider + dialog podglądu (`resources/js/layouts/app-layout.tsx:14-19`). Przewija się dokument, nie `<main>`.
- Prymitywy nie pasują: `ui/sheet.tsx` to Radix Dialog z nakładką `fixed inset-0` (CLAUDE.md zabrania); drugi `SidebarProvider` przesłoniłby kontekst lewego menu, a skrót Ctrl/Cmd+B i cookie `sidebar_state` są globalne (`ui/sidebar.tsx:26-31,94-108`).
- Budżet szerokości przy 1366 px: treść ok. 1070 px przy rozwiniętym lewym menu (arytmetyka z badań, niezmierzone).
- Testy: Vitest obejmuje tylko `resources/js/**/*.test.ts`, bez jsdom (`vite.config.ts:42-44`); Playwright ma `auth.setup.ts` i `seed.spec.ts`; F-01 jeszcze nie istnieje.

## Desired End State

- W `AppSidebarLayout` jest slot panelu jako trzeci element wiersza flex obok `<main>`; dowolny komponent (na poziomie layoutu lub strony) renderuje `<SidePanel>` i pojawia się on w slocie, a treść strony zwęża się obok niego — bez nakładki, bez zmiany `ui/sidebar.tsx`.
- `SidePanel` ma wariant `standard` (`clamp(24rem, 30vw, 36rem)` ≈ 410 px przy 1366 / 576 px przy 2560) i `wide` (`clamp(40rem, 55vw, 72rem)` ≈ 750 / 1152 px); poniżej `md` (768 px) zajmuje cały ekran bez przyciemnienia. Jest przyklejony na wysokość okna, ma własne przewijanie treści, nagłówek z tytułem i przyciskiem „Zamknij panel”.
- Zamknięcie (X, Escape w panelu) przechodzi przez `onRequestClose` konsumenta, więc strażnik niezapisanych zmian może je wstrzymać. Escape w zagnieżdżonym modalu zamyka tylko modal.
- `lib/side-panel-url.ts` + `useSidePanelNavigation(key)` dają `hrefFor`, `open`, `close`; w adresie jest najwyżej jeden klucz panelu naraz, inne parametry (np. `?spouse=`, sortowanie) i ścieżka zostają nietknięte.
- Strona `/dev/side-panel` (200 tylko przy `APP_ENV=local`, inaczej 404) pokazuje podmianę rekordu, oba warianty, zagnieżdżony dialog i strażnik zmian.

Weryfikacja: testy Vitest i Pest zielone, `npm run check`/`types:check`/`build` i Larastan przechodzą, ręczne sprawdzenie na stronie demo przy 1366, 2560 i < 768 px.

### Key Discoveries:

- `person-preview-provider.tsx:39-49` (`withPerson`) i `:85-92` (`close`) — wzorzec do uogólnienia w pomocniku; nie zmieniamy go w F-02.
- Radix `DismissableLayer` nasłuchuje Escape na `document` w fazie capture i przy zamknięciu woła `event.preventDefault()` (`node_modules/@radix-ui/react-dismissable-layer/dist/index.mjs:91-106`) — panel z natywnym nasłuchem na własnym elemencie i kontrolą `defaultPrevented` nie zamknie się razem z zagnieżdżonym modalem.
- `ui/sidebar.tsx:302-313` — `SidebarInset` w wariancie `inset` ma `m-2 ml-0 rounded-xl shadow-sm`; panel na desktopie ma wyglądać jak sąsiednia karta tego samego stylu.
- `resources/js/components/form-dialog.tsx:14-29` — dzisiejszy formularz jest pełnoekranowy poniżej `sm`; panel pełnoekranowy poniżej `md` zachowuje to doświadczenie mobilne.
- `phpunit.xml:24` — testy działają z `APP_ENV=testing`, więc trasa demo w testach domyślnie zwraca 404; tryb `local` włącza się w teście przez `app()->detectEnvironment(fn () => 'local')`.

## What We're NOT Doing

- Nie przenosimy podglądu osoby, edycji źródła ani niczego innego do panelu — to S-06, S-02, S-01, S-12. `PersonPreviewProvider`/`PersonPreviewDialog` zostają bez zmian (dalej modal z własnymi opcjami wizyty).
- Nie zmieniamy formatu `?person=` ani przekierowań `PersonController` — klucz na typ zostawia je tak, jak są.
- Nie przecentrowujemy drzewa po zwężeniu (`use-centre-in-scroll.ts:63-64`) — zostaje dla S-05/S-06.
- Nie zwijamy automatycznie lewego menu i nie dodajemy przeciąganego brzegu.
- Bez animacji zamknięcia (panel znika od razu, gdy właściwość danych staje się `null`); jest tylko wysunięcie przy otwarciu.
- Bez nowych zależności (jsdom, Testing Library) i bez testu E2E — pierwszy test E2E panelu powstaje z pierwszym konsumentem.
- Brak wpisu strony demo w menu nawigacji.

## Implementation Approach

Powłoka renderuje się przez portal do slotu w layoucie: slot to element `display: contents` po `AppContent`, więc portalowany panel staje się bezpośrednim dzieckiem wiersza flex `SidebarProvider` — `<main>` (`flex-1 min-w-0`) zwęża się sam. Portal pozwala renderować panel z dowolnego miejsca drzewa React (konsumenci layoutu jak dziś `PersonPreviewDialog`, albo strona jak demo), a i tak trafia w jedno miejsce DOM. Stan otwarcia trzymamy w URL według istniejącego wzorca „parametr → właściwość → częściowe przeładowanie”; pomocnik uogólnia `withPerson` i opcje wizyty, a rejestr klucz → właściwość pozwala przy otwarciu jednego panelu wyczyścić klucze i dane pozostałych.

## Critical Implementation Details

**Partial reload przy podmianie typu panelu.** Otwarcie panelu typu X przy otwartym Y musi przeładować wszystkie właściwości paneli z rejestru (`only` = wszystkie wartości rejestru), nie tylko właściwość X — inaczej dane Y zostaną niepuste po usunięciu jego klucza z adresu i oba panele wyrenderują się naraz. Resolver każdej właściwości zwraca `null`, gdy jego klucza nie ma w zapytaniu, więc dodatkowe właściwości są tanie.

**Escape i zagnieżdżone modale.** Nasłuch Escape ma być natywnym `addEventListener('keydown')` na elemencie panelu (ref), nie Reactowym `onKeyDown`: zdarzenia syntetyczne z portalowanego modala Radix przechodzą przez drzewo React do panelu, natywne — nie. Dodatkowo ignoruj zdarzenie z `defaultPrevented === true`.

**Fokus i StrictMode.** `strictMode: true` (`app.tsx:23`) uruchamia efekty dwukrotnie w dev — element, który miał fokus przed otwarciem, zapamiętaj tylko przy pierwszym uruchomieniu (ref pusty), inaczej drugi przebieg zapamięta już nagłówek panelu.

**Warstwy na wąskim ekranie.** Panel pełnoekranowy poniżej `md` ma `z-40`, poniżej `z-50` dialogów Radix (`ui/dialog.tsx`), żeby potwierdzenia i pickery otwierane z panelu były nad nim.

**Rola dostępności.** Panel to `role="dialog"` z `aria-modal="false"`, nazwany tytułem (`aria-labelledby`). Testy F-01 piszą lokatory rolami i nazwami (`roadmap.md:101`); ta sama rola i nazwa co dzisiejszy modal pozwala im przetrwać przejście modal → panel.

## Phase 1: Pomocnik URL i nawigacja panelu

### Overview

Czysta logika adresu panelu (testowana w Vitest) i hook nawigacji, z których korzystają powłoka-konsumenci. Bez zmian w UI.

### Changes Required:

#### 1. Pomocnik adresu panelu

**File**: `resources/js/lib/side-panel-url.ts`

**Intent**: Uogólnić `withPerson` z `person-preview-provider.tsx:39-49`: ustawić lub usunąć klucz panelu w bieżącym adresie, usuwając przy tym klucze wszystkich innych paneli, i dostarczyć wspólne opcje wizyty otwierającej panel. Funkcja ma być czysta (bez `window`), żeby testował ją Vitest.

**Contract**:
- `PANEL_PROPS` — rejestr `as const` klucz zapytania → nazwa właściwości Inertii; na start `{ person: 'personPreview', demo: 'demoPanel' }`. Komentarz w pliku: każdy wycinek panelu (S-01, S-02, S-12) dopisuje tu swój wpis razem z resolverem po stronie serwera.
- `type PanelKey = keyof typeof PANEL_PROPS`.
- `withPanel(url: string, key: PanelKey, id: number | string | null): string` — zwraca `pathname + search + hash`; przy `id !== null` ustawia `key` i usuwa pozostałe klucze rejestru, przy `null` usuwa tylko `key`. Parametry spoza rejestru, ścieżka (w tym segment `tree/{person}`) i hash zostają. Rozbiór względem stałej bazy (np. `http://localhost`), bo wejściem jest względny `page.url`.
- `panelVisitOptions: { replace: true; preserveState: true; preserveScroll: true; only: string[] }` — `only` zawiera wszystkie wartości `PANEL_PROPS` (zob. Critical Implementation Details).

#### 2. Testy pomocnika

**File**: `resources/js/lib/side-panel-url.test.ts`

**Intent**: Zabezpieczyć niezmienniki adresu, na których opierają się cztery przyszłe panele.

**Contract**: Przypadki: otwarcie na adresie bez zapytania; podmiana id tego samego klucza; otwarcie `demo` przy obecnym `?person=` usuwa `person`; `null` usuwa tylko swój klucz; `?spouse=3&sort=name` i hash zostają nietknięte; ścieżka `/tree/5` nie jest traktowana jak klucz `person`; `panelVisitOptions.only` zawiera każdą wartość rejestru. Import z `vite-plus/test` jak w `resources/js/lib/partial-date.test.ts`.

#### 3. Hook nawigacji panelu

**File**: `resources/js/hooks/use-side-panel-navigation.ts`

**Intent**: Dać konsumentowi jeden punkt otwierania, podmiany i zamykania panelu danego typu z bieżącej strony, według wzorca `PersonPreviewProvider.open/close`.

**Contract**: `useSidePanelNavigation(key: PanelKey): { hrefFor(id: number | string): string; open(id: number | string): void; close(): void }`.
- `hrefFor` — `withPanel(usePage().url, key, id)` (do prawdziwych linków `<Link href>`).
- `open` — `router.visit(hrefFor(id), panelVisitOptions)`; ta sama funkcja podmienia rekord w otwartym panelu.
- `close` — `router.replace({ url: withPanel(url, key, null), props: (current) => ({ ...current, [PANEL_PROPS[key]]: null }), preserveScroll: true, preserveState: true })`, bez żądania do serwera.

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą, w tym nowy `side-panel-url.test.ts`: `vendor/bin/sail npm run test:js`
- Sprawdzenie typów przechodzi: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`

**Implementation Note**: Faza bez UI — po zielonych sprawdzeniach automatycznych przejdź do fazy 2.

---

## Phase 2: Powłoka i slot w layoucie

### Overview

Komponent `SidePanel`, slot w layoucie i provider hosta. Po tej fazie powłoka istnieje, ale nikt jej jeszcze nie renderuje — layout bez otwartego panelu wygląda jak dziś.

### Changes Required:

#### 1. Powłoka, slot i provider

**File**: `resources/js/components/side-panel.tsx`

**Intent**: Jedna powłoka dla czterech paneli: portal do slotu, warianty szerokości, przyklejenie na wysokość okna, pełny ekran poniżej `md`, nagłówek z zamknięciem, przewijana treść, Escape, fokus. Plik na poziomie `components/` jak `form-dialog.tsx`, bo powłoka nie należy do żadnej domeny.

**Contract**:
- `SidePanelSlotProvider({ children })` — kontekst `{ host: HTMLElement | null; setHost }`.
- `SidePanelSlot()` — `<div className="contents" ref={setHost} />`; slot pusty nie zajmuje miejsca.
- `SidePanel(props)`:
  - `title: string`, `description?: string`, `variant?: 'standard' | 'wide'` (domyślnie `standard`), `contentKey?: string | number` (zmienia się przy podmianie rekordu), `onRequestClose: () => void`, `actions?: ReactNode` (przyciski w nagłówku), `footer?: ReactNode` (przypięty dół), `children`.
  - Renderuje `createPortal(..., host)`; `null`, gdy host jeszcze nie istnieje. Poza providerem rzuca błąd jak `usePersonPreview` (`person-preview-provider.tsx:111-121`).
  - Korzeń `<section role="dialog" aria-modal="false" aria-labelledby aria-describedby?>`; nagłówek z `<h2 tabIndex={-1}>` i przyciskiem ikonowym `aria-label="Zamknij panel"` wołającym `onRequestClose`; treść `flex-1 overflow-y-auto`.
  - Desktop (`md:`): `sticky`, `shrink-0`, wysokość okna minus margines karty, styl karty jak `SidebarInset` inset (`rounded-xl`, obramowanie/cień, `bg-background`), szerokość `md:w-[clamp(24rem,30vw,36rem)]` / `md:w-[clamp(40rem,55vw,72rem)]`.
  - Poniżej `md`: `fixed inset-0 z-40 w-full`, bez przyciemnienia.
  - Wysunięcie przy otwarciu klasami `tw-animate-css` (`animate-in slide-in-from-right`), bez ponownej animacji przy zmianie `contentKey`.
  - Escape: natywny nasłuch na korzeniu; `Escape` bez `defaultPrevented` → `preventDefault()` + `onRequestClose()`.
  - Fokus: przy otwarciu i przy każdej zmianie `contentKey` — nagłówek (`focus({ preventScroll: true })`); przy odmontowaniu — powrót do elementu zapamiętanego przed otwarciem, jeśli jest jeszcze w DOM (`isConnected`).

#### 2. Montaż w layoucie

**File**: `resources/js/layouts/app-layout.tsx`, `resources/js/layouts/app/app-sidebar-layout.tsx`

**Intent**: Provider obejmuje szablon i konsumentów na poziomie layoutu; slot stoi w wierszu flex obok `<main>`, dzięki czemu treść zwęża się bez nakładki.

**Contract**: `AppLayout` owija dotychczasową zawartość w `SidePanelSlotProvider`. `AppSidebarLayout` renderuje `<SidePanelSlot />` jako trzecie dziecko `AppShell`, po `AppContent`. `ui/sidebar.tsx` bez zmian.

### Success Criteria:

#### Automated Verification:

- Sprawdzenie typów przechodzi: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Test seed E2E nadal przechodzi: `npx playwright test tests/e2e/seed.spec.ts`

#### Manual Verification:

- Bez otwartego panelu drzewo, lista osób, lista źródeł i ustawienia wyglądają jak przed zmianą (brak dodatkowego marginesu po prawej)
- Podgląd osoby (`?person=`) nadal otwiera się jako modal na drzewie i zamyka do tego samego miejsca

**Implementation Note**: Po zielonych sprawdzeniach automatycznych zatrzymaj się na ręczne potwierdzenie przez człowieka przed fazą 3.

---

## Phase 3: Strona demo i weryfikacja

### Overview

Strona demonstracyjna dostępna tylko w `local`, sterowana prawdziwą ścieżką „parametr `?demo=` → właściwość `demoPanel` → częściowe przeładowanie”, oraz test Pest. Służy do ręcznego sprawdzenia powłoki teraz i jako wzorzec dla S-01/S-02.

### Changes Required:

#### 1. Kontroler i trasa

**File**: `app/Http/Controllers/SidePanelDemoController.php` (`vendor/bin/sail artisan make:controller SidePanelDemoController --invokable --no-interaction`), `routes/web.php`

**Intent**: Strona demo bez bazy danych, niedostępna poza `local`; nieznany id zachowuje się jak cudzy rekord (404), tak jak `previewPerson()`.

**Contract**:
- Trasa w grupie `auth`/`verified`: `GET dev/side-panel` → `SidePanelDemoController`, nazwa `dev.side-panel`. Po dodaniu: `vendor/bin/sail artisan wayfinder:generate`.
- `__invoke(Request $request): Response` — `abort_unless(app()->isLocal(), 404)`; renderuje `dev/side-panel` z `items` (stała lista ok. 5 rekordów `{id, title, body, wide}`, polskie teksty, co najmniej jeden `wide: true` i jeden z długą treścią) oraz `demoPanel` jako domknięcie: `null`, gdy `?demo=` brak lub nie jest liczbą; rekord, gdy id istnieje; `abort(404)`, gdy nie istnieje. Kształty tablic w PHPDoc (Larastan poziom 7).

#### 2. Strona demo

**File**: `resources/js/pages/dev/side-panel.tsx`

**Intent**: Pokazać każde zachowanie powłoki w jednym miejscu: zwężanie płynnej i szerokiej treści, podmianę rekordu, oba warianty, Escape z zagnieżdżonym modalem i wstrzymanie zamknięcia.

**Contract**:
- Okruszki przez `setLayoutProps` jak `pages/tree/show.tsx:33`.
- Treść strony: lista rekordów jako `<Link href={hrefFor(id)} {...panelVisitOptions}>` z `useSidePanelNavigation('demo')`, płynny blok tekstu i przewijany poziomo blok `min-w-[68rem]` (jak siatka drzewa w `components/tree/family-sheet.tsx:242`) oraz treść dłuższa niż okno, żeby sprawdzić zachowanie przewijania.
- Gdy `demoPanel !== null`: `<SidePanel title variant={wide ? 'wide' : 'standard'} contentKey={id} onRequestClose={...}>` z treścią rekordu, linkiem „Następny rekord” (podmiana), polem „Mam niezapisane zmiany” i przyciskiem otwierającym zagnieżdżony `ui/dialog`. Przy zaznaczonym polu zamknięcie (X lub Escape) otwiera modal „Porzucić zmiany?”; potwierdzenie woła `close()`.

#### 3. Test Pest

**File**: `tests/Feature/SidePanelDemoTest.php` (`vendor/bin/sail artisan make:test --pest SidePanelDemoTest --no-interaction`)

**Intent**: Zabezpieczyć bramkę środowiska i 404 dla nieznanego rekordu w ścieżce, którą powielą prawdziwe panele.

**Contract**: Przypadki: poza `local` (domyślne `testing`) zalogowany użytkownik dostaje 404; w `local` (`app()->detectEnvironment(fn () => 'local')`) strona renderuje komponent `dev/side-panel` z `items` i `demoPanel === null`; `?demo=<istniejący id>` daje `demoPanel.id` równe temu id; `?demo=999` daje 404; gość jest przekierowany na `/login`.

### Success Criteria:

#### Automated Verification:

- Test Pest przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/SidePanelDemoTest.php`
- Formatowanie PHP: `vendor/bin/sail bin pint --dirty --format agent`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Testy Vitest przechodzą: `vendor/bin/sail npm run test:js`
- Sprawdzenie typów przechodzi: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`

#### Manual Verification:

- Przy 1366 px i rozwiniętym lewym menu panel standardowy ma ok. 410 px, szeroki ok. 750 px; treść zwęża się obok panelu, panel nie zasłania treści, strona nie przewija się w poziomie
- Przy 2560 px panel standardowy ma ok. 576 px, szeroki ok. 1150 px
- Po przewinięciu strony w dół, otwarciu i zamknięciu panelu pozycja przewinięcia jest ta sama; panel pozostaje przyklejony na wysokość okna, a jego treść przewija się osobno
- „Następny rekord” podmienia treść panelu, w adresie jest jeden parametr `?demo=`, fokus przechodzi na nagłówek; „Wstecz” przeglądarki nie przechodzi przez kolejne rekordy
- Escape w panelu zamyka panel i przywraca fokus na link, który go otworzył; Escape w zagnieżdżonym modalu zamyka tylko modal
- Przy zaznaczonym „Mam niezapisane zmiany” X i Escape pokazują „Porzucić zmiany?”, a anulowanie zostawia panel otwarty
- Poniżej 768 px panel zajmuje cały ekran, zagnieżdżony modal jest nad nim, zamknięcie wraca do strony
- Poza `local` (np. `APP_ENV=production` lokalnie) `/dev/side-panel` zwraca 404

**Implementation Note**: Po zielonych sprawdzeniach automatycznych zatrzymaj się na ręczne potwierdzenie przez człowieka; potem poproś o pełny zestaw testów `vendor/bin/sail artisan test --compact`.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/side-panel-url.test.ts` — niezmienniki adresu: jeden klucz panelu naraz, nietknięte inne parametry, ścieżka i hash, `null` usuwa tylko swój klucz, `only` obejmuje cały rejestr.

### Integration Tests:

- `tests/Feature/SidePanelDemoTest.php` — bramka `local`, rendering z `demoPanel`, 404 dla nieznanego id, przekierowanie gościa.
- Bez E2E w F-02; pierwszy test E2E panelu (lokatory `getByRole('dialog', { name })`) przychodzi z pierwszym konsumentem (S-01/S-02) przez `/10x-e2e`.

### Manual Testing Steps:

1. `vendor/bin/sail npm run dev`, zaloguj się, otwórz `/dev/side-panel` przy oknie 1366 px.
2. Otwórz rekord standardowy, potem szeroki; zmierz szerokości w DevTools; sprawdź zwężanie bloku płynnego i blok `min-w-[68rem]`.
3. Przewiń stronę, otwórz i zamknij panel; podmień rekord „Następny rekord”; sprawdź adres i fokus.
4. Escape w panelu, w zagnieżdżonym modalu; strażnik zmian z X i Escape.
5. Powtórz przy 2560 px i przy < 768 px (tryb urządzenia w DevTools).
6. Na `/tree` otwórz podgląd osoby — nadal modal, zamyka się do tego samego miejsca.

## Performance Considerations

`only` przy otwarciu obejmuje wszystkie właściwości paneli; resolvery zwracają `null` bez swojego klucza (bez zapytań do bazy), więc koszt dodatkowych kluczy jest pomijalny. Przeliczanie szerokości to czysty CSS (`clamp`), bez nasłuchu zmiany rozmiaru.

## Migration Notes

Nie dotyczy — brak zmian w bazie danych i danych.

## References

- Related research: `context/changes/side-panel-shell/research.md`
- Roadmapa F-02: `context/foundation/roadmap.md:105-117`; PRD FR-006, FR-019, FR-028, NFR 1366–2560 px (`context/foundation/prd.md:110,155,184,212`)
- Wzorzec stanu w URL: `resources/js/components/persons/person-preview-provider.tsx:22-92`, `app/Http/Middleware/HandleInertiaRequests.php:54-91`
- Layout: `resources/js/layouts/app-layout.tsx:14-19`, `resources/js/layouts/app/app-sidebar-layout.tsx:13-21`, `resources/js/components/ui/sidebar.tsx:139,302-313`
- Escape w Radix: `node_modules/@radix-ui/react-dismissable-layer/dist/index.mjs:91-106`
- Decyzja o stanie w URL: `context/archive/2026-09-28-person-preview-in-modal/plan.md:33`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Pomocnik URL i nawigacja panelu

#### Automated

- [x] 1.1 Testy Vitest przechodzą, w tym nowy `side-panel-url.test.ts`: `vendor/bin/sail npm run test:js` — bdc57e8
- [x] 1.2 Sprawdzenie typów przechodzi: `vendor/bin/sail npm run types:check` — bdc57e8
- [x] 1.3 Format i lint przechodzą: `vendor/bin/sail npm run check` — bdc57e8

### Phase 2: Powłoka i slot w layoucie

#### Automated

- [x] 2.1 Sprawdzenie typów przechodzi: `vendor/bin/sail npm run types:check` — 5f4a55c
- [x] 2.2 Format i lint przechodzą: `vendor/bin/sail npm run check` — 5f4a55c
- [x] 2.3 Build przechodzi: `vendor/bin/sail npm run build` — 5f4a55c
- [x] 2.4 Test seed E2E nadal przechodzi: `npx playwright test tests/e2e/seed.spec.ts` — 5f4a55c

#### Manual

- [x] 2.5 Bez otwartego panelu drzewo, lista osób, lista źródeł i ustawienia wyglądają jak przed zmianą (brak dodatkowego marginesu po prawej) — 5f4a55c
- [x] 2.6 Podgląd osoby (`?person=`) nadal otwiera się jako modal na drzewie i zamyka do tego samego miejsca — 5f4a55c

### Phase 3: Strona demo i weryfikacja

#### Automated

- [x] 3.1 Test Pest przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/SidePanelDemoTest.php`
- [x] 3.2 Formatowanie PHP: `vendor/bin/sail bin pint --dirty --format agent`
- [x] 3.3 Larastan przechodzi: `vendor/bin/sail composer types:check`
- [x] 3.4 Testy Vitest przechodzą: `vendor/bin/sail npm run test:js`
- [x] 3.5 Sprawdzenie typów przechodzi: `vendor/bin/sail npm run types:check`
- [x] 3.6 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [x] 3.7 Build przechodzi: `vendor/bin/sail npm run build`

#### Manual

- [x] 3.8 Przy 1366 px i rozwiniętym lewym menu panel standardowy ma ok. 410 px, szeroki ok. 750 px; treść zwęża się obok panelu, panel nie zasłania treści, strona nie przewija się w poziomie
- [x] 3.9 Przy 2560 px panel standardowy ma ok. 576 px, szeroki ok. 1150 px
- [x] 3.10 Po przewinięciu strony w dół, otwarciu i zamknięciu panelu pozycja przewinięcia jest ta sama; panel pozostaje przyklejony na wysokość okna, a jego treść przewija się osobno
- [x] 3.11 „Następny rekord” podmienia treść panelu, w adresie jest jeden parametr `?demo=`, fokus przechodzi na nagłówek; „Wstecz” przeglądarki nie przechodzi przez kolejne rekordy
- [x] 3.12 Escape w panelu zamyka panel i przywraca fokus na link, który go otworzył; Escape w zagnieżdżonym modalu zamyka tylko modal
- [x] 3.13 Przy zaznaczonym „Mam niezapisane zmiany” X i Escape pokazują „Porzucić zmiany?”, a anulowanie zostawia panel otwarty
- [x] 3.14 Poniżej 768 px panel zajmuje cały ekran, zagnieżdżony modal jest nad nim, zamknięcie wraca do strony
- [x] 3.15 Poza `local` (np. `APP_ENV=production` lokalnie) `/dev/side-panel` zwraca 404
