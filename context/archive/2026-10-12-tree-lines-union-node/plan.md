# Linie drzewa i węzeł związku — plan implementacji

## Overview

Wdrażamy linie arkusza rodziny i węzeł związku z design systemu Root Keeper (https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU: `components/UnionNode/README.md`, `components/PersonCard/README.md`, `tokens.json` → `layout`, wiersze `components/tree/*` w `implementation.md`; makieta https://claude.ai/artifact/Nvqqp3f43K3Kf22tNudBuS, artboardy `Main.dc.html` i `Union.dc.html`). Arkusz dostaje rok ślubu par rodziców i dziadków, linie stają się łamane z zaokrąglonymi narożnikami. Linia do zaślepki jest przerywana, linia przodków osoby w centrum ma kolor `primary`, a pigułka związku i węzeł rodziców dostają obrączki. Pod zdjęciem na karcie pojawia się listwa w kolorze płci.

## Current State Analysis

- **Dane**: rok ślubu jest tylko dla związków osoby w centrum (`unions[].marriage_year`, `app/Actions/Trees/BuildFamilySheet.php:103-112`). Dla par rodziców jest jedynie `unmarried_parents` (`:170`, `:181-222`), a dla par dziadków nie ma nic. Arkusz nie ma API Resource: `handle()` zwraca tablicę wprost do `Inertia::render` (`TreeController.php:26`, `:45`). `loadPeople()` ładuje już osoby z `events` aż do dziadków (`BuildFamilySheet.php:328-343`), więc rok ślubu par da się wyliczyć bez nowych zapytań.
- **Linie** (`resources/js/components/tree/family-sheet.tsx`): `PairConnector` to jeden nawias `inset-x-[25%] h-3 rounded-b-md border-x border-b` z pniem `w-px` (`:124-156`). Linia pary to `h-px` rysowane tylko przy `partner.kind !== 'empty'` (`:411-416`), pień to `w-px` (`:466-478`), belka dzieci składa się z połówek `h-px` (`:258-273`). Wszystko w `tree-line`, grubość 1 px, nic nie jest przerywane, nie ma linii przodków.
- **Węzeł** (`union-node.tsx`): `rounded-lg h-8.5 border` (`:110`), wszystkie stany tekstowe już są (`:37-49`, `:116-127`). Przy `unions.length === 0` zwraca `null` (`:67-69`).
- **Karta**: `<img className="absolute inset-0 size-full …">` zakrywa cały pasek płci (`sheet-person-card.tsx:54`).
- **Tokeny**: `app.css` nie ma `--tree-line-width` ani `--lineage-line-width`. `tree-corner-radius` (0.75rem) = domyślne `rounded-xl` (`app.css` nie nadpisuje `--radius-xl`).
- **Strażnik**: `view-tokens.test.ts` celowo pomija `family-sheet.tsx` (`:16-19`), a reguła px łapie tylko całkowite piksele (`:86`).
- **Test 404**: brak testu, że `GET tree.show` z osobą z cudzego drzewa daje 404. Autoryzacja istnieje (`TreeController.php:40`, `PersonPolicy.php:14-19`).

## Desired End State

Na `/tree` linie mają 1,5 px w `tree-line`, są łamane z narożnikami 12 px i dochodzą do środka krawędzi kart. Od dziadków przez rodziców do karty osoby w centrum biegnie linia 2 px w `primary`. Każda linia do zaślepki (brakujący rodzic, partner, w tym statyczna „Nieznany partner”, slot „Dziecko” obok belki, pień przy braku dzieci) jest przerywana i w `tree-line`. Tam, gdzie ramiona pary rodziców lub dziadków spotykają się z pniem, stoi węzeł z obrączkami i rokiem ślubu („1861”, „ok. 1828”). Ślub bez daty to same obrączki w kółku 24 px, a para bez ślubu albo niepełna nie ma węzła. Pigułka związku jest okrągła, z obrączkami przy ślubie; „bez ślubu” ma przerywaną ramkę i linię. Pigułka jest też na arkuszu bez związków („Związki”, menu z „Dodaj małżonka”). Karta ze zdjęciem ma pod zdjęciem listwę 4 px w kolorze płci. Weryfikacja: testy Pest arkusza, `view-tokens.test.ts` z `family-sheet.tsx`, `check` / `types:check`, zrzuty jasny/ciemny w `screenshots/`.

### Key Discoveries:

- Tailwind 4.3.3: `@theme { --border-width-line: var(--tree-line-width); --border-width-lineage: var(--lineage-line-width) }` generuje `border-line`, `border-x-line`, `border-t-line`, `border-b-line`, `border-l-line`, `border-lineage`, `border-l-lineage` itd. (sprawdzone kompilacją próbki klas). Nazwa nie koliduje z kolorem `border-tree-line`. `--border-width-tree-line` by kolidowało (badania, `research.md:78`).
- Reguły makiety (`rk.css` w artefakcie makiety: `.rk-j`, `.rk-knot`, `.rk-kid`, `.rk-couple-*`, `.rk-union`): ramiona mają 16 px wysokości z narożnikiem 12 px na zewnętrznym dolnym rogu, a pień biegnie od złącza do dołu. Węzeł rodziców jest wyśrodkowany na złączu: `h-5.5`, tło `background`, ramka w kolorze i grubości linii, tekst 12 px/500 `tabular-nums`. Pigułka pary: `h-8`, `rounded-full`, 12 px/600, pozycja „1 z 2” w `muted-foreground`/500, chevron 14 px. Dzieci: pierwsze dziecko ma narożnik w lewym górnym rogu, środkowe mają pełną belkę i pion, a odgałęzienie do „Dziecko” to przerywany narożnik w prawym górnym rogu.
- Z makiety (trzecia para dziadków w `Main.dc.html`): gdy oba sloty pary to zaślepki, przerywany jest również pień.
- Kolejność slotów dziadków `[ojciec ojca, matka ojca, ojciec matki, matka matki]`: pary to indeksy `[0,1]` i `[2,3]`. Frontend rysuje łączniki po tych parach (`family-sheet.tsx:368-379`).
- `PersonOrdering::marriages()` (`app/Support/PersonOrdering.php:65-70`) daje kolejność, w której pierwszy jest najwcześniejszy ślub. Tej samej zasady używa `unions` (test `TreeTest.php:223`).
- `tests/e2e/support/navigation.ts:50,61` szuka pigułki przez `/^Związek \d z \d/`. Nowy `aria-label` dla arkusza bez związków nie może dawać „Związek 0 z 0”.

## What We're NOT Doing

- Bez zmian w `unmarried_parents` i przycisku „Połącz jako małżeństwo” (D3). Węzeł rodziców i przycisk się wykluczają (ślub / brak ślubu).
- Bez linii do slotu „Dziecko”, gdy stoi pod ostatnim dzieckiem (od 4 dzieci). Belka kończy się wtedy ciągłym narożnikiem nad ostatnim dzieckiem (D6).
- Bez klikalnego węzła rodziców i bez `marriage_id` w nowych polach: węzeł jest dekoracyjny (DS).
- Bez zmian w `ancestor-chart.tsx` (widok przodków) i bez dodawania go do `VIEW_FILES`.
- Bez `toHaveScreenshot` i regresji pikselowych; bramka wizualna to zrzuty z oceną użytkownika.
- Bez nowego tokenu promienia (wystarczy `rounded-xl`) i bez nowych zależności.

## Implementation Approach

Od danych do pikseli. Faza 1 rozszerza kontrakt arkusza (backend + typy) i domyka testy. Faza 2 dodaje tokeny i samodzielne elementy (obrączki, pigułka, listwa), które nie zależą od geometrii arkusza. Faza 3 przebudowuje geometrię linii w `family-sheet.tsx` na tych tokenach i obejmuje ją strażnikiem. Wszystkie linie są ramkami (`border-*-line` na elementach o zerowej szerokości lub wysokości), a nie wypełnieniami `bg-tree-line w-px`, bo tylko ramka może być przerywana (`border-dashed`).

## Critical Implementation Details

**Listwa płci a `<img>`.** Element zastępowany (`<img>`) w pozycji absolutnej z `width/height: auto` przyjmuje rozmiar naturalny i ignoruje parę `top-0 bottom-1`. Samo usunięcie `size-full` (sugestia z `implementation.md` i badań) dałoby więc zdjęcie w rozmiarze pliku. Zdjęcie trzeba zamknąć w kontenerze `absolute inset-x-0 top-0 bottom-1 overflow-hidden`, a w nim zostawić `<img>` z `size-full object-cover`. Test `tests/e2e/person-photo-on-card.spec.ts:66` szuka zdjęcia po pustym `alt` wewnątrz karty, więc wrapper go nie psuje.

**Pień nad jednym dzieckiem.** Pień spod węzła pary trafia w środek rzędu dzieci. Przy jednym dziecku jest to środek komórki pierwszego dziecka, a zaokrąglony narożnik „pierwszego” zostawiłby między pniem a łukiem przerwę o długości promienia. Dlatego pierwsze dziecko dostaje narożnik tylko przy ≥2 dzieciach. Pojedyncze dziecko dostaje prosty pion i prawą połówkę belki (do przerywanego odgałęzienia slotu obok).

**Która połowa linii pary należy do partnera.** Linia pary musi być rozbita na dwie połowy: lewą (25%–50%) i prawą (50%–75%). Strona partnera wynika z `focus_side`: przy `'right'` partner stoi po lewej (`order` w `family-sheet.tsx:302-305`).

## Faza 1: Dane par w arkuszu

### Overview

Arkusz niesie rok ślubu dwóch par rodziców i czterech par dziadków, wyliczony z już załadowanych zdarzeń. Testy Pest pokrywają wszystkie przypadki i brakujący 404.

### Changes Required:

#### 1. Pola `parent_marriages` i `grandparent_marriages`

**File**: `app/Actions/Trees/BuildFamilySheet.php`

**Intent**: Dodać do wyniku `handle()` dwa pola wyrównane do slotów łączników, liczone z `$this->people[...]->events` (filtrowanych po `EventType::Marriage`, część wspólna id zdarzeń obu osób pary). Przy kilku ślubach tej samej pary wygrywa najwcześniejszy według `PersonOrdering::marriages()`, spójnie z `unions`. Sloty dziadków liczyć raz i użyć zarówno w `grandparents`, jak i w nowym polu.

**Contract**: nowe `@phpstan-type PairMarriage array{year: string|null}` w nagłówku klasy; w `@return` `handle()`:
`parent_marriages: array{focus: PairMarriage|null, partner: PairMarriage|null}`,
`grandparent_marriages: array{focus: list<PairMarriage|null>, partner: list<PairMarriage|null>}` (zawsze 2 elementy: para `[0,1]` i `[2,3]`).
`PairMarriage` istnieje tylko, gdy oba sloty pary to `kind: 'person'` i łączy je zdarzenie `Marriage`; `year` = `$marriage->date->formatYear()` (`null` dla ślubu bez daty, „ok. 1828” dla przybliżonej). W każdym innym przypadku `null` (bez ślubu, zaślepka, pusty slot, brak partnera). Bez nowych zapytań i bez zmian w `unmarriedParents()`.

#### 2. Typ TS arkusza

**File**: `resources/js/types/tree.ts`

**Intent**: Odzwierciedlić nowe pola w typie `FamilySheet`.

**Contract**: `export type PairMarriage = { year: string | null }`; w `FamilySheet`:
`parent_marriages: { focus: PairMarriage | null; partner: PairMarriage | null }`,
`grandparent_marriages: { focus: [PairMarriage | null, PairMarriage | null]; partner: [PairMarriage | null, PairMarriage | null] }`.

Pola są wymagane, więc oba pliki, które budują pełny `FamilySheet`, dostają je w fixture (inaczej `types:check` i `test:js` padną): `resources/js/lib/add-person-target.test.ts` (`:75`) i `resources/js/lib/centre-hints.test.ts` (`:44`) — `parent_marriages: { focus: null, partner: null }`, `grandparent_marriages: { focus: [null, null], partner: [null, null] }`.

#### 3. Testy arkusza i 404

**File**: `tests/Feature/TreeTest.php`

**Intent**: Pokryć nowe pola przez helper `sheetFor()` oraz `attachMarriage()` / `linkParent()` (`tests/Pest.php`), a także brakujący przypadek cudzego drzewa.

**Contract**: przypadki:
- para rodziców po ślubie, `{year: '1861'}`;
- ślub bez daty, `{year: null}`;
- data przybliżona daje „ok. 1828” (zdarzenie z `date_is_approximate = true`);
- para bez ślubu daje `null`;
- jeden rodzic nieznany daje `null`;
- para dziadków (rodzice ojca) z rokiem, pozostałe pary `null`;
- blok partnera: rok przy wybranym partnerze, `null` w obu polach przy braku partnera;
- dwa śluby tej samej pary dają rok najwcześniejszego;
- `GET route('tree.show', $foreign)` dla osoby z innego drzewa daje `assertNotFound()` (wzorzec `tests/Feature/AncestorsTest.php:233-240`).

Test licznika zapytań (`TreeTest.php:512-549`) zostaje bez zmian i musi przejść.

### Success Criteria:

#### Automated Verification:

- Testy arkusza przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`
- Larastan poziom 7 bez błędów: `vendor/bin/sail composer types:check`
- Pint bez zmian po formatowaniu: `vendor/bin/sail bin pint --dirty --format agent`
- Typy TS: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Prop `sheet` na `/tree` (React devtools albo odpowiedź Inertia) zawiera `parent_marriages` i `grandparent_marriages` w opisanym kształcie

**Implementation Note**: Po zielonych bramkach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Tokeny, obrączki, pigułka i listwa płci

### Overview

Tokeny grubości w CSS i utility Tailwind, ikona obrączek, pigułka związku we wszystkich stanach DS (także bez związków), listwa płci pod zdjęciem i strażnik ułamkowych pikseli.

### Changes Required:

#### 1. Tokeny grubości

**File**: `resources/css/app.css`

**Intent**: Dodać grubości linii z DS jako zmienne i utility `border-line` / `border-lineage`, żeby widoki nie pisały `[1.5px]`.

**Contract**: w `:root` (wartości wspólne dla obu motywów, bez powtórzenia w `.dark`): `--tree-line-width: 1.5px; --lineage-line-width: 2px;`. W `@theme`: `--border-width-line: var(--tree-line-width); --border-width-lineage: var(--lineage-line-width);`.

#### 2. Ikona obrączek

**File**: `resources/js/components/tree/marriage-rings-icon.tsx` (nowy)

**Intent**: Znak ślubu ⚭, którego `lucide-react` nie ma. Kolor dziedziczy z rodzica (`currentColor`).

**Contract**: `export function MarriageRingsIcon({ className }: { className?: string })`, SVG `viewBox="0 0 16 12"`, dwa okręgi `r=4.25`, `cx=5.5` i `cx=10.5`, `cy=6`, `fill="none"`, `stroke="currentColor"`, `strokeWidth={1.75}`, `aria-hidden="true"`; domyślny rozmiar `w-4 h-3 shrink-0`.

#### 3. Pigułka związku

**File**: `resources/js/components/tree/union-node.tsx`

**Intent**: Węzeł pary jako punkt na linii małżonków, ze stanem czytelnym bez rozwijania menu. Przy braku związków pigułka też się renderuje (D1).

**Contract**:
- Trigger: `rounded-full h-8 border-line border-tree-line bg-background shadow-xs text-xs font-semibold tabular-nums`, padding i odstępy jak w makiecie. Hover, `data-[state=open]:border-primary`, `cardInteractive`, `ZOOM_NO_PAN_CLASS` i `triggerActive` zostają.
- Stany: ślub → `MarriageRingsIcon` w `text-tree-line` przed „ślub 1890” / „ślub, data nieznana”; „bez ślubu” → `border-dashed`, bez ikony; kilka związków → „1 z 2” w `text-muted-foreground font-medium` przed stanem; nieznany partner / brak związków → „Związki”. Chevron 14 px (`size-3.5`) w `text-muted-foreground`.
- `unions.length === 0`: menu zawiera etykietę „Związki — {imię}”, bez grupy radiowej i szczegółów, oraz „Dodaj małżonka”. `aria-label` = „Związki: brak związków. Dodaj małżonka” (nie pasuje do `/^Związek \d z \d/`). Dla pozostałych stanów `aria-label` bez zmian.

#### 4. Listwa płci pod zdjęciem

**File**: `resources/js/components/tree/sheet-person-card.tsx`

**Intent**: Pod zdjęciem zostaje 4 px paska `stripGender`, żeby płeć na karcie ze zdjęciem nie zależała od samej barwy tła.

**Contract**: w `PhotoStrip` `<img>` trafia do kontenera `absolute inset-x-0 top-0 bottom-1 overflow-hidden`; `<img>` zachowuje `size-full object-cover object-center` (patrz Critical Implementation Details). Inicjały bez zdjęcia bez zmian.

#### 5. Strażnik tokenów

**File**: `resources/js/lib/view-tokens.test.ts`

**Intent**: Reguła „arbitrary pixel value” łapie też ułamki (`[1.5px]`) (D4); nowa ikona jest skanowana.

**Contract**: wzorzec reguły rozszerzony o część ułamkową (np. `-\[-?\d+(?:\.\d+)?px\]`); `components/tree/marriage-rings-icon.tsx` dodany do `VIEW_FILES`.

### Success Criteria:

#### Automated Verification:

- Strażnik tokenów i testy JS: `vendor/bin/sail npm run test:js`
- Format i lint: `vendor/bin/sail npm run check`
- Typy TS: `vendor/bin/sail npm run types:check`
- Build: `vendor/bin/sail npm run build`
- Brak `[1.5px]` i `[2px]` w widokach drzewa: `grep -rnE '\[(1\.5|2)px\]' resources/js/components/tree` nic nie zwraca (shadcn `components/ui/*` ma własne `[2px]` i jest poza strażnikiem)

#### Manual Verification:

- Pigułka na `/tree` w stanach: ślub z datą, ślub bez daty, bez ślubu (przerywana), „1 z 2”, nieznany partner, osoba bez związków („Związki”, menu z „Dodaj małżonka” otwiera panel dodawania)
- Karta ze zdjęciem pokazuje listwę 4 px w kolorze płci pod zdjęciem, zdjęcie nie jest rozciągnięte ani w rozmiarze pliku

**Implementation Note**: Po zielonych bramkach automatycznych zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Linie arkusza

### Overview

Przebudowa geometrii linii w `family-sheet.tsx` według DS i makiety, węzeł rodziców, plik pod strażnikiem tokenów, dane demo z małżeństwami przodków i zrzuty jasny/ciemny.

### Changes Required:

#### 1. Łącznik pary (rodzice i dziadkowie)

**File**: `resources/js/components/tree/family-sheet.tsx` (`PairConnector`)

**Intent**: Rozbić nawias na dwa ramiona z zaokrąglonymi narożnikami, żeby każde mogło mieć własny styl (ciągłe / przerywane, `tree-line` / linia przodków), i postawić węzeł rodziców na złączu ramion z pniem.

**Contract**:
- Nowe propsy: `lineage: boolean`, `marriage: PairMarriage | null`; `slots`, `className` i `action` zostają.
- Lewe ramię od środka lewej karty do 50% (`border-b-* border-l-* rounded-bl-xl`), prawe od 50% do środka prawej karty (`border-b-* border-r-* rounded-br-xl`), wysokość jak w makiecie (16 px). Zewnętrzne krawędzie ramion nie leżą na 25%/75%: środek karty to `COLUMN_REM / 2` od krawędzi łącznika dziadków (`left-1/4` dałoby 0,1875rem, czyli 3 px, odchyłki), a `COLUMN_REM + COLUMN_GAP_REM / 2` od krawędzi łącznika rodziców. Wartości z tych stałych podajemy przez `style` (`left` / `right` w rem), jak reszta geometrii arkusza (wyjątek `style={` z §4). Prop `PairConnector`: `cardCentreRem` (6 dla dziadków, 12,375 dla rodziców). Pień od złącza do dołu łącznika (`border-l-*` na elemencie o zerowej szerokości).
- Styl ramienia: slot `kind === 'person'` daje linię ciągłą, `primary` + `*-lineage` przy `lineage`, w przeciwnym razie `tree-line` + `*-line`. Inny slot daje `border-dashed` w `tree-line` + `*-line` (D2).
- Styl pnia: przerywany `tree-line`, gdy żaden slot nie jest osobą; w pozostałych przypadkach ciągły w kolorze i grubości linii (przodków lub zwykłej).
- Węzeł (`aria-hidden`, gdy `marriage !== null`): wyśrodkowany na złączu, `rounded-full bg-background`, ramka w kolorze i grubości linii, `MarriageRingsIcon` w kolorze linii, rok `text-xs font-medium tabular-nums text-foreground`. Z rokiem: pigułka `h-5.5`; bez roku: kółko `size-6` z samymi obrączkami.
- Wysokość łącznika mieści węzeł: `h-11` (makieta: 44 px), ramiona `h-4` (16 px, złącze na 16 px od góry). Wersja z `action` zostaje `h-12` i wyśrodkowuje przycisk „Połącz jako małżeństwo” na złączu (`top-4 -translate-y-1/2`), nie na `top-1/2`.
- Siatka arkusza traci `gap-y-1` (`family-sheet.tsx:351`): 4 px przerwy między wierszami przerywałyby ramiona, pnie i linię pary, a linie mają dochodzić do kart bez odstępu (3.13). Oddech między rzędami kart daje wysokość łączników (44 px) i górny padding komórek dzieci.
- Wywołania: łącznik rodziców dostaje `marriage = sheet.parent_marriages[block]`, łączniki dziadków `sheet.grandparent_marriages[block][start / 2]`; `lineage = block === 'focus'` we wszystkich trzech łącznikach bloku centrum.

#### 2. Linia pary, pień i pigułka

**File**: `resources/js/components/tree/family-sheet.tsx` (wiersz „Związek” i pień)

**Intent**: Linia małżonków z dwóch połówek i pigułka zawsze na środku (D1). Pień schodzi spod pigułki do dzieci i jest przerywany, gdy prowadzi tylko do zaślepki „Dziecko” (D5).

**Contract**:
- Linia pary rysowana zawsze (także przy `partner.kind === 'empty'`), dwie połówki `border-t-line border-tree-line`. Obie przerywane, gdy partner to osoba, a wybrany związek ma `is_marriage === false`. Połówka partnera przerywana, gdy partner nie jest osobą (`unknown` lub `empty`). Poza tym ciągłe.
- `UnionNode` renderowany zawsze. `addActive` bez zmian (przy `partner.kind === 'empty'` aktywny jest slot dodawania).
- Pień: od pigułki do dołu wiersza pary (jak `rk-couple-stem`), a dalej przez wiersz pnia do belki. `border-l-line border-tree-line`, `border-dashed` przy `children.length === 0`. Przycisk „Połącz jako małżeństwo” pary w centrum zostaje na pniu.
- Przy 0 dzieci `NoChildren` (`family-sheet.tsx:218-227`) nie stawia napisu „Brak dzieci w tym związku.” na osi między pniem a slotem „Dziecko”: napis przechodzi na `sr-only` (albo, jeśli makieta `Main.dc.html` go pokazuje, ląduje obok slotu, poza osią), a przerywany pień sięga do góry slotu. Przed zmianą implementator sprawdza w makiecie, co dzieje się z napisem.

#### 3. Belka dzieci

**File**: `resources/js/components/tree/family-sheet.tsx` (`ChildrenRow`)

**Intent**: Belka z zaokrąglonymi końcami i przerywanym odgałęzieniem do slotu „Dziecko” stojącego obok.

**Contract**:
- Komórka dziecka `i`: pierwsze (`i === 0`, przy ≥2 dzieciach) dostaje narożnik od środka do prawej krawędzi (`border-t-* border-l-* rounded-tl-xl`). Ostatnie (przy ≥2 dzieciach i slocie pod rzędem) dostaje narożnik od lewej krawędzi do środka (`border-t-* border-r-* rounded-tr-xl`). Pozostałe komórki, a także ostatnie dziecko przy slocie obok, dostają pełną belkę i pion. Jedyne dziecko dostaje pion i prawą połówkę belki (patrz Critical Implementation Details).
- Slot obok (`slotBeside`): przerywany narożnik od lewej krawędzi do środka slotu (`rounded-tr-xl border-dashed`), w `tree-line`.
- Slot pod rzędem (≥4 dzieci): bez linii (D6).
- Wszystkie linie dzieci w `tree-line` + `*-line`; wysokość odgałęzień `h-4` i górny padding komórek `pt-4` (16 px z makiety, narożnik 12 px + 4 px prostego odcinka).

#### 4. Strażnik tokenów dla arkusza

**File**: `resources/js/lib/view-tokens.test.ts`

**Intent**: Objąć `family-sheet.tsx` kontrolą tokenów z wyjątkiem geometrii kolumn liczonej w runtime (D4).

**Contract**: `components/tree/family-sheet.tsx` w `VIEW_FILES`; wpis w `EXCEPTIONS` `{ file: 'components/tree/family-sheet.tsx', token: 'style={', reason: 'geometria kolumn i komórek arkusza liczona w runtime (rem z COLUMN_REM / liczby dzieci) …' }`. Komentarz nad `VIEW_FILES` mówi, że pominięty jest już tylko `ancestor-chart.tsx`. Procentowe `inset-x-[25%]` / `right-[25%]` zastąpić skalą (`left-1/4`, `right-1/4`) w linii pary (tam środki kart leżą dokładnie na 25%/75%); w ramionach łączników patrz §1 (`style` z `COLUMN_REM`).

#### 5. Dane demo i zrzuty

**File**: `database/seeders/TreeViewDemoSeeder.php`, `context/changes/tree-lines-union-node/screenshots/`

**Intent**: Arkusz probanda w drzewie demo pokazuje wszystkie stany węzła rodziców, a bramka wizualna utrwala wynik.

**Contract**: w `seedTree()` małżeństwa przez istniejący `marry()`, którego phpdoc dostaje `is_approximate?: bool` (`CreateMarriage::handle` już to obsługuje; Larastan skanuje `database/`): Adam + Maria z rokiem (węzeł z rokiem, linia przodków), Józef + Helena z datą przybliżoną („ok. …”), Stanisław + Zofia bez daty (same obrączki). Rodzice Anny zostają bez ślubu (przycisk „Połącz jako małżeństwo” w bloku partnera). Skrypt `screenshots/shots.mjs` wzorem `context/archive/2026-10-12-design-tokens/screenshots/shots.mjs` (z `deviceScaleFactor: 2`: przy DPR 1 przeglądarka zaokrągla ramkę 1,5 px do 1 px i zrzut nie pokazałby projektowanej grubości) robi zrzuty `/tree` probanda (Anna i Ewa), osoby bez związków (np. Kacper) i pary z ≥4 dziećmi w obu motywach. `screenshots/verdict.md` zawiera jedno zdanie na zrzut.

### Success Criteria:

#### Automated Verification:

- Strażnik tokenów z `family-sheet.tsx` i testy JS: `vendor/bin/sail npm run test:js`
- Format i lint: `vendor/bin/sail npm run check`
- Typy TS: `vendor/bin/sail npm run types:check`
- Build: `vendor/bin/sail npm run build`
- Testy arkusza nadal zielone: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`
- Brak linii jako wypełnień w arkuszu: `grep -nE 'bg-tree-line|w-px|h-px' resources/js/components/tree/family-sheet.tsx` nic nie zwraca
- Zrzuty i werdykt istnieją: `ls context/changes/tree-lines-union-node/screenshots/*.png context/changes/tree-lines-union-node/screenshots/verdict.md`

#### Manual Verification:

- Linia przodków probanda 2 px w `primary` od obu par dziadków przez rodziców do jego karty; rodzina partnera i dzieci w `tree-line`
- Ramię i pień do zaślepek przerywane i w `tree-line`, także na linii przodków; para dwóch zaślepek ma przerywany również pień
- Węzły rodziców: rok, „ok. …”, same obrączki, brak węzła dla pary bez ślubu i niepełnej; przycisk „Połącz jako małżeństwo” nadal działa w bloku partnera
- Linia pary: ciągła przy ślubie, przerywana przy „bez ślubu”, połówka partnera przerywana przy nieznanym partnerze i przy braku związków
- Belka dzieci: zaokrąglone końce, pień styka się z belką przy 1, 2 i 3 dzieciach, przerywane odgałęzienie do „Dziecko” obok; przy ≥4 dzieciach slot pod rzędem bez linii; przy 0 dzieciach pień przerywany
- Linie dochodzą do środka krawędzi kart bez odstępu, w jasnym i ciemnym motywie
- `verdict.md` i zrzuty jasny/ciemny zaakceptowane przez użytkownika

**Implementation Note**: Po zielonych bramkach automatycznych zatrzymaj się na ręczne potwierdzenie i ocenę zrzutów.

---

## Testing Strategy

### Unit Tests:

- `view-tokens.test.ts`: brak literałów (także `[1.5px]`) w `union-node.tsx`, `sheet-person-card.tsx`, `marriage-rings-icon.tsx`, `family-sheet.tsx`; jedyny wyjątek arkusza to `style={`.

### Integration Tests:

- `tests/Feature/TreeTest.php`: osiem przypadków `parent_marriages` / `grandparent_marriages` z Fazy 1, 404 `tree.show` dla cudzego drzewa, niezmieniony licznik zapytań.
- Istniejące E2E drzewa (`tree-slot-spouse`, `tree-slot-child`, `marriage-side-panel`, `person-photo-on-card`) powinny przejść bez zmian. Nowy `aria-label` przy braku związków nie pasuje do `/^Związek \d z \d/`.

### Manual Testing Steps:

1. `/tree` probanda demo (Jan Kowalski): linia przodków, trzy rodzaje węzłów rodziców, rodzice Anny z przyciskiem „Połącz jako małżeństwo”.
2. Przełącz związek na Ewę (ślub bez daty, jedna znana matka): pigułka „2 z 4”, „ślub, data nieznana”; ramię do „Nieznany ojciec” w bloku partnera przerywane. Związek z Martą: „3 z 4”, „bez ślubu”, przerywana ramka pigułki i cała linia pary.
3. Związek z nieznanym partnerem (Tomasz): pigułka „Związki”, przerywana połówka do „Nieznany partner”.
4. Osoba bez związków (Kacper): pigułka „Związki”, menu „Dodaj małżonka”, połówka do slotu przerywana, pień przerywany.
5. Para z 5 dziećmi i para z 3 dziećmi z danych demo: belka, odgałęzienie do „Dziecko” obok / brak linii pod rzędem.
6. Ciemny motyw: kontrast linii `tree-line` i `primary` na tle.

## Performance Considerations

Liczba zapytań arkusza bez zmian. Nowe pola liczone są w pamięci z już załadowanych `events` (co najwyżej 6 par, po kilka zdarzeń na osobę). Test `TreeTest.php:512-549` tego pilnuje.

## Migration Notes

Brak migracji. Nowe pola tylko dochodzą do propsa `sheet`, nic nie jest usuwane. Seeder demo dostaje dodatkowe małżeństwa (dane nieprodukcyjne).

## References

- Badania: `context/changes/tree-lines-union-node/research.md` (decyzje D1–D4)
- Design system: https://claude.ai/artifact/U31mtUCgmLXcrgocGCWstU (`components/UnionNode/README.md`, `components/PersonCard/README.md`, `tokens.json` → `layout`, `implementation.md` §3)
- Makieta: https://claude.ai/artifact/Nvqqp3f43K3Kf22tNudBuS (`project/Main.dc.html`, `project/Union.dc.html`, `project/rk.css` → `.rk-j`, `.rk-knot`, `.rk-kid`, `.rk-couple-*`, `.rk-union`)
- Kształt arkusza: `app/Actions/Trees/BuildFamilySheet.php:53-172`, sloty `:392-440`
- Wzorzec 404: `tests/Feature/AncestorsTest.php:233-240`
- Wzorzec zrzutów: `context/archive/2026-10-12-design-tokens/screenshots/shots.mjs`
- Poprzednia zmiana (tokeny kolorów, odłożony zakres): `context/archive/2026-10-12-design-tokens/plan.md:40-42`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Dane par w arkuszu

#### Automated

- [x] 1.1 Testy arkusza przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php` — 3400d95
- [x] 1.2 Larastan poziom 7 bez błędów: `vendor/bin/sail composer types:check` — 3400d95
- [x] 1.3 Pint bez zmian po formatowaniu: `vendor/bin/sail bin pint --dirty --format agent` — 3400d95
- [x] 1.4 Typy TS: `vendor/bin/sail npm run types:check` — 3400d95

#### Manual

- [x] 1.5 Prop `sheet` na `/tree` zawiera `parent_marriages` i `grandparent_marriages` w opisanym kształcie — 3400d95

### Phase 2: Tokeny, obrączki, pigułka i listwa płci

#### Automated

- [x] 2.1 Strażnik tokenów i testy JS: `vendor/bin/sail npm run test:js` — 51b98df
- [x] 2.2 Format i lint: `vendor/bin/sail npm run check` — 51b98df
- [x] 2.3 Typy TS: `vendor/bin/sail npm run types:check` — 51b98df
- [x] 2.4 Build: `vendor/bin/sail npm run build` — 51b98df
- [x] 2.5 Brak `[1.5px]` i `[2px]` w widokach drzewa (`resources/js/components/tree`) — 51b98df

#### Manual

- [x] 2.6 Pigułka na `/tree` we wszystkich stanach, także osoba bez związków — 51b98df
- [x] 2.7 Karta ze zdjęciem pokazuje listwę 4 px w kolorze płci pod zdjęciem — 51b98df

### Phase 3: Linie arkusza

#### Automated

- [x] 3.1 Strażnik tokenów z `family-sheet.tsx` i testy JS: `vendor/bin/sail npm run test:js` — ce6b16c
- [x] 3.2 Format i lint: `vendor/bin/sail npm run check` — ce6b16c
- [x] 3.3 Typy TS: `vendor/bin/sail npm run types:check` — ce6b16c
- [x] 3.4 Build: `vendor/bin/sail npm run build` — ce6b16c
- [x] 3.5 Testy arkusza nadal zielone: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php` — ce6b16c
- [x] 3.6 Brak linii jako wypełnień w arkuszu — ce6b16c
- [x] 3.7 Zrzuty i werdykt istnieją — ce6b16c

#### Manual

- [x] 3.8 Linia przodków probanda 2 px w `primary`, rodzina partnera i dzieci w `tree-line` — ce6b16c
- [x] 3.9 Ramię i pień do zaślepek przerywane i w `tree-line` — ce6b16c
- [x] 3.10 Węzły rodziców: rok, „ok. …”, same obrączki, brak węzła dla pary bez ślubu i niepełnej — ce6b16c
- [x] 3.11 Linia pary: ciągła, przerywana przy „bez ślubu”, połówka partnera przerywana przy braku partnera — ce6b16c
- [x] 3.12 Belka dzieci przy 0, 1, 2, 3 i ≥4 dzieciach — ce6b16c
- [x] 3.13 Linie dochodzą do środka krawędzi kart w obu motywach — ce6b16c
- [x] 3.14 `verdict.md` i zrzuty jasny/ciemny zaakceptowane przez użytkownika — ce6b16c
