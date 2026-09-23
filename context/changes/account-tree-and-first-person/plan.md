# Konto z gotowym drzewem i pierwsza osoba — Implementation Plan

## Overview

Wycinek S-01 z roadmapy M-1. Rejestracja konta od razu tworzy jedno drzewo użytkownika (FR-001–FR-003), a zalogowany użytkownik może dodać do niego osobę z imieniem, nazwiskiem, urodzeniem, zamieszkaniem, wyznaniem i zgonem, z datami pełnymi, niepełnymi lub „około” (FR-005). Osobę widzi na liście i w podglądzie (FR-008). Dane innych użytkowników są niewidoczne (Access Control). Przy okazji interfejs, który dotykamy, przechodzi na polski, a reset hasła ze starter kitu znika (PRD Non-Goals).

Model urodzenia i zgonu jako **wydarzeń z uczestnikami** jest tu kluczowy: S-03 powiąże skany z wydarzeniem, a S-07 doda małżeństwo jako jedno wydarzenie dwóch osób. Żaden z tych wycinków nie powinien wymagać przebudowy.

## Current State Analysis

- Konto tworzy `CreateNewUser::create` (`app/Actions/Fortify/CreateNewUser.php:20-32`): walidacja, potem goły `User::create`, bez transakcji, zdarzeń i listenerów.
- Nie ma schematu domenowego. Migracje obejmują tylko users, cache, jobs, passkeys i 2FA. Nie ma katalogów `app/Policies`, `app/Enums` ani domenowych kontrolerów.
- Konwencje backendu:
  - kontrolery w stylu resource z typami zwracanymi (`app/Http/Controllers/Settings/ProfileController.php`);
  - Form Requests w `app/Http/Requests/Settings/` z regułami typowanymi `array<string, ValidationRule|array<mixed>|string>`;
  - toasty przez `Inertia::flash('toast', …)`;
  - bez `strict_types`; Larastan poziom 7 (`phpstan.neon:13`).
- Konwencje frontendu:
  - wszystkie formularze to Inertia `<Form {...Controller.action.form()}>` z render-propem `({ processing, errors })` (`resources/js/pages/settings/profile.tsx:40-47`);
  - pola według wzoru `Label` + `Input` + `InputError`;
  - layout przydzielany centralnie w `resources/js/app.tsx:13-24` (nowe strony dostają `AppLayout`);
  - breadcrumbs przez statyczne `Page.layout`.
- UI jest w całości po angielsku. Nie ma katalogu `lang/`, a `APP_LOCALE=en` (`config/app.php:81`, `.env.example:8`, `.env.prod.example:7`).
- `dashboard` to pusty placeholder (`routes/web.php:7-9`, `config/fortify.php:76` `home => '/dashboard'`). Odwołują się do niego `welcome.tsx`, `app-header.tsx`, `app-sidebar.tsx`, `passkey-verify.tsx`, `DashboardTest` oraz asercje przekierowań w `RegistrationTest`, `AuthenticationTest`, `EmailVerificationTest` i `VerificationNotificationTest`.
- Reset hasła jest podpięty w 7 miejscach:
  - `config/fortify.php:165`;
  - `FortifyServiceProvider.php:6,42,52,56-64`;
  - `app/Actions/Fortify/ResetUserPassword.php`;
  - `pages/auth/forgot-password.tsx`, `pages/auth/reset-password.tsx`;
  - link w `pages/auth/login.tsx:12,17,20,53-61`;
  - `tests/Feature/Auth/PasswordResetTest.php`.
- Testy lokalnie działają na pgsql, a w CI na sqlite (`composer setup` kopiuje `.env.example`). Schemat i zapytania muszą działać na obu.
- Produkcja ma już za sobą pierwszy deploy, więc mogą na niej istnieć konta bez drzewa.

## Desired End State

- Po rejestracji użytkownik ma dokładnie jedno drzewo i trafia na listę osób. Ta sama lista jest stroną startową po zalogowaniu.
- Konta istniejące przed tą zmianą też mają drzewo, bo utworzyła je migracja.
- Formularz „Dodaj osobę”, po polsku, ma pola:
  - imię, nazwisko, miejsce zamieszkania;
  - wyznanie z listy;
  - urodzenie (rok/miesiąc/dzień, „około”, miejsce);
  - checkbox „Zmarły/a” z polami zgonu.
- Zapis tworzy osobę i zawsze jej wydarzenie urodzenia. Wydarzenie zgonu powstaje tylko przy zaznaczonym checkboxie. Potem następuje przejście na podgląd osoby.
- Lista osób jest posortowana po nazwisku, potem imieniu; osoby bez nazwiska są na końcu. Każda pozycja prowadzi do podglądu.
- Podgląd pokazuje dane osoby oraz sekcje „Urodzenie” i „Zgon” z datą sformatowaną po polsku (`12.03.1850`, `03.1850`, `1850`, `ok. 1850`) i miejscem, a w braku danych tekst „brak danych”.
- Osoba z cudzego drzewa zwraca 404. Gość jest przekierowywany do logowania.
- Komunikaty walidacji są po polsku.
- Nie ma już ścieżek `/forgot-password` ani `/reset-password`, a logowanie nie ma linku „Zapomniałeś hasła?”.

Weryfikacja: `vendor/bin/sail artisan test --compact` jest zielone, statyczne kontrole (Pint, Larastan, `tsc`, `vp check`) przechodzą, a ręczny przebieg rejestracja → dodanie osoby → lista → podgląd działa w przeglądarce.

### Key Discoveries:

- `app/Actions/Fortify/CreateNewUser.php:27` — jedyne miejsce tworzenia użytkownika. Seeder używa `WithoutModelEvents` (`database/seeders/DatabaseSeeder.php:11`), więc drzewo **nie może** powstawać w observerze modelu. Powstaje jawnie w akcji.
- `resources/js/components/nav-main.tsx:23` używa `isCurrentUrl` (dokładne dopasowanie). `useCurrentUrl` ma też `isCurrentOrParentUrl` (`resources/js/layouts/settings/layout.tsx:32`), potrzebne, żeby „Osoby” były podświetlone także na `/persons/create` i `/persons/{id}`.
- `vite.config.ts:27-29` — Wayfinder z `formVariants: true`. Wygenerowane `resources/js/{routes,actions,wayfinder}` są w `.gitignore`, więc usunięte trasy łamią `tsc`, dopóki importy nie zostaną poprawione.
- `resources/js/components/ui/` ma `select`, `checkbox`, `card`, `badge`, `label`, `input` i `separator`. Nie ma `table` ani `textarea`, ale ta zmiana ich nie wymaga.
- `laravel-lang/common` 6.8 instaluje się czysto na Laravel 13.32 (sprawdzone `composer require --dry-run` 2026-09-23).
- `config/database.php:40` — sqlite ma włączone klucze obce, więc kaskady zachowują się w CI tak jak na pgsql.

## What We're NOT Doing

- Edycji i usuwania osoby (S-05). Z tego samego powodu nie rozstrzygamy, co dzieje się z wydarzeniami osieroconymi po usunięciu osoby.
- Relacji rodzic–dziecko ani małżeństwa (S-06, S-07). Typ wydarzenia `marriage` i rola `spouse` **nie są** dodawane teraz, tylko schemat je dopuszcza.
- Skanów i powiązań wydarzenie ↔ skan (S-02, S-03).
- Wyszukiwania na liście (S-04) ani paginacji listy (skala: setki osób).
- Słownika miejsc i autouzupełniania. Miejsca są wolnym tekstem.
- Walidacji spójności między wydarzeniami, np. „zgon przed urodzeniem”.
- Polskiego sortowania z kolacją językową. Kolejność liter diakrytycznych zależy od kolacji bazy.
- Tłumaczenia własnych tekstów stron auth i settings (napisy w React). Polskie stają się tylko komunikaty backendu z paczki laravel-lang.
- Zmiany weryfikacji e-mail (`MustVerifyEmail` pozostaje wyłączone).
- Usuwania tabeli `password_reset_tokens` ani brokera haseł w `config/auth.php`. Są nieszkodliwe.
- Wdrożenia na produkcję w ramach faz. Deploy jest ręczny (`scripts/deploy.sh`), po merge do `main`.

## Implementation Approach

Najpierw model danych i drzewo przy rejestracji, testowane bez UI. Wszystkie późniejsze wycinki opierają się na tym schemacie, więc jego testy są najważniejsze. Potem pionowy wycinek „dodaj i obejrzyj osobę” razem z polską lokalizacją, bo walidacja i strony powstają w tym samym kroku. Na koniec małe, izolowane usunięcie resetu hasła.

Izolacja danych: każde zapytanie domenowe idzie od `$request->user()->tree`. Dostęp do pojedynczej osoby dodatkowo pilnuje `PersonPolicy`, która dla cudzej osoby odpowiada 404 (`Response::denyAsNotFound()`), żeby nie zdradzać istnienia rekordu.

## Critical Implementation Details

- **Nazwa tabeli osób:** Laravel pluralizuje `Person` do `people`. Zostajemy przy domyślnej tabeli `people`, bez nadpisywania `$table`, a trasy i katalog stron nazywamy `persons`.
- **Nazwa modelu `Event`:** koliduje nazwą z fasadą `Illuminate\Support\Facades\Event`, zwłaszcza w testach (`Event::fake`). W plikach, które potrzebują obu, trzeba użyć aliasu importu.
- **Uzupełnienie drzew to osobna migracja** z `up()` na query builderze (nie na modelach, bo model może się zmienić w przyszłych wycinkach). Dzięki temu test może wywołać jej `up()` bezpośrednio.

## Phase 1: Drzewo zakładane razem z kontem

### Overview

Schemat domenowy (drzewo, osoba, wydarzenie, uczestnik wydarzenia), modele z relacjami, enumy, fabryki i obiekt daty częściowej. Rejestracja tworzy drzewo w jednej transakcji z kontem, a istniejące konta dostają drzewo z migracji.

### Changes Required:

#### 1. Migracje schematu

**File**: `database/migrations/2026_09_23_000001_create_trees_table.php`, `…_000002_create_people_table.php`, `…_000003_create_events_table.php`, `…_000004_create_event_participants_table.php` (generowane `vendor/bin/sail artisan make:migration`, więc prefiksy dat mogą się różnić)

**Intent**: Wprowadzić model „osoba → wydarzenie” zdolny przyjąć małżeństwo (dwóch uczestników) i powiązania skanów (po `event_id`) bez przebudowy.

**Contract**:
- `trees`: `id`, `user_id` (FK → users, **unique**, cascadeOnDelete), timestamps.
- `people`:
  - `id`, `tree_id` (FK → trees, cascadeOnDelete);
  - `first_name` string nullable, `last_name` string nullable;
  - `residence` string nullable, `religion` string nullable (wartość enumu `Religion`);
  - timestamps;
  - indeks (`tree_id`, `last_name`, `first_name`).
- `events`:
  - `id`, `tree_id` (FK → trees, cascadeOnDelete);
  - `type` string (wartość `EventType`);
  - `date_year` unsignedSmallInteger nullable, `date_month` unsignedTinyInteger nullable, `date_day` unsignedTinyInteger nullable;
  - `date_is_approximate` boolean default false;
  - `place` string nullable;
  - timestamps.
- `event_participants`:
  - `id`, `event_id` (FK → events, cascadeOnDelete), `person_id` (FK → people, cascadeOnDelete);
  - `role` string (wartość `ParticipantRole`);
  - timestamps;
  - unique (`event_id`, `person_id`).
- Usunięcie konta (istniejące `ProfileController::destroy`) kaskadowo usuwa drzewo, osoby, wydarzenia i uczestników.

#### 2. Uzupełnienie drzew dla istniejących kont

**File**: `database/migrations/2026_09_23_000005_create_trees_for_existing_users.php`

**Intent**: Konta założone przed tą zmianą (produkcja, lokalne) dostają drzewo, więc niezmiennik „każdy użytkownik ma dokładnie jedno drzewo” obowiązuje od razu.

**Contract**: `up()` wstawia wiersz `trees` dla każdego `users.id`, który go nie ma (query builder, idempotentnie). `down()` nic nie robi, bo tabela znika w migracji `trees`.

#### 3. Enumy

**File**: `app/Enums/EventType.php`, `app/Enums/ParticipantRole.php`, `app/Enums/Religion.php`

**Intent**: Zamknięte zbiory wartości zapisywane jako stringi, z polskimi etykietami do UI.

**Contract**:
- `EventType: string` — `Birth = 'birth'`, `Death = 'death'`.
- `ParticipantRole: string` — `Subject = 'subject'`.
- `Religion: string`, każdy przypadek z `label(): string`:

  | Przypadek       | Wartość            | Etykieta          |
  | --------------- | ------------------ | ----------------- |
  | `RomanCatholic` | `'roman_catholic'` | rzymskokatolickie |
  | `GreekCatholic` | `'greek_catholic'` | grekokatolickie   |
  | `Evangelical`   | `'evangelical'`    | ewangelickie      |
  | `Orthodox`      | `'orthodox'`       | prawosławne       |
  | `Jewish`        | `'jewish'`         | mojżeszowe        |
  | `Other`         | `'other'`          | inne              |

- `Religion` ma też statyczną `options(): array<int, array{value: string, label: string}>` dla formularza.

#### 4. Obiekt daty częściowej

**File**: `app/Support/PartialDate.php`

**Intent**: Jedno miejsce, które zna reguły daty niepełnej i formatu wyświetlania, używane przez model i zasoby.

**Contract**:
- Klasa `final readonly` z polami `?int $year`, `?int $month`, `?int $day`, `bool $isApproximate`.
- `isEmpty(): bool`.
- `format(): ?string` zwraca `null`, gdy brak roku. Poza tym `DD.MM.YYYY`, `MM.YYYY` lub `YYYY` (dzień i miesiąc dopełnione zerem), z prefiksem `ok. ` przy `isApproximate`.

#### 5. Modele i relacje

**File**: `app/Models/Tree.php`, `app/Models/Person.php`, `app/Models/Event.php`, `app/Models/EventParticipant.php`, `app/Models/User.php`

**Intent**: Relacje Eloquent odzwierciedlające schemat, zgodnie ze stylem `User` (atrybuty `#[Fillable]`, `casts()`, docblock `@property`).

**Contract**:
- `User::tree(): HasOne`.
- `Tree`:
  - `user(): BelongsTo`, `people(): HasMany`, `events(): HasMany`.
- `Person`:
  - `tree(): BelongsTo`;
  - `events(): BelongsToMany` (przez `event_participants`, `using(EventParticipant::class)`, `withPivot('role')`);
  - rzutowanie `religion` na `Religion`;
  - `birth(): ?Event` i `death(): ?Event` szukają w załadowanej kolekcji `events` po typie;
  - `displayName(): string`, z `NN` w miejscu brakującego imienia lub nazwiska.
- `Event`:
  - `tree(): BelongsTo`, `participants(): BelongsToMany` (osoby);
  - rzutowanie `type` na `EventType`, `date_is_approximate` na bool;
  - akcesor `date(): Attribute` zwracający `PartialDate`.
- `EventParticipant extends Pivot`, rzutuje `role` na `ParticipantRole`.

#### 6. Fabryki

**File**: `database/factories/TreeFactory.php`, `PersonFactory.php`, `EventFactory.php`, `database/factories/UserFactory.php`

**Intent**: Testy tworzą spójne dane bez ręcznego składania relacji.

**Contract**:
- `UserFactory`:
  - `configure()` z `afterCreating` tworzy drzewo użytkownika, co odzwierciedla niezmiennik z produkcji;
  - stan `withoutTree()` pomija tworzenie drzewa (dla testu migracji uzupełniającej).
- `PersonFactory` — `for(Tree)`.
- `EventFactory`:
  - stany `birth()`, `death()`, `approximate()`;
  - stan `withoutDate()`, w którym wszystkie pola daty są `null`.

#### 7. Drzewo przy rejestracji

**File**: `app/Actions/Fortify/CreateNewUser.php`

**Intent**: Konto i drzewo powstają atomowo (FR-003: drzewo dostępne od razu po rejestracji).

**Contract**: `create()` po walidacji, w `DB::transaction`, tworzy `User`, a potem `$user->tree()->create()`. Zwraca `User`.

#### 8. Testy

**File**: `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/TreeBackfillTest.php`, `tests/Unit/PartialDateTest.php`

**Intent**: Testy regresji dla niezmiennika drzewa i formatu dat, na którym opierają się kolejne wycinki.

**Contract**:
- Rejestracja tworzy dokładnie jedno drzewo należące do nowego użytkownika.
- Migracja uzupełniająca (`require` pliku znalezionego przez `glob(database_path('migrations/*_create_trees_for_existing_users.php'))`, bo prefiks daty może się różnić, i `up()`):
  - tworzy drzewo użytkownikowi, który go nie miał (`User::factory()->withoutTree()`);
  - nie tworzy drugiego drzewa temu, który je ma.
- `PartialDate::format()`:
  - pełna data → `12.03.1850`;
  - rok i miesiąc → `03.1850`;
  - sam rok → `1850`;
  - `około` + rok → `ok. 1850`;
  - brak roku → `null`.

### Success Criteria:

#### Automated Verification:

- Migrations apply cleanly on pgsql: `vendor/bin/sail artisan migrate:fresh --no-interaction`
- Phase 1 tests pass: `vendor/bin/sail artisan test --compact tests/Feature/Auth/RegistrationTest.php tests/Feature/TreeBackfillTest.php tests/Unit/PartialDateTest.php`
- Static analysis passes: `vendor/bin/sail composer types:check`
- Formatting passes: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual Verification:

- On the local database with existing accounts, `vendor/bin/sail artisan migrate` creates exactly one tree for every user (check with a `trees` vs `users` query)

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 2: Dodanie i podgląd osoby po polsku

### Overview

Polska lokalizacja backendu, trasy, polityka, walidacja i akcja tworzenia osoby. Do tego trzy strony React (lista, formularz, podgląd). Lista osób zastępuje Dashboard jako stronę startową.

### Changes Required:

#### 1. Polska lokalizacja

**File**: `composer.json`, `lang/pl/*.php`, `lang/pl.json`, `.env.example`, `.env.prod.example`, `config/app.php`

**Intent**: Komunikaty walidacji i backendu po polsku (PRD: interfejs wyłącznie po polsku), bez pisania tłumaczeń ręcznie.

**Contract**:
- `vendor/bin/sail composer require laravel-lang/common --dev` (zgoda na zależność: rozmowa planistyczna 2026-09-23), potem `vendor/bin/sail artisan lang:add pl`.
- Opublikowane pliki `lang/` są commitowane, więc produkcja (`--no-dev`) nie potrzebuje paczki.
- `APP_LOCALE=pl` i `APP_FAKER_LOCALE=pl_PL` w `.env.example` i `.env.prod.example`. Domyślna wartość `'locale'` w `config/app.php` to `'pl'`. `APP_FALLBACK_LOCALE` zostaje `en`.
- Istniejące testy, które sprawdzają angielskie komunikaty, dostosować do polskich lub do kluczy.

#### 2. Trasy i strona startowa

**File**: `routes/web.php`, `config/fortify.php`

**Intent**: Lista osób jest domem zalogowanego użytkownika. Placeholder Dashboard znika.

**Contract**:
- W grupie `['auth', 'verified']`: `Route::resource('persons', PersonController::class)->only(['index', 'create', 'store', 'show'])`. Daje to nazwy `persons.index|create|store|show`, parametr `{person}` i model `Person`.
- Trasa `dashboard` jest usunięta.
- `config/fortify.php` `'home' => '/persons'`.

#### 3. Polityka dostępu

**File**: `app/Policies/PersonPolicy.php`

**Intent**: Guardrail „żadnych cudzych danych” dla pojedynczego rekordu.

**Contract**: `view(User $user, Person $person): Response` zwraca `allow`, gdy `$person->tree_id === $user->tree->id`, w przeciwnym razie `Response::denyAsNotFound()`. Polityka jest wykrywana automatycznie po konwencji nazw.

#### 4. Walidacja formularza osoby

**File**: `app/Http/Requests/PersonStoreRequest.php`

**Intent**: Wymusić reguły z rozmowy: imię lub nazwisko, poprawne daty częściowe, „około” tylko z rokiem, wyznanie z listy, pola zgonu tylko przy zaznaczonym „Zmarły/a”.

**Contract** — pola wejściowe (nazwy są kontraktem z formularzem React):
- `first_name`: `required_without:last_name`, string, max 255.
- `last_name`: `required_without:first_name`, string, max 255.
- `residence`: nullable string, max 255.
- `religion`: nullable, `Rule::enum(Religion::class)`.
- `birth.year`, `birth.month`, `birth.day`, `birth.is_approximate`, `birth.place` oraz te same pola dla `death.*`:
  - `year`: nullable integer, 1000–bieżący rok; wymagany, gdy podano `month` lub zaznaczono `is_approximate`;
  - `month`: nullable integer 1–12; wymagany, gdy podano `day`;
  - `day`: nullable integer 1–31;
  - `is_approximate`: boolean;
  - `place`: nullable string, max 255.
- Poprawność pełnej daty (`checkdate`, np. 31.02 odrzucone) sprawdza hook `after()` z błędem na `birth.day` lub `death.day`.
- `is_deceased`: boolean. Pola `death.*` są `prohibited`, gdy `is_deceased` nie jest zaznaczone (`prohibited_unless:is_deceased,1`).
- Checkboxy wysyłają `"1"` (patrz §7), bo Radix domyślnie wysyła `"on"`, którego reguła `boolean` nie przyjmuje. Reguły warunkowe opierają się na wartości `"1"`, np. `year` z `required_if_accepted:birth.is_approximate`.
- `attributes()` zwraca polskie nazwy pól (np. `birth.year` → „rok urodzenia”).

#### 5. Akcja tworzenia osoby

**File**: `app/Actions/Persons/CreatePerson.php`

**Intent**: Jedno miejsce z regułą „urodzenie zawsze, zgon tylko dla zmarłych”, atomowo.

**Contract**:
- `handle(Tree $tree, array $data): Person` w `DB::transaction`:
  - tworzy osobę;
  - tworzy wydarzenie `Birth` (także puste) z uczestnikiem `Subject`;
  - gdy `is_deceased`, tworzy wydarzenie `Death` (także puste) z uczestnikiem `Subject`.
- `$data` ma kształt zwalidowanych danych z `PersonStoreRequest` (opisany w PHPDoc jako array shape).

#### 6. Kontroler i zasoby

**File**: `app/Http/Controllers/PersonController.php`, `app/Http/Resources/PersonResource.php`, `app/Http/Resources/EventResource.php`

**Intent**: Cienki kontroler według wzoru `Settings/ProfileController`. Zasoby ustalają kształt propsów Inertia.

**Contract**:
- `index(Request): Response` renderuje `persons/index` z `persons` = osoby drzewa użytkownika, z załadowanymi `events` (bez N+1).
  - Kolejność: najpierw osoby z nazwiskiem, potem bez (`orderByRaw('last_name is null')`, działa na pgsql i sqlite), następnie `last_name`, `first_name`, `id`.
- `create(): Response` renderuje `persons/create` z `religions` = `Religion::options()`.
- `store(PersonStoreRequest, CreatePerson): RedirectResponse`:
  - wywołuje akcję z drzewem użytkownika;
  - flash toast „Dodano osobę”;
  - przekierowuje na `persons.show`.
- `show(Person): Response`: `Gate::authorize('view', $person)` i render `persons/show` z `person`.
- `PersonResource` zwraca:
  - `id`, `first_name`, `last_name`, `display_name`, `residence`;
  - `religion` jako `{value, label} | null`;
  - `birth` i `death` jako `EventResource | null`.
- `EventResource` zwraca `type`, `date` (`PartialDate::format()`, `string | null`), `place`.

#### 7. Strony React

**File**:
- `resources/js/pages/persons/index.tsx`, `create.tsx`, `show.tsx`;
- `resources/js/components/persons/partial-date-fields.tsx`;
- `resources/js/types/person.ts` (eksport z `resources/js/types/index.ts`, jeśli istnieje barrel).

**Intent**: Trzy ekrany po polsku, zbudowane z istniejących komponentów `ui/*` i wzoru formularza z `settings/profile.tsx`.

**Contract**:
- `persons/index`:
  - nagłówek „Osoby” i przycisk „Dodaj osobę”;
  - lista pozycji (`display_name` oraz daty urodzenia i zgonu, gdy są), każda jako `Link` do `persons.show`;
  - pusty stan z zachętą do dodania pierwszej osoby.
- `persons/create`:
  - `<Form {...PersonController.store.form()}>` z polami: imię, nazwisko, miejsce zamieszkania, wyznanie (`ui/select` z `name="religion"` i pustą opcją);
  - sekcje „Urodzenie” i „Zgon” z `PartialDateFields prefix="birth" | "death"` (rok/miesiąc/dzień jako `Input type="number"`, `Checkbox` „około”, miejsce);
  - `Checkbox name="is_deceased"` „Zmarły/a”, który pokazuje lub ukrywa sekcję zgonu;
  - `InputError` pod każdym polem;
  - każdy `Checkbox` (`is_deceased`, `*.is_approximate`) ma `value="1"`.
- `persons/show`:
  - `Heading` z `display_name`;
  - dane osoby (zamieszkanie, wyznanie, z „brak danych” zamiast pustych wartości);
  - karty „Urodzenie” i „Zgon” (data, miejsce albo „brak danych”). Karta „Zgon” jest widoczna tylko wtedy, gdy wydarzenie istnieje.
- Breadcrumbs:
  - `Osoby` na liście;
  - `Osoby › Dodaj osobę` na formularzu;
  - `Osoby › <display_name>` na podglądzie (dynamicznie, np. `setLayoutProps`).
- `<Head title>` po polsku.

#### 8. Nawigacja i usunięcie Dashboardu

**File**:
- `resources/js/components/app-sidebar.tsx`, `nav-main.tsx`, `app-header.tsx`, `passkey-verify.tsx`;
- `resources/js/pages/welcome.tsx`;
- `resources/js/pages/dashboard.tsx` (usunięty).

**Intent**: Jedyną pozycją nawigacji domenowej jest „Osoby”. Nic nie odwołuje się do usuniętej trasy `dashboard`.

**Contract**:
- W `mainNavItems` pozycja `{ title: 'Osoby', href: persons.index(), icon: Users }` zamiast Dashboard.
- Etykieta grupy w `nav-main` zmienia się z „Platform” na „Drzewo”.
- Aktywna pozycja jest wybierana przez `isCurrentOrParentUrl`.
- Wszystkie linki `dashboard()` (logo, welcome, header) prowadzą do `persons.index()`.
- `passkey-verify.tsx:34` ma literał `'/dashboard'` (nie `dashboard()`), którego `tsc` nie wykryje. Trzeba go zamienić na `persons.index().url`.

#### 9. Testy

**File**: `tests/Feature/PersonsTest.php` (zastępuje `tests/Feature/DashboardTest.php`, zgoda z rozmowy planistycznej), asercje przekierowań w `tests/Feature/Auth/{RegistrationTest,AuthenticationTest,EmailVerificationTest,VerificationNotificationTest}.php`

**Intent**: Pokryć izolację danych, regułę wydarzeń, sortowanie i walidację: zachowania, na których opiera się S-03/S-04/S-05.

**Contract** — nazwane przypadki:
- Gość jest przekierowany do logowania z `persons.index`, `persons.create`, `persons.show` i `persons.store`.
- `persons.index` pokazuje wyłącznie osoby z drzewa zalogowanego użytkownika, w kolejności nazwisko → imię, z osobami bez nazwiska na końcu.
- `persons.show` z osobą innego użytkownika zwraca 404.
- `store`:
  - bez zaznaczonego „Zmarły/a” tworzy osobę i jedno wydarzenie `birth` z uczestnikiem `subject`, nawet gdy pola urodzenia są puste;
  - z zaznaczonym „Zmarły/a” i pustymi polami zgonu tworzy dodatkowo puste wydarzenie `death`;
  - przekierowuje na `persons.show`.
- `store` odrzuca:
  - brak imienia i nazwiska;
  - dzień bez miesiąca;
  - miesiąc bez roku;
  - 31.02.1850;
  - rok z przyszłości;
  - „około” bez roku;
  - nieznane wyznanie;
  - pola `death.*` bez „Zmarły/a”.
- Komunikat walidacji jest po polsku (jedna asercja na treść).
- Przypadki `store` wysyłają checkboxy jako `"1"`, tak jak formularz (nie `true`).
- Po zalogowaniu i po rejestracji następuje przekierowanie na `persons.index`.

### Success Criteria:

#### Automated Verification:

- Persons and auth tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/Auth`
- Static analysis passes: `vendor/bin/sail composer types:check`
- Formatting passes: `vendor/bin/sail bin pint --dirty --format agent`
- TypeScript passes: `vendor/bin/sail npm run types:check`
- Frontend lint/format passes: `vendor/bin/sail npm run check`
- Production build succeeds: `vendor/bin/sail npm run build`
- Full suite passes: `vendor/bin/sail artisan test --compact`

#### Manual Verification:

- A newly registered account lands on the empty "Osoby" list with a prompt to add the first person
- Adding a person with a date "ok. 1850", a place and religion shows the preview with Polish formatting, and the person appears on the list in the right position
- Checking "Zmarły/a" with no data shows a "Zgon" card with "brak danych"; leaving it unchecked shows no death card
- Invalid dates (e.g. 31.02) show Polish error messages next to the right fields
- Opening another account's person URL shows a 404

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Phase 3: Usunięcie resetu hasła

### Overview

Zamknięcie rozbieżności z PRD (Non-Goals: brak odzyskiwania hasła). Funkcja resetu i wszystkie jej ślady w UI znikają.

### Changes Required:

#### 1. Wyłączenie funkcji w Fortify

**File**: `config/fortify.php`, `app/Providers/FortifyServiceProvider.php`, `app/Actions/Fortify/ResetUserPassword.php` (usunięty)

**Intent**: Fortify nie rejestruje tras `password.request|email|reset|update`.

**Contract**:
- `Features::resetPasswords()` usunięte z listy funkcji.
- Usunięte z `FortifyServiceProvider`: import i `resetUserPasswordsUsing`, `resetPasswordView`, `requestPasswordResetLinkView` oraz prop `canResetPassword` widoku logowania.
- Trasa `user-password.update` (zmiana hasła w ustawieniach) zostaje bez zmian.

#### 2. Frontend

**File**: `resources/js/pages/auth/login.tsx`, `resources/js/pages/auth/forgot-password.tsx` (usunięty), `resources/js/pages/auth/reset-password.tsx` (usunięty)

**Intent**: Brak linku „Forgot your password?” i brak martwych stron.

**Contract**: `login.tsx` bez importu `@/routes/password`, bez propa `canResetPassword` i bez `TextLink` resetu.

#### 3. Testy

**File**: `tests/Feature/Auth/PasswordResetTest.php` (usunięty, zgoda z rozmowy planistycznej), `tests/Feature/Auth/AuthenticationTest.php`

**Intent**: Testy stanu, którego już nie ma, zastępuje asercja, że funkcja jest wyłączona.

**Contract**: Nowy test: `GET /forgot-password` zwraca 404, a trasa `password.request` nie istnieje.

### Success Criteria:

#### Automated Verification:

- Auth tests pass: `vendor/bin/sail artisan test --compact tests/Feature/Auth`
- No remaining references to the reset: `grep -rn "resetPasswords\|ResetUserPassword\|forgot-password\|canResetPassword\|'@/routes/password'" app config resources/js/pages resources/js/components` returns nothing (tests are excluded, because the new test deliberately checks `/forgot-password`; the quoted module name deliberately does not match `@/routes/password/confirm`, which stays)
- Static analysis passes: `vendor/bin/sail composer types:check`
- Formatting passes: `vendor/bin/sail bin pint --dirty --format agent`
- TypeScript and build pass: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run build`
- Full suite passes: `vendor/bin/sail artisan test --compact`

#### Manual Verification:

- The login page has no "forgot your password" link, and `/forgot-password` shows a 404
- Changing the password in settings still works

**Implementation Note**: After completing this phase and all automated verification passes, pause here for manual confirmation from the human that the manual testing was successful before proceeding to the next phase.

---

## Testing Strategy

### Unit Tests:

- `PartialDate::format()`: all precisions, "około", no year.

### Integration Tests:

- Registration creates the tree; the backfill migration is idempotent.
- `PersonsTest`: isolation (list, 404 on preview), event rule (birth always, death only when "Zmarły/a" is checked), sorting with no surname at the end, the full list of validation rejections, Polish messages.
- Redirects after login and registration go to `persons.index`; the reset is unavailable.

### Manual Testing Steps:

1. Register a new account, confirm you land on the empty "Osoby" list.
2. Add "Jan Kowalski", birth "ok. 1850", place "Wieliczka", religion "rzymskokatolickie". Check the preview.
3. Add "Maria" with no surname and "Zmarły/a" checked with no data. Check the "NN" label, the list order and the "Zgon: brak danych" card.
4. Submit the form with 31.02.1850 and check the Polish error.
5. In another browser, log into a second account and open the first account's person URL (404).
6. Log out and check the login page (no reset link) and `/forgot-password` (404).

## Performance Considerations

The list loads all of the user's people with their events in one eager-loaded query (scale: hundreds of people, PRD `data_volume: small`). Pagination and search are S-04's job.

## Migration Notes

- The `create_trees_for_existing_users` migration gives trees to accounts that already exist in production. It runs during the next `scripts/deploy.sh`, after the database backup the deploy takes before migrating (F-01).
- **Manual owner step before the deploy:** set `APP_LOCALE=pl` (and `APP_FAKER_LOCALE=pl_PL`) in `.env.prod` on the VPS. Otherwise production stays on the `en` value from its env file. The code default `'pl'` only applies when the variable is absent.
- The `lang/` files are committed; production installs `--no-dev` and does not need laravel-lang.

## References

- Roadmap: `context/foundation/roadmap.md` (S-01)
- PRD: `context/foundation/prd.md` (FR-001–FR-005, FR-008, Business Logic, Access Control, Non-Goals)
- Registration: `app/Actions/Fortify/CreateNewUser.php:20-32`
- Form pattern: `resources/js/pages/settings/profile.tsx:40-66`
- Controller + toast pattern: `app/Http/Controllers/Settings/ProfileController.php:41`
- Layout assignment: `resources/js/app.tsx:13-24`
- Previous change (deploy with a backup before migrating): `context/archive/2026-09-22-production-data-safety/plan.md`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Drzewo zakładane razem z kontem

#### Automated

- [ ] 1.1 Migrations apply cleanly on pgsql: `vendor/bin/sail artisan migrate:fresh --no-interaction`
- [ ] 1.2 Phase 1 tests pass: `vendor/bin/sail artisan test --compact tests/Feature/Auth/RegistrationTest.php tests/Feature/TreeBackfillTest.php tests/Unit/PartialDateTest.php`
- [ ] 1.3 Static analysis passes: `vendor/bin/sail composer types:check`
- [ ] 1.4 Formatting passes: `vendor/bin/sail bin pint --dirty --format agent`

#### Manual

- [ ] 1.5 On the local database with existing accounts, `vendor/bin/sail artisan migrate` creates exactly one tree for every user (check with a `trees` vs `users` query)

### Phase 2: Dodanie i podgląd osoby po polsku

#### Automated

- [ ] 2.1 Persons and auth tests pass: `vendor/bin/sail artisan test --compact tests/Feature/PersonsTest.php tests/Feature/Auth`
- [ ] 2.2 Static analysis passes: `vendor/bin/sail composer types:check`
- [ ] 2.3 Formatting passes: `vendor/bin/sail bin pint --dirty --format agent`
- [ ] 2.4 TypeScript passes: `vendor/bin/sail npm run types:check`
- [ ] 2.5 Frontend lint/format passes: `vendor/bin/sail npm run check`
- [ ] 2.6 Production build succeeds: `vendor/bin/sail npm run build`
- [ ] 2.7 Full suite passes: `vendor/bin/sail artisan test --compact`

#### Manual

- [ ] 2.8 A newly registered account lands on the empty "Osoby" list with a prompt to add the first person
- [ ] 2.9 Adding a person with a date "ok. 1850", a place and religion shows the preview with Polish formatting, and the person appears on the list in the right position
- [ ] 2.10 Checking "Zmarły/a" with no data shows a "Zgon" card with "brak danych"; leaving it unchecked shows no death card
- [ ] 2.11 Invalid dates (e.g. 31.02) show Polish error messages next to the right fields
- [ ] 2.12 Opening another account's person URL shows a 404

### Phase 3: Usunięcie resetu hasła

#### Automated

- [ ] 3.1 Auth tests pass: `vendor/bin/sail artisan test --compact tests/Feature/Auth`
- [ ] 3.2 No remaining references to the reset: `grep -rn "resetPasswords\|ResetUserPassword\|forgot-password\|canResetPassword\|'@/routes/password'" app config resources/js/pages resources/js/components` returns nothing (tests are excluded, because the new test deliberately checks `/forgot-password`; the quoted module name deliberately does not match `@/routes/password/confirm`, which stays)
- [ ] 3.3 Static analysis passes: `vendor/bin/sail composer types:check`
- [ ] 3.4 Formatting passes: `vendor/bin/sail bin pint --dirty --format agent`
- [ ] 3.5 TypeScript and build pass: `vendor/bin/sail npm run types:check && vendor/bin/sail npm run build`
- [ ] 3.6 Full suite passes: `vendor/bin/sail artisan test --compact`

#### Manual

- [ ] 3.7 The login page has no "forgot your password" link, and `/forgot-password` shows a 404
- [ ] 3.8 Changing the password in settings still works
