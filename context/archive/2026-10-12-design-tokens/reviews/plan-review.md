<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Wdrożenie tokenów z design systemu Root Keeper

- **Plan**: context/changes/design-tokens/plan.md
- **Mode**: Deep
- **Date**: 2026-10-12
- **Verdict**: REVISE → SOUND po triage
- **Findings**: 1 critical, 1 warning, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | WARNING |
| Plan Completeness | FAIL |

## Grounding
13/13 ścieżek ✓; symbole ✓ (`ring-ring/50` w 12 plikach, `text-destructive` w 9 liniach, `chart-` tylko w app.css, `ring-2` w menu w 7 liniach); skan strażnika na 14 nowych `VIEW_FILES` = dokładnie 3 trafienia, które plan naprawia lub zwalnia ✓; brief↔plan ✓; plan↔artefakt `implementation.md` ✓; Progress↔Phase ✓.

## Findings

### F1 — Kryterium 3.1 nigdy nie przejdzie

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 — Success Criteria (3.1)
- **Detail**: `grep -rn "Twoim\|Twojego\|…" resources/js app lang` obejmuje `lang/pl.json`, gdzie „Twojego konta” występuje w dwóch tłumaczeniach Fortify (linie 151, 274), których plan nie zmienia.
- **Fix**: Rozdzielić na `grep … resources/js` (Twoim/Twojego/data nieznana) i `grep … app lang` (Password/Profile updated).
- **Decision**: FIXED (Fix in plan)

### F2 — Przyciski ghost w ciemnym menu podświetlają się jasno

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Phase 2 — #4 Menu
- **Detail**: `SidebarTrigger` i „Zamknij menu” (`app-sidebar.tsx:79,86`) to `Button variant="ghost"` z `hover:bg-accent` (`ui/button.tsx:20`) — token strony; w jasnym motywie jasny kafel na ciemnym menu. Zrzuty nie łapią hover.
- **Fix**: `hover:bg-sidebar-accent hover:text-sidebar-accent-foreground` na obu przyciskach w Phase 2 #4.
- **Decision**: FIXED (Fix in plan)

### F3 — Nowy wpis EXCEPTIONS bez zapisanego odroczenia

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 4 — #1 VIEW_FILES i EXCEPTIONS
- **Detail**: Komentarz nad `EXCEPTIONS` w `view-tokens.test.ts` wymaga odroczonego punktu dla każdego nowego wyjątku; zmiana nie ma research.md, a `reason` dla `style={` w `source-panel.tsx` go nie wskazywał.
- **Fix**: `reason` wskazuje odroczenie; nowa pozycja w „What We're NOT Doing”.
- **Decision**: FIXED (Fix in plan)
