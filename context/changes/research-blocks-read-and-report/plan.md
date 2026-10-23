# Import — odczyt bloków danych z repo badawczego i raport: plan implementacji

## Overview

Pierwszy wycinek M-3 (S-01, FR-001 – FR-004). Ustala kontrakt bloku danych osoby w plikach repo badawczego (YAML frontmatter, `wersja: 1`, stały identyfikator `O-0001`). Dodaje komendę `import:research {email} {path}`, która czyta bloki z `drzewo/*.md`, waliduje je i wypisuje raport: przeczytane osoby, pominięte wpisy z powodem i ostrzeżenia. Komenda nic nie zapisuje do bazy. Dzięki temu autor może konwertować pliki przodków w repo i od razu sprawdzać je komendą, zanim powstanie tor zapisu (S-02).

## Current State Analysis

- **Aplikacja:**
  - Jedyna komenda to `app/Console/Commands/RestoreBackup.php` (atrybuty `#[Signature]` / `#[Description]`, komunikaty po polsku, `self::FAILURE` z `$this->error(...)`).
  - Nie ma `app/Actions/Import` ani testów komend z wyborem. Wzór testu komendy to `tests/Backup/RestoreCommandTest.php`.
- **Parser YAML:**
  - `symfony/yaml` v8.1.8 jest w `composer.lock` tylko przez `laravel/sail` i `laravel/roster` (dev), więc w obrazie produkcyjnym (`--no-dev`) go nie ma.
  - Rozszerzenie FrontMatter z `league/commonmark` też go wymaga (`vendor/league/commonmark/composer.json:47`).
- **Model:**
  - `people` ma `first_name`, `last_name` (nazwisko rodowe, jedyne), `gender` (`App\Enums\Gender`: `male`/`female`).
  - `App\Enums\EventType` ma `birth`, `death`, `marriage`, `burial`.
  - `App\Support\PartialDate` zna tylko datę dokładną i „ok.”.
  - Pól importu (id repo, poziom A–D, warianty) jeszcze nie ma. S-01 ich nie dodaje, bo nic nie zapisuje.
- **Repo badawcze** (żywe w `/mnt/d/my-projects/genealogy-project`, HEAD a2113a2; kopia w `storage/gen-tree-import/` jest nieaktualna: 246 zamiast 283 plików):
  - `drzewo/` jest płaski: 279 plików osób + 4 indeksy `_*.md`.
  - Żaden plik nie ma frontmattera ani bloku kodu. 121 plików ma CRLF, bez BOM.
  - Numer Ahnentafel ma 187 osób. Implex jest opisany prozą, a jedna osoba może mieć dwa numery.
  - Nie ma identyfikatora osoby. Nazwy plików są niespójne i bywają zmieniane (`_xxxx`, `okYYYY`, warianty z nazwiskiem męża).
  - Repo zna już stałe ID z prefiksem dla zadań badawczych (`K-07`, nigdy nie używane ponownie; `CLAUDE.md:86` repo).
  - Relacje i małżeństwa to proza z `nr N` i poziomem `[A–D]`. Małżeństwo jest opisane w plikach obojga małżonków.
  - Daty: dokładne w kilku zapisach, `ok.`, zakresy, `przed`, `po`, `między … a …` oraz podwójne juliańskie.
  - Notatka `notatki/propozycja_struktury_plikow_2026-10-20.md:156-160` odrzuca YAML zamiast markdownu. PRD z 2026-10-23 jest późniejsze i wprowadza blok obok prozy. Notatkę aktualizuje autor w repo; aplikacja nigdy tam nie pisze.

## Desired End State

- `context/foundation/research-block-contract.md` opisuje kontrakt bloku osoby v1 i zawiera gotową regułę do wklejenia w `CLAUDE.md` repo badawczego.
- `symfony/yaml` jest w `require`.
- Komenda `vendor/bin/sail artisan import:research autor@example.com /ścieżka/do/repo`:
  - rozpoznaje konto,
  - czyta wszystkie `drzewo/*.md` (bez plików `_*.md`),
  - wypisuje raport: pliki przejrzane, z blokiem i bez bloku; osoby przeczytane; relacje rodzic–dziecko i małżeństwa rozpoznane; tabelę pominiętych wpisów (plik, powód); tabelę ostrzeżeń (plik, opis),
  - kończy się sukcesem, nie zapisując niczego w bazie.

Weryfikacja: testy na wymyślonym repo w `tests/Fixtures/research-repo/` plus ręczny przebieg na żywym repo po skonwertowaniu kilku plików przodków.

### Key Discoveries:

- `vendor/symfony/yaml/Inline.php:866-886`: niecytowana data `1801-05-12` bez flagi `Yaml::PARSE_DATETIME` staje się liczbą (unix timestamp, ujemny dla dat sprzed 1970). Z flagą staje się `DateTimeImmutable`.
- `.gitattributes`: `* text=auto eol=lf`. Fixture z CRLF zostałby znormalizowany przy commicie, więc przypadek CRLF testujemy na treści zbudowanej w teście.
- `app/Support/PartialDate.php`: wzór małej klasy wartości (`final readonly`), obok niej wartości importu w `app/Support/Import/`.
- `CLAUDE.md` („Migrations that move existing data”) nazywa przyszłe rodzaje daty: exact, approximate, range, before, after. Enum rodzaju daty z S-01 używa tych nazw, żeby S-07 go przejął.

## What We're NOT Doing

- Żadnego zapisu do bazy, migracji ani nowych pól w modelach (S-02 i dalej). Nota dla S-02: `events` zna tylko `date_is_approximate` (`PartialDate`), więc `exact` → `false`, `approximate` → `true`, a `range`, `before` i `after` nie mają celu zapisu przed S-07. S-02 musi je pominąć z ostrzeżeniem albo poczekać na S-07.
- Żadnego podglądu, dopasowania ani zatwierdzania (S-02 – S-04).
- Brak bloku źródła i kopiowania skanów (S-06). Kontrakt v1 dotyczy osoby.
- Brak dopasowania miejsc do spisu. Miejsce w v1 to tekst (S-05 może dodać opcjonalne pola).
- Brak parsowania prozy i `_PRZODKOWIE.md`. Numery Ahnentafel pochodzą wyłącznie z bloku.
- Brak konwersji plików w repo badawczym i jakichkolwiek zapisów tam: konwersję robi autor z Claude w repo.
- Brak wsparcia linii bocznych w tym kroku. Kontrakt ich nie wyklucza (`ahnentafel` jest opcjonalne), ale nikt ich nie konwertuje.
- Brak kopii bazy przed zapisem (S-02).

## Implementation Approach

Najpierw kontrakt jako dokument i zależność, żeby konwersja plików w repo mogła ruszyć równolegle z kodem. Potem odczyt i walidacja jako Actions z `handle()`, testowane bezpośrednio na wymyślonym repo. Na końcu cienka komenda, która tylko rozpoznaje argumenty i drukuje raport (reguła „Research repo import” w `CLAUDE.md`).

Walidacja ma dwa poziomy:
- **Pomiń** — błąd kształtu bloku. Cały wpis trafia do raportu z powodem.
- **Ostrzeż** — wpis jest przeczytany, a problem dotyczy odwołań między plikami albo nieznanych kluczy.

## Critical Implementation Details

- **Daty w YAML:** parsuj z `Yaml::PARSE_DATETIME`. `DateTimeInterface` w polu `data` to pominięcie wpisu z powodem „ujmij datę w cudzysłów: "RRRR-MM-DD"”, a nie normalizacja: symfony/yaml tworzy go przez `new DateTimeImmutable($scalar)` (`Inline.php:866-886`), które przesuwa dni niemożliwe (`1801-02-31` → `1801-03-03`, `1801-02-29` → `1801-03-01`) i przyjmuje `1801-5-7`. Liczbę całkowitą w `data` zamień na tekst roku, więc `data: 1801` i `data: "1801"` dają ten sam wynik. Bez flagi pełna data cicho zamieniłaby się w unix timestamp.
- **CRLF i BOM:** przed szukaniem frontmattera usuń BOM UTF-8 i zamień `\r\n` na `\n`. Frontmatter musi zaczynać się w pierwszej linii od `---` i kończyć linią `---` (spacje na końcu tych linii są dozwolone).
- **Blok nie na początku pliku:** plik bez frontmattera, w którym jakaś linia pasuje do `^id: O-\d+` albo `^wersja:`, to ostrzeżenie „blok nie zaczyna się w pierwszej linii” (plik liczony jako bez bloku). Bez tego przodek z przesuniętym blokiem znika z importu i widać to tylko z `-v`.
- **Duplikat id:** gdy dwa pliki mają ten sam `id`, oba wpisy są pomijane. Sprawdzenia odwołań biegną dopiero po zebraniu wszystkich poprawnych wpisów, więc odwołanie do pominiętego id jest ostrzeżeniem „brak poprawnego bloku”.

## Phase 1: Kontrakt bloku i parser YAML

### Overview

Kontrakt bloku osoby v1 jako dokument, który autor i Claude w repo badawczym traktują jako źródło reguł. Do tego produkcyjna zależność `symfony/yaml` i wskazanie kontraktu w `CLAUDE.md` root-keepera.

### Changes Required:

#### 1. Kontrakt bloku osoby

**File**: `context/foundation/research-block-contract.md`

**Intent**: Jeden opis formatu, na którym opierają się konwersja plików w repo i walidator. Dokument zawiera pola, gramatykę dat, reguły pomijania i ostrzeżeń, politykę wersji oraz gotową do wklejenia regułę utrzymania bloku dla `CLAUDE.md` repo badawczego. Reguła mówi: blok obok prozy, nie zmyślać, zgodność bloku z prozą, nadawanie kolejnych `O-NNNN` bez ponownego użycia, nie zmieniać `id` przy zmianie nazwy pliku. Dokument zawiera też krótką notę, że notatka repo `propozycja_struktury_plikow_2026-10-20.md` wymaga aktualizacji przez autora.

**Contract**: Blok to frontmatter YAML na samej górze pliku osoby, przed nagłówkiem H1. Pola v1 (przykład z wymyślonymi danymi):

```yaml
---
wersja: 1                       # wymagane, tylko 1
id: O-0042                      # wymagane, ^O-\d{4,}$, stałe, nigdy nie używane ponownie
ahnentafel: [42]                # opcjonalne, lista liczb > 0; implex: [50, 142]
imiona: Jan                     # wymagane imiona albo nazwisko
nazwisko: Kowal                 # nazwisko rodowe
warianty_nazwiska: [Kowalski]   # opcjonalne, lista tekstów
plec: M                         # opcjonalne, M | K
rodzice:                        # opcjonalne
  ojciec: O-0084
  matka: O-0085
urodzenie:                      # opcjonalne; obecność klucza = wydarzenie zaszło
  data: "1801-05-12"           # pełna data zawsze w cudzysłowie
  miejsce: Wieś Przykładowa     # tekst
  poziom: A                     # opcjonalne, A | B | C | D
  notatka: "24 IV 1801 st.st."  # opcjonalne (styl juliański, uwagi)
zgon: {}                        # zmarł, data nieznana
pochowek:                       # miejsce = cmentarz jako tekst
  miejsce: Cmentarz parafialny w Wsi Przykładowej
malzenstwa:                     # opcjonalne; w plikach obojga małżonków, zgodnie
  - z: O-0043
    data: ok. 1825
    miejsce: Wieś Przykładowa
    poziom: B
---
```

Gramatyka `data`:
- `RRRR`, `RRRR-MM`, `RRRR-MM-DD` — dokładna, ewentualnie częściowa,
- `ok. <data>` — przybliżona,
- `przed <data>`, `po <data>`,
- `między RRRR a RRRR` — zakres, od < do.

Rok musi mieścić się w 1000…bieżący, a data być prawdziwą datą kalendarzową. Daty piszemy w kalendarzu gregoriańskim, a styl juliański idzie do `notatka`.

Data z miesiącem i dniem stoi w cudzysłowie (`data: "1801-05-12"`). Bez cudzysłowu YAML zamienia ją w obiekt daty, a PHP po cichu przesuwa dzień niemożliwy (`1801-02-31` → `1801-03-03`), więc taki wpis jest pomijany z powodem „ujmij datę w cudzysłów”. Sam rok może być liczbą (`data: 1801`).

Wersjonowanie: nowe opcjonalne klucze dochodzą w v1 razem z aktualizacją aplikacji (S-05, S-06). Zmiana znaczenia lub nazwy pola to `wersja: 2`. Komenda zna tylko wersje, które obsługuje.

#### 2. Zależność produkcyjna

**File**: `composer.json`, `composer.lock`

**Intent**: Parser YAML musi być dostępny w obrazie produkcyjnym (`--no-dev`). Autor zatwierdził nową zależność w planowaniu.

**Contract**: `symfony/yaml` (`^8.1`) w `require` (`vendor/bin/sail composer require symfony/yaml:^8.1`). `vendor/bin/sail composer why symfony/yaml` pokazuje projekt główny.

#### 3. Wskazanie kontraktu w regułach projektu

**File**: `CLAUDE.md` (sekcja „Research repo import”)

**Intent**: Następny agent ma wiedzieć, gdzie jest kontrakt i że zmiana pola to nowa wersja.

**Contract**: Jedna nowa pozycja w sekcji: format bloku opisuje `context/foundation/research-block-contract.md`; zmiana znaczenia pola wymaga podbicia `wersja`; parser YAML jest w `require`.

### Success Criteria:

#### Automated Verification:

- `vendor/bin/sail composer why symfony/yaml` wymienia `laravel/react-starter-kit` (projekt główny) jako wymagający pakietu
- `vendor/bin/sail composer validate` przechodzi

#### Manual Verification:

- Autor przegląda kontrakt i potwierdza, że da się w nim zapisać kilku prawdziwych przodków (implex, zgon bez daty, data juliańska, małżeństwo w dwóch plikach)

**Implementation Note**: Po tej fazie autor może zacząć konwersję plików przodków w repo badawczym, wklejając regułę z kontraktu do jego `CLAUDE.md`.

---

## Phase 2: Odczyt i walidacja bloków

### Overview

Actions, które z katalogu repo budują wynik odczytu: poprawne osoby, rozpoznane małżeństwa, wpisy pominięte z powodem, ostrzeżenia i pliki bez bloku. Testowane bezpośrednio na wymyślonym repo.

### Changes Required:

#### 1. Wartości importu

**File**: `app/Support/Import/ResearchDate.php`, `ResearchEvent.php`, `ResearchMarriage.php`, `ResearchPerson.php`, `ResearchRepoReading.php`, `ImportIssue.php`, `ParsedPersonBlock.php`; `app/Enums/DateQualifier.php`, `app/Enums/ConfidenceLevel.php`

**Intent**: Typowany wynik odczytu, który przejmie S-02 (zapis) bez ponownego parsowania. `DateQualifier` (`exact`, `approximate`, `before`, `after`, `range`) i `ConfidenceLevel` (`A`–`D`) są enumami, które S-07 i S-08 rozbudują o etykiety i kolumny.

**Contract**:
- `ResearchDate::parse(string $value): ?self` zwraca `null`, gdy tekst nie pasuje do gramatyki. Pola: `qualifier`, `year`, `month`, `day`, `untilYear` (tylko dla `range`). Klasa jest czysta (bez fasad i `now()`; bieżący rok z `(int) date('Y')`), bo `tests/Unit` działa bez aplikacji (`tests/Pest.php` wiąże `TestCase` tylko dla Feature).
- `ParsedPersonBlock`: wynik `ParsePersonBlock` — brak bloku (z ewentualnym ostrzeżeniem), pominięcie z powodem albo osoba z listą ostrzeżeń.
- `ResearchPerson`: `id`, `file`, `ahnentafel` (list<int>), `givenNames`, `surname`, `surnameVariants`, `gender` (`?Gender`; M → `male`, K → `female`), `fatherId`, `motherId`, `birth`/`death`/`burial` (`?ResearchEvent`).
- `ResearchEvent`: `?ResearchDate date`, `?string place`, `?ConfidenceLevel level`, `?string note`.
- `ResearchMarriage`: para id (posortowana), dane ślubu jak w `ResearchEvent`, pliki źródłowe.
- `ResearchRepoReading`: `persons` (po id), `marriages`, `skipped` i `warnings` (list<ImportIssue{file, message}>), `filesWithoutBlock` (list<string>), `scannedFiles` (int). Dodatkowo licznik relacji rodzic–dziecko, czyli odwołań `ojciec`/`matka` rozwiązanych do przeczytanej osoby.

Klasy `final readonly` jak `PartialDate`.

#### 2. Odczyt pojedynczego bloku

**File**: `app/Actions/Import/ParsePersonBlock.php`

**Intent**: Z treści jednego pliku zwraca osobę z ostrzeżeniami albo powód pominięcia. Każdy blok przechodzi przez `Validator::make` z regułami przed zbudowaniem `ResearchPerson` (reguła „Research repo import”). Powody pomijania i ostrzeżenia są po polsku i wskazują pole.

**Contract**: `handle(string $file, string $contents): ParsedPersonBlock`. Wynik to brak bloku, pominięcie z powodem albo osoba z listą ostrzeżeń.

Pominięcie wpisu:
- niezamknięty frontmatter albo błąd składni YAML: powód po polsku „Błąd składni YAML w linii N” z numerem z `ParseException::getParsedLine()` i oryginalnym (angielskim) komunikatem w nawiasie,
- blok, który nie jest mapą,
- brak `wersja` albo wersja inna niż 1,
- brak `id` albo `id` niezgodny z wzorcem,
- brak zarówno `imiona`, jak i `nazwisko`,
- `plec` spoza M/K,
- `ahnentafel` niebędące listą liczb dodatnich,
- `data` poza gramatyką albo niemożliwa,
- `data` z pełną datą bez cudzysłowu (obiekt daty z YAML),
- `poziom` spoza A–D,
- odwołanie (`ojciec`, `matka`, `z`) niezgodne z wzorcem albo wskazujące na własne `id`,
- wartość o złym typie (np. lista zamiast tekstu).

Ostrzeżenie (osoba przeczytana): nieznany klucz na dowolnym poziomie.

Ostrzeżenie (brak bloku): plik bez frontmattera z linią `^id: O-\d+` albo `^wersja:` (patrz Critical Implementation Details).

Plik bez frontmattera to brak bloku, nie błąd. YAML parsowany z `Yaml::PARSE_DATETIME`; obiekt daty w `data` to pominięcie („ujmij datę w cudzysłów”), a rok-liczba staje się tekstem (patrz Critical Implementation Details).

#### 3. Odczyt repo i sprawdzenia między plikami

**File**: `app/Actions/Import/ReadResearchRepo.php`

**Intent**: Przegląda `<path>/drzewo/*.md` (bez podkatalogów, bez plików zaczynających się od `_`) tylko do odczytu, woła `ParsePersonBlock` dla każdego pliku, a potem sprawdza spójność między plikami.

**Contract**: `handle(string $repoPath): ResearchRepoReading`. Rzuca `RuntimeException` z polskim komunikatem, gdy katalog lub `drzewo/` nie istnieje (w `app/` nie ma własnych klas wyjątków; wzór `app/Actions/Sources/StoreSource.php:39`). Komenda łapie go i zamienia na `$this->error(...)` + `self::FAILURE`. Pliki są przeglądane w kolejności nazw, więc raport jest deterministyczny.

Sprawdzenia między plikami:
- **Pominięcie:** ten sam `id` w kilku plikach pomija wszystkie te wpisy. Raport wymienia oba pliki.
- **Ostrzeżenie — odwołania:**
  - `ojciec`, `matka` albo `z` wskazuje id bez poprawnego bloku,
  - `ojciec` ma `plec: K` albo `matka` ma `plec: M`,
  - ten sam numer `ahnentafel` u dwóch różnych osób.
- **Małżeństwa:**
  - para jest łączona w jedno małżeństwo,
  - zapis tylko w jednym z dwóch plików z blokiem daje ostrzeżenie „jednostronne”, a małżeństwo jest rozpoznane,
  - różne `data`, `miejsce`, `poziom` lub `notatka` po obu stronach dają ostrzeżenie, a małżeństwo jest pominięte (nie trafia do `marriages`). Porównanie idzie na wartościach znormalizowanych: `ResearchDate` (rodzaj, rok, miesiąc, dzień, `untilYear`), tekst przycięty i ze ściśniętymi spacjami, więc `ok. 1825` i `ok.  1825` są zgodne,
  - `z` wskazuje id bez poprawnego bloku: ostrzeżenie o odwołaniu, a małżeństwo nie trafia do `marriages` (S-02 nie ma drugiej osoby do zapisania),
  - ta sama para dwa razy w `malzenstwa` jednego pliku: ostrzeżenie, a małżeństwo jest pominięte. Powtórny ślub tej samej pary jest poza v1.

#### 4. Wymyślone repo testowe

**File**: `tests/Fixtures/research-repo/drzewo/*.md` (+ `tests/Fixtures/research-repo/drzewo/_PRZODKOWIE.md`)

**Intent**: Małe, wymyślone repo (imiona i miejsca zmyślone, nigdy z prawdziwych danych), które pokrywa każdą regułę. Zawiera:
- trzy pokolenia przodków z poprawnymi blokami: pełna data w cudzysłowie, rok jako liczba, `ok.`, `przed`, `między`, zgon bez daty, implex z dwoma numerami,
- małżeństwo zgodne w obu plikach (z różnicą tylko w spacjach), małżeństwo rozbieżne i jednostronne, małżeństwo z osobą bez bloku, ta sama para dwa razy w jednym pliku,
- plik bez bloku i indeks `_PRZODKOWIE.md`, który ma być zignorowany,
- plik z blokiem pod nagłówkiem H1 (ostrzeżenie „blok nie zaczyna się w pierwszej linii”),
- po jednym pliku na każdy powód pominięcia z listy wyżej i parę plików z duplikatem `id`,
- nieznany klucz, odwołanie do osoby bez bloku i niezgodną płeć rodzica.

**Contract**: Pliki z LF. Przypadki CRLF i BOM są budowane w teście.

#### 5. Testy

**File**: `tests/Unit/ResearchDateTest.php`, `tests/Feature/ResearchRepoReadTest.php`

**Intent**: Test jednostkowy gramatyki dat (każdy rodzaj, granice roku, 31 lutego, `między 1811 a 1805` odrzucone) w stylu `tests/Unit/PartialDateTest.php`. Test odczytu na fixture'ach: liczby osób, małżeństw i relacji, dokładny powód każdego pominięcia, ostrzeżenia, pliki bez bloku, zignorowany indeks. Do tego `ParsePersonBlock` na treści z CRLF i na pełnej dacie bez cudzysłowu (`1801-02-31` i `1801-05-12` dają pominięcie z powodem „ujmij datę w cudzysłów”, a nie przesuniętą datę).

**Contract**: Testy Pest. Brak dostępu do prawdziwego repo.

### Success Criteria:

#### Automated Verification:

- Testy odczytu i dat przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/ResearchDateTest.php tests/Feature/ResearchRepoReadTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

---

## Phase 3: Komenda i raport

### Overview

Cienka komenda Artisan: rozpoznaje konto i katalog, woła `ReadResearchRepo` i drukuje raport. Bez zapisu.

### Changes Required:

#### 1. Komenda importu

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Punkt wejścia całego importu M-3. Na razie tylko odczyt i raport. Konto jest rozpoznawane raz z e-maila (`User::where('email', …)->first()?->tree`), żeby S-02 dostał gotowe drzewo do przekazania w dół. Nie `firstOrFail()`: konsola Laravela ma `setCatchExceptions(false)`, więc `ModelNotFoundException` wyleciałby z komendy (i z testu) zamiast kodu 1, a `tree` jest nullable (`UserFactory::withoutTree()`).

**Contract**: `#[Signature('import:research {email : E-mail konta, do którego drzewa trafi import} {path : Katalog repo badawczego}')]` i polski `#[Description]`, wzorem `RestoreBackup`.

Zachowanie:
- **Nieznany e-mail, konto bez drzewa albo brak katalogu / `drzewo/`:** `$this->error(...)` po polsku i `self::FAILURE`, wzorem `RestoreBackup.php:51-55`.
- **Raport po odczycie:**
  - nagłówek z e-mailem konta i ścieżką,
  - podsumowanie (pliki przejrzane, z blokiem, bez bloku; osoby przeczytane; relacje rodzic–dziecko; małżeństwa),
  - tabela „Pominięte wpisy” (Plik | Powód) i tabela „Ostrzeżenia” (Plik | Opis); każda tylko, gdy niepusta,
  - z `-v` dodatkowo lista plików bez bloku,
  - linia końcowa, że nic nie zostało zapisane.
- **Kod wyjścia:** `self::SUCCESS` także przy pominiętych wpisach (FR-004: błędne pomijane, reszta idzie).

#### 2. Test komendy

**File**: `tests/Feature/ImportResearchCommandTest.php`

**Intent**: Dowodzi raportu i tego, że komenda niczego nie zapisuje w żadnym drzewie. Ustawienie ma dwóch użytkowników z osobami o tych samych nazwiskach co w fixture'ach (reguła: każdy test importu ma drugiego użytkownika z nakładającymi się danymi).

**Contract**: `$this->artisan('import:research', [...])` z `expectsOutputToContain` / `expectsTable` (sprawdzić aktualne API przez `search-docs`). Przypadki:
- raport na fixture'ach: liczby, wiersz pominięcia z powodem, wiersz ostrzeżenia, `assertSuccessful`,
- liczby osób, wydarzeń i relacji obu użytkowników niezmienione po przebiegu,
- nieznany e-mail daje `assertFailed` z komunikatem,
- konto bez drzewa (`User::factory()->withoutTree()`) daje `assertFailed` z komunikatem,
- brak katalogu `drzewo/` daje `assertFailed`,
- `-v` wypisuje plik bez bloku.

### Success Criteria:

#### Automated Verification:

- Test komendy przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`
- Komenda jest zarejestrowana: `vendor/bin/sail artisan list import` pokazuje `import:research`

#### Manual Verification:

- Przebieg na kopii repo z kilkoma skonwertowanymi plikami przodków (`vendor/bin/sail artisan import:research <e-mail konta testowego> <ścieżka w kontenerze>`) daje czytelny raport, a celowo zepsuty blok pojawia się w „Pominięte wpisy” z powodem, który da się poprawić bez zaglądania w kod
- Autor potwierdza, że raport przy dziesiątkach plików mieści się w terminalu i da się go czytać (tabele, kolejność po nazwie pliku)

**Implementation Note**: Po fazie i zielonych testach automatycznych poprosić autora o ręczny przebieg na prawdziwych plikach, potem o pełny zestaw `vendor/bin/sail artisan test --compact`.

---

## Testing Strategy

### Unit Tests:

- `ResearchDate::parse` dla każdego rodzaju daty, granic roku (999, 1000, bieżący, bieżący+1), niemożliwych dni, odwróconego zakresu i tekstu spoza gramatyki.

### Integration Tests:

- `ReadResearchRepo` na `tests/Fixtures/research-repo/`: każdy powód pominięcia, każde ostrzeżenie, duplikat id (oba pliki pominięte), małżeństwa zgodne, jednostronne, rozbieżne, z osobą bez bloku i powtórzone w jednym pliku, implex, pliki bez bloku, zignorowany `_PRZODKOWIE.md`.
- `ParsePersonBlock` na treści z CRLF, z BOM i z niecytowaną pełną datą (pominięcie z powodem, bez przesunięcia dnia).
- Komenda: raport, kod wyjścia, nieznany e-mail, konto bez drzewa, brak katalogu, `-v`, brak zapisów u dwóch użytkowników.

### Manual Testing Steps:

1. Skonwertować w repo badawczym 3–5 plików przodków według kontraktu (w tym implex i małżeństwo w obu plikach).
2. Skopiować repo w zasięg Sail (np. `storage/gen-tree-import/`) i uruchomić komendę na koncie testowym.
3. Zepsuć celowo datę w jednym bloku i sprawdzić powód w raporcie.
4. Uruchomić z `-v` i sprawdzić listę plików bez bloku.

## Performance Considerations

Brak istotnych: ok. 300 małych plików czytanych raz, odczyt w pamięci.

## Migration Notes

Brak migracji. Jedyna zmiana wdrożeniowa to `symfony/yaml` w `require`: obraz produkcyjny po przebudowie zawiera pakiet.

## References

- Roadmap: `context/foundation/roadmap.md` (S-01)
- PRD: `context/foundation/prd.md` (FR-001 – FR-004, Constraints & Compatibility)
- Wzór komendy: `app/Console/Commands/RestoreBackup.php:15-20`
- Wzór testu komendy: `tests/Backup/RestoreCommandTest.php`
- Wzór wartości: `app/Support/PartialDate.php`, test `tests/Unit/PartialDateTest.php`
- Konwersja dat w YAML: `vendor/symfony/yaml/Inline.php:866-886`
- Szablon wpisu w repo badawczym: `CLAUDE.md:51-66` repo badawczego; konwencja stałych ID: `CLAUDE.md:86` repo

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Kontrakt bloku i parser YAML

#### Automated

- [x] 1.1 `vendor/bin/sail composer why symfony/yaml` wymienia `laravel/react-starter-kit` (projekt główny) jako wymagający pakietu
- [x] 1.2 `vendor/bin/sail composer validate` przechodzi

#### Manual

- [x] 1.3 Autor przegląda kontrakt i potwierdza, że da się w nim zapisać kilku prawdziwych przodków (implex, zgon bez daty, data juliańska, małżeństwo w dwóch plikach)

### Phase 2: Odczyt i walidacja bloków

#### Automated

- [x] 2.1 Testy odczytu i dat przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/ResearchDateTest.php tests/Feature/ResearchRepoReadTest.php`
- [x] 2.2 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [x] 2.3 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`

### Phase 3: Komenda i raport

#### Automated

- [x] 3.1 Test komendy przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/ImportResearchCommandTest.php`
- [x] 3.2 Analiza statyczna przechodzi: `vendor/bin/sail composer types:check`
- [x] 3.3 Formatowanie: `vendor/bin/sail bin pint --dirty --format agent`
- [x] 3.4 Komenda jest zarejestrowana: `vendor/bin/sail artisan list import` pokazuje `import:research`

#### Manual

- [x] 3.5 Przebieg na kopii repo z kilkoma skonwertowanymi plikami przodków daje czytelny raport, a celowo zepsuty blok pojawia się w „Pominięte wpisy” z powodem, który da się poprawić bez zaglądania w kod
- [x] 3.6 Autor potwierdza, że raport przy dziesiątkach plików mieści się w terminalu i da się go czytać (tabele, kolejność po nazwie pliku)
