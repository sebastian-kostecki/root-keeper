# Panel osoby obok drzewa (S-06) — plan implementacji

## Overview

Autor ogląda i edytuje osobę w prawym panelu bocznym F-02 zamiast w modalu. Otwarty panel nie zasłania drzewa ani listy, bo treść strony zwęża się obok niego. Kliknięcie karty drzewa otwiera panel osoby, a przycisk „Podgląd” znika. Przecentrowanie drzewa to akcja panelu „Pokaż na drzewie”, która zostawia panel otwarty. Przejście do krewnego podmienia treść panelu, a po zamknięciu autor jest w tym samym miejscu drzewa. Panel otwiera się w ten sam sposób z listy osób i z panelu źródła (FR-016, FR-019, FR-026, FR-028, FR-029).

## Current State Analysis

- Stan podglądu już żyje w adresie (`?person=`). Klucz `person → personPreview` jest w rejestrze paneli (`resources/js/lib/side-panel-url.ts:7`). Globalny resolver w `app/Http/Middleware/HandleInertiaRequests.php:56-95` robi `findOrFail` w drzewie użytkownika i `Gate::authorize('view')`, więc cudza osoba daje 404 na każdej stronie. Renderuje się jednak Radix `Dialog` (`resources/js/components/persons/person-preview-dialog.tsx:42-85`) montowany w layoucie (`resources/js/layouts/app-layout.tsx:17-27`). Layout jest trwały (`resources/js/app.tsx:12-21`).
- Powłoka `SidePanel` (`resources/js/components/side-panel.tsx:99-254`) jest gotowa. Ma propsy `title`, `description`, `variant`, `contentKey`, `onRequestClose`, `actions` i `footer`. Ma `role="dialog" aria-modal="false"` nazwany tytułem i natywny Escape, który pomija zdarzenia `defaultPrevented`. Renderuje się portalem do slotu za `<main>`, więc treść strony się zwęża.
- `PersonPreviewProvider` (`resources/js/components/persons/person-preview-provider.tsx:23-109`) ma własne `withPerson` i `previewVisitOptions` z `only: ['personPreview']`. Nie czyści innych kluczy paneli i trzyma stos „Wstecz”. Migracja na `withPanel`/`panelVisitOptions` czeka od impl-review F3 w `context/archive/2026-10-04-side-panel-shell/`.
- **Strażnik edycji sekcji obejmuje dziś tylko modal.** `SectionEditorProvider` (`resources/js/components/persons/section-editor.tsx:69-195`) owija sam dialog (`person-preview-dialog.tsx:26`). `PersonPreviewLink` na kartach drzewa i wierszach listy dostaje więc `useOptionalSectionEditor() === null` (`person-preview-link.tsx:33`). Modal zasłaniał stronę, więc to nie szkodziło. Przy panelu obok drzewa kliknięcie innej karty po cichu porzuciłoby brudną sekcję.
- `PersonModalHeader` używa `DialogTitle`/`DialogDescription` (`person-modal-header.tsx:58-65`), które wymagają kontekstu Radix `Dialog`. „Pokaż na drzewie” prowadzi na `tree/{id}` bez `?person` (`:39,85-124`), więc dziś zamyka podgląd.
- Karta drzewa: ciało to `<Link href={treeShow(id,{spouse})}>`, które przecentrowuje. Karta fokusu to `div` bez akcji, a ikona „Podgląd” jest na każdej karcie (`resources/js/components/tree/sheet-person-card.tsx:56-87`). Drugi przycisk „Podgląd” stoi w nagłówku drzewa (`resources/js/pages/tree/show.tsx:104-108`).
- `spouse` z karty wybiera związek po przecentrowaniu, np. dziadek → związek z babcią (`family-sheet.tsx:42-50,66,327,364,417`). Bez niego `selectUnion` bierze pierwszy związek (`app/Actions/Trees/BuildFamilySheet.php:348-362`).
- Przełączenie związku buduje adres od zera (`resources/js/components/tree/union-node.tsx:80-98`), a dodanie małżonka przekierowuje na `to_route('tree.show', [person, spouse])` (`app/Http/Controllers/PersonSpouseController.php:28,41`). Oba gubią `?person=`. Zapisy rodzica i dziecka używają `back()`, więc go zachowują.
- Link „Potwierdza” w panelu źródła (`resources/js/components/sources/source-panel.tsx:517-532`) to `PersonPreviewLink` bez strażnika formularza źródła. Dziś modal stawał nad panelem źródła, więc formularz zostawał (`context/archive/2026-10-05-source-side-panel/plan.md:31`).
- E2E wchodzi do osoby wyłącznie przez `openPerson()` (`tests/e2e/support/navigation.ts:81-91`, `getByRole('dialog', { name: displayName })`). Jedyne lokatory strukturalne to `familySection` i `sourcesGroup` (`:94-107,133-141`), szukające `section` po `h3` (F9 z `context/archive/2026-10-04-critical-flows-e2e-guard/follow-ups/review-fixes.md`).
- Vitest działa bez DOM, więc testy jednostkowe mają tylko czyste funkcje w `resources/js/lib/*.test.ts`.

## Desired End State

- Na każdej stronie layoutu aplikacji (drzewo, lista osób, źródła, miejscowości, parafie) `?person={id}` otwiera po prawej panel `standard` o nazwie `display_name` z opisem lat życia. Treść strony zwęża się obok panelu. W nagłówku panelu są „Pokaż na drzewie” i menu „⋯” („Ustaw jako probanta”, „Usuń osobę…”). Treść to zakładki Przegląd/Rodzina/Źródła z edycją sekcji jak dziś, w jednym przewijanym obszarze.
- Kliknięcie dowolnej karty drzewa, także karty w centrum, otwiera panel tej osoby. Na kartach nie ma ikony „Podgląd”, a w nagłówku drzewa nie ma przycisku „Podgląd”.
- „Pokaż na drzewie” prowadzi na `tree/{id}?person={id}` i panel zostaje otwarty. Gdy osoba jest na bieżącym arkuszu, adres niesie też `spouse` z kontekstu jej karty. Gdy osoba jest już centrum drzewa, akcji nie ma.
- Przejście do krewnego z zakładki Rodzina podmienia treść panelu. Nie ma przycisku „Wstecz”. Zamknięcie (X, Escape) zdejmuje tylko `?person`: centrum, związek, filtry i przewinięcie zostają.
- Brudna sekcja osoby pyta paskiem „Masz niezapisane zmiany w …” (Porzuć/Zapisz) w panelu przy zamknięciu, otwarciu innej osoby (karta, lista, Rodzina, źródło), „Pokaż na drzewie”, przejściu do źródła, zmianie zakładki, „Ustaw jako probanta” i „Usuń osobę…”.
- „Potwierdza” w panelu źródła zastępuje panel źródła panelem osoby (jeden panel naraz). Przy niezapisanym formularzu źródła najpierw pyta „Odrzucić niezapisane zmiany?”.
- Przełączenie związku i dodanie małżonka (ze slotu i z menu związku) zostawiają otwarty panel.
- Cudzy lub nieistniejący `?person=` nadal daje 404 na każdej stronie-gospodarzu.
- Weryfikacja: Pest (przekierowanie małżonka z `person`, istniejące 404), Vitest (podpowiedzi związku), istniejące E2E zielone po przejściu lokatorów na regiony, nowy spec E2E przepływu karta → panel → krewny → zamknięcie oraz ręczna ocena przy 1366 px.

### Key Discoveries:

- `withPanel` przy otwarciu usuwa inne klucze paneli (`side-panel-url.ts:35-53`), a `panelVisitOptions.only` obejmuje wszystkie propsy paneli (`:17-27`). Wejście z `/sources?source=X` ustawia więc `?person=Y` bez `source`, a `sourcePanel` wraca jako `null`. Zastąpienie panelu źródła dzieje się samo, bez kodu po stronie strony źródeł.
- Wayfinder obsługuje `mergeQuery` (`resources/js/wayfinder/index.ts:28,76-81`), które scala parametry z bieżącym `window.location`. Przełączenie związku może zachować `person` bez ręcznego składania adresu.
- `back()` i odczyt Referera to już przyjęty wzorzec przenoszenia stanu adresu przez zapis (`context/archive/2026-10-05-source-side-panel/plan.md`, „Critical Implementation Details”). Przekierowanie po dodaniu małżonka może czytać `person` z `url()->previous()`.
- `UnsavedChangesBar` jest `absolute inset-x-0 bottom-0` (`section-editor.tsx:267-269`). Sekcja `SidePanel` jest pozycjonowana (`fixed` / `md:sticky`), więc pasek zakotwiczy się w panelu.
- Testy serwera z ręcznie złożonym `?source=&person=` (`tests/Feature/PersonsTest.php:965-971`, `tests/Feature/OwnershipBoundariesTest.php:174-194`) sprawdzają resolver i granicę 404, a nie to, co składa klient. Zostają bez zmian. Jeden panel naraz gwarantuje `withPanel`.

## What We're NOT Doing

- Zmiany danych panelu, resolvera `?person=` ani kształtu `BuildPersonPreview`: prop nadal nazywa się `personPreview`, a komponenty `PersonPreviewLink`/`PersonPreviewProvider` zachowują nazwy, żeby nie rozlewać diffu.
- Dodawanie osoby w panelu: `PersonCreateDialog` zostaje modalem, a `store` dalej przekierowuje na `/tree/{id}?person={id}`, co otworzy już panel (decyzja z badań nr 4).
- Historia „Wstecz” między krewnymi (FR-028 pozwala ją usunąć).
- Przywracanie ręcznie przesuniętego widoku drzewa po zamknięciu panelu i przycisk „Dopasuj widok”. „To samo miejsce” znaczy: ten sam fokus, związek, strona i przewinięcie. Faza 3 sprawdza ręcznie, że przesunięty fokus pozostaje widoczny.
- Strażnik sekcji osoby na nawigacjach strony spoza panelu: menu boczne, „Wróć do probanta”, sloty i otwarcie innego typu panelu z listy strony (np. źródła z listy `/sources`, gdy otwarta jest osoba). Tak samo działa panel źródła (decyzja planu: strażnik na akcjach panelu).
- Wyróżnienie na drzewie karty osoby otwartej w panelu.
- Zmiana etykiety „Pokaż na drzewie”: zostaje ta sama na każdej stronie (decyzja z badań nr 1).
- Zmiany testów serwera z `?source=&person=` (patrz Key Discoveries).

## Implementation Approach

Kolejność: najpierw powłoka (faza 1). Panel zastępuje modal przy niezmienionych punktach wejścia, a istniejące E2E i Pest sprawdzają, że lokatory `dialog` i 404 przetrwały. Dopiero potem zmienia się zachowanie drzewa (faza 2): kliknięcie karty, przecentrowanie z panelu, zachowanie `?person` przy związku i małżonku. Na końcu nowy spec E2E i ocena układu przy 1366 px (faza 3).

Panel osoby jest hostowany w layoucie, nie na stronach, więc globalny resolver dalej obsługuje każdą stronę bez pracy serwera (decyzja z badań nr 2). Podpowiedzi związku do przecentrowania panel liczy sam czystą funkcją z propu `sheet` strony drzewa (`usePage().props`): mapa `personId → spouse` i id centrum. Częściowa wizyta z `only` = propsy paneli zostawia `sheet` w propsach, a na innych stronach go nie ma, więc nie ma rejestru ani nieaktualnych podpowiedzi.

## Critical Implementation Details

- **Strażnik sekcji musi owijać stronę, nie tylko panel.** `SectionEditorProvider` przechodzi do `AppLayout` nad `AppLayoutTemplate`, z `personId` przyjmującym `null`, gdy panel jest zamknięty. Wtedy `PersonPreviewLink` na kartach, w wierszach listy i w panelu źródła widzi `isDirty` i kieruje kliknięcie do `requestExit`. Reset stanu przy zmianie osoby (`section-editor.tsx:83-88`) musi dalej działać, także przy przejściu `id → null → id`.
- **Podpowiedzi związku z propsów strony.** Panel czyta `sheet` z `usePage().props` ze strażnikiem typu (`'sheet' in props`), bo globalne `PageProps` go nie mają; tylko `tree/show` przekazuje `sheet`. Bez `sheet` (inna strona, `sheet === null`) podpowiedzi to `null`. Gdy panel osoby z bieżącego centrum dostałby „Pokaż na drzewie” bez `spouse`, przełączyłby związek na pierwszy, dlatego akcja jest ukryta dla `focusId`.
- **Escape w panelu z brudną sekcją.** `SidePanel` zamyka na Escape przez `onRequestClose`, a panel osoby przekazuje tam `requestExit(close)`. Escape w otwartym polu wyboru lub menu w sekcji jest już `defaultPrevented` przez Radix, więc nie zamknie panelu.

## Phase 1: Panel osoby zamiast modala

### Overview

Modal podglądu zastępuje panel `SidePanel` hostowany w layoucie. Punkty wejścia przechodzą na wspólną nawigację paneli, strażnik sekcji obejmuje całą stronę, a wejście ze źródła pilnuje formularza źródła. Lokatory E2E sekcji przechodzą na regiony (F9). Drzewo zachowuje się jeszcze jak dziś: ikona „Podgląd” otwiera teraz panel.

### Changes Required:

#### 1. Panel osoby w layoucie

**File**: `resources/js/components/persons/person-panel.tsx` (nowy, zastępuje `person-preview-dialog.tsx`, który znika), `resources/js/layouts/app-layout.tsx`

**Intent**: Zastąpić `Dialog` panelem `SidePanel` z treścią `PersonModal`, hostowanym w layoucie wewnątrz `SidePanelSlotProvider`, tak żeby zwężał każdą stronę.

**Contract**: `PersonPanel` renderuje się, gdy `usePage().props.personPreview !== null`, jako `<SidePanel variant="standard" title={person.display_name} description={lifespan ?? 'Podgląd osoby'} contentKey={person.id} onRequestClose={() => requestExit(close)} actions={…}>`. `actions` zawiera „Pokaż na drzewie” (ukryte poniżej `sm` jak dziś) i menu „⋯”. `UnsavedChangesBar` renderuje się w panelu tylko przy `hasPendingExit`. W `AppLayout` kolejność od zewnątrz to: `PersonPreviewProvider` → `SidePanelSlotProvider` → `SectionEditorProvider personId={personPreview?.person.id ?? null}` → `AppLayoutTemplate` + `PersonPanel`.

#### 2. Nagłówek i zakładki bez kontekstu Radix Dialog

**File**: `resources/js/components/persons/person-modal-header.tsx`, `resources/js/components/persons/person-modal.tsx`

**Intent**: Nazwę i lata życia niesie teraz tytuł i opis powłoki, więc nagłówek osoby traci `DialogTitle`/`DialogDescription` oraz prop `leading` („Wstecz”). Akcje przechodzą do `actions` panelu, a w treści zostaje wiersz z awatarem i chipami (płeć, wyznanie, „Probant”). Zakładki mają mieć jeden przewijany obszar, czyli body panelu, i dopasowane wcięcia zamiast własnego `overflow-y-auto px-6` w każdym `TabsContent`.

**Contract**: Eksport akcji nagłówka (np. `PersonPanelActions({ person, isRootPerson })`) osobno od wiersza tożsamości. „Pokaż na drzewie” w tej fazie prowadzi na `tree/{id}?person={id}` (bez `spouse`, który dochodzi w fazie 2) przez `guardClick`. `DeletePersonDialog` i `useSetRootPerson` bez zmian.

#### 3. Nawigacja panelu osoby przez wspólny rejestr

**File**: `resources/js/components/persons/person-preview-provider.tsx`, `resources/js/components/persons/person-preview-link.tsx`

**Intent**: Usunąć `withPerson`, `previewVisitOptions` i stos „Wstecz” (`stack`/`push`/`back`). Provider opiera `hrefFor`/`open`/`close` na `useSidePanelNavigation('person')` (impl-review F3 z F-02). Link używa `panelVisitOptions`, a przy brudnej sekcji (teraz także poza panelem) oddaje wizytę do `editor.requestExit`.

**Contract**: `usePersonPreview()` zwraca `{ currentId, hrefFor, open, close }`. `PersonPreviewLink` zachowuje prawdziwy `href` (środkowy klik, nowa karta) i zwykłe propsy `Link`.

#### 4. Strażnik sekcji z wartością `null`

**File**: `resources/js/components/persons/section-editor.tsx`

**Intent**: Pozwolić providerowi żyć w layoucie, gdy żaden panel nie jest otwarty, i zaktualizować komentarze „null outside the modal”.

**Contract**: `SectionEditorProvider({ personId: number | null })`. Zmiana `personId` (w tym na `null` i z `null`) resetuje `edit`, `isDirty` i `pending`. `useOptionalSectionEditor` zostaje dla komponentów renderowanych także poza `AppLayout`. `PersonCreateDialog` (`person-create-dialog.tsx:42`) zachowuje własny `SectionEditorProvider personId={0}`, który przesłania ten z layoutu, żeby stan brudny formularza tworzenia nie mieszał się z panelem osoby.

#### 5. Wejście „Potwierdza” z panelu źródła ze strażnikiem źródła

**File**: `resources/js/components/sources/source-panel.tsx`, `resources/js/pages/sources/index.tsx`

**Intent**: Panel osoby zastępuje teraz panel źródła, więc kliknięcie „Potwierdza” przy niezapisanym formularzu źródła musi najpierw zapytać „Odrzucić niezapisane zmiany?”.

**Contract**: Bez nowego API w `useUnsavedChangesGuard`. `SourcePanel` dostaje prop `onPersonLinkClick: (event, personId) => void`. Strona źródeł składa go z istniejących `isPlainClick` (`section-editor.tsx`) i `guard`: przy zwykłym kliknięciu `event.preventDefault()` i `guard(() => router.visit(withPanel(liveUrl, 'person', id), panelVisitOptions))`. `guard` wykonuje wizytę od razu albo po „Odrzuć”. Modyfikowane kliknięcia przechodzą bez zmian.

#### 6. Regiony sekcji Rodziny i Źródeł (F9)

**File**: `resources/js/components/persons/family-tab.tsx`, `resources/js/components/persons/sources-tab.tsx`, `tests/e2e/support/navigation.ts`

**Intent**: `<section>` dostaje `aria-labelledby` wskazujące swój `h3`, więc staje się nazwanym regionem. `familySection` i `sourcesGroup` szukają wtedy `dialog.getByRole('region', { name })` zamiast `section` filtrowanej po nagłówku, a komentarz o „jedynym lokatorze strukturalnym” znika.

**Contract**: Sygnatury `familySection(dialog, name)` i `sourcesGroup(dialog, heading)` bez zmian. Specy nie zmieniają się. Komentarz nagłówka `navigation.ts:1-4` aktualizuje, co pomocnicy wciąż celowo pomijają.

#### 7. Testy Pest odwołujące się do modala

**File**: `tests/Pest.php` (docblock `personPreviewUrl`), ewentualnie opisy testów w `tests/Feature/PersonsTest.php`

**Intent**: Opisy mówią „panel” zamiast „modal”. Asercje bez zmian.

**Contract**: Brak zmian zachowania serwera w tej fazie.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Vitest przechodzi: `vendor/bin/sail npm run test:js`
- Pest dla osoby i granic przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/SourcesTest.php`
- Istniejące E2E przechodzą bez zmian w specach: `npx playwright test`
- W kodzie nie ma już `PersonPreviewDialog`, `previewVisitOptions` ani `withPerson`: `grep -rn "PersonPreviewDialog\|previewVisitOptions\|withPerson" resources/js` zwraca pusto

#### Manual Verification:

- `/tree/{id}?person={id}` przy 1366 px: panel po prawej, drzewo zwężone obok, zakładki i edycja sekcji Urodzenie działają, jeden pasek przewijania w panelu
- Brudna sekcja + X, Escape, ikona „Podgląd” innej karty: każde pokazuje pasek „Masz niezapisane zmiany” w panelu; „Porzuć” wykonuje akcję, „Zapisz” zapisuje i wykonuje
- `/sources?source={id}` → „Potwierdza”: panel osoby zastępuje panel źródła; przy zmienionym opisie źródła najpierw pyta „Odrzucić niezapisane zmiany?”
- Poniżej `md` panel jest pełnoekranowy, a zamknięcie oddaje fokus elementowi, który go otworzył

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Kliknięcie karty i przecentrowanie z panelu

### Overview

Karta drzewa otwiera panel, a przecentrowanie przechodzi do panelu z zachowanym związkiem. `?person=` przeżywa przełączenie związku i dodanie małżonka.

### Changes Required:

#### 1. Karta otwiera panel

**File**: `resources/js/components/tree/sheet-person-card.tsx`, `resources/js/pages/tree/show.tsx`

**Intent**: Ciało każdej karty, także karty fokusu, staje się `PersonPreviewLink`. Ikona „Podgląd” na karcie i przycisk „Podgląd” w nagłówku drzewa znikają (FR-016). Prop `spouse` przestaje sterować linkiem karty.

**Contract**: `SheetPersonCard({ person, isFocus })`. Nazwa dostępności linku to treść karty (płeć sr-only, imię i nazwisko, lata). Wygląd fokusu (`cardFocus`) i hover (`cardInteractive`) zostają.

#### 2. Podpowiedzi związku do przecentrowania

**File**: `resources/js/lib/centre-hints.ts` (nowy) + `resources/js/lib/centre-hints.test.ts`, `resources/js/components/tree/family-sheet.tsx`, `resources/js/components/persons/person-panel.tsx`

**Intent**: Zachować dzisiejszy wybór związku po przecentrowaniu, np. dziadek → związek z babcią, gdy przecentrowanie przechodzi do panelu. Czysta funkcja wylicza z `sheet` mapę osoby na `spouse` z tą samą logiką co dzisiejsze `slotSpouse` (dziadkowie w parach, rodzice, partner → `focus.id`). `PersonPanel` wywołuje ją na `sheet` z `usePage().props`, gdy strona go ma; bez rejestru w providerze.

**Contract**: `centreHints(sheet: FamilySheet): { focusId: number; spouses: Record<number, string> }`. Wartość `spouse` to id partnera jako string albo `'unknown'`, a osoba bez pary nie ma wpisu. Przy osobie widocznej na arkuszu dwa razy wygrywa pierwsze wystąpienie w kolejności dziadkowie → rodzice → partner. `slotSpouse` przenosi się do `centre-hints.ts` i jest stamtąd importowane. W panelu: `const hints = sheet ? centreHints(sheet) : null`, gdzie `sheet` pochodzi z `usePage().props` po zawężeniu `'sheet' in props`.

#### 3. „Pokaż na drzewie” z `person` i `spouse`

**File**: akcje nagłówka panelu z fazy 1 (`resources/js/components/persons/person-modal-header.tsx` lub `person-panel.tsx`)

**Intent**: Przecentrowanie zostawia panel otwarty na tej samej osobie (decyzja z badań nr 1) i wybiera związek z podpowiedzi. Gdy osoba jest centrum bieżącego drzewa, akcja (przycisk i pozycja w „⋯”) nie pokazuje się.

**Contract**: `treeShow(person.id, { query: { person: person.id, ...(spouse ? { spouse } : {}) } })`, gdzie `spouse = hints?.spouses[person.id]`. Ukryte, gdy `hints?.focusId === person.id`. Wizyta przez `guardClick` jak dziś.

#### 4. Przełączenie związku zachowuje panel

**File**: `resources/js/components/tree/union-node.tsx`

**Intent**: Przełączenie związku nie zamyka otwartego panelu.

**Contract**: `treeShow(focus.id, { mergeQuery: { spouse } })` zamiast `query`. Opcje wizyty bez zmian (`preserveScroll`).

#### 5. Dodanie małżonka zachowuje panel

**File**: `app/Http/Controllers/PersonSpouseController.php`, `tests/Feature/MarriagesTest.php`

**Intent**: `attach` i `store` przenoszą `person` z adresu poprzedniej strony (Referer, jak `back()`) do przekierowania, więc panel otwarty przed dodaniem małżonka zostaje otwarty.

**Contract**: Przekierowanie na `route('tree.show', ['person' => $person, 'spouse' => $spouse->id])` z doklejonym `&person=<id>` (jak `personPreviewUrl` w `tests/Pest.php:104-109`) tylko wtedy, gdy zapytanie `url()->previous()` ma `person` złożone z samych cyfr. Inaczej adres jest jak dziś. Klucza zapytania nie da się przekazać w tablicy parametrów `route()`, bo `person` to już parametr trasy. Wspólny prywatny helper dla `attach` i `store`. Nowe testy: z Refererem `/tree/{focus}?person={x}` przekierowanie to `tree/{focus}?spouse={s}&person={x}`. Bez Referera i z nieliczbowym `person` adres jest jak dziś. Istniejące asercje (`MarriagesTest.php:302,322,384`) zostają.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Vitest `centreHints` przechodzi (pary dziadków, rodzice, partner, `unknown`, osoba bez pary, pierwsze wystąpienie): `vendor/bin/sail npm run test:js`
- Pest małżeństw przechodzi, w tym nowe przypadki przekierowania z `person`: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php`
- Istniejące E2E slotów przechodzą: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts`

#### Manual Verification:

- Kliknięcie karty dziadka otwiera jego panel; „Pokaż na drzewie” centruje na dziadku ze związkiem z babcią i panel zostaje otwarty
- Kliknięcie karty centrum otwiera panel bez akcji „Pokaż na drzewie”; nagłówek drzewa nie ma „Podgląd”
- Przy otwartym panelu: przełączenie związku i dodanie małżonka ze slotu zostawiają panel otwarty
- Przy otwartym panelu dodanie rodzica i dziecka ze slotów działa jak dziś (modal slotu nad drzewem, panel zostaje)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: E2E przepływu panelu i ocena przy 1366 px

### Overview

Nowy spec E2E przypina zachowanie, które zmienia S-06: karta → panel właściwej osoby obok drzewa → krewny podmienia treść → zamknięcie zostawia centrum. Ręczna ocena sprawdza szerokość `standard` i zwężanie drzewa.

### Changes Required:

#### 1. Spec przepływu panelu osoby

**File**: `tests/e2e/person-side-panel.spec.ts` (nowy), w razie potrzeby nowy pomocnik w `tests/e2e/support/navigation.ts`

**Intent**: Ryzyko „UI wysyła akcję do niewłaściwej osoby” (`context/foundation/test-plan.md:86`) przy nowym wejściu kliknięciem karty. Spec powstaje przez `/10x-e2e` (eksploracja `playwright-cli`, przegląd względem pięciu antywzorców, weryfikacja przez celowe wprowadzenie błędu).

**Contract**: Dane przez `tests/e2e/support/api.ts` (osoba + rodzic z sufiksem timestamp, sprzątanie). Wejście `gotoTree`. Kliknięcie karty rodzica (`getByRole('link', { name: … })`) → `getByRole('dialog', { name: rodzic })` widoczny razem z nagłówkiem drzewa (`heading level 1` z imieniem centrum). Rodzina → dziecko podmienia dialog na centrum. „Zamknij panel” → dialog znika, adres to `/tree/{centrum}` bez `person`, nagłówek bez zmian. Bez `waitForTimeout`.

### Success Criteria:

#### Automated Verification:

- Nowy spec przechodzi: `npx playwright test tests/e2e/person-side-panel.spec.ts`
- Cały pakiet E2E przechodzi: `npx playwright test`
- Pełny Pest przechodzi: `vendor/bin/sail artisan test --compact`

#### Manual Verification:

- Celowo zepsuty link karty (np. `personId` rodzica zamieniony na id centrum) daje czerwony spec; po cofnięciu zielony
- 1366×768: panel `standard` (~410 px) mieści zakładki i edycję sekcji Urodzenie/Małżeństwo bez poziomego przewijania; nieruszone drzewo dopasowuje się po otwarciu i po zamknięciu panelu
- 1366×768: przesunięty ręcznie widok → otwarcie i zamknięcie panelu → karta centrum pozostaje widoczna (bez przywracania transformacji)
- Lista osób (widok Lista i Tabela) i strona miejscowości: panel zwęża treść obok siebie

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `centreHints(sheet)` (Vitest): pary dziadków po obu stronach, rodzice, partner → id centrum, `'unknown'` dla nieznanego partnera, osoba bez pary bez wpisu, pierwsze wystąpienie przy powtórzonej osobie.
- `side-panel-url.test.ts` bez zmian: pokrywa już `withPanel`, które teraz obsługuje osobę.

### Integration Tests:

- Pest `MarriagesTest`: przekierowanie `attach`/`store` z `person` z Referera; bez Referera i z nieliczbowym `person` bez zmian.
- Pest `PersonsTest`, `OwnershipBoundariesTest`: bez zmian zachowania, cudzy `?person=` dalej 404 na każdym gospodarzu.
- E2E: istniejące specy przez `openPerson` i regiony (faza 1) oraz nowy `person-side-panel.spec.ts` (faza 3).

### Manual Testing Steps:

1. `/tree/{id}` → klik karty rodzica → panel rodzica obok drzewa.
2. Rodzina → dziecko → panel podmienia treść, bez „Wstecz”.
3. Edycja Urodzenia bez zapisu → klik innej karty → pasek „Masz niezapisane zmiany” → „Porzuć”.
4. „Pokaż na drzewie” u dziadka → centrum na dziadku ze związkiem z babcią, panel otwarty.
5. Przełączenie związku, dodanie małżonka → panel otwarty.
6. `/sources?source={id}` → zmiana opisu → „Potwierdza” → pytanie o odrzucenie → panel osoby zamiast źródła.

## Performance Considerations

Otwarcie i podmiana panelu to częściowe wizyty z `only` = propsy paneli, jak dziś, a `sheet` nie jest przeliczany. Podpowiedzi związku to jedna mapa liczona w panelu z `sheet`.

## Migration Notes

Bez zmian danych i schematu. Zakładki przeglądarki z `?person=` działają dalej i otwierają panel.

## References

- Research: `context/changes/person-side-panel/research.md`
- Powłoka F-02: `context/archive/2026-10-04-side-panel-shell/` (impl-review F3)
- Wzorzec konsumenta panelu: `context/archive/2026-10-05-source-side-panel/plan.md`, `resources/js/components/sources/source-panel.tsx:110-187`
- Dopasowanie drzewa: `context/archive/2026-10-05-tree-fits-screen/plan-brief.md:28`, `resources/js/components/zoom-canvas.tsx:109-147`
- F9 lokatory: `context/archive/2026-10-04-critical-flows-e2e-guard/follow-ups/review-fixes.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Panel osoby zamiast modala

#### Automated

- [x] 1.1 Format i lint przechodzą: `vendor/bin/sail npm run check` — dd3c67d
- [x] 1.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — dd3c67d
- [x] 1.3 Vitest przechodzi: `vendor/bin/sail npm run test:js` — dd3c67d
- [x] 1.4 Pest dla osoby i granic przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/OwnershipBoundariesTest.php tests/Feature/SourcesTest.php` — dd3c67d
- [x] 1.5 Istniejące E2E przechodzą bez zmian w specach: `npx playwright test` — dd3c67d
- [x] 1.6 W kodzie nie ma już `PersonPreviewDialog`, `previewVisitOptions` ani `withPerson`: `grep -rn "PersonPreviewDialog\|previewVisitOptions\|withPerson" resources/js` zwraca pusto — dd3c67d

#### Manual

- [x] 1.7 `/tree/{id}?person={id}` przy 1366 px: panel po prawej, drzewo zwężone obok, zakładki i edycja sekcji Urodzenie działają, jeden pasek przewijania w panelu — dd3c67d
- [x] 1.8 Brudna sekcja + X, Escape, ikona „Podgląd” innej karty: każde pokazuje pasek „Masz niezapisane zmiany” w panelu; „Porzuć” wykonuje akcję, „Zapisz” zapisuje i wykonuje — dd3c67d
- [x] 1.9 `/sources?source={id}` → „Potwierdza”: panel osoby zastępuje panel źródła; przy zmienionym opisie źródła najpierw pyta „Odrzucić niezapisane zmiany?” — dd3c67d
- [x] 1.10 Poniżej `md` panel jest pełnoekranowy, a zamknięcie oddaje fokus elementowi, który go otworzył — dd3c67d

### Phase 2: Kliknięcie karty i przecentrowanie z panelu

#### Automated

- [x] 2.1 Format i lint przechodzą: `vendor/bin/sail npm run check` — 9a8b595
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — 9a8b595
- [x] 2.3 Vitest `centreHints` przechodzi (pary dziadków, rodzice, partner, `unknown`, osoba bez pary, pierwsze wystąpienie): `vendor/bin/sail npm run test:js` — 9a8b595
- [x] 2.4 Pest małżeństw przechodzi, w tym nowe przypadki przekierowania z `person`: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php` — 9a8b595
- [x] 2.5 Istniejące E2E slotów przechodzą: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts` — 9a8b595

#### Manual

- [x] 2.6 Kliknięcie karty dziadka otwiera jego panel; „Pokaż na drzewie” centruje na dziadku ze związkiem z babcią i panel zostaje otwarty — 9a8b595
- [x] 2.7 Kliknięcie karty centrum otwiera panel bez akcji „Pokaż na drzewie”; nagłówek drzewa nie ma „Podgląd” — 9a8b595
- [x] 2.8 Przy otwartym panelu: przełączenie związku i dodanie małżonka ze slotu zostawiają panel otwarty — 9a8b595
- [x] 2.9 Przy otwartym panelu dodanie rodzica i dziecka ze slotów działa jak dziś (modal slotu nad drzewem, panel zostaje) — 9a8b595

### Phase 3: E2E przepływu panelu i ocena przy 1366 px

#### Automated

- [x] 3.1 Nowy spec przechodzi: `npx playwright test tests/e2e/person-side-panel.spec.ts`
- [x] 3.2 Cały pakiet E2E przechodzi: `npx playwright test`
- [x] 3.3 Pełny Pest przechodzi: `vendor/bin/sail artisan test --compact`

#### Manual

- [x] 3.4 Celowo zepsuty link karty (np. `personId` rodzica zamieniony na id centrum) daje czerwony spec; po cofnięciu zielony
- [x] 3.5 1366×768: panel `standard` (~410 px) mieści zakładki i edycję sekcji Urodzenie/Małżeństwo bez poziomego przewijania; nieruszone drzewo dopasowuje się po otwarciu i po zamknięciu panelu
- [x] 3.6 1366×768: przesunięty ręcznie widok → otwarcie i zamknięcie panelu → karta centrum pozostaje widoczna (bez przywracania transformacji)
- [x] 3.7 Lista osób (widok Lista i Tabela) i strona miejscowości: panel zwęża treść obok siebie
