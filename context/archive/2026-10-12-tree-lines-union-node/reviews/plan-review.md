<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Linie drzewa i węzeł związku

- **Plan**: context/changes/tree-lines-union-node/plan.md
- **Mode**: Deep (inline)
- **Date**: 2026-10-12
- **Verdict**: REVISE → SOUND po poprawkach (wszystkie ustalenia naprawione w planie)
- **Findings**: 0 critical, 6 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
10/10 paths ✓, 8/8 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — Fixtures TS `FamilySheet` złamią types:check
- **Severity**: ⚠️ WARNING · **Impact**: 🏃 LOW · **Dimension**: Plan Completeness · **Location**: Faza 1 §2
- **Detail**: `add-person-target.test.ts:75` i `centre-hints.test.ts:44` budują pełny `FamilySheet`.
- **Fix**: oba pliki z nowymi polami `null`.
- **Decision**: FIXED (Fix in plan)

### F2 — Grep w 2.5 nigdy nie przejdzie
- **Severity**: ⚠️ WARNING · **Impact**: 🏃 LOW · **Dimension**: Plan Completeness · **Location**: Faza 2, 2.5
- **Detail**: trafia w shadcn `ui/table.tsx`, `ui/tooltip.tsx`, `ui/sidebar.tsx`.
- **Fix**: zawęzić do `resources/js/components/tree`.
- **Decision**: FIXED (Fix in plan)

### F3 — `gap-y-1` arkusza przerywa linie
- **Severity**: ⚠️ WARNING · **Impact**: 🔎 MEDIUM · **Dimension**: End-State Alignment · **Location**: Faza 3 §1–2
- **Detail**: 4 px szczeliny między wierszami siatki (`family-sheet.tsx:351`) przeczą 3.13.
- **Fix**: usunąć `gap-y-1`, oddech w wysokości łączników.
- **Decision**: FIXED (Fix in plan)

### F4 — Ramiona na 25%/75% mijają środek kart o 3 px
- **Severity**: ⚠️ WARNING · **Impact**: 🔎 MEDIUM · **Dimension**: End-State Alignment · **Location**: Faza 3 §1
- **Detail**: środek karty 6rem / 12,375rem vs 25% = 6,19rem / 12,56rem.
- **Fix A ⭐**: ramiona z `COLUMN_REM` przez `style`.
- **Decision**: FIXED (Fix A)

### F5 — Przerywany pień przy 0 dzieci kończy się na tekście
- **Severity**: ⚠️ WARNING · **Impact**: 🔎 MEDIUM · **Dimension**: End-State Alignment / Blind Spots · **Location**: Faza 3 §2, `NoChildren`
- **Detail**: napis „Brak dzieci…” leży na osi między pniem a slotem.
- **Fix A ⭐**: napis poza oś (`sr-only` / obok slotu), pień do slotu; sprawdzić makietę.
- **Decision**: FIXED (Fix A)

### F6 — `marry()` w seederze nie przyjmie `is_approximate`
- **Severity**: ⚠️ WARNING · **Impact**: 🏃 LOW · **Dimension**: Plan Completeness · **Location**: Faza 3 §5
- **Fix**: rozszerzyć phpdoc `marry()`.
- **Decision**: FIXED (Fix in plan)

### F7 — Wysokości geometrii bez rozstrzygnięcia
- **Severity**: 🔍 OBSERVATION · **Impact**: 🏃 LOW · **Dimension**: Plan Completeness · **Location**: Faza 3 §1, §3
- **Fix**: `h-11`, `h-4`, `pt-4`, przycisk „Połącz” na złączu.
- **Decision**: FIXED (Fix in plan)

### F8 — Border 1,5 px przy DPR 1 zaokrąglany do 1 px
- **Severity**: 🔍 OBSERVATION · **Impact**: 🏃 LOW · **Dimension**: Blind Spots · **Location**: Faza 3 §5
- **Fix**: `deviceScaleFactor: 2` w `shots.mjs`.
- **Decision**: FIXED (Fix in plan)
