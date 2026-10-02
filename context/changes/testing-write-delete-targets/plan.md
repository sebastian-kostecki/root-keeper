# Zapis i usuwanie trafiają we właściwe dane (ryzyka #1, #3) — plan implementacji

## Overview

Faza 2 wdrożenia testów (`context/foundation/test-plan.md` §3). Testy Pest na prawdziwym PostgreSQL dowodzą dwóch rzeczy. (#1) Zapis z drzewa lub modala zmienia dokładnie wskazaną osobę lub parę, a potwierdzenie innych faktów zostaje. (#3) Usunięcie kasuje dokładnie to, co pokazało potwierdzenie, a dane współmałżonka i innych osób zostają. Narzędziem jest nowy helper różnicy wierszy (co zniknęło, co doszło, co się zmieniło — w każdej tabeli i na dysku `local`) oraz jedna scena rodzinna z kilkoma związkami i postronnymi osobami. Po fazach ryzyka siłę testów sprawdza selektywna bramka mutacyjna `pest --mutate` (wtyczka `pestphp/pest-plugin-mutate`, już zainstalowana; odpowiednik Strykera dla PHP). Na końcu cookbook §6.1, §6.4, §6.7 (testowanie mutacyjne) i werdykt badania dla fazy 4 (e2e) w §6.6.

Plan dodaje wyłącznie testy i dokumentację; kodu produkcyjnego ani zależności nie zmienia.

## Current State Analysis

Źródło: `context/changes/testing-write-delete-targets/research.md` (badanie i decyzje autora z 2026-10-02).

- **Frontend nie wymyśla id.** Każde id w żądaniu zapisu pochodzi z bieżących propsów Inertia w chwili kliknięcia: `sheet` (drzewo) albo `personPreview` (modal). Parę wybiera serwer z `?spouse=` (`app/Actions/Trees/BuildFamilySheet.php`, `add_child.parents`), a klient wysyła `parents[0]` w adresie i `parents[1]?.id ?? null` jako `other_parent_id` (`resources/js/components/tree/add-child-dialog.tsx:70-84`). Ryzyko #1 sprowadza się więc do kontraktu „prop → żądanie → baza”, który da się sprawdzić w Pest.
- **Wybór pary przy kilku związkach nie ma testu łańcucha.** `tests/Feature/TreeTest.php:515` sprawdza `add_child.parents` tylko dla jednego związku; `:133` sprawdza dzieci per związek, ale nie to, kto trafia do żądania. Testy zapisu z drzewa (`ParentChildRelationsTest.php:360-776`, `MarriagesTest.php:294-422`) budują id ręcznie na fiksturze jednej pary, bez asercji „nic innego się nie zmieniło”.
- **Listy skutków mają dwa źródła prawdy.** Osoba: klient liczy skutki z `personPreview` — `birth.sources`, `death.sources`, `marriages[].sources`, `parents`, `children`, `marriages` (`resources/js/components/persons/delete-person-dialog.tsx:44-54`), dane z `BuildPersonPreview.php:27` (relacje mają `relation_id`, `RelativeResource.php:32`). Źródło: serwer wysyła `confirms` — jeden wiersz na (zdarzenie × uczestnik), `is_last_source = sources_count === 1` (`SourceController.php:114-131`). Żaden test nie porównuje listy z faktycznie usuniętym zbiorem.
- **Kontrakt usunięcia = kaskady FK + akcje.** `DeletePerson.php:21-32` (małżeństwa osoby, potem zdarzenia z jednym uczestnikiem, potem osoba; relacje kaskadą; źródła nietknięte); `DeleteSource.php:17-26` (wiersz, kaskada `event_source`, potem oba pliki); `MarriageController.php:49-59`, `ParentChildRelationController.php:34-43`, `EventSourceController.php:34-48` (`detach`). `trees.root_person_id` → `nullOnDelete`.
- **Słabe asercje usunięć.** `EventSourcesTest.php:105` sprawdza tylko `assertDatabaseCount('event_source', 0)`; `SourcesTest.php:402` ma jedno źródło i jedno zdarzenie; `PersonsTest.php:874` nie ma małżeństw, relacji ani postronnych.
- **Narzędzia.** `ownershipSnapshot()` (`tests/Pest.php:109-141`) daje tylko skrót per tabela (równość przed/po), nie różnicę. Helpery `attachBirth/attachDeath/attachMarriage/linkParent` w `tests/Pest.php`. Środowisko testowe: sesja i cache `array` (`phpunit.xml:28,33`), więc żądania nie piszą do bazy niczego poza danymi domenowymi.

## Desired End State

- `tests/Pest.php` ma helper zrzutu wierszy i różnicy oraz builder sceny rodzinnej, używane przez oba nowe pliki testów.
- `tests/Feature/DeleteImpactTest.php`: dla usunięcia osoby, źródła, małżeństwa i relacji na scenie test dowodzi, że różnica w bazie i na dysku jest **dokładnie** zbiorem oczekiwanym z fikstury i wyroczni. Dla osoby i źródła ten sam zbiór jest równy liście skutków z propsa (`personPreview` / `confirms`).
- `tests/Feature/WriteTargetsTest.php`: przy kilku związkach `?spouse=` wybiera właściwą parę w propsie `sheet`. Każdy z 9 zapisów z drzewa i 4 zapisów z modala, z id wziętymi z propsa jak robi to UI, zmienia dokładnie oczekiwane wiersze. Po edycji osoby lub małżeństwa żadne powiązanie `event_source` nie znika.
- `test-plan.md` §6.1 i §6.4 opisują wzorzec zamiast TBD; §6.6 zapisuje werdykt badania dla fazy 4 (status §3 bez zmian).

- Bramka mutacyjna `pest --mutate` na klasach z faz 2–3 nie zostawia przetrwałej mutacji, która szkodziłaby użytkownikowi; każda świadomie zignorowana jest zapisana w planie z uzasadnieniem.
- `test-plan.md` §4/§5 notują `pest --mutate` jako bramkę ad hoc (nie CI), a §6.7 opisuje przebieg.

Weryfikacja: `vendor/bin/sail artisan test --compact tests/Feature/DeleteImpactTest.php tests/Feature/WriteTargetsTest.php` zielone; przebiegi `--mutate` z kryteriów faz 2–3 bez nieocenionych przetrwałych mutacji.

### Key Discoveries:

- Wszystkie tabele domenowe (`people`, `events`, `event_participants`, `event_source` — dawniej `event_scan`, `parent_child_relations`, `sources`, `trees`) mają kolumnę `id`, więc różnicę da się liczyć po `id`: ten sam `id` z inną treścią = „zmieniony”, brak = „usunięty”, nowy = „dodany”.
- Nowa osoba z drzewa zawsze dostaje zdarzenie urodzenia z uczestnikiem (`CreatePerson.php:46-57`); nowy małżonek dodatkowo zdarzenie małżeństwa z dwoma uczestnikami (`CreateSpouseForPerson.php:26-28`). Wyrocznia: archiwalne plany `2026-09-28-tree-child-existing-or-new`, `tree-spouse-existing-or-new`, `tree-parent-slot-existing-or-new` („nowa osoba atomowo z relacją”).
- Źródła id w UI: dziecko `add-child-dialog.tsx:70-84`; małżonek istniejący `add-spouse-dialog.tsx:87` (`spouse_id`, focus w adresie); rodzic istniejący `parent-slot-dialog.tsx:85-91` (`parent_id`, `child_id = slot.id`, `role = slot.role`, gdzie `slot = sheet.parents[block][i].parent_of`); „ożeń parę” `marry-couple-dialog.tsx:38` z par `family-sheet.tsx:113-127` (niezaślubieni rodzice bloku, `unmarried_parents[block]`) i `:215-225` (para centrum, gdy wybrany związek nie jest małżeństwem).
- Lista skutków osoby w propsie to zbiory id, nie liczby: `marriages[].id`, `parents[].relation_id`, `children[].relation_id`, `birth/death/marriages[].sources[].id`. Test porównuje zbiory; liczników z TSX nie przepisujemy (to byłby test lustrzany).
- `confirms` dla małżeństwa daje dwa wiersze (po jednym na małżonka) — zachowanie zamierzone (decyzja autora w research).
- Larastan (`phpstan.neon`) nie obejmuje `tests/`; bramki dla tej zmiany to Pest i Pint.
- **Testowanie mutacyjne jest już w repo.** `vendor/pestphp/pest-plugin-mutate` daje `pest --mutate` z `--class`, `--parallel`, `--min`, `--id`, `--covered-only`; pokrycie przez `pcov` (załadowany w Sail). Stryker mutuje tylko JS/TS, a testy tej fazy są w PHP, więc nie dałby sygnału; Infection rozwiązuje się z Pest 5 / PHPUnit 13 (`composer require --dev --dry-run`: 0.35.5, 16 pakietów), ale dublowałby wbudowane narzędzie.
- **Stan bazowy (smoke 2026-10-02):** `vendor/bin/sail bin pest tests/Feature/PersonsTest.php --mutate --class='App\Actions\Persons\DeletePerson' --no-cache` → 6 mutacji, wynik 83,33%, ~29 s. Przetrwała `RemoveMethodCall` w `DeletePerson.php:22` (usunięcie kasowania małżeństw osoby) — dokładnie luka ryzyka #3, którą faza 2 ma zabić.

## What We're NOT Doing

- **Vitest i wyciąganie logiki dialogów do `lib`** (payload dziecka, filtry płci, kwalifikowalność „ożeń parę”, liczniki skutków, `person-payload.ts`) — decyzja autora w research; odłożone.
- **Nowa reguła serwera dla „niezgodnej pary”** (`other_parent_id` z własnego drzewa, ale nie partner wybranego związku) — dane poprawne (decyzja autora); testy tylko dokumentują kontrakt null vs ustawiony.
- **Test okna wyścigu po `choose()`** (stary `sheet` do końca wizyty) i jakikolwiek e2e — werdykt trafia do §6.6, faza 4 zostaje „not started”.
- **Test współbieżności** walidacji „zmarła ze źródłami” (research Open Questions #3) — poza zakresem.
- **Zmiana strategii lub kolumn §3 test-planu** (np. „integration + unit”) — to Lekcja 1; odnotowujemy tylko odłożenie unit w cookbooku.
- **Przepisywanie istniejących testów** (`PersonsTest.php:874`, `SourcesTest.php:402`, `EventSourcesTest.php:105`) — nowe testy na scenie je nadbudowują; stare zostają.
- **Stryker (JS) i Infection** — Stryker nie mutuje PHP, a ta faza nie ma testów Vitest; Infection dublowałby `pest --mutate`. Nowych zależności nie dodajemy.
- **Bramka mutacyjna w CI i progi `--min`** — przebieg jest ad hoc po fazie ryzyka (CLAUDE.md: „nie ścigaj 100%”); bez ustalonego progu.
- **Zmiany kodu produkcyjnego.** Testy charakteryzują istniejący kod: czerwony test oznacza rozbieżność z wyrocznią — zatrzymaj się i zapytaj autora, zamiast przepisywać oczekiwanie z kodu.

## Implementation Approach

Kolejność: najpierw narzędzie i scena (faza 1), potem ryzyko #3 (faza 2) i #1 (faza 3) na tej samej scenie, na końcu cookbook (faza 4). Warstwa: Pest feature na PostgreSQL — kaskady FK, `nullOnDelete` i transakcje to SQL, stub by kłamał (test-plan §2). Hermetyczne stuby niepotrzebne: jedyną niedeterministyczną porażkę (plik nieusuwalny) pokrywa już `SourcesTest.php:468`.

Wspólny wzorzec testu w tym planie: scena → (dla ścieżek z UI) GET strony z propsem i odczyt id z propsa tak, jak robi to dialog → `$before = databaseRows()` → żądanie → `assertSessionHasNoErrors()` → `rowDiff($before, databaseRows())` porównane w całości z oczekiwaną różnicą zbudowaną z rekordów sceny (lista tabel z różnicą + id/klucze w każdej + pliki).

Oczekiwane wartości wyprowadzamy z fikstury (wiemy, co zbudowaliśmy) i z tabeli „Wyrocznia” w research — nigdy z zapytania, które liczy listę skutków, ani z ponownego wywołania kodu produkcyjnego.

Bramka mutacyjna (CLAUDE.md, „Testowanie mutacyjne — selektywna bramka jakości”): po zielonych testach fazy 2 i 3 uruchom `pest --mutate` zawężony przez `--class` do klas produkcyjnych, których dotyczy faza, razem z nowym plikiem testów i istniejącymi plikami tej domeny (żeby nie zgłaszać mutacji zabijanych już gdzie indziej). Każdą przetrwałą mutację oceń pytaniem „czy ta zmiana zaszkodziłaby użytkownikowi?”: tak → dodaj asercję na stanie danych w nowym pliku testów (z wyroczni, nie z kodu); nie (równoważna / kosmetyczna, np. komunikat toastu, kolejność w niepowiązanej metodzie kontrolera) → wpisz ją do „Ocena przetrwałych mutacji” w bloku fazy z jednym zdaniem uzasadnienia. Nie przypinaj szczegółów implementacji tylko po to, by zabić kosmetyczną mutację.

Tryb: wszystkie fazy przez `/10x-implement` — fazy 2–3 to testy charakteryzujące istniejący kod (oczekiwany wynik: zielony od razu), więc nie ma czerwonego testu przed kodem.

## Critical Implementation Details

- **Kolejność w teście z GET.** Odczyt propsa (`sheet`, `personPreview`, `confirms`) musi nastąpić przed `$before = databaseRows()` i przed żądaniem zapisu; Inertia w testach zwraca props w odpowiedzi GET, więc to zwykłe `->assertInertia(fn ($page) => ...)` albo odczyt `$response->inertiaProps(...)`/`viewData('page')` (sprawdź w `search-docs` API pomocnicze Inertia v3 dla Pest przed użyciem).
- **`Storage::fake('local')` przed sceną.** Scena zapisuje pliki źródeł na dysk `local`, a `databaseRows()` czyta ten dysk — fake musi być ustawiony wcześniej.
- **Klucze dodanych wierszy.** Nowe wiersze mają nieznane z góry `id`; oczekiwania dla „dodanych” formułuj po treści (np. pary `parent_id`/`child_id`, `event_id`/`person_id`, typ zdarzenia), a dla „usuniętych” i „zmienionych” po `id` rekordów sceny.

## Faza 1: Helper różnicy wierszy i scena rodzinna

### Overview

Dostarcza narzędzie „co dokładnie się zmieniło” i jedną scenę wspólną dla faz 2–3, tak aby każdy test mógł sprawdzić pełną różnicę zamiast pojedynczych rekordów.

### Changes Required:

#### 1. Zrzut wierszy i różnica

**File**: `tests/Pest.php`

**Intent**: Dodać obok `ownershipSnapshot()` dwie funkcje: zrzut wszystkich wierszy każdej tabeli `public` (poza `migrations`) oraz listy plików dysku `local`, i różnicę dwóch zrzutów. Różnica zawiera wyłącznie tabele, w których coś się zmieniło, więc lista jej kluczy sama dowodzi „nic poza tym”.

**Contract**:
- `databaseRows(): array{tables: array<string, array<string, array<string, mixed>>>, files: list<string>}` — wiersze odczytane jako `to_jsonb(t)` (zdekodowane do tablic), w każdej tabeli kluczowane `id` (dla tabel bez `id` — kanoniczną treścią wiersza); lista plików posortowana. Tabele wyprowadzane ze schematu tak jak w `ownershipSnapshot()` (nowa tabela wchodzi sama).
- `rowDiff(array $before, array $after): array{tables: array<string, array{removed: list<array<string, mixed>>, added: list<array<string, mixed>>, changed: list<array{before: array<string, mixed>, after: array<string, mixed>}>}>, files: array{removed: list<string>, added: list<string>}}` — tabele bez różnic pominięte; kolejność tabel i wierszy deterministyczna (po nazwie tabeli i `id`).
- PHPDoc z kształtem tablic jak przy `ownershipSnapshot()`; wymóg `Storage::fake('local')` w opisie.

#### 2. Scena rodzinna

**File**: `tests/Pest.php`

**Intent**: Builder sceny w drzewie podanego użytkownika, zwracający nazwane rekordy, z których testy budują oczekiwane różnice. Scena musi zawierać każdy kształt, przy którym błędny zapis lub zbyt szerokie usunięcie dałyby inny wynik niż wyrocznia.

**Contract**: `familyScene(User $user): array<string, Person|Event|Source>` (kształt w PHPDoc) — rekordy co najmniej:
- `focus` (mężczyzna, proband drzewa: `root_person_id`), z urodzeniem i zgonem.
- `focusFather`, `focusMother` — rodzice `focus`, **bez** małżeństwa (para do „ożeń parę” w bloku focus).
- `wifeA` (małżeństwo `marriageA`, 1880) i `wifeB` (`marriageB`, 1900); `coParent` — współrodzic bez małżeństwa; dzieci `childA` (focus+wifeA), `childB` (focus+wifeB), `childC` (focus+coParent), `childUnknown` (tylko focus). `wifeB` bez rodziców (puste sloty w bloku partnera).
- Źródła z plikami (oryginał i miniatura na dysku `local`): `sourceShared` przypięte do urodzenia `focus` (jedyne źródło tego zdarzenia), do `marriageA` i do urodzenia postronnej osoby; `sourceOther` przypięte do `marriageA` (drugie źródło — fakt zostaje potwierdzony) i do urodzenia `wifeA`; `sourceBystander` przypięte tylko do małżeństwa postronnych.
- Postronni: `bystander` i `bystanderSpouse` z małżeństwem i dzieckiem (relacje), urodzenie `bystander` potwierdzone `sourceShared`.
- Kandydaci do zapisów z drzewa, bez relacji: `loneMan`, `loneWoman`.

Builder używa istniejących helperów (`attachBirth`, `attachDeath`, `attachMarriage`, `linkParent`) i fabryk; nie woła tras.

### Success Criteria:

#### Automated Verification:

- `vendor/bin/sail artisan test --compact tests/Feature` zielone (helpery nie psują istniejących testów)
- `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu

#### Manual Verification:

- Scena w `tests/Pest.php` zawiera każdy rekord z listy w Contract (przegląd kodu)

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Usunięcia = lista skutków (ryzyko #3)

### Overview

Dowodzi, że zbiór faktycznie usuniętych lub odłączonych rekordów i plików jest równy liście skutków z potwierdzenia, a dane współmałżonka i postronnych zostają (test-plan §2, #3).

### Changes Required:

#### 1. Plik testów usunięć

**File**: `tests/Feature/DeleteImpactTest.php` (nowy, `vendor/bin/sail artisan make:test --pest DeleteImpactTest`)

**Intent**: Cztery testy na scenie, każdy z pełną różnicą `rowDiff`.

**Contract** (oczekiwania z fikstury i wyroczni research; nazwy testów opisowe, po angielsku jak w sąsiednich plikach):

1. **Usunięcie osoby (`persons.destroy` dla `focus`)** — trzy zbiory muszą być równe:
   - (a) oczekiwany z fikstury: `people` usunięta tylko `focus`; `events` usunięte urodzenie i zgon `focus` oraz `marriageA`, `marriageB` (także u `wifeA`/`wifeB`); `event_participants` usunięte dokładnie wiersze tych zdarzeń; `event_source` usunięte dokładnie powiązania tych zdarzeń; `parent_child_relations` usunięte dokładnie relacje (focusFather/focusMother → focus) i (focus → childA, childB, childC, childUnknown); `trees` zmieniony jeden wiersz z `root_person_id` → `null`; brak różnic w `sources` i w plikach. Lista tabel z różnicą = dokładnie te tabele.
   - (b) pokazany w propsie `personPreview` (GET `personPreviewUrl($focus)` przed usunięciem): `marriages[].id` = usunięte małżeństwa; `parents[].relation_id` ∪ `children[].relation_id` = usunięte relacje; pary (zdarzenie, źródło) z `birth.sources`, `death.sources`, `marriages[].sources` = usunięte wiersze `event_source`.
   - (c) faktyczna różnica w bazie.
2. **Usunięcie źródła (`sources.destroy` dla `sourceShared`)**:
   - `confirms` z `sources.show` (przed usunięciem) jako zbiór (`event_id`, `person.id`, `is_last_source`) = oczekiwany z fikstury: (urodzenie focus, focus, true), (marriageA, focus, false), (marriageA, wifeA, false), (urodzenie bystander, bystander, true) — dwa wiersze dla małżeństwa (decyzja autora).
   - Różnica: `sources` usunięty tylko `sourceShared`; `event_source` usunięte dokładnie wiersze (e, `sourceShared`) dla `e` = zbiór różnych `event_id` z `confirms`; pliki usunięte dokładnie `path` i `thumbnail_path` `sourceShared`; brak innych tabel (zdarzenia i osoby nietknięte).
   - Po usunięciu: każde zdarzenie z `is_last_source = true` ma 0 źródeł, każde z `false` ma ≥1 — lista mówi prawdę o utracie potwierdzenia.
3. **Usunięcie małżeństwa (`marriages.destroy` dla `marriageB`)**: różnica = `events` tylko `marriageB`, `event_participants` jego dwa wiersze, `event_source` jego powiązania (jeśli są); `people`, `parent_child_relations` (w tym childB z obojgiem rodziców), pliki — bez zmian.
4. **Usunięcie relacji (`parent-child-relations.destroy` dla relacji focus → childC)**: różnica = jeden wiersz `parent_child_relations`; nic poza tym (obie osoby i relacja coParent → childC zostają).

Każdy test: `Storage::fake('local')` → `familyScene()` → (dla 1–2) GET i odczyt propsa → `$before = databaseRows()` → żądanie z `assertSessionHasNoErrors()` i przekierowaniem → asercja różnicy.

#### 2. Bramka mutacyjna dla usunięć

**File**: brak nowego pliku; ewentualne dodatkowe asercje w `tests/Feature/DeleteImpactTest.php`, ocena w tym bloku planu.

**Intent**: Sprawdzić, że nowe testy zabijają mutacje w kodzie usuwającym, zwłaszcza `RemoveMethodCall` w `DeletePerson.php:22`, która w stanie bazowym przetrwała. Każdą przetrwałą mutację ocenić według reguły z Implementation Approach.

**Contract**: `vendor/bin/sail bin pest tests/Feature/DeleteImpactTest.php tests/Feature/PersonsTest.php tests/Feature/SourcesTest.php tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventSourcesTest.php --mutate --covered-only --class='App\Actions\Persons\DeletePerson,App\Actions\Sources\DeleteSource,App\Http\Controllers\SourceController,App\Http\Controllers\MarriageController,App\Http\Controllers\ParentChildRelationController'`. Wynik: każda przetrwała mutacja zabita nową asercją albo wpisana poniżej. Znane przykłady kosmetyczne (stan bazowy): `ConcatRemoveLeft/Right`, `ConcatSwitchSides` w `DeleteSource.php:24` — treść komunikatu wyjątku do `report()`; ignorowane świadomie.

**Ocena przetrwałych mutacji** (wypełnia implementujący): `plik:linia` — mutator — „szkodzi / nie szkodzi” — decyzja.

Przebieg 2026-10-02 (polecenie z Contract, `--no-cache`): 140 mutacji, 117 zabitych, 83,57%. Wszystkie 6 mutacji `DeletePerson.php` zabite, w tym `RemoveMethodCall` w linii 22. Pozostałe 23 przetrwałe:
- `DeleteSource.php:24` `ConcatRemoveLeft/Right`, `ConcatSwitchSides` — nie szkodzi: treść komunikatu wyjątku do `report()`; ignorowane.
- `SourceController.php:112`, `:156`, `:170`, `:187`, `:189` i `MarriageController.php:51`, `ParentChildRelationController.php:36` (`Gate::authorize` / `RemoveNot`) — nie dotyczy tej fazy: zabijane przez `OwnershipBoundariesTest.php` (poza listą plików polecenia); kontrolny przebieg z tym plikiem je zabił.
- `SourceController.php:58, 84, 99–102, 143, 202–203` (`rtrim`/`trim` filtra, lista sortowań, `except` w `store`/`update`, toast, nagłówki pliku) — nie szkodzi usunięciom: lista, tworzenie, edycja i nagłówki; poza ryzykiem #3; ignorowane.

### Success Criteria:

#### Automated Verification:

- `vendor/bin/sail artisan test --compact tests/Feature/DeleteImpactTest.php` zielone
- `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu
- Bramka mutacyjna fazy 2 (polecenie z Contract) kończy się, a mutacja `RemoveMethodCall` w `DeletePerson.php:22` jest zabita

#### Manual Verification:

- Każda przetrwała mutacja z bramki fazy 2 zabita asercją albo wpisana do „Ocena przetrwałych mutacji” z uzasadnieniem
- Kontrola mutacji spoza katalogu mutatorów Pest: usunięcie kroku małżeństw w `DeletePerson::handle` → test osoby czerwony; usuwanie tylko `path` (bez miniatury) w `DeleteSource::handle` → test źródła czerwony; `confirms` tylko dla pierwszego uczestnika zdarzenia w `SourceController::show` → test źródła czerwony; każda mutacja cofnięta
- Oczekiwane zbiory w testach zbudowane z rekordów sceny, nie z zapytań do bazy ani wywołań kodu produkcyjnego (przegląd kodu)

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3. Test czerwony od razu (bez mutacji) = rozbieżność z wyrocznią: zatrzymaj się i zapytaj autora.

---

## Faza 3: Zapisy trafiają we wskazaną osobę lub parę (ryzyko #1)

### Overview

Dowodzi, że zapis z drzewa lub modala, z id wziętymi z propsa tak jak robi to UI, zmienia dokładnie wskazaną osobę lub parę, inne osoby i pary są nietknięte, a potwierdzenie pozostałych faktów się nie zmienia (test-plan §2, #1).

### Changes Required:

#### 1. Wybór pary przez serwer

**File**: `tests/Feature/WriteTargetsTest.php` (nowy, `vendor/bin/sail artisan make:test --pest WriteTargetsTest`)

**Intent**: Na scenie z kilkoma związkami prop `sheet` dla `tree.show` z `?spouse=` niesie parę wybranego związku — to jedyne wejście, z którego klient bierze drugiego rodzica.

**Contract**: dataset po `spouse` → oczekiwane `add_child.parents` (id): `wifeA` → [focus, wifeA]; `wifeB` → [focus, wifeB]; `coParent` → [focus, coParent]; `unknown` → [focus]. Dla `wifeB` dodatkowo `parents.partner` to dwa sloty `unknown` z `parent_of.id = wifeB` i rolami `father`, `mother` (`BuildFamilySheet::parentSlots`, `:406-410`).

#### 2. Zapisy z drzewa

**File**: `tests/Feature/WriteTargetsTest.php`

**Intent**: Jeden test z datasetem 9 przypadków. Każdy: GET `tree.show` z danym `?spouse=` → zbudowanie żądania z propsa `sheet` według kontraktu dialogu (Key Discoveries) → różnica = dokładnie oczekiwane wiersze.

**Contract** (przypadek: spouse / żądanie z propsa → oczekiwana różnica):

| # | Związek | Żądanie (id z `sheet`) | Oczekiwana różnica |
|---|---|---|---|
| 1 | `wifeB` | `persons.children.attach(add_child.parents[0])`, `child_id = loneMan`, `other_parent_id = parents[1]` | `parent_child_relations` +2: (focus → loneMan), (wifeB → loneMan) |
| 2 | `unknown` | jw., `other_parent_id = null` | `parent_child_relations` +1: (focus → loneMan) |
| 3 | `coParent` | `persons.children.store(parents[0])`, ukryte `other_parent_id = parents[1]`, imię | `people` +1, `events` +1 (urodzenie), `event_participants` +1, `parent_child_relations` +2 (focus i coParent → nowa osoba) |
| 4 | `wifeB` | `persons.spouses.attach(focus.id)`, `spouse_id = loneWoman` | `events` +1 (małżeństwo), `event_participants` +2 (focus, loneWoman) |
| 5 | `wifeB` | `persons.spouses.store(focus.id)`, imię | `people` +1, `events` +2 (urodzenie, małżeństwo), `event_participants` +3 |
| 6 | `wifeB` | `parent-child-relations.store`: `parent_id = loneMan`, `child_id`/`role` z `parents.partner[0].parent_of` | `parent_child_relations` +1: (loneMan → wifeB) |
| 7 | `wifeB` | `persons.parents.store(parent_of.id)`, `role = parent_of.role`, imię, płeć zgodna z rolą | `people` +1, `events` +1, `event_participants` +1, `parent_child_relations` +1 (nowa osoba → wifeB) |
| 8 | `wifeA` | `marriages.store`: `person_id`/`spouse_id` = osoby z `parents.focus` (gdy `unmarried_parents.focus`) | `events` +1, `event_participants` +2 (focusFather, focusMother) |
| 9 | `coParent` | `marriages.store`: `focus.id` i `partner.person.id` (wybrany związek nie jest małżeństwem) | `events` +1, `event_participants` +2 (focus, coParent) |

We wszystkich przypadkach żadna inna tabela nie ma różnicy, `removed` i `changed` są puste (zapisy z drzewa tylko dodają), a pliki bez zmian. Przypadki 1 i 2 dokumentują kontrakt `other_parent_id` ustawiony vs `null` (decyzja autora: bez nowej reguły serwera).

#### 3. Zapisy z modala

**File**: `tests/Feature/WriteTargetsTest.php`

**Intent**: Edycja i przypinanie źródeł z podglądu osoby zmieniają tylko wskazane zdarzenie; potwierdzenia innych faktów (wiersze `event_source`) zostają — edycja nie kasuje zdarzeń, tylko aktualizuje je w miejscu (wyrocznia: `archive/2026-09-30-fact-confirmed-by-any-source/plan.md:14,37`).

**Contract** (id z `personPreview` dla `focus`):

| # | Żądanie | Oczekiwana różnica |
|---|---|---|
| 10 | `persons.update(focus)` z danymi `personPreview.personForm` i zmienionym rokiem urodzenia oraz miejscem zamieszkania | `people` zmieniony tylko focus; `events` zmienione tylko urodzenie focus (nowy rok); brak różnic w `event_source`, `event_participants` i innych tabelach |
| 11 | `marriages.update(marriageA)` (id z `marriages[]`) z nową datą | `events` zmienione tylko `marriageA`; brak różnic w `event_source` |
| 12 | `events.sources.store(marriageB)`, `source_id = sourceBystander` | `event_source` +1: (marriageB, sourceBystander) |
| 13 | `events.sources.destroy(marriageA, sourceShared)` (id z `marriages[].sources`) | `event_source` −1: tylko (marriageA, sourceShared); (marriageA, sourceOther) i (urodzenie focus, sourceShared) zostają |

#### 4. Bramka mutacyjna dla zapisów

**File**: brak nowego pliku; ewentualne dodatkowe asercje w `tests/Feature/WriteTargetsTest.php`, ocena w tym bloku planu.

**Intent**: Sprawdzić, że testy zapisów zabijają mutacje w wyborze pary (`BuildFamilySheet`), aktualizacji w miejscu (`UpdatePerson`, `UpdateMarriage`) i akcjach zapisu z drzewa. `BuildFamilySheet` ma dużo logiki prezentacji (etykiety, `lifespan`, `focus_side`) — mutacje tam, które nie zmieniają id w żądaniu ani zbioru zapisanych wierszy, to kandydaci do świadomego pominięcia, nie do nowych asercji.

**Contract**: `vendor/bin/sail bin pest tests/Feature/WriteTargetsTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/ParentChildRelationsTest.php tests/Feature/EventSourcesTest.php --mutate --covered-only --class='App\Actions\Trees\BuildFamilySheet,App\Actions\Persons\UpdatePerson,App\Actions\Relations\UpdateMarriage,App\Actions\Relations\AttachChildToParents,App\Actions\Relations\CreateChildForParents,App\Actions\Relations\CreateSpouseForPerson,App\Actions\Relations\CreateParentForPerson,App\Actions\Relations\CreateMarriage,App\Http\Controllers\PersonChildController,App\Http\Controllers\PersonSpouseController,App\Http\Controllers\EventSourceController'`. Wynik jak w fazie 2: każda przetrwała mutacja zabita albo oceniona poniżej. Gdy przebieg jest zbyt długi, uruchom go per klasa (to samo polecenie z jedną klasą w `--class`).

**Ocena przetrwałych mutacji** (wypełnia implementujący): `plik:linia` — mutator — „szkodzi / nie szkodzi” — decyzja.

### Success Criteria:

#### Automated Verification:

- `vendor/bin/sail artisan test --compact tests/Feature/WriteTargetsTest.php` zielone (dataset wyboru pary, 9 zapisów z drzewa, 4 zapisy z modala)
- `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu
- Bramka mutacyjna fazy 3 (polecenie z Contract) kończy się

#### Manual Verification:

- Każda przetrwała mutacja z bramki fazy 3 zabita asercją albo wpisana do „Ocena przetrwałych mutacji” z uzasadnieniem
- Kontrola mutacji spoza katalogu mutatorów Pest: `BuildFamilySheet` zawsze wybierający pierwszy związek → przypadki wyboru pary i 1/3/6/7 czerwone; `UpdatePerson` usuwający i tworzący urodzenie od nowa zamiast aktualizacji w miejscu → przypadek 10 czerwony; `detach` bez źródła (wszystkie powiązania zdarzenia) w `EventSourceController::destroy` → przypadek 13 czerwony; każda mutacja cofnięta
- Żądania w datasetach budowane z wartości propsa `sheet` / `personPreview`, nie z rekordów sceny wprost (przegląd kodu)

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4. Test czerwony od razu = rozbieżność z wyrocznią: zatrzymaj się i zapytaj autora.

---

## Faza 4: Cookbook i werdykt dla fazy 4

### Overview

Zapisuje wzorzec testu w cookbooku, aby kolejne endpointy i akcje usuwające dostawały test tego samego kształtu, utrwala bramkę mutacyjną w §4/§5/§6.7 oraz odpowiada na warunek z §3 dla fazy 4 (e2e).

### Changes Required:

#### 1. Cookbook §6.1 — wzorzec asercji na stanie danych

**File**: `context/foundation/test-plan.md`

**Intent**: Zastąpić linię „TBD — see §3 Phase 2” wzorcem: scena `familyScene()`, id z propsa jak w UI, `databaseRows()` / `rowDiff()`, pełna różnica (lista tabel + klucze). Reference test, polecenie uruchomienia, pułapki (Key Discoveries i Critical Implementation Details) oraz wpis „unit (Vitest) dla logiki dialogów — odłożony, decyzja autora 2026-10-02”.

**Contract**: sekcja `### 6.1 Test nowego endpointu (backend)` — bez TBD; format jak §6.5 (Location, Reference test, Run locally, Wyrocznia, Pułapki, „Nowy endpoint zapisu”).

#### 2. Cookbook §6.4 — test akcji usuwającej

**File**: `context/foundation/test-plan.md`

**Intent**: Zastąpić TBD wzorcem „trzy zbiory równe: fikstura = lista w propsie potwierdzenia = różnica w bazie i na dysku”, z przykładami osoby i źródła, ostrzeżeniem przed tautologią (lista liczona tym samym zapytaniem) i instrukcją dla nowej akcji usuwającej.

**Contract**: sekcja `### 6.4 Test akcji usuwającej (skutki = lista w potwierdzeniu)` — bez TBD.

#### 3. Cookbook §6.6 — werdykt badania fazy 2 dla e2e

**File**: `context/foundation/test-plan.md`

**Intent**: Dopisać pod TBD werdykt: id w żądaniach pochodzą z propsów Inertia, wybór pary robi serwer, a kontrakt „prop → żądanie → baza” pokrywa Pest (`WriteTargetsTest.php`); e2e uruchomić dopiero, gdy pojawi się stan id po stronie klienta albo gdy okno po `choose()` (stary `sheet` do końca wizyty) da potwierdzony błąd. Wiersz fazy 4 w §3 bez zmian (`not started`).

**Contract**: `### 6.6 Test przepływu w przeglądarce` — TBD zostaje, plus akapit „Werdykt fazy 2 (2026-10-02)”; §3 nietknięte poza tym, co zmienia orkiestrator.

#### 4. Testowanie mutacyjne w §4, §5 i nowym §6.7

**File**: `context/foundation/test-plan.md`

**Intent**: Zapisać bramkę mutacyjną tak, jak każe CLAUDE.md: selektywna, ad hoc po fazie ryzyka, nie w CI. Dzięki temu kolejne fazy uruchomią ją tym samym sposobem.

**Contract**:
- §4 Stack: nowy wiersz „mutation testing (backend) | `pestphp/pest-plugin-mutate` (`pest --mutate`) | wersja z `composer.lock` | ad hoc, zawężone `--class`; pokrycie `pcov`; Stryker tylko dla JS/TS — nieużywany, bo brak testów Vitest w fazach ryzyka”.
- §5 Quality Gates: wiersz „testowanie mutacyjne (`pest --mutate`) | lokalnie, po fazie ryzyka | ad hoc (nie wymagane w CI) | testy, które przechodzą mimo zepsutej linii”.
- §6.7 „Testowanie mutacyjne (bramka selektywna)”: Location (polecenie), Reference run (polecenia z faz 2–3 tego planu), przebieg (testy zielone → `--mutate --covered-only --class=…` z nowym plikiem i plikami domeny → ocena przetrwałych mutacji pytaniem „czy szkodzi użytkownikowi?”), pułapki (bez `--covered-only` klasy niepokryte zawyżają liczbę nieprzetestowanych; komunikaty wyjątków i etykiety to mutacje kosmetyczne; nie dodawać progu `--min` ani asercji przypinających implementację; `--no-cache` po zmianie testów). Numeracja: §6.7 po §6.6, bez zmiany istniejących numerów.

### Success Criteria:

#### Automated Verification:

- `grep -n "TBD — see §3 Phase 2" context/foundation/test-plan.md` nic nie zwraca
- `grep -n "pest --mutate" context/foundation/test-plan.md` zwraca wpisy w §4, §5 i §6.7
- `vendor/bin/sail artisan test --compact tests/Feature/DeleteImpactTest.php tests/Feature/WriteTargetsTest.php` zielone

#### Manual Verification:

- Wzorce §6.1 i §6.4 pozwalają napisać test nowej akcji bez czytania tego planu (przegląd przez autora)
- Werdykt w §6.6 zgadza się z research i nie zmienia statusu fazy 4 w §3

**Implementation Note**: Po zakończeniu fazy poproś autora o pełny przebieg `vendor/bin/sail artisan test --compact`.

---

## Testing Strategy

### Integration Tests (Pest, PostgreSQL):

- Usunięcia: osoba (trzy zbiory + `root_person_id`), źródło (`confirms` = odłączone zdarzenia, `is_last_source` = stan po, pliki tylko tego źródła), małżeństwo, relacja — wszystko na scenie z postronnymi.
- Zapisy: wybór pary z `?spouse=` przy czterech związkach; 9 zapisów z drzewa z id z `sheet`; 4 zapisy z modala z id z `personPreview`, w tym edycje bez utraty potwierdzeń.
- Przypadki brzegowe w scenie: związek z nieznanym partnerem (`other_parent_id = null`), współrodzic bez małżeństwa, fakt z dwoma źródłami, źródło dzielone z postronną osobą, proband jako usuwana osoba.

### Manual Testing Steps:

1. Bramki `pest --mutate` z faz 2 i 3 oraz ocena każdej przetrwałej mutacji.
2. Kontrole mutacji spoza katalogu mutatorów Pest z kryteriów faz 2 i 3 (zepsuć linię, zobaczyć czerwony test, cofnąć).
3. Pełny przebieg `vendor/bin/sail artisan test --compact` po fazie 4.

## Performance Considerations

`databaseRows()` czyta całe tabele, ale baza testowa ma kilkadziesiąt wierszy na test; scena jest budowana fabrykami bez tras. Brak wpływu na czas CI poza kilkunastoma nowymi przypadkami. Bramka `--mutate` działa tylko lokalnie: ~29 s dla jednej małej klasy i jednego pliku testów (stan bazowy); `BuildFamilySheet` da wielokrotnie więcej mutacji, dlatego dopuszczalny jest przebieg per klasa.

## References

- Research: `context/changes/testing-write-delete-targets/research.md`
- Test plan: `context/foundation/test-plan.md` §2 (#1, #3), §3 (faza 2, 4), §6.1, §6.4, §6.6
- Wzorzec poprzedniej fazy: `context/archive/2026-10-02-testing-relation-rules/plan.md`, cookbook §6.5
- Helpery: `tests/Pest.php` (`ownershipSnapshot`, `attachMarriage`, `linkParent`, `personPreviewUrl`)
- Kontrakt id w UI: `resources/js/components/tree/add-child-dialog.tsx:70-84`, `parent-slot-dialog.tsx:85-91`, `add-spouse-dialog.tsx:87`, `marry-couple-dialog.tsx:38`, `family-sheet.tsx:113-127`, `:215-225`
- Listy skutków: `resources/js/components/persons/delete-person-dialog.tsx:44-54`, `app/Http/Controllers/SourceController.php:114-131`
- Akcje usuwające: `app/Actions/Persons/DeletePerson.php:21-32`, `app/Actions/Sources/DeleteSource.php:17-26`
- Testowanie mutacyjne: `vendor/pestphp/pest-plugin-mutate`, `vendor/bin/sail bin pest --help` (sekcja MUTATION TESTING OPTIONS); reguły w `CLAUDE.md` („Testowanie mutacyjne — selektywna bramka jakości”)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Helper różnicy wierszy i scena rodzinna

#### Automated

- [x] 1.1 `vendor/bin/sail artisan test --compact tests/Feature` zielone (helpery nie psują istniejących testów) — b4d2bd8
- [x] 1.2 `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu — b4d2bd8

#### Manual

- [x] 1.3 Scena w `tests/Pest.php` zawiera każdy rekord z listy w Contract (przegląd kodu) — b4d2bd8

### Phase 2: Usunięcia = lista skutków (ryzyko #3)

#### Automated

- [x] 2.1 `vendor/bin/sail artisan test --compact tests/Feature/DeleteImpactTest.php` zielone
- [x] 2.2 `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu
- [x] 2.3 Bramka mutacyjna fazy 2 (polecenie z Contract) kończy się, a mutacja `RemoveMethodCall` w `DeletePerson.php:22` jest zabita

#### Manual

- [x] 2.4 Każda przetrwała mutacja z bramki fazy 2 zabita asercją albo wpisana do „Ocena przetrwałych mutacji” z uzasadnieniem
- [x] 2.5 Kontrola mutacji spoza katalogu mutatorów Pest: usunięcie kroku małżeństw w `DeletePerson::handle` → test osoby czerwony; usuwanie tylko `path` (bez miniatury) w `DeleteSource::handle` → test źródła czerwony; `confirms` tylko dla pierwszego uczestnika zdarzenia w `SourceController::show` → test źródła czerwony; każda mutacja cofnięta
- [x] 2.6 Oczekiwane zbiory w testach zbudowane z rekordów sceny, nie z zapytań do bazy ani wywołań kodu produkcyjnego (przegląd kodu)

### Phase 3: Zapisy trafiają we wskazaną osobę lub parę (ryzyko #1)

#### Automated

- [ ] 3.1 `vendor/bin/sail artisan test --compact tests/Feature/WriteTargetsTest.php` zielone (dataset wyboru pary, 9 zapisów z drzewa, 4 zapisy z modala)
- [ ] 3.2 `vendor/bin/sail bin pint --dirty --format agent` bez zmian po uruchomieniu
- [ ] 3.3 Bramka mutacyjna fazy 3 (polecenie z Contract) kończy się

#### Manual

- [ ] 3.4 Każda przetrwała mutacja z bramki fazy 3 zabita asercją albo wpisana do „Ocena przetrwałych mutacji” z uzasadnieniem
- [ ] 3.5 Kontrola mutacji spoza katalogu mutatorów Pest: `BuildFamilySheet` zawsze wybierający pierwszy związek → przypadki wyboru pary i 1/3/6/7 czerwone; `UpdatePerson` usuwający i tworzący urodzenie od nowa zamiast aktualizacji w miejscu → przypadek 10 czerwony; `detach` bez źródła (wszystkie powiązania zdarzenia) w `EventSourceController::destroy` → przypadek 13 czerwony; każda mutacja cofnięta
- [ ] 3.6 Żądania w datasetach budowane z wartości propsa `sheet` / `personPreview`, nie z rekordów sceny wprost (przegląd kodu)

### Phase 4: Cookbook i werdykt dla fazy 4

#### Automated

- [ ] 4.1 `grep -n "TBD — see §3 Phase 2" context/foundation/test-plan.md` nic nie zwraca
- [ ] 4.2 `grep -n "pest --mutate" context/foundation/test-plan.md` zwraca wpisy w §4, §5 i §6.7
- [ ] 4.3 `vendor/bin/sail artisan test --compact tests/Feature/DeleteImpactTest.php tests/Feature/WriteTargetsTest.php` zielone

#### Manual

- [ ] 4.4 Wzorce §6.1 i §6.4 pozwalają napisać test nowej akcji bez czytania tego planu (przegląd przez autora)
- [ ] 4.5 Werdykt w §6.6 zgadza się z research i nie zmienia statusu fazy 4 w §3
