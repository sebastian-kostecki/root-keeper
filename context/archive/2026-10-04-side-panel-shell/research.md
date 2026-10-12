---
date: 2026-10-04T22:34:10+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: 6cdce71
branch: develop
repository: root-keeper
topic: "Wspólna powłoka panelu bocznego (F-02): gdzie ją zamontować, jak trzymać stan otwartego panelu i co ją ogranicza"
tags: [research, codebase, side-panel, layout, inertia, person-preview, sidebar]
status: complete
last_updated: 2026-10-04
last_updated_by: Claude (Opus 5.5)
---

# Research: Wspólna powłoka panelu bocznego (F-02)

**Date**: 2026-10-04T22:34:10+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: 6cdce71
**Branch**: develop
**Repository**: root-keeper

## Research Question

Roadmapa F-02 (`context/foundation/roadmap.md:105-117`): jedna powłoka panelu wysuwanego z prawej, bez nakładki — otwarty panel zwęża treść obok siebie, ma wariant szerszy (źródło), zamyka się do tego samego miejsca, a otwarcie innego rekordu podmienia zawartość. Otwarte pytanie z roadmapy (`roadmap.md:115`): gdzie trzymać stan otwartego panelu — w adresie strony czy w stanie klienta? Badanie mapuje: obecny mechanizm modali, które panel zastąpi; szkielet layoutu i miejsce montażu; prymitywy UI, których nie da się użyć; budżet szerokości przy 1366–2560 px; wcześniejsze decyzje; możliwości testowania.

## Summary

- **Precedens stanu w URL już istnieje i jest udokumentowaną decyzją.** Podgląd osoby to modal sterowany parametrem `?person=` na bieżącym adresie: współdzielona właściwość `personPreview` jest niepusta tylko przy tym parametrze (`app/Http/Middleware/HandleInertiaRequests.php:54-58`), otwarcie to częściowa wizyta `only: ['personPreview']` z `replace`, `preserveState`, `preserveScroll` (`resources/js/components/persons/person-preview-provider.tsx:23-33,66-68`), zamknięcie to `router.replace` po stronie klienta bez żądania (`:85-92`). Archiwalny plan uzasadnia to tym, że mutacje wracają `back()` na ten sam adres, więc panel i strona pod nim odświeżają się bez dodatkowego okablowania (`context/archive/2026-09-28-person-preview-in-modal/plan.md:33`). Cudzy lub nieistniejący `?person=` daje 404 (`HandleInertiaRequests.php:84-86`).
- **Pozycja drzewa też jest w URL**: centrum to parametr trasy `tree/{person}`, wybrana para to `?spouse=` (`TreeController.php:37-57` wg raportu; `context/archive/2026-09-28-graphical-tree-view/plan.md:56-59`). Dodanie/usunięcie samego parametru panelu z `preserveScroll`/`preserveState` nie zmienia centrum ani pary — to spełnia „zamknięcie wraca do tego samego miejsca” na tej sprawdzonej ścieżce.
- **Brak miejsca na prawy panel w layoucie.** `AppSidebarLayout` to `AppShell` (`SidebarProvider`, `flex min-h-svh w-full`) z dwoma dziećmi: `AppSidebar` i `AppContent` (`<main>`) (`resources/js/layouts/app/app-sidebar-layout.tsx:13-21`, `resources/js/components/ui/sidebar.tsx:139`). `AppLayout` jest trwałym layoutem domyślnym (`resources/js/app.tsx:13-22`) i już hostuje provider + dialog podglądu (`resources/js/layouts/app-layout.tsx:14-19`). Strona przewija się razem z dokumentem (brak `scroll-region`), więc panel jako element przepływu musi być przyklejony na wysokość okna.
- **Żaden istniejący prymityw nie spełnia wymagań bez przeróbki**: `ui/sheet.tsx` to Radix Dialog z nakładką `fixed inset-0` (CLAUDE.md:57-58 tego zabrania); `ui/sidebar.tsx` obsługuje `side="right"`, ale ma jeden kontekst, globalny skrót Ctrl/Cmd+B, cookie `sidebar_state` i stałą szerokość `--sidebar-width` (16rem) — drugi `SidebarProvider` przesłoniłby kontekst lewego menu (wniosek z kodu, nieuruchomione).
- **Budżet szerokości przy 1366 px jest napięty** (arytmetyka, niezmierzone): treść ma ok. 1070 px przy rozwiniętym lewym menu i ok. 1260 px przy zwiniętym; siatka drzewa ma `min-w-[68rem]` = 1088 px (`resources/js/components/tree/family-sheet.tsx:242`), więc już dziś przewija się w poziomie. Panel 400 px zostawia drzewu ok. 670 px; szeroki panel źródła 900 px — ok. 170 px.
- **Testy**: Vitest obejmuje tylko `resources/js/**/*.test.ts`, bez jsdom i Testing Library (`vite.config.ts:42-44`) — komponentu powłoki nie da się dziś przetestować jednostkowo bez nowych zależności (wymagają zgody). Playwright ma tylko `auth.setup.ts` i `seed.spec.ts`; testy F-01 jeszcze nie istnieją.

## Detailed Findings

### Obecne modale, które zastąpią panele

- **Podgląd osoby** (`resources/js/components/persons/person-preview-dialog.tsx`): bez propsów, montowany raz w `AppLayout`, czyta `usePage().props.personPreview` (typ `resources/js/types/global.d.ts:18`), renderuje `<Dialog open>` tylko przy niepustej właściwości. Treść: nagłówek, `PersonModal` z zakładkami, `UnsavedChangesBar`, całość w `SectionEditorProvider`; strażnik niezapisanych zmian `requestExit(...)` stoi przed zamknięciem, „Wstecz” i zmianą zakładki, Escape przechwytywany przez `onEscapeKeyDown` (`person-preview-dialog.tsx:52-57`).
- **Stos „Wstecz”**: `useState<number[]>` w `PersonPreviewProvider` (`person-preview-provider.tsx:58`), czyszczony, gdy `personPreview` staje się null (`:60-62`). Wpisy dodaje `PersonPreviewLink` w `onSuccess` (`resources/js/components/persons/person-preview-link.tsx:30-75`). FR-028 pozwala go usunąć (`context/foundation/prd.md:184-186,195`).
- **Edycja źródła** (`resources/js/components/sources/source-edit-dialog.tsx:27-34,70-82`): props `source`, `kinds`, `recordTypes`, `trigger?`, `open?`, `onOpenChange?`; stan sterowany albo lokalny `useState`; zapis przez `<Form {...SourceController.update.form(id)}>` (`:134`). Brak stanu w URL. Otwierany z `pages/sources/show.tsx:58,93` i menu wiersza `components/sources/sources-table.tsx:199-227`.
- **Tworzenie źródła / osoby**: lokalny `useState` (`source-create-dialog.tsx:27`, `person-create-dialog.tsx:67`). Zapis osoby przekierowuje na `tree.show/{id}?person={id}`, co otwiera podgląd (`app/Http/Controllers/PersonController.php:103`).
- **Małżeństwo**: brak osobnego dialogu; data i miejsce edytowane w zakładce Przegląd podglądu osoby (`resources/js/components/persons/overview-tab.tsx:205-218`).
- **Miejscowość**: brak jakiegokolwiek komponentu (powstanie w S-01).
- **Punkty wejścia `PersonPreviewLink`**: `pages/tree/show.tsx:104` (przycisk „Podgląd”), `components/tree/sheet-person-card.tsx:73` (ikona oka), `components/persons/persons-table.tsx:139,190`, `components/persons/persons-list.tsx:57`, `pages/sources/show.tsx:124`, `components/persons/family-tab.tsx:125` (krewny w podglądzie).
- **Endpointy**: brak tras JSON dla podglądu; jedyny `JsonResponse` w kodzie dotyczy 2FA (`routes/settings.php`), `useHttp` występuje tylko w `hooks/use-two-factor-auth.ts` (raport pracownika). `personPreview` buduje `app/Actions/Persons/BuildPersonPreview.php:25-35`. `availableSources` to `Inertia::optional`, ładowany `router.reload({only: ['availableSources']})` z bieżącego adresu, który niesie `?person=` (`HandleInertiaRequests.php:59-61`; `components/persons/source-picker-dialog.tsx:51-70`).
- **Mutacje w panelu wracają `back()`** (Marriage, EventSource, PersonChild/Parent, Source update, `PersonController::update` `:113`) — referer zawiera parametr panelu, więc panel zostaje otwarty i dostaje świeże dane. `PersonController::destroy` usuwa `person` z referera i przechodzi na `tree.index`, gdy usunięto osobę w centrum (`PersonController.php:126-163`).

### Szkielet layoutu i miejsce montażu

- `createInertiaApp({ layout })` przypisuje `AppLayout` wszystkim stronom poza `auth/` i `settings/` (te drugie dostają `[AppLayout, SettingsLayout]`) (`resources/js/app.tsx:13-22`). Layout jest trwały między wizytami (dokumentacja Inertia v3 „Persistent Layouts”), więc stan w providerze w `AppLayout` przetrwa nawigację.
- `AppSidebarLayout`: `AppShell` → `AppSidebar` + `AppContent className="min-w-0 overflow-x-clip"` → `AppMobileHeader` (`md:hidden`) + `div.min-w-0 px-6 py-6 md:px-4` z okruszkami i stroną (`app-sidebar-layout.tsx:13-21`). `AppContent` to `SidebarInset` (`<main>`, `relative flex max-w-full min-h-svh flex-1 flex-col`, w wariancie inset `m-2 ml-0 rounded-xl shadow-sm`) (`components/app-content.tsx:4-9`, `ui/sidebar.tsx:302-313`).
- Brak nagłówka desktopowego; okruszki są wewnątrz paddingu treści. Strony ustawiają okruszki przez `setLayoutProps` (`pages/tree/show.tsx:33`).
- Dwa kandydackie miejsca montażu (wniosek): (a) trzecie dziecko flex po `AppContent` w `AppSidebarLayout`, (b) podział wnętrza `<main>` na `[strona | panel]`. W obu przypadkach panel jest elementem przepływu (to daje zwężanie bez nakładki) i potrzebuje `sticky top-0` + wysokości okna, bo przewija się dokument.

### Prymitywy UI

- `ui/sheet.tsx`: `@radix-ui/react-dialog`, nakładka `fixed inset-0 bg-black/80` (`:37`), treść `fixed z-50`, domyślnie `side="right"`, `w-3/4 sm:max-w-sm`, animacje `tw-animate-css` (`:48-61`). Nie spełnia „bez nakładki” (CLAUDE.md:57-58; `context/foundation/stack-assessment.md:86-90,123-128`).
- `ui/sidebar.tsx`: `side="right"`, warianty `sidebar|floating|inset`, `collapsible offcanvas|icon|none` (`:150-160`); na desktopie odsuwa treść przez element-dystans `w-(--sidebar-width)` w przepływie plus `fixed` panel (`:214-245`); poniżej 768 px renderuje się jako Sheet (nakładka) (`:179-200`, próg `hooks/use-mobile.tsx:3`). Stałe: 16rem / 3rem / 18rem; cookie `sidebar_state`; skrót Ctrl/Cmd+B (`:26-31,94-108`). Lewe menu: `collapsible="icon" variant="inset"` (`components/app-sidebar.tsx:153`).
- Animacje: `tw-animate-css` importowany w `resources/css/app.css:3`; sidebar używa `transition-[width]`/`transition-[left,right,width] duration-200 ease-linear` (`ui/sidebar.tsx:216,226`).
- Klawiatura i fokus: poza `ui/` brak własnych globalnych handlerów klawiszy; jedyny globalny `keydown` to Ctrl/Cmd+B sidebaru. Escape obsługuje Radix (`onEscapeKeyDown`), fokus po zamknięciu — `onCloseAutoFocus` (`components/tree/add-spouse-dialog.tsx:26`, `pick-or-create-person-dialog.tsx:43`).

### Szerokości i zachowanie treści obok panelu

- Drzewo: kontener `overflow-x-auto pb-2` z wygaszeniem krawędzi, siatka `grid min-w-[68rem] grid-cols-8` (`components/tree/family-sheet.tsx:240-242`). `hooks/use-centre-in-scroll.ts` centruje kartę osoby w centrum przez `scrollLeft`, gdy zmienia się `key`; jego `ResizeObserver` przelicza tylko wygaszenia (`:63-64`) — zwężenie kontenera przez panel nie przecentruje drzewa (wniosek z kodu). S-05 i tak przebuduje drzewo (dopasowanie do ekranu, przybliżanie; `roadmap.md` S-05).
- Lista osób (`pages/persons/index.tsx:131-145`) i lista źródeł (`pages/sources/index.tsx:126-159`) są płynne; galeria źródeł używa progów zależnych od okna `grid-cols-2 lg:grid-cols-3 xl:grid-cols-4` (`components/sources/sources-gallery.tsx:12`), więc nie zareaguje na zwężenie przez panel (wymagałaby zapytań kontenerowych). Strona źródła: `lg:grid-cols-[minmax(0,1fr)_20rem]` (`pages/sources/show.tsx:84`).
- Arytmetyka (niezmierzona): lewe menu 256 px rozwinięte / ok. 64 px zwinięte, margines inset 8 px, padding treści 32 px → treść ok. 1070 / 1260 px przy 1366 px. NFR wymaga użyteczności paneli od 1366 do 2560 px (`prd.md:212`).

### Testy

- Vitest: `include: ['resources/js/**/*.test.ts']` (`vite.config.ts:42-44`), importy z `vite-plus/test`, brak pliku setup, brak jsdom/happy-dom i `@testing-library` w `package.json`. Istniejące testy to czysta logika (`resources/js/lib/*.test.ts`, `resources/js/hooks/use-initials.test.ts`). Pomocnik URL panelu (budowanie/usuwanie parametru) da się testować w `.ts`; komponentu — nie bez nowych zależności.
- Playwright: `playwright.config.ts` (testDir `tests/e2e`, port 8100, projekt `setup` → `playwright/.auth/user.json`, webServer `scripts/e2e-server.sh`), pliki `tests/e2e/auth.setup.ts` i `tests/e2e/seed.spec.ts` (lokatory rolami i etykietami, polskie teksty, tylko Desktop Chrome bez ustawionego viewportu). Roadmapa: lokatory F-01 mają przetrwać przejście modal → panel („testy opisują przepływ rolami i etykietami, nie strukturą okna”, `roadmap.md:101`).
- Pest: 404 dla cudzego `?person=` jest egzekwowane w middleware (`HandleInertiaRequests.php:84-86`); każdy nowy parametr panelu wymaga takiego samego testu (CLAUDE.md, „Every new endpoint gets a Pest feature test … other-user 404”).

## Code References

- `resources/js/components/persons/person-preview-provider.tsx:22-33` — `previewVisitOptions` (`replace`, `preserveState`, `preserveScroll`, `only: ['personPreview']`)
- `resources/js/components/persons/person-preview-provider.tsx:39-49` — `withPerson()` ustawia/usuwa parametr w bieżącym URL
- `resources/js/components/persons/person-preview-provider.tsx:85-92` — zamknięcie przez `router.replace` bez żądania
- `resources/js/layouts/app-layout.tsx:14-19` — provider i dialog podglądu w trwałym layoucie
- `resources/js/layouts/app/app-sidebar-layout.tsx:13-21` — `AppShell` / `AppSidebar` / `AppContent`
- `resources/js/components/ui/sidebar.tsx:139,150-160,214-245,302-313` — wrapper flex, warianty, mechanizm odsuwania, `SidebarInset`
- `resources/js/components/ui/sheet.tsx:37,48-61` — nakładka i `fixed` treść
- `app/Http/Middleware/HandleInertiaRequests.php:49-61,71-91` — `sidebarOpen`, `personPreview`, `availableSources`, `previewPerson()` z 404
- `app/Actions/Persons/BuildPersonPreview.php:25-35` — dane panelu osoby
- `resources/js/components/tree/family-sheet.tsx:240-242` — przewijany kontener i minimalna szerokość drzewa
- `resources/js/hooks/use-centre-in-scroll.ts:63-64` — `ResizeObserver` tylko dla wygaszeń
- `resources/js/app.tsx:13-22` — layout domyślny
- `vite.config.ts:42-44` — zakres Vitest

## Architecture Insights

- **Wzorzec „parametr URL → współdzielona właściwość → częściowe przeładowanie”** jest ustalonym sposobem otwierania rekordu nad stroną. Daje za darmo: odświeżenie po mutacjach z `back()`, 404 dla cudzych rekordów w jednym miejscu (middleware), ładowanie opcjonalnych właściwości przez `router.reload` z bieżącego adresu, zachowanie pozycji drzewa (parametr trasy i `?spouse=` nietknięte). Stan klienta bez URL wymagałby nowego kanału danych (`useHttp` + endpoint JSON — dziś nieużywany poza 2FA) i ręcznego odświeżania po mutacjach.
- **Powłoka jest niezależna od typu rekordu**: potrzebuje tylko stanu otwarty/zamknięty, wariantu szerokości, tytułu dostępnego (dziś `DialogTitle`/sr-only „Podgląd osoby”), zamknięcia z możliwością wstrzymania (strażnik niezapisanych zmian — `requestExit`) i miejsca na treść. Dane i parametr URL należą do każdego wycinka (S-01, S-02, S-06, S-12).
- **Zagnieżdżone modale zostają** (potwierdzenia usunięcia, pickery; CLAUDE.md:56). Modal Radix otwarty z niemodalnego panelu sam obsłuży swój Escape; własny handler Escape panelu musi ignorować zdarzenia już obsłużone przez warstwę Radix (wniosek, niezweryfikowany uruchomieniem). Archiwalny plan blokował też zamknięcie podglądu kliknięciem poza nim, żeby portalowe dialogi go nie zamykały (`context/archive/2026-09-28-person-preview-in-modal/plan.md:59`) — panel bez nakładki nie ma „kliknięcia poza”, więc ten problem znika.
- **Nazwa `person`** występuje i jako parametr trasy `tree/{person}`, i jako klucz zapytania `?person=` (`context/archive/2026-09-29-person-modal/plan.md:167`) — ogólny klucz panelu musi tego unikać albo zachować `?person=` dla zgodności.

## Historical Context (from prior changes)

- `context/archive/2026-09-28-person-preview-in-modal/plan.md:33,41,51,55` — decyzja: modal sterowany `?person=`, parametr to „mechanizm, nie wspierany adres”, bez wpisu historii na osobę; stos „Wstecz” w providerze layoutu, bo mutacje przemontowują stronę. **Status: aktualne** (kod zgodny, `person-preview-provider.tsx`).
- `context/archive/2026-09-28-person-preview-in-modal/plan.md:15` — wizyta na ten sam URL zastępuje wpis historii. **Status: częściowo** — kod i tak przekazuje jawnie `replace: true` (`person-preview-provider.tsx:29`).
- `context/archive/2026-09-29-person-modal/plan.md:80,133` — strażnik niezapisanych zmian na każdym wyjściu (X, Esc, „Wstecz”, link krewnego). **Status: aktualne** (`person-preview-dialog.tsx:52-57`).
- `context/archive/2026-09-28-graphical-tree-view/plan.md:56-59` — pozycja drzewa w URL (`/tree/{person}` + `?spouse=`), więc „Wstecz” przeglądarki ją przywraca. **Status: aktualne** wg raportu z `TreeController.php:37-57`.
- `context/foundation/archive/prd-v4.md:80,87-90,95-96` — v4: modal pełnoekranowy na wąskich ekranach, „Wstecz”, zamknięcie zostawia drzewo w tym samym miejscu, `/persons/{id}` usunięte. Obecny PRD zmienia modal na panel i znosi wymóg „Wstecz” (`prd.md:155,184-186`).
- `context/archive/2026-09-30-sources-list-views/reviews/impl-review.md:66` — `{editing && <SourceEditDialog open>}` traci animację zamknięcia; dotyczy też warunkowo renderowanego panelu.
- `context/foundation/stack-assessment.md:86-90,123-128` — ostrzeżenie: `sheet.tsx` dałby nakładkę niezgodną z FR-019; wybór komponentu zostawiony `/10x-plan`.

## Related Research

Brak wcześniejszych plików `research.md` dotyczących paneli w `context/changes/**` ani `context/archive/**` (przeszukane przez pracownika; znalezione tylko plany i przeglądy cytowane wyżej).

## Open Questions

Decyzje dla `/10x-plan` (dowody wyżej, wybór należy do użytkownika/planu):

1. **Stan panelu: URL czy klient.** Dowody przemawiają za URL (precedens, mutacje `back()`, 404 w middleware, pozycja drzewa nietknięta). Do rozstrzygnięcia: jeden ogólny klucz (np. `?panel=source:12`) czy osobny klucz na typ (`?person=`, `?source=`, `?marriage=`, `?place=`); czy F-02 dostarcza wspólny pomocnik URL/provider, czy tylko komponent wizualny.
2. **Szerokości wariantów** (standardowy dla osoby/małżeństwa/miejscowości, szeroki dla źródła) i zachowanie przy 1366 px, gdzie szeroki panel zostawia treści ok. 170 px przy rozwiniętym lewym menu: zwinąć lewe menu na czas szerokiego panelu, szerokość procentowa z limitami, czy przeciągany brzeg.
3. **Ekrany < 768 px** (poza NFR 1366–2560, ale aplikacja ma nagłówek mobilny): panel na pełną szerokość jako nakładka, czy bez specjalnej obsługi.
4. **Weryfikacja powłoki bez treści rekordu** (roadmapa: „tylko powłoka”, `roadmap.md:116`): czy F-02 przenosi jakiegoś konsumenta (np. obecny podgląd osoby) do panelu, czy kończy się na komponencie sprawdzonym ręcznie/E2E na stronie testowej. Komponentowe testy jednostkowe wymagają nowych zależności (jsdom/Testing Library) — potrzebna zgoda.
5. **Drzewo po zwężeniu** nie przecentrowuje się (`use-centre-in-scroll.ts:63-64`); czy F-02 to poprawia, czy zostawia S-05/S-06.
