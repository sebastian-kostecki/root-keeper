---
date: 2026-10-05T22:43:16+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: bc3db92c890351cbd55465ae68b87d2f280df54b
branch: develop
repository: root-keeper
topic: "S-06 person-side-panel — jak dziś działa podgląd/edycja osoby i jej punkty wejścia, i jak przenieść je do powłoki panelu F-02 obok zwężonego drzewa"
tags: [research, codebase, persons, side-panel, tree, zoom-canvas, HandleInertiaRequests, e2e]
status: complete
last_updated: 2026-10-05
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje użytkownika do pytań otwartych 1–4 (2026-10-05)"
---

# Research: S-06 — panel osoby obok drzewa

**Date**: 2026-10-05T22:43:16+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: bc3db92 (lokalne referencje file:line; drzewo robocze ma tylko nieśledzone `.playwright/` i ten folder)
**Branch**: develop
**Repository**: root-keeper

## Research Question

Wycinek S-06 z roadmapy (`context/foundation/roadmap.md:188-199`): kliknięcie karty otwiera panel osoby z prawej (przycisk „Podgląd” znika), drzewo zwęża się obok panelu, ustawienie osoby w centrum jest akcją w panelu, przejście do krewnego podmienia zawartość panelu, po zamknięciu autor jest w tym samym miejscu drzewa; panel otwiera się też z listy osób i ze źródła; edycja osoby odbywa się w panelu (FR-016, FR-019, FR-026, FR-028, FR-029 — `context/foundation/prd.md:144,155,178,184,187`).

Pytania badawcze:
1. Jak działa powłoka F-02 i jak podłączają się do niej panele źródła, miejscowości i parafii?
2. Jak dziś działa podgląd i edycja osoby (dane, akcje, nawigacja do krewnych) i skąd się je otwiera?
3. Jak działa drzewo po S-05 (kliknięcie karty, dopasowanie do ekranu, sloty) i co panel z nim zrobi?
4. Które testy i wcześniejsze decyzje ograniczają przeniesienie?

## Summary

- **Osoba już jest „panelem” w URL, ale renderuje się jako modal.** Klucz `person → personPreview` jest w rejestrze paneli (`resources/js/lib/side-panel-url.ts:7`), a `?person=` rozwiązuje globalny prop współdzielony w `app/Http/Middleware/HandleInertiaRequests.php:56-60,73-95` (`findOrFail` w drzewie użytkownika + `Gate::authorize('view')` → cudza/nieistniejąca osoba to 404 na każdej stronie). Komponent to jednak Radix `Dialog` (`resources/js/components/persons/person-preview-dialog.tsx:42-85`) montowany w layoucie (`resources/js/layouts/app-layout.tsx:17-27`), już wewnątrz `SidePanelSlotProvider`. S-06 zmienia przede wszystkim powłokę wokół istniejącej treści (`PersonModal`: zakładki Przegląd/Rodzina/Źródła), nie dane.
- **Powłoka F-02 jest gotowa do użycia bez zmian**: `SidePanel` (`resources/js/components/side-panel.tsx:99-125`) z wariantami `standard`/`wide`, `contentKey` (przeniesienie fokusu przy podmianie rekordu, `:178-180`), `actions`, `footer`; portal do slotu `display: contents` za `<main>` (`resources/js/layouts/app/app-sidebar-layout.tsx:20-39`), więc treść strony się zwęża; `role="dialog" aria-modal="false"` nazwany tytułem (`side-panel.tsx:204-209`).
- **Dwa wątki zostawione wprost dla S-06**: (a) migracja `withPerson`/`previewVisitOptions` (`resources/js/components/persons/person-preview-provider.tsx:23-49` — `only: ['personPreview']`, nie czyści innych kluczy) na `withPanel`/`panelVisitOptions` (impl-review F3 w `context/archive/2026-10-04-side-panel-shell/`); (b) F9 z `context/archive/2026-10-04-critical-flows-e2e-guard/follow-ups/review-fixes.md` — `aria-labelledby` dla sekcji w `family-tab.tsx` i `familySection` przez `getByRole('region')` w `tests/e2e/support/navigation.ts`.
- **Kliknięcie karty dziś przecentrowuje**: ciało karty to `<Link href={treeShow(id,{spouse})}>`, karta fokusu to zwykły `div`, a „Podgląd” to ikona `PersonPreviewLink` na każdej karcie (`resources/js/components/tree/sheet-person-card.tsx:58-86`) plus przycisk w nagłówku (`resources/js/pages/tree/show.tsx:104-108`). Przecentrowanie istnieje w modalu jako „Pokaż na drzewie” (`resources/js/components/persons/person-modal-header.tsx:269-283,297-308`) — to naturalna akcja „ustaw w centrum”; „Ustaw jako probanta” (`:309-316`) to inna akcja (zmienia probanta).
- **Drzewo zwęży się samo i przeliczy dopasowanie — o ile autor nie przesunął widoku.** `ZoomCanvas` mierzy kontener `ResizeObserver`em (`resources/js/components/zoom-canvas.tsx:143-147`) i ponownie dopasowuje przy zmianie szerokości tylko, gdy widok nie był ruszony (`:114`); decyzja S-05 podjęta właśnie pod S-06 (`context/archive/2026-10-05-tree-fits-screen/plan-brief.md:28`). Wizyty panelu (`preserveState`, `only` = propsy paneli) nie zmieniają `sheet` ani klucza `ZoomCanvas` (`family-sheet.tsx:380-384`), więc instancja i jej transformacja przetrwają otwarcie/zamknięcie/podmianę panelu. Przy przesuniętym widoku biblioteka może przyciąć pozycję przy zwężeniu i nie przywróci jej po zamknięciu (wniosek z `limitToBounds`, niezweryfikowany w przeglądarce).
- **Sloty (FR-026) nie zależą od modala podglądu**: wszystkie to osobne Radix `Dialog` przez `PickOrCreatePersonDialog` (`resources/js/components/tree/pick-or-create-person-dialog.tsx:63-249`). Zapisy rodzica/dziecka/małżeństwa kończą się `back()`, które bierze nagłówek Referer (`vendor/laravel/framework/src/Illuminate/Routing/UrlGenerator.php:164-166`), więc `?person=` przeżywa zapis; wyjątek to dodanie małżonka — `to_route('tree.show', [person, spouse])` (`app/Http/Controllers/PersonSpouseController.php:28,41`) gubi `?person=`, więc otwarty panel by się zamknął.
- **E2E przetrwa przeniesienie przy jednym warunku**: każda asercja osoby idzie przez `openPerson()` (`tests/e2e/support/navigation.ts:81-91`: `goto /tree/{id}?person={id}` → `getByRole('dialog', { name: displayName })`), a panel ma `role="dialog"` — lokator działa, jeśli `SidePanel` dostanie `title={display_name}` (`context/archive/2026-10-04-critical-flows-e2e-guard/plan-brief.md:79`). Żaden spec nie klika karty, „Podgląd”, „Wstecz” ani zamknięcia (reguła F-01, nagłówek `navigation.ts:1-4`).

## Detailed Findings

### 1. Powłoka F-02 i wzorzec konsumentów

- **Komponent** `resources/js/components/side-panel.tsx`: `SidePanelSlotProvider` (:31-72), `SidePanelSlot` (:93-97), `SidePanel` (:117-125), `SidePanelView` (:127-254). Propsy (:99-111): `title`, `description?`, `variant?: 'standard' | 'wide'`, `contentKey?`, `onRequestClose`, `actions?`, `footer?`, `children`.
- **Szerokości** (:213-215): standard `md:w-[clamp(24rem,30vw,36rem)]`, wide `md:w-[clamp(40rem,55vw,72rem)]`. Od `md` panel jest sticky na wysokość ekranu z własnym przewijaniem (:212, :248); poniżej `md` — `fixed inset-0 z-40` na pełny ekran, a treść strony dostaje `inert` (:38-60, :211).
- **Dostępność**: `<section role="dialog" aria-modal="false" aria-labelledby aria-describedby>` (:204-209), fokusowalny nagłówek (:220-227), „Zamknij panel” (:242). Escape na korzeniu panelu ignoruje zdarzenia już `defaultPrevented` (zagnieżdżone modale Radix nie zamykają panelu, :182-201). Fokus wraca do elementu otwierającego po zdjęciu `inert` (:144-176).
- **Stan otwarcia w URL**: rejestr `PANEL_PROPS` (`resources/js/lib/side-panel-url.ts:6-12`) mapuje 5 kluczy: `person`, `demo`, `locality`, `parish`, `source`. `withPanel()` (:35-53) przy otwarciu ustawia jeden klucz i usuwa pozostałe klucze paneli; `null` usuwa tylko swój klucz; ścieżka i inne parametry (`/tree/{id}`, `?spouse=`) zostają. `panelVisitOptions` (:17-27): `replace`, `preserveState`, `preserveScroll`, `only` = wszystkie 5 propsów paneli.
- **Hook** `resources/js/hooks/use-side-panel-navigation.ts:25-44`: `hrefFor(id)`, `open(id)` (`router.visit(withPanel(liveUrl(), …), panelVisitOptions)`), `close()` (`router.replace` bez żądania do serwera, prop → null).
- **Resolvery** pozostałych paneli są per strona w `index` kontrolera: `SourceController.php:50-72`, `LocalityController.php:31-47`, `ParishController.php:36-52` (`findOrFail` w drzewie + `Gate`, cudzy → 404). Osoba jest wyjątkiem — resolver globalny w middleware (patrz §2).
- **Kompozycja panelu** (wzorzec `resources/js/components/sources/source-panel.tsx:110-187`): od razu formularz (bez trybu podglądu), „Usuń” w `actions` jako modal, „Zapisz” w `footer` przez `form={FORM_ID}`, formularz z `key={id}`. Strona-gospodarz łączy `useSidePanelNavigation` z `useUnsavedChangesGuard` i `DiscardChangesDialog` (`resources/js/pages/sources/index.tsx:65-72,314-329`; hook `resources/js/hooks/use-unsaved-changes-guard.ts:12-68`, gdzie `guardLink` czyni klik w już otwarty rekord no-opem).
- **Zapis**: `update` → `back()` (Referer niesie klucz panelu, więc panel i strona odświeżają się same: `SourceController.php:281-292`, `PersonController.php:111-117`).

### 2. Podgląd i edycja osoby dziś

- **Dane**: prop `personPreview` (`HandleInertiaRequests.php:56-60`) → `previewPerson()` (:73-95; tylko cyfry, memoizacja na żądaniu) → `app/Actions/Persons/BuildPersonPreview.php:25-36` zwraca `{person: PersonResource, personForm: PersonFormResource, genders, religions, isRootPerson}`. `availableSources` to `Inertia::optional` (:61-63). Brak endpointu JSON i brak `PersonController::show` (`routes/web.php:24` — `only(['index','store','update','destroy'])`).
- **Otwieranie/zamykanie**: `PersonPreviewLink` (`resources/js/components/persons/person-preview-link.tsx:24-75`) to `<Link>` z prawdziwym href (`withPerson`) i `previewVisitOptions`; przy brudnej karcie idzie przez `editor.requestExit` (:44-57). `close()` — `router.replace` bez `?person`, prop → null (`person-preview-provider.tsx:85-92`).
- **„Wstecz”**: stos w providerze (`person-preview-provider.tsx:58-83`), czyszczony, gdy `personPreview` staje się null; przycisk tylko przy niepustym stosie (`person-preview-dialog.tsx:62-75`). FR-028 pozwala go usunąć (`prd.md:184,195`).
- **Nagłówek** `person-modal-header.tsx`: `DialogTitle`/`DialogDescription` z Radix (:12, :58-65 — wymagają kontekstu `Dialog`, więc w `SidePanel` trzeba je zastąpić tytułem/opisem powłoki); `display_name` z „z d.” i wiek (:225-229); chipy płeć/wyznanie/„Probant” (:251-265); „Pokaż na drzewie” (:269-283) i menu „⋯” z „Pokaż na drzewie” (:297-308), „Ustaw jako probanta” (:309-316, ukryte dla probanta; `hooks/use-set-root-person.ts`) i „Usuń osobę…” (:318-325 → `DeletePersonDialog` z listą skutków).
- **Zakładki** (`person-modal.tsx:113-184`, reset na Przegląd przy zmianie osoby :117-123):
  - Przegląd (`overview-tab.tsx`): karty Dane osobowe / Urodzenie / Małżeństwo z X / Zgon, edycja inline po jednej karcie (`section-card.tsx`, `section-editor.tsx:104-175`), zapis `router.put` do `PersonController.update` z **pełnym** `personForm` scalonym z sekcją (`lib/person-payload.ts`, `overview-tab.tsx:201-244`) lub `MarriageController.update`; źródło faktu → `/sources?source=` (opuszcza stronę, :105-135).
  - Rodzina (`family-tab.tsx:255-308`): rodzice/małżeństwa/dzieci jako `PersonPreviewLink` (:125-130 — podmiana treści), usuwanie relacji/małżeństwa w „⋯”, bez dodawania.
  - Źródła (`sources-tab.tsx`): grupy per fakt, podpinanie/odpinanie, numer wpisu, „Otwórz źródło” → `/sources?source=` (:100-117).
- **Tworzenie** to osobny modal `PersonCreateDialog` (`person-create-dialog.tsx`), wywoływany tylko z listy osób (`pages/persons/index.tsx:138,188`); `store` przekierowuje na `/tree/{id}?person={id}` (`PersonController.php:99-106`).
- **Usuwanie**: `destroy` (:123-132) + `urlAfterDelete` (:139-164) — wraca na stronę z Referera bez `person`; `tree/{usunięty}` → `tree.index`.

### 3. Punkty wejścia do osoby (wszystkie przez `PersonPreviewLink`, czyli `?person=` na bieżącym URL)

| # | Miejsce | Kotwica | Dziś |
|---|---|---|---|
| 1 | Ikona „Podgląd” na karcie drzewa | `components/tree/sheet-person-card.tsx:73-86` | otwiera modal; ciało karty (:62-70) przecentrowuje |
| 2 | Przycisk „Podgląd” w nagłówku drzewa | `pages/tree/show.tsx:104-108` | otwiera modal osoby w centrum |
| 3 | Lista osób — widok Lista | `components/persons/persons-list.tsx:57-88` | cały wiersz otwiera modal |
| 4 | Lista osób — widok Tabela | `components/persons/persons-table.tsx:139-155,190-198` | nazwisko i „Otwórz podgląd”; „Pokaż na drzewie” idzie na drzewo |
| 5 | Panel źródła — „Potwierdza” | `components/sources/source-panel.tsx:517-532` | `withPerson` zachowuje `?source=`, więc modal osoby staje **nad** panelem źródła (akceptowane „do czasu S-06”, `context/archive/2026-10-05-source-side-panel/plan.md:31`) |
| 6 | Rodzina w modalu | `components/persons/family-tab.tsx:125-130` | podmienia treść, odkłada na „Wstecz” |
| 7 | Przekierowanie po utworzeniu | `app/Http/Controllers/PersonController.php:105` | `/tree/{id}?person={id}` |

Brak samodzielnych stron osoby (`resources/js/pages/persons/` ma tylko `index.tsx`; `GET /persons/{id}` → 405, `tests/Feature/PersonsTest.php:427`).

### 4. Drzewo po S-05

- Centrum to parametr trasy `tree/{person}` (`routes/web.php:26-27`, `TreeController.php:20-50`); jedyny odczytywany parametr zapytania to `?spouse=` (`TreeController.php:52-57`). Strona `pages/tree/show.tsx` z propsami `sheet`, `rootPersonId`, `personCandidates` (optional), `genders`, `religions` (:18-24).
- Nagłówek drzewa: „Wróć do probanta”, `SetRootPersonButton` „Ustaw jako probanta”, „Podgląd” (`show.tsx:86-108`).
- `ZoomCanvas` (`components/zoom-canvas.tsx`): dopasowanie `chooseFitView` (`lib/fit-view.ts:16-45` — całość przy skali ≥0.75, max 1.5, inaczej widok kotwicy 0.75 na parze); przeliczenie w `requestAnimationFrame` (:109-118) tylko gdy `!movedRef.current` (:114); `movedRef` ustawia przeciągnięcie >5 px (:13,160-165), natywny scroll (:121-141) i przesunięcie przez Tab (:222-269); resetuje go tylko remount (klucz `${focus.id}:${selected_union}`, `family-sheet.tsx:380-384`).
- Ręczne przybliżanie wyłączone (decyzja S-05, `context/archive/2026-10-05-tree-fits-screen/plan.md:311`; `zoom-canvas.tsx:285-295`). Przegląd ostrzega, że zwężenie panelem S-06 sprawi, że widok kotwicy 0.75 będzie częsty, a całe drzewo dostępne tylko przeciąganiem (`reviews/impl-review.md:32,37`); zrzut przy płótnie ~670 px daje kotwicę 0.75 (`screenshots/verdict.md:32-33,47`).
- Kliknięcia z portali: dialogi slotów i menu związku renderują się wewnątrz drzewa React `ZoomCanvas`, więc ich zdarzenia bąbelkują przez płótno; filtruje je `isInsideCanvas()` (`zoom-canvas.tsx:167-219`). Panel osoby renderowany z layoutu (jak dziś modal) tego nie dotyczy; renderowany z wnętrza `FamilySheet` — dotyczyłby.
- Przełączenie związku `router.visit(treeShow(focus.id,{query:{spouse}}))` (`components/tree/union-node.tsx:80-98`) buduje URL od zera i gubi `?person=`.

### 5. Testy, które ograniczają zmianę

- **Pest**: `tests/Feature/PersonsTest.php` — prop podglądu na drzewie/źródłach (:912, :951), null bez/nieliczbowy parametr (:974), **cudza/brak → 404 (:989)**, `availableSources` (~:1001), `update` → `back()` (:1031), warianty przekierowania po usunięciu (:1041-1109), `store` na drzewo (:693), wiek (:927), nazwisko rodowe (:1191-1252). `tests/Feature/OwnershipBoundariesTest.php:174-194` — cudzy `?person=` to 404 na `tree.index`, `tree.show`, `sources.index` (z i bez `?source=`), `localities.index`. Pomocnik `personPreviewUrl()` w `tests/Pest.php:104-109`. Prop `personPreview` sprawdzają też `TreeTest.php:79-87`, `MarriagesTest`, `SourcesTest.php:739`, `WriteTargetsTest.php:264-389`, `DeleteImpactTest`, `EventSourcesTest`, `ParentChildRelationsTest`.
- **Vitest**: `resources/js/lib/side-panel-url.test.ts` (m.in. `/tree/5` to ścieżka, nie klucz — :47-50; `only` zawiera wszystkie propsy — :53-58). Brak testów komponentów (Vitest bez jsdom; dodanie wymaga zgody).
- **Playwright** (`tests/e2e/`, 1366×768, `playwright.config.ts:30-36`): `openPerson` używany w `fact-source-scan.spec.ts:53`, `source-found-by-place-and-year.spec.ts:81`, `tree-slot-child.spec.ts:93,107`, `tree-slot-parent.spec.ts:68,78`, `tree-slot-spouse.spec.ts:67,81`. Strukturalne lokatory `familySection` (`navigation.ts:94-107`) i `sourcesGroup` (:133-141) filtrują `section` w dialogu. `support/api.ts:275-287` czyta `props.personPreview` z `/tree/{id}?person={id}`. Lokatory `slotDialog`/`treeSlot` (`navigation.ts:16-35`) celują w modale slotów — nietknięte.

## Code References

- `resources/js/components/side-panel.tsx:99-254` — powłoka panelu (propsy, szerokości, a11y, Escape, fokus, inert)
- `resources/js/lib/side-panel-url.ts:6-53` — rejestr kluczy, `panelVisitOptions`, `withPanel`
- `resources/js/hooks/use-side-panel-navigation.ts:25-44` — `hrefFor`/`open`/`close`
- `resources/js/hooks/use-unsaved-changes-guard.ts:12-68` — wspólny strażnik niezapisanych zmian
- `resources/js/layouts/app-layout.tsx:17-27` — globalny host modala osoby wewnątrz `SidePanelSlotProvider`
- `resources/js/layouts/app/app-sidebar-layout.tsx:20-39` — slot panelu jako rodzeństwo `<main>`
- `app/Http/Middleware/HandleInertiaRequests.php:56-95` — globalny resolver `?person=` z 404
- `app/Actions/Persons/BuildPersonPreview.php:25-36` — kształt danych panelu
- `resources/js/components/persons/person-preview-provider.tsx:23-92` — `previewVisitOptions`, `withPerson`, stos „Wstecz”, `close`
- `resources/js/components/persons/person-preview-dialog.tsx:18-87` — modal do zastąpienia
- `resources/js/components/persons/person-modal-header.tsx:58-65,269-325` — tytuł Radix, „Pokaż na drzewie”, „⋯”
- `resources/js/components/persons/person-modal.tsx:113-184` — zakładki
- `resources/js/components/persons/person-preview-link.tsx:24-75` — link wejścia ze strażnikiem
- `resources/js/components/tree/sheet-person-card.tsx:58-86` — kliknięcie karty i ikona „Podgląd”
- `resources/js/pages/tree/show.tsx:86-108` — nagłówek drzewa z „Podgląd”
- `resources/js/components/zoom-canvas.tsx:109-147` — pomiar i warunkowe przeliczenie dopasowania
- `app/Http/Controllers/PersonSpouseController.php:28,41` — przekierowanie gubiące `?person=`
- `resources/js/components/tree/union-node.tsx:80-98` — przełączenie związku gubiące `?person=`
- `tests/e2e/support/navigation.ts:1-4,81-111,133-141` — jedyne miejsce lokatorów osoby w E2E

## Architecture Insights

- **Jeden klucz URL na typ panelu, „replace” zamiast „push”.** Zamknięcie = usunięcie klucza po stronie klienta, bez żądania; dlatego pozycja strony (centrum, związek, filtry, przewinięcie) zostaje. „Wstecz” przeglądarki nie zamyka panelu (świadomie zaakceptowane w F-02, impl-review F5).
- **Osoba to jedyny panel z resolverem globalnym** — pozostałe są per strona. Globalny resolver sprawia, że panel osoby może zwęzić dowolną stronę w layoucie (drzewo, lista osób, źródła, miejscowości) bez pracy po stronie serwera; hostowanie panelu w layoucie (jak dziś modal) zachowuje tę cechę.
- **Panel nie ma trybu „podgląd”** w konwencji S-01/S-02 (od razu formularz); osoba zachowuje własny model edycji per karta (`SectionEditorProvider` + `requestExit`), który jest odrębny od `useUnsavedChangesGuard`. Pogodzenie dwóch strażników to praca dla planu.
- **`withPanel` czyści inne klucze paneli** — przejście na niego zmienia zachowanie wejścia z panelu źródła: panel osoby zastąpi panel źródła zamiast stanąć nad nim (jedna powłoka, jeden panel naraz).

## Historical Context (from prior changes)

- `context/archive/2026-10-04-side-panel-shell/plan-brief.md:23,29` — stan w URL, `?person=` zostaje; `role="dialog"` nazwany tytułem, żeby lokatory F-01 przetrwały modal → panel. **Wspierane** przez bieżący kod (`side-panel.tsx:204-209`, `side-panel-url.ts:7`).
- `context/archive/2026-10-04-side-panel-shell/plan.md:38` — przecentrowanie drzewa po zwężeniu zostawione na S-05/S-06. **Częściowo rozwiązane**: S-05 przelicza dopasowanie przy zmianie szerokości, ale tylko dla nieruszonego widoku (`zoom-canvas.tsx:114`).
- `context/archive/2026-10-04-side-panel-shell/` impl-review F3 — „Migrate `withPerson`/`previewVisitOptions` to `withPanel`/`panelVisitOptions` in S-06”. **Nadal otwarte** (`person-preview-provider.tsx:23-49`).
- `context/archive/2026-10-05-source-side-panel/plan.md:31,38-40` — modal osoby nad panelem źródła akceptowany do S-06; panel źródła poza `/sources` i migracja panelu osoby — „to S-06”. **Nadal aktualne**; wejścia z osoby do źródła to pełna wizyta `/sources?source=` (`sources-tab.tsx:100-117`, `overview-tab.tsx:113-125`).
- `context/archive/2026-10-04-critical-flows-e2e-guard/follow-ups/review-fixes.md` F9 — kolejka dla S-06: `aria-labelledby` dla sekcji Rodziny i `familySection` przez `getByRole('region')`. **Nadal otwarte** (`navigation.ts:94-107`).
- `context/archive/2026-10-05-tree-fits-screen/plan.md:40` — „Panel osoby i zmiana kliknięcia karty (S-06). Przycisk „Podgląd” na karcie zostaje.” — **wspierane**, ikona nadal jest (`sheet-person-card.tsx:73-86`).
- `context/archive/2026-09-29-person-modal/` — jedna powłoka podglądu, edycja per karta, zapis z pełnym `personForm` (inaczej giną `is_deceased`/zgon/`maiden_name`), strażnik każdego wyjścia, bez dodawania relacji w podglądzie. **Wspierane** (`overview-tab.tsx:201-244`, `lib/person-payload.ts`).
- `context/archive/2026-09-29-preview-relations-read-only/` — dodawanie krewnych tylko na drzewie, usuwanie relacji w podglądzie. **Wspierane** (`family-tab.tsx:255-308`).
- `context/archive/2026-10-05-maiden-name-and-age/` — „Panel osoby (S-06) — zmiana dotyczy danych i formatu, nie powłoki”; wiek tylko w nagłówku osoby i na karcie. **Wspierane** (`person-modal-header.tsx:225-229`).

## Related Research

- `context/archive/2026-10-05-source-side-panel/research.md` — pierwszy konsument szerokiego panelu, punkty wejścia źródła z modala osoby.
- `context/archive/2026-10-04-side-panel-shell/` — projekt powłoki i rejestru kluczy.
- `context/archive/2026-10-05-tree-fits-screen/` — dopasowanie drzewa i decyzja o ponownym dopasowaniu przy zwężeniu.

## Open Questions

Decyzje użytkownika (rozstrzygnięte 2026-10-05):

1. **„Ustawienie osoby w centrum” w panelu** — czy to dzisiejsze „Pokaż na drzewie” (przecentrowanie `tree/{id}`), i czy panel po przecentrowaniu zostaje otwarty na tej osobie, czy się zamyka (dziś: zamyka, `preview-relations-read-only`)?
   → **Decyzja:** przecentrowanie na `tree/{id}` z zachowanym `?person={id}` — panel zostaje otwarty na tej samej osobie. „Ustaw jako probanta” pozostaje osobną akcją.
2. **Panel osoby na liście osób i na stronie źródeł** — czy tam też zwęża treść obok siebie (otwarte pytanie roadmapy, `roadmap.md:197`)? Przy hostowaniu w layoucie dzieje się to bez dodatkowej pracy.
   → **Decyzja:** tak — ten sam panel wszędzie, hostowany w layoucie (globalny resolver `?person=` zostaje).
3. **Wejście ze źródła „Potwierdza”** — panel osoby zastępuje panel źródła (jeden panel naraz, `withPanel`), czy zostaje dzisiejsze nakładanie?
   → **Decyzja:** panel osoby zastępuje panel źródła (`withPanel` czyści `?source=`); koniec wyjątku z `source-side-panel/plan.md:31` — testy przypinające nakładanie (`PersonsTest:965`, przypadek gospodarza w `OwnershipBoundariesTest`) wymagają aktualizacji w planie.
4. **Dodawanie nowej osoby** z listy osób — zostaje modalem `PersonCreateDialog`, czy przechodzi do panelu?
   → **Decyzja:** zostaje modalem; po zapisie przekierowanie `/tree/{id}?person={id}` otwiera już panel nowej osoby.

Do rozstrzygnięcia w planie (nie blokują):

5. Szerokość panelu osoby: `standard` (przy 1366 px ok. 410 px = 30vw; z arytmetyki, niezmierzone) vs zakładki i edycja inline, które dziś mieszczą się w modalu.
6. Czy zamknięcie panelu ma przywracać przesunięty widok drzewa (dziś gwarantowane tylko dla nieruszonego dopasowania), oraz czy zwężenie wymaga przycisku „Dopasuj” (ostrzeżenie z impl-review S-05).
7. Zachowanie `?person=` po dodaniu małżonka ze slotu (`PersonSpouseController.php:28,41`) i po przełączeniu związku (`union-node.tsx:80-98`).
8. Pogodzenie strażnika edycji per karta (`SectionEditorProvider.requestExit`) ze strażnikiem panelu (`useUnsavedChangesGuard`) przy zamykaniu i podmianie panelu.
9. Kliknięcie karty fokusu (dziś `div` bez akcji) — czy też otwiera panel.
