# Test Plan

> Phased test rollout for this project. Strategy is frozen at the top
> (§1–§5); cookbook patterns at the bottom (§6) fill in as phases ship.
> Read before writing any new test.
>
> Refresh: re-run `/10x-test-plan --refresh` when stale (see §8).
>
> Last updated: 2026-10-01

Treść planu jest po polsku; wartości statusu w §3 zostają po angielsku, bo czyta je parser orkiestratora.

## 1. Strategy

Testy w tym projekcie stosują trzy nienegocjowalne zasady:

1. **Koszt × sygnał.** Wygrywa najtańszy test, który daje prawdziwy sygnał dla ryzyka. Nie awansuj do e2e, bo e2e „wydaje się bezpieczniejsze”. Nie kładź modelu wizji na deterministycznym porównaniu, które już łapie regresję.
2. **Obawy użytkownika są dowodem pierwszej klasy.** Ryzyka zakotwiczone w „autor obawia się X, a awaria ujawniłaby się gdzieś w <obszarze>” mają tę samą wagę co linie PRD czy dane o hot-spotach.
3. **Risks are scenarios, not code locations.** Ten plan opisuje, *co może zawieść* i *dlaczego uważamy to za prawdopodobne* — na podstawie dokumentów, wywiadu i *sygnału* z kodu (częstotliwość zmian, struktura, baza testów). NIE twierdzi, że wie, która linia odpowiada za awarię. Tę wiedzę wytwarza `/10x-research` w każdej fazie wdrożenia. Jeśli plan i badanie nie zgadzają się co do miejsca awarii, prawdą jest badanie.

Hot-spot scope used for likelihood weighting: `app/`, `resources/js/` (bez `resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`, `resources/js/components/ui`), `routes/`, `database/`, `scripts/` — 98 commitów w ostatnich 30 dniach (stan 2026-09-30).

## 2. Risk Map

Najważniejsze scenariusze awarii, uporządkowane według wpływ × prawdopodobieństwo. Kolumna Source podaje *dowód, który podniósł ryzyko* — nigdy plik jako „miejsce awarii” (patrz §1, zasada 3).

| # | Risk (failure scenario) | Impact | Likelihood | Source (evidence — not anchor) |
|---|---|---|---|---|
| 1 | Fakt, akt lub relacja zapisuje się przy **niewłaściwej osobie** (dziecko przy złej parze, rodzic w złym slocie, źródło przy innej osobie) albo potwierdzenie faktu znika po cichu po edycji — drzewo przestaje odpowiadać prawdzie. | High | High | wywiad P1 („przypisane innej osobie”), P3 (modal osoby i drzewo przebudowywane najczęściej); prd v4 FR-004 – FR-006, prd-v3 FR-002; hot-spot dir `resources/js/components/persons` — 120 zmian/30d, `resources/js/components/tree` — 45, `app/Actions/Relations` — 16; 20 z 98 commitów w zakresie to poprawki |
| 2 | Kopia zapasowa nie obejmuje wszystkich danych (w tym plików źródeł po zmianie skany → źródła) albo **odtworzenie** na czystej instancji nie przywraca kompletnych danych z czytelnymi plikami. | High | Medium | wywiad P1 (utrata danych, skanów), P2 (utrata dostępu po wygaśnięciu licencji), P4 (odtworzenie przećwiczone raz ręcznie, przed zmianą na źródła); PRD v1 NFR (RPO 24 h) i Guardrails („dane i skany nie giną”); archive `2026-09-22-production-data-safety/plan.md`, `2026-09-29-scans-become-sources/plan.md` (zmiana nazw tabel i katalogu plików) |
| 3 | Usunięcie osoby, źródła, relacji lub małżeństwa **kasuje więcej**, niż pokazało potwierdzenie, a produkt nie ma kosza ani cofania. | High | Medium | wywiad P1 („przypadkowe utracenie danych”); PRD v1 FR-007, FR-015 i Non-Goals (brak kosza i cofania); prd-v3 FR-011; prd v4 FR-013; archive `2026-09-25-person-edit-and-delete/plan.md`, `2026-09-25-scan-delete-with-impact/plan.md` |
| 4 | *(nadużycie)* Inny zarejestrowany użytkownik odczytuje lub zmienia moje osoby, źródła albo pliki, podając id z mojego drzewa w adresie **lub w treści żądania** (np. akcje z drzewa przyjmujące wskazaną osobę). | High | Medium | PRD v1 Access Control (otwarta rejestracja) i Guardrails; prd v4 Access Control („cudza osoba to 404”); hot-spot dir `app/Http/Requests` — 30 zmian/30d, `app/Policies` — 10 |
| 5 | Reguły relacji da się obejść poza filtrem wyboru — trzeci rodzic, cykl przodków, kobieta w slocie ojca — i drzewo „kłamie”. | Medium | Medium | wywiad P1 (dane nieprawdziwe w odniesieniu do osoby); prd v4 FR-011 i Guardrails; prd-v3 FR-012; hot-spot dir `app/Actions/Relations` — 16 zmian/30d |

### Risk Response Guidance

| Risk | What would prove protection | Must challenge | Context `/10x-research` must ground | Likely cheapest layer | Anti-pattern to avoid |
|------|-----------------------------|----------------|--------------------------------------|-----------------------|-----------------------|
| #1 | Po akcji z drzewa lub modala w bazie zmieniła się dokładnie wskazana osoba / para, inne osoby są nietknięte, a stan „potwierdzone” pozostałych faktów się nie zmienił. | „Backend ma testy, więc przebudowa UI nie pomyli osób” — UI może wysłać złe id osoby lub pary. | Skąd UI bierze id osoby i pary (przełącznik związku, historia „Wstecz” w modalu); kształt żądań z drzewa i modala; które reguły wyboru pary są czystą logiką po stronie klienta. | integracja (Pest feature) na kontrakcie żądania; unit (Vitest) dla czystej logiki wyboru pary / historii modala | asercja tylko na kodzie odpowiedzi zamiast na stanie danych; oczekiwana wartość przepisana z implementacji (problem wyroczni) |
| #2 | Archiwum kopii zawiera bazę i każdy plik źródła (oryginał i miniaturę); odtworzenie na czystej instancji daje tę samą liczbę osób, faktów i powiązań, a pliki się otwierają. | „Komenda backupu zakończyła się bez błędu, więc kopia jest kompletna.” | Konfiguracja kopii i ścieżki plików po zmianie nazw; procedura odtworzenia z F-01; istniejące testy harmonogramu kopii; co z tego da się sprawdzić automatycznie, a co wymaga drillu na serwerze. | integracja (kopia na dysk tymczasowy + sprawdzenie zawartości) oraz spisany, powtarzalny drill odtworzenia (manual smoke) | test sprawdzający tylko, że komenda jest zaplanowana; drill bez porównania liczności danych przed i po |
| #3 | Zbiór faktycznie usuniętych lub odłączonych rekordów i plików jest równy liście skutków pokazanej w potwierdzeniu; dane współmałżonka i innych osób zostają. | „Kaskada w bazie usuwa tylko to, co trzeba.” | Kaskady i klucze obce; skąd pochodzi lista skutków; usuwanie plików z dysku. | integracja (Pest feature) | porównanie listy skutków z tym samym zapytaniem, które ją liczy (tautologia) |
| #4 | Każdy endpoint odczytu i zapisu z cudzym id — w adresie i w treści — kończy się 404 i niczego nie zapisuje; plik cudzego źródła nie jest serwowany. | „Wiązanie modelu z trasy i polityka wystarczą” — id w treści żądania ich omija. | Pełna lista tras; które przyjmują id osób, par lub źródeł w treści; jak serwowane są pliki. | integracja (Pest feature, dataset po trasach) | sprawdzenie jednej trasy „na próbę”; oczekiwanie 403 zamiast 404 |
| #5 | Serwer odrzuca trzeciego rodzica, cykl i niezgodną płeć na każdej ścieżce zapisu (nowa i istniejąca osoba, z drzewa i z podglądu), niezależnie od filtra w UI. | „Wybór ukrywa zakazane osoby, więc serwer nie musi sprawdzać.” | Gdzie reguły są egzekwowane; czy wszystkie ścieżki tworzenia relacji przechodzą tę samą walidację. | integracja (Pest feature) | testowanie tylko filtra wyboru; tylko ścieżka szczęśliwa |

## 3. Phased Rollout

Każdy wiersz to osobna faza wdrożenia, która otwiera własny folder zmiany przez `/10x-new`. Status przesuwa się od lewej do prawej; orkiestrator aktualizuje go, gdy na dysku pojawiają się artefakty.

| # | Phase name | Goal (one line) | Risks covered | Test types | Status | Change folder |
|---|---|---|---|---|---|---|
| 1 | Kopia i odtworzenie | Udowodnić, że kopia obejmuje bazę i wszystkie pliki źródeł, a odtworzenie na czystej instancji przywraca kompletne dane (kolejność: decyzja autora, wywiad P1/P4) | #2 | integration + manual smoke (drill odtworzenia) | complete | `context/archive/2026-10-01-testing-backup-restore/` (zarchiwizowana) |
| 2 | Zapis i usuwanie trafiają we właściwe dane | Udowodnić, że akcje z drzewa i modala zmieniają dokładnie wskazaną osobę lub parę, a usunięcia kasują dokładnie to, co pokazało potwierdzenie | #1, #3 | integration + unit | change opened | `context/changes/testing-write-delete-targets/` |
| 3 | Granice własności i reguły relacji | Udowodnić, że cudze id (w adresie i w treści) daje 404 bez zapisu, a reguły relacji egzekwuje serwer na każdej ścieżce | #4, #5 | integration | complete | `context/archive/2026-10-02-testing-ownership-boundaries/`, `context/archive/2026-10-02-testing-relation-rules/` (zarchiwizowane) |
| 4 | Przepływy drzewa i modala w przeglądarce | 1–2 krytyczne przepływy (np. v4 US-01: istniejący ojciec z pustego slotu) z asercją na danych, nie na wyglądzie — tylko jeśli badanie fazy 2 pokaże, że tańsze warstwy nie łapią pomyłki osoby w UI | #1 (połowa UI) | e2e | not started | — |

## 4. Stack

| Layer | Tool | Version | Notes |
|---|---|---|---|
| backend integration + unit | Pest (PHPUnit) + pest-plugin-laravel | 5.2 / 13.3 | ~23 pliki, ~330 przypadków, głównie `tests/Feature`; w CI na PostgreSQL 18 |
| frontend unit | Vitest przez `vp test` (vite-plus) | 0.3 | 7 plików, ~95 przypadków, wyłącznie czysta logika w `resources/js/lib` i `resources/js/hooks` |
| e2e | none yet — see Phase 4 | — | kandydat: `pestphp/pest-plugin-browser` + Playwright (checked: 2026-09-30); nie jest zależnością projektu |
| mutation testing (backend) | `pestphp/pest-plugin-mutate` (`pest --mutate`) | v5.0.2 (`composer.lock`) | ad hoc, zawężone `--class`; pokrycie `pcov`; Stryker tylko dla JS/TS — nieużywany, bo brak testów Vitest w fazach ryzyka |
| backup / restore | spatie backup + `tests/Backup` + drill odtworzenia (§6.3) | — | zawartość kopii i odtworzenie zrzutu sprawdza `tests/Backup`; drill na serwerze według §6.3 |
| (optional) AI-native | eksploracyjny przebieg agenta po drzewie w przeglądarce (checked: 2026-09-30) | n/a | When NOT to use: jako bramka regresji ani do oceny wyglądu (§7); tylko rekonesans przed napisaniem testu fazy 4 |

Profil bazy testów: `meaningful` — konfiguracja Pest i Vitest, ~30 plików testów rozproszonych po domenach; brak jakiejkolwiek warstwy klikającej po prawdziwym UI (przebudowy UI sprawdzane jednorazowymi skryptami zrzutów w folderach zmian).

**Stack grounding tools (current session):**
- Docs: Laravel Boost `search-docs` — potwierdzono Pest 5 browser testing (`pest-plugin-browser`, Playwright, `assertNoJavaScriptErrors`); checked: 2026-09-30
- Search: Exa.ai — dostępne, nieużyte (dokumentacja Boost wystarczyła); checked: 2026-09-30
- Runtime/browser: none — brak MCP przeglądarki w tej sesji; checked: 2026-09-30
- Provider/platform: GitHub przez CLI `gh` (nie MCP) — możliwy odczyt stanu CI; nieużyte; checked: 2026-09-30

## 5. Quality Gates

| Gate | Where | Required? | Catches |
|---|---|---|---|
| lint + format (Pint, Oxlint/Oxfmt przez `vp check`) | local + CI (`lint`) | required | styl i ostrzeżenia lint (denyWarnings) |
| typecheck (Larastan level 7, `tsc`) | local + CI (`static-analysis`) | required (zielony potwierdzony 2026-10-01) | dryf typów |
| backend unit + integration (Pest) | local + CI (`tests`, PostgreSQL 18) | required | regresje logiki, autoryzacji i danych |
| frontend unit (Vitest) | local + CI (`lint`, `npm run test:js`) | required | regresje czystej logiki UI |
| weryfikacja zawartości kopii | CI (`tests`, `tests/Backup`) | required | kopia bez plików źródeł lub bez danych |
| drill odtworzenia na czystej instancji | ręcznie, na serwerze | required — kwartalnie i po zmianach (§6.3) | kopia, której nie da się odtworzyć |
| testowanie mutacyjne (`pest --mutate`) | lokalnie, po fazie ryzyka | ad hoc (nie wymagane w CI) | testy, które przechodzą mimo zepsutej linii |
| e2e na krytycznych przepływach drzewa i modala | CI on PR | required after §3 Phase 4 | UI wysyła akcję do niewłaściwej osoby |

## 6. Cookbook Patterns

Jak dodać nowy test w tym projekcie. Każda podsekcja wypełnia się po zakończeniu odpowiedniej fazy wdrożenia (§6.6 czeka na fazę 4); §6.7 opisuje selektywną bramkę mutacyjną używaną po fazach ryzyka.

### 6.1 Test nowego endpointu (backend)

- **Location**: `tests/Feature/<Domain>Test.php` (konwencja z `CLAUDE.md`: happy path + cudzy rekord = 404). Testy „zapis trafia we właściwe dane” na scenie rodzinnej: `tests/Feature/WriteTargetsTest.php`; helpery `databaseRows()`, `rowDiff()`, `familyScene()` i `personPreviewUrl()` w `tests/Pest.php`.
- **Reference test**: `tests/Feature/PersonsTest.php` (happy path + 404); wzorzec różnicy wierszy: `a tree write built from the sheet props adds exactly the expected rows and changes nothing else` (dataset 9 zapisów z drzewa) oraz `editing a person in the preview changes only that person and the birth fact in place` w `tests/Feature/WriteTargetsTest.php`; ten sam kształt dla usunięć: `tests/Feature/DeleteImpactTest.php` (§6.4).
- **Run locally**: `vendor/bin/sail artisan test --compact tests/Feature/<Domain>Test.php`; dla wzorca różnicy: `vendor/bin/sail artisan test --compact tests/Feature/WriteTargetsTest.php tests/Feature/DeleteImpactTest.php`.
- **Wyrocznia**: (1) zapis zmienia dokładnie wskazaną osobę lub parę, a inne osoby i potwierdzenia pozostałych faktów (`event_source`) zostają; wartości oczekiwane pochodzą z fikstury (rekordy `familyScene()`) i z planów archiwum (nowa osoba z drzewa atomowo z urodzeniem i relacją, nowy małżonek dodatkowo ze zdarzeniem małżeństwa; edycja aktualizuje zdarzenia w miejscu, nie kasuje ich — `archive/2026-09-30-fact-confirmed-by-any-source/plan.md`), nigdy z odczytu kodu zapisu. (2) Frontend nie wymyśla id: każde id w żądaniu pochodzi z propsa Inertia w chwili kliknięcia (`sheet` w drzewie, `personPreview` w modalu), a parę wybiera serwer z `?spouse=`; test buduje żądanie z propsa tak jak dialog, więc dowodzi kontraktu „prop → żądanie → baza”.
- **Pułapki**: GET z propsem (`readInertiaProp()`) przed `$before = databaseRows()` i przed żądaniem zapisu; `Storage::fake('local')` przed `familyScene()` (scena zapisuje pliki, a `databaseRows()` czyta dysk `local`); id żądania bierz z propsa, nie z rekordów sceny wprost (inaczej test nie sprawdza łańcucha UI → serwer); klucze dodanych wierszy formułuj po treści (pary `parent_id`/`child_id`, `event_id`/`person_id`, typ zdarzenia), bo ich `id` nie są znane z góry — „usunięte” i „zmienione” po `id` rekordów sceny; porównuj całą różnicę, nie pojedyncze rekordy: lista tabel z różnicą sama dowodzi „nic poza tym” (zapisy z drzewa tylko dodają, więc `removed` i `changed` muszą być puste, a pliki bez zmian).
- **Nowy endpoint zapisu**: (1) happy path i cudzy rekord = 404 w `<Domain>Test.php` oraz wpis w `ownershipRouteCases()` (§6.5); (2) na scenie `familyScene($user)`: GET strony z propsem, z którego UI bierze id → `$before = databaseRows()` → żądanie z `assertSessionHasNoErrors()` → `rowDiff($before, databaseRows())` porównane w całości z oczekiwaną różnicą (lista tabel + klucze wierszy + pliki); (3) przypadek brzegowy z ryzyka (np. `other_parent_id` ustawiony vs `null`, związek z nieznanym partnerem, współrodzic bez małżeństwa). Czerwony test od razu oznacza rozbieżność z wyrocznią — zatrzymaj się i zapytaj, nie przepisuj oczekiwania z kodu.
- **Odłożone**: unit (Vitest) dla logiki dialogów (payload dziecka, filtry płci, kwalifikowalność „ożeń parę”, liczniki skutków) — odłożony, decyzja autora 2026-10-02; kontrakt pokrywa Pest na propsach.

### 6.2 Test czystej logiki frontendu

- **Location**: obok modułu, `resources/js/lib/<module>.test.ts`.
- **Reference test**: `resources/js/lib/partial-date.test.ts`.
- **Run locally**: `vendor/bin/sail npm run test:js`.

### 6.3 Test kopii zapasowej i drill odtworzenia

- **Location**: `tests/Backup/`, klasa bazowa `tests/BackupTestCase.php` (izolowany `storage_path`, `DatabaseTruncation`, sprzątanie w `tearDown`).
- **Reference test**: `tests/Backup/BackupRestoreTest.php` (pełny obieg kopia → odtworzenie komendą `backup:restore`, instancja czysta i zajęta z `--force`); zawartość archiwum: `tests/Backup/BackupContentsTest.php`; odmowy komendy (zajęta instancja bez `--force`, odrzucone potwierdzenie, złe hasło, wpis `../`, zrzut bez `OWNER TO`): `tests/Backup/RestoreCommandTest.php`.
- **Run locally**: `vendor/bin/sail artisan test --compact tests/Backup`.
- **Wyrocznia**: (1) rekordy `sources` (`path`, `thumbnail_path`) — nie manifest spatie; każdy plik z bazy musi być w archiwum jako `private/<path>` i zgadzać się bajt w bajt (sha256). (2) Po odtworzeniu: skrót zawartości każdej tabeli trwałej (`tableDigests()`: liczba wierszy + `md5` z `string_agg` wierszy, niezależnie od kluczy głównych) porównany ze zrzutem zrobionym na instancji źródłowej zaraz po kopii; tabele trwałe są wyprowadzane ze schematu (`durableTables()`: wszystkie tabele `public` bez `EPHEMERAL_TABLES`), więc `passkeys` i każda przyszła tabela wchodzą automatycznie, a każda musi mieć wiersze. (3) Pliki: sha256 ciała odpowiedzi `sources.file` / `sources.thumbnail` dla przywróconego użytkownika równy sha256 wgranego pliku (dwa źródła o różnych bajtach, więc zamienione ścieżki wywalają test); do tego odszyfrowanie kodów odzyskiwania 2FA tym samym `APP_KEY` i wgranie nowego źródła po odtworzeniu (sekwencje).
- **Pułapki**: bez `Storage::fake` (zapisuje poza ścieżką kopii); `storage_path` przestawiany przed bootem aplikacji (nigdy nie dotykamy prawdziwego `storage/app`); wiersze muszą być zatwierdzone (`DatabaseTruncation`, nie `RefreshDatabase`), bo `pg_dump` nie widzi transakcji; konfiguracja spatie jest `scoped` — hasło ustawiać przed jej pierwszym użyciem (`useEncryptedBackups()`), bez pomijania testu, gdy AES jest niedostępny (produkcja też by nie odtworzyła); dane przygotowuje `arrangeBackupData()` z klasy bazowej (nie wolna funkcja w pliku testu — Pest ładuje tylko podane pliki).
- **Przełączenie instancji i `tearDown`**: `switchToCleanInstance()` przepina domyślne połączenie na pustą bazę `testing_restore` (`DB::purge`) i storage na drugi katalog tymczasowy (`useStoragePath` oraz roty dysków `local`, `public`, `backups` + `Storage::forgetDisk`). `tearDown` musi najpierw przywrócić oryginalne połączenie, `storage_path` i dyski, dopiero potem czyścić tabele trwałe `testing` i usuwać `testing_restore` i oba katalogi; w odwrotnej kolejności czyści się bazę restore, a zatwierdzone wiersze zostają w `testing` i psują `Feature`. Po przełączeniu uruchom `migrate --force` (jak na serwerze po wdrożeniu); sekunda w nazwie archiwum spatie wymaga `travel(1)->seconds()` przed `--force`, bo kopia bezpieczeństwa nie może nadpisać archiwum wejściowego.
- **Nowa tabela cache/kolejki**: dopisz ją do `EPHEMERAL_TABLES` w `tests/BackupTestCase.php` i w `app/Console/Commands/RestoreBackup.php` (dwie listy, ta sama zawartość); inaczej jej treść trafia do porównania i do tabeli „zostaną zastąpione”. Każda inna nowa tabela jest trwała i trafia do porównania sama.
- **CI**: job `tests` instaluje klienta PostgreSQL 18 (`pg_dump`/`psql`); testy z AES (w tym obieg odtworzenia) muszą przejść w CI, nie zostać pominięte.
- **Luka**: kopia przed wdrożeniem (`scripts/lib/production-deploy.sh`) to `--only-db`. Przed każdą migracją, która przenosi pliki, uruchom ręcznie pełny `backup:run`.

**Odtworzenie (komenda)** — `backup:restore <archiwum>` (ścieżka bezwzględna albo względna do dysku `backups`; hasło z `BACKUP_ARCHIVE_PASSWORD`, bez opcji `--password`):

1. `scripts/artisan.sh down` — bez tego żądania trafiają w pusty schemat między `db:wipe` a końcem `psql`.
2. `scripts/artisan.sh backup:restore <archiwum>`.
3. `TRUNCATE sessions, cache, cache_locks` — zrzut przywraca sesje i cache z chwili kopii.
4. `scripts/artisan.sh up`.

Krok 1 działa, bo sterownik trybu serwisowego to `file` (`APP_MAINTENANCE_DRIVER=file`, flaga w `storage/framework`, której odtworzenie nie rusza); przy sterowniku `cache` lub bazodanowym `db:wipe` skasowałoby flagę.

- **Czysta instancja** (pusta tabela `users` i brak plików w `private/sources`): `db:wipe`, załadowanie zrzutu w jednej transakcji, rozpakowanie plików, sprawdzenie, że każdy wiersz `sources` ma plik i miniaturę. Na `production` komenda pyta o potwierdzenie.
- **Zajęta instancja**: bez `--force` odmawia i niczego nie zmienia. Z `--force` pokazuje, co zostanie zastąpione (wiersze tabel trwałych, liczba plików źródeł), pyta o potwierdzenie (domyślnie nie; `--no-interaction` przerywa), robi pełną kopię bezpieczeństwa (`backup:run`) i dopiero potem czyści `private/sources` i odtwarza. Nieudana kopia bezpieczeństwa lub ta sama nazwa co archiwum wejściowe przerywa przed jakąkolwiek zmianą.
- **Archiwa sprzed zmiany**: opcja `--no-owner --no-acl` (`dump.add_extra_option` w `config/database.php`) dotyczy tylko nowych archiwów. Istniejące archiwa produkcyjne zawierają `OWNER TO <użytkownik produkcyjny>`; odtworzenie działa, gdy użytkownik bazy docelowej ma tę samą nazwę, a w przeciwnym razie kończy się czytelnym błędem.

**Potrzebne do odtworzenia poza archiwum** (wszystko to trzymaj poza VPS-em, bo bez tego archiwum jest bezużyteczne lub niepełne):

- `BACKUP_ARCHIVE_PASSWORD` — bez niego archiwum AES się nie otworzy.
- `APP_KEY` — sekrety 2FA są nim szyfrowane; z innym kluczem 2FA przestaje działać.
- ta sama domena w `APP_URL` — klucze dostępu (passkeys) są związane z hostem.
- dane dostępowe do bazy w `.env.prod`.

**Restore drill checklist** (komendy: sekcja „Odtworzenie (komenda)” powyżej; środowisko serwera: `../kostecki-dev-infra/docs/root-keeper.md`):

1. Zapisz na produkcji liczności wszystkich tabel trwałych wyprowadzone ze schematu (wszystkie tabele `public` poza `migrations`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`; w tym `passkeys`) oraz `migrations`; liczby zapisz w „Drill log”.
2. Uruchom `select count(*) from sources where path like 'scans/%' or thumbnail_path like 'scans/%'`; gdy > 0, zanotuj wiersze i otwórz zadanie naprawcze.
3. Pobierz najnowsze nocne archiwum poza VPS-em i przygotuj osobną jednorazową instancję z własną bazą i własnym storage (np. lokalny checkout Sail z wdrożonym commitem, z produkcyjnym `APP_KEY` i `BACKUP_ARCHIVE_PASSWORD`). Nigdy w kontenerze produkcyjnym.
4. Uruchom na niej `backup:restore <archiwum>` (zamiast ręcznego `7z` + `psql`) i porównaj liczności z krokiem 1.
5. Porównaj sha256 jednego prawdziwego oryginału źródła i jego miniatury z produkcją.
6. Posprzątaj instancję jednorazową i katalog tymczasowy.
7. Dopisz datowaną linię do „Drill log” poniżej.

**Rytm**: kwartalnie oraz po zmianie konfiguracji kopii, układu plików, głównej wersji PostgreSQL lub serwera.

**Drill log**:

- 2026-09-22 — F-01: `users` i `migrations`, jeden plik ręcznie (przed zmianą skany → źródła).
- 2026-10-01 — po zmianie na źródła: świeża pełna kopia po wgraniu jednego źródła przez UI, odtworzenie do `postgres:18-alpine`; liczności 8 tabel domenowych i `migrations` oraz sha256 oryginału i miniatury potwierdzone przez autora jako zgodne z produkcją. Wynik zapytania o `scans/%`: 0.

### 6.4 Test akcji usuwającej (skutki = lista w potwierdzeniu)

- **Location**: `tests/Feature/DeleteImpactTest.php`; helpery `databaseRows()`, `rowDiff()`, `familyScene()` i `personPreviewUrl()` w `tests/Pest.php`.
- **Reference test**: `deleting a person removes exactly what the preview lists and nothing of bystanders, spouses or sources` (osoba, lista z propsa `personPreview`) i `deleting a source detaches exactly the facts the confirmation listed and removes only its own files` (źródło, lista z propsa `confirms`); prostsze: `deleting a marriage removes only that marriage event with its participants and source links`, `deleting a parent-child relation removes only that relation and keeps both people and the other parent link`.
- **Run locally**: `vendor/bin/sail artisan test --compact tests/Feature/DeleteImpactTest.php`.
- **Wyrocznia**: trzy zbiory muszą być równe: (a) **fikstura** — zbiór wyprowadzony z rekordów `familyScene()` i z tego, co autor uznaje za skutek usunięcia (PRD, plany archiwum usunięć); (b) **lista w propsie potwierdzenia** — osoba: zbiory id z `personPreview` (`marriages[].id`, `parents[].relation_id` ∪ `children[].relation_id`, pary zdarzenie–źródło z `birth.sources`, `death.sources`, `marriages[].sources`), źródło: `confirms` jako zbiór (`event_id`, `person.id`, `is_last_source`), dla małżeństwa dwa wiersze (po jednym na małżonka — decyzja autora); (c) **różnica w bazie i na dysku** — `rowDiff()`: tabele z różnicą, usunięte klucze (np. `event_id:person_id`, `parent_id:child_id`) i `files.removed`. Usunięcie osoby dodatkowo zeruje `trees.root_person_id` (zmieniony wiersz, `nullOnDelete`); usunięcie źródła kasuje tylko jego własny oryginał i miniaturę, a każde zdarzenie z `is_last_source = true` zostaje bez źródeł, z `false` ma ≥ 1.
- **Pułapki**: nie licz oczekiwanej listy tym samym zapytaniem, które ją generuje, ani nie przepisuj liczników z TSX (`delete-person-dialog.tsx`) — to tautologia: lista i usuwanie mogą się pomylić razem, a test przejdzie; oczekiwany zbiór buduj z rekordów sceny. Lista tabel z różnicą (`array_keys($diff['tables'])`) musi być dokładna, żeby zbyt szerokie usunięcie (dane współmałżonka, postronnych, cudze źródła i pliki) zaświeciło na czerwono; `Storage::fake('local')` przed sceną; GET z propsem przed `$before = databaseRows()`.
- **Nowa akcja usuwająca**: (1) dopisz do `familyScene()` rekord, którego usunięcie ma coś skasować, i postronny rekord, który ma zostać; (2) napisz test: scena → GET potwierdzenia i odczyt listy skutków z propsa → `$before = databaseRows()` → usunięcie z `assertSessionHasNoErrors()` → `rowDiff()`; (3) zbuduj zbiór oczekiwany z fikstury i sprawdź równość z listą z propsa oraz z różnicą w bazie i na dysku; (4) uruchom bramkę mutacyjną z §6.7 na klasie akcji — usunięcie kroku kasowania ma zabić test.

### 6.5 Test własności i reguł relacji

**Własność (ryzyko #4)**

- **Location**: `tests/Feature/OwnershipBoundariesTest.php`; helpery `ownershipSnapshot()` i `personPreviewUrl()` w `tests/Pest.php`.
- **Reference test**: dataset `answers 404 and writes nothing when the address holds another tree's id` (funkcja `ownershipRouteCases()` — jeden wpis na przypadek: nazwa trasy, metoda, parametry adresu, treść; dla tras z treścią dwa warianty, „poprawna treść” i „pusta treść”). Strażnik `has a case for every controller route that carries an id in the address` czyta tę samą listę. Dalej: `?person=` na stronach hosta, `tree_id` w treści `persons.store` / `sources.store`; passkey w `tests/Feature/Settings/SecurityTest.php` (`another user's passkey cannot be deleted`).
- **Run locally**: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php`.
- **Wyrocznia**: (1) cudze id w adresie albo w `?person=` → 404 niezależnie od treści, a `ownershipSnapshot()` (liczba wierszy i skrót zawartości każdej tabeli poza `migrations` plus lista plików dysku `local`) przed i po żądaniu jest identyczny. (2) Cudze id w treści → błąd walidacji bez zapisu, ten sam komunikat co dla nieistniejącego id (decyzja archiwum, nie 404); 9 pól w 6 endpointach pokrywają testy tras. (3) `tree_id` w treści jest ignorowany: rekord i plik źródła trafiają do drzewa atakującego. (4) Cudzy passkey (`passkey.destroy`) → 403 (kod vendor, dane konta; wyjątek od „404, nie 403”), wiersz nadal istnieje.
- **Pułapki**: autoryzacja trasy z Form Requestem stoi w `authorize(): Response` (`Gate::inspect(...)`), nie w kontrolerze — walidacja biegnie przed ciałem kontrolera, więc `Gate::authorize` w kontrolerze daje 302 z błędem walidacji zamiast 404 i zdradza istnienie id (stąd przypadek „pusta treść”); `Storage::fake('local')` wołaj przed `ownershipSnapshot()` (snapshot czyta dysk `local`); „poprawna treść” to id z drzewa atakującego (`ownPerson`, `ownSource`), inaczej test dowodzi tylko, że walidacja odrzuciła treść; trasy z dwoma parametrami (`events.sources.destroy`) wymagają przypadku na każdy parametr z osobna; rekordy skrzyżowane (`ownershipRecords()`) dają pivotowi wiersz do błędnego usunięcia; passkey nie ma fabryki — wiersz przez model `Passkey` z `user_id` ustawionym poza `fillable`, żądanie z `auth.password_confirmed_at` w sesji.
- **Nowy endpoint z id w adresie**: dopisz wpis do `ownershipRouteCases()` (z wariantami treści, jeśli trasa ma treść); strażnik kompletności wymusza to, wskazując brakującą nazwę trasy. Nowe pole z id w treści: reguła `Rule::exists(...)->where('tree_id', ...)` oraz test błędu walidacji bez zapisu.

**Reguły relacji (ryzyko #5)**

- **Location**: `tests/Feature/ParentChildRelationsTest.php` (reguły na 4 trasach tworzących relację); zmiana płci rodzica w `tests/Feature/PersonsTest.php` (`changing the sex of a parent is rejected…`). Helpery `linkParent()` i `ownershipSnapshot()` w `tests/Pest.php`.
- **Reference test**: `a lone parent cannot attach a child who already has two parents` (wzorzec: arrange → `Storage::fake('local')` → `$before = ownershipSnapshot()` → żądanie → `assertSessionHasErrors(['relation' => dokładny komunikat])` → `expect(ownershipSnapshot())->toBe($before)`). Dla pierwszego linku wycofywanego w transakcji: `attaching a child who is an ancestor of the other parent is rejected as a cycle…`.
- **Run locally**: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/PersonsTest.php`.
- **Wyrocznia**: komunikaty z archiwum `2026-09-25-parent-child-relations/plan.md:92-94` („Ta relacja już istnieje.”, „Ta osoba ma już dwoje rodziców.”, „Ta relacja utworzyłaby cykl w drzewie.”) oraz „Ta osoba ma już ojca/matkę.”. Wszystkie reguły egzekwuje `CreateParentChildRelation`; `role` jest wymagane w `parent-child-relations.store` (bez niego pomijana byłaby kontrola slotu). Zmiana płci osoby na znaną płeć, którą ma już inny rodzic jej dziecka, kończy się błędem na `gender` („Ta osoba jest rodzicem dziecka, które ma już ojca/matkę.”); zmiana na „nieznana” i edycja bez zmiany płci przechodzą (starsze pary tej samej płci nie blokują edycji).
- **Macierz reguła × trasa** (n/d = nowa osoba nie ma jeszcze rodziców ani potomków, więc reguła nie może zadziałać):

| Reguła | `parent-child-relations.store` | `persons.parents.store` | `persons.children.attach` | `persons.children.store` |
|---|---|---|---|---|
| trzeci rodzic | ✓ | ✓ | ✓ (para i samotny rodzic) | n/d |
| cykl | ✓ | n/d | ✓ (z adresu i przez `other_parent_id`) | n/d |
| ta sama płeć | ✓ | ✓ | ✓ | ✓ |
| płeć × slot | ✓ (`role`) | ✓ | n/d | n/d |
| duplikat | ✓ | n/d | ✓ | n/d |

- **Pułapki**: snapshot rób po arrange (także po żądaniach przygotowujących), tuż przed odrzucanym żądaniem; `Storage::fake('local')` przed snapshotem; przy `children.attach` z parą pierwszy link przechodzi, drugi pada — snapshot dowodzi, że transakcja wycofała pierwszy; każde nowe wywołanie `parent-child-relations.store` w testach wymaga `'role' => 'parent'` (inaczej test trafia w walidację, nie w regułę akcji); relację rodzic–dziecko w arrange zakładaj przez `linkParent()`, nie przez trasę.
- **Nowa ścieżka tworząca relację**: musi przejść przez `CreateParentChildRelation`; dopisz jej kolumnę w macierzy i test (wzorzec wyżej) na każdą stosowalną regułę, z dokładnym komunikatem i snapshotem.

### 6.6 Test przepływu w przeglądarce

- TBD — see §3 Phase 4 (wzorzec: przepływ drzewa lub modala z asercją na danych, nie na wyglądzie).

**Werdykt fazy 2 (2026-10-02)**: id w żądaniach zapisu pochodzą z propsów Inertia (`sheet`, `personPreview`), a parę wybiera serwer z `?spouse=`; kontrakt „prop → żądanie → baza” pokrywa Pest (`tests/Feature/WriteTargetsTest.php`), więc tańsza warstwa łapie pomyłkę osoby. e2e uruchomić dopiero, gdy pojawi się stan id po stronie klienta albo gdy okno po `choose()` (stary `sheet` do końca wizyty) da potwierdzony błąd. Status fazy 4 w §3 bez zmian.

### 6.7 Testowanie mutacyjne (bramka selektywna)

Pokrycie mówi „linia została wykonana”; wynik mutacji mówi „czy test by nie przeszedł, gdybym zepsuł tę linię”. Bramka jest ad hoc, po fazie ryzyka, nie w CI (§5).

- **Location**: `pest --mutate` przez Sail: `vendor/bin/sail bin pest <pliki testów> --mutate --covered-only --class='<Klasa1>,<Klasa2>' --no-cache` (wtyczka `pestphp/pest-plugin-mutate`, pokrycie przez `pcov` załadowany w Sail; opcje: `vendor/bin/sail bin pest --help`, sekcja MUTATION TESTING OPTIONS).
- **Reference run**:
  - usunięcia (faza 2): `vendor/bin/sail bin pest tests/Feature/DeleteImpactTest.php tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventSourcesTest.php --mutate --covered-only --class='App\Actions\Persons\DeletePerson,App\Actions\Sources\DeleteSource,App\Http\Controllers\SourceController,App\Http\Controllers\MarriageController,App\Http\Controllers\ParentChildRelationController'`
  - zapisy (faza 3): `vendor/bin/sail bin pest tests/Feature/WriteTargetsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventSourcesTest.php --mutate --covered-only --class='App\Actions\Trees\BuildFamilySheet,App\Actions\Persons\UpdatePerson,App\Actions\Relations\UpdateMarriage,App\Actions\Relations\AttachChildToParents,App\Actions\Relations\CreateChildForParents,App\Actions\Relations\CreateSpouseForPerson,App\Actions\Relations\CreateParentForPerson,App\Actions\Relations\CreateMarriage,App\Http\Controllers\PersonChildController,App\Http\Controllers\PersonSpouseController,App\Http\Controllers\EventSourceController'`
  - wyniki i ocena przetrwałych mutacji: sekcje „Ocena przetrwałych mutacji” w `context/changes/testing-write-delete-targets/plan.md` (fazy 2 i 3).
- **Przebieg**: (1) testy fazy zielone; (2) `--mutate --covered-only --class=…` z nowym plikiem testów i istniejącymi plikami tej domeny (żeby nie zgłaszać mutacji, które zabija inny plik); (3) dla każdej przetrwałej mutacji pytanie „czy ta zmiana zaszkodziłaby użytkownikowi?”: tak → asercja na stanie danych z wyroczni (nie z kodu), która ją zabija; nie (mutacja równoważna lub kosmetyczna) → świadomie zignoruj i zapisz jedno zdanie uzasadnienia w planie zmiany.
- **Pułapki**: bez `--covered-only` klasy niepokryte testami zawyżają liczbę nieprzetestowanych mutacji; komunikaty wyjątków (konkatenacje w `report()`), etykiety, toasty, kolejność prezentacji i opakowania listy (`array_values`) to mutacje kosmetyczne; nie dodawaj progu `--min` ani asercji przypinających szczegóły implementacji tylko po to, by zabić kosmetyczną mutację (taki test sam jest testem vibe); `--no-cache` po zmianie testów, inaczej wynik może pochodzić z pamięci podręcznej; `--parallel` psuje wspólną bazę `testing` — uruchamiaj serialnie; gdy przebieg jest zbyt długi (np. `BuildFamilySheet`, setki mutacji), uruchom go per klasa (jedna klasa w `--class`); Stryker mutuje tylko JS/TS, więc dla kodu PHP nie daje sygnału.

## 7. What We Deliberately Don't Test

Wykluczenia uzgodnione w wywiadzie (P5). Obowiązują, dopóki nie zmieni się założenie.

- **Wygląd — kolory, odstępy, tryb ciemny** — autor ocenia to sam przy każdej przebudowie UI. Ponownie rozważyć, gdy aplikacja zyska innych użytkowników. (Source: Phase 2 interview Q5.)
- **Same widoki i układ stron** — autor widzi je sam; testy UI sprawdzają tylko skutek w danych (§3 Phase 4), nie układ. Ponownie rozważyć, gdy błąd widoku ukryje lub zafałszuje dane. (Source: Phase 2 interview Q5.)
- **Eksport danych** — funkcja nie istnieje (FR-018 odłożony); ochronę przed utratą dostępu daje odtworzenie z kopii (§2 #2). Ponownie rozważyć, gdy eksport wejdzie do zakresu. (Source: roadmap `## Parked`, interview Q2.)

## 8. Freshness Ledger

- Strategy (§1–§5) last reviewed: 2026-09-30
- Stack versions last verified: 2026-09-30
- AI-native tool references last verified: 2026-09-30

Refresh (`/10x-test-plan --refresh`) when:

- a new top-3 risk surfaces from the roadmap or archive,
- a recommended tool's `checked:` date is older than three months,
- the project's tech stack changes (new framework, new test runner),
- §7 negative-space no longer matches what the team believes.
