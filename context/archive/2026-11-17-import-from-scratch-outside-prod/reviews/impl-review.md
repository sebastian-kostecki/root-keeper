<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Import „od zera” poza produkcją

- **Plan**: context/changes/import-from-scratch-outside-prod/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2
- **Date**: 2026-11-17
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
| Success Criteria | WARNING |

Weryfikacja automatyczna (2026-11-17, worktree w kontenerze `root-keeper-laravel.test-1`):
- `ImportClearTreeTest` + `ImportResearchCommandTest`: 70 passed (632 assertions).
- Pint (`--test` na zmienionych plikach): passed.
- Larastan (`composer types:check`): No errors.
- Pełny zestaw: trzy przebiegi dały 38, 60 i 31 błędów, za każdym razem inne testy. Wszystkie błędy to błędy schematu (`relation "users" does not exist`, brak kolumn `repo_id`, `date_qualifier`, `two_factor_secret`, deadlock w `LocalitySeedMigrationTest`), czyli współbieżne użycie wspólnej bazy `testing`. Każdy z tych plików uruchomiony osobno przechodzi:
  - `ImportApplyTest` 119/119,
  - `ImportPlanTest` 128/128,
  - `TreeTest` 55/55,
  - `TreeProbandTest` 7/7,
  - `LocalitySeedMigrationTest` 6/6,
  - testy kopii i przywracania 28/28.
- Ręczne 2.6–2.8: nieodhaczone (oczekujące). Dla 2.8 jest już dowód: `import:research --help` pokazuje `--from-scratch  Poza produkcją najpierw usuń osoby i fakty drzewa konta (z potwierdzeniem)`.

## Findings

### F1 — Tabela przed usunięciem nie pokazuje wariantów nazwisk ani odpięcia probanta

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/ImportResearch.php:280-294 (`clearanceTable`), :149 (linia „Usunięto…”)
- **Detail**: Usunięcie osób zabiera kaskadą `person_surname_variants`, w tym warianty dodane ręcznie, których import nie odtworzy. Ustawia też `trees.root_person_id` na `null`, czyli probant znika. `ImportClearTreeTest` sprawdza oba skutki, ale tabela pokazywana przed pytaniem „tak/nie” nie ma o nich ani słowa. Plan też ich nie wymienił w liście wierszy (luka planu, nie odchylenie). Linia po usunięciu pomija zamieszkania, powiązania ze źródłami i pochowanych, choć tabela je pokazuje.
- **Fix**: Dodać wiersz „Warianty nazwisk” (nowe pole w `TreeClearance`, liczone przez `whereIn('person_id', $people)`) i linię „Probant zostanie odpięty.”, gdy `root_person_id` nie jest `null`. Linia „Usunięto…” ma pokazywać te same pozycje co tabela.
- **Decision**: FIXED — pola `surnameVariants` i `hasProband` w `TreeClearance`; wiersz „Warianty nazwisk”, linie o probancie, „Usunięto…” z tymi samymi pozycjami co tabela.

### F2 — Puste asercje w testach komendy (produkcja i pełny przebieg)

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: tests/Feature/ImportResearchCommandTest.php:1696, :1774-1775
- **Detail**: Plan wymaga w teście produkcji „brak raportu repo w wyjściu”. Asercja `doesntExpectOutputToContain('Raport')` niczego jednak nie sprawdza, bo słowo „Raport” nie występuje w komendzie; raport zaczyna się od „Odczyt repo badawczego dla konta …” (`ImportResearch.php:1381`). Test przeszedłby nawet wtedy, gdyby strażnik produkcji stał za odczytem repo. Pozostałe słabe miejsca w pełnym przebiegu:
  - `doesntExpectOutputToContain('Usunięte w aplikacji 1/')` sprawdza tekst pytania, którego zamockowany prompt nie wypisuje. Pokrycie i tak jest, bo nieoczekiwane pytanie wywraca mock.
  - `'Usunięto z drzewa konta autor@example.com:'` nie sprawdza liczb.
- **Fix**: Zmienić na `doesntExpectOutputToContain('Odczyt repo badawczego')`, w pełnym przebiegu dodać `expectsOutputToContain('Usunięte w aplikacji: 0 (do decyzji), 0 (zostawione wcześniej)')` i pełną linię „Usunięto…” z liczbami.
- **Decision**: FIXED — `doesntExpectOutputToContain('Odczyt repo badawczego')`, „Usunięte w aplikacji: 0 (do decyzji), 0 (zostawione wcześniej)” i pełna linia „Usunięto…” z liczbami; celowe przesunięcie strażnika za raport wywraca test.

### F3 — Cztery testy `ImportClearTreeTest` bez drugiego konta, z globalnymi licznikami

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: tests/Feature/ImportClearTreeTest.php:106, :116 (globalne `count()` w :129-131), :138, :178
- **Detail**: CLAUDE.md („Research repo import”): „Every import feature test has a second user with overlapping data”. Drugie konto z nakładającymi się danymi (`arrangeOverlappingClearableTree()`) mają tylko testy :87 i :152. Pozostałe cztery tworzą wyłącznie autora. Test :116 sprawdza `event_source`, `event_participants` i `buried_people` globalnym `count() === 0`. To przechodzi tylko dlatego, że drugiego konta nie ma, więc test nie wykryłby usunięcia zbyt szerokiego (bez zakresu drzewa).
- **Fix**: Wywołać `arrangeOverlappingClearableTree()` w tych czterech testach, a trzy liczniki zawęzić do osób i zdarzeń autora (lub sprawdzić, że liczniki drugiego konta są bez zmian).
- **Decision**: FIXED — `arrangeOverlappingClearableTree()` w czterech testach; globalne liczniki porównane z wierszami drugiego konta.

### F4 — Ścieżki po usunięciu: brak dopisku przy wyjątku, mylący komunikat przy pustym drzewie

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/ImportResearch.php:134-137, :153-258
- **Detail**:
  - Usunięcie jest zatwierdzone przed klasyfikacją, dopasowaniem i pytaniami; plan to akceptuje (plan.md:110). Dopisek „Osoby i fakty drzewa zostały już usunięte.” pojawia się jednak tylko na obsłużonych wyjściach. Gdy po usunięciu poleci nieprzechwycony wyjątek z `ClassifyRepoRecords`, `Match*` lub `decidePlaces`, drzewo zostaje puste bez żadnej informacji. Poza produkcją nie ma też kopii.
  - W gałęzi pustego drzewa komenda pisze „nie ma czego usuwać”, a potem `ClearTree` kasuje pamięć importu osób i faktów (także `deletion_kept`) i katalog zdjęć. `$cleared = true` sprawia, że przy odmowie zapisu użytkownik czyta „Osoby i fakty drzewa zostały już usunięte.”, choć żadnych nie było.
  - Gałęzie `needsTerminal($cleared)` i błąd kopii z `$cleared` są nieosiągalne przy `--from-scratch`, bo strażnicy produkcji i terminala odcinają je wcześniej. Są nieszkodliwe.
- **Fix**: W gałęzi pustego drzewa napisać „Drzewo … nie ma osób ani faktów; czyszczę pamięć importu osób i faktów (N).” i nie ustawiać `$cleared`. Resztę przebiegu po usunięciu objąć `try { … } catch (Throwable $e) { $this->error(withClearedNote(…)); throw $e; }`.
  - Strength: użytkownik zawsze wie, że drzewo jest już puste; komunikat pustego drzewa mówi prawdę.
  - Tradeoff: kolejny blok try wokół długiej metody `handle()`; Ctrl+C (SIGINT) i tak go nie wywoła.
  - Confidence: MED — prosta zmiana, ale `handle()` jest długa i ma wiele wyjść.
  - Blind spot: nie sprawdzono, czy któraś z Akcji dopasowania realnie rzuca nieprzechwycone wyjątki przy poprawnym odczycie.
- **Decision**: FIXED — komunikat pustego drzewa mówi o usuniętej pamięci importu (bez `$cleared`); `try/catch (Throwable)` od strażnika pustego repo do `ApplyImport` drukuje dopisek i rzuca dalej; nowy test z wyjątkiem z `ClassifyRepoRecords` (celowe usunięcie dopisku wywraca test).

### F5 — `--env=local` omija strażnika produkcji

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Console/Commands/ImportResearch.php:105
- **Detail**: `app()->isProduction()` zależy od nazwy środowiska, a globalna opcja Artisana `--env=local` zmienia ją przy uruchomieniu. Na serwerze produkcyjnym `import:research … --from-scratch --env=local` przejdzie strażnika na produkcyjnej bazie. Bez `.env.local` Laravel czyta `.env`. Ta sama flaga omija już dziś kopię bazy przed zapisem (:240), więc to świadome działanie operatora, nie przypadek; strażnik ma chronić przed pomyłką, a nie przed obejściem.
- **Fix**: Przyjąć jako znane ograniczenie i dopisać jedno zdanie w planie (sekcja „What We're NOT Doing”).
- **Decision**: FIXED — przyjęte jako ograniczenie, zdanie w planie („What We're NOT Doing”).

### F6 — Identyfikatory snapshotów czytane dwa razy i usuwane listą `whereIn`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: app/Actions/Import/ClearTree.php:27-32, app/Actions/Import/PlanTreeClearance.php:75, :84-90
- **Detail**: `ClearTree` wywołuje `plan->handle()`, które liczy `clearableSnapshotIds()`, a zaraz potem woła `clearableSnapshotIds()` drugi raz. Za każdym razem wczytuje wszystkie snapshoty drzewa do PHP. Liczba w raporcie i usunięte id pochodzą z dwóch odczytów, bez blokady. `whereIn('id', $ids)` wiąże jeden parametr na wiersz; przy rozmiarach drzewa genealogicznego to bez znaczenia.
- **Fix**: Policzyć id raz w `ClearTree` i użyć ich zarówno do liczby `snapshots`, jak i do usunięcia (np. `PlanTreeClearance::handle()` przyjmuje opcjonalnie gotowe id).
- **Decision**: FIXED — `ClearTree` liczy id snapshotów raz i przekazuje je do `PlanTreeClearance::handle()`.

### F7 — Pełny zestaw testów niestabilny przez wspólną bazę `testing`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: N/A (środowisko worktree, plan.md:145)
- **Detail**: Kryterium 2.3 (`php artisan test --compact`) w tym przeglądzie nie przeszło ani razu: 38, 60 i 31 błędów, za każdym razem inny zestaw. Wszystkie to błędy schematu lub deadlock, nie asercje; każdy dotknięty plik osobno przechodzi. Plan ostrzega, że baza `testing` jest wspólna z głównym checkoutem, a w tym czasie korzystała z niej inna sesja. Odhaczenie 2.3 przy 5e13329 jest wiarygodne, ale wynik pełnego zestawu w worktree nie jest powtarzalny.
- **Fix**: Uruchamiać pełny zestaw w worktree z osobną bazą (np. `DB_DATABASE=testing_wt` w `.env.testing` worktree, nieśledzonym) albo tylko wtedy, gdy nikt inny nie testuje.
- **Decision**: FIXED — osobna baza `testing_import_from_scratch` (`docker exec -e DB_DATABASE=…`), opis w planie; pełny zestaw: 1716 passed (9602 assertions).
