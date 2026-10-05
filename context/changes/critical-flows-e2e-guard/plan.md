# F-01 critical-flows-e2e-guard — plan implementacji

## Overview

Cztery testy Playwright chronią dwie ścieżki z guardrails PRD przed przebudową S-04, S-05 i S-06 (`prd.md:209`, `roadmap.md:90-104`):

- **FR-025:** fakt potwierdzony źródłem prowadzi do tego samego źródła i tego samego skanu (wyrocznia sha256);
- **FR-026:** rodzica, dziecko i małżonka, istniejącego lub nowego, dodaje się z pustych slotów drzewa. Relację sprawdzamy z obu stron w podglądzie `?person=`.

Testy korzystają z jednego modułu pomocniczego, więc przebudowa paneli i drzewa zmienia jeden plik, a nie asercje. Pliki źródeł z E2E dostają własny wolumin. Okno `choose()` rozstrzygamy jednorazową sondą. Na koniec `test-plan.md` dostaje status fazy 4, bramkę i wzorzec §6.6.

## Current State Analysis

- Playwright jest skonfigurowany: `playwright.config.ts`, projekt `setup` z `storageState`, osobny kontener Sail z bazą `e2e` na porcie 8100 (`scripts/e2e-server.sh`). Istnieją jednak tylko `tests/e2e/auth.setup.ts` i `tests/e2e/seed.spec.ts`. Nic nie chroni FR-025 ani FR-026 (`roadmap.md:86`).
- Wszystkie specyfikacje działają w trybie `fullyParallel: true` (`playwright.config.ts:14`) na jednym użytkowniku i jednym drzewie (`trees.user_id` unikalne). Baza `e2e` nie jest czyszczona między przebiegami (`e2e-server.sh:16-18`: `migrate`, nie `migrate:fresh`).
- Kontener E2E montuje repozytorium (`compose.yaml:20-21`). Pliki źródeł lądują w `storage/app/private/sources/{tree_id}/` i `…/thumbnails/` (`StoreSource.php:33-47`). Drzewo E2E i deweloperskie drzewo mają id 1, więc dzielą katalog.
- Na ścieżkach slotów żaden `useState` nie trzyma id osoby. Każde id pochodzi z propsa `sheet` (`research.md` §2). Pest pokrywa kontrakt „prop → żądanie → baza” (`tests/Feature/WriteTargetsTest.php`). E2E dokłada to, czego Pest nie widzi: czy UI po przebudowie nadal pozwala przejść ścieżkę i trafia we właściwą osobę lub źródło.
- `tests/e2e` jest objęte Oxlintem (`vite.config.ts:46-56` bez ignore), ale nie `tsc` (`tsconfig.json:115-119` obejmuje tylko `resources/js`).

## Desired End State

- `npx playwright test` przechodzi na zielono, z równoległymi workerami, wielokrotnie pod rząd. Cztery nowe specyfikacje w `tests/e2e/` chronią FR-025 i FR-026. Każda z nich zrobiła się czerwona na asercji ryzyka przy celowym uszkodzeniu, które potem cofnięto.
- Po zielonym przebiegu w bazie `e2e` nie zostaje żadna osoba ani źródło z prefiksem testowym. Pliki źródeł E2E nie trafiają do katalogu deweloperskiego `storage/app/private/sources/`.
- Przejście modala na panel (S-06), przebudowa siatki (S-05) i nowy picker (S-04) wymagają zmian w `tests/e2e/support/`, a nie w asercjach.
- W `test-plan.md` faza 4 ma status `complete` z uzasadnieniem „ochrona przed przebudową”. Są tam też bramka e2e „lokalnie przed S-04 – S-06, CI później”, wypełniony §6.6 i werdykt sondy okna `choose()`.

### Key Discoveries:

- `persons.store` przekierowuje na `/tree/{id}?person={id}` (`PersonController.php:103`), a `sources.store` na `/sources/{id}` (`SourceController.php:104`). Id nowego rekordu odczytamy więc z nagłówka `Location` przy `maxRedirects: 0`.
- `persons.store` przyjmuje `birth.year` i `birth.place` (`PersonStoreRequest.php:38`), więc tworzy osobę razem ze zdarzeniem urodzenia. Id zdarzenia jest tylko w propsie `personPreview.events[].id` (`EventResource.php:38`, `HandleInertiaRequests.php:54-58`).
- `sources.store` wymaga `kind` i `file` (jpg/png/webp, `SourceStoreRequest.php:24-25`). Oryginał jest zapisywany bajt w bajt (`StoreSource.php:35`) i serwowany bez zmian (`SourceController.php:168-180`). Miniatura jest kodowana od nowa, więc do porównania sha256 się nie nadaje.
- `page.request` dzieli ciasteczka z kontekstem przeglądarki, ale nie wysyła `Sec-Fetch-Site`. Żądania zapisu wymagają więc `X-XSRF-TOKEN` z ciasteczka `XSRF-TOKEN`, zdekodowanego z URL (`PreventRequestForgery.php:145-147`, `:185-187`).
- `persons.spouses.store` przekierowuje na `/tree/{focus}?spouse={nowyId}` (`PersonSpouseController.php:41`). Tak przygotujemy osobę z dwoma związkami.
- Nazwy dostępności slotów, tytułów dialogów, toastów i menu związku są wypisane dosłownie w `research.md` §2 i §3. Wzorzec testu to `tests/e2e/seed.spec.ts`: nagłówek pochodzenia z ryzykiem, komentarze kroków, lokatory rolami.
- `playwright-cli run-code` daje dostęp do `page.route`, więc sondę okna `choose()` da się przeprowadzić bez zmian w kodzie.

## What We're NOT Doing

- **Joba E2E w CI.** To osobna, późniejsza zmiana (decyzja 1 w `research.md`). F-01 testy uruchamia się lokalnie przed S-04 – S-06.
- **Stałego testu wyścigu w oknie `choose()`.** Zamiast niego robimy jednorazową sondę w fazie 3. Potwierdzony błąd trafia do osobnej zmiany (`/10x-frame`) z własnym testem i nie jest naprawiany w F-01.
- **Żadnego kodu produkcyjnego.** Celowe uszkodzenia są tymczasowe i nigdy nie trafiają do commita.
- **FR-027 (usunięcie z listą skutków) i FR-029 (404).** Pokrywa je Pest (`DeleteImpactTest.php`, `OwnershipBoundariesTest.php`).
- **Asercji na wyglądzie.** Bez położenia slotów, `aria-modal`, zasłonięcia drzewa, treści kafelka pickera i kształtu URL strony źródła. Nie używamy też `Podgląd`, `Wstecz`, kliknięcia karty ani przycisku zamknięcia modala, bo S-05 i S-06 je zmieniają.
- **Użytkownika na workera, testowych tras ani komend artisan.** Zostaje wspólny użytkownik E2E z unikalnym sufiksem (decyzja 2).
- **Zmian w `context/foundation/test-stack.md`.** Ten plik zapisuje `/10x-e2e-setup`. Wolumin opisuje komentarz w `e2e-server.sh` i §6.6 w `test-plan.md`.
- **Viewportu mobilnego.** E2E działa na Desktop Chrome (`playwright.config.ts`).

## Implementation Approach

Kolejność: najpierw infrastruktura, potem pierwszy konsument modułu pomocniczego, potem pozostali konsumenci, na końcu dokumentacja. Faza 1 izoluje pliki, zanim jakikolwiek test zacznie je tworzyć. Faza 2 buduje moduł pomocniczy razem z testem ścieżki B. Ten test używa wszystkich operacji API (osoba ze zdarzeniem, źródło multipart, powiązanie, odczyt propsów, sprzątanie), więc moduł jest od razu sprawdzony na prawdziwym konsumencie. Faza 3 dokłada trzy testy slotów na gotowym module i sondę. Faza 4 zapisuje wynik w `test-plan.md`.

Każdy test ma ten sam kształt:

1. Przygotowanie danych przez `page.request` z unikalnym sufiksem.
2. Chroniona ścieżka w UI, z lokatorami `getByRole` / `getByLabel` / `getByText` zawężonymi do dialogu osoby.
3. Asercja na danych z obu stron.
4. Sprzątanie w `finally` (osoby i źródła z sufiksem testu, każde żądanie z `toBeOK`).

Bez `waitForTimeout`. Gotowość to zmiana treści: toast, zniknięcie slotu, nowa nazwa węzła związku.

## Critical Implementation Details

- **Odczyt propsów bez wersji Inertia.** Do id zdarzenia i do wyszukania osób utworzonych w UI moduł pobiera HTML strony (`/tree/{id}?person={id}`, `/persons?q={sufiks}`) i parsuje osadzony obiekt strony Inertia. Nie wysyła żądania `X-Inertia`, bo wymaga ono zgodnej wersji zasobów, a niezgodna wersja daje 409. Obiekt jest osadzony jako `<script data-page="app" type="application/json">…</script>` (`<x-inertia::app />`, `vendor/inertiajs/inertia-laravel/src/View/Components/App.php:29`), a nie w atrybucie `data-page` na `<div>`. Parsujemy więc treść tego elementu `script` (JSON, znaki `<` i `>` są zakodowane przez `JSON_HEX_TAG`). Id elementu potwierdzić jednym `curl` w pierwszym przebiegu.
- **Sprzątanie po sufiksie, nie po zapamiętanych id.** Osoby utworzone w UI („nowa osoba”) nie mają id znanego testowi. `finally` szuka więc wszystkich osób i źródeł z sufiksem testu i je usuwa. Najpierw źródła (`DeleteSource` kasuje `event_source` i pliki), potem osoby (`DeletePerson` kasuje związki, zdarzenia i relacje). Sprzątanie musi zadziałać także po czerwonej asercji.
- **Prawa zapisu woluminu.** PHP w kontenerze E2E działa jako `sail` (`WWWUSER`), a świeży nazwany wolumin Dockera należy do roota. Jeśli nie zadbamy o właściciela, pierwszy upload zwróci błąd zapisu.
- **Zawężanie do dialogu osoby.** Na stronie drzewa istnieją niewidoczne dla oka (sr-only) nagłówki `Rodzice` i `Dzieci` oraz `<h1>` z imieniem osoby w centrum. Gdy S-06 zamieni modal na panel bez `aria-modal`, lokator bez zawężenia złamie tryb strict. Każde wyszukiwanie sekcji `Rodzina` idzie więc przez `getByRole('dialog', { name: display_name })`.

## Faza 1: Izolacja plików źródeł E2E

### Overview

Pliki źródeł tworzone przez aplikację E2E trafiają do nazwanego woluminu Dockera zamiast do deweloperskiego `storage/app/private/sources/`. To ten sam model co osobna baza `e2e`.

### Changes Required:

#### 1. Skrypt serwera E2E

**File**: `scripts/e2e-server.sh`

**Intent**: zamontować nazwany wolumin na `storage/app/private/sources` w kontenerze `root-keeper-e2e`. Przerwany albo czerwony przebieg nie zostawia wtedy sierot w katalogu deweloperskiego drzewa 1, a pliki E2E przetrwają między przebiegami razem z bazą `e2e`.

**Contract**:
- `docker compose run` dostaje montaż woluminu (np. `root-keeper-e2e-sources`) na `/var/www/html/storage/app/private/sources`.
- Katalog jest zapisywalny dla `WWWUSER` przed startem `php -S`. Właściciela ustawia kontener pomocniczy jako root albo krok przygotowania, który działa jako root, zanim przejdzie do `sail`.
- Komentarz w skrypcie wyjaśnia, dlaczego wolumin istnieje i jak go wyczyścić (`docker volume rm …`). Zaznacza też, że wolumin usuwa się razem z bazą `e2e`, bo po jej odtworzeniu zostałyby w nim pliki bez rekordów.
- Zachowanie skryptu wobec Playwright się nie zmienia: ten sam port, `trap` i `SIGTERM`.

### Success Criteria:

#### Automated Verification:

- Seed i logowanie przechodzą na świeżo uruchomionym serwerze: `npx playwright test tests/e2e/seed.spec.ts`
- Format i lint przechodzą: `vendor/bin/sail npm run check`

#### Manual Verification:

- Źródło wgrane w aplikacji E2E (`http://localhost:8100/sources` → `Dodaj źródło`) wyświetla skan i miniaturę, a w `storage/app/private/sources/` na hoście nie pojawia się nowy plik.
- Po zatrzymaniu i ponownym starcie serwera E2E wcześniej wgrany skan nadal się wyświetla, a potem zostaje usunięty z UI.

**Implementation Note**: po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Faza 2: Moduł pomocniczy i test „fakt → źródło → skan”

### Overview

Powstaje moduł `tests/e2e/support/` i jego pierwszy konsument: test ryzyka **FR-025 fact-source-scan**. Fakt urodzenia potwierdzony źródłem prowadzi z podglądu osoby do tego samego źródła, a skan ma bajty wgranej fikstury.

### Changes Required:

#### 1. Dane testowe i API

**File**: `tests/e2e/support/api.ts` (nazwa do potwierdzenia przy implementacji, katalog stały)

**Intent**: przygotowywać i sprzątać dane przez istniejące trasy aplikacji, bez dostępu do bazy, żeby test przechodził w UI tylko chronioną ścieżkę.

**Contract**:
- `uniqueSuffix(testInfo)` zwraca jeden token bez spacji i znaków `%`, `_`: stały prefiks testowy, timestamp, indeks workera, `repeatEachIndex` i `retry`. Prefiks pozwala w fazach 2 i 3 policzyć pozostałości w bazie.
- Sufiks trafia do `last_name` osoby i do nazwy źródła, bo tylko tam go znajdzie sprzątanie: `/persons?q=` dopasowuje wyłącznie `first_name` i `last_name`. `SourceController::index` nie filtruje po `q` (zwraca wszystkie źródła drzewa, filtr działa w kliencie), więc `cleanupBySuffix` pobiera listę źródeł bez `q` i wybiera te, których nazwa zawiera sufiks.
- Nagłówek `X-XSRF-TOKEN` jest zdekodowany z ciasteczka kontekstu. Jeśli ciasteczka brak, najpierw następuje jedno `GET /`.
- `createPerson(fields) → id`: `persons.store` z `maxRedirects: 0`, id z `Location`.
- `createSpouse(focusId, fields) → id`: `persons.spouses.store`, id z `?spouse=`.
- `createSource({ kind, name, fixture }) → id`: `sources.store` multipart, id z `Location`.
- `linkSource(eventId, sourceId)`: `events.sources.store`.
- `personPreview(personId) → props.personPreview` (z HTML, patrz Critical Implementation Details).
- `cleanupBySuffix(suffix)`: usuwa źródła, potem osoby, a każde `DELETE` sprawdza `toBeOK`.

#### 2. Nawigacja odporna na S-02, S-05 i S-06

**File**: `tests/e2e/support/navigation.ts`

**Intent**: trzymać w jednym miejscu wszystko, co S-05, S-06 i S-02 zmienią. Wtedy przebudowa poprawia ten plik, a nie asercje (`research.md` §4).

**Contract**:
- `gotoTree(page, personId)` → `/tree/{id}`, czeka na widoczny slot lub kartę.
- `openPerson(page, personId, displayName) → Locator` → wejście przez `?person={id}`, zwraca `getByRole('dialog', { name: displayName })`.
- `familySection(dialog, 'Rodzice' | 'Małżeństwa' | 'Dzieci')` → zakładka `/^Rodzina/` i sekcja zawężona do dialogu.
- Nie używa `Podgląd`, `Wstecz`, kliknięcia karty ani przycisku zamknięcia.

#### 3. Test ścieżki B

**File**: `tests/e2e/fact-source-scan.spec.ts`

**Intent**: chronić FR-025. Fakt z plakietką `Potwierdzone` prowadzi do tego samego źródła i tego samego skanu, a strona źródła pokazuje fakt z powrotem.

**Contract**:
- Nagłówek pochodzenia według `seed.spec.ts` (ryzyko FR-025, `prd.md:209`, `roadmap.md` F-01).
- Przygotowanie przez API: osoba z `birth.year` i `birth.place`, źródło `akt metrykalny` z `tests/Fixtures/portrait-small.jpg`, powiązanie urodzenia ze źródłem.
- Ścieżka w UI:
  1. `openPerson` → region `Urodzenie` → plakietka `Potwierdzone…` → link `Otwórz źródło {nazwa}`.
  2. Kliknięcie → strona źródła z nagłówkiem nazwy.
- Asercje:
  - `img` o nazwie źródła ma `src` z id utworzonego źródła;
  - sha256 treści `GET /sources/{id}/file` równa się sha256 fikstury;
  - `Potwierdza` zawiera `Urodzenie — {osoba}`.
- Sprzątanie: `cleanupBySuffix` w `finally`.

### Success Criteria:

#### Automated Verification:

- Test fakt → źródło → skan przechodzi: `npx playwright test tests/e2e/fact-source-scan.spec.ts`
- Celowe uszkodzenie (`SourceController::file` serwuje miniaturę zamiast oryginału) daje czerwony wynik na asercji sha256, po czym zostaje cofnięte
- Test jest stabilny i niezależny: `npx playwright test tests/e2e/fact-source-scan.spec.ts --repeat-each=3`
- Po zielonym przebiegu w bazie `e2e` nie ma osób ani źródeł z prefiksem testowym (zapytanie `count(*)` przez `vendor/bin/sail exec -T pgsql psql … -d e2e`)
- Format i lint przechodzą: `vendor/bin/sail npm run check`

#### Manual Verification:

- Przegląd specyfikacji i modułu pomocniczego pod kątem pięciu antywzorców z `.claude/skills/10x-e2e/references/e2e-anti-patterns.md`: brak CSS, XPath, `nth()` i `waitForTimeout`, a każda asercja dotyczy danych.

**Implementation Note**: po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Faza 3: Relacje z pustych slotów drzewa i sonda okna `choose()`

### Overview

Trzy testy chronią FR-026: po jednym na rodzica, dziecko i małżonka. Każdy sprawdza wejście „istniejąca osoba” i „nowa osoba” tego samego dialogu oraz relację z obu stron w podglądzie `?person=`. Do tego dochodzi jednorazowa sonda okna `choose()`, której wynik zapisuje faza 4.

### Changes Required:

#### 1. Test rodzica

**File**: `tests/e2e/tree-slot-parent.spec.ts`

**Intent**: rodzica dodanego z pustego slotu widać jako rodzica tej osoby, a ją jako jego dziecko.

**Contract**:
- Przygotowanie przez API: dziecko C i kandydat na ojca F.
- Ścieżka:
  1. `gotoTree(C)` → slot `Dodaj ojca: C` → dialog `Dodaj ojca — C` → `Szukaj osoby` z sufiksem → wybór F → toast `Dodano relację`.
  2. Slot `Dodaj matkę: C` → `Nowa osoba` → `Imię`, `Nazwisko`, `Płeć: Kobieta` → `Zapisz` → toast `Dodano rodzica`.
- Asercje: w `Rodzice` u C są F i nowa matka, a w `Dzieci` u F i u matki jest C.

#### 2. Test dziecka w drugim związku

**File**: `tests/e2e/tree-slot-child.spec.ts`

**Intent**: dziecko dodane po przełączeniu na drugi związek ma za rodziców osobę w centrum i *właściwego* partnera (decyzja 5).

**Contract**:
- Przygotowanie przez API: osoba P, partnerzy S1 i S2 (`createSpouse` dwa razy), kandydat na dziecko K.
- Ścieżka:
  1. `gotoTree(P)` → menu węzła związku → `menuitemradio` drugiego związku → czekanie, aż węzeł związku albo karta partnera pokaże S2 (nie tylko URL).
  2. Slot `Dodaj dziecko: P i S2` → wybór K → toast `Dodano dziecko`.
  3. Ten sam slot → `Nowa osoba` → toast `Dodano dziecko`.
- Asercje: w `Rodzice` u K i u nowego dziecka są P i S2, a S1 nie ma. W `Dzieci` u S2 jest dwoje dzieci.

#### 3. Test małżonka

**File**: `tests/e2e/tree-slot-spouse.spec.ts`

**Intent**: małżonka dodanego z pustego slotu i z menu związku widać w `Małżeństwa` po obu stronach (decyzja 6).

**Contract**:
- Przygotowanie przez API: osoba P bez związków i kandydat S.
- Ścieżka:
  1. `gotoTree(P)` → slot `Dodaj małżonka: P` → `Nowa osoba` → toast `Dodano małżonka`.
  2. Menu węzła związku → `menuitem` `Dodaj małżonka` → wybór S → toast `Dodano małżonka`.
- Asercje: w `Małżeństwa` u P jest nowy małżonek i S, a u każdego z nich jest P.

#### 4. Sonda okna `choose()` (bez commitowanego testu)

**File**: brak. Wynik zapisuje faza 4 w `context/foundation/test-plan.md`.

**Intent**: rozstrzygnąć, czy kliknięcie `Dodaj dziecko` w trakcie wizyty `?spouse=` celuje w poprzednią parę (`union-node.tsx:79-97`, `research.md` §2). To pytanie jest otwarte od fazy 2.

**Contract**:
- W `playwright-cli` na danych z sufiksem: `run-code` z `page.route`, który opóźnia żądanie z `spouse=` o kilka sekund.
- Przebieg: przełączenie na drugi związek → kliknięcie slotu dziecka w czasie opóźnienia → zapis nowej osoby → odczyt rodziców w podglądzie.
- Wynik do zapisania w fazie 4: potwierdzony albo niepotwierdzony, z przebiegiem i obserwacją. Potwierdzony błąd → propozycja nowej zmiany przez `/10x-frame`, F-01 nie jest blokowane.
- Na koniec dane sondy są posprzątane.

### Success Criteria:

#### Automated Verification:

- Test rodzica przechodzi: `npx playwright test tests/e2e/tree-slot-parent.spec.ts`
- Test dziecka przechodzi: `npx playwright test tests/e2e/tree-slot-child.spec.ts`
- Test małżonka przechodzi: `npx playwright test tests/e2e/tree-slot-spouse.spec.ts`
- Celowe uszkodzenia dają czerwony wynik na asercji relacji, po czym zostają cofnięte: rodzic (`parent-slot-dialog.tsx` zamienia `parent_id` i `child_id`), dziecko (`add-child-dialog.tsx` bierze partnera pierwszego związku), małżonek (`add-spouse-dialog.tsx` wysyła attach do wybranej osoby zamiast do osoby w centrum). To pliki TSX, a serwer E2E serwuje build zrobiony przy starcie (`reuseExistingServer`), więc przed każdym czerwonym przebiegiem zatrzymaj serwer na 8100 (skrypt przebuduje frontend), a po cofnięciu uszkodzenia przebuduj go ponownie przed następnym zielonym przebiegiem
- Cały suite jest stabilny przy równoległych workerach: `npx playwright test --repeat-each=2`
- Po zielonym przebiegu w bazie `e2e` nie ma osób ani źródeł z prefiksem testowym
- Format i lint przechodzą: `vendor/bin/sail npm run check`

#### Manual Verification:

- Sonda okna `choose()` przeprowadzona, wynik (potwierdzony albo niepotwierdzony, z obserwacją) zanotowany do fazy 4
- Przegląd trzech specyfikacji pod kątem pięciu antywzorców oraz sprawdzenie, że żadna nie używa `Podgląd`, `Wstecz`, kliknięcia karty ani przycisku zamknięcia modala

**Implementation Note**: po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Faza 4: Domknięcie w `test-plan.md`

### Overview

`test-plan.md` dostaje nowy stan fazy 4, wzorzec testu przeglądarkowego i werdykt sondy, żeby kolejne zmiany (S-04 – S-06, przyszły job CI) wiedziały, co chroni ścieżki krytyczne i jak dopisać kolejny test.

### Changes Required:

#### 1. Plan testów

**File**: `context/foundation/test-plan.md`

**Intent**: zastąpić warunkowe „tylko jeśli faza 2…” rzeczywistym uzasadnieniem i wynikiem (decyzja 7).

**Contract**:
- §3, wiersz fazy 4: status `complete`, cel przepisany na „ochrona przed przebudową” (`prd.md:209`), folder zmiany `context/changes/critical-flows-e2e-guard/`.
- §4, wiersz e2e: 4 specyfikacje przepływów obok seed.
- §5, wiersz bramki e2e: „lokalnie przed S-04 – S-06, CI w osobnej zmianie”.
- §6.6: wzorzec z gotowych testów, czyli kolejno:
  - przygotowanie przez `tests/e2e/support/` z sufiksem;
  - ścieżka w UI zawężona do dialogu osoby;
  - asercja relacji z obu stron albo sha256;
  - sprzątanie po sufiksie w `finally`;
  - celowe uszkodzenie;
  - wolumin plików E2E i jego czyszczenie (razem z bazą `e2e`, żeby nie zostawały pliki bez rekordów).
- Akapit werdyktu fazy 2: dopisek z wynikiem sondy `choose()` (data, przebieg, wynik, ewentualna nowa zmiana).
- §8: `Strategy (§1–§5) last reviewed` → data domknięcia.

### Success Criteria:

#### Automated Verification:

- Cały suite E2E przechodzi na koniec: `npx playwright test`
- `test-plan.md` nie zawiera już `TBD` w §6.6 ani statusu `not started` w wierszu fazy 4: `grep -n "TBD — see §3 Phase 4\|not started" context/foundation/test-plan.md` nie zwraca wiersza fazy 4

#### Manual Verification:

- Przegląd `test-plan.md`: §3, §4, §5, §6.6 i werdykt sondy są spójne z gotowymi testami i decyzjami z `research.md`

**Implementation Note**: po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### E2E (Playwright):

- 4 testy, po jednym na ryzyko (budżet z decyzji 4): `fact-source-scan`, `tree-slot-parent`, `tree-slot-child` i `tree-slot-spouse`.
- Każdy test ma własną konfigurację, akcję, asercję i sprzątanie oraz unikalny sufiks. Testy przechodzą w dowolnej kolejności i równolegle.
- Każdy test ma celowe uszkodzenie zapisane w Success Criteria i cofnięte przed commitem.

### Integration / Unit:

- Bez zmian. Kontrakt „prop → żądanie → baza” pokrywa `tests/Feature/WriteTargetsTest.php`.

### Manual Testing Steps:

1. Wgranie źródła w aplikacji E2E nie tworzy pliku w deweloperskim `storage/app/private/sources/` (faza 1).
2. Sonda okna `choose()` z opóźnioną wizytą `?spouse=` (faza 3).
3. Przegląd specyfikacji pod kątem antywzorców (fazy 2 i 3).

## Performance Considerations

Przygotowanie przez API zamiast UI skraca każdy test do samej chronionej ścieżki. Cztery specyfikacje w trybie `fullyParallel` nie powinny wydłużyć lokalnego przebiegu ponad minutę, nie licząc budowania serwera.

## Migration Notes

Brak migracji. Nowy wolumin Dockera zaczyna pusty. Źródła utworzone w bazie `e2e` przed fazą 1 (dziś 0) wskazywałyby na pliki poza woluminem.

## References

- Research: `context/changes/critical-flows-e2e-guard/research.md` (decyzje użytkownika 1–7)
- Roadmapa: `context/foundation/roadmap.md:90-104` (F-01)
- PRD: `context/foundation/prd.md:209` (guardrails chronione E2E)
- Plan testów: `context/foundation/test-plan.md:54`, `:86`, `:196-200`
- Wzorzec testu: `tests/e2e/seed.spec.ts`; konfiguracja `playwright.config.ts`, `scripts/e2e-server.sh`
- Reguły E2E: `.claude/skills/10x-e2e/references/e2e-anti-patterns.md`, `e2e-quality-rules.md`
- Powłoka panelu (lokator dialogu): `resources/js/components/side-panel.tsx:153-158`, `context/archive/2026-10-04-side-panel-shell/plan.md:58`

## Odchylenia od planu (po implementacji i impl-review 2026-10-05)

- **Gotowość po zapisie bez toastów.** Testy slotów czekają na zamknięcie dialogu (`toBeHidden`), a test rodzica także na zniknięcie slotu, zamiast na toasty `Dodano …`. Flash data żyje w jednej sesji wspólnej dla równoległych testów, więc toast mógłby pochodzić z innego testu.
- **`gotoTree` czeka na nagłówek `h1`**, a nie na slot czy kartę. Następny lokator i tak czeka na właściwy element.
- **Celowe uszkodzenia dziecka i małżonka inne niż w planie.**
  - Dziecko: `other_parent_id: null`.
  - Małżonek: `spouse_id: focus.id`.
  - Planowane uszkodzenie małżonka („attach do wybranej osoby”) jest symetryczne i daje tę samą relację, więc test słusznie zostaje zielony. To była wada planu.
- **Sprzątanie w fixture `suffix`** (`tests/e2e/support/fixtures.ts`) zamiast w `finally`, czyli Fix A z F1 w impl-review.
  - Teardown działa także po timeoucie.
  - Ma guard prefiksu `e2etest-`.
  - Źródła z API usuwa po id.
- **Pomocniki zapisu wysyłają `Accept: application/json`** i wymagają 302 z oczekiwaną ścieżką `Location`, a DELETE nie podąża za przekierowaniem (F2).
- **Lokatory slotów, węzła związku i linku źródła siedzą w `support/navigation.ts`** (F3), więc S-05 zmienia jeden plik.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Izolacja plików źródeł E2E

#### Automated

- [x] 1.1 Seed i logowanie przechodzą na świeżo uruchomionym serwerze — 1bd142c
- [x] 1.2 Format i lint przechodzą — 1bd142c

#### Manual

- [x] 1.3 Źródło wgrane w aplikacji E2E nie tworzy pliku w deweloperskim storage/app/private/sources/ — 1bd142c
- [x] 1.4 Skan przetrwał restart serwera E2E i został usunięty z UI — 1bd142c

### Phase 2: Moduł pomocniczy i test „fakt → źródło → skan”

#### Automated

- [x] 2.1 Test fakt → źródło → skan przechodzi — dbcee8f
- [x] 2.2 Celowe uszkodzenie (file serwuje miniaturę) daje czerwony wynik na asercji sha256, cofnięte — dbcee8f
- [x] 2.3 Test stabilny przy --repeat-each=3 — dbcee8f
- [x] 2.4 Brak osób i źródeł z prefiksem testowym w bazie e2e po przebiegu — dbcee8f
- [x] 2.5 Format i lint przechodzą — dbcee8f

#### Manual

- [x] 2.6 Przegląd specyfikacji i modułu pomocniczego pod kątem pięciu antywzorców — dbcee8f

### Phase 3: Relacje z pustych slotów drzewa i sonda okna choose()

#### Automated

- [x] 3.1 Test rodzica przechodzi — bb7ec74
- [x] 3.2 Test dziecka przechodzi — bb7ec74
- [x] 3.3 Test małżonka przechodzi — bb7ec74
- [x] 3.4 Celowe uszkodzenia rodzica, dziecka i małżonka (z przebudową serwera przed czerwonym przebiegiem i po cofnięciu) dają czerwony wynik na asercji relacji, cofnięte — bb7ec74
- [x] 3.5 Cały suite stabilny przy --repeat-each=2 — bb7ec74
- [x] 3.6 Brak osób i źródeł z prefiksem testowym w bazie e2e po przebiegu — bb7ec74
- [x] 3.7 Format i lint przechodzą — bb7ec74

#### Manual

- [x] 3.8 Sonda okna choose() przeprowadzona, wynik zanotowany do fazy 4 — bb7ec74
- [x] 3.9 Przegląd trzech specyfikacji pod kątem antywzorców i lokatorów zmienianych przez S-05/S-06 — bb7ec74

### Phase 4: Domknięcie w test-plan.md

#### Automated

- [x] 4.1 Cały suite E2E przechodzi — 9899f01
- [x] 4.2 test-plan.md bez TBD w §6.6 i bez not started w fazie 4 — 9899f01

#### Manual

- [x] 4.3 Przegląd test-plan.md: §3, §4, §5, §6.6 i werdykt sondy spójne z testami i decyzjami — 9899f01
