<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Wyszukiwanie osób po wariantach nazwiska

- **Plan**: context/changes/surname-variant-search/plan.md
- **Mode**: Deep
- **Date**: 2026-11-16
- **Verdict**: REVISE → SOUND (after fixes)
- **Findings**: 1 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
Grounding: 26/26 paths ✓, 10/10 symbols ✓, brief↔plan ✓

## Findings

### F1 — guardPerson porówna tekst z inną postacią niż etykieta z planu

- **Severity**: ❌ CRITICAL
- **Impact**: 🏃 LOW — szybka decyzja; poprawka oczywista i wąska
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §3 (Plan) i §4 (Zapis)
- **Detail**: `guardPerson()` porównuje bieżącą wartość z `appLabel` pozycji (`ApplyImport.php:518-543, 998-1018`). Plan kazał porównywać `fromPerson()->text()` (nazwy złączone „\n”), a etykiety brały `label()` (nazwy złączone „, ”). Przy dwóch lub więcej wariantach zapis zawsze by odmówił, a testy z jednym wariantem tego nie wykryją. `label(): string` dawało "" dla pustej listy zamiast `null`. `PlannedFill::valueLabel()` nie miało ramienia dla `SurnameVariants`.
- **Fix**: Jedna postać etykiety: `label(): ?string` w `appLabel` / `repoLabel` / `lastLabel` i w ramieniu `guardPerson()`, ramię w `valueLabel()`, testy zapisu z co najmniej dwoma wariantami.
- **Decision**: FIXED

### F2 — Odłączona osoba zachowuje warianty, a plan twierdzi, że ich nie ma

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — prawdziwy kompromis; warto się zatrzymać
- **Dimension**: Blind Spots
- **Location**: What We're NOT Doing
- **Detail**: `ApplyImport::detach()` (`:753-759`) zeruje `people.repo_id` i zostawia osobę razem z wierszami `person_surname_variants`. Plan twierdził: „Osoba bez repo_id nie ma wariantów”.
- **Fix A ⭐ Recommended**: Warianty zostają przy odłączeniu, tak jak blok notatki. Poprawione zdanie i dodany test: detach zachowuje warianty, a ponowne dopasowanie z inną listą daje konflikt.
  - Strength: Zgodne z zachowaniem notatki; osoba dalej wyszukiwalna.
  - Tradeoff: Dane tylko do odczytu bez właściciela.
  - Confidence: HIGH — ponowne dopasowanie przechodzi przez regułę trzech wartości.
  - Blind spot: Ścieżka konfliktu w `writeMatch` dla `surname_variants` zostanie sprawdzona testem.
- **Fix B**: `detach()` usuwa warianty.
  - Strength: Niezmiennik „warianty tylko przy repo_id” jest prawdziwy.
  - Tradeoff: Wyszukiwarka po cichu traci trafienie; niespójne z notatką.
  - Confidence: MED.
  - Blind spot: Podsumowanie komendy przy detach.
- **Decision**: FIXED (Fix A)

### F3 — personValues() gubi `warianty`, gdy płeć została pominięta

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — szybka decyzja; poprawka oczywista i wąska
- **Dimension**: Blind Spots
- **Location**: Faza 2 §2/§4
- **Detail**: Pamięć osoby pochodzi z `PlanImport::personValues()` (`:373`). Gałąź pominiętej płci (`:1742`) składa tablicę ręcznie z czterema kluczami, więc `warianty` ginie, a późniejsza zmiana w repo pokazałaby się jako konflikt.
- **Fix**: Gałąź nadpisuje tylko `plec` w `$values`; dodany test planu.
- **Decision**: FIXED

### F4 — Test „ta sama lista innymi wielkościami liter” jest niejednoznaczny

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — szybka decyzja; poprawka oczywista i wąska
- **Dimension**: Blind Spots
- **Location**: Faza 2 §6
- **Detail**: `ThreeWayValue::compare` porównuje przez `===`, a normalizacja zachowuje wielkość liter, więc zmiana samej wielkości liter jest zmianą. Brzmienie testu sugerowało coś przeciwnego.
- **Fix**: Przeredagowany test (duplikat innej wielkości liter i pusty wpis, lista równa po normalizacji) i nowy test „zmiana samej wielkości liter to zmiana”.
- **Decision**: FIXED

### F5 — „Teraz w repo” pokaże tylko pierwszy wariant

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — szybka decyzja; poprawka oczywista i wąska
- **Dimension**: Blind Spots
- **Location**: Faza 2 §5
- **Detail**: `ImportResearch::repoValuesText()` (`:613`) bierze `strtok($value, "\n")`, a `warianty` w pamięci to nazwy złączone „\n”.
- **Fix**: Dla klucza `warianty` zamiana „\n” na „, ” przed skróceniem.
- **Decision**: FIXED
