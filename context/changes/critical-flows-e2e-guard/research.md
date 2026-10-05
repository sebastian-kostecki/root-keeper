---
date: 2026-10-04T23:26:36+02:00
researcher: Claude (Opus 5.5) dla Sebastian Kostecki
git_commit: 9bd91b85c4fa7555c9b7de9d5af112925b804b17
branch: develop
repository: root-keeper
topic: "F-01 critical-flows-e2e-guard — co i jak chronić testami E2E przed przebudową drzewa i paneli"
tags: [research, e2e, playwright, tree, sources, side-panel, test-plan-phase-4]
status: complete
last_updated: 2026-10-04
last_updated_by: Claude (Opus 5.5)
last_updated_note: "Rozstrzygnięte Open Questions 1–6 decyzjami użytkownika (2026-10-04)"
---

# Research: F-01 critical-flows-e2e-guard

**Date**: 2026-10-04T23:26:36+02:00
**Researcher**: Claude (Opus 5.5) dla Sebastian Kostecki
**Git Commit**: 9bd91b85c4fa7555c9b7de9d5af112925b804b17
**Branch**: develop
**Repository**: root-keeper

## Research Question

Pytanie wyprowadzone z `context/foundation/roadmap.md` (F-01, faza 4 z `context/foundation/test-plan.md:54`). Brzmi ono: jak dziś w przeglądarce działają dwie ścieżki z guardrails PRD (`context/foundation/prd.md:68-70`, `:202`, `:209`)?

- **(A)** dodanie rodzica, dziecka i małżonka, istniejącego lub nowego, z pustych slotów drzewa (FR-026);
- **(B)** fakt potwierdzony źródłem prowadzi do tego samego źródła i skanu (FR-025).

Dla obu ścieżek sprawdzamy też:

- które lokatory i asercje na danych przetrwają przebudowy S-04, S-05 i S-06 oraz przejście z modala na panel F-02;
- jak w obecnej infrastrukturze Playwright przygotować i posprzątać dane;
- co trzeba rozstrzygnąć przed `/10x-plan`.

## Summary

**Uzasadnienie zmieniło się.** Werdykt fazy 2 mówi, że e2e jest potrzebne dopiero wtedy, gdy pojawi się stan id po stronie klienta albo potwierdzony błąd okna `choose()` (`test-plan.md:200`). Nowsze PRD i roadmapa wymagają jednak e2e jako *ochrony przed przebudową* paneli i drzewa (`prd.md:209`, `roadmap.md:92`, `roadmap.md:102`). Badanie potwierdza, że na sprawdzonych ścieżkach slotów żaden `useState` nie trzyma id osób. Każde id pochodzi z propsa `sheet` w chwili renderu lub kliknięcia (`add-child-dialog.tsx:70-88`). Pest pokrywa więc kontrakt „prop → żądanie → baza”. E2E dokłada tylko to, czego Pest nie widzi: czy przebudowany UI nadal *pozwala* przejść ścieżkę i trafia w tę samą osobę lub to samo źródło. Wiersz §6.6 i status fazy 4 w `test-plan.md` trzeba zaktualizować w planie, a nie pominąć.

**Ścieżka A ma 6 wariantów żądania:** rodzic, dziecko i małżonek, każdy jako istniejąca lub nowa osoba. Rozkładają się one na 3 dialogi oparte na wspólnym `pick-or-create-person-dialog.tsx`. Sloty to przyciski o stabilnych nazwach dostępności: `Dodaj ojca: {imię}`, `Dodaj matkę: …`, `Dodaj rodzica: …`, `Dodaj dziecko: {rodzice}` i `Dodaj małżonka: {osoba}`. Pusty slot małżonka pojawia się tylko wtedy, gdy osoba w centrum nie ma żadnego związku (`BuildFamilySheet.php:369-373`). W przeciwnym razie małżonka dodaje się z menu węzła związku.

**Ścieżka B** przebiega tak: dialog osoby, potem link `Otwórz źródło {nazwa}`, potem strona `/sources/{id}` z `<img src="/sources/{id}/file" alt={nazwa}>`. Najmocniejsza wyrocznia „ten sam skan” to sha256 odpowiedzi `GET /sources/{id}/file` równy sha256 wgranej fikstury, bo oryginał jest zapisywany i serwowany bez zmian (`StoreSource.php:35`, `SourceController.php:168-180`).

**Lokatory przetrwają przejście z modala na panel, jeśli spełnione są dwa warunki.** Dialog osoby trzeba lokalizować jako `getByRole('dialog', { name: display_name })`, a wejście do niego otwierać przez URL `?person={id}`. Powłoka F-02 celowo ma `role="dialog"` z nazwą z tytułu (`side-panel.tsx:153-158`, `side-panel-shell/plan.md:58`). Zmienią się natomiast: przycisk zamknięcia, „Podgląd”, „Wstecz”, kliknięcie karty i geometria drzewa.

**Infrastruktura działa, ale dane współdzielą jeden użytkownik i jedno drzewo.** Wszystkie specyfikacje działają na tym jednym drzewie i w trybie `fullyParallel`. Dane nie są czyszczone między uruchomieniami. Brak joba E2E w CI.

## Detailed Findings

### 1. Zakres i uzasadnienie fazy 4

- Faza 4 w `test-plan.md:54` ma status `not started` i warunek: „tylko jeśli badanie fazy 2 pokaże, że tańsze warstwy nie łapią pomyłki osoby w UI”. Werdykt fazy 2 brzmiał: tańsza warstwa łapie tę pomyłkę (`test-plan.md:200`; źródło: `context/archive/2026-10-02-testing-write-delete-targets/research.md:61`, `:124`).
- PRD w wersji z 2026-10-04 wymaga e2e niezależnie od tego werdyktu: „ścieżki krytyczne z guardrails (fakt–źródło, relacje ze slotów drzewa) mają być chronione testami E2E przed przebudową paneli i drzewa” (`prd.md:209`). Roadmapa każe F-01 wyprzedzić S-04 – S-06 (`roadmap.md:102`). Ta sama zasada 1 z `test-plan.md:17` („nie awansuj do e2e, bo wydaje się bezpieczniejsze”) jest tu spełniona inaczej. Ryzykiem nie jest błędne id w żądaniu, które pokrywa Pest. Ryzykiem jest ścieżka UI, która po przebudowie przestaje istnieć albo zaczyna trafiać gdzie indziej. Tego Pest z definicji nie widzi.
- W zakresie są tylko te wymagania FR, które PRD oznacza jako zachowane guardraile i które roadmapa wymienia w F-01, czyli FR-025 i FR-026 (`roadmap.md:92`). FR-027 (usunięcie źródła z listą skutków) i FR-029 (404) są w `prd.md:202` na liście „zachowane”, ale outcome F-01 ich nie wymienia. Pokrywają je Pest (`tests/Feature/DeleteImpactTest.php`, `tests/Feature/OwnershipBoundariesTest.php`).

### 2. Ścieżka A — relacje z pustych slotów drzewa

**Rodzaje slotów** (`app/Actions/Trees/BuildFamilySheet.php`):

- Rodzic: `ojciec`, `matka` albo `rodzic` (gdy znany rodzic ma płeć nieznaną). Sloty występują na poziomie „Rodzice” i „Dziadkowie” (`:390-438`). Dziadków można dodać tylko nad znanym rodzicem. Nad nieznanym rodzicem slot jest `empty` i niewidoczny (`:427-438`; `family-sheet.tsx:40-71`).
- Małżonek: pusty slot partnera, gdy osoba w centrum nie ma związku (`:369-373`). Gdy związki istnieją, w menu węzła związku jest pozycja `Dodaj małżonka` (`union-node.tsx:174-182`).
- Dziecko: slot jest zawsze renderowany (`family-sheet.tsx:130-198`, `:371-381`). Celem jest `sheet.add_child.parents`, czyli osoba w centrum i partner wybranego związku (`BuildFamilySheet.php:229-290`).

**Żądania i źródło id** (każde id pochodzi z propsa `sheet` z serwera):

| Wariant | Żądanie | Id z |
|---|---|---|
| istniejący rodzic | `POST /parent-child-relations` `{parent_id, child_id, role}` (`parent-slot-dialog.tsx:84-91`) | `slot.parent_of.id`, kandydat z `personCandidates` |
| nowy rodzic | `POST /persons/{id}/parents` + pola osoby + `role` (`parent-slot-dialog.tsx:93-99`) | `slot.parent_of.id` |
| istniejące dziecko | `POST /persons/{p0}/children/attach` `{child_id, other_parent_id}` (`add-child-dialog.tsx:70-76`) | `sheet.add_child.parents` |
| nowe dziecko | `POST /persons/{p0}/children` + ukryte `other_parent_id` (`add-child-dialog.tsx:77-88`) | `sheet.add_child.parents` |
| istniejący małżonek | `POST /persons/{focus}/spouses/attach` `{spouse_id}` (`add-spouse-dialog.tsx:85-88`) | `sheet.focus.id` |
| nowy małżonek | `POST /persons/{focus}/spouses` + pola osoby (`add-spouse-dialog.tsx:89-100`) | `sheet.focus.id` |

Po zapisie rodzica i dziecka następuje `back()`, więc URL się nie zmienia. Po zapisie małżonka następuje przekierowanie na `/tree/{focus}?spouse={nowyId}` (`PersonSpouseController.php:83`, `:96`).

**Nazwy dostępności** (dosłownie):

- Slot: przycisk z `aria-label` `Dodaj ojca: {display_name}` / `Dodaj matkę: …` / `Dodaj rodzica: …` (`parent-slot-dialog.tsx:21-31`, `unknown-person-card.tsx:31-45`); `Dodaj dziecko: {imiona połączone „ i ”}` (`add-child-dialog.tsx:31-41`); `Dodaj małżonka: {osoba}` (`add-spouse-dialog.tsx:46`).
- Tytuły dialogów (myślnik długi): `Dodaj ojca — {name}`, `Dodaj dziecko — {names}`, `Dodaj małżonka — {focus}` (`parent-slot-dialog.tsx:60`, `add-child-dialog.tsx:43`, `add-spouse-dialog.tsx:63`).
- Lista kandydatów: `searchbox` `Szukaj osoby` (`person-candidate-list.tsx:96-102`). Opcje to zwykłe `<button>` z nazwą, po której od razu następuje data urodzenia, więc trzeba je dopasowywać regexem lub `hasText`, a nie `exact` (`:108-131`). Przyciski w stopce dialogu: `Zamknij`, `Nowa osoba`.
- Formularz nowej osoby: etykiety `Imię`, `Nazwisko`, grupa radiowa `Płeć` z opcjami `Mężczyzna` / `Kobieta` / `Nieznana`, przyciski `Wróć do listy` i `Zapisz` (`pick-or-create-person-dialog.tsx:226-241`).
- Toasty (`use-flash-toast.ts`): `Dodano rodzica` (nowy rodzic), `Dodano relację` (istniejący rodzic, `ParentChildRelationController.php:152`), `Dodano dziecko`, `Dodano małżonka`.
- Menu związku: przycisk `Związek {i} z {n}: {status|partner}. Zmień związek lub dodaj małżonka`, pozycje `menuitemradio` oraz `menuitem` `Dodaj małżonka` (`union-node.tsx:99-111`, `:171-181`).

**Weryfikacja danych z UI** (bez dostępu do bazy):

- Po zapisie strona montuje się od nowa ze świeżym `sheet`. Karta nowej osoby ma przycisk `Podgląd: {display_name}` (`sheet-person-card.tsx:73-79`). Slot `Dodaj ojca: X` znika, a pojawiają się sloty dziadków nad nowym rodzicem.
- Twardszy dowód relacji daje podgląd `?person={id}`. Dialog nazywa się `display_name` (`person-modal-header.tsx:53-55`). Ma zakładkę `Rodzina` z licznikiem (dopasowanie `/^Rodzina/`) i sekcje `<h3>` `Rodzice` / `Małżeństwa` / `Dzieci` (`family-tab.tsx:258-300`). Wyszukiwanie trzeba zawęzić do dialogu, bo na stronie drzewa istnieją niewidoczne dla oka (sr-only) nagłówki `Rodzice` i `Dzieci` (`family-sheet.tsx:73-75`).
- Asercja relacji z obu stron, np. dziecko widzi rodzica i rodzic widzi dziecko, jest asercją na danych, a nie na wyglądzie. Spełnia to wymóg `roadmap.md:92`.

**Stan po stronie klienta i okno `choose()`:**

- Na sprawdzonych ścieżkach (3 dialogi slotów, `union-node.tsx`, `pick-or-create-person-dialog.tsx:75-81`) żaden `useState` nie trzyma id osoby.
- `choose()` (`union-node.tsx:79-97`) robi `router.visit(... ?spouse=...)`. Do końca tej wizyty renderowany jest stary `sheet`, więc `Dodaj dziecko` celuje w poprzednią parę. Okno nie jest potwierdzone uruchomieniem (`testing-write-delete-targets/research.md:61`). Test dziecka w drugim związku musi czekać na zmianę nazwy węzła związku albo karty partnera, a nie tylko na URL. Jednocześnie jest to jedyny sprawdzony kandydat na błąd, który widzi wyłącznie przeglądarka.

### 3. Ścieżka B — fakt → źródło → skan

- **Wejście:** podgląd osoby przez `?person={id}` (`HandleInertiaRequests.php:54-58`, `person-preview-provider.tsx:126-136`). Zakładki `Przegląd`, `Rodzina` i `Źródła` (`person-modal.tsx:58-68`).
- **Przegląd:** każdy fakt to `<section aria-label>` („Urodzenie”, „Małżeństwo z {imię}”, „Zgon”), czyli region z nazwą (`section-card.tsx:99-100`, `overview-tab.tsx:285`, `:328`, `:361`). Plakietka statusu pokazuje `Potwierdzone: {rodzaje}` albo `Bez źródła` (`event-source-status.tsx:17`, `:25`). Link źródła ma nazwę `Otwórz źródło {display_name}` (`overview-tab.tsx:105-134`). Karta urodzenia pojawia się tylko wtedy, gdy urodzenie ma datę lub miejsce (`overview-tab.tsx:181-183`).
- **Zakładka Źródła:** sekcje bez nazwy dostępności, nagłówek `<h3>` (`sources-tab.tsx:45-48`). W wierszu są: miniatura `<img alt={nazwa} src=/sources/{id}/thumbnail>`, link `Otwórz źródło {nazwa}` i przycisk `Odepnij źródło {nazwa}` (`sources-tab.tsx:68-130`). Przycisk otwierający picker ma tekst `Powiąż źródło` albo `Powiąż kolejne źródło` (`:143-153`).
- **Picker:** dialog `Powiąż źródło` (`source-picker-dialog.tsx:134`). Nie ma pola wyszukiwania. Opcje to `<button>` z połączoną treścią (`:93-120`). Gdy są dopasowania, widać grupy-regiony `Pasujące akty` i `Pozostałe źródła` (`:178-199`). Lista zawiera wszystkie źródła drzewa, od najnowszych (`HandleInertiaRequests.php:59-61`). Żądanie: `POST /events/{eventId}/sources {source_id}` (`:76-85`). Toast: `Powiązano źródło` (`EventSourceController.php:26`).
- **Strona źródła** `/sources/{id}`: nagłówek h2 = `display_name` (`show.tsx:52-55`), skan `<img src="/sources/{id}/file" alt={display_name}>` (`source-viewer.tsx:51-55`), karta `Potwierdza` z linkami `{Urodzenie|…} — {osoba}` (`show.tsx:110-130`).
- **Wyrocznia „to samo źródło i ten sam skan”**, od najsłabszej do najmocniejszej:
  1. `href` linku i URL po kliknięciu to `/sources/{id}` z id zapamiętanym przy tworzeniu źródła. Tworzenie przekierowuje na `/sources/{newId}` (`SourceController.php:102-104`), więc id da się odczytać z URL.
  2. `img[src]` = `/sources/{id}/file`.
  3. sha256 treści `page.request.get('/sources/{id}/file')` = sha256 fikstury. Kopiowanie jest bajt w bajt (`putFileAs`, `StoreSource.php:35`; strumień `SourceController.php:168-180`). Miniatura jest kodowana od nowa (`GenerateSourceThumbnail.php:55-69`), więc nie nadaje się do porównania z fiksturą.
  4. „Potwierdza” na stronie źródła zawiera `{fakt} — {osoba}`. To asercja w drugą stronę.
- **Tworzenie źródła w UI:** `/sources` → `Dodaj źródło` → dialog `Dodaj źródło` z polami: `Plik` (`<input type=file>`, `setInputFiles` działa, typy jpeg/png/webp), grupa radiowa `Rodzaj` (`akt metrykalny` / `zdjęcie nagrobka` / `inne`), `Nazwa`, combobox `Typ aktu`, przycisk `Zapisz` (`source-create-dialog.tsx:52-135`, `source-kind-field.tsx:25-49`). Fikstury w repozytorium: `tests/Fixtures/portrait-small.jpg` (300×400) i `tests/Fixtures/rotated-exif.jpg`.
- **Sprzątanie źródła:** `/sources/{id}` → `Usuń` → dialog → `Usuń`. Na stronie są dwa przyciski `Usuń`, więc trzeba zawęzić wyszukiwanie do dialogu. Żądanie `DELETE /sources/{id}` kasuje wiersze `event_source` i oba pliki (`DeleteSource.php:19-25`).

### 4. Odporność na S-02, S-04, S-05 i S-06

- **Powłoka F-02:** `<section role="dialog" aria-modal="false" aria-labelledby={h2}>` (`side-panel.tsx:153-158`), przycisk zamknięcia `Zamknij panel` (`:187-195`). Stan panelu jest w URL, jeden klucz na typ, a `?person=` zostaje bez zmian (`side-panel-url.ts:6-9`, `:32-50`; `side-panel-shell/plan.md:37`, `:46`). Jedyny konsument to dziś strona demo `/dev/side-panel` (`pages/dev/side-panel.tsx`). Modal osoby i modal edycji źródła pozostają modalami do S-06 i S-02 (`side-panel-shell/plan.md:36`).
- **Przetrwa przejście modal → panel:** `getByRole('dialog', { name: display_name })`, pod warunkiem że S-06 poda `title={display_name}` (intencja z `side-panel-shell/plan.md:58`, jeszcze niezrealizowana). Przetrwają też zakładki, pola z etykietami i przyciski wyszukiwane po nazwie wewnątrz tego dialogu, dialogi pickerów po nazwie z poziomu `page` (portal do `<body>`) oraz wejście przez URL `?person=`.
- **Zepsuje się:**
  - zamknięcie: Radix ma `Close` (`ui/dialog.tsx:66`), panel ma `Zamknij panel`;
  - `Podgląd` i `Podgląd: X` (S-06 je usuwa, `roadmap.md:190`);
  - `Wstecz` (FR-028);
  - kliknięcie karty jako przecentrowanie (S-06 zmienia je na otwarcie panelu);
  - asercje na `aria-modal`, opisie lub zasłonięciu drzewa.
- **Pułapka modalności:** modal Radix ukrywa resztę strony przed drzewem dostępności. Panel tego nie robi, więc nagłówek `<h1>` z imieniem osoby w centrum (`tree/show.tsx:82`) i niewidoczne nagłówki drzewa staną się widoczne obok panelu. Lokator bez zawężenia, np. `getByRole('heading', { name })`, da wtedy błąd trybu strict. Dlatego zawsze trzeba zawężać do dialogu osoby. Ten wniosek opiera się na wiedzy o Radix i nie był obserwowany na żywo.
- **S-05** (`roadmap.md:175-186`): przebudowa siatki z zoomem i przesuwaniem (react-zoom-pan-pinch). Sloty mają działać „jak dziś”, ale mogą się zmienić ich położenie i wejście do związku. Wniosek: treść poza widocznym obszarem transformacji może wymagać kroku „Dopasuj” przed kliknięciem.
- **S-04** (`roadmap.md:162-173`): picker dostaje filtry miejsca i roku, a numer wpisu przechodzi na powiązanie. Asercje nie powinny dotyczyć treści kafelka pickera ani miejsca wyświetlania „nr”.
- **S-02:** źródło przechodzi do szerokiego panelu. Nie rozstrzygnięto, czy `/sources/{id}` zostanie stroną, czy stanie się kluczem `?source=` (`side-panel-url.ts:2-4`). Asercja na id w `img[src]` i na sha256 przetrwa oba warianty. Asercja na kształt URL strony — nie.
- **Zalecenie strukturalne:** otwieranie i zamykanie podglądu osoby, przejście do źródła i wejście w drzewo (`/tree/{id}`) trzymać w jednym module pomocniczym. Wtedy S-05, S-06 i S-02 zmieniają jeden plik, a nie asercje (`roadmap.md:101`).

### 5. Dane testowe, izolacja i sprzątanie

- **Jeden użytkownik, jedno drzewo:** `trees.user_id` jest unikalne (`2026_09_23_045720_create_trees_table.php:16`). `scripts/e2e-server.sh:18-22` tworzy jednego użytkownika `E2E_USERNAME` z drzewem. W bazie `e2e` jest dziś 1 drzewo (id 1, `e2e@example.test`) oraz 0 osób i 0 źródeł (zapytanie 2026-10-04).
- **Konsekwencje `fullyParallel: true`** (`playwright.config.ts:14`):
  - `/tree` zależy od `root_person_id`, który zmienia się przy ustawieniu probanta albo usunięciu osoby-probanta. Specyfikacje powinny używać `/tree/{własnyId}` i nie ustawiać probanta (`TreeController.php:20-50`).
  - `personCandidates` zwraca wszystkie osoby drzewa (`ListPersonCandidates.php:17-33`). Picker źródeł zwraca wszystkie źródła drzewa. Unikalny sufiks w imionach i nazwach źródeł oraz filtr `Szukaj osoby` są więc konieczne.
- **Trwałość danych:** `e2e-server.sh:16-18` tworzy bazę tylko wtedy, gdy jej brak, i uruchamia `migrate`, a nie `migrate:fresh`. Pozostałości po czerwonych przebiegach kumulują się. Sprzątanie musi więc działać także wtedy, gdy test padnie (`afterEach`/`finally`, asercja `toBeOK`), zgodnie z `.claude/skills/10x-e2e/references/e2e-anti-patterns.md:122-166`.
- **Pliki:** kontener E2E montuje repozytorium (`compose.yaml:20-21`), a pliki trafiają do `storage/app/private/sources/{tree_id}` (`StoreSource.php:33-35`). Drzewo E2E ma id 1, a baza deweloperska `laravel` też ma drzewo 1, więc katalog `sources/1` jest współdzielony. Nazwy plików to UUID, a `DeleteSource` usuwa tylko własne pliki, więc kolizji nazw nie ma. Pliki nieposprzątane po E2E zostają jednak w katalogu deweloperskiego drzewa 1 jako sieroty (dziś deweloperskie drzewo 1 ma 0 źródeł).
- **Sprzątanie osób:** `DeletePerson` w jednej transakcji kasuje małżeństwa osoby i zdarzenia z jednym uczestnikiem, a relacje rodzic–dziecko kasuje klucz obcy (`DeletePerson.php`). Usunięcie wszystkich osób utworzonych przez test sprząta więc jego relacje. Zostają źródła, które trzeba usunąć osobno.
- **Sprzątanie przez HTTP:** `page.request` nie wysyła `Sec-Fetch-Site: same-origin` ani tokenu. `PreventRequestForgery` przepuszcza żądanie przy `same-origin` (`PreventRequestForgery.php:145-147`) albo przy zgodnym `X-CSRF-TOKEN` / `X-XSRF-TOKEN` (`:185-187`). Sprzątanie przez `page.request.delete` wymaga więc nagłówka `X-XSRF-TOKEN` z ciasteczka `XSRF-TOKEN` (zdekodowanego z URL). Tego nie uruchamiano.
- **Przygotowanie danych:** nie ma tras ani komend artisan tylko dla testów (`app/Console/Commands` zawiera tylko `RestoreBackup.php`). `TreeViewDemoSeeder` używa stałych e-maili i za każdym razem kasuje dane (`TreeViewDemoSeeder.php:61-80`), więc nie nadaje się do pracy równoległej. Są trzy opcje:
  1. UI, wolno: każda osoba to osobny dialog.
  2. `page.request.post` na istniejące trasy z tokenem XSRF: `persons.store`, `sources.store` (multipart), `events.sources.store`.
  3. `docker exec root-keeper-e2e php artisan tinker`: wiąże testy z Dockerem i Sail.

  Skill `10x-e2e` nie narzuca UI ani API. Granice wewnętrzne (auth, routing, DB) mają być prawdziwe (`10x-e2e/SKILL.md:240`, `:390`).
- **Rejestracja jest otwarta** (`config/fortify.php:164-173`). `User` nie implementuje `MustVerifyEmail` (`app/Models/User.php:5`, `:33`). Limit logowania to 5 na minutę na e-mail+IP (`FortifyServiceProvider.php:74-77`). Użytkownik na workera lub na specyfikację jest więc wykonalny, ale repozytorium nie ma dziś takiego wzorca. To wniosek, nie przetestowano go.

### 6. CI

- Workflowy `lint.yml`, `static-analysis.yml` i `tests.yml` nie mają joba E2E. `tests.yml:18-39` ma już usługę `postgres:18-alpine`. Joby używają `setup-php` i Node, nie Sail.
- `e2e-server.sh` zależy od `vendor/bin/sail`, `docker compose run laravel.test` i `.env`. W CI potrzebna byłaby gałąź natywna (pgsql z usługi, `composer setup`, `migrate`, utworzenie użytkownika, `php -S`) albo budowa obrazu Sail. Do tego dochodzą `npx playwright install --with-deps chromium`, sekrety `E2E_USERNAME` / `E2E_PASSWORD` i artefakty `playwright-report/`. To wniosek, nie próbowano tego.
- `test-plan.md:86` przewiduje, że bramka E2E będzie wymagana na PR po fazie 4. `roadmap.md:100` zostawia decyzję użytkownikowi („Block: no”).

## Code References

- `app/Actions/Trees/BuildFamilySheet.php:369-373` — pusty slot partnera tylko bez związków
- `app/Actions/Trees/BuildFamilySheet.php:390-438` — sloty rodziców i dziadków
- `resources/js/components/tree/parent-slot-dialog.tsx:21-99` — nazwy slotów rodzica, żądania istniejący/nowy
- `resources/js/components/tree/add-child-dialog.tsx:31-88` — slot dziecka, id z `sheet.add_child.parents`
- `resources/js/components/tree/add-spouse-dialog.tsx:46-100` — slot małżonka
- `resources/js/components/tree/union-node.tsx:79-111,171-182` — `choose()` i menu związku
- `resources/js/components/tree/sheet-person-card.tsx:57-79` — karta (Link przecentrowujący) i `Podgląd: {name}`
- `resources/js/components/persons/overview-tab.tsx:105-146` — fakty, `Otwórz źródło {name}`, `Powiąż źródło`
- `resources/js/components/persons/sources-tab.tsx:45-153` — zakładka Źródła
- `resources/js/components/sources/source-picker-dialog.tsx:57-134` — picker i żądanie powiązania
- `resources/js/components/sources/source-viewer.tsx:51-55` — `img src=/sources/{id}/file`
- `app/Actions/Sources/StoreSource.php:33-35` — ścieżka i zapis oryginału
- `app/Http/Controllers/SourceController.php:102-104,168-180` — przekierowanie po utworzeniu, strumień pliku
- `resources/js/components/side-panel.tsx:153-195` — role i nazwy powłoki panelu
- `resources/js/lib/side-panel-url.ts:6-50` — stan panelu w URL
- `app/Actions/Persons/DeletePerson.php` — kaskada przy usunięciu osoby
- `scripts/e2e-server.sh:16-34`, `playwright.config.ts:14-17` — baza `e2e`, jeden użytkownik, równoległość

## Architecture Insights

- Wzorzec „parametr URL → właściwość Inertia → częściowe przeładowanie” obejmuje `?person=`, `?spouse=`, panel F-02, `personCandidates` i `availableSources`. Każdy stan, który test musi ustawić, da się więc ustawić adresem, bez klikania przez kolejne widoki. Dzięki temu testy są tańsze i odporne na S-05 i S-06.
- Akcje zapisu kończą się `back()` albo przekierowaniem i ponownym zamontowaniem strony. Sygnałem gotowości jest zmiana treści, czyli zniknięcie slotu lub pojawienie się karty albo toastu, a nie odpowiedź sieciowa.
- Nazwy dostępności slotów i linków niosą imię osoby lub nazwę źródła. Unikalny sufiks w danych testu daje więc jednoznaczne lokatory bez `nth()` i bez `getByTestId`.

## Historical Context (from prior changes)

- `context/archive/2026-10-02-testing-write-delete-targets/research.md:61` — okno `choose()` ze starym `sheet`. Werdykt wspierany: kod `union-node.tsx:79-97` jest bez zmian. Nadal niepotwierdzone uruchomieniem.
- `context/archive/2026-10-02-testing-write-delete-targets/research.md:124` i `test-plan.md:200` — „bez e2e, dopóki Pest/Vitest nie pokażą luki”. Werdykt częściowo nieaktualny. Fakt o źródle id nadal się potwierdza (patrz §2). Wniosek „e2e niepotrzebne” zastąpiło nowe uzasadnienie z `prd.md:209` (ochrona przed przebudową).
- `context/archive/2026-10-04-side-panel-shell/plan.md:58`, `:237` — panel ma `role="dialog"` z nazwą, żeby lokatory F-01 przetrwały przejście modal → panel. Wspierane przez `side-panel.tsx:153-158`.
- `context/archive/2026-10-04-side-panel-shell/plan.md:41` — „pierwszy test E2E panelu powstaje z pierwszym konsumentem”. Dotyczy panelu, nie F-01.
- `context/archive/2026-10-04-side-panel-shell/reviews/impl-review.md:40-52` — `inert` dla strony za panelem poniżej `md` odłożono do pierwszego konsumenta. Jeśli zostanie dodane, lokatory drzewa przy wąskim viewporcie przestaną działać. Dziś E2E działa tylko na Desktop Chrome (`playwright.config.ts`).
- Brak zarchiwizowanej zmiany e2e-setup. Konfigurację opisuje wyłącznie `context/foundation/test-stack.md` (2026-10-02).

## Related Research

- `context/archive/2026-10-02-testing-write-delete-targets/research.md`
- `context/archive/2026-10-04-side-panel-shell/research.md`

## Open Questions

Wszystkie sześć pytań rozstrzygnięto 2026-10-04, patrz „Decyzje użytkownika” niżej. Lista zostaje jako zapis tego, co rozważano:

1. **CI teraz czy później?** `test-plan.md:86` mówi „wymagane po fazie 4”, a `roadmap.md:100` zostawia decyzję użytkownikowi. Job wymaga gałęzi `e2e-server` bez Sail.
2. **Izolacja danych:** współdzielony użytkownik z unikalnymi sufiksami i obowiązkowym sprzątaniem, czy użytkownik na workera z własnym `storageState`?
3. **Przygotowanie danych:** UI (wolne) czy `page.request` na istniejące trasy z tokenem XSRF? Przygotowanie przez API skraca test, ale samo przejście ścieżki musi zostać w UI.
4. **Budżet:** skill zaleca 1–3 testy na fazę (`10x-e2e/SKILL.md:230-245`), a ścieżka A ma 6 wariantów żądania. Zalecenie: dzielić na rodzaj relacji, nie na „istniejący/nowy”. Każdy test sprawdza oba wejścia jednego dialogu, bo dialog i lista kandydatów są wspólne.
5. **Okno `choose()`:** czy dziecko w drugim związku wchodzi do zakresu? To jedyny sprawdzony kandydat na błąd widoczny tylko w przeglądarce. Oczekiwanego zachowania nie potwierdzono.
6. **Porządek w `test-plan.md`:** zaktualizować §3 fazę 4 (status, uzasadnienie), §6.6 i §5 (bramka) po zakończeniu planu.

## Decyzje użytkownika (2026-10-04)

Rozstrzygają Open Questions 1–6. Każdą wybrał użytkownik spośród opcji z rekomendacją.

1. **CI:** testy F-01 uruchamiane lokalnie przed S-04 – S-06. Job CI to osobna, późniejsza zmiana. Zastępuje to oczekiwanie „required on PR po fazie 4” z `test-plan.md:86` na czas F-01.
2. **Izolacja:** wspólny użytkownik E2E (obecny `storageState`), unikalny sufiks w imionach i nazwach źródeł, wejście przez `/tree/{własneId}`, zakaz ustawiania probanta. Sprzątanie w `afterEach`/`finally` z asercją `toBeOK`.
3. **Przygotowanie danych:** przez `page.request` do istniejących tras (`persons.store`, `sources.store` multipart z fiksturą, `events.sources.store`) z nagłówkiem `X-XSRF-TOKEN`. Chroniona ścieżka (slot → dialog, fakt → źródło → skan) zostaje w UI.
4. **Budżet: 4 testy.** Po jednym na rodzica, dziecko i małżonka. Każdy sprawdza wejście „istniejąca osoba” i „nowa osoba” tego samego dialogu oraz relację z obu stron w podglądzie `?person=`. Czwarty test: fakt → źródło → skan z wyrocznią sha256.
5. **Okno `choose()`:** wchodzi do testu dziecka. Osoba w centrum ma dwa związki. Test przełącza na drugi, czeka na zmianę nazwy węzła związku, dodaje dziecko i sprawdza, że drugim rodzicem jest właściwy partner.
6. **Małżonek, oba wejścia:** pierwszy małżonek z pustego slotu (nowa osoba), drugi z menu węzła związku (istniejąca osoba).
7. **`test-plan.md`:** aktualizacja w ostatniej fazie planu F-01. Zakres: §3 faza 4 → `complete` z uzasadnieniem „ochrona przed przebudową” (`prd.md:209`), §6.6 wypełnione wzorcem z gotowych testów, §5 bramka „lokalnie przed S-04 – S-06, CI później”.
