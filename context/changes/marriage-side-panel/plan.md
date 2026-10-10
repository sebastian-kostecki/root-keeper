# Panel małżeństwa (S-12) Implementation Plan

## Overview

Czwarty konsument wspólnej powłoki panelu bocznego. Autor otwiera obok drzewa panel małżeństwa: widzi małżonków, datę i miejsce ślubu (edycja jak w panelu osoby), źródła ślubu z pełną edycją powiązań oraz wspólne dzieci pary. Małżeństwo można też usunąć z nagłówka panelu. Edycja małżeństwa zostaje także w panelu osoby. To wycinek S-12 z roadmapy (FR-017).

## Current State Analysis

- **Małżeństwo to zdarzenie, nie osobny model.** Jest wierszem `events` z `type = marriage` i dwoma uczestnikami z rolą `spouse` (`app/Models/Event.php:68-73`, `app/Enums/ParticipantRole.php:7-8`).
  - Miejsce: `events.locality_id` (`database/migrations/2026_10_06_070850_link_event_places_to_localities.php:182`).
  - Źródła: `event_source` z `entry_number` (`app/Models/Event.php:78-86`).
  - Para nie ma ról męża i żony.
- **Zapisy już istnieją, a każdy zwraca `back()`.**
  - `MarriageController::update` zapisuje datę i miejsce (`app/Http/Controllers/MarriageController.php:37-44`).
  - `MarriageController::destroy` usuwa małżeństwo (`:49-59`).
  - `EventSourceController` obsługuje powiązanie, numer wpisu i odpięcie (`routes/web.php:45-47`).
- **Polityka.** `EventPolicy` ma tylko `update` (`app/Policies/EventPolicy.php`); brakuje `view`.
- **Panel osoby żyje w layoucie i ma globalny resolver.** `personPreview` z `?person=` (`app/Http/Middleware/HandleInertiaRequests.php:57-61, 79-99`) daje 404 dla cudzego id. Pozostałe panele (źródło, miejscowość, parafia) mają resolver w `index` swojej strony.
- **Rejestr paneli nie zna małżeństwa.** `PANEL_PROPS` w `resources/js/lib/side-panel-url.ts:6-12` nie ma klucza `marriage`. Od rejestru zależą `panelVisitOptions.only` i czyszczenie kluczy w `withPanel`.
- **Strażnik niezapisanych zmian jest przypięty do osoby.** `SectionEditorProvider` (`resources/js/components/persons/section-editor.tsx:82-146`) przyjmuje `personId`. Jego nasłuch `router.on('before')` przepuszcza tylko wizyty z tym samym `person`.
  - `SectionId` już zawiera `marriage-${number}`, a `labelFor` zwraca „małżeństwie”.
  - Layout montuje provider z `personId` (`resources/js/layouts/app-layout.tsx:21-35`).
- **Edycja małżeństwa w panelu osoby.**
  - Przegląd: `SectionCard` z `FactView` i `MarriageFields`; zapis przez `MarriageController.update` (`resources/js/components/persons/overview-tab.tsx:271-314, 439-483`).
  - Źródła: lokalny `SourceGroup` w `sources-tab.tsx:41-188`.
  - Rodzina: `MarriageRow` (`family-tab.tsx:205-258`) to wiersz małżonka z menu „Usuń małżeństwo”; samego małżeństwa nie da się kliknąć.
- **Drzewo.**
  - `UnionNode` (`resources/js/components/tree/union-node.tsx:58-204`) to menu z listą związków (radio) i „Dodaj małżonka”.
  - `SheetUnion` (`resources/js/types/tree.ts:45-52`) nie zna id małżeństwa, choć `BuildFamilySheet` ma je w zasięgu (`app/Actions/Trees/BuildFamilySheet.php:103-104`).
  - Karta centruje drzewo i zachowuje tylko `?person` (`resources/js/components/tree/sheet-person-card.tsx:69-81`, od commita `0bd881e`). Panel osoby otwiera ikona „Podgląd”.
- **„Dzieci pary” nie mają `marriage_id`.**
  - `parent_child_relations` łączy tylko rodzica z dzieckiem.
  - Drzewo grupuje dzieci po „drugim rodzicu” (`BuildFamilySheet.php:74-79, 131-136`) i porządkuje je `PersonOrdering::relatives`.
  - `RelativeResource` wymaga `relation_id` jednej relacji, więc nie pasuje do dziecka dwojga rodziców.
- **Usuwanie dziś.**
  - `RemoveMarriageDialog` (`resources/js/components/persons/remove-marriage-dialog.tsx`) wywołuje `MarriageController.destroy` i dostaje `back()`.
  - Z otwartym `?marriage=` ten powrót trafiłby na resolver usuniętego rekordu, czyli 404.

## Desired End State

- **Adres i wariant.** Na każdej stronie aplikacji `?marriage={id}` otwiera po prawej panel `standard`.
- **Tytuł** to „{pierwszy} i {drugi}” z nazw wyświetlanych małżonków, np. „Jan Kowalski i Anna Kowalska z d. Nowak”. Mężczyzna stoi przed kobietą. Przy parze innej niż mężczyzna–kobieta decyduje data urodzenia (znana przed nieznaną, wcześniejsza pierwsza), a potem id.
- **Treść panelu:**
  - małżonkowie jako linki do ich paneli osoby;
  - karta „Ślub” z datą i miejscem oraz edycją jak w panelu osoby: „Edytuj” rozwija `MarriageFields`, zapis idzie przez `MarriageController.update`;
  - grupa źródeł ślubu (powiąż, numer wpisu, odepnij, otwórz);
  - wspólne dzieci pary jako linki do ich paneli osoby, z komunikatem, gdy ich nie ma.
- **„Usuń małżeństwo” w nagłówku** otwiera ten sam dialog co w Rodzinie. Po usunięciu panel znika, a strona zostaje w tym samym miejscu.
- **Wejścia:**
  - pozycja „Szczegóły małżeństwa” w menu węzła związku na drzewie, tylko dla wybranego związku ze ślubem;
  - link „Szczegóły małżeństwa z {małżonek}” w wierszu małżeństwa w zakładce Rodzina panelu osoby.
- **Zachowanie panelu.** Przecentrowanie karty drzewa nie zamyka otwartego panelu małżeństwa. Niezapisana karta „Ślub” jest chroniona tym samym paskiem „Masz niezapisane zmiany w małżeństwie” co sekcje osoby, także przy kliknięciu w menu boczne.
- **Cudze albo nie-ślubne `?marriage=` daje 404.**

Weryfikacja: Pest (resolver, dzieci, kolejność, 404, przekierowanie po usunięciu, `marriage_id`), Vitest rejestru, jeden spec E2E oraz ręczne sprawdzenie przy 1366 px.

### Key Discoveries:

- Wzorzec globalnego resolvera z memoizacją i `findOrFail` + `Gate::authorize('view')`: `app/Http/Middleware/HandleInertiaRequests.php:79-99`.
- Opcjonalne `localityOptions` i `availableSources` są współdzielone globalnie (`HandleInertiaRequests.php:62-69`). `LocalityPicker` i `SourcePickerDialog` zadziałają w panelu małżeństwa bez nowych właściwości.
- Przekierowanie bez klucza panelu przy usuwaniu: walidacja hosta i ścieżki z `PersonController::urlAfterDelete` (`app/Http/Controllers/PersonController.php:140-164`), ale odczyt tylko nagłówka `referer` jak w `SourceController::listQuery` (`:161-173`). `urlAfterDelete` używa `url()->previous()` (`:146`), czyli ma wadę z lekcji F1 z S-06 (`context/archive/2026-10-05-person-side-panel/reviews/impl-review.md:26-34`); nie kopiować tej linii.
- `panelVisitOptions.only` musi obejmować wszystkie propsy rejestru, inaczej zostaną dwa panele naraz (`context/archive/2026-10-04-side-panel-shell/plan.md:52-53`).
- `familyScene()` (`tests/Pest.php:255`) ma `marriageA` (focus + wifeA, `childA`), `marriageB` (focus + wifeB, `childB`), `childC` z partnerką bez ślubu i `childUnknown` tylko z focusem. To gotowa wyrocznia „tylko wspólne dzieci”.
- Lokatory E2E paneli: `getByRole('dialog', { name: tytuł })` i regiony nazwane nagłówkiem (`tests/e2e/support/navigation.ts:95-135`).

## What We're NOT Doing

- Wejście z „Potwierdza” w panelu źródła: nadal otwiera panel osoby, a lista dalej pokazuje małżeństwo raz na małżonka.
- Link do panelu małżeństwa z karty „Małżeństwo z …” w Przeglądzie panelu osoby. Wejściem z panelu osoby jest wiersz w Rodzinie.
- Dzieci z innych związków któregokolwiek małżonka. Dziecko z jednym wpisanym rodzicem nie trafia do żadnej pary.
- Dodawanie dziecka pary z panelu; zostaje slot dziecka na drzewie.
- Świadkowie ślubu, rozwód, typ małżeństwa, zmiana małżonka (PRD Non-Goals, archiwum S-07).
- Zmiany modelu danych i migracje.
- Obsługa adresu z jednocześnie `?person=` i `?marriage=`. `withPanel` nigdy go nie tworzy.
- Synchronizacja otwartego panelu z przełączeniem związku na drzewie. Panel pokazuje otwarte małżeństwo, a przełączenie zmienia tylko drzewo.

## Implementation Approach

- **Wzorzec F-02 bez zmian.** Działa łańcuch parametr → właściwość → częściowe przeładowanie.
  - Klucz `marriage` trafia do rejestru, a globalna właściwość `marriagePanel` do `HandleInertiaRequests`, obok `personPreview`. Wejście z panelu osoby musi działać na każdej stronie, na której ten panel żyje.
  - Dane składa akcja `BuildMarriagePanel`. Zapisy zostają w istniejących kontrolerach. Ich `back()` niesie `?marriage=` w Refererze, więc panel odświeża się po każdym zapisie.
- **Uogólniony strażnik.** `SectionEditorProvider` przechodzi z `personId` na rekord otwartego panelu `{ key, id }`. Panel małżeństwa używa wtedy `SectionCard` dokładnie jak Przegląd osoby, a globalny strażnik nawigacji obejmuje oba panele.
- **Wydzielenia zamiast kopii.** Wspólne kawałki (`SourceGroup`, `Field`, budowa żądania zapisu małżeństwa) przechodzą do własnych modułów. Oba panele edytują to samo małżeństwo tym samym kodem.

## Critical Implementation Details

- **Kolejność przy usuwaniu.** `destroy` musi przekierować na Referer bez `marriage` (fallback `tree.index`), zanim panel wyrenderuje się po raz kolejny. Inaczej odpowiedź po udanym usunięciu kończy się stroną 404. Usuwanie z Rodziny panelu osoby (Referer z `?person=`) ma działać jak dziś.
- **Klucz resetu strażnika.** Provider resetuje stan przy zmianie `${key}:${id}`, a nie samego `id`. Osoba 12 i małżeństwo 12 to różne rekordy. Przejście osoba → małżeństwo przy brudnej sekcji najpierw przechodzi przez pasek.
- **N+1.** `birth()`/`death()` osoby czytają zdarzenia. Dla małżonków i dzieci akcja ładuje je z góry, tak jak `BuildPersonPreview` (`app/Actions/Persons/BuildPersonPreview.php:27`).

## Phase 1: Backend panelu małżeństwa

### Overview

Serwer rozwiązuje `?marriage=` na dane panelu (albo 404) na każdej stronie, drzewo zna id małżeństwa związku, a usunięcie nie wraca na adres usuniętego panelu.

### Changes Required:

#### 1. Polityka odczytu zdarzenia

**File**: `app/Policies/EventPolicy.php`

**Intent**: Resolver panelu autoryzuje odczyt tak jak resolvery osoby i źródła. Cudze zdarzenie daje 404.

**Contract**: `view(User $user, Event $event): Response` z tą samą regułą `tree_id === $user->tree->id`, w przeciwnym razie `Response::denyAsNotFound()`.

#### 2. Kolejność małżonków

**File**: `app/Support/PersonOrdering.php`

**Intent**: Jedna stała kolejność małżonków dla tytułu i listy, niezależna od wejścia (decyzja użytkownika: najpierw mężczyzna).

**Contract**: `PersonOrdering::spouses(Collection<int, Person>): Collection<int, Person>`.
- Para mężczyzna + kobieta: mężczyzna pierwszy.
- Każda inna para (ta sama płeć albo nieznana płeć któregoś): znana data urodzenia przed nieznaną, potem rok/miesiąc/dzień rosnąco, potem id.

#### 3. Dane panelu

**File**: `app/Actions/Relations/BuildMarriagePanel.php` (nowy)

**Intent**: Składa dane panelu z jednego zdarzenia-małżeństwa: ślub z miejscem i źródłami, uporządkowanych małżonków i wspólne dzieci.

**Contract**: `handle(Event $marriage): array`, zwraca kształt `MarriagePanelPayload`:
- `marriage`: `EventResource::make(...)->resolve()` z załadowanymi `locality` i `sources`, w tym `date_parts`, `confirmed_kinds` i `sources[].entry_number`;
- `spouses`: lista dwóch skrótów osoby `{ id, display_name, gender, birth_date, death_date }` (ten sam kształt co `spouse` w `MarriageResource`), w kolejności z `PersonOrdering::spouses`;
- `children`: skróty osób, których rodzicami są **oboje** małżonkowie, w kolejności `PersonOrdering::relatives`.

#### 4. Globalny resolver `marriagePanel`

**File**: `app/Http/Middleware/HandleInertiaRequests.php`

**Intent**: Panel małżeństwa jest dostępny na każdej stronie, jak panel osoby. Cudze albo nie-ślubne id daje 404.

**Contract**: Współdzielona leniwa właściwość `marriagePanel` (closure, jak `personPreview`) i prywatna metoda `marriagePanelEvent(Request): ?Event`:
- bez użytkownika albo z nienumerycznym `?marriage` zwraca `null`;
- w przeciwnym razie `$user->tree->events()->where('type', EventType::Marriage)->findOrFail($id)`, potem `Gate::authorize('view', $event)`;
- zdarzenie bez dokładnie dwóch uczestników daje 404 (`abort_unless`), bo `BuildMarriagePanel` i tytuł zakładają dwóch małżonków.

#### 5. Przekierowanie po usunięciu

**File**: `app/Http/Controllers/MarriageController.php`

**Intent**: Usunięcie z panelu małżeństwa nie wraca na adres z `?marriage=` usuniętego rekordu. Usunięcie z panelu osoby wraca jak dziś.

**Contract**: `destroy(Request $request, Event $marriage)` przekierowuje na URL z nagłówka `referer` z usuniętym kluczem `marriage`:
- czyta wyłącznie `$request->headers->get('referer')`, wzorem `SourceController::listQuery` (`SourceController.php:161-173`); `PersonController::urlAfterDelete` jest wzorcem tylko walidacji hosta i ścieżki, bo sam używa `url()->previous()` (lekcja F1);
- tylko ten sam host i ścieżka zaczynająca się od `/`;
- brak albo obcy Referer daje `route('tree.index')`, także gdy w sesji został stary adres z `?marriage=` (nie używa `url()->previous()`).

#### 6. Id małżeństwa w związku drzewa

**File**: `app/Actions/Trees/BuildFamilySheet.php`, `resources/js/types/tree.ts`

**Intent**: Węzeł związku może otworzyć panel małżeństwa wybranego związku.

**Contract**: Każdy element `unions` dostaje `marriage_id: int|null`: id zdarzenia dla związku ze ślubem, `null` dla współrodzica bez ślubu i dla `unknown`. Typ `SheetUnion.marriage_id: number | null`. Zaktualizować PHPDoc kształtu w akcji.

#### 7. Testy Pest

**File**: `tests/Feature/MarriagesTest.php`, `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/TreeTest.php`, `tests/Pest.php`

**Intent**: Pokryć kontrakt resolvera, wyrocznię dzieci pary, kolejność, 404 i przekierowanie. Ryzyko nr 1 z `test-plan.md` to zapis albo odczyt złej pary.

**Contract**:
- Helper `marriagePanelUrl(Event $marriage, string $route = 'persons.index', array $parameters = [])`, wzorem `personPreviewUrl` (`tests/Pest.php:105`).
- `MarriagesTest`, otwarcie `marriageA` z `familyScene`:
  - `spouses` = [focus, wifeA];
  - `children` = [childA] (bez childB, childC, childUnknown);
  - źródła = sourceShared i sourceOther z numerem wpisu.
- `MarriagesTest`, `marriageB`: `children` = [childB].
- `MarriagesTest`, kolejność:
  - kobieta utworzona przed mężczyzną, a w tytule mężczyzna pierwszy;
  - para mężczyzna + nieznana płeć ze starszą osobą o nieznanej płci, która wtedy jest pierwsza.
- `MarriagesTest`, resolver:
  - id zdarzenia urodzenia w `?marriage=` daje 404;
  - wartość nienumeryczna i brak klucza dają `marriagePanel` = `null`;
  - `marriagePanel` działa na `tree.show`, `persons.index` i `sources.index`.
- `MarriagesTest`, usuwanie:
  - `from('/tree/{id}?person={id}&marriage={m}')` przekierowuje na `/tree/{id}?person={id}`;
  - brak Referera przekierowuje na `tree.index`, także gdy w sesji zostaje poprzedni adres z `?marriage=` (czerwony na `url()->previous()`);
  - usunięcie z Refererem z samym `?person=` zostaje bez zmian.
- `OwnershipBoundariesTest`: przypadek „`?marriage=` cudzego małżeństwa na stronie-hoście” (`tree.index`, `persons.index`, `sources.index`) daje 404 i nic nie zapisuje, wzorem testu `?person=` (`:180-200`).
- `TreeTest`: `unions[].marriage_id` jest równe id małżeństwa dla związku ze ślubem i `null` dla współrodzica i `unknown`.

### Success Criteria:

#### Automated Verification:

- Pest małżeństw, drzewa i granic przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/OwnershipBoundariesTest.php`
- Pest pozostałych miejsc z małżeństwami przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/WriteTargetsTest.php tests/Feature/DeleteImpactTest.php tests/Feature/SourcesTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint czysty: `vendor/bin/sail bin pint --dirty --format agent`
- Typy przechodzą po dodaniu `marriage_id`: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- `/tree/{id}?marriage={id}` w przeglądarce zwraca stronę, a w props Inertii (devtools) jest `marriagePanel` z małżonkami w kolejności mężczyzna–kobieta i samymi wspólnymi dziećmi

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Uogólniony strażnik i panel małżeństwa

### Overview

`?marriage=` renderuje w layoucie panel małżeństwa z edycją ślubu, źródłami, dziećmi i usuwaniem. Strażnik niezapisanych zmian obejmuje oba panele.

### Changes Required:

#### 1. Rejestr paneli

**File**: `resources/js/lib/side-panel-url.ts`, `resources/js/lib/side-panel-url.test.ts`

**Intent**: Klucz `marriage` uczestniczy w częściowych przeładowaniach i w wzajemnym wykluczaniu paneli.

**Contract**:
- `PANEL_PROPS.marriage = 'marriagePanel'`.
- Vitest: `panelVisitOptions.only` zawiera `marriagePanel`, `withPanel(url, 'person', id)` usuwa `marriage`, a `withPanel(url, 'marriage', id)` usuwa `person`.

#### 2. Typy

**File**: `resources/js/types/global.d.ts`, `resources/js/types/person.ts` (albo nowy `resources/js/types/marriage.ts` eksportowany z `@/types`)

**Intent**: Typowany dostęp do `marriagePanel` w `usePage().props`.

**Contract**:
- `MarriagePanelPayload = { marriage: SourcedPersonEvent; spouses: PersonSummary[]; children: PersonSummary[] }`.
- `PersonSummary` to kształt `Marriage['spouse']` bez `null`; jeśli istniejący typ już go nazywa, użyć go.
- W globalnych propsach: `marriagePanel: MarriagePanelPayload | null`.

#### 3. Strażnik dla rekordu dowolnego panelu

**File**: `resources/js/components/persons/section-editor.tsx`, `resources/js/layouts/app-layout.tsx`

**Intent**: Te same sekcje, pasek i globalny nasłuch `before` chronią panel osoby i panel małżeństwa (decyzja użytkownika: edycja jak w panelu osoby).

**Contract**:
- `SectionEditorProvider({ record, children })`, gdzie `record: { key: PanelKey; id: number } | null` zastępuje `personId`.
- Reset stanu przy zmianie `${key}:${id}`.
- Nasłuch `before` przepuszcza wizyty, w których `searchParams.get(record.key) === String(record.id)`. Pozostałe warunki przepuszczania (prefetch, nie-GET, wyjście po decyzji) zostają bez zmian.
- Layout podaje `{ key: 'person', id }` z `personPreview`, w przeciwnym razie `{ key: 'marriage', id }` z `marriagePanel`, w przeciwnym razie `null`.
- Zaktualizować docblock (provider nie jest już „dla panelu osoby”).

#### 4. Wydzielone części wspólne

**File**: `resources/js/components/persons/source-group.tsx` (nowy, z `sources-tab.tsx`), `resources/js/components/persons/fact-field.tsx` (nowy, `Field` z `overview-tab.tsx`), `resources/js/lib/person-payload.ts`, `resources/js/hooks/use-section-save.ts` (nowy, z `overview-tab.tsx`)

**Intent**: Panel małżeństwa i panel osoby renderują źródła i pola faktu oraz zapisują małżeństwo tym samym kodem, bez drugiej kopii obsługi zapisu i błędów (decyzja z przeglądu planu: F4, Fix A).

**Contract**:
- `SourceGroup` eksportowany z niezmienionymi propsami (`label`, `event`, `availableSources`) i tym samym regionem nazwanym nagłówkiem. `SourcesTab` importuje go.
- `Field` eksportowany; `OverviewTab` importuje go.
- `person-payload.ts`: `marriageSaveRequest(id, values)` zwraca `[MarriageController.update.url(id), { marriage: eventPayloadFromValues('marriage', values) }]`, a `marriageFieldDefaults(marriage)` daje domyślną wartość `MarriageFields` z dzisiejszego `overview-tab.tsx:465-476`.
- `useSectionSave(belongsToSection)` zwraca `{ savingId, errorsFor(id), strayErrorFor(id), save(id, url, payload): Promise<boolean> }`. Przenosi z `OverviewTab` dzisiejsze `router.put` z `onStart/onSuccess/onError/onFinish`, stan `savingId` i `failure` oraz `errorsFor`/`strayErrorFor` (`errorsFor` czyta `useSectionEditor().editing`). `belongsToSection(id, key)` zostaje w `overview-tab.tsx` i jest podawane jako parametr; panel małżeństwa podaje wariant dla `marriage.`.
- `OverviewTab` zachowuje obecne zachowanie dla sekcji osoby i małżeństwa; panel małżeństwa używa tego samego hooka. Bramką regresji są istniejące spece z 2.5.

#### 5. Nawigacja panelu małżeństwa

**File**: `resources/js/hooks/use-marriage-panel.ts` (nowy), `resources/js/components/marriages/marriage-panel-link.tsx` (nowy)

**Intent**: Wejścia otwierają panel małżeństwa tak, jak `PersonPreviewLink` otwiera panel osoby: z prawdziwym `href` i przez pasek przy brudnej sekcji.

**Contract**:
- `useMarriagePanel()` zwraca `{ currentId, hrefFor, open, close }`: `useSidePanelNavigation('marriage')` plus `currentId` z `usePage().props.marriagePanel?.marriage.id ?? null`.
- `MarriagePanelLink({ marriageId, ...linkProps })` zachowuje się jak `PersonPreviewLink` (`resources/js/components/persons/person-preview-link.tsx`): `panelVisitOptions`, a przy brudnym edytorze i zwykłym kliknięciu `requestExit(() => open(id))`.

#### 6. Panel małżeństwa w layoucie

**File**: `resources/js/components/marriages/marriage-panel.tsx` (nowy), `resources/js/layouts/app-layout.tsx`

**Intent**: Panel z FR-017 obok strony, montowany w layoucie obok `PersonPanel`.

**Contract**: `SidePanel variant="standard"`:
- `title`: „{spouses[0].display_name} i {spouses[1].display_name}”;
- `description`: „Małżeństwo · ślub {marriage.date}” albo „Małżeństwo, data nieznana”;
- `contentKey={marriage.id}`, `onRequestClose={() => requestExit(close)}`;
- `actions`: przycisk „Usuń małżeństwo” z `RemoveMarriageDialog` (imiona obojga małżonków).

Treść w kolejności:
1. Region „Małżonkowie”: wiersze małżonków jako `PersonPreviewLink`, jak `PersonRow` w Rodzinie.
2. `SectionCard` `marriage-{id}` „Ślub” z odznaką `EventSourceStatus`. Widok to Data i Miejsce przez `Field`. Edycja to `MarriageFields` z `marriageFieldDefaults(marriage)`. Zapis i błędy przez `useSectionSave` i `marriageSaveRequest` (bez własnej kopii logiki z `cardProps`).
3. `SourceGroup` z etykietą „Źródła ślubu”.
4. Region „Dzieci”: wspólne dzieci jako `PersonPreviewLink`, a przy pustej liście „Brak wspólnych dzieci”.
5. `UnsavedChangesBar`.

#### 7. Dialog usuwania dla obu paneli

**File**: `resources/js/components/persons/remove-marriage-dialog.tsx`

**Intent**: Ten sam dialog działa z Rodziny i z nagłówka panelu małżeństwa. Po usunięciu serwer przekierowuje bez `?marriage`, więc panel znika.

**Contract**: Propsy bez zmian (`marriage` wymaga tylko `id` i `sources`; zawęzić typ do `Pick<Marriage, 'id' | 'sources'>`, jeśli panel podaje `SourcedPersonEvent`). Bez zmian zachowania przy wywołaniu z Rodziny.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Vitest przechodzi, w tym nowe przypadki rejestru: `vendor/bin/sail npm run test:js`
- Build przechodzi: `vendor/bin/sail npm run build`
- Istniejące E2E panelu osoby i strażnika przechodzą bez zmian w specach: `npx playwright test tests/e2e/person-side-panel.spec.ts tests/e2e/person-panel-unsaved-guard.spec.ts tests/e2e/person-panel-page-navigation-guard.spec.ts tests/e2e/tree-slot-spouse.spec.ts`

#### Manual Verification:

- `/tree/{id}?marriage={id}` przy 1366 px pokazuje panel obok zwężonego drzewa: tytuł mężczyzna i kobieta, karta „Ślub”, źródła, tylko wspólne dzieci
- Edycja daty i miejsca w karcie „Ślub” zapisuje się, a panel osoby małżonka pokazuje nową wartość
- Powiązanie źródła, numer wpisu i odpięcie w panelu małżeństwa działają, a panel zostaje otwarty
- Brudna karta „Ślub” i kolejno X, Escape, klik dziecka, klik „Źródła” w menu bocznym: każde pokazuje pasek „Masz niezapisane zmiany w małżeństwie”
- „Usuń małżeństwo” w nagłówku: po potwierdzeniu panel znika, drzewo zostaje w tym samym miejscu, bez strony 404

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Wejścia do panelu

### Overview

Autor otwiera panel z drzewa i z panelu osoby, a praca na drzewie nie zamyka otwartego panelu małżeństwa.

### Changes Required:

#### 1. Pozycja menu węzła związku

**File**: `resources/js/components/tree/union-node.tsx`

**Intent**: Z linii pary na drzewie do jej małżeństwa (decyzja użytkownika: drzewo i panel osoby).

**Contract**:
- Gdy wybrany związek ma `is_marriage` i `marriage_id !== null`, menu pokazuje pozycję `DropdownMenuItem` „Szczegóły małżeństwa” nad separatorem „Dodaj małżonka”.
- Wybór otwiera panel przez `useMarriagePanel().open`, owinięte w `requestExit` edytora, gdy edytor istnieje.
- Pozostałe pozycje menu i `aria-label` triggera bez zmian.

#### 2. Link w wierszu małżeństwa w Rodzinie

**File**: `resources/js/components/persons/family-tab.tsx`

**Intent**: Z panelu osoby do panelu małżeństwa, bez zmiany tego, że nazwa małżonka otwiera panel małżonka.

**Contract**:
- `MarriageRow` dostaje w części `extra` `MarriagePanelLink` z nazwą dostępną „Szczegóły małżeństwa z {spouseName}”, tylko gdy `marriage.spouse !== null`. Wiersz z nieznanym małżonkiem (przypadek obronny, `CreateMarriage` zawsze wiąże dwie osoby) linku nie ma, bo panel wymaga dwóch małżonków.
- Menu wiersza („Usuń małżeństwo”) bez zmian.

#### 3. Przecentrowanie zachowuje panel małżeństwa

**File**: `resources/js/components/tree/sheet-person-card.tsx`

**Intent**: Panel nie znika przy zwykłej pracy na drzewie, jak panel osoby w S-06.

**Contract**: Zapytanie linku karty dodaje `marriage: currentId` z `useMarriagePanel()`, gdy panel małżeństwa jest otwarty. Dzisiejsze zachowanie `person` i `spouse` zostaje bez zmian.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Istniejące E2E drzewa przechodzą bez zmian w specach: `npx playwright test tests/e2e/tree-card-recentre.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts tests/e2e/person-panel-show-on-tree.spec.ts`

#### Manual Verification:

- Menu węzła wybranego związku ze ślubem ma „Szczegóły małżeństwa”, a związek bez ślubu i nieznany partner jej nie mają
- Rodzina panelu osoby: link „Szczegóły małżeństwa z …” podmienia panel osoby na panel małżeństwa, a nazwa małżonka nadal otwiera jego panel
- Przy otwartym panelu małżeństwa klik karty centruje drzewo, a panel zostaje otwarty; zamknięcie panelu zostawia drzewo w tym samym miejscu
- Brudna sekcja w panelu osoby i „Szczegóły małżeństwa” w menu węzła: najpierw pasek, a „Porzuć” otwiera panel małżeństwa

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 4: E2E panelu małżeństwa

### Overview

Jeden spec pilnuje przepływu, którego Pest i Vitest bez DOM nie widzą: wejście z węzła związku, zbiór dzieci w panelu i uogólniony strażnik. Faza idzie przez `/10x-e2e`: eksploracja `playwright-cli`, przegląd pod pięć antywzorców, weryfikacja celowym błędem.

### Changes Required:

#### 1. Helpery danych i nawigacji

**File**: `tests/e2e/support/api.ts`, `tests/e2e/support/navigation.ts`

**Intent**: Spec zakłada parę ze wspólnym dzieckiem i dzieckiem tylko jednego małżonka przez API i dochodzi do panelu bez lokatorów w specu.

**Contract**:
- `api.ts`: typ `PersonPreview` rozszerzony o `person.marriages: { id: number; spouse: { id: number } | null }[]`, żeby odczytać id małżeństwa.
- `api.ts`: helper dziecka pary i helper dziecka jednego rodzica, przez istniejące trasy `persons.children.store`/`attach`. Pola żądania sprawdzić w Form Requestach.
- `navigation.ts`: `openMarriageFromUnion(page)` wybiera „Szczegóły małżeństwa” przez `chooseUnionMenuItem`.
- `navigation.ts`: `marriagePanel(page, title)` zwraca `getByRole('dialog', { name: title })`.
- Sprzątanie przez istniejące `cleanupBySuffix` (usunięcie osoby usuwa jej małżeństwa).

#### 2. Spec przepływu

**File**: `tests/e2e/marriage-side-panel.spec.ts` (nowy)

**Intent**: Panel otwiera się dla właściwej pary, pokazuje tylko wspólne dzieci, a niezapisana data ślubu nie ginie po cichu.

**Contract**: Scenariusz z sufiksem z fixture:
1. Drzewo męża i „Szczegóły małżeństwa” z menu węzła.
2. Dialog nazwany „{mąż} i {żona}”.
3. Region „Dzieci” zawiera wspólne dziecko, a nie zawiera dziecka męża z innego związku.
4. „Edytuj” w karcie „Ślub”, zmiana daty bez zapisu.
5. Klik „Źródła” w menu bocznym (wizyta omijająca `requestExit`, jak w `person-panel-page-navigation-guard.spec.ts`) pokazuje alert „Masz niezapisane zmiany w małżeństwie”, a dialog nadal ma nazwę pary i wpisaną datę.
6. Klik wspólnego dziecka pokazuje ten sam alert, a dialog nadal ma nazwę pary.
7. „Zapisz” otwiera dialog dziecka.
8. Asercja na danych: data małżeństwa zapisana (props przez API).

Celowe błędy:
- layout nie przekazuje rekordu małżeństwa do `SectionEditorProvider` (`record = null`): nasłuch `before` się nie rejestruje, czerwono na alercie z kroku 5 (klik dziecka z kroku 6 przechodzi przez `requestExit` i tego nie złapie);
- dzieci pary to wszystkie dzieci jednego małżonka: czerwono na regionie „Dzieci”.

Przebieg z `--repeat-each=2`.

### Success Criteria:

#### Automated Verification:

- Nowy spec przechodzi dwukrotnie: `npx playwright test tests/e2e/marriage-side-panel.spec.ts --repeat-each=2`
- Pełne E2E przechodzi: `npx playwright test`
- Format i lint przechodzą (specy i helpery): `vendor/bin/sail npm run check`

#### Manual Verification:

- Oba celowe błędy dały czerwony wynik w oczekiwanym miejscu i zostały cofnięte (zapis w raporcie `/10x-e2e`)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- Vitest `side-panel-url.test.ts`: `marriagePanel` w `only`, wzajemne usuwanie kluczy `person`/`marriage`.

### Integration Tests:

- Pest `MarriagesTest`:
  - kształt `marriagePanel` na `familyScene` (`marriageA` → `childA`, `marriageB` → `childB`; childC i childUnknown nigdy);
  - kolejność małżonków (mężczyzna–kobieta i para z nieznaną płcią po dacie urodzenia);
  - 404 dla zdarzenia innego niż ślub, `null` bez klucza i dla nienumerycznej wartości;
  - działanie na trzech stronach;
  - przekierowanie `destroy` (Referer z `marriage`, bez Referera, z samym `person`).
- Pest `OwnershipBoundariesTest`: `?marriage=` cudzego małżeństwa na trzech stronach-hostach daje 404 i niezmieniony snapshot.
- Pest `TreeTest`: `marriage_id` w `unions`.
- E2E `marriage-side-panel.spec.ts`: wejście z węzła, dzieci pary, strażnik z „Zapisz”.

### Manual Testing Steps:

1. Drzewo osoby z dwoma małżeństwami przy 1366 px: przełączyć związek, „Szczegóły małżeństwa”, sprawdzić tytuł, dzieci i zwężenie drzewa.
2. W panelu edytować datę i miejsce (dodać nową miejscowość inline), zapisać, otworzyć panel małżonka i sprawdzić tę samą wartość w Przeglądzie.
3. Powiązać źródło („Pasujące akty” pierwsze), ustawić numer wpisu, odpiąć.
4. Z Rodziny panelu osoby wejść w „Szczegóły małżeństwa z …”, potem klik dziecka, a zamknięcie zostawia drzewo w miejscu.
5. Usunąć małżeństwo z nagłówka panelu: panel znika, związek na drzewie staje się „bez ślubu” (dzieci zostają), brak 404.
6. Poniżej `md`: panel pełnoekranowy, zamknięcie oddaje fokus.

## Performance Considerations

Resolver liczy się tylko przy `?marriage=` i w częściowych przeładowaniach z `only` paneli. Akcja ładuje zdarzenia małżonków i dzieci z góry, bez zapytań per osoba (uwaga N+1 z przeglądu S-07 dla `MarriageResource`).

## Migration Notes

Brak zmian schematu i danych.

## References

- Roadmapa: `context/foundation/roadmap.md` (S-12), PRD `context/foundation/prd.md:147-149` (FR-017), `:239` (bez świadków)
- Powłoka F-02: `context/archive/2026-10-04-side-panel-shell/plan.md`
- Panel osoby S-06: `context/archive/2026-10-05-person-side-panel/plan.md`, `follow-ups/review-fixes.md` (F3 globalny strażnik)
- Małżeństwo S-07: `context/archive/2026-09-25-marriage-with-shared-source/plan.md`
- Wzorzec resolvera: `app/Http/Middleware/HandleInertiaRequests.php:57-99`
- Wzorzec przekierowania po usunięciu: walidacja z `app/Http/Controllers/PersonController.php:140-164`, odczyt Referera z `app/Http/Controllers/SourceController.php:161-173`
- Edycja małżeństwa w panelu osoby: `resources/js/components/persons/overview-tab.tsx:271-314, 439-483`
- Wyrocznia rodziny: `tests/Pest.php:240-330` (`familyScene`)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend panelu małżeństwa

#### Automated

- [x] 1.1 Pest małżeństw, drzewa i granic przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/MarriagesTest.php tests/Feature/TreeTest.php tests/Feature/OwnershipBoundariesTest.php` — 6308889
- [x] 1.2 Pest pozostałych miejsc z małżeństwami przechodzi: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/WriteTargetsTest.php tests/Feature/DeleteImpactTest.php tests/Feature/SourcesTest.php` — 6308889
- [x] 1.3 Larastan przechodzi: `vendor/bin/sail composer types:check` — 6308889
- [x] 1.4 Pint czysty: `vendor/bin/sail bin pint --dirty --format agent` — 6308889
- [x] 1.5 Typy przechodzą po dodaniu `marriage_id`: `vendor/bin/sail npm run types:check` — 6308889

#### Manual

- [x] 1.6 `/tree/{id}?marriage={id}` w przeglądarce zwraca stronę, a w props Inertii (devtools) jest `marriagePanel` z małżonkami w kolejności mężczyzna–kobieta i samymi wspólnymi dziećmi — 6308889

### Phase 2: Uogólniony strażnik i panel małżeństwa

#### Automated

- [x] 2.1 Format i lint przechodzą: `vendor/bin/sail npm run check` — 391214a
- [x] 2.2 Typy przechodzą: `vendor/bin/sail npm run types:check` — 391214a
- [x] 2.3 Vitest przechodzi, w tym nowe przypadki rejestru: `vendor/bin/sail npm run test:js` — 391214a
- [x] 2.4 Build przechodzi: `vendor/bin/sail npm run build` — 391214a
- [x] 2.5 Istniejące E2E panelu osoby i strażnika przechodzą bez zmian w specach: `npx playwright test tests/e2e/person-side-panel.spec.ts tests/e2e/person-panel-unsaved-guard.spec.ts tests/e2e/person-panel-page-navigation-guard.spec.ts tests/e2e/tree-slot-spouse.spec.ts` — 391214a

#### Manual

- [x] 2.6 `/tree/{id}?marriage={id}` przy 1366 px pokazuje panel obok zwężonego drzewa: tytuł mężczyzna i kobieta, karta „Ślub”, źródła, tylko wspólne dzieci — 391214a
- [x] 2.7 Edycja daty i miejsca w karcie „Ślub” zapisuje się, a panel osoby małżonka pokazuje nową wartość — 391214a
- [x] 2.8 Powiązanie źródła, numer wpisu i odpięcie w panelu małżeństwa działają, a panel zostaje otwarty — 391214a
- [x] 2.9 Brudna karta „Ślub” i kolejno X, Escape, klik dziecka, klik „Źródła” w menu bocznym: każde pokazuje pasek „Masz niezapisane zmiany w małżeństwie” — 391214a
- [x] 2.10 „Usuń małżeństwo” w nagłówku: po potwierdzeniu panel znika, drzewo zostaje w tym samym miejscu, bez strony 404 — 391214a

### Phase 3: Wejścia do panelu

#### Automated

- [x] 3.1 Format i lint przechodzą: `vendor/bin/sail npm run check`
- [x] 3.2 Typy przechodzą: `vendor/bin/sail npm run types:check`
- [x] 3.3 Istniejące E2E drzewa przechodzą bez zmian w specach: `npx playwright test tests/e2e/tree-card-recentre.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts tests/e2e/person-panel-show-on-tree.spec.ts`

#### Manual

- [ ] 3.4 Menu węzła wybranego związku ze ślubem ma „Szczegóły małżeństwa”, a związek bez ślubu i nieznany partner jej nie mają
- [ ] 3.5 Rodzina panelu osoby: link „Szczegóły małżeństwa z …” podmienia panel osoby na panel małżeństwa, a nazwa małżonka nadal otwiera jego panel
- [ ] 3.6 Przy otwartym panelu małżeństwa klik karty centruje drzewo, a panel zostaje otwarty; zamknięcie panelu zostawia drzewo w tym samym miejscu
- [ ] 3.7 Brudna sekcja w panelu osoby i „Szczegóły małżeństwa” w menu węzła: najpierw pasek, a „Porzuć” otwiera panel małżeństwa

### Phase 4: E2E panelu małżeństwa

#### Automated

- [ ] 4.1 Nowy spec przechodzi dwukrotnie: `npx playwright test tests/e2e/marriage-side-panel.spec.ts --repeat-each=2`
- [ ] 4.2 Pełne E2E przechodzi: `npx playwright test`
- [ ] 4.3 Format i lint przechodzą (specy i helpery): `vendor/bin/sail npm run check`

#### Manual

- [ ] 4.4 Oba celowe błędy dały czerwony wynik w oczekiwanym miejscu i zostały cofnięte (zapis w raporcie `/10x-e2e`)
