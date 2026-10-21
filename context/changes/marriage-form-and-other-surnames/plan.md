# Forma ślubu — plan implementacji

## Overview

Małżeństwo dostaje atrybut **forma ślubu**: cywilny / wyznaniowy / cywilno-wyznaniowy / nieznana (domyślna). Autor wybiera ją ręcznie w karcie „Ślub” (panel małżeństwa i przegląd osoby), a forma jest widoczna w tej karcie i w wierszu małżeństwa w zakładce Rodzina. Para bez ślubu i inne nazwiska osoby — mimo nazwy zmiany — są poza zakresem (decyzje autora 1 i 4 w `research.md`).

## Current State Analysis

- Małżeństwo to `Event{type: marriage}` + dwóch uczestników `spouse`; zapisuje się tylko data częściowa i `locality_id` (`app/Actions/Persons/MapsEventInput.php:18-27`). Formy ślubu nie modeluje nic.
- Jedyna ścieżka edycji: `PUT marriages/{id}` → `MarriageUpdateRequest::rules()` = `partialDateRules('marriage')` (`app/Http/Requests/MarriageUpdateRequest.php:38-41`) → `UpdateMarriage::handle` = `$marriage->update($this->eventAttributesFrom($date))` (`app/Actions/Relations/UpdateMarriage.php:18-23`) — pełne zastąpienie daty i miejsca.
- Tworzenie: `CreateMarriage::handle($tree, $person, $spouse, $date)` (`app/Actions/Relations/CreateMarriage.php:24-55`); wywołują je `MarriageController::store` (z `MarryCoupleDialog`, bez daty), `PersonSpouseController::attach` i `CreateSpouseForPerson` (zawsze `[]`).
- Odczyt: `EventResource` (`app/Http/Resources/EventResource.php:37-76`) — używany bezpośrednio przez `BuildMarriagePanel` (`app/Actions/Relations/BuildMarriagePanel.php:50`) i rozszerzany przez `MarriageResource` (`app/Http/Resources/MarriageResource.php:41-55`, małżeństwa w przeglądzie i Rodzinie).
- Opcje enumów trafiają na front jako `{value, label}[]` z `Enum::options()`; `cemeteryManagers` jest propem współdzielonym (`app/Http/Middleware/HandleInertiaRequests.php:78`, typ w `resources/js/types/global.d.ts:34`).
- Front: karta „Ślub” w panelu małżeństwa — własny grid Data/Miejsce (`resources/js/components/marriages/marriage-panel.tsx:179-187`); w przeglądzie osoby — `FactView` wspólny z urodzeniem i zgonem (`resources/js/components/persons/overview-tab.tsx:100-118, 419-424`); edycja w obu — `MarriageFields` (`resources/js/components/persons/person-section-fields.tsx:256-268`); zapis — `marriageFieldDefaults` / `marriageSaveRequest` / `eventPayloadFromValues` (`resources/js/lib/person-payload.ts:19, 73-84, 186-212`); wiersz Rodziny — `MarriageRow`, `details = [date, place].join(', ')` (`resources/js/components/persons/family-tab.tsx:220-221`).
- Wzorzec „nieznana” w Select: stała `NONE` + ukryty input z pustą wartością (`person-section-fields.tsx:25-26, 128-149`, pole Wyznanie). `Field` pokazuje pusty stan jako wyszarzone „brak danych” (`resources/js/components/persons/fact-field.tsx:2-17`).

## Desired End State

- Kolumna `events.marriage_form` (nullable string, `null` = nieznana) z rzutowaniem na enum `App\Enums\MarriageForm` (`civil`, `religious`, `civil_religious`; etykiety „cywilny”, „wyznaniowy”, „cywilno-wyznaniowy”).
- `PUT marriages/{id}` i `POST marriages` przyjmują `marriage.form` (nullable, enum); brak klucza zapisuje `null` (pełne zastąpienie, jak data i miejsce).
- Payload małżeństwa (panel i `person.marriages`) niesie `marriage_form` i `marriage_form_label` (oba `null` dla nieznanej); współdzielony prop `marriageForms` niesie opcje.
- Karta „Ślub” w edycji ma Select „Forma ślubu” (Nieznana / cywilny / wyznaniowy / cywilno-wyznaniowy); w widoku zawsze pole „Forma ślubu”, pusta = wyszarzone „nieznana”.
- Wiersz Rodziny przy znanej formie: „ślub cywilny, 15.06.2001, Kraków”; przy nieznanej — bez zmian („15.06.2001, Kraków”).
- Nagłówek panelu małżeństwa, drzewo (`union-node.tsx`) i oś czasu — bez zmian.
- Demo: małżeństwo Jan i Anna (2001, Kraków) w `TreeViewDemoSeeder` ma formę cywilno-wyznaniową.
- FR-017 w PRD wymienia formę ślubu.

Weryfikacja: testy Pest w `tests/Feature/MarriagesTest.php`, Vitest dla payloadu i szczegółów wiersza, ręczny przegląd panelu małżeństwa, przeglądu osoby i Rodziny na danych demo.

### Key Discoveries:

- Pełne zastąpienie w `UpdateMarriage` oznacza, że front musi **zawsze** wysyłać `marriage.form` — inaczej zapis daty skasuje formę (`person-payload.ts:203-212`).
- `MapsEventInput::eventAttributesFrom` obsługuje też urodzenie i zgon (`app/Actions/Persons/MapsEventInput.php:18-27`); forma nie może do niego trafić, bo zapis osoby wyzerowałby `marriage_form` innym zdarzeniom (bez znaczenia) albo — gorzej — zacząłby wymagać klucza w payloadach urodzenia/zgonu.
- `MarriagePanelPayload.marriage` ma typ `SourcedPersonEvent`, nie `Marriage` (`resources/js/types/person.ts:44-70`) — nowe pola trzeba dodać w obu typach.
- Precedens pary wartość + etykieta w payloadzie: `kind` / `kind_short_label` źródła w `EventResource.php:67-68`.
- Precedens opcjonalnego pola tylko dla jednego typu zdarzenia w `FactView`: `burialPlace` (`overview-tab.tsx:115-117`).

## What We're NOT Doing

- Para bez ślubu jako zapisany rekord (bez dzieci, z panelem) — zostaje „bez ślubu” wyliczane ze wspólnych dzieci (`BuildFamilySheet.php:77-117`).
- Lista innych nazwisk osoby (po mężu / wariant / vel) i zmiany wyszukiwania lub pickerów — non-goal PRD `prd.md:240` bez zmian.
- Podpowiedź formy ze źródeł (rodzaj prowadzącego akta, `ParishKind`).
- Forma w nagłówku panelu małżeństwa, na węźle drzewa (`union-node.tsx`), na osi czasu i na liście `/persons`.
- Wybór formy przy tworzeniu małżeństwa (`MarryCoupleDialog`, dodanie małżonka na drzewie) — małżeństwo powstaje z formą nieznaną, uzupełnia się ją w karcie „Ślub”.
- Dwa małżeństwa tej samej pary (cywilny + kościelny osobno) — nadal jedno zdarzenie na parę; „cywilno-wyznaniowy” to ten przypadek.
- Testy E2E (Playwright) — ryzyko leży na granicy HTTP i payloadu, pokrywają je Pest i Vitest.
- Zmiana identyfikatora zmiany `marriage-form-and-other-surnames` (nieaktualna część „other-surnames” zostaje w nazwie folderu).

## Implementation Approach

Atrybut zdarzenia, nie nowy byt: jedna nullable kolumna na `events`, przechodząca istniejącą ścieżką request → akcja → resource → `MarriageFields`, bez nowych tras i polityk. Faza 1 kończy backend z testami (front nadal działa, bo nowe pola w odpowiedzi są dodatkowe, a brak klucza w PUT daje `null` — stan identyczny z dzisiejszym). Faza 2 dokłada edycję i wyświetlanie.

## Phase 1: Model i zapis formy ślubu

### Overview

Enum, kolumna, walidacja, mapowanie w akcjach małżeństwa, pola w resource, współdzielony prop z opcjami, seeder demo i testy Pest.

### Changes Required:

#### 1. Enum formy ślubu

**File**: `app/Enums/MarriageForm.php` (nowy, `vendor/bin/sail artisan make:enum`)

**Intent**: Zamknięta lista form ślubu z polskimi etykietami i listą opcji dla frontu, wzorem `CemeteryManager`.

**Contract**: `enum MarriageForm: string` — `Civil = 'civil'`, `Religious = 'religious'`, `CivilReligious = 'civil_religious'`; `label()` → „cywilny” / „wyznaniowy” / „cywilno-wyznaniowy”; `static options(): array<int, array{value: string, label: string}>`. Brak przypadku „nieznana” — to `null`.

#### 2. Migracja i model

**File**: `database/migrations/<timestamp>_add_marriage_form_to_events_table.php` (nowy), `app/Models/Event.php`

**Intent**: Kolumna na formę ślubu; istniejące wiersze dostają `null` (nieznana), więc nic nie jest przenoszone i test migracji danych nie jest potrzebny.

**Contract**: `events.marriage_form` — `string`, nullable, bez domyślnej wartości; `down()` usuwa kolumnę. `Event`: `marriage_form` w `#[Fillable]`, cast `MarriageForm::class`, `@property MarriageForm|null $marriage_form`. Kolumna ma znaczenie tylko dla `type = marriage`.

#### 3. Walidacja

**File**: `app/Http/Requests/MarriageUpdateRequest.php`, `app/Http/Requests/MarriageStoreRequest.php`

**Intent**: Przyjmować formę razem z datą i miejscem; nieznana wartość jest błędem walidacji.

**Contract**: reguła `'marriage.form' => ['nullable', Rule::enum(MarriageForm::class)]` w obu requestach (w `MarriageUpdateRequest` scalona z `partialDateRules('marriage')`); atrybut `'marriage.form' => 'forma ślubu'`.

#### 4. Mapowanie w akcjach małżeństwa

**File**: `app/Actions/Persons/MapsEventInput.php`, `app/Actions/Relations/UpdateMarriage.php`, `app/Actions/Relations/CreateMarriage.php`

**Intent**: Forma zapisuje się tylko dla małżeństwa, z tą samą semantyką pełnego zastąpienia co data i miejsce; mapowanie urodzenia i zgonu się nie zmienia.

**Contract**: nowa metoda w traicie `marriageAttributesFrom(array $marriage): array` = `eventAttributesFrom($marriage)` + `'marriage_form' => $marriage['form'] ?? null`; `UpdateMarriage::handle` i `CreateMarriage::handle` używają jej zamiast `eventAttributesFrom`. PHPDoc shape parametru `$date` w obu akcjach rozszerzony o `form?: string|null`. `eventAttributesFrom` bez zmian.

#### 5. Payload małżeństwa i opcje

**File**: `app/Http/Resources/EventResource.php`, `app/Http/Middleware/HandleInertiaRequests.php`

**Intent**: Front dostaje wartość (do edycji) i etykietę (do widoku) formy przy każdym małżeństwie oraz listę opcji na każdej stronie z panelem małżeństwa lub osoby.

**Contract**: `EventResource::toArray` dodaje — tylko gdy `type === EventType::Marriage` (`$this->when` / `mergeWhen`) — `marriage_form: string|null` i `marriage_form_label: string|null`; PHPDoc shape uzupełniony o `marriage_form?`, `marriage_form_label?` (`MarriageResource` dziedziczy je przez spread). Współdzielony prop `'marriageForms' => fn (): array => MarriageForm::options()` obok `cemeteryManagers`.

#### 6. Dane demo

**File**: `database/seeders/TreeViewDemoSeeder.php`

**Intent**: Demo pokazuje oba stany (znana i nieznana forma) do przeglądu i zrzutów ekranu.

**Contract**: wywołanie `marry($jan, $anna, [...])` (`:140`) dostaje `'form' => MarriageForm::CivilReligious->value`; pozostałe małżeństwa bez zmian. Przed zmianą sprawdzić, że żaden test ani spec nie porównuje dokładnego payloadu tego małżeństwa.

#### 7. Testy Pest

**File**: `tests/Feature/MarriagesTest.php`

**Intent**: Pokryć zapis, pełne zastąpienie, walidację i odczyt formy w obu payloadach.

**Contract**: nowe testy — (a) update z `form: civil_religious` zapisuje formę, a panel (`marriagePanelUrl`) i przegląd osoby zwracają `marriage_form` + `marriage_form_label` „cywilno-wyznaniowy”; (b) update bez klucza `form` (lub `form: null`) przy ustawionej formie zapisuje `null` i oba payloady zwracają `null`; (c) niepoprawna wartość (`'kościelny'`) zwraca błąd na `marriage.form` i nic nie zmienia; (d) store z `marriage.form` tworzy małżeństwo z tą formą, a `attach` / store małżonka tworzą małżeństwo z `marriage_form = null`; (e) zdarzenie urodzenia w payloadzie osoby nie ma klucza `marriage_form`. Istniejący test „update changes the date and locality and keeps linked sources” bez zmian. Ewentualna zmiana listy kluczy w `tests/Feature/WriteTargetsTest.php` tylko, jeśli test porównuje kolumny (dziś porównuje tabele).

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate`
- Testy małżeństw i zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/PersonsTest.php tests/Feature/TreeTest.php`
- Testy kopii zapasowej przechodzą: `vendor/bin/sail artisan test --compact tests/Backup`
- Seeder demo działa: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder` kończy się bez błędu
- Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`

**Implementation Note**: Po przejściu weryfikacji automatycznej przejdź do Fazy 2 — ta faza nie ma kroków ręcznych (UI jeszcze nie pokazuje formy).

---

## Phase 2: Edycja i wyświetlanie formy ślubu

### Overview

Typy, Select w karcie „Ślub”, zawsze wysyłane pole `form`, pole „Forma ślubu” w obu widokach karty, przedrostek w Rodzinie, Vitest i dopisanie formy do FR-017.

### Changes Required:

#### 1. Typy

**File**: `resources/js/types/person.ts`, `resources/js/types/global.d.ts` (i `resources/js/types/index.ts`, jeśli tam są reeksporty)

**Intent**: Pola formy w typach małżeństwa i opcje we współdzielonych propach.

**Contract**: `MarriageFormOption = { value: string; label: string }`; typ `MarriageEvent = SourcedPersonEvent & { marriage_form: string | null; marriage_form_label: string | null }`; `Marriage` oparty na `MarriageEvent`, `MarriagePanelPayload.marriage: MarriageEvent`; w `global.d.ts` `marriageForms: MarriageFormOption[]`.

#### 2. Select „Forma ślubu”

**File**: `resources/js/components/persons/person-section-fields.tsx`

**Intent**: Wybór formy w edycji karty „Ślub” (panel i przegląd), wzorem pola Wyznanie.

**Contract**: `MarriageFields` renderuje pod `PartialDateInput` Select z etykietą „Forma ślubu” (`id="marriage-form"`), pierwsza opcja `NONE` = „Nieznana”, potem `usePage().props.marriageForms`; ukryty `<input name="marriage.form">` z pustą wartością dla nieznanej; `InputError` dla `errors['marriage.form']`. `defaultValue` rozszerzone o `form: string | null`.

#### 3. Payload zapisu

**File**: `resources/js/lib/person-payload.ts`

**Intent**: Każdy zapis karty „Ślub” wysyła formę, żeby pełne zastąpienie nie kasowało jej przy edycji samej daty.

**Contract**: `MarriageSavePayload = { marriage: PartialDatePayload & { form: string | null } }`; `marriageSaveRequest` dokłada `form: values['marriage.form'] || null` (pusty string → `null`); `marriageFieldDefaults(marriage: MarriageEvent)` zwraca dodatkowo `form: marriage.marriage_form`. `eventPayloadFromValues` bez zmian (używają go urodzenie i zgon).

#### 4. Pole w widoku karty

**File**: `resources/js/components/persons/fact-field.tsx`, `resources/js/components/marriages/marriage-panel.tsx`, `resources/js/components/persons/overview-tab.tsx`

**Intent**: Forma zawsze widoczna w karcie „Ślub”; pusta jako wyszarzone „nieznana” (to samo słowo co opcja w Select).

**Contract**: `Field` dostaje opcjonalny prop `emptyText` (domyślnie „brak danych”). Panel małżeństwa: trzecie `<Field label="Forma ślubu" value={marriage.marriage_form_label} emptyText="nieznana" />` w gridzie karty. `FactView`: opcjonalny prop `marriageForm?: string | null` renderujący to samo pole, gdy `!== undefined` (wzór `burialPlace`); karta małżeństwa w przeglądzie przekazuje `marriage.marriage_form_label`. Nagłówek (`description`) panelu bez zmian.

#### 5. Szczegóły wiersza w Rodzinie

**File**: `resources/js/lib/marriage-details.ts` (nowy), `resources/js/lib/marriage-details.test.ts` (nowy), `resources/js/components/persons/family-tab.tsx`

**Intent**: Przedrostek „ślub {forma}” tylko przy znanej formie; logika w czystej funkcji, żeby dało się ją przetestować w Vitest.

**Contract**: `marriageRowDetails(marriage: Pick<Marriage, 'marriage_form_label' | 'date' | 'place'>): string` = znane części `[`ślub ${label}`, date, place]` złączone `', '`; pusty string, gdy nic nie wiadomo. `MarriageRow` używa jej zamiast `[marriage.date, marriage.place].filter(Boolean).join(', ')`. Test Vitest: forma + data + miejsce → „ślub cywilny, 15.06.2001, Kraków”; bez formy → „15.06.2001, Kraków”; sama forma → „ślub cywilno-wyznaniowy”; nic → „”.

#### 6. Test payloadu

**File**: `resources/js/lib/person-payload.test.ts`

**Intent**: Zabezpieczyć, że zapis karty zawsze niesie `form`.

**Contract**: `marriageSaveRequest` z `marriage.form = 'civil'` → `form: 'civil'`; z pustym `marriage.form` → `form: null`; `marriageFieldDefaults` przenosi `marriage_form`.

#### 7. PRD

**File**: `context/foundation/prd.md`

**Intent**: FR-017 opisuje formę ślubu w panelu małżeństwa.

**Contract**: FR-017 (`prd.md:147`) → „Autor can otworzyć panel małżeństwa: data, miejsce, forma ślubu (cywilny / wyznaniowy / cywilno-wyznaniowy / nieznana), źródło (z edycją) i dzieci pary.” Non-goal `prd.md:240` bez zmian.

### Success Criteria:

#### Automated Verification:

- Testy frontendu przechodzą (w tym `marriage-details.test.ts`, `person-payload.test.ts`, `view-tokens.test.ts`): `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Testy małżeństw nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php`

#### Manual Verification:

- Na danych demo panel małżeństwa Jan i Anna pokazuje „Forma ślubu: cywilno-wyznaniowy”, a inne małżeństwo — wyszarzone „nieznana”
- Zmiana formy w panelu małżeństwa zapisuje się i jest widoczna w przeglądzie osoby oraz w Rodzinie („ślub cywilny, …”) obu małżonków
- Edycja samej daty w przeglądzie osoby nie zmienia ustawionej formy
- Ustawienie „Nieznana” usuwa przedrostek w Rodzinie i pokazuje „nieznana” w karcie
- Nagłówek panelu małżeństwa, węzeł drzewa i oś czasu wyglądają jak przed zmianą
- Zrzuty ekranu karty „Ślub” (widok i edycja) i wiersza Rodziny w `context/changes/marriage-form-and-other-surnames/screenshots/`

**Implementation Note**: Po przejściu weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przez człowieka, że testy manualne się udały.

---

## Testing Strategy

### Unit Tests:

- Vitest: `marriageRowDetails` (cztery przypadki z Fazy 2 §5), `marriageSaveRequest` / `marriageFieldDefaults` (Faza 2 §6).
- `view-tokens.test.ts` już skanuje `marriage-panel.tsx` — nowe pole nie może wprowadzić klas palety ani wartości arbitralnych.

### Integration Tests:

- Pest `MarriagesTest`: zapis i odczyt formy w panelu i przeglądzie, pełne zastąpienie (brak klucza = `null`), błąd walidacji dla nieznanej wartości, store z formą, `attach` / store małżonka z `null`, brak klucza w zdarzeniach niebędących małżeństwem.
- Istniejące testy cudzego drzewa (404) dla `PUT marriages/{id}` pokrywają nowe pole bez zmian — trasa i polityka są te same.

### Manual Testing Steps:

1. `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder` (dane demo), otwórz drzewo Jana, otwórz panel małżeństwa z Anną — „cywilno-wyznaniowy”.
2. Edytuj kartę „Ślub”, wybierz „cywilny”, zapisz; otwórz panel Anny → Przegląd i Rodzina — „cywilny” / „ślub cywilny, 15.06.2001, Kraków”.
3. W przeglądzie Jana zmień tylko dzień ślubu, zapisz — forma zostaje „cywilny”.
4. Ustaw „Nieznana” — karta pokazuje wyszarzone „nieznana”, Rodzina bez przedrostka.

## Migration Notes

Kolumna dodawana jako nullable bez wartości domyślnej — istniejące małżeństwa mają formę nieznaną, żadne dane nie są przepisywane (reguła „migracje przenoszące dane” nie ma zastosowania). Produkcja robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`) — bez zmian. Wycofanie: `migrate:rollback` usuwa kolumnę razem z wpisanymi formami.

## References

- Research: `context/changes/marriage-form-and-other-surnames/research.md` (decyzje autora 1–5)
- Wzorzec enumu z opcjami: `app/Enums/CemeteryManager.php`, prop `cemeteryManagers` w `app/Http/Middleware/HandleInertiaRequests.php:78`
- Wzorzec Select z „nieznaną”: `resources/js/components/persons/person-section-fields.tsx:128-149` (Wyznanie)
- Ścieżka zapisu małżeństwa: `app/Http/Requests/MarriageUpdateRequest.php`, `app/Actions/Relations/UpdateMarriage.php`, `resources/js/lib/person-payload.ts:186-212`
- Decyzja „jedno małżeństwo na parę”: `context/archive/2026-09-25-marriage-with-shared-source/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Model i zapis formy ślubu

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate`, `vendor/bin/sail artisan migrate:rollback --step=1`, `vendor/bin/sail artisan migrate`
- [x] 1.2 Testy małżeństw i zapisu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/PersonsTest.php tests/Feature/TreeTest.php`
- [x] 1.3 Testy kopii zapasowej przechodzą: `vendor/bin/sail artisan test --compact tests/Backup`
- [x] 1.4 Seeder demo działa: `vendor/bin/sail artisan db:seed --class=TreeViewDemoSeeder` kończy się bez błędu
- [x] 1.5 Formatowanie PHP i Larastan: `vendor/bin/sail bin pint --dirty --format agent`, `vendor/bin/sail composer types:check`

### Phase 2: Edycja i wyświetlanie formy ślubu

#### Automated

- [ ] 2.1 Testy frontendu przechodzą (w tym `marriage-details.test.ts`, `person-payload.test.ts`, `view-tokens.test.ts`): `vendor/bin/sail npm run test:js`
- [ ] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [ ] 2.3 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [ ] 2.4 Build przechodzi: `vendor/bin/sail npm run build`
- [ ] 2.5 Testy małżeństw nadal przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php`

#### Manual

- [ ] 2.6 Na danych demo panel małżeństwa Jan i Anna pokazuje „Forma ślubu: cywilno-wyznaniowy”, a inne małżeństwo — wyszarzone „nieznana”
- [ ] 2.7 Zmiana formy w panelu małżeństwa zapisuje się i jest widoczna w przeglądzie osoby oraz w Rodzinie („ślub cywilny, …”) obu małżonków
- [ ] 2.8 Edycja samej daty w przeglądzie osoby nie zmienia ustawionej formy
- [ ] 2.9 Ustawienie „Nieznana” usuwa przedrostek w Rodzinie i pokazuje „nieznana” w karcie
- [ ] 2.10 Nagłówek panelu małżeństwa, węzeł drzewa i oś czasu wyglądają jak przed zmianą
- [ ] 2.11 Zrzuty ekranu karty „Ślub” (widok i edycja) i wiersza Rodziny w `context/changes/marriage-form-and-other-surnames/screenshots/`
