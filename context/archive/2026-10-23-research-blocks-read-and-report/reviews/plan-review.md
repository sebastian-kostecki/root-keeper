<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Import — odczyt bloków danych z repo badawczego i raport

- **Plan**: context/changes/research-blocks-read-and-report/plan.md
- **Mode**: Deep
- **Date**: 2026-10-23
- **Verdict**: REVISE → SOUND po triage
- **Findings**: 1 critical, 3 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
Grounding: 8/8 paths ✓, 4/5 symbols ✓ (composer root name ✗ → F7), brief↔plan ✓

## Findings

### F1 — Niemożliwa data bez cudzysłowu przechodzi po cichu

- **Severity**: ❌ CRITICAL
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details („Daty w YAML”); Faza 2 §2
- **Detail**: Niecytowane `data: 1801-02-31` symfony/yaml tworzy przez `new DateTimeImmutable($scalar)` (`vendor/symfony/yaml/Inline.php:866-886`), które przesuwa dzień zamiast rzucić wyjątek. Sprawdzone w Sail: `1801-02-31` → `1801-03-03`, `1801-02-29` → `1801-03-01`, a `1801-5-7` jest przyjmowane. Po normalizacji do `Y-m-d` walidator widzi poprawną datę, więc obiecane pominięcie „daty niemożliwej” nie zachodzi.
- **Fix A ⭐ Recommended**: `data` tylko jako tekst albo rok; `DateTimeInterface` w `data` daje pominięcie z powodem „ujmij datę w cudzysłów”.
  - Strength: Usuwa całą klasę problemu, bez regexów na surowym YAML.
  - Tradeoff: Autor musi pisać cudzysłów.
  - Confidence: HIGH — zachowanie sprawdzone w Sail.
  - Blind spot: Daty niemożliwe w kalendarzu (1801-13-01) dalej dają ParseException (F6).
- **Fix B**: Przed parsowaniem znaleźć niecytowane `\d{4}-\d{1,2}-\d{1,2}` w surowym tekście i sprawdzić je przez `checkdate`.
  - Strength: Autor pisze daty bez cudzysłowu.
  - Tradeoff: Kruche, to drugi parser obok YAML.
  - Confidence: MED.
  - Blind spot: Daty w innych polach.
- **Decision**: FIXED (Fix A) — zmieniono przykład kontraktu, gramatykę, Critical Implementation Details, powody pominięcia, fixture'y i testy oraz brief.

### F2 — Nierozstrzygnięte przypadki brzegowe małżeństw

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Faza 2 §3 — Małżeństwa
- **Detail**: (a) Nie wiadomo, czy małżeństwo z `z` wskazującym id bez poprawnego bloku trafia do `marriages`. (b) Ta sama para dwa razy w jednym pliku. (c) Nie wiadomo, czy rozbieżność porównuje surowy tekst czy sparsowane wartości.
- **Fix**: (a) ostrzeżenie, małżeństwo poza `marriages`; (b) ostrzeżenie i małżeństwo pominięte, a ponowny ślub tej samej pary poza v1; (c) porównanie znormalizowanych wartości; każdy przypadek w fixture'ach.
- **Decision**: FIXED

### F3 — `firstOrFail()` w komendzie nie da `assertFailed()`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §1
- **Detail**: `Console/Application.php:76` ustawia `setCatchExceptions(false)`, więc `ModelNotFoundException` wylatuje z testu zamiast zwrócić kod 1. `User->tree` jest nullable (`UserFactory::withoutTree()`).
- **Fix**: `->first()?->tree`; brak konta albo drzewa daje `$this->error` + `self::FAILURE`; dochodzi test „konto bez drzewa”.
- **Decision**: FIXED

### F4 — Uszkodzony początek bloku = cicho „bez bloku”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §2
- **Detail**: BOM, pusta linia przed `---` albo blok pod H1 sprawiają, że plik jest liczony jako „bez bloku” i widać go tylko z `-v`. Przodek znika z importu bez śladu w raporcie.
- **Fix**: Usuwać BOM; plik bez frontmattera z linią `^id: O-\d+` albo `^wersja:` daje ostrzeżenie „blok nie zaczyna się w pierwszej linii”; przypadki w fixture'ach i testach.
- **Decision**: FIXED

### F5 — Rodzaje daty bez celu zapisu do czasu S-07

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Architectural Fitness
- **Location**: Faza 2 §1
- **Detail**: `events` zna tylko `date_is_approximate`. S-02 idzie przed S-07, więc `range`, `before` i `after` nie mają celu zapisu.
- **Fix**: Nota dla S-02 w „What We're NOT Doing”.
- **Decision**: FIXED

### F6 — Drobne luki w kontrakcie Fazy 2

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 2 §1–§3, §5
- **Detail**:
  - Brak `ParsedPersonBlock` na liście plików.
  - „Wyjątek domenowy” nie ma wzorca w `app/`.
  - `tests/Unit` działa bez aplikacji, więc `ResearchDate` nie może użyć `now()`.
  - Komunikaty `ParseException` są po angielsku.
- **Fix**:
  - `ParsedPersonBlock.php` w §1.
  - `RuntimeException` z polskim komunikatem, który komenda łapie.
  - Rok z `(int) date('Y')`.
  - Powód „Błąd składni YAML w linii N” po polsku.
- **Decision**: FIXED

### F7 — Kryterium 1.1 oczekuje złej nazwy pakietu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 1 Success Criteria / Progress 1.1
- **Detail**: `composer.json` ma `"name": "laravel/react-starter-kit"`.
- **Fix**: Kryterium i Progress 1.1 wymieniają `laravel/react-starter-kit`.
- **Decision**: FIXED
