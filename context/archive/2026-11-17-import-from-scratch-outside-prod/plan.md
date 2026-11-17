# Import „od zera” poza produkcją — plan implementacji

## Overview

Dodajemy do komendy `import:research` opcję `--from-scratch` (roadmapa S-10, FR-020, nice-to-have). Opcja działa wyłącznie poza produkcją. Po odczycie repo komenda pokazuje tabelę tego, co zniknie z drzewa wskazanego konta, i pyta tak/nie z domyślnym „nie”. Po zgodzie w jednej transakcji usuwa:
- wszystkie osoby i fakty drzewa, razem z ich zależnościami,
- pamięć importu dla osób i faktów.

Dalej import przebiega dokładnie jak pierwszy import do pustego drzewa. Źródła, skany i miejsca zostają.

Cel: autor może powtarzać import na koncie testowym lub lokalnie bez ręcznego czyszczenia drzewa i bez setek pytań „usunięte w aplikacji — przywrócić?”.

## Current State Analysis

**Komenda** `app/Console/Commands/ImportResearch.php` (`import:research {email} {path}`, `:66-68`):
- `handle()` po kolei:
  - rozwiązuje konto i drzewo (`:79-96`),
  - czyta repo (`:98-104`) i drukuje raport (`:106`),
  - odmawia, gdy repo nie ma żadnej poprawnej osoby, a drzewo ma osoby z importu (`:108-112`),
  - klasyfikuje rekordy względem pamięci importu (`:114`), dopasowuje osoby, miejsca i źródła, planuje,
  - pyta o końcową zgodę (`:189`); na produkcji robi `backup:run --only-db` (`:195-200`); zapisuje przez `ApplyImport` (`:203`).
- Bez terminala komenda kończy się `needsTerminal()` (`:217-222`), gdy potrzebne są odpowiedzi.
- Odmowa końcowego zapisu drukuje „Nic nie zostało zapisane.” (`:190`); błąd zapisu drukuje to samo (`:207`).

**Model danych** (bez `SoftDeletes`, bez observerów; sprzątanie robią klucze obce i kod):
- `events` **nie mają FK do osób**, więc `$tree->people()->delete()` zostawiłoby zdarzenia bez uczestników. Zdarzenia trzeba usuwać jawnie przez `$tree->events()`. Kaskadą znikają wtedy `event_participants` i `event_source`.
- Usunięcie osób kaskadą zabiera:
  - `event_participants`,
  - `parent_child_relations`,
  - `residences`,
  - `person_surname_variants`,
  - `buried_people`.

  `trees.root_person_id` przechodzi na `null` (`nullOnDelete`).
- `sources`, `localities`, `parishes`, `cemeteries` mają FK *do* miejsc, a nie do osób i zdarzeń. Usunięcie osób i zdarzeń ich nie rusza. Źródła tracą tylko powiązania z faktami (`event_source`) i listy pochowanych (`buried_people`).
- Zdjęcia osób leżą na dysku `local` w `persons/{tree_id}/` (miniatury w `persons/{tree_id}/thumbnails/`; `app/Actions/Persons/StorePersonPhoto.php:31-35`). Masowe `delete()` w SQL zostawiłoby je jako sieroty.

**Pamięć importu** `repo_snapshots` (`tree_id`, `repo_id` jako klucz, `values`, `deletion_kept`; bez FK do rekordów):
- Klasyfikacja w `app/Actions/Import/ClassifyRepoRecords.php:286-295` (`stateOf`):
  - klucz z repo bez rekordu, ale ze snapshotem → „usunięte w aplikacji”,
  - z `deletion_kept = true` → zostawione na zawsze, bez pytania.
- Po usunięciu osób bez czyszczenia snapshotów każda osoba z repo dostałaby pytanie „Zostaw usunięte / Przywróć z repo” (`ImportResearch.php:299-316`). Wcześniejsze „Zostaw” blokowałyby ją po cichu.
- Rodzaj klucza rozpoznaje `RepoKeys::kindOf()` (`app/Support/Import/RepoKeys.php`):
  - osoba `O-0042`,
  - urodzenie, zgon, pochówek: `O-0042/urodzenie`, `/zgon`, `/pochowek`,
  - relacja `O-0084>O-0042`,
  - ślub `O-0001+O-0004/slub`,
  - źródło `ZR-0012`.
- Snapshoty źródeł (`ZR-`) muszą zostać. Dzięki nim zachowane źródła dalej są „znane”, trójstronne porównanie działa, a import podepnie je do nowych faktów.

**Środowisko:**
- `config/app.php:29`: `env('APP_ENV', 'production')`, więc brak zmiennej oznacza produkcję.
- Wartości w użyciu: `local`, `testing`, `production`; nie ma stagingu.
- `app()->isProduction()` to jedyny mechanizm (`ImportResearch.php:195`, `AppServiceProvider.php:40`).

**Wzorce do naśladowania:**
- `app/Actions/Persons/DeletePerson.php:25-49`: transakcja, pliki po commicie (`DB::afterCommit`), błąd usuwania pliku idzie przez `report(new RuntimeException(...))`, nie przerywa.
- `app/Http/Controllers/Settings/ProfileController.php:58-61`: `Storage::disk('local')->deleteDirectory("persons/{$treeId}")`.
- `app/Console/Commands/RestoreBackup.php:132-143`: tabela liczb (`$this->table`) + `confirm(..., false)` przed niszczącą operacją.

**Testy** (`tests/Feature/ImportResearchCommandTest.php`, 57 testów):
- Helpery:
  - `arrangeOverlappingUsers()` (`:40`),
  - `arrangeEmptyAuthorWithOverlappingOther()` (`:56`),
  - `fakeBackupRun()` (`:72`),
  - `saveQuestion()` (`:88`),
  - `importArguments()` (`:102`),
  - `importRepoInto()` (`:733`),
  - `arrangeImportedAuthors()` (`:748`),
  - `treeState()` (`:777`),
  - `ownershipSnapshot()` (`tests/Pest.php:148`).
- Produkcję symuluje `app()->detectEnvironment(fn (): string => 'production')` (`:259-274`).
- Bez terminala testy używają `Artisan::call(..., ['--no-interaction' => true])` + `Artisan::output()` (`:148-157`).

**Worktree:** zmiana powstaje w `.claude/worktrees/import-from-scratch-outside-prod` (gałąź `feat/import-from-scratch-outside-prod` z `develop`). Worktree nie ma `vendor/`, `node_modules/` ani `.env`. Kontener `root-keeper-laravel.test-1` montuje główne repo jako `/var/www/html`, więc worktree widać w nim pod `/var/www/html/.claude/worktrees/import-from-scratch-outside-prod`. Dowiązanie `vendor/` do głównego checkoutu nie działa: autoloader Composera rozwiązuje ścieżki względem prawdziwego katalogu i ładowałby `app/` z `develop`.

## Desired End State

- `import:research {email} {path} --from-scratch` poza produkcją, gdy drzewo ma osoby lub fakty:
  1. Komenda czyta repo i drukuje raport.
  2. Pokazuje tabelę: osoby, fakty, relacje rodzic–dziecko, zamieszkania, zdjęcia, powiązania faktów ze źródłami, pochowani na nagrobkach, pamięć importu (osoby i fakty).
  3. Pyta „Usunąć z drzewa konta {email} wszystkie osoby i fakty przed importem? …” z domyślnym „nie”.
  4. Po „tak” usuwa wszystko w jednej transakcji, po commicie usuwa katalog zdjęć drzewa i drukuje, co usunęła.
  5. Dalej idzie zwykły import, który klasyfikuje każdą osobę i fakt z repo jako nowe i nie pyta o przywracanie.
- Po „nie” na pytanie o usunięcie: nic nie zostaje usunięte ani zapisane, komenda kończy się sukcesem.
- Po usunięciu i odmowie końcowego zapisu (lub błędzie zapisu) komunikat mówi wprost, że osoby i fakty zostały już usunięte.
- Na produkcji `--from-scratch` kończy się błędem przed odczytem repo, bez żadnej zmiany i bez kopii bazy.
- Bez terminala (`--no-interaction`) `--from-scratch` kończy się błędem przed usunięciem.
- Repo bez żadnej poprawnej osoby: odmowa przed usunięciem (chroni przed niezamontowanym dyskiem).
- Drzewo bez osób i faktów: brak pytania o usunięcie, informacja, że nie ma czego usuwać; snapshoty osób i faktów i tak są czyszczone (żeby stary `deletion_kept` niczego nie blokował), a import idzie dalej.
- Dane innego konta (osoby, fakty, zdjęcia, pamięć importu) pozostają nietknięte. Źródła autora, ich pliki skanów, snapshoty `ZR-` i miejsca autora też zostają.

Weryfikacja: testy Akcji i komendy (niżej), Pint, Larastan oraz ręczny przebieg na koncie testowym.

### Key Discoveries:

- Zdarzenia bez FK do osób, więc kolejność jest zdarzenia → osoby; odwrotnie zostałyby zdarzenia bez uczestników (`database/migrations/2026_09_23_045723_create_events_table.php`, `2026_09_23_045724_create_event_participants_table.php:16-17`).
- Snapshoty do usunięcia to klucze, dla których `RepoKeys::kindOf($key) !== RepoKeyKind::Source`.
- Czyszczenie pamięci kasuje też zapamiętane tokeny miejsc (`miejsce_id`, `cmentarz_id`). Pewne dopasowania miejsc nie pytają, wątpliwe i nowe zapytają ponownie (`MatchResearchPlaces::needsDecision`, `app/Actions/Import/MatchResearchPlaces.php:340-347`). To oczekiwane.
- Strażnik `:108-112` po usunięciu przestaje chronić, bo drzewo nie ma osób z importu. Dlatego `--from-scratch` sam sprawdza pusty odczyt przed usunięciem.
- Import nie ustawia probanta (`ImportResearch.php:1294-1304`). Po usunięciu `root_person_id` jest `null`, a komenda po zapisie już dziś podpowiada wybór probanta.

## What We're NOT Doing

- Nie usuwamy źródeł, plików skanów, snapshotów źródeł (`ZR-`) ani miejsc (miejscowości, parafie, cmentarze, aliasy). Źródła dodane ręcznie tracą powiązania z faktami. Wszystkie źródła (także z importu) tracą listy pochowanych, bo `buried_people` powstaje tylko ręcznie w aplikacji i import go nie odtworzy (`WriteResearchSources.php:665` tylko je przekazuje); tabela przed pytaniem pokazuje ich liczbę. Tak to zostaje.
- Nie sprzątamy miejsc, które po usunięciu faktów przestały być używane.
- Nie dajemy `--force` ani innego sposobu pominięcia potwierdzenia; opcja wymaga terminala.
- Nie wymagamy przepisywania e-maila jako potwierdzenia.
- Nie robimy kopii bazy poza produkcją (na produkcji opcja jest niedostępna).
- Nie bronimy się przed świadomym `--env=local` na serwerze produkcyjnym. Strażnik opiera się na `app()->isProduction()` i chroni przed pomyłką, nie przed celowym obejściem. Ta sama flaga omija już dziś kopię bazy przed zapisem zwykłego importu.
- Nie robimy usunięcia „wirtualnego” (podgląd na udawanym pustym drzewie i usunięcie razem z zapisem). Odmowa końcowego zapisu zostawia puste drzewo.
- Nie zmieniamy klasyfikacji, dopasowania, planowania ani zapisu importu (`ClassifyRepoRecords`, `Match*`, `PlanImport`, `ApplyImport`).
- Bez zmian w UI, trasach i migracjach.

## Implementation Approach

Podział decide/apply jak w reszcie importu (CLAUDE.md, „Research repo import”):
- `PlanTreeClearance` tylko liczy, co zniknie.
- `ClearTree` usuwa w jednej transakcji i zwraca te same liczby.
- Komenda jedynie sprawdza warunki, drukuje, pyta i wywołuje Akcje.

Usunięcie następuje zaraz po potwierdzeniu, przed klasyfikacją. Dzięki temu cała dalsza ścieżka importu działa bez zmian na prawdziwie pustym drzewie.

Strażnik produkcji to `app()->isProduction()`, sprawdzany przed odczytem repo. Jest bezpieczny, bo brak `APP_ENV` też oznacza produkcję.

## Critical Implementation Details

**Kolejność w komendzie:** konto i drzewo → strażnik produkcji → odczyt repo i raport → strażnik terminala → strażnik pustego odczytu → tabela, pytanie, `ClearTree` → istniejąca ścieżka od `:108`.

Strażnik produkcji musi stać przed odczytem repo: na produkcji nic nie może się zdarzyć, nawet raport. Strażnik terminala i pustego repo muszą stać przed jakimkolwiek zapisem.

**Pliki po commicie:** `deleteDirectory("persons/{tree_id}")` w `DB::afterCommit`, a niepowodzenie przez `report()`, jak w `DeletePerson`. Wycofana transakcja nie może skasować zdjęć.

## Przygotowanie worktree (przed Fazą 1, jednorazowo)

Wszystkie polecenia uruchamiaj w działającym kontenerze głównego Sail z katalogiem roboczym worktree. Dalej skrót `WT`:

```bash
WT="docker exec -u sail -w /var/www/html/.claude/worktrees/import-from-scratch-outside-prod root-keeper-laravel.test-1"
cp /home/sebastian/my-projects/root-keeper/.env /home/sebastian/my-projects/root-keeper/.claude/worktrees/import-from-scratch-outside-prod/.env
$WT composer install --no-interaction
```

- Zmiana nie dodaje paczek, więc `composer.lock` jest ten sam co na `develop`.
- `.env` jest w `.gitignore`.
- Baza `testing` jest wspólna z głównym checkoutem i innymi worktree; równoległe przebiegi psują sobie schemat (brak tabel lub kolumn, deadlocki). Pełny zestaw w worktree uruchamiaj na osobnej bazie. `phpunit.xml` ustawia `DB_DATABASE` bez `force`, więc zmienna z `docker exec -e` wygrywa:
  ```bash
  docker exec root-keeper-pgsql-1 psql -U sail -d postgres -c "CREATE DATABASE testing_import_from_scratch"
  docker exec -e DB_DATABASE=testing_import_from_scratch -u sail -w /var/www/html/.claude/worktrees/import-from-scratch-outside-prod root-keeper-laravel.test-1 php artisan test --compact
  ```
  Po zamknięciu worktree: `DROP DATABASE testing_import_from_scratch`.
- `node_modules/` nie jest potrzebne, bo zmiana nie dotyka frontendu.

## Faza 1: Akcje czyszczenia drzewa

### Overview

Dwie Akcje i klasa wartości: policzenie i usunięcie osób i faktów drzewa wraz z pamięcią importu osób i faktów oraz plikami zdjęć. Bez zmian w komendzie.

### Changes Required:

#### 1. Liczby czyszczenia

**File**: `app/Support/Import/TreeClearance.php` (nowy)

**Intent**: Niezmienna klasa wartości z liczbami tego, co znika. Komenda drukuje z niej tabelę przed pytaniem i podsumowanie po usunięciu.

**Contract**: `final readonly class TreeClearance` z polami `int`: `persons`, `events`, `relations`, `residences`, `photos` (osoby z `photo_path`), `sourceLinks` (wiersze `event_source` faktów drzewa), `buriedLinks` (wiersze `buried_people` osób drzewa), `snapshots` (pamięć importu spoza `ZR-`). Metoda `hasRecords(): bool` zwraca prawdę, gdy `persons + events > 0`. Rodzeństwo: pozostałe klasy w `app/Support/Import/`.

#### 2. Plan czyszczenia (bez zapisu)

**File**: `app/Actions/Import/PlanTreeClearance.php` (nowy)

**Intent**: Policzyć dla drzewa, co usunie `ClearTree`, żeby komenda mogła pokazać tabelę przed pytaniem. Niczego nie zapisuje.

**Contract**: `handle(Tree $tree): TreeClearance`. Każde liczenie idzie przez drzewo:
- `$tree->people()`, `$tree->events()`, `$tree->parentChildRelations()`, `$tree->repoSnapshots()`;
- zamieszkania, powiązania ze źródłami i pochowani (`buried_people`) przez podzapytanie na osobach lub zdarzeniach drzewa, nigdy przez globalne zapytanie bez drzewa;
- snapshoty: te, których `RepoKeys::kindOf(repo_id) !== RepoKeyKind::Source`.

#### 3. Usunięcie

**File**: `app/Actions/Import/ClearTree.php` (nowy)

**Intent**: Usunąć w jednej transakcji wszystkie fakty, wszystkie osoby i pamięć importu osób i faktów drzewa. Po commicie usunąć katalog zdjęć drzewa. Źródła, skany, snapshoty `ZR-` i miejsca zostają.

**Contract**: `handle(Tree $tree): TreeClearance`. Zwraca liczby policzone w tej samej transakcji, przed usunięciem.

Kolejność w transakcji:
1. `$tree->events()->delete()`
2. `$tree->people()->delete()`
3. usunięcie snapshotów drzewa spoza `ZR-`, z tym samym kryterium co w `PlanTreeClearance`

Kaskady bazy zabierają relacje, zamieszkania, warianty nazwisk, `event_participants`, `event_source` i `buried_people`, a `root_person_id` przechodzi na `null`.

Po commicie: `Storage::disk('local')->deleteDirectory("persons/{$tree->id}")`. Niepowodzenie zgłasza `report(new RuntimeException(...))` i nie przerywa.

#### 4. Testy Akcji

**File**: `tests/Feature/ImportClearTreeTest.php` (nowy, `vendor/bin/sail artisan make:test --pest`, tu przez `$WT php artisan make:test ImportClearTreeTest --pest --no-interaction`)

**Intent**: Sprawdzić bezpośrednio liczby i skutki usunięcia, z drugim użytkownikiem o nakładających się danych (imiona, miejsca, klucze repo). Wcześniej przeczytać skill `testing-best-practices`.

**Contract**: Przypadki:
- `PlanTreeClearance` zwraca poprawne liczby i niczego nie zmienia (`ownershipSnapshot()` bez zmian).
- `ClearTree` u autora usuwa:
  - osoby i fakty (także ręczne, bez `repo_id`),
  - relacje, zamieszkania, warianty nazwisk,
  - powiązania faktów ze źródłami i wiersze `buried_people` (liczba równa `buriedLinks`),
  - snapshoty osób, faktów, relacji i ślubów, także te z `deletion_kept = true`.

  `root_person_id` przechodzi na `null`, a katalog `persons/{tree_id}` znika z `Storage::fake('local')`.
- Zostają:
  - źródła autora (importowane i ręczne) i ich pliki w `sources/{tree_id}`,
  - snapshoty `ZR-`,
  - miejscowości, parafie i cmentarze.
- Drugie konto: osoby, fakty, relacje, pamięć importu i pliki zdjęć bez zmian.
- Wycofanie: test wywołuje `ClearTree` wewnątrz zewnętrznego `DB::transaction` i rzuca wyjątek po `handle()`. Wewnętrzna transakcja jest savepointem, więc callback `afterCommit` przepada. Osoby, zdarzenia, snapshoty i pliki `persons/{tree_id}` zostają bez zmian. Bez punktów wstrzykiwania błędu w kodzie produkcyjnym.

### Success Criteria:

#### Automated Verification:

- Testy Akcji przechodzą: `$WT php artisan test --compact tests/Feature/ImportClearTreeTest.php`
- Istniejące testy importu przechodzą: `$WT php artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php`
- Pint bez zmian do zrobienia: `$WT vendor/bin/pint --dirty --format agent`
- Larastan bez nowych błędów: `$WT composer types:check`

**Implementation Note**: Po przejściu weryfikacji automatycznej przejdź do Fazy 2. Faza 1 nie ma kroków ręcznych.

---

## Faza 2: Opcja `--from-scratch` w komendzie

### Overview

Podłączenie Akcji do `import:research`: opcja w sygnaturze, strażnicy, tabela, pytanie, usunięcie, uczciwe komunikaty po odmowie zapisu, testy komendy.

### Changes Required:

#### 1. Sygnatura i przebieg

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Dodać opcję i jej przebieg, zostawiając zwykły import bez zmian, gdy opcji nie podano. Komenda tylko sprawdza, drukuje i pyta, a liczenie i usuwanie robią Akcje.

**Contract**:
- Sygnatura: `{--from-scratch : Poza produkcją najpierw usuń osoby i fakty drzewa konta (z potwierdzeniem)}`.
- `handle()` dostaje `PlanTreeClearance` i `ClearTree` przez wstrzyknięcie.
- Przy `--from-scratch`, w kolejności z „Critical Implementation Details”:
  1. **Produkcja:** zaraz po rozwiązaniu konta i drzewa, przed `ReadResearchRepo`, błąd „Import od zera jest niedostępny na produkcji; nic nie zostało zmienione.” i `FAILURE`.
  2. **Brak terminala** (`! $this->input->isInteractive()`): po raporcie, przed usunięciem, `needsTerminal()`.
  3. **Pusty odczyt** (`$reading->persons === []`): błąd „Repo nie zawiera żadnej poprawnej osoby; nic nie zostało usunięte. Sprawdź ścieżkę i czy dysk jest zamontowany.” i `FAILURE`.
  4. **Drzewo bez osób i faktów** (`! hasRecords()`): informacja „Drzewo konta {email} nie ma osób ani faktów — nie ma czego usuwać.”, potem `ClearTree` bez pytania (czyści starą pamięć osób i faktów), dalej import.
  5. **W przeciwnym razie:** `$this->table(['Do usunięcia', 'Liczba'], …)` z wierszami Osoby, Fakty, Relacje rodzic–dziecko, Zamieszkania, Zdjęcia osób, Powiązania faktów ze źródłami, Pochowani na nagrobkach (nie wrócą z importu), Pamięć importu (osoby i fakty). Do tego linia „Zostają: źródła ze skanami i miejsca.” Potem `confirm("Usunąć z drzewa konta {$email} wszystkie osoby i fakty przed importem?", false)`.
     - Po „nie”: „Nic nie zostało usunięte ani zapisane.” i `SUCCESS`.
     - Po „tak”: `ClearTree`, potem linia „Usunięto z drzewa konta {email}: osoby N, fakty M, relacje R, zdjęcia Z, pamięć importu P.”
  6. Dalej istniejąca ścieżka od strażnika `:108` bez zmian.
- Po wykonanym usunięciu dwa istniejące komunikaty dostają dopisek „Osoby i fakty drzewa zostały już usunięte.”:
  - odmowa końcowego zapisu (`:190`),
  - błąd zapisu (`:207`).

  Dopisek też przy błędzie kopii, choć poza produkcją go nie ma, oraz przy `needsTerminal()` i błędzie planu po usunięciu. Prosty sposób: flaga lokalna w `handle()` i jedna metoda pomocnicza dopisująca zdanie.

#### 2. Testy komendy

**File**: `tests/Feature/ImportResearchCommandTest.php`

**Intent**: Pokryć przebieg komendy z drugim użytkownikiem o nakładających się danych, korzystając z istniejących helperów.

**Contract**: Nowe testy (helper `clearQuestion(string $email)` dla tekstu pytania o usunięcie, analogicznie do `saveQuestion()`):
- **Produkcja:** `detectEnvironment('production')` + `fakeBackupRun(0)`, `--from-scratch`. Komenda kończy się błędem z komunikatem o produkcji, `ownershipSnapshot()` bez zmian, `backup:run` niewywołany, brak raportu repo w wyjściu.
- **Bez terminala:** `Artisan::call(..., ['--from-scratch' => true, '--no-interaction' => true])` na `arrangeImportedAuthors()`. Błąd, `ownershipSnapshot()` bez zmian.
- **Pusty odczyt:** tymczasowe repo bez poprawnej osoby (`commandTemporaryRepo`). Błąd „nic nie zostało usunięte”, `ownershipSnapshot()` bez zmian.
- **„Nie” na pytanie o usunięcie:** `expectsConfirmation(clearQuestion(...), 'no')`. Sukces, `ownershipSnapshot()` bez zmian.
- **Pełny przebieg:**
  - Przygotowanie: `cmdSrcImported()` (`:1390`; dwa konta, źródła `ZR-0001` ze skanem i urzędem, `ZR-0003`, miejscowość Lipnica). U autora dodatkowo ręczna osoba ze zdjęciem i fakt oraz wiersz `buried_people` przy jednym źródle; u drugiego konta zdjęcie osoby.
  - Przebieg: `--from-scratch` → tabela (`expectsTable` z liczbami) → „tak” → zwykłe pytania pierwszego importu (bez pytań „Usunięte w aplikacji”) → „tak” na zapis.
  - Porównanie ze wzorcem przez nowy helper `normalizedTreeState(Tree $tree)` (nie surowe `treeState()`, bo ma `id`, `tree_id` i znaczniki czasu): osoby i zdarzenia jako `repo_id` → wartości bez `id`, `tree_id`, `created_at`, `updated_at`; relacje jako pary `repo_id` rodzica i dziecka; `event_source` jako pary (`repo_id` zdarzenia, `repo_id` źródła); snapshoty jako `repo_id` → `values` i `deletion_kept`. Wzorzec: `normalizedTreeState()` autora zaraz po `cmdSrcImported()` (to jest stan po pierwszym imporcie do pustego drzewa), zapisany przed dodaniem ręcznej osoby, faktu i `buried_people`. To samo drzewo, więc `locality_id`, `parish_id`, `source_id` i tokeny miejsc w snapshotach (`miejsce_id`, `parafia_id`) mają te same id; różnią się tylko id osób i zdarzeń, które normalizacja pomija. Wynik autora po `--from-scratch` równa się wzorcowi.
  - Ręczna osoba, jej zdjęcie i wiersz `buried_people` zniknęły. Źródła autora: te same `id` wierszy i te same pliki na dysku, powiązania z faktami z repo odtworzone.
  - `cmdSrcState()` i pliki drugiego konta bez zmian.
  - Dokładne pytania o miejsca ustal przy implementacji z wyjścia; nie poprawiaj asercji pod inne zachowanie niż opisane w planie.
- **Usunięcie, potem „nie” na zapis:** komunikat z dopiskiem „Osoby i fakty drzewa zostały już usunięte.”, drzewo autora bez osób i faktów, drugie konto bez zmian.
- **Drzewo bez osób i faktów:** `arrangeEmptyAuthorWithOverlappingOther()` + `--from-scratch`. Brak pytania o usunięcie, informacja „nie ma czego usuwać”, dalej zwykły pierwszy import.

### Success Criteria:

#### Automated Verification:

- Testy komendy przechodzą: `$WT php artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- Wszystkie testy importu przechodzą: `$WT php artisan test --compact --filter=Import`
- Pełny zestaw testów przechodzi: `$WT php artisan test --compact`
- Pint bez zmian do zrobienia: `$WT vendor/bin/pint --dirty --format agent`
- Larastan bez nowych błędów: `$WT composer types:check`

#### Manual Verification:

- Lokalnie na koncie testowym `--from-scratch` z „tak”: sensowna tabela, brak pytań „Usunięte w aplikacji”, drzewo jak po pierwszym imporcie, źródła podpięte
- Odpowiedź „nie” na pytanie o usunięcie zostawia drzewo bez zmian
- `import:research --help` pokazuje `--from-scratch` z polskim opisem

Przebieg ręczny (`APP_ENV=local`, konto testowe z wcześniejszym importem, z worktree albo z głównego checkoutu po scaleniu): `import:research <e-mail testowy> <repo> --from-scratch`; drzewo i widok przodków porównaj z wynikiem pierwszego importu, źródło ze skanem sprawdź na karcie faktu.

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie autora.

---

## Testing Strategy

### Unit Tests:

- `ImportClearTreeTest`:
  - liczby `PlanTreeClearance` bez zapisu,
  - skutki `ClearTree` (co znika, co zostaje),
  - drugie konto nietknięte,
  - wycofana transakcja nie kasuje zdjęć.

### Integration Tests:

- `ImportResearchCommandTest`:
  - strażnicy: produkcja, terminal, puste repo,
  - odmowa usunięcia,
  - pełny przebieg od zera z porównaniem do pierwszego importu,
  - usunięcie + odmowa zapisu,
  - drzewo bez osób i faktów.
- Każdy test z drugim kontem o nakładających się danych (wymóg CLAUDE.md dla importu).

### Manual Testing Steps:

1. Na koncie testowym z importem uruchom `--from-scratch`, sprawdź tabelę i odpowiedz „tak”.
2. Przejdź import, zatwierdź zapis, obejrzyj drzewo, widok przodków i źródło ze skanem.
3. Uruchom ponownie zwykły import (bez opcji): powinno być „Nic do zapisania.” albo tylko oczekiwane zmiany.

## Performance Considerations

Usuwanie to trzy zapytania `DELETE` na drzewo (kaskady w bazie) i jedno usunięcie katalogu. Przy setkach osób nie ma znaczenia.

## Migration Notes

Brak migracji i zmian schematu. Opcja jest dodatkowa; bez niej komenda działa jak dotąd.

## References

- Wymaganie: `context/foundation/prd.md:131` (FR-020), `:116` (FR-007, czyszczenie tylko poza produkcją), `:182`
- Roadmapa: `context/foundation/roadmap.md:206-215` (S-10)
- Komenda: `app/Console/Commands/ImportResearch.php:66-215`
- Klasyfikacja: `app/Actions/Import/ClassifyRepoRecords.php:286-295`; klucze `app/Support/Import/RepoKeys.php`
- Wzorce usuwania: `app/Actions/Persons/DeletePerson.php:25-49`, `app/Http/Controllers/Settings/ProfileController.php:58-61`, `app/Console/Commands/RestoreBackup.php:132-143`
- Testy: `tests/Feature/ImportResearchCommandTest.php` (helpery `:40-102`, `:674-777`; produkcja `:259-274`), `tests/Pest.php:148`
- Poprzedni plan importu: `context/archive/2026-10-23-first-import-into-empty-tree/plan.md`

## Poprawki z przeglądu implementacji

Raport: `reviews/impl-review.md` (2026-11-17). Odstępstwa od opisu wyżej:
- `TreeClearance` ma dwa nowe pola: `surnameVariants` i `hasProband`.
  - Tabela przed pytaniem ma wiersz „Warianty nazwisk”, a gdy drzewo ma probanta, linię „Probant zostanie odpięty (osoby znikają razem z nim).”.
  - Linia „Usunięto…” wymienia te same pozycje co tabela, a po niej pada „Probant został odpięty; wybierz go ponownie po imporcie.”.
- `PlanTreeClearance::handle()` przyjmuje opcjonalnie gotowe id snapshotów. `ClearTree` liczy je raz i używa ich do liczby i do usunięcia.
- Drzewo bez osób i faktów, ale ze starą pamięcią importu: zamiast „nie ma czego usuwać” komunikat „…; usunięto tylko pamięć importu osób i faktów: N.”, bez dopisku „już usunięte” przy dalszych odmowach.
- Wyjątek, który wyleci po usunięciu, a przed zapisem, drukuje „Import przerwany błędem; nic nie zostało zapisane. Osoby i fakty drzewa zostały już usunięte.” i leci dalej.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Akcje czyszczenia drzewa

#### Automated

- [x] 1.1 Testy Akcji przechodzą: `$WT php artisan test --compact tests/Feature/ImportClearTreeTest.php` — 2ec3efb
- [x] 1.2 Istniejące testy importu przechodzą: `$WT php artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php` — 2ec3efb
- [x] 1.3 Pint bez zmian do zrobienia: `$WT vendor/bin/pint --dirty --format agent` — 2ec3efb
- [x] 1.4 Larastan bez nowych błędów: `$WT composer types:check` — 2ec3efb

### Phase 2: Opcja `--from-scratch` w komendzie

#### Automated

- [x] 2.1 Testy komendy przechodzą: `$WT php artisan test --compact tests/Feature/ImportResearchCommandTest.php` — 5e13329
- [x] 2.2 Wszystkie testy importu przechodzą: `$WT php artisan test --compact --filter=Import` — 5e13329
- [x] 2.3 Pełny zestaw testów przechodzi: `$WT php artisan test --compact` — 5e13329
- [x] 2.4 Pint bez zmian do zrobienia: `$WT vendor/bin/pint --dirty --format agent` — 5e13329
- [x] 2.5 Larastan bez nowych błędów: `$WT composer types:check` — 5e13329

#### Manual

- [ ] 2.6 Lokalnie na koncie testowym `--from-scratch` z „tak”: sensowna tabela, brak pytań „Usunięte w aplikacji”, drzewo jak po pierwszym imporcie, źródła podpięte
- [ ] 2.7 Odpowiedź „nie” na pytanie o usunięcie zostawia drzewo bez zmian
- [ ] 2.8 `import:research --help` pokazuje `--from-scratch` z polskim opisem
