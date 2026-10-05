# S-05 Drzewo wypełnia ekran — Implementation Plan

## Overview

Arkusz rodziny przestaje być siatką z poziomym przewijaniem u góry strony. Staje się obszarem, który wypełnia okno, dopasowuje drzewo do ekranu, pozwala je przybliżać i oddalać, ma większe karty i rysuje jedną linię od widocznej pary do każdego dziecka. Realizuje FR-013 i FR-014, zachowuje FR-026 (sloty) oraz kontrakt propsa `sheet`. Dostarcza też komponent przybliżania wielokrotnego użytku dla S-06 (zwężenie panelem) i S-15 (widok przodków).

## Current State Analysis

- Drzewo to jedna siatka `grid min-w-[68rem] grid-cols-8` w kontenerze `overflow-x-auto` (`resources/js/components/tree/family-sheet.tsx:238-242`), centrowana przez `scrollLeft` w `useCentreInScroll` (`resources/js/hooks/use-centre-in-scroll.ts:16-73`, jedyny konsument: `family-sheet.tsx:229`). Przy 1366 px treść ma ok. 1070 px, mniej niż `min-w`, więc drzewo przewija się już dziś.
- Drzewo zajmuje tylko górę ekranu, bo w łańcuchu `<main>` → strona nic nie ma określonej wysokości. `SidebarInset` ma tylko `min-h-svh` (`resources/js/components/ui/sidebar.tsx:307-308`), a kontener treści `div.min-w-0 px-6 py-6` nie jest elementem flex (`resources/js/layouts/app/app-sidebar-layout.tsx:20`). Strona drzewa to `space-y-6` (`resources/js/pages/tree/show.tsx:80-117`).
- Karty: `cardSurface` = `min-h-16 px-7 py-2.5`, nazwa `text-sm`, metadane `text-xs` (`resources/js/components/tree/card-styles.ts:5-6`, `sheet-person-card.tsx:26-43`). Dziadkowie `col-span-1`, rodzice i dzieci `col-span-2`, para `col-span-4 max-w-72`.
- Linie to obramowania i divy 1 px w procentach siatki (`family-sheet.tsx:78-110,162-170,308-368`). Dzieci układane są w rzędach po 4 (`CHILDREN_PER_ROW`, `:20`). Pień pary kończy się nad pierwszym rzędem, a rzędy od drugiego nie mają połączenia z parą. To właśnie jest błąd FR-014 przy jednym widocznym związku.
- `react-zoom-pan-pinch` 4.2.0 jest już zależnością. Jedyny konsument to `resources/js/components/sources/source-viewer.tsx` (przyciski „Powiększ”, „Pomniejsz”, „Dopasuj”). API: `fitToView({ minScale, maxScale })`, `zoomToElement(node, { scale })`, `onInit`, `panning.excluded`, `doubleClick.disabled` (`node_modules/react-zoom-pan-pinch/dist/index.d.ts:58,64,200-222,255-345`). Transformacja to 2D `translate() scale()` bez `will-change` (`src/utils/styles.utils.ts:14`), więc tekst przy skali > 1 powinien zostać ostry.
- Biblioteka po inicjalizacji nie dopasowuje ponownie. Przy zmianie rozmiaru tylko wyrównuje do granic (`src/core/resize/resize.logic.ts:179-250`). Nie tłumi też kliknięcia po przeciągnięciu (`src/core/pan/*` nie obsługuje `click`).
- Ochrona: E2E slotów (`tests/e2e/tree-slot-{parent,child,spouse}.spec.ts`) z lokatorami w jednym pliku `tests/e2e/support/navigation.ts`, viewport `Desktop Chrome` 1280×720 (`playwright.config.ts:27-33`). Kształt `sheet` przypinają `tests/Feature/TreeTest.php:95-192` i `tests/Feature/WriteTargetsTest.php`. Brak testów komponentów drzewa w Vitest; `vite.config.ts` obejmuje `resources/js/**/*.test.ts`.

## Desired End State

Na stronie `/tree/{id}` drzewo zajmuje całą wysokość okna pod nagłówkiem strony. Przy wejściu dopasowuje się tak, by było widoczne w całości:
- skala jest ograniczona z góry do **150%**, więc na 2560 px drzewo jest powiększone, a mała rodzina nie robi się groteskowa;
- gdy całość wymagałaby skali poniżej **75%** (np. 1366 px z wieloma dziećmi albo obok panelu), drzewo startuje w 75% wyśrodkowane na parze w centrum.

Kółko myszy przybliża wokół kursora, a przeciągnięcie przesuwa. Pływający pasek w prawym dolnym rogu ma „Powiększ”, „Pomniejsz” i „Dopasuj”; ten ostatni pokazuje całe drzewo, także poniżej progu. Po zmianie szerokości obszaru drzewo dopasowuje się ponownie tylko wtedy, gdy autor go nie ruszał od ostatniego dopasowania. Zmiana osoby w centrum lub związku zawsze dopasowuje od nowa.

Karty są większe (wyższe, większa czcionka), a proporcje są jak dziś: dziadkowie mają połowę szerokości karty rodzica. Wszystkie dzieci wybranego związku stoją w jednym rzędzie pod jedną belką, a od pary prowadzi jeden pień do belki i zejście do każdego dziecka. Do 4 dzieci karty mają szerokość karty rodzica, od 5 szerokość karty dziadka. Sloty dodawania działają jak dziś, a E2E slotów przechodzą przy 1366×768.

Weryfikacja: zielone `check`, `types:check`, `test:js` (logika doboru skali), Pest drzewa, pełny zestaw E2E przy 1366×768 oraz zrzuty dla 1366, 1920 i 2560 px i 0, 3, 5, 8 i 10 dzieci z werdyktem w folderze zmiany.

### Key Discoveries:

- `fitToView`/`zoomToElement` przyjmują własne `minScale`/`maxScale`/`scale`, ale są ograniczone przez `minScale`/`maxScale` wrappera (`index.d.ts:62`). Wrapper musi mieć niski `minScale`, inaczej „Dopasuj” nie pokaże dużego drzewa.
- `fitOnInit` liczy tylko „contain” bez progu (`src/core/instance.core.ts:772-795`), więc logika progu musi iść przez `onInit` i własne wywołania.
- Radix `DropdownMenu` (przełącznik `UnionNode`, `resources/js/components/tree/union-node.tsx:99-110`) otwiera się na `pointerdown`, więc start przesuwania na nim otworzyłby menu.
- `setLayoutProps` jest już używany na stronie drzewa (`show.tsx:33`) do okruszków. Ten sam mechanizm może przekazać layoutowi „wypełnij wysokość”.
- `TreeViewDemoSeeder` ma pary z 5 i 3 dziećmi (`database/seeders/TreeViewDemoSeeder.php:159,177`) i nie jest używany w testach. Skrypt zrzutów liczy id jako przesunięcia od probanta (`context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs:7`), więc nowe rodziny trzeba dopisać na końcu.

## What We're NOT Doing

- Pokazywanie kilku związków naraz. Zostaje jeden związek z przełącznikiem (rozstrzygnięte 2026-10-05, `research.md` Open Questions 1). Bez zmian payloadu `sheet`, `BuildFamilySheet` i bazy.
- Panel osoby i zmiana kliknięcia karty (S-06). Przycisk „Podgląd” na karcie zostaje.
- Zdjęcia i inicjały na kartach (S-16). Karta ma tylko zostawiać na nie miejsce w przyszłości, bez rezerwacji teraz.
- Rysowanie linii w SVG, biblioteki układu grafów i jakiekolwiek nowe zależności npm.
- Minimapa, nawigacja klawiszami strzałek (`keyboard` w bibliotece pozostaje wyłączony), przybliżanie dwuklikiem (wyłączone, żeby dwuklik na slocie nie przybliżał).
- Trwałe zapamiętywanie skali i pozycji między wizytami.
- Drugi projekt Playwright na 2560 px. Wygląd na dużym ekranie oceniamy zrzutami.
- Testy pikselowe (`toHaveScreenshot`). Wygląd ocenia jednorazowy skrypt zrzutów (`test-plan.md:67,229-234`).
- Zmiany asercji w specach E2E. Ewentualne poprawki lokatorów idą tylko do `tests/e2e/support/navigation.ts`.

## Implementation Approach

Drzewo renderuje się w **stałym naturalnym rozmiarze**: siatka o stałej szerokości kolumn zamiast `min-w` + `1fr`, karty o stałych wymiarach. Całe dopasowanie i przybliżanie robi transformacja `react-zoom-pan-pinch` w nowym, ogólnym komponencie `ZoomCanvas`.

Decyzję o widoku startowym podejmuje czysta funkcja testowana w Vitest. Wybiera „całość” (skala `min(szer, wys)`, z limitem 150%) albo „kotwica” (75%, wyśrodkowane na elemencie kotwicy = wiersz pary). Komponent stosuje ją przy inicjalizacji i ponownie po zmianie rozmiaru, jeśli autor nie ruszał widoku.

Ponowne dopasowanie po zmianie osoby lub związku wynika z remontu wrappera przez `key` (jak `SourceViewer` z `key={src}`). Layout dostaje opcjonalny prop wypełnienia wysokości, ustawiany przez stronę drzewa.

Linie zostają w CSS. Przy jednym związku i jednym rzędzie dzieci geometria jest stała:
- pień od środka linii pary w dół do belki;
- belka złożona z półodcinków w komórkach dzieci (działa dla dowolnego N bez mierzenia położeń);
- zejście do każdej karty.

Kolejność faz chroni guardrail: najpierw obszar z dopasowaniem i viewport E2E 1366 (E2E zielone na docelowej szerokości), potem nowe karty i rząd dzieci, na końcu ocena wyglądu zrzutami.

## Critical Implementation Details

**Kliknięcie po przeciągnięciu.** Biblioteka nie tłumi kliknięcia, które kończy przesuwanie. Przeciągnięcie zakończone nad kartą (`<Link>`) albo slotem nawigowałoby lub otwierało dialog. `ZoomCanvas` musi tłumić `click` w fazie capture, gdy od `pointerdown` wskaźnik przesunął się dalej niż kilka pikseli. Przełącznik `UnionNode` otwiera menu na `pointerdown`, więc trzeba go wyłączyć z przesuwania przez `panning.excluded`.

**Fokus klawiatury.** Wrapper biblioteki ma `overflow: hidden`. Tab na elemencie poza kadrem przewija ten wrapper natywnie (`scrollLeft/Top ≠ 0`), a to rozjeżdża współrzędne transformacji. Obsługa `focusin` musi zerować scroll wrappera i przesuwać widok tak, by fokusowany element był w kadrze, bez zmiany skali.

**Kolejność dopasowania.** Dopasowanie startowe stosujemy dopiero, gdy wrapper i treść mają wymiary, czyli po `onInit` i pierwszym layoucie. Do tego czasu treść jest niewidoczna (`visibility`), żeby nie mignęła w skali 1. Przy zmianie rozmiaru nasze ponowne dopasowanie musi przyjść po wyrównaniu do granic, które robi sama biblioteka.

**Belka dzieci w CSS.** Półodcinki belki w komórkach stykają się tylko wtedy, gdy między komórkami nie ma `gap`. Odstęp między kartami robi padding wewnątrz komórki. Rząd dzieci jest wyśrodkowany na osi pary, a slot „Dodaj dziecko” stoi poza belką i nie przesuwa tej osi.

## Faza 1: Obszar przybliżania i dopasowania

### Overview

Drzewo (jeszcze w dzisiejszym wyglądzie) trafia do obszaru wypełniającego wysokość okna, z dopasowaniem, progiem, limitem, przybliżaniem i paskiem kontrolek. Poziome przewijanie i `useCentreInScroll` znikają, a E2E przechodzą przy 1366×768.

### Changes Required:

#### 1. Logika doboru widoku startowego

**File**: `resources/js/lib/fit-view.ts`, `resources/js/lib/fit-view.test.ts`

**Intent**: Czysta funkcja decydująca, czy pokazać całość, czy kotwicę na progu. Dzięki temu reguła progu i limitu jest testowalna bez przeglądarki.

**Contract**: `chooseFitView({ viewport: {width, height}, content: {width, height}, floor, max }): { kind: 'whole'; scale: number } | { kind: 'anchor'; scale: number } | null`, gdzie `scale` całości = `min(vw/cw, vh/ch, max)`. Wynik `anchor` ze `scale = floor` pojawia się, gdy skala całości < `floor`. Wynik `null` oznacza zerowe wymiary (brak layoutu). Stałe `TREE_FIT_FLOOR = 0.75`, `TREE_FIT_MAX = 1.5` eksportowane z tego modułu. Testy: całość mieści się powyżej progu, duży ekran ograniczony do `max`, poniżej progu wychodzi kotwica, wartość dokładnie na progu daje całość, zerowe wymiary dają `null`.

#### 2. Komponent `ZoomCanvas`

**File**: `resources/js/components/zoom-canvas.tsx`

**Intent**: Ogólny obszar przybliżania wypełniający rodzica, wielokrotnego użytku (drzewo teraz, widok przodków w S-15). Stosuje widok startowy z `chooseFitView`, dopasowuje ponownie po zmianie rozmiaru tylko, gdy widok nie był ruszany, i ma pływający pasek kontrolek.

**Contract**:
- Props: `children`, `anchorRef: RefObject<HTMLElement | null>` (element do wyśrodkowania przy kotwicy), opcjonalny `className`.
- Wrapper `TransformWrapper` z niskim `minScale` (ok. 0.2, żeby „Dopasuj” zmieściło duże drzewo), `maxScale` ok. 3, kółkiem przybliżającym (domyślne zachowanie biblioteki), `doubleClick.disabled`, `panning.excluded` z klasą znacznika dla elementów, od których przesuwanie nie może startować.
- Stan „ruszany”: ustawiany przez gesty autora (przesunięcie, kółko, szczypanie, „Powiększ”, „Pomniejsz”), zerowany przez dopasowanie startowe i „Dopasuj”. `ResizeObserver` na wrapperze ponownie stosuje ostatni tryb dopasowania (auto z progiem albo całość po „Dopasuj”) tylko, gdy stan nie jest „ruszany”.
- Pasek: `role="toolbar"` z `aria-label="Widok drzewa"`, absolutnie w prawym dolnym rogu obszaru, poza transformowaną treścią. Przyciski `Button variant="outline" size="sm"` z tekstem „Powiększ”, „Pomniejsz”, „Dopasuj” (jak `source-viewer.tsx:24-47`) i tłem, które nie przepuszcza linii drzewa. „Dopasuj” = całość (`fitToView` z `maxScale: TREE_FIT_MAX`), także poniżej progu.
- Tłumienie kliknięcia po przeciągnięciu i obsługa `focusin` (patrz Critical Implementation Details).
- Komentarz JSDoc w stylu `source-viewer.tsx:11`: komponent wypełnia rodzica, więc rodzic musi mieć określoną wysokość.

#### 3. Wypełnienie wysokości w layoucie

**File**: `resources/js/types/ui.ts`, `resources/js/layouts/app-layout.tsx`, `resources/js/layouts/app/app-sidebar-layout.tsx`

**Intent**: Strona może poprosić layout o określoną wysokość treści równą pozostałej wysokości okna. Bez tego `ZoomCanvas` nie ma wysokości, a drzewo nie wypełni ekranu. Mechanizm jest opcjonalny, a pozostałe strony działają bez zmian.

**Contract**: `AppLayoutProps` dostaje `fillHeight?: boolean`, przekazywane `AppLayout` → `AppSidebarLayout`. Gdy `true`: `<main>` (`AppContent`) ma określoną wysokość okna (z uwzględnieniem wariantu `inset`, czyli `calc(100svh - spacing(4))` na `md`) i nie przewija dokumentu, a kontener treści staje się `flex min-h-0 flex-1 flex-col` (okruszki nad, strona wypełnia resztę). Gdy `false` lub brak: klasy jak dziś.

#### 4. Strona i arkusz w obszarze

**File**: `resources/js/pages/tree/show.tsx`, `resources/js/components/tree/family-sheet.tsx`, `resources/js/components/tree/union-node.tsx`

**Intent**: Strona drzewa wypełnia wysokość i umieszcza arkusz w `ZoomCanvas`. Arkusz traci poziome przewijanie, maski krawędzi i `useCentreInScroll`, a dostaje stałą naturalną szerokość. Wrapper remontuje się przy zmianie osoby lub związku, więc każda wizyta dopasowuje od nowa.

**Contract**:
- `setLayoutProps({ breadcrumbs, fillHeight: true })` tylko w gałęzi z arkuszem (pusty stan „Nie wybrano probanta” bez zmian). Korzeń strony to `flex min-h-0 flex-1 flex-col gap-6`, a nagłówek z akcjami zostaje nad obszarem.
- `ZoomCanvas` dostaje `key={`${sheet.focus.id}:${sheet.selected_union ?? ''}`}`, ten sam klucz co dziś w `family-sheet.tsx:232`, i `anchorRef` = wiersz pary (dzisiejszy `focusRef` przeniesiony na cały wiersz związku).
- Siatka ma stałą naturalną szerokość (kolumny o stałej szerokości zamiast `min-w-[68rem]` + `1fr`). Kontener `overflow-x-auto` i maski `mask-*-from-90%` są usunięte.
- Wyzwalacz menu `UnionNode` dostaje klasę znacznika z `panning.excluded`.
- `resources/js/hooks/use-centre-in-scroll.ts` jest usunięty (jedyny konsument znika).

#### 5. Viewport E2E na dolnej granicy NFR

**File**: `playwright.config.ts`

**Intent**: Testy slotów strzegą drzewa na najmniejszym wspieranym ekranie (NFR 1366 px), a nie poniżej niego.

**Contract**: Projekt `chromium` ma `viewport: { width: 1366, height: 768 }` nadpisujące `devices['Desktop Chrome']`, z komentarzem wskazującym NFR. Specy bez zmian. Jeśli klik slotu chybia przez transformację, poprawka idzie wyłącznie do `tests/e2e/support/navigation.ts` (np. „Dopasuj” przed kliknięciem w `openSlot`), nigdy do asercji.

### Success Criteria:

#### Automated Verification:

- Testy logiki dopasowania przechodzą: `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Pest drzewa bez zmian i zielony: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/WriteTargetsTest.php`
- Pełny zestaw E2E zielony przy 1366×768: `npx playwright test`

#### Manual Verification:

- Na 1366×768 drzewo probanta zajmuje całą wysokość pod nagłówkiem strony, strona nie przewija się w pionie, a drzewo jest widoczne w całości albo (poniżej progu) wyśrodkowane na parze
- Kółko przybliża wokół kursora, przeciągnięcie przesuwa, a przeciągnięcie zakończone nad kartą nie nawiguje
- „Dopasuj” pokazuje całe drzewo. Po zmianie szerokości okna nieruszane drzewo dopasowuje się ponownie, a po ręcznym przybliżeniu widok zostaje
- Przejście do innej osoby i zmiana związku dopasowują drzewo od nowa
- Tab przez karty i sloty pokazuje fokusowany element w kadrze bez rozjechania widoku
- Sloty „Dodaj ojca/matkę/dziecko/małżonka” i menu związku działają kliknięciem jak dziś

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Większe karty i jeden rząd dzieci

### Overview

Karty rosną, a siatka dostaje docelowe naturalne wymiary w proporcjach jak dziś. Dzieci stoją w jednym rzędzie pod jedną belką, połączonej pniem z parą. Szerokość kart dzieci zależy od ich liczby.

### Changes Required:

#### 1. Wymiary kart

**File**: `resources/js/components/tree/card-styles.ts`, `resources/js/components/tree/sheet-person-card.tsx`, `resources/js/components/tree/unknown-person-card.tsx`

**Intent**: Większe karty (FR-013): wyższe, z większą czcionką nazwy i metadanych, wspólne dla kart osób i slotów. W skali 75% tekst ma pozostać czytelny.

**Contract**: `cardSurface` ma wyższą minimalną wysokość (rząd `min-h-24`) i proporcjonalnie większy padding. Nazwa ma `text-base` zamiast `text-sm`, metadane `text-sm` zamiast `text-xs`, a sloty mają czcionkę nazwy. Ikona „Podgląd” zostaje w rogu i nie nachodzi na tekst. Nazwy dostępności (`aria-label`, `sr-only`) bez zmian.

#### 2. Naturalna siatka arkusza

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Stała szerokość kolumny dobrana tak, by na 1366 px z rozwiniętym menu i typową rodziną (≤ 8 dzieci) całość mieściła się powyżej progu 75%. Proporcje jak dziś: dziadek = 1 kolumna, rodzic = 2, strona pary = 4.

**Contract**: Szerokość kolumny jest jedną stałą w komponencie (rząd 10rem, z odstępem rzędu 0.75rem). Szerokość rzędu dziadków (8 kolumn) jest szerokością bazową arkusza. Para, łączniki dziadków i rodziców, `UnionNode` i akcja „Połącz jako małżeństwo” zachowują dzisiejsze położenia względem kolumn.

#### 3. Jeden rząd dzieci z linią od pary

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: FR-014: od widocznej pary prowadzi jeden pień do jednej belki, a z niej zejście do każdego dziecka, także gdy dzieci jest więcej niż 4. Zastępuje rzędy po 4 (`CHILDREN_PER_ROW`, `CHILDREN_ROW_START`) i niepołączone belki.

**Contract**:
- Wszystkie `sheet.children` w jednym rzędzie wyśrodkowanym na osi pary. Karta dziecka ma szerokość 2 kolumn przy N ≤ 4 i 1 kolumny przy N ≥ 5. Przy N > 8 rząd jest szerszy od rzędu dziadków, a treść rośnie symetrycznie (dopasowanie to obsłuży).
- Pień od środka linii pary do belki. Belka biegnie od środka pierwszego do środka ostatniego dziecka (przy N = 1 pień schodzi prosto do karty), a każde dziecko ma zejście z belki.
- Slot „Dodaj dziecko” stoi na prawo od ostatniego dziecka, w szerokości kart dzieci, poza belką i bez przesuwania osi rzędu.
- Przy N = 0 tekst „Brak dzieci w tym związku.” i slot pod parą jak dziś.
- Nagłówek `sr-only` „Dzieci” i nazwa slotu bez zmian.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Pest drzewa bez zmian i zielony: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/WriteTargetsTest.php`
- E2E slotów zielone przy 1366×768: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts`

#### Manual Verification:

- Karty są wyraźnie większe niż przed zmianą, a w skali 75% nazwy i daty pozostają czytelne
- Przy 1, 3, 5 i 8 dzieciach każde dziecko ma zejście z jednej belki połączonej pniem z parą. Przy 5+ karty dzieci są wąskie, a rząd mieści się w szerokości dziadków
- Slot „Dodaj dziecko” stoi obok rzędu, nie na belce, a rząd dzieci pozostaje wyśrodkowany pod parą
- Długie nazwiska zawijają się w kartach dziadków i wąskich dzieci, nie wychodzą poza kartę i nie wchodzą pod ikonę „Podgląd”

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Ocena wyglądu na granicach NFR

### Overview

Jednorazowy skrypt zrzutów ocenia dopasowanie, próg, limit i linie na 1366, 1920 i 2560 px oraz dla 0, 3, 5, 8 i 10 dzieci. Werdykt i ewentualne poprawki domykają FR-013 i FR-014.

### Changes Required:

#### 1. Rodziny 8 i 10 dzieci w seederze demo

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Zrzuty mają przypadki, które dziś nie istnieją: 8 dzieci (rząd w szerokości dziadków) i 10 dzieci (rząd szerszy, start na progu przy 1366).

**Contract**: Dwie nowe pary małżeńskie z 8 i 10 dziećmi dopisane **na końcu** seedera, po wszystkich istniejących rekordach, żeby nie przesunąć id liczonych jako przesunięcia od probanta w skrypcie zrzutów.

#### 2. Skrypt zrzutów i werdykt

**File**: `context/changes/tree-fits-screen/screenshots/shots.mjs`, `context/changes/tree-fits-screen/screenshots/verdict.md`

**Intent**: Bramka wizualna według wzorca `context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs` (Playwright z cache npx, nie zależność repo). Zrzuty po dopasowaniu startowym, bez `fullPage`, bo strona już nie przewija się w pionie.

**Contract**: Zestaw:
- proband (rodzina z kilkoma związkami) przy 1366×768 z menu rozwiniętym i zwiniętym, 1920×1080 i 2560×1440;
- pary z 0, 3, 5, 8 i 10 dziećmi przy 1366×768 i 2560×1440;
- treść zwężona jak obok panelu `standard` (ok. 670 px treści, symulowane szerokością okna) dla probanta i pary z 8 dziećmi;
- stan po „Dopasuj” dla pary z 10 dziećmi przy 1366;
- jeden zrzut w trybie ciemnym.

Id są liczone przesunięciami od probanta. Werdykt opisuje każdy zrzut względem kryteriów fazy 1 i 2 oraz listę poprawek. Poprawki wyglądu (stałe kolumn, wymiary kart, położenie paska) wprowadzane są w tej fazie, z ponownym uruchomieniem skryptu.

### Success Criteria:

#### Automated Verification:

- Seeder demo uruchamia się bez błędów: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder --no-interaction`
- Skrypt zrzutów generuje komplet plików: `node context/changes/tree-fits-screen/screenshots/shots.mjs context/changes/tree-fits-screen/screenshots/after`
- Format, lint i typy po poprawkach przechodzą: `vendor/bin/sail npm run check && vendor/bin/sail npm run types:check`
- Pełny zestaw E2E po poprawkach zielony przy 1366×768: `npx playwright test`

#### Manual Verification:

- Na 2560×1440 drzewo wypełnia przestrzeń (powiększone do limitu 150%, chyba że wcześniej wypełni wysokość lub szerokość) bez rozmytego tekstu
- Na 1366×768 rodzina z ≤ 8 dziećmi mieści się w całości (skala ≥ 75%), a rodzina z 10 dziećmi startuje w 75% wyśrodkowana na parze
- W zwężeniu jak obok panelu drzewo startuje wyśrodkowane na parze, a „Dopasuj” pokazuje całość
- Pasek kontrolek nie zasłania kart przy dopasowaniu startowym
- Werdykt w `screenshots/verdict.md` akceptuje zestaw (autor)

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie werdyktu przez autora.

---

## Testing Strategy

### Unit Tests:

- `resources/js/lib/fit-view.test.ts`: całość powyżej progu, limit 150% na dużym ekranie, kotwica poniżej progu, dokładnie na progu wychodzi całość, zerowe wymiary dają `null`.

### Integration Tests:

- Pest `TreeTest.php` i `WriteTargetsTest.php` uruchamiane bez zmian jako dowód, że kontrakt `sheet` nie drgnął.
- E2E `tree-slot-{parent,child,spouse}.spec.ts` przy 1366×768 strzegą FR-026 w nowym obszarze. Asercje bez zmian, lokatory tylko w `support/navigation.ts`.
- Bez nowego speca E2E dla przybliżania: zachowanie widoku (skala, przesunięcie) to ryzyko wizualne, oceniane zrzutami i ręcznie.

### Manual Testing Steps:

1. `/tree` przy 1366×768: drzewo w całości lub wyśrodkowane na parze, brak pionowego przewijania strony.
2. Kółko, przeciągnięcie, przeciągnięcie zakończone na karcie (brak nawigacji), „Powiększ”, „Pomniejsz”, „Dopasuj”.
3. Zmiana szerokości okna przed i po ręcznym przybliżeniu.
4. Kliknięcie karty rodzica (nowa osoba w centrum, nowe dopasowanie) i przełączenie związku.
5. Tab przez karty i sloty.
6. Dodanie dziecka, rodzica i małżonka ze slotów.
7. Para z 5 i 8 dziećmi z seedera demo: belka, zejścia, slot obok rzędu.

## Performance Considerations

Transformacja obejmuje jeden arkusz (do ok. 30 kart), więc przybliżanie nie wymaga wirtualizacji. Ponowne dopasowanie po zmianie rozmiaru reaguje na `ResizeObserver` i powinno zbierać serię zdarzeń (np. jedna klatka `requestAnimationFrame`), żeby przeciąganie krawędzi okna nie animowało dopasowania przy każdym pikselu.

## Migration Notes

Brak zmian danych i schematu. Seeder demo dostaje tylko nowe rekordy na końcu.

## References

- Research: `context/changes/tree-fits-screen/research.md`
- Roadmapa S-05: `context/foundation/roadmap.md:175-186`; PRD FR-013, FR-014, FR-026, NFR 1366–2560 px: `context/foundation/prd.md`
- Wzorzec przybliżania: `resources/js/components/sources/source-viewer.tsx:14-60`
- Arkusz: `resources/js/components/tree/family-sheet.tsx:130-198,238-368`
- Lokatory E2E: `tests/e2e/support/navigation.ts:13-78`
- Wzorzec skryptu zrzutów: `context/archive/2026-09-29-tree-sheet-variant-c/screenshots/shots.mjs`
- Wcześniejsze decyzje: `context/archive/2026-09-28-graphical-tree-view/plan.md:84,93`, `context/archive/2026-10-04-side-panel-shell/plan.md:38`, `context/archive/2026-10-04-critical-flows-e2e-guard/plan.md:24`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Obszar przybliżania i dopasowania

#### Automated

- [x] 1.1 Testy logiki dopasowania przechodzą: `vendor/bin/sail npm run test:js` — 480a062
- [x] 1.2 Format i lint przechodzą: `vendor/bin/sail npm run check` — 480a062
- [x] 1.3 Typy przechodzą: `vendor/bin/sail npm run types:check` — 480a062
- [x] 1.4 Build przechodzi: `vendor/bin/sail npm run build` — 480a062
- [x] 1.5 Pest drzewa bez zmian i zielony: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/WriteTargetsTest.php` — 480a062
- [x] 1.6 Pełny zestaw E2E zielony przy 1366×768: `npx playwright test` — 480a062

#### Manual

- [x] 1.7 Na 1366×768 drzewo probanta zajmuje całą wysokość pod nagłówkiem strony, strona nie przewija się w pionie, a drzewo jest widoczne w całości albo (poniżej progu) wyśrodkowane na parze — 480a062
- [x] 1.8 Kółko przybliża wokół kursora, przeciągnięcie przesuwa, a przeciągnięcie zakończone nad kartą nie nawiguje — 480a062
- [x] 1.9 „Dopasuj” pokazuje całe drzewo. Po zmianie szerokości okna nieruszane drzewo dopasowuje się ponownie, a po ręcznym przybliżeniu widok zostaje — 480a062
- [x] 1.10 Przejście do innej osoby i zmiana związku dopasowują drzewo od nowa — 480a062
- [x] 1.11 Tab przez karty i sloty pokazuje fokusowany element w kadrze bez rozjechania widoku — 480a062
- [x] 1.12 Sloty „Dodaj ojca/matkę/dziecko/małżonka” i menu związku działają kliknięciem jak dziś — 480a062

### Phase 2: Większe karty i jeden rząd dzieci

#### Automated

- [x] 2.1 Format i lint przechodzą: `vendor/bin/sail npm run check` — 7e1b28b
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — 7e1b28b
- [x] 2.3 Build przechodzi: `vendor/bin/sail npm run build` — 7e1b28b
- [x] 2.4 Pest drzewa bez zmian i zielony: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/WriteTargetsTest.php` — 7e1b28b
- [x] 2.5 E2E slotów zielone przy 1366×768: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts` — 7e1b28b

#### Manual

- [x] 2.6 Karty są wyraźnie większe niż przed zmianą, a w skali 75% nazwy i daty pozostają czytelne — 7e1b28b
- [x] 2.7 Przy 1, 3, 5 i 8 dzieciach każde dziecko ma zejście z jednej belki połączonej pniem z parą. Przy 5+ karty dzieci są wąskie, a rząd mieści się w szerokości dziadków — 7e1b28b
- [x] 2.8 Slot „Dodaj dziecko” stoi obok rzędu, nie na belce, a rząd dzieci pozostaje wyśrodkowany pod parą — 7e1b28b
- [x] 2.9 Długie nazwiska zawijają się w kartach dziadków i wąskich dzieci, nie wychodzą poza kartę i nie wchodzą pod ikonę „Podgląd” — 7e1b28b

### Phase 3: Ocena wyglądu na granicach NFR

#### Automated

- [x] 3.1 Seeder demo uruchamia się bez błędów: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder --no-interaction`
- [x] 3.2 Skrypt zrzutów generuje komplet plików: `node context/changes/tree-fits-screen/screenshots/shots.mjs context/changes/tree-fits-screen/screenshots/after`
- [x] 3.3 Format, lint i typy po poprawkach przechodzą: `vendor/bin/sail npm run check && vendor/bin/sail npm run types:check`
- [x] 3.4 Pełny zestaw E2E po poprawkach zielony przy 1366×768: `npx playwright test`

#### Manual

- [x] 3.5 Na 2560×1440 drzewo wypełnia przestrzeń (powiększone do limitu 150%, chyba że wcześniej wypełni wysokość lub szerokość) bez rozmytego tekstu
- [x] 3.6 Na 1366×768 rodzina z ≤ 8 dziećmi mieści się w całości (skala ≥ 75%), a rodzina z 10 dziećmi startuje w 75% wyśrodkowana na parze
- [x] 3.7 W zwężeniu jak obok panelu drzewo startuje wyśrodkowane na parze, a „Dopasuj” pokazuje całość
- [x] 3.8 Pasek kontrolek nie zasłania kart przy dopasowaniu startowym
- [x] 3.9 Werdykt w `screenshots/verdict.md` akceptuje zestaw (autor)
