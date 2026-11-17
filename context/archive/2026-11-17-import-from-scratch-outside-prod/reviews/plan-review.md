<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Import „od zera” poza produkcją

- **Plan**: context/changes/import-from-scratch-outside-prod/plan.md
- **Mode**: Deep
- **Date**: 2026-11-17
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 0 critical, 2 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Grounding: 11/11 paths ✓, 12/12 symbols ✓, brief↔plan ✓

Potwierdzone w kodzie:
- **Klucze obce:** żaden FK do `people` ani `events` nie blokuje usunięcia. Kaskady działają tak, jak opisuje plan, a `events` nie mają FK do osób.
- **Klasyfikacja i pamięć:** po czyszczeniu klasyfikacja daje same nowe rekordy. `RepoKeys::kindOf` nie rzuca wyjątku. `repo_snapshots` to jedyna pamięć importu.
- **Źródła:** zachowane źródła podpinają się do nowych faktów (`PlanResearchSources.php:178-194`, `WriteResearchSources.php:365-368`).
- **Nowa Akcja:** żaden istniejący kod nie czyści osób drzewa, więc nowa Akcja ma uzasadnienie.

## Findings

### F1 — Test pełnego przebiegu nie może przejść i nie sprawdza źródeł

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Completeness
- **Location**: Faza 2 — Testy komendy, „Pełny przebieg”
- **Detail**:
  - **Porównanie:** `treeState()` (`ImportResearchCommandTest.php:777`) zwraca surowe wiersze z `id`, `tree_id` i znacznikami czasu. Nie mogą być równe wierszom z osobnego importu.
  - **Fixture:** `arrangeImportedAuthors()` (`:748`) nie ma źródeł, skanów ani miejsc. Asercja, że powiązania ze źródłami wróciły, niczego więc nie sprawdza.
- **Fix**:
  - **Fixture:** oprzeć test na `cmdSrcImported()`.
  - **Porównanie:** użyć znormalizowanego stanu z helpera `normalizedTreeState()`, opartego na kluczach `repo_id`. Wzorcem jest stan autora zaraz po `cmdSrcImported()`, zapisany przed dodaniem ręcznych rekordów. To samo drzewo ma te same id miejsc i źródeł, a drzewo trzeciego konta miałoby inne.
  - **Strength:** testuje główną obietnicę planu.
  - **Tradeoff:** jeden nowy helper.
  - **Confidence:** HIGH.
  - **Blind spot:** dokładne pytania o miejsca trzeba odczytać z wyjścia komendy.
- **Decision**: FIXED

### F2 — Tabela przed pytaniem nie pokazuje list pochowanych

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 (TreeClearance), Faza 2 (tabela)
- **Detail**: Import nigdy nie zapisuje `buried_people`; tylko przekazuje istniejące id (`WriteResearchSources.php:665`). Listy pochowanych znikają więc na stałe przy wszystkich źródłach, nie tylko przy ręcznych. Tabela, którą autor widzi przed potwierdzeniem, tego nie pokazywała.
- **Fix**:
  - **Liczba:** pole `buriedLinks` w `TreeClearance`.
  - **Tabela:** wiersz „Pochowani na nagrobkach (nie wrócą z importu)”.
  - **Zakres:** poprawione zdanie w „What We're NOT Doing” i w briefie.
- **Decision**: FIXED

### F3 — Niejasne, jak wymusić błąd w teście wycofania

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 — Testy Akcji
- **Detail**: Plan pisze „np. wymuszony błąd”, ale `ClearTree` nie ma zależności, którą dałoby się podmienić, a masowe `delete()` nie wywołuje zdarzeń modelu.
- **Fix**: W teście wywołać `ClearTree` wewnątrz zewnętrznego `DB::transaction` i rzucić wyjątek po `handle()`. Callback `afterCommit` przepada; dane i pliki zostają bez zmian.
- **Decision**: FIXED
