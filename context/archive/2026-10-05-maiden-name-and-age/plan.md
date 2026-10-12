# Nazwisko rodowe i wiek osoby (S-13) — plan implementacji

## Overview

Realizuje S-13 (`context/foundation/roadmap.md`, FR-020 i FR-024). Autor zapisuje nazwisko rodowe; osoba wyświetla się „Anna Kowalska z d. Nowak”, gdy znane są oba nazwiska, i jest wyszukiwana po obu. Autor widzi też wiek osoby obok żywotu: ile przeżyła (gdy jest zdarzenie zgonu z rokiem) albo ile ma (bez zdarzenia zgonu, tylko przy wyniku poniżej 110 lat).

## Current State Analysis

Pełna mapa kodu jest w `context/changes/maiden-name-and-age/research.md`. Najważniejsze:

- Nazwiska rodowego nie ma nigdzie. Tabela `people`: `first_name`, `last_name` (oba nullable, wzajemnie `required_without`), `gender`, `residence`, `religion`.
- Nazwa wyświetlana powstaje w jednym miejscu: `Person::displayName()` (`app/Models/Person.php:102-105`). Emituje ją 8 plików PHP, a frontend renderuje tylko gotowe `display_name`.
- Wyszukiwanie: serwerowe w `PersonController.php:40-50` (każde słowo pasuje do `first_name` lub `last_name`), klienckie w pickerach drzewa po `display_name` (`person-candidate-list.tsx:28-33`).
- Zapis osoby to pełne zastąpienie (`UpdatePerson.php:51-57`): klucz nieobecny w żądaniu → `null`. Nowe pole musi przejść całą ścieżkę: request → akcje → `PersonFormResource` → `PersonFormData` → `person-payload.ts` → `PERSONAL_KEYS`.
- Daty urodzenia/zgonu to wiersze `events` z `date_year/month/day` nullable i `date_is_approximate`; `Event::$date` to `App\Support\PartialDate`. Zgon bez daty to zdarzenie z samymi `null`.
- Liczenia wieku nie ma. Żywot jest składany w PHP dla karty drzewa (`SheetPersonResource.php:29-47`) i w TS dla nagłówka podglądu (`person-modal-header.tsx:40` → `lib/lifespan.ts`).
- `config/app.php` ma `timezone = UTC`; testy nigdzie nie zamrażają czasu.
- Polska odmiana liczebników istnieje po stronie TS: `resources/js/lib/plural.ts` (`pluralPhrase`).

## Desired End State

- Formularz osoby (dodawanie, dialogi z drzewa, edycja sekcji „Dane osobowe”) ma pole „Nazwisko rodowe” pod „Nazwiskiem”. Zapis samego nazwiska rodowego (bez „Nazwiska”) jest odrzucany z polskim komunikatem pod polem.
- Gdy oba nazwiska są znane i różne (bez względu na wielkość liter), `display_name` brzmi „Imię Nazwisko z d. Rodowe” w całej aplikacji: drzewo, listy, pickery, dialogi, nagłówek. Przy równych nazwiskach dopisek się nie pojawia.
- Lista osób (`/persons?q=`) i pickery na drzewie znajdują osobę po nazwisku rodowym. Inicjały awatara to imię + nazwisko („AK”), a nie nazwisko rodowe.
- Nagłówek podglądu osoby i karta drzewa pokazują wiek w nawiasie po żywocie: „1850 – 1922 (72 lata)”, „ur. 1980 (45 lat)”, „1980 – (45 lat)”, „02.01.1890 – 20.03.1890 (2 mies.)”, „1890 – 1890 (poniżej roku)”.
- Sprawdzenie: testy Pest (Feature + Unit), Vitest i trzy istniejące specy E2E przechodzą; ręcznie widać „z d.” i wiek w aplikacji.

### Key Discoveries:

- `PersonStoreRequest` (`app/Http/Requests/PersonStoreRequest.php:30-31,50-51`) jest bazą dla `PersonUpdateRequest`, `PersonSpouseStoreRequest`, `PersonChildStoreRequest`, `PersonParentStoreRequest` — jedna reguła obejmuje pięć requestów; akcje dodawania krewnych delegują do `CreatePerson::handle`.
- `getInitials()` (`resources/js/hooks/use-initials.tsx:9-27`) bierze pierwszy i ostatni token — dla „Anna Kowalska z d. Nowak” da „AN”. Używa go `PersonAvatar` i `family-tab.tsx:92`.
- `getByLabel('Nazwisko')` w `tests/e2e/tree-slot-{spouse,child,parent}.spec.ts:44/77/54` dopasowuje podciąg i po dodaniu pola złamie się w trybie strict.
- `SheetPersonResource` i `PersonResource` mają załadowane `events` (wymóg `birth()`/`death()`), więc wiek liczony z modelu nie dokłada zapytań.
- `tests/Feature/WriteTargetsTest.php:274` odtwarza payload formularza z jawnej listy kluczy `personForm` — trzeba dopisać nowy klucz, żeby test nadal odzwierciedlał prawdziwy formularz.
- `TreeTest.php:443-473` sprawdza `lifespan` dosłownie — wiek idzie w osobnym polu `age`, więc te asercje zostają bez zmian.
- Katalog `.ai/rules` nie istnieje; obowiązują konwencje z `CLAUDE.md`.

## What We're NOT Doing

- Wiele nazwisk po kolejnych mężach (PRD Non-Goals) — tylko jedno nazwisko rodowe.
- Wiek w zakładce Rodzina, liście/tabeli osób i pickerze — tylko nagłówek osoby i karta drzewa.
- Oznaczanie niepewności wieku („ok.”, zakres „71–72”) — wiek to zawsze sama różnica z części znanych w obu datach.
- Walidacja „zgon nie przed urodzeniem” — ujemny wynik po prostu ukrywa wiek.
- Zmiana strefy czasowej aplikacji (`config/app.php`) — Europe/Warsaw tylko dla „dziś” w liczeniu wieku.
- Zmiana placeholdera „Szukaj po imieniu lub nazwisku”, sortowania (nadal po `last_name`) i indeksu `people`.
- Nowa specyfikacja E2E — zachowanie pokrywają testy Pest/Vitest; istniejące specy dostają tylko poprawione lokatory.
- Panel osoby (S-06) — zmiana dotyczy danych i formatu, nie powłoki.

## Implementation Approach

Dwie pionowe fazy, każda od migracji/serwera po UI i testy, każda sprawdzalna w aplikacji.

1. **Nazwisko rodowe** wchodzi w jedno źródło nazwy (`displayName()`), więc „z d.” trafia wszędzie bez zmian w komponentach; w wyszukiwaniu serwerowym dochodzi jedna kolumna, a pickery na drzewie zadziałają same.
2. **Wiek** liczy serwer w jednej klasie `App\Support\Age` (obok `PartialDate`), bo karta drzewa dostaje żywot z PHP. Zasób zwraca strukturę `{unit, value}`, a tekst składa TS przez istniejący `pluralPhrase`, więc odmiana jest w jednym miejscu.

**Reguła wieku** (ustalona w planowaniu):

- Koniec okresu: data zgonu, gdy istnieje zdarzenie zgonu; inaczej „dziś” jako pełna data w strefie Europe/Warsaw.
- Brak wieku, gdy: brak roku urodzenia; zdarzenie zgonu bez roku; wynik ujemny; osoba bez zdarzenia zgonu z wynikiem ≥ 110 lat.
- Precyzja = wspólna precyzja obu dat. Sam rok w którejkolwiek → lata = rok końca − rok urodzenia. Rok+miesiąc w obu (dzień brakuje w którejś) → lata pomniejszone o 1, gdy miesiąc końca < miesiąca urodzenia. Pełne obie → pełna różnica kalendarzowa. Flaga „około” nie zmienia wyniku.
- Lata ≥ 1 → `years`. Lata = 0: przy pełnych obu datach → `months` (gdy ≥ 1 miesiąc), inaczej `days` (0 dni renderuje się jako „poniżej dnia”); przy niepełnych → `under_year`.
- Przykłady: ur. 1850, zm. 15.03.1922 → 72 lata. Ur. 05.1850, zm. 03.1922 → 71 lat. Ur. 1916 bez zgonu, dziś 2026 → 110 → ukryty. Ur. 1917 → 109 lat. Ur. 02.01.1890, zm. 20.03.1890 → 2 mies. Ur. 02.03.1890, zm. 19.03.1890 → 17 dni. Ur. 1890, zm. 1890 → poniżej roku.

## Critical Implementation Details

- **Pełne zastąpienie przy zapisie:** każde miejsce budujące payload PUT/POST osoby musi nieść `maiden_name`; jeśli pominie się choć jedno (`personFormToPayload`, gałąź `personal` w `mergeSectionIntoPayload`), zapis innej sekcji (np. Urodzenie) po cichu wyczyści nazwisko rodowe. Test w Fazie 1 sprawdza właśnie to.
- **„Dziś” a testy:** to pierwsza logika zależna od bieżącej daty. Testy Feature zamrażają czas (`$this->travelTo(...)`), a testy Unit przekazują „dziś” jawnie do czystej funkcji. Strefę Europe/Warsaw potwierdza test na granicy doby: 23:30 UTC w przeddzień urodzin to już dzień urodzin w Polsce.

## Faza 1: Nazwisko rodowe

### Overview

Kolumna `maiden_name` na całej ścieżce zapisu i odczytu, „z d.” w `displayName()`, wyszukiwanie po trzech kolumnach, pole w formularzu i w widoku Przeglądu, poprawione inicjały i lokatory E2E.

### Changes Required:

#### 1. Migracja i model

**File**: `database/migrations/<timestamp>_add_maiden_name_to_people_table.php` (przez `vendor/bin/sail artisan make:migration --no-interaction`), `app/Models/Person.php`

**Intent**: Dodać nullable kolumnę i regułę wyświetlania „z d.”. Migracja nie przepisuje istniejących wierszy, więc nie wymaga `<Name>MigrationTest.php`.

**Contract**: `people.maiden_name` — `string` nullable, `after('last_name')`, `down()` usuwa kolumnę (wzór: `2026_09_28_064501_add_gender_to_people_table.php`). `Person`: `maiden_name` w `#[Fillable]` i w docblocku `@property string|null $maiden_name`. `displayName()` dopisuje ` z d. {maiden_name}` tylko gdy `last_name` i `maiden_name` są niepuste i różne po `mb_strtolower`; część „Imię Nazwisko” (z `NN`) bez zmian.

#### 2. Walidacja

**File**: `app/Http/Requests/PersonStoreRequest.php`, `lang/pl/validation.php`

**Intent**: Przyjąć nazwisko rodowe i odrzucić je bez nazwiska głównego (rozstrzygnięcie PRD FR-020: jedno znane nazwisko idzie w pole główne).

**Contract**: reguła `maiden_name`: nullable, string, max:255, zabronione, gdy `last_name` jest puste. Błąd na kluczu `maiden_name` z komunikatem „Gdy znasz tylko jedno nazwisko, wpisz je w pole „Nazwisko”.” (przez `messages()`). Etykieta `maiden_name` → „nazwisko rodowe” w `attributes()` i w `lang/pl/validation.php`. Dziedziczą to cztery requesty pochodne.

#### 3. Akcje zapisu

**File**: `app/Actions/Persons/CreatePerson.php`, `app/Actions/Persons/UpdatePerson.php`

**Intent**: Zapisywać nowe pole tak jak pozostałe pola osoby.

**Contract**: `'maiden_name' => $data['maiden_name'] ?? null` w `create()` i `update()`, plus klucz `maiden_name?: string|null` w docblockach kształtu `$data`.

#### 4. Zasoby

**File**: `app/Http/Resources/PersonResource.php`, `app/Http/Resources/PersonFormResource.php`

**Intent**: Wysłać surową wartość do widoku Przeglądu i do prefill formularza.

**Contract**: klucz `maiden_name: string|null` w obu zasobach (obok `last_name`) i w ich docblockach `@return array{...}`. Pozostałe emitery dostają „z d.” przez `displayName()` bez zmian.

#### 5. Wyszukiwanie na liście osób

**File**: `app/Http/Controllers/PersonController.php`

**Intent**: Każde słowo zapytania może pasować także do nazwiska rodowego.

**Contract**: w grupie dla słowa trzecie `->orWhereLike('maiden_name', $pattern)`; escapowanie i sortowanie bez zmian.

#### 6. Typy i payload na froncie

**File**: `resources/js/types/person.ts`, `resources/js/lib/person-payload.ts`

**Intent**: Pole przechodzi przez pełne zastąpienie przy każdym zapisie sekcji.

**Contract**: `maiden_name: string | null` w `Person` i `PersonFormData`; w `PersonPayload`, w `personFormToPayload` i w gałęzi `personal` funkcji `mergeSectionIntoPayload`.

#### 7. Formularz i widok Przeglądu

**File**: `resources/js/components/persons/person-section-fields.tsx`, `resources/js/components/persons/overview-tab.tsx`

**Intent**: Pole „Nazwisko rodowe” w `PersonalFields` (wspólne dla dodawania, dialogów z drzewa i edycji), widoczne dla każdej płci; odczyt w karcie „Dane osobowe”.

**Contract**: `Label htmlFor="maiden_name"` „Nazwisko rodowe”, `Input name="maiden_name" maxLength={255}`, `defaultValue` z `person?.maiden_name`, `InputError` dla `errors.maiden_name`; położenie bezpośrednio po „Nazwisku”. `PERSONAL_KEYS` zawiera `'maiden_name'`. W widoku odczytu `Field label="Nazwisko rodowe"` obok „Nazwiska”.

#### 8. Inicjały

**File**: `resources/js/hooks/use-initials.tsx`, `resources/js/hooks/use-initials.test.ts`

**Intent**: Awatar pokazuje imię + nazwisko, nie nazwisko rodowe.

**Contract**: `getInitials` przed podziałem odcina końcowy dopisek ` z d. …`; `getInitials('Anna Kowalska z d. Nowak') === 'AK'`. Nowy przypadek w istniejącym teście.

#### 9. Lokatory E2E

**File**: `tests/e2e/tree-slot-spouse.spec.ts:44`, `tests/e2e/tree-slot-child.spec.ts:77`, `tests/e2e/tree-slot-parent.spec.ts:54`

**Intent**: Zmiana selektora po dodaniu pola „Nazwisko rodowe” (dozwolona wg CLAUDE.md — asercje bez zmian).

**Contract**: `getByLabel('Nazwisko', { exact: true })`.

#### 10. Testy Pest

**File**: `tests/Feature/PersonsTest.php`, `tests/Feature/WriteTargetsTest.php`

**Intent**: Pokryć zapis, odrzucenie, wyświetlanie, wyszukiwanie i przetrwanie pola przy zapisie innej sekcji.

**Contract**: nowe testy w `PersonsTest.php` (wzorce: wyszukiwanie `:44-67`, zapis `:476`, aktualizacja `:755`, polskie błędy `:714`):
- zapis z nazwiskiem rodowym → `display_name` w propsach = „Anna Kowalska z d. Nowak”;
- nazwisko rodowe bez nazwiska → błąd na `maiden_name` z polskim komunikatem, nic nie zapisane;
- nazwisko rodowe równe nazwisku (inna wielkość liter) → `display_name` bez „z d.”;
- `/persons?q=Nowak` znajduje osobę po nazwisku rodowym, `q=Anna Nowak` też (słowa w różnych kolumnach);
- aktualizacja z payloadem zawierającym `maiden_name` (np. zmiana daty urodzenia) zachowuje wartość.

W `WriteTargetsTest.php:274` dopisać `'maiden_name'` do listy kluczy payloadu.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1` + ponowne `migrate`
- Testy Pest osób przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/WriteTargetsTest.php tests/Feature/TreeTest.php`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Typy, lint i format przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check`
- Larastan i Pint przechodzą: `vendor/bin/sail bin phpstan analyse` i `vendor/bin/sail bin pint --dirty --format agent`
- Trzy specy E2E slotów drzewa przechodzą: `npx playwright test tests/e2e/tree-slot-spouse.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-parent.spec.ts`

#### Manual Verification:

- W edycji „Dane osobowe” wpisanie nazwiska rodowego daje na drzewie i w nagłówku „Imię Nazwisko z d. Rodowe”, a awatar ma inicjały imienia i nazwiska
- Wpisanie samego nazwiska rodowego pokazuje polski błąd pod polem „Nazwisko rodowe”
- Lista osób i picker „wybierz osobę” na drzewie znajdują osobę po nazwisku rodowym
- Zmiana daty urodzenia (inna sekcja) nie czyści nazwiska rodowego

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed Fazą 2.

---

## Faza 2: Wiek osoby

### Overview

Klasa `App\Support\Age` liczy wiek z dat częściowych według reguły z „Implementation Approach”, `Person::age()` podaje „dziś” w Europe/Warsaw, zasoby nagłówka i karty drzewa niosą `age`, a TS składa tekst i dopisuje go w nawiasie po żywocie.

### Changes Required:

#### 1. Liczenie wieku

**File**: `app/Support/Age.php` (przez `vendor/bin/sail artisan make:class`)

**Intent**: Czysta, testowalna bez frameworka logika wieku — jedno źródło dla nagłówka i karty.

**Contract**: `final readonly class Age` z `public string $unit` (`'years'|'months'|'days'|'under_year'`) i `public ?int $value` (`null` tylko dla `under_year`). Fabryka `Age::from(?PartialDate $birth, ?PartialDate $death, bool $isDeceased, CarbonImmutable $today): ?Age` realizuje regułę (koniec okresu, warunki braku wieku, wspólna precyzja, próg 110 tylko przy `!$isDeceased`, jednostki dla lat = 0). `toArray(): array{unit: string, value: int|null}`. Pełna różnica przy pełnych datach przez `CarbonImmutable::diff()` (rok/miesiąc/dzień interwału).

#### 2. Wiek osoby w modelu

**File**: `app/Models/Person.php`

**Intent**: Podać „dziś” w polskiej strefie bez zmiany strefy aplikacji.

**Contract**: `public function age(): ?Age` — woła `Age::from($this->birth()?->date, $this->death()?->date, $this->death() !== null, Date::now('Europe/Warsaw')->toImmutable()->startOfDay())`. Wymaga załadowanego `events` (jak `birth()`/`death()`).

#### 3. Zasoby

**File**: `app/Http/Resources/PersonResource.php`, `app/Http/Resources/SheetPersonResource.php`

**Intent**: Nagłówek podglądu i karta drzewa dostają gotową strukturę wieku; `lifespan` karty bez zmian.

**Contract**: klucz `age: array{unit: 'years'|'months'|'days'|'under_year', value: int|null}|null` = `$this->age()?->toArray()` w obu zasobach i ich docblockach.

**Uwaga (impl-review F4):** `PersonResource` obsługuje też listę osób (`persons/index`), więc `age` trafia także do jej wierszy, choć lista go nie wyświetla. To przyjęte świadomie: `events` są tam już eager-loaded, więc nie ma dodatkowych zapytań, a typ `Person` pozostaje wspólny dla listy i podglądu.

#### 4. Formatowanie w TS

**File**: `resources/js/lib/age.ts`, `resources/js/lib/age.test.ts`, `resources/js/types/person.ts`, `resources/js/types/tree.ts`

**Intent**: Jedno miejsce składające tekst wieku z polską odmianą.

**Contract**: typ `PersonAge = { unit: 'years' | 'months' | 'days' | 'under_year'; value: number | null }`; pole `age: PersonAge | null` w `Person` i `SheetPerson`. `formatAge(age: PersonAge): string`: `years` → `pluralPhrase(n, 'rok', 'lata', 'lat')` („1 rok”, „72 lata”, „45 lat”, „22 lata”, „112 lat”), `months` → „N mies.”, `days` → `pluralPhrase(n, 'dzień', 'dni', 'dni')`, a 0 → „poniżej dnia”, `under_year` → „poniżej roku”. Test Vitest (import z `'vite-plus/test'`, wzorzec `partial-date.test.ts`) dla każdej jednostki i form odmiany.

#### 5. Nagłówek osoby i karta drzewa

**File**: `resources/js/components/persons/person-modal-header.tsx`, `resources/js/components/tree/sheet-person-card.tsx`

**Intent**: Dopisać wiek w nawiasie po żywocie.

**Contract**: nagłówek: `DialogDescription` = `` `${lifespan} (${formatAge(person.age)})` ``, gdy `age` jest, inaczej sam `lifespan` (gdy `age` istnieje, żywot zawsze jest, bo wymaga roku urodzenia). Karta: ta sama reguła w spanie żywotu (`sheet-person-card.tsx:34-38`).

**Odchylenie w implementacji (impl-review F3):** na karcie drzewa wiek jest w osobnej linii pod żywotem (`whitespace-nowrap`), a nie w tym samym spanie. Na wąskiej karcie „1850 – 1922 (72 lata)” w jednej linii się łamało. Nagłówek podglądu ma wiek w tej samej linii, zgodnie z planem.

#### 6. Testy Pest

**File**: `tests/Unit/AgeTest.php`, `tests/Feature/TreeTest.php`, `tests/Feature/PersonsTest.php`

**Intent**: Pokryć regułę w całości na poziomie czystej funkcji i jedno przejście przez każdy zasób z zamrożonym czasem.

**Contract**:
- `tests/Unit/AgeTest.php` (bez frameworka, wzorzec `tests/Unit/PartialDateTest.php`, „dziś” przekazane jawnie): każdy przykład z „Implementation Approach”; zgon bez roku → `null`; brak roku urodzenia → `null`; wynik ujemny → `null`; żyjący 109 → pokazany, 110 → `null`; zmarły w wieku 112 → pokazany; flaga „około” nie zmienia wyniku; urodzenie i zgon tego samego dnia → `days`, 0.
- `TreeTest.php`: karta drzewa niesie `age` dla zmarłego i żyjącego (`travelTo`), `lifespan` bez zmian.
- `PersonsTest.php`: podgląd osoby niesie `age`; test granicy doby — `travelTo('2026-03-14 23:30:00 UTC')`, urodzony 15.03.1980 → 46 lat (w Europe/Warsaw to już 15.03).

### Success Criteria:

#### Automated Verification:

- Testy Unit i Feature przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/AgeTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Typy, lint i format przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check`
- Larastan i Pint przechodzą: `vendor/bin/sail bin phpstan analyse` i `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Nagłówek podglądu zmarłej osoby pokazuje „A – B (N lat)”, żyjącej — „ur. A (N lat)”
- Karta drzewa pokazuje wiek w nawiasie po żywocie i nie rozjeżdża się na wąskiej karcie
- Osoba ur. 1916 (sam rok) bez zgonu nie ma wieku, ur. 1917 ma 109 lat; dziecko zmarłe przed rokiem ma miesiące/dni albo „poniżej roku”

**Implementation Note**: Po zakończeniu fazy i zielonej weryfikacji automatycznej poproś o ręczne potwierdzenie, a potem o uruchomienie pełnego zestawu `vendor/bin/sail artisan test --compact`.

---

## Testing Strategy

### Unit Tests:

- `tests/Unit/AgeTest.php` — cała reguła wieku na czystej funkcji z jawnym „dziś”: precyzje, progi, jednostki, przypadki bez wieku.
- Vitest: `age.test.ts` (tekst i odmiana), `use-initials.test.ts` (dopisek „z d.” pomijany).

### Integration Tests:

- `PersonsTest.php` — zapis, odrzucenie, wyświetlanie, wyszukiwanie nazwiska rodowego; wiek w podglądzie i strefa Europe/Warsaw.
- `TreeTest.php` — wiek na karcie drzewa; istniejące asercje `lifespan` bez zmian.
- `WriteTargetsTest.php` — payload formularza z nowym kluczem.
- E2E: trzy istniejące specy slotów drzewa z poprawionym lokatorem (bez nowego specu).

### Manual Testing Steps:

1. Dodaj kobietę z nazwiskiem i nazwiskiem rodowym z pustego slotu drzewa; sprawdź kartę, nagłówek, inicjały.
2. Wyszukaj ją po nazwisku rodowym na liście osób i w pickerze „wybierz istniejącą osobę”.
3. Zmień jej datę urodzenia; sprawdź, że nazwisko rodowe zostało.
4. Sprawdź wiek dla: zmarłej z pełnymi datami, przodka z samym rokiem, żyjącej, osoby ur. 1900 bez zgonu (brak wieku), niemowlęcia.

## Performance Considerations

Brak istotnych: wiek liczy się z już załadowanej relacji `events`, wyszukiwanie dokłada jedno `LIKE` na tej samej tabeli.

## Migration Notes

Kolumna nullable, bez przepisywania danych i z odwracalnym `down()`; produkcja i tak robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`).

## References

- Related research: `context/changes/maiden-name-and-age/research.md`
- Roadmap: `context/foundation/roadmap.md` (S-13), PRD: `context/foundation/prd.md` (FR-020, FR-024)
- Wzorzec kolumny: `database/migrations/2026_09_28_064501_add_gender_to_people_table.php:15`
- Źródło nazwy: `app/Models/Person.php:102-105`
- Wyszukiwanie: `app/Http/Controllers/PersonController.php:40-50`
- Żywot karty: `app/Http/Resources/SheetPersonResource.php:29-47`
- Data częściowa: `app/Support/PartialDate.php`, test `tests/Unit/PartialDateTest.php`
- Odmiana: `resources/js/lib/plural.ts`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Nazwisko rodowe

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1` + ponowne `migrate` — a7e1c86
- [x] 1.2 Testy Pest osób przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/WriteTargetsTest.php tests/Feature/TreeTest.php` — a7e1c86
- [x] 1.3 Vitest przechodzi: `vendor/bin/sail npm run test:js` — a7e1c86
- [x] 1.4 Typy, lint i format przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check` — a7e1c86
- [x] 1.5 Larastan i Pint przechodzą: `vendor/bin/sail bin phpstan analyse` i `vendor/bin/sail bin pint --dirty --format agent` — a7e1c86
- [x] 1.6 Trzy specy E2E slotów drzewa przechodzą: `npx playwright test tests/e2e/tree-slot-spouse.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-parent.spec.ts` — a7e1c86

#### Manual

- [x] 1.7 W edycji „Dane osobowe” wpisanie nazwiska rodowego daje na drzewie i w nagłówku „Imię Nazwisko z d. Rodowe”, a awatar ma inicjały imienia i nazwiska — a7e1c86
- [x] 1.8 Wpisanie samego nazwiska rodowego pokazuje polski błąd pod polem „Nazwisko rodowe” — a7e1c86
- [x] 1.9 Lista osób i picker „wybierz osobę” na drzewie znajdują osobę po nazwisku rodowym — a7e1c86
- [x] 1.10 Zmiana daty urodzenia (inna sekcja) nie czyści nazwiska rodowego — a7e1c86

### Phase 2: Wiek osoby

#### Automated

- [x] 2.1 Testy Unit i Feature przechodzą: `vendor/bin/sail artisan test --compact tests/Unit/AgeTest.php tests/Feature/TreeTest.php tests/Feature/PersonsTest.php` — 46b504c
- [x] 2.2 Vitest przechodzi: `vendor/bin/sail npm run test:js` — 46b504c
- [x] 2.3 Typy, lint i format przechodzą: `vendor/bin/sail npm run types:check` i `vendor/bin/sail npm run check` — 46b504c
- [x] 2.4 Larastan i Pint przechodzą: `vendor/bin/sail bin phpstan analyse` i `vendor/bin/sail bin pint --dirty --format agent` — 46b504c

#### Manual

- [x] 2.5 Nagłówek podglądu zmarłej osoby pokazuje „A – B (N lat)”, żyjącej — „ur. A (N lat)” — 46b504c
- [x] 2.6 Karta drzewa pokazuje wiek w nawiasie po żywocie i nie rozjeżdża się na wąskiej karcie — 46b504c
- [x] 2.7 Osoba ur. 1916 (sam rok) bez zgonu nie ma wieku, ur. 1917 ma 109 lat; dziecko zmarłe przed rokiem ma miesiące/dni albo „poniżej roku” — 46b504c
