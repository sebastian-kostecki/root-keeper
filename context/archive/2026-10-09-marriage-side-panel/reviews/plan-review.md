<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Panel małżeństwa (S-12)

- **Plan**: context/changes/marriage-side-panel/plan.md
- **Mode**: Deep (inline, bez sub-agenta)
- **Date**: 2026-10-09
- **Verdict**: REVISE → SOUND po poprawkach z triage
- **Findings**: 0 critical, 4 warnings, 0 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | WARNING |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
15/15 paths ✓, 11/11 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — Celowy błąd nr 1 w E2E nie zaczerwieni się w opisanym miejscu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM
- **Dimension**: Blind Spots
- **Location**: Faza 4, pkt 2
- **Detail**: Klik wspólnego dziecka obsługuje `PersonPreviewLink` przez `requestExit`, bez udziału nasłuchu `router.on('before')`; „Zapisz” to PUT, który nasłuch przepuszcza. Spec przechodził na zielono przy zepsutym uogólnieniu strażnika.
- **Fix**: Krok z kliknięciem „Źródła” w menu bocznym; celowy błąd nr 1 = layout nie przekazuje rekordu małżeństwa (`record = null`).
- **Decision**: FIXED (Fix in plan: dodany krok 5, zmieniony celowy błąd nr 1)

### F2 — Link „Szczegóły małżeństwa” w wierszu z nieznanym małżonkiem

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW
- **Dimension**: Blind Spots
- **Location**: Faza 3 pkt 2; Faza 1 pkt 4
- **Detail**: Link miał być także przy `marriage.spouse === null`, a panel zakłada dwóch małżonków (`spouses[1].display_name`).
- **Fix**: Bez linku przy `spouse === null`; resolver daje 404 dla zdarzenia bez dokładnie dwóch uczestników.
- **Decision**: FIXED (Fix in plan: Faza 3 pkt 2 i Faza 1 pkt 4)

### F3 — Wzorzec przekierowania wskazuje metodę z `url()->previous()`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW
- **Dimension**: Plan Completeness
- **Location**: Faza 1 pkt 5; Key Discoveries; References
- **Detail**: `PersonController::urlAfterDelete` używa `url()->previous()` (wada z lekcji F1 z S-06), a plan wskazywał go jako wzorzec.
- **Fix**: Odczyt Referera wzorem `SourceController::listQuery`; `urlAfterDelete` tylko jako wzorzec walidacji; test z poprzednim adresem w sesji.
- **Decision**: FIXED (Fix in plan: kontrakt, Key Discoveries, test, References)

### F4 — Logika zapisu karty „Ślub” kopiowana zamiast współdzielona

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM
- **Dimension**: Lean Execution
- **Location**: Faza 2, pkt 4 i 6
- **Detail**: `router.put` z callbackami, `savingId`/`failure`, `errorsFor`/`strayErrorFor` i domyślna wartość `MarriageFields` zostawały do skopiowania w panelu (~50 linii).
- **Fix A ⭐**: Wspólny hook `useSectionSave` + `marriageSaveRequest` + `marriageFieldDefaults`.
- **Fix B**: Świadoma duplikacja.
- **Decision**: FIXED via Fix A (Faza 2 pkt 4 i 6)
