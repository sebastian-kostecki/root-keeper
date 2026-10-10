<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Widok przodków (S-15 ancestors-view)

- **Plan**: context/changes/ancestors-view/plan.md
- **Mode**: Deep
- **Date**: 2026-10-10
- **Verdict**: SOUND
- **Findings**: 0 critical · 3 warnings · 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
23/23 paths ✓, 11/11 symbols ✓, brief↔plan ✓, Progress↔Phases ✓. Zweryfikowane: react-zoom-pan-pinch 4.2.0 synchronizuje `ctrlKey` do `pressedKeys` (`syncModifierKeys`), więc szczypanie na touchpadzie działa z `activationKeys: ['Control']`; panel osoby bierze `genders`/`religions` z `personPreview`, nie z propsów strony.

## Findings

### F1 — Drugi przycisk w nagłówku panelu zjada tytuł

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 4 §1 — Akcja „Pokaż przodków”
- **Detail**: Panel `md:w-[clamp(24rem,30vw,36rem)]` (side-panel.tsx:215) ma 384 px przy ekranie ≤1280 px; dwa przyciski tekstowe + „⋯” + „×” zajmują ~370 px, a tytuł (`truncate flex-1`) z imieniem — jedyne czytelne miejsce imienia — zostaje ucięty prawie do zera.
- **Fix A ⭐ Recommended**: „Pokaż przodków” tylko w menu „⋯”
  - Strength: Nagłówek bez zmian; zero ryzyka układu.
  - Tradeoff: O jedno kliknięcie więcej; E2E otwiera menu.
  - Confidence: HIGH — menu już ma „Pokaż na drzewie”.
  - Blind spot: None significant.
- **Fix B**: Przycisk ikonowy z aria-label i tooltipem
  - Strength: Widoczny jednym kliknięciem, ~32 px.
  - Tradeoff: Ikona mniej oczywista niż tekst.
  - Confidence: MED — szerokość nie zmierzona w przeglądarce.
  - Blind spot: Dostępność pasującej ikony i tooltipa.
- **Decision**: FIXED (Fix A)

### F2 — E2E: „Nieznana matka nad ojcem” nie da się sprawdzić zgodnie z zasadami

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 5 §1 — krok 3
- **Detail**: Przy 4 pokoleniach „Nieznana matka” występuje 3×, „Nieznany ojciec” 2×; `getByText` złamie strict mode, a „nad ojcem” to asercja układu zakazana kontraktem i test-plan §7.
- **Fix**: Asercje `toHaveCount` (3 i 2) zamiast położenia; po „2” zastępników 0.
- **Decision**: FIXED

### F3 — Zła wskazówka helpera dla przełącznika pokoleń

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §3 — Contract
- **Detail**: `withPanel(url, key: PanelKey, id)` (side-panel-url.ts:36) obsługuje tylko klucze paneli i czyści pozostałe panele; nie ustawi `generations`.
- **Fix**: Skopiuj bieżący query, podmień `generations`, odwiedź `ancestors(focus.id, { query })`.
- **Decision**: FIXED

### F4 — test-plan.md nazywa FR-018 „eksportem”

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 4 §3 — Ujednolicenie dokumentacji
- **Detail**: `test-plan.md:233` „Eksport danych — … (FR-018 odłożony)”, a FR-018 w PRD to widok przodków.
- **Fix**: Dopisać poprawkę `test-plan.md` §7 do Fazy 4 §3.
- **Decision**: FIXED
