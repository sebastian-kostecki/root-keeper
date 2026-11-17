<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Wyszukiwanie osób po wariantach nazwiska

- **Plan**: context/changes/surname-variant-search/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-16
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 4 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Weryfikacja kryteriów sukcesu (2026-11-16)

- `vendor/bin/sail artisan test --compact` (pełny zestaw): pierwszy przebieg 23 błędy w niezwiązanych plikach (OwnershipBoundaries, ParentChildRelations, Sources, profil). Równolegle działało 5 innych procesów `artisan test` na tej samej testowej bazie i dysku. Ponowny przebieg wszystkich dotkniętych plików oraz plików tej zmiany (PersonsTest, ImportPlan/Apply/ResearchCommand, SurnameVariantsTest, OwnershipBoundaries, ParentChildRelations, Sources, SourceMigration, Settings): 748/748 PASS.
- `vendor/bin/sail npm run test:js`: 443/443 PASS.
- `vendor/bin/sail npm run check`: PASS. `vendor/bin/sail npm run types:check`: PASS (exit 0). `vendor/bin/sail npm run build`: PASS.
- `vendor/bin/sail composer types:check`: 0 błędów. Pint: PASS.
- Migracja: `2026_11_16_065513_create_person_surname_variants_table` ma status Ran. Rollbacku `--step=1` nie uruchomiono, bo cofnąłby teraz późniejszą migrację `2026_11_16_072318` z import-sources-with-scans. `down()` usuwa tabelę.
- Manual: 1.4, 2.4, 2.5, 3.6, 3.7, 3.8 są nieodhaczone, czyli oczekujące (to nie pozorne odhaczenie).

## Triage (2026-11-16)

Użytkownik: „napraw samodzielnie według rekomendacji”. Fixed: F1–F6 (6). Accepted: F7 (1). Po poprawkach: ImportPlan/Apply/ResearchCommand, ResearchRepoRead, SurnameVariantsTest 399/399, PersonsTest + Backup 154/154, Pint i Larastan bez błędów.

## Findings

### F1 — Licznik wariantów w podsumowaniu liczy surową listę z repo

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/PlanImport.php:377, :476
- **Detail**: `$totals->variants += count($research->surnameVariants)` liczy listę przed normalizacją. Dla [Mysiuk, Myszuk, MYSIUK] podsumowanie „Warianty nazwiska przy osobach” pokaże 3, a zapisane zostaną 2. Przy pierwszym dopasowaniu liczy też warianty, które mogą nie zostać zapisane (konflikt rozstrzygnięty na korzyść aplikacji). Docblock `NoteValueCounts` mówi „the variants written”.
- **Fix**: Liczyć `count(SurnameVariants::fromResearch($research)->names)` w obu miejscach i przy dopasowaniach doprecyzować docblock („warianty z repo”), bo zapis zależy od decyzji w triage.
- **Decision**: FIXED — licznik bierze `SurnameVariants::fromResearch(...)->names` (PlanImport.php:377, :476), docblock NoteValueCounts doprecyzowany; test „counts the surname variants of a new person without repeats”.

### F2 — Linia „Warianty nazwiska przy osobach” wcięta w grupie „Miejsca:”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: app/Console/Commands/ImportResearch.php:1176
- **Detail**: Plan (faza 2, p. 5) mówi o osobnej linii pod liniami „Miejsca”. Kod drukuje `'  Warianty nazwiska przy osobach: %d'` z wcięciem, zaraz pod „zapamiętane warianty: %d” (aliasy miejscowości). Wygląda to jak podpunkt „Miejsca” i myli dwa różne „warianty”. Test używa `expectsOutputToContain`, więc zniesie zmianę wcięcia.
- **Fix**: Usunąć wcięcie (`'Warianty nazwiska przy osobach: %d'`), żeby linia stała osobno.
- **Decision**: FIXED — linia bez wcięcia; test komendy sprawdza ją przez `expectsOutput` (dokładna linia).

### F3 — Wariant dłuższy niż 255 znaków przerywa cały import

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ParsePersonBlock.php:190
- **Detail**: Reguła `'warianty_nazwiska.*' => ['string']` nie ma `max:255`, a kolumna `person_surname_variants.name` to varchar(255). Za długi wariant przejdzie walidację, a potem wywoła błąd insertu w transakcji `ApplyImport` i cofnie cały zapis. CLAUDE.md wymaga, żeby błędny wpis był pominięty i zgłoszony z plikiem i powodem, a reszta szła dalej. Ta sama luka istnieje już dla `imiona` / `nazwisko`, ale zapis wariantów jest nowy.
- **Fix**: Dodać `'max:255'` do `warianty_nazwiska.*` z testem planu na pominięty blok (luka przy `imiona` / `nazwisko` jako osobna pozycja na później).
- **Decision**: FIXED — `max:255` dla `warianty_nazwiska.*`; test „skips a person whose surname variant is longer than 255 characters and reads the rest” (ResearchRepoReadTest). Luka przy `imiona` / `nazwisko` zostaje do osobnej zmiany.

### F4 — Deduplikacja w PHP i unikalny indeks w PostgreSQL inaczej zmieniają wielkość liter dla rzadkich znaków

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/Import/SurnameVariants.php:62-66
- **Detail**: `mb_strtolower` stosuje pełne mapowanie (np. `İ` daje `i̇`), a `lower()` w bazie (libc, en_US.utf8) daje `i`. [„Iwański”, „İwański”] przejdzie deduplikację w PHP i naruszy indeks `(person_id, lower(name))`, cofając import. Dla polskich liter oba dają ten sam wynik (sprawdzone zapytaniem), więc realne dane raczej tego nie trafią.
- **Fix**: Opcjonalnie deduplikować przez `mb_convert_case($clean, MB_CASE_LOWER_SIMPLE)`.
- **Decision**: FIXED — deduplikacja przez `mb_convert_case(..., MB_CASE_LOWER_SIMPLE)`; test jednostkowy „folds case by simple case mapping”.

### F5 — `guardPerson` doładowuje warianty osobno dla każdej osoby

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ApplyImport.php:495 (używane w guardPerson, :1120)
- **Detail**: Osoby blokowane do weryfikacji ładują się bez `surnameVariants`, więc `guardPerson` robi jedno zapytanie na każdą osobę z pozycją wariantów. To małe N+1, ograniczone liczbą takich osób.
- **Fix**: Dodać `->with('surnameVariants')` do zapytania blokującego osoby w `:495`.
- **Decision**: FIXED — `->with('surnameVariants')` w zapytaniu blokującym osoby (ApplyImport.php:495).

### F6 — `PersonSurnameVariant` nie ma relacji `tree()` jak `LocalityAlias`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Models/PersonSurnameVariant.php
- **Detail**: Wzorcowy `LocalityAlias` ma `tree()` i `locality()`. Nowy model ma tylko `person()`. Dziś nic tego nie potrzebuje, bo każdy odczyt idzie przez osobę.
- **Fix**: Dodać `tree(): BelongsTo` dla spójności z modelem wzorcowym.
- **Decision**: FIXED — `PersonSurnameVariant::tree(): BelongsTo`.

### F7 — Zbędny osobny indeks `person_id`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: database/migrations/2026_11_16_065513_create_person_surname_variants_table.php:23
- **Detail**: Unikalny indeks `(person_id, lower(name))` zaczyna się od `person_id`, więc obsługuje FK i podzapytanie EXISTS. W `locality_aliases` osobny indeks miał sens, bo unikalny zaczyna się od `tree_id`. Tutaj jest skopiowany bez potrzeby. Jest nieszkodliwy.
- **Fix**: Zostawić. Ewentualnie usunąć w późniejszej migracji, ale nie edytować uruchomionej.
- **Decision**: ACCEPTED — zgodnie z rekomendacją zostaje; uruchomionej migracji nie edytujemy.
