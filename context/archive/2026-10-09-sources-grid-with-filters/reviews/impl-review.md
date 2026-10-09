<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Siatka źródeł z filtrami (S-10)

- **Plan**: context/changes/sources-grid-with-filters/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-10-09
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

## Weryfikacja kryteriów (uruchomione w tym przeglądzie)

- SourcesTest: 142 passed (980 assertions); Larastan: 0 errors; Pint: passed
- Vitest: 257 passed; `types:check`: czysto; `npm run check`: czysto (215 plików)
- `npm run build`: OK
- E2E (nowa specyfikacja + source-panel-edit + source-panel-parish): 4 passed
- Cała suita E2E (4.2) i ręczne 3.6–3.10, 4.3 — nie uruchamiane ponownie; zaznaczone w Progress, commity a0d0505/73a8176 w historii.

## Findings

### F1 — Zaległy debounce cofa wybór z innej kontrolki

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/sources/index.tsx:133-156, 197-208
- **Detail**: `visit()` nie czyści `pendingSearch`. Wpisanie roku/wyszukiwania i w ciągu 300 ms wybór miejsca, archiwum lub rodzaju: wizyta z selecta zatwierdza wpisany tekst, ale timer z efektu (zależności zmienią się dopiero po odpowiedzi serwera) odpala `visit({q, lata, kind: filters.kind, view: filters.view}, true)` z domknięciem sprzed kliknięcia — stare `place`/`archive`/`sort`/`kind` wracają do adresu i Inertia anuluje poprzednią wizytę. `openFromList` i `clearFilters` czyszczą timer ręcznie, `visit` nie. E2E tego nie łapie (każdy krok czeka na adres).
- **Fix**: Na początku `visit()` wyczyść `pendingSearch` (clearTimeout + null); duplikaty w `openFromList` i `clearFilters` stają się zbędne. Dodać test E2E: wpisz rok, od razu wybierz miejsce, oba filtry w adresie.
- **Decision**: FIXED (Fix now: timer czyszczony na początku `visit()`, duplikat w `clearFilters` usunięty)

### F2 — Zbędny eager load i zdublowane zapytanie o archiwa

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Http/Controllers/SourceController.php:132-134, 67/110
- **Detail**: `placeOptions()` robi `->with('locality')`, a `Parish::label()` czyta tylko `name` i `religion` — jedno zbędne zapytanie. Przy otwartym panelu i pełnym przeładowaniu `distinctArchives()` wykonuje się dwa razy (`archiveOptions` i `panelOptions()['archives']`).
- **Fix**: Usuń `->with('locality')`; drugie zapytanie pomijalne w skali „mała”.
- **Decision**: FIXED (Fix now: usunięto `->with('locality')`; zdublowane zapytanie o archiwa zostawione)

### F3 — Dwa przyciski „Wyczyść filtry” naraz przy pustym wyniku

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/sources/sources-filters.tsx:151-157, sources-empty-state.tsx (SourcesFilteredNoResults)
- **Detail**: Plan zakładał akcję w pustym stanie i przycisk w pasku; gdy wynik jest pusty, oba są widoczne, więc `getByRole('button', {name: 'Wyczyść filtry'})` w przyszłych testach E2E będzie niejednoznaczne (strict mode). Obecny E2E tego nie dotyka.
- **Fix**: Zostaw; przyszłe testy zawężają lokator (np. do regionu `search`).
- **Decision**: FIXED (Fix now: przycisk w pasku ukryty, gdy pusty stan ma własne „Wyczyść filtry”)

## Uwagi bez ustalenia

- Plan Adherence: wszystkie pozycje Faz 1–4 = MATCH; jedyny drobny dryf — testy Referer dla `store` nie izolują samego niepasującego archiwum ani mismatchu miejscowości-nagrobka (logika poprawna, pokrycie mniej rozdrobnione niż w planie).
- EXTRA nieszkodliwe: eksport `parseYearInput`, komponent `SourcesFilteredNoResults`, `rounded-xl`→`rounded-lg` w kartach.
- Brak wycieku między drzewami (zapytanie z `orWhere` zgrupowane), brak open redirect, N+1, XSS; partial reload `only:['filters']` nie dotyka `sources`.
- `FILTER_VALIDATE_INT` po stronie serwera akceptuje `+1850`/spacje, klient `parseYearInput` nie — rozbieżność tylko dla ręcznie wpisanego adresu.
