---
date: 2026-10-19T08:52:58Z
researcher: Claude (Opus 5.5)
git_commit: d123e47
branch: develop
repository: root-keeper
topic: "Audyt dwukierunkowy widoku przodków (/tree/{person}/ancestors) wg 10x-ui"
tags: [research, ui-audit, ancestors-view, zoom-canvas, card-avatar, build-ancestor-chart]
status: complete
last_updated: 2026-10-19
last_updated_by: Claude (Opus 5.5)
---

# Research: Audyt dwukierunkowy widoku przodków

**Date**: 2026-10-19T08:52:58Z
**Researcher**: Claude (Opus 5.5)
**Git Commit**: d123e47 (drzewo robocze: tylko nowy folder tej zmiany i niezwiązane `storage/gen-tree-import/`)
**Branch**: develop
**Repository**: root-keeper

## Research Question

Audyt widoku `/tree/{person}/ancestors` wg `/10x-ui`: potwierdzić zarzuty 1–5 z `change.md` (wejście z drzewa, zoom kółkiem bez Ctrl, „wszystkie” pokolenia z wpływem na backend/`BuildAncestorChart` i `fit-view`, wiek tylko na tym widoku, wygląd zdjęcia — zrzut desktop + mobile), ocenić 7× `style=` w `ancestor-chart.tsx`. Wynik w `## Charges`.

## Summary

Wszystkie pięć zarzutów potwierdzone na kodzie i na zrzutach z działającej aplikacji (serwer E2E, dane z datami i zdjęciem). Widok jest zbudowany z tokenów — skan zakodowanych wartości na 7 plikach widoku daje 0 trafień — więc problem leży w zachowaniu i punktach wejścia (kategoria „przypadkowa architektura”), nie w kolorach. Najcięższy zarzut to pokolenia: rekurencja `BuildAncestorChart::buildNode` nie ma ochrony przed cyklem i zatrzymuje ją wyłącznie limit głębokości, a w bazie dev istnieje pętla (osoba 560 jest własnym rodzicem). Decyzje użytkownika (2026-10-19): samo kółko zoomuje (tylko przodkowie), przełącznik pokoleń pokazuje tyle opcji, ile przodków faktycznie jest, wiek znika tylko z widoku przodków, avatar większy, nadal obok imienia.

## Charges

Kategorie wg `/10x-ui`: **T** brakujące tokeny, **K** brakujący współdzielony komponent/wariant, **A** przypadkowa architektura.

| # | Kat. | Zarzut (plik:linia) | Wpływ na użytkownika | Decyzja / kierunek |
|---|---|---|---|---|
| C1 | A | Jedyne wejście to pozycja „Pokaż przodków” w menu „⋯” panelu osoby (`resources/js/components/persons/person-modal-header.tsx:107-113`, `:162-175`). Rząd tytułu drzewa nie ma żadnej akcji (`resources/js/pages/tree/show.tsx:79-81`). | Użytkownik na drzewie nie wie, że widok przodków istnieje; żeby go otworzyć, musi otworzyć panel i rozwinąć menu. | Przycisk „Przodkowie” w rzędzie tytułu `/tree` (`Heading` + akcja, jak `ancestors.tsx:55-67`), link do `treeAncestors(sheet.focus.id)`. Pozycja w menu panelu zostaje (pokrywa ją E2E). |
| C2 | A | Zoom kółkiem wymaga Ctrl: `wheel={zoomControls ? { activationKeys: ['Control'] } : { disabled: true }}` (`resources/js/components/zoom-canvas.tsx:594-598`). Krok domyślny biblioteki (`step: 0.015 × |deltaY|`, `node_modules/react-zoom-pan-pinch/dist/index.esm.js:814-820`, `:2016`). | Samo kółko nie robi nic (zmierzone: skala 0.654 → 0.654). Ctrl+kółko jednym ruchem (deltaY 400) skacze do maksimum (0.654 → 1.5) — zoom jest nieużywalny. | **Decyzja: samo kółko zoomuje**, tylko gdy `zoomControls` (widok przodków); `family-sheet.tsx:712-721` bez zmian. Krok `wheel.step` dostroić, by jeden ząbek był małym krokiem. |
| C3 | A | Pokolenia na sztywno 2–6: `TreeAncestorsController.php:14-18` (MIN/DEFAULT/MAX), `:35-42` (poza zakresem → 4); `GENERATION_ITEMS` (`resources/js/pages/tree/ancestors.tsx:16-20`); próg startu dobrany pod 6 (`resources/js/lib/fit-view.ts:7-14`). | Głębszych gałęzi nie da się zobaczyć; przy płytkich danych przełącznik oferuje „5” i „6”, które dają tylko rzędy „Nieznany ojciec/matka”. | **Decyzja: przełącznik zna dostępną głębokość** — od 2 do faktycznej liczby pokoleń przodków danej osoby, bez opcji ponad dane i bez sztywnego 6. Wymaga ochrony przed cyklem w `buildNode` (patrz Detailed Findings §3). |
| C4 | K | Wiek pokazuje wspólny `CardBody` (`resources/js/components/tree/sheet-person-card.tsx:76-80`), używany przez kartę przodka (`resources/js/components/tree/ancestor-card.tsx:41-47`) i kartę standardową drzewa (`sheet-person-card.tsx:193`). | Na gęstym wykresie każda karta ma dodatkowy wiersz „(76 lat)”, który nie pomaga w czytaniu linii przodków i zabiera miejsce nazwisku. | **Decyzja: wiek znika tylko z widoku przodków.** Szew: opcjonalny prop `CardBody` (np. `showAge`), `false` z `ancestor-card.tsx`. Backend bez zmian (`SheetPersonResource.php:75` dalej wysyła `age`). |
| C5 | K | Avatar karty przodka ma stałe `size-10` (2.5rem): `card-avatar.tsx:66-72` daje `size-10 @xs:size-14`, a `@xs` (20rem) nigdy nie zachodzi, bo kolumna przodków ma 12–18rem (`fit-view.ts:20`, `:30`). Wariant `large` (`size-16`) istnieje dla karty pary (`card-avatar.tsx:56-57`, `sheet-person-card.tsx:141`). | Po dopasowaniu (skala ~0.65 przy 4 pokoleniach, 1366×768) zdjęcie ma ok. 26 px — twarzy nie da się rozpoznać (`screenshots/before-focus-card.png`). | **Decyzja: większy avatar, nadal obok imienia** (układ jak karta pary). Wariant rozmiaru w `CardAvatar`, nie nowy komponent. |
| C6 | A (guard) | `ancestor-chart.tsx` jest w całości pominięty w `resources/js/lib/view-tokens.test.ts:14-20`; nowy `card-avatar.tsx` też nie jest na liście `VIEW_FILES`. | Hex lub klasa palety dodana do tych plików przejdzie CI niezauważona. | 7× `style=` (`ancestor-chart.tsx:44-136`) to **uzasadniony wyjątek** (geometria rem liczona w runtime, per-element `left`/`width` linii). Naprawa: dodać oba pliki do `VIEW_FILES` z wyjątkiem `style={` jak `family-sheet.tsx` — w fazie „Utrwal to”. |
| C7 | A | Mobile 390×844: wykres dopasowany do szerokości jest mikroskopijny, nad nim pusty pas, przyciski zoomu zajmują własny rząd (`screenshots/before-ancestors-4gen-390x844.png`). | Na telefonie widok jest nieczytelny bez ręcznego zoomu. | **Deferred** — poza życzeniami użytkownika; pinch już działa (`zoom-canvas.tsx:599`). Do osobnej zmiany, chyba że plan zmieści start zakotwiczony na osobie. |
| C8 | — | Dane: relacja `parent_child_relations.id=471` ma `parent_id = child_id = 560` (zapytanie do bazy dev `laravel`). `CreateParentChildRelation.php:38` sprawdza `descendantsOf($child)`, który nie zawiera samego dziecka. | Pętla w danych; dziś nieszkodliwa tylko dzięki limitowi pokoleń (C3). | **Deferred / osobna zmiana** (integralność danych). Ta zmiana musi jedynie być odporna na cykl (C3). |

Liczniki skanu (`/10x-ui`, przed zmianą): 0 trafień zakodowanych wartości na `ancestors.tsx`, `ancestor-chart.tsx`, `ancestor-card.tsx`, `card-avatar.tsx`, `sheet-person-card.tsx`, `unknown-person-card.tsx`, `zoom-canvas.tsx`; 7× `style=` w `ancestor-chart.tsx`, 0 w pozostałych sześciu.

## Detailed Findings

### 1. Wejście (C1)

- Linki do trasy `tree.ancestors` poza katalogami generowanymi: menu panelu (`person-modal-header.tsx:107-113`, `:162-175`, ukryte dla centrum wykresu `:105`), okruszek i przełącznik na samej stronie (`ancestors.tsx:29`, `:44`). Trasa: `routes/web.php:35`.
- `/tree` centruje się na `sheet.focus` (probant na `/tree`, `{person}` na `/tree/{person}`) — `app/Http/Controllers/TreeController.php:23-26`. Rząd tytułu to sam `Heading` w `div.mb-3` (`show.tsx:79-81`).
- `tests/e2e/tree-fill-window.spec.ts:155-171` mierzy obszar drzewa od dołu h1 — wyższy rząd tytułu może przesunąć tę asercję; przycisk w tym samym rzędzie co `Heading` (bez nowego wiersza) jej nie rusza.
- `tests/e2e/ancestors-view.spec.ts:44-55`, `:125` wchodzą przez menu „⋯” — nowy przycisk ich nie łamie, usunięcie pozycji menu by łamało.

### 2. Kółko (C2)

- Bez Ctrl `isWheelAllowed` zwraca false (`react-zoom-pan-pinch/dist/index.esm.js:1835-1837`), panning kółkiem jest domyślnie wyłączony (`trackPadPanning.disabled: true`, `:822-823`), wrapper ma `overflow: hidden`, a strona z `fillHeight: true` (`ancestors.tsx:34`) się nie przewija — stąd brak jakiejkolwiek reakcji.
- Pusta lista `activationKeys` oznacza „zawsze dozwolone” (`index.esm.js:2777-2779`); precedens: `resources/js/components/sources/source-viewer.tsx:14-20` używa domyślnych ustawień, więc tam kółko już zoomuje skan.
- `onWheelStart={markMoved}` (`zoom-canvas.tsx:603`) już chroni ręczny zoom przed ponownym dopasowaniem przy resize.
- Pomiar (Playwright, 1366×768, 4 pokolenia): `matrix(0.654…)` → po `wheel(0,-400)` bez Ctrl bez zmian → po tym samym z Ctrl `matrix(1.5…)` (maksimum `ANCESTORS_ZOOM_MAX`, `fit-view.ts:17`).
- Ctrl+kółko nad paskiem przycisków robi zoom strony przeglądarki, bo pasek jest rodzeństwem wrappera (`zoom-canvas.tsx:561-562`) — drobne, do rozważenia w planie.
- Szczypanie na touchpadzie przychodzi jako wheel z `ctrlKey` (`index.esm.js:2748-2752`) — po zmianie dalej działa.
- Żaden test nie przypina zachowania kółka; `ancestors-view.spec.ts:139-225` pokrywa tylko przyciski i zachowanie zoomu przy resize.

### 3. Pokolenia (C3)

- `ParentChildGraph::ancestorIdsWithin` (`app/Support/ParentChildGraph.php:86-111`) idzie poziomami z `$visited` — odporny na cykl, liczba zapytań niezależna od głębokości (graf z jednego zapytania `:23-36`, osoby z jednego `BuildAncestorChart.php:47-51`). Nadaje się do policzenia dostępnej głębokości.
- `buildNode`/`parentNodes` (`BuildAncestorChart.php:68-111`) rozwija wykres jako drzewo bez zbioru odwiedzonych; jedyny stop to `$generation < $this->generations` (`:76`). Wspólny przodek (pedigree collapse) jest kopiowany i oznaczany przez `markRepeated` (`:127-138`). Przy głębokości liczonej z danych cykl (C8) dałby rekurencję do limitu — potrzebny stop na ścieżce (osoba już na bieżącej ścieżce → bez rodziców) albo twardy sufit.
- Test `tests/Feature/AncestorsTest.php:202-213` („cycle … does not hang”) przechodzi tylko dzięki domyślnemu 4.
- Głębokość w danych dev (zapytania tylko do odczytu, ścieżki bez cykli): drzewo 1 (249 osób) — najgłębszy wykres 10 rzędów, ~170 kolumn; drzewo 2 — 7 rzędów, 43 kolumny; drzewo 6 — 5 rzędów, 16 kolumn. 170 kolumn × 12.75rem ≈ 34 700 px → skala ~0.03 na 1000 px, poniżej `ANCESTORS_FIT_FLOOR = 0.1` (`fit-view.ts:14`); przy `minScale={floor}` (`zoom-canvas.tsx:592`) widok wystartuje zakotwiczony na osobie i nie da się oddalić do całości. Plan musi zdecydować o progu/starcie dla głębokości > 6.
- Przepływ wartości: `$request->integer('generations', 4)` (`TreeAncestorsController.php:35-42`); prop `generations: number` (`resources/js/types/tree.ts:106`); okruszek (`ancestors.tsx:29-31`); link w panelu (`person-panel.tsx:39-40` → `person-modal-header.tsx:107-112`); klucz `${focus.id}:${generations}` w `ancestor-chart.tsx:98`. Payload potrzebuje nowego pola z dostępną głębokością (np. `available_generations`), żeby przełącznik zbudował opcje.
- Testy przypinające zakres: `AncestorsTest.php:73-85` (domyślnie 4), `:105-112` (2), `:117-126` (fallback, dataset `['1','7','abc']` na `:126` — „7” przestanie być zawsze „poza zakresem”), `:202-213` (cykl), `:215-231` (liczba zapytań przy 6); `tests/Feature/PersonsTest.php:1260-1264` (generations=3 w URL); `tests/e2e/ancestors-view.spec.ts:58-59`, `:84-88`, `:129-130`, `:166`. `AncestorsDemoSeeder::GENERATIONS = 6` (`database/seeders/AncestorsDemoSeeder.php:31`). Vitest nie przypina 6.
- Przy 6 pokoleniach na 1366×768 tekst kart jest już nieczytelny (`screenshots/before-ancestors-6gen-1366x768.png`).

### 4. Wiek (C4)

- `formatAge` na kartach drzew: `CardBody` (`sheet-person-card.tsx:76-80`, tylko gdy `lifespan && age`), `PairBody` (`:165-169`); poza kartami nagłówek panelu (`person-modal-header.tsx:35-36`). `CompactBody` (`:104-127`) wieku nie ma.
- `CardBody` ma dokładnie dwóch konsumentów: karta standardowa (`sheet-person-card.tsx:193`) i `AncestorCard` (`ancestor-card.tsx:41-47`).
- Backend: `BuildAncestorChart::render()` → `SheetPersonResource::make($person)->resolve()` (`BuildAncestorChart.php:143-149`), które zawsze wysyła `age` (`SheetPersonResource.php:75`); typ `SheetPerson.age` jest wspólny i nieopcjonalny (`types/tree.ts:23`). Ukrycie w backendzie to więcej zmian bez zysku w UI.
- Testy wieku: `tests/Feature/TreeTest.php:509-528` (arkusz drzewa), `PersonsTest.php:1101-1122` (panel), `tests/Unit/AgeTest.php`, `resources/js/lib/age.test.ts`. Ani `AncestorsTest.php`, ani E2E nie asertują wieku na kartach przodków.

### 5. Zdjęcie (C5)

- `f641432` zastąpił pasek zdjęcia (`PhotoStrip`, `w-12 @xs:w-18`) okrągłym `CardAvatar` i przez wspólny `CardBody` zmienił też kartę przodka; plan tamtej zmiany deklarował przodków „wizualnie i geometrycznie bez zmian” (`context/archive/2026-10-19-tree-dashboard-fill-window/plan.md:36`, `research.md:162`) — zmiana przodków była poza planem.
- Avatar: `relative mt-3.5 ml-3 shrink-0 self-start overflow-hidden rounded-full border`, tło `genderSurface` (`card-avatar.tsx:66-72`, `resources/js/lib/gender-styles.ts:4-8`), bez zdjęcia sylwetka SVG (`card-avatar.tsx:20-47`).
- Nieaktualne komentarze po `f641432`: `card-styles.ts:10-13` i `:19` („photo strip”), `fit-view.ts:29` („threshold of the `@xs` strip”). Do poprawy przy okazji C5.

### 6. Tokeny (kierunek źródło → widok)

- Tokeny drzewa istnieją w obu motywach: `@theme` `resources/css/app.css:63-73` (`gender-*`, `gender-*-surface`, `gender-*-border`, `tree-line`, `tree-meta`, `border-width-line`), `:root` `:111-120`, `.dark` `:156-164`; `--tree-line-width` tylko w `:root` (`:120`) — szerokość nie zależy od motywu.
- Widok czyta je: `ancestor-chart.tsx` (`bg-tree-line`), `ancestor-card.tsx` (`text-tree-meta`, `genderCard`), `card-avatar.tsx` (`text-gender-*`, `genderSurface`); komponenty `Heading`, `SegmentedToggle` (`ancestors.tsx:2-3`), `Button` (`zoom-canvas.tsx`).
- Brak pliku reguł zachęcającego do jednorazowych wartości; reguły UI w `CLAUDE.md` („UI: tokens and shared components”) są zgodne z kontraktem. Nie ma `.ai/rules`.

## Code References

- `resources/js/pages/tree/ancestors.tsx:16-20` — `GENERATION_ITEMS` 2–6
- `resources/js/pages/tree/show.tsx:79-81` — rząd tytułu drzewa (miejsce na „Przodkowie”)
- `resources/js/components/persons/person-modal-header.tsx:107-113`, `:162-175` — jedyne wejście
- `resources/js/components/zoom-canvas.tsx:561-587` — przyciski zoomu; `:594-603` — konfiguracja kółka/szczypania
- `resources/js/components/tree/ancestor-chart.tsx:14`, `:44-136`, `:98` — `WIDE_CHART_COLUMNS`, geometria `style=`, klucz wykresu
- `resources/js/components/tree/ancestor-card.tsx:41-47` — karta przodka → `CardBody`
- `resources/js/components/tree/sheet-person-card.tsx:76-80` — wiersz wieku
- `resources/js/components/tree/card-avatar.tsx:56-57`, `:66-72` — rozmiary avatara
- `resources/js/lib/fit-view.ts:7-17`, `:20`, `:29-30` — próg/max ancestors, kolumny
- `resources/js/lib/view-tokens.test.ts:14-20` — pominięcie `ancestor-chart.tsx`
- `app/Http/Controllers/TreeAncestorsController.php:14-18`, `:35-42` — zakres pokoleń
- `app/Actions/Trees/BuildAncestorChart.php:68-111`, `:127-138`, `:143-149` — rekurencja, `markRepeated`, render
- `app/Support/ParentChildGraph.php:86-111` — `ancestorIdsWithin` (odporny na cykl)
- `tests/Feature/AncestorsTest.php:73-85`, `:117-126`, `:202-231` — testy zakresu, cyklu, liczby zapytań

## Architecture Insights

- `ZoomCanvas` jest współdzielony przez drzewo (`family-sheet.tsx:712-721`, bez `zoomControls`) i przodków (`ancestor-chart.tsx:97-105`, z `zoomControls`). Zmiana kółka w gałęzi `zoomControls` nie dotyka drzewa.
- Karta przodka nie ma własnego wariantu — dziedziczy `CardBody` drzewa, więc każda zmiana karty drzewa przecieka na przodków (tak przyszedł avatar w `f641432`). Prop wariantu na `CardBody`/`CardAvatar` daje przodkom własne miejsce bez kopiowania markupu.
- Głębokość wykresu: serwer liczy, klient tylko układa (`ancestor-layout.ts:51-55` — kolumny = liście, `:87` — rzędy z kart, bez sztywnego limitu).

## Historical Context (from prior changes)

- `context/archive/2026-10-10-ancestors-view/plan-brief.md:22` — „Domyślnie 4, maks. 6 — kompromis między zasięgiem a czytelnością” (wspierane; decyzja użytkownika z 2026-10-19 zastępuje sztywne 6 zakresem z danych). `plan.md:187`, `:385`; `research.md:76`, `:127` (Gramps do 5, MyHeritage 6).
- `.../plan-brief.md:26` — „Ctrl+kółko i szczypanie; samo kółko nic nie robi — bez przypadkowego przybliżania”; `plan.md:179-181`, `:261`, `:444`. **Zastąpione** decyzją użytkownika z 2026-10-19 dla widoku przodków; zakaz zoomu na drzewie (`plan.md:201`, `:430`; `context/archive/2026-10-05-tree-fits-screen/plan.md:311`) nadal obowiązuje.
- `.../plan-brief.md:28` — wejście tylko z „⋯”, bo drugi przycisk w nagłówku panelu ucinał tytuł przy 24rem (`reviews/plan-review.md:25-35`) — dotyczy nagłówka panelu, nie rzędu tytułu drzewa; `plan-brief.md:45`, `plan.md:45` — wejście z nagłówka drzewa było poza zakresem, nie odrzucone.
- `context/foundation/prd.md:151-152` — sprzeciw: „przy 6+ pokoleniach widok przodków robi się nieczytelny” — potwierdzony zrzutem 6 pokoleń; plan musi pokazać, jak głębokość > 6 jest czytelna (zoom kółkiem, start zakotwiczony).
- `context/archive/2026-10-19-tree-dashboard-fill-window/plan.md:36`, `research.md:162` — przodkowie mieli zostać bez zmian; `f641432` zmienił ich kartę poza planem.

## Related Research

- `context/archive/2026-10-10-ancestors-view/research.md`
- `context/archive/2026-10-19-tree-dashboard-fill-window/research.md`
- `context/archive/2026-10-05-tree-fits-screen/` (decyzja o braku zoomu na drzewie)

## Evidence: screenshots

`context/changes/ancestors-view-polish/screenshots/` (serwer E2E, port 8100, baza `e2e`, dane: 5 pokoleń z latami, zdjęcie `tests/Fixtures/portrait-small.jpg` na osobie w centrum; rekordy usunięte przez fixture `suffix`):

- `before-ancestors-4gen-1366x768.png` — desktop, 4 pokolenia: wiek na każdej karcie, małe avatary, pusty pas nad wykresem
- `before-focus-card.png` — karta centrum: zdjęcie ~26 px
- `before-ancestors-6gen-1366x768.png` — 6 pokoleń: tekst nieczytelny
- `before-ancestors-4gen-390x844.png` — mobile: wykres mikroskopijny (C7)
- `before-tree-show-1366x768.png` — rząd tytułu `/tree` bez akcji (C1)

## Open Questions

Rozstrzygnięte z użytkownikiem 2026-10-19: kółko (samo zoomuje, tylko przodkowie), pokolenia (opcje do faktycznej głębokości), wiek (tylko przodkowie), zdjęcie (większy avatar obok imienia). Pozostają dla `/10x-plan`:

1. **Min. i krawędzie przełącznika:** co pokazać, gdy osoba nie ma żadnych rodziców (dziś 2 pokolenia z placeholderami) albo ma tylko rodziców (jedna opcja „2”) — ukryć przełącznik czy pokazać pojedynczą opcję. Czy „N” jest dostępne, gdy na pokoleniu N jest choć jedna znana osoba (wtedy wykres kończy się rzędem placeholderów nad nią)?
2. **Domyślna głębokość i adres poza zakresem:** domyślnie `min(4, dostępne)`; `?generations=` większe niż dostępne → przyciąć do dostępnych czy fallback do domyślnej (zmienia `AncestorsTest.php:117-126`).
3. **Sufit bezpieczeństwa:** ochrona przed cyklem na ścieżce w `buildNode` plus twardy sufit techniczny (np. 20) czy sam stop na ścieżce.
4. **Start i próg dla > 6 pokoleń:** obniżyć `ANCESTORS_FIT_FLOOR`, czy startować zakotwiczonym na osobie i polegać na zoomie kółkiem; wydajność ~170 kart + linii nie była mierzona.
5. **Krok kółka:** wartość `wheel.step` (do dobrania w przeglądarce, mysz vs touchpad).
6. **Rozmiar avatara przodka:** `size-16` jak karta pary czy pośredni; wpływ na wysokość karty (`min-h-24`, `card-styles.ts`) i na układ rzędów.
