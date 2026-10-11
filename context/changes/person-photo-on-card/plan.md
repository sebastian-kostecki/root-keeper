# Zdjęcie osoby na karcie drzewa (S-16 `person-photo-on-card`) — plan implementacji

## Overview

Autor dodaje zdjęcie osoby w panelu bocznym (menu na awatarze) i widzi je na karcie drzewa oraz karcie widoku przodków jako pasek na całą wysokość przy lewej krawędzi karty. Imię, daty i wiek stoją obok, wyrównane do lewej. Karta bez zdjęcia pokazuje w tym miejscu pasek z inicjałami w kolorze płci (FR-015). Zdjęcie pokazuje się też w awatarach panelu, listy i tabeli osób. FR-015 jest pierwsze do odcięcia, gdy zabraknie czasu (`context/foundation/prd.md:92`).

## Current State Analysis

- Osoba nie ma zdjęcia: tabela `people` nie ma kolumn na plik, `Person` (`app/Models/Person.php:32`) ani zasoby go nie znają.
- Pliki źródeł mają sprawdzony wzorzec, który można powtórzyć:
  - prywatny dysk `local` (`storage/app/private`, `'serve' => false`, `config/filesystems.php:33-39`);
  - pliki `sources/{tree}/{uuid}.{ext}` i `sources/{tree}/thumbnails/{uuid}.jpg` (`app/Actions/Sources/StoreSource.php:33-55`);
  - sprzątanie plików, gdy zapis do bazy się nie uda (`StoreSource.php:81-85`);
  - serwowanie przez kontroler z `Gate::authorize('view')` i nagłówkami `Cache-Control: private, max-age=86400` oraz `nosniff` (`app/Http/Controllers/SourceController.php:466-504`).
- `GenerateSourceThumbnail::handle(string $absolutePath, string $mimeType): ?string` (`app/Actions/Sources/GenerateSourceThumbnail.php:25`) robi z GD JPEG ok. 400 px szerokości z poprawioną orientacją EXIF. Nie zna źródeł. Przy błędzie zwraca null.
- Pliki źródeł nigdy nie są podmieniane (`UpdateSource` ich nie rusza). Podmiana pliku to nowa logika.
- `DeletePerson` (`app/Actions/Persons/DeletePerson.php:19-33`) nie usuwa plików.
- Usunięcie konta czyści tylko `sources/{treeId}` (`app/Http/Controllers/Settings/ProfileController.php:60`).
- `RestoreBackup` zna tylko katalog `sources`:
  - liczy jego pliki przy sprawdzaniu, czy instancja jest zajęta (`app/Console/Commands/RestoreBackup.php:118`);
  - czyści go przed odtworzeniem (`:166`);
  - sprawdza tylko pliki z wierszy `sources` (`:381-396`).
  - Kopiuje natomiast wszystkie pliki z archiwum (`:358-375`), a kopia obejmuje całe `storage/app` (`config/backup.php:28-43`).
- Edycja osoby wysyła pełny JSON przez `router.put` (`resources/js/hooks/use-section-save.ts:36`). Notatka ma osobny endpoint (`PUT persons/{person}/note`, `app/Http/Controllers/PersonNoteController.php`). Pliku nie da się wysłać PUT-em bez obejścia, więc zdjęcie dostaje osobne endpointy POST/DELETE.
- Karty:
  - `CardBody` (`resources/js/components/tree/sheet-person-card.tsx:25-49`) jest wspólny dla `SheetPersonCard` i `AncestorCard` (`ancestor-card.tsx:41`). Renderuje wyśrodkowaną kolumnę wewnątrz `cardSurface` (`card-styles.ts:5`: `flex-col items-center … px-7 py-3.5 text-center`).
  - `UnknownPersonCard` używa tego samego `cardSurface`.
  - Kolumna siatki ma `COLUMN_REM = 10` w `family-sheet.tsx:21` i `ancestor-chart.tsx:10`. Cała geometria (linie, szerokość par i dzieci) liczy się z tych stałych w jednostkach kolumn.
- `PersonAvatar` (`resources/js/components/persons/person-avatar.tsx:26`) to kółko w kolorze płci z inicjałami, bez obrazka. Używają go nagłówek panelu (`person-modal-header.tsx:44`), lista (`persons-list.tsx:61`) i tabela osób (`persons-table.tsx:142`).
- Payload kart: `SheetPersonResource` (`app/Http/Resources/SheetPersonResource.php:30-49`) i `@phpstan-type SheetPerson` (`app/Actions/Trees/BuildFamilySheet.php:19`, importowany w `BuildAncestorChart.php:20`). Osoby ładują się jednym zapytaniem z `->with('events')`. Testy liczby zapytań: `tests/Feature/TreeTest.php:499` i `tests/Feature/AncestorsTest.php:222`.
- Potwierdzenie usunięcia osoby liczy skutki po stronie klienta z danych osoby (`resources/js/components/persons/delete-person-dialog.tsx:45-60`).

## Desired End State

- W nagłówku panelu osoby awatar jest przyciskiem „Zdjęcie osoby” z menu:
  - „Dodaj zdjęcie”, a gdy zdjęcie jest, „Zmień zdjęcie”;
  - „Usuń zdjęcie”, tylko gdy zdjęcie jest.
- Wybór pliku od razu go wysyła i kończy się toastem „Zapisano zdjęcie”. Usunięcie wymaga krótkiego potwierdzenia i kończy się toastem „Usunięto zdjęcie”.
- Karta osoby w drzewie i w widoku przodków to układ poziomy:
  - po lewej pasek na całą wysokość karty (ok. 4,5rem, proporcja ok. 3:4) ze zdjęciem kadrowanym do środka;
  - bez zdjęcia ten sam pasek ma tło w kolorze płci i inicjały;
  - obok imię, daty i wiek jeden pod drugim, wyrównane do lewej i do góry; daty i wiek mają co najmniej 6rem szerokości, czyli tyle co dziś.
- Kolumna siatki ma 12rem w obu widokach.
- Awatary w panelu, liście i tabeli osób pokazują zdjęcie, a bez niego inicjały jak dziś.
- Po podmianie zdjęcia każde miejsce od razu pokazuje nowe zdjęcie mimo jednodniowego cache przeglądarki.
- Pliki zdjęć znikają przy podmianie, usunięciu zdjęcia, usunięciu osoby i usunięciu konta.
- `RestoreBackup` traktuje zdjęcia osób jak pliki źródeł.
- Cudza osoba daje 404 na wszystkich trzech endpointach zdjęcia.

### Key Discoveries:

- `GenerateSourceThumbnail` można wywołać bez zmian (`app/Actions/Sources/GenerateSourceThumbnail.php:25`). Kadr środka robi CSS `object-cover object-center`, więc serwer nie potrzebuje kodu kadrującego.
- Zasoby nigdy nie wysyłają ścieżek ani URL-i plików. Front buduje URL przez Wayfinder (`SourceResource.php:84-88`, `source-thumbnail.tsx:27-37`). Zdjęcie idzie tą samą drogą, z polem wersji do pomijania cache.
- Kolumny na `people` nie dodają zapytań, a istniejące testy liczby zapytań to pilnują (`TreeTest.php:499`, `AncestorsTest.php:222`).
- Nazwa dostępna karty pochodzi z jej tekstu (płeć sr-only, imię, lata), a E2E na niej polegają. Zdjęcie ma `alt=""`, a inicjały `aria-hidden`, więc nazwa się nie zmienia.
- `RestoreBackup::copyStagedFiles` (`:358-375`) już przywraca każdy plik z archiwum, także `persons/…`. Do dopisania zostają tylko liczenie, czyszczenie i weryfikacja.

## What We're NOT Doing

- Wybór zdjęcia z istniejących źródeł. Zdjęcie to własny plik osoby, a nie źródło.
- Narzędzie kadrowania i wybór punktu kadru. Kadr jest zawsze ze środka; autor może przyciąć zdjęcie przed wgraniem.
- Podgląd przed zapisem i powiększenie zdjęcia (lightbox) w panelu.
- Trasa serwująca oryginał. Oryginał jest przechowywany (kopia, przyszłe użycie), ale serwujemy tylko miniaturę.
- Zdjęcia w wierszach zakładki Rodzina (`family-tab.tsx:84-93` rysuje inicjały inline, bez `PersonAvatar`), w wyborze osoby i na kartach-zaślepkach (`UnknownPersonCard`).
- Przenoszenie `GenerateSourceThumbnail` i `ReadExifOrientation` do wspólnej przestrzeni nazw.
- Kilka zdjęć osoby lub galeria.
- Nowe zależności (Intervention, Glide, biblioteki kadrowania).

## Implementation Approach

Najpierw backend z pełnym cyklem życia pliku i testami Pest, bo to on niesie ryzyka danych (#2 kopia, #3 usuwanie, #4 cudze dane). Potem front w dwóch krokach:
- panel i awatar: miejsce, gdzie zdjęcie się dodaje;
- nowy układ karty: zmiana wizualna, którą trzeba obejrzeć na 1366 px i 2560 px.

Na końcu jeden test E2E przez `/10x-e2e` chroni ścieżkę „dodane w panelu → widoczne na karcie”.

## Critical Implementation Details

- **Kolejność przy podmianie.** Zapisz nowe pliki, potem zaktualizuj wiersz, a dopiero potem usuń stare pliki (porażka usunięcia → `report()`, bez wyjątku). Jeśli zapis do bazy się nie uda, usuń nowe pliki (wzór `StoreSource.php:81-85`). Przy usuwaniu osoby zbierz ścieżki przed transakcją i usuń pliki po jej zatwierdzeniu, żeby wycofana transakcja nie zostawiła osoby bez pliku.
- **Wersja w URL.** Serwowanie ma `Cache-Control: private, max-age=86400`, więc URL musi się zmieniać przy podmianie. `photo_version` to nazwa pliku miniatury bez rozszerzenia (UUID z nowego uploadu), dopisywana jako `?v=`.
- **Strażnik niezapisanych zmian w panelu.** Panel ma strażnika (`person-panel-unsaved-guard.spec.ts`, `person-panel-page-navigation-guard.spec.ts`). Upload i usunięcie zdjęcia to wizyty Inertii z panelu. Strażnik strony (`section-editor.tsx:121-156`) przepuszcza każdą wizytę inną niż GET (`:134`). Dlatego bezpośredni `router.post` / `router.delete` z `preserveScroll` i `preserveState`, z serwerem odpowiadającym `back()` (Referer zachowuje `?person=`), nie wywoła pytania i nie zamknie panelu. Nie używać `useSectionSave` (to `router.put` z błędami przypisanymi do sekcji). Nie owijać akcji w `requestExit`, jak robią „Ustaw jako probanta” i „Usuń osobę…”: `requestExit` zamyka edytowaną sekcję, a upload ma ją zostawić otwartą (kryterium 2.11).

## Faza 1: Backend zdjęcia osoby

### Overview

Kolumny, upload z podmianą, usuwanie, serwowanie miniatury i `photo_version` w zasobach. Do tego sprzątanie plików przy usunięciu osoby i konta, obsługa w `RestoreBackup` i testy Pest.

### Changes Required:

#### 1. Migracja i model

**File**: `database/migrations/<timestamp>_add_photo_columns_to_people_table.php` (przez `vendor/bin/sail artisan make:migration`), `app/Models/Person.php`

**Intent**: Dodać do osoby ścieżki oryginału i miniatury zdjęcia. Migracja tylko dodaje kolumny i nie przepisuje danych, więc nie potrzebuje testu migracji.

**Contract**: `people.photo_path` (string, nullable), `people.photo_thumbnail_path` (string, nullable). `down()` usuwa obie kolumny; komentarz mówi, że pliki na dysku zostają. Obie kolumny trafiają do `#[Fillable]` i do `@property` w `Person`.

#### 2. Akcje zapisu i usunięcia zdjęcia

**File**: `app/Actions/Persons/StorePersonPhoto.php`, `app/Actions/Persons/DeletePersonPhoto.php`

**Intent**:
- `StorePersonPhoto` zapisuje oryginał i miniaturę na dysku `local`. Miniatura powstaje przez `GenerateSourceThumbnail`. Akcja ustawia kolumny osoby i usuwa poprzednie pliki (kolejność z Critical Implementation Details).
- Gdy miniatury nie da się zrobić, usuwa zapisany oryginał i rzuca błąd walidacji pola `photo`: „Nie udało się przetworzyć zdjęcia. Spróbuj innego pliku.” Zdjęcie bez miniatury nigdzie by się nie pokazało.
- `DeletePersonPhoto` zeruje kolumny, a potem usuwa pliki (porażka → `report()`).

**Contract**:
- `StorePersonPhoto::handle(Person $person, UploadedFile $photo): void`;
- `DeletePersonPhoto::handle(Person $person): void`;
- ścieżki `persons/{tree_id}/{uuid}.{ext}` (rozszerzenie z wykrytego MIME, jak `StoreSource`) i `persons/{tree_id}/thumbnails/{uuid}.jpg`.

#### 3. Form Request, kontroler i trasy

**File**: `app/Http/Requests/PersonPhotoStoreRequest.php`, `app/Http/Controllers/PersonPhotoController.php`, `routes/web.php`

**Intent**: Osobny zasób zdjęcia osoby na wzór `PersonNoteController`.
- `store` i `destroy` przekierowują `back()` z toastem.
- `show` strumieniuje miniaturę z tymi samymi nagłówkami co `SourceController` (`:497-504`). Daje 404, gdy osoba nie ma zdjęcia albo pliku brak na dysku.

**Contract**:
- `POST persons/{person}/photo` → `persons.photo.store`:
  - walidacja `photo`: `['required', File::image()->types(['jpg','jpeg','png','webp'])->max(20 * 1024)]`, jak w `SourceStoreRequest.php:25`;
  - autoryzacja: `Gate::inspect('update', $person)`, jak w `PersonUpdateRequest`;
  - toast „Zapisano zdjęcie”.
- `DELETE persons/{person}/photo` → `persons.photo.destroy`: `Gate::authorize('update')`, toast „Usunięto zdjęcie”.
- `GET persons/{person}/photo` → `persons.photo.show`: `Gate::authorize('view')`.
- Wszystkie trzy trasy w grupie `auth`, `verified`. Cudza osoba daje 404 przez `PersonPolicy`.
- Po dodaniu tras: `vendor/bin/sail artisan wayfinder:generate`.

#### 4. Wersja zdjęcia w zasobach

**File**: `app/Http/Resources/SheetPersonResource.php`, `app/Actions/Trees/BuildFamilySheet.php` (`@phpstan-type SheetPerson`), `app/Http/Resources/PersonResource.php`, `resources/js/types/tree.ts`, `resources/js/types/person.ts`, `resources/js/lib/centre-hints.test.ts`, `resources/js/lib/ancestor-layout.test.ts`, `resources/js/lib/persons-index.test.ts`

**Intent**: Karty, panel, lista i tabela dostają wersję zdjęcia zamiast ścieżki. Front buduje z niej URL.

**Contract**: Nowe pole `photo_version: string|null` (nazwa pliku miniatury bez rozszerzenia; null bez zdjęcia):
- w `SheetPersonResource`, w typie `SheetPerson` (PHPStan i TS `SheetPerson` w `types/tree.ts:3-10`);
- w `PersonResource` i typie TS `Person` (`types/person.ts:89`), z którego korzystają `PersonPreview`, lista i tabela;
- w docblockach `@return` obu zasobów (`SheetPersonResource.php:21-28`, `PersonResource.php`), bo Larastan level 7 porównuje je z kształtem tablicy;
- w literałach typów w testach Vitest, które `tsc` sprawdza (`tsconfig.json` obejmuje `*.test.ts`): `resources/js/lib/centre-hints.test.ts:5-14` i `resources/js/lib/ancestor-layout.test.ts:5-14` (`SheetPerson`) oraz `resources/js/lib/persons-index.test.ts:28-46` (`Person`); wartość `photo_version: null`.

#### 5. Sprzątanie plików przy usunięciu osoby i konta

**File**: `app/Actions/Persons/DeletePerson.php`, `app/Http/Controllers/Settings/ProfileController.php`

**Intent**: Pliki zdjęcia nie zostają po usuniętej osobie ani po usuniętym koncie.

**Contract**:
- `DeletePerson::handle` usuwa `photo_path` i `photo_thumbnail_path` z dysku `local` po zatwierdzeniu transakcji (porażka → `report()`). Trzeba zaktualizować docblock.
- `ProfileController::destroy` usuwa też katalog `persons/{$treeId}`, pod tym samym zabezpieczeniem przed null.

#### 6. Zdjęcia osób w odtwarzaniu z kopii

**File**: `app/Console/Commands/RestoreBackup.php`

**Intent**: Odtwarzanie traktuje zdjęcia osób jak pliki źródeł, zgodnie z ryzykiem #2 z `context/foundation/test-plan.md`.

**Contract**:
- Stała katalogu `persons`.
- Pliki zdjęć wliczają się do decyzji „instancja zajęta”. Komunikaty pokazują osobną linię „pliki zdjęć osób: N”.
- Katalog jest czyszczony razem z `sources` przed odtworzeniem na zajętej instancji.
- Weryfikacja po odtworzeniu sprawdza też niepuste `people.photo_path` i `people.photo_thumbnail_path`. Komunikat o brakach obejmuje oba rodzaje plików.

#### 7. Testy Pest

**File**: `tests/Feature/PersonPhotoTest.php` (nowy), `tests/Feature/OwnershipBoundariesTest.php`, `tests/Feature/PersonsTest.php`, `tests/Feature/Settings/ProfileUpdateTest.php`, `tests/Backup/RestoreCommandTest.php`, `tests/Backup/BackupContentsTest.php`

**Intent**: Pokryć ścieżkę główną i tryby awarii. Wszystkie testy z `Storage::fake('local')` i fixturą `tests/Fixtures/portrait-small.jpg`.

**Contract**: przypadki:
- **Upload:** zapisuje oryginał i miniaturę pod `persons/{tree}/`, ustawia kolumny i toast.
- **Podmiana:** usuwa stare pliki i zmienia `photo_version`.
- **Walidacja:** odrzuca pdf, gif, svg i plik ponad 20 MB.
- **Nieudana miniatura** (np. plik tekstowy z rozszerzeniem jpg albo zamockowany `GenerateSourceThumbnail` zwracający null): błąd walidacji `photo`, brak plików na dysku, kolumny bez zmian.
- **`destroy`:** zeruje kolumny i usuwa pliki.
- **`show`:** zwraca miniaturę z `Cache-Control: private, max-age=86400` i `nosniff`; 404 bez zdjęcia; gość trafia na logowanie.
- **Cudzy użytkownik:** 404 na `store`, `destroy` i `show`, bez zapisu i bez plików (`OwnershipBoundariesTest`).
- **Usunięcie osoby** usuwa jej pliki zdjęcia (`PersonsTest`).
- **Usunięcie konta** usuwa `persons/{treeId}` (`ProfileUpdateTest`).
- **`RestoreBackup`:**
  - liczy zdjęcia osób jako dane zajętej instancji;
  - zgłasza brak przywróconego pliku zdjęcia;
  - przywraca plik zdjęcia na czystej instancji.
- **Archiwum kopii** zawiera plik zdjęcia osoby (`BackupContentsTest`).
- **Zasoby:** `photo_version` w `SheetPerson` (drzewo i przodkowie) oraz w `PersonResource`; testy liczby zapytań (`TreeTest.php:499`, `AncestorsTest.php:222`) nadal zielone.

### Success Criteria:

#### Automated Verification:

- Migracja przechodzi w obie strony: `vendor/bin/sail artisan migrate` i `vendor/bin/sail artisan migrate:rollback --step=1` (potem znów `migrate`)
- Testy zdjęcia przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/PersonPhotoTest.php`
- Testy granic, osób, profilu i kopii przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/OwnershipBoundariesTest.php tests/Feature/PersonsTest.php tests/Feature/Settings/ProfileUpdateTest.php tests/Backup`
- Testy drzewa i przodków (w tym liczba zapytań) przechodzą: `vendor/bin/sail artisan test --compact tests/Feature/TreeTest.php tests/Feature/AncestorsTest.php`
- Larastan przechodzi: `vendor/bin/sail composer types:check`
- Pint bez zmian: `vendor/bin/sail bin pint --dirty --format agent`
- Typy TS po `wayfinder:generate` przechodzą: `vendor/bin/sail npm run types:check`

#### Manual Verification:

- `vendor/bin/sail artisan route:list --path=persons` pokazuje trzy trasy `persons.photo.*` w grupie `auth`, `verified`

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 2.

---

## Faza 2: Zdjęcie w panelu i awatarach

### Overview

`PersonAvatar` pokazuje zdjęcie, a w nagłówku panelu awatar otwiera menu dodawania, zmiany i usuwania zdjęcia. Potwierdzenie usunięcia osoby wymienia zdjęcie.

### Changes Required:

#### 1. URL zdjęcia

**File**: `resources/js/lib/person-photo.ts` (nowy)

**Intent**: Jedno miejsce, które z id osoby i wersji buduje URL miniatury przez Wayfinder.

**Contract**: `personPhotoUrl(personId: number, version: string | null): string | null` zwraca `PersonPhotoController.show.url(personId, { query: { v: version } })` albo null, gdy wersji brak.

#### 2. `PersonAvatar` ze zdjęciem

**File**: `resources/js/components/persons/person-avatar.tsx`

**Intent**: Awatar pokazuje zdjęcie wykadrowane do środka kółka. Bez zdjęcia albo gdy obrazek się nie wczyta (`onError`), pokazuje inicjały jak dziś. Odczyt płci (sr-only) bez zmian.

**Contract**: Nowy opcjonalny prop `photoUrl?: string | null`. Obrazek ma `alt=""` i `object-cover object-center`, wypełnia kółko. Stan błędu wczytania resetuje się przy zmianie `photoUrl` (np. `key={photoUrl}` na elemencie trzymającym ten stan). Inaczej po jednym błędzie nowe zdjęcie nie pokazałoby się do przeładowania, bo `preserveState` zachowuje stan komponentu. Podają go nagłówek panelu, `persons-list.tsx` i `persons-table.tsx` (z `personPhotoUrl`).

#### 3. Menu zdjęcia w nagłówku panelu

**File**: `resources/js/components/persons/person-modal-header.tsx` (oraz ewentualnie nowy `resources/js/components/persons/person-photo-menu.tsx`)

**Intent**:
- Awatar w wierszu tożsamości staje się przyciskiem „Zdjęcie osoby” z `DropdownMenu`.
- Pozycje: „Dodaj zdjęcie” / „Zmień zdjęcie” (otwiera ukryte pole pliku) oraz „Usuń zdjęcie” (tylko ze zdjęciem; otwiera modal potwierdzenia z `ui/dialog`).
- Wybór pliku od razu wysyła `router.post` na `persons.photo.store`. Podczas wysyłania awatar pokazuje stan ładowania i jest zablokowany.
- Błąd walidacji `photo` pokazuje się pod wierszem tożsamości.

**Contract**:
- pole pliku: `accept="image/jpeg,image/png,image/webp"`, nazwa `photo`;
- bezpośrednie `router.post` / `router.delete` z `preserveScroll` i `preserveState`, bez `useSectionSave` i bez `requestExit` (Critical Implementation Details);
- treść potwierdzenia: „Usunąć zdjęcie osoby?”, przyciski „Usuń” i „Anuluj”.

#### 4. Zdjęcie w skutkach usunięcia osoby

**File**: `resources/js/components/persons/delete-person-dialog.tsx`

**Intent**: Lista skutków odpowiada temu, co faktycznie zniknie (ryzyko #3).

**Contract**: Gdy `person.photo_version !== null`, lista skutków ma pozycję „zdjęcie osoby”.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Build przechodzi: `vendor/bin/sail npm run build`
- Istniejące E2E panelu przechodzą: `npx playwright test tests/e2e/person-side-panel.spec.ts tests/e2e/person-panel-unsaved-guard.spec.ts tests/e2e/person-panel-page-navigation-guard.spec.ts`

#### Manual Verification:

- Dodanie zdjęcia z menu awatara pokazuje toast i zdjęcie w panelu bez zamykania panelu
- „Zmień zdjęcie” od razu pokazuje nowe zdjęcie (nie stare z cache)
- „Usuń zdjęcie” po potwierdzeniu przywraca inicjały
- Plik PDF lub zbyt duży pokazuje błąd pod wierszem tożsamości
- Lista i tabela osób pokazują zdjęcie w awatarze
- Potwierdzenie usunięcia osoby ze zdjęciem wymienia „zdjęcie osoby”
- Upload przy otwartej edycji sekcji nie gubi wpisanych zmian i nie pokazuje pytania o niezapisane zmiany

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 3.

---

## Faza 3: Nowy układ karty

### Overview

Karta osoby w drzewie i w widoku przodków dostaje pasek zdjęcia lub inicjałów po lewej i dane wyrównane obok. Kolumna siatki rośnie do 12rem.

### Changes Required:

#### 1. Geometria karty osoby

**File**: `resources/js/components/tree/card-styles.ts`

**Intent**: Osobna geometria dla kart osób: układ poziomy, bez wewnętrznego paddingu, z przycięciem do zaokrąglonych rogów (pasek i gruba ramka osoby centralnej). Zaślepki (`UnknownPersonCard`) zostają przy obecnym `cardSurface`, z tą samą minimalną wysokością.

**Contract**: Nowa stała `personCardSurface` (`flex h-full min-h-24 flex-row items-stretch overflow-hidden rounded-xl border text-left`). `cardSurface` bez zmian.

#### 2. `CardBody` z paskiem zdjęcia

**File**: `resources/js/components/tree/sheet-person-card.tsx`

**Intent**: `CardBody` renderuje dwie części:
- pasek przy lewej krawędzi, na całą wysokość, ok. 4,5rem szerokości:
  - ze zdjęciem: `<img alt="" loading="lazy">` z `object-cover object-center`;
  - bez zdjęcia albo przy `onError`: tło w kolorze płci wyraźnie inne niż wypełnienie karty i inicjały z `getInitials` z `aria-hidden`; stan błędu resetuje się przy zmianie URL zdjęcia, jak w `PersonAvatar`;
- kolumnę danych: imię (z płcią sr-only jak dziś), daty i wiek jeden pod drugim, wyrównane do lewej i do góry (nie do środka w pionie).
- `SheetPersonCard` używa `personCardSurface`. Miejsce na przycisk podglądu (oko w `top-1 right-1`) zabiera tylko pierwsza linia imienia: przed imieniem stoi odstęp `float-right` w rozmiarze oka. Dalsze linie imienia, daty i wiek mają pełną szerokość kolumny.
- Rachunek przy kolumnie 12rem: 12rem − 4,5rem paska − odstępy kolumny danych ≥ 6rem tekstu (dziś 10rem − 2×1,75rem = 6,5rem). Stała rezerwa pod oko na całej wysokości dałaby ok. 4,75rem, a wtedy „1890 – 1965” w `text-sm` by się złamało.

**Contract**:
- `CardBody({ person, children?, reserveActionSpace? })`;
- `children` to dodatkowe linie w kolumnie danych (np. „Występuje kilka razy”);
- `reserveActionSpace` dodaje odstęp `float-right` pod oko przed pierwszą linią imienia (nie prawy padding całej kolumny);
- nazwa dostępna karty bez zmian.

#### 3. Karta przodka

**File**: `resources/js/components/tree/ancestor-card.tsx`

**Intent**: Karta przodka używa `personCardSurface` i nowego `CardBody`. „Występuje kilka razy” trafia do kolumny danych jako `children`.

**Contract**: Bez przycisku oka, więc bez `reserveActionSpace`.

#### 4. Szersza kolumna

**File**: `resources/js/components/tree/family-sheet.tsx`, `resources/js/components/tree/ancestor-chart.tsx`, `resources/js/lib/fit-view.ts` (tylko komentarz)

**Intent**: Imię i daty mieszczą się obok paska na kartach jednokolumnowych (dziadkowie, przodkowie, dzieci przy ponad 4).

**Contract**: `COLUMN_REM` z 10 na 12 w obu plikach, z aktualizacją komentarzy. Reszta geometrii wynika z tej stałej. Komentarz przy `ANCESTORS_FIT_FLOOR` w `resources/js/lib/fit-view.ts:8-11` liczy „10 rem per card”. Po zmianie: 32 karty to ok. 6500 px, więc wartość 0,1 nadal wystarcza (cała górna linia mieści się na ekranie do ok. 650 px). Poprawić tylko liczby w komentarzu.

### Success Criteria:

#### Automated Verification:

- Format i lint przechodzą: `vendor/bin/sail npm run check`
- Typy przechodzą: `vendor/bin/sail npm run types:check`
- Testy JS przechodzą: `vendor/bin/sail npm run test:js`
- E2E drzewa i przodków przechodzą bez zmiany lokatorów: `npx playwright test tests/e2e/tree-card-recentre.spec.ts tests/e2e/ancestors-view.spec.ts tests/e2e/tree-slot-parent.spec.ts tests/e2e/tree-slot-child.spec.ts tests/e2e/tree-slot-spouse.spec.ts tests/e2e/person-side-panel.spec.ts tests/e2e/marriage-side-panel.spec.ts`

#### Manual Verification:

- Na drzewie karty ze zdjęciem pokazują pasek zdjęcia, a bez zdjęcia pasek z inicjałami w kolorze płci; dane wyrównane do lewej obok paska
- Długie imię i nazwisko na karcie dziadka (12rem) łamie się czytelnie i nie wchodzi pod oko
- Osoba centralna ma grubą ramkę obejmującą także pasek, bez prześwitu w rogach
- Drzewo dopasowane do ekranu pozostaje czytelne na 1366 px i wypełnia przestrzeń na 2560 px
- Widok przodków przy 4 i 6 pokoleniach: karty spójne z drzewem, linie trafiają w środek kart
- Zaślepki (puste sloty, nieznani przodkowie) mają tę samą wysokość co karty osób

**Implementation Note**: Po zielonej weryfikacji automatycznej zatrzymaj się na ręczne potwierdzenie przed fazą 4.

---

## Faza 4: E2E zdjęcia na karcie (`/10x-e2e`)

### Overview

Jeden test przeglądarkowy chroni ryzyko „zdjęcie dodane w panelu pokazuje się na karcie drzewa, a po usunięciu karta wraca do inicjałów”. Pest tego nie widzi, bo łączy upload, wersję w URL, serwowanie z autoryzacją i render karty.

### Changes Required:

#### 1. Spec

**File**: `tests/e2e/person-photo-on-card.spec.ts`

**Intent**: Pętla `/10x-e2e`: eksploracja przez `playwright-cli` → generowanie → przegląd względem antywzorców → weryfikacja przez celowe wprowadzenie błędu.
- Test tworzy własną osobę z unikalnym sufiksem, otwiera jej panel i wgrywa `tests/Fixtures/portrait-small.jpg` z menu „Zdjęcie osoby”.
- Sprawdza, że na karcie tej osoby w drzewie zdjęcie jest widoczne i wczytane. Potem usuwa zdjęcie i sprawdza inicjały zamiast zdjęcia.
- Sprząta osobę.

**Contract**:
- menu i pozycje po rolach (`button` „Zdjęcie osoby”, `menuitem`);
- karta po nazwie dostępnej;
- `getByTestId` dla obrazka na karcie tylko wtedy, gdy `alt=""` uniemożliwia lokator po roli (zgodnie z regułą lokatorów z `CLAUDE.md`);
- bez `waitForTimeout`; czekanie na odpowiedź uploadu i widoczność.

### Success Criteria:

#### Automated Verification:

- Spec przechodzi: `npx playwright test tests/e2e/person-photo-on-card.spec.ts`
- Spec przechodzi dwa razy z rzędu (niezależność i sprzątanie): `npx playwright test tests/e2e/person-photo-on-card.spec.ts --repeat-each=2`
- Format i lint przechodzą: `vendor/bin/sail npm run check`

#### Manual Verification:

- Celowe zepsucie (np. `CardBody` ignoruje `photo_version`) czerwieni test, a po cofnięciu znów jest zielony

---

## Testing Strategy

### Unit Tests:

- Brak nowych testów jednostkowych. `GenerateSourceThumbnail` ma już testy (`tests/Feature/GenerateSourceThumbnailTest.php`), a `getInitials` ma `use-initials.test.ts`.

### Integration Tests:

- Pest feature (faza 1): upload, podmiana, walidacja, nieudana miniatura, usunięcie, serwowanie, 404 dla cudzego użytkownika, sprzątanie przy usunięciu osoby i konta, `RestoreBackup`, zawartość archiwum, `photo_version` w zasobach, liczba zapytań.
- E2E (faza 4): panel → karta drzewa → usunięcie.

### Manual Testing Steps:

1. Dodaj zdjęcie pionowe, poziome i PNG z przezroczystością; sprawdź kadr środka na karcie i w awatarze.
2. Podmień zdjęcie i bez przeładowania sprawdź kartę, panel i listę osób.
3. Usuń osobę ze zdjęciem i sprawdź, że `storage/app/private/persons/{tree}` nie ma jej plików.
4. Obejrzyj drzewo na 1366 px i 2560 px oraz widok przodków przy 6 pokoleniach.

## Performance Considerations

- Widok przodków przy 6 pokoleniach to do 63 miniatur (ok. 400 px, JPEG q80, kilkadziesiąt KB każda). Pomagają `loading="lazy"` i `Cache-Control: private, max-age=86400`. Wersja w URL unieważnia cache tylko dla podmienionego zdjęcia.
- Kolumny na `people` nie dodają zapytań (pilnują `TreeTest.php:499` i `AncestorsTest.php:222`).
- Miniatura powstaje synchronicznie w żądaniu, jak przy źródłach. Strażnik 25 MP w `GenerateSourceThumbnail` chroni pamięć.

## Migration Notes

Migracja tylko dodaje dwie kolumny nullable. Istniejące osoby nie mają zdjęcia, więc nie ma danych do przeniesienia. Produkcyjny `scripts/deploy.sh` robi `backup:run --only-db` przed `migrate`, bez zmian. Nowe pliki są w `storage/app/private/persons/`, które kopia już obejmuje.

## References

- Roadmapa: `context/foundation/roadmap.md:317-328` (S-16)
- PRD: `context/foundation/prd.md:141-143` (FR-015), `:210-215` (kopie, termin)
- Wzorzec przechowywania: `context/archive/2026-09-24-scan-upload-and-preview/plan.md:68-75`, `app/Actions/Sources/StoreSource.php`
- Serwowanie plików: `app/Http/Controllers/SourceController.php:466-504`
- Osobny endpoint osoby: `app/Http/Controllers/PersonNoteController.php`
- Wspólne ciało karty: `context/archive/2026-10-10-ancestors-view/plan.md:233-239`, `resources/js/components/tree/sheet-person-card.tsx:25`
- Ryzyka testów: `context/foundation/test-plan.md` (#2, #3, #4)

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Backend zdjęcia osoby

#### Automated

- [x] 1.1 Migracja przechodzi w obie strony — 223c2ae
- [x] 1.2 Testy zdjęcia przechodzą (PersonPhotoTest) — 223c2ae
- [x] 1.3 Testy granic, osób, profilu i kopii przechodzą — 223c2ae
- [x] 1.4 Testy drzewa i przodków (w tym liczba zapytań) przechodzą — 223c2ae
- [x] 1.5 Larastan przechodzi — 223c2ae
- [x] 1.6 Pint bez zmian — 223c2ae
- [x] 1.7 Typy TS po wayfinder:generate przechodzą — 223c2ae

#### Manual

- [x] 1.8 route:list pokazuje trzy trasy persons.photo.* w grupie auth, verified — 223c2ae

### Phase 2: Zdjęcie w panelu i awatarach

#### Automated

- [x] 2.1 Format i lint przechodzą — bd08e00
- [x] 2.2 Typy przechodzą — bd08e00
- [x] 2.3 Build przechodzi — bd08e00
- [x] 2.4 Istniejące E2E panelu przechodzą — bd08e00

#### Manual

- [x] 2.5 Dodanie zdjęcia z menu awatara pokazuje toast i zdjęcie w panelu bez zamykania panelu — bd08e00
- [x] 2.6 „Zmień zdjęcie” od razu pokazuje nowe zdjęcie — bd08e00
- [x] 2.7 „Usuń zdjęcie” po potwierdzeniu przywraca inicjały — bd08e00
- [x] 2.8 Plik PDF lub zbyt duży pokazuje błąd pod wierszem tożsamości — bd08e00
- [x] 2.9 Lista i tabela osób pokazują zdjęcie w awatarze — bd08e00
- [x] 2.10 Potwierdzenie usunięcia osoby ze zdjęciem wymienia „zdjęcie osoby” — bd08e00
- [x] 2.11 Upload przy otwartej edycji sekcji nie gubi zmian i nie pokazuje pytania o niezapisane zmiany — bd08e00

### Phase 3: Nowy układ karty

#### Automated

- [x] 3.1 Format i lint przechodzą — fb35d0b
- [x] 3.2 Typy przechodzą — fb35d0b
- [x] 3.3 Testy JS przechodzą — fb35d0b
- [x] 3.4 E2E drzewa i przodków przechodzą bez zmiany lokatorów — fb35d0b

#### Manual

- [x] 3.5 Karty pokazują pasek zdjęcia albo inicjałów, dane wyrównane do lewej obok paska — fb35d0b
- [x] 3.6 Długie imię na karcie dziadka łamie się czytelnie i nie wchodzi pod oko — fb35d0b
- [x] 3.7 Gruba ramka osoby centralnej obejmuje pasek bez prześwitu w rogach — fb35d0b
- [x] 3.8 Drzewo czytelne na 1366 px i wypełnia przestrzeń na 2560 px — fb35d0b
- [x] 3.9 Widok przodków przy 4 i 6 pokoleniach spójny z drzewem, linie trafiają w środek kart — fb35d0b
- [x] 3.10 Zaślepki mają tę samą wysokość co karty osób — fb35d0b

### Phase 4: E2E zdjęcia na karcie

#### Automated

- [x] 4.1 Spec przechodzi
- [x] 4.2 Spec przechodzi dwa razy z rzędu
- [x] 4.3 Format i lint przechodzą

#### Manual

- [ ] 4.4 Celowe zepsucie czerwieni test, a po cofnięciu znów jest zielony
