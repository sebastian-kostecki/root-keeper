# Jedno nazwisko rodowe — plan implementacji

## Overview

Osoba ma **jedno nazwisko — zawsze rodowe** (z urodzenia). Jedyne pole nazwiska w formularzu i w widoku „Dane osobowe” nazywa się „Nazwisko rodowe” i zapisuje `people.last_name`. Drugie pole (`maiden_name`) i dopisek „z d.” znikają. U osób z dwoma różnymi nazwiskami migracja przenosi rodowe do `last_name`, a dotychczasowe nazwisko dopisuje do notatki osoby, żeby nic nie zginęło.

Zmiana koryguje decyzję autora 4 z `context/changes/marriage-form-and-other-surnames/research.md` (2026-10-21) i zastępuje wcześniejszy model „nazwisko + nazwisko rodowe” z `context/archive/2026-10-05-maiden-name-and-age/`.

## Current State Analysis

- Dwie kolumny: `people.last_name` („Nazwisko”) i `people.maiden_name` („Nazwisko rodowe”).
- **Walidacja** zabrania wpisania samego rodowego: `app/Http/Requests/PersonStoreRequest.php:41, 60, 74`. Dziedziczą `PersonUpdateRequest`, `PersonSpouseStoreRequest`, `PersonChildStoreRequest`, `PersonParentStoreRequest` i `TreeProbandStoreRequest`.
- **Zapis** przez pełne zastąpienie: `app/Actions/Persons/CreatePerson.php:23, 50`, `app/Actions/Persons/UpdatePerson.php:26, 58`.
- **Wyświetlanie**: `Person::displayName()` dopisuje „z d. {rodowe}”, gdy oba nazwiska są niepuste i różne bez względu na wielkość liter. `cardNameParts()` bierze rodowe przed nazwiskiem (`app/Models/Person.php:148-180`; fillable `:34`, `@property :24`).
- **Wyszukiwanie** `/persons` wycina z zapytania „z d.”, a każde słowo dopasowuje do `first_name` / `last_name` / `maiden_name` przez ILIKE (`app/Http/Controllers/PersonController.php:43-52`). Pickery filtrują po `display_name` (`resources/js/components/persons/person-candidate-list.tsx:33-38`), który `ListPersonCandidates` buduje z `displayName()`.
- **Payload**: `PersonResource.php:26, 48`, `PersonFormResource.php:29, 47`.
- **Front**:
  - `PersonalFields`: pola „Nazwisko” i „Nazwisko rodowe” (`resources/js/components/persons/person-section-fields.tsx:70-93`);
  - widok „Dane osobowe” (`resources/js/components/persons/overview-tab.tsx:316-323`) i `PERSONAL_KEYS` (`:50`);
  - `resources/js/lib/person-payload.ts:40, 128, 164`;
  - typy `resources/js/types/person.ts:103, 174`.
- **Lista `/persons`** sortuje i grupuje po `last_name` (`resources/js/lib/persons-index.ts:75-111, 168-190`).
- **Dane deweloperskie** (2026-10-21):
  - 367 osób; 2 mają rodowe inne niż nazwisko, 2 nie mają nazwiska;
  - 165 kobiet ma tylko jedno nazwisko, z tego 97 zamężnych — ich `last_name` może być nazwiskiem po mężu.
- `people.note` to nullable `text` (`database/migrations/2026_10_10_194026_add_note_to_people_table.php:17`), widoczna i edytowalna w zakładce notatki panelu osoby.

## Desired End State

- Formularz osoby (panel, `?add=person`, sloty drzewa) ma jedno pole „Nazwisko rodowe” (`last_name`). Widok „Dane osobowe” pokazuje „Imię” i „Nazwisko rodowe”.
- Nazwa wyświetlana to „Imię Nazwisko” bez „z d.”: nagłówek panelu, lista `/persons`, pickery i oś przodków. Karta drzewa pokazuje `last_name`.
- Wyszukiwanie `/persons` dopasowuje każde słowo do imienia lub `last_name`; „z d.” w zapytaniu nie jest już wycinane.
- Aplikacja nie czyta ani nie zapisuje `maiden_name`. Klucz w żądaniu jest ignorowany, a kolumna zostaje w bazie z dotychczasowymi wartościami do usunięcia późniejszą zmianą.
- Po migracji „Anna Nowak z d. Kowalska” (kobieta) nazywa się „Anna Kowalska”, a jej notatka kończy się linią „Nazwisko po mężu: Nowak”. Mężczyzna z dwoma różnymi nazwiskami dostaje linię „Wcześniej zapisane nazwisko: …”.
- Osoby z jednym nazwiskiem (w tym zamężne kobiety) zostają bez zmian; autor poprawia je ręcznie.
- FR-020 i non-goal `prd.md:240` opisują jedno nazwisko rodowe.

### Key Discoveries:

- Po samej migracji (Faza 1) `last_name` równa się `maiden_name`, więc dzisiejszy `displayName()` nie pokazuje „z d.”, a formularz pokazuje dwa razy to samo nazwisko. Aplikacja działa poprawnie między fazami.
- Pickery nie wymagają zmian w kodzie — dziedziczą nazwę z `displayName()`.
- Lokatory E2E:
  - `getByLabel('Nazwisko', { exact: true })` w 6 specyfikacjach przestanie trafiać, bo etykieta to teraz „Nazwisko rodowe”;
  - `getByRole('textbox', { name: 'Nazwisko rodowe' })` w 4 specyfikacjach zacznie trafiać w jedyne pole (`last_name`).
- Domyślne nazwisko dziecka w panelu dodawania (`defaults.last_name`) to dziś nazwisko ojca. Po zmianie dalej jest poprawnym nazwiskiem rodowym dziecka.

## What We're NOT Doing

- Lista wielu nazwisk (po mężu / wariant / vel) ani osobne pole na nazwisko po ślubie — to trafia tylko do notatki.
- Zgadywanie lub oznaczanie kobiet, które mają jedno nazwisko (może być po mężu) — bez zmian, autor poprawia ręcznie.
- Usunięcie kolumny `people.maiden_name` — reguła „najpierw kopiuj, później usuwaj”; robi to późniejsza zmiana.
- Wyliczanie nazwiska po ślubie z nazwiska męża na drzewie lub w panelu.
- Zmiana `resources/js/hooks/use-initials.tsx` — obcinanie od „z d.” zostaje, jest nieszkodliwe.
- Nowe testy E2E — aktualizujemy tylko istniejące specyfikacje dotknięte zmianą etykiety i nazwy.

## Implementation Approach

`last_name` zmienia znaczenie na nazwisko rodowe; to zawężenie istniejącego modelu, bez nowych kolumn i tras. Faza 1 przepisuje dane migracją z własnym testem, a aplikacja działa po niej bez zmian. Faza 2 usuwa `maiden_name` z kodu: walidacja, akcje, model, resources, wyszukiwanie i front. Do tego przepisanie testów utrwalających „z d.”, aktualizacja lokatorów E2E i PRD.

## Phase 1: Migracja danych

### Overview

Wpisane nazwiska rodowe przechodzą do `last_name`, a dotychczasowe nazwisko trafia na koniec notatki. Kolumna `maiden_name` zostaje nietknięta.

### Changes Required:

#### 1. Migracja

**File**: `database/migrations/<timestamp>_move_maiden_names_to_last_name.php` (nowy, `vendor/bin/sail artisan make:migration`)

**Intent**: Nazwisko rodowe staje się jedynym nazwiskiem. Dotychczasowe nazwisko zostaje czytelne w notatce, żeby autor mógł je przejrzeć.

**Contract**: dotyczy każdej osoby z niepustym `maiden_name`, różnym od `last_name` bez względu na wielkość liter (`mb_strtolower`, jak w `Person::displayName()`).
- Jeśli `last_name` jest niepuste, do `note` trafia linia:
  - `Nazwisko po mężu: {last_name}`, gdy `gender` to `female` albo `null`;
  - `Wcześniej zapisane nazwisko: {last_name}` w pozostałych przypadkach.
  
  Pusta notatka (`null` lub `''`) dostaje samą linię, niepusta dostaje `"\n\n"` + linię na końcu.
- Następnie `last_name := maiden_name`.

Osoby z rodowym równym nazwisku, bez rodowego i z jednym nazwiskiem zostają bez zmian. `maiden_name`, `first_name` i `gender` nietknięte.

Implementacja: iteracja `DB::table('people')->...->chunkById(...)` (bez modelu Eloquent) w transakcji.

`down()` nic nie robi, a komentarz wyjaśnia dlaczego: dawne nazwisko żyje już w notatce, którą autor mógł edytować, więc nie da się go wiarygodnie odtworzyć. Przywrócenie robi się z kopii `backup:run --only-db`, którą `scripts/deploy.sh` wykonuje przed `migrate`.

#### 2. Test migracji

**File**: `tests/Feature/SingleSurnameMigrationTest.php` (nowy, wzór `tests/Feature/SourceMigrationTest.php` — migracja ładowana przez `require database_path(...)`)

**Intent**: Wymagany przez CLAUDE.md test migracji przepisującej dane: wiersze w starym kształcie → `up()` → nowy kształt, żadna wartość nie zginęła.

**Contract**: wiersze wstawione przed `up()`:
- (a) kobieta „Anna Nowak” z `maiden_name` „Kowalska” i notatką „Córka młynarza.” → `last_name = 'Kowalska'`, `note = "Córka młynarza.\n\nNazwisko po mężu: Nowak"`;
- (b) kobieta z dwoma nazwiskami i pustą notatką → `note = 'Nazwisko po mężu: Nowak'`;
- (c) osoba o nieznanej płci z dwoma nazwiskami → linia „Nazwisko po mężu: …”;
- (d) mężczyzna z dwoma różnymi nazwiskami → linia „Wcześniej zapisane nazwisko: …”;
- (e) `last_name = 'Nowak'`, `maiden_name = 'NOWAK'` → bez zmian;
- (f) samo `last_name` → bez zmian;
- (g) brak nazwisk → bez zmian;
- (h) osoba z drugiego drzewa jak (a).

We wszystkich wierszach `maiden_name` zachowuje wartość sprzed migracji. Drugie wywołanie `up()` nie dopisuje kolejnej linii, bo po pierwszym `last_name` równa się `maiden_name`.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi: `vendor/bin/sail artisan migrate`
- Test migracji przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/SingleSurnameMigrationTest.php`
- Testy osób i drzewa nadal przechodzą (dane między fazami są spójne): `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php`
- Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`

#### Manual Verification:

- Na bazie deweloperskiej dwie osoby z dawnym rodowym mają je teraz jako nazwisko, a ich notatka (zakładka notatki w panelu osoby) kończy się linią „Nazwisko po mężu: …” albo „Wcześniej zapisane nazwisko: …”

**Implementation Note**: Po weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed Fazą 2. Nie usuwaj kroku `backup:run --only-db` z `scripts/deploy.sh` — `down()` tej migracji nic nie odtwarza.

---

## Phase 2: Jedno pole w aplikacji

### Overview

Backend i front przestają używać `maiden_name`. Zostaje jedno pole „Nazwisko rodowe”, nazwa bez „z d.” i wyszukiwanie po imieniu i `last_name`. Testy, specyfikacje E2E i PRD przechodzą na nowe zachowanie.

### Changes Required:

#### 1. Backend: zapis, nazwa, wyszukiwanie

**File**: `app/Http/Requests/PersonStoreRequest.php`, `app/Actions/Persons/CreatePerson.php`, `app/Actions/Persons/UpdatePerson.php`, `app/Models/Person.php`, `app/Http/Resources/PersonResource.php`, `app/Http/Resources/PersonFormResource.php`, `app/Http/Controllers/PersonController.php`

**Intent**: Aplikacja zna jedno nazwisko — `last_name`, nazywane „nazwisko rodowe”. Klucz `maiden_name` w żądaniu jest ignorowany i nie trafia do odpowiedzi.

**Contract**:
- `PersonStoreRequest`: usunięte reguła, komunikat `maiden_name.prohibited` i atrybut `maiden_name`; atrybut `last_name` → „nazwisko rodowe”. Requesty dziedziczące bez zmian.
- `CreatePerson` / `UpdatePerson`: nie zapisują `maiden_name`; PHPDoc shape bez tego klucza.
- `Person`:
  - `maiden_name` usunięte z `#[Fillable]`; `@property` zostaje z dopiskiem, że to kolumna legacy do usunięcia;
  - `displayName()` zwraca „{imię ?? NN} {nazwisko ?? NN}” (dzisiejsze `$name` bez dopisku „z d.”);
  - `cardNameParts()['surname']` = `last_name ?? 'NN'`.
- `PersonResource` / `PersonFormResource`: bez `maiden_name` (także w PHPDoc).
- `PersonController::index`: każde słowo zapytania dopasowane do `first_name` lub `last_name`; wycinanie „z d.” (`preg_replace` z `$nameWithoutMaidenMarker`) usunięte.

#### 2. Frontend: jedno pole

**File**: `resources/js/components/persons/person-section-fields.tsx`, `resources/js/components/persons/overview-tab.tsx`, `resources/js/lib/person-payload.ts`, `resources/js/types/person.ts`

**Intent**: Formularz i widok „Dane osobowe” mają jedno pole nazwiska z etykietą „Nazwisko rodowe”.

**Contract**:
- `PersonalFields`: pole `last_name` z etykietą „Nazwisko rodowe” (`id`/`name` `last_name`, wartość domyślna jak dziś, w tym `defaults.last_name`); blok `maiden_name` usunięty.
- Widok „Dane osobowe”: `Field label="Nazwisko rodowe" value={person.last_name}`, bez drugiego pola.
- `PERSONAL_KEYS` bez `maiden_name`.
- `person-payload.ts` bez `maiden_name` w typie, w budowie payloadu i w scalaniu sekcji.
- Typy `Person` i `PersonFormData` bez `maiden_name`.

#### 3. Testy Pest

**File**: `tests/Feature/PersonsTest.php`, `tests/Feature/TreeTest.php`, `tests/Feature/AncestorsTest.php`, `tests/Feature/WriteTargetsTest.php`

**Intent**: Testy, które dziś utrwalają „z d.”, dwa nazwiska i zakaz samego rodowego, opisują zachowanie, które ta zmiana świadomie usuwa (decyzja autora, 2026-10-21). Przepisujemy je na nowe zachowanie. To nie jest dopasowywanie asercji do regresji.

**Contract**:
- `PersonsTest.php:1392-1470` — pięć testów nazwiska rodowego zastępują:
  - (a) store z kluczem `maiden_name` zapisuje osobę, nie ustawia kolumny `maiden_name`, a `display_name` = „Anna Kowalska”;
  - (b) store z samym `last_name` przechodzi walidację;
  - (c) update osoby z legacy `maiden_name` w bazie zostawia tę kolumnę nietkniętą i nie zwraca jej w payloadzie;
  - (d) wyszukiwanie znajduje po `last_name` oraz po „imię nazwisko”, a nie znajduje po wartości obecnej tylko w `maiden_name`.
- `TreeTest.php:495-507, 1020-1025` i `AncestorsTest.php:455-464`: `display_name` i `card_name` z `last_name`, bez „z d.”, a fabryki bez `maiden_name`.
- `WriteTargetsTest.php:283, 357`: listy kluczy bez `maiden_name`.

Usunięte testy wymienione w opisie commita.

#### 4. Vitest

**File**: `resources/js/lib/person-payload.test.ts`, `resources/js/lib/persons-index.test.ts`

**Intent**: Fixtury i testy payloadu bez drugiego nazwiska.

**Contract**: `person-payload.test.ts:13-55` — blok „mergeSectionIntoPayload: maiden name” zastępuje test, że payload osoby nie ma klucza `maiden_name`, a `last_name` pochodzi z sekcji danych osobowych. `persons-index.test.ts:38` — fixture bez `maiden_name`. `use-initials.test.ts` bez zmian.

#### 5. Specyfikacje E2E

**File**: `tests/e2e/support/api.ts` i specyfikacje niżej

**Intent**: Zmieniona etykieta to zmiana selektora, więc aktualizujemy lokatory. Tam, gdzie spec utrwala „z d.”, asercja przechodzi na zachowanie zatwierdzone w tej zmianie.

**Contract**:
- `getByLabel('Nazwisko', { exact: true })` → `getByLabel('Nazwisko rodowe', { exact: true })` w:
  - `tree-slot-parent.spec.ts:55`
  - `persons-add-person-panel.spec.ts:28, 40, 82`
  - `tree-add-person-guard.spec.ts:86`
  - `tree-slot-child.spec.ts:80`
  - `tree-slot-spouse.spec.ts:44`
- `getByRole('textbox', { name: 'Nazwisko rodowe' })` trafia teraz w jedyne pole nazwiska w:
  - `fact-places-from-gazetteer.spec.ts:83`
  - `residence-history.spec.ts:83`
  - `person-panel-page-navigation-guard.spec.ts:25-37`
  - `person-panel-unsaved-guard.spec.ts:40`

  Sprawdź, że każdy z tych speców nadal chroni swoje ryzyko (pole służy w nich do zabrudzenia formularza). W `person-panel-unsaved-guard.spec.ts:58` oczekiwany nagłówek `${centreName} z d. Kowalska` zmienia się na imię centrum + „Kowalska”.
- `tree-card-name.spec.ts:96-150`: przypadek „karta kobiety pokazuje nazwisko rodowe” tworzy osobę z `last_name: 'Nowak'` i sprawdza „Anna Nowak” na karcie. Asercja nieobecności „Anna Kowalska z d. Nowak” odpada razem z `maiden_name`. Komentarz nagłówka speca (`:2`) dostosowany.
- `support/api.ts:19, 112`: bez `maiden_name`.

#### 6. PRD

**File**: `context/foundation/prd.md`

**Intent**: Wymagania opisują jedno nazwisko rodowe.

**Contract**:
- FR-020 (`prd.md:158-160`) → `[modified] FR-020: Autor can zapisać jedno nazwisko osoby — rodowe (z urodzenia); osoba wyświetla się imieniem i tym nazwiskiem i jest po nim wyszukiwana. Priority: must-have. (Było: nazwisko i nazwisko rodowe, wyświetlanie „z d. …”, wyszukiwanie po obu.)`
- Non-goal `prd.md:240` → „**Jedno nazwisko na osobę** — zawsze rodowe; nazwiska po ślubie w notatce.”

### Success Criteria:

#### Automated Verification:

- Testy osób, drzewa, przodków, zapisu i granic własności przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php tests/Feature/AncestorsTest.php tests/Feature/WriteTargetsTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/SingleSurnameMigrationTest.php`
- `grep -rn "maiden_name" app resources/js tests --include='*.php' --include='*.ts' --include='*.tsx'` trafia tylko w `@property` modelu, w test migracji i w migracjach
- Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`
- Frontend: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run build`
- Dotknięte specyfikacje E2E przechodzą: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/persons-add-person-panel.spec.ts tests/e2e/tree-add-person-guard.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts tests/e2e/fact-places-from-gazetteer.spec.ts tests/e2e/residence-history.spec.ts tests/e2e/person-panel-page-navigation-guard.spec.ts tests/e2e/person-panel-unsaved-guard.spec.ts tests/e2e/tree-card-name.spec.ts`

#### Manual Verification:

- Formularz osoby (panel, `?add=person`, sloty drzewa) ma jedno pole „Nazwisko rodowe”; widok „Dane osobowe” pokazuje imię i nazwisko rodowe
- Nazwa w nagłówku panelu, na liście `/persons`, w pickerach i na karcie drzewa to „Imię Nazwisko” bez „z d.”
- Wyszukiwanie na `/persons` i w pickerze znajduje osobę po nazwisku rodowym
- Dodanie dziecka na drzewie podpowiada nazwisko ojca w polu „Nazwisko rodowe”
- Zrzut ekranu formularza i widoku „Dane osobowe” w `context/changes/single-birth-surname/screenshots/`

**Implementation Note**: Po weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka.

---

## Testing Strategy

### Unit Tests:

- Vitest: payload osoby bez `maiden_name`, fixtury bez drugiego nazwiska (Faza 2 §4).
- `view-tokens.test.ts` skanuje widoki `/persons` — zmiana pól nie może wprowadzić klas palety.

### Integration Tests:

- Pest `SingleSurnameMigrationTest`: osiem kształtów wiersza z Fazy 1 §2, idempotencja `up()`, nietknięte `maiden_name`.
- Pest `PersonsTest` / `TreeTest` / `AncestorsTest` / `WriteTargetsTest`: nazwa bez „z d.”, karta z `last_name`, ignorowany klucz `maiden_name`, wyszukiwanie po imieniu i `last_name`.
- E2E: tylko aktualizacja 10 istniejących specyfikacji (Faza 2 §5).

### Manual Testing Steps:

1. Po Fazie 1 otwórz obie osoby z dawnym nazwiskiem rodowym — nazwisko to dawne rodowe, a ostatnia linia notatki to „Nazwisko po mężu: …”.
2. Po Fazie 2 otwórz dowolną osobę → Przegląd → „Dane osobowe” → Edytuj: jedno pole „Nazwisko rodowe”.
3. Dodaj dziecko na drzewie: pole „Nazwisko rodowe” ma domyślnie nazwisko ojca. Zapisz i znajdź dziecko na `/persons` po tym nazwisku.
4. W pickerze (np. „Dodaj osobę” → wybierz istniejącą) wpisz nazwisko rodowe osoby po migracji — osoba się znajduje.

## Migration Notes

Faza 1 przepisuje istniejące dane (`people.last_name`, `people.note`), więc ma własny test migracji. Zgodnie z regułą „najpierw kopiuj, później usuwaj” kolumna `people.maiden_name` zostaje z dotychczasowymi wartościami; usunie ją osobna, późniejsza zmiana, gdy autor przejrzy notatki. `down()` nic nie odtwarza (komentarz w migracji); wycofanie robi się z kopii bazy, którą `scripts/deploy.sh` wykonuje przez `backup:run --only-db` przed `migrate`. Dopisana linia jest zwykłym tekstem notatki — widoczna i edytowalna od razu.

## References

- Research (mapa kodu nazwisk, sekcje D–F; decyzja 4 skorygowana): `context/changes/marriage-form-and-other-surnames/research.md`
- Model zastępowany: `context/archive/2026-10-05-maiden-name-and-age/plan-brief.md`
- Karta drzewa (rodowe ?: nazwisko): `context/archive/2026-10-12-tree-card-name-one-line/plan-brief.md`
- Wzorzec testu migracji danych: `tests/Feature/SourceMigrationTest.php`
- Wyszukiwanie: `app/Http/Controllers/PersonController.php:43-52`
- Nazwa i karta: `app/Models/Person.php:148-180`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Migracja danych

#### Automated

- [x] 1.1 Migracja przechodzi: `vendor/bin/sail artisan migrate` — 2107052
- [x] 1.2 Test migracji przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/SingleSurnameMigrationTest.php` — 2107052
- [x] 1.3 Testy osób i drzewa nadal przechodzą (dane między fazami są spójne): `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php` — 2107052
- [x] 1.4 Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check` — 2107052

#### Manual

- [x] 1.5 Na bazie deweloperskiej dwie osoby z dawnym rodowym mają je teraz jako nazwisko, a ich notatka (zakładka notatki w panelu osoby) kończy się linią „Nazwisko po mężu: …” albo „Wcześniej zapisane nazwisko: …” — 2107052

### Phase 2: Jedno pole w aplikacji

#### Automated

- [x] 2.1 Testy osób, drzewa, przodków, zapisu i granic własności przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/TreeTest.php tests/Feature/AncestorsTest.php tests/Feature/WriteTargetsTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/SingleSurnameMigrationTest.php`
- [x] 2.2 `grep -rn "maiden_name" app resources/js tests --include='*.php' --include='*.ts' --include='*.tsx'` trafia tylko w `@property` modelu, w test migracji i w migracjach
- [x] 2.3 Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`
- [x] 2.4 Frontend: `vendor/bin/sail npm run test:js`, `vendor/bin/sail npm run types:check`, `vendor/bin/sail npm run check`, `vendor/bin/sail npm run build`
- [x] 2.5 Dotknięte specyfikacje E2E przechodzą: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/persons-add-person-panel.spec.ts tests/e2e/tree-add-person-guard.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts tests/e2e/fact-places-from-gazetteer.spec.ts tests/e2e/residence-history.spec.ts tests/e2e/person-panel-page-navigation-guard.spec.ts tests/e2e/person-panel-unsaved-guard.spec.ts tests/e2e/tree-card-name.spec.ts`

#### Manual

- [x] 2.6 Formularz osoby (panel, `?add=person`, sloty drzewa) ma jedno pole „Nazwisko rodowe”; widok „Dane osobowe” pokazuje imię i nazwisko rodowe
- [x] 2.7 Nazwa w nagłówku panelu, na liście `/persons`, w pickerach i na karcie drzewa to „Imię Nazwisko” bez „z d.”
- [x] 2.8 Wyszukiwanie na `/persons` i w pickerze znajduje osobę po nazwisku rodowym
- [x] 2.9 Dodanie dziecka na drzewie podpowiada nazwisko ojca w polu „Nazwisko rodowe”
- [x] 2.10 Zrzut ekranu formularza i widoku „Dane osobowe” w `context/changes/single-birth-surname/screenshots/`
