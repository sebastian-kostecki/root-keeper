<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Widok /persons zgodny z systemem projektowym

- **Plan**: context/changes/persons-index-ui/plan.md
- **Mode**: Deep (inline)
- **Date**: 2026-10-11
- **Verdict**: REVISE → SOUND po poprawkach F1–F4
- **Findings**: 1 critical, 1 warning, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding
20/20 istniejących ścieżek ✓, symbole ✓, callbacki Inertia v3 ✓, Progress↔Fazy ✓, brief↔plan ✓

## Findings

### F1 — Zmiana redirectu `store` psuje helper E2E `createPerson`

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW
- **Dimension**: Plan Completeness
- **Location**: Faza 3 pkt 1, kryterium 3.5, Faza 5 5.4
- **Detail**: `tests/e2e/support/api.ts:143-154` wymusza ścieżkę `/^\/tree\/\d+$/` po POST `/persons`; po zmianie fallback daje `/persons?person=<id>`. Helper używany w 18 specach.
- **Fix**: nowy pkt 3 Fazy 3 (edycja `api.ts`, wzorzec `/^\/persons$/`).
- **Decision**: FIXED (Fix in plan)

### F2 — Wyścig: debounce wyszukiwania kontra wizyta otwierająca panel

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM
- **Dimension**: Blind Spots
- **Location**: Faza 3 pkt 5 i 6
- **Detail**: Brak odpowiednika `openFromList` (`sources/index.tsx:236-262`); timer wyszukiwania może anulować wizytę otwarcia panelu.
- **Fix**: `onClick` na linkach „Dodaj osobę” scalający oczekujące wyszukiwanie z wizytą otwarcia; dodane kryterium ręczne w 3.9.
- **Decision**: FIXED (Fix in plan)

### F3 — Warunek startu Fazy 3 jest nieaktualny

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW
- **Dimension**: Plan Completeness
- **Location**: Current State, Critical Implementation Details, Faza 3, plan-brief
- **Detail**: p3 drzewa zacommitowane (`af8606e`); realny konflikt to niezacommitowane `tree/show.tsx` i `family-sheet.tsx`.
- **Fix**: warunek startu przepisany; wzmianka o dwóch wywołaniach `AddPersonPanel`.
- **Decision**: FIXED (Fix in plan)

### F4 — Wyjątek w teście skanu nie pasuje do własnego wzorca

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW
- **Dimension**: Plan Completeness
- **Location**: Faza 5 pkt 2
- **Detail**: `-\[\d+px\]` nie łapie `align-[-2px]`.
- **Fix**: wzorzec `-\[-?\d+px\]` z jawnym wyjątkiem.
- **Decision**: FIXED (Fix in plan)
