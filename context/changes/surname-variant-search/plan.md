# Wyszukiwanie osób po wariantach nazwiska — plan implementacji

## Overview

Warianty zapisu nazwiska z bloku osoby (`warianty_nazwiska`) przestają być linią tekstu w notatce i trafiają do własnej tabeli przy osobie. Wyszukiwarka `/persons` znajduje osobę po każdym wariancie (Misiuk po „Mysiuk”), panel osoby pokazuje warianty tylko do odczytu, a wiersz listy wyjaśnia trafienie po wariancie dopiskiem „zapis: Mysiuk”. Roadmapa S-09, PRD US-03 i FR-019.

## Current State Analysis

- Import już czyta warianty: `ParsePersonBlock` waliduje `warianty_nazwiska` jako listę tekstów (`app/Actions/Import/ParsePersonBlock.php:187-188`) i wypełnia `ResearchPerson::$surnameVariants` (`ParsePersonBlock.php:360`, `app/Support/Import/ResearchPerson.php:20`).
- Jedyny zapis wariantów to linia `Warianty nazwiska: A, B` w bloku „Z repo badawczego” notatki osoby (`app/Support/Import/RepoValues.php:149-151`). Podsumowanie komendy liczy je w grupie „Do notatki osoby” jako `warianty nazwiska: %d (S-09)` (`app/Console/Commands/ImportResearch.php:952`). Licznik żyje w `NoteValueCounts::$surnameVariants` i jest liczony dla nowych osób i pierwszych dopasowań (`app/Actions/Import/PlanImport.php:339`, `:436`).
- Warianty biorą też udział w dopasowaniu osób przy pierwszym imporcie (`app/Support/Import/NameKey.php:45-58`). Ta zmiana tego nie zmienia.
- Tabela `people` ma tylko `first_name` / `last_name` (nazwisko rodowe) i nie ma kolumn JSON. Wzorzec wariantów nazwy to `locality_aliases` (`database/migrations/2026_10_30_150725_create_locality_aliases_table.php`): osobna tabela z `tree_id`, unikalny indeks wyrażeniowy na `lower(name)`, zapis tylko przez import i scalanie, panel pokazuje „Inne zapisy nazwy” (`resources/js/components/localities/locality-panel.tsx:137-141`).
- Wyszukiwarka: każde słowo `q` musi pasować przez `whereLike` do `first_name` albo `last_name` (`app/Http/Controllers/PersonController.php:42-51`). Wielkość liter nie ma znaczenia, polskie znaki mają (utrwala to test `tests/Feature/PersonsTest.php:88`).
- Import porównuje pola osoby regułą trzech wartości (`app/Support/Import/ThreeWayValue.php`). Pamięć ostatniego importu trzyma `repo_snapshots.values` z kluczami `imiona`, `nazwisko`, `plec`, `notatka` (`RepoValues::person()`, `RepoValues.php:18-32`). Pola osoby planuje `PlanImport::planFields()` (`PlanImport.php:937-1004`). Zapis robią `ApplyImport::createPerson()` (`:765-794`), `writeUpdate()` (`:805-902`) i `writeMatch()` (`:913-987`), a kontrola „nic się nie zmieniło od planu” to `guardPerson()` (`:998-1018`). `PlannedChange`, `PlannedFill` i `ValueConflict` przyjmują tylko wartości skalarne (`string|Gender|ResearchDate|PlannedPlace`).
- Baza deweloperska nie ma ani jednej zaimportowanej osoby (0 osób z `repo_id`, 0 notatek z linią wariantów).

## Desired End State

- Osoba ma listę wariantów nazwiska w tabeli `person_surname_variants`. Wpisuje ją tylko import: nowa osoba dostaje warianty przy utworzeniu, pierwsze dopasowanie przez uzupełnienie, a znana osoba przez zmianę do zatwierdzenia, jak inne pola.
- Blok notatki z importu nie zawiera już linii `Warianty nazwiska:`.
- Wpisanie „Mysiuk” w wyszukiwarce `/persons` pokazuje Jana Misiuka, który ma wariant „Mysiuk”. Wiersz listy i tabeli pokazuje wtedy wyciszone „zapis: Mysiuk”.
- Karta „Dane osobowe” w panelu osoby pokazuje pole „Warianty nazwiska” (tylko do odczytu), gdy osoba ma warianty.
- Sprawdzenie: testy Pest (wyszukiwarka, plan, zapis, komenda) i Vitest (dopisek) przechodzą, a ręczny import wymyślonego repo na koncie testowym daje opisany wynik.

### Key Discoveries:

- Wariantów nie ma gdzie zapisać poza notatką. Linia w notatce znika w tej samej zmianie, w której pojawia się pole, inaczej wartość byłaby w dwóch miejscach (`RepoValues.php:149-151`).
- `lastOf()` zwraca „brak pamięci”, gdy pamięć nie ma klucza (`PlanImport.php:1011-1018`). Osoby zaimportowane przed tą zmianą dostaną więc przy kolejnym imporcie uzupełnienie wariantów, a nie konflikt.
- Edycja osoby to pełne zastąpienie payloadu (`resources/js/lib/person-payload.ts:139-185`). Warianty nie wchodzą do formularza ani do `UpdatePerson`, więc zapis formularza ich nie dotyka.
- Pickery osób filtrują po stronie przeglądarki po `display_name` (`app/Actions/Trees/ListPersonCandidates.php:17-32`, `resources/js/components/persons/person-candidate-list.tsx:33-38`) i zostają bez zmian.

## What We're NOT Doing

- Ręcznej edycji wariantów w formularzu osoby (decyzja autora: warianty przychodzą tylko z importu). Osoba dodana tylko w aplikacji nie ma wariantów. Osoba odłączona przez import (`ApplyImport::detach()` zeruje `repo_id` i zostawia osobę) zachowuje swoje warianty, tak jak zachowuje blok notatki „Z repo badawczego”. Przy ponownym dopasowaniu inna lista z repo daje konflikt.
- Wyszukiwania po wariancie w pickerach osób (rodzic, dziecko, małżonek, pochowani w źródle) ani w spisie miejsc.
- Ignorowania polskich znaków w wyszukiwarce. Zostaje reguła z `PersonsTest.php:88`.
- Zmian w dopasowaniu osób przy imporcie (`NameKey`, `MatchResearchPersons`). Wariantów osób z drzewa nie dodajemy do kluczy dopasowania.
- Cichej synchronizacji wariantów. Każda zmiana listy u znanej osoby idzie do zatwierdzenia.
- Zmiany wersji kontraktu bloku. Znaczenie i nazwa `warianty_nazwiska` się nie zmieniają.
- Testów E2E. Ryzyko leży w zapytaniu, w planie importu i w czystej funkcji dopisku, które pokrywają Pest i Vitest.
- Przepisywania notatek osób zaimportowanych wcześniej migracją. Linię usuwa kolejny import jako zwykłą zmianę notatki.

## Implementation Approach

Pionowo w trzech krokach:

1. Tabela i model, żeby wyszukiwarkę dało się przetestować na danych z fabryki.
2. Import jako jedyny piszący. Warianty to jedno nowe pole osoby w istniejącej regule trzech wartości, z kluczem pamięci `warianty`, kluczem zmiany `O-NNNN/warianty` i etykietą „Warianty nazwiska”. Mały obiekt wartości `SurnameVariants` daje jedną postać tekstową do porównania i pamięci oraz etykietę do terminala, więc `ThreeWayValue` zostaje bez zmian.
3. Widok: pole w panelu i dopisek w wierszu, liczony czystą funkcją TS z aktywnego `q`.

## Critical Implementation Details

**Postać porównywana.** Lista z repo i lista z bazy przechodzą przez tę samą normalizację: przycięcie i scalenie spacji (`PlanImport::cleanText`), usunięcie pustych, usunięcie duplikatów bez względu na wielkość liter (zostaje pierwszy), kolejność z repo. Tekst do porównania i do pamięci to nazwy złączone znakiem nowej linii, a pusta lista to `null`. Etykieta w terminalu to nazwy złączone „, ”. Zmiana samej kolejności w repo jest zmianą.

**Reguła dopisku.** Słowa zapytania to `q` podzielone po białych znakach, jak na serwerze. Słowo „tłumaczy się wariantem”, gdy nie zawiera się (bez wielkości liter, z polskimi znakami) ani w imieniu, ani w nazwisku. Dla takich słów zbieramy warianty osoby, które je zawierają, bez powtórzeń i w kolejności wariantów. Niepusta lista daje „zapis: A, B”. Przykłady dla Jana Misiuka z wariantami [Mysiuk, Myszuk]:

| `q` | Dopisek |
| --- | --- |
| „Mysiuk” | „zapis: Mysiuk” |
| „Jan Mysiuk” | „zapis: Mysiuk” |
| „My” | „zapis: Mysiuk, Myszuk” |
| „siuk” | brak, bo pasuje nazwisko |
| puste | brak |

---

## Faza 1: Tabela wariantów i wyszukiwarka

### Overview

Nowa tabela i relacja osoby oraz zapytanie `/persons`, które sprawdza każde słowo także w wariantach.

### Changes Required:

#### 1. Migracja `person_surname_variants`

**File**: `database/migrations/<timestamp>_create_person_surname_variants_table.php` (przez `vendor/bin/sail artisan make:migration --no-interaction`)

**Intent**: Miejsce na warianty osoby, przypisane do drzewa jak każdy rekord domeny, usuwane razem z osobą lub drzewem.

**Contract**: Kolumny `id`, `tree_id` (FK `trees`, cascade), `person_id` (FK `people`, cascade), `name` string(255), `position` unsigned smallInteger, timestamps. Indeks na `person_id`. Unikalny indeks wyrażeniowy `(person_id, lower(name))` przez `DB::statement`, z komentarzem jak w `locality_aliases`. `down()` usuwa tabelę. Migracja nie przepisuje istniejących wierszy, więc nie potrzebuje `MigrationTest`.

#### 2. Model, relacja i fabryka

**File**: `app/Models/PersonSurnameVariant.php`, `app/Models/Person.php`, `database/factories/PersonSurnameVariantFactory.php`

**Intent**: Relacja do odczytu w wyszukiwarce i panelu oraz fabryka do testów.

**Contract**: `PersonSurnameVariant` ma fillable `tree_id`, `person_id`, `name`, `position` i `belongsTo` osoby. `Person::surnameVariants(): HasMany` jest posortowane po `position`, potem `id`. Fabryka domyślnie bierze `tree_id` z osoby.

#### 3. Zapytanie wyszukiwarki

**File**: `app/Http/Controllers/PersonController.php` (`index`)

**Intent**: Każde słowo `q` pasuje do imienia, nazwiska albo któregoś wariantu osoby. Pozostałe reguły (escapowanie `%` i `_`, limit 100 znaków, sortowanie) bez zmian.

**Contract**: W domknięciu słowa dochodzi `orWhereHas('surnameVariants', fn ($variants) => $variants->whereLike('name', $pattern))`. Zapytanie wychodzi dalej z `$tree->people()`. Przy niepustym `q` lista ładuje też `surnameVariants` (potrzebne w fazie 3).

#### 4. Testy wyszukiwarki

**File**: `tests/Feature/PersonsTest.php`

**Intent**: Utrwalić US-03 i granicę drzewa.

**Contract**: Nowe testy:
- „persons index finds a person by a surname variant”: Misiuk z wariantem Mysiuk, `q=Mysiuk`.
- „persons index matches each query word against first name, surname or a surname variant”: `q=Jan Mysiuk` trafia, `q=Anna Mysiuk` nie.
- „persons index matches a surname variant ignoring case but not polish diacritics”.
- „persons index never returns another users person by a surname variant”: osoba drugiego konta z tym samym wariantem nie wraca.
- „deleting a person removes its surname variants”.

Istniejące testy wyszukiwarki przechodzą bez zmian.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1`, potem ponownie `migrate`
- Testy wyszukiwarki przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php`
- Pint i Larastan bez nowych błędów: `vendor/bin/sail bin pint --dirty --format agent` i `vendor/bin/sail composer types:check`

#### Manual Verification:

- Na koncie deweloperskim osoba z wariantem dodanym w tinkerze pojawia się na `/persons` po wpisaniu wariantu

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Import zapisuje warianty

### Overview

Warianty stają się polem osoby w planie, zapisie i pamięci importu. Linia w notatce znika, a podsumowanie komendy pokazuje warianty poza grupą „Do notatki osoby”.

### Changes Required:

#### 1. Obiekt wartości `SurnameVariants`

**File**: `app/Support/Import/SurnameVariants.php`, `tests/Unit/SurnameVariantsTest.php`

**Intent**: Jedna normalizacja listy dla repo i dla bazy, żeby porównanie, pamięć, etykieta i zapis używały tej samej postaci.

**Contract**: `final readonly class SurnameVariants`:
- `list<string> $names`;
- `fromResearch(ResearchPerson)` i `fromPerson(Person)`;
- `text(): ?string` (złączone nową linią, `null` dla pustej listy), postać do porównania w `ThreeWayValue` i do pamięci;
- `label(): ?string` (złączone „, ”, `null` dla pustej listy), postać `appLabel` / `repoLabel` / `lastLabel` w pozycjach planu i w `guardPerson()`.

Normalizacja jak w „Critical Implementation Details”. Test jednostkowy pokrywa: przycięcie, puste, duplikat innej wielkości liter, kolejność, pustą listę.

#### 2. Pamięć i notatka

**File**: `app/Support/Import/RepoValues.php`

**Intent**: Pamięć osoby zna nowe pole, a blok notatki nie powtarza wariantów.

**Contract**: `RepoValues::person()` dodaje klucz `warianty` => `SurnameVariants::fromResearch($person)->text()`. `RepoValues::note()` nie dodaje już linii `Warianty nazwiska:`. Osoba, której blok miał tylko warianty, nie dostaje bloku (`null`).

#### 3. Plan

**File**: `app/Actions/Import/PlanImport.php`, `app/Support/Import/PlannedChange.php`, `app/Support/Import/PlannedFill.php`, `app/Support/Import/ValueConflict.php`

**Intent**: Warianty porównywane regułą trzech wartości jak imię, dla znanych i dla pierwszy raz dopasowanych osób.

**Contract**:
- `planFields()` porównuje pole `surname_variants`: etykieta „Warianty nazwiska”, nazwa w pamięci `warianty`, klucz `{id}/warianty`. Repo daje `SurnameVariants::fromResearch(...)->text()`, aplikacja `SurnameVariants::fromPerson(...)->text()`.
- Wartość w pozycji planu to obiekt `SurnameVariants`, więc unie typów `$repoValue` / `$value` w trzech klasach pozycji i w `itemFor()` dostają `SurnameVariants`. `ThreeWayValue::compare` dostaje `text()`, a `itemFor()` dostaje jako `appLabel` / `repoLabel` / `lastLabel` wartości `label()` (pamięć `warianty` przechowuje `text()`, więc `lastLabel` to `str_replace("\n", ', ', $last)`). Tylko ta postać trafia do `appLabel`, który potem czyta `guardPerson()`.
- `PlannedFill::valueLabel()` dostaje ramię `$this->value instanceof SurnameVariants => $this->value->label() ?? PlanImport::EMPTY_LABEL` (dziś gałąź `default` zwróciłaby obiekt).
- `PlanImport::personValues()` (`:1734-1743`): gałąź pominiętej płci nie składa już tablicy ręcznie, tylko nadpisuje `plec` w `$values` (`['plec' => $old->values['plec']] + $values`), żeby pamięć nie zgubiła `warianty`.
- Pusta lista w repo przy pamięci równej aplikacji daje zmianę z `null`, czyli wyczyszczenie listy.
- `PlanImport::FIELDS` dostaje `surname_variants`.
- Osoby planu ładują `surnameVariants`, żeby nie było N+1.
- Licznik `NoteValueCounts::$surnameVariants` zostaje, z poprawionym opisem: warianty zapisywane przy nowych i pierwszy raz dopasowanych osobach.

#### 4. Zapis

**File**: `app/Actions/Import/ApplyImport.php`

**Intent**: Zapis listy w tej samej transakcji co reszta osoby, z ochroną przed zmianą od planu.

**Contract**:
- Prywatna metoda zastępuje warianty osoby: usuwa wiersze osoby i wstawia listę w kolejności, z `tree_id` osoby i `position` od 0.
- `createPerson()` zapisuje warianty z `ResearchPerson`.
- `writeUpdate()` i `writeMatch()` mają ramię `surname_variants` w `match` pól.
- `guardPerson()` dostaje ramię `'surname_variants' => SurnameVariants::fromPerson($person)->label()` i porównuje je z `appLabel` z planu (ta sama postać „, ”, `null` dla pustej listy, bo guard zamienia „—” na `null`). Przy różnicy odmawia jak dla innych pól.
- Pamięć (`rememberSnapshots`) dostaje `warianty` przez `RepoValues::person()`.

#### 5. Komenda

**File**: `app/Console/Commands/ImportResearch.php`

**Intent**: Autor widzi warianty jako zwykłą pozycję zmian, uzupełnień i konfliktów, a w podsumowaniu poza „Do notatki osoby”.

**Contract**:
- Linia `warianty nazwiska: %d (S-09)` znika z grupy „Do notatki osoby”.
- Pod liniami „Miejsca” dochodzi osobna linia `Warianty nazwiska przy osobach: %d`.
- Tabele zmian i konfliktów pokazują pole „Warianty nazwiska” z etykietami „, ” bez dodatkowego kodu, jeśli `label()` trafia do istniejących `appLabel` / `repoLabel`. Sprawdzić `shown()` / `optionLabel()`.
- `repoValuesText()` (`:606-622`) bierze tylko pierwszą linię wartości (`strtok($value, "\n")`). Dla klucza `warianty` zamienia `"\n"` na „, ” przed skróceniem, żeby „Teraz w repo” pokazywało wszystkie warianty.

#### 6. Testy importu

**File**: `tests/Feature/ImportPlanTest.php`, `tests/Feature/ImportApplyTest.php`, `tests/Feature/ImportResearchCommandTest.php`

**Intent**: Pokryć pole w trzech miejscach i granicę drugiego konta.

**Contract**:
- Zaktualizowane testy:
  - „writes the person note exactly as specified” (`ImportPlanTest.php:235`): bez linii wariantów.
  - „counts the values that go into the person note” (`ImportPlanTest.php:277`): licznik dalej 1.
- Nowe testy planu (helpery `replanPerson`, `replanBlocks`):
  - znana osoba bez `warianty` w pamięci dostaje uzupełnienie;
  - zmieniona lista przy pamięci równej aplikacji to zmiana;
  - lista pusta w repo czyści;
  - lista z repo z duplikatem innej wielkości liter i z pustym wpisem, równa zapisanej po normalizacji, nie daje pozycji;
  - zmiana samej wielkości liter wariantu (repo „MYSIUK”, aplikacja „Mysiuk”) to zmiana, jak przy nazwisku (`ThreeWayValue` porównuje przez `===`);
  - przy płci pominiętej regułą płci rodziców pamięć osoby nadal ma `warianty`.
- Nowe testy zapisu (listy zmian i odmowy mają co najmniej dwa warianty, żeby rozróżnić postać „, ” od „\n”):
  - nowe osoby dostają warianty w kolejności z repo, bez duplikatów;
  - zatwierdzona zmiana zastępuje listę, a odrzucona ją zostawia;
  - zapis odmawia, gdy warianty zmieniły się po planie;
  - pamięć ma `warianty`;
  - osoba odłączona przez import zachowuje warianty, a jej ponowne dopasowanie z inną listą w repo daje konflikt, którego wzięcie z repo zastępuje listę;
  - drugie konto z tą samą osobą i wariantami zostaje nietknięte (`reimportTwins`, `expectOnlyAuthorRowsChanged`).
- Test komendy: tabela zmian pokazuje wiersz „Warianty nazwiska”, a podsumowanie nową linię.

#### 7. Kontrakt bloku

**File**: `context/foundation/research-block-contract.md`

**Intent**: Opis pola mówi, co import z nim robi. Wersja bez zmian, bo znaczenie się nie zmienia.

**Contract**:
- Wiersz `warianty_nazwiska` w tabeli „Pola”: „Lista tekstów. Import zapisuje je przy osobie (bez powtórzeń, bez względu na wielkość liter); wyszukiwarka osób znajduje osobę po każdym wariancie.”
- Frontmatter `updated: <dzień implementacji>`.

### Success Criteria:

#### Automated Verification:

- Testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Feature/ImportResearchCommandTest.php tests/Unit/SurnameVariantsTest.php`
- Pełny zestaw Pest przechodzi: `vendor/bin/sail artisan test --compact`
- Pint i Larastan bez nowych błędów: `vendor/bin/sail bin pint --dirty --format agent` i `vendor/bin/sail composer types:check`

#### Manual Verification:

- Import wymyślonego repo (`tests/Fixtures/research-repo`) na osobnym koncie testowym: podsumowanie pokazuje „Warianty nazwiska przy osobach”, a notatka Jana Nowaka nie ma linii wariantów
- Drugi przebieg bez zmian w repo kończy się „Nic do zapisania.”

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Warianty widoczne przy osobie i na liście

### Overview

Panel osoby pokazuje warianty, a wiersz listy i tabeli wyjaśnia trafienie po wariancie.

### Changes Required:

#### 1. Dane dla widoku

**File**: `app/Http/Resources/PersonResource.php`, `app/Actions/Persons/BuildPersonPreview.php`, `resources/js/types/person.ts`

**Intent**: Warianty idą do przeglądarki tylko tam, gdzie są potrzebne: w podglądzie osoby i na liście przy aktywnym wyszukiwaniu.

**Contract**:
- `PersonResource` dostaje `surname_variants?: list<string>` przez `whenLoaded('surnameVariants')`, z typem w docblocku.
- `BuildPersonPreview` ładuje `surnameVariants`.
- Typ `Person` w TS dostaje `surname_variants?: string[]`.

#### 2. Pole w „Dane osobowe”

**File**: `resources/js/components/persons/overview-tab.tsx`

**Intent**: Autor widzi warianty przy osobie, tylko do odczytu.

**Contract**: W widoku karty „Dane osobowe”, przy niepustej liście, `Field label="Warianty nazwiska"` z nazwami złączonymi „, ”. Tryb edycji (`PersonalFields`) i payload formularza bez zmian.

#### 3. Dopisek na liście i w tabeli

**File**: `resources/js/lib/persons-index.ts`, `resources/js/lib/persons-index.test.ts`, `resources/js/components/persons/persons-list.tsx`, `resources/js/components/persons/persons-table.tsx`, `resources/js/pages/persons/index.tsx`

**Intent**: Trafienie po wariancie nie wygląda na przypadkowy wynik. Bez wyszukiwania lista wygląda jak dziś.

**Contract**:
- Czysta funkcja `surnameVariantHint(person: Person, query: string): string[]` według „Reguły dopisku”.
- Strona przekazuje aktywne `filters.q` do listy i tabeli.
- Wiersz pod nazwą renderuje wyciszone „zapis: …” (tokeny, bez nowych kolorów), tylko gdy funkcja zwraca niepustą listę.
- Vitest pokrywa wszystkie przykłady z tabeli w „Critical Implementation Details” oraz osobę bez `surname_variants`.

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą (w tym `view-tokens.test.ts`): `vendor/bin/sail npm run test:js`
- Format, lint i typy przechodzą: `vendor/bin/sail npm run check` i `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Pest przechodzi: `vendor/bin/sail artisan test --compact`
- Pint i Larastan bez nowych błędów: `vendor/bin/sail bin pint --dirty --format agent` i `vendor/bin/sail composer types:check`

#### Manual Verification:

- Na koncie testowym po imporcie wymyślonego repo: „Nowakowski” na `/persons` pokazuje Jana Nowaka z dopiskiem „zapis: Nowakowski” w widoku lista i tabela
- „Nowak” pokazuje Jana Nowaka bez dopisku, a lista bez wyszukiwania wygląda jak przed zmianą
- Panel Jana Nowaka pokazuje w „Dane osobowe” pole „Warianty nazwiska: Nowakowski”, a zapis sekcji „Dane osobowe” go nie usuwa

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie.

---

## Testing Strategy

### Unit Tests:

- `SurnameVariants`: normalizacja (przycięcie, puste, duplikaty bez względu na wielkość liter, kolejność), `text()` i `label()`.
- `surnameVariantHint` (Vitest): przykłady z tabeli reguły dopisku, osoba bez wariantów, puste zapytanie.

### Integration Tests:

- `PersonsTest`: trafienie po wariancie, wiele słów, wielkość liter bez ignorowania polskich znaków, drugie konto, usunięcie osoby.
- `ImportPlanTest`, `ImportApplyTest`, `ImportResearchCommandTest`: uzupełnienie, zmiana, wyczyszczenie, brak pozycji przy tej samej liście, zmiana samej wielkości liter, odmowa po zmianie od planu (dwa warianty), pamięć `warianty` także przy pominiętej płci, odłączona osoba zachowuje warianty, drugie konto nietknięte, wiersz w tabeli zmian i linia podsumowania.

### Manual Testing Steps:

1. Zarejestruj osobne konto testowe i uruchom `vendor/bin/sail artisan import:research <e-mail-testowy> tests/Fixtures/research-repo`, zatwierdź zapis.
2. Na `/persons` wpisz „Nowakowski” i sprawdź dopisek w widoku lista i tabela.
3. Otwórz Jana Nowaka i sprawdź pole „Warianty nazwiska” oraz brak linii wariantów w notatce.
4. Uruchom import drugi raz i sprawdź „Nic do zapisania.”

## Performance Considerations

`whereHas` na każde słowo dodaje podzapytanie `EXISTS` po indeksie `person_id`. Przy kilkuset osobach w drzewie to pomijalny koszt. Warianty ładujemy na liście tylko przy niepustym `q`.

## Migration Notes

- Nowa tabela bez przepisywania danych, z odwracalnym `down()`. `scripts/deploy.sh` robi kopię bazy przed `migrate` jak zawsze.
- Osoby zaimportowane przed tą zmianą mają linię wariantów w notatce i pamięć bez `warianty`. Kolejny import pokaże u nich zmianę notatki (blok bez linii) do zatwierdzenia i uzupełnienie wariantów. Baza deweloperska nie ma takich osób.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-09)
- PRD: `context/foundation/prd.md` (US-03, FR-019)
- Kontrakt bloku: `context/foundation/research-block-contract.md`
- Wzorzec tabeli wariantów: `database/migrations/2026_10_30_150725_create_locality_aliases_table.php`, `context/archive/2026-10-30-import-places-matched-to-gazetteer/plan-brief.md`
- Wyszukiwarka: `app/Http/Controllers/PersonController.php:42-51`
- Pola osoby w imporcie: `app/Actions/Import/PlanImport.php:937-1004`, `app/Actions/Import/ApplyImport.php:765-1018`, `app/Support/Import/RepoValues.php:18-151`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Tabela wariantów i wyszukiwarka

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1`, potem ponownie `migrate` — 6986784
- [x] 1.2 Testy wyszukiwarki przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php` — 6986784
- [x] 1.3 Pint i Larastan bez nowych błędów: `vendor/bin/sail bin pint --dirty --format agent` i `vendor/bin/sail composer types:check` — 6986784

#### Manual

- [x] 1.4 Na koncie deweloperskim osoba z wariantem dodanym w tinkerze pojawia się na `/persons` po wpisaniu wariantu — ręcznie 2026-11-16: „Nowakowski” na `/persons` konta testowego trafia Jana Nowaka (wariant z importu, ta sama ścieżka zapytania) — 6986784

### Phase 2: Import zapisuje warianty

#### Automated

- [x] 2.1 Testy importu przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ImportPlanTest.php tests/Feature/ImportApplyTest.php tests/Feature/ImportResearchCommandTest.php tests/Unit/SurnameVariantsTest.php` — b88afb5
- [x] 2.2 Pełny zestaw Pest przechodzi: `vendor/bin/sail artisan test --compact` — b88afb5
- [x] 2.3 Pint i Larastan bez nowych błędów: `vendor/bin/sail bin pint --dirty --format agent` i `vendor/bin/sail composer types:check` — b88afb5

#### Manual

- [x] 2.4 Import wymyślonego repo (`tests/Fixtures/research-repo`) na osobnym koncie testowym: podsumowanie pokazuje „Warianty nazwiska przy osobach”, a notatka Jana Nowaka nie ma linii wariantów — ręcznie 2026-11-16: konto s09-test, linia bez wcięcia, notatka O-0001 bez wariantów — b88afb5
- [x] 2.5 Drugi przebieg bez zmian w repo kończy się „Nic do zapisania.” — ręcznie 2026-11-16 — b88afb5

### Phase 3: Warianty widoczne przy osobie i na liście

#### Automated

- [x] 3.1 Testy JS przechodzą (w tym `view-tokens.test.ts`): `vendor/bin/sail npm run test:js` — 32f97d0
- [x] 3.2 Format, lint i typy przechodzą: `vendor/bin/sail npm run check` i `vendor/bin/sail npm run types:check` — 32f97d0
- [x] 3.3 Build przechodzi: `vendor/bin/sail npm run build` — 32f97d0
- [x] 3.4 Pest przechodzi: `vendor/bin/sail artisan test --compact` — 32f97d0
- [x] 3.5 Pint i Larastan bez nowych błędów: `vendor/bin/sail bin pint --dirty --format agent` i `vendor/bin/sail composer types:check` — 32f97d0

#### Manual

- [x] 3.6 Na koncie testowym po imporcie wymyślonego repo: „Nowakowski” na `/persons` pokazuje Jana Nowaka z dopiskiem „zapis: Nowakowski” w widoku lista i tabela — ręcznie 2026-11-16 (lista i tabela) — 32f97d0
- [x] 3.7 „Nowak” pokazuje Jana Nowaka bez dopisku, a lista bez wyszukiwania wygląda jak przed zmianą — ręcznie 2026-11-16 — 32f97d0
- [x] 3.8 Panel Jana Nowaka pokazuje w „Dane osobowe” pole „Warianty nazwiska: Nowakowski”, a zapis sekcji „Dane osobowe” go nie usuwa — ręcznie 2026-11-16 (PUT /persons/111 → 303, pole zostaje) — 32f97d0
