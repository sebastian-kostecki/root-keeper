---
date: 2026-10-05T08:45:38+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: 1e275fd933bad7170461313208c0499dc0d270ad
branch: develop
repository: root-keeper
topic: "S-13 maiden-name-and-age — na czym zbudować nazwisko rodowe (FR-020) i wiek osoby (FR-024)"
tags: [research, codebase, persons, people-table, display-name, person-search, partial-date, events, age]
status: complete
last_updated: 2026-10-05
last_updated_by: Claude (Opus 5.5)
---

# Research: nazwisko rodowe i wiek osoby (S-13)

**Date**: 2026-10-05T08:45:38+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: 1e275fd933bad7170461313208c0499dc0d270ad
**Branch**: develop
**Repository**: root-keeper

## Research Question

Co w obecnym kodzie dotyka S-13 (`roadmap.md:277-290`): FR-020 — autor zapisuje nazwisko rodowe, osoba wyświetla się „z d. …”, gdy znane są oba nazwiska, i jest wyszukiwana po obu (`prd.md:158-160`); FR-024 — autor widzi wiek: ile przeżyła (gdy zna datę zgonu) albo ile ma (tylko gdy wynik < ok. 110 lat) (`prd.md:170-172`, `prd.md:227`). Gdzie są dane, gdzie składa się nazwę, gdzie się szuka, jak zapisane są daty i co trzeba zmienić, żeby niczego nie zgubić.

## Summary

- **Nazwisko rodowe nie istnieje nigdzie w kodzie** (grep `maiden|rodow|birth_name|family_name|z d\.` po `app`, `database`, `resources`, `routes`, `tests`, `lang`, `config` — trafienia tylko w `context/`). Tabela nazywa się `people` (`database/migrations/2026_09_23_045722_create_people_table.php:15-25`); wzór dodania kolumny: `2026_09_28_064501_add_gender_to_people_table.php:15`. Nowa kolumna nullable nie przepisuje wierszy, więc reguła `<Name>MigrationTest.php` z CLAUDE.md jej nie obejmuje.
- **Nazwa wyświetlana powstaje w jednym miejscu**: `Person::displayName()` (`app/Models/Person.php:102-105`) = `first_name ?? 'NN'` + `last_name ?? 'NN'`. W przeszukanym `resources/js` (poza wygenerowanymi `actions/routes/wayfinder`) żaden komponent nie skleja imienia z nazwiskiem — renderują serwerowe `display_name`. Zmiana tej metody przenosi „z d. …” naraz do emiterów w 8 plikach PHP (lista w §2).
- **Wyszukiwanie po nazwie ma dwie ścieżki**: serwerowa (`PersonController.php:40-50`, każde słowo musi pasować do `first_name` lub `last_name`) i kliencka w pickerach drzewa (`person-candidate-list.tsx:28-33`, filtruje po `display_name`). Pierwsza wymaga dopisania `orWhereLike` dla nowej kolumny; druga zadziała sama, jeśli `display_name` zawiera nazwisko rodowe.
- **Zapis jest pełnym zastąpieniem** (`app/Actions/Persons/UpdatePerson.php:51-57`): klucz nieobecny w żądaniu → `null`. Nowe pole trzeba dodać na całej ścieżce (request, `CreatePerson`, `UpdatePerson`, `PersonFormResource`, `PersonFormData`, `person-payload.ts`), inaczej zapis sekcji urodzenia/zgonu wyczyści nazwisko rodowe.
- **Daty to wiersze `events`** (typ `birth`/`death`), z `date_year`/`date_month`/`date_day` nullable i `date_is_approximate` (`create_events_table.php:48-58`). Precyzja wynika z tego, które części są `null`; brak „przed/po”. Wartość obiektowa `app/Support/PartialDate.php` istnieje po stronie PHP, `resources/js/lib/partial-date.ts` po stronie TS.
- **Zgon bez daty jest stanem dopuszczalnym**: osoba „nie żyje”, gdy istnieje zdarzenie zgonu, także z samymi `null` (`CreatePerson.php:56-58`, `UpdatePerson.php:59-60`, `PersonFormResource.php:46`). Wniosek: „ile ma” ma sens tylko przy braku zdarzenia zgonu; zgon bez roku → brak wieku.
- **Obliczania wieku nie ma** w kodzie (grep `diffInYears|wiek|age` po `app`, `resources/js`, `tests` — brak trafień w logice). Sposób liczenia przy datach niepełnych/„około” jest jawnie otwarty dla `/10x-plan` (`roadmap.md:286`).

## Detailed Findings

### 1. Schemat i model `Person`

- Kolumny `people`: `id`, `tree_id`, `first_name` nullable, `last_name` nullable, `residence`, `religion`, timestamps (`create_people_table.php:15-25`); `gender` dodany później z `after('last_name')` (`add_gender_to_people_table.php:15`). Jedyny indeks: `(tree_id, last_name, first_name)` (`create_people_table.php:23`).
- `Person` ma `#[Fillable([...])]` z sześcioma polami (`app/Models/Person.php:27`) i docblock `@property` (`:17-25`) — oba wymagają dopisania kolumny.
- `birth()` / `death()` czytają z załadowanej relacji `events` przez `firstWhere('type', ...)` (`Person.php:92-100`) — wiek liczony z tych metod wymaga załadowanego `events`, inaczej lazy load.

### 2. Gdzie powstaje i gdzie trafia `display_name`

Emitery PHP — 8 plików, każdy woła `displayName()`:
- `app/Http/Resources/PersonResource.php:42`, `PersonFormResource.php:40-42`, `RelativeResource.php:34`, `SheetPersonResource.php:42`, `MarriageResource.php:48`
- `app/Actions/Trees/ListPersonCandidates.php:29`
- `app/Actions/Trees/BuildFamilySheet.php:109` (`partner_name`), `:284` (`add_child.parents[].display_name`), `:463-464` (`parent_of.display_name`)
- `app/Http/Controllers/SourceController.php:128` (`confirms[].person.display_name`)

Toasty nie zawierają nazw (np. `PersonController.php:101,113,127`).

Konsumenci po stronie TS renderują tylko `display_name` (m.in. `person-modal-header.tsx:48,54`, `persons-list.tsx:62,70`, `persons-table.tsx:144,150,183`, `family-tab.tsx:92,96`, `sheet-person-card.tsx:32,75`, `union-node.tsx:100,136,162`, `delete-person-dialog.tsx:91`, `pages/tree/show.tsx:39,78,82`). Jedyny kliencki odpowiednik sklejania to helper testowy `resources/js/lib/persons-index.test.ts:36` (bez `NN`, inny niż serwer).

Pułapki przy dopisaniu „z d. …” do `display_name`:
- **Inicjały**: `getInitials()` (`resources/js/hooks/use-initials.tsx:9-27`) bierze pierwszy i ostatni token zaczynający się literą/cyfrą. Dla „Anna Kowalska z d. Nowak” da „AN” zamiast „AK”. Używany przez `PersonAvatar` (`person-avatar.tsx:44`) i `family-tab.tsx:92`; ma test `resources/js/hooks/use-initials.test.ts`.
- **E2E**: specy znajdują osoby po dokładnym `display_name` (`tree-slot-parent.spec.ts:61`, `tree-slot-child.spec.ts:82`, `tree-slot-spouse.spec.ts:60`) i dialog po nazwie (`tests/e2e/support/navigation.ts:84-87`). Osoby E2E nie mają nazwiska rodowego, więc dopisek warunkowy (tylko gdy oba nazwiska są) zostawia je zielone.
- **Etykieta pola**: trzy specy wołają `getByLabel('Nazwisko')` w dialogach dodawania (`tree-slot-spouse.spec.ts:44`, `tree-slot-child.spec.ts:77`, `tree-slot-parent.spec.ts:54`). Domyślnie `getByLabel` dopasowuje podciąg, więc pole „Nazwisko rodowe” w tym samym formularzu (`PersonalFields` jest wspólny, patrz §3) da dwa trafienia i błąd strict mode. Zmiana lokatora na `{ exact: true }` to zmiana selektora, nie asercji (dozwolone wg CLAUDE.md).

### 3. Ścieżka zapisu osoby

- Walidacja: `PersonStoreRequest.php:30-31` — `first_name`/`last_name` nullable z wzajemnym `required_without`; polskie etykiety w `attributes()` (`:50-51`) i `lang/pl/validation.php:201,218`. Dziedziczą z niego `PersonUpdateRequest`, `PersonSpouseStoreRequest`, `PersonChildStoreRequest`, `PersonParentStoreRequest` — reguła dopisana w `PersonStoreRequest` obejmie te 5 requestów.
- Akcje mają jawne listy pól: `CreatePerson.php:47-48`, `UpdatePerson.php:51-57`. `CreateSpouseForPerson`, `CreateChildForParents`, `CreateParentForPerson` delegują do `CreatePerson::handle` z `$request->validated()`.
- Front: pola imienia i nazwiska są w jednym komponencie `PersonalFields` (`resources/js/components/persons/person-section-fields.tsx:43-151`, nazwisko `:68-80`), używanym przez `person-create-dialog.tsx:236`, `person-form-fields.tsx:271` (dialogi z drzewa przez `tree/pick-or-create-person-dialog.tsx`) i edycję w `overview-tab.tsx`.
- Edycja sekcjami: `overview-tab.tsx:41-47` `PERSONAL_KEYS` (decyduje, gdzie renderują się błędy serwera), widok odczytu `overview-tab.tsx:262-263` (Imię/Nazwisko). Payload: `resources/js/lib/person-payload.ts` — `PersonPayload` (`:17-26`), `personFormToPayload` (`:70-86`), `mergeSectionIntoPayload` gałąź `personal` (`:99-104`); typ `PersonFormData` (`resources/js/types/person.ts:102-113`).
- Ponieważ `UpdatePerson` zastępuje całość, każde miejsce budujące payload PUT musi nieść nowe pole. W testach dotyczy to `tests/Feature/WriteTargetsTest.php:274` (lista `array_flip([...])` sześciu kluczy z propsa `personForm`): bez dopisania nowego klucza test nadal przejdzie (osoba ze sceny nie ma nazwiska rodowego), ale przestanie odzwierciedlać prawdziwy formularz.

### 4. Wyszukiwanie i sortowanie

- Serwer (lista osób): `PersonController.php:40-50` — `q` dzielone po białych znakach, escapowanie `\ % _`, dla każdego słowa grupa `whereLike('first_name')->orWhereLike('last_name')`; zakres `$tree->people()`. Reguła z archiwum: „każde słowo zawarte w imieniu lub nazwisku” (`context/archive/2026-09-25-person-search/plan.md:7`). Testy wyszukiwania: `tests/Feature/PersonsTest.php:44-182` (wzorzec: `:44-67`). Placeholder „Szukaj po imieniu lub nazwisku” (`resources/js/pages/persons/index.tsx:149`).
- Klient (pickery na drzewie): `matchesFilter` po `display_name` (`person-candidate-list.tsx:28-33`); kandydaci z `ListPersonCandidates.php:17-33` niosą tylko `display_name`, `birth_date`, `gender`. Ten sam placeholder (`person-candidate-list.tsx:98`).
- Innych zapytań filtrujących osoby po nazwie nie znaleziono (sprawdzono `routes/web.php`, kontrolery, `resources/js/lib/*`); `SourceController::normalizeSearchQuery` dotyczy źródeł.
- W czterech sprawdzonych miejscach sortowanie idzie po `last_name` (`PersonController.php:51-54`, `ListPersonCandidates.php:21-24`, `app/Support/PersonOrdering.php:57-59`, `resources/js/lib/persons-index.ts:53-76,150-170`). Nazwisko rodowe jako pole drugorzędne nie wymaga tu zmian.
- E2E sprząta po sufiksie przez `/persons?q=<suffix>` i `display_name.includes(suffix)` (`tests/e2e/support/api.ts:295-305`); sufiks jest w `last_name`, więc dopisanie kolumny do wyszukiwania tego nie zmienia.

### 5. Daty urodzenia i zgonu

- Zapis: `events.type` string (`app/Enums/EventType.php:5-9`: `Birth`, `Death`, `Marriage`), `date_year` smallint nullable, `date_month`/`date_day` tinyint nullable, `date_is_approximate` bool (`create_events_table.php:48-58`); połączenie z osobą przez `event_participants` (`create_event_participants_table.php:82-90`). Brak enumu precyzji po stronie PHP; TS ma `DatePrecision` (`resources/js/lib/partial-date.ts:8`).
- Walidacja (`app/Concerns/PartialDateValidationRules.php:17-28`): rok 1000…bieżący (`Date::now()->year`), rok wymagany przy miesiącu i przy `is_approximate`, miesiąc wymagany przy dniu; `validateFullDate` odrzuca niemożliwe daty (`:33-50`). Nie ma reguły „zgon nie przed urodzeniem” w `PersonStoreRequest`.
- Wejście w UI: jedno pole tekstowe (`resources/js/components/persons/partial-date-input.tsx:32-122`, placeholder „np. 1948, 03.1948, ok. 1948”) parsowane przez `parsePartialDate` (`partial-date.ts:96-159`): rok, miesiąc+rok, pełna data; prefiks `około|ok|ca|~` na każdym poziomie precyzji. Pola zgonu tylko przy zaznaczonym „Osoba nie żyje” (`person-section-fields.tsx:178-221`).
- Trzy stany zgonu: brak zdarzenia (nie wiadomo, czy żyje), zdarzenie z `date_year = null` („data nieznana”, `persons-index.ts:198`, `persons-table.tsx:79`), zdarzenie z datą. Zdarzenie bez roku powstaje tylko, gdy wszystkie trzy części są `null` (rok `required_with` miesiąca).
- Formatowanie: PHP `PartialDate::format()` / `formatYear()` z prefiksem „ok. ” (`app/Support/PartialDate.php:19-45`); TS `formatPartialDate(parts, 'short'|'long')` („około 1948”, `partial-date.ts:165-203`), `formatLifespan` na gotowych stringach (`resources/js/lib/lifespan.ts:5-24`, bez testu).

### 6. Gdzie dziś renderuje się daty/żywot (kandydaci na miejsce wieku)

| Miejsce | Plik | Dane na wejściu |
|---|---|---|
| Karta na drzewie | `components/tree/sheet-person-card.tsx:34,172-191` | `lifespan` string z `SheetPersonResource.php:29-47` (budowany w PHP) |
| Nagłówek podglądu osoby | `components/persons/person-modal-header.tsx:40,56-60` | `PersonPreview` z `birth`/`death` (`EventResource.php:35-47`: `date` + `date_parts`) |
| Zakładka Przegląd, fakt | `components/persons/overview-tab.tsx:94` | `personForm.birth`/`death` (części daty) |
| Zakładka Rodzina | `components/persons/family-tab.tsx:84,98-101` | `birth_date`/`death_date` jako stringi (`RelativeResource.php:36-37`) — bez części |
| Lista / tabela osób | `persons-list.tsx:53-80`, `persons-table.tsx:72-104,158,161` | `IndexPersonEvent` |
| Picker osoby | `person-candidate-list.tsx:120-122` | tylko `birth_date` |

Wiek w miejscach, które dziś dostają tylko stringi (Rodzina, karta, picker), wymaga dodania do zasobu części daty albo gotowego wieku.

### 7. „Dziś” i strefa czasowa

`config/app.php:68` ma na sztywno `'timezone' => 'UTC'`; obraz Dockera `TZ=UTC` (`docker/8.5/Dockerfile:14`). W sprawdzonym `app/` serwer używa „dziś” do górnej granicy roku w walidacji (`PartialDateValidationRules.php:19`, `SourceDescriptionValidationRules.php:20`); innej logiki zależnej od daty nie znaleziono. Klient nie dostaje bieżącej daty w propsach. Testy nie zamrażają czasu (brak `travelTo`/`freezeTime`/`setTestNow` w `tests/`). Wiek „ile ma” będzie pierwszą logiką zależną od dzisiejszej daty — test musi ustawić czas.

### 8. Testy, których dotknie zmiana

- Pest Feature: wyszukiwanie i zapis w `tests/Feature/PersonsTest.php` (wyszukiwanie `:44-182`, zapis z jednym nazwiskiem `:476`, brak nazwisk `:501`, aktualizacja pól `:755`, polskie błędy `:714`, props `personForm` `:725`); `tests/Feature/TreeTest.php:165,168,219,321` porównują z `->displayName()` (pozostaną spójne, bo porównują z metodą); żywot karty `TreeTest.php:443-473`.
- Pest Unit bez bootu frameworka: wzorzec `tests/Unit/PartialDateTest.php`; `tests/Pest.php` wiąże `TestCase`/`RefreshDatabase` tylko z `Feature`.
- Helpery: `attachBirth($person, $y, $m, $d)` (bez `approximate`), `attachDeath($person, $year, $approximate)` (bez miesiąca/dnia); `EventFactory` ma stany `birth()`, `death()`, `approximate()`.
- Vitest (`vp test`, include `resources/js/**/*.test.ts` w `vite.config.ts`): import z `'vite-plus/test'`; wzorce `partial-date.test.ts`, `persons-index.test.ts`, `use-initials.test.ts`.
- `databaseRows()` (`tests/Pest.php:150`) i `ownershipSnapshot()` (`:117`) obejmą nową kolumnę automatycznie — bez zmian w helperach.

## Code References

- `app/Models/Person.php:27` — `#[Fillable]`; `:92-100` — `birth()`/`death()`; `:102-105` — `displayName()`
- `database/migrations/2026_09_28_064501_add_gender_to_people_table.php:15` — wzorzec nowej kolumny w `people`
- `app/Http/Requests/PersonStoreRequest.php:24-40,50-51` — reguły nazw i dat, etykiety
- `app/Actions/Persons/CreatePerson.php:47-48,56-58` — zapis nazw, zgon przy `is_deceased`
- `app/Actions/Persons/UpdatePerson.php:51-57` — pełne zastąpienie pól osoby
- `app/Http/Controllers/PersonController.php:40-54` — wyszukiwanie i sortowanie
- `resources/js/components/persons/person-candidate-list.tsx:28-33` — filtr pickera po `display_name`
- `resources/js/components/persons/person-section-fields.tsx:43-151,178-221` — pola osobowe i zgonu
- `resources/js/lib/person-payload.ts:17-26,70-118` — payload pełnego zastąpienia
- `resources/js/components/persons/overview-tab.tsx:41-47,262-263` — `PERSONAL_KEYS`, widok Dane osobowe
- `resources/js/hooks/use-initials.tsx:9-27` — inicjały z pierwszego i ostatniego tokenu
- `database/migrations/2026_09_23_045723_create_events_table.php:48-58` — kolumny daty
- `app/Support/PartialDate.php:19-45` — format daty, prefiks „ok. ”
- `app/Concerns/PartialDateValidationRules.php:17-50` — walidacja daty częściowej
- `resources/js/lib/partial-date.ts:96-203` — parser i formatter daty po stronie TS
- `app/Http/Resources/SheetPersonResource.php:29-47` — żywot karty liczony w PHP
- `app/Http/Resources/RelativeResource.php:36-37` — krewni dostają daty tylko jako stringi
- `tests/Feature/WriteTargetsTest.php:257-300` — edycja osoby, jawna lista pól payloadu (`:274`)
- `tests/e2e/tree-slot-{spouse,child,parent}.spec.ts:44/77/54` — `getByLabel('Nazwisko')`

## Architecture Insights

- **Jedno źródło nazwy**: w sprawdzonych emiterach (§2) prezentacja nazwy przechodzi przez `Person::displayName()` → zasoby → `display_name`. Reguła „z d. tylko gdy oba nazwiska” mieści się w tej jednej metodzie i automatycznie trafia do drzewa, list, pickerów, dialogów i tytułów (dziedziczy to też panel osoby S-06, zgodnie z ryzykiem w `roadmap.md:287`).
- **Pełne zastąpienie przy zapisie**: dodanie pola to zawsze komplet — request → akcja → `PersonFormResource` → `PersonFormData` → `person-payload.ts` (obie funkcje) → `PERSONAL_KEYS`.
- **Daty częściowe są pierwszoklasowe**: precyzja i „około” są w danych; istniejący precedens prezentacji niepewności to prefiks „ok. ”/„około”.
- **Żywot liczony w dwóch miejscach**: PHP dla karty (`SheetPersonResource`), TS dla podglądu (`lifespan.ts`). Wiek może pójść jedną z tych dróg; serwer ma `PartialDate` wszędzie tam, gdzie relacja `events` jest załadowana, klient dostaje części daty tylko w `PersonPreview` (`EventResource.date_parts`) i `personForm`.

## Historical Context (from prior changes)

- `context/foundation/prd.md:159-160` — FR-020, rozstrzygnięcie: jedno znane nazwisko wpisuje się w pole główne, „z d.” tylko przy obu. Wspierane przez kod: brak przeszkód (pole nie istnieje).
- `context/foundation/prd.md:240`, `roadmap.md:369` — dokładnie jedno nazwisko rodowe; kolejne nazwiska po mężach w notatce.
- `context/foundation/shape-notes.md:89` — notatka właściciela: „nazwisko panieńskie (zastanowić się jak to lepiej zrobić)”; nazwa kolumny i format niezadecydowane.
- `context/foundation/prd.md:171-172` — kontrargument „brak daty zgonu nie znaczy, że osoba żyje — ur. 1850 nie „ma 176 lat””; rozstrzygnięcie: „ile ma” tylko poniżej progu ok. 110 lat.
- `context/foundation/roadmap.md:286` — otwarte: jak liczyć wiek przy datach niepełnych lub „około”; właściciel `/10x-plan`, nieblokujące.
- `context/archive/2026-09-25-person-search/plan.md:7` — reguła wyszukiwania słowo-po-słowie w imieniu lub nazwisku; wielkość liter ignorowana, polskie znaki nie są składane. Nadal zgodne z kodem (`PersonController.php:40-50`).
- `context/archive/2026-09-28-graphical-tree-view/plan.md:151-153,358-360` — reguły żywotu karty i `PartialDate::formatYear()`. Zgodne z `SheetPersonResource.php:29-47`.
- `context/archive/2026-09-23-account-tree-and-first-person/plan.md:288,296` — reguły daty częściowej (rok 1000…bieżący, „około” tylko z rokiem). Zgodne z `PartialDateValidationRules.php:17-28`.
- `context/archive/2026-09-28-tree-spouse-existing-or-new/plan.md:56` — nowy małżonek bez podpowiedzi nazwiska, bo „maiden names differ”.
- `context/archive/2026-10-04-side-panel-shell/plan.md:36` — podgląd osoby nadal w modalu do S-06; S-13 zmienia dane i format, nie powłokę.

## Related Research

- `context/archive/2026-09-29-person-modal/research.md` — wcześniejsza analiza żywotu w nagłówku i zakładce Rodzina (`:122-128`) oraz gramatyki dat (`:189,298`).
- `context/archive/2026-09-28-graphical-tree-view/research.md` — tło kart drzewa.

## Open Questions

Wybory produktowe/techniczne dla `/10x-plan` (nie blokują badań):

1. **Wiek przy datach niepełnych i „około”** (`roadmap.md:286`). Dane pozwalają rozróżnić: obie daty pełne → wiek dokładny; którakolwiek bez dnia/miesiąca lub z `is_approximate` → wiek przybliżony (np. „ok. 72 lata” albo „71–72 lata”); brak roku urodzenia lub (przy zgonie) roku zgonu → brak wieku. Który format i od jakiej niepewności — decyzja.
2. **Gdzie pokazać wiek**: nagłówek podglądu / Przegląd (części daty już są), zakładka Rodzina i karta drzewa (trzeba rozszerzyć `RelativeResource` / `SheetPersonResource`). PRD mówi tylko „zobaczyć wiek osoby”.
3. **Serwer czy klient**: serwer ma `PartialDate` przy każdym zasobie z załadowanym `events`, ale strefa to UTC (`config/app.php:68`); klient zna lokalne „dziś”, ale dla krewnych i kart nie ma części daty. Różnica stref zmienia wynik najwyżej w dniu urodzin.
4. **Odmiana „rok/lata/lat”**: w kodzie nie znaleziono istniejącego helpera liczby mnogiej dla polskich lat (nie przeszukano `lang/` pod tym kątem).
5. **Nazwisko rodowe bez nazwiska głównego**: PRD każe wpisać jedyne nazwisko w pole główne; czy walidacja ma odrzucić samo nazwisko rodowe, czy wyświetlać „NN z d. …”? Czy ukrywać „z d.”, gdy nazwisko rodowe = nazwisko?
6. **Inicjały**: przy „z d. …” w `display_name` trzeba albo poprawić `getInitials`, albo przekazywać do awatara imię i nazwisko osobno.
7. **Etykieta pola**: „Nazwisko rodowe” koliduje z `getByLabel('Nazwisko')` w trzech specach E2E — zmiana lokatorów na `exact` lub inna etykieta.
8. **Wyszukiwanie**: dopisać kolumnę w `PersonController` i zaktualizować placeholder („po imieniu lub nazwisku”) — czy tekst ma wspominać nazwisko rodowe.

Niezweryfikowane: zachowanie `whereLike` dla znaków spoza ASCII na PostgreSQL vs sqlite (istniejący test `PersonsTest.php:83` utrwala bieżące oczekiwanie); pełna treść `family-sheet.tsx`, `union-node.tsx`, `person-preview-dialog.tsx` poza liniami wyświetlania.
