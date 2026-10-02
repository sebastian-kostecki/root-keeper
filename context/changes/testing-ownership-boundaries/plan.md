# Granice własności (ryzyko #4) — plan implementacji

## Overview

Faza 3 wdrożenia testów (`context/foundation/test-plan.md` §3), część dla ryzyka #4: inny zarejestrowany użytkownik nie może odczytać ani zmienić danych mojego drzewa, podając id z mojego drzewa w adresie lub w treści żądania. Plan zamienia rozproszone testy „cudzego id” na jeden dataset po trasach z generycznym dowodem braku zapisu, dodaje strażnika kompletności tras, naprawia jedyną znalezioną realną rozbieżność z wyrocznią (5 tras `persons/{person}/…` walidujących treść przed autoryzacją) i domyka pozostałe granice (`?person=`, wstrzyknięty `tree_id`, cudzy passkey). Ryzyko #5 (reguły relacji) jest poza tym planem — wymaga osobnego badania.

## Current State Analysis

Źródło: `context/changes/testing-ownership-boundaries/research.md` (badanie + decyzje autora z 2026-10-02).

- **Wyrocznia** (research → Decisions #1, #2, #4):
  - cudze **istniejące** id w adresie → **404 i brak zapisu, niezależnie od treści** (także pustej lub niepoprawnej) — prd v4 `context/foundation/prd.md:143`, `CLAUDE.md`;
  - cudze id w **treści** → błąd walidacji bez zapisu, ten sam komunikat co dla nieistniejącego id (wariant z archiwum, F5) — 9 istniejących testów jest z tym zgodnych i zostaje bez zmian;
  - cudze `?person=` → 404 całej strony;
  - cudzy passkey (`DELETE /user/passkeys/{passkey}`) → **403** akceptowane (kod vendor, dane konta), klucz nie jest usuwany.
- **19 nazw tras z id w adresie** (`routes/web.php:17-40`); 14 z nich daje dziś 404 niezależnie od treści (polityka w `authorize()` Form Requestu albo bramka w kontrolerze bez Form Requestu).
- **5 tras `persons/{person}/…` waliduje treść przed autoryzacją**: `persons.parents.store`, `persons.children.attach`, `persons.children.store`, `persons.spouses.attach`, `persons.spouses.store`. Form Requesty nie mają `authorize()`, a `Gate::authorize('view', $person)` stoi w ciele kontrolera (`PersonParentController.php:19`, `PersonChildController.php:22,43`, `PersonSpouseController.php:22,40`) — cudza osoba + niepoprawna treść daje 302 z błędem walidacji zamiast 404 (wyrocznia istnienia id).
- **Testy cudzego id** istnieją dla wszystkich 19 tras, ale rozproszone po 6 plikach, z niejednolitym dowodem „niczego nie zapisano” (luki: `MarriagesTest.php:242`, `EventSourcesTest.php:94,104`, `PersonsTest.php:754,921` bez wydarzeń osoby) i bez wariantu z pustą treścią.
- Brak testów: cudze `?person=` poza `persons.index`, `tree_id` w treści `persons.store`/`sources.store`, usunięcie cudzego passkeya.

## Desired End State

- `tests/Feature/OwnershipBoundariesTest.php` zawiera dataset wszystkich 19 tras z id w adresie: trasy z treścią w dwóch wariantach (poprawna i pusta), pozostałe w jednym; każdy przypadek → 404 i niezmieniony skrót wszystkich tabel trwałych oraz listy plików na dysku `local`.
- Strażnik kompletności: test pada, gdy w aplikacji pojawi się trasa z parametrem w adresie, której nie ma w datasecie.
- 5 tras `persons/{person}/…` autoryzuje osobę z adresu w `authorize(): Response` Form Requestu, przed walidacją.
- Rozproszone testy w pełni pokryte przez dataset są usunięte; testy z dodatkowymi asercjami zostają.
- Pokryte dodatkowo: cudze `?person=` na stronach hosta, ignorowany `tree_id` w treści, cudzy passkey → 403 bez usunięcia.
- `test-plan.md` §6.5 opisuje wzorzec testu własności (część dla ryzyka #4).

Weryfikacja: `vendor/bin/sail artisan test --compact tests/Feature` zielone; ręczna kontrola mutacji (usunięcie `authorize()` lub bramki → dataset czerwony).

### Key Discoveries:

- `persons.spouses.store` używa wprost `PersonStoreRequest` (`PersonSpouseController.php:38`), tej samej klasy co `persons.store` (trasa bez `{person}`) — `authorize()` nie może trafić do klasy bazowej; potrzebny osobny `PersonSpouseStoreRequest`.
- `PersonParentStoreRequest` i `PersonChildStoreRequest` dziedziczą po `PersonStoreRequest`; `PersonChildAttachRequest` i `PersonSpouseAttachRequest` po `FormRequest`.
- Wzorzec docelowy: `EventSourceStoreRequest::authorize(): Response` z `Gate::inspect(...)` (`app/Http/Requests/EventSourceStoreRequest.php:16-19`) i kontroler bez `Gate` (`EventSourceController.php:18-28`).
- `PersonPolicy::view` zwraca `Response::denyAsNotFound()` dla cudzej osoby (`app/Policies/PersonPolicy.php:14-19`); `FormRequest::passesAuthorization()` zamienia taki `Response` na 404 (research, `vendor/.../FormRequest.php:342-351`).
- Wzorzec skrótu tabel: `tableDigests()` + `durableTables()` w `tests/BackupTestCase.php:260-289` (liczba wierszy + `md5(string_agg(t::text …))` per tabela `public`).
- W testach `SESSION_DRIVER=array`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync` (`phpunit.xml:28-33`) — w Feature z bazy zapisuje tylko aplikacja; poza skrótem wystarczy wykluczyć `migrations`.
- Trasa passkeya ma middleware `password.confirm` (`vendor/laravel/fortify/routes/routes.php:185-187,217-219`), a pakiet nie dostarcza fabryki passkeyów.
- `TreeTest.php:33` testuje akcję `SetTreeRootPerson` bezpośrednio (obrona w głąb), nie trasę — nie jest duplikatem datasetu.

## What We're NOT Doing

- **Ryzyko #5** (trzeci rodzic, cykl, niezgodna płeć) — osobne `/10x-research` w tym samym folderze zmiany, potem uzupełnienie planu.
- **Zmiana wyroczni dla id w treści** na 404 — świadomie przyjęty błąd walidacji (research Decisions #1); poprawa brzmienia `test-plan.md:42` należy do `/10x-test-plan --refresh` (Lekcja 1).
- **Obrona w głąb w akcjach `app/Actions/Relations/`** i ponowne pobranie `source_id` przez drzewo w `EventSourceController::store` — dziś nieeksploatowalne (każde wywołanie idzie przez kontroler z regułą `exists` ograniczoną do drzewa).
- **Wspólna reguła „należy do mojego drzewa”** zamiast 9 ręcznych `Rule::exists(...)->where('tree_id', …)` — refaktor bez zmiany zachowania.
- **Konfiguracja serwera WWW** (czy nic nie wystawia `storage/`) — poza repozytorium (research Open Question #4).
- **Zmiana statusu 403 dla cudzego passkeya** na 404 — kod vendor, wyjątek przyjęty.
- **Refaktor `tests/BackupTestCase`** na wspólny helper skrótu — helper w `tests/Pest.php` powstaje osobno; ujednolicenie poza zakresem.
- **Wygląd strony 404** (goła strona bez powłoki aplikacji) — §7 test-planu.

## Implementation Approach

Kolejność zgodna z łańcuchem lekcji: najpierw test, który dziś pada (faza 1, test-first), potem rozszerzenie siatki na trasy już chronione (faza 2, testy charakteryzujące — oczekiwane zielone), potem pozostałe granice spoza adresu (faza 3), na końcu cookbook (faza 4). Warstwa: Pest feature na prawdziwym PostgreSQL — polityki porównują `tree_id`, reguły `exists` to SQL, stub by kłamał (research, Architecture Insights).

Definicje używane w całym planie:

- **Poprawna treść** — treść, która przeszłaby walidację, gdyby rekord z adresu należał do atakującego (id w treści pochodzą z drzewa atakującego). Dowodzi, że 404 nie zależy od tego, czy walidacja przeszła.
- **Pusta treść** — `[]`. Dowodzi, że autoryzacja poprzedza walidację.
- **Brak zapisu** — skrót bazy (liczba wierszy + md5 zawartości każdej tabeli `public` poza `migrations`) i posortowana lista plików na dysku `local` są identyczne przed i po żądaniu.

## Faza 1: Pięć tras `persons/{person}/…` → 404 niezależnie od treści

Tryb: `/10x-tdd`. Pierwszy czerwony test: „POST na `persons/{cudza}/children/attach` z pustą treścią zwraca 404 i niczego nie zapisuje”.

### Overview

Wprowadza helper „brak zapisu”, plik `OwnershipBoundariesTest.php` z datasetem 5 tras zagnieżdżonych (2 warianty treści) i naprawia kolejność autoryzacji. Przed poprawką 5 przypadków z pustą treścią jest czerwonych (302 z błędami walidacji), 5 z poprawną treścią zielonych.

### Changes Required:

#### 1. Helper skrótu stanu

**File**: `tests/Pest.php`

**Intent**: Globalna funkcja zwracająca skrót bazy i plików, żeby każdy test własności dowodził braku zapisu jedną asercją, także w tabelach pomocniczych (uczestnicy wydarzeń, `event_source`), o których autor testu by nie pomyślał.

**Contract**: `function ownershipSnapshot(): array` — `['tables' => array<string, array{rows: int, digest: string}>, 'files' => list<string>]`; tabele z `information_schema.tables` (`table_schema = 'public'`, `BASE TABLE`) bez `migrations`, zapytanie jak w `BackupTestCase::tableDigests()`; pliki z `Storage::disk('local')->allFiles()`, posortowane. Wymaga `Storage::fake('local')` w teście (inaczej czyta prawdziwy `storage/app/private`).

#### 2. Dataset 5 tras zagnieżdżonych

**File**: `tests/Feature/OwnershipBoundariesTest.php` (nowy, `vendor/bin/sail artisan make:test --pest OwnershipBoundariesTest`)

**Intent**: Jeden sparametryzowany test: atakujący (zalogowany, z własnym drzewem i osobami do treści) wysyła żądanie na trasę z osobą z drzewa ofiary; oczekiwane 404 i `ownershipSnapshot()` bez zmian.

**Contract**: dataset nazwany `'<route name> / <wariant>'` (np. `'persons.children.attach / pusta treść'`); wpis = nazwa trasy, metoda HTTP, closure budująca parametry adresu z rekordów ofiary, closure budująca treść z rekordów atakującego. Poprawne treści: `persons.parents.store` — `role=parent` + `first_name`; `persons.children.attach` — `child_id` osoby atakującego; `persons.children.store` — `first_name`; `persons.spouses.attach` — `spouse_id` osoby atakującego; `persons.spouses.store` — `first_name`. Struktura wpisu ma pomieścić w fazie 2 trasy z kilkoma parametrami i bez treści.

#### 3. Autoryzacja przed walidacją

**File**: `app/Http/Requests/PersonParentStoreRequest.php`, `PersonChildAttachRequest.php`, `PersonChildStoreRequest.php`, `PersonSpouseAttachRequest.php`

**Intent**: Osoba z adresu jest autoryzowana zanim reguły zobaczą treść, więc cudza osoba daje 404 niezależnie od treści (wzorzec `EventSourceStoreRequest`).

**Contract**: `public function authorize(): Response` zwracające `Gate::inspect('view', $this->route('person'))` (ta sama zdolność, którą dziś sprawdzają kontrolery).

#### 4. Osobny Form Request dla nowego małżonka

**File**: `app/Http/Requests/PersonSpouseStoreRequest.php` (nowy, `make:request`), `app/Http/Controllers/PersonSpouseController.php`

**Intent**: `persons.spouses.store` dostaje własną klasę z `authorize()`, bo `PersonStoreRequest` obsługuje też `persons.store` bez `{person}`.

**Contract**: `class PersonSpouseStoreRequest extends PersonStoreRequest` z samym `authorize(): Response` jak wyżej; `PersonSpouseController::store(PersonSpouseStoreRequest $request, …)`.

#### 5. Kontrolery bez zdublowanej bramki

**File**: `app/Http/Controllers/PersonParentController.php`, `PersonChildController.php`, `PersonSpouseController.php`

**Intent**: Usunąć `Gate::authorize('view', $person)` i nieużywany import `Gate`, bo autoryzację przejął Form Request (jak `EventSourceController::store`).

**Contract**: sygnatury akcji bez zmian poza typem żądania w `PersonSpouseController::store`.

### Success Criteria:

#### Automated Verification:

- Przed poprawką dataset ma dokładnie 5 czerwonych przypadków (wariant „pusta treść”), 5 zielonych: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php`
- Po poprawce dataset 5 tras jest zielony (10 przypadków)
- Istniejące testy tras zagnieżdżonych przechodzą (happy path i błędy walidacji własnej osoby): `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/MarriagesTest.php tests/Feature/PersonsTest.php`
- Pint bez zmian po formatowaniu: `vendor/bin/sail bin pint --dirty --format agent`
- Larastan bez błędów: `vendor/bin/sail bin phpstan analyse`

#### Manual Verification:

- Z własną osobą w drzewie dodanie rodzica, dziecka i małżonka (nowego i istniejącego) z UI działa jak przed zmianą, a błąd walidacji (np. pusta nazwa) nadal pokazuje się przy polu, nie jako 404

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przed fazą 2.

---

## Faza 2: Dataset wszystkich 19 tras i strażnik kompletności

Tryb: `/10x-implement` (testy charakteryzujące — oczekiwane zielone).

### Overview

Rozszerza dataset o pozostałe 14 tras, dodaje test kompletności tras i usuwa rozproszone testy w pełni pokryte datasetem. Uzupełnia dowody braku zapisu w testach, które zostają.

### Changes Required:

#### 1. Pozostałe trasy w datasecie

**File**: `tests/Feature/OwnershipBoundariesTest.php`

**Intent**: Każda trasa z id w adresie ma przypadek z cudzym rekordem; trasy z treścią w obu wariantach.

**Contract**:
- dwa warianty (poprawna + pusta treść): `persons.update`, `sources.update`, `events.sources.store` (poprawna = `source_id` źródła atakującego), `marriages.update`;
- jeden wariant (brak treści): GET `tree.show`, `sources.show`, `sources.file`, `sources.thumbnail`; `tree.root-person.update`; DELETE `persons.destroy`, `sources.destroy`, `parent-child-relations.destroy`, `marriages.destroy`;
- `events.sources.destroy` dwa przypadki: cudze wydarzenie + źródło atakującego, wydarzenie atakującego + cudze źródło;
- źródło ofiary ma oryginał i miniaturę na zafałszowanym dysku `local` (żeby skrót plików wykrył skasowanie przy `sources.destroy`); wydarzenia ofiary mają uczestników i powiązane źródło (żeby skrót wykrył odpięcie przy `persons.destroy` / `marriages.destroy`).

#### 2. Strażnik kompletności tras

**File**: `tests/Feature/OwnershipBoundariesTest.php`

**Intent**: Nowy endpoint z id w adresie bez przypadku w datasecie wywala CI zamiast przejść niezauważony.

**Contract**: zbiór nazw tras z `Route::getRoutes()`, których akcja jest w przestrzeni `App\Http\Controllers` i których URI ma parametr (`{…}`), równy zbiorowi nazw tras użytych w datasecie. Dataset trzymany jako jedna lista wpisów (np. funkcja zwracająca tablicę), z której korzystają i dataset, i strażnik. Komunikat błędu wymienia brakujące i nadmiarowe nazwy.

#### 3. Usunięcie zdublowanych testów

**File**: `tests/Feature/PersonsTest.php`, `SourcesTest.php`, `TreeTest.php`, `EventSourcesTest.php`, `ParentChildRelationsTest.php`, `MarriagesTest.php`

**Intent**: Jedno źródło prawdy dla „cudze id w adresie → 404 bez zapisu”, bez nadmiarowych kopii (zgoda autora na usunięcie: wywiad planu, 2026-10-02).

**Contract**: usuwany jest test, którego **każda** asercja ma odpowiednik w wierszu datasetu. Kandydaci (zweryfikować przed usunięciem): `PersonsTest.php:754,921`; `SourcesTest.php:60,122,133,432,640`; `TreeTest.php:22,144`; `EventSourcesTest.php:82,138,173`; `ParentChildRelationsTest.php:153,422,573,723`; `MarriagesTest.php:242,253,393,443`. Zostają: testy id w treści (wyrocznia: błąd walidacji), `TreeTest.php:33` (akcja), `PersonsTest.php:984` (obejmuje też nieistniejące id), `EventSourcesTest.php:94,104` (inna treść niż dataset) — te dwa dostają asercję braku zapisu (`ownershipSnapshot()` bez zmian albo `event_source` bez wierszy).

### Success Criteria:

#### Automated Verification:

- Dataset 19 tras zielony (ok. 29 przypadków), strażnik zielony: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php`
- Cały katalog Feature zielony po usunięciu duplikatów: `vendor/bin/sail artisan test --compact tests/Feature`
- Pint bez zmian po formatowaniu: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Kontrola mutacji: chwilowe usunięcie `authorize()` z `PersonChildAttachRequest` i osobno `Gate::authorize` z `PersonController::destroy` daje czerwony dataset (oba przywrócone)
- Kontrola strażnika: chwilowo dopisana trasa z parametrem w `routes/web.php` wywala strażnika z jej nazwą (przywrócone)
- Lista usuniętych testów i powód zostawienia pozostałych kandydatów zapisane w opisie commita

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie ręcznej weryfikacji przed fazą 3.

---

## Faza 3: Granice spoza adresu — `?person=`, `tree_id` w treści, passkey

Tryb: `/10x-implement` (oczekiwane zielone; czerwony wynik to błąd do zgłoszenia autorowi, nie do cichej poprawki).

### Overview

Domyka przypadki z research Decisions #3 i #4, które nie są trasą z id w adresie.

### Changes Required:

#### 1. Cudze `?person=` na stronach hosta

**File**: `tests/Feature/OwnershipBoundariesTest.php`

**Intent**: Prop `personPreview` (każda odpowiedź Inertia, `HandleInertiaRequests.php:74-86`) nie ujawnia cudzej osoby na żadnej stronie.

**Contract**: dataset stron hosta `tree.index`, `tree.show` (własna osoba w adresie), `sources.index`, `sources.show` (własne źródło) z `?person=<cudza osoba>` przez `personPreviewUrl()` → 404.

#### 2. `tree_id` w treści jest ignorowane

**File**: `tests/Feature/OwnershipBoundariesTest.php`

**Intent**: Atakujący nie wstrzyknie rekordu do cudzego drzewa przez pole `tree_id`.

**Contract**: `persons.store` i `sources.store` (plik przez `UploadedFile::fake()`) z `tree_id` drzewa ofiary → nowy rekord ma `tree_id` drzewa atakującego, liczba osób/źródeł ofiary bez zmian; plik źródła ląduje pod `sources/{tree_id atakującego}/`.

#### 3. Cudzy passkey

**File**: `tests/Feature/Settings/SecurityTest.php`

**Intent**: Usunięcie cudzego klucza dostępu nie jest możliwe; 403 jest akceptowanym statusem (wyjątek: dane konta, kod vendor).

**Contract**: `DELETE route('passkey.destroy', <passkey innego użytkownika>)` z potwierdzonym hasłem (`withSession(['auth.password_confirmed_at' => time()])`) → 403, wiersz passkeya nadal istnieje. Brak fabryki — wiersz tworzony przez model pakietu (`laravel/passkeys`) z minimalnymi wymaganymi kolumnami (sprawdzić migrację pakietu). Test pomijany, gdy funkcja passkeys jest wyłączona — zgodnie z istniejącymi testami w tym pliku.

### Success Criteria:

#### Automated Verification:

- Nowe przypadki zielone: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php tests/Feature/Settings/SecurityTest.php`
- Pint bez zmian po formatowaniu: `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: Faza bez weryfikacji ręcznej; po zielonych testach przejdź do fazy 4.

---

## Faza 4: Cookbook §6.5 i stan §3

Tryb: `/10x-implement`.

### Overview

Zapisuje wzorzec testu własności w `test-plan.md`, żeby następny endpoint dostał test bez ponownego badania.

### Changes Required:

#### 1. Cookbook

**File**: `context/foundation/test-plan.md` §6.5

**Intent**: Zastąpić TBD dla części „własność” wzorcem z tej zmiany; część „reguły relacji” zostaje TBD (ryzyko #5).

**Contract**: podsekcja w formacie §6.3: Location (`tests/Feature/OwnershipBoundariesTest.php`), Reference test, Run locally, Wyrocznia (adres/`?person=` → 404 niezależnie od treści; id w treści → błąd walidacji bez zapisu, ten sam komunikat co nieistniejące; passkey → 403), Pułapki (autoryzacja w `authorize(): Response` Form Requestu, nie w kontrolerze, gdy trasa ma Form Request; `Storage::fake('local')` przed `ownershipSnapshot()`; „poprawna treść” = id z drzewa atakującego), Nowy endpoint (dopisz wpis do listy datasetu — strażnik wymusza).

#### 2. Wiersz fazy 3 w §3

**File**: `context/foundation/test-plan.md` §3

**Intent**: Odnotować otwarty folder zmiany fazy 3.

**Contract**: wiersz `| 3 | Granice własności i reguły relacji |`: Status `change opened`, Change folder `context/changes/testing-ownership-boundaries/`. Bez zmian w §1–§2, §4–§5 (strategia należy do Lekcji 1).

### Success Criteria:

#### Automated Verification:

- §6.5 nie zawiera już „TBD” dla części własności: `grep -n "6.5" -A12 context/foundation/test-plan.md`
- Cały Feature zielony: `vendor/bin/sail artisan test --compact tests/Feature`

#### Manual Verification:

- Autor czyta §6.5 i potwierdza, że wystarcza do napisania testu własności dla nowego endpointu bez czytania tego planu

---

## Testing Strategy

### Integration Tests:

- Dataset tras z cudzym id w adresie (2 warianty treści dla tras z treścią) → 404 + `ownershipSnapshot()` bez zmian — wykrywa: autoryzację po walidacji, usuniętą bramkę, zapis w dowolnej tabeli lub pliku przed odmową.
- Strażnik kompletności — wykrywa: nowy endpoint z parametrem bez testu własności.
- `?person=` na stronach hosta — wykrywa: wyciek podglądu cudzej osoby.
- `tree_id` w treści — wykrywa: przyjęcie `tree_id` z wejścia (np. `validated()` z regułą dla `tree_id`).
- Passkey — wykrywa: usunięcie cudzego klucza.

### Edge cases (przypadek brzegowy na ryzyko):

- pusta treść (autoryzacja przed walidacją), wiele parametrów adresu (`events.sources.destroy` — każdy z osobna cudzy), pliki źródła na dysku (skasowanie przy odmowie), wydarzenia z uczestnikami (kaskada przy odmowie).

### Manual Testing Steps:

1. Kontrola mutacji z fazy 2 (usunięcie `authorize()` / bramki → czerwono).
2. Dodanie rodzica, dziecka i małżonka własnej osobie z UI po fazie 1.

## Performance Considerations

`ownershipSnapshot()` wykonuje jedno zapytanie na tabelę (ok. 15 tabel) dwa razy na przypadek; przy ~35 przypadkach to kilkaset małych zapytań na pustej bazie testowej — pomijalne.

## Migration Notes

Brak zmian schematu i danych.

## References

- Research: `context/changes/testing-ownership-boundaries/research.md`
- Strategia: `context/foundation/test-plan.md` §2 (ryzyko #4), §3 (faza 3), §6.5
- Wzorzec autoryzacji przed walidacją: `app/Http/Requests/EventSourceStoreRequest.php:16-19`
- Wzorzec skrótu tabel: `tests/BackupTestCase.php:260-289`
- Decyzja z archiwum (autoryzuj przed walidacją): `context/archive/2026-09-24-birth-death-confirmed-by-scan/plan.md:94`
- Decyzja z archiwum (id w treści = błąd walidacji): `context/archive/2026-09-29-scans-become-sources/reviews/impl-review.md:70-78`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Pięć tras persons/{person}/… → 404 niezależnie od treści

#### Automated

- [x] 1.1 Przed poprawką dataset ma dokładnie 5 czerwonych przypadków (wariant „pusta treść”), 5 zielonych — 8d16922
- [x] 1.2 Po poprawce dataset 5 tras jest zielony (10 przypadków) — 8d16922
- [x] 1.3 Istniejące testy tras zagnieżdżonych przechodzą (happy path i błędy walidacji własnej osoby) — 8d16922
- [x] 1.4 Pint bez zmian po formatowaniu — 8d16922
- [x] 1.5 Larastan bez błędów — 8d16922

#### Manual

- [x] 1.6 Dodanie rodzica, dziecka i małżonka własnej osobie z UI działa, błąd walidacji pokazuje się przy polu — 8d16922

### Phase 2: Dataset wszystkich 19 tras i strażnik kompletności

#### Automated

- [x] 2.1 Dataset 19 tras zielony (ok. 29 przypadków), strażnik zielony — 59dbeb0
- [x] 2.2 Cały katalog Feature zielony po usunięciu duplikatów — 59dbeb0
- [x] 2.3 Pint bez zmian po formatowaniu — 59dbeb0

#### Manual

- [x] 2.4 Kontrola mutacji: usunięcie authorize() / bramki daje czerwony dataset — 59dbeb0
- [x] 2.5 Kontrola strażnika: dopisana trasa z parametrem wywala strażnika — 59dbeb0
- [x] 2.6 Lista usuniętych testów i powód zostawienia pozostałych w opisie commita — 59dbeb0

### Phase 3: Granice spoza adresu — ?person=, tree_id w treści, passkey

#### Automated

- [x] 3.1 Nowe przypadki zielone (OwnershipBoundariesTest, SecurityTest)
- [x] 3.2 Pint bez zmian po formatowaniu

### Phase 4: Cookbook §6.5 i stan §3

#### Automated

- [ ] 4.1 §6.5 nie zawiera już „TBD” dla części własności
- [ ] 4.2 Cały Feature zielony

#### Manual

- [ ] 4.3 Autor potwierdza, że §6.5 wystarcza do napisania testu własności nowego endpointu
