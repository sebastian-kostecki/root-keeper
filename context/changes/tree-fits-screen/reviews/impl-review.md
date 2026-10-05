<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: S-05 Drzewo wypełnia ekran

- **Plan**: context/changes/tree-fits-screen/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-05
- **Verdict**: REJECTED
- **Findings**: 1 critical, 4 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | FAIL |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

Weryfikacja automatyczna (2026-10-05): `test:js` 187/187, `check` czysto, `types:check` czysto, Pest `TreeTest` + `WriteTargetsTest` 56/56, `npx playwright test` 9/9 przy 1366×768 (webServer robi `npm run build`). Wszystko zielone.

## Findings

### F1 — Ręczne przybliżanie i „Dopasuj” usunięte bez zapisu w planie i PRD

- **Severity**: ❌ CRITICAL
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/zoom-canvas.tsx:220-229
- **Detail**: `wheel` i `pinch` są wyłączone, nie ma paska `role="toolbar"` z „Powiększ/Pomniejsz/Dopasuj” ani trybu dopasowania „całość”. Plan (`plan.md:23,94-100`) i PRD FR-013 (must-have: „z przybliżaniem i oddalaniem”, `prd.md:135`) tego wymagają. Decyzja „bez ręcznego zoomu” jest zapisana tylko w komunikacie commita 480a062 i w `verdict.md`. Treść planu, `plan-brief.md` i PRD jej nie znają. Pozycje Progress 1.8 („Kółko przybliża”), 1.9 („Dopasuj pokazuje całe drzewo… po ręcznym przybliżeniu”), 3.7 („Dopasuj pokazuje całość”) i 3.8 („Pasek kontrolek nie zasłania kart”) są odhaczone `[x]`, choć z dostarczonym kodem nie mogą przejść. Skutek dla użytkownika: przy starcie w kotwicy 75% (10 dzieci na 1366 px, zwężenie obok panelu z S-06) w aplikacji nie da się zobaczyć całego drzewa. Zostaje przeciąganie albo Tab. S-15 ma używać „tego samego mechanizmu dopasowania i przybliżania” (`prd.md:152`).
- **Fix A ⭐ Recommended**: Zapisz decyzję. Zaktualizuj plan (Desired End State, kontrakt ZoomCanvas, lista zrzutów fazy 3) i FR-013 w PRD, a pozycje 1.8, 1.9, 3.7 i 3.8 odznacz albo opisz jako „usunięte decyzją z <data>”.
  - Strength: Commit p1 i werdykt pokazują, że brak zoomu był świadomym wyborem w trakcie fazy 1, zaakceptowanym w 3.9. Źródło prawdy zgadza się wtedy z kodem, zanim S-06/S-15 oprą się na planie.
  - Tradeoff: FR-013 (must-have) zostaje zawężony. Przy kotwicy nadal nie ma sposobu, by zobaczyć całość.
  - Confidence: MED — nie wiem, czy decyzję podjął autor, czy agent implementujący.
  - Blind spot: S-06 zwęża drzewo panelem, więc kotwica 75% będzie częsta. Bez „Dopasuj” może to wrócić jako problem.
- **Fix B**: Przywróć zakres planu: kółko, szczypanie i pasek „Powiększ/Pomniejsz/Dopasuj” poza transformowaną treścią, z trybem „całość” ponownie stosowanym po zmianie rozmiaru.
  - Strength: Spełnia FR-013 i 1.8/1.9/3.7/3.8 dosłownie. Daje S-06/S-15 gotowy mechanizm. `minScale 0.2`/`maxScale 3` już są ustawione pod „Dopasuj”.
  - Tradeoff: Ok. 60–100 linii w `zoom-canvas.tsx`, nowe zrzuty (stan po „Dopasuj”) i ręczna weryfikacja 1.8/1.9. Cofa decyzję, która mogła mieć powód.
  - Confidence: HIGH — wzorzec przycisków jest w `source-viewer.tsx:24-47`, a API biblioteki zbadane w research.
  - Blind spot: Nieznany powód usunięcia (np. konflikt kółka z przewijaniem strony albo panelem).
- **Decision**: FIXED (Fix A) — decyzja zapisana w plan.md (Addendum, adnotacje 1.8/1.9/3.7/3.8) i PRD FR-013; follow-up dla S-15/FR-018 w follow-ups/review-fixes.md

### F2 — Tłumienie kliknięcia łapie zdarzenia z dialogów i może zablokować się na stałe

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/zoom-canvas.tsx:125-157
- **Detail**: Wszystkie dialogi slotów (`AddChildDialog`, `ParentSlotDialog`, `AddSpouseDialog`, `MarryCoupleDialog`) i menu związku renderują się wewnątrz `<ZoomCanvas>` (`family-sheet.tsx:279-436`). Zdarzenia React z ich portali przechodzą przez handlery capture. `onFocus` filtruje je przez `wrapper.contains(target)`, ale handlery pointer i click tego nie robią. Gdy przesunięcie kończy się poza obszarem (puszczenie nad sidebarem albo poza oknem), `down`/`dragged` zostają `true`, bo `pointerup` nie trafia do kontenera i nie pada `click`. Następne kliknięcie z klawiatury (Enter/spacja na karcie albo slocie, Enter w polu dialogu, który wysyła syntetyczny `click` na „Zapisz”) jest wtedy połykane przez `preventDefault` + `stopPropagation`.
- **Fix**: W handlerach pointer/click pomijaj zdarzenia, których cel nie leży w `wrapperComponent`, i tłum tylko kliknięcia wskaźnika (`event.detail > 0`).
- **Decision**: FIXED — isInsideCanvas() w handlerach pointer/click + tłumienie tylko gdy event.detail > 0

### F3 — Reguła położenia slotu „Dodaj dziecko” niezgodna z planem i z werdyktem

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/tree/family-sheet.tsx:151-158
- **Detail**: Plan (`plan.md:190`) mówi: slot zawsze na prawo od ostatniego dziecka, w szerokości karty dziecka. Faza 3 zmieniła to na slot o szerokości 1 kolumny, obok rzędu tylko w szerokości dziadków + 1 rem. Werdykt (`verdict.md:9-10,38`) i commit 3afa239 opisują wynik jako „obok do 5 dzieci, pod od 6”. Formuła daje co innego: obok przy 1–3, **pod przy 4** (rząd 86 rem > 85,25 rem), obok przy 5–**6** (nadmiar 0,375 rem), pod od 7. Slot skacze więc między pozycjami przy kolejnych liczbach dzieci. Typowa rodzina z 4 dziećmi ma slot pod ostatnim dzieckiem, a zestaw zrzutów (0/3/5/8/10) nie obejmuje 4, 6 ani 7. Przy 0 dzieci slot nadal ma 2 kolumny (`:167`). Warunek `rowRem <= GRID_REM` jest nadmiarowy wobec warunku tolerancji.
- **Fix A ⭐ Recommended**: Uprość regułę do jednego progu liczby dzieci, z którego zachowanie jest przewidywalne (np. „obok przy ≤ 3, pod od 4”, albo próg wyliczony raz), popraw komentarz, werdykt i dopisz zrzuty dla 4 i 6 dzieci.
  - Strength: Slot ma stałe miejsce w rodzinach o podobnej wielkości, a dokumentacja zgadza się z kodem.
  - Tradeoff: Przy 5–6 dzieciach slot przejdzie pod rząd, choć zmieściłby się obok.
  - Confidence: MED — to wybór UX, który powinien potwierdzić autor.
  - Blind spot: Nie sprawdzono skali startowej przy 4 i 6 dzieciach na 1366 px.
- **Fix B**: Zostaw formułę, popraw tylko opis w werdykcie, komentarz i plan (addendum) oraz dodaj zrzuty dla 4 i 6 dzieci.
  - Strength: Bez zmian w kodzie. Formuła poprawnie realizuje intencję „nie poszerzaj arkusza”.
  - Tradeoff: Niespójne położenie slotu (4 pod, 5–6 obok) zostaje.
  - Confidence: HIGH — wyliczenia sprawdzone ręcznie na stałych z `family-sheet.tsx:21-28,131-158`.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix A) — próg SLOT_BESIDE_MAX_CHILDREN = 3 (obok przy 1–3, pod od 4); seeder + zrzuty 4/6 dzieci; verdict.md poprawiony

### F4 — Karty pary węższe niż karty rodziców i dzieci

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/tree/family-sheet.tsx:368
- **Detail**: Strona pary zachowała `max-w-72` (18 rem), a kolumny urosły do 10 rem. Karty rodziców (`col-span-2`) i dzieci przy ≤ 4 mają 20,75 rem, więc osoba w centrum ma najmniejszą kartę w arkuszu. Widać to na `screenshots/after/couple-3-children-1366.png`. Przed zmianą karta pary była szersza od kart rodziców. Plan (`plan.md:177-179`) wymaga „proporcji jak dziś”.
- **Fix**: Zastąp `max-w-72` szerokością 2 kolumn wyliczoną ze stałych (`2 * COLUMN_REM + COLUMN_GAP_REM` rem).
- **Decision**: FIXED — COUPLE_CARD_REM (2 kolumny + odstęp) zamiast max-w-72; zrzuty odświeżone, skale bez zmian

### F5 — Stałe geometrii powielone jako literały Tailwind

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/tree/family-sheet.tsx:21-28, 195, 220, 226, 289
- **Detail**: Plan (`plan.md:179`): „Szerokość kolumny jest jedną stałą w komponencie”. `COLUMN_REM = 10` i `COLUMN_GAP_REM = 0.75` sterują obliczeniami belki i slotu. Siatka ma jednak na sztywno `grid-cols-[repeat(8,10rem)] gap-x-3`, a komórki `px-[0.375rem]`. Zmiana stałej po jednej stronie bez ostrzeżenia rozjedzie belkę i decyzję o slocie z siatką. Szerokość rzędu i `slotStandsBeside` są też liczone dwa razy (`FamilySheet` i `ChildrenRow`).
- **Fix**: Ustaw kolumny, odstęp i padding komórek przez `style` budowany ze stałych, a szerokość rzędu i położenie slotu licz raz i przekazuj w dół.
- **Decision**: FIXED — GRID_STYLE i CELL_PADDING_STYLE ze stałych zamiast literałów Tailwind; childrenRowLayout() liczony raz i przekazany do ChildrenRow; skale i wymiary zrzutów identyczne

### F6 — `fillHeight` nigdy nie jest ustawiane na `false`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/tree/show.tsx:45
- **Detail**: `...(sheet ? { fillHeight: true } : {})` pomija klucz zamiast ustawić `false`. Jeśli magazyn layout props scala wartości przy wizycie z `preserveState` na tę samą stronę i `sheet === null`, layout zostaje w `h-svh overflow-y-clip` i strona przestaje się przewijać. Nie zweryfikowano w przeglądarce.
- **Fix**: `fillHeight: sheet !== null`.
- **Decision**: FIXED — fillHeight: sheet !== null

### F7 — Krawędzie odporności `ZoomCanvas`

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/zoom-canvas.tsx:65-96, 175-176, 229
- **Detail**: (a) Treść jest `visibility: hidden` do pierwszego udanego dopasowania. Przy zerowej wysokości obszaru (niski viewport, `overflow-y-clip`) `chooseFitView` zwraca `null` i drzewo nigdy się nie pokaże. (b) `onPanning={markMoved}` oznacza widok jako ruszony przy drgnięciu o 1 px w trakcie kliknięcia, więc ponowne dopasowanie po zmianie rozmiaru przestaje działać. (c) Natywny scroll wrappera jest zerowany tylko w `onFocus`. Ctrl+F na nazwie poza kadrem przewija wrapper i rozjeżdża współrzędne.
- **Fix**: Ustaw `min-h-*` obszaru (albo pokaż treść po fallbacku), oznaczaj ruch dopiero po `DRAG_THRESHOLD` (flaga `dragged`) i zeruj scroll także w listenerze `scroll` wrappera.
- **Decision**: FIXED — min-h-64 obszaru; onPanning oznacza ruch tylko po DRAG_THRESHOLD; listener scroll zamienia natywny scroll wrappera na przesunięcie (sprawdzone w przeglądarce: refit po drgnięciu 2 px, scroll → 0,0)

### F8 — Ogólny `ZoomCanvas` zaszyty na stałe pod drzewo

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architecture
- **Location**: resources/js/components/zoom-canvas.tsx:5, 61-62, 223-224
- **Detail**: Komponent ma być wielokrotnego użytku (S-15), ale importuje `TREE_FIT_FLOOR`/`TREE_FIT_MAX` z modułu drzewa. `minScale={0.2}`/`maxScale={3}` istniały tylko dla „Dopasuj” i przy obecnym kodzie nic nie robią (skala zawsze 0,75–1,5). Zależy od decyzji w F1.
- **Fix**: Przekaż `floor`/`max` jako propsy (z domyślnymi wartościami drzewa), a `minScale`/`maxScale` usuń albo opisz, jeśli F1 skończy się Fix A.
- **Decision**: FIXED — opcjonalne propsy floor/max (domyślnie TREE_FIT_*), minScale/maxScale = floor/max; skale zrzutów bez zmian

### F9 — Luki w zestawie zrzutów i skrypcie

- **Severity**: 💬 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/tree-fits-screen/screenshots/shots.mjs:10, 59, 101-109
- **Detail**: Brakuje planowanego zrzutu „po Dopasuj” (konsekwencja F1). „couple-0-children” to osoba bez związku (Kacper), a nie para bez dzieci. Brak przypadków 4/6/7 dzieci (F3). Skrypt importuje Playwright z bezwzględnej ścieżki cache npx, używa `waitForTimeout(250)` i ma mylące pozostałości komentarzy. Jako jednorazowy skrypt jest to dopuszczalne, ale nie da się go uruchomić na innej maszynie.
- **Fix**: Przy ponownym uruchomieniu (F1/F3) dodaj parę bez dzieci do seedera, zrzuty 4/6 dzieci i popraw komentarze. Ścieżkę importu zostaw jak we wzorcu archiwalnym.
- **Decision**: FIXED — para bez dzieci (Leszek Zerowy) na końcu seedera, zrzut couple-0-children przepięty, mylące komentarze usunięte; zrzut „po Dopasuj” pominięty zgodnie z F1
