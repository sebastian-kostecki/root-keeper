<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Szczegóły miejscowości

- **Plan**: context/changes/locality-details/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-10-20
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 2 warnings, 3 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Evidence

- Diff: `89b5bfd..HEAD` (a50e32e p1, d68ca89 p2, 0dca67c + 7fac49e epilog) — 36 plików, każda zaplanowana zmiana kodu MATCH, brak DRIFT.
- Automatyczne (uruchomione 2026-10-20): Pest Localities/Places/Persons/Sources 358/358 ✓; Pint ✓; Larastan 0 błędów ✓; Vitest 372/372 ✓; `npm run check` ✓; `tsc` ✓; build ✓.
- 1.1 migracja: `migrate:rollback --step=1 --pretend` → `drop column` dla 6 nowych kolumn (bez faktycznego rollbacku, żeby nie skasować danych deweloperskich).
- EXTRA (nieszkodliwe): `md:min-w-64` / `min-w-0` w przyklejonej komórce nazwy, fixture w `places-index.test.ts`, helper `localityDetails()` w `LocalitiesTest.php`.
- Sprawdzone bez uwag: scoping przez `$request->user()->tree`, polityki i 404, `Coordinates::parse` (DMS, `1e5`, przecinek dziesiętny w obu liczbach, zakres po zaokrągleniu, brak ReDoS), zgodność walidacji z akcją, transakcja + `lockForUpdate` w scalaniu, klucz `<Form>` (wpisane dane przeżywają błąd walidacji), `formatCoordinates` dla 0, `rel="noreferrer"`, tokeny widoku.

## Findings

### F1 — Krok 2.9 odhaczony, a zrzutów ekranu nie ma

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/locality-details/plan.md:312 (Progress 2.9); context/changes/locality-details/screenshots/
- **Detail**: Plan (Faza 2 §6) wymaga zrzutów — jasny/ciemny motyw, szeroki/wąski ekran — listy z kolumną Rodzaj i plakietką, panelu nieistniejącej miejscowości ze statusem i odnośnikiem mapy oraz podpowiedzi z dopiskiem. Katalog nie istnieje ani w drzewie roboczym, ani w historii git, ani nie jest ignorowany; inne zmiany (np. `context/archive/2026-10-12-places-page/screenshots`) je commitują. Progress 2.9 ma `[x] — d68ca89` bez artefaktu — pozorne odhaczenie bramki wizualnej (CLAUDE.md: „Visual gate: screenshots in context/changes/<id>/screenshots/”). Pozostałe punkty Manual (2.5–2.8) mają pokrycie w kodzie, ale bez zrzutów brak dowodu ich ręcznego przejścia.
- **Fix**: Zrobić 12 zrzutów (3 widoki × 2 motywy × 2 szerokości) przez `playwright-cli` na danych z nieistniejącą miejscowością i zacommitować je do `screenshots/`.
- **Decision**: FIXED — 12 zrzutów + `shots.mjs` + `verdict.md` w `screenshots/` (2026-10-20); bramka wizualna OK

### F2 — Scalanie gubi opis zaniku duplikatu, gdy obie miejscowości „nie istnieją”

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Localities/MergeLocality.php:102
- **Detail**: `'defunct_note' => $isDefunct ? ($target->defunct_note ?? $duplicate->defunct_note) : null` — gdy obie miejscowości mają różne opisy zaniku, opis duplikatu przepada przy usunięciu duplikatu. Tak zapisał to plan (Faza 1 §7), więc to wada planu, nie odchylenie — ale sprzeczna z intencją tej samej sekcji („Szczegóły duplikatu nie giną przy scalaniu”), a opis i przynależność w tej samej metodzie są łączone. Tekst dialogu (`merge-locality-dialog.tsx:76-78`) nie uprzedza o tym. Test „both keep the target note” utrwala obecne zachowanie.
- **Fix A ⭐ Recommended**: Gdy oba opisy zaniku są niepuste i różne, dopisać opis duplikatu do opisu miejscowości przez istniejący `mergedText()` (np. „Opis zaniku z „<nazwa duplikatu>”: …”); `defunct_note` celu bez zmian; test zaktualizowany.
  - Strength: Nic nie ginie, kolumna 255 znaków nie jest przekraczana, korzysta z tego samego helpera co opis i przynależność.
  - Tradeoff: Opis dostaje tekst wygenerowany przez system; zmiana świadomie decyzji planu.
  - Confidence: HIGH — kilka linii w jednej metodzie objętej transakcją i istniejącymi testami scalania.
  - Blind spot: Ewentualny limit `max:5000` opisu po połączeniu — ta sama sytuacja, którą plan już zaakceptował dla opisów.
- **Fix B**: Zostawić regułę i dopisać w `DialogDescription`, że opis zaniku duplikatu nie jest przenoszony, gdy cel ma własny.
  - Strength: Zero zmian w danych; użytkownik wie, co traci, przed potwierdzeniem.
  - Tradeoff: Utrata danych dalej możliwa; dialog robi się dłuższy dla rzadkiego przypadku.
  - Confidence: HIGH — zmiana tylko tekstu.
  - Blind spot: Użytkownik i tak nie widzi w dialogu treści obu opisów, żeby ocenić stratę.
- **Decision**: FIXED via Fix A — różny opis zaniku duplikatu dopisywany do Opisu celu („Opis zaniku z „<nazwa>”: …”), docblock `handle()` zaktualizowany, test `merging two defunct localities keeps a different duplicate note in the description` (2 przypadki); odstępstwo od Fazy 1 §7 planu świadome

### F3 — „52,21” zapisuje się jako punkt (52, 21) zamiast błędu formatu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Support/Coordinates.php:17
- **Detail**: Przecinek bez spacji jest separatorem, więc pojedyncza liczba z przecinkiem dziesiętnym (nawyk polskiej klawiatury) daje poprawną, ale błędną parę współrzędnych zamiast komunikatu „Wpisz współrzędne jako…”. Pary z przecinkiem dziesiętnym w obu liczbach (`50,0614, 19,9366`) są poprawnie odrzucane; plan wyłącza przecinek dziesiętny, ale nie przewidział tej dwuznaczności. Dataset w `LocalitiesTest` nie ma tego przypadku.
- **Fix**: Odrzucać parę liczb całkowitych rozdzielonych samym przecinkiem bez spacji (np. `52,21`) i dopisać ten przypadek do datasetu błędnych współrzędnych.
- **Decision**: FIXED — `Coordinates::parse` odrzuca dwie liczby całkowite rozdzielone samym przecinkiem; przypadek `52,21` w datasecie błędnych współrzędnych

### F4 — Martwe `errors.latitude` / `errors.longitude` w panelu

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/localities/locality-panel.tsx:252-256, 285-286
- **Detail**: Pole współrzędnych czyta błędy pod kluczami `latitude` i `longitude`, ale serwer waliduje wyłącznie `coordinates` (`LocalityValidationRules`), więc te gałęzie nigdy nie są aktywne. Dodatek spoza planu.
- **Fix**: Usunąć dwa dodatkowe `InputError` i warunki `errors.latitude`/`errors.longitude` w `aria-invalid`.
- **Decision**: FIXED — usunięte `InputError` i warunki `errors.latitude`/`errors.longitude`

### F5 — Test „współrzędne tylko jako para” nie sprawdza warunku pary

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/LocalitiesTest.php (test „merging takes the coordinates of the duplicate only as a pair”); app/Actions/Localities/MergeLocality.php:91-92
- **Detail**: Test pokrywa tylko pusty cel przejmujący pełną parę — to samo co poprzedni test scalania. Gałąź `$takesCoordinates` (cel z jedną współrzędną / duplikat z połową pary) nie jest wykonywana, więc zmiana warunku z `&&` na `||` nie złamałaby żadnego testu.
- **Fix**: Ustawić w teście połowę pary bezpośrednio przez `forceFill()->save()` (z pominięciem walidacji) i sprawdzić, że duplikat z połową pary nie nadpisuje celu, a cel z połową pary przejmuje pełną parę duplikatu.
- **Decision**: FIXED — test przerobiony na dataset (pusty cel, połowa pary duplikatu, cel z połową pary, cel z pełną parą); mutacja `&&`→`||` w `$takesCoordinates` daje czerwony test

## Triage (2026-10-20)

- Fixed: F1, F2 (Fix A), F3, F4, F5 (5)
- Po poprawkach: Pest Localities + Places 95/95, Pint ✓, Larastan 0 błędów, Vitest 372/372, `npm run check` ✓, `tsc` ✓.
