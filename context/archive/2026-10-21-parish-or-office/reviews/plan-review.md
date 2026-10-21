<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Parafia / urząd z wezwaniem

- **Plan**: context/changes/parish-or-office/plan.md
- **Mode**: Deep
- **Date**: 2026-10-21
- **Verdict**: SOUND
- **Findings**: 0 critical, 1 warning, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | WARNING |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | PASS |

## Grounding
50/50 paths ✓, 8/8 symbols ✓ (CLAUDE.md:87/88/90, prd.md:95/118, MergeLocality:50/68-82, MergeParish:89-97, SourceController:108/138), brief↔plan ✓, Progress↔Phase ✓ (1.1–1.7, 2.1–2.3, 3.1–3.9, 4.1–4.5)

Verified in code: the CHECK `locality_id IS NOT NULL OR name IS NOT NULL` cannot be violated (DeleteLocality refuses a locality with parishes, nothing sets `parishes.locality_id` to NULL, seat required in validation); backup restore replays a full `pg_dump` with its own schema, so it is unaffected; the five E2E specs listed are exactly the ones that create or assert parishes; N+1 only in `placeOptions` and `ParishMergeController`, as the plan states.

## Findings

### F1 — initialQuery w SuggestField jest zbędny i znika po blur

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Lean Execution
- **Location**: Faza 3 §2–§3, kryterium 3.8
- **Detail**: Plan dodawał `initialQuery` do współdzielonego `SuggestField` (7 wywołań, `LocalityPicker` ma 6 dalszych). `NewParishDialog` dostaje już `localities` (`new-parish-dialog.tsx:31-32`), a nazwy miejscowości są unikalne w drzewie bez względu na wielkość liter (`create_localities_table.php:24`). Trafioną siedzibę wystarczy więc przekazać jako `value`. Tekst bez trafienia znikałby po blur (`suggest-field.tsx:84,227-232`: `handleBlur` → `isEditing=false`).
- **Fix A ⭐ Recommended**: Bez initialQuery. Trafienie → wybrana siedziba, brak trafienia → puste pole siedziby.
  - Strength: Wspólny komponent się nie zmienia; trafienie to jedna linia w dialogu.
  - Tradeoff: Nieznaną nazwę autor wpisuje ponownie w siedzibie.
  - Confidence: HIGH — dialog ma już wszystkie potrzebne dane.
  - Blind spot: None significant.
- **Fix B**: Zostaw initialQuery, dopisz kontrakt (isEditing=true na starcie, zachowanie po blur) i test Vitest.
  - Strength: Zachowuje przepływ dla nieznanej nazwy.
  - Tradeoff: Zmiana współdzielonego komponentu.
  - Confidence: MED — reset w handleBlur wymaga decyzji.
  - Blind spot: Wpływ na pozostałe wywołania nie był testowany.
- **Decision**: FIXED (Fix A) — usunięta Faza 3 §2 (pozostałe sekcje przenumerowane), `NewParishDialog` dostaje `initialSeatName`, zaktualizowane Desired End State, Current State, kryterium 3.8 i plan-brief.

### F2 — Klucz scalania miejscowości sklejany bez separatora

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §2 — MergeLocality::mergeCollidingParishes()
- **Detail**: `kind || coalesce(religion,'') || lower(coalesce(dedication,''))` nie ma separatora: parafia z wyznaniem „inne” bez wyróżnika i parafia bez wyznania z wyróżnikiem „Other” dają ten sam klucz „parishother” i zostałyby po cichu scalone.
- **Fix**: Porównuj krotkę pól (trzy kolumny, klucz w PHP przez `json_encode`) zamiast sklejania.
- **Decision**: FIXED — kontrakt Fazy 2 §2 opisuje klucz jako krotkę.
