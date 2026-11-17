<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Miejsca z repo dopasowane do spisu

- **Plan**: context/changes/import-places-matched-to-gazetteer/plan.md
- **Mode**: Deep
- **Date**: 2026-10-30
- **Verdict**: REVISE (po triage: SOUND)
- **Findings**: 1 critical, 5 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | WARNING |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
40/40 paths ✓, 7/7 symbols ✓, brief↔plan ✓, Progress↔Phases ✓ (6/6 phases, 29/29 criteria)

## Findings

### F1: Pochowanie bez cmentarza znika przy zapisie osoby w UI

- **Severity**: ❌ CRITICAL
- **Impact**: 🔬 HIGH: architectural stakes; think carefully before deciding
- **Dimension**: Blind Spots
- **Location**: Faza 4.3, Faza 5.3, Critical Implementation Details
- **Detail**: Plan nie mówił, co import robi z `pochowek` bez rozpoznanego cmentarza. `UpdatePerson.php:67-73` kasuje pochowanie bez cmentarza przy każdym zapisie formularza osoby, więc kolejny import pytałby o „usunięte w aplikacji”.
- **Fix A ⭐ Recommended**: Pochowanie powstaje tylko z rozpoznanym cmentarzem; bez niego tekst w notatce i pamięć klucza bez rekordu.
  - Strength: Model aplikacji bez zmian.
  - Tradeoff: Nowy przypadek „pamięć bez rekordu” w klasyfikacji.
  - Confidence: MED: wzór `keptDeletedSnapshots` już istnieje.
  - Blind spot: Cmentarz dodany później do spisu nie tworzy pochowania sam.
- **Fix B**: `UpdatePerson` zachowuje pochowanie bez cmentarza.
  - Strength: Prostszy tor S-04.
  - Tradeoff: Zmiana formularza i niezmiennika z CLAUDE.md.
  - Confidence: MED.
  - Blind spot: Widok osoby z pustym miejscem pochowania.
- **Decision**: FIXED (Fix A)

### F2: Pamięć miejsca jako tekst nie mówi, co import zapisał

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH: architectural stakes; think carefully before deciding
- **Dimension**: End-State Alignment
- **Location**: Faza 4.2 (`placeItem`), Faza 4.4 (notatka)
- **Detail**: `ThreeWayValue::compare` porównuje `$app === $last` jako tekst, a S był tekstem repo, A miejscowością. Rozpoznanie S „dziś” dawało fałszywe konflikty. `$placed` w notatce nie był zdefiniowany dla nazw bez pytania (R == S), co groziło albo zmianą notatki w każdym przebiegu, albo utratą tekstu repo.
- **Fix A ⭐ Recommended**: Pamięć trzyma `miejsce_id` / `cmentarz_id`; porównanie tokenami id; tekst znika z notatki tylko, gdy wydarzenie ma miejsce z R.
  - Strength: Dokładna reguła trzech wartości; dawne „bez miejsca” się uzupełnia.
  - Tradeoff: Po scaleniu id nie istnieje; powrót do rozpoznania tekstu.
  - Confidence: MED.
  - Blind spot: Notatka zależna od rozstrzygnięcia konfliktu, które zapada po planie.
- **Fix B**: Sam tekst, dopisane tokeny i definicja `$placed`.
  - Strength: Mniej zmian.
  - Tradeoff: Konflikty po zmianie nazwy; „bez miejsca” nigdy się nie uzupełnia.
  - Confidence: MED.
  - Blind spot: Brak.
- **Decision**: FIXED (Fix A). Dopisano uwagę, że notatka zależna od konfliktu wymaga dwóch wariantów albo liczenia w `ApplyImport`.

### F3: Kolejność wariant / nazwa przy rozpoznaniu nie jest ustalona

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM: real tradeoff; pause to reason through it
- **Dimension**: Architectural Fitness
- **Location**: Faza 3.2
- **Detail**: Wariant zapamiętany przy kolizji dwóch miejscowości o tym samym kluczu dawał przy kolejnym imporcie remis nazwa / wariant i pytanie wracało.
- **Fix**: Wariant wygrywa z nazwą; remis nazw bez wariantu jest niepewny; test kolizji.
- **Decision**: FIXED

### F4: Tabela nowych cmentarzy przed decyzjami o miejscowościach

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW: quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Desired End State, Faza 6.1
- **Detail**: Werdykt cmentarza zależy od decyzji o miejscowości, a tabela miała je łączyć.
- **Fix**: Nowe cmentarze wchodzą bez pytania i są liczone w podsumowaniu.
- **Decision**: FIXED (cmentarze bez pytania; brief też zaktualizowany)

### F5: Nowe miejsca mogą zostać bez użycia po odrzuceniu zmian

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW: quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 4.1
- **Detail**: Odrzucenia i rozstrzygnięcia konfliktów trafiają do `ApplyImport::handle` (ApplyImport.php:64), po planie.
- **Fix**: `ApplyImport` tworzy tylko nowe miejsca użyte przez zapisywany element; warianty zawsze.
- **Decision**: FIXED

### F6: Klucz pochowania pomija miejsca zakodowane na sztywno

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW: quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Fazy 2.3, 4.3, 5.4
- **Detail**: `RepoKeys::kindOf` default Person, `ClassifyRepoRecords.php:186` i `:330`, `ApplyImport.php:145`, `RepoValues::forKey`, `ImportResearch.php:436`.
- **Fix**: Wymienione w fazach z numerami linii.
- **Decision**: FIXED

### F7: `MatchResearchPlaces` nie dostaje pamięci w sygnaturze

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW: quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3.2, 3.3
- **Detail**: Pamięć ładuje sam `PlanImport` (`:89`); brak źródła zbioru nazw do kontroli pokrycia decyzji.
- **Fix**: Action ładuje `$tree->repoSnapshots()`; `PlanImport` wywołuje tę samą Action.
- **Decision**: FIXED

### F8: Polecenia testów pomijają pliki, których zmiana dotyczy

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW: quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 i 2, Success Criteria
- **Detail**: Test 404 scalania jest w `OwnershipBoundariesTest.php:99`; `--filter=Import` mógł pominąć testy korzystające z fixture.
- **Fix**: Pliki wymienione jawnie.
- **Decision**: FIXED

### F9: Pisownia nowej miejscowości i kolizja kluczy pod blokadą

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW: quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 3.2, Faza 5.2
- **Detail**: Brak reguły pisowni dla nazw o tym samym kluczu; indeks `lower(name)` nie łapie innej pisowni tego samego klucza.
- **Fix**: Pisownia z polskimi znakami, potem najczęstsza; sprawdzenie klucza pod blokadą.
- **Decision**: FIXED
