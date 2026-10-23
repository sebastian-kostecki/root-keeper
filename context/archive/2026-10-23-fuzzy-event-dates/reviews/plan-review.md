<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Daty nieostre: zakres, przed, po

- **Plan**: context/changes/fuzzy-event-dates/plan.md
- **Mode**: Deep
- **Date**: 2026-10-23
- **Verdict**: REVISE → SOUND po poprawkach
- **Findings**: 1 critical, 1 warning, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
40/40 paths ✓, 9/9 symbols ✓, brief↔plan ✓, Progress↔Phase ✓

## Findings

### F1 — Faza 1 cofa „ok.” do daty dokładnej

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Phase 1 — §3 Model Event, §4 Fabryka, helpery i seedery
- **Detail**: Faza 1 zdejmuje `date_is_approximate` z `#[Fillable]` i dodaje hook `saving`, który wylicza flagę z `date_qualifier`. `MapsEventInput` zmienia się jednak dopiero w fazie 2 i oddaje `date_is_approximate`. Aplikacja nie ma `Model::shouldBeStrict`, więc ten klucz ginie po cichu: „ok.” z formularza zapisuje się jako data dokładna i `MarriagesTest.php:45` pada. Ten sam mechanizm psuje miejsca spoza listy fazy 1: `TreeTest.php:835`, `PersonTimelineTest.php:218,429,440`, `TreeViewDemoSeeder.php:201,549`.
- **Fix**: W fazie 1 `MapsEventInput` nadal czyta wejście `is_approximate`, ale oddaje `date_qualifier`. Wymienione testy i seeder w fazie 1 piszą `date_qualifier`.
  - Strength: Faza 1 jest naprawdę neutralna i 1.3 przechodzi bez zmian asercji.
  - Tradeoff: Jedna linia mostu nadpisana w fazie 2.
  - Confidence: HIGH — brak strict mode i miejsca zapisu sprawdzone grepem.
  - Blind spot: None significant.
- **Decision**: FIXED (Fix in plan)

### F2 — Tabela odrzuceń w partial-date.test.ts pominięta

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 3 — §1 Parser i formatter (Testy)
- **Detail**: Tabela `partial-date.test.ts:210-228` wprost odrzuca `'1948-1950'`, `'1948 - 1950'`, `'przed 1948'`, `'po 1948'` (`:211-214`). Po zmianie te przypadki są poprawne, a plan wymieniał tylko test `:65`.
- **Fix**: Dopisać w §1, że przypadki `:211-214` przechodzą do tabeli rozpoznanych, a reszta tabeli odrzuceń zostaje.
- **Decision**: FIXED (Fix in plan)

### F3 — Typ qualifier w MapsEventInput nieokreślony dla S-02

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Phase 2 — §3 Mapowanie zapisu
- **Detail**: Formularze wysyłają `qualifier` jako string, a `ResearchDate` z S-01 trzyma enum `DateQualifier`. Plan S-02 (`:130`) odsyła do kontraktu z tej zmiany. Reszta gramatyki (lata 1000…bieżący, zakres z gołych lat od < do, brak przedrostka = exact) się zgadza.
- **Fix**: Kontrakt §3: `qualifier?: DateQualifier|string|null` (`DateQualifier::tryFrom` dla stringa).
- **Decision**: FIXED (Fix in plan)
