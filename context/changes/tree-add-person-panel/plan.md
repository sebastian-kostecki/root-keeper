# Dodawanie osób na drzewie w panelu bocznym — Implementation Plan

## Overview

Cztery wejścia dodawania na drzewie (Dodaj ojca/matkę/rodzica, Dodaj dziecko, Dodaj małżonka ze slotu i z menu związku) oraz dwa warianty pustego drzewa przechodzą z modala `PickOrCreatePersonDialog` do jednego panelu bocznego sterowanego kluczem URL `?add=…`. Po zapisie lub wyborze osoby serwer przekierowuje na ten sam widok drzewa z otwartym podglądem dodanej osoby (`?person=<id>`). Canvas trzyma aktywny slot w kadrze, a na wąskim ekranie kotwiczy na karcie osoby centralnej. Zmiana zamyka zarzuty C1–C6 z `research.md` i przepisuje regułę „small pickers stay modal” w `CLAUDE.md`.

## Current State Analysis

- Modal: `resources/js/components/tree/pick-or-create-person-dialog.tsx:63-249` (`Dialog` + pełnoekranowy `FormDialogContent`, lista ↔ formularz, `useOptionalList`, brak strażnika). Trzy konfiguracje: `parent-slot-dialog.tsx:44-106`, `add-child-dialog.tsx:25-91`, `add-spouse-dialog.tsx:35-103`. Montowane w `family-sheet.tsx:66-76` (sloty rodziców przez `Slot`), `:287-294` (dziecko), `:394-402` (slot małżonka), `union-node.tsx:205-232` (menu „Dodaj małżonka”).
- Ścieżka zapisu gubi id nowej osoby: `PersonParentController.php:26`, `PersonChildController.php:31,53`, `ParentChildRelationController.php:28` → `back()`. `PersonSpouseController.php:52-64` (`showNewUnion`) przenosi stary `person` z Referera. Jedyny wzorzec „otwórz panel nowej osoby”: `PersonController.php:106`. Wszyscy wywołujący te endpointy są na stronie drzewa (jedyny inny użytkownik `ParentChildRelationController` to `destroy` w `remove-relation-dialog.tsx:48`).
- Panele: rejestr `resources/js/lib/side-panel-url.ts:6-13`, `withPanel()` usuwa klucze innych paneli (`:44-48`); nawigacja `hooks/use-side-panel-navigation.ts`; powłoka `components/side-panel.tsx:117-254` (`role="dialog"`, `aria-labelledby` = tytuł, fokus nagłówka na `contentKey`, Escape, przywracanie fokusu).
- Strażnik: `components/persons/section-editor.tsx:89-278` — `router.on('before')` blokuje GET-y opuszczające rekord (`keepsRecord` porównuje `searchParams.get(key) === String(id)`), przepuszcza POST; wzorzec trybu tworzenia `persons/person-create-dialog.tsx:40-46,171-221` (zagnieżdżony provider, brudność przez `serialize` + `MutationObserver`, `registerSubmit`).
- Pusty stan: `pages/tree/show.tsx:47-71` (doraźny `Card` + link do `/persons`); `TreeController.php:20-31` zwraca `sheet: null` zarówno dla drzewa bez osób, jak i bez probanta. Probanta ustawia tylko `app/Actions/Trees/SetTreeRootPerson.php:13-18`. Współdzielony `components/empty-state.tsx:3-31`.
- Kadr: `zoom-canvas.tsx:66-113` (`fit()` → `lib/fit-view.ts:28-54`), `ResizeObserver` → `schedule` → `fit()` tylko gdy `!movedRef` (`:118-126,151-153`); `onFocus` (`:248-295`) przesuwa fokusowany element do kadru minimalnym ruchem i oznacza widok jako ruszony. `FamilySheet` kotwiczy na `coupleRef` (`family-sheet.tsx:285,304,372-376`), który ma szerokość całej siatki (`col-span-8`, ~101rem) — na 390 px karta osoby centralnej wypada ~109 px poza kadr (zrzut `before/04`), a po otwarciu panelu slot „Dodaj ojca” wypada z kadru (`before/08`).
- Tokeny: `family-sheet.tsx:428` `h-[30px]`, `union-node.tsx:117` `h-[34px]` (C6).
- Luki 7 stanów (research „Macierz 7 stanów”): wiersz kandydata bez `focus-visible` (`person-candidate-list.tsx:116`), brak spinnera zapisu, kopia błędu wczytania „Zamknij okno…” bez ponowienia (`:62-68`).

## Desired End State

- Klik w slot dodawania albo „Dodaj małżonka” w menu związku otwiera po prawej panel `Dodaj … — <osoba>` (ten sam tytuł co dziś) z listą osób z drzewa i przyciskiem „Nowa osoba”; drzewo zwęża się obok, kliknięty slot jest wyróżniony i widoczny w kadrze.
- Wybór kandydata albo zapis formularza tworzy relację i przełącza panel na podgląd tej osoby; drzewo zostaje na tej samej osobie centralnej i tym samym związku (`spouse=`), dodana osoba pojawia się w miejscu slotu.
- Brudny formularz w panelu chroni pasek „Masz niezapisane zmiany w nowej osobie” (Porzuć / Zapisz) przy X, Escape, kliknięciu w kartę lub slot na drzewie i nawigacji.
- `/tree` bez osób pokazuje `EmptyState` z „Dodaj pierwszą osobę” (panel od razu z formularzem); z osobami, ale bez probanta — „Wybierz probanta” (panel z listą i „Nowa osoba”). Zapisana lub wybrana osoba zostaje probantem, strona pokazuje jej arkusz z otwartym podglądem.
- Na 390 px karta osoby centralnej jest w kadrze; na desktopie z otwartym panelem aktywny slot jest w kadrze; po zamknięciu panelu nieruszony widok wraca do dopasowania.
- `CLAUDE.md` „Side panels vs modals” mówi, że dodawanie/wybór osoby na drzewie odbywa się w panelu.

Weryfikacja: Pest (redirecty, proband), Vitest (`side-panel-url`, rozwiązywanie celu `?add=`, wybór kotwicy), trzy specy slotów E2E + przypadek strażnika, zrzuty „po” porównane ze zrzutami „przed”.

### Key Discoveries:

- Kontekst każdego slotu da się jednoznacznie odtworzyć z `FamilySheet` po stronie klienta: slot rodzica = (`ParentSlotTarget.id` dziecka, `role`) w `parents`/`grandparents` (`types/tree.ts:15-21,56-67`), dziecko = `add_child`, małżonek = `add_spouse` + `focus` — `excluded_ids`/`blocked_ids` liczy już `BuildFamilySheet`, więc `?add=` nie potrzebuje resolvera na serwerze.
- `tree-slot-child.spec.ts:72-73` po wyborze dziecka klika slot dziecka ponownie — redirect musi zachować `spouse=<związek>` z bieżącego URL (dziś robi to `back()`).
- `tests/e2e/support/navigation.ts:26-32` szuka `getByRole('dialog', { name: 'Dodaj … — …' })`; `SidePanel` ma `role="dialog"` z nazwą z tytułu (`side-panel.tsx:204-209`), więc lokatory przetrwają przy zachowanym tytule.
- Wspólny użytkownik E2E nie może tracić probanta (`context/foundation/test-plan.md:209`) — pusty stan pokrywa Pest + weryfikacja ręczna.
- `react-zoom-pan-pinch` 4.2: `setTransform` nie klamruje do granic (`index.esm.js:1211-1227`) — odsłanianie slotu liczymy sami, jak `ZoomCanvas.onFocus`.
- `ZoomCanvas` jest współdzielony z `ancestor-chart.tsx:95-100` — nowe propsy muszą być opcjonalne.

## What We're NOT Doing

- „Dodaj osobę” na `/persons` (`PersonCreateDialog`) zostaje modalem — osobna zmiana.
- `MarryCoupleDialog`, potwierdzenia usuwania i `persons/pick-person-dialog.tsx` (pochowani w źródle) zostają modalne.
- Brak resolvera `?add=` na serwerze i propa panelu dodawania — kontekst pochodzi z arkusza.
- Panel wyboru probanta nie otwiera się sam po wejściu na `/tree` — tylko z przycisku w `EmptyState`.
- Brak E2E pustego stanu i drugiego konta E2E.
- Brak `toHaveScreenshot` / kitchen sinka; bramka wizualna to jednorazowe zrzuty w folderze zmiany.
- Bez zmian w `PUT tree/root-person/{person}` (przycisk „Ustaw jako probanta” w nagłówku drzewa i w panelu osoby działa jak dziś).
- Bez zapamiętywania widoku canvasu i bez ręcznego zoomu.

## Implementation Approach

Najpierw serwer (faza 1): wspólny redirect „ten sam widok + podgląd osoby” i endpointy probanta — dzięki temu panel od pierwszego dnia dostaje właściwe odpowiedzi. Potem panel dla slotów (faza 2) z kluczem `add` zarejestrowanym po stronie klienta jako klucz panelu bez propa serwerowego, z zagnieżdżonym `SectionEditorProvider` jako strażnikiem; trzy istniejące specy slotów muszą przejść bez zmian. Faza 3 dokłada tryb `proband` i pusty stan. Faza 4 dotyka współdzielonego `ZoomCanvas` (odsłanianie elementu, kotwica zastępcza) i stylu aktywnego slotu. Faza 5 dopisuje asercje E2E, przepisuje regułę i robi zrzuty „po”.

## Critical Implementation Details

- **Kolejność kadrowania.** Otwarcie panelu zwęża wrapper → `ResizeObserver` → `schedule` → `fit()`. Odsłonięcie aktywnego slotu musi biec w tym samym zaplanowanym przebiegu, *po* `fit()`, inaczej `fit()` (`zoom-canvas.tsx:106`) je nadpisze. Odsłonięcie nie ustawia `movedRef` — inaczej zamknięcie panelu przestanie przywracać dopasowanie (decyzja `context/archive/2026-10-05-tree-fits-screen/plan-brief.md:28`). Żądanie odsłonięcia trzyma flaga w refie (nie domknięcie rAF), bo kolejny `schedule()` z `ResizeObservera` anuluje poprzednią klatkę — szczegóły w fazie 4 §2.
- **Pomiar kotwicy.** `getBoundingClientRect()` zwraca wymiary w bieżącej skali transformacji; przed porównaniem szerokości pary z wrapperem przy skali docelowej trzeba je przeliczyć (podzielić przez bieżącą skalę, pomnożyć przez docelową).
- **Strażnik a własne przeładowania.** Zagnieżdżony provider porównuje `searchParams.get('add')` z wartością rekordu jako string — `router.reload({ only: ['personCandidates'] })` na tym samym URL musi przejść; dlatego `record.id` przyjmuje `string`, a nie stałe `0` jak w `person-create-dialog`.
- **Fokus.** `SidePanel` fokusuje nagłówek przy montowaniu i zmianie `contentKey` (= wartość `add`). Przejście do widoku formularza fokusuje pierwsze pole („Imię”); powrót do listy — pole „Szukaj osoby”. Po udanym zapisie slot znika, więc fokus przejmuje nagłówek panelu nowej osoby (nie przywracamy fokusu na nieistniejący slot).

## Faza 1: Ścieżka zapisu i endpointy probanta

### Overview

Endpointy dodawania przekierowują na bieżący widok z podglądem dodanej/wybranej osoby; nowe endpointy ustawiają probanta z pustego drzewa; `TreeController@index` mówi, czy drzewo ma osoby.

### Changes Required:

#### 1. Wspólny redirect na widok z panelem osoby

**File**: `app/Support/PersonPanelRedirect.php` (nowy, `vendor/bin/sail artisan make:class`)

**Intent**: Jedno miejsce, które z Referera bierze ścieżkę i query (bez hosta — brak otwartego redirectu), usuwa klucze wszystkich paneli i dokłada `person=<id>`, zachowując pozostałe parametry (np. `spouse`). Bez Referera (albo z Refererem z obcego hosta lub ścieżką `//…`) używa podanego fallbacku — do fallbacku też dokłada `person=<id>` (żądania `page.request` w Playwright nie wysyłają Referera, a helpery `createParent`/`createChild` z fazy 5 czytają id z `?person=` w `Location`). Czyta wyłącznie nagłówek `referer`, nie `url()->previous()` (sesja jest wspólna dla równoległych żądań — por. komentarz `PersonSpouseController.php:50`). Parsowanie Referera wzorowane na `MarriageController::urlAfterDelete` (`:68-86`: strażnik hosta i `//`, fallback) — to czwarte miejsce parsujące Referer w repo (obok `SourceController.php:166-170` i usuwanego `showNewUnion`), więc nie wymyślamy nowej odmiany.

**Contract**: `public static function to(Request $request, Person $person, string $fallbackUrl): RedirectResponse`. Klucze paneli usuwane z query: `person`, `marriage`, `add`, `source`, `locality`, `parish`, `demo` (lustro `PANEL_PROPS` + `add` z fazy 2 — PHPDoc wskazuje `resources/js/lib/side-panel-url.ts` jako źródło listy).

#### 2. Kontrolery dodawania

**File**: `app/Http/Controllers/PersonParentController.php`, `PersonChildController.php`, `ParentChildRelationController.php`, `PersonSpouseController.php`

**Intent**: Zamiast `back()` zwracać `PersonPanelRedirect::to(...)` z osobą, którą użytkownik właśnie dodał lub wybrał; wynik akcji (`Person`) przestaje być odrzucany. Małżonek: `showNewUnion` przestaje czytać Referer i buduje `tree.show(person, spouse=<id>)` + `person=<spouse id>`.

**Contract**:
- `PersonParentController@store` → nowy rodzic; fallback `route('tree.show', $person)` (dziecko).
- `ParentChildRelationController@store` → `$parent`; fallback `route('tree.show', $child)`.
- `PersonChildController@attach` → `$child`; `@store` → nowe dziecko (`CreateChildForParents::handle` zwraca `Person`, `:24`); fallback `route('tree.show', $person)`.
- `PersonSpouseController@attach/@store` → `tree.show($person) ?spouse=<spouse>&person=<spouse>`; PHPDoc `showNewUnion` aktualizowany (Referer nie jest już czytany).
- Toasty bez zmian.

#### 3. Proband z pustego drzewa

**File**: `app/Http/Controllers/TreeProbandController.php` (nowy), `app/Actions/Trees/CreateProband.php` (nowy), `app/Http/Requests/TreeProbandAttachRequest.php`, `app/Http/Requests/TreeProbandStoreRequest.php` (nowe), `routes/web.php`

**Intent**: Para `attach`/`store` jak w `PersonChildController`/`PersonSpouseController`: wybór istniejącej osoby albo utworzenie nowej — w obu przypadkach osoba zostaje probantem, a odpowiedź otwiera arkusz probanta z jej podglądem. Oba endpointy działają tylko, gdy drzewo nie ma probanta (nieaktualny panel nie podmieni probanta po cichu).

**Contract**:
- Trasy: `POST tree/proband/attach` → `tree.proband.attach`; `POST tree/proband` → `tree.proband.store`.
- `TreeProbandAttachRequest`: `person_id` required integer; `withValidator()` z `$validator->after(...)` → błąd `proband` „Drzewo ma już probanta.” gdy `root_person_id !== null` (konwencja `PersonParentStoreRequest.php:39-54`). Kontroler: `$tree->people()->findOrFail(person_id)` (cudza osoba → 404), `Gate::authorize('update', $person)`, `SetTreeRootPerson`.
- `TreeProbandStoreRequest extends PersonStoreRequest` + ta sama reguła w `withValidator()`, które najpierw woła `parent::withValidator($validator)` (walidacja pełnych dat rodzica).
- `CreateProband::handle(Tree $tree, array $data): Person` — w `DB::transaction`: `CreatePerson::handle` + `SetTreeRootPerson::handle`.
- Redirect: `route('tree.index').'?person=<id>'`; toasty `Ustawiono probanta` (attach) / `Dodano osobę i ustawiono probanta` (store).
- Po dodaniu tras: `vendor/bin/sail artisan wayfinder:generate`.

#### 4. Informacja o osobach na pustym drzewie

**File**: `app/Http/Controllers/TreeController.php`

**Intent**: `index` przekazuje, czy drzewo ma jakiekolwiek osoby — strona wybiera wariant pustego stanu.

**Contract**: nowy prop `hasPeople: bool` w `index` (`$tree->people()->exists()`); `show` bez zmian (zawsze ma osobę). Typ `Props` w `pages/tree/show.tsx` dostaje `hasPeople?: boolean`.

#### 5. Testy Pest

**File**: `tests/Feature/ParentChildRelationsTest.php`, `tests/Feature/MarriagesTest.php`, `tests/Feature/TreeTest.php`, `tests/Feature/TreeProbandTest.php` (nowy, `vendor/bin/sail artisan make:test --pest TreeProbandTest`)

**Intent**: Asercje redirectów zmieniają się celowo (nowe zachowanie), żaden test nie jest usuwany; nowe endpointy mają happy path i 404 dla cudzego rekordu.

**Contract**:
- `ParentChildRelationsTest.php:24` → `persons.index?person=<parent>`; `:350`, `:376`, `:470`, `:658` → `$treeUrl` z zachowanym `spouse` i `person=<dodana/wybrana osoba>`.
- Nowy test w `ParentChildRelationsTest`: Referer `tree.show(child)?spouse=…&add=father:<id>&person=<inny>` → redirect zachowuje `spouse`, usuwa `add`, `person` = nowy rodzic.
- Nowy test w `ParentChildRelationsTest`: bez Referera → redirect na fallback `tree.show(child)?person=<nowy rodzic>`; Referer z obcego hosta → ten sam fallback.
- `MarriagesTest.php:308`, `:363`, `:425` → oczekiwany URL z `&person=<spouse>`; `:328`, `:447` przepisane na „otwiera panel nowego małżonka zamiast panelu otwartego wcześniej”; `:340` („ignoruje URL z sesji”) i `:351` („ignoruje nienumeryczne `person`”) zostają z nowym oczekiwanym URL-em (Referer nie wpływa na wynik).
- `TreeTest.php:112` rozszerzony o `hasPeople` false; nowy przypadek: osoby bez probanta → `sheet` null, `hasPeople` true.
- `TreeProbandTest.php`: attach ustawia probanta i przekierowuje na `tree.index?person=<id>`; store tworzy osobę i probanta w jednym żądaniu; oba odmawiają przy istniejącym probancie (błąd `proband`, brak zmian w bazie); attach z osobą innego użytkownika → 404; goście → login; store bez imienia i nazwiska → błąd walidacji i brak probanta.

### Success Criteria:

#### Automated Verification:

- Testy fazy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/TreeProbandTest.php tests/Feature/WriteTargetsTest.php tests/Feature/OwnershipBoundariesTest.php`
- Larastan przechodzi: `vendor/bin/sail bin phpstan analyse`
- Formatowanie PHP: `vendor/bin/sail bin pint --dirty --format agent`
- Wayfinder wygenerowany i typy przechodzą: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- Na drzewie dodanie rodzica przez obecny modal otwiera po zapisie podgląd nowego rodzica obok drzewa (modal jeszcze istnieje — weryfikacja samego redirectu).

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 2: Panel dodawania dla slotów drzewa

### Overview

Klucz `?add=` otwiera jeden panel dodawania (lista + formularz + strażnik) dla czterech wejść; modal i trzy jego konfiguracje znikają. Zamyka C1 i luki stanów listy.

### Changes Required:

#### 1. Klucz panelu po stronie klienta

**File**: `resources/js/lib/side-panel-url.ts`, `resources/js/lib/side-panel-url.test.ts`, `resources/js/hooks/use-side-panel-navigation.ts`

**Intent**: `add` jest pełnoprawnym kluczem panelu dla `withPanel` (otwarcie dodawania zamyka panel osoby i odwrotnie), ale nie ma propa serwerowego, więc nie trafia do `panelVisitOptions.only`.

**Contract**: `CLIENT_PANEL_KEYS = ['add'] as const`; `PanelKey = keyof typeof PANEL_PROPS | (typeof CLIENT_PANEL_KEYS)[number]`; `withPanel` czyści klucze z obu zbiorów; `close()` w `useSidePanelNavigation` zeruje prop tylko dla kluczy z `PANEL_PROPS`. Vitest: otwarcie `add` usuwa `person`/`marriage`, otwarcie `person` usuwa `add`, `panelVisitOptions.only` bez `add`.

#### 2. Strażnik z rekordem o id typu string

**File**: `resources/js/components/persons/section-editor.tsx`

**Intent**: Provider przyjmuje rekord identyfikowany wartością klucza (np. `father:987`), żeby przeładowania tego samego URL-a nie były blokowane.

**Contract**: `record: { key: PanelKey; id: number | string } | null`; porównanie `keepsRecord` przez `String(recordId)`. Istniejący konsumenci (`app-layout.tsx:21-25`, `person-create-dialog.tsx:42`) bez zmian zachowania.

#### 3. Rozwiązanie celu `?add=` z arkusza

**File**: `resources/js/lib/add-person-target.ts` (nowy) + `resources/js/lib/add-person-target.test.ts` (nowy)

**Intent**: Czysta funkcja zamieniająca wartość klucza na cel dodawania znaleziony w arkuszu; nieznana lub nieaktualna wartość (slot już zajęty, inna osoba centralna) daje `null` i panel się nie renderuje.

**Contract**: wartości `father:<childId>` | `mother:<childId>` | `parent:<childId>` | `child` | `spouse` (+ `proband` w fazie 3). `resolveAddTarget(sheet: FamilySheet, value: string | null): AddTarget | null`, gdzie `AddTarget = { kind: 'parent'; slot: ParentSlotTarget } | { kind: 'child'; target: AddChildTarget } | { kind: 'spouse'; focus: SheetPerson; target: AddSpouseTarget }`. Slot rodzica szukany w `parents.*` i `grandparents.*` po `parent_of.id` + `role`. Pomocnik odwrotny `addValueFor(target)` dla wyzwalaczy. Vitest: każdy rodzaj, wartość nieaktualna, wartość zniekształcona.

#### 4. Konfiguracje celów

**File**: `resources/js/components/tree/add-person-config.ts` (nowy); usunięte `parent-slot-dialog.tsx`, `add-child-dialog.tsx`, `add-spouse-dialog.tsx`, `pick-or-create-person-dialog.tsx`

**Intent**: Logika tytułu, opisu, filtrowania kandydatów, `pick` i `form` z trzech wrapperów przenosi się 1:1 do jednej funkcji zależnej od `AddTarget` — tytuły i opisy bez zmian (lokatory E2E).

**Contract**: `addPersonConfig(target: AddTarget, options: { genders; religions }): { title; description; select; pick; form? }` — typy `PickOrCreateSelection`/`PickOrCreateForm` przeniesione z `pick-or-create-person-dialog.tsx:18-34`; `submitTestId` zachowane (`create-parent-button`, `create-child-button`, `create-spouse-button`).

#### 5. Panel dodawania

**File**: `resources/js/components/tree/add-person-panel.tsx` (nowy), renderowany w `resources/js/pages/tree/show.tsx`

**Intent**: Panel na `SidePanel` (wariant `standard`) z widokiem listy (`PersonCandidateList`, `useOptionalList('personCandidates', …)` z `load()` wołanym raz przy montowaniu panelu — nie w efekcie zależnym od `value`, w przeciwieństwie do `pick-or-create-person-dialog.tsx:104-108`; po zapisie panel się odmontowuje, więc następne otwarcie i tak wczyta świeżą listę; „Nowa osoba” w stopce) i formularza (`<Form>` z `PersonFormFields`, ukryte pola, stopka „Wróć do listy” / „Zapisz” ze spinnerem). Wybór kandydata to `router.post(pick…)` bez `onSuccess` — panel podmienia redirect z fazy 1. Owinięty w zagnieżdżony `SectionEditorProvider record={{ key: 'add', id: value }}`; brudność tylko w widoku formularza (wzorzec `person-create-dialog.tsx:171-221`: `serialize` + `MutationObserver`, `registerSubmit`, oraz podpięcie `resolver`/`settle` z `:69-74,122-127` — bez niego „Zapisz” z paska nigdy się nie rozwiązuje); `UnsavedChangesBar` nad stopką; X/Escape → `requestExit(close)` **zagnieżdżonego** providera (`close()` to `router.replace`, który nie odpala `router.on('before')`, więc tylko jawny `requestExit` go zatrzyma). Błędy: `InputError` pod listą (relacja) i per pole; przewinięcie do pierwszego błędu szuka od korzenia formularza w panelu.

**Contract**: `AddPersonPanel({ value, sheet, personCandidates, genders, religions })`; `contentKey = value`; tytuł z `addPersonConfig` (np. `Dodaj ojca — Jan Kowalski`). Strona czyta `add` z `usePage().url` i renderuje panel tylko gdy `resolveAddTarget` ≠ `null`.

#### 6. Wyzwalacze na drzewie

**File**: `resources/js/components/tree/family-sheet.tsx`, `resources/js/components/tree/union-node.tsx`, `resources/js/components/tree/unknown-person-card.tsx`

**Intent**: Sloty rodziców, dziecka i małżonka renderują `UnknownPersonCard`, którego klik otwiera `?add=<wartość>` przez `useSidePanelNavigation('add').open` (przez `requestExit` layoutu, jak `union-node.tsx:195-198`); „Dodaj małżonka” w menu związku robi to samo zamiast stanu `addSpouseOpen`. `aria-label` slotów bez zmian (`Dodaj ojca: …` — `navigation.ts:14-23`). `FamilySheet` przestaje przyjmować i przekazywać `genders`/`religions` w dół do slotów (zostają potrzebne tylko panelowi).

Dwa mechanizmy, każdy dla innego panelu: `requestExit` layoutu chroni brudny **panel osoby** (zewnętrzny `record` = `person`), a przy otwartym `?add=` zewnętrzny `record` jest `null` (`app-layout.tsx:19-25`) i wykonuje akcję od razu. Brudny formularz **dodawania** chroni globalny `router.on('before')` zagnieżdżonego providera (`section-editor.tsx:121-156`) — blokuje każdy GET, który zmienia lub usuwa `add` (inny slot, karta `<Link>` z `sheet-person-card.tsx:139-150`, która nie przenosi `add`, link oka, menu związku).

**Contract**: `UnknownPersonCard` przyjmuje `onClick` (już przez `...props`); usunięte `AddSpouseDialog`/`onCloseAutoFocus` z `union-node.tsx:217-231`.

#### 7. Luki stanów listy kandydatów

**File**: `resources/js/components/persons/person-candidate-list.tsx`

**Intent**: Wiersz kandydata ma widoczny `focus-visible` (ten sam pierścień co przyciski shadcn), błąd wczytania mówi „Nie udało się wczytać osób.” z przyciskiem „Spróbuj ponownie”, gdy podano `onRetry`. Zmiana dotyka też `pick-person-dialog.tsx` (pochowani) — tam `onRetry` = `load`.

**Contract**: nowy opcjonalny prop `onRetry?: () => void`; klasy `focus-visible:` z tokenu `ring`.

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą: `vendor/bin/sail npm run test:js`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Lint i format przechodzą: `vendor/bin/sail npm run check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Trzy specy slotów przechodzą bez zmian w kodzie testów: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts`
- Brak odwołań do usuniętego modala: `grep -rn "PickOrCreatePersonDialog\|ParentSlotDialog\|AddChildDialog\|AddSpouseDialog" resources/js` zwraca pusto

#### Manual Verification:

- Klik „Dodaj ojca” przy otwartym panelu osoby zamienia go na panel dodawania; drzewo zostaje zwężone obok, nie przyciemnione.
- Wybór kandydata i zapis nowej osoby przełączają panel na podgląd tej osoby; osoba pojawia się w drzewie w miejscu slotu.
- Wpisanie imienia w formularzu, potem klik w kartę na drzewie / X / Escape pokazuje pasek „Masz niezapisane zmiany w nowej osobie”; „Porzuć” wykonuje akcję, „Zapisz” zapisuje i wykonuje akcję.
- Tab po liście kandydatów pokazuje pierścień fokusu na wierszu; po odcięciu sieci „Spróbuj ponownie” wczytuje listę.
- `/tree/<id>?add=father:999999` (nieaktualny slot) nie otwiera panelu i nie wywala strony.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 3: Pusty stan drzewa i wybór probanta

### Overview

Dwa warianty `EmptyState` na `/tree` i tryb `?add=proband` w tym samym panelu. Zamyka C3.

### Changes Required:

#### 1. Pusty stan

**File**: `resources/js/pages/tree/show.tsx`

**Intent**: Zamiast doraźnego `Card` (`:47-71`) współdzielony `EmptyState`: bez osób — tytuł „Drzewo jest puste”, opis „Dodaj pierwszą osobę — zostanie probantem, od którego zaczyna się drzewo.”, przycisk „Dodaj pierwszą osobę”; z osobami — „Nie wybrano probanta”, „Wybierz osobę, od której zaczyna się drzewo, albo dodaj nową.”, przycisk „Wybierz probanta”. Oba przyciski otwierają `?add=proband`. Wzorzec użycia: `pages/persons/index.tsx:185-192`.

**Contract**: wariant wybiera `hasPeople` z fazy 1; link do `/persons` znika.

#### 2. Tryb probanta w panelu

**File**: `resources/js/lib/add-person-target.ts` (+ test), `resources/js/components/tree/add-person-config.ts`, `resources/js/components/tree/add-person-panel.tsx`

**Intent**: Cel `{ kind: 'proband'; hasPeople: boolean }` rozwiązywany, gdy `sheet === null` i wartość = `proband` (przy istniejącym arkuszu → `null`). Bez osób panel startuje w widoku formularza bez „Wróć do listy”; z osobami — lista wszystkich osób (bez filtrów) i „Nowa osoba”. `pick` → `TreeProbandController.attach` (`person_id`), `form` → `TreeProbandController.store.form()`. Błąd `proband` z serwera wyświetlany jak błąd relacji.

**Contract**: tytuły „Dodaj pierwszą osobę” / „Wybierz probanta”; `resolveAddTarget(sheet: FamilySheet | null, value, { hasPeople })`. Panel renderowany także w gałęzi `sheet === null` strony.

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą: `vendor/bin/sail npm run test:js`
- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Testy probanta przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/TreeProbandTest.php tests/Feature/TreeTest.php`

#### Manual Verification:

- Nowe konto bez osób: `/tree` pokazuje „Drzewo jest puste”; „Dodaj pierwszą osobę” otwiera panel od razu z formularzem; zapis pokazuje arkusz tej osoby z plakietką „Probant” i jej podglądem w panelu.
- Konto z osobami bez probanta (np. po usunięciu probanta): „Wybierz probanta” → wybór z listy ustawia probanta; „Nowa osoba” → zapis ustawia nową osobę probantem.
- Strażnik działa także w formularzu probanta.
- 390 px: pusty stan czytelny, panel pełnoekranowy, zamknięcie wraca do pustego stanu.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 4: Kadrowanie i aktywny slot

### Overview

`ZoomCanvas` odsłania wskazany element i ma kotwicę zastępczą; aktywny slot jest wyróżniony; dwie wartości spoza skali odstępów przechodzą na skalę. Zamyka C5 i C6.

### Changes Required:

#### 1. Wybór kotwicy (czysta funkcja)

**File**: `resources/js/lib/fit-view.ts`, `resources/js/lib/fit-view.test.ts`

**Intent**: Gdy para (obie karty pary z węzłem) mieści się we wrapperze przy skali kotwiczenia — kotwicą jest jej środek (oś pary, jak dziś); gdy nie — karta osoby centralnej. Spełnia decyzję „na wąskim ekranie kotwica obejmuje kartę osoby centralnej” i zachowuje „para w centrum zawsze w kadrze” tam, gdzie się mieści.

**Contract**: `chooseAnchor(primaryWidth: number, wrapperWidth: number, margin: number): 'primary' | 'fallback'` (szerokości przy skali docelowej). Vitest: mieści się / nie mieści / równa granica.

#### 2. `ZoomCanvas`: kotwica zastępcza i odsłanianie

**File**: `resources/js/components/zoom-canvas.tsx`

**Intent**: `fit()` w gałęzi kotwiczenia mierzy kotwicę główną (sumę prostokątów, gdy podano kilka refów) i używa zastępczej według `chooseAnchor`. Każdy zaplanowany przebieg (`schedule`) po `fit()` odsłania element `revealRef` minimalnym przesunięciem z marginesem 16 px (wydzielona logika delty z `onFocus`, `:266-293`), bez ustawiania `movedRef`; zmiana `revealKey` planuje przebieg także, gdy widok był ruszony.

**Contract**: nowe opcjonalne propsy `anchorRef: RefObject<HTMLElement | null> | RefObject<HTMLElement | null>[]`, `fallbackAnchorRef?: RefObject<HTMLElement | null>`, `revealRef?: RefObject<HTMLElement | null>`, `revealKey?: string | null`. `ancestor-chart.tsx` bez zmian.

- **Flaga zamiast domknięcia rAF.** `schedule()` (`:118-126`) ma jeden `frame` i `cancelAnimationFrame` — przebieg z `ResizeObservera` (zwężenie wrappera przy otwarciu panelu) anuluje przebieg z tej samej klatki. Dlatego zmiana `revealKey` ustawia `pendingRevealRef.current = true` i woła `schedule()`; callback rAF robi: `fit()` gdy `!fittedRef || !movedRef` (jak dziś), potem — jeśli `pendingRevealRef` albo `revealRef` jest ustawiony — odsłonięcie, niezależnie od `movedRef`, i zeruje flagę. Odsłonięcie nie ustawia `movedRef`.
- **Osobny efekt** na `[revealKey]` tylko ustawia flagę i woła `schedule()` (przez ref do `schedule`, jak `fitRef`); główny efekt nie dostaje `revealRef`/`revealKey` w zależnościach, żeby nie odłączał `ResizeObservera` przy każdej zmianie slotu.
- **Stabilne kotwice.** `FamilySheet` nie przekazuje inline'owej tablicy (`[ref1, ref2]` zmienia tożsamość co render i restartuje efekt z deps `[anchorRef, floor, max]`, `:163`) — tablica refów pary trzymana w `useRef`/stałej tożsamości albo jeden callback ref zbierający obie komórki.
- Klucz canvasu `${focus.id}:${selected_union}` (`family-sheet.tsx:297-298`) remontuje go po zapisie małżonka — flaga żyje w instancji, więc po remoncie przebieg startowy (`schedule()` przy montowaniu) robi `fit()` + odsłonięcie, jeśli `revealKey` jest ustawiony.

#### 3. `FamilySheet`: kotwice i aktywny slot

**File**: `resources/js/components/tree/family-sheet.tsx`, `resources/js/components/tree/union-node.tsx`, `resources/js/components/tree/unknown-person-card.tsx`, `resources/js/components/tree/card-styles.ts`

**Intent**: Kotwica główna = dwie komórki kart pary (`:384-388` dla obu bloków), zastępcza = komórka osoby centralnej (zamiast `coupleRef` całego wiersza). Wyzwalacz, którego wartość równa się bieżącemu `add`, dostaje `aria-expanded="true"`, styl aktywny (tło `accent`, obramowanie ciągłe — nowa stała w `card-styles.ts` obok `placeholderAction`, `:81`) i ref przekazany jako `revealRef`; `revealKey` = wartość `add`. Dla `?add=spouse` przy istniejącym partnerze celem jest przycisk węzła związku (`union-node.tsx:112-120`) ze stylem aktywnym (bez `aria-expanded`, którym zarządza Radix).

**Contract**: `FamilySheet` przyjmuje `activeAdd: string | null` od strony.

#### 4. Tokeny odstępów

**File**: `resources/js/components/tree/family-sheet.tsx:428`, `resources/js/components/tree/union-node.tsx:117`

**Intent**: `h-[30px]` → `h-7.5`, `h-[34px]` → `h-8.5` (te same wartości ze skali Tailwind v4).

**Contract**: skan `grep -rnE "\[[0-9]+px\]" resources/js/components/tree resources/js/pages/tree` zwraca tylko `ring-[3px]` z `card-styles.ts:23`.

### Success Criteria:

#### Automated Verification:

- Testy JS przechodzą (w tym `fit-view.test.ts`): `vendor/bin/sail npm run test:js`
- Typy, lint i build przechodzą: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run check && vendor/bin/sail npm run build`
- Skan zakodowanych wartości zwraca wyłącznie `ring-[3px]`: `grep -rnE "\[[0-9]+px\]" resources/js/components/tree resources/js/pages/tree`
- Specy drzewa przechodzą: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts tests/e2e/tree-card-recentre.spec.ts tests/e2e/ancestors-view.spec.ts`

#### Manual Verification:

- 1440×900 i 1366×768: klik „Dodaj ojca” (dziadkowie, skrajna kolumna) — po otwarciu panelu slot jest w kadrze i wyróżniony; zamknięcie panelu bez ruszania widoku wraca do dopasowania na osi pary.
- 390×844: karta osoby centralnej w kadrze po wejściu na arkusz.
- Menu związku → „Dodaj małżonka”: węzeł związku wyróżniony i w kadrze.
- Widok przodków kadruje jak przed zmianą.

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Faza 5: E2E, reguła agenta i bramka wizualna

### Overview

Specy slotów sprawdzają nowe zachowanie (panel przełącza się na dodaną osobę), nowy przypadek strażnika; reguła `CLAUDE.md` przestaje utrwalać modal; zrzuty „po”. Zamyka C2. E2E prowadzone przez `/10x-e2e` (ryzyko → eksploracja `playwright-cli` → generowanie → przegląd antywzorców → weryfikacja przez wprowadzony błąd).

### Changes Required:

#### 1. Specy slotów

**File**: `tests/e2e/tree-slot-parent.spec.ts`, `tests/e2e/tree-slot-child.spec.ts`, `tests/e2e/tree-slot-spouse.spec.ts`, `tests/e2e/support/api.ts`

**Intent**: Po każdym zapisie/wyborze, oprócz `toBeHidden()` panelu dodawania, asercja, że widoczny jest dialog nazwany imieniem i nazwiskiem dodanej osoby (panel podglądu). `createParent`/`createChild` czytają id z `?person=` w `Location` zamiast szukać po nazwie; komentarze `api.ts:175-176,203-205` aktualizowane.

**Contract**: lokatory `getByRole('dialog', { name })`, bez `waitForTimeout`; sufiksy w nazwach bez zmian.

#### 2. Spec strażnika panelu dodawania

**File**: `tests/e2e/tree-add-person-guard.spec.ts` (nowy)

**Intent**: Ryzyko: wpisane dane nowej osoby giną po kliknięciu w drzewo obok niemodalnego panelu. Scenariusz: otwórz slot → „Nowa osoba” → wpisz imię → klik karty osoby na drzewie → widoczny alert „Masz niezapisane zmiany w nowej osobie” → „Porzuć” otwiera panel klikniętej osoby i nie tworzy osoby; drugi przebieg: „Zapisz” tworzy osobę i otwiera panel; trzeci przebieg: klik innego slotu przy brudnym formularzu → pasek → „Porzuć” otwiera panel drugiego slotu (inna ścieżka: wizyta panelu, nie `<Link>` karty).

**Contract**: własne dane z sufiksem (`createPerson` z `support/api.ts`), wejście przez `/tree/{własneId}`; wzorzec `tests/e2e/person-panel-unsaved-guard.spec.ts`.

#### 3. Reguła agenta

**File**: `CLAUDE.md` (sekcja „Side panels vs modals”), `context/foundation/test-plan.md` (jeśli wymienia modal slotów)

**Intent**: Dodawanie i wybór osoby na drzewie (sloty, menu związku, pusty stan) odbywa się w panelu dodawania `?add=`; modalne zostają potwierdzenia usuwania, `MarryCoupleDialog`, małe pickery bez formularza (parafia inline, pochowani w źródle) i „Dodaj osobę” na `/persons` do czasu osobnej zmiany. Nowy wybór osoby z formularzem tworzenia → panel, nie modal.

**Contract**: zmieniona linia `CLAUDE.md:57` („small pickers…”) z nazwanym wyjątkiem i wskazaniem `resources/js/components/tree/add-person-panel.tsx` jako wzorca.

#### 4. Zrzuty „po”

**File**: `context/changes/tree-add-person-panel/screenshots/after/` (nowy katalog)

**Intent**: Te same ujęcia co `before/01–08` (serwer E2E, `playwright-cli`, 1440×900 i 390×844) dla porównania; `05–07` jako panel zamiast modala.

**Contract**: nazwy plików jak w `before/`.

### Success Criteria:

#### Automated Verification:

- Specy slotów i strażnika przechodzą: `npx playwright test tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts tests/e2e/tree-add-person-guard.spec.ts`
- Spec strażnika czerwienieje po celowym wyłączeniu `markDirty` w panelu i zielenieje po przywróceniu
- Pełny zestaw E2E przechodzi: `npx playwright test`
- `CLAUDE.md` nie zawiera już „small pickers (choose a person”: `grep -n "choose a person" CLAUDE.md` zwraca pusto
- Lint i format przechodzą: `vendor/bin/sail npm run check`

#### Manual Verification:

- Zrzuty `after/01–08` porównane z `before/`: brak przyciemnienia drzewa przy dodawaniu, slot w kadrze, karta osoby centralnej w kadrze na 390 px, pusty stan z `EmptyState`.
- Pełny zestaw Pest uruchomiony przez użytkownika: `vendor/bin/sail artisan test --compact`

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful.

---

## Testing Strategy

### Unit Tests:

- Vitest `side-panel-url.test.ts`: wzajemne wykluczanie `add` i `person`/`marriage`; `panelVisitOptions.only` bez `add`.
- Vitest `add-person-target.test.ts`: każdy rodzaj celu, slot rodzica w rzędzie dziadków, wartość nieaktualna/zniekształcona → `null`, `proband` tylko przy `sheet === null`.
- Vitest `fit-view.test.ts`: `chooseAnchor` na granicy.

### Integration Tests:

- Pest: redirecty sześciu endpointów (zachowany `spouse`, usunięte klucze paneli, `person` = dodana/wybrana osoba, fallback bez Referera), `TreeProbandController` (happy path, istniejący proband, 404 cudzej osoby, goście, walidacja), `hasPeople`.
- E2E: trzy specy slotów (panel przełącza się na dodaną osobę), strażnik panelu dodawania.

### Manual Testing Steps:

1. Arkusz z pustymi slotami, 1440×900: dodaj ojca z listy, matkę jako nową osobę, dziecko, małżonka ze slotu i z menu — po każdym kroku panel pokazuje dodaną osobę, drzewo zostaje na tej samej osobie i związku.
2. Wpisz dane nowej osoby, kliknij inny slot — pasek strażnika; „Porzuć” otwiera drugi slot.
3. 390×844: arkusz pokazuje kartę osoby centralnej; panel dodawania pełnoekranowy.
4. Nowe konto: pusty stan → pierwsza osoba zostaje probantem; usuń probanta → „Wybierz probanta”.

## Performance Considerations

`personCandidates` nadal ładuje się leniwie (`Inertia::optional`) przy otwarciu panelu; przełączanie slotów przy otwartym panelu nie przeładowuje listy, jeśli jest już wczytana (`useOptionalList` trzyma ostatnią listę). `hasPeople` to jedno `exists()` tylko w `index`.

## Migration Notes

Brak migracji danych. Zakładki/linki z `?add=` po zmianie arkusza degradują się do strony bez panelu.

## References

- Research: `context/changes/tree-add-person-panel/research.md`
- Zrzuty „przed”: `context/changes/tree-add-person-panel/screenshots/before/`
- Tworzenie w panelu: `app/Http/Controllers/SourceController.php:70-90`, `context/archive/2026-10-05-source-side-panel/plan.md`
- Redirect z panelem osoby: `app/Http/Controllers/PersonController.php:100-107`
- Strażnik w trybie tworzenia: `resources/js/components/persons/person-create-dialog.tsx:40-221`
- Kadrowanie: `context/archive/2026-10-05-tree-fits-screen/plan-brief.md`, `resources/js/components/zoom-canvas.tsx:66-153,248-295`
- Para `attach`/`store`: `app/Http/Controllers/PersonChildController.php`, `PersonSpouseController.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Ścieżka zapisu i endpointy probanta

#### Automated

- [x] 1.1 Testy fazy przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/ParentChildRelationsTest.php tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/TreeProbandTest.php tests/Feature/WriteTargetsTest.php tests/Feature/OwnershipBoundariesTest.php` — fccf66e
- [x] 1.2 Larastan przechodzi: `vendor/bin/sail bin phpstan analyse` — fccf66e
- [x] 1.3 Formatowanie PHP: `vendor/bin/sail bin pint --dirty --format agent` — fccf66e
- [x] 1.4 Wayfinder wygenerowany i typy przechodzą: `vendor/bin/sail npm run types:check` — fccf66e

#### Manual

- [x] 1.5 Na drzewie dodanie rodzica przez obecny modal otwiera po zapisie podgląd nowego rodzica obok drzewa — fccf66e

### Phase 2: Panel dodawania dla slotów drzewa

#### Automated

- [x] 2.1 Testy JS przechodzą: `vendor/bin/sail npm run test:js` — 9350c59
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — 9350c59
- [x] 2.3 Lint i format przechodzą: `vendor/bin/sail npm run check` — 9350c59
- [x] 2.4 Build przechodzi: `vendor/bin/sail npm run build` — 9350c59
- [x] 2.5 Trzy specy slotów przechodzą bez zmian w kodzie testów — 9350c59
- [x] 2.6 Brak odwołań do usuniętego modala — 9350c59

#### Manual

- [x] 2.7 Klik „Dodaj ojca” przy otwartym panelu osoby zamienia go na panel dodawania; drzewo zwężone, nie przyciemnione
- [x] 2.8 Wybór kandydata i zapis nowej osoby przełączają panel na podgląd tej osoby — 9350c59
- [x] 2.9 Strażnik: pasek przy kliknięciu w kartę / X / Escape; Porzuć i Zapisz działają — 9350c59
- [x] 2.10 Pierścień fokusu na wierszu kandydata; „Spróbuj ponownie” wczytuje listę — 9350c59
- [x] 2.11 Nieaktualny `?add=` nie otwiera panelu i nie wywala strony — 9350c59

### Phase 3: Pusty stan drzewa i wybór probanta

#### Automated

- [x] 3.1 Testy JS przechodzą: `vendor/bin/sail npm run test:js` — af8606e
- [x] 3.2 Typy, lint i build przechodzą — af8606e
- [x] 3.3 Testy probanta przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/TreeProbandTest.php tests/Feature/TreeTest.php` — af8606e

#### Manual

- [x] 3.4 Konto bez osób: pierwsza osoba zostaje probantem, arkusz z jej podglądem — af8606e
- [x] 3.5 Osoby bez probanta: wybór z listy i „Nowa osoba” ustawiają probanta — af8606e
- [x] 3.6 Strażnik działa w formularzu probanta — af8606e
- [x] 3.7 390 px: pusty stan czytelny, panel pełnoekranowy, zamknięcie wraca do pustego stanu — af8606e

### Phase 4: Kadrowanie i aktywny slot

#### Automated

- [x] 4.1 Testy JS przechodzą (w tym `fit-view.test.ts`) — d83b387
- [x] 4.2 Typy, lint i build przechodzą — d83b387
- [x] 4.3 Skan zakodowanych wartości zwraca wyłącznie `ring-[3px]` — d83b387
- [x] 4.4 Specy drzewa przechodzą (sloty, `tree-card-recentre`, `ancestors-view`) — d83b387

#### Manual

- [x] 4.5 1440×900 i 1366×768: aktywny slot w kadrze i wyróżniony; zamknięcie wraca do dopasowania — d83b387
- [x] 4.6 390×844: karta osoby centralnej w kadrze — d83b387
- [x] 4.7 Menu związku → „Dodaj małżonka”: węzeł wyróżniony i w kadrze — d83b387
- [x] 4.8 Widok przodków kadruje jak przed zmianą — d83b387

### Phase 5: E2E, reguła agenta i bramka wizualna

#### Automated

- [x] 5.1 Specy slotów i strażnika przechodzą — 962cd10
- [x] 5.2 Spec strażnika czerwienieje po celowym wyłączeniu `markDirty` i zielenieje po przywróceniu — 962cd10
- [x] 5.3 Pełny zestaw E2E przechodzi: `npx playwright test` — 962cd10
- [x] 5.4 `CLAUDE.md` nie zawiera już „small pickers (choose a person” — 962cd10
- [x] 5.5 Lint i format przechodzą: `vendor/bin/sail npm run check` — 962cd10

#### Manual

- [ ] 5.6 Zrzuty `after/01–08` porównane z `before/`
- [ ] 5.7 Pełny zestaw Pest uruchomiony przez użytkownika: `vendor/bin/sail artisan test --compact`
