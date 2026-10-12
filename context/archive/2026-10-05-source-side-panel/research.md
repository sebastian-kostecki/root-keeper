---
date: 2026-10-05T11:09:15+02:00
researcher: Claude (Opus 5.5) for Sebastian Kostecki
git_commit: ffd80c6d608ffd856c2d6d7c4b0149be929a9225
branch: develop
repository: root-keeper
topic: "S-02 source-side-panel — co dziś składa się na źródło (dodawanie/podgląd/edycja/usuwanie) i jak przenieść je do szerokiego panelu F-02"
tags: [research, codebase, sources, side-panel, SourceController, source-viewer, e2e]
status: complete
last_updated: 2026-10-05
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje użytkownika do pytań otwartych 1–7 (2026-10-05)"
---

# Research: S-02 — źródło w szerokim panelu bocznym

**Date**: 2026-10-05T11:09:15+02:00
**Researcher**: Claude (Opus 5.5) for Sebastian Kostecki
**Git Commit**: ffd80c6 (lokalne referencje file:line; drzewo robocze ma tylko nieśledzone `.playwright/` i ten folder)
**Branch**: develop
**Repository**: root-keeper

## Research Question

Wycinek S-02 z roadmapy (`context/foundation/roadmap.md` — sekcja „S-02: Źródło w szerokim panelu bocznym”): autor dodaje, ogląda i edytuje źródło w szerokim panelu bocznym, w którym skan (z powiększeniem jak dziś) stoi obok formularza opisu, zamiast w modalu; usuwanie po liście faktów tracących potwierdzenie działa jak dziś (FR-006, FR-027). Pola opisu się nie zmieniają (to robi S-03).

Pytania badawcze:
1. Jak dziś działa źródło — backend, komponenty, wszystkie punkty wejścia?
2. Jak konsument podłącza się do powłoki F-02 i co z wzorca S-01 (miejscowości) jest do ponownego użycia?
3. Które testy (Pest, Vitest, Playwright) i wcześniejsze decyzje ograniczają przeniesienie?
4. Co wiadomo o układzie przy 1366 px (otwarte pytanie roadmapy)?

## Summary

- **Dziś podgląd źródła nie jest modalem, tylko osobną stroną** `sources/show` (`app/Http/Controllers/SourceController.php:110-133`, `resources/js/pages/sources/show.tsx`), na której skan i opis już stoją obok siebie w siatce `lg:grid-cols-[minmax(0,1fr)_20rem]` (`show.tsx:84`). Modalami są: dodawanie (`source-create-dialog.tsx:41-142`), edycja (`source-edit-dialog.tsx:120-255`, z zagnieżdżonym modalem „Zmienić rodzaj źródła?” `:229-253`), usuwanie (`delete-source-dialog.tsx`), wybór źródła i odpięcie. S-02 w praktyce łączy stronę podglądu i dwa modale formularza w jeden panel.
- **Powłoka F-02 jest gotowa, ale `source` nie jest zarejestrowany**: `PANEL_PROPS` ma tylko `person`, `demo`, `locality` (`resources/js/lib/side-panel-url.ts:6-10`). Wariant szeroki to `md:w-[clamp(40rem,55vw,72rem)]` (`resources/js/components/side-panel.tsx:162-163`), ciało przewija się niezależnie (`:197`), stopka jest przypięta (`:200`). Strażnik niezapisanych zmian nie jest częścią powłoki — S-01 i strona demo mają po własnej kopii (`resources/js/pages/localities/index.tsx:99-136,219-244`; `resources/js/pages/dev/side-panel.tsx:91-108,216-240`).
- **Rozwiązywanie klucza panelu nie jest ogólne**: `?person=` jest rozwiązywany globalnie w `HandleInertiaRequests` (`app/Http/Middleware/HandleInertiaRequests.php:55-58,72-92`), a `?locality=` per strona w kontrolerze (`app/Http/Controllers/LocalityController.php:31-47`). To, gdzie trafi resolver `?source=`, decyduje, czy panel źródła otworzy się tylko na `/sources`, czy też z modala osoby (punkty wejścia 10 i 12 poniżej).
- **Od trasy/strony `sources.show` lub przekierowania `store` → `sources.show` zależy siedem plików testowych**: `SourcesTest`, `DeleteImpactTest`, `MarriagesTest`, `PersonsTest`, `EventSourcesTest`, `OwnershipBoundariesTest` i pomocnik E2E `tests/e2e/support/api.ts` (szczegóły w §5), w tym wyrocznia FR-027 `tests/Feature/DeleteImpactTest.php:119-131`, która czyta `confirms` z `GET sources.show`. Usunięcie strony wymaga przepięcia tych testów, a nie ich skasowania.
- **Test E2E `tests/e2e/fact-source-scan.spec.ts:55-79` nie zakłada modala ani strony** — szuka nagłówka i `img` po nazwie źródła i linku „Urodzenie — …”; panel F-02 ma `role="dialog"` nazwany tytułem (`side-panel.tsx:153-158`). Pęka na pewno pomocnik `tests/e2e/support/api.ts:181-182` (oczekuje przekierowania `^/sources/\d+$`), jeśli zmieni się przekierowanie `store`.
- **1366 px**: z arytmetyki panel szeroki ma ok. 751 px (55vw); siatka ze strony podglądu (formularz 20rem + odstęp 1.5rem) zostawiłaby skanowi ok. 375 px. Siatka `show.tsx:84` reaguje na szerokość okna (`lg:`), nie panelu, a `SourceViewer` ma wysokość na sztywno `h-[calc(100vh-12rem)]` (`resources/js/components/sources/source-viewer.tsx:46`). Obu nie da się przenieść do panelu bez zmiany. Wartości nie są zmierzone.

## Detailed Findings

### 1. Backend źródeł dziś

- Trasy: `Route::resource('sources')->only(['index','store','show','update','destroy'])` (`routes/web.php:26`), plus `sources.file` i `sources.thumbnail` (`routes/web.php:27-28`); `Route::pattern('source','[0-9]+')` (`app/Providers/AppServiceProvider.php:30`). Powiązanie z faktem: `POST events/{event}/sources`, `DELETE events/{event}/sources/{source}` (`routes/web.php:32-33`).
- `SourceController`:
  - `index` (`:31-47`) — `sources/index` z `sources`, `filters {q, kind, view, sort}`, `kinds`, `recordTypes`; brak właściwości panelu.
  - `store` (`:93-105`) — `StoreSource`, toast „Dodano źródło”, **`to_route('sources.show', $source)` (`:104`)**.
  - `show` (`:110-133`) — `Gate::authorize('view')`, renderuje `sources/show` z `source`, `kinds`, `recordTypes`, `confirms` (`{event_id, event_type, event_label, is_last_source, person{id, display_name}}`).
  - `update` (`:138-149`) — `UpdateSource`, toast „Zapisano źródło”, **`back()` (`:148`)**.
  - `destroy` (`:154-163`) — `DeleteSource`, toast „Usunięto źródło”, **`to_route('sources.index')` (`:162`)**.
- Walidacja: `SourceStoreRequest.php:20-27` (plik JPG/PNG/WebP do 20 MB + reguły opisu; bez `authorize()`), `SourceUpdateRequest.php:23-26` (`Gate::inspect('update')`), reguła `kind` blokująca usunięcie typu/numeru bez `discard_record_fields` (`:37-41`, `:64-72`). Wspólne reguły opisu: `app/Concerns/SourceDescriptionValidationRules.php:18-31`.
- Policy: `app/Policies/SourcePolicy.php` — `view/update/delete` z `denyAsNotFound` dla cudzego drzewa.
- Współdzielone właściwości: `availableSources` jako `Inertia::optional` (`HandleInertiaRequests.php:60-62`) — ładowana przez wybór źródła; brak klucza `source`.

### 2. Komponenty i strony źródeł dziś

- `source-create-dialog.tsx` — modal z `<Form {...SourceController.store.form()}>` (`:56-66`), pole pliku bez podglądu wybranego obrazu (`:70-88`), pasek postępu (`:90-104`), `SourceKindField`, `SourceDescriptionFields` z `fileName` jako placeholder nazwy.
- `source-edit-dialog.tsx` — modal z `<Form {...SourceController.update.form(id)}>` (`:134-167`); ukryte pole `discard_record_fields` (`:171-176`); `onBefore` liczy `erasedRecordFields` i wstrzymuje wysłanie, otwierając zagnieżdżony modal potwierdzenia (`:138-155`, `:229-253`); błąd przewija do pierwszego `[data-slot="input-error"]` (`:102-108`).
- `source-description-fields.tsx` — pola niekontrolowane (`defaultValue`) w jednej kolumnie; pola aktu ukryte, ale zamontowane, gdy rodzaj ≠ akt. Identyfikatory pól są stałymi napisami (`name`, `year` …), więc dwa egzemplarze formularza naraz zderzyłyby się identyfikatorami (wniosek z kodu, nie sprawdzony w przeglądarce).
- `source-viewer.tsx` — `react-zoom-pan-pinch`, `minScale 0.5`, `maxScale 32`, przyciski Powiększ/Pomniejsz/Dopasuj (`:11-44`), kontener `h-[calc(100vh-12rem)]` (`:46`); propsy tylko `{src, alt}`.
- `source-details.tsx` — widok tylko do odczytu (`<dl>` z polami + blok „Plik”), pusty stan ze slotem `emptyAction`.
- `pages/sources/show.tsx` — nagłówek z Edytuj/Usuń (`:51-82`), siatka skan | opis 20rem (`:84-105`), karta „Potwierdza” z `PersonPreviewLink` (`:107-136`).
- `pages/sources/index.tsx` — „Dodaj źródło” w nagłówku (`:134-138`) i w pustym stanie (`:222-226`); wszystkie wizyty filtrów przez `visit()` budujące adres wyłącznie z `buildSourcesQuery(filters)` z `only: ['filters']` (`:53-67`).

### 3. Punkty wejścia (stan na ffd80c6)

Sprawdzone ścieżki: `resources/js/pages/sources/*`, `resources/js/components/sources/*`, `resources/js/components/persons/*`, `resources/js/components/app-sidebar.tsx`; brak odwołań do źródeł w `components/tree/*`, `pages/tree*`, `pages/persons/*` (grep agenta, niepotwierdzony osobno).

| # | Miejsce | Wyzwalacz | Dziś |
|---|---|---|---|
| 1 | `pages/sources/index.tsx:134` | „Dodaj źródło” w nagłówku | modal dodawania → po zapisie strona `/sources/{id}` |
| 2 | `pages/sources/index.tsx:222` | „Dodaj źródło” w pustym stanie | jak #1 |
| 3 | `components/sources/sources-gallery.tsx:18-19` | karta galerii | `Link` do `/sources/{id}` |
| 4 | `components/sources/sources-table.tsx:129-130` | nazwa w tabeli | `Link` do `/sources/{id}` |
| 5 | `components/sources/sources-table.tsx:190-196` | menu „Otwórz” | `Link` do `/sources/{id}` |
| 6 | `components/sources/sources-table.tsx:198-209,219-228` | menu „Edytuj” | modal edycji nad listą |
| 7 | `pages/sources/show.tsx:58-67` | „Edytuj” | modal edycji |
| 8 | `pages/sources/show.tsx:93-102` | „Dodaj opis” | modal edycji |
| 9 | `pages/sources/show.tsx:68-80` | „Usuń” | modal usuwania → `/sources` |
| 10 | `components/persons/overview-tab.tsx:113-129` | nazwa źródła przy fakcie (`aria-label="Otwórz źródło …"`) | strażnik zmian, potem `router.visit` do `/sources/{id}` (opuszcza modal osoby) |
| 11 | `components/persons/overview-tab.tsx:137-147` | „Powiąż źródło” | modal wyboru (zostaje modalem — CLAUDE.md „small pickers stay modal”) |
| 12 | `components/persons/sources-tab.tsx:94-112` | „Otwórz” w zakładce Źródła osoby | `router.visit` do `/sources/{id}` (`:101`) |
| 13 | `components/persons/sources-tab.tsx:113-131` | „Odepnij” | modal odpięcia |
| 14 | `components/persons/sources-tab.tsx:143-153` | „Powiąż (kolejne) źródło” | modal wyboru |
| 15 | `components/sources/source-picker-dialog.tsx:126-131` | „Dodaj źródło” przy pustym drzewie | `Link` do `/sources` (nie do formularza) |
| 16 | `components/app-sidebar.tsx:42` | menu „Źródła” | `/sources` |
| 17 | `pages/sources/show.tsx:124-130` | „Potwierdza” (kierunek odwrotny) | `PersonPreviewLink` → modal osoby nad stroną źródła (`?person=`) |

Wejścia #3–#10, #12 i #15 to dziś zmiana strony; po S-02 staną się otwarciem panelu (`?source=`). Wejścia #10 i #12 startują z modala osoby, który jest montowany w layoucie na każdej stronie (`resources/js/layouts/app-layout.tsx:20`).

### 4. Powłoka F-02 i wzorzec konsumenta (S-01)

- `SidePanel` (`side-panel.tsx:54-66`): `title`, `description?`, `variant 'standard'|'wide'`, `contentKey` (zmiana przenosi fokus na nagłówek, `:127-129`), `onRequestClose` (jedyna ścieżka zamknięcia — X i Escape), `actions` (przyciski w nagłówku, `:186`), `footer` (przypięty dół, `:200`). Portal do `SidePanelSlot` (`:48-52`) za `AppContent` (`resources/js/layouts/app/app-sidebar-layout.tsx:23`); provider w `app-layout.tsx`.
- Wysokość od `md`: `md:sticky md:top-2 md:h-[calc(100svh-(--spacing(4)))]` (`side-panel.tsx:161`), ciało `min-h-0 flex-1 overflow-y-auto px-4 py-4` (`:197`). Poniżej `md`: `fixed inset-0 z-40` (`:160`) — pod dialogami Radix `z-50`. `inert` dla strony za pełnoekranowym panelem odłożono „do pierwszego konsumenta” (`context/archive/2026-10-04-side-panel-shell/reviews/impl-review.md`, F2); S-01 go nie dodał — brak `inert` w `resources/js` (grep agenta).
- Escape: natywny nasłuch z `defaultPrevented` (`side-panel.tsx:131-150`) — Escape w zagnieżdżonym modalu (np. „Zmienić rodzaj źródła?”) zamyka tylko modal.
- URL: `withPanel` ustawia jeden klucz i usuwa pozostałe zarejestrowane (`side-panel-url.ts:33-51`); `panelVisitOptions.only` = wszystkie właściwości z rejestru (`:15-25`), więc każdy resolver musi zwracać `null` przy braku swojego klucza. Hook `useSidePanelNavigation(key)` → `hrefFor/open/close`, `close` bez żądania (`resources/js/hooks/use-side-panel-navigation.ts:17-36`).
- Wzorzec S-01 (miejscowości):
  - resolver jako zwykłe domknięcie w `LocalityController@index` (`:31-47`): `'new'` → `{locality: null}`, nie-cyfry → `null`, inaczej `findOrFail` w drzewie + `Gate::authorize('view')` (404).
  - `store` → `localities.index?locality={id}` (`:76`), `update` → `back()` (`:92`).
  - Panel: `<Form key={id ?? 'new'}>` z `setDefaultsOnSuccess`, `DirtyReporter` podnosi `isDirty` (`resources/js/components/localities/locality-panel.tsx:21-35,51-60`).
  - Strona: `guard`, `guardLink` (klik w już otwarty rekord = no-op), `discard`, dialog „Odrzucić niezapisane zmiany?” (`pages/localities/index.tsx:99-136,219-244`); wiersze i „Dodaj” to `<Link href={hrefFor(…)} {...panelVisitOptions} onClick={guardLink}>`.
  - Szukanie buduje adres z bieżącego `window.location`, zachowując klucz panelu (`resources/js/lib/localities-index.ts:16-28`).
- Elementy ogólne do wydzielenia przy drugim konsumencie (wniosek): `DirtyReporter`, logika `guard/guardLink/pendingAction/discard` i dialog porzucenia. Elementy specyficzne dla miejscowości: pola formularza, pomocnik szukania.
- Strona demo `/dev/side-panel` pokazuje wariant szeroki tylko z tekstem (`pages/dev/side-panel.tsx:170-174`) — żaden z trzech sprawdzonych konsumentów powłoki (demo, miejscowości, osoba jako modal) nie ma układu dwukolumnowego w panelu.

### 5. Testy, które ograniczają zmianę

Pest (stan na ffd80c6):
- Przekierowanie `store` → `sources.show`: `tests/Feature/SourcesTest.php:127`, `:206`, `:226`.
- Strona `sources/show` i jej właściwości: `SourcesTest.php:60-112` (render, `confirms`), `:180-187`, `:687-698`, `:744-751`; `MarriagesTest.php:179-192` (`confirms` z `sources.show`); `PersonsTest.php:965-970` (`?person=` na `sources.show`).
- **Wyrocznia FR-027**: `DeleteImpactTest.php:119-131` czyta `props.confirms` z `GET sources.show` i porównuje z faktycznie odpiętymi wierszami. `destroy` → `sources.index`: `SourcesTest.php:416-476`, `DeleteImpactTest.php:146-148`.
- `update` przez `back()`: `SourcesTest.php:564-579` i `EventSourcesTest.php:105-118` (`from(sources.show)`) — przechodzą dalej, jeśli `update` zostaje `back()`, a zmieni się tylko `from()`.
- `OwnershipBoundariesTest.php`: przypadki tras z id (`sources.show` `:95`, `update` `:82`, `file`/`thumbnail` `:96-97`, `destroy` `:100`); strażnik kompletności `:139-155` zgłasza zarówno brakujący, jak i nadmiarowy przypadek — usunięcie trasy `sources.show` wymaga usunięcia `:95` oraz przypadku hosta `?person=` `'sources.show (własne źródło w adresie)'` (`:176`). Strażnik widzi tylko id w ścieżce, więc `?source=` potrzebuje osobnego testu jak `?locality=` (`:179-193`).
- Kontrakty, które muszą zostać: `/sources/create` → 404 (`SourcesTest.php:54-58`), reguła potwierdzenia zmiany rodzaju (`:590-636`), częściowe przeładowanie `filters` bez zapytania o źródła (`:821-845`; w S-01 dodano wariant z otwartym panelem — `LocalitiesTest.php:167-176`).
- `file`/`thumbnail` używa przywracanie kopii (`tests/Backup/BackupRestoreTest.php:34-35`) — trasy muszą zostać.

Vitest: `resources/js/lib/side-panel-url.test.ts:44-49` sprawdza, że `only` zawiera każdą wartość `PANEL_PROPS` (nowy wpis pokryty automatycznie); `source-description.test.ts` (`erasedRecordFields` itd.) i `sources-index.test.ts` (`buildSourcesQuery`) muszą zostać zielone.

Playwright: jedyny spec ze źródłem to `tests/e2e/fact-source-scan.spec.ts`. Lokatory: link „Otwórz źródło {nazwa}” (`tests/e2e/support/navigation.ts:65-72`), nagłówek i `img` z nazwą źródła oraz `src` zawierającym `/sources/{id}/` i sha256 pliku (`spec:55-73`), link „Urodzenie — {osoba}” (`:76-78`). Żaden lokator nie celuje w modale „Dodaj/Edytuj źródło”. Pomocnik `createSource` oczekuje przekierowania `^/sources/\d+$` i czyta id z ostatniego segmentu (`tests/e2e/support/api.ts:181-182`). `test-plan.md:209` każe zawężać lokatory do dialogu/panelu, bo nie-modalny panel zostawia widoczne nagłówki strony obok.

### 6. Układ przy 1366 px (arytmetyka, nie pomiar)

- Panel szeroki: `clamp(40rem,55vw,72rem)` → 0,55 × 1366 ≈ 751 px; przy 2560 px → 72rem = 1152 px (`side-panel.tsx:163`; `context/archive/2026-10-04-side-panel-shell/plan-brief.md` — „Szerokości”).
- Wnętrze panelu po `px-4` (2 × 16 px): ok. 719 px. Przy siatce ze strony podglądu (formularz 20rem = 320 px, odstęp `gap-6` = 24 px) skan dostałby ok. 375 px szerokości przy 1366 px.
- Obok panelu przy rozwiniętym lewym menu zostaje ok. 320 px treści — zaakceptowane w F-02 z adnotacją, że „S-02 zdecyduje o układzie skanu i formularza w panelu” (`side-panel-shell/plan-brief.md`, „Open Risks & Assumptions”).
- `lg:grid-cols-[…]` (`show.tsx:84`) to próg szerokości okna: przy 1366 px (`lg` ≥ 1024 px) dałby dwie kolumny niezależnie od tego, ile miejsca ma panel — dopasowanie do panelu wymaga zapytań kontenerowych albo stałego układu.
- `h-[calc(100vh-12rem)]` w `source-viewer.tsx:46` liczy wysokość od okna, a panel ma własną wysokość `100svh − 1rem` z nagłówkiem i przewijanym ciałem; skan trzeba dopasować do panelu (wniosek).

## Code References

- `app/Http/Controllers/SourceController.php:93-105,110-133,138-149,154-163` — store/show/update/destroy i ich przekierowania
- `app/Http/Middleware/HandleInertiaRequests.php:55-62,72-92` — globalny resolver `?person=`, `availableSources`
- `app/Http/Controllers/LocalityController.php:31-47,76,92` — wzorzec resolvera panelu i przekierowań
- `resources/js/lib/side-panel-url.ts:6-10,15-25,33-51` — rejestr paneli, opcje wizyty, `withPanel`
- `resources/js/hooks/use-side-panel-navigation.ts:17-36` — `hrefFor/open/close`
- `resources/js/components/side-panel.tsx:54-66,131-150,153-200` — API powłoki, Escape, szerokości, przewijanie
- `resources/js/components/localities/locality-panel.tsx:21-60` — formularz w panelu, `DirtyReporter`
- `resources/js/pages/localities/index.tsx:48,59-75,99-136,219-244` — strażnik zmian, szukanie z kluczem panelu
- `resources/js/pages/sources/show.tsx:51-136` — dzisiejszy podgląd (skan | opis, Potwierdza)
- `resources/js/pages/sources/index.tsx:53-67,134-138,222-226` — wizyty filtrów gubiące inne klucze, przyciski dodawania
- `resources/js/components/sources/source-create-dialog.tsx`, `source-edit-dialog.tsx`, `source-viewer.tsx:46`, `delete-source-dialog.tsx`
- `resources/js/components/persons/overview-tab.tsx:113-129`, `sources-tab.tsx:94-112` — wejścia z modala osoby
- `resources/js/components/persons/person-preview-provider.tsx:22-49` — `withPerson` i `only: ['personPreview']`
- `tests/Feature/DeleteImpactTest.php:119-148`, `tests/Feature/OwnershipBoundariesTest.php:82-100,139-155,173-193`, `tests/Feature/SourcesTest.php`
- `tests/e2e/fact-source-scan.spec.ts:50-79`, `tests/e2e/support/navigation.ts:65-72`, `tests/e2e/support/api.ts:164-182`

## Architecture Insights

- Wzorzec „parametr → właściwość → częściowe przeładowanie” (F-02): stan panelu w URL, resolver zwraca dane albo `null`, zapis przez `back()` odświeża panel i listę bez dodatkowego kodu. `store` musi jawnie przekierować na adres z kluczem nowego rekordu (precedens `LocalityController.php:76`).
- Konsument decyduje o zasięgu panelu wyborem miejsca resolvera: per strona (miejscowości) albo globalnie w `HandleInertiaRequests` (osoba). Rejestr `PANEL_PROPS` jest globalny, więc `only` zawiera też właściwości, których dana strona nie definiuje; zachowanie Inertii przy brakującym kluczu w `only` nie zostało sprawdzone (wnioskujemy, że jest ignorowany).
- Edycja źródła ma regułę ochronną po obu stronach: klient (`erasedRecordFields` + zagnieżdżony modal) i serwer (`SourceUpdateRequest` `kind` + `discard_record_fields`) — serwer jest autorytetem (`context/archive/2026-09-30-source-description-in-modal/plan-brief.md`).
- Potwierdzenia usunięcia i małe pickery zostają modalne (CLAUDE.md „Side panels vs modals”); w panelu to oznacza zagnieżdżone Radix-dialogi nad panelem — powłoka to obsługuje (`z-40` vs `z-50`, natywny Escape).

## Historical Context (from prior changes)

- `context/archive/2026-10-04-side-panel-shell/plan-brief.md` — osobny klucz `?source=` zarezerwowany; szeroki wariant dla źródła; „S-02 zdecyduje o układzie skanu i formularza”; pierwszy test E2E panelu miał przyjść z pierwszym konsumentem — S-01 go pominął (`context/archive/2026-10-05-places-gazetteer/plan-brief.md`, „Testy: Pest + Vitest, bez E2E”). Werdykt: wszystkie trzy wciąż aktualne.
- `context/archive/2026-10-05-places-gazetteer/reviews/impl-review.md` — F1 szukanie gubiło klucz panelu (ten sam kształt błędu jest dziś w `sources/index.tsx:53-67`), F2 klik w otwarty rekord przy brudnym formularzu, F4 `bail` przy regułach z zapytaniami, F5 dodanie czyści szukanie (znane ograniczenie). Werdykt: F1 i F2 mają bezpośredni odpowiednik w S-02.
- `context/archive/2026-09-30-source-description-in-modal/plan-brief.md` — po dodaniu otwiera się podgląd nowego źródła; edycja wraca do tego samego podglądu z toastem; **brak strażnika niezapisanych zmian (Esc/X porzuca)**; pola aktu ukryte, ale zamontowane. Werdykt: strażnik został od tego czasu wprowadzony w panelach F-02/S-01, więc decyzja „bez strażnika” dotyczy modala i nie przenosi się automatycznie na panel — do rozstrzygnięcia w planie.
- `context/archive/2026-09-25-scan-delete-with-impact/plan-brief.md` i `2026-09-29-scans-become-sources/plan-brief.md` — lista wpływu renderowana z `confirms` już obecnych na stronie; usuwanie tylko z podglądu, potem lista z toastem; dwa warianty dialogu usuwania. Werdykt: aktualne, chronione przez `DeleteImpactTest`.
- `context/archive/2026-09-30-sources-list-views/plan-brief.md` — menu tabeli „Otwórz/Edytuj”, bez usuwania z listy, bo lista wpływu jest tylko na podglądzie; filtry w URL. Werdykt: aktualne.
- `context/archive/2026-09-24-scan-upload-and-preview/plan-brief.md` — jeden plik na przesłanie; powiększenie `react-zoom-pan-pinch`; pliki tylko przez autoryzowane trasy `file`/`thumbnail` z `Cache-Control: private`. Werdykt: aktualne.
- `context/archive/2026-10-04-critical-flows-e2e-guard/plan.md` — nawigacja E2E zebrana w `tests/e2e/support/navigation.ts`, który S-02 ma edytować; id z nagłówka `Location`. Werdykt: aktualne.
- `context/foundation/lessons.md` i `.ai/rules/` nie istnieją (sprawdzone 2026-10-05).

## Related Research

- `context/archive/2026-10-04-side-panel-shell/research.md`
- `context/archive/2026-10-05-places-gazetteer/research.md`
- `context/archive/2026-10-04-critical-flows-e2e-guard/research.md`

## Decyzje użytkownika (2026-10-05)

Rozstrzygają pytania 1–7 z „Open Questions”; fakty z badania bez zmian.

| # | Pytanie | Decyzja | Skutek dla planu (wniosek z ustaleń powyżej) |
|---|---|---|---|
| 1 | Los `/sources/{id}` | **Przekierowanie** `/sources/{id}` → `/sources?source={id}` | Trasa `sources.show` zostaje (przypadek `:95` w `OwnershipBoundariesTest` i 404 dla cudzego id dalej obowiązują); testy czytające komponent `sources/show` i `confirms` z niego (`SourcesTest`, `DeleteImpactTest`, `MarriagesTest`, `PersonsTest:965-970`) trzeba przepiąć na `/sources?source={id}` i właściwość panelu. Linki `SourceController.show(id)` (wejścia #3–#5, #10, #12) działają dalej przez przekierowanie. |
| 2 | Zasięg `?source=` | **Tylko na liście źródeł** — resolver w `SourceController@index`, wzorem `LocalityController` | Wejścia #10 i #12 z modala osoby przechodzą na `/sources?source={id}` (opuszczają bieżącą stronę jak dziś). Szukanie/filtry na liście muszą zachować klucz panelu (błąd F1 z S-01). |
| 3 | Układ przy 1366 px | **Zawsze obok siebie** (PRD FR-006) | Przy 1366 px ok. 375 px skanu + ok. 320 px formularza (arytmetyka, do zmierzenia); wysokość `SourceViewer` dopasowana do panelu zamiast `100vh-12rem`. |
| 4 | Tryb panelu | **Od razu formularz** (jak panel miejscowości) | Lista „Potwierdza” i „Usuń” (dialog z listą wpływu) w panelu; pytanie o niezapisane zmiany przy zamknięciu i przełączeniu rekordu; potwierdzenie zmiany rodzaju zostaje zagnieżdżonym modalem. |
| 5 | Dodawanie (`?source=new`) | **Podgląd pliku przed zapisem** — skan widoczny lokalnie obok formularza od chwili wybrania pliku | Lokalny URL obiektu dla wybranego pliku w `SourceViewer`; `store` przekierowuje na `/sources?source={id}` (zmiana `SourcesTest:127,206,226` i `tests/e2e/support/api.ts:181-182`). |
| 6 | Strażnik niezapisanych zmian | **Wydzielić wspólny** hook + dialog i użyć go w miejscowościach i źródłach | Drobna zmiana w `pages/localities/index.tsx` / `locality-panel.tsx`; `inert` poniżej `md` (F2 powłoki) nie rozstrzygnięty — do `/10x-plan`. |
| 7 | E2E | **Jeden nowy test E2E panelu źródła** + aktualizacja `navigation.ts` i `api.ts` | Np. otwarcie źródła z faktu, edycja opisu w panelu, zapis; lokatory zawężone do panelu (`test-plan.md:209`). |

## Open Questions

Wybory dla `/10x-plan` lub użytkownika (fakty powyżej, decyzje nie):

1. **Los strony `/sources/{id}`** — usunąć (wtedy przepiąć 7 grup testów z §5, w tym wyrocznię FR-027, i strażnik tras) czy zostawić jako przekierowanie/stronę zapasową? Roadmapa mówi „zamiast w modalu”, nie rozstrzyga o stronie.
2. **Zasięg `?source=`** — tylko na `/sources` (resolver w `SourceController@index`; wejścia #10 i #12 z modala osoby przechodzą na `/sources?source={id}`) czy globalnie w `HandleInertiaRequests` (panel otwiera się na bieżącej stronie, np. drzewie)? Dopóki S-06 nie przeniesie osoby, `withPerson` nie czyści innych kluczy (`person-preview-provider.tsx:39-49`), więc „Potwierdza” w panelu źródła da `?source=X&person=Y` — modal osoby nad panelem źródła.
3. **Układ przy 1366 px** — skan obok formularza w ok. 719 px wnętrza (skan ok. 375 px przy formularzu 20rem) czy układ jeden pod drugim poniżej progu zapytania kontenerowego? PRD (`prd.md:110-112`) wymaga „skan i formularz obok siebie”; NFR (`prd.md:212`) — użyteczność od 1366 px. Wartości trzeba zmierzyć w przeglądarce.
4. **Podgląd vs formularz** — czy panel pokazuje od razu formularz (edycja inline, jak S-01), czy widok `SourceDetails` z przyciskiem „Edytuj”? Wpływa na strażnika zmian i na miejsce listy „Potwierdza” i przycisku „Usuń”.
5. **Dodawanie w panelu** (`?source=new`) — skan obok formularza jest możliwy dopiero po wybraniu pliku (dziś brak podglądu pliku przed wysłaniem, `source-create-dialog.tsx:77-81`). Czy panel dodawania pokazuje lokalny podgląd (`URL.createObjectURL`), czy tylko pole pliku do zapisu, po którym otwiera się panel nowego źródła?
6. **Strażnik niezapisanych zmian** — wydzielić wspólny hook/dialog z S-01 (drugi konsument) czy skopiować? I czy dodać `inert` poniżej `md` (odłożone F2 powłoki)?
7. **E2E** — czy S-02 dodaje pierwszy test E2E panelu (odłożony przez F-02 i S-01), czy tylko aktualizuje `navigation.ts`/`api.ts`?
