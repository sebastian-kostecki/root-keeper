# Karta osoby na drzewie: imię i nazwisko rodowe, łamanie po słowach, szersze kolumny na dużym ekranie — plan implementacji

## Overview

Karty na `/tree` i `/tree/{id}/ancestors` pokazują imię i nazwisko rodowe (bez nazwiska po ślubie i bez „z d. …”). Nazwa łamie się wyłącznie między słowami, ma maksymalnie 2 linie, a potem wielokropek. Karta jednokolumnowa zyskuje miejsce przez węższy pasek zdjęcia (4,5 → 3rem). Na dużym ekranie nadmiar szerokości obszaru idzie w szerokość kolumny (12–18rem), a nie w powiększenie, więc przy 2560 imię i nazwisko do ok. 18 znaków mieści się w jednej linii przy tekście 16 px.

## Current State Analysis

(Na podstawie `research.md`; pomiary Playwright przy otwartym menu bocznym.)

- Siatka ma stałą kolumnę 12rem, zdublowaną w `resources/js/components/tree/family-sheet.tsx:26` i `resources/js/components/tree/ancestor-chart.tsx:10` (C4). Start dopasowuje ją skalą do `TREE_FIT_MAX = 1.5` (`resources/js/lib/fit-view.ts:5`, `resources/js/components/zoom-canvas.tsx:137-150`). Przy 2560 skala wynosi 1,384, a karta dziadka 266×213 px, mimo to nazwa zajmuje 2–4 linie (C1).
- Na pierwszą linię nazwy na karcie dziadka zostaje 68 px: 192 − pasek `w-18` 72 (`sheet-person-card.tsx:44`) − `px-3` 24 (`:88`) − float `size-7` 28 (`:91-95`) (C3).
- `[overflow-wrap:anywhere] break-words hyphens-auto` (`sheet-person-card.tsx:89`): w CSS wygrywa `break-words`, więc długie słowo poszerza kolumnę, wchodzi pod oko i jest ucinane krawędzią karty (`overflow-hidden`, `card-styles.ts:15`) (C2).
- Nazwa karty to `display_name` z `SheetPersonResource.php:44` (`Person::displayName()`, `Person.php:148-157`, z „z d. …”). `display_name` czytają też nagłówki, okruszki, `<Head>`, menu związków, `add-person-config.ts`, `MarryCoupleDialog` i `aria-label` oka. E2E `person-panel-unsaved-guard.spec.ts:58` oczekuje „z d.” w h1 (C5).
- `tsconfig.json:115-119` obejmuje `resources/js/**/*.ts`, więc literały `SheetPerson` w `lib/centre-hints.test.ts:5-15`, `lib/ancestor-layout.test.ts:5-15` i `lib/add-person-target.test.ts:15-23` są sprawdzane przez `tsc`.
- `ZoomCanvas` ma jeden `ResizeObserver` na `wrapper` i `content` (`zoom-canvas.tsx:289-291`). Dopasowanie powtarza się przy zmianie rozmiaru, dopóki autor nie przesunie ani nie przybliży widoku (`movedRef`). Treść jest ukryta (`visibility: hidden`) do pierwszego dopasowania (`:208-212`, `:497`).
- E2E `ancestors-view.spec.ts:198-201`: po otwarciu panelu przy ręcznym zoomie szerokość karty na ekranie się nie zmienia (przy 1366).
- D1 (deferred): przy 1366 drzewo z dziadkami startuje zakotwiczone przy 0,75. Ta zmiana nie może tego pogorszyć.

Sonda w Chromium (planowanie, 2026-10-12): `float` z rezerwą działa wewnątrz `display: -webkit-box` z `-webkit-line-clamp`; słowo dłuższe niż linia dostaje wielokropek na swojej linii przy `overflow: hidden` + `text-overflow: ellipsis`. Pierwsze słowo, które nie mieści się obok rezerwy, spada pod nią, a karta rośnie o jedną linię.

## Desired End State

- Karta pokazuje `first_name` + (`maiden_name`, a gdy pusty, `last_name`), z „NN” w miejsce brakującej części. Nigdzie poza kartą nazwa się nie zmienia.
- Nazwa nigdy nie łamie się w środku słowa, ma maks. 2 linie, a nadmiar kończy się wielokropkiem w obrębie kolumny tekstu (nie pod okiem, nie krawędzią karty). Nazwa na karcie (`card_name`, nieucięta) jest w podpowiedzi (`title`) i w nazwie dostępnej karty; dla kobiety z nazwiskiem rodowym nazwa dostępna zmienia się więc świadomie z „kobieta Anna Kowalska z d. Nowak” na „kobieta Anna Nowak”.
- Karta węższa niż 20rem ma pasek zdjęcia 3rem, szersza 4,5rem.
- Szerokość kolumny = szerokość obszaru przy skali 1, w granicach 12–18rem, wspólna dla `/tree` i `/ancestors`. Liczy się przy każdym dopasowaniu, a po ręcznym ruchu zostaje. Przy 1366 i 1920 geometria jak dziś (12rem), przy 2560 ok. 17rem i skala ok. 1. Powyżej ok. 2700 px kolumna stoi na 18rem i dalej działa skala do 1,5.
- Weryfikacja: Pest (pole `card_name`), Vitest (`fitColumnRem`), E2E przy 2560 i 1366, zrzuty `after-*` w `screenshots/`.

### Key Discoveries:

- Oko (`absolute top-1 right-1 size-7`, `sheet-person-card.tsx:161`) zachodzi na kolumnę tekstu tylko ok. 19 px (4 + 28 − 12 `px-3` − 1 obramowanie), a float rezerwuje 28 px. Rezerwa `w-5` daje pierwszej linii ok. 98 px przy 12rem i pasku 3rem.
- Tailwind v4 ma wbudowane `@container` i `@xs` (min. 20rem), więc pasek zależny od szerokości karty nie potrzebuje propsów ani wartości arbitralnych.
- Wszystkie odległości siatki (`Arm` `edgeRem`, `cardCentreRem`, `childCellRem`, `SLOT_CELL_REM`, `COUPLE_CARD_REM`, `toRem` w `ancestor-chart.tsx:23-25`) wynikają z kolumny i odstępu, więc jedna wartość z jednego źródła przesuwa całą geometrię razem.
- `ZoomCanvas` używają tylko `family-sheet.tsx:565` i `ancestor-chart.tsx:95`.

## What We're NOT Doing

- Nie zmieniamy `display_name`, `Person::displayName()` ani `aria-label` oka („Podgląd: {display_name}”).
- Nie naprawiamy D1 (1366 zakotwiczone przy 0,75): osobna zmiana.
- Nie zmieniamy `TREE_FIT_MAX`, `TREE_FIT_FLOOR`, `ANCESTORS_FIT_FLOOR` ani `ANCESTORS_ZOOM_MAX`.
- Nie zmieniamy czcionki nazwy (zostaje 16 px), nie przenosimy oka poza pierwszą linię, nie zmieniamy paska na kartach szerszych niż 20rem (rodzice, para, do 4 dzieci).
- Nie wprowadzamy skali docelowej < 1 przy 1920; nazwiska dłuższe niż ok. 12–13 znaków przy 1366 i 1920 mają wielokropek w drugiej linii.
- Nie dodajemy komponentu tooltipu; podpowiedź to natywny `title`.
- Nie mierzymy kolumny pod kątem wysokości obszaru (niskie, bardzo szerokie okna mogą dalej dawać skalę < 1).

## Implementation Approach

Od danych do geometrii: najpierw nowe pole (backend i typy), potem układ wewnątrz karty (zależy tylko od szerokości karty), na końcu kolumna zależna od obszaru (zależy od `ZoomCanvas`). Każda faza zostawia działające drzewo: po fazie 2 karty przy 12rem są już czytelniejsze (pasek 3rem, rezerwa 20 px, łamanie po słowach), a faza 3 tylko poszerza je na dużym ekranie. Kolumnę liczy czysta funkcja z testami; `ZoomCanvas` jedynie mierzy obszar i trzyma wynik w stanie, w tym samym przebiegu co dopasowanie.

## Critical Implementation Details

**Kolejność w dopasowaniu.** Kolumna musi się ustalić przed pierwszym pokazaniem treści i przed ustawieniem skali: w zaplanowanym przebiegu (`schedule` → rAF) najpierw policz kolumnę z `wrapper.clientWidth`. Jeśli różni się od bieżącej, ustaw stan i nie dopasowuj w tym przebiegu: zmiana szerokości treści wywoła `ResizeObserver` na `content` i kolejny przebieg. `setReady(true)` zostaje tylko w gałęzi, w której kolumna jest już ustalona. Po ręcznym ruchu (`movedRef`) przebieg nie liczy kolumny ani nie dopasowuje, tak jak dziś. „Dopasuj” liczy oba. Kolumna zależy wyłącznie od szerokości obszaru (nie treści), więc pętla się nie wzbudza. W przebiegu, który tylko zmienia kolumnę, `reveal` nie biegnie (geometria jest jeszcze stara); wykona go kolejny przebieg po `ResizeObserver` na `content`. Po starcie (treść już widoczna) zmiana kolumny przy otwarciu panelu lub zmianie okna może dać jedną klatkę nowej kolumny przy starej transformacji; dlatego `fitColumnRem` zaokrągla wynik do 0,25rem, a ręczny krok 3.8 sprawdza brak widocznego skoku.

**Maksimum kolumny poniżej 20rem.** Pasek reaguje na szerokość karty (`@xs` = 20rem). Karta jednokolumnowa przy maksymalnej kolumnie (18rem) musi pozostać poniżej 20rem, a najwęższa karta dwukolumnowa (2·12 + 0,75 = 24,75rem) powyżej. Podniesienie `COLUMN_MAX_REM` do 20rem lub więcej po cichu przywróci szeroki pasek na kartach dziadków.

## Faza 1: Nazwa na karcie (`card_name`)

### Overview

Nowe pole ładunku karty z imieniem i nazwiskiem rodowym, użyte w treści karty i w inicjałach paska. Zamyka C5.

### Changes Required:

#### 1. Ładunek karty

**File**: `app/Http/Resources/SheetPersonResource.php`, `app/Actions/Trees/BuildFamilySheet.php:19`

**Intent**: Dodać `card_name` obok `display_name`. Karta drzewa ma pokazywać imię i nazwisko rodowe, a `display_name` zostaje dla nagłówków, menu i etykiet.

**Contract**: `card_name: string` = `first_name ?? 'NN'` + spacja + (`maiden_name`, gdy `filled()`, w przeciwnym razie `last_name ?? 'NN'`), przycięte jak w `displayName()`. Klucz dopisany do array shape w PHPDoc zasobu i do `@phpstan-type SheetPerson` w `BuildFamilySheet.php:19`. Logika może być metodą `Person::cardName()` obok `displayName()` (`Person.php:148`), jeśli tak czytelniej.

#### 2. Typ TS i literały w testach

**File**: `resources/js/types/tree.ts:3-11`, `resources/js/lib/centre-hints.test.ts`, `resources/js/lib/ancestor-layout.test.ts`, `resources/js/lib/add-person-target.test.ts`

**Intent**: `SheetPerson` dostaje wymagane `card_name: string`; trzy literały testowe uzupełnione, żeby `tsc` przeszedł.

**Contract**: `card_name: string` w `SheetPerson` (zaraz po `display_name`).

#### 3. Karta

**File**: `resources/js/components/tree/sheet-person-card.tsx:63,102`

**Intent**: Treść nazwy i inicjały w pasku biorą `card_name`, żeby inicjały odpowiadały nazwisku rodowemu. `aria-label` oka (`:166`) zostaje z `display_name`.

**Contract**: `CardBody` renderuje `person.card_name`; `getInitials(person.card_name)`.

#### 4. Testy Pest

**File**: `tests/Feature/TreeTest.php`, `tests/Feature/AncestorsTest.php`

**Intent**: Jeden test na drzewie obejmuje przypadki nazwy karty; jeden na przodkach potwierdza, że ten sam zasób niesie pole w wykresie.

**Contract**: Przypadki w `TreeTest.php` (wzorem `'the lifespan label covers …'`, `:458`): kobieta `Anna Kowalska` z `maiden_name = 'Nowak'` → `card_name = 'Anna Nowak'`, a `display_name` dalej `'Anna Kowalska z d. Nowak'`; osoba bez `maiden_name` → `'Jan Kowalski'`; brak imienia i nazwiska → `'NN NN'`; `maiden_name` przy pustym `last_name` → nazwisko rodowe. W `AncestorsTest.php` (wzorem `:252`) `card_name` rodzica z `maiden_name`.

### Success Criteria:

#### Automated Verification:

- Testy Pest drzewa i przodków przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/AncestorsTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Typy TS przechodzą: `vendor/bin/sail npm run types:check`
- Testy Vitest przechodzą: `vendor/bin/sail npm run test:js`
- Pint bez zmian po formatowaniu: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Na `/tree` osoby z nazwiskiem rodowym pokazuje na karcie imię i nazwisko rodowe, a nagłówek strony dalej „… z d. …”

**Implementation Note**: Po fazie i przejściu automatów zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Układ nazwy i paska na karcie

### Overview

Nazwa łamie się po słowach, maks. 2 linie z wielokropkiem; rezerwa na oko równa rzeczywistemu zachodzeniu; pasek 3rem na kartach węższych niż 20rem. Zamyka C2 i C3. Zmiana działa w obu widokach, bo `ancestor-card.tsx` używa `CardBody` i `personCardSurface`.

### Changes Required:

#### 1. Nazwa

**File**: `resources/js/components/tree/sheet-person-card.tsx:89-103`

**Intent**: Zastąpić `[overflow-wrap:anywhere] break-words hyphens-auto` łamaniem wyłącznie między słowami z limitem 2 linii i wielokropkiem; nazwa karty w podpowiedzi. Usuwa jedyną wartość arbitralną na plikach drzewa.

**Contract**: Element nazwy: `w-full line-clamp-2 text-ellipsis` (bez klas `overflow-wrap`/`hyphens`), `title={person.card_name}`. `w-full` jest potrzebne, żeby słowo dłuższe niż linia było ucinane w kolumnie tekstu (przy `items-start` element inaczej przyjmuje szerokość najdłuższego słowa). Treść DOM zostaje pełna (wraz z `sr-only` „mężczyzna”/„kobieta”), więc `line-clamp` niczego nie ucina w nazwie dostępnej. Nazwa dostępna linku karty i karty przodka wynika jednak z treści DOM, czyli po zmianie z `card_name`: dla osoby z nazwiskiem rodowym zmienia się („kobieta Anna Nowak” zamiast „kobieta Anna Kowalska z d. Nowak”), dla pozostałych nie. Istniejące E2E używają nazw bez `maiden_name`, więc tej zmiany nie wykryją; chroni ją asercja w `tree-card-name.spec.ts` (faza 4).

#### 2. Rezerwa na oko

**File**: `resources/js/components/tree/sheet-person-card.tsx:91-95`

**Intent**: Float rezerwuje tylko tyle, ile oko zachodzi na kolumnę tekstu, żeby imiona 10-znakowe („Aleksandra”, „Franciszek”, „Bartłomiej”) mieściły się obok oka zamiast spadać pod nie i dokładać linię.

**Contract**: Float `float-right size-5` (było `size-7`). Oko (`:161`) bez zmian.

#### 3. Pasek zależny od szerokości karty

**File**: `resources/js/components/tree/card-styles.ts:14` (`personCardSurface`), `resources/js/components/tree/sheet-person-card.tsx:44`

**Intent**: Karta jednokolumnowa oddaje 24 px przez węższy pasek, a karty szersze (rodzice, para, do 4 dzieci) zostają przy 4,5rem.

**Contract**: `personCardSurface` dostaje `@container`; `PhotoStrip` `w-12 @xs:w-18` (było `w-18`). Inicjały `text-lg` mieszczą się w 3rem. Komentarz `CardBody` (`:70-75`) opisuje nową regułę paska.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy TS przechodzą: `vendor/bin/sail npm run types:check`
- Brak wartości arbitralnych na plikach kart: `grep -nE '\[[a-z-]+:|-\[' resources/js/components/tree/sheet-person-card.tsx resources/js/components/tree/card-styles.ts` nic nie zwraca (indeksy TS typu `stripGender[...]` nie pasują)
- Istniejące E2E kart przechodzą: `npx playwright test tests/e2e/person-photo-on-card.spec.ts tests/e2e/tree-card-recentre.spec.ts tests/e2e/ancestors-view.spec.ts`

#### Manual Verification:

- Przy 1366 karta dziadka ma pasek 3rem, rodzic 4,5rem; „Aleksandra Brzęczyszczykiewicz” ma 2 linie, druga kończy się wielokropkiem przed prawą krawędzią kolumny tekstu, nic nie wchodzi pod oko
- Najechanie na nazwę pokazuje pełną nazwę; karta przodka wygląda tak samo (bez oka, bez rezerwy)

**Implementation Note**: Po fazie i przejściu automatów zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Kolumna z szerokości obszaru

### Overview

Wspólne źródło geometrii dla obu widoków (C4) i kolumna liczona z szerokości obszaru w cyklu dopasowania `ZoomCanvas` (C1).

### Changes Required:

#### 1. Wspólne stałe i funkcja kolumny

**File**: `resources/js/lib/fit-view.ts`

**Intent**: Jedno źródło dla kolumny i odstępu oraz czysta funkcja, która z szerokości obszaru liczy kolumnę, przy której treść ma skalę 1. Plik już trzyma progi dopasowania, więc kolumna jest ich częścią.

**Contract**: Eksporty `COLUMN_MIN_REM = 12`, `COLUMN_MAX_REM = 18` (komentarz: musi zostać < 20rem, patrz pasek `@xs`), `COLUMN_GAP_REM = 0.75` oraz `fitColumnRem({ areaWidth, rootFontSize, columns, paddingRem }): number` = `clamp(COLUMN_MIN_REM, floor4((areaWidth / rootFontSize − paddingRem − (columns − 1) · COLUMN_GAP_REM) / columns), COLUMN_MAX_REM)`, gdzie `floor4` zaokrągla w dół do 0,25rem (`COLUMN_STEP_ROUND_REM`). Zaokrąglenie w dół trzyma treść w obszarze (skala ≥ 1 przy kolumnie wyliczonej) i ogranicza liczbę przeliczeń kolumny przy przeciąganiu okna lub animacji panelu do jednej na 4 px. Dla `areaWidth ≤ 0` zwraca `COLUMN_MIN_REM`. Komentarz `ANCESTORS_FIT_FLOOR` (`:7-12`) mówi o kolumnie minimalnej.

#### 2. Testy funkcji kolumny

**File**: `resources/js/lib/fit-view.test.ts`

**Intent**: Przypadki graniczne utrwalają regułę kolumny, która decyduje o wyglądzie przy 1366/1920/2560.

**Contract**: `describe('fitColumnRem')`, 8 kolumn, padding 1rem, 16 px/rem: obszar 1070 → 12; 1624 → 12 (wynik ≈ 11,9 podniesiony do minimum); 2264 → 16,75 (16,9 zaokrąglone w dół do 0,25); 3544 → 18; obszar 0 → 12; 2 kolumny przy 2264 → 18; wynik zawsze będący wielokrotnością 0,25 w zakresie 12–18.

#### 3. `ZoomCanvas` liczy kolumnę w dopasowaniu

**File**: `resources/js/components/zoom-canvas.tsx`

**Intent**: Gdy widok poda liczbę kolumn, `ZoomCanvas` liczy kolumnę z `wrapper.clientWidth` przy każdym dopasowaniu (start, zmiana rozmiaru, otwarcie/zamknięcie panelu, „Dopasuj”), dopóki autor nie przesunie ani nie przybliży widoku, i podaje ją treści. Bez liczby kolumn zachowanie bez zmian.

**Contract**: Nowe opcjonalne props `columns?: number` i `paddingRem?: number`; `children: ReactNode | ((columnRem: number) => ReactNode)`. Stan `columnRem` startuje od `COLUMN_MIN_REM`. `rootFontSize` z `getComputedStyle(document.documentElement).fontSize`. Kolejność w przebiegu zgodnie z „Critical Implementation Details”. Komentarz komponentu (`:70-77`) dopisuje, że kolumna należy do dopasowania.

#### 4. Drzewo

**File**: `resources/js/components/tree/family-sheet.tsx:25-45,312-322,559-681`

**Intent**: Usunąć lokalne `COLUMN_REM`/`COLUMN_GAP_REM`; cała geometria (siatka, `GRID_REM`, `COUPLE_CARD_REM`, `childCellRem`, `SLOT_CELL_REM`, `cardCentreRem`, `slotOverflowRem`) liczy się z kolumny podanej przez `ZoomCanvas`.

**Contract**: `ZoomCanvas` dostaje `columns={8}` i `paddingRem={1}` (wiersz dziadków to szerokość bazowa, `p-2`). Stałe zależne od kolumny stają się funkcjami lub wartościami liczonymi w renderze z `columnRem`; `COLUMN_GAP_REM` importowany z `lib/fit-view`. Wiersz dzieci szerszy niż 8 kolumn dalej po prostu zmniejsza skalę.

#### 5. Przodkowie

**File**: `resources/js/components/tree/ancestor-chart.tsx:9-14,78-127`

**Intent**: To samo dla wykresu przodków: kolumna z `ZoomCanvas`, liczba kolumn z układu, ale nie mniej niż 8 jak na drzewie. Wykres 1–4-kolumnowy (2–3 pokolenia lub mało znanych przodków) inaczej dostałby kolumnę 16–18rem, a skala dalej rosłaby do 1,5, więc karta byłaby większa niż dziś (18 → 27rem efektywnie).

**Contract**: `ZoomCanvas` dostaje `columns={Math.max(columns, WIDE_CHART_COLUMNS)}` (`columns` z `layoutAncestorChart`, `WIDE_CHART_COLUMNS = 8` z komentarzem „tyle co siatka drzewa”) i `paddingRem={1}`; `COLUMN_STEP_REM`, `toRem`, `widthRem`, `rowStyle` i szerokość karty liczone z `columnRem`; lokalne stałe usunięte.

### Success Criteria:

#### Automated Verification:

- Testy Vitest (w tym `fitColumnRem`) przechodzą: `vendor/bin/sail npm run test:js`
- Typy TS przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- `COLUMN_REM = 12` nie występuje już w komponentach: `grep -rn "COLUMN_REM = " resources/js/components` pusty
- E2E drzewa i przodków przechodzą: `npx playwright test tests/e2e/ancestors-view.spec.ts tests/e2e/tree-card-recentre.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-spouse.spec.ts`

#### Manual Verification:

- Przy 2560 karty dziadków mają ok. 17rem, drzewo wypełnia szerokość przy skali ok. 1, nie ma mignięcia wąskiej siatki przy starcie
- Przy 1366 i 1920 drzewo i wykres przodków (4 pokolenia oraz 2 pokolenia lub jeden znany rodzic) wyglądają jak po fazie 2: kolumna 12rem, start przy 1366 dalej zakotwiczony przy 0,75, mały wykres nie ma kart większych niż dziś
- Przy 2560 otwarcie panelu osoby zwęża kolumny i drzewo mieści się obok panelu, bez widocznego skoku widoku; zamknięcie wraca do tego samego widoku (też przy zmianie rozmiaru okna); po ręcznym przesunięciu otwarcie panelu nie zmienia szerokości kart
- Linie, belki par, węzły małżeństw i wiersz dzieci (2, 5 dzieci) trzymają się środków kart przy 2560

**Implementation Note**: Po fazie i przejściu automatów zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: E2E, bramka wizualna i system projektowy

### Overview

Test przeglądarkowy zachowania nazwy przy dwóch szerokościach, zrzuty po zmianie i aktualizacja DS.

### Changes Required:

#### 1. Spec E2E (przez `/10x-e2e`)

**File**: `tests/e2e/tree-card-name.spec.ts`

**Intent**: Chroni ryzyka, które widać tylko w przeglądarce: nazwa dziadka rozsądnej długości w jednej linii przy 2560 i długa nazwa w maks. 2 liniach bez łamania w słowie przy 1366.

**Contract**: Dane z sufiksem timestamp (wzorem `ancestors-view.spec.ts`), osoba centralna z rodzicami i dziadkami. Przy `setViewportSize(2560×1440)`: element nazwy dziadka „Jan Wróblewski” (14 zn.; pierwsza linia ma tu ok. 176 px, czyli ok. 18 znaków, więc nazwa ≤ 16 znaków daje zapas) ma wysokość jednej linii; „Stanisław Wróblewski” (20 zn.) już się nie mieści i nie jest asercją jednej linii. Przy 1366×768: nazwa „Aleksandra Brzęczyszczykiewicz” ma wysokość ≤ 2 linii, a kobieta z `maiden_name` pokazuje na karcie nazwisko rodowe, a link karty ma nazwę dostępną `kobieta Anna Nowak` (zmiana nazwy dostępnej, patrz faza 2). Lokatory przez rolę/nazwę zgodnie z CLAUDE.md; sprzątanie przez fixture sufiksu. Weryfikacja przez celowe zepsucie (np. powrót `line-clamp-2` → brak limitu).

#### 2. Zrzuty

**File**: `context/changes/tree-card-name-one-line/screenshots/`

**Intent**: Bramka wizualna `/10x-ui`: te same ujęcia co `before-*`.

**Contract**: `after-tree-{1366,1920,2560}.png`, `after-ancestors-{1366,1920,2560}.png`, `after-tree-2560-panel.png` (otwarty panel osoby), na tej samej rodzinie testowej co pomiar w research.

#### 3. System projektowy

**File**: artefakt Root Keeper Design System, `project/components/PersonCard/README.md` (i `project/tokens.json`, jeśli trzyma `photo-strip-width`)

**Intent**: DS ma opisywać to, co jest w kodzie: nazwa na karcie bez „z d.”, łamanie po słowach maks. 2 linie, pasek 3rem dla kart < 20rem, kolumna 12–18rem.

**Contract**: Odczyt artefaktu, edycja tylko tych sekcji, ponowna publikacja pod tym samym URL.

### Success Criteria:

#### Automated Verification:

- Nowy spec przechodzi: `npx playwright test tests/e2e/tree-card-name.spec.ts`
- Spec czerwienieje przy celowo zepsutym limicie linii i wraca do zieleni po cofnięciu
- Zrzuty istnieją: `ls context/changes/tree-card-name-one-line/screenshots/after-*.png` zwraca 7 plików

#### Manual Verification:

- Zrzuty `after-*` zestawione z `before-*`: przy 2560 karty mniejsze na ekranie, nazwy do ok. 18 znaków w jednej linii; przy 1920 nazwy łamią się tylko po słowach, „Kowalczykows…” z wielokropkiem jest akceptowalne
- DS `PersonCard/README.md` opisuje nazwę rodową, łamanie i pasek zgodnie z kodem

---

## Testing Strategy

### Unit Tests:

- `fitColumnRem`: minimum przy 1366 i 1920, 16,75rem przy 2560 (16,9 zaokrąglone w dół do 0,25), maksimum przy 3544, obszar 0, mała liczba kolumn.
- Pest: `card_name` dla nazwiska rodowego, braku nazwiska rodowego, „NN”, nazwiska rodowego przy pustym `last_name`; `display_name` bez zmian.

### Integration Tests:

- E2E `tree-card-name.spec.ts` (2560: jedna linia; 1366: maks. 2 linie, nazwisko rodowe).
- Istniejące E2E drzewa i przodków jako regresja geometrii i selektorów (`Podgląd: …`, `mężczyzna …`).

### Manual Testing Steps:

1. Otwórz `/tree` przy 2560 z dziadkami o nazwach 9–30 znaków; sprawdź jedną linię do ok. 18 znaków i brak łamania w słowie.
2. Otwórz panel osoby, zamknij go; sprawdź powrót do tego samego widoku. Przesuń drzewo, otwórz panel; szerokość kart się nie zmienia.
3. `/tree/{id}/ancestors?generations=6` przy 1920: kolumna 12rem, start jak dziś; `Pomniejsz`/`Dopasuj` działają. To samo z `?generations=2` (mały wykres): karty nie większe niż dziś.

## Performance Considerations

Kolumna liczy się w istniejącym przebiegu rAF, bez nowych obserwatorów. Każda zmiana kolumny to jedno renderowanie całego drzewa i jedno dodatkowe dopasowanie. Przy starcie i otwarciu panelu zdarza się to raz; przy przeciąganiu krawędzi okna zaokrąglenie do 0,25rem (ok. 4 px) ogranicza to do jednej zmiany na 4 px szerokości obszaru, nie na każdą klatkę.

## Migration Notes

Brak zmian w bazie. `card_name` jest nowym polem w ładunku; stare klienty go nie czytają.

## References

- Research: `context/changes/tree-card-name-one-line/research.md` (zarzuty C1–C5, D1, decyzje 1–3, rachunek)
- Zrzuty przed zmianą: `context/changes/tree-card-name-one-line/screenshots/before-*.png`
- Wcześniejsze decyzje: `context/archive/2026-10-11-person-photo-on-card/plan.md:303-304,340`, `context/archive/2026-10-05-tree-fits-screen/plan.md:20,259`
- Wzorzec testu ładunku karty: `tests/Feature/TreeTest.php:458`, `tests/Feature/AncestorsTest.php:252`
- Wzorzec E2E z zoomem: `tests/e2e/ancestors-view.spec.ts:166-224`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Nazwa na karcie (`card_name`)

#### Automated

- [x] 1.1 Testy Pest drzewa i przodków przechodzą — 36f2087
- [x] 1.2 Larastan przechodzi — 36f2087
- [x] 1.3 Typy TS przechodzą — 36f2087
- [x] 1.4 Testy Vitest przechodzą — 36f2087
- [x] 1.5 Pint bez zmian po formatowaniu — 36f2087

#### Manual

- [x] 1.6 Karta pokazuje nazwisko rodowe, nagłówek dalej „z d.” — 36f2087

### Phase 2: Układ nazwy i paska na karcie

#### Automated

- [x] 2.1 Format i lint przechodzą
- [x] 2.2 Typy TS przechodzą
- [x] 2.3 Brak wartości arbitralnych na plikach kart
- [x] 2.4 Istniejące E2E kart przechodzą

#### Manual

- [x] 2.5 Przy 1366 pasek 3rem/4,5rem, długa nazwa w 2 liniach z wielokropkiem, nic pod okiem
- [x] 2.6 Podpowiedź z pełną nazwą, karta przodka bez zmian w układzie

### Phase 3: Kolumna z szerokości obszaru

#### Automated

- [ ] 3.1 Testy Vitest (w tym fitColumnRem) przechodzą
- [ ] 3.2 Typy TS przechodzą
- [ ] 3.3 Format i lint przechodzą
- [ ] 3.4 COLUMN_REM = 12 nie występuje już w komponentach
- [ ] 3.5 E2E drzewa i przodków przechodzą

#### Manual

- [ ] 3.6 Przy 2560 kolumna ok. 17rem, skala ok. 1, bez mignięcia przy starcie
- [ ] 3.7 Przy 1366 i 1920 geometria jak po fazie 2 (też mały wykres przodków)
- [ ] 3.8 Panel przy 2560: zwężenie bez skoku, powrót po zamknięciu, brak zmiany po ręcznym ruchu
- [ ] 3.9 Linie, belki, węzły i wiersz dzieci trzymają się środków kart przy 2560

### Phase 4: E2E, bramka wizualna i system projektowy

#### Automated

- [ ] 4.1 Nowy spec przechodzi
- [ ] 4.2 Spec czerwienieje przy celowo zepsutym limicie linii
- [ ] 4.3 Zrzuty after-* istnieją

#### Manual

- [ ] 4.4 Zrzuty after-* zestawione z before-* spełniają cel
- [ ] 4.5 DS PersonCard opisuje nazwę rodową, łamanie i pasek
