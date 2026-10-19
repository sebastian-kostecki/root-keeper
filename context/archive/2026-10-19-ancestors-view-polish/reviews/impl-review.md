<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Dopracowanie widoku przodków

- **Plan**: context/changes/ancestors-view-polish/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-19
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Automatyczne kryteria (uruchomione 2026-10-19): Pest `AncestorsTest` + `PersonsTest` 157/157, Larastan 0 błędów, Pint czysty, Vitest 357/357, `types:check` OK, build OK, E2E `ancestors-view` + `tree-fill-window` 9/9. Globalny `npm run check` pada tylko na nieśledzonym `storage/gen-tree-import/` (poza zakresem); `vp check` na 13 zmienionych plikach przechodzi.

Autoryzacja bez zmian (`Gate::authorize('view', $person)`, odczyty przez `$person->tree`). Memoizacja `ancestorGenerations` z regułą stopu na ścieżce została sprawdzona przez dwóch recenzentów niezależnie: wartość zapamiętana jest niezależna od ścieżki, więc N zgadza się z wykresem.

## Findings

### F1 — Rozmiar wykresu nieograniczony przy zawężeniu linii do 20 pokoleń

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Trees/BuildAncestorChart.php:86-96 (oraz `MAX_GENERATIONS` :30)
- **Detail**: `buildNode` rozwija rodziców przy każdym wystąpieniu osoby (stop tylko dla osoby już na bieżącej ścieżce), a `render()` wywołuje `SheetPersonResource::resolve()` dla każdego wystąpienia. Liczba węzłów rośnie więc z liczbą ścieżek, nie z liczbą osób. Przed zmianą sufit 6 dawał ≤ 63 sloty; teraz sufit 20 to do 2^20 − 1 ≈ 1 mln węzłów (np. 2 osoby na pokolenie, obie rodzicami obu osób niżej — 40 osób). Skutek: limit pamięci/timeout → 500, a gdyby odpowiedź wróciła, setki MB JSON i ~2^19 kolumn kart w DOM. Domyślne 4 jest bezpieczne; ryzyko dotyczy wyboru wysokiej głębokości w drzewie z silnym zawężeniem (np. import GEDCOM rodów szlacheckich — `storage/gen-tree-import/` sugeruje importy). Plan w „Performance Considerations” zabezpieczył tylko liczenie N, nie rozmiar samego wykresu — to luka planu.
- **Fix A ⭐ Recommended**: Rozwijać rodziców tylko przy pierwszym wystąpieniu osoby; kolejne wystąpienia to liście z `repeated = true`, a `ancestorGenerations` liczy tą samą regułą. Dodać `render()` memoizowane per id i test Pest z „drabiną” zawężeń na 20 pokoleń.
  - Strength: Rozmiar wykresu ograniczony liczbą osób (liniowy); karta „Występuje kilka razy” już istnieje, więc semantyka UI jest gotowa.
  - Tradeoff: Zmienia regułę wykresu (powtórzony przodek nie pokazuje drugi raz swoich rodziców) i regułę N; trzeba zaktualizować test zawężenia linii i ryzyko E2E; przy pierwszym wystąpieniu „głębiej w kolejności” może być płytsza gałąź, więc N musi liczyć tą samą kolejnością DFS.
  - Confidence: MED — wymaga przemyślenia, które wystąpienie jest „pierwsze” (kolejność DFS vs najpłytsze), żeby N i wykres się zgadzały.
  - Blind spot: Nie sprawdzono, czy użytkownik chce widzieć pełną gałąź przy każdym wystąpieniu (decyzja produktowa).
- **Fix B**: Budżet węzłów (np. 2–5 tys.): akcja obniża `available_generations` do największej głębokości, której wykres mieści się w budżecie; plus memoizacja `render()`.
  - Strength: Zachowuje obecną semantykę wykresu; zmiana lokalna w akcji.
  - Tradeoff: Potrzebne tanie liczenie rozmiaru wykresu per głębokość (licznik ścieżek z memoizacją); przełącznik może zaskoczyć mniejszym N niż realna głębokość danych.
  - Confidence: MED — liczenie ścieżek z memo jest proste w DAG, ale cykl wyłącza memo (zob. reguła `isFinal`).
  - Blind spot: Wartość budżetu nie zmierzona (wydajność renderu kilku tys. kart w przeglądarce).
- **Decision**: FIXED (Fix A) — rodzice tylko przy pierwszym wystąpieniu w najbliższym pokoleniu (BFS `ParentChildGraph::ancestorLevels` zastępuje `ancestorGenerations`); N = najgłębszy rząd ze znaną osobą wg tej reguły; `render()` memoizowane; test łączenia linii 7 → 6 i nowy test „drabiny” 20 pokoleń (75 węzłów). Pest 31/31 + 127/127, Larastan, Pint, E2E przodków 7/7. Uwaga: plan (Desired End State, Key Discoveries, Faza 1 pkt 1–2) opisuje starą regułę „najdłuższej linii” — do addendum (zob. F4).

### F2 — `minScale` nieaktualne po ręcznym zoomie i zmianie rozmiaru

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/zoom-canvas.tsx:252 (aktualizacja tylko w `fit`), :367 (resize nie wywołuje `fit` po `movedRef`)
- **Detail**: `setWholeScale` liczy skalę całości tylko w `fit`. Po ręcznym zoomie/przesunięciu resize nie dopasowuje ponownie, więc zwężenie płótna (np. otwarcie panelu bocznego obok wykresu) zostawia stare `minScale` — „Pomniejsz”/kółko zatrzymują się tuż przed całością, wbrew kryterium „oddalają aż do całości”.
- **Fix**: W obserwatorze resize przeliczać `wholeScale` (z `wrapper`/`content`) także wtedy, gdy `movedRef.current` jest ustawione.
- **Decision**: FIXED — wydzielone `measureWhole()` wołane w `fit` i w `schedule`, gdy widok jest ręczny; `vp check`, `types:check`, Vitest 357/357, E2E przodków + drzewa 9/9.

### F3 — Bramka wizualna niepełna (zrzut 8 pokoleń, stan loading)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/ancestors-view-polish/screenshots/
- **Detail**: Plan wymagał `after-ancestors-8gen-1366x768.png`; zapisano `after-ancestors-7gen-1366x768.png`, który dodatkowo złapał toast „Dodano rodzica”. Komórka macierzy „loading” nie ma zrzutu ani zapisanego N/A z powodem, a 4.7 i 4.8 są odhaczone — możliwe pozorne odhaczenie.
- **Fix**: Zrobić ponownie zrzut głębokiego startu bez toastu (nazwa zgodna z planem albo adnotacja o 7 pokoleniach w planie) i dopisać w planie/zmianie wynik komórki „loading” (obserwacja: brak skoku układu, `visibility` do dopasowania) lub zrzut.
- **Decision**: FIXED — `after-ancestors-7gen-1366x768.png` zrobiony ponownie na świeżych danych (7 pełnych pokoleń, 126 osób, po regule z F1), start zakotwiczony na osobie, bez toastu; 7 pokoleń wystarcza do zakotwiczenia przy 1366 px, więc nazwa zostaje (plan: `8gen`). Komórka „loading”: `after-states-loading-switched.png` + pomiar — po zmianie 7 → 4 pokolenia `boundingBox` h1 (272,58,366×28) i przełącznika (1308,54,34×36) identyczne przed i po, wykres dopasowany w całości. Tymczasowa specyfikacja usunięta, dane posprzątane przez fixture `suffix`.

### F4 — Odchylenia testów i kontraktu od planu (nieszkodliwe)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/e2e/ancestors-view.spec.ts (R3), tests/Feature/AncestorsTest.php:375, app/Actions/Trees/BuildAncestorChart.php:64
- **Detail**: (1) R3 używa pełnego drzewa 6 pokoleń przy 390×844 i progu ≥ 0.7 zamiast linii 8 pokoleń i ≥ `ANCESTORS_ANCHOR_SCALE` (0.75); intencja pokryta. (2) Test liczby zapytań porównuje głębokość 6 z 0, plan mówił 8. (3) Osoby nadal ładowane przez `ancestorIdsWithin` mimo kontraktu „nie najkrótsza odległość” — dowodowo kompletne (najkrótsza odległość ≤ rząd na wykresie < N), potwierdza to test zawężenia linii; obawa planu była przesadzona.
- **Fix**: Dopisać w planie krótkie addendum z tymi trzema odchyleniami i ich uzasadnieniem.
- **Decision**: FIXED — sekcja „Addendum (impl-review 2026-10-19)” w `plan.md` (odchylenia testów, ładowanie osób, zrzuty, nowa reguła wykresu z F1, F2).

### F5 — N liczy wszystkich rodziców, wykres rysuje najwyżej dwóch

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/ParentChildGraph.php:120-176 vs app/Actions/Trees/BuildAncestorChart.php:118-123
- **Detail**: `ancestorGenerations` idzie po wszystkich `parentsOf`, a `parentNodes` bierze `$known[0]`, `$known[1]` po `PersonOrdering::parents`. Limit 2 rodziców pilnuje tylko `CreateParentChildRelation.php:34`; baza ma tylko `unique(parent_id, child_id)`. Przy imporcie z trzecim rodzicem o najgłębszej linii N oferowałby głębokość z pustym górnym rzędem. Dziś nieosiągalne.
- **Fix**: Liczyć N po tych samych „pierwszych dwóch uporządkowanych rodzicach” co wykres (albo pilnować ≤ 2 rodziców przy imporcie).
- **Decision**: FIXED (wariant) — wspólna reguła bez ładowania modeli: `ParentChildGraph::firstParentsOf($id, 2)` (najniższe id) używane przez `ancestorLevels` (BFS → poziomy i N) i `parentNodes`; `PersonOrdering::parents` decyduje już tylko o lewo/prawo. Stała `PARENTS_PER_PERSON = 2`. Nowy test „trzeci zapisany rodzic” (N = 2, rysowani ojciec i matka). Pest 32/32 + 180/180, Larastan, Pint.

### F6 — Krok kółka addytywny: przy głębokim wykresie jeden ząbek to kilkukrotny skok

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/lib/fit-view.ts:25-32
- **Detail**: react-zoom-pan-pinch 4.2.0 liczy `scale + delta * step`, więc ząbek (deltaY 100) to zawsze +0.1. Kryterium „< 25%” jest spełnione od skali 0.4 (komentarz to przyznaje), ale skoro `minScale` schodzi teraz do całości (drzewo 1 dev ≈ 0.03), jeden ząbek z całości daje ≈ 0.13, czyli ~4×.
- **Fix**: Skok zależny od skali: ustawić `wheel.step` w `onZoom`/`onWheel` proporcjonalnie do bieżącej skali (np. `ANCESTORS_WHEEL_STEP * scale / 0.4`, z dolnym ograniczeniem).
  - Strength: Stały względny krok w całym zakresie, kryterium „< 25%” prawdziwe także dla głębokich wykresów.
  - Tradeoff: Logika obok biblioteki; trzeba uważać na touchpad (strumień małych delt) i React Compiler (stan vs ref).
  - Confidence: MED — biblioteka czyta `wheel.step` z propsów przy każdym zdarzeniu, ale nie zweryfikowano w przeglądarce.
  - Blind spot: Nie sprawdzono, jak to odczuwa użytkownik; może wystarczyć akceptacja i dopisek w komentarzu.
- **Decision**: SKIPPED

### F7 — E2E: czekanie na klatki bez komentarza i zdublowany helper

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/ancestors-view.spec.ts:255 (`afterFrames`), :177 (lokalny `settledWidth`)
- **Detail**: `afterFrames` (podwójny `requestAnimationFrame`) przed asercją negatywną („drzewo nie zoomuje”) jest uzasadniony, ale nagina regułę „czekaj na stan” z `CLAUDE.md` bez wyjaśnienia. Test 2 definiuje lokalny `settledWidth` dublujący helper modułowy z :237.
- **Fix**: Dodać komentarz przy `afterFrames` (brak stanu do czekania w asercji negatywnej) i użyć modułowego `settledWidth` w teście 2.
- **Decision**: FIXED — komentarz przy `afterFrames`, test 2 używa `settledWidth(fatherCard)`; `vp check` czysty, E2E przodków 7/7.

### F8 — Nadpisanie wysokości przycisku `h-7`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/tree/show.tsx:86
- **Detail**: `<Button size="sm" className="h-7">` nadpisuje `h-8` z `sm` — jedyne takie miejsce w widokach. Służy kryterium „rząd tytułu nie wyższy”, ale nie jest opisane w planie i omija warianty `button.tsx`.
- **Fix**: Jeśli 28 px ma zostać, dodać wariant `size="xs"` w `components/ui/button.tsx` (lub zostawić z komentarzem, czemu `h-7`).
- **Decision**: FIXED — wariant `xs` (`h-7`, padding jak `sm`) w `components/ui/button.tsx`, `show.tsx` używa `size="xs"` bez `className`; `vp check`, `types:check`, Vitest 357/357, build, E2E drzewa + przodków 9/9 (wysokość rzędu tytułu bez zmian).

### F9 — `MIN_GENERATIONS = 2` w trzech miejscach

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/TreeAncestorsController.php:14, app/Actions/Trees/BuildAncestorChart.php:34, resources/js/pages/tree/ancestors.tsx:17
- **Detail**: To samo minimum zdefiniowane w kontrolerze, akcji i kliencie. Reguła z `CLAUDE.md` mówi, że zakres pokoleń przychodzi z serwera, a dolna granica jest zakodowana w kliencie.
- **Fix**: Kontroler używa publicznej stałej akcji; klient dostaje minimum z payloadu (albo zostaje komentarz, że 2 to stała produktu).
- **Decision**: FIXED — `BuildAncestorChart::MIN_GENERATIONS` publiczne i jedyne (kontroler go używa), payload ma `min_generations` (typ `AncestorChart`), `ancestors.tsx` buduje opcje `min_generations…available_generations`; asercja w teście Pest. Pest 159/159, Larastan, Pint, `vp check`, Vitest 357/357, build, E2E 9/9 (jeden jednorazowy fail `toHaveText` w teście przełącznika przy zimnym przebiegu 3.2 min, nie powtórzył się w 2 kolejnych przebiegach).

## Triage summary (2026-10-19)

| Decyzja | Ustalenia |
|---|---|
| Fixed | F1 (Fix A, reguła „najbliższe pokolenie”), F2, F3, F4, F5 (wariant: najniższe id), F7, F8, F9 |
| Skipped | F6 |

Kod zmieniony w triage: `app/Actions/Trees/BuildAncestorChart.php`, `app/Support/ParentChildGraph.php`, `app/Http/Controllers/TreeAncestorsController.php`, `resources/js/components/zoom-canvas.tsx`, `resources/js/components/ui/button.tsx`, `resources/js/pages/tree/{show,ancestors}.tsx`, `resources/js/types/tree.ts`, `tests/Feature/AncestorsTest.php`, `tests/e2e/ancestors-view.spec.ts`; plan: sekcja „Addendum (impl-review 2026-10-19)”; zrzuty: `after-ancestors-7gen-1366x768.png` (ponownie), `after-states-loading-switched.png` (nowy).
