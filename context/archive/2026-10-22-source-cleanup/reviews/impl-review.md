<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Porządki w źródłach — nagrobek bez roku, archiwum i numeru wpisu

- **Plan**: context/changes/source-cleanup/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-10-22
- **Verdict**: APPROVED
- **Findings**: 0 critical 0 warnings 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | PASS |
| Success Criteria | PASS |

Automated: Pest (migracja + SourcesTest + EventSourcesTest) 186/186, Larastan 0 błędów, Pint czysty, `npm run check`, `types:check`, Vitest 408/408, E2E (gravestone + source-found-by-place-and-year) 3/3.

## Findings

### F1 — Numer wpisu liczony poza blokadą przy zmianie rodzaju na nagrobek

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Sources/UpdateSource.php:90
- **Detail**: `noteWithMovedValues()` czyta `entry_number` z `event_source` bez blokady, a potem transakcja zeruje kolumnę. Równoległa edycja numeru (PATCH) między odczytem a zerowaniem mogłaby zgubić nowy numer. Przy jednym użytkowniku na drzewo praktycznie nieosiągalne.
- **Fix**: `->lockForUpdate()` na zapytaniu o numery, jeśli ryzyko ma znaczenie.
- **Decision**: FIXED (lockForUpdate)

### F2 — Dopisek do notatki może przekroczyć limit 5000 znaków walidacji

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Sources/UpdateSource.php:100, migracja 2026_10_22_100000
- **Detail**: Kolumna `note` to `text`, więc zapis działa, ale notatka po dopisaniu linii może mieć >5000 znaków i kolejny zapis formularza zostanie odrzucony, dopóki użytkownik jej nie skróci. Skrajny przypadek.
- **Fix**: Zaakceptować; ewentualnie opisać w planie jako znane ograniczenie.
- **Decision**: SKIPPED
