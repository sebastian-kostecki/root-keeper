<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Nagrobek z cmentarzem i osobami pochowanymi (S-09)

- **Plan**: context/changes/gravestone-cemetery-and-buried/plan.md
- **Mode**: Deep
- **Date**: 2026-10-06
- **Verdict**: REVISE → SOUND (after triage)
- **Findings**: 0 critical · 4 warnings · 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Grounding: 30/30 paths ✓, 9/9 symbols ✓ (1 signature mismatch → F4), brief↔plan ✓

## Findings

### F1 — „Ta sama transakcja” nie istnieje w ścieżce zapisu źródła

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 1 §4; Implementation Approach; Critical Implementation Details
- **Detail**: `StoreSource`, `UpdateSource` i `SourceController` nie mają `DB::transaction`. `StoreSource` zapisuje plik przed wierszem i sprząta go tylko w `catch` (`StoreSource.php:35-69`). Plan nie mówi, gdzie postawić transakcję. Postawiona poza `try` osieroci pliki przy błędzie sync/fill, a bez transakcji zostanie źródło z połową listy.
- **Fix**: `StoreSource`: `DB::transaction` (create + sync + fill) wewnątrz istniejącego `try`. `UpdateSource`: `DB::transaction` (update + sync + fill). Test: wyjątek w fill → brak wiersza i plików.
- **Decision**: FIXED (Fix in plan)

### F2 — Dialog zmiany rodzaju liczy tylko formularz, serwer liczy stan zapisany

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §3, Faza 1 §5
- **Detail**: Plan każe `erasedRecordFields` liczyć cmentarz i listę „wg bieżących wartości formularza”, a `wouldEraseRecordFields` patrzy na stan zapisany. Gdy autor wyczyści zapisaną listę i zmieni rodzaj, dialog się nie otworzy, a serwer zwróci błąd `kind` bez sposobu na potwierdzenie. Istniejąca reguła liczy wartość zapisaną nawet po wyczyszczeniu w formularzu (`source-description.ts:40-44`). Test `source-description.test.ts:153` asertuje dziś `[]` dla gravestone→other.
- **Fix**: Ta sama reguła co dla pól aktu: liczy się cmentarz z formularza lub zapisany, a N = |`source.buried_people` ∪ ids z formularza|. Dodać przypadek Vitest dla wyczyszczonej zapisanej listy i zaktualizować asercję `:153`.
- **Decision**: FIXED (Fix in plan)

### F3 — Dirty-state listy pochowanych zostawiony jako „sprawdzić”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Critical Implementation Details, Faza 3 §2
- **Detail**: Inertia `<Form>` porównuje FormData tylko przy zdarzeniach `input`/`change`/`reset`, bez MutationObservera. `SuggestField` wysyła `change` z ukrytego inputu w `useEffect`. Po usunięciu ostatniej osoby nie ma inputu, z którego dałoby się wysłać zdarzenie.
- **Fix**: `BuriedPeopleField` ma zawsze zamontowany element i w `useEffect([ids])` wysyła z niego `new Event('change', {bubbles: true})`.
- **Decision**: FIXED (Fix in plan)

### F6 — Podpowiedź pochowania wraca po celowym wyczyszczeniu

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 4 §3; Open Risks w briefie
- **Detail**: `SectionCard` serializuje FormData przy montowaniu (`section-card.tsx:149-181`), więc wstępny cmentarz staje się czystym stanem bazowym. Strażnik nie ostrzega, a „Zapisz” wysyła `burial.*` (`overview-tab.tsx:65-70`). Każda edycja Zgonu u osoby z celowo wyczyszczonym pochowaniem przywraca cmentarz.
- **Fix A ⭐ Recommended**: Przyjąć jawnie — dopisać do Open Risks i do kroku ręcznego 4.5.
  - Strength: Zero kodu; spójne z decyzją „każdy zapis, cała lista”.
  - Tradeoff: Ciche przywracanie zostaje; chroni przed nim tylko dopisek „Podpowiedź z nagrobka”.
  - Confidence: HIGH — mechanika potwierdzona w `section-card.tsx`.
  - Blind spot: Nie wiadomo, jak często autor celowo czyści pochowanie.
- **Fix B**: Podpowiadać tylko przy „Oznacz zgon” (intent `mark-deceased`).
  - Strength: Usuwa ciche przywracanie przy zwykłej edycji.
  - Tradeoff: Zmarły bez pochowania przy edycji nie dostanie podpowiedzi (łata to ponowny zapis nagrobka).
  - Confidence: MED — intent jest dostępny, ale przekazania go do `DeathFields` nie sprawdzono.
  - Blind spot: Desired End State do przeredagowania.
- **Decision**: FIXED (Fix A)

### F4 — Kontrakt PickPersonDialog nie zgadza się z API PersonCandidateList

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3 §2
- **Detail**: `PersonCandidateList` ma `onSelect(candidateId)` i wymagany `processingId`, nie `onPick(candidate)` (`person-candidate-list.tsx:12-27`). `createdLocalities` zasila dziś tylko `NewParishDialog.onLocalityCreated` (`source-description-fields.tsx:64-83,211`).
- **Fix**: `onSelect(id)` → `display_name` z listy kandydatów, `processingId = null`, picker cmentarza z `onCreated` dopisującym do `createdLocalities`.
- **Decision**: FIXED (Fix in plan)

### F5 — Seed backupu: liczniki źródeł są przypięte

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 §6
- **Detail**: `arrangeBackupData()` asertuje 2 źródła (`BackupTestCase.php:173`), a `BackupRestoreTest.php:45` asertuje 3. Nowe źródło-nagrobek złamie oba liczniki.
- **Fix**: Przestawić akt-2 przez `sources.update` na gravestone z cmentarzem `$seat` i pochowanym `$parent` (liczniki bez zmian), albo zaktualizować oba liczniki.
- **Decision**: FIXED (Fix in plan)
