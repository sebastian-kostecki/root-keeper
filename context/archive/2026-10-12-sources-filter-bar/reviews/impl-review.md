<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Pasek filtrów z pigułkami i okienkami na /sources

- **Plan**: context/changes/sources-filter-bar/plan.md
- **Scope**: Full plan (Phases 1–3 code reviewed; Phase 2 Progress not fully ticked — 2.11 open)
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-10-12
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 4 warnings, 6 observations

Note: the reviewers were read-only and did not re-run gates. Gates were run per phase when each phase landed (Vitest 329/329, tsc, check, build, Playwright targeted and full, break-check).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | WARNING |

## Findings

### F1 — Home/End przechwytywane w polu „Szukaj”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality (a11y)
- **Location**: resources/js/components/sources/sources-filters.tsx:54-86, :168
- **Detail**: `moveOptionFocus` jest na całym wrapperze i wywołuje `preventDefault` dla Home/End. Wpisując w „Szukaj miejsca” / „Szukaj archiwum” Home/End przenosi fokus na opcję zamiast kursora w polu. Strzałki z pola działają poprawnie.
- **Fix**: Dla Home/End wyjdź wcześniej, gdy `event.target` nie jest `[role="option"]`.

### F2 — `aria-haspopup="listbox"` przy popupie typu dialog; zduplikowane etykiety

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality (a11y)
- **Location**: resources/js/components/sources/sources-filters.tsx:279, :432, :585 (`ariaHasPopup`); :443 i :182 (etykiety)
- **Detail**: Przycisk Miejsce/Archiwum deklaruje `aria-haspopup="listbox"`, ale Radix renderuje `PopoverContent` jako `role="dialog"` (domyślne `dialog` jest nadpisane przez przekazany prop). Popup jest więc dialogiem zawierającym listbox. Etykieta „Miejsce”/„Archiwum” jest na dialogu i na listboxie.
- **Fix**: Usunąć prop `ariaHasPopup` (zostaje domyślne `dialog`) i jedną z dwóch zduplikowanych etykiet.

### F3 — Dwa regiony `role="status"` podczas wizyty

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality (a11y)
- **Location**: resources/js/components/sources/sources-filters.tsx:623; resources/js/components/ui/spinner.tsx:8; resources/js/components/search-field.tsx:30-32
- **Detail**: Gdy `isVisiting`, `SearchField busy` renderuje Spinner z `role="status"`, a licznik wyników też jest `role="status" aria-live="polite"`. Czytnik widzi dwa regiony naraz.
- **Fix A ⭐ Recommended**: Spinner w `SearchField` bez `role="status"` (ukryty przed czytnikiem, np. `aria-hidden`). · Strength: jeden region ogłasza wynik, zgodnie z planem. · Tradeoff: zmiana we wspólnym komponencie, dotyka także innych stron. · Confidence: HIGH — spinner jest czysto wizualny. · Blind spot: nie sprawdzono innych miejsc, gdzie `SearchField busy` jest używany.
- **Fix B**: Zostawić spinner i zmienić tylko licznik wyników na nie-live (`aria-live` usunięte). · Strength: zmiana tylko w tym pliku. · Tradeoff: plan wymaga, by status ogłaszał się przy pisaniu. · Confidence: MEDIUM. · Blind spot: nie zweryfikowano zachowania czytników.

### F4 — ✕ i „Wyczyść lata” nie zatwierdzają od razu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/sources/sources-filters.tsx:293-308 (✕ Lata); resources/js/pages/sources/index.tsx:467-470 (`onClear` Lata)
- **Detail**: ✕ na pigułce Lata i „Wyczyść lata” tylko zmieniają stan lokalny i czekają 300 ms na debounce, a pigułka przez ten czas pokazuje starą wartość. ✕ miejsca i archiwum wizytują od razu. Plan wymaga, by ✕ Lata czyścił oba pola „tak samo jak «Wyczyść lata»”, a zachowanie miało być spójne.
- **Fix**: Czyszczenie lat wywołuje `visit({ year_from: null, year_to: null })` tak jak `clearFilters`, a stan pola czyszczony równocześnie.

### F5 — Brak `onOpenAutoFocus` (plan wymaga fokusu na wybranej pozycji lub wyszukiwarce)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/components/sources/sources-filters.tsx:442, :473, :595 (PopoverContent), :89-107 (FilterList)
- **Detail**: Plan („Critical Implementation Details”): po otwarciu fokus na wybranej pozycji albo na wyszukiwarce. Nic tego nie ustawia, więc Radix daje fokus pierwszemu elementowi (przy braku wyszukiwarki: „Wszystkie …”).
- **Fix**: Przekazać `onOpenAutoFocus` do `PopoverContent`, który fokusuje `[aria-selected="true"]` albo pole wyszukiwania.

### F6 — Liczby przy pozycjach liczone z wpisanego tekstu, status z zatwierdzonego

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/sources/index.tsx ~:352-374 (`searchFilters`, `placeCounts`, `archiveCounts`)
- **Detail**: Liczby przy opcjach używają wpisanego `trimmedSearch` i wpisanych lat, a status i pigułki zatwierdzonych filtrów. Mogą się chwilowo rozjechać do końca debounce. Komentarz w kodzie mówi to celowo, więc to nie błąd.
- **Fix**: Dopisać to do komentarza jako zamierzone albo liczyć wszystko z zatwierdzonych wartości.

### F7 — Brak zrzutu ciemnego „błąd zakresu lat”

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/sources-filter-bar/screenshots/
- **Detail**: Plan (Phase 2 §4) wymienia zrzut ciemny z otwartym okienkiem Lata i błędem zakresu. Jest 9 zrzutów, tego brakuje. Pozycja 2.11 pozostaje otwarta.
- **Fix**: Zrobić zrzut ciemny po naprawie F3–F5 albo odnotować lukę w Progress.

### F8 — Przełącznik widoku: `sm:ml-auto` zamiast `ml-auto`

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: resources/js/pages/sources/index.tsx (rząd 1)
- **Detail**: Plan mówi `ml-auto`; kod ma `sm:ml-auto`. Wygląda na celowe dla układu mobilnego.
- **Fix**: Potwierdzić jako zamierzone albo odnotować odstępstwo w planie.

### F9 — Niezescapowany regex w E2E i zbędne `export`

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/e2e/sources-grid-with-filters.spec.ts (`new RegExp('^' + localityName)`); resources/js/lib/sources-index.ts:34 (`SourcesCounts`), :38 (`SourcesResult`)
- **Detail**: Nazwa lokalizacji w regexie nie jest escapowana i jest zakotwiczona na początku. Przy unikalnym sufiksie to bezpieczne. Dwa typy są eksportowane, a używane tylko w module.
- **Fix**: Użyć `{ name: localityName }` bez regexu albo zescapować; usunąć `export` z obu typów.

### F10 — Brak testu dopełniacza „1 z 2 źródeł”

- **Severity**: 👁️ OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: resources/js/lib/sources-index.test.ts:431-443
- **Detail**: Przykład z planu „1 z 2 źródeł” nie ma asercji. Implementacja (sources-index.ts:546) to obsługuje.
- **Fix**: Dodać jedną asercję.

## Checks that passed

- Plan: wszystkie kontrakty Fazy 1 zgodne (`hiddenFilterCount`, `filterOptionCounts`, `formatYearsFilter`, `halfCenturyPresets`, `resultCountText`); Faza 2 zgodna poza F2/F4/F5/F8; Faza 3 bez osłabionych asercji o kartach i adresie.
- Zakres: brak zmian w backendzie, routach, parametrach URL, `filterSources`, `resolveFilters`; brak cmdk; `package.json` dodaje tylko `@radix-ui/react-popover`.
- Bezpieczeństwo: brak `innerHTML`, `style=`, hardcoded `/sources`; wartości miejsc i archiwów tylko z opcji serwera.
- Tokeny: brak klas palety, hex, rgb/oklch i arbitralnych pikseli w `components/sources` i `pages/sources`.
- E2E: tylko `getByRole` / `getByLabel` / `getByText`; brak `waitForTimeout`.

## Findings

Sortowane wg wagi: WARNING (F1–F5), potem OBSERVATION (F6–F10).

## Triage

| ID | Decision | Outcome |
|----|----------|---------|
| F1 | FIXED | Home/End obsługiwane tylko na opcjach; w polu „Szukaj” zostają w kursorze (`sources-filters.tsx`, `moveOptionFocus`). |
| F2 | FIXED | Usunięty `aria-haspopup` (popup to dialog); etykieta „Miejsce”/„Archiwum” zostaje na listbox, dialog bez zdublowanej nazwy. Lata zachowuje `aria-label="Lata"` (testy). |
| F3 | FIXED (wariant A) | Spinner w `SearchField` ma `aria-hidden="true"`; jedyny region `role="status"` to licznik wyników. Wspólny komponent — sprawdzone pełnym Playwright (36/36). |
| F4 | FIXED | ✕ Lata i „Wyczyść lata” wołają `onYearsClear` → `visit({ year_from: null, year_to: null })` od razu, tak jak miejsce i archiwum. |
| F5 | FIXED | `focusListOnOpen` na popoverach Miejsce i Archiwum: fokus na polu wyszukiwania, a gdy go nie ma — na wybranej pozycji. |
| F6 | DOCUMENTED | Komentarz w `pages/sources/index.tsx` mówi, że liczby mogą chwilowo różnić się od statusu do końca debounce — zamierzone. |
| F7 | FIXED | Dodany zrzut `desktop-dark-years-error.png`. Zrzuty odświeżone po poprawkach. Pozycja 2.11 nadal otwarta: brak porównania z makietą. |
| F8 | CONFIRMED | `sm:ml-auto` zamierzone dla układu mobilnego; plan nie zmieniany. |
| F9 | FIXED (odstępstwo) | `export` usunięty z `SourcesCounts` i `SourcesResult`. Regex w E2E zostaje zakotwiczony, ale nazwa jest escapowana (`escapeRegExp`). Zamiast `{ name: localityName }`: etykieta parafii zawiera nazwę miejscowości, więc zwykły substring dałby dwa dopasowania. |
| F10 | FIXED | Dodana asercja `resultCountText(1, 2, true)` → „1 z 2 źródeł”. |

Gates po poprawkach: test:js 329/329, types:check, check, build — przechodzą. Playwright pełny: 36/36.
