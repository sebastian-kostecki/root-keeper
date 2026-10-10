# Zakładki wydarzeń i notatki w panelu osoby (S-14) Implementation Plan

## Overview

Panel osoby dostaje dwie nowe zakładki na końcu listy: **Wydarzenia** i **Notatka**. Wydarzenia to oś życia osoby, którą serwer składa gotową i posortowaną. Obejmuje własne urodzenie, małżeństwa, zgon i pochowanie, a do tego urodzenia i zgony dzieci oraz zgony małżonków. Lista jest uporządkowana po dacie, a wydarzenia bez roku idą na koniec. Notatka to jedno pole tekstowe o osobie, edytowane w karcie z ołówkiem i zapisywane osobnym endpointem. To wycinek S-14 z roadmapy (FR-021, FR-023).

## Current State Analysis

- **Host zakładek istnieje.**
  - `PersonModal` renderuje Radix `Tabs` z zakładkami `przeglad` / `rodzina` / `zrodla` (`resources/js/components/persons/person-modal.tsx:52-86`).
  - Zmiana zakładki idzie przez `requestExit` (`:54`), a przy zmianie osoby zakładka wraca do `przeglad` (`:33-36`).
  - Licznik `TabCount` znika przy 0 (`:14-24`).
  - `TabsList` ma klasy `w-full sm:w-fit` (`:58`). Panel `standard` przy 1366 px ma ok. 410 px (`resources/js/components/side-panel.tsx:213-215`).
- **Dane panelu to współdzielony prop `personPreview`.**
  - Buduje go `BuildPersonPreview::handle` (`app/Actions/Persons/BuildPersonPreview.php:25-36`).
  - Eager-load obejmuje `events.locality`, `children.events` i `marriages.participants.events`.
  - **Brakuje** `locality` dla wydarzeń dzieci i małżonków, więc miejsce w nowej liście dałoby N+1. Projekt nie włącza `preventLazyLoading`, więc N+1 nie rzuci wyjątku sam z siebie.
- **`PersonResource` obsługuje też listę osób** (`app/Http/Controllers/PersonController.php:35`). Pola tylko dla podglądu nie mogą w nim trafić do indeksu.
- **Krewni w payloadzie nie mają dat strukturalnych.** `RelativeResource` (`app/Http/Resources/RelativeResource.php:30-37`) i `MarriageResource.spouse` (`app/Http/Resources/MarriageResource.php:43-52`) niosą tylko tekstowe `birth_date` / `death_date`.
- **Model wydarzeń.**
  - Tabela `events`: `type` ∈ {birth, death, marriage, burial} (`app/Enums/EventType.php:7-10`), data `date_year` / `date_month` / `date_day` (nullable) + `date_is_approximate`, uczestnicy przez `event_participants`.
  - Małżeństwo to jeden wiersz wspólny dla obu małżonków. Pochowanie ma tylko miejsce.
  - Akcesory `Person::birth()` / `death()` / `burial()` czytają załadowaną kolekcję `events` (`app/Models/Person.php:117-127`).
- **Puste urodzenia istnieją masowo.** `UpdatePerson::putEvent` zawsze tworzy lub aktualizuje wiersz urodzenia, także bez daty i miejsca (`app/Actions/Persons/UpdatePerson.php:63,160-185`). Przegląd ukrywa taki wiersz (`hasBirth` w `resources/js/components/persons/overview-tab.tsx`).
- **Sortowanie „bez roku na końcu”** już istnieje: `PersonOrdering::marriageSortKey` = `[rok null ? 1 : 0, rok, miesiąc ?? 0, dzień ?? 0, id]` (`app/Support/PersonOrdering.php:120-132`). Sam rok sortuje się przed pełną datą w tym samym roku.
- **Notatki osoby nie ma.** Brak kolumny w `people` i pola w typach.
  - Wzorzec walidacji: `['nullable', 'string', 'max:5000']` z etykietą `notatka` (`app/Concerns/SourceDescriptionValidationRules.php:28,66`).
  - Komponent `resources/js/components/ui/textarea.tsx` już istnieje.
- **Edycja per sekcja.**
  - `SectionCard` (`resources/js/components/persons/section-card.tsx`) przełącza widok i formularz, rejestruje submit dla paska strażnika i liczy dirty z serializacji formularza.
  - Przy otwarciu edycji ustawia fokus tylko na `input:not([type="hidden"]), [role="checkbox"]`, więc textarea fokusu nie dostanie.
  - `SectionId` to zamknięta unia, a `labelFor` zwraca miejscownik do paska „Masz niezapisane zmiany w …” (`resources/js/components/persons/section-editor.tsx:14-19,49-70`).
  - `useSectionSave.save` przyjmuje tylko `PersonPayload | MarriageSavePayload` (`resources/js/hooks/use-section-save.ts:26-30`).
- **Wzorzec małego endpointu na osobie:** `TreeRootPersonController::update` z `Gate::authorize('update', $person)`, akcją, toastem i `back()` (`app/Http/Controllers/TreeRootPersonController.php`). Polityka `PersonPolicy::update` już istnieje.
- **Granice.**
  - `OwnershipBoundariesTest` wymaga przypadku dla każdej trasy kontrolera z id w adresie (`tests/Feature/OwnershipBoundariesTest.php:162`).
  - `WriteTargetsTest` sprawdza diff wierszy po zapisie z panelu (`tests/Feature/WriteTargetsTest.php:259-302`).

## Desired End State

- Panel osoby ma zakładki w kolejności **Przegląd · Rodzina · Źródła · Wydarzenia · Notatka**. Przy zmianie zakładki obowiązuje dotychczasowy strażnik niezapisanych zmian. Gdy pięć triggerów się nie mieści, lista zakładek przewija się poziomo, bez łamania układu.
- **Wydarzenia** pokazują jedną listę:
  - własne urodzenie (jeśli ma datę lub miejsce), każde małżeństwo raz, własny zgon i pochowanie;
  - urodzenie dziecka (jeśli ma datę lub miejsce) i zgon dziecka;
  - zgon małżonka.
- **Porządek listy.** Najpierw wydarzenia ze znanym rokiem, rosnąco według rok → miesiąc → dzień (sam rok przed pełną datą w tym roku). Przy równym kluczu daty decyduje stała kolejność typów. Wydarzenia bez roku idą na koniec w tej samej kolejności typów.
- **Wiersz pokazuje** etykietę, datę (lub „data nieznana”; pochowanie nie ma daty), miejsce i — dla wierszy z krewnym — imię krewnego jako `PersonPreviewLink`, który podmienia panel.
- **Etykiety zależą od płci krewnego.**
  - Dziecko: „Urodzenie syna” / „Urodzenie córki” / „Urodzenie dziecka”, „Zgon syna” / „Zgon córki” / „Zgon dziecka”.
  - Małżonek: „Zgon męża” / „Zgon żony” / „Zgon małżonka”.
  - Własne: „Urodzenie”, „Małżeństwo” (z imieniem małżonka), „Zgon”, „Pochowanie”.
- **Notatka** to karta z ołówkiem:
  - podgląd zachowuje akapity, a pusta notatka pokazuje stan pusty;
  - edycja to textarea (maks. 5000 znaków) z Zapisz / Anuluj;
  - niezapisana notatka blokuje zamknięcie panelu i zmianę zakładki paskiem „Masz niezapisane zmiany w notatce”;
  - przy niepustej notatce trigger „Notatka” ma kropkę.
- `PUT persons/{person}/note` zapisuje notatkę tylko tej osoby. Cudza osoba dostaje 404. Pełny zapis osoby (`PUT persons/{person}`) notatki nie zmienia.
- `persons.index` nie dostaje pól `note` ani `timeline`.

Weryfikacja: testy Pest z faz 1–2, `check`, `types:check`, `build`, ręczny przegląd panelu przy 1366 px i 375 px oraz spec E2E z fazy 4.

### Key Discoveries:

- `UpdatePerson` zapisuje pola osoby jawną listą (`app/Actions/Persons/UpdatePerson.php:55-61`). Kolumna `note` poza tą listą jest więc bezpieczna przy pełnym zapisie osoby — pułapka z nazwiskiem rodowym (`context/archive/2026-10-05-maiden-name-and-age/reviews/impl-review.md` F1) tu nie zachodzi, o ile nikt nie doda `note` do `UpdatePerson`.
- Małżeństwo jest jednym wierszem `events` dla obu małżonków. Lista bierze je ze strony osoby, a ze strony małżonka bierze tylko jego zgon.
- Dzieci nie są przypisane do małżeństwa (`parent_child_relations` bez `marriage_id`), więc zbiór dzieci to `person.children`.
- Mapowanie „podsumowania osoby” ma już trzy kopie (F4 z `context/archive/2026-10-09-marriage-side-panel/reviews/`). Wiersz osi niesie tylko `{id, display_name}` krewnego, nie czwarte podsumowanie.

## What We're NOT Doing

- Zamieszkania (`residences`) nie trafiają na oś. Nie trafia też urodzenie małżonka, pochowanie dzieci i małżonków ani wydarzenia rodziców.
- Wiersze osi nie pokazują statusu potwierdzenia źródłem i nie dają edycji. Wydarzenia edytuje się w Przeglądzie i w panelu małżeństwa.
- Wiersz małżeństwa nie linkuje do panelu małżeństwa. Linkiem jest tylko imię małżonka.
- Zakładka „Wydarzenia” nie ma licznika — oszczędzamy szerokość paska zakładek.
- Notatka nie trafia do pełnego payloadu `PUT persons/{person}` ani do `PersonFormResource` / `personFormToPayload`.
- Brak wielu notatek, historii i „dziennika badań” (odrzucone w FR-023). Limit nie przekracza 5000 znaków.
- Bez przeliczania wieku i dat na osi; data to `PartialDate::format()` z prefiksem „ok.”, a flaga „około” nie wpływa na sortowanie.
- Brak nowej zależności frontendowej.
- Remis między wydarzeniami tego samego typu z identycznym kluczem daty (np. bliźnięta z samym rokiem) rozstrzyga id wydarzenia, czyli kolejność wprowadzenia — świadomie, bez dodatkowej reguły.

## Implementation Approach

Backend najpierw, każdy kawałek pokryty Pestem; potem UI, na końcu E2E.

- **Notatka (faza 1)** to osobna ścieżka zapisu zgodna z konwencją: Form Request → Action → toast + `back()`. Dzięki temu nie dotyka kruchego „pełnego formularza + nałożonej sekcji”.
- **Oś (faza 2)** buduje serwer, tak jak porządek krewnych i małżeństw w `PersonOrdering`. Nowa klasa wsparcia zbiera wpisy z już załadowanych relacji, a zasób serializuje je razem z polską etykietą. Mapa etykiet zależnych od płci ma jedno miejsce i test Pest.
- **Pola tylko dla podglądu.** `note` i `timeline` dokłada `BuildPersonPreview` do tablicy `person`, więc indeks osób zostaje bez zmian.
- **Frontend (faza 3)** tylko renderuje gotowe dane i wpina kartę notatki w istniejący `SectionEditor`.

## Critical Implementation Details

**Klucz sortowania osi.** Klucz to `[rok === null ? 1 : 0, rok ?? 0, miesiąc ?? 0, dzień ?? 0, ranga typu, id wydarzenia]`. Ranga typu: własne urodzenie 0, małżeństwo 1, urodzenie dziecka 2, zgon dziecka 3, zgon małżonka 4, własny zgon 5, pochowanie 6. Ranga stoi **po** dniu, więc działa tylko przy identycznym kluczu daty. Przykład: ślub „1880” i urodzenie syna „1880” → ślub pierwszy, a ślub „1880” wypada przed urodzeniem syna „05.03.1880”.

**Pochowanie dziedziczy klucz daty własnego zgonu.** Wiersz pochowania nie ma daty (tylko miejsce), więc sortuje się po rok / miesiąc / dzień własnego zgonu z rangą 6, czyli zaraz po zgonie. Bez roku zgonu (albo bez wiersza zgonu) idzie na koniec, jak każde wydarzenie bez roku. Przykład: zgon „1900”, zgon syna „1950” → „Zgon 1900 → Pochowanie → Zgon syna 1950”.

**Fokus i dirty w karcie notatki.** `SectionCardEditor` liczy dirty z `FormData` i nasłuchuje `input` / `change` — textarea działa bez zmian. Selektor autofokusu trzeba rozszerzyć o `textarea`, inaczej po kliknięciu ołówka fokus zostaje na przycisku.

## Phase 1: Notatka osoby — backend

### Overview

Kolumna `people.note`, endpoint `PUT persons/{person}/note` i `person.note` w podglądzie. Testy obejmują walidację, granice własności i to, że pełny zapis osoby nie rusza notatki.

### Changes Required:

#### 1. Migracja i model

**File**: `database/migrations/<timestamp>_add_note_to_people_table.php` (przez `vendor/bin/sail artisan make:migration add_note_to_people_table --no-interaction`), `app/Models/Person.php`

**Intent**: Dodaje nullable kolumnę tekstową na notatkę i pozwala ją masowo przypisać. Migracja nie przenosi danych, więc nie potrzebuje testu migracji; `down()` usuwa kolumnę.

**Contract**: `people.note text NULL`; `@property string|null $note`; `'note'` w `#[Fillable]`. `UpdatePerson` **nie** dostaje `note`.

#### 2. Endpoint notatki

**File**: `routes/web.php`, `app/Http/Controllers/PersonNoteController.php`, `app/Http/Requests/PersonNoteUpdateRequest.php`, `app/Actions/Persons/UpdatePersonNote.php`

**Intent**: Osobna ścieżka zapisu jednej notatki: kontroler → Form Request (autoryzacja i walidacja) → akcja → toast „Zapisano notatkę” → `back()`. Odpowiada wzorcowi `TreeRootPersonController`.

**Contract**:
- trasa `PUT persons/{person}/note` → `PersonNoteController@update`, nazwa `persons.note.update`, w grupie `auth` obok innych tras `persons/{person}/…`;
- `PersonNoteUpdateRequest::authorize()` → `Gate::inspect('update', $this->route('person'))` (cudza osoba → 404 przez `denyAsNotFound` w `PersonPolicy`);
- `rules()` → `['note' => ['nullable', 'string', 'max:5000']]`, `attributes()` → `['note' => 'notatka']`;
- `UpdatePersonNote::handle(Person $person, ?string $note): Person` zapisuje tylko `note` (pusty ciąg jest już `null` dzięki `ConvertEmptyStringsToNull`);
- po dodaniu trasy: `vendor/bin/sail artisan wayfinder:generate`.

#### 3. Notatka w podglądzie

**File**: `app/Actions/Persons/BuildPersonPreview.php`

**Intent**: Podgląd niesie notatkę jako pole dokładane do `person` tylko tutaj, więc indeks osób jej nie dostaje.

**Contract**: `person` = `PersonResource::make($person)->resolve()` + `'note' => $person->note` (string|null). Aktualizacja PHPDoc kształtu zwrotu.

#### 4. Testy Pest

**File**: `tests/Feature/PersonNoteTest.php` (przez `vendor/bin/sail artisan make:test --pest PersonNoteTest`), `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/WriteTargetsTest.php`

**Intent**: Pokrywa happy path, walidację, granice i regresję pełnego zapisu.

**Contract**: przypadki:
- **zapis:** wieloakapitowy tekst zapisuje się dosłownie (z `\n`) i wraca w `personPreview.person.note`; pusty ciąg czyści notatkę do `null`;
- **walidacja:** 5000 znaków przechodzi, 5001 daje błąd `note`;
- **pełny zapis osoby:** `PUT persons/{person}` z payloadem bez `note` zostawia notatkę bez zmian (wzór: `tests/Feature/PersonsTest.php:1393`);
- **indeks:** `persons.index` — elementy `persons` nie mają klucza `note`;
- **`OwnershipBoundariesTest`:** nowy wiersz `['persons.note.update', 'put', … victimPerson …, fn => ['note' => 'Przejęta']]`, czyli 404 i brak zapisu;
- **`WriteTargetsTest`:** zapis notatki z podglądu zmienia dokładnie jeden wiersz `people` (tej osoby); zmienione kolumny ⊆ `{note, updated_at}` (`rowDiff` nie pomija znaczników czasu — wzorzec `array_diff` z `tests/Feature/WriteTargetsTest.php:447-450`).

### Success Criteria:

#### Automated Verification:

- Migracja stosuje się i cofa: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate`
- Testy notatki przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonNoteTest.php`
- Testy granic przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/PersonsTest.php`
- Larastan i Pint czyste: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- `vendor/bin/sail artisan route:list --name=persons.note` pokazuje `PUT persons/{person}/note`

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Phase 2: Oś wydarzeń — backend

### Overview

Klasa wsparcia składa posortowaną listę wydarzeń osoby i krewnych. Zasób serializuje każdy wpis z polską etykietą, a podgląd dostaje `person.timeline` bez N+1.

### Changes Required:

#### 1. Budowa osi

**File**: `app/Support/PersonTimeline.php` (przez `vendor/bin/sail artisan make:class Support/PersonTimeline --no-interaction`), `app/Support/TimelineEntry.php`

**Intent**: Zbiera wpisy wyłącznie z już załadowanych relacji osoby i sortuje je kluczem z sekcji „Critical Implementation Details”. Wpis to niezmienny obiekt wartości: wydarzenie, rola (własne / dziecko / małżonek) i krewny (`Person|null`).

**Contract**:
- `PersonTimeline::for(Person $person): list<TimelineEntry>`; `TimelineEntry` (readonly): `Event $event`, rola (`self` | `child` | `spouse`), `?Person $relative`, oraz metoda zwracająca rangę typu;
- **reguła zakresu:**
  - własne `birth` (pomijane, gdy brak roku i brak `locality_id`), każde własne `marriage` (krewny = drugi uczestnik lub `null`), własne `death`, własne `burial`;
  - dla każdego dziecka: `birth` (ta sama reguła pomijania) i `death`;
  - dla każdego **unikalnego** małżonka z `marriages.participants` (bez samej osoby): `death`;
- zgon, małżeństwo i pochowanie są pokazywane zawsze, nawet bez daty i miejsca;
- **sortowanie:** `[rok null ? 1 : 0, rok ?? 0, miesiąc ?? 0, dzień ?? 0, ranga, event id]`, rangi 0–6 jak w „Critical Implementation Details”; dla pochowania rok / miesiąc / dzień bierze się z własnego zgonu (brak zgonu lub jego roku → rok null).

#### 2. Serializacja wpisu z etykietą

**File**: `app/Http/Resources/TimelineEntryResource.php`

**Intent**: Jedno miejsce mapowania wpisu na payload i polskiej etykiety zależnej od typu, roli i płci krewnego.

**Contract**:
- kształt: `{id: int (event id), type: 'birth'|'death'|'marriage'|'burial', label: string, date: string|null (PartialDate::format()), place: string|null, relative: {id: int, display_name: string}|null}`;
- **etykiety:**
  - własne: `EventType::label()` („Urodzenie”, „Małżeństwo”, „Zgon”, „Pochowanie”);
  - dziecko: „Urodzenie syna / córki / dziecka”, „Zgon syna / córki / dziecka” (male / female / null);
  - małżonek: „Zgon męża / żony / małżonka”.

#### 3. Eager-load i pole w podglądzie

**File**: `app/Actions/Persons/BuildPersonPreview.php`

**Intent**: Dociąga miejsca wydarzeń krewnych jednym `load` i dokłada `timeline` do `person` tylko w podglądzie.

**Contract**: do listy `load` dochodzą `children.events.locality` i `marriages.participants.events.locality`; `person.timeline` = `TimelineEntryResource::collection(PersonTimeline::for($person))->resolve()` (lista).

#### 4. Testy Pest

**File**: `tests/Feature/PersonTimelineTest.php` (przez `vendor/bin/sail artisan make:test --pest PersonTimelineTest`)

**Intent**: Sprawdza zakres, sortowanie, etykiety i stałą liczbę zapytań na rzeczywistym payloadzie `personPreview` (helper `personPreviewUrl()`).

**Contract**: przypadki z oczekiwaniami spisanymi ręcznie, nie przepisanymi z implementacji:
- **zakres:**
  - osoba z urodzeniem, dwoma małżeństwami, zgonem i pochowaniem, synem (urodzenie + zgon) i córką (urodzenie) oraz małżonkiem ze zgonem → dokładnie oczekiwane wiersze;
  - małżeństwo występuje raz;
  - urodzenie małżonka, zamieszkania i wydarzenia rodziców nie pojawiają się;
- **puste urodzenie:** pominięte zarówno własne, jak i dziecka; urodzenie z samym miejscem (bez daty) zostaje i idzie na koniec;
- **zgon bez daty i miejsca:** jest, z `date: null`, na końcu;
- **porządek:**
  - ślub „1880” przed urodzeniem syna „1880”;
  - ślub „1880” przed urodzeniem syna „05.03.1880”;
  - „ok. 1850” sortuje się jak 1850;
  - wiersze bez roku na końcu w kolejności rang (zgon bez daty przed pochowaniem);
  - pochowanie zaraz po datowanym zgonie: zgon „1900”, zgon syna „1950” → zgon, pochowanie, zgon syna;
- **etykiety:** syn / córka / dziecko bez płci; mąż / żona / małżonek bez płci;
- **krewny:** `relative.id` i `display_name` dla wierszy dziecka i małżonka; `null` dla własnych poza małżeństwem;
- **zapytania:** liczba zapytań przy GET podglądu jest taka sama dla 1 i dla 3 dzieci z urodzeniem i zgonem w miejscowościach oraz dla 1 i dla 2 małżonków ze zgonem w miejscowości (`DB::enableQueryLog`);
- **indeks:** `persons.index` — elementy `persons` nie mają klucza `timeline`.

### Success Criteria:

#### Automated Verification:

- Testy osi przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonTimelineTest.php`
- Testy podglądu bez regresji: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/EventSourcesTest.php`
- Larastan i Pint czyste: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent`

**Implementation Note**: Po zielonej weryfikacji automatycznej przejdź do fazy 3.

---

## Phase 3: Zakładki Wydarzenia i Notatka — frontend

### Overview

Typy, dwie nowe zakładki w `PersonModal`, karta notatki wpięta w strażnika, kropka przy triggerze i przewijanie paska zakładek.

### Changes Required:

#### 1. Typy

**File**: `resources/js/types/person.ts`

**Intent**: Opisuje nowe pola podglądu.

**Contract**: `TimelineEntry = {id: number; type: 'birth' | 'death' | 'marriage' | 'burial'; label: string; date: string | null; place: string | null; relative: {id: number; display_name: string} | null}`; `PersonPreview` dostaje `note: string | null` i `timeline: TimelineEntry[]`.

#### 2. Sekcja notatki w strażniku i zapis

**File**: `resources/js/components/persons/section-editor.tsx`, `resources/js/hooks/use-section-save.ts`, `resources/js/components/persons/section-card.tsx`

**Intent**: Notatka staje się sekcją jak każda inna. Strażnik nazywa ją w pasku, zapis przyjmuje jej payload, a textarea dostaje fokus po kliknięciu ołówka.

**Contract**:
- `SectionId` dostaje `'note'`, a `labelFor('note')` → `'notatce'`;
- `useSectionSave.save` przyjmuje też `{ note: string | null }` (nazwany typ, np. `PersonNotePayload`, w `resources/js/lib/person-payload.ts`);
- selektor autofokusu w `SectionCardEditor` obejmuje `textarea`.

#### 3. Zakładka Notatka

**File**: `resources/js/components/persons/note-tab.tsx`

**Intent**: Karta z ołówkiem dla jednej notatki: podgląd z zachowanymi akapitami albo stan pusty, a w edycji textarea z limitem i Zapisz / Anuluj.

**Contract**:
- `NoteTab({ person })`; `SectionCard` z `id="note"`, `title="Notatka"`;
- widok: tekst z `whitespace-pre-wrap` albo „Brak notatki.”;
- edycja: `Textarea name="note" maxLength={5000}` z wartością startową `person.note ?? ''`;
- zapis: `save('note', PersonNoteController.update.url(person.id), { note: values.note })` z własnego `useSectionSave` (`belongsToSection`: klucz `note`);
- błąd pola pod textarea przez `InputError`.

#### 4. Zakładka Wydarzenia

**File**: `resources/js/components/persons/events-tab.tsx`

**Intent**: Renderuje gotową listę z serwera, bez sortowania na kliencie.

**Contract**:
- `EventsTab({ person })` renderuje listę `person.timeline`;
- wiersz pokazuje `label`, a przy `relative` dodaje „: ” i `PersonPreviewLink personId={relative.id}` z `display_name`;
- data: `date` albo „data nieznana” (dla `type === 'burial'` daty nie pokazujemy);
- `place`, jeśli jest;
- stan pusty „Brak wydarzeń.”

#### 5. Host zakładek

**File**: `resources/js/components/persons/person-modal.tsx`

**Intent**: Dwie nowe zakładki na końcu, kropka przy niepustej notatce i pasek zakładek odporny na brak miejsca.

**Contract**:
- triggery w kolejności `przeglad`, `rodzina`, `zrodla`, `wydarzenia` („Wydarzenia”), `notatka` („Notatka”);
- przy `person.note` niepustym trigger „Notatka” ma kropkę (`aria-hidden`) i tekst `sr-only` „(jest notatka)”;
- `TabsList` mieści się w panelu: kontener z poziomym przewijaniem (`overflow-x-auto`), triggery bez ściskania i zawijania etykiet;
- `onValueChange` dalej idzie przez `requestExit`.

### Success Criteria:

#### Automated Verification:

- Format i lint: `vendor/bin/sail npm run check`
- Typy: `vendor/bin/sail npm run types:check`
- Testy JS bez regresji: `vendor/bin/sail npm run test:js`
- Build: `vendor/bin/sail npm run build`

#### Manual Verification:

- Przy 1366 px pięć zakładek mieści się lub przewija poziomo bez łamania układu; przy 375 px pasek zakładek przewija się, a treść jest czytelna
- Zakładka Wydarzenia osoby z dziećmi i małżonkiem pokazuje oczekiwaną kolejność i etykiety; kliknięcie imienia dziecka podmienia panel na panel dziecka
- Edycja notatki: ołówek ustawia fokus w textarea; zapis z wieloma akapitami zachowuje akapity; kropka przy „Notatka” pojawia się po zapisie i znika po wyczyszczeniu
- Brudna notatka + zmiana zakładki albo zamknięcie panelu pokazuje pasek „Masz niezapisane zmiany w notatce.”; „Zapisz” zapisuje i wykonuje wyjście, „Porzuć” odrzuca

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Phase 4: E2E zakładek wydarzeń i notatki

### Overview

Spec Playwright dla ryzyk, które wymagają przeglądarki: strażnik niezapisanej notatki przy zmianie zakładki i nawigacja z osi do panelu krewnego. Realizowana przez `/10x-e2e` (pętla: ryzyko → eksploracja `playwright-cli` → generowanie → przegląd antywzorców → weryfikacja przez celowe wprowadzenie błędu).

### Changes Required:

#### 1. Spec przepływu

**File**: `tests/e2e/person-events-and-note-tabs.spec.ts` (helpery danych w `tests/e2e/support/`, jeśli istniejące nie wystarczą)

**Intent**: Dwa niezależne testy z własnymi danymi (sufiks timestamp) i sprzątaniem.

**Contract**: lokatory `getByRole` / `getByLabel` / `getByText`, bez `waitForTimeout`; przypadki:
- **notatka:** wpis notatki → próba przejścia na zakładkę „Wydarzenia” pokazuje alert „Masz niezapisane zmiany w notatce.” → „Zapisz” → zakładka Wydarzenia aktywna; po ponownym otwarciu panelu notatka zawiera wpisany tekst;
- **oś:** osoba z dzieckiem urodzonym w danym roku → zakładka „Wydarzenia” zawiera wiersz „Urodzenie syna/córki” z imieniem dziecka → kliknięcie imienia podmienia panel na panel dziecka (nagłówek z jego imieniem).

### Success Criteria:

#### Automated Verification:

- Spec przechodzi: `npx playwright test tests/e2e/person-events-and-note-tabs.spec.ts`
- Istniejące specy panelu osoby bez regresji: `npx playwright test tests/e2e/person-side-panel.spec.ts tests/e2e/person-panel-unsaved-guard.spec.ts`

#### Manual Verification:

- Celowe wprowadzenie błędu (np. usunięcie `requestExit` z `onValueChange` albo odwrócenie rangi małżeństwa i urodzenia dziecka) czerwieni odpowiedni test

---

## Testing Strategy

### Unit Tests:

- Logika sortowania i etykiet żyje na serwerze, więc nie ma nowych testów Vitest. Frontend tylko renderuje gotowe dane.

### Integration Tests:

- `PersonNoteTest`: zapis, czyszczenie, limit 5000 / 5001, pełny zapis osoby nie rusza notatki, brak `note` w indeksie.
- `PersonTimelineTest`: zakres, pomijanie pustych urodzeń, porządek z rangą typu, etykiety według płci, krewny w wierszu, stała liczba zapytań, brak `timeline` w indeksie.
- `OwnershipBoundariesTest`: 404 i brak zapisu dla `persons.note.update` z cudzą osobą.
- `WriteTargetsTest`: zapis notatki zmienia tylko `people.note` (i `updated_at`) tej osoby.

### Manual Testing Steps:

1. Otwórz panel osoby z dziećmi i dwoma małżeństwami przy 1366 px. Sprawdź pasek zakładek oraz kolejność i etykiety w „Wydarzenia”.
2. Kliknij imię córki w osi i sprawdź, że panel pokazuje córkę, a zakładka wróciła do „Przegląd”.
3. W „Notatka” wpisz trzy akapity, zapisz, zamknij i otwórz panel ponownie — tekst i kropka są na miejscu.
4. Zmień notatkę i bez zapisu zamknij panel — pojawia się pasek strażnika z „notatce”.
5. Powtórz punkt 1 przy 375 px.

## Performance Considerations

Dwie dodatkowe relacje w eager-loadzie (`children.events.locality`, `marriages.participants.events.locality`) to stała liczba zapytań na podgląd, pilnowana testem. Sortowanie dotyczy kilkudziesięciu wierszy w pamięci.

## Migration Notes

Migracja dodaje tylko nullable kolumnę `people.note` — nie przenosi danych i jest odwracalna (`down()` usuwa kolumnę). Produkcja robi `backup:run --only-db` przed `migrate` (`scripts/deploy.sh`), a notatka jest objęta zrzutem bazy bez nowej pracy.

## References

- Related research: `context/changes/person-events-and-note-tabs/research.md`
- Roadmap: `context/foundation/roadmap.md` (S-14), PRD: `context/foundation/prd.md:161-169` (FR-021, FR-023)
- Host zakładek: `resources/js/components/persons/person-modal.tsx:52-86`
- Strażnik i sekcje: `resources/js/components/persons/section-editor.tsx:14-19,49-70`, `resources/js/components/persons/section-card.tsx`
- Wzorzec sortowania: `app/Support/PersonOrdering.php:120-132`
- Wzorzec małego endpointu: `app/Http/Controllers/TreeRootPersonController.php`
- Wzorzec walidacji notatki: `app/Concerns/SourceDescriptionValidationRules.php:28,66`
- Testy granic: `tests/Feature/OwnershipBoundariesTest.php:75-170`, `tests/Feature/WriteTargetsTest.php:259-302`
- Poprzedni panel: `context/archive/2026-10-09-marriage-side-panel/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Notatka osoby — backend

#### Automated

- [x] 1.1 Migracja stosuje się i cofa: `vendor/bin/sail artisan migrate` oraz `vendor/bin/sail artisan migrate:rollback --step=1 && vendor/bin/sail artisan migrate` — 60f92a5
- [x] 1.2 Testy notatki przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonNoteTest.php` — 60f92a5
- [x] 1.3 Testy granic przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php tests/Feature/WriteTargetsTest.php tests/Feature/PersonsTest.php` — 60f92a5
- [x] 1.4 Larastan i Pint czyste: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent` — 60f92a5

#### Manual

- [x] 1.5 `vendor/bin/sail artisan route:list --name=persons.note` pokazuje `PUT persons/{person}/note` — 60f92a5

### Phase 2: Oś wydarzeń — backend

#### Automated

- [x] 2.1 Testy osi przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonTimelineTest.php` — bdba899
- [x] 2.2 Testy podglądu bez regresji: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/MarriagesTest.php tests/Feature/EventSourcesTest.php` — bdba899
- [x] 2.3 Larastan i Pint czyste: `vendor/bin/sail composer types:check` oraz `vendor/bin/sail bin pint --dirty --format agent` — bdba899

### Phase 3: Zakładki Wydarzenia i Notatka — frontend

#### Automated

- [x] 3.1 Format i lint: `vendor/bin/sail npm run check` — 41e1967
- [x] 3.2 Typy: `vendor/bin/sail npm run types:check` — 41e1967
- [x] 3.3 Testy JS bez regresji: `vendor/bin/sail npm run test:js` — 41e1967
- [x] 3.4 Build: `vendor/bin/sail npm run build` — 41e1967

#### Manual

- [x] 3.5 Przy 1366 px pięć zakładek mieści się lub przewija poziomo bez łamania układu; przy 375 px pasek zakładek przewija się, a treść jest czytelna — 41e1967
- [x] 3.6 Zakładka Wydarzenia osoby z dziećmi i małżonkiem pokazuje oczekiwaną kolejność i etykiety; kliknięcie imienia dziecka podmienia panel na panel dziecka — 41e1967
- [x] 3.7 Edycja notatki: ołówek ustawia fokus w textarea; zapis z wieloma akapitami zachowuje akapity; kropka przy „Notatka” pojawia się po zapisie i znika po wyczyszczeniu — 41e1967
- [x] 3.8 Brudna notatka + zmiana zakładki albo zamknięcie panelu pokazuje pasek „Masz niezapisane zmiany w notatce.”; „Zapisz” zapisuje i wykonuje wyjście, „Porzuć” odrzuca — 41e1967

### Phase 4: E2E zakładek wydarzeń i notatki

#### Automated

- [x] 4.1 Spec przechodzi: `npx playwright test tests/e2e/person-events-and-note-tabs.spec.ts` — 86b63b0
- [x] 4.2 Istniejące specy panelu osoby bez regresji: `npx playwright test tests/e2e/person-side-panel.spec.ts tests/e2e/person-panel-unsaved-guard.spec.ts` — 86b63b0

#### Manual

- [x] 4.3 Celowe wprowadzenie błędu (np. usunięcie `requestExit` z `onValueChange` albo odwrócenie rangi małżeństwa i urodzenia dziecka) czerwieni odpowiedni test — 86b63b0
