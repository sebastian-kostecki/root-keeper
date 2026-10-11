<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Zdjęcie osoby na karcie drzewa (S-16)

- **Plan**: context/changes/person-photo-on-card/plan.md
- **Mode**: Deep
- **Date**: 2026-10-11
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical, 2 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
35/35 paths ✓, 8/8 symbols ✓, brief↔plan ✓, Progress↔Faza ✓ (8/11/10/4)

## Findings

### F1 — Karta drzewa 12rem ma mniej miejsca na tekst niż dziś

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: End-State Alignment
- **Location**: Faza 3 §2, §4; brief „Wąskie karty”
- **Detail**: Dziś karta dziadka ma ~6,5rem (104 px) tekstu (10rem − 2×1,75rem). Po zmianie: 12rem − 4,5rem paska − ~0,75rem odstępu − ~2rem rezerwy pod oko ≈ 4,75rem (76 px). „1890 – 1965” w `text-sm` (~80 px) się złamie, a imiona z `[overflow-wrap:anywhere]` łamią się w środku słowa. Plan twierdził, że „imię i daty mieszczą się”.
- **Fix A ⭐ Recommended**: rezerwa pod oko tylko przy pierwszej linii (float-right), dane wyrównane do góry, cel ≥ 6rem tekstu
  - Strength: zostaje 12rem, bez dalszego zmniejszania drzewa na 1366 px.
  - Tradeoff: dane nie są wyśrodkowane w pionie.
  - Confidence: MED — arytmetyka pewna; float trzeba obejrzeć.
  - Blind spot: karta z samym imieniem nieobejrzana.
- **Fix B**: kolumna ≈14rem przy obecnym układzie
  - Strength: prosty CSS.
  - Tradeoff: siatka dziadków ~1880 px; na 1366 px z panelem częściej widok kotwicy.
  - Confidence: HIGH — wynika z GRID_REM.
  - Blind spot: szerokość panelu przy 1366 px niezmierzona.
- **Decision**: FIXED (Fix A)

### F2 — Pominięte miejsca, które łamią types:check i Larastan

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §4, Faza 3 §4
- **Detail**: `tsconfig.json` obejmuje `*.test.ts`. Literały `SheetPerson`/`Person` budują `centre-hints.test.ts`, `ancestor-layout.test.ts` i `persons-index.test.ts`. Docblocki `@return` w `SheetPersonResource` i `PersonResource` wymagają nowego pola dla Larastana. Komentarz `fit-view.ts:8-11` liczy 10rem na kartę.
- **Fix**: dopisać pliki do Fazy 1 §4, a `fit-view.ts` (tylko komentarz) do Fazy 3 §4.
- **Decision**: FIXED

### F3 — Mylące uzasadnienie strażnika niezapisanych zmian

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Critical Implementation Details, Faza 2 §3
- **Detail**: Strażnik przepuszcza każdą wizytę inną niż GET (`section-editor.tsx:134`). `use-section-save` nie ma obejścia: to `router.put` z błędami sekcji. Sąsiednie akcje nagłówka używają `requestExit`, który zamyka edytowaną sekcję, co złamałoby kryterium 2.11.
- **Fix**: bezpośredni `router.post`/`router.delete` z `preserveScroll`/`preserveState`, `back()` na serwerze, bez `useSectionSave` i `requestExit`.
- **Decision**: FIXED

### F4 — Fallback onError nie resetuje się po zmianie zdjęcia

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §2, Faza 3 §2
- **Detail**: Stan błędu wczytania przetrwa podmianę zdjęcia (`preserveState`). Po jednym błędzie nowe zdjęcie nie pokaże się do przeładowania.
- **Fix**: stan błędu resetuje się przy zmianie URL (np. `key={photoUrl}`).
- **Decision**: FIXED
