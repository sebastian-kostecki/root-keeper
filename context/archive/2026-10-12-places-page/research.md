---
date: 2026-10-12T06:33:26Z
researcher: Claude (Opus 5.5)
git_commit: 4059edb
branch: develop
repository: root-keeper
topic: "Audyt /10x-ui list /localities i /parishes — scalenie w jedną stronę „Miejsca”"
tags: [research, ui, localities, parishes, places, side-panel, design-tokens, states]
status: complete
last_updated: 2026-10-12
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje użytkownika 1–4 (wzór A, /places, „Źródła”, suma w menu)"
---

# Research: scalenie list Miejscowości i Parafie w jedną stronę „Miejsca”

**Date**: 2026-10-12T06:33:26Z
**Researcher**: Claude (Opus 5.5)
**Git Commit**: 4059edb (drzewo robocze czyste poza `context/changes/places-page/`)
**Branch**: develop
**Repository**: root-keeper

## Research Question

Użytkownik (/10x-ui, `change.md`): „scalić zakładki „Miejscowości” i „Parafie” w jedną zakładkę — rozwiązanie
do przemyślenia”. Dwukierunkowy audyt (źródło → widoki, widok → źródło) dwóch list
(`resources/js/pages/{localities,parishes}/index.tsx` + `components/{localities,parishes}/*-{table,empty-state}.tsx`)
w trzech kategoriach /10x-ui; weryfikacja 5 wstępnych zarzutów z `change.md`; porównanie wzorów scalenia
A/B/C; lista punktów wejścia, które scalenie dotyka; wyjściowa macierz 7 stanów. Źródło tokenów:
`resources/css/app.css`; wariant kontraktu: istniejący system projektowy.

## Summary

- **Dwie zakładki to przypadkowa architektura, nie decyzja.** Lista parafii powstała jako „kopia modułu
  miejscowości” z własną pozycją w menu (decyzja 5, `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md:140`);
  scalenia, strony „Miejsca” ani zakładek nie omawiano w żadnym z przejrzanych dokumentów `context/archive/**`,
  `prd.md`, `roadmap.md`, `shape-notes.md`. PRD traktuje oba spisy jako jedną sekcję („### Spisy: miejscowości,
  parafie, archiwa”, `context/foundation/prd.md:114`).
- **Kod obu stron jest niemal identyczny**: `diff` stron różni się tylko identyfikatorami, tekstami, propsami
  parafii (`religions`, `localities`) i polem wyszukiwania (parafie szukają też po siedzibie,
  `resources/js/lib/parishes-index.ts:38-43`). Scalenie jest okazją do wydzielenia wspólnej logiki zamiast trzeciej kopii.
- **Technicznie jedna strona udźwignie oba panele**: `withPanel` usuwa każdy inny klucz panelu przy otwarciu
  (`resources/js/lib/side-panel-url.ts:61-68`), `panelVisitOptions.only` przeładowuje oba propsy paneli, a
  `useSidePanelNavigation` jest per-klucz i nie zależy od ścieżki. Trzy pułapki: kolizja propsa `localities`,
  strażnik niezapisanych zmian porównujący gołe id (miejscowość 3 vs parafia 3), nieświeża lista miejscowości
  po dodaniu jej inline z panelu parafii.
- **Rekomendacja: wzór A** — jedna strona `/places` („Miejsca”) z `SegmentedToggle` „Miejscowości n | Parafie n”
  zapisanym w URL (domyślna zakładka pominięta w adresie), wspólne wyszukiwanie, „Dodaj …” zależne od
  zakładki, panele bez zmian, przekierowania ze starych tras. B (hierarchia) i C (mieszana tabela) odrzucone
  w obecnym zakresie — uzasadnienie w *Wzory scalenia*. Decyzja należy do użytkownika (*Open Questions*).
- **Tokeny są w dobrym stanie**: skan zakodowanych wartości daje 2 trafienia (oba ręcznie wpisany pierścień
  fokusu). Główne zarzuty to architektura (C1, C4), duplikacja (C2) i mobile (C5).

## Charges

| # | Kategoria | Dowód (plik:linia) | Wpływ na użytkownika | Status |
|---|---|---|---|---|
| C1 | Przypadkowa architektura | `resources/js/components/app-sidebar.tsx:47-58` — dwa wpisy menu (MapPin „Miejscowości”, Church „Parafie”) dla jednej domeny; parafia ma siedzibę w miejscowości (`resources/js/components/parishes/parish-panel.tsx:145`), a „użycia” miejscowości liczą parafie (`app/Http/Resources/LocalityResource.php:31`) | Szukając „Tarnów”, autor sprawdza dwie strony; na liście miejscowości nie widać, że Tarnów jest siedzibą parafii, a menu rośnie o pozycję na każdy spis | potwierdzony |
| C2 | Brakujący współdzielony komponent | `resources/js/pages/localities/index.tsx:40-147` ≡ `resources/js/pages/parishes/index.tsx:53-160` (debounce, `openFromList`, `statusText`, `addButton`); `lib/localities-index.ts:8-28` ≡ `lib/parishes-index.ts:8-28`; tabele i stany puste o tej samej powłoce | Pośredni: każda poprawka zachowania listy (np. F2/F5 z impl-review gazetteera) trzeba robić dwa razy, a druga kopia się rozjeżdża; scalenie bez wydzielenia da trzecią | potwierdzony |
| C3 | Brakujący token/styl | `resources/js/components/localities/localities-table.tsx:67`, `resources/js/components/parishes/parishes-table.tsx:68` — `focus-visible:ring-ring/50 … focus-visible:ring-[3px]` zamiast `focusRing` (`resources/js/lib/focus-ring.ts:7`) | Dziś pierścień jest widoczny (pomiar: `:focus-visible` = true, `box-shadow … 0 0 0 3px`, zrzuty `before/04-localities-focus-row.png`, `before/08-parishes-focus-row.png`), ale literał rozjedzie się z tokenem przy zmianie pierścienia; poza zakresem testu tokenów | potwierdzony (odroczony jako D1 w sources-page) |
| C4 | Przypadkowa architektura (semantyka) | „Użycia” = `usage.total` (fakty + parafie + zamieszkania + nagrobki) w `localities-table.tsx:87`, a `sources_count` w `parishes-table.tsx:82` | Na jednej stronie dwie kolumny o tej samej nazwie mierzą co innego; „2” przy parafii znaczy „2 źródła”, przy miejscowości „2 dowolne odwołania” — autor nie wie, co blokuje usunięcie | potwierdzony |
| C5 | Przypadkowa architektura (mobile) | `resources/js/components/parishes/parishes-table.tsx:33-48` — 4 kolumny bez przyklejonej pierwszej; zrzut `before/06-parishes-mobile.png` (375 px): „Wyzn…” ucięte, Siedziba i Użycia poza ekranem | Na telefonie autor nie widzi siedziby ani liczby użyć parafii bez przewijania tabeli w bok; `stickyCell` z `resources/js/lib/table-styles.ts` istnieje, ale tabela go nie używa | potwierdzony |

Wstępny zarzut 5 z `change.md` („punkty wejścia”) nie jest osobnym zarzutem UI — to lista kontraktów, które
scalenie musi przenieść (*Punkty wejścia i kontrakty*).

## Detailed Findings

### Pochodzenie dwóch stron (C1)

- S-01 ustaliło „W sidebarze jest pozycja „Miejscowości” z licznikiem” (`context/archive/2026-10-05-places-gazetteer/plan-brief.md:16`).
- S-03 dodało `/parishes` jako „kopia modułu miejscowości” z własną pozycją w menu; autor odpowiadał tylko na pytanie,
  czy lista parafii w ogóle jest potrzebna (`context/archive/2026-10-05-act-described-by-parish-and-archive/research.md:140,149`).
- Siedziba parafii jest opcjonalna — świadoma decyzja autora (decyzja 6, tamże `research.md:141`); migracja:
  `database/migrations/2026_10_05_114218_create_parishes_table.php:22` (`nullable()`).
- Precedens mieszanej listy w UI istnieje: filtr miejsca na `/sources` łączy oba spisy w jednym Select z
  `SelectGroup` „Miejscowości” / „Parafie” (`resources/js/components/sources/sources-filters.tsx:116-139`).
- Brak w archiwum jakiejkolwiek opinii użytkownika o długości menu lub zakładkach; widok przodków celowo nie dostał
  pozycji w menu (`context/archive/2026-10-10-ancestors-view/plan-brief.md:28`).

### Duplikacja (C2)

- Strony: wspólne bloki — stan wyszukiwania i refy (loc :40-44 / par :53-57), `visit()` (:54-70 / :67-83),
  synchronizacja `filters.q` z historią (:73-78 / :86-91), debounce 300 ms (:80-99 / :93-112), `openFromList`
  (:102-125 / :115-138), `statusText` (:130-135 / :143-148, różnią się tylko formy `pluralPhrase`), `addButton`
  (:137-147 / :150-160), układ (:149-197 / :162-210).
- Biblioteki: `build*Query` i `with*Search` identyczne poza nazwami; sortowanie identyczne (`Intl.Collator('pl')`,
  remis po id, `lib/localities-index.ts:47-50`); filtr różni się polem siedziby (`lib/parishes-index.ts:38-43`).
  Wyszukiwanie jest wrażliwe na znaki diakrytyczne („lodz” nie znajdzie „Łódź”) w obu (`toLocaleLowerCase('pl').includes`).
- Tabele: ta sama powłoka i propsy (`openId`, `hrefFor`, `onRowClick`), ten sam link nazwy z `aria-current` i
  `data-state="selected"`; różnią się kolumnami środkowymi.
- Stany puste: różnią się ikoną i dwoma napisami (`components/{localities,parishes}/*-empty-state.tsx:1,10,12,30`).
- Kontroler: `normalizeSearchQuery` skopiowane dosłownie (`app/Http/Controllers/LocalityController.php:57-64`,
  `ParishController.php:60-67`).

### Wzory scalenia

| | A: przełącznik zakładek | B: hierarchia | C: mieszana tabela |
|---|---|---|---|
| Opis | `/places`, `SegmentedToggle` „Miejscowości n \| Parafie n”, zakładka w URL, wspólne wyszukiwanie, tabela per zakładka | miejscowości z parafiami pod siedzibą + grupa „Bez siedziby” | jedna tabela z kolumną „Rodzaj” i filtrem rodzaju |
| Dane | bez nowych zapytań; oba spisy już są closure'ami (`LocalityController.php:28`, `ParishController.php:29`) | z istniejących propsów (`parish.locality_id`) | z istniejących propsów |
| Komponenty | `SegmentedToggle` już obsługuje `count` (`resources/js/components/segmented-toggle.tsx:9,58-62`) i `iconOnlyBelowSm`; wzór z `/sources` i `/persons` | nowy wiersz-dziecko; kolumny rodzica i dziecka się nie pokrywają (opis/użycia vs wyznanie/źródła) | kolumny dwóch typów się nie pokrywają; C4 staje się dotkliwy |
| Wyszukiwanie | per zakładka (parafie też po siedzibie) | trzeba zdecydować, czy miejscowość pokazuje się, gdy pasują tylko dzieci | „Tarnów” od razu znajduje oba rekordy |
| Ryzyko | pierwszy przełącznik między dwoma modelami (nie filtr jednego) — nazwa parametru do ustalenia | parafie bez siedziby (dozwolone, decyzja 6) psują drzewo; sortowanie na dwóch poziomach | długa, niejednorodna lista; mobile gorzej niż dziś (C5) |
| Werdykt | **rekomendowany** | odrzucony w tym zakresie (relację lepiej pokazać w panelu) | odrzucony |

Relację miejscowość ↔ parafia (sedno C1) A może pokazać tanio bez hierarchii: kolumna „Siedziba” w zakładce
Parafie już jest; panel miejscowości może w przyszłości wymienić parafie z siedzibą tutaj (poza zakresem — panel
nie jest częścią tego widoku).

### Punkty wejścia i kontrakty dotknięte przez scalenie

- **Trasy**: `routes/web.php:45` i `:49` (`Route::resource(...)->only(['index','store','update','destroy'])`);
  inline (`:44`, `:48`) i merge (`:46`, `:50`) zwracają JSON lub przekierowują — nie zależą od strony.
- **Przekierowania na stare indeksy (6)**: `LocalityController.php:79` (store), `:109` (destroy),
  `LocalityMergeController.php:27`, `ParishController.php:83` (store), `:114` (destroy), `ParishMergeController.php:27`.
  `update` w obu robi `back()` (`LocalityController.php:95`, `ParishController.php:100`) — przeniesie się samo.
- **Linki w UI**: tylko pasek boczny (`app-sidebar.tsx:18-19,47-58`) i breadcrumbs stron (`localities/index.tsx:35`,
  `parishes/index.tsx:42`). Panele osoby/źródła i pickery nie linkują do list.
- **Propsy**: kolizja `localities` — pełna `LocalityResource[]` na `/localities` (`LocalityController.php:28`) vs
  opcje `{id,name}` na `/parishes` (`ParishController.php:34-36`). Opcje stają się zbędne, bo `Locality` jest
  nadzbiorem `LocalityOption`.
- **Panele**: `PANEL_PROPS` ma już `locality` i `parish` (`side-panel-url.ts:6-13`); `PersonPanelRedirect::PANEL_KEYS`
  (`app/Support/PersonPanelRedirect.php:17`) ich lustro. Parametr zakładki przeżywa otwarcie/zamknięcie panelu
  (`withPanel` kasuje tylko klucze paneli).
- **Strażnik niezapisanych zmian**: `useUnsavedChangesGuard` porównuje `id === openKey`
  (`resources/js/hooks/use-unsaved-changes-guard.ts:36`) — przy jednym strażniku kliknięcie parafii 3, gdy otwarta
  jest miejscowość 3, zostanie połknięte. Klucz musi mieć prefiks typu albo potrzebne są dwa strażniki.
- **Nieświeża lista**: miejscowość dodana inline z panelu parafii (`components/localities/locality-picker.tsx:46`)
  nie pojawi się w zakładce Miejscowości do pełnego przeładowania.
- **Liczniki menu**: `navCounts.localities` i `.parishes` (`app/Http/Middleware/HandleInertiaRequests.php:57-58`,
  typ `resources/js/types/global.d.ts:21-25`); jedna pozycja potrzebuje jednej liczby (suma lub nowy klucz).
  Aktywność pozycji to prefiks ścieżki bez query (`resources/js/hooks/use-current-url.ts:31-35,62-66`) —
  `/places?…` podświetli „Miejsca”.
- **Testy**: `tests/Feature/LocalitiesTest.php` (komponent `:34`, `:392` nagłówek `X-Inertia-Partial-Component`,
  przekierowania `:50,:63,:171,:218`, GET indeksu m.in. `:18,:31`), `tests/Feature/ParishesTest.php` (komponent `:29`,
  `has('localities', 1)` `:35`, przekierowania `:68,:83,:222,:253`), `tests/Feature/OwnershipBoundariesTest.php:210,224,240`,
  `tests/Feature/AppShellTest.php:35-52` (klucze `navCounts`); e2e `tests/e2e/places-merge-and-delete.spec.ts:28,48,61`
  (`:48` kotwiczy `locality=${id}$` na końcu URL) i `tests/e2e/support/api.ts:447,457` (sprzątanie czyta propsy stron
  po URL). Vitest: `side-panel-url.test.ts:23-33`, `localities-index.test.ts:56-63`, `parishes-index.test.ts:65-72`
  używają starych ścieżek tylko jako przykładowych napisów.
- **Koszt danych**: wizyty wyszukiwania (`only: ['filters']`) i paneli (`panelVisitOptions.only`) nie przeładowują
  list; pełne wejście lub przekierowanie po zapisie liczy oba spisy (miejscowości: 1 zapytanie z 4 podzapytaniami
  liczącymi; parafie: 1 zapytanie + eager `locality` + 1 liczenie).

### Macierz 7 stanów — stan wyjściowy (obie listy)

| Stan | Dziś | Dowód |
|---|---|---|
| default | tokeny i komponenty repo poza C3 | skan: 2 trafienia; `before/01`, `before/05` |
| hover | `TableRow` z `components/ui/table.tsx:58` (`hover:bg-muted/50`, zaznaczony `bg-muted`) | klasa komponentu UI |
| focus-visible | widoczny 3 px na przycisku „Dodaj …” i linku wiersza (po przejściu `transition-[box-shadow]`) | pomiar w `screenshots/shots.mjs`, `before/04-*`, `before/08-*` |
| disabled | brak kontrolek wyłączanych na liście dziś; w A — segment z licznikiem 0 (wzór sources-page: wyłączony) | `context/archive/2026-10-12-sources-page/reviews/plan-review.md:65` |
| error | nieudana wizyta wyszukiwania przechodzi cicho — strony nie używają `failureHandlers` (`resources/js/lib/visit-failure.ts`), które ma `/sources` | `localities/index.tsx:54-70`, `parishes/index.tsx:67-83` |
| empty | `*EmptyTree` i `*NoResults` z „Wyczyść”; w A dochodzi „pusta zakładka przy niepustej drugiej” | `components/*/*-empty-state.tsx` |
| loading | filtrowanie po stronie klienta, brak skoku; `SearchField` nie dostaje `busy` | `localities/index.tsx:166-172` |

## Code References

- `resources/js/components/app-sidebar.tsx:47-58` — dwa wpisy menu
- `resources/js/pages/localities/index.tsx:40-209`, `resources/js/pages/parishes/index.tsx:53-224` — zduplikowana logika listy
- `resources/js/lib/localities-index.ts:8-50`, `resources/js/lib/parishes-index.ts:8-50` — zapytanie, filtr, sort
- `resources/js/components/localities/localities-table.tsx:67,87`, `resources/js/components/parishes/parishes-table.tsx:68,82` — pierścień-literał, „Użycia”
- `resources/js/lib/side-panel-url.ts:6-13,51-71` — rejestr paneli, `withPanel`
- `resources/js/hooks/use-unsaved-changes-guard.ts:36` — porównanie gołego id
- `resources/js/components/segmented-toggle.tsx:5-66` — API przełącznika z `count`
- `app/Http/Controllers/LocalityController.php:23-52,79,109`, `app/Http/Controllers/ParishController.php:24-55,83,114` — index i przekierowania
- `app/Http/Middleware/HandleInertiaRequests.php:54-59` — `navCounts`
- `resources/js/lib/view-tokens.test.ts:15-29` — `VIEW_FILES` (dziś bez list miejsc)

## Architecture Insights

- Wzór list w repo: zakładki/widoki jako `SegmentedToggle` sterowany URL-em, polskie slugi wartości, domyślna wartość
  pominięta w adresie, normalizacja na serwerze z powrotem do domyślnej (`app/Http/Controllers/SourceController.php:379-389`,
  `resources/js/lib/sources-index.ts:80-86,116-143`); przełączniki robią push historii, wyszukiwanie replace
  (`context/archive/2026-09-30-sources-list-views/plan.md:134`). Adres budowany z żywego `window.location`, by
  zachować klucze paneli (`context/archive/2026-10-05-places-gazetteer/reviews/impl-review.md:33-50`).
- A byłby pierwszym przełącznikiem między dwoma modelami (dotąd `kind`/`view` filtrują lub przełączają widok jednego
  modelu) — nazwa parametru nie ma precedensu.
- Bramka wizualna: `screenshots/{before,after}/NN-*.png` + `shots.mjs` + `verdict.md`, 1440×900 i 375×812, serwer
  E2E na 8100 z `TreeViewDemoSeeder` (w bazie `e2e`: 4 miejscowości, 1 parafia, 0 parafii bez siedziby).
  Zrzuty z `transition-*` lub panelem trzeba robić po zakończeniu animacji (`document.getAnimations()`), inaczej
  pomiar pierścienia daje 0 px.

## Historical Context (from prior changes)

- `context/archive/2026-10-05-places-gazetteer/plan-brief.md:16` — pozycja „Miejscowości” w menu (wspiera C1: menu per spis).
- `context/archive/2026-10-05-act-described-by-parish-and-archive/research.md:140-141` — lista parafii jako kopia modułu; siedziba opcjonalna (wspiera C1, C2; ogranicza B).
- `context/archive/2026-10-06-places-merge-and-delete/plan.md:73,168,231` — dwie różne definicje „Użycia” (wspiera C4).
- `context/archive/2026-10-12-sources-page/screenshots/verdict.md:39` — pierścień-literał w tabelach miejsc odroczony jako D1 „jeden widok na zmianę” (C3 wraca tutaj).
- `context/archive/2026-10-11-persons-index-ui/reviews/plan-review.md:25-33` — zmiana przekierowania psuje pomocnika e2e oczekującego ścieżki (dotyczy `places-merge-and-delete.spec.ts:48`).

## Related Research

- `context/archive/2026-10-12-sources-page/research.md` — audyt /10x-ui `/sources` (wzór przełącznika, stanów, bramki)
- `context/archive/2026-10-11-persons-index-ui/research.md` — audyt /10x-ui `/persons`
- `context/archive/2026-10-05-places-gazetteer/research.md` — powstanie spisu miejscowości

## Decisions (użytkownik, 2026-10-12)

1. **Wzór scalenia: A** — jedna strona z `SegmentedToggle` „Miejscowości n | Parafie n”, zakładka w URL, wspólna
   wyszukiwarka, tabele i panele jak dziś. B i C odrzucone.
2. **Adres: nowe `/places`** — `/localities` i `/parishes` (GET indeksu) przekierowują na `/places` z zachowaniem
   otwartego panelu (`?locality=` / `?parish=`) i wyszukiwania; przekierowania po zapisie/usunięciu/scaleniu
   (6 miejsc z *Punkty wejścia*) celują w `/places` z właściwą zakładką.
3. **„Użycia” (C4)**: liczby bez zmian; w zakładce Parafie kolumna nazywa się „Źródła”, Miejscowości zostają przy „Użycia”.
4. **Licznik „Miejsca” w menu**: suma obu spisów; rozbicie na przełączniku zakładek.

## Open Questions

Do ustalenia w planie (nie blokuje): nazwa i wartości parametru zakładki (`kind`/`view` mają już inne znaczenie);
prefiks typu w strażniku niezapisanych zmian vs dwa strażniki; czy C5 rozwiązać `stickyCell` czy ukryciem kolumn
na mobile; dodanie `failureHandlers` i `busy` (stan error/loading); czy segment z licznikiem 0 jest wyłączony
(wzór sources-page), skoro zakładka pustego spisu musi nadal pozwolić dodać pierwszy rekord.
