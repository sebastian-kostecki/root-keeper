# Drzewo jako domyślny dashboard wypełniający okno — plan implementacji

## Overview

`/tree` staje się stroną po zalogowaniu. Arkusz rodzinny (dziadkowie, rodzice, para, dzieci) wypełnia okno:
- **Nagłówek strony** znika.
- **Karty są stopniowane:** kompaktowi dziadkowie bez zdjęcia, szeroka para z pełnymi danymi.
- **Wysokości rzędów** wynikają z wysokości obszaru, z limitami min/max.
- **Laptop:** przy 1366–1440 px cała rodzina jest widoczna od startu, w skali ≈ 1.

Pracujemy na osobnym branchu `feat/tree-dashboard-fill-window` (od `develop`, PR do `develop`).

## Current State Analysis

Pełna analiza jest w `context/changes/tree-dashboard-fill-window/research.md` (zarzuty C1–C5, decyzje U1–U4 i P1–P4). Najważniejsze punkty:

- **Strona po zalogowaniu** to jedna wartość, `config/fortify.php:76` `'home' => '/persons'`. Czytają ją wszystkie odpowiedzi Fortify oraz `bootstrap/app.php:21`. Na froncie jedynym literałem jest fallback passkey, `resources/js/components/passkey-verify.tsx:35` (`personsIndex()`). Na nowym koncie `/tree` pokazuje stan pusty „Dodaj pierwszą osobę” (`resources/js/pages/tree/show.tsx:124-142`).
- **Kolumna kart** wynika tylko z szerokości: `fitColumnRem` (`resources/js/lib/fit-view.ts:37-59`) w zakresie 12–18 rem. Wysokość karty to stałe `min-h-24` (`resources/js/components/tree/card-styles.ts:8,16`). `ZoomCanvas.settleColumn` (`resources/js/components/zoom-canvas.tsx:154-176`) nie czyta wysokości obszaru. Skutek:
  - przy 1920×1080 ok. 43% pionu jest puste;
  - przy 1366–1440 px 8 dziadków × 12 rem nie mieści się w skali ≥ 0,75 (`TREE_FIT_FLOOR`), więc widok startuje zakotwiczony na parze (regresja D1).
- **Wspólne karty:** każde pokolenie używa tego samego `SheetPersonCard` → `CardBody` (`resources/js/components/tree/sheet-person-card.tsx:78-123`). `CardBody` i `personCardSurface` współdzieli też `AncestorCard` (`resources/js/components/tree/ancestor-card.tsx:41-47`).
- **Payload karty** (`app/Http/Resources/SheetPersonResource.php:43-52`) niesie tylko `lifespan` (lata) i `age`. Zdarzenia są załadowane (`app/Actions/Trees/BuildFamilySheet.php:357-361`, `with('events')`), ale bez `events.locality`. Pochówek jest zdarzeniem bez daty, z samym miejscem (`app/Models/Person.php:130-133`). Ten sam resource buduje widok przodków (`app/Actions/Trees/BuildAncestorChart.php:146`).
- **Nagłówek** `tree/show.tsx:85-107` (h1, odznaka „Probant”, „Wróć do probanta”, `SetRootPersonButton`) zajmuje ≈ 56 px. `SetRootPersonButton` jest używany tylko tutaj. „Ustaw jako probanta” jest też w nagłówku panelu osoby (`resources/js/components/persons/person-modal-header.tsx:181`). Testy E2E korzystają z h1 przez `gotoTree` (`tests/e2e/support/navigation.ts:86-89`) i przez `toHaveText` w ok. 5 specyfikacjach.

## Desired End State

- Po zalogowaniu (hasło, 2FA, passkey, rejestracja, weryfikacja e-maila) użytkownik trafia na `/tree`.
- Nad drzewem nie ma wiersza nagłówka. Zostają okruszki z layoutu i h1 `sr-only` z nazwą osoby w centrum.
- Karty arkusza:
  - **Dziadkowie oraz dzieci przy ≥ 5 dzieciach** dostają kartę kompaktową: bez paska zdjęcia, imię w linii 1, nazwisko w linii 2 (zawsze, na każdej szerokości), pod spodem lata.
  - **Rodzice i dzieci przy ≤ 4 dzieciach** mają kartę jak dziś.
  - **Para** (osoba w centrum i wybrany partner) ma kartę szeroką na 3 kolumny, z paskiem zdjęcia 4,5 rem, liniami „ur. …”, „zm. …”, „poch. …” oraz wiekiem.
- Nad rzędem dzieci jest widoczna etykieta „Dzieci (N)”, a slot „Dodaj dziecko” zostaje bez zmian.
- Rzędy rosną z wysokością obszaru w proporcjach para > rodzice ≈ dzieci > dziadkowie, z limitami min/max. Nadmiar pionu zostaje jako wyśrodkowanie.
- Przy 1366×768 i 1440×900 (panel boczny rozwinięty) start pokazuje cały arkusz bez kotwicy. `TREE_FIT_FLOOR` zostaje 0,75.
- Widok przodków jest wizualnie i geometrycznie bez zmian.

Weryfikacja: Pest (Auth + Tree), Vitest (`fit-view`, formatowanie danych pary, `view-tokens`), E2E przez `/10x-e2e` oraz zrzuty w `context/changes/tree-dashboard-fill-window/screenshots/`.

### Key Discoveries:

- `fitColumnRem` ma minimum zaszyte w `COLUMN_MIN_REM` (`fit-view.ts:20,58`). Dodanie parametru `minRem` z domyślną wartością `COLUMN_MIN_REM` zostawia istniejące testy `fit-view.test.ts:24-56` i widok przodków bez zmian.
- `ZoomCanvas` ma już pętlę „stan się zmienił → zwróć false → kolejny przebieg ResizeObserver” (`zoom-canvas.tsx:154-176`, `296-315`). Budżet wysokości wpina się tam jako drugi krok. Wysokości nie zmieniają szerokości, więc pętla się zbiega.
- `chooseFitView` centruje treść w obszarze (`zoom-canvas.tsx:200,243`). Wyśrodkowanie nadmiaru pionu jest więc za darmo.
- Wyjątek na `style=` w `view-tokens.test.ts:114-118` ma tylko `family-sheet.tsx`. Wysokości rzędów trafiają jako `minHeight` w stylu komórek siatki w tym pliku, a pliki kart pozostają bez `style=` i bez wartości `-[Npx]`.
- `formatPartialDate(parts, 'long')` (`resources/js/lib/partial-date.ts:165-203`) daje „12 marca 1948”, „marzec 1948”, „około 1850”, a `''` bez roku. Panel osoby używa go już dla tych samych pól (`resources/js/components/persons/overview-tab.tsx:107-117`).
- `Person::cardName()` (`app/Models/Person.php:162-167`) = imię (lub „NN”) + nazwisko panieńskie albo bieżące (lub „NN”). Dwulinijkowa karta potrzebuje tych dwóch części osobno.
- `tree-card-name.spec.ts` liczy linie pierwszego niepustego węzła tekstu w elemencie z `title` (`:16-37`). Przy nowej strukturze karty kompaktowej pomiar trzeba dostosować do widocznych linii.

## What We're NOT Doing

- Widok przodków: bez zmian w karcie, kolumnie (12 rem) i `ANCESTORS_FIT_FLOOR` (U4).
- Obniżanie `TREE_FIT_FLOOR` (U5 zastąpione przez P1).
- Widoczne etykiety pokoleń „Dziadkowie / Rodzice / Związek”: zostają `sr-only` (P4).
- Odznaka „Probant” i przyciski „Wróć do probanta” / „Ustaw jako probanta” na drzewie: usunięte bez zamiennika. Ustawienie probanta zostaje w panelu osoby, a powrót do probanta to pozycja „Drzewo” w menu.
- Elementy PLSoft (research, sekcja 4): zdjęcie ślubne, licznik rodzeństwa, wyświetlane ID, wydruk/PDF, przełączniki widoku, Wstecz/Dalej, gradientowe łączniki, „Wstaw z listy”/„Usuń” na karcie.
- Ustawienie „ekran startowy” per użytkownik: `fortify.home` jest stałą.
- Układ mobilny (390 px): start na parze i przeciąganie jak dziś.
- Zmiana marginesów layoutu (`app-sidebar-layout.tsx:20-35`, `py-6`) i okruszków.
- Pokrycie testami przekierowań po 2FA, potwierdzeniu hasła i passkey (luka sprzed tej zmiany).
- Aktualizacja artefaktu DS i makiety `/tree` (osobny krok po zmianie).
- Zmiana tytułu `tests/e2e/seed.spec.ts`: test nadal trafia na `/persons` przez `intended()`.

## Implementation Approach

Kolejność: najpierw rzeczy niezależne od geometrii, potem karty, na końcu geometria.
1. Start i nagłówek (backend + strona).
2. Payload pary (backend + typy).
3. Warianty kart, które czytają wysokości z komórek siatki. Do czasu fazy 4 komórki mają domyślne minimum.
4. Geometria: niższe minimum kolumny tylko dla arkusza, budżet wysokości w `ZoomCanvas`, rozkład rzędów czystą funkcją z testami Vitest.
5. E2E dla ryzyk przeglądarkowych i bramka zrzutów.

Faza 3 bez fazy 4 wygląda poprawnie (kolumna 12 rem, karty w minimalnych wysokościach), więc każdą fazę da się zatrzymać i zweryfikować osobno.

## Critical Implementation Details

**Timing & lifecycle.** Budżet wysokości zależy od skali, a skala od szerokości treści. Kolejność w jednym przebiegu `refit()` jest więc stała:
1. `settleColumn()` (szerokość);
2. pomiar `content.offsetWidth` (szerokość układu, bez transformacji);
3. `settleHeight()`;
4. `fit()`.

Każdy krok, który zmienia stan, zwraca false, a następny przebieg ResizeObserver idzie dalej. Budżet zaokrąglamy w dół do kroku 0,25 rem (jak `COLUMN_STEP_ROUND_REM`), żeby zmiana o 1 px nie wywoływała kolejnych renderów.

**Rozkład rzędów a rzeczywista treść.** Wysokość rzędu to `minHeight`, nie `height`. Karta z dłuższą treścią może urosnąć, a `chooseFitView` liczy skalę z rzeczywistego rozmiaru treści. Rozbieżność między stałymi pionu a DOM daje więc najwyżej trochę mniejszą skalę, nigdy ucięcie.

**Dostępność karty kompaktowej.** Imię i nazwisko w dwóch blokach nie dają spacji w nazwie dostępnej. Widoczne linie są `aria-hidden`, a obok jest `sr-only` z pełnym `card_name`. Nazwa linku zostaje taka jak dziś („kobieta Aleksandra Brzęczyszczykiewicz”), a `title` z pełnym imieniem i nazwiskiem zostaje na kontenerze imienia (`getByTitle` w E2E).

---

## Faza 1: Start na drzewie, bez nagłówka

### Overview

Ustawiamy `/tree` jako stronę po zalogowaniu i usuwamy wiersz nagłówka nad drzewem. Branch `feat/tree-dashboard-fill-window` jest już utworzony od `develop`.

### Changes Required:

#### 1. Strona po zalogowaniu

**File**: `config/fortify.php`, `resources/js/components/passkey-verify.tsx`

**Intent**: Wszystkie wejścia po uwierzytelnieniu prowadzą do drzewa zamiast do listy osób. Powód z `archive/2026-09-30-app-shell-sidebar` („nowe konto ma tam Dodaj osobę”) jest nieaktualny, bo `/tree` ma stan pusty.

**Contract**: `'home' => '/tree'` (`config/fortify.php:76`). Fallback passkey `personsIndex()` → `index()` z `@/routes/tree` (`passkey-verify.tsx:35`).

#### 2. Testy przekierowań

**File**: `tests/Feature/Auth/AuthenticationTest.php:29`, `RegistrationTest.php:26`, `EmailVerificationTest.php:37,82,97`, `VerificationNotificationTest.php:31`, `EntryTest.php:12-20`, `tests/e2e/auth.setup.ts:30-33`

**Intent**: Asercje odzwierciedlają nową, świadomie wybraną stronę startową. To zmiana zachowania biznesowego zatwierdzona przez użytkownika, a nie dopasowanie do błędu.

**Contract**:
- `route('persons.index')` → `route('tree.index')`. Weryfikacja e-maila zachowuje `?verified=1`.
- `EntryTest`: `assertRedirect('/tree')`, komponent `tree/show`, a nazwa testu mówi o lądowaniu na drzewie.
- `auth.setup.ts`: `toHaveURL(/\/tree/)` i widoczny h1 poziomu 1. Nazwa h1 nie jest stała, bo konto E2E może mieć probanta albo stan pusty „Drzewo”.

#### 3. Usunięcie nagłówka nad drzewem

**File**: `resources/js/pages/tree/show.tsx`, `resources/js/components/tree/set-root-person-button.tsx` (usunięcie), `resources/js/lib/view-tokens.test.ts:48`, `app/Http/Controllers/TreeController.php`, `tests/Feature/TreeTest.php`

**Intent**: Arkusz dostaje ok. 56 px pionu. h1 zostaje dla czytników ekranu i dla E2E. Stan pusty `sheet === null` zostaje bez zmian (widoczny `Heading` „Drzewo”).

**Contract**:
- W gałęzi z arkuszem: `<h1 className="sr-only">{sheet.focus.display_name}</h1>`, bez wiersza akcji i bez `gap-6`. Kontener `flex min-h-0 flex-1 flex-col` zostaje.
- Plik `SetRootPersonButton` i jego wpis na liście w `view-tokens.test.ts` są usunięte.
- Nieużywane importy (`Badge`, `Link`, `SetRootPersonButton`) są usunięte. Hook `useSetRootPerson` zostaje, bo używa go `person-modal-header.tsx`.
- Prop `rootPersonId` służy na tej stronie tylko nagłówkowi, więc znika z `Props` w `show.tsx`, z obu `Inertia::render` w `app/Http/Controllers/TreeController.php:28,46` i z asercji `->where('rootPersonId', …)` w `tests/Feature/TreeTest.php:121,133,144`. Pozostałe asercje tych testów zostają. Lista osób (`PersonsTest.php:283,288`) zachowuje swój prop.

### Success Criteria:

#### Automated Verification:

- Testy Auth i Tree przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/Auth tests/Feature/TreeTest.php`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- Logowanie hasłem kończy się na `/tree` (z probantem: arkusz; bez: stan pusty).
- Nad drzewem nie ma nagłówka ani przycisków; czytnik ekranu (lub drzewo dostępności) podaje h1 z nazwą osoby w centrum.

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych.

---

## Faza 2: Dane pary w payloadzie

### Overview

Każda karta arkusza dostaje imię i nazwisko osobno. Karty osoby w centrum i partnera dostają `details` z datą i miejscem urodzenia, zgonu oraz miejscem pochówku.

### Changes Required:

#### 1. Części imienia karty

**File**: `app/Models/Person.php`, `app/Http/Resources/SheetPersonResource.php`, `app/Actions/Trees/BuildFamilySheet.php:19` (phpstan-type)

**Intent**: Karta kompaktowa wyświetla imię i nazwisko w osobnych liniach bez dzielenia ciągu na froncie. `cardName()` zostaje źródłem prawdy i jest złożeniem tych dwóch części.

**Contract**:
- Nowa metoda `cardNameParts(): array{given: string, surname: string}` z tymi samymi fallbackami „NN”. `cardName()` = `trim(given.' '.surname)`.
- Resource dodaje `card_given_name: string` i `card_surname: string`. Widok przodków też je dostaje, ale ich nie używa.

#### 2. Pełne dane pary

**File**: `app/Http/Resources/SheetPersonResource.php`, `app/Actions/Trees/BuildFamilySheet.php` (`loadPeople` :357-361, `render` :527-533, focus :154, `partnerSlot` :418-431)

**Intent**: Tylko para niesie pełne dane, więc payload pozostałych kart (i widoku przodków) nie rośnie. Miejsca pochodzą z `locality`, jak w `EventResource:43` i `PersonResource:60`.

**Contract**:
- `loadPeople` ładuje `with(['events.locality'])` (+1 zapytanie, stałe względem rozmiaru drzewa).
- `render(Person $person, bool $withDetails = false)`. `true` tylko dla `focus` i dla partnera w `partnerSlot`.
- Resource z flagą dodaje:

```
details: {
  birth: { date_parts: {year, month, day, is_approximate}, place: string|null } | null,
  death: { date_parts: {…}, place: string|null } | null,
  burial_place: string|null
}
```

- `birth`/`death` są `null`, gdy zdarzenia brak albo nie ma ani roku, ani miejsca. `burial_place` = `burial()?->locality?->name`. Bez flagi klucza `details` nie ma w ogóle.
- `date_parts` mają ten sam kształt co `EventResource:45-50`, więc front formatuje je istniejącym `formatPartialDate`.

#### 3. Typy TS

**File**: `resources/js/types/tree.ts:3-12`

**Intent**: Front zna nowe pola.

**Contract**: `SheetPerson` dostaje `card_given_name: string`, `card_surname: string`, `details?: SheetPersonDetails`. Nowy typ `SheetPersonDetails` odpowiada kształtowi z punktu 2, a `date_parts` ma ten sam typ co w `PersonEvent` (`resources/js/types/person.ts:14`).

#### 4. Testy Pest

**File**: `tests/Feature/TreeTest.php`

**Intent**: Pokrywamy nowe pola i to, że nie wyciekają poza parę.

**Contract**: Nowe testy przez `sheetFor()` (`:96-103`) i stany `EventFactory` (`birth()`, `death()`, `burial()`, `withLocality()`, `approximate()`):
- `focus.details` i `partner.person.details` mają datę (części) i miejsce urodzenia, zgonu oraz miejsce pochówku;
- data przybliżona daje `is_approximate: true`;
- osoba żyjąca ma `death: null`;
- grandparent, parent i child nie mają klucza `details`;
- `card_given_name`/`card_surname` z nazwiskiem panieńskim i z fallbackiem „NN”.

Istniejący test liczby zapytań (`:532-569`) nadal przechodzi bez zmiany asercji.

### Success Criteria:

#### Automated Verification:

- Testy drzewa przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php`
- Testy widoku przodków przechodzą: `vendor/bin/sail artisan test --compact --filter=Ancestor`
- Larastan przechodzi: `vendor/bin/sail bin phpstan analyse`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent`

---

## Faza 3: Warianty kart arkusza

### Overview

Dochodzą karta kompaktowa (1 kolumna), karta pary (3 kolumny, pełne dane) i etykieta „Dzieci (N)”. Wysokość kart wynika z komórek siatki, które w tej fazie mają stałe minimum. Widok przodków zostaje przy `CardBody`.

### Changes Required:

#### 1. Style kart

**File**: `resources/js/components/tree/card-styles.ts`

**Intent**: Wariant kompaktowy nie dziedziczy `min-h-24`, bo jego wysokość wyznacza komórka. Karty przodków i obecne karty zostają przy dzisiejszych klasach.

**Contract**:
- `compactCardSurface` (kolumna, bez paska, `h-full`, mniejszy poziomy padding, bez `min-h-*`);
- `compactPlaceholderSurface` dla `UnknownPersonCard` w 1 kolumnie (mniejszy padding, `text-sm`, bez rezerwy na przycisk oka).

`cardSurface` i `personCardSurface` bez zmian.

#### 2. Karta kompaktowa i karta pary

**File**: `resources/js/components/tree/sheet-person-card.tsx`, `resources/js/components/tree/unknown-person-card.tsx`

**Intent**: `SheetPersonCard` wybiera treść według wariantu. Działanie (link do przecentrowania, przycisk oka, `aria-current` dla osoby w centrum) zostaje wspólne.

**Contract**:
- `SheetPersonCard` dostaje prop `variant?: 'standard' | 'compact' | 'pair'` (domyślnie `standard` = dzisiejsze `CardBody`).
- **`compact`:**
  - linia 1 `card_given_name`, linia 2 `card_surname`, każda w 1 linii z wielokropkiem (`truncate`), font imienia mniejszy niż w karcie standardowej (14 px, `text-sm`);
  - pod spodem `lifespan`, bez wieku;
  - zawsze dwie linie imienia, niezależnie od szerokości;
  - `title={card_name}` na kontenerze imienia, linie `aria-hidden` i `sr-only` z płcią i pełnym `card_name` (patrz Critical Implementation Details);
  - rezerwa na przycisk oka w linii 1.
- **`pair`:** pasek zdjęcia (karta ≥ 20 rem włącza `@xs:w-18`, czyli 4,5 rem), imię jak w `standard`, potem linie z `pairDetailLines(details, is_deceased)`, a na końcu wiek jak dziś.
- `CardBody` (używany przez `AncestorCard`) bez zmian w zachowaniu.
- `UnknownPersonCard` dostaje `compact?: boolean`, który przełącza `cardSurface` na `compactPlaceholderSurface`.

#### 3. Linie danych pary

**File**: `resources/js/lib/pair-details.ts` (nowy), `resources/js/lib/pair-details.test.ts` (nowy)

**Intent**: Czysta funkcja formatuje dane pary, więc format testujemy w Vitest, a nie w E2E.

**Contract**: `pairDetailLines(details: SheetPersonDetails, isDeceased: boolean): string[]`.

Format linii:
- `ur. {data long}, {miejsce}`
- `zm. {data long}, {miejsce}`
- `poch. {miejsce}`

Reguły:
- Datę formatuje `formatPartialDate(parts, 'long')`.
- Brak daty daje `ur. {miejsce}`, a brak miejsca `ur. {data}`.
- Brak obu pomija linię.
- Linia „zm.” pojawia się tylko przy `isDeceased`, a „poch.” tylko przy niepustym `burial_place`.

Testy obejmują: pełne dane, datę przybliżoną („około”), sam rok, samo miejsce, osobę żyjącą i pusty zestaw (`[]`).

#### 4. Użycie wariantów w arkuszu

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Dziadkowie i 1-kolumnowe dzieci dostają `compact`, para `pair`, a rodzice i dzieci przy ≤ 4 zostają `standard`. Karta pary ma 3 kolumny.

**Contract**:
- `Slot` dostaje `variant`, przekazywany do `SheetPersonCard` oraz jako `compact` do `UnknownPersonCard`/`AddPersonSlot` w rzędzie dziadków.
- `ChildrenRow` wybiera `compact`, gdy `count > WIDE_CHILDREN_MAX`. Slot „Dodaj dziecko” zostaje kartą jak dziś, bez zmiany nazwy i `aria-label`.
- `coupleCardRem(column)` = `3 * column + 2 * COLUMN_GAP_REM`, ograniczone do `PAIR_CARD_MAX_REM` (28 rem, do potwierdzenia na zrzucie 2560). Karty zostają wyśrodkowane w swoich połówkach `col-span-4`, a `UnionNode` zostaje na osi.
- Komórki rzędów (dziadkowie, rodzice, para, dzieci) dostają `minHeight` w stylu, w tej fazie ze stałych domyślnych `SHEET_ROW_DEFAULT_REM` równych dzisiejszemu `min-h-24` (6 rem) dla `standard` i `pair` oraz 5 rem dla `compact`.
- Etykieta „Dzieci (N)”:
  - widoczny `RowLabel` w wersji widocznej (`text-tree-meta text-xs`) nad rzędem dzieci, przy pniu;
  - N = `sheet.children.length`, czyli dzieci wybranego związku;
  - przy 0 dzieci „Dzieci (0)” nad slotem „Dodaj dziecko”, zamiast dzisiejszego `sr-only` „Dzieci” (`:484,773`) i obok notatki `sr-only` „Brak dzieci w tym związku.”;
  - etykiety „Dziadkowie / Rodzice / Związek” zostają `sr-only`.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi, w tym nowe `pair-details.test.ts` i niezmienione `view-tokens.test.ts`: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`

#### Manual Verification:

- Dziadkowie: karta bez zdjęcia, imię i nazwisko w dwóch liniach, lata pod spodem; puste sloty dziadków („Dodaj ojca/matkę”) czytelne w wąskiej karcie.
- Para: karta szersza od rodzica, większe zdjęcie, linie ur./zm./poch. i wiek; brakujące pola pominięte.
- Przy 5+ dzieciach karty dzieci są kompaktowe; przy ≤ 4 jak dziś.
- „Dzieci (N)” widoczne nad rzędem dzieci, a przy braku dzieci „Dzieci (0)” nad slotem.
- Widok przodków wygląda jak przed zmianą (porównanie z zrzutem `develop`).
- Tryb ciemny: nowe karty używają tokenów płci i `text-tree-meta`, bez utraty kontrastu.

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych.

---

## Faza 4: Geometria wypełnienia okna

### Overview

Arkusz dostaje niższe minimum kolumny i budżet wysokości z obszaru. Rzędy rozkładają budżet w proporcjach z limitami. Podłoga skali zostaje 0,75.

### Changes Required:

#### 1. Czyste funkcje geometrii

**File**: `resources/js/lib/fit-view.ts`, `resources/js/lib/fit-view.test.ts`

**Intent**: Liczenie wysokości jest testowalne bez przeglądarki, jak dzisiejsze `fitColumnRem` i `chooseFitView`.

**Contract**:
- `fitColumnRem({..., minRem = COLUMN_MIN_REM})`, czyli nowy opcjonalny parametr. Istniejące przypadki testów `:24-56` zostają, a nowe obejmują `minRem` (np. 1070 px, 8 kolumn, pad 1 rem, `minRem` 7,5 → 7,5).
- `SHEET_COLUMN_MIN_REM = 7.5`. Wartość jest ustalona obliczeniowo dla 1366 px. Implementujący potwierdza ją pomiarem: karta kompaktowa nie może się rozjechać, a „Dodaj matkę” mieści się w 2 liniach.
- `fillHeightRem({ areaWidth, areaHeight, contentWidth, rootFontSize, floor, max }): number` = `areaHeight / clamp(areaWidth / contentWidth, floor, max) / rootFontSize`, zaokrąglone w dół do 0,25 rem. Zwraca 0, gdy któryś rozmiar ≤ 0.
- `distributeRows({ budgetRem, rows: { min, max, weight }[] }): number[]` — rozkład typu „napełnianie wodą”:
  1. każdy rząd startuje od `min`;
  2. nadwyżka idzie proporcjonalnie do `weight`;
  3. rząd, który dojdzie do `max`, odpada, a resztę rozkłada się dalej;
  4. budżet mniejszy niż suma minimów daje same minima, a nadwyżka ponad sumę maksimów zostaje nierozdzielona.

  Testy pokrywają wszystkie cztery gałęzie i budżet 0.

#### 2. Budżet wysokości w `ZoomCanvas`

**File**: `resources/js/components/zoom-canvas.tsx`

**Intent**: Treść dostaje oprócz kolumny wysokość, którą ma wypełnić w skali startowej. Widok przodków nie włącza tej opcji, więc jego zachowanie zostaje.

**Contract**:
- Nowe propsy:
  - `columnMinRem?: number`, przekazywany do `fitColumnRem`;
  - `fillHeight?: boolean`, domyślnie false.
- `children: ReactNode | ((columnRem: number, fillHeightRem: number) => ReactNode)`. Przy `fillHeight` false drugi argument to 0.
- Nowy krok `settleHeight()` między `settleColumn()` a `fit()` w `refit()`, z tym samym kontraktem „zmiana → false” i stanem + refem jak `columnRem` (`:110-111`). Kolejność opisuje Critical Implementation Details.
- Docblock komponentu (`:79-88`) dopisuje, że z `fillHeight` treść dostaje wysokość do wypełnienia.

#### 3. Rozkład rzędów w arkuszu

**File**: `resources/js/components/tree/family-sheet.tsx`

**Intent**: Arkusz zamienia budżet na wysokości czterech rzędów, odejmując stały pion (łączniki, pień, pady, etykieta dzieci).

**Contract**:
- `ZoomCanvas` dostaje `columnMinRem={SHEET_COLUMN_MIN_REM}` i `fillHeight`.
- Stały pion liczy funkcja `sheetFixedRem(...)` z tych samych stałych co klasy. Wysokości łączników (`h-11`/`h-12`, `h-7.5`), pady `p-2`, `pt-4 pb-2` i etykieta są zamienione na nazwane stałe rem użyte w stylu, żeby nie rozjechały się z obliczeniem.
- Przy `slotBeside === false` (≥ 4 dzieci) rząd dzieci liczy się podwójnie, bo slot stoi pod rzędem.
- Rzędy i limity (rem, startowe, do strojenia na zrzutach):

  | Rząd | min | max | waga |
  | --- | --- | --- | --- |
  | dziadkowie | 5 | 7 | 1 |
  | rodzice | 6 | 9 | 1,25 |
  | para | 10 | 16 | 2 |
  | dzieci | 6 | 10 | 1,25 |

  Wynik `distributeRows` zastępuje `SHEET_ROW_DEFAULT_REM` w `minHeight` komórek z fazy 3. Przy budżecie 0 (pierwszy render) obowiązują minima.

### Success Criteria:

#### Automated Verification:

- Vitest przechodzi, w tym nowe przypadki `fitColumnRem` (`minRem`), `fillHeightRem` i `distributeRows`: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`

#### Manual Verification:

- 1366×768 i 1440×900 (panel boczny rozwinięty): start pokazuje 8 dziadków i dzieci w całości, bez kotwicy, tekst czytelny.
- 1920×1080: arkusz zajmuje co najmniej ok. 80% wysokości obszaru, a para jest wyraźnie najwyższa.
- 2560×1440: rzędy na limitach, arkusz wyśrodkowany w pionie, karta pary nie szersza niż ok. 28 rem.
- Otwarty panel boczny (osoba, `?add=`) zwęża obszar, a arkusz dopasowuje się ponownie bez skakania (brak pętli renderów, brak migotania).
- Przesunięcie widoku ręką blokuje ponowne dopasowanie jak dziś. Zmiana związku (remount) dopasowuje od nowa.
- Widok przodków: start, „Dopasuj” i przybliżanie jak przed zmianą.

**Implementation Note**: Po fazie i zielonej weryfikacji automatycznej zatrzymaj się na potwierdzenie testów ręcznych.

---

## Faza 5: E2E i bramka wizualna

### Overview

Ryzyka przeglądarkowe przechodzą pętlę `/10x-e2e` (ryzyko → eksploracja przez `playwright-cli` → generowanie → przegląd względem antywzorców → weryfikacja przez wprowadzenie błędu). Zmiana oczekiwania w `tree-card-name.spec.ts` wynika z decyzji użytkownika. Zrzuty są bramką wizualną.

### Changes Required:

#### 1. Nowe ryzyka E2E

**File**: `tests/e2e/tree-fill-window.spec.ts` (nowy, nazwa do potwierdzenia w `/10x-e2e`)

**Intent**: Pilnujemy dwóch zachowań, których nie pokryją Vitest ani Pest, bo zależą od realnego układu.

**Contract**:
- **R1 — 1366×768, cała rodzina na starcie:**
  - dane: osoba z 4 dziadkami i 1 dzieckiem, z unikalnym sufiksem, przez `tests/e2e/support/api.ts`;
  - po `gotoTree` karty skrajnych dziadków i dziecka mieszczą się w granicach obszaru drzewa (bounding box);
  - lokatory po roli/nazwie (`getByRole('link', { name: … })`).
  - Weryfikacja przez wprowadzenie błędu: chwilowe przywrócenie `columnMinRem` 12 → test czerwony.
- **R2 — 1920×1080, stopniowane wypełnienie:**
  - wysokość karty pary > karty rodzica > karty dziadka;
  - dolna krawędź rzędu dzieci minus górna krawędź rzędu dziadków ≥ 80% wysokości obszaru.
  - Weryfikacja przez wprowadzenie błędu: wyłączenie `fillHeight` → test czerwony.
- Bez `waitForTimeout`: czekamy na widoczność kart i stabilny układ, a każdy test sprząta swoje dane.

#### 2. Zmiana oczekiwań w testach nazw kart

**File**: `tests/e2e/tree-card-name.spec.ts`

**Intent**: Test 1 opisywał wcześniejszą decyzję „imię dziadka w 1 linii przy 2560”. Użytkownik zdecydował (2026-10-19), że karta kompaktowa ma zawsze imię i nazwisko w osobnych liniach, więc to świadoma zmiana zachowania, nie dopasowanie do błędu. Test 2 zachowuje oczekiwania.

**Contract**:
- Test 1: tytuł i asercja „imię i nazwisko dziadka stoją w osobnych liniach” (linia nazwiska jest pod linią imienia, każda w 1 linii).
- Helper `visibleNameLines` mierzy widoczne linie w nowej strukturze karty kompaktowej. To zmiana lokatora/pomiaru w przeglądanym diffie, a progi testu 2 (`≤ 2`, `Anna Nowak`, nazwa linku `kobieta Anna Nowak`) zostają.

#### 3. Zrzuty

**File**: `context/changes/tree-dashboard-fill-window/screenshots/`

**Intent**: Bramka wizualna wymagana przez CLAUDE.md dla zmian widoku.

**Contract**: Zrzuty:
- `/tree` przy 1366×768, 1440×900, 1920×1080 i 2560×1440;
- 1366×768 z otwartym panelem osoby;
- rodzina z 6 dziećmi;
- tryb ciemny przy 1920×1080;
- `/tree/{id}/ancestors` przy 1366×768 (bez regresji).

### Success Criteria:

#### Automated Verification:

- Nowe specyfikacje R1 i R2 przechodzą, a każda czerwienieje po wprowadzeniu opisanego błędu: `vendor/bin/sail npx playwright test tests/e2e/tree-fill-window.spec.ts`
- `tree-card-name.spec.ts` przechodzi z nowym oczekiwaniem testu 1: `vendor/bin/sail npx playwright test tests/e2e/tree-card-name.spec.ts`
- Cały zestaw E2E przechodzi (w tym `auth.setup.ts` z lądowaniem na `/tree`): `vendor/bin/sail npx playwright test`
- Zrzuty istnieją w `context/changes/tree-dashboard-fill-window/screenshots/`

#### Manual Verification:

- Przegląd zrzutów: brak ucięć, karty pary/dziadków zgodne z decyzjami, wyśrodkowanie przy 2560, widok przodków bez zmian.
- Pełny zestaw Pest uruchomiony przez użytkownika: `vendor/bin/sail artisan test --compact`

---

## Testing Strategy

### Unit Tests:

- Vitest `fit-view.test.ts`: `fitColumnRem` z `minRem`, `fillHeightRem` (skala obcięta do floor/max, rozmiary zerowe), `distributeRows` (minima, wagi, limity, nadwyżka, budżet 0).
- Vitest `pair-details.test.ts`: kombinacje daty, miejsca, przybliżenia, osoby żyjącej i pochówku.
- `view-tokens.test.ts` bez zmian (pliki kart bez `style=` i `-[Npx]`).

### Integration Tests:

- Pest `tests/Feature/Auth/*`: lądowanie na `tree.index`.
- Pest `TreeTest.php`: `details` tylko dla pary, części imienia, stała liczba zapytań.
- E2E: R1, R2, `tree-card-name.spec.ts` i pełny zestaw.

### Manual Testing Steps:

1. Zaloguj się → `/tree` z arkuszem probanta, bez nagłówka.
2. Przy 1366×768 sprawdź, że wszyscy dziadkowie i dzieci są widoczni od startu.
3. Przełącz związek z „k z N” → arkusz dopasowany od nowa, etykieta „Dzieci (N)” zgodna z wybranym związkiem.
4. Otwórz panel osoby i panel dodawania → arkusz zwęża się i dopasowuje bez skakania.
5. Otwórz widok przodków → bez zmian.

## Performance Considerations

- `events.locality` dodaje jedno zapytanie na żądanie arkusza, stałe względem rozmiaru drzewa (pilnuje tego test `TreeTest.php:532-569`).
- `settleHeight` dodaje najwyżej jeden render na zmianę rozmiaru obszaru. Zaokrąglenie do 0,25 rem zapobiega kaskadzie renderów przy przeciąganiu okna.

## Migration Notes

Bez migracji danych. Zmiana `fortify.home` działa od wdrożenia. Ewentualne zapisane `url.intended` nadal mają pierwszeństwo.

## References

- Research: `context/changes/tree-dashboard-fill-window/research.md`
- Wcześniejsze zmiany: `context/archive/2026-10-05-tree-fits-screen/`, `context/archive/2026-10-11-person-photo-on-card/`, `context/archive/2026-10-12-tree-card-name-one-line/`, `context/archive/2026-09-30-app-shell-sidebar/`
- Wzorzec pętli dopasowania: `resources/js/components/zoom-canvas.tsx:154-176,296-315`
- Formatter dat: `resources/js/lib/partial-date.ts:165-203`
- Testy payloadu arkusza: `tests/Feature/TreeTest.php:96-103,458-569`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Start na drzewie, bez nagłówka

#### Automated

- [x] 1.1 Testy Auth i Tree przechodzą — f3b9ae6
- [x] 1.2 Typy przechodzą — f3b9ae6
- [x] 1.3 Format i lint przechodzą — f3b9ae6
- [x] 1.4 Vitest przechodzi — f3b9ae6
- [x] 1.5 Pint bez zmian — f3b9ae6

#### Manual

- [x] 1.6 Logowanie hasłem kończy się na /tree — f3b9ae6
- [x] 1.7 Brak nagłówka nad drzewem, h1 dostępny dla czytnika — f3b9ae6

### Phase 2: Dane pary w payloadzie

#### Automated

- [x] 2.1 Testy drzewa przechodzą — a1d3ef3
- [x] 2.2 Testy widoku przodków przechodzą — a1d3ef3
- [x] 2.3 Larastan przechodzi — a1d3ef3
- [x] 2.4 Typy przechodzą — a1d3ef3
- [x] 2.5 Pint bez zmian — a1d3ef3

### Phase 3: Warianty kart arkusza

#### Automated

- [x] 3.1 Vitest przechodzi, w tym pair-details i view-tokens — 993832c
- [x] 3.2 Typy przechodzą — 993832c
- [x] 3.3 Format i lint przechodzą — 993832c
- [x] 3.4 Build przechodzi — 993832c

#### Manual

- [x] 3.5 Karta kompaktowa dziadków i puste sloty czytelne — 993832c
- [x] 3.6 Karta pary z danymi i wiekiem — 993832c
- [x] 3.7 Kompaktowe dzieci przy 5+ — 993832c
- [x] 3.8 Etykieta Dzieci (N) widoczna — 993832c
- [x] 3.9 Widok przodków bez zmian — 993832c
- [x] 3.10 Tryb ciemny bez utraty kontrastu — 993832c

### Phase 4: Geometria wypełnienia okna

#### Automated

- [x] 4.1 Vitest przechodzi z nowymi przypadkami geometrii
- [x] 4.2 Typy przechodzą
- [x] 4.3 Format i lint przechodzą
- [x] 4.4 Build przechodzi

#### Manual

- [x] 4.5 1366 i 1440: cała rodzina bez kotwicy
- [x] 4.6 1920: arkusz wypełnia ok. 80% wysokości
- [x] 4.7 2560: limity i wyśrodkowanie
- [x] 4.8 Otwarty panel boczny: ponowne dopasowanie bez skakania
- [x] 4.9 Przesunięcie i zmiana związku jak dziś
- [x] 4.10 Widok przodków: dopasowanie i przybliżanie bez zmian

### Phase 5: E2E i bramka wizualna

#### Automated

- [ ] 5.1 Specyfikacje R1 i R2 przechodzą i czerwienieją po wprowadzeniu błędu
- [ ] 5.2 tree-card-name.spec.ts przechodzi z nowym oczekiwaniem
- [ ] 5.3 Cały zestaw E2E przechodzi
- [ ] 5.4 Zrzuty istnieją

#### Manual

- [ ] 5.5 Przegląd zrzutów
- [ ] 5.6 Pełny zestaw Pest uruchomiony przez użytkownika
