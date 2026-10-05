---
date: 2026-10-05T20:37:46+02:00
researcher: Claude (Opus 5.5)
git_commit: d8ecb6a6e2c7db8e31af5b2b84ef29df668d1880
branch: develop
repository: root-keeper
topic: "S-05 tree-fits-screen — jak dziś działa drzewo i co trzeba zmienić, by miało większe karty, dopasowanie do ekranu, przybliżanie i linie dzieci od właściwej pary"
tags: [research, tree, family-sheet, zoom, react-zoom-pan-pinch, side-panel, e2e]
status: complete
last_updated: 2026-10-05
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Autor rozstrzygnął FR-014: jeden związek naraz z przełącznikiem (Open Questions 1)."
---

# Research: S-05 tree-fits-screen

**Date**: 2026-10-05T20:37:46+02:00
**Researcher**: Claude (Opus 5.5)
**Git Commit**: d8ecb6a6e2c7db8e31af5b2b84ef29df668d1880
**Branch**: develop
**Repository**: root-keeper

## Research Question

Roadmapa S-05 (`context/foundation/roadmap.md:175-186`), PRD FR-013, FR-014, FR-026 i NFR 1366–2560 px (`context/foundation/prd.md:135-140,178,212`): autor ogląda drzewo z większymi kartami, które dopasowuje się do ekranu (na dużym wypełnia przestrzeń), przybliża je i oddala, a linia do dziecka wychodzi od właściwej pary; dodawanie rodzica, dziecka i małżonka ze slotów działa jak dziś.

Pytania: (1) jak drzewo renderuje się dziś i skąd biorą się „małe karty u góry ekranu” oraz „nieczytelne linie”; (2) czy dane pozwalają przypisać dziecko do pary; (3) jaka infrastruktura przybliżania i dopasowania istnieje i w jakim układzie strony żyje drzewo (także pod panel z S-06); (4) co chroni sloty i co pęknie przy przebudowie; (5) jakie wcześniejsze decyzje ta zmiana odwraca.

## Summary

- **Drzewo to „arkusz rodziny” z jednym związkiem naraz.** Jedna siatka CSS `grid min-w-[68rem] grid-cols-8` (`resources/js/components/tree/family-sheet.tsx:242`) w kontenerze `overflow-x-auto` (`:238-240`): 8 dziadków, 4 rodziców, para (osoba w centrum + wybrany partner), dzieci tylko wybranego związku. Inne związki są dostępne wyłącznie przez menu `UnionNode`, które robi wizytę z `?spouse=` (`resources/js/components/tree/union-node.tsx:79-97`).
- **Dlaczego drzewo zajmuje tylko górę ekranu:** w łańcuchu `<main>` → strona nic nie nadaje określonej wysokości (`resources/js/layouts/app/app-sidebar-layout.tsx:20` nie jest `flex-1`; strona to `space-y-6`, `resources/js/pages/tree/show.tsx:80-117`), a kolumny `1fr` rosną tylko wszerz. Karta nie ma stałej szerokości, ma `min-h-16` (`resources/js/components/tree/card-styles.ts:5-6`); para ma limit `max-w-72` (`family-sheet.tsx:319`).
- **Linie to obramowania i divy 1 px w procentach siatki, bez SVG** (`family-sheet.tsx:94,98,311,360,164-170`). W widocznym arkuszu jest dokładnie jedna para i jeden pień do dzieci (`:356-368`), więc dziś linia zawsze wychodzi od jedynej widocznej pary. Przy więcej niż 4 dzieciach (`CHILDREN_PER_ROW = 4`, `:20`) każdy rząd z >1 dzieckiem dostaje własną belkę (`:162-170`), ale nic nie łączy rzędów od drugiego z pniem pary (pień kończy się nad pierwszym rzędem, `:356-368`).
- **Baza nie wie, z którego małżeństwa jest dziecko.** `parent_child_relations` nie ma kolumny związku; związek wyprowadza się z pary rodziców: `BuildFamilySheet` grupuje dzieci osoby w centrum po „drugim rodzicu” (`app/Actions/Trees/BuildFamilySheet.php:74-79`), a do frontendu wysyła tylko dzieci wybranego związku (`:131`) i `children_count` pozostałych (`:112`). Pokazanie kilku związków naraz wymaga zmiany kształtu propsa `sheet` (backend ma wszystkie dane w `ParentChildGraph`).
- **FR-014 w praktyce oznacza decyzję o widoku wielu związków naraz.** Rozstrzygnięcie FR-014 odpowiada na zarzut „dzieci z różnych małżeństw — czytelne linie nie mówią, z którego związku” (`context/foundation/shape-notes.md:216-218`). Przy jednym widocznym związku ten problem nie występuje w obecnym widoku. Wcześniejsza decyzja brzmiała: „No showing all spouses at once; one partner at a time, with a switch” (`context/archive/2026-09-28-graphical-tree-view/plan.md:93`). **Rozstrzygnięte 2026-10-05 przez autora: zostaje jeden związek naraz z przełącznikiem;** FR-014 realizują czytelniejsze linie od widocznej pary do wszystkich rzędów dzieci (zob. Open Questions 1).
- **Przybliżanie: biblioteka już jest i ma API dopasowania.** `react-zoom-pan-pinch` 4.2.0 (`node_modules/react-zoom-pan-pinch/package.json:3`) eksportuje `fitToView` (`dist/index.d.ts:64`), `zoomToElement` (`:58`) i prop `fitOnInit` (`:277`). Jedynym konsumentem jest `resources/js/components/sources/source-viewer.tsx:14-60` (przyciski „Powiększ”, „Pomniejsz”, „Dopasuj”). Według kodu źródłowego biblioteki zmiana rozmiaru kontenera po inicjalizacji tylko wyrównuje transformację do granic i nie dopasowuje ponownie (`src/core/resize/resize.logic.ts:179-250`, odczyt kodu, nie sprawdzone w przeglądarce) — zwężenie przez panel (S-06) wymaga jawnego `fitToView()`.
- **Sloty chroni E2E skupione w jednym pliku lokatorów** `tests/e2e/support/navigation.ts`, a kształt propsa `sheet` przypinają `tests/Feature/TreeTest.php` i `tests/Feature/WriteTargetsTest.php`. Zmiana układu bez zmiany nazw dostępności i bez zmiany payloadu zostawia je zielone; pokazanie wszystkich związków naraz łamie założenia `switchUnion` (`navigation.ts:45-53`) i asercje `TreeTest.php:133-192`.

## Detailed Findings

### 1. Obecne renderowanie arkusza

- Trasa: `routes/web.php:26-27` → `TreeController::index/show` (`app/Http/Controllers/TreeController.php:20-50`) → `BuildFamilySheet::handle()` (`app/Actions/Trees/BuildFamilySheet.php:53`).
- Strona: nagłówek `h1` z imieniem osoby w centrum (`resources/js/pages/tree/show.tsx:82`), akcje („Wróć do probanta” `:89-95`, „Podgląd” `:103-107`), potem `<FamilySheet>` (`:111`).
- Siatka (`family-sheet.tsx:242`), jedna dla wszystkich rzędów:
  - dziadkowie: 8 × `col-span-1` (`:244-265`), łączniki par `col-span-2` (`:267-278`);
  - rodzice: 4 × `col-span-2` (`:281-296`), łączniki `col-span-4` z akcją „Połącz jako małżeństwo” (`:298-305`, `:113-128`);
  - para: zagnieżdżona siatka 8 kolumn, każda strona `col-span-4 mx-auto w-full max-w-72` (`:308-354`), `UnionNode` absolutnie w środku (`:343-353`); kolejność stron z `focus_side` (`:211-214`);
  - dzieci: rzędy po 4 (`:20,136-138`), centrowane klasami `CHILDREN_ROW_START` (`:23-28`); slot „Dodaj dziecko” w rzędzie przy ≤2 dzieciach w ostatnim rzędzie, w osobnym rzędzie przy >2 (`:156-191`); brak dzieci → tekst „Brak dzieci w tym związku.” (`:140-148`).
- Nagłówki pokoleń są `sr-only` `h3` (`:73-75`).
- Przewijanie: `min-w-[68rem]` (1088 px) + `overflow-x-auto`; `useCentreInScroll` (`resources/js/hooks/use-centre-in-scroll.ts:16-73`) ustawia `scrollLeft` na osobę w centrum przy zmianie klucza `focus:selected_union` (`family-sheet.tsx:229-233`) i steruje maskami krawędzi (`:234-235`). Przy 1366 px i rozwiniętym menu treść ma ok. 1070 px, czyli mniej niż `min-w` — drzewo przewija się już dziś (arytmetyka z `context/archive/2026-10-04-side-panel-shell/research.md:32`, niezmierzone).
- Szerokości kart wynikają z kolumn `1fr`: przy 1088 px kolumna ma ok. 129 px (dziadek), rodzic/dziecko ok. 266 px, para ≤288 px — szacunek z arytmetyki siatki, niezmierzony.
- Kliknięcie karty innej niż centrum to `<Link href={treeShow(person.id, {query: {spouse}})}>` (`resources/js/components/tree/sheet-person-card.tsx:63-71`); karta centrum to zwykły div (`:58-61`); każda karta ma ikonę „Podgląd” z `aria-label="Podgląd: {display_name}"` (`:73-86`). S-06 zmienia kliknięcie karty na otwarcie panelu (`roadmap.md:188-199`) — poza zakresem S-05.

### 2. Linie

- Kolory: tokeny `--tree-line` / `--tree-meta` (`resources/css/app.css:70-71,120-121,165-166`).
- `PairConnector` (`family-sheet.tsx:78-110`): klamra `absolute inset-x-[25%] top-0 h-3 rounded-b-md border-x border-b` (`:94`) + pień `left-1/2 w-px` (`:98`); rysowany, gdy któryś slot pary nie jest pusty (`:87`).
- Linia pary: `absolute top-1/2 left-[25%] right-[25%] h-px` (`:309-314`), gdy partner nie jest pusty.
- Pień do dzieci: wiersz `h-[30px]` (lub `h-12` z przyciskiem „Połącz jako małżeństwo”) z `left-1/2 w-px` (`:356-368`).
- Belka dzieci: `h-[13px] rounded-t-sm border-x border-t` z marginesami `(4+1-n)*12.5%` (`:162-170`), rysowana w rzędzie z >1 dzieckiem.
- Geometria jest stała w procentach siatki, nie liczona z położeń elementów. Dowolny układ z wieloma parami lub zmienną liczbą kolumn wymaga innego sposobu rysowania linii (np. SVG liczone z pozycji kart albo siatka z łącznikami per para) — to decyzja planu.

### 3. Model danych związków i dzieci

- `parent_child_relations(tree_id, parent_id, child_id)` z unikalnym `(parent_id, child_id)`; bez kolumny związku i bez roli matka/ojciec (rola z płci rodzica). Małżeństwo to `events` typu `marriage` + `event_participants` (role `subject|spouse`). Reguły ograniczają dziecko do 2 rodziców (`BuildFamilySheet.php:274`).
- Wyprowadzanie związków (`BuildFamilySheet.php:53-136`):
  - partnerzy z małżeństw, deduplikowani per partner (`:58-72`);
  - dzieci osoby w centrum grupowane po drugim rodzicu, `0` = nieznany (`:74-79`);
  - kolejność: małżeństwa, potem współrodzice bez małżeństwa (`is_marriage: false`), potem klucz `unknown` dla dzieci bez drugiego rodzica (`:94-125`);
  - `selectUnion` z `?spouse=`, z fallbackiem na pierwszy (`:348-363`);
  - `children` zawiera wyłącznie dzieci wybranego związku (`:131-136`); pozostałe związki niosą tylko `children_count` (`:112`).
- Typ payloadu: `resources/js/types/tree.ts:22-63`. `SheetPerson` = `{id, display_name, gender, lifespan, is_deceased, age}` (`app/Http/Resources/SheetPersonResource.php:41-48`) — bez identyfikatorów rodziców.
- Wniosek: frontend nie może dziś narysować dzieci kilku związków; backend ma dane (`app/Support/ParentChildGraph.php:41` `parentsOf`, `childrenOf`), więc rozszerzenie payloadu (np. dzieci per związek) jest lokalne dla `BuildFamilySheet` i typu `FamilySheet`.
- Związki pokazywane są tylko dla osoby w centrum. Dla rodziców i dziadków wiadomo jedynie, czy para jest małżeństwem (`unmarried_parents`, `BuildFamilySheet.php:179-220`); ich inne związki i rodzeństwo osoby w centrum nie są pokazywane.

### 4. Przybliżanie i dopasowanie

- `react-zoom-pan-pinch` 4.2.0, jedyny konsument `source-viewer.tsx`:
  - `<TransformWrapper key={src} initialScale={1} minScale={0.5} maxScale={32} centerOnInit>` (`:14-20`), render-prop `{ zoomIn, zoomOut, resetTransform }` (`:21`);
  - przyciski `Button variant="outline" size="sm"` z tekstem „Powiększ”, „Pomniejsz”, „Dopasuj” (ten ostatni wywołuje `resetTransform()`) (`:24-47`);
  - viewport `min-h-0 flex-1 overflow-hidden` w `flex h-full flex-col`, `TransformComponent` 100%/100% (`:50-60`); komentarz `:11` wymaga określonej wysokości rodzica.
  - Wzorzec do skopiowania, nie gotowy komponent: skan jest dopasowany przez `object-contain`, drzewo potrzebuje treści w naturalnym rozmiarze dopasowanej transformacją.
- API v4 (`node_modules/react-zoom-pan-pinch/dist/index.d.ts`): `fitToView({ mode: 'contain'|'cover', minScale, maxScale, ... })` (`:64`), prop `fitOnInit` (`:277`), `zoomToElement(node | node[], …)` (`:58`), a także `centerView`, `useControls`, `keyboard`, `wheel.excluded`/`panning.excluded` (lista klas, na których gest się nie zaczyna — przydatne dla przycisków slotów). Według `src/core/handlers/handlers.utils.ts:226` przy `fitOnInit` `resetTransform()` wraca do stanu dopasowanego.
- Zmiana rozmiaru: biblioteka ma własny `ResizeObserver` (`src/core/instance.core.ts:330-338`), ale po inicjalizacji tylko wyrównuje transformację do granic (`src/core/resize/resize.logic.ts:179-250`). Czy `fitOnInit` dopasuje ponownie po zmianie treści (np. inna osoba w centrum bez zmiany `key`), nie zostało sprawdzone w przeglądarce. `minScale` ogranicza dopasowanie, więc musi być na tyle niski, by drzewo mogło się zmniejszyć.
- Brak ogólnego hooka rozmiaru elementu w `resources/js/hooks/` i `resources/js/lib/` (sprawdzone katalogi); `@container` Tailwind jest już używany (`resources/js/components/sources/source-panel.tsx:77,156`). Brak bibliotek grafów/układu (d3, elkjs, dagre, react-flow) w `package.json`.

### 5. Układ strony i panel boczny (S-06)

- Powłoka panelu: `resources/js/components/side-panel.tsx` (`SidePanelSlotProvider` `:31`, `SidePanelSlot` `display: contents` `:93-97`, `SidePanel` `:117-125`); na desktopie `md:sticky md:h-[calc(100svh-(--spacing(4)))]`, szerokość `standard` `clamp(24rem,30vw,36rem)`, `wide` `clamp(40rem,55vw,72rem)` (`:211-215`).
- Panel jest rodzeństwem `<main>` w wierszu flex `SidebarProvider` (`resources/js/components/ui/sidebar.tsx:139`); `<main>` ma `flex-1 min-w-0 overflow-x-clip` (`app-sidebar-layout.tsx:18`), więc zwęża się zwykłym flexboksem.
- `tree/show.tsx` nie używa jeszcze panelu: podgląd osoby to modal (`show.tsx:104`, `resources/js/layouts/app-layout.tsx:20`).
- Wysokość: `SidebarInset` ma tylko `min-h-svh` (w wariancie `inset` `md:min-h-[calc(100svh-(--spacing(4)))]` + `m-2`, `ui/sidebar.tsx:307-308`); przewija się dokument. Wewnątrz `<main>`: `AppMobileHeader` `h-14 md:hidden`, potem `div.min-w-0 px-6 py-6 md:px-4` (`app-sidebar-layout.tsx:20`) z okruszkami i stroną. `h-full` w tym łańcuchu nie zadziała; opcje to `calc(100svh − …)` albo łańcuch `flex flex-col` + `flex-1 min-h-0` dla strony drzewa.
- Budżet szerokości przy 1366 px: ok. 1070 px treści z rozwiniętym menu, ok. 670 px obok panelu `standard` (`context/archive/2026-10-04-side-panel-shell/research.md:32,67`, arytmetyka).

### 6. Testy chroniące drzewo

- **E2E** (Playwright, Desktop Chrome 1280×720, `playwright.config.ts:27-33` — poniżej dolnej granicy NFR 1366 px). Wszystkie lokatory drzewa w `tests/e2e/support/navigation.ts`:
  - `treeSlot`: `getByRole('button', { name: 'Dodaj {ojca|matkę|dziecko|małżonka}: {who}', exact: true })` (`:13-22`) — z `aria-label` w `resources/js/components/tree/unknown-person-card.tsx:34` ustawianym przez `parent-slot-dialog.tsx:57`, `add-child-dialog.tsx:40`, `add-spouse-dialog.tsx:46`;
  - `slotDialog`: `getByRole('dialog', { name: 'Dodaj X — who' })` (`:25-31`);
  - `switchUnion` / `chooseUnionMenuItem`: przycisk `/^Związek \d z \d/`, potem `menuitemradio` / `menuitem` „Dodaj małżonka” (`:45-62`; `union-node.tsx:99-110,138-182`);
  - `gotoTree`: `/tree/{id}` i oczekiwanie na `heading` poziomu 1 (`:75-78`).
  - Asercje końcowe są na danych (sekcje „Rodzina” w dialogu osoby), nie na wyglądzie (`tests/e2e/tree-slot-*.spec.ts`; `context/foundation/test-plan.md:208-209`).
  - Co pęknie: zmiana nazw dostępności slotów lub tytułów dialogów; drugi element o tej samej nazwie (tryb strict — np. slot powielony per związek, minimapa); usunięcie przełącznika związków albo ról `menuitemradio`/`menuitem`. Przy transformacji przybliżenia `toBeVisible()` przechodzi dla elementu poza ekranem, a `.click()` może chybić, bo Playwright nie przewija elementu przesuniętego transformacją CSS — przewidziane w `context/archive/2026-10-04-critical-flows-e2e-guard/research.md:123` („może wymagać kroku »Dopasuj« przed kliknięciem”). Poprawki lokatorów idą wyłącznie do `support/navigation.ts`, nie do asercji (`context/archive/2026-10-04-critical-flows-e2e-guard/plan.md:24`).
- **Pest**: `tests/Feature/TreeTest.php` czyta `props.sheet` (`:95-102`) i przypina klucze `unions[]`, `selected_union`, `partner`, `parents`, `grandparents`, `children`, `focus_side`, `add_child`, `add_spouse`, `unmarried_parents`; w tym dzieci per wybrany związek (`:133-192`) i stałą liczbę zapytań (`:498`). `tests/Feature/WriteTargetsTest.php:53-152,194-216` buduje żądania dialogów z `sheet.add_child.parents`, `parents.partner[i].parent_of`, `partner.person.id`. `tests/Feature/MarriagesTest.php:302-330` i `tests/Feature/ParentChildRelationsTest.php:344-376` sprawdzają przekierowania na `tree.show?spouse=`.
- **Vitest**: brak testów komponentów drzewa; `vite.config.ts:42-44` obejmuje tylko `resources/js/**/*.test.ts`, brak jsdom/Testing Library w `package.json`.
- Wygląd celowo nie jest testowany automatycznie; przebudowy UI ocenia się jednorazowymi skryptami zrzutów w folderze zmiany (`test-plan.md:67,229-234`; wzorzec `context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs` na `TreeViewDemoSeeder`).

## Code References

- `resources/js/components/tree/family-sheet.tsx:238-242` — kontener z przewijaniem i siatka `min-w-[68rem] grid-cols-8`
- `resources/js/components/tree/family-sheet.tsx:78-110` — `PairConnector` (klamra + pień z obramowań)
- `resources/js/components/tree/family-sheet.tsx:130-198` — rzędy dzieci po 4, belka i slot „Dodaj dziecko”
- `resources/js/components/tree/family-sheet.tsx:308-368` — wiersz pary, `UnionNode`, pień do dzieci
- `resources/js/components/tree/card-styles.ts:5-6` — powierzchnia karty `min-h-16 px-7 py-2.5`
- `resources/js/components/tree/sheet-person-card.tsx:58-86` — kliknięcie karty (Link) i „Podgląd”
- `resources/js/components/tree/union-node.tsx:79-182` — przełącznik związków (wizyta z `?spouse=`)
- `resources/js/hooks/use-centre-in-scroll.ts:16-73` — centrowanie przez `scrollLeft`
- `resources/js/pages/tree/show.tsx:80-117` — strona drzewa
- `app/Actions/Trees/BuildFamilySheet.php:53-136,348-363` — wyprowadzanie związków i wybór związku
- `app/Support/ParentChildGraph.php:23,41,75` — graf rodzic–dziecko (`forTree`, `parentsOf`, `ancestorsOf`)
- `resources/js/types/tree.ts:22-63` — typ `FamilySheet`
- `resources/js/components/sources/source-viewer.tsx:11-60` — jedyne użycie `react-zoom-pan-pinch`
- `node_modules/react-zoom-pan-pinch/dist/index.d.ts:58,64,277` — `zoomToElement`, `fitToView`, `fitOnInit`
- `resources/js/components/side-panel.tsx:211-215` — wymiary panelu
- `resources/js/layouts/app/app-sidebar-layout.tsx:16-25` — łańcuch układu bez określonej wysokości
- `tests/e2e/support/navigation.ts:13-78` — wszystkie lokatory drzewa w E2E
- `tests/Feature/TreeTest.php:95-192` — kontrakt propsa `sheet`

## Architecture Insights

- Kształt drzewa jest w całości ustalany na serwerze (`BuildFamilySheet` → props `sheet`), a frontend tylko rozkłada sloty w siatce. Rozszerzenie widoku o kilka związków to zmiana kontraktu serwera, a nie tylko CSS.
- Nazwy dostępności slotów i przełącznika związków są publicznym kontraktem dla E2E; układ wizualny nie jest. Przebudowa układu jest bezpieczna dla E2E, dopóki zostają nazwy i każdy slot występuje raz.
- Przybliżanie musi być wielokrotnego użytku: S-15 (widok przodków) ma używać „tego samego mechanizmu dopasowania i przybliżania” (`prd.md:150-152`), a S-06 zwęża drzewo panelem i potrzebuje ponownego dopasowania po zmianie szerokości (`side-panel-shell/plan.md:38`).
- Karta powinna zostawić miejsce na zdjęcie lub inicjały z S-16 (`roadmap.md:315-325`).

## Historical Context (from prior changes)

- `context/archive/2026-09-28-graphical-tree-view/plan.md:84` — „pan/zoom is deliberately **not** used (the user chose horizontal scroll)”. **Odwrócone** przez FR-013 (`prd.md:135-137`).
- `context/archive/2026-09-28-graphical-tree-view/plan.md:89-91` — brak drzewa z pan/zoom, brak algorytmu układu i biblioteki grafów (bez nowej zależności npm). Część o pan/zoom odwrócona przez FR-013; część „bez nowej zależności” nadal obowiązuje (`CLAUDE.md`: nowe zależności wymagają zgody), a `react-zoom-pan-pinch` już jest zależnością.
- `context/archive/2026-09-28-graphical-tree-view/plan.md:93` — „No showing all spouses at once; one partner at a time, with a switch”. **Nadal obowiązuje** — potwierdzone przez autora 2026-10-05 (Open Questions 1).
- `context/archive/2026-09-28-tree-family-sheet-ui/plan-brief.md:34` — linia pary to dane: linia focus↔partner, zejście z jej środka, dzieci wyśrodkowane. Nadal aktualne dla jednego związku.
- `context/archive/2026-09-28-tree-family-sheet-ui/plan.md:166-172` — zmiana `min-w-[72rem]` → `min-w-[68rem]`, by zmieścić się przy 1440 px; przy 1366 px już nie wystarcza (sekcja 1).
- `context/archive/2026-09-29-tree-sheet-variant-c/plan-brief.md:29,35` — węzeł związku jako menu także przy jednym związku; umiejscowienie slotu „Dodaj dziecko”.
- `context/archive/2026-10-04-side-panel-shell/plan.md:38` — przecentrowanie drzewa po zwężeniu odłożone do S-05/S-06.
- `context/archive/2026-10-04-critical-flows-e2e-guard/research.md:123`, `plan.md:24` — S-05 może wymagać kroku „Dopasuj” przed kliknięciem slotu; poprawki tylko w `support/navigation.ts`.

## Related Research

- `context/archive/2026-09-28-graphical-tree-view/research.md` — porównanie wariantów (arkusz vs drzewo z pan/zoom; famtreejs, family-chart, webtrees).
- `context/archive/2026-10-04-side-panel-shell/research.md` — budżet szerokości przy 1366 px z panelem.
- `context/archive/2026-10-04-critical-flows-e2e-guard/research.md` — wpływ S-04 – S-06 na testy E2E.

## Open Questions

1. ~~**Co znaczy „linia do dziecka od właściwej pary” (FR-014)?**~~ — **Rozstrzygnięte 2026-10-05 (autor): (a) jeden związek naraz z przełącznikiem.** Decyzja `graphical-tree-view/plan.md:93` nadal obowiązuje; FR-014 = czytelniejsze linie od widocznej pary do wszystkich rzędów dzieci; kontrakt propsa `sheet` i lokatory E2E bez zmian. Rozważane opcje: (a) nadal jeden związek naraz z przełącznikiem — FR-014 sprowadza się do czytelniejszych linii (łączniki dla wszystkich rzędów dzieci, wyraźna linia od pary), payload i E2E bez zmian; (b) wszystkie związki osoby w centrum obok siebie, każdy z własną parą i dziećmi — zmiana payloadu (`TreeTest`, `WriteTargetsTest`), układu linii i lokatorów E2E (przełącznik związków, unikalność slotów „Dodaj dziecko”), odwraca `graphical-tree-view/plan.md:93`.
2. **Jak drzewo mieści się przy 1366 px z wieloma dziećmi i kilkoma małżeństwami — dopasowanie zmniejsza karty czy przechodzi w przybliżanie?** Owner: `/10x-plan` (`roadmap.md:184`). Dane do decyzji: sekcje 1, 4, 5.
3. **Czy przybliżanie zastępuje przewijanie w poziomie i `useCentreInScroll`?** Owner: `/10x-plan`. Przy transformacji centrowanie osoby to `zoomToElement`/`fitToView`, a nie `scrollLeft`.
4. **Zachowanie biblioteki do sprawdzenia w przeglądarce:** ponowne dopasowanie po zmianie treści przy niezmienionym `key`, odczucie `limitToBounds` przy oddaleniu, przechwytywanie kliknięć przez warstwę przesuwania (`panning.excluded`). Owner: implementacja.
5. **Rozdzielczość E2E 1280 px vs NFR 1366 px:** testy slotów będą działać poniżej dolnej granicy NFR. Owner: `/10x-plan` (czy zmienić viewport projektu Playwright).
