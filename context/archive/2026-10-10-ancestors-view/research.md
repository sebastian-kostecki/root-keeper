---
date: 2026-10-10T22:46:25+02:00
researcher: Claude (Opus 5.5) dla Sebastiana Kosteckiego
git_commit: c2fd702fe5d4e6925ebe3bad66ce20e491d93491
branch: develop
repository: root-keeper
topic: "S-15 ancestors-view — co istnieje i czego brakuje, by pokazać osobę i wszystkich jej przodków (FR-018, FR-029)"
tags: [research, codebase, tree, zoom-canvas, parent-child-graph, side-panel, ownership]
status: complete
last_updated: 2026-10-10
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Dopisane decyzje autora z 2026-10-10 rozstrzygające Open Questions 1–4."
---

# Research: widok wszystkich przodków (S-15 `ancestors-view`)

**Date**: 2026-10-10
**Researcher**: Claude (Opus 5.5) dla Sebastiana Kosteckiego
**Git Commit**: c2fd702fe5d4e6925ebe3bad66ce20e491d93491
**Branch**: develop
**Repository**: root-keeper

## Research Question

Roadmapa S-15 (`context/foundation/roadmap.md:304-315`), PRD FR-018 (`context/foundation/prd.md:150-152`) i FR-029 (`prd.md:187`): autor wybiera osobę i widzi ją oraz wszystkich jej przodków w jednym widoku z wyborem liczby pokoleń, dopasowanym do ekranu i „z przybliżaniem jak w drzewie”; widok tylko do oglądania (`prd.md:236`); cudza osoba to 404. Co w kodzie już istnieje (dane, dostęp, układ, dopasowanie, panel, testy), co trzeba dobudować i które decyzje są otwarte?

## Summary

- **Dane przodków:** `ParentChildGraph::ancestorsOf()` (`app/Support/ParentChildGraph.php:75-78`) zwraca płaską listę id w kolejności BFS, bez numeru pokolenia, pozycji ojciec/matka i limitu głębokości; przodek osiągalny dwiema liniami pojawia się raz (`$visited`, `:92-114`). Widok pokoleniowy potrzebuje nowej metody przechodzącej po `parentsOf()` poziom po poziomie z limitem. Wzorzec zapytań z drzewa (1 zapytanie grafu + 1 zapytanie osób z `events`) wystarcza bez rekurencyjnego CTE.
- **Dostęp:** `PersonPolicy::view` daje `denyAsNotFound()` dla cudzej osoby (`app/Policies/PersonPolicy.php:72-77`), a `TreeController::show` autoryzuje przez `Gate::authorize('view', $person)` (`app/Http/Controllers/TreeController.php:39`). Nowa trasa z `{person}` **musi** trafić do `ownershipRouteCases()` — test kompletności (`tests/Feature/OwnershipBoundariesTest.php:162-177`) zawodzi, gdy trasa kontrolera z parametrem nie ma przypadku.
- **Karty:** `SheetPersonResource` (`app/Http/Resources/SheetPersonResource.php:30-49`) i `SheetPersonCard` / `UnknownPersonCard` (`resources/js/components/tree/`) nadają się bez zmian treści; link karty prowadzi jednak do `tree.show` (`sheet-person-card.tsx:71-86`).
- **Układ:** `FamilySheet` to sztywna siatka 8 kolumn na 3 pokolenia (`family-sheet.tsx:21-35`, `BuildFamilySheet.php:146-171`) z nieodłączalnymi akcjami dodawania — widok przodków to nowy komponent, nie flaga `readOnly`. Linie są w CSS; `PairConnector` (`family-sheet.tsx:87-119`) jest prywatny dla pliku.
- **Dopasowanie:** `ZoomCanvas` (`resources/js/components/zoom-canvas.tsx`) jest ogólny, z propami `anchorRef`, `floor`, `max`, i był wprost projektowany także dla S-15. **Ręcznego przybliżania nie ma** (decyzja 2026-10-05, `context/archive/2026-10-05-tree-fits-screen/plan.md:23`) — sformułowanie FR-018 „przybliżanie jak w drzewie” jest nieaktualne, a otwarty follow-up każe to rozstrzygnąć właśnie w S-15 (`.../follow-ups/review-fixes.md:5`).
- **Panel osoby:** renderuje go `AppLayout` na każdej stronie (`resources/js/layouts/app-layout.tsx:19-41`), stan w `?person=` rozwiązuje middleware z 404 dla cudzej osoby (`app/Http/Middleware/HandleInertiaRequests.php:60-64,87-106`). Otwarte pytanie roadmapy („czy kliknięcie przodka otwiera panel osoby → zależność od S-06”) przestało blokować: S-06 jest `done`, a panel jest dostępny bez pracy serwerowej.
- **Otwarte decyzje produktowe:** domyślna i maksymalna liczba pokoleń, kierunek układu, punkt wejścia, co robi kliknięcie karty, jak pokazać przodka powtarzającego się (pedigree collapse), oraz czy widok dostaje ręczne przybliżanie/„Dopasuj”. Żaden dokument tego nie ustala (sprawdzone: PRD, shape-notes, roadmapa, archiwa wymienione w Historical Context).

## Detailed Findings

### 1. Dane: graf rodzic–dziecko i przodkowie

- Tabela `parent_child_relations`: `id, tree_id, parent_id, child_id, timestamps`, `unique(parent_id, child_id)`, kaskadowe FK (`database/migrations/2026_09_25_061809_create_parent_child_relations_table.php:14-24`). Brak kolumny roli: ojciec/matka wynika z `people.gender` (enum `App\Enums\Gender`, nullable). Brak typów (adopcja itp.).
- `ParentChildGraph::forTree(Tree)` (`app/Support/ParentChildGraph.php:23-36`) — jedno zapytanie `parent_id, child_id` całego drzewa, mapy `parentsByChild` / `childrenByParent` w pamięci (same id). API: `parentsOf` (:41), `childIds` (:51), `childrenOf` (:59), `descendantsOf` (:67), `ancestorsOf` (:75), `hasRelation` (:80).
- `traverse()` (:92-114): BFS z `array_shift`, zbiór `$visited` chroni przed cyklem i usuwa duplikaty. Zwraca `list<int>` bez osoby startowej. **Brak** głębokości, pozycji i limitu.
- Reguły relacji są tylko w kodzie aplikacji (`app/Actions/Relations/CreateParentChildRelation.php:103-135`, w transakcji z blokadą): istnieje już (:113), najwyżej 2 rodziców (:117), brak cyklu przez `descendantsOf` (:121), nie dwóch rodziców tej samej znanej płci (`guardSameSexParent` :145-163; płeć nieznana nie koliduje). `UpdatePerson::guardParentSexChange` (`app/Actions/Persons/UpdatePerson.php:129-158`). Baza nie ma CHECK, a helper testowy `linkParent` (`tests/Pest.php:76`) i fabryka omijają reguły — wniosek: przejście w widoku przodków powinno pozostać odporne na cykl i tolerować >2 rodziców w danych.
- Ustawienie dwóch rodziców w slotach: `BuildFamilySheet::parentSlots` (`app/Actions/Trees/BuildFamilySheet.php:392-422`) sortuje przez `rank()` (:477-484: mężczyzna 0, nieznana 1, kobieta 2), brakujący rodzic to zastępnik „Nieznany ojciec/matka/rodzic”. Metoda jest prywatna; dla widoku przodków do wydzielenia albo powtórzenia tej samej reguły (decyzja z `context/archive/2026-09-28-graphical-tree-view/plan-brief.md:45`: mężczyzna z lewej, kobieta z prawej, nieznany pomiędzy, rozstrzygnięcie datą urodzenia).
- Brak testu jednostkowego `ParentChildGraph` (`tests/Unit` zawiera Age, PartialDate, Example). `ancestorsOf` pokryty pośrednio przez `tests/Feature/TreeTest.php:651`.

### 2. Serwowanie drzewa i wzorzec zapytań

- Trasy: `GET tree` → `TreeController@index` (`tree.index`, centruje na `trees.root_person_id`) i `GET tree/{person}` → `TreeController@show` (`tree.show`) (`routes/web.php:29-30`). Fokus to parametr trasy z implicit binding + `Gate::authorize('view', $person)` (`TreeController.php:39`); `?spouse=` wybiera związek (:52-57).
- Propsy strony `tree/show`: `sheet`, `rootPersonId`, `personCandidates` (`Inertia::optional`), `genders`, `religions` (`TreeController.php:43-49`).
- `BuildFamilySheet` ładuje osoby jednym `people()->with('events')->whereIn(ids)` (:339-343) po jednym zapytaniu grafu; `TreeTest.php:498` pilnuje stałej liczby zapytań przy rosnącym drzewie — naturalny wzorzec testu regresji dla widoku przodków.
- Seedery: `DatabaseSeeder` tworzy tylko pustego użytkownika; `TreeViewDemoSeeder` (~40+ osób) ma najgłębszą linię 3 pokolenia powyżej (`database/seeders/TreeViewDemoSeeder.php:109-153`). Nie ma seedera z 5+ pokoleniami do ręcznego sprawdzenia czytelności.

### 3. Dostęp i testy własności (FR-029)

- `PersonPolicy::view|update|delete` — `tree_id` zgodne → allow, inaczej `Response::denyAsNotFound()` (`app/Policies/PersonPolicy.php:72-97`).
- `ownershipRouteCases()` (`tests/Feature/OwnershipBoundariesTest.php:73`), przypadek `tree.show` w liście `$withoutBody` (:101-102); test uruchamiający (:147-160) sprawdza `assertNotFound` i brak zapisów; test kompletności (:162-177) porównuje nazwy wszystkich tras kontrolerów z `{` w URI z pokrytymi przypadkami (`missing` i `surplus` muszą być puste).
- Jeśli strona przodków ma obsługiwać `?person=`, warto dodać ją do zbioru stron z panelem w tym samym pliku (:181-199).
- `PersonController::urlAfterDelete` (`app/Http/Controllers/PersonController.php:140-165`): po usunięciu osoby wraca na poprzedni URL bez `?person`; wyjątek tylko dla `tree.show` tej samej osoby (→ `tree.index`). Usunięcie osoby-fokusu z panelu na stronie przodków wróciłoby na URL przodków tej osoby → 404, chyba że nowa trasa dostanie ten sam wyjątek.

### 4. Frontend: strona drzewa, karty, linie

- Jedna strona `resources/js/pages/tree/show.tsx` dla `tree.index` i `tree.show`; `setLayoutProps({ breadcrumbs, fillHeight: sheet !== null })` (`show.tsx:32-45`) — `fillHeight` daje treści wysokość okna (`resources/js/layouts/app/app-sidebar-layout.tsx:65-76`), bez niej `ZoomCanvas` nie ma wysokości.
- Typy: `SheetPerson` i `SheetSlot` (`person` / `unknown` z opcjonalnym `parent_of` / `empty`) w `resources/js/types/tree.ts:1-25`, `FamilySheet` (:55-67).
- Karty: `SheetPersonCard` (`sheet-person-card.tsx:55-105`) — ciało to `<Link>` do `treeShow(person.id, …)` zachowujący `?person`/`?marriage` (:71-86), ikona oka `PersonPreviewLink` z `aria-label="Podgląd: …"` otwiera panel (:89-102); karta fokusu ma `aria-current` (:66-69). `UnknownPersonCard` bez `addLabel` jest statyczny. Style w `card-styles.ts` (`cardSurface` z `min-h-24`, `cardFocus`, `cardInteractive`), wygląd ustalony w `context/archive/2026-09-29-tree-sheet-variant-c/change.md:12` (wypełnienie kolorem płci, bez podpisów „Pokolenie”/„Przodkowie”).
- `FamilySheet`: siatka `repeat(8, 10rem)` z odstępem 0,75 rem (`family-sheet.tsx:21-35`), wiersze na sztywno: 8 dziadków, 4 rodziców, para, dzieci (:312-454). Linie: `PairConnector` — obramowanie `border-x border-b` + pionowy pień 1 px `bg-tree-line` (:87-119), nieeksportowany. Tokeny `--tree-line`, `--tree-meta` w `resources/css/app.css:70-71,120-121,165-166`.
- Akcje dodawania renderowane bezwarunkowo albo zależnie od danych: `AddChildDialog` (`family-sheet.tsx:287-294,181-190,241-252`), `AddSpouseDialog` (:394-402), `UnionNode` (:414-424), `MarryCoupleDialog` (:122-137,276-283,434-438), `ParentSlotDialog` dla slotu `unknown` z `parent_of` (:66-76; serwer zawsze ustawia `parent_of` w `BuildFamilySheet.php:407-420,458-473`). `FamilySheet` nie ma propa `readOnly`.

### 5. Dopasowanie do ekranu i (brak) przybliżania

- `ZoomCanvas` (`resources/js/components/zoom-canvas.tsx`): propsy `children`, `anchorRef`, `floor?`, `max?`, `className?` (:15-24). Opakowuje `TransformWrapper` z `minScale={floor}`, `maxScale={max}` i wyłączonym kółkiem, szczypaniem i dwuklikiem (:285-295). Przeciąganie przesuwa; próg 5 px odróżnia przeciągnięcie od kliknięcia i połyka kliknięcie kończące przesunięcie (:207-219); Tab przesuwa widok do fokusu (:222-269); `ResizeObserver` dopasowuje ponownie, dopóki autor nie przesunął (:109-153).
- `chooseFitView` (`resources/js/lib/fit-view.ts:23-44`): skala = min(szerokość, wysokość, `max`); poniżej `floor` → widok „anchor” w skali `floor` wyśrodkowany na `anchorRef`. Stałe `TREE_FIT_FLOOR = 0.75`, `TREE_FIT_MAX = 1.5` (:2,5). Testy Vitest: `lib/fit-view.test.ts`.
- `FamilySheet` wymusza świeże dopasowanie przez `key` na `ZoomCanvas` (`family-sheet.tsx:302-305`); widok przodków analogicznie kluczem `personId:generations`.
- Decyzja „bez ręcznego przybliżania” (2026-10-05, commit 480a062): `context/archive/2026-10-05-tree-fits-screen/plan.md:23` i Addendum (:311); PRD FR-013 zaktualizowano (`prd.md:135`), FR-018 (`prd.md:150,152`) i roadmapa S-15 (`roadmap.md:306`) nie. Follow-up F1 (niezaznaczony): „rozstrzygnąć przy S-15, czy widok przodków potrzebuje przybliżania/«Dopasuj», i ujednolicić opisy” (`.../follow-ups/review-fixes.md:5`). Jedyne przyciski przybliżania w kodzie: `resources/js/components/sources/source-viewer.tsx:14-47` („Powiększ”/„Pomniejsz”/„Dopasuj”).
- **Szacunek (wniosek, nie pomiar):** przy kartach 10 rem i odstępie 0,75 rem najwyższy wiersz *n*-tego pokolenia przodków ma 2ⁿ kart. Dla n = 4 (16 kart) to 16·10 + 15·0,75 ≈ 171 rem ≈ 2740 px. Przy szerokości 1366 px minus pasek boczny 16 rem (`resources/js/components/ui/sidebar.tsx:28`) skala wyszłaby ok. 0,4 — poniżej `TREE_FIT_FLOOR`, więc z obecnym `floor` widok startowałby w 75% „na kotwicy” i całości nie dałoby się zobaczyć bez przeciągania. Układ poziomy (przodkowie w prawo) przenosi ten sam problem na wysokość: 16 kart × min. 6 rem + odstępy ≈ 107 rem ≈ 1710 px przy wysokości ok. 700 px. Wniosek: dla 5+ pokoleń trzeba niższego `floor` dla tego widoku, mniejszych kart dla dalszych pokoleń, albo przybliżania/„Dopasuj” — to rdzeń decyzji z follow-upu F1.

### 6. Panel osoby i nawigacja

- `AppLayout` renderuje `<PersonPanel/>` i `<MarriagePanel/>` dla każdej strony nie-auth (`resources/js/layouts/app-layout.tsx:19-41`, `resources/js/app.tsx:13-22`). Klient: `useSidePanelNavigation('person')` (`resources/js/hooks/use-side-panel-navigation.ts:18-43`), `withPanel`/`panelVisitOptions` (`resources/js/lib/side-panel-url.ts:6-53`), `PersonPreviewLink` z ochroną niezapisanych zmian (`resources/js/components/persons/person-preview-link.tsx:22`). Lista osób już z tego korzysta (`persons-table.tsx`).
- Zwężanie: `SidePanel` portaluje do `SidePanelSlot` obok `AppContent` (`resources/js/components/side-panel.tsx:93-97,117-125,210-216`); `ResizeObserver` w `ZoomCanvas` dopasowuje ponownie.
- **Pułapka:** `PersonPanel` czyta `props.sheet` i rzutuje na `FamilySheet` dla `centreHints` przycisku „Pokaż w drzewie” (`resources/js/components/persons/person-panel.tsx:31-33`). Strona przodków nie może mieć propa `sheet` o innym kształcie; bez `sheet` podpowiedzi są `null` i „Pokaż w drzewie” prowadzi na domyślny związek osoby (`context/archive/2026-10-05-person-side-panel/plan.md:61`).
- Kliknięcie karty na drzewie: S-06 zmienił je na „otwiera panel” (`context/archive/2026-10-05-person-side-panel/plan.md:5,162`), a commit 0bd881e (2026-10-06, „kliknięcie karty centruje drzewo, ikona «Podgląd» otwiera panel”) to cofnął. PRD FR-016 (`prd.md:144`) i `prd.md:192` nadal opisują kliknięcie = panel — rozjazd dokumentacji, istotny dla decyzji o kliknięciu karty przodka.
- Pasek boczny: `mainNavItems` — Drzewo, Osoby, Źródła, Miejscowości, Parafie (`resources/js/components/app-sidebar.tsx:29-59`). Aktywność przez `isCurrentOrParentUrl` = porównanie prefiksu ścieżki (`resources/js/hooks/use-current-url.ts:62-67`, `startsWith`), więc ścieżka pod `/tree/...` (np. `/tree/{person}/ancestors`) podświetli „Drzewo”, a np. `/ancestors/{person}` nie. Punktu wejścia do widoku przodków dziś nie ma; naturalni kandydaci: nagłówek `tree/show.tsx` (:80-103) i akcje nagłówka panelu osoby (`person-modal-header.tsx:23,81-84`).

### 7. Testy

- Pest: nowy endpoint → `tests/Feature/<Domain>Test.php` z happy path i 404 dla cudzej osoby (CLAUDE.md, `context/foundation/test-plan.md:94,99`) + przypadek w `ownershipRouteCases()` (§6.5, `test-plan.md:175`). Kandydat na test stałej liczby zapytań jak `TreeTest.php:498`.
- Vitest: tylko `resources/js/**/*.test.ts` (`vite.config.ts:42-43`), brak testów komponentów — logikę układu pedigree warto wydzielić jako czystą funkcję w `resources/js/lib/` (wzorzec: `fit-view.test.ts`, `centre-hints.test.ts`, `side-panel-url.test.ts`).
- E2E (`tests/e2e/`): fixture `suffix` + `cleanupBySuffix` (`tests/e2e/support/fixtures.ts`), helpery danych `createPerson:144`, `createParent:178` (`tests/e2e/support/api.ts`), nawigacja `gotoTree:86`, `openPerson:92` (`tests/e2e/support/navigation.ts`). Najbliższy szablon wielopokoleniowy: `tests/e2e/tree-card-recentre.spec.ts`. `test-plan.md:233-234` wyklucza testowanie wyglądu — E2E asercje na danych/nawigacji, nie na układzie.

## Code References

- `app/Support/ParentChildGraph.php:23-36` — jedno zapytanie grafu drzewa
- `app/Support/ParentChildGraph.php:75-78,92-114` — `ancestorsOf` / BFS, płaska lista bez głębokości
- `app/Actions/Relations/CreateParentChildRelation.php:103-163` — reguły relacji (≤2 rodziców, brak cyklu, płeć)
- `app/Actions/Trees/BuildFamilySheet.php:146-171,328-344,392-422,477-484` — pokolenia drzewa, ładowanie osób, sloty rodziców, `rank()`
- `app/Http/Controllers/TreeController.php:39,43-49` — autoryzacja fokusu, propsy strony
- `app/Http/Resources/SheetPersonResource.php:30-49` — dane karty
- `app/Policies/PersonPolicy.php:72-77` — `denyAsNotFound()`
- `app/Http/Middleware/HandleInertiaRequests.php:60-64,87-106` — `personPreview` z `?person=`
- `app/Http/Controllers/PersonController.php:140-165` — `urlAfterDelete` (wyjątek tylko `tree.show`)
- `routes/web.php:29-30` — trasy drzewa
- `tests/Feature/OwnershipBoundariesTest.php:101-102,147-177,181-199` — przypadki 404, test kompletności, strony z panelem
- `tests/Feature/TreeTest.php:498,651` — stała liczba zapytań; pośredni test `ancestorsOf`
- `resources/js/components/zoom-canvas.tsx:15-24,109-153,207-219,285-295` — ogólne płótno dopasowania
- `resources/js/lib/fit-view.ts:2,5,23-44` — `chooseFitView`, `TREE_FIT_FLOOR`, `TREE_FIT_MAX`
- `resources/js/components/tree/family-sheet.tsx:21-35,87-119,302-305` — siatka, `PairConnector`, klucz płótna
- `resources/js/components/tree/sheet-person-card.tsx:55-105` — karta: link do `tree.show` + ikona panelu
- `resources/js/components/tree/card-styles.ts:6` — `cardSurface` (`min-h-24`)
- `resources/js/components/persons/person-panel.tsx:31-33` — rzutowanie `props.sheet`
- `resources/js/layouts/app-layout.tsx:19-41` — panele w layoucie
- `resources/js/hooks/use-current-url.ts:62-67` — aktywność nawigacji po prefiksie
- `resources/js/types/tree.ts:1-25,55-67` — typy karty i arkusza

## Architecture Insights

- Wzorzec odczytu drzewa: graf id w pamięci (1 zapytanie) → wybór id → jedno zapytanie osób z `events` → `SheetPersonResource::make()->resolve()`. Widok przodków mieści się w tym wzorcu (3 zapytania niezależnie od głębokości).
- Stan paneli żyje w URL (`?person=`, `?marriage=`) i jest rozwiązywany po stronie serwera w jednym miejscu; nowa strona dostaje panel osoby za darmo, jeśli działa pod `AppLayout`.
- `ZoomCanvas` jest celowo ogólny (review F8 S-05 uczynił `floor`/`max` propsami z myślą o S-15, `context/archive/2026-10-05-tree-fits-screen/reviews/impl-review.md:118-122`); `FamilySheet` celowo nie jest — to arkusz rodziny z akcjami edycji.
- Logika układu i dopasowania jest w czystych funkcjach `resources/js/lib/*` testowanych Vitestem; komponenty nie mają testów jednostkowych.
- Konwencje: strona w `resources/js/pages/<domain>/…`, komponenty w `resources/js/components/<domain>/`, trasy przez Wayfinder (`@/routes/tree`), teksty UI po polsku. `.ai/rules/` w repo nie istnieje — reguły są w `CLAUDE.md`.

## Historical Context (from prior changes)

- `context/archive/2026-09-28-graphical-tree-view/plan.md:89`, `plan-brief.md:56-58` — wykres przodków/potomków i drzewo z przybliżaniem świadomie poza zakresem; arkusz 3 pokolenia + dzieci (`plan-brief.md:41`), kolejność rodziców (`:45`), kliknięcie = przecentrowanie (`:34`). Wciąż aktualne (zgodne z `family-sheet.tsx` i 0bd881e).
- `context/archive/2026-09-28-graphical-tree-view/research.md:95-180` — przegląd konwencji pedigree: Gramps do 5 pokoleń, domyślnie 3 (:117-127); MyHeritage 6 pokoleń, kliknięcie otwiera panel profilu (:145-152); FamilySearch poziomo przodkowie w prawo, pionowo w górę (:157-160); webtrees — układ poziomy lepiej znosi wiersze 16–64 osób (:174-177); powtórzony przodek jako numer odsyłacza w „Moi Przodkowie” (:99-100). Źródło zewnętrzne sprzed miesiąca, nie weryfikowane ponownie.
- `context/archive/2026-10-05-tree-fits-screen/plan-brief.md:32`, `plan.md:94` — jeden `ZoomCanvas` „dla S-05, S-06 i S-15”, linie w CSS, bez SVG i nowych zależności. Aktualne.
- `context/archive/2026-10-05-tree-fits-screen/plan.md:23,311`, `follow-ups/review-fixes.md:5`, `reviews/impl-review.md:32-43` — brak ręcznego przybliżania; ryzyko „nie widać całości” przy widoku na kotwicy; F1 odroczone do S-15. Aktualne (otwarte).
- `context/archive/2026-10-05-person-side-panel/plan.md:5,162` — kliknięcie karty otwiera panel. **Nieaktualne**: cofnięte commitem 0bd881e (2026-10-06); `plan-brief.md:23` (panel w layoucie na każdej stronie) — aktualne.
- `context/archive/2026-10-04-critical-flows-e2e-guard/plan-brief.md:23-24` — izolacja danych E2E: wspólny użytkownik, sufiks, wejście przez `/tree/{id}`, dane przez `page.request`. Aktualne.
- `context/foundation/roadmap.md:313` — otwarte pytanie „czy kliknięcie przodka otwiera panel osoby (zależność od S-06)” — zależność już spełniona (S-06 `done`, `roadmap.md:52`).

## Related Research

- `context/archive/2026-09-28-graphical-tree-view/research.md` — konwencje widoków pedigree w innych narzędziach.
- `context/archive/2026-10-05-tree-fits-screen/research.md` — mechanizm dopasowania i `react-zoom-pan-pinch`.

## Decyzje autora (2026-10-10)

Odpowiedzi na Open Questions 1–4, zebrane po badaniu:

1. **Liczba pokoleń:** domyślnie 4, maksymalnie 6.
2. **Kierunek układu:** przodkowie w górę (jak arkusz drzewa). Konsekwencja z szacunku w §5: przy maksimum najwyższy wiersz ma 32 karty (gdy osoba liczy się jako 1. pokolenie) albo 64 (gdy liczy się 6 pokoleń przodków) — sposób liczenia do ustalenia w planie; w obu wariantach szerokość rośnie szybko — rozmiar kart dalszych pokoleń i próg skali to zadanie dla `/10x-plan`.
3. **Dopasowanie:** start z dopasowaną całością (niższy `floor` tylko w tym widoku) oraz przyciski „Powiększ / Pomniejsz / Dopasuj” jak w `resources/js/components/sources/source-viewer.tsx:14-47`. Rozstrzyga follow-up F1 S-05 (`context/archive/2026-10-05-tree-fits-screen/follow-ups/review-fixes.md:5`); FR-018 i roadmapa S-15 pozostają zgodne z „przybliżaniem”, natomiast `ZoomCanvas` dziś wyłącza kółko/szczypanie i nie ma kontrolek — trzeba je dodać tylko dla tego widoku, nie zmieniając drzewa.
4. **Kliknięcie karty przodka:** otwiera panel osoby (`PersonPreviewLink` / `?person=`), a nie przecentrowuje — inaczej niż karta drzewa (0bd881e). `SheetPersonCard` linkuje do `tree.show`, więc widok przodków potrzebuje własnego wariantu karty albo propa sterującego celem kliknięcia. Przecentrowanie widoku przodków na innej osobie — do ustalenia w planie (np. akcja w panelu).

## Open Questions

Pytania 1–4 rozstrzygnięte w sekcji „Decyzje autora (2026-10-10)”. Pozostałe (5–7) do rozstrzygnięcia w `/10x-plan`.

Decyzje produktowe (właściciel: autor) — żadna nie jest zapisana w PRD, shape-notes ani roadmapie:

1. **Liczba pokoleń:** wartość domyślna i maksimum (np. 4 domyślnie, do 6–8?) oraz forma wyboru (przełącznik, lista, parametr w URL `?generations=`).
2. **Kierunek układu:** przodkowie w górę (jak arkusz drzewa) czy w prawo (lepiej znosi szerokie pokolenia).
3. **Czytelność 5+ pokoleń (follow-up F1):** niższy `floor` tylko dla tego widoku, mniejsze karty dla dalszych pokoleń, czy przywrócenie przybliżania/„Dopasuj” (jak w `source-viewer.tsx`) — i ujednolicenie FR-018/roadmapy po decyzji.
4. **Kliknięcie karty przodka:** przecentrowanie widoku przodków na tej osobie, otwarcie panelu, czy jak na drzewie (karta = przecentrowanie, ikona oka = panel).
5. **Punkt wejścia:** przycisk w nagłówku drzewa, akcja w panelu osoby, pozycja w pasku bocznym; oraz ścieżka URL (pod `/tree/...` podświetla „Drzewo”).
6. **Powtarzający się przodek (pedigree collapse):** pokazać kartę dwukrotnie czy oznaczyć odsyłaczem.
7. **Brakujący przodek:** pusty slot „Nieznany ojciec/matka” (statyczny) czy brak karty.

Kwestie techniczne dla `/10x-plan`: nowa metoda grafu z głębokością i pozycją; wyjątek w `urlAfterDelete`; przypadek w `ownershipRouteCases()`; nazwa propa inna niż `sheet`; ewentualne wydzielenie `PairConnector` i reguły kolejności rodziców; seeder lub dane E2E z 5+ pokoleniami do sprawdzenia czytelności.
