# Widok przodków (S-15 `ancestors-view`) — plan implementacji

## Overview

Nowa strona tylko do oglądania: wybrana osoba i jej przodkowie w górę, w 2–6 pokoleniach (osoba to 1. pokolenie, domyślnie 4). Widok startuje z dopasowaną całością, ma przyciski „Powiększ / Pomniejsz / Dopasuj” oraz Ctrl+kółko i szczypanie. Kliknięcie karty otwiera panel osoby. Wejście i przecentrowanie prowadzi wyłącznie pozycja „Pokaż przodków” w menu „⋯” panelu osoby. Realizuje FR-018 i FR-029 (cudza osoba → 404) z roadmapy S-15.

## Current State Analysis

Pełna mapa kodu: `context/changes/ancestors-view/research.md`. Najważniejsze dla planu:

- **Graf:** `ParentChildGraph::ancestorsOf()` (`app/Support/ParentChildGraph.php:75-78`) zwraca płaską listę BFS bez pokolenia, pozycji i limitu głębokości. Wzorzec odczytu drzewa: jedno zapytanie grafu i jedno zapytanie osób z `events` (`app/Actions/Trees/BuildFamilySheet.php:328-344`).
- **Kolejność i zastępniki rodziców:** `BuildFamilySheet::parentSlots()` (`:392-422`) sortuje `PersonOrdering::relatives()`, a potem prywatnym `rank()` (`:477-484`; mężczyzna 0, nieznana płeć 1, kobieta 2). Brakujący rodzic dostaje etykietę „Nieznany ojciec / Nieznana matka / Nieznany rodzic”, zależnie od płci znanego rodzica.
- **Dostęp:** `TreeController::show` autoryzuje przez `Gate::authorize('view', $person)` (`app/Http/Controllers/TreeController.php:39`), a `PersonPolicy::view` odpowiada `denyAsNotFound()`. Test kompletności `ownershipRouteCases()` (`tests/Feature/OwnershipBoundariesTest.php:162-177`) zawodzi, gdy trasa z `{person}` nie ma przypadku.
- **Strona Inertia:** `config/inertia.php:66` `ensure_pages_exist => true`, więc test `assertInertia` wymaga istnienia pliku strony już w fazie backendowej.
- **Płótno:** `ZoomCanvas` (`resources/js/components/zoom-canvas.tsx`) ma wyłączone kółko, szczypanie i dwuklik (`:285-295`) oraz brak kontrolek. Startowe dopasowanie liczy `chooseFitView` (`resources/js/lib/fit-view.ts:23-44`) z `floor`/`max` jako propsami. `react-zoom-pan-pinch` 4.2.0 obsługuje `wheel.activationKeys`.
- **Karta:** `SheetPersonCard` (`resources/js/components/tree/sheet-person-card.tsx:55-105`) linkuje ciałem do `tree.show`, a ikoną oka otwiera panel. `CardBody` (`:25-49`) jest prywatny. Style kart są w `card-styles.ts` (`placeholderStatic` = szara karta bez akcji).
- **Panel osoby:** `AppLayout` renderuje go na każdej stronie. `PersonPanel` czyta `props.sheet` tylko na drzewie (`resources/js/components/persons/person-panel.tsx:31-33`). Akcje nagłówka siedzą w `PersonPanelActions` (`resources/js/components/persons/person-modal-header.tsx:69-157`): „Pokaż na drzewie” jest przyciskiem od `sm` i pozycją menu „⋯”.
- **Usuwanie osoby:** `PersonController::urlAfterDelete` (`app/Http/Controllers/PersonController.php:140-165`) ma wyjątek tylko dla `tree.show` tej samej osoby (→ `tree.index`). Test wzorcowy: `tests/Feature/PersonsTest.php:1186-1188`.
- **Nawigacja:** aktywność pozycji paska bocznego to prefiks ścieżki (`resources/js/hooks/use-current-url.ts:62-67`), więc `/tree/{person}/ancestors` podświetla „Drzewo”.
- **Rozjazd dokumentacji:** FR-018 (`context/foundation/prd.md:150-152`) i roadmapa S-15 (`roadmap.md:306,313`) mówią „przybliżanie jak w drzewie”, a drzewo od 2026-10-05 nie ma ręcznego przybliżania. Follow-up F1 (`context/archive/2026-10-05-tree-fits-screen/follow-ups/review-fixes.md:5`) odkłada tę sprawę do S-15.

## Desired End State

- `GET /tree/{person}/ancestors?generations=N` (`tree.ancestors`) renderuje stronę `tree/ancestors`:
  - nagłówek z imieniem osoby i przełącznikiem „Pokolenia: 2 3 4 5 6”;
  - pod nim płótno z osobą na dole i przodkami w górę.
- Nieprawidłowe `generations` (brak, tekst, poza 2–6) daje 4. Cudza osoba daje 404, także w `?person=`.
- Każda znana osoba poniżej najwyższego pokolenia ma dokładnie dwie karty rodziców: znanych albo szare „Nieznany ojciec / Nieznana matka / Nieznany rodzic”. Nad szarą kartą nic nie ma, a układ nie rezerwuje dla niej miejsca.
- Osoba, która występuje w widoku więcej niż raz, jest pokazana w każdym miejscu razem ze swoimi przodkami i ma znacznik powtórzenia.
- Kliknięcie dowolnej karty osoby (także osoby w centrum) otwiera jej panel obok płótna. Płótno się zwęża, a stan żyje w `?person=`.
- W menu „⋯” panelu osoby jest pozycja „Pokaż przodków” (pod „Pokaż na drzewie”). Nagłówek panelu się nie zmienia, bo drugi przycisk przy panelu 24rem ucina tytuł z imieniem. Pozycja znika, gdy oglądamy przodków tej osoby. Przecentrowanie zachowuje wybraną liczbę pokoleń i otwarty panel.
- Drzewo (`tree/show`) zachowuje się dokładnie jak dziś: bez przybliżania i bez kontrolek.
- Weryfikacja: testy Pest, Vitest i E2E z faz poniżej oraz ręczny przegląd na `AncestorsDemoSeeder`.

### Key Discoveries:

- `PersonPanel` rozpoznaje stronę drzewa po propie `sheet` (`person-panel.tsx:31-33`). Strona przodków dostaje prop `chart`, nigdy `sheet`.
- `ZoomCanvas` ukrywa treść do pierwszego dopasowania (`ready`, `:113-117`) i dopasowuje ponownie przy zmianie rozmiaru, dopóki autor nie przesunął widoku (`movedRef`). „Dopasuj” i przybliżanie muszą działać w tym samym modelu.
- `isCurrentOrParentUrl` porównuje prefiks, więc trasa pod `/tree/...` nie wymaga zmian w pasku bocznym.
- E2E: `createPerson` / `createParent` (`tests/e2e/support/api.ts:144,178`), `openPerson` (`tests/e2e/support/navigation.ts:92`); szablon wielopokoleniowy to `tests/e2e/tree-card-recentre.spec.ts`.

## What We're NOT Doing

- Brak dodawania osób i relacji w widoku przodków (PRD Non-Goals). Szare karty są statyczne.
- Brak wejścia z nagłówka drzewa i z paska bocznego. Jedynym wejściem jest panel osoby (decyzja planu).
- Brak przybliżania na drzewie (`tree/show`). Kontrolki są włączane tylko w widoku przodków.
- Brak kompaktowych kart dla dalszych pokoleń. Wszystkie pokolenia mają kartę o tej samej treści i rozmiarze co drzewo.
- Brak pełnej siatki 2ⁿ z pustymi miejscami i brak kart-odsyłaczy dla powtórzeń.
- Brak oznaczenia „są dalsi przodkowie powyżej limitu” w najwyższym pokoleniu.
- Brak widoku potomków, małżonków i rodzeństwa w tym widoku.
- Brak zmian w archiwum (`context/archive/**`). Follow-up F1 zamyka się aktualizacją PRD i roadmapy, bez edycji zarchiwizowanego pliku.

## Implementation Approach

Serwer buduje gotowe drzewo węzłów przodków z limitem pokoleń, kolejnością rodziców i zastępnikami w tej samej regule co arkusz rodziny. Używa przy tym wzorca jednego zapytania grafu i jednego zapytania osób. Klient zamienia to drzewo w czystej, testowanej Vitestem funkcji na pozycje w kolumnach siatki CSS (jak `FamilySheet`) i linie w CSS bez SVG. Szerokość gałęzi wynika z liczby jej rzeczywistych kart. Siatka trafia do `ZoomCanvas` z nowym, opcjonalnym trybem przybliżania i niskim `floor`, więc widok startuje z całością. Wejście dokłada się na końcu do istniejącego `PersonPanelActions`.

## Critical Implementation Details

- **Kolejność faz:** strona `tree/ancestors.tsx` musi istnieć w fazie 1, bo `ensure_pages_exist` sprawia, że testy `assertInertia` zawodzą bez pliku. W fazie 1 wystarczy szkielet (nagłówek i breadcrumbs); płótno dochodzi w fazie 3.
- **„Dopasuj” a `movedRef`:** po „Dopasuj” widok wraca do stanu „dopasowany, nieprzesunięty”, więc zmiana rozmiaru (np. otwarcie panelu) znów dopasowuje. Ręczne przybliżenie (przycisk, Ctrl+kółko, szczypanie) liczy się jak przesunięcie: ustawia `movedRef`, żeby `ResizeObserver` nie cofał przybliżenia autora.
- **Kliknięcie po przesunięciu:** karta w widoku przodków jest linkiem `?person=`, więc istniejący `onClickCapture` w `ZoomCanvas` musi nadal połykać kliknięcie kończące przeciągnięcie, a przyciski przybliżania nie mogą rozpoczynać przesuwania (`ZOOM_NO_PAN_CLASS`).

## Faza 1: Dane przodków i trasa

### Overview

Serwer zwraca drzewo przodków z limitem pokoleń pod nową, chronioną trasą. Szkielet strony przechodzi `ensure_pages_exist`. Testy Pest pokrywają dane, dostęp i liczbę zapytań.

### Changes Required:

#### 1. Kolejność rodziców wspólna dla arkusza i widoku przodków

**File**: `app/Support/PersonOrdering.php`, `app/Actions/Trees/BuildFamilySheet.php`

**Intent**: Wydzielić regułę „mężczyzna z lewej, nieznana płeć pośrodku, kobieta z prawej, remis według daty urodzenia” do `PersonOrdering`, żeby arkusz i widok przodków ustawiały rodziców identycznie.

**Contract**: `PersonOrdering::parents(Collection<int, Person>): Collection<int, Person>` = `relatives()`, a potem stabilne sortowanie według płci (Male 0, null 1, Female 2). `BuildFamilySheet::parentSlots()` używa tej metody, a prywatne `rank()` znika. Zachowanie arkusza się nie zmienia, co pilnuje istniejący `tests/Feature/TreeTest.php`.

#### 2. Przodkowie z limitem pokoleń w grafie

**File**: `app/Support/ParentChildGraph.php`

**Intent**: Zebrać id wszystkich przodków do zadanej głębokości, żeby akcja mogła załadować osoby jednym zapytaniem. Przechodzenie musi być odporne na cykl i na więcej niż dwóch rodziców w danych.

**Contract**: `ancestorIdsWithin(int $personId, int $generations): list<int>`, czyli id przodków co najwyżej `$generations - 1` poziomów nad osobą (osoba to 1. pokolenie), bez samej osoby i bez duplikatów.

#### 3. Akcja budująca widok

**File**: `app/Actions/Trees/BuildAncestorChart.php` (nowy, `make:class`)

**Intent**: Zbudować rekurencyjne drzewo węzłów od osoby w górę:
- Znana osoba poniżej najwyższego pokolenia dostaje dokładnie dwa węzły rodziców. Znani rodzice są w kolejności `PersonOrdering::parents()`, a najwyżej dwóch pierwszych, tak jak w arkuszu. Brakującego zastępuje węzeł `unknown` z etykietą jak w `BuildFamilySheet::parentSlots()`, ale bez `parent_of`.
- Osoba w najwyższym pokoleniu ma `parents: []`. Węzeł `unknown` nigdy nie ma rodziców.
- `repeated` = osoba występuje w zbudowanym widoku więcej niż raz.
- Zapytania: jedno grafu (`ParentChildGraph::forTree`) i jedno osób `people()->with('events')->whereIn(...)`. Karty przez `SheetPersonResource::make()->resolve()`.

**Contract**: `handle(Person $person, int $generations): array{focus: SheetPerson, generations: int, root: AncestorNode}`. Kształt węzła (PHPDoc array shape i typ TS w kroku 6):

```ts
export type AncestorNode =
    | { kind: 'person'; person: SheetPerson; repeated: boolean; parents: AncestorNode[] }
    | { kind: 'unknown'; label: string };
```

#### 4. Kontroler i trasa

**File**: `app/Http/Controllers/TreeAncestorsController.php` (nowy, invokable), `routes/web.php`

**Intent**: Wyrenderować `tree/ancestors` dla osoby z drzewa użytkownika, z liczbą pokoleń z adresu.

**Contract**: `Route::get('tree/{person}/ancestors', TreeAncestorsController::class)->name('tree.ancestors')` w grupie `auth, verified`, obok tras drzewa. `Gate::authorize('view', $person)`. `generations` = `$request->integer('generations', 4)`; wartość spoza 2–6 → 4. Propsy: `chart` (wynik akcji). Stałe min/default/max (2/4/6) w jednym miejscu kontrolera albo akcji. Po dodaniu trasy: `vendor/bin/sail artisan wayfinder:generate`.

#### 5. Powrót po usunięciu osoby w centrum widoku

**File**: `app/Http/Controllers/PersonController.php`

**Intent**: Usunięcie osoby, której przodków właśnie oglądamy, nie może wracać na jej (już nieistniejący) widok przodków.

**Contract**: w `urlAfterDelete` warunek wyjątku obejmuje `tree.show` i `tree.ancestors` z tym samym `person` → `route('tree.index')`. Usunięcie innego przodka wraca na widok przodków bez `?person`, jak dziś na innych stronach.

#### 6. Typy i szkielet strony

**File**: `resources/js/types/tree.ts`, `resources/js/pages/tree/ancestors.tsx` (nowy)

**Intent**: Dodać typy `AncestorNode` i `AncestorChart` oraz minimalną stronę z `Head`, nagłówkiem (imię osoby) i breadcrumbs „Drzewo › {osoba} (tree.show) › Przodkowie”, żeby testy Inertii przechodziły. Płótno dochodzi w fazie 3.

**Contract**: `AncestorChart = { focus: SheetPerson; generations: number; root: AncestorNode }`. Prop strony nazywa się `chart` (nie `sheet`, bo `PersonPanel` interpretuje `sheet` jako arkusz drzewa).

#### 7. Testy Pest

**File**: `tests/Feature/AncestorsTest.php` (nowy, `make:test --pest`), `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/PersonsTest.php`

**Intent**: Objąć ścieżkę szczęśliwą i granice zgodnie z CLAUDE.md i `test-plan.md`.

**Contract**:
- **`AncestorsTest`, kształt widoku:**
  - Bez `generations` są 4 pokolenia. Przy `generations=2` są tylko osoba i rodzice. Wartości `1`, `7`, `abc` dają 4.
  - Osoba w najwyższym pokoleniu ma `parents: []`.
  - Kolejność: ojciec po lewej, matka po prawej.
  - Jeden znany rodzic daje znaną kartę i zastępnik z właściwą etykietą, a zastępnik nie ma rodziców.
  - Osoba bez rodziców poniżej limitu ma dwa zastępniki.
- **`AncestorsTest`, powtórzenie:** wspólny przodek dwóch linii występuje dwa razy z `repeated: true`, a pozostali mają `false`.
- **`AncestorsTest`, odporność:** cykl wstawiony przez `linkParent` z pominięciem reguł nie zawiesza żądania.
- **`AncestorsTest`, liczba zapytań:** stała przy rosnącym drzewie (wzorzec `TreeTest.php:498`).
- **`OwnershipBoundariesTest`:** `tree.ancestors` w `$withoutBody`. Strona `tree.ancestors` (własna osoba w adresie) w zbiorze stron z panelem `?person=` (`:181-199`).
- **`PersonsTest`:** usunięcie osoby z `from` = jej widok przodków przekierowuje na `tree.index` (wzorzec `:1186-1188`).

### Success Criteria:

#### Automated Verification:

- Testy widoku przodków przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php`
- Testy granic własności przechodzą (z przypadkiem `tree.ancestors` i testem kompletności): `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php`
- Testy osób i drzewa przechodzą po wydzieleniu kolejności rodziców: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent`
- Typy i lint frontendu przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check`

#### Manual Verification:

- `/tree/{id}/ancestors` własnej osoby otwiera stronę z nagłówkiem i breadcrumbs, a „Drzewo” w pasku bocznym jest podświetlone

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Faza 2: Przybliżanie w `ZoomCanvas` (tylko na żądanie)

### Overview

`ZoomCanvas` dostaje opcjonalny tryb z kontrolkami i gestami. Domyślnie wyłączony, więc drzewo się nie zmienia.

### Changes Required:

#### 1. Tryb przybliżania

**File**: `resources/js/components/zoom-canvas.tsx`

**Intent**: Na żądanie włączyć przyciski „Powiększ / Pomniejsz / Dopasuj”, przybliżanie Ctrl+kółkiem (samo kółko nic nie robi; szczypanie na touchpadzie przeglądarka zgłasza jako Ctrl+kółko) i szczypanie dotykowe. Dwuklik zostaje wyłączony. „Dopasuj” uruchamia to samo dopasowanie co start i zeruje `movedRef`. Ręczne przybliżenie ustawia `movedRef`.

**Contract**: nowy prop `zoomControls?: boolean` (domyślnie `false`). Przy `true`: `wheel={{ activationKeys: ['Control'] }}`, `pinch` włączone, a pasek przycisków w rogu płótna (klasa `ZOOM_NO_PAN_CLASS`, `Button variant="outline" size="sm"` jak w `source-viewer.tsx:24-48`) z polskimi etykietami. Zakres skali przybliżania = `[floor, max]` istniejących propsów. Przy `false` zachowanie i DOM są takie jak dziś. Komentarz JSDoc komponentu („No manual zoom”) zaktualizować.

#### 2. Stałe dopasowania widoku przodków

**File**: `resources/js/lib/fit-view.ts`

**Intent**: Niski próg startowy, żeby 6 pełnych pokoleń (32 karty w górnym rzędzie) startowało jako całość, oraz górny limit przybliżania.

**Contract**: `ANCESTORS_FIT_FLOOR` (ok. 0,1 — tak, żeby rząd 32 kart po 10 rem zmieścił się na ekranie ok. 1000 px) i `ANCESTORS_ZOOM_MAX` (1,5 jak `TREE_FIT_MAX`, chyba że ręczny test pokaże potrzebę więcej), z komentarzem uzasadniającym wartości. `chooseFitView` bez zmian.

### Success Criteria:

#### Automated Verification:

- Testy Vitest przechodzą (w tym `fit-view.test.ts`): `vendor/bin/sail npm run test:js`
- Typy i lint przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check`
- Istniejące E2E drzewa przechodzą bez zmian: `npx playwright test tests/e2e/tree-card-recentre.spec.ts tests/e2e/tree-slot-parent.spec.ts`

#### Manual Verification:

- Na drzewie (`/tree/{id}`) kółko, Ctrl+kółko i szczypanie nie zmieniają skali, a kontrolek nie ma

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Faza 3: Układ i karty przodków

### Overview

Drzewo węzłów z serwera staje się siatką kart z liniami na płótnie z przybliżaniem, z przełącznikiem liczby pokoleń.

### Changes Required:

#### 1. Czysta funkcja układu

**File**: `resources/js/lib/ancestor-layout.ts` (nowy), `resources/js/lib/ancestor-layout.test.ts` (nowy)

**Intent**: Policzyć pozycje kart w jednostkach kolumn siatki:
- Karta bez rodziców (zastępnik, najwyższe pokolenie) zajmuje 1 kolumnę.
- Osoba z rodzicami zajmuje sumę kolumn rodziców i jest wyśrodkowana pod nimi.
- Pokolenie 1 (osoba) jest na dole.

Do tego połączenia: pozioma kreska od środka pierwszego do środka drugiego rodzica i pień w dół do środka dziecka.

**Contract**: `layoutAncestorChart(root: AncestorNode): { columns: number; generations: number; cards: { node: AncestorNode; generation: number; column: number; span: number }[]; links: { generation: number; from: number; to: number; trunk: number }[] }`. `column`/`span` są całkowite (0-based), a `from`/`to`/`trunk` to środki w jednostkach kolumn. Klucz karty jest stabilny wg ścieżki w drzewie, nie wg id, bo powtórzona osoba występuje kilka razy. Testy Vitest:
- sama osoba z dwoma zastępnikami;
- pełne 3 pokolenia (4 kolumny);
- asymetria: jedna gałąź ucięta zastępnikiem jest węższa;
- powtórzona osoba daje dwa wpisy;
- połączenia mają `from ≤ trunk ≤ to`.

#### 2. Karta przodka

**File**: `resources/js/components/tree/ancestor-card.tsx` (nowy), `resources/js/components/tree/sheet-person-card.tsx`

**Intent**: Karta wygląda jak karta drzewa (ta sama treść, kolor płci, `cardSurface`), ale cała jest linkiem otwierającym panel osoby przez `PersonPreviewLink` (ochrona niezapisanych zmian). Karta osoby w centrum ma `aria-current` i `cardFocus`. Powtórzona osoba ma widoczny znacznik z tekstem dostępnym „Występuje kilka razy”. Zastępnik to statyczna karta `placeholderStatic` z etykietą.

**Contract**: eksport `CardBody` z `sheet-person-card.tsx` do ponownego użycia (bez zmiany wyglądu). `AncestorCard` props: `{ node: AncestorNode; isFocus?: boolean }`. Nazwa dostępna linku zaczyna się od płci (sr-only) i imienia, jak w karcie drzewa, więc E2E może jej szukać po roli `link`.

#### 3. Komponent widoku i strona

**File**: `resources/js/components/tree/ancestor-chart.tsx` (nowy), `resources/js/pages/tree/ancestors.tsx`

**Intent**: Wyrenderować wynik `layoutAncestorChart` na siatce CSS o kolumnach stałej szerokości i odstępie jak `FamilySheet` (`family-sheet.tsx:21-35`). Wiersze kart i wiersze połączeń występują na przemian, a linie są w CSS tokenem `--tree-line`, bez SVG. Całość jest w `ZoomCanvas` z `zoomControls`, `floor=ANCESTORS_FIT_FLOOR`, `max=ANCESTORS_ZOOM_MAX`, `anchorRef` na karcie osoby i `key` = `${focus.id}:${generations}` (świeże dopasowanie po zmianie). Strona ustawia `fillHeight: true`, a w nagłówku ma przełącznik „Pokolenia” (`ToggleGroup` z `ui/toggle-group.tsx`, wartości 2–6).

**Contract**: zmiana pokoleń to wizyta Inertii na `tree.ancestors` z `generations`, zachowująca `?person`/`?marriage`: skopiuj bieżący query strony, podmień `generations` i odwiedź `ancestors(focus.id, { query })`. Nie używaj `withPanel` (`resources/js/lib/side-panel-url.ts:36`) — przyjmuje tylko klucz panelu (`PanelKey`) i przy otwieraniu czyści pozostałe panele. Szerokość kolumny i odstęp to stałe współdzielone przez komponent i przeliczanie połączeń na długości CSS.

### Success Criteria:

#### Automated Verification:

- Testy Vitest układu przechodzą: `vendor/bin/sail npm run test:js`
- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run build`
- Testy Pest widoku przodków nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php`

#### Manual Verification:

- 4 pokolenia startują jako cała widoczna struktura, ojciec po lewej, matka po prawej, linie łączą parę rodziców z dzieckiem
- Przełącznik 2–6 zmienia liczbę pokoleń i ponownie dopasowuje widok; adres zawiera `?generations=`
- „Powiększ / Pomniejsz / Dopasuj”, Ctrl+kółko i szczypanie na touchpadzie działają; samo kółko nic nie robi
- Kliknięcie karty otwiera panel osoby, płótno zwęża się obok; przeciągnięcie zakończone nad kartą nie otwiera panelu
- Szara karta brakującego rodzica jest statyczna; powtórzona osoba ma znacznik w obu miejscach
- Tryb ciemny: karty, linie i kontrolki czytelne

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Faza 4: Wejście z panelu osoby i domknięcie

### Overview

„Pokaż przodków” w panelu osoby jako jedyne wejście i sposób przecentrowania, seeder do ręcznego sprawdzenia 6 pokoleń, ujednolicenie dokumentacji.

### Changes Required:

#### 1. Akcja „Pokaż przodków”

**File**: `resources/js/components/persons/person-modal-header.tsx`, `resources/js/components/persons/person-panel.tsx`

**Intent**: Dodać „Pokaż przodków” jako pozycję menu „⋯” (pod „Pokaż na drzewie”), bez przycisku w nagłówku, z tą samą ochroną niezapisanych zmian (`guardClick`). Panel ma min. 24rem, a drugi przycisk obok „Pokaż na drzewie” ucinałby tytuł z imieniem osoby. Link otwiera `tree.ancestors` tej osoby z jej panelem (`?person=`). Na stronie przodków przenosi bieżące `generations`; na stronie przodków tej samej osoby akcja jest ukryta.

**Contract**: `PersonPanel` czyta `props.chart` (tylko strona przodków), analogicznie do `sheet`, i przekazuje do `PersonPanelActions` np. `ancestorsContext: { focusId: number; generations: number } | null`. Wayfinder: `ancestors` z `@/routes/tree` (nazwa wygenerowana z `tree.ancestors`; sprawdzić po `wayfinder:generate`).

#### 2. Seeder do ręcznej kontroli

**File**: `database/seeders/AncestorsDemoSeeder.php` (nowy)

**Intent**: Dać drzewo do ręcznego przeglądu czytelności. Wzorzec `TreeViewDemoSeeder`: osobny użytkownik demo, uruchamiany jawnie, nie w `DatabaseSeeder`. Drzewo ma:
- probanta z 6 pokoleniami, w większości pełnymi;
- jedno powtórzenie (małżeństwo kuzynów);
- kilku brakujących rodziców, w tym jednego rodzica bez płci;
- długie nazwiska.

**Contract**: `vendor/bin/sail artisan db:seed --class=AncestorsDemoSeeder`; relacje przez `CreateParentChildRelation`, żeby dane spełniały reguły aplikacji.

#### 3. Ujednolicenie dokumentacji

**File**: `context/foundation/prd.md`, `context/foundation/roadmap.md`, `context/foundation/test-plan.md`

**Intent**: Zamknąć rozjazd z follow-upu F1. FR-018 i outcome S-15 opisują dopasowanie do ekranu plus przybliżanie w tym widoku (przyciski, Ctrl+kółko, szczypanie), a nie „jak w drzewie”. Unknown S-15 o panelu osoby oznaczyć jako rozstrzygnięte (kliknięcie karty otwiera panel).

**Contract**: zmiana treści FR-018 (`prd.md:150-152`) i bloku S-15 (`roadmap.md:304-315`), bez zmiany ID i priorytetów. W `test-plan.md` §7 (`:233`) usunąć błędne „(FR-018 odłożony)” przy „Eksport danych”, bo FR-018 to widok przodków. Status roadmapy prowadzą skille cyklu życia, nie ta edycja.

### Success Criteria:

#### Automated Verification:

- Typy, lint i Vitest przechodzą: `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run test:js`
- Seeder działa na czystej bazie dev: `vendor/bin/sail artisan db:seed --class=AncestorsDemoSeeder`
- Pint i testy Pest przechodzą: `vendor/bin/sail bin pint --dirty --format agent` oraz `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php tests/Feature/OwnershipBoundariesTest.php`

#### Manual Verification:

- Panel osoby na drzewie, liście osób i widoku przodków ma „Pokaż przodków” w menu „⋯”; nagłówek panelu i tytuł z imieniem wyglądają jak dziś
- Na widoku przodków osoby X jej panel nie pokazuje „Pokaż przodków”, panel przodka pokazuje; kliknięcie przecentrowuje z zachowaną liczbą pokoleń i otwartym panelem
- Niezapisana edycja w panelu blokuje przejście „Pokaż przodków” tak samo jak „Pokaż na drzewie”
- Na danych `AncestorsDemoSeeder` przy 6 pokoleniach całość startuje dopasowana, a po „Powiększ” imiona są czytelne
- Usunięcie osoby w centrum z jej panelu na widoku przodków wraca do drzewa probanta

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Faza 5: E2E ścieżki wejścia i przecentrowania

### Overview

Jeden test przeglądarkowy (`/10x-e2e`) dla ryzyka, którego Pest nie widzi: stan panelu w URL między dwiema stronami i jedyne wejście do widoku.

### Changes Required:

#### 1. Spec widoku przodków

**File**: `tests/e2e/ancestors-view.spec.ts` (nowy)

**Intent**: Ryzyko: „wejście do widoku przodków i przecentrowanie prowadzą do niewłaściwej osoby albo gubią panel”. Dane przez `page.request` (`createPerson`, `createParent`) z sufiksem. Scenariusz:
1. Przygotuj dziecko, ojca, matkę i dziadka ze strony ojca (babci brak).
2. Otwórz panel dziecka → menu „Więcej działań” → „Pokaż przodków” → URL `/tree/{dziecko}/ancestors`, nagłówek z imieniem dziecka.
3. Widoczne są karty ojca, matki i dziadka. Zastępników jest tyle, ile wynika z danych: „Nieznana matka” ×3 (nad ojcem, matką i dziadkiem), „Nieznany ojciec” ×2 (`toHaveCount`, bez asercji położenia).
4. Wybór „2” w „Pokolenia” ukrywa dziadka i wszystkie zastępniki (liczba 0).
5. Kliknięcie karty matki otwiera jej panel.
6. „Pokaż przodków” w menu panelu matki przecentrowuje (URL i nagłówek matki), panel matki zostaje otwarty, a `generations=2` jest zachowane.

**Contract**: lokatory `getByRole` / `getByText`, bez CSS i XPath, bez `waitForTimeout`; asercje na danych i nawigacji, nie na układzie (`test-plan.md:233-234`). Weryfikacja przez celowe wprowadzenie błędu zgodnie z `/10x-e2e`.

### Success Criteria:

#### Automated Verification:

- Spec przechodzi: `npx playwright test tests/e2e/ancestors-view.spec.ts`
- Spec zawodzi po celowym zepsuciu (np. „Pokaż przodków” linkuje do osoby w centrum zamiast osoby z panelu) i wraca do zieleni po cofnięciu

#### Manual Verification:

- Przegląd testu względem pięciu antywzorców z `/10x-e2e` bez uwag

---

## Testing Strategy

### Unit Tests:

- Vitest `ancestor-layout.test.ts`: szerokości gałęzi, pozycje pokoleń, połączenia, powtórzenia, asymetria.
- Istniejący `fit-view.test.ts` bez zmian (nowe stałe nie zmieniają funkcji).

### Integration Tests:

- Pest `AncestorsTest`: limit i walidacja pokoleń, kolejność rodziców, zastępniki, powtórzenia, odporność na cykl, stała liczba zapytań.
- Pest `OwnershipBoundariesTest`: 404 dla cudzej osoby w adresie i w `?person=`.
- Pest `PersonsTest`: powrót po usunięciu osoby w centrum.
- Playwright `ancestors-view.spec.ts`: wejście z panelu, zmiana pokoleń, panel z karty, przecentrowanie.

### Manual Testing Steps:

1. `vendor/bin/sail artisan db:seed --class=AncestorsDemoSeeder`, zaloguj się na użytkownika demo, otwórz panel probanta → „Pokaż przodków”.
2. Przełącz 2 → 6 pokoleń; sprawdź dopasowanie, „Powiększ”, Ctrl+kółko, szczypanie, „Dopasuj”.
3. Otwórz panel przodka, sprawdź zwężenie płótna, przecentruj, zamknij panel.
4. Sprawdź powtórzonego przodka (znacznik w obu miejscach) i szare karty.
5. Na drzewie sprawdź, że przybliżanie nadal nie działa.

## Performance Considerations

Przy 6 pokoleniach widok ma najwyżej 63 karty osób (plus zastępniki). Serwer wykonuje stałą liczbę zapytań niezależnie od rozmiaru drzewa i głębokości (test w fazie 1). Graf całego drzewa ładuje się jednym zapytaniem, jak w arkuszu.

## Migration Notes

Brak migracji i zmian danych.

## References

- Research: `context/changes/ancestors-view/research.md`
- PRD: FR-018, FR-029 (`context/foundation/prd.md:150-152,187,236`); roadmapa S-15 (`context/foundation/roadmap.md:304-315`)
- Wzorzec arkusza: `app/Actions/Trees/BuildFamilySheet.php:328-422`, `resources/js/components/tree/family-sheet.tsx:21-35,87-119`
- Płótno: `resources/js/components/zoom-canvas.tsx`, `resources/js/lib/fit-view.ts`; kontrolki: `resources/js/components/sources/source-viewer.tsx:14-47`
- Panel: `resources/js/components/persons/person-modal-header.tsx:69-157`, `resources/js/components/persons/person-panel.tsx:31-33`
- Testy wzorcowe: `tests/Feature/TreeTest.php:498`, `tests/Feature/OwnershipBoundariesTest.php:101-102,162-199`, `tests/Feature/PersonsTest.php:1186-1188`, `tests/e2e/tree-card-recentre.spec.ts`
- Follow-up F1: `context/archive/2026-10-05-tree-fits-screen/follow-ups/review-fixes.md:5`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Dane przodków i trasa

#### Automated

- [x] 1.1 Testy widoku przodków przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php` — 5522689
- [x] 1.2 Testy granic własności przechodzą (z przypadkiem `tree.ancestors` i testem kompletności): `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php` — 5522689
- [x] 1.3 Testy osób i drzewa przechodzą po wydzieleniu kolejności rodziców: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php` — 5522689
- [x] 1.4 Larastan przechodzi: `vendor/bin/sail composer types:check` — 5522689
- [x] 1.5 Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent` — 5522689
- [x] 1.6 Typy i lint frontendu przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check` — 5522689

#### Manual

- [x] 1.7 `/tree/{id}/ancestors` własnej osoby otwiera stronę z nagłówkiem i breadcrumbs, a „Drzewo” w pasku bocznym jest podświetlone — 5522689

### Phase 2: Przybliżanie w `ZoomCanvas` (tylko na żądanie)

#### Automated

- [x] 2.1 Testy Vitest przechodzą (w tym `fit-view.test.ts`): `vendor/bin/sail npm run test:js` — 50b64d9
- [x] 2.2 Typy i lint przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check` — 50b64d9
- [x] 2.3 Istniejące E2E drzewa przechodzą bez zmian: `npx playwright test tests/e2e/tree-card-recentre.spec.ts tests/e2e/tree-slot-parent.spec.ts` — 50b64d9

#### Manual

- [x] 2.4 Na drzewie (`/tree/{id}`) kółko, Ctrl+kółko i szczypanie nie zmieniają skali, a kontrolek nie ma — 50b64d9

### Phase 3: Układ i karty przodków

#### Automated

- [x] 3.1 Testy Vitest układu przechodzą: `vendor/bin/sail npm run test:js` — dd61429
- [x] 3.2 Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run build` — dd61429
- [x] 3.3 Testy Pest widoku przodków nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php` — dd61429

#### Manual

- [x] 3.4 4 pokolenia startują jako cała widoczna struktura, ojciec po lewej, matka po prawej, linie łączą parę rodziców z dzieckiem — dd61429
- [x] 3.5 Przełącznik 2–6 zmienia liczbę pokoleń i ponownie dopasowuje widok; adres zawiera `?generations=` — dd61429
- [x] 3.6 „Powiększ / Pomniejsz / Dopasuj”, Ctrl+kółko i szczypanie na touchpadzie działają; samo kółko nic nie robi — dd61429
- [x] 3.7 Kliknięcie karty otwiera panel osoby, płótno zwęża się obok; przeciągnięcie zakończone nad kartą nie otwiera panelu — dd61429
- [x] 3.8 Szara karta brakującego rodzica jest statyczna; powtórzona osoba ma znacznik w obu miejscach — dd61429
- [x] 3.9 Tryb ciemny: karty, linie i kontrolki czytelne — dd61429

### Phase 4: Wejście z panelu osoby i domknięcie

#### Automated

- [x] 4.1 Typy, lint i Vitest przechodzą: `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run test:js` — 98e5f9e
- [x] 4.2 Seeder działa na czystej bazie dev: `vendor/bin/sail artisan db:seed --class=AncestorsDemoSeeder` — 98e5f9e
- [x] 4.3 Pint i testy Pest przechodzą: `vendor/bin/sail bin pint --dirty --format agent` oraz `vendor/bin/sail artisan test --compact tests/Feature/AncestorsTest.php tests/Feature/OwnershipBoundariesTest.php` — 98e5f9e

#### Manual

- [x] 4.4 Panel osoby na drzewie, liście osób i widoku przodków ma „Pokaż przodków” w menu „⋯”; nagłówek panelu i tytuł z imieniem wyglądają jak dziś — 98e5f9e
- [x] 4.5 Na widoku przodków osoby X jej panel nie pokazuje „Pokaż przodków”, panel przodka pokazuje; kliknięcie przecentrowuje z zachowaną liczbą pokoleń i otwartym panelem — 98e5f9e
- [x] 4.6 Niezapisana edycja w panelu blokuje przejście „Pokaż przodków” tak samo jak „Pokaż na drzewie” — 98e5f9e
- [x] 4.7 Na danych `AncestorsDemoSeeder` przy 6 pokoleniach całość startuje dopasowana, a po „Powiększ” imiona są czytelne — 98e5f9e
- [x] 4.8 Usunięcie osoby w centrum z jej panelu na widoku przodków wraca do drzewa probanta — 98e5f9e

### Phase 5: E2E ścieżki wejścia i przecentrowania

#### Automated

- [x] 5.1 Spec przechodzi: `npx playwright test tests/e2e/ancestors-view.spec.ts`
- [x] 5.2 Spec zawodzi po celowym zepsuciu (np. „Pokaż przodków” linkuje do osoby w centrum zamiast osoby z panelu) i wraca do zieleni po cofnięciu

#### Manual

- [x] 5.3 Przegląd testu względem pięciu antywzorców z `/10x-e2e` bez uwag
