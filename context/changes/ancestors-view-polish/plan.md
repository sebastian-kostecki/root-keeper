# Dopracowanie widoku przodków — plan implementacji

## Overview

Widok `/tree/{person}/ancestors` dostaje pięć zmian, o które prosi użytkownik:
- **Wejście:** przycisk „Przodkowie” w rzędzie tytułu drzewa.
- **Zoom:** samo kółko przybliża, małym krokiem.
- **Pokolenia:** tyle, ile jest w danych, zamiast sztywnego 2–6.
- **Avatar:** większy, nadal okrągły i obok imienia.
- **Wiek:** znika z kart przodków (drzewo go zachowuje).

Zmiana idzie ścieżką `/10x-ui`: zarzuty C1–C6 z `research.md` są przypisane do faz, a C7 i C8 są odroczone. Pracujemy na branchu `feat/ancestors-view-polish` (od `develop`, PR do `develop`).

## Current State Analysis

Pełny audyt (zarzuty C1–C8, liczniki skanu, zrzuty „before”) jest w `context/changes/ancestors-view-polish/research.md`. Najważniejsze:

- **Wejście (C1):** jedyna droga to pozycja „Pokaż przodków” w menu „⋯” panelu osoby (`resources/js/components/persons/person-modal-header.tsx:107-113`, `:162-175`). Rząd tytułu `/tree` to sam `Heading` (`resources/js/pages/tree/show.tsx:79-81`).
- **Kółko (C2):** `wheel={zoomControls ? { activationKeys: ['Control'] } : { disabled: true }}` (`resources/js/components/zoom-canvas.tsx:594-598`). Samo kółko nie robi nic. Ctrl+kółko (deltaY 400) skacze od razu do maksimum 1.5.
- **Pokolenia (C3):**
  - Zakres 2–6 jest na sztywno w trzech miejscach: `TreeAncestorsController.php:14-18` i `:35-42` (poza zakresem → 4), `GENERATION_ITEMS` w `resources/js/pages/tree/ancestors.tsx:16-20` oraz próg startu w `resources/js/lib/fit-view.ts:7-14`.
  - `BuildAncestorChart::buildNode` (`app/Actions/Trees/BuildAncestorChart.php:68-121`) rozwija wykres jako drzewo bez zbioru odwiedzonych. Jedynym stopem jest `$generation < $this->generations`.
  - W bazie dev jest pętla (C8: osoba 560 jest swoim rodzicem). Test cyklu (`tests/Feature/AncestorsTest.php:202-213`) przechodzi tylko dzięki limitowi 4.
- **Wiek (C4):** `CardBody` (`resources/js/components/tree/sheet-person-card.tsx:76-80`) ma dwóch konsumentów: kartę drzewa (`:193`) i `AncestorCard` (`resources/js/components/tree/ancestor-card.tsx:41-47`).
- **Avatar (C5):** `CardAvatar` (`resources/js/components/tree/card-avatar.tsx:66-72`) daje `size-10 @xs:size-14`. `@xs` (20rem) nigdy nie zachodzi w kolumnie przodków (12–18rem), więc zdjęcie ma ok. 26 px po dopasowaniu.
- **Strażnik (C6):** `ancestor-chart.tsx` i `card-avatar.tsx` nie są w `VIEW_FILES` (`resources/js/lib/view-tokens.test.ts:14-56`). 7× `style=` w `ancestor-chart.tsx` to uzasadniona geometria liczona w runtime.
- **Głębokość danych dev:**
  - drzewo 1: 10 rzędów, ok. 170 kolumn (≈ 34 700 px przy 12.75rem, skala całości ≈ 0.03);
  - drzewo 2: 7 rzędów;
  - drzewo 6: 5 rzędów.

## Desired End State

- `/tree` ma w rzędzie tytułu przycisk „Przodkowie”, który otwiera `/tree/{focus}/ancestors` bez parametru, czyli z domyślną głębokością.
- Przełącznik „Pokolenia” pokazuje każdą liczbę od 2 do N, gdzie N to dostępna głębokość przodków tej osoby. Przy jednej opcji (N = 2) przełącznika nie ma.
- Domyślna głębokość to `min(4, N)`. `?generations=` większe niż N daje N. Wartość poniżej 2 albo nieliczbowa daje wartość domyślną.
- Wykres jest odporny na cykl w danych: osoba, która już jest na bieżącej ścieżce, pojawia się jeszcze raz jako karta „Występuje kilka razy” bez rodziców. Budowa wykresu i liczenie N nigdy nie przekraczają technicznego sufitu 20 pokoleń.
- Samo kółko przybliża i oddala widok przodków. Jeden ząbek myszy to mały krok. Drzewo (`family-sheet.tsx`) nadal nie ma zoomu.
- Start widoku:
  - wykres mieszczący się w skali ≥ `ANCESTORS_FIT_FLOOR` (0.1) startuje w całości, jak dziś;
  - większy startuje zakotwiczony na osobie w centrum w czytelnej skali (`ANCESTORS_ANCHOR_SCALE`);
  - w obu przypadkach kółko i „Pomniejsz” oddalają aż do całości.
- Karta przodka nie ma wieku i ma avatar `size-14` obok imienia. Karta drzewa wygląda jak przed zmianą.
- `view-tokens.test.ts` pilnuje `ancestor-chart.tsx` (z wyjątkiem `style={`) i `card-avatar.tsx`. `CLAUDE.md` mówi, gdzie żyją opcje karty przodka i że zoomuje tylko widok przodków.

Weryfikacja: Pest (`AncestorsTest.php`), Vitest (`fit-view.test.ts`, `view-tokens.test.ts`), nowe ryzyka E2E oraz zrzuty „after” w `context/changes/ancestors-view-polish/screenshots/`.

### Key Discoveries:

- `ParentChildGraph::ancestorIdsWithin` (`app/Support/ParentChildGraph.php:86-111`) jest odporny na cykl, ale idzie poziomami z `$visited`, więc liczy najkrótszą odległość. Nie wyznacza głębokości wykresu: przy zawężeniu linii (pedigree collapse) wspólny przodek ma rodziców głębiej niż jego najkrótszy poziom. N trzeba liczyć tą samą regułą, którą buduje się wykres.
- Rząd placeholderów pojawia się tylko jako rodzice znanej osoby, a górny rząd (`generation == N`) ma `parents: []`. Przy N = najgłębsze pokolenie ze znaną osobą wykres nie kończy się więc rzędem samych placeholderów. Osoba bez rodziców ma N = 2 (rząd „Nieznany ojciec/matka”, jak dziś).
- `ZoomCanvas` używa `floor` jednocześnie jako `minScale` (`zoom-canvas.tsx:592`), progu startu i skali zakotwiczenia (`chooseFitView`, `fit-view.ts`). Drzewo przekazuje `TREE_FIT_FLOOR` i nie ma zoomu. Nowe zachowanie musi być opcjonalne, żeby drzewo się nie zmieniło.
- Pusta lista `activationKeys` w `react-zoom-pan-pinch` oznacza, że kółko działa zawsze. Szczypanie touchpada przychodzi jako wheel z `ctrlKey`, więc działa dalej. `onWheelStart={markMoved}` już chroni ręczny zoom przed ponownym dopasowaniem.
- `size-14` to rozmiar, który karta drzewa już ma przy szerokiej kolumnie (`@xs:size-14`). Przy kolumnie 12rem imię zachowuje ok. 6.25rem (≈ 100 px).
- `tests/e2e/tree-fill-window.spec.ts:155-171` mierzy obszar drzewa od dołu h1. Przycisk musi siedzieć w tym samym rzędzie co `Heading` i nie może go podwyższać.

## What We're NOT Doing

- **C7 — mobile 390 px:** wykres dalej startuje mały, a przyciski zoomu mają własny rząd. Dotyczy to tylko wykresów, które mieszczą się w progu; głęboki wykres startuje na osobie także na telefonie. Odroczone do osobnej zmiany.
- **C8 — naprawa danych i walidacja** (`CreateParentChildRelation.php:38` nie odrzuca relacji „sam swoim rodzicem”): osobna zmiana o integralności danych. Tutaj wykres jest tylko odporny na cykl.
- **Zoom na drzewie** (`family-sheet.tsx`): bez zmian, zakaz z `context/archive/2026-10-05-tree-fits-screen/` nadal obowiązuje.
- **Opcja „Wszystkie” w przełączniku:** odrzucona, użytkownik wybrał każdą liczbę 2…N.
- **Ukrywanie `age` w backendzie:** `SheetPersonResource` dalej wysyła `age`, ukrywamy go w UI karty przodka.
- **Usunięcie pozycji „Pokaż przodków” z menu „⋯”:** zostaje, korzystają z niej E2E i panel.
- **Ctrl+kółko nad paskiem przycisków zoomu** (zoom strony przeglądarki): drobiazg, bez zmian.
- **Zmiana tokenów i palety:** skan daje 0 trafień, faza tokenów nie dotyczy tej zmiany.

## Implementation Approach

Kolejność `/10x-ui`: biblioteka → tokeny (N/A) → widok → stany.
1. Backend daje kontrakt, na którym stoi przełącznik: `available_generations`, przycięcie adresu i stop na ścieżce.
2. Widok przodków korzysta z tego kontraktu i dostaje kółko oraz start głębokiego wykresu. Zmiany w `ZoomCanvas` są opcjonalnymi propsami, więc drzewo zostaje nietknięte.
3. Karta przodka dostaje własne opcje na istniejącym `CardBody`/`CardAvatar` (bez nowego komponentu), a `/tree` dostaje przycisk wejścia.
4. Ostatnia faza dodaje ryzyka E2E, macierz stanów, zrzuty „after” i zabezpieczenie (strażnik tokenów i reguła).

## Critical Implementation Details

**Głębokość N a budowa wykresu.** N musi być liczone tą samą regułą co `buildNode`: rodzice osoby, chyba że jest już na bieżącej ścieżce, z sufitem 20. Wtedy wykres przy `generations = N` zawsze ma znaną osobę w górnym rzędzie, a przełącznik nie oferuje głębokości, która dałaby pusty rząd. `ancestorIdsWithin` (poziomy BFS) nie nadaje się do tego, patrz Key Discoveries. Osoby i tak trzeba załadować do pełnej głębokości wykresu, nie do najkrótszej odległości.

**Wydajność przy pełnej głębokości.** Drzewo 1 z dev przy N = 10 to ok. 170 kolumn i kilkaset kart. Liczba zapytań musi zostać stała (graf i osoby w jednym zapytaniu każde), a liczenie N nie może rosnąć wykładniczo przy zawężeniu linii. Przy zawężeniu zapamiętujemy wynik dla osoby, a sufit 20 ogranicza najgorszy przypadek.

## Faza 1: Backend — dostępna głębokość i odporność na cykl

### Overview

Kontroler i akcja przestają znać zakres 2–6. Akcja liczy dostępną głębokość N, buduje wykres ze stopem na ścieżce, a kontroler przycina żądaną głębokość do N.

### Changes Required:

#### 1. Stop na ścieżce i dostępna głębokość

**File**: `app/Actions/Trees/BuildAncestorChart.php`

**Intent**: rekurencja dostaje zbiór osób na bieżącej ścieżce. Osoba, która już na niej jest, staje się węzłem bez rodziców. Licznik `occurrences` dalej ją widzi, więc `markRepeated` oznaczy ją „Występuje kilka razy”. Akcja liczy też N, czyli liczbę rzędów wykresu zbudowanego tą regułą do sufitu: co najmniej 2, najwyżej 20.

**Contract**:
- `handle(Person $person, ?int $generations)` przyjmuje żądaną głębokość po walidacji kontrolera (`null` = domyślna), przycina ją do N i zwraca `array{focus: SheetPerson, generations: int, available_generations: int, root: array<string, mixed>}`.
- Stała `MAX_GENERATIONS = 20` w akcji to sufit techniczny (PHPDoc: zabezpieczenie, nie limit produktowy).
- Ładowanie osób obejmuje pełną głębokość wykresu, nie najkrótszą odległość z `ancestorIdsWithin`.
- Wykres i N muszą się zgadzać: dla `generations = N` najgłębszy rząd zawiera znaną osobę.

#### 2. Liczenie głębokości w grafie

**File**: `app/Support/ParentChildGraph.php`

**Intent**: nowa metoda zwraca liczbę pokoleń najdłuższej linii przodków osoby. Krawędź prowadząca do osoby już na ścieżce zamyka cykl i jest pomijana. Wynik jest zapamiętywany per osoba i ucięty do sufitu. Dzięki temu liczba operacji nie zależy wykładniczo od zawężeń linii.

**Contract**: `public function ancestorGenerations(int $personId, int $ceiling): int`. Zwraca 1 dla osoby bez rodziców. Akcja zamienia to na `max(2, …)`. Metoda jest odporna na cykl i działa bez zapytań, na grafie już w pamięci.

#### 3. Parametr głębokości

**File**: `app/Http/Controllers/TreeAncestorsController.php`

**Intent**: znikają `MAX_GENERATIONS = 6` i fallback „poza zakresem → 4”. Kontroler rozróżnia brak/śmieci (→ domyślna) od liczby ≥ 2 (→ przekazana akcji, która przycina do N). Domyślna to `min(4, N)`, więc liczy ją akcja, która zna N.

**Contract**: `generationsParameter()` zwraca `?int`. `null` oznacza „użyj domyślnej”: brak parametru, wartość nieliczbowa albo < 2. Akcja przyjmuje `?int` i stosuje `min(4, N)` dla `null` oraz `min(żądana, N)` dla liczby. PHPDoc na kontrolerze opisuje regułę.

#### 4. Typ payloadu

**File**: `resources/js/types/tree.ts`

**Intent**: klient dostaje N, żeby zbudować opcje przełącznika.

**Contract**: `AncestorChart` dostaje pole `available_generations: number`.

#### 5. Testy Pest

**File**: `tests/Feature/AncestorsTest.php`

**Intent**: przypiąć nowe reguły i zaktualizować testy, które przypinały 2–6. To celowa zmiana zachowania, a nie dopasowanie asercji do błędu.

**Contract**:
- Nowe przypadki:
  - `available_generations` równe najgłębszemu pokoleniu ze znaną osobą (linia 7 pokoleń → 7);
  - osoba bez rodziców → 2;
  - osoba z samymi rodzicami → 2;
  - przy zawężeniu linii N bierze dłuższą ścieżkę;
  - domyślnie `min(4, N)` (linia 3 pokoleń → 3; linia 6 pokoleń → 4);
  - `generations` większe niż N (np. `9` przy N = 5) → N;
  - głębokość > 6 (np. 8) jest obsługiwana;
  - sufit: linia dłuższa niż 20 daje N = 20 i wykres 20 rzędów.
- Zmiana testu `:117-126`: dataset `['1', 'abc']` (oraz pusta wartość) → domyślna. `'7'` przenosi się do testu przycięcia.
- Zmiana testu cyklu `:202-213`: dziecko i rodzic w pętli dają wykres `[child, parent, child]`, gdzie powtórzone dziecko nie ma rodziców i ma `repeated = true`, a `available_generations = 3`. Test działa przy żądaniu pełnej głębokości (`generations=20`), nie przy domyślnym 4.
- Zmiana testu liczby zapytań `:215-231`: zamiast `generations=6` pełna głębokość (`generations=20`), a liczba zapytań jest równa dla głębokości 0 i 8.
- `tests/Feature/PersonsTest.php:1260-1264` (`generations=3` w URL) bez zmian, ale musi dalej przechodzić.

### Success Criteria:

#### Automated Verification:

- Testy widoku przodków przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php`
- Testy panelu osoby przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Larastan przechodzi: `vendor/bin/sail bin phpstan analyse`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- W bazie dev `/tree/560/ancestors?generations=20` (osoba w pętli) odpowiada szybko i pokazuje powtórzoną kartę zamiast pętli do sufitu
- W bazie dev wykres najgłębszej osoby drzewa 1 przy pełnej głębokości ładuje się bez odczuwalnego opóźnienia serwera

**Implementation Note**: po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 2: Widok przodków — przełącznik, kółko, start głębokiego wykresu

### Overview

Przełącznik buduje opcje z `available_generations`. `ZoomCanvas` dostaje opcjonalne zachowanie tylko dla przodków: zoom samym kółkiem, czytelne zakotwiczenie głębokiego wykresu i oddalanie do całości.

### Changes Required:

#### 1. Przełącznik z danych

**File**: `resources/js/pages/tree/ancestors.tsx`

**Intent**: `GENERATION_ITEMS` z 2–6 zastępują opcje 2…`available_generations`. Przy jednej opcji przełącznik i jego etykieta „Pokolenia” nie są renderowane. Przy wielu opcjach (do 19) przełącznik nie może wymuszać poziomego przewijania strony: zawija się w swoim rzędzie (rząd nagłówka ma już `flex-wrap`) albo przewija się w poziomie we własnym kontenerze. Wybór należy do implementującego, kryterium jest manualne poniżej.

**Contract**: komentarz nad opcjami mówi, że zakres pochodzi z serwera (`available_generations`). `changeGenerations` bez zmian.

#### 2. Kółko i start w `ZoomCanvas`

**File**: `resources/js/components/zoom-canvas.tsx`

**Intent**:
- Gdy `zoomControls`, kółko działa bez klawisza i z małym krokiem. Gałąź bez `zoomControls` (drzewo) zostaje `{ disabled: true }`.
- Nowy opcjonalny prop pozwala dać zakotwiczonemu startowi inną skalę niż próg. Domyślnie skala = `floor`, więc drzewo działa jak dziś.
- Gdy `zoomControls`, `minScale` to niższa z wartości `floor` i skali, w której mieści się cała treść. Dzięki temu kółko i „Pomniejsz” oddalają do całości także przy głębokim wykresie.
- „Dopasuj” dalej uruchamia to samo dopasowanie co start, więc przy głębokim wykresie wraca na osobę.

**Contract**:
- Nowy prop `anchorScale?: number` (domyślnie `floor`).
- `wheel={zoomControls ? { step: ANCESTORS_WHEEL_STEP } : { disabled: true }}`, bez `activationKeys`.
- `minScale` dla `zoomControls` liczone z rozmiaru treści przy dopasowaniu (stan aktualizowany w `fit`).
- Komentarze propsów i komponentu (`:59`, `:91`) mówią „kółko” zamiast „Ctrl+wheel”.

#### 3. Stałe i czyste funkcje dopasowania

**File**: `resources/js/lib/fit-view.ts`

**Intent**: `chooseFitView` przyjmuje opcjonalną skalę zakotwiczenia (domyślnie `floor`) i dla `kind: 'anchor'` zwraca ją zamiast progu. Nowe stałe ustawia się w przeglądarce.

**Contract**:
- `chooseFitView({ …, anchorScale? })`.
- `ANCESTORS_ANCHOR_SCALE` (start 0.75, tyle co `TREE_FIT_FLOOR`; kryterium: imię na karcie osoby w centrum jest czytelne na 1366×768).
- `ANCESTORS_WHEEL_STEP` (kryterium: jeden ząbek myszy, deltaY 100, zmienia skalę o mniej niż 25%, a touchpad nie przeskakuje do min/max).
- Komentarz `ANCESTORS_FIT_FLOOR` (`:7-14`) mówi, że próg decyduje o tym, czy startujemy z całości, a nie że jest dolną granicą zoomu. Komentarz `COLUMN_MAX_REM` (`:29`) mówi o avatarze `@xs`, a nie o „strip”.

#### 4. Wykres przodków

**File**: `resources/js/components/tree/ancestor-chart.tsx`

**Intent**: przekazuje `anchorScale={ANCESTORS_ANCHOR_SCALE}`. Kotwica to dalej karta osoby w centrum (`anchorRef`).

**Contract**: bez zmian w geometrii i `style=`.

#### 5. Testy Vitest

**File**: `resources/js/lib/fit-view.test.ts`

**Intent**: przypiąć regułę startu.

**Contract**:
- `chooseFitView` z `anchorScale` zwraca `{ kind: 'anchor', scale: anchorScale }`, gdy całość jest poniżej progu.
- Bez `anchorScale` zachowanie się nie zmienia (`scale: floor`).
- Gdy całość mieści się ≥ progu, `anchorScale` jest ignorowane.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi, w tym nowe przypadki `chooseFitView`: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Istniejące E2E przodków przechodzą: `npx playwright test tests/e2e/ancestors-view.spec.ts`

#### Manual Verification:

- Na widoku przodków samo kółko myszy przybliża do kursora małym krokiem; szczypanie touchpada działa; na `/tree` kółko nadal nie zoomuje
- Osoba z 4 pokoleniami: przełącznik 2…N, start w całości jak przed zmianą
- Najgłębsza osoba drzewa 1 (N = 10): przełącznik 2…10 bez poziomego przewijania strony przy 1366 px i 390 px; przy 10 start zakotwiczony na osobie w czytelnej skali; kółkiem lub „Pomniejsz” da się oddalić do całości; „Dopasuj” wraca na osobę
- Osoba bez rodziców i osoba z samymi rodzicami: brak przełącznika, wykres z rodzicami/placeholderami
- Przewijanie i przeciąganie głębokiego wykresu (kilkaset kart) jest płynne

**Implementation Note**: po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 3: Karta przodka i wejście z drzewa

### Overview

Karta przodka dostaje własne opcje na wspólnym `CardBody`: bez wieku i z avatarem `size-14`. Karta drzewa zostaje bez zmian. `/tree` dostaje przycisk „Przodkowie”.

### Changes Required:

#### 1. Opcje karty

**File**: `resources/js/components/tree/sheet-person-card.tsx`, `resources/js/components/tree/card-avatar.tsx`

**Intent**: `CardBody` dostaje jedną opcję dla karty przodka, która ukrywa wiersz wieku i ustawia stały avatar `size-14`. `CardAvatar` zamienia `large?: boolean` na wariant rozmiaru, żeby trzy rozmiary (karta drzewa, przodek, para) były jednym miejscem. Karta pary dalej używa `size-16`.

**Contract**:
- `CardAvatar({ person, size?: 'card' | 'ancestor' | 'pair' })`: `card` = `size-10 @xs:size-14` (domyślny), `ancestor` = `size-14`, `pair` = `size-16`. Konsument pary (`sheet-person-card.tsx:141`) przechodzi z `large` na `size="pair"`.
- `CardBody` dostaje prop `variant?: 'tree' | 'ancestor'` (domyślnie `tree`). `ancestor` oznacza brak wieku i `CardAvatar size="ancestor"`.
- PHPDoc/JSDoc `CardBody` (`:30-37`) opisuje oba warianty.

**File**: `resources/js/components/tree/ancestor-card.tsx`

**Intent**: przekazuje `variant="ancestor"`.

**Contract**: bez innych zmian.

#### 2. Nieaktualne komentarze po `f641432`

**File**: `resources/js/components/tree/card-styles.ts`

**Intent**: `:10-13` i `:19` mówią o „photo strip”. Poprawić na okrągły avatar i kontener `@container` dla jego rozmiaru.

**Contract**: tylko komentarze.

#### 3. Przycisk „Przodkowie” na `/tree`

**File**: `resources/js/pages/tree/show.tsx`

**Intent**: rząd tytułu (`:79-81`) staje się rzędem `Heading` + akcja, wzorem `ancestors.tsx:55-67` (`flex flex-wrap items-center justify-between`). Akcja to `Button` (`variant="outline"`, `size="sm"`) jako `Link` do `treeAncestors(sheet.focus.id)` bez query, z ikoną lucide i etykietą „Przodkowie”. Stan pusty (bez probanta) nie ma przycisku. Rząd nie może być wyższy niż dziś (patrz `tree-fill-window.spec.ts:155-171`).

**Contract**: URL z Wayfinder (`@/routes/tree`), bez hardkodu. Dostępna nazwa linku: „Przodkowie”.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi (w tym niezmieniony `view-tokens.test.ts`): `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- E2E drzewa i przodków przechodzą: `npx playwright test tests/e2e/tree-fill-window.spec.ts tests/e2e/ancestors-view.spec.ts`
- Skan zakodowanych wartości na plikach widoku daje 0 trafień (poza 7× `style=` w `ancestor-chart.tsx`)

#### Manual Verification:

- Karta przodka nie ma wieku, avatar jest wyraźnie większy niż przed zmianą (porównanie z `screenshots/before-focus-card.png`), imię obok avatara nie zachodzi na niego przy kolumnie 12rem
- Karty drzewa (`/tree`) wyglądają jak przed zmianą: wiek jest, avatar `size-10`/`size-14`, karta pary `size-16`
- Przycisk „Przodkowie” na `/tree` otwiera widok przodków osoby w centrum z domyślną głębokością; rząd tytułu nie jest wyższy

**Implementation Note**: po tej fazie i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed kolejną fazą.

---

## Faza 4: E2E, bramka wizualna i zabezpieczenie

### Overview

Nowe ryzyka E2E przez `/10x-e2e` (kolejność z `CLAUDE.md`), macierz 7 stanów, zrzuty „after” i zabezpieczenie: strażnik tokenów oraz reguła w `CLAUDE.md`.

### Changes Required:

#### 1. Nowe ryzyka E2E

**File**: `tests/e2e/ancestors-view.spec.ts` (lub nowa specyfikacja obok, decyzja `/10x-e2e`)

**Intent**: każde ryzyko to osobny niezależny test z własnymi danymi (sufiks timestamp) i sprzątaniem, z lokatorami `getByRole`/`getByLabel`/`getByText`, bez `waitForTimeout`.
- **R1 — kółko:** jeden ruch kółka (deltaY -100, bez Ctrl) nad wykresem zwiększa skalę o mniej niż 25%. Na `/tree` ten sam ruch nie zmienia skali.
- **R2 — przełącznik z danych:** linia 3 pokoleń daje opcje dokładnie „2”, „3”. Osoba z samymi rodzicami nie ma grupy „Pokolenia”. `?generations=9` przy N = 3 pokazuje 3 zaznaczone.
- **R3 — głęboki start:** linia 8 pokoleń przy `generations=8` startuje zakotwiczona: karta osoby w centrum jest widoczna, a skala ≥ `ANCESTORS_ANCHOR_SCALE`. „Pomniejsz”/kółko oddalają poniżej `ANCESTORS_FIT_FLOOR`.
- **R4 — wejście z drzewa:** link „Przodkowie” na `/tree/{person}` prowadzi do `/tree/{person}/ancestors`, a h1 ma imię tej osoby.

**Contract**: specyfikacje przechodzą samodzielnie i równolegle. Każda przechodzi weryfikację przez celowe wprowadzenie błędu (`/10x-e2e`).

#### 2. Strażnik tokenów

**File**: `resources/js/lib/view-tokens.test.ts`

**Intent**: dopisać `components/tree/ancestor-chart.tsx` i `components/tree/card-avatar.tsx` do `VIEW_FILES`. Dla `ancestor-chart.tsx` dodać wyjątek `style={` jak dla `family-sheet.tsx` (geometria rem liczona w runtime). Zaktualizować komentarz `:14-20`, który dziś mówi, że `ancestor-chart.tsx` jest pominięty celowo.

**Contract**: test przechodzi. Dodanie klasy palety do któregoś z tych plików go łamie (sprawdzone ręcznie i cofnięte).

#### 3. Reguła

**File**: `CLAUDE.md` (sekcja „UI: tokens and shared components”, poza blokiem 10x-cli)

**Intent**: dwie krótkie reguły dla następnego agenta:
1. Karta przodka ma własny wariant (`CardBody variant="ancestor"`, `CardAvatar size`): bez wieku, avatar `size-14`. Zmiana karty drzewa nie zmienia karty przodka bez decyzji w planie.
2. Zoom ma tylko widok przodków (samo kółko, przyciski, szczypanie). Drzewo nie zoomuje. Zakres pokoleń przodków przychodzi z serwera (`available_generations`), nie z listy w kliencie.

**Contract**: dopisek do istniejącej sekcji, bez nowego pliku reguł.

#### 4. Zrzuty i macierz 7 stanów

**File**: `context/changes/ancestors-view-polish/screenshots/`

**Intent**: zrzuty „after” na serwerze E2E w tych samych ustawieniach co „before”:
- `after-ancestors-4gen-1366x768.png`, `after-ancestors-4gen-390x844.png`;
- `after-ancestors-8gen-1366x768.png` (start zakotwiczony);
- `after-focus-card.png`;
- `after-tree-show-1366x768.png` (przycisk);
- `after-states-*.png` dla stanów pokazywalnych.

**Contract**: macierz stanów jest kryterium manualnym poniżej. Każda komórka jest pokazana na zrzucie albo oznaczona N/A z powodem.

### Success Criteria:

#### Automated Verification:

- Nowe i istniejące E2E przodków przechodzą: `npx playwright test tests/e2e/ancestors-view.spec.ts`
- E2E drzewa przechodzą: `npx playwright test tests/e2e/tree-fill-window.spec.ts`
- Vitest przechodzi z rozszerzonym `VIEW_FILES`: `vendor/bin/sail npm run test:js`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Testy Pest przodków przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php`

#### Manual Verification:

- Każde nowe ryzyko E2E (R1–R4) czerwienieje po celowym wprowadzeniu błędu i zielenieje po jego cofnięciu
- Zrzuty „after” (desktop 1366×768 i 390×844) zapisane i porównane z „before”: wiek zniknął, avatar większy, przycisk na `/tree`, start głębokiego wykresu na osobie
- Macierz 7 stanów: każda komórka pokazana na zrzucie albo N/A z powodem
  - default: zrzut 4 pokoleń;
  - hover: obramowanie karty przodka i przycisk „Przodkowie”;
  - focus-visible: przycisk „Przodkowie”, segment przełącznika, karta przodka (pierścień `focusRing`);
  - disabled: N/A (przełącznik nie ma wyłączonych opcji, a przy jednej opcji jest ukryty; przyciski zoomu nie mają stanu wyłączonego);
  - error: N/A (widok tylko do odczytu, bez formularza; cudza osoba to 404 pokryte Pest);
  - empty: osoba bez rodziców, czyli wykres z placeholderami bez przełącznika;
  - loading: zmiana pokoleń nie skacze układem, wykres jest ukryty do dopasowania (`visibility` w `ZoomCanvas`).
- Reguła w `CLAUDE.md` jest dopisana, a strażnik łamie się na celowo dodanej klasie palety w `card-avatar.tsx` (cofniętej)

**Implementation Note**: po tej fazie uruchom `/10x-impl-review`. Ustalenia UI nie są domyślnie „cosmetic skip”.

---

## Testing Strategy

### Unit Tests:

- Vitest `fit-view.test.ts`: `chooseFitView` z `anchorScale` i bez niego, granica progu.
- Vitest `view-tokens.test.ts`: rozszerzone `VIEW_FILES`.
- Pest `AncestorsTest.php`:
  - N (bez rodziców, sami rodzice, linia, zawężenie linii, sufit);
  - domyślna `min(4, N)`, przycięcie, śmieci → domyślna;
  - cykl przy pełnej głębokości;
  - stała liczba zapytań przy pełnej głębokości.

### Integration Tests:

- E2E R1–R4 (kółko, przełącznik z danych, głęboki start, wejście z drzewa) oraz dotychczasowe `ancestors-view.spec.ts` i `tree-fill-window.spec.ts`.

### Manual Testing Steps:

1. `/tree` → „Przodkowie” → widok przodków osoby w centrum, domyślnie `min(4, N)` pokoleń.
2. Kółko myszy nad wykresem: mały krok, zoom do kursora. Touchpad: szczypanie.
3. Najgłębsza osoba drzewa 1 w dev: przełącznik 2…10, wybór 10 → start na osobie, oddalenie do całości, „Dopasuj”.
4. Osoba 560 (pętla, dev): pełna głębokość bez zawieszenia, karta „Występuje kilka razy”.
5. Osoba bez rodziców: brak przełącznika, placeholdery.
6. Karta przodka bez wieku i z większym avatarem. Karta drzewa bez zmian.

## Performance Considerations

- Liczba zapytań stała (jedno zapytanie na graf, jedno na osoby) niezależnie od głębokości. Przypięte testem Pest.
- Liczenie N z zapamiętywaniem per osoba i sufitem 20. Bez tego zawężenia linii dają wykładniczą liczbę ścieżek.
- Najgłębszy wykres dev (ok. 170 kolumn, kilkaset kart i linii) sprawdzony ręcznie pod kątem płynności przewijania i zoomu. Pomiar nie był robiony w researchu.

## Migration Notes

Brak migracji i zmian danych. Pętla w danych dev (C8) zostaje, a wykres jest na nią odporny.

## References

- Research (zarzuty, liczniki, zrzuty „before”): `context/changes/ancestors-view-polish/research.md`
- Poprzednia zmiana widoku przodków: `context/archive/2026-10-10-ancestors-view/plan.md`, `plan-brief.md`
- Wypełnienie okna i wspólna karta: `context/archive/2026-10-19-tree-dashboard-fill-window/plan.md`
- Wzorzec rzędu tytułu z akcją: `resources/js/pages/tree/ancestors.tsx:55-67`
- Wzorzec wyjątku `style={` w strażniku: `resources/js/lib/view-tokens.test.ts` (`family-sheet.tsx`)
- Konfiguracja kółka w bibliotece: `node_modules/react-zoom-pan-pinch/dist/index.esm.js:2777-2779` (pusta lista `activationKeys`)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend — dostępna głębokość i odporność na cykl

#### Automated

- [x] 1.1 Testy widoku przodków przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php` — c74c578
- [x] 1.2 Testy panelu osoby przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php` — c74c578
- [x] 1.3 Larastan przechodzi: `vendor/bin/sail bin phpstan analyse` — c74c578
- [x] 1.4 Typy przechodzą: `vendor/bin/sail npm run types:check` — c74c578
- [x] 1.5 Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent` — c74c578

#### Manual

- [x] 1.6 W bazie dev `/tree/560/ancestors?generations=20` (osoba w pętli) odpowiada szybko i pokazuje powtórzoną kartę zamiast pętli do sufitu — c74c578
- [x] 1.7 W bazie dev wykres najgłębszej osoby drzewa 1 przy pełnej głębokości ładuje się bez odczuwalnego opóźnienia serwera — c74c578

### Phase 2: Widok przodków — przełącznik, kółko, start głębokiego wykresu

#### Automated

- [x] 2.1 Vitest przechodzi, w tym nowe przypadki `chooseFitView`: `vendor/bin/sail npm run test:js` — ed1bece
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — ed1bece
- [x] 2.3 Format i lint przechodzą: `vendor/bin/sail npm run check` — ed1bece
- [x] 2.4 Build przechodzi: `vendor/bin/sail npm run build` — ed1bece
- [x] 2.5 Istniejące E2E przodków przechodzą: `npx playwright test tests/e2e/ancestors-view.spec.ts` — ed1bece

#### Manual

- [x] 2.6 Na widoku przodków samo kółko myszy przybliża do kursora małym krokiem; szczypanie touchpada działa; na `/tree` kółko nadal nie zoomuje — ed1bece
- [x] 2.7 Osoba z 4 pokoleniami: przełącznik 2…N, start w całości jak przed zmianą — ed1bece
- [x] 2.8 Najgłębsza osoba drzewa 1 (N = 10): przełącznik 2…10 bez poziomego przewijania strony przy 1366 px i 390 px; przy 10 start zakotwiczony na osobie w czytelnej skali; kółkiem lub „Pomniejsz” da się oddalić do całości; „Dopasuj” wraca na osobę — ed1bece
- [x] 2.9 Osoba bez rodziców i osoba z samymi rodzicami: brak przełącznika, wykres z rodzicami/placeholderami — ed1bece
- [x] 2.10 Przewijanie i przeciąganie głębokiego wykresu (kilkaset kart) jest płynne — ed1bece

### Phase 3: Karta przodka i wejście z drzewa

#### Automated

- [x] 3.1 Vitest przechodzi (w tym niezmieniony `view-tokens.test.ts`): `vendor/bin/sail npm run test:js` — dcec54f
- [x] 3.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — dcec54f
- [x] 3.3 Format i lint przechodzą: `vendor/bin/sail npm run check` (global check padł na nieśledzonym `storage/gen-tree-import/`, poza zakresem; check na plikach fazy przeszedł) — dcec54f
- [x] 3.4 Build przechodzi: `vendor/bin/sail npm run build` — dcec54f
- [x] 3.5 E2E drzewa i przodków przechodzą: `npx playwright test tests/e2e/tree-fill-window.spec.ts tests/e2e/ancestors-view.spec.ts` — dcec54f
- [x] 3.6 Skan zakodowanych wartości na plikach widoku daje 0 trafień (poza 7× `style=` w `ancestor-chart.tsx`) — dcec54f

#### Manual

- [x] 3.7 Karta przodka nie ma wieku, avatar jest wyraźnie większy niż przed zmianą (porównanie z `screenshots/before-focus-card.png`), imię obok avatara nie zachodzi na niego przy kolumnie 12rem — dcec54f
- [x] 3.8 Karty drzewa (`/tree`) wyglądają jak przed zmianą: wiek jest, avatar `size-10`/`size-14`, karta pary `size-16` — dcec54f
- [x] 3.9 Przycisk „Przodkowie” na `/tree` otwiera widok przodków osoby w centrum z domyślną głębokością; rząd tytułu nie jest wyższy — dcec54f

### Phase 4: E2E, bramka wizualna i zabezpieczenie

#### Automated

- [x] 4.1 Nowe i istniejące E2E przodków przechodzą: `npx playwright test tests/e2e/ancestors-view.spec.ts`
- [x] 4.2 E2E drzewa przechodzą: `npx playwright test tests/e2e/tree-fill-window.spec.ts`
- [x] 4.3 Vitest przechodzi z rozszerzonym `VIEW_FILES`: `vendor/bin/sail npm run test:js`
- [x] 4.4 Format i lint przechodzą: `vendor/bin/sail npm run check` (global check pada na nieśledzonym `storage/gen-tree-import/`, poza zakresem; `vp check` na plikach fazy przeszedł)
- [x] 4.5 Testy Pest przodków przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php`

#### Manual

- [x] 4.6 Każde nowe ryzyko E2E (R1–R4) czerwienieje po celowym wprowadzeniu błędu i zielenieje po jego cofnięciu
- [x] 4.7 Zrzuty „after” (desktop 1366×768 i 390×844) zapisane i porównane z „before”: wiek zniknął, avatar większy, przycisk na `/tree`, start głębokiego wykresu na osobie
- [x] 4.8 Macierz 7 stanów: każda komórka pokazana na zrzucie albo N/A z powodem
- [x] 4.9 Reguła w `CLAUDE.md` jest dopisana, a strażnik łamie się na celowo dodanej klasie palety w `card-avatar.tsx` (cofniętej)
