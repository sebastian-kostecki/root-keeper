<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Strona „Miejsca” (/places)

- **Plan**: context/changes/places-page/plan.md
- **Mode**: Deep
- **Date**: 2026-10-12
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | PASS |
| Plan Completeness | WARNING |

## Grounding
16/16 paths ✓, 7/7 symbols ✓, brief↔plan ✓, Progress↔Fazy ✓. Sub-agent potwierdził: `Locality` ⊇ `LocalityOption`, `router.reload` (preserveState wymuszone, async) nie resetuje formularza parafii ani wyboru w pickerze, dwa `useSidePanelNavigation` nie kolidują, `AppShellTest` sprawdza tylko klucze `navCounts`, `?person=` daje 404 na `places.index` (shared `personPreview`).

## Findings

### F1 — Nie wiadomo, kiedy przełącznik jest widoczny

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §3, Faza 3 §2
- **Detail**: Na `/sources` przełącznik siedzi w wierszu `role="search"`, który znika przy pustym spisie. Skopiowany wzór ukryłby przełącznik w pustym drzewie, a „Dodaj parafię” byłoby nieosiągalne (kryterium 3.5).
- **Fix**: Przełącznik renderowany zawsze, pod nagłówkiem, poza warunkiem wyszukiwarki.
- **Decision**: FIXED

### F2 — Zapis, usunięcie i scalenie mogą zmienić zakładkę

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 §2, „What We're NOT Doing”
- **Detail**: Panel może stać nad zakładką drugiego typu. Przekierowania po store, destroy i merge wybierają zakładkę według typu rekordu, a `q` ginie.
- **Fix**: Świadome przyjęcie w „What We're NOT Doing”.
- **Decision**: FIXED

### F3 — Tekst błędu skopiowany z /sources mówi o filtrach

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §3
- **Detail**: `VISIT_ERROR` na `/sources` to „Nie udało się zastosować filtrów.”, a `/places` nie ma filtrów. Komponent nie był nazwany.
- **Fix**: `InputError role="alert"` + `Button` „Spróbuj ponownie”, tekst „Nie udało się wczytać listy.”
- **Decision**: FIXED

### F4 — Testy update przechodzą, ale sprawdzają stary adres

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 §5
- **Detail**: `LocalitiesTest.php:61-63` i `ParishesTest.php:81-83` używają `from(route('*.index'))` przy `back()`. Spec e2e `:48` wymaga, by `locality` było ostatnim parametrem po scaleniu.
- **Fix**: `from(route('places.index', …))` w testach update; po scaleniu miejscowości tylko klucz panelu.
- **Decision**: FIXED
